<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Secrets\Drivers;

use SashaLenz\SettingsUi\Contracts\SecretStore;
use SashaLenz\SettingsUi\Secrets\SecretLocation;

/**
 * Driver that resolves secrets from environment variables or .env.
 * Ref format: 'STRIPE_API_KEY' or 'DB_PASSWORD'.
 */
final class EnvSecretDriver implements SecretStore
{
    public function __construct(
        private readonly string $prefix = '',
    ) {}

    public function get(SecretLocation $location): ?string
    {
        $varName = $this->prefix !== '' ? $this->prefix.$location->ref : $location->ref;
        $val = getenv($varName);

        if ($val === false || $val === '') {
            $val = $_ENV[$varName] ?? $_SERVER[$varName] ?? null;
        }

        return $val !== null ? (string) $val : null;
    }

    public function put(SecretLocation $location, string $value): void
    {
        // Environment variables are typically read-only at runtime
        $varName = $this->prefix !== '' ? $this->prefix.$location->ref : $location->ref;
        putenv("{$varName}={$value}");
        $_ENV[$varName] = $value;
        $_SERVER[$varName] = $value;
    }

    public function forget(SecretLocation $location): void
    {
        $varName = $this->prefix !== '' ? $this->prefix.$location->ref : $location->ref;
        putenv($varName);
        unset($_ENV[$varName], $_SERVER[$varName]);
    }

    public function healthy(): bool
    {
        return true;
    }
}
