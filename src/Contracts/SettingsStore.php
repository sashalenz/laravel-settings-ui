<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Contracts;

/**
 * Where a setting's value physically lives.
 *
 * Stores deal in RAW values only — whatever came out of the declaration's
 * serialize step. Encryption and type casting happen in the value pipeline
 * above, so `->encrypted()` composes with any store rather than needing a
 * store of its own.
 *
 * `load()` is the hot path: one call per root group per boot, batched. `get()`
 * exists for stores where batching is wrong — an external secret provider
 * should resolve lazily, per item, not fetch a group's worth of secrets to
 * answer one question.
 */
interface SettingsStore
{
    /**
     * Every stored value under a root group.
     *
     * Only paths that actually hold a value appear. A missing key is what lets
     * the overlay fall through to the host's config file default, so a store
     * must never pad the result with nulls.
     *
     * @return array<string, mixed> full dotted path => raw value
     */
    public function load(string $group): array;

    /** Raw value, or null when nothing is stored. */
    public function get(string $path): mixed;

    public function put(string $path, mixed $value): void;

    public function forget(string $path): void;

    /**
     * Paths this store holds that the given list does not contain — orphans
     * left behind when a declaration is deleted.
     *
     * @param  list<string>  $declaredPaths
     * @return list<string>
     */
    public function orphans(array $declaredPaths): array;

    public function writable(): bool;
}
