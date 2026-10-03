<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use SashaLenz\SettingsUi\Contracts\FieldRendererInterface;
use SashaLenz\SettingsUi\Contracts\SecretStore;
use SashaLenz\SettingsUi\Resolution\TypeCaster;
use SashaLenz\SettingsUi\Schema\ResolvedField;
use SashaLenz\SettingsUi\Secrets\SecretBindings;
use SashaLenz\SettingsUi\SettingsManager;
use SashaLenz\SettingsUi\Stores\StoreManager;
use Throwable;

/**
 * Answers the questions that are otherwise expensive to answer.
 *
 * Chiefly: **where does this secret actually resolve from?** Allowing both a
 * declared location and a database override buys flexibility at the cost of
 * ambiguity, and a secret read from the wrong place fails silently. This
 * command is the other half of that trade — without it the flexibility would
 * not be worth having.
 *
 * It also reports schema provenance, which is what you want the moment a
 * settings group appears that nobody in the app declared.
 */
final class DoctorCommand extends Command
{
    protected $signature = 'settings:doctor {--verify : Check each secret location against the live provider}';

    protected $description = 'Diagnose settings declarations, stores and secret bindings';

    public function handle(
        SettingsManager $settings,
        StoreManager $stores,
        SecretBindings $bindings,
        TypeCaster $caster,
        FieldRendererInterface $renderer,
        Container $container,
    ): int {
        $this->reportProvenance($settings);

        // Both run regardless of the other's verdict — a doctor that stops at
        // the first problem makes you run it N times to find N problems.
        $typesOk = $this->reportTypes($settings, $caster, $renderer);
        $secretsOk = $this->reportSecrets($settings, $bindings, $container);

        $this->reportStores($stores, $settings);

        return $typesOk && $secretsOk ? self::SUCCESS : self::FAILURE;
    }

    private function reportProvenance(SettingsManager $settings): void
    {
        $this->components->info('Declared schema');

        $rows = [];

        foreach ($settings->provenance() as $entry) {
            $group = $settings->group($entry['root']);

            $rows[] = [$entry['root'], (string) ($group?->fieldCount() ?? 0), $entry['source']];
        }

        $this->table(['group', 'fields', 'declared by'], $rows);
    }

    private function reportTypes(
        SettingsManager $settings,
        TypeCaster $caster,
        FieldRendererInterface $renderer,
    ): bool {
        $unknown = [];

        foreach ($settings->schema()->fields as $path => $field) {
            if ($caster->knows($field->field->type) && $renderer->supports($field->field->type)) {
                continue;
            }

            $unknown[] = [$path, $field->field->type];
        }

        if ($unknown === []) {
            return true;
        }

        $this->newLine();
        $this->components->error(sprintf('%d field(s) use a type nothing has registered:', count($unknown)));
        $this->table(['path', 'type'], $unknown);

        return false;
    }

    private function reportSecrets(SettingsManager $settings, SecretBindings $bindings, Container $container): bool
    {
        /** @var list<ResolvedField> $secrets */
        $secrets = [];

        foreach ($settings->schema()->fields as $field) {
            if ($field->field->secret) {
                $secrets[] = $field;
            }
        }

        if ($secrets === []) {
            return true;
        }

        $this->newLine();
        $this->components->info('Secrets');

        $bound = $container->bound(SecretStore::class);
        $verify = (bool) $this->option('verify') && $bound;
        $backend = $verify ? $container->make(SecretStore::class) : null;

        $rows = [];
        $healthy = true;

        foreach ($secrets as $field) {
            $location = $bindings->locate($field);
            $source = $bindings->source($field) ?? '—';
            $status = '—';

            if ($field->field->encrypted) {
                $source = 'encrypted at rest';
                $location = null;
            } elseif ($location === null) {
                // Bound to the secrets store with nowhere to read from: the
                // value will silently resolve to its file default forever.
                $status = '<fg=red>no location</>';
                $healthy = false;
            } elseif ($verify && $backend !== null) {
                try {
                    $status = $backend->get($location) !== null
                        ? '<fg=green>present</>'
                        : '<fg=yellow>empty</>';
                } catch (Throwable $e) {
                    $status = '<fg=red>'.class_basename($e).'</>';
                    $healthy = false;
                }
            }

            $rows[] = [$field->path, $source, $location?->describe() ?? '—', $status];
        }

        $this->table(['path', 'location from', 'location', $verify ? 'verified' : 'status'], $rows);

        if (! $bound) {
            $this->components->warn(sprintf(
                'No %s is bound, so provider-backed secrets resolve to null. Bind your adapter.',
                SecretStore::class,
            ));
        }

        return $healthy;
    }

    private function reportStores(StoreManager $stores, SettingsManager $settings): void
    {
        $this->newLine();
        $this->components->info('Stores');

        $usage = [];
        $default = $stores->defaultName();

        foreach ($settings->schema()->fields as $field) {
            $name = $field->storeName($default);
            $usage[$name] = ($usage[$name] ?? 0) + 1;
        }

        $rows = [];

        foreach ($usage as $name => $count) {
            $writable = '—';

            try {
                $writable = $stores->store($name)->writable() ? 'yes' : '<fg=yellow>read-only</>';
            } catch (Throwable $e) {
                $writable = '<fg=red>'.class_basename($e).'</>';
            }

            $rows[] = [$name.($name === $default ? ' (default)' : ''), (string) $count, $writable];
        }

        $this->table(['store', 'fields', 'writable'], $rows);

        // A single JSON file cannot be shared safely across instances, and a
        // queue worker is the clearest sign that there is more than one.
        if (isset($usage['file']) || $default === 'file') {
            $this->components->warn(
                'The file store keeps all values in one JSON file. It is not safe for multi-instance '
                .'deployments — use the database store if more than one process writes settings.'
            );
        }
    }
}
