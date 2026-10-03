<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Tests;

use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use SashaLenz\SettingsUi\SettingsServiceProvider;

class TestCase extends Orchestra
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            SettingsServiceProvider::class,
        ];
    }

    /**
     * The package ships its migration as a `.stub` so a host publishes it with
     * its own timestamp; run it by hand here rather than through
     * loadMigrationsFrom, which only sees `.php`.
     */
    protected function runPackageMigrations(): void
    {
        foreach (glob(__DIR__.'/../database/migrations/*.php.stub') ?: [] as $migration) {
            (include $migration)->up();
        }
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Discovery scans the host's vendor dir; the testbench skeleton has
        // none of our fixtures in it, so leave it off unless a test opts in.
        config()->set('settings.auto_discover', false);
    }
}
