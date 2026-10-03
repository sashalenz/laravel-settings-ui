<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Stores;

use SashaLenz\SettingsUi\Contracts\SettingsStore;
use SashaLenz\SettingsUi\Exceptions\StoreNotWritableException;

/**
 * Reads nothing, writes nothing — every setting resolves to its file default.
 *
 * For locked-down environments where runtime overrides are not allowed, and for
 * a host that wants the declaration and validation machinery without any
 * settings UI. A write is a hard error rather than a silent no-op: an operator
 * being told "saved" when nothing was is worse than a failure.
 */
final class NullStore implements SettingsStore
{
    public function load(string $group): array
    {
        return [];
    }

    public function get(string $path): mixed
    {
        return null;
    }

    public function put(string $path, mixed $value): void
    {
        throw StoreNotWritableException::for('null', $path);
    }

    public function forget(string $path): void
    {
        // Nothing is stored, so forgetting always succeeds.
    }

    public function orphans(array $declaredPaths): array
    {
        return [];
    }

    public function writable(): bool
    {
        return false;
    }
}
