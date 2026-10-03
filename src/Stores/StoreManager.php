<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Stores;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;
use SashaLenz\SettingsUi\Contracts\SettingsStore;
use SashaLenz\SettingsUi\Exceptions\UnknownStoreException;
use SashaLenz\SettingsUi\Secrets\SecretBindings;
use SashaLenz\SettingsUi\SettingsManager;

/**
 * Resolves store names to instances, one instance per name per request.
 *
 * Drivers are extensible: a host registers its own with `extend()`, which is
 * how the secrets driver reaches an external provider without this package
 * knowing anything about it.
 */
final class StoreManager
{
    /** @var array<string, SettingsStore> */
    private array $resolved = [];

    /** @var array<string, Closure(array<string, mixed>, Container): SettingsStore> */
    private array $drivers = [];

    /** @var array<string, array<string, mixed>> store name => config overrides */
    private array $overrides = [];

    public function __construct(
        private readonly Container $container,
        private readonly ConfigRepository $config,
    ) {
        $this->registerBuiltinDrivers();
    }

    public function store(?string $name = null): SettingsStore
    {
        $name ??= $this->defaultName();

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /**
     * Read from config on every call rather than captured at construction.
     *
     * The manager is a singleton built during boot — before the overlay has
     * run, and long before anything that might want to point settings at a
     * different store. Freezing the answer at construction makes the store
     * choice depend on when the container happened to resolve this class,
     * which is neither obvious nor stable.
     */
    public function defaultName(): string
    {
        return (string) $this->config->get('settings.default_store', 'database');
    }

    /** @param  Closure(array<string, mixed>, Container): SettingsStore  $factory */
    public function extend(string $driver, Closure $factory): void
    {
        $this->drivers[$driver] = $factory;
        $this->resolved = [];
    }

    /** Bind a ready-made instance under a store name. Tests, and `Settings::fake()`. */
    public function set(string $name, SettingsStore $store): void
    {
        $this->resolved[$name] = $store;
    }

    /** @param  array<string, mixed>  $config */
    public function configure(string $name, array $config): void
    {
        $this->overrides[$name] = $config;
        unset($this->resolved[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_values(array_unique([
            ...array_keys($this->configuredStores()),
            ...array_keys($this->overrides),
            ...array_keys($this->resolved),
        ]));
    }

    /** @return array<string, array<string, mixed>> */
    private function configuredStores(): array
    {
        /** @var array<string, array<string, mixed>> $stores */
        $stores = (array) $this->config->get('settings.stores', []);

        return $stores;
    }

    private function resolve(string $name): SettingsStore
    {
        $config = $this->overrides[$name] ?? $this->configuredStores()[$name] ?? null;

        if ($config === null) {
            throw UnknownStoreException::named($name, $this->names());
        }

        $driver = (string) ($config['driver'] ?? $name);

        if (! isset($this->drivers[$driver])) {
            throw UnknownStoreException::named($driver, array_keys($this->drivers));
        }

        return ($this->drivers[$driver])($config, $this->container);
    }

    private function registerBuiltinDrivers(): void
    {
        $this->drivers['database'] = static fn (array $config, Container $container): SettingsStore => new DatabaseStore(
            connection: $container->make(DatabaseManager::class)->connection($config['connection'] ?? null),
            table: (string) ($config['table'] ?? 'settings'),
        );

        $this->drivers['array'] = static fn (): SettingsStore => new ArrayStore;

        $this->drivers['null'] = static fn (): SettingsStore => new NullStore;

        $this->drivers['file'] = static fn (array $config): SettingsStore => new FileStore(
            path: (string) ($config['path'] ?? storage_path('app/settings.json')),
        );

        $this->drivers['secrets'] = static fn (array $config, Container $container): SettingsStore => new SecretsStore(
            container: $container,
            settings: $container->make(SettingsManager::class),
            bindings: $container->make(SecretBindings::class),
            cache: $container->make(CacheFactory::class)->store($config['cache_store'] ?? null),
            ttl: (int) ($config['ttl'] ?? 60),
        );
    }
}
