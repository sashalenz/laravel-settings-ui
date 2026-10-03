<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi;

use SashaLenz\SettingsUi\Contracts\DeclaresSettings;
use SashaLenz\SettingsUi\Discovery\DirectorySchemaDiscovery;
use SashaLenz\SettingsUi\Schema\Group;
use SashaLenz\SettingsUi\Schema\ResolvedField;
use SashaLenz\SettingsUi\Schema\ResolvedGroup;
use SashaLenz\SettingsUi\Schema\Schema;
use SashaLenz\SettingsUi\Schema\SchemaRegistry;

/**
 * The package's public seam — what `Settings::` resolves to.
 *
 * Deliberately thin: it holds no state of its own beyond delegation, so the
 * pieces behind it (registry now, resolver and stores next) stay independently
 * testable and swappable.
 */
final class SettingsManager
{
    public function __construct(private readonly SchemaRegistry $registry) {}

    /**
     * Contribute settings. Accepts a class name (resolved through the
     * container, so declarations can inject), an instance, or a bare Group for
     * quick cases and tests.
     *
     * @param  class-string<DeclaresSettings>|DeclaresSettings|Group  $schema
     */
    public function declare(string|DeclaresSettings|Group $schema, ?string $source = null): void
    {
        $this->registry->declare($schema, $source);
    }

    /**
     * Register multiple schemas at once, optionally with a custom source identifier.
     * Ideal for Service Providers in external packages or domain modules.
     *
     * @param  list<class-string<DeclaresSettings>|DeclaresSettings|Group>  $schemas
     */
    public function register(array $schemas, ?string $source = null): void
    {
        foreach ($schemas as $schema) {
            $this->declare($schema, $source);
        }
    }

    /**
     * Discover and register setting schemas from directories or glob patterns.
     * Ideal for DDD or modular applications.
     *
     * @param  list<string>  $paths
     */
    public function discover(array $paths, ?string $source = 'discovery'): void
    {
        $scanner = new DirectorySchemaDiscovery;
        $classes = $scanner->discover($paths);

        foreach ($classes as $class) {
            $this->declare($class, $source);
        }
    }

    public function schema(): Schema
    {
        return $this->registry->schema();
    }

    public function registry(): SchemaRegistry
    {
        return $this->registry;
    }

    /** @return list<string> */
    public function groups(): array
    {
        return $this->schema()->rootNames();
    }

    public function group(string $name): ?ResolvedGroup
    {
        return $this->schema()->root($name);
    }

    public function field(string $path): ?ResolvedField
    {
        return $this->schema()->field($path);
    }

    /** @return list<string> */
    public function paths(): array
    {
        return $this->schema()->paths();
    }

    public function declared(string $path): bool
    {
        return $this->schema()->has($path);
    }

    /** @return list<array{source: string, root: string}> */
    public function provenance(): array
    {
        return $this->registry->provenance();
    }

    /**
     * Mount the package's routes.
     *
     * Call this inside whatever prefix, middleware and name group the host
     * wants — the admin surface belongs to the host's shell, so the package
     * never registers them itself:
     *
     *     Route::prefix('control')->middleware(['web', 'auth:admin'])->group(
     *         fn () => Settings::routes(),
     *     );
     */
    public function routes(): void
    {
        require __DIR__.'/../routes/settings.php';
    }
}
