<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Schema;

/** A group node with its path, ordered children and depth resolved. */
final readonly class ResolvedGroup
{
    /**
     * @param  list<ResolvedField>  $fields  ordered
     * @param  list<self>  $groups  ordered
     */
    public function __construct(
        public string $path,
        public string $root,
        public ?string $parentPath,
        public int $depth,
        public Group $group,
        public array $fields,
        public array $groups,
        public string $source,
    ) {}

    public function isRoot(): bool
    {
        return $this->depth === 0;
    }

    /**
     * Fields in this node and every node beneath it.
     *
     * @return list<ResolvedField>
     */
    public function allFields(): array
    {
        $fields = $this->fields;

        foreach ($this->groups as $group) {
            foreach ($group->allFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function fieldCount(): int
    {
        return count($this->allFields());
    }
}
