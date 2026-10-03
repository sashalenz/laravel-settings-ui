<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Exceptions;

final class StoreNotWritableException extends SettingsException
{
    public static function for(string $store, string $path): self
    {
        return new self(sprintf(
            'The "%s" store is read-only, so "%s" cannot be written. '
            .'Point the field at a writable store, or drop the write.',
            $store,
            $path,
        ));
    }
}
