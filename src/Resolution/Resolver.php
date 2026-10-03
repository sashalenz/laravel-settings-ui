<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Resolution;

use SashaLenz\SettingsUi\Exceptions\UndeclaredSettingException;
use SashaLenz\SettingsUi\Schema\ResolvedField;
use SashaLenz\SettingsUi\SettingsManager;
use SashaLenz\SettingsUi\Stores\StoreManager;

/**
 * Reads stored values through their stores and the value pipeline.
 *
 * The distinction this class exists to preserve: **a stored value and a
 * defaulted value are not the same thing.** `snapshot()` returns only what is
 * actually stored, which is what lets the overlay leave a valueless setting
 * alone so the host's `config/{group}.php` default stands. Collapse the two and
 * you silently overwrite every file default with a declaration default.
 *
 * Snapshots are cached per group, primitives only — see {@see SettingsCache}.
 */
final class Resolver
{
    /** @var array<string, array<string, mixed>> per-request memo, group => path => value */
    private array $memo = [];

    public function __construct(
        private readonly SettingsManager $settings,
        private readonly StoreManager $stores,
        private readonly ValuePipeline $pipeline,
        private readonly SettingsCache $cache,
    ) {}

    /**
     * Stored values for one root group, hydrated and cast.
     *
     * Only paths with something actually stored appear. Provider-backed fields
     * are excluded on purpose: their values are fetched lazily, one at a time,
     * and must never be baked into a shared snapshot.
     *
     * @return array<string, mixed>
     */
    public function snapshot(string $group): array
    {
        return $this->memo[$group] ??= $this->cache->remember(
            $group,
            $this->settings->schema()->fingerprint(),
            fn (): array => $this->load($group),
        );
    }

    /**
     * One value: stored if present, otherwise the caller's fallback.
     *
     * Note this does NOT consult `config()`. During boot the overlay has not
     * run yet, so a config read here would return the file default and hide
     * whether anything is stored — the very distinction callers need.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $field = $this->settings->field($path);

        if ($field === null) {
            return $default;
        }

        if ($field->field->isProviderBacked()) {
            return $this->resolveLazily($field) ?? $default;
        }

        $snapshot = $this->snapshot($field->root);

        return array_key_exists($path, $snapshot) ? $snapshot[$path] : $default;
    }

    public function has(string $path): bool
    {
        $field = $this->settings->field($path);

        if ($field === null) {
            return false;
        }

        return array_key_exists($path, $this->snapshot($field->root));
    }

    /** Persist a value through the declaring field's store. */
    public function put(string $path, mixed $value): void
    {
        $field = $this->settings->field($path)
            ?? throw UndeclaredSettingException::path($path);

        $store = $this->stores->store($field->storeName($this->stores->defaultName()));

        if ($value === null) {
            $store->forget($path);
        } else {
            $store->put($path, $this->pipeline->dehydrate($value, $field->field));
        }

        $this->forget($field->root);
    }

    public function forget(string $group): void
    {
        unset($this->memo[$group]);

        $this->cache->forget($group, $this->settings->schema()->fingerprint());
        $this->cache->bumpVersion();
    }

    public function forgetAll(): void
    {
        $this->memo = [];

        $this->cache->flush($this->settings->groups(), $this->settings->schema()->fingerprint());
        $this->cache->bumpVersion();
    }

    public function version(): int
    {
        return $this->cache->version();
    }

    /**
     * Batched read of one group, grouped by store so each store is hit once
     * rather than once per field.
     *
     * @return array<string, mixed>
     */
    private function load(string $group): array
    {
        $fields = $this->settings->schema()->fieldsIn($group);
        $default = $this->stores->defaultName();

        /** @var array<string, array<string, mixed>> $rawByStore */
        $rawByStore = [];
        $values = [];

        foreach ($fields as $path => $field) {
            // Lazy by contract — a secret is fetched when asked for, never
            // pulled in bulk into a snapshot other processes share.
            if ($field->field->isProviderBacked()) {
                continue;
            }

            $storeName = $field->storeName($default);
            $rawByStore[$storeName] ??= $this->stores->store($storeName)->load($group);

            if (! array_key_exists($path, $rawByStore[$storeName])) {
                continue;
            }

            $hydrated = $this->pipeline->hydrate($rawByStore[$storeName][$path], $field->field);

            // Unreadable ciphertext hydrates to null. Dropping the key rather
            // than storing null keeps "stored" meaning "usable", so the file
            // default still surfaces while the operator re-enters the secret.
            if ($hydrated === null) {
                continue;
            }

            $values[$path] = $hydrated;
        }

        return $values;
    }

    private function resolveLazily(ResolvedField $field): mixed
    {
        $store = $this->stores->store($field->storeName($this->stores->defaultName()));

        return $this->pipeline->hydrate($store->get($field->path), $field->field);
    }
}
