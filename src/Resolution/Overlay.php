<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Resolution;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\QueryException;
use SashaLenz\SettingsUi\SettingsManager;
use Throwable;

/**
 * Pushes stored values over Laravel's config repository on boot.
 *
 * This is the whole consumer contract: application code reads plain
 * `config('group.key')` and never learns this package exists, which is why a
 * setting can be introduced without touching a single call site.
 *
 * ## Precedence
 *
 * Highest first: stored value → host's `config/{group}.php` → declaration
 * `->default()` → null.
 *
 * Rungs two and three are the subtle part. Not writing anything when nothing is
 * stored looks like an optimisation; it is actually the precedence rule, since
 * leaving the key untouched is what lets the already-loaded file value stand.
 * A declaration default is applied ONLY where the config repository has no such
 * key at all — which is the case for a package whose host never shipped a
 * config file for that group.
 *
 * ## Failure posture
 *
 * A settings problem must never take an app down. The database being
 * unreachable (fresh install, CI clone before migrate, package discovery),
 * a single corrupt value, or one bad group all degrade to file defaults for the
 * affected scope while everything else overlays normally.
 */
final class Overlay
{
    public function __construct(
        private readonly SettingsManager $settings,
        private readonly Resolver $resolver,
        private readonly ConfigRepository $config,
    ) {}

    public function apply(): void
    {
        try {
            $groups = $this->settings->groups();
        } catch (Throwable $e) {
            // A declaration that cannot even be built (a conflict, a container
            // error inside schema()) must not stop the app from booting on its
            // file defaults.
            report($e);

            return;
        }

        // Never resolve provider-backed secrets while the config cache is being
        // built — that would bake plaintext into bootstrap/cache/config.php on
        // disk. They still apply at runtime, because this overlay re-runs on
        // every boot after the cached config is loaded.
        $skipSecrets = $this->isBuildingConfigCache();

        foreach ($groups as $group) {
            try {
                $this->applyGroup($group, $skipSecrets);
            } catch (QueryException) {
                // No table or no connection yet. Boot on file defaults quietly;
                // this is the expected state before the first migrate.
                return;
            } catch (Throwable $e) {
                report($e);

                // Re-query once in case the group's snapshot was the problem.
                // If it fails again, leave this group's file defaults standing
                // rather than taking the whole application down.
                try {
                    $this->resolver->forget($group);
                    $this->applyGroup($group, $skipSecrets);
                } catch (Throwable $retry) {
                    report($retry);
                }
            }
        }
    }

    /** Drop every snapshot and re-apply. Used by the queue-worker refresh. */
    public function flushAndApply(): void
    {
        $this->resolver->forgetAll();
        $this->apply();
    }

    private function applyGroup(string $group, bool $skipSecrets): void
    {
        $stored = $this->resolver->snapshot($group);

        foreach ($this->settings->schema()->fieldsIn($group) as $path => $field) {
            if (array_key_exists($path, $stored)) {
                $this->config->set($path, $stored[$path]);

                continue;
            }

            if ($field->field->isProviderBacked()) {
                if ($skipSecrets) {
                    continue;
                }

                $secret = $this->resolver->get($path);

                if ($secret !== null) {
                    $this->config->set($path, $secret);
                }

                continue;
            }

            // Nothing stored. The file default wins if there is one — leaving
            // the key untouched IS how it wins. Only fill in the declaration
            // default where the host has no such config key at all.
            if ($field->field->default !== null && ! $this->config->has($path)) {
                $this->config->set($path, $field->field->default);
            }
        }
    }

    /**
     * `optimize` runs `config:cache` in-process, so its own argv is empty while
     * the outer process still names `optimize` — match both.
     */
    private function isBuildingConfigCache(): bool
    {
        if (PHP_SAPI !== 'cli') {
            return false;
        }

        /** @var list<string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        return in_array('config:cache', $argv, true)
            || in_array('optimize', $argv, true);
    }
}
