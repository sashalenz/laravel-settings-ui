<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Secrets;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use SashaLenz\SettingsUi\Console\DoctorCommand;
use SashaLenz\SettingsUi\Schema\ResolvedField;

/**
 * Resolves where a field's secret lives: declaration, or per-environment
 * override.
 *
 * Two sources means two places to look when a secret resolves wrong, which is
 * a real cost — a secret reading from the wrong location fails silently and
 * expensively. It is paid down by {@see DoctorCommand},
 * which prints the winning source for every provider-backed path, and by
 * recording override writes in the change log.
 *
 * The override table is small and read once per request; a missing table (fresh
 * install, pre-migrate) degrades to declaration-only rather than failing.
 */
final class SecretBindings
{
    public const string SOURCE_OVERRIDE = 'override';

    public const string SOURCE_DECLARATION = 'declaration';

    /** @var array<string, SecretLocation>|null path => location */
    private ?array $overrides = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'setting_secret_bindings',
    ) {}

    public function locate(ResolvedField $field): ?SecretLocation
    {
        return $this->overrides()[$field->path] ?? $this->declared($field);
    }

    /** Which source won — for diagnostics, never for resolution. */
    public function source(ResolvedField $field): ?string
    {
        if (isset($this->overrides()[$field->path])) {
            return self::SOURCE_OVERRIDE;
        }

        return $this->declared($field) !== null ? self::SOURCE_DECLARATION : null;
    }

    public function override(string $path, SecretLocation $location): void
    {
        $now = $this->connection->raw('CURRENT_TIMESTAMP');

        $this->connection->table($this->table)->updateOrInsert(
            ['path' => $path],
            [
                'provider' => $location->provider,
                'ref' => $location->ref,
                'field' => $location->field,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $this->forget();
    }

    public function removeOverride(string $path): void
    {
        $this->connection->table($this->table)->where('path', $path)->delete();

        $this->forget();
    }

    public function forget(): void
    {
        $this->overrides = null;
    }

    private function declared(ResolvedField $field): ?SecretLocation
    {
        $ref = $field->field->secretRef;

        if ($ref === null || $ref === '') {
            return null;
        }

        return new SecretLocation(
            ref: $ref,
            field: $field->field->secretField,
            provider: $field->field->secretProvider,
        );
    }

    /** @return array<string, SecretLocation> */
    private function overrides(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        $this->overrides = [];

        try {
            $rows = $this->connection->table($this->table)->get(['path', 'provider', 'ref', 'field']);
        } catch (QueryException) {
            // No table yet. Declaration-only is the correct fallback — an app
            // must boot before its migrations have run.
            return $this->overrides;
        }

        foreach ($rows as $row) {
            $this->overrides[(string) $row->path] = new SecretLocation(
                ref: (string) $row->ref,
                field: $row->field !== null ? (string) $row->field : null,
                provider: $row->provider !== null ? (string) $row->provider : null,
            );
        }

        return $this->overrides;
    }
}
