<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Database\Seeders;

use Illuminate\Database\Seeder;
use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\SettingsManager;

/**
 * Base Seeder for populating setting values.
 *
 * Supports safe idempotent execution (only seed keys that have no stored value yet),
 * environment-specific overrides, or force-syncing defaults.
 */
class SettingsSeeder extends Seeder
{
    /**
     * Settings to seed: array of [path => value].
     * If left empty, default values declared in schema are used.
     *
     * @var array<string, mixed>
     */
    protected array $settings = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->syncMissing();
    }

    /**
     * Seeds only settings that currently have NO value stored in the database/store.
     * Safe for production and staging deploys.
     */
    public function syncMissing(): int
    {
        return $this->seed(force: false);
    }

    /**
     * Force-updates all settings to their declared/defined values,
     * overwriting whatever is stored in the database.
     * Useful for local development and test suites.
     */
    public function forceSync(): int
    {
        return $this->seed(force: true);
    }

    /**
     * Define environment-specific defaults if needed.
     *
     * @return array<string, mixed>
     */
    protected function environmentSettings(): array
    {
        return [];
    }

    protected function seed(bool $force = false): int
    {
        /** @var SettingsManager $settings */
        $settings = app(SettingsManager::class);
        /** @var Resolver $resolver */
        $resolver = app(Resolver::class);

        $custom = array_merge($this->settings, $this->environmentSettings());
        $count = 0;

        // 1. Process custom predefined settings
        foreach ($custom as $path => $value) {
            $field = $settings->field($path);
            if ($field === null) {
                continue;
            }

            if (! $force && $resolver->has($path)) {
                continue;
            }

            $resolver->put($path, $value);
            $count++;
        }

        // 2. Process schema declared defaults for any unseeded fields
        foreach ($settings->schema()->fields as $path => $resolved) {
            if (isset($custom[$path])) {
                continue;
            }

            if (! $force && $resolver->has($path)) {
                continue;
            }

            if ($resolved->field->default !== null) {
                $resolver->put($path, $resolved->field->default);
                $count++;
            }
        }

        return $count;
    }
}
