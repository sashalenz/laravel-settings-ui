<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Exceptions;

final class UnknownStoreException extends SettingsException
{
    /** @param  list<string>  $known */
    public static function named(string $store, array $known): self
    {
        return new self(sprintf(
            'Unknown settings store "%s". Configured stores: %s.',
            $store,
            $known === [] ? '(none)' : implode(', ', $known),
        ));
    }
}
