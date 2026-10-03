<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Import;

use Illuminate\Database\ConnectionInterface;
use SashaLenz\SettingsUi\Schema\FieldType;

/**
 * Reads a legacy flat settings table and recovers the tree hiding in its keys.
 *
 * Runs against the live table on purpose. A generator that only handles the
 * rows someone remembered to declare would prove nothing; the point is to
 * discover what the schema model must actually cope with — including the rows
 * that were seeded by hand over the years and never declared anywhere.
 */
final class LegacyImporter
{
    /** @var list<array{level: string, path: string, message: string}> */
    private array $warnings = [];

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'setting_fields',
    ) {}

    /**
     * @param  list<string>  $only  restrict to these root groups
     * @return array<string, ImportNode> root name => tree
     */
    public function import(array $only = []): array
    {
        $this->warnings = [];

        $roots = [];

        foreach ($this->rows($only) as $row) {
            $root = $roots[$row->group] ??= new ImportNode($row->group, $row->group);

            $segments = $row->segments();
            $leaf = array_pop($segments);

            $node = $root;

            foreach ($segments as $segment) {
                $node = $node->child($segment);
            }

            $this->guardLeafAgainstGroup($node, $leaf, $row);

            $node->add($row);
        }

        $this->guardGroupsAgainstFields($roots);

        return $roots;
    }

    /** @return list<array{level: string, path: string, message: string}> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param  list<string>  $only
     * @return list<LegacyRow>
     */
    private function rows(array $only): array
    {
        $query = $this->connection->table($this->table)->orderBy('group')->orderBy('sort_order')->orderBy('id');

        if ($only !== []) {
            $query->whereIn('group', $only);
        }

        $rows = [];

        foreach ($query->get() as $record) {
            $rows[] = $this->normalise((array) $record);
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $record */
    private function normalise(array $record): LegacyRow
    {
        $path = sprintf('%s.%s', $record['group'], $record['key']);
        $type = (string) $record['type'];

        if (FieldType::tryFrom($type) === null) {
            $this->warn('error', $path, sprintf(
                'Unknown type "%s" — the generated declaration uses Field::custom() and will need a registered type.',
                $type,
            ));
        }

        $encrypted = (bool) ($record['is_encrypted'] ?? false);
        $secretRef = $this->nullableString($record['secret_ref'] ?? null);

        if ($encrypted && $secretRef !== null) {
            // The two secret mechanisms are documented as mutually exclusive.
            // Resolve it the way the runtime already does — the provider ref is
            // checked first, so the encrypted flag never had any effect — and
            // say so, because the declaration is where it stops being silent.
            $this->warn('warning', $path, 'Marked both encrypted and provider-backed. '
                .'Importing as provider-backed only: the resolver checks the secret ref first, '
                .'so the encrypted flag was already dead — but a re-seed keeps re-asserting it.');

            $encrypted = false;
        }

        if ($record['default_value'] !== null && $record['value'] === null) {
            // Under the legacy resolver `value ?? default_value` made a stored
            // default win over the host's config file. Under the new precedence
            // the file wins, so such a row would change value on cutover.
            $this->warn('warning', $path, 'Has default_value but no value. Legacy resolution let '
                .'default_value beat the config file; the new precedence does not. Verify this path in the cutover diff.');
        }

        return new LegacyRow(
            group: (string) $record['group'],
            key: (string) $record['key'],
            type: $type,
            label: $this->nullableString($record['label'] ?? null),
            help: $this->nullableString($record['help'] ?? null),
            rules: $this->nullableString($record['rules'] ?? null),
            default: $this->decode($record['default_value'] ?? null),
            value: $this->decode($record['value'] ?? null),
            options: $this->decodeOptions($this->decode($record['options'] ?? null)),
            modelClass: $this->nullableString($record['model_class'] ?? null),
            encrypted: $encrypted,
            secretProvider: $this->nullableString($record['secret_provider'] ?? null),
            secretRef: $secretRef,
            secretField: $this->nullableString($record['secret_field'] ?? null),
            order: (int) ($record['sort_order'] ?? 0),
        );
    }

    /**
     * A leaf cannot share a name with a group at the same level — `a.b` as a
     * field and `a.b.c` as another would need `a.b` to be two things at once.
     */
    private function guardLeafAgainstGroup(ImportNode $node, string $leaf, LegacyRow $row): void
    {
        if (isset($node->children[$leaf])) {
            $this->warn('error', $row->path(), sprintf(
                'Key "%s" is a field here but also a path prefix for other keys. One of them must be renamed.',
                $leaf,
            ));
        }
    }

    /** @param  array<string, ImportNode>  $roots */
    private function guardGroupsAgainstFields(array $roots): void
    {
        foreach ($roots as $root) {
            $this->walkForCollisions($root);
        }
    }

    private function walkForCollisions(ImportNode $node): void
    {
        $fieldNames = array_map(
            static fn (LegacyRow $row): string => (string) array_slice($row->segments(), -1)[0],
            $node->rows,
        );

        foreach (array_keys($node->children) as $childName) {
            if (in_array($childName, $fieldNames, true)) {
                $this->warn('error', $node->path.'.'.$childName, 'Declared as both a group and a field.');
            }
        }

        foreach ($node->children as $child) {
            $this->walkForCollisions($child);
        }
    }

    private function warn(string $level, string $path, string $message): void
    {
        $this->warnings[] = ['level' => $level, 'path' => $path, 'message' => $message];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    private function decode(mixed $raw): mixed
    {
        if ($raw === null || ! is_string($raw)) {
            return $raw;
        }

        return json_decode($raw, true);
    }

    /** @return array<string, string>|null */
    private function decodeOptions(mixed $decoded): ?array
    {
        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        $options = [];

        foreach ($decoded as $key => $label) {
            $options[(string) $key] = (string) $label;
        }

        return $options;
    }
}
