<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Contracts;

/**
 * High-level repository contract for reading and writing settings.
 */
interface SettingsRepositoryInterface
{
    /**
     * Get a setting by dot-notation path with an optional fallback.
     */
    public function get(string $path, mixed $default = null): mixed;

    /**
     * Set a setting value by dot-notation path.
     */
    public function set(string $path, mixed $value): void;

    /**
     * Determine if a setting exists in the declared schema or store.
     */
    public function has(string $path): bool;

    /**
     * Get all resolved settings under a root group.
     *
     * @return array<string, mixed>
     */
    public function all(string $group): array;

    /**
     * Forget/delete a stored setting value.
     */
    public function forget(string $path): void;
}
