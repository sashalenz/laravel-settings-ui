<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Import;

use Illuminate\Support\Str;
use SashaLenz\SettingsUi\Schema\FieldType;

/**
 * Turns a recovered tree into a declaration class.
 *
 * Labels and help text come out as translation KEYS, with the literals routed
 * to a companion lang file. That is deliberate: the flat table stored
 * user-facing Ukrainian strings inside PHP, which is exactly what the host's
 * own rules forbid, and doing the split here makes it mechanical instead of a
 * 95-row manual chore nobody would finish.
 */
final class DeclarationPrinter
{
    /** @var array<string, string> translation key => literal */
    private array $translations = [];

    public function __construct(
        private readonly string $namespace,
        private readonly string $langNamespace = 'settings',
        private readonly bool $literalLabels = false,
    ) {}

    public function className(string $root): string
    {
        return Str::studly(str_replace('-', '_', $root)).'Settings';
    }

    /** @return array<string, string> translation key => literal, from the last print run */
    public function translations(): array
    {
        return $this->translations;
    }

    public function resetTranslations(): void
    {
        $this->translations = [];
    }

    public function print(ImportNode $root): string
    {
        $class = $this->className($root->name);
        $body = $this->printGroup($root, 2, isRoot: true);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$this->namespace};

        use SashaLenz\\SettingsUi\\Contracts\\DeclaresSettings;
        use SashaLenz\\SettingsUi\\Schema\\Field;
        use SashaLenz\\SettingsUi\\Schema\\Group;

        /**
         * Generated from the legacy `setting_fields` table by `settings:import`.
         * Review before use: grouping, ordering and whether every knob here is
         * still read by anything.
         */
        final class {$class} implements DeclaresSettings
        {
            public function schema(): Group
            {
        {$body}
            }
        }

        PHP;
    }

    private function printGroup(ImportNode $node, int $depth, bool $isRoot = false): string
    {
        $pad = str_repeat('    ', $depth);
        $lines = [];

        $lines[] = $isRoot
            ? $pad.'return Group::make('.$this->quote($node->name).')'
            : $pad.'Group::make('.$this->quote($node->name).')';

        $lines[] = $pad.'    ->label('.$this->quote($this->translationKey($node->path, 'title', Str::headline($node->name))).')';

        if (! $isRoot) {
            $lines[] = $pad.'    ->order('.$node->order().')';
        }

        $rows = $node->orderedRows();

        if ($rows !== []) {
            $lines[] = $pad.'    ->fields([';

            foreach ($rows as $row) {
                $lines[] = $this->printField($row, $depth + 2);
            }

            $lines[] = $pad.'    ])';
        }

        $children = $node->orderedChildren();

        if ($children !== []) {
            $lines[] = $pad.'    ->groups([';

            foreach ($children as $child) {
                $lines[] = $this->printGroup($child, $depth + 2).',';
            }

            $lines[] = $pad.'    ])';
        }

        $source = implode(PHP_EOL, $lines);

        return $isRoot ? $source.';' : $source;
    }

    private function printField(LegacyRow $row, int $depth): string
    {
        $pad = str_repeat('    ', $depth);
        $leaf = (string) array_slice($row->segments(), -1)[0];

        $lines = [$pad.$this->maker($row, $leaf)];

        $lines[] = $pad.'    ->label('.$this->quote($this->translationKey($row->path(), 'label', $row->label ?? Str::headline($leaf))).')';

        if ($row->help !== null) {
            $lines[] = $pad.'    ->help('.$this->quote($this->translationKey($row->path(), 'help', $row->help)).')';
        }

        if ($row->rules !== null) {
            $lines[] = $pad.'    ->rules('.$this->quote($row->rules).')';
        }

        if ($row->options !== null && FieldType::tryFrom($row->type) === FieldType::Select) {
            $lines[] = $pad.'    ->options('.$this->export($row->options, $depth + 1).')';
        }

        if ($row->default !== null) {
            $lines[] = $pad.'    ->default('.$this->export($row->default, $depth + 1).')';
        }

        if ($row->isProviderBacked()) {
            $args = [$this->quote((string) $row->secretRef)];
            $args[] = $row->secretField !== null ? $this->quote($row->secretField) : 'null';

            if ($row->secretProvider !== null) {
                $args[] = $this->quote($row->secretProvider);
            }

            $lines[] = $pad.'    ->at('.implode(', ', $args).')';
        } elseif ($row->encrypted) {
            $lines[] = $pad.'    ->encrypted()';
        }

        $lines[] = $pad.'    ->order('.$row->order.'),';

        return implode(PHP_EOL, $lines);
    }

    private function maker(LegacyRow $row, string $leaf): string
    {
        $key = $this->quote($leaf);
        $model = $row->modelClass !== null ? '\\'.ltrim($row->modelClass, '\\').'::class' : null;

        return match (FieldType::tryFrom($row->type)) {
            FieldType::Text => "Field::text({$key})",
            FieldType::Textarea => "Field::textarea({$key})",
            FieldType::Boolean => "Field::boolean({$key})",
            FieldType::Integer => "Field::integer({$key})",
            FieldType::Decimal => "Field::decimal({$key})",
            FieldType::Money => "Field::money({$key})",
            FieldType::Select => "Field::select({$key})",
            FieldType::Date => "Field::date({$key})",
            FieldType::Datetime => "Field::datetime({$key})",
            FieldType::Phone => "Field::phone({$key})",
            FieldType::KeyValue => "Field::keyValue({$key})",
            FieldType::Model => sprintf('Field::model(%s, %s)', $key, $model ?? "''"),
            FieldType::Tree => sprintf('Field::tree(%s, %s)', $key, $model ?? "''"),
            null => sprintf('Field::custom(%s, %s)', $key, $this->quote($row->type)),
        };
    }

    /**
     * Register a literal under a translation key and return whichever of the
     * two the caller wants emitted.
     */
    private function translationKey(string $path, string $suffix, string $literal): string
    {
        if ($this->literalLabels) {
            return $literal;
        }

        $key = sprintf('%s.%s.%s', $this->langNamespace, $path, $suffix);

        $this->translations[$key] = $literal;

        return $key;
    }

    private function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    private function export(mixed $value, int $depth): string
    {
        $exported = var_export($value, true);
        $exported = preg_replace('/array \(/', '[', $exported) ?? $exported;
        $exported = preg_replace('/^(\s*)\)/m', '$1]', $exported) ?? $exported;
        $exported = (string) preg_replace('/=>\s*\n\s*\[/', '=> [', $exported);

        $pad = str_repeat('    ', $depth);

        return implode(PHP_EOL.$pad, explode(PHP_EOL, $exported));
    }
}
