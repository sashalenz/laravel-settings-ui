<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Import;

/** One row of a legacy flat settings table, normalised. */
final readonly class LegacyRow
{
    /** @param  array<string, string>|null  $options */
    public function __construct(
        public string $group,
        public string $key,
        public string $type,
        public ?string $label,
        public ?string $help,
        public ?string $rules,
        public mixed $default,
        public mixed $value,
        public ?array $options,
        public ?string $modelClass,
        public bool $encrypted,
        public ?string $secretProvider,
        public ?string $secretRef,
        public ?string $secretField,
        public int $order,
    ) {}

    public function path(): string
    {
        return $this->group.'.'.$this->key;
    }

    /** @return list<string> the key split into path segments */
    public function segments(): array
    {
        return explode('.', $this->key);
    }

    public function isProviderBacked(): bool
    {
        return $this->secretRef !== null && $this->secretRef !== '';
    }
}
