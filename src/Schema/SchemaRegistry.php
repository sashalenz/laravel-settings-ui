<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Schema;

use Illuminate\Contracts\Container\Container;
use SashaLenz\SettingsUi\Contracts\DeclaresSettings;
use SashaLenz\SettingsUi\Exceptions\SchemaConflictException;

/**
 * Collects declarations and compiles them into one resolved {@see Schema}.
 *
 * Declaring is cheap — a class name goes on a list. Nothing is instantiated or
 * validated until the first schema access, so a provider that declares during
 * `register()` costs nothing on requests that never touch settings.
 *
 * Root groups of the same name MERGE rather than overwrite, so an app can add
 * fields to a group a package introduced. What does not merge is a duplicated
 * field path: that throws, because silently resolving it last-wins is how an
 * auto-discovered package would shadow a host's own setting.
 */
final class SchemaRegistry
{
    /** @var list<array{source: string, schema: class-string<DeclaresSettings>|DeclaresSettings|Group}> */
    private array $declarations = [];

    private ?Schema $compiled = null;

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<DeclaresSettings>|DeclaresSettings|Group  $schema
     * @param  string|null  $source  provenance label; defaults to the class name
     */
    public function declare(string|DeclaresSettings|Group $schema, ?string $source = null): void
    {
        $this->declarations[] = [
            'source' => $source ?? $this->describe($schema),
            'schema' => $schema,
        ];

        $this->compiled = null;
    }

    /** Drop everything. Tests, and `settings:sync` rebuilding in-process. */
    public function flush(): void
    {
        $this->declarations = [];
        $this->compiled = null;
    }

    public function schema(): Schema
    {
        return $this->compiled ??= $this->compile();
    }

    /** @return list<array{source: string, root: string}> */
    public function provenance(): array
    {
        $out = [];

        foreach ($this->declarations as $declaration) {
            $out[] = [
                'source' => $declaration['source'],
                'root' => $this->resolveGroup($declaration['schema'])->name,
            ];
        }

        return $out;
    }

    // --------------------------------------------------------------- compile

    private function compile(): Schema
    {
        /** @var array<string, Group> $merged */
        $merged = [];
        /** @var array<string, string> $sources root => first declaring source */
        $sources = [];

        foreach ($this->declarations as $declaration) {
            $group = $this->resolveGroup($declaration['schema']);

            $merged[$group->name] = isset($merged[$group->name])
                ? $this->mergeGroups($merged[$group->name], $group)
                : $group;

            $sources[$group->name] ??= $declaration['source'];
        }

        /** @var array<string, ResolvedField> $fields */
        $fields = [];
        /** @var array<string, string> $groupPaths path => source, for collision detection */
        $groupPaths = [];
        $roots = [];

        foreach ($this->sortGroups(array_values($merged)) as $root) {
            $roots[$root->name] = $this->resolveGroupNode(
                group: $root,
                path: $root->name,
                root: $root->name,
                parentPath: null,
                depth: 0,
                source: $sources[$root->name],
                fields: $fields,
                groupPaths: $groupPaths,
            );
        }

        return new Schema($roots, $fields);
    }

    /**
     * @param  array<string, ResolvedField>  $fields
     * @param  array<string, string>  $groupPaths
     */
    private function resolveGroupNode(
        Group $group,
        string $path,
        string $root,
        ?string $parentPath,
        int $depth,
        string $source,
        array &$fields,
        array &$groupPaths,
    ): ResolvedGroup {
        if (isset($fields[$path])) {
            throw SchemaConflictException::fieldGroupCollision($path, $source);
        }

        $groupPaths[$path] = $source;

        $resolvedFields = [];

        foreach ($this->sortFields($group->fields) as $field) {
            $fieldPath = $path.'.'.$field->key;

            if (isset($fields[$fieldPath])) {
                throw SchemaConflictException::duplicateField(
                    $fieldPath,
                    $fields[$fieldPath]->source,
                    $source,
                );
            }

            if (isset($groupPaths[$fieldPath])) {
                throw SchemaConflictException::fieldGroupCollision($fieldPath, $source);
            }

            $resolved = new ResolvedField(
                path: $fieldPath,
                root: $root,
                parentPath: $path,
                field: $field,
                source: $source,
            );

            $fields[$fieldPath] = $resolved;
            $resolvedFields[] = $resolved;
        }

        $resolvedGroups = [];

        foreach ($this->sortGroups($group->groups) as $child) {
            $resolvedGroups[] = $this->resolveGroupNode(
                group: $child,
                path: $path.'.'.$child->name,
                root: $root,
                parentPath: $path,
                depth: $depth + 1,
                source: $source,
                fields: $fields,
                groupPaths: $groupPaths,
            );
        }

        return new ResolvedGroup(
            path: $path,
            root: $root,
            parentPath: $parentPath,
            depth: $depth,
            group: $group,
            fields: $resolvedFields,
            groups: $resolvedGroups,
            source: $source,
        );
    }

