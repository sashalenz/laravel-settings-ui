<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Exceptions;

/**
 * Two declarations claim the same path.
 *
 * Thrown at schema-build time rather than resolved last-wins, so an
 * auto-discovered package can never silently shadow a host's own setting —
 * the whole point of naming the declaring class in the message is that the
 * operator can tell which composer package just collided with their app.
 */
final class SchemaConflictException extends SettingsException
{
    public static function duplicateField(string $path, string $existingSource, string $incomingSource): self
    {
        return new self(sprintf(
            'Setting "%s" is declared twice: by %s and by %s. Rename one, or remove the duplicate declaration.',
            $path,
            $existingSource,
            $incomingSource,
        ));
    }

    public static function fieldGroupCollision(string $path, string $source): self
    {
        return new self(sprintf(
            'Path "%s" is declared as both a group and a field (%s). A path is one or the other.',
            $path,
            $source,
        ));
    }
}
