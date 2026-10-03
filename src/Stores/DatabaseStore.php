<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Stores;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use SashaLenz\SettingsUi\Contracts\SettingsStore;

/**
 * The default store: one row per stored value in the `settings` table.
 *
 * Deliberately a query builder and not an Eloquent model. Snapshots of what
 * this returns get cached, and the moment a model instance can reach a cache
 * payload, renaming or moving that class turns every cached blob into
 * `__PHP_Incomplete_Class` and fatals on first property access — the failure
 * that bricked a deploy in the app this package was extracted from. Rows in,
 * primitives out, nothing serializable-by-class in between.
 */
final class DatabaseStore implements SettingsStore
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'settings',
    ) {}

    public function load(string $group): array
    {
        $rows = $this->query()
            ->where('group', $group)
            ->get(['path', 'value']);

        $values = [];

        foreach ($rows as $row) {
            $values[(string) $row->path] = $this->decode($row->value);
        }

        return $values;
    }

    public function get(string $path): mixed
    {
        $value = $this->query()->where('path', $path)->value('value');

        return $value === null ? null : $this->decode($value);
    }

    public function put(string $path, mixed $value): void
    {
        $now = $this->connection->raw('CURRENT_TIMESTAMP');

        $this->query()->updateOrInsert(
            ['path' => $path],
            [
                'group' => $this->rootOf($path),
                'value' => $this->encode($value),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function forget(string $path): void
    {
        $this->query()->where('path', $path)->delete();
    }

    public function orphans(array $declaredPaths): array
    {
        $stored = $this->query()->pluck('path')->all();

        /** @var list<string> $orphans */
        $orphans = array_values(array_diff(
            array_map(strval(...), $stored),
            $declaredPaths,
        ));

        sort($orphans);

        return $orphans;
    }

    public function writable(): bool
    {
        return true;
    }

    /**
     * Deletes several paths at once. Used by `settings:sync --prune`, where the
     * list can be long and one statement beats N.
     *
     * @param  list<string>  $paths
     */
    public function forgetMany(array $paths): int
    {
        if ($paths === []) {
            return 0;
        }

        return $this->query()->whereIn('path', $paths)->delete();
    }

    private function query(): Builder
    {
        return $this->connection->table($this->table);
    }

    private function rootOf(string $path): string
    {
        return strtok($path, '.') ?: $path;
    }

    private function encode(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function decode(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        // Already decoded by a connection with a JSON-aware driver.
        if (! is_string($raw)) {
            return $raw;
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
