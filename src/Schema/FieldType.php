<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Schema;

/**
 * Built-in field types.
 *
 * The backed values are deliberately the ones a20's `setting_fields.type`
 * column already stores (`number`, `wire_select`, `nested_set`, `key_value`),
 * so `settings:import` maps rows across without a translation table and an
 * imported declaration round-trips to the same cast.
 *
 * Types are open: a host or package registers additional ones through
 * `Settings::registerType()`, so a `Field`'s type is carried as a string and
 * this enum is the built-in half of that namespace.
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Boolean = 'boolean';
    case Integer = 'number';
    case Decimal = 'decimal';
    case Money = 'money';
    case Select = 'select';
    case Model = 'wire_select';
    case Tree = 'nested_set';
    case Date = 'date';
    case Datetime = 'datetime';
    case Phone = 'phone';
    case KeyValue = 'key_value';

    /** Types that require `options` to be non-empty. */
    public function needsOptions(): bool
    {
        return $this === self::Select;
    }

    /** Types that require an Eloquent model class for their picker. */
    public function needsModelClass(): bool
    {
        return in_array($this, [self::Model, self::Tree], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