    /**
     * Union two declarations of the same group name. Metadata is first-wins so
     * an app adding fields to a package's group can't accidentally relabel it;
     * children merge recursively by name.
     */
    private function mergeGroups(Group $base, Group $incoming): Group
    {
        $merged = Group::make($base->name);

        foreach (['label', 'description', 'icon'] as $property) {
            $value = $base->{$property} ?? $incoming->{$property};

            if ($value !== null) {
                $merged->{$property}($value);
            }
        }

        $order = $base->order ?? $incoming->order;

        if ($order !== null) {
            $merged->order($order);
        }

        if ($base->wide || $incoming->wide) {
            $merged->wide();
        }

        $ability = $base->ability ?? $incoming->ability;

        if ($ability !== null) {
            $merged->authorize($ability);
        }

        $condition = $base->visibleWhen ?? $incoming->visibleWhen;

        if ($condition !== null) {
            $merged->when($condition);
        }

        $merged->fields([...$base->fields, ...$incoming->fields]);

        /** @var array<string, Group> $children */
        $children = [];

        foreach ([...$base->groups, ...$incoming->groups] as $child) {
            $children[$child->name] = isset($children[$child->name])
                ? $this->mergeGroups($children[$child->name], $child)
                : $child;
        }

        return $merged->groups(array_values($children));
    }

    /**
     * @param  list<Group>  $groups
     * @return list<Group>
     */
    private function sortGroups(array $groups): array
    {
        return $this->sortByOrder($groups);
    }

    /**
     * @param  list<Field>  $fields
     * @return list<Field>
     */
    private function sortFields(array $fields): array
    {
        return $this->sortByOrder($fields);
    }

    /**
     * Anything with an explicit `->order()` comes first, by that value;
     * everything else follows in declaration order. Ties break on declaration
     * position, so the sort is stable and reproducible.
     *
     * The tempting rule — `order ?? declarationIndex` — reads as if it merges
     * the two into one sequence, but it silently mixes two number spaces: with
     * three fields declared c, a, b, an `->order(0)` on `a` ties with `c`'s
     * implicit index 0 and then LOSES the tie-break, so the one thing
     * `->order()` is normally reached for ("put this at the top") quietly does
     * nothing. Explicit-first has no such corner.
     *
     * Merged groups are the case that makes this matter: when two packages
     * contribute to one root group, declaration order between them is an
     * accident of provider boot order, and `->order()` is the only way to pin
     * a position.
     *
     * @template T of Field|Group
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function sortByOrder(array $items): array
    {
        $ordered = [];
        $unordered = [];

        foreach ($items as $index => $item) {
            if ($item->order === null) {
                $unordered[] = [$index, $item];

                continue;
            }

            $ordered[] = [$item->order, $index, $item];
        }

        usort($ordered, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return [
            ...array_map(static fn (array $row) => $row[2], $ordered),
            ...array_map(static fn (array $row) => $row[1], $unordered),
        ];
    }

    /** @param  class-string<DeclaresSettings>|DeclaresSettings|Group  $schema */
    private function resolveGroup(string|DeclaresSettings|Group $schema): Group
    {
        if ($schema instanceof Group) {
            return $schema;
        }

        if (is_string($schema)) {
            /** @var DeclaresSettings $schema */
            $schema = $this->container->make($schema);
        }

        return $schema->schema();
    }

    /** @param  class-string<DeclaresSettings>|DeclaresSettings|Group  $schema */
    private function describe(string|DeclaresSettings|Group $schema): string
    {
        return match (true) {
            is_string($schema) => $schema,
            $schema instanceof Group => sprintf('Group("%s")', $schema->name),
            default => $schema::class,
        };
    }
}
