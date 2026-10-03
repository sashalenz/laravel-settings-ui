<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Contracts;

use SashaLenz\SettingsUi\Exceptions\SecretMissingException;
use SashaLenz\SettingsUi\Exceptions\SecretUnavailableException;
use SashaLenz\SettingsUi\Secrets\SecretLocation;

/**
 * An external secret backend — OVH, Vault, 1Password, AWS Secrets Manager.
 *
 * The package deliberately knows nothing about any of them. A host binds an
 * adapter, usually a few dozen lines over whatever client it already has, and
 * everything above this line stays provider-agnostic.
 *
 * Implementations should throw {@see SecretUnavailableException}
 * for a transient outage and {@see SecretMissingException}
 * for a missing item — the distinction matters, because one is worth alerting
 * on and the other is not.
 */
interface SecretStore
{
    public function get(SecretLocation $location): ?string;

    public function put(SecretLocation $location, string $value): void;

    public function forget(SecretLocation $location): void;

    public function healthy(): bool;
}
