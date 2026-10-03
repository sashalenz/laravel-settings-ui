<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Discovery;

use Illuminate\Support\Arr;

/**
 * Finds settings declared by installed composer packages.
 *
 * A package advertises them in its own composer.json:
 *
 *     "extra": { "laravel-settings": { "schemas": ["Acme\\Foo\\FooSettings"] } }
 *
 * Discovered settings show up in the host's admin UI, so `composer require` on
 * an unrelated package can materially change what an operator sees. That is why
 * the result is a manifest with provenance attached rather than a flat list,
 * why `dont_discover` exists, and why nothing here instantiates anything — the
 * host still decides.
 */
final class PackageSchemaDiscovery
{
    public function __construct(
        private readonly string $vendorPath,
        private readonly ?string $manifestPath = null,
    ) {}

    /**
     * @param  list<string>  $dontDiscover  composer package names to skip
     * @return array<string, list<class-string>> package name => schema classes
     */
    public function discover(array $dontDiscover = []): array
    {
        $manifest = $this->cached();

        if ($manifest === null) {
            $manifest = $this->scan();
            $this->write($manifest);
        }

        return array_diff_key($manifest, array_flip($dontDiscover));
    }

    /** @return array<string, list<class-string>> */
    public function scan(): array
    {
        $installed = $this->vendorPath.'/composer/installed.json';

        if (! is_file($installed)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($installed), true);

        if (! is_array($decoded)) {
            return [];
        }

        // composer 2 nests under `packages`; composer 1 was a bare list.
        $packages = Arr::get($decoded, 'packages', $decoded);

        if (! is_array($packages)) {
            return [];
        }

        $manifest = [];

        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }

            $schemas = Arr::get($package, 'extra.laravel-settings.schemas');

            if (! is_array($schemas) || $schemas === []) {
                continue;
            }

            $name = (string) Arr::get($package, 'name', '');

            if ($name === '') {
                continue;
            }

            /** @var list<class-string> $classes */
            $classes = array_values(array_filter(
                $schemas,
                static fn (mixed $class): bool => is_string($class) && $class !== '',
            ));

            $manifest[$name] = $classes;
        }

        ksort($manifest);

        return $manifest;
    }

    public function forget(): void
    {
        if ($this->manifestPath !== null && is_file($this->manifestPath)) {
            @unlink($this->manifestPath);
        }
    }

    /** @return array<string, list<class-string>>|null */
    private function cached(): ?array
    {
        if ($this->manifestPath === null || ! is_file($this->manifestPath)) {
            return null;
        }

        $manifest = require $this->manifestPath;

        return is_array($manifest) ? $manifest : null;
    }

    /** @param  array<string, list<class-string>>  $manifest */
    private function write(array $manifest): void
    {
        if ($this->manifestPath === null) {
            return;
        }

        $directory = dirname($this->manifestPath);

        if (! is_dir($directory)) {
            return;
        }

        // Best-effort: an unwritable cache directory must degrade to scanning
        // on every boot, never to a fatal.
        @file_put_contents(
            $this->manifestPath,
            '<?php return '.var_export($manifest, true).';'.PHP_EOL,
        );
    }
}
