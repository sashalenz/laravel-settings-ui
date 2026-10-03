<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Stores;

use SashaLenz\SettingsUi\Contracts\SettingsStore;

/** In-memory store. Tests, and any host that wants settings without persistence. */
final class ArrayStore implements SettingsStore
{
    /** @param  array<string, mixed>  $values path => raw value */
    public function __construct(private array $values = []) {}

    public function load(string $group): array
    {
        $prefix = $group.'.';

        return array_filter(
            $this->values,
            static fn (string $path): bool => str_starts_with($path, $prefix),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function get(string $path): mixed
    {
        return $this->values[$path] ?? null;
    }

    public function put(string $path, mixed $value): void
    {
        $this->values[$path] = $value;
    }

    public function forget(string $path): void
    {
        unset($this->values[$path]);
    }

    public function orphans(array $declaredPaths): array
    {
        /** @var list<string> $orphans */
        $orphans = array_values(array_diff(array_keys($this->values), $declaredPaths));

        sort($orphans);

        return $orphans;
    }

    public function writable(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    public function flush(): void
    {
        $this->values = [];
    }
}
