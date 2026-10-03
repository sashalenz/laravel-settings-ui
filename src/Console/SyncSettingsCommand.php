<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use SashaLenz\SettingsUi\Projection\SchemaProjector;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\SettingsManager;
use SashaLenz\SettingsUi\Stores\DatabaseStore;
use SashaLenz\SettingsUi\Stores\StoreManager;

/**
 * Deploy step. Rebuilds the schema projection and reports orphan values.
 *
 * Replaces the seeder a DB-authoritative setup needs. The difference matters:
 * a seeder had to be careful never to clobber an operator's value, because it
 * wrote to the same table. This writes only derived data, so it can replace
 * everything it owns without a second thought — and orphan values are deleted
 * only when explicitly asked.
 */
final class SyncSettingsCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'settings:sync
        {--prune : Delete stored values whose declaration is gone}
        {--dry-run : Report without writing}
        {--force : Skip the production confirmation prompt when pruning}';

    protected $description = 'Rebuild the settings schema projection and report orphan values';

    public function handle(
        SettingsManager $settings,
        SchemaProjector $projector,
        StoreManager $stores,
        Resolver $resolver,
    ): int {
        $paths = $settings->paths();

        if (! $this->option('dry-run')) {
            $counts = $projector->project();
            $this->components->info(sprintf(
                'Projection rebuilt: %d groups, %d fields across %d root groups.',
                $counts['groups'],
                $counts['fields'],
                count($settings->groups()),
            ));
        } else {
            $this->components->info(sprintf(
                '%d declared paths across %d root groups.',
                count($paths),
                count($settings->groups()),
            ));
        }

        $orphans = $this->orphans($stores, $paths);

        if ($orphans === []) {
            $this->components->info('No orphan values.');
        } else {
            $this->components->warn(sprintf('%d stored value(s) have no declaration:', count($orphans)));

            foreach ($orphans as $path) {
                $this->components->twoColumnDetail($path, '<fg=yellow>orphan</>');
            }

            // Pruning deletes an operator's values. Rebuilding the projection
            // is safe to run unattended on every deploy; this is not, so it
            // asks before doing it in production.
            if ($this->option('prune') && ! $this->option('dry-run') && $this->confirmToProceed()) {
                $this->prune($stores, $orphans);
            } else {
                $this->components->info('Run with --prune to delete them.');
            }
        }

        if (! $this->option('dry-run')) {
            // Declarations may have changed, which changes the fingerprint and
            // therefore every cache key — but a running process holding the old
            // snapshot would keep serving it until something bumps the stamp.
            $resolver->forgetAll();
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function orphans(StoreManager $stores, array $paths): array
    {
        $orphans = [];

        foreach ($stores->names() as $name) {
            $store = $stores->store($name);

            if (! $store->writable()) {
                continue;
            }

            foreach ($store->orphans($paths) as $path) {
                $orphans[$path] = $path;
            }
        }

        $orphans = array_values($orphans);
        sort($orphans);

        return $orphans;
    }

    /** @param  list<string>  $orphans */
    private function prune(StoreManager $stores, array $orphans): void
    {
        $deleted = 0;

        foreach ($stores->names() as $name) {
            $store = $stores->store($name);

            if (! $store->writable()) {
                continue;
            }

            if ($store instanceof DatabaseStore) {
                $deleted += $store->forgetMany($orphans);

                continue;
            }

            foreach ($orphans as $path) {
                $store->forget($path);
                $deleted++;
            }
        }

        $this->components->info(sprintf('Pruned %d orphan value(s).', $deleted));
    }
}
