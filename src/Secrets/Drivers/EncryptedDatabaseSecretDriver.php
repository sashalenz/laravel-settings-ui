<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Secrets\Drivers;

use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Database\Connection;
use SashaLenz\SettingsUi\Contracts\SecretStore;
use SashaLenz\SettingsUi\Exceptions\SecretUnavailableException;
use SashaLenz\SettingsUi\Secrets\SecretLocation;
use Throwable;

/**
 * Driver that stores encrypted secrets directly in database table using Laravel's Encrypter (App Key).
 * Zero external dependencies.
 */
final class EncryptedDatabaseSecretDriver implements SecretStore
{
    public function __construct(
        private readonly Connection $db,
        private readonly StringEncrypter $encrypter,
        private readonly string $table = 'settings_secrets',
    ) {}

    public function get(SecretLocation $location): ?string
    {
        try {
            $row = $this->db->table($this->table)
                ->where('ref', $location->ref)
                ->where('field', $location->field)
                ->first(['payload']);

            if (! $row || ! isset($row->payload)) {
                return null;
            }

            return $this->encrypter->decryptString((string) $row->payload);
        } catch (Throwable $e) {
            throw new SecretUnavailableException(
                "Failed to decrypt secret at location [{$location->describe()}]: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function put(SecretLocation $location, string $value): void
    {
        try {
            $encrypted = $this->encrypter->encryptString($value);

            $this->db->table($this->table)->updateOrInsert(
                [
                    'ref' => $location->ref,
                    'field' => $location->field,
                ],
                [
                    'payload' => $encrypted,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (Throwable $e) {
            throw new SecretUnavailableException(
                "Failed to store secret at location [{$location->describe()}]: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function forget(SecretLocation $location): void
    {
        try {
            $this->db->table($this->table)
                ->where('ref', $location->ref)
                ->where('field', $location->field)
                ->delete();
        } catch (Throwable $e) {
            throw new SecretUnavailableException(
                "Failed to forget secret at location [{$location->describe()}]: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function healthy(): bool
    {
        try {
            return $this->db->getSchemaBuilder()->hasTable($this->table);
        } catch (Throwable) {
            return false;
        }
    }
}
