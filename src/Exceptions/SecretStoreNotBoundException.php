<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Exceptions;

use SashaLenz\SettingsUi\Contracts\SecretStore;

final class SecretStoreNotBoundException extends SettingsException
{
    public static function make(): self
    {
        return new self(sprintf(
            'A setting is bound to the "secrets" store, but no %s implementation is bound in the container. '
            .'Bind your provider adapter, or point the field at another store.',
            SecretStore::class,
        ));
    }
}
