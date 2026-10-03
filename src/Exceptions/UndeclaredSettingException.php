<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Exceptions;

final class UndeclaredSettingException extends SettingsException
{
    public static function path(string $path): self
    {
        return new self(sprintf(
            'No setting is declared at "%s". Declarations are the source of truth — '
            .'add the field to its Group, or check the path for a typo.',
            $path,
        ));
    }
}
