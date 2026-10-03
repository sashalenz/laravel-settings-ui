<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Resolution;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use SashaLenz\SettingsUi\Schema\Field;

/**
 * The two-way transform between what a store holds and what a consumer gets.
 *
 * Read:  raw → decrypt → type cast → custom cast
 * Write: value → serialize → encrypt
 *
 * Encryption sits here rather than in a store so `->encrypted()` composes with
 * whichever store a field uses, and so exactly one place knows the order the
 * steps run in.
 */
final class ValuePipeline
{
    public function __construct(
        private readonly TypeCaster $caster,
        private readonly StringEncrypter $encrypter,
    ) {}

    /** Store value → consumer value. */
    public function hydrate(mixed $raw, Field $field): mixed
    {
        if ($raw === null) {
            return null;
        }

        if ($field->encrypted && is_string($raw) && $raw !== '') {
            try {
                $raw = $this->encrypter->decryptString($raw);
            } catch (DecryptException) {
                // Ciphertext we can no longer read: a rotated APP_KEY, a
                // hand-edited row, a value copied between environments. Treat
                // it as absent so the app boots on its file default and the
                // operator can re-enter the secret — a crash here would take
                // down every request instead.
                return null;
            }
        }

        return $this->caster->cast($raw, $field->type);
    }

    /** Consumer value → store value. */
    public function dehydrate(mixed $value, Field $field): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($field->encrypted) {
            return $this->encrypter->encryptString((string) $value);
        }

        return $this->caster->cast($value, $field->type);
    }
}
