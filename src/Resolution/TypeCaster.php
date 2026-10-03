<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Resolution;

use Closure;
use SashaLenz\SettingsUi\Schema\FieldType;

/**
 * Turns a raw stored value into the PHP type a consumer expects from
 * `config('group.key')`.
 *
 * The casts for built-in types mirror the ones the host app already relied on,
 * so an imported setting keeps returning the same primitive it always did —
 * a knob that silently changed from `int` to `string` on cutover would break
 * strict comparisons far away from here.
 *
 * Custom types registered by a host or package cast to themselves unless they
 * supply a closure, so registering a type never forces you to think about
 * casting before you need to.
 */
final class TypeCaster
{
    /** @var array<string, Closure(mixed): mixed> */
    private array $custom = [];

    /** @param  Closure(mixed): mixed  $cast */
    public function register(string $type, Closure $cast): void
    {
        $this->custom[$type] = $cast;
    }

    public function knows(string $type): bool
    {
        return isset($this->custom[$type]) || FieldType::tryFrom($type) !== null;
    }

    public function cast(mixed $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        if (isset($this->custom[$type])) {
            return ($this->custom[$type])($raw);
        }

        return match (FieldType::tryFrom($type)) {
            FieldType::Integer,
            FieldType::Money,
            FieldType::Model,
            FieldType::Tree => (int) $raw,

            FieldType::Decimal => (float) $raw,

            FieldType::Boolean => (bool) $raw,

            FieldType::Text,
            FieldType::Textarea,
            FieldType::Select,
            FieldType::Date,
            FieldType::Datetime,
            FieldType::Phone => (string) $raw,

            // A key-value field stores a JSON object. It arrives decoded, but a
            // hand-edited database row can leave a string behind — coerce it so
            // consumers can rely on `array` without defensive checks.
            FieldType::KeyValue => match (true) {
                is_array($raw) => $raw,
                is_string($raw) => (array) (json_decode($raw, true) ?: []),
                default => [],
            },

            // An unregistered custom type passes through untouched.
            null => $raw,
        };
    }
}
