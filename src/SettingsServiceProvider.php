<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use SashaLenz\SettingsUi\Authorization\GateAuthorizer;
use SashaLenz\SettingsUi\Contracts\FieldRendererInterface;
use SashaLenz\SettingsUi\Contracts\RecordsChanges;
use SashaLenz\SettingsUi\Contracts\SettingsAuthorizer;
use SashaLenz\SettingsUi\Discovery\PackageSchemaDiscovery;
use SashaLenz\SettingsUi\History\ChangeRecorder;
use SashaLenz\SettingsUi\History\Recorders\DatabaseRecorder;
use SashaLenz\SettingsUi\Projection\SchemaProjector;
use SashaLenz\SettingsUi\Rendering\NativeFieldRenderer;
use SashaLenz\SettingsUi\Resolution\Overlay;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\Resolution\SettingsCache;
use SashaLenz\SettingsUi\Resolution\TypeCaster;
use SashaLenz\SettingsUi\Resolution\ValuePipeline;
use SashaLenz\SettingsUi\Schema\SchemaRegistry;
use SashaLenz\SettingsUi\Secrets\SecretBindings;
use SashaLenz\SettingsUi\Stores\StoreManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Wires the package.
 *
 * Note what is deliberately absent: routes. The package ships a route file but
 * never loads it — a host mounts `Settings::routes()` inside whatever prefix,
 * middleware and name group it wants, because the admin surface belongs to the
 * host's shell, not to us.
 *
 * Declarations are collected in `packageBooted()`, after every provider has
 * registered, so a schema may depend on container bindings from anywhere. They
 * are only *collected* here — nothing is instantiated until the first schema
 * access, which keeps requests that never touch settings free of cost.
 */
class SettingsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('settings')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasMigrations([
                'create_settings_table',
                'create_setting_schema_table',
                'create_setting_changes_table',
                'create_setting_secret_bindings_table',
            ])
            ->hasCommands([
                Console\ImportSettingsCommand::class,
                Console\SyncSettingsCommand::class,
                Console\DoctorCommand::class,
                Console\SeedSettingsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(
            SchemaRegistry::class,
            fn ($app): SchemaRegistry => new SchemaRegistry($app),
        );

        $this->app->singleton(
            SettingsManager::class,
            fn ($app): SettingsManager => new SettingsManager($app->make(SchemaRegistry::class)),
        );

        $this->app->singleton(
            PackageSchemaDiscovery::class,
            fn ($app): PackageSchemaDiscovery => new PackageSchemaDiscovery(
                vendorPath: $app->basePath('vendor'),
                manifestPath: $app->bootstrapPath('cache/settings-schemas.php'),
            ),
        );

        $this->app->singleton(TypeCaster::class);

        $this->app->singleton(
            StoreManager::class,
            fn ($app): StoreManager => new StoreManager(
                container: $app,
                config: $app->make(ConfigRepository::class),
            ),
        );

        $this->app->singleton(
            ValuePipeline::class,
            fn ($app): ValuePipeline => new ValuePipeline(
                caster: $app->make(TypeCaster::class),
                encrypter: $app->make(StringEncrypter::class),
            ),
        );

        $this->app->singleton(
            SettingsCache::class,
            function ($app): SettingsCache {
                $config = $app->make(ConfigRepository::class);

                return new SettingsCache(
                    cache: $app->make(CacheFactory::class)->store($config->get('settings.cache.store')),
                    prefix: (string) $config->get('settings.cache.prefix', 'settings'),
                    enabled: (bool) $config->get('settings.cache.enabled', true),
                    ttl: $config->get('settings.cache.ttl'),
                );
            },
        );

        // Scoped, not singleton: the per-request memo inside the resolver must
        // reset between requests, or a value changed in one request would be
        // served stale to the next in a long-lived process (Octane, workers).
        $this->app->scoped(
            Resolver::class,
            fn ($app): Resolver => new Resolver(
                settings: $app->make(SettingsManager::class),
                stores: $app->make(StoreManager::class),
                pipeline: $app->make(ValuePipeline::class),
                cache: $app->make(SettingsCache::class),
            ),
        );

        $this->app->scoped(
            Overlay::class,
            fn ($app): Overlay => new Overlay(
                settings: $app->make(SettingsManager::class),
                resolver: $app->make(Resolver::class),
                config: $app->make(ConfigRepository::class),
            ),
        );

        $this->app->singleton(
            FieldRendererInterface::class,
            NativeFieldRenderer::class,
        );

        // Scoped: the override map is memoised per request, so a binding
        // changed in one request must not be served stale to the next.
        $this->app->scoped(
            SecretBindings::class,
            fn ($app): SecretBindings => new SecretBindings(
                connection: $app->make(DatabaseManager::class)->connection(),
            ),
        );

        // Bound to the contract, so a host swaps the whole authorization story
        // with one binding rather than by configuring around ours.
        $this->app->singleton(
            SettingsAuthorizer::class,
            fn ($app): SettingsAuthorizer => new GateAuthorizer(
                gate: $app->make(Gate::class),
                config: $app->make(ConfigRepository::class),
            ),
        );

        $this->app->singleton(
            SchemaProjector::class,
            fn ($app): SchemaProjector => new SchemaProjector(
                settings: $app->make(SettingsManager::class),
                connection: $app->make(DatabaseManager::class)->connection(),
            ),
        );

        $this->app->singleton(
            DatabaseRecorder::class,
            fn ($app): DatabaseRecorder => new DatabaseRecorder(
                connection: $app->make(DatabaseManager::class)->connection(),
            ),
        );

        $this->app->singleton(
            ChangeRecorder::class,
            function ($app): ChangeRecorder {
                $config = $app->make(ConfigRepository::class);

                /** @var list<class-string<RecordsChanges>> $recorders */
                $recorders = (array) $config->get('settings.history.recorders', []);

                return new ChangeRecorder(
                    container: $app,
                    configured: $recorders,
                    enabled: (bool) $config->get('settings.history.enabled', true),
                );
            },
        );
    }

    public function packageBooted(): void
    {
        $this->registerLivewireComponents();
        $this->declareConfiguredSchemas();
        $this->declareDiscoveredSchemas();
        $this->applyOverlay();
        $this->registerWorkerRefresh();
    }

    private function registerLivewireComponents(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('settings::group-form', Http\Livewire\SettingsGroupForm::class);
            Livewire::component('settings::table', Http\Livewire\SettingsTable::class);
            Livewire::component('settings::history-table', Http\Livewire\SettingsHistoryTable::class);
            Livewire::component('settings::schema-table', Http\Livewire\SettingSchemaTable::class);
        }
    }

    /**
     * Runs in boot(), after every provider has registered, so a declaration may
     * depend on bindings from anywhere.
     *
     * The trade-off worth knowing: a provider that reads config inside its own
     * register() sees the file default, not the stored value. Services that
     * read config lazily at point of use — the overwhelming majority — see the
     * overlay.
     */
    private function applyOverlay(): void
    {
        if (config('settings.overlay.enabled', true) !== true) {
            return;
        }

        $this->app->make(Overlay::class)->apply();
    }

    /**
     * Long-lived workers hold the boot-time overlay, so a settings change
     * reaches them only on restart. With this on, one cheap version read per
     * job decides whether to re-apply — and it re-applies only when the stamp
     * actually moved, which is what makes it affordable.
     *
     * Between jobs, never mid-job: a job sees one consistent config for its
     * whole run.
     */
    private function registerWorkerRefresh(): void
    {
        if (config('settings.workers.refresh', false) !== true) {
            return;
        }

        if (! $this->app->bound(QueueManager::class)) {
            return;
        }

        $seen = null;

        Queue::looping(function () use (&$seen): void {
            $overlay = $this->app->make(Overlay::class);
            $version = $this->app->make(Resolver::class)->version();

            if ($seen === $version) {
                return;
            }

            $seen = $version;
            $overlay->flushAndApply();
        });
    }

    /** Schemas listed by the host in `config/settings.php`. */
    private function declareConfiguredSchemas(): void
    {
        $manager = $this->app->make(SettingsManager::class);

        /** @var list<class-string> $schemas */
        $schemas = (array) config('settings.schemas', []);

        foreach ($schemas as $schema) {
            $manager->declare($schema);
        }
    }

    /** Schemas advertised by installed packages through composer `extra`. */
    private function declareDiscoveredSchemas(): void
    {
        if (config('settings.auto_discover', true) !== true) {
            return;
        }

        $manager = $this->app->make(SettingsManager::class);
        $discovery = $this->app->make(PackageSchemaDiscovery::class);

        /** @var list<string> $dontDiscover */
        $dontDiscover = (array) config('settings.dont_discover', []);

        foreach ($discovery->discover($dontDiscover) as $package => $schemas) {
            foreach ($schemas as $schema) {
                // Provenance carries the composer package name, not just the
                // class — when a group appears that nobody in the app declared,
                // "which package did this come from" is the first question.
                $manager->declare($schema, sprintf('%s (%s)', $schema, $package));
            }
        }
    }
}
