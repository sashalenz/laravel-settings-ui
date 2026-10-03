<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Projection;

use Illuminate\Database\ConnectionInterface;
use SashaLenz\SettingsUi\Schema\ResolvedGroup;
use SashaLenz\SettingsUi\SettingsManager;

/**
 * Rewrites the `setting_schema` projection from the declarations.
 *
 * Wholesale replace, not diff-and-patch. The table contains no operator data,
 * so there is nothing to preserve and nothing to get wrong — and "rebuild it"
 * is a far easier property to reason about than "keep it in sync". It is also
 * why a missed `settings:sync` is a cosmetic bug rather than an outage.
 */
final class SchemaProjector
{
    public function __construct(
        private readonly SettingsManager $settings,
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'setting_schema',
    ) {}

    /** @return array{groups: int, fields: int} */
    public function project(): array
    {
        $rows = [];

        foreach ($this->settings->schema()->roots as $root) {
            $this->collect($root, $rows);
        }

        $this->connection->transaction(function () use ($rows): void {
            $this->connection->table($this->table)->delete();

            foreach (array_chunk($rows, 200) as $chunk) {
                $this->connection->table($this->table)->insert($chunk);
            }
        });

        return [
            'groups' => count(array_filter($rows, static fn (array $row): bool => (bool) $row['is_group'])),
            'fields' => count(array_filter($rows, static fn (array $row): bool => ! $row['is_group'])),
        ];
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function collect(ResolvedGroup $group, array &$rows): void
    {
        $now = $this->connection->raw('CURRENT_TIMESTAMP');

        $rows[] = [
            'path' => $group->path,
            'root' => $group->root,
            'parent_path' => $group->parentPath,
            'is_group' => true,
            'depth' => $group->depth,
            'type' => null,
            'store' => null,
            'label_key' => $group->group->label,
            'icon' => $group->group->icon,
            'is_secret' => false,
            'sort_order' => $group->group->order ?? 0,
            'field_count' => $group->fieldCount(),
            'source' => $group->source,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ($group->fields as $field) {
            $rows[] = [
                'path' => $field->path,
                'root' => $field->root,
                'parent_path' => $field->parentPath,
                'is_group' => false,
                'depth' => $group->depth + 1,
                'type' => $field->field->type,
                'store' => $field->field->store,
                'label_key' => $field->field->label,
                'icon' => null,
                'is_secret' => $field->field->secret,
                'sort_order' => $field->field->order ?? 0,
                'field_count' => 0,
                'source' => $field->source,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($group->groups as $child) {
            $this->collect($child, $rows);
        }
    }
}
