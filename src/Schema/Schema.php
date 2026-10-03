<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Schema;

/**
 * The whole declared tree, resolved and indexed.
 *
 * Built once per request from the registry and treated as immutable. The flat
 * `$fields` index is what the resolver and the stores work against; the
 * `$roots` tree is what the UI renders.
 */
final readonly class Schema
{
    /**
     * @param  array<string, ResolvedGroup>  $roots  root name => tree, ordered
     * @param  array<string, ResolvedField>  $fields  full path => field
     */
    public function __construct(
        public array $roots,
        public array $fields,
    ) {}

    public static function empty(): self
    {
        return new self([], []);
    }

    public function root(string $name): ?ResolvedGroup
    {
        return $this->roots[$name] ?? null;
    }

    public function field(string $path): ?ResolvedField
    {
        return $this->fields[$path] ?? null;
    }

    public function has(string $path): bool
    {
        return isset($this->fields[$path]);
    }

    /** @return list<string> */
    public function rootNames(): array
    {
        return array_keys($this->roots);
    }

    /** @return list<string> */
    public function paths(): array
    {
        return array_keys($this->fields);
    }

    /** @return array<string, ResolvedField> path => field, for one root group */
    public function fieldsIn(string $root): array
    {
        return array_filter(
            $this->fields,
            static fn (ResolvedField $field): bool => $field->root === $root,
        );
    }

    /**
     * Stable fingerprint of the declared structure.
     *
     * Cache keys carry this, so a deploy that adds, removes, re-types or
     * re-homes a setting invalidates every snapshot by itself — there is no
     * window in which a cached payload can describe a schema that no longer
     * exists. Values are deliberately NOT part of it: a value change busts the
     * cache through its own write path.
     */
    public function fingerprint(): string
    {
        $parts = [];

        foreach ($this->fields as $path => $field) {
            $parts[] = implode(':', [
                $path,
                $field->field->type,
                $field->field->store ?? '-',
                $field->field->encrypted ? 'e' : '-',
                $field->field->secretRef ?? '-',
            ]);
        }

        sort($parts);

        return substr(hash('xxh128', implode('|', $parts)), 0, 16);
    }
}
