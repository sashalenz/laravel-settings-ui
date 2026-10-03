<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Exceptions;

final class SecretNotLocatedException extends SettingsException
{
    public static function path(string $path): self
    {
        return new self(sprintf(
            'Setting "%s" uses the secrets store but has no location. '
            .'Declare one with ->at(ref, field), or add a binding row for this path.',
            $path,
        ));
    }
}
