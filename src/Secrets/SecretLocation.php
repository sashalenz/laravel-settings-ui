<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Secrets;

/**
 * Where a secret physically lives in an external store.
 *
 * `provider` is nullable so a host with a single secret backend never has to
 * name it; `field` addresses one key inside a multi-field item, which is how
 * most secret managers model a "note" or "login" entry.
 */
final readonly class SecretLocation
{
    public function __construct(
        public string $ref,
        public ?string $field = null,
        public ?string $provider = null,
    ) {}

    public function describe(): string
    {
        return implode('', [
            $this->provider !== null ? $this->provider.':' : '',
            $this->ref,
            $this->field !== null ? '#'.$this->field : '',
        ]);
    }

    public function equals(?self $other): bool
    {
        return $other !== null
            && $this->ref === $other->ref
            && $this->field === $other->field
            && $this->provider === $other->provider;
    }
}
