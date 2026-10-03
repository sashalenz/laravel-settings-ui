<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Exceptions;

/**
 * A group or field name is not a usable path segment.
 *
 * Dots get their own message because reaching for `Field::text('a.b')` is the
 * natural instinct for anyone coming from a flat settings table — and it is
 * exactly the habit this package exists to replace. Nesting is expressed with
 * nested groups so that it is modelled, orderable and renderable; a dotted key
 * would slip past all three.
 */
final class InvalidPathException extends SettingsException
{
    public static function segment(string $segment, string $kind): self
    {
        if (str_contains($segment, '.')) {
            return new self(sprintf(
                'The %s name "%s" contains a dot. Nesting is expressed with nested groups, '
                .'not dotted keys — declare Group::make("%s")->fields([Field::…("%s")]) instead.',
                $kind,
                $segment,
                strtok($segment, '.'),
                substr($segment, strpos($segment, '.') + 1),
            ));
        }

        return new self(sprintf(
            'The %s name "%s" is not a valid path segment. Expected lowercase letters, '
            .'digits, underscores or dashes, starting with a letter.',
            $kind,
            $segment,
        ));
    }
}
