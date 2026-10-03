<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Stores;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use SashaLenz\SettingsUi\Contracts\SecretStore;
use SashaLenz\SettingsUi\Contracts\SettingsStore;
use SashaLenz\SettingsUi\Exceptions\SecretMissingException;
use SashaLenz\SettingsUi\Exceptions\SecretNotLocatedException;
use SashaLenz\SettingsUi\Exceptions\SecretStoreNotBoundException;
use SashaLenz\SettingsUi\Exceptions\SecretUnavailableException;
use SashaLenz\SettingsUi\Secrets\SecretBindings;
use SashaLenz\SettingsUi\Secrets\SecretLocation;
use SashaLenz\SettingsUi\SettingsManager;
use Throwable;

/**
 * Reads and writes values through an external secret backend.
 *
 * **Lazy, never batched.** `load()` returns nothing on purpose: pulling a
 * group's worth of secrets so one of them can be read would multiply provider
 * calls, and — worse — those values would land in a snapshot shared across
 * processes. Secrets are fetched one at a time, when asked for.
 *
 * **A secrets outage never takes the app down.** A transient failure resolves
 * to null so the consumer falls back to its file default; a missing item is
 * reported (it is a config bug, not weather) but still resolves to null.
 * Neither throws into a request.
 *
 * **Misses are cached too.** Not doing so makes the broken path the expensive
 * one: the overlay asks for every provider-backed field on every boot, so a
 * single dangling ref becomes a provider round-trip and an error report on
 * every request until someone fixes it. Only the stable failures are
 * remembered — a provider outage is not, so recovery stays immediate.
 */
final class SecretsStore implements SettingsStore
{
    public function __construct(
        private readonly Container $container,
        private readonly SettingsManager $settings,
        private readonly SecretBindings $bindings,
        private readonly ?CacheRepository $cache = null,
        private readonly int $ttl = 60,
        private readonly string $cachePrefix = 'settings:secret',
    ) {}

    public function load(string $group): array
    {
        return [];
    }

    public function get(string $path): mixed
    {
        $location = $this->locate($path);

        if ($location === null) {
            return null;
        }

        $key = $this->cacheKey($path);

        if ($this->cache !== null) {
            $cached = $this->cache->get($key);

            if (is_string($cached)) {
                return $cached;
            }

            // A remembered miss. Absence has to be cached as deliberately as
            // presence: the overlay resolves every provider-backed field on
            // every boot, so one dangling ref that is re-asked each time costs
            // a provider round-trip *and* a report per request, indefinitely.
            if ($cached === false) {
                return null;
            }
        }

        try {
            $value = $this->backend()->get($location);
        } catch (SecretUnavailableException) {
            // Weather. The consumer falls back to its file default and the app
            // keeps serving; alerting on this would be noise. Deliberately NOT
            // remembered — the value must return the moment the provider does.
            return null;
        } catch (SecretMissingException $e) {
            // A configuration bug: someone bound a path to a location that
            // isn't there. Surface it, but still don't break the request — and
            // remember it, so it is surfaced once a TTL rather than once a hit.
            report($e);

            $this->remember($key, false);

            return null;
        } catch (SecretStoreNotBoundException $e) {
            // No provider was called, so there is no round-trip to spare, and
            // the binding may well appear later in this same process.
            report($e);

            return null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        // A null answer is a miss the provider chose not to raise on; it is
        // just as stable as a raised one, so it is remembered the same way.
        $this->remember($key, $value ?? false);

        return $value;
    }

    public function put(string $path, mixed $value): void
    {
        $location = $this->locate($path) ?? throw SecretNotLocatedException::path($path);

        $this->backend()->put($location, (string) $value);

        // Drop the short-TTL copy so the next read reflects the write instead
        // of serving the previous secret for up to a minute.
        $this->cache?->forget($this->cacheKey($path));
    }

    public function forget(string $path): void
    {
        $this->cache?->forget($this->cacheKey($path));

        $location = $this->locate($path);

        if ($location === null) {
            return;
        }

        $this->backend()->forget($location);
    }

    public function orphans(array $declaredPaths): array
    {
        // An external store holds far more than this app's settings, so
        // "everything here that we no longer declare" is not ours to compute —
        // and certainly not ours to offer for deletion.
        return [];
    }

    public function writable(): bool
    {
        return true;
    }

    private function locate(string $path): ?SecretLocation
    {
        $field = $this->settings->field($path);

        return $field === null ? null : $this->bindings->locate($field);
    }

    private function backend(): SecretStore
    {
        if (! $this->container->bound(SecretStore::class)) {
            throw SecretStoreNotBoundException::make();
        }

        return $this->container->make(SecretStore::class);
    }

    private function cacheKey(string $path): string
    {
        return $this->cachePrefix.':'.sha1($path);
    }

    /**
     * `false` is the miss marker. It cannot collide with a real secret, which
     * is always a string, so the three states stay distinguishable: a string is
     * a hit, `false` is a known miss, and absent means not yet asked.
     */
    private function remember(string $key, string|false $value): void
    {
        $this->cache?->put($key, $value, $this->ttl);
    }
}
