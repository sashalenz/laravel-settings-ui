<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Resolution;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Per-group snapshots of resolved values.
 *
 * Two properties are load-bearing, and both exist because of one incident: a
 * deploy that relocated the settings model left every cached snapshot holding
 * objects of a class that no longer resolved, so they thawed as
 * `__PHP_Incomplete_Class` and fatalled on first property access. `cache:clear`
 * could not fix it, because clearing the cache boots the very provider that
 * crashes.
 *
 *  1. **Primitives only.** A payload is scalars, nulls and arrays of those.
 *     Validated on read, not merely promised — a payload that fails the check
 *     is treated as a miss and re-queried, so a poisoned entry self-heals
 *     instead of propagating.
 *
 *  2. **Version-stamped keys.** The key carries a cache-format version and a
 *     hash of the declared schema, so a deploy that changes declarations, or a
 *     package upgrade that changes the payload shape, cannot reach the old
 *     entry at all. Stale reads are unreachable rather than handled.
 */
final class SettingsCache
{
    /** Bump when the payload shape changes. Old entries become unreachable. */
    private const int FORMAT_VERSION = 1;

    private const string VERSION_KEY = 'version';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $prefix = 'settings',
        private readonly bool $enabled = true,
        private readonly ?int $ttl = null,
    ) {}

    /**
     * @param  Closure(): array<string, mixed>  $fresh
     * @return array<string, mixed>
     */
    public function remember(string $group, string $fingerprint, Closure $fresh): array
    {
        if (! $this->enabled) {
            return $fresh();
        }

        $key = $this->key($group, $fingerprint);
        $cached = $this->cache->get($key);

        if (is_array($cached) && $this->isStorable($cached)) {
            return $cached;
        }

        if ($cached !== null) {
            $this->cache->forget($key);
        }

        // Cache stampede protection using cache lock if supported
        $lockKey = "{$key}:lock";
        $lock = method_exists($this->cache, 'lock')
            ? $this->cache->lock($lockKey, 5)
            : null;

        if ($lock !== null) {
            try {
                $lock->block(3);
                // Double-check cache after acquiring lock
                $cached = $this->cache->get($key);
                if (is_array($cached) && $this->isStorable($cached)) {
                    return $cached;
                }
                $values = $fresh();
                $this->put($key, $values);

                return $values;
            } finally {
                optional($lock)->release();
            }
        }

        $values = $fresh();
        $this->put($key, $values);

        return $values;
    }

    public function forget(string $group, string $fingerprint): void
    {
        $this->cache->forget($this->key($group, $fingerprint));
    }

    /** @param  list<string>  $groups */
    public function flush(array $groups, string $fingerprint): void
    {
        foreach ($groups as $group) {
            $this->forget($group, $fingerprint);
        }
    }

    /**
     * A single monotonic stamp bumped on every write.
     *
     * Long-lived queue workers read this to decide whether their boot-time
     * overlay is still current — one cheap read per job, and re-applying only
     * when it actually moved.
     */
    public function version(): int
    {
        $version = $this->cache->get($this->versionKey());

        return is_numeric($version) ? (int) $version : 0;
    }

    public function bumpVersion(): void
    {
        $key = $this->versionKey();

        if ($this->cache->get($key) === null) {
            $this->cache->forever($key, 1);

            return;
        }

        $this->cache->increment($key);
    }

    public function key(string $group, string $fingerprint): string
    {
        return sprintf('%s:v%d:%s:%s', $this->prefix, self::FORMAT_VERSION, $fingerprint, $group);
    }

    private function versionKey(): string
    {
        return sprintf('%s:%s', $this->prefix, self::VERSION_KEY);
    }

    /** @param  array<string, mixed>  $values */
    private function put(string $key, array $values): void
    {
        if ($this->ttl === null) {
            $this->cache->forever($key, $values);

            return;
        }

        $this->cache->put($key, $values, $this->ttl);
    }

    /**
     * Scalars, nulls and arrays of those — nothing that carries a class name.
     *
     * This is the check that turns "we only cache primitives" from a convention
     * into a guarantee: an object reaching a payload by any route is caught on
     * the way out, not on the property access that would have fatalled.
     */
    private function isStorable(mixed $value): bool
    {
        if ($value === null || is_scalar($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! $this->isStorable($item)) {
                return false;
            }
        }

        return true;
    }
}
