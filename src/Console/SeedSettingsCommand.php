<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Console;

use Illuminate\Console\Command;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\SettingsManager;

/**
 * Artisan command to seed settings into database/store.
 */
final class SeedSettingsCommand extends Command
{
    protected $signature = 'settings:seed
                            {--group= : Seed only a specific root group}
                            {--force : Force overwrite existing values with defaults}
                            {--missing-only : Only seed missing keys without existing value (default)}';

    protected $description = 'Seed declared default setting values into the storage';

    public function handle(SettingsManager $settings, Resolver $resolver): int
    {
        $group = $this->option('group');
        $force = (bool) $this->option('force');

        $this->info('Seeding settings...');

        $fields = $settings->schema()->fields;
        if (is_string($group) && $group !== '') {
            $fields = array_filter(
                $fields,
                static fn ($resolved) => $resolved->root === $group
            );
        }

        $count = 0;
        foreach ($fields as $path => $resolved) {
            $has = $resolver->has($path);

            if (! $force && $has) {
                continue;
            }

            if ($resolved->field->default !== null) {
                $resolver->put($path, $resolved->field->default);
                $this->line("  ✓ <comment>{$path}</comment> set to default");
                $count++;
            }
        }

        $this->info("Completed. Seeded {$count} setting(s).");

        return self::SUCCESS;
    }
}
