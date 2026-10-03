<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Schema;

use Closure;
use SashaLenz\SettingsUi\Exceptions\InvalidPathException;

/**
 * One runtime-editable setting, declared in code.
 *
 * A field owns only the leaf name — its full dotted path is composed by the
 * groups above it when the schema is resolved, so the same declaration can be
 * re-parented without touching it.
 *
 * Properties are `public private(set)`: readable everywhere (renderers, stores,
 * the importer) but writable only through the fluent API, so a declaration can
 * never be mutated after the schema is built.
 */
final class Field
{
    public private(set) string $key;

    public private(set) string $type;

    public private(set) ?string $label = null;

    public private(set) ?string $help = null;

    public private(set) ?string $placeholder = null;

    public private(set) mixed $default = null;

    /** @var string|list<string>|null */
    public private(set) string|array|null $rules = null;

    public private(set) ?int $order = null;

    public private(set) bool $secret = false;

    public private(set) bool $encrypted = false;

    public private(set) ?string $store = null;

    /** @var array<string, string> */
    public private(set) array $options = [];

    public private(set) ?string $modelClass = null;

    public private(set) ?string $secretProvider = null;

    public private(set) ?string $secretRef = null;

    public private(set) ?string $secretField = null;

    public private(set) ?Closure $cast = null;

    public private(set) ?Closure $visibleWhen = null;

    public private(set) ?string $ability = null;

    public private(set) bool $hidden = false;

    public private(set) bool $readonly = false;

    public private(set) ?string $deprecated = null;

    private function __construct(string $key, string $type)
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $key) !== 1) {
            throw InvalidPathException::segment($key, 'field');
        }

        $this->key = $key;
        $this->type = $type;
    }

    // ---------------------------------------------------------------- makers

    public static function make(string $key, FieldType|string $type): self
    {
        return new self($key, $type instanceof FieldType ? $type->value : $type);
    }

    public static function text(string $key): self
    {
        return self::make($key, FieldType::Text);
    }

    public static function textarea(string $key): self
    {
        return self::make($key, FieldType::Textarea);
    }

    public static function boolean(string $key): self
    {
        return self::make($key, FieldType::Boolean);
    }

    public static function integer(string $key): self
    {
        return self::make($key, FieldType::Integer);
    }

    public static function decimal(string $key): self
    {
        return self::make($key, FieldType::Decimal);
    }

    public static function money(string $key): self
    {
        return self::make($key, FieldType::Money);
    }

    /** @param  array<string, string>  $options */
    public static function select(string $key, array $options = []): self
    {
        return self::make($key, FieldType::Select)->options($options);
    }

    public static function date(string $key): self
    {
        return self::make($key, FieldType::Date);
    }

    public static function datetime(string $key): self
    {
        return self::make($key, FieldType::Datetime);
    }

    public static function phone(string $key): self
    {
        return self::make($key, FieldType::Phone);
    }

    public static function keyValue(string $key): self
    {
        return self::make($key, FieldType::KeyValue);
    }

    /** Autocomplete picker over an Eloquent model; stores the id. */
    public static function model(string $key, string $modelClass): self
    {
        return self::make($key, FieldType::Model)->modelClass($modelClass);
    }

    /** Nested-set picker over an Eloquent model; stores the id. */
    public static function tree(string $key, string $modelClass): self
    {
        return self::make($key, FieldType::Tree)->modelClass($modelClass);
    }

    /** A type registered by the host or another package. */
    public static function custom(string $key, string $type): self
    {
        return self::make($key, $type);
    }

    // -------------------------------------------------------------- builders

    /** Translation key; used literally when it does not resolve. */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Fallback value. Loses to the host's `config/{group}.php` entry — see the
     * overlay's null-skip. This is for hosts that shipped no config file for
     * the declaring package's group.
     */
    public function default(mixed $default): self
    {
        $this->default = $default;

        return $this;
    }

    /** @param  string|list<string>  $rules */
    public function rules(string|array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Masked input, never pre-filled, skip-if-empty on save, redacted in
     * history. Orthogonal to where the value is stored.
     */
    public function secret(): self
    {
        $this->secret = true;

        return $this;
    }

    /** Encrypt at rest in the value store. Implies secret(). */
    public function encrypted(): self
    {
        $this->encrypted = true;

        return $this->secret();
    }

    public function store(string $store): self
    {
        $this->store = $store;

        return $this;
    }

    /**
     * Bind this field to an item in the external secret store. Implies
     * secret(); the value never touches the value table.
     *
     * A per-environment override may point the same path elsewhere at runtime
     * without a deploy — `settings:doctor` reports which source won.
     */
    public function at(string $ref, ?string $field = null, ?string $provider = null): self
    {
        $this->secretRef = $ref;
        $this->secretField = $field;
        $this->secretProvider = $provider;

        return $this->secret()->store($this->store ?? 'secrets');
    }

    /** @param  array<string, string>  $options */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /** @param  class-string  $modelClass */
    public function modelClass(string $modelClass): self
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /** Override the type's cast. Receives the raw stored value. */
    public function cast(Closure $cast): self
    {
        $this->cast = $cast;

        return $this;
    }

    /** Render only when the closure returns true. */
    public function when(Closure $condition): self
    {
        $this->visibleWhen = $condition;

        return $this;
    }

    /** Optional extra gate, on top of the host's manage ability. */
    public function authorize(string $ability): self
    {
        $this->ability = $ability;

        return $this;
    }

    /** Resolvable through config(), but never rendered in the UI. */
    public function hidden(): self
    {
        $this->hidden = true;

        return $this;
    }

    /** Rendered, but not editable. */
    public function readonly(): self
    {
        $this->readonly = true;

        return $this;
    }

    /** Flags the field for `settings:prune` and greys it in the UI. */
    public function deprecated(string $reason): self
    {
        $this->deprecated = $reason;

        return $this;
    }

    // --------------------------------------------------------------- queries

    public function fieldType(): ?FieldType
    {
        return FieldType::tryFrom($this->type);
    }

    public function isProviderBacked(): bool
    {
        return $this->secretRef !== null;
    }

    /** @return list<string> */
    public function ruleList(): array
    {
        if ($this->rules === null) {
            return ['nullable'];
        }

        $rules = is_array($this->rules)
            ? $this->rules
            : explode('|', $this->rules);

        $rules = array_values(array_filter(
            array_map(static fn (string $rule): string => trim($rule), $rules),
            static fn (string $rule): bool => $rule !== '',
        ));

        return $rules === [] ? ['nullable'] : $rules;
    }

    public function isRequired(): bool
    {
        foreach ($this->ruleList() as $rule) {
            if ($rule === 'required' || str_starts_with($rule, 'required_')) {
                return true;
            }
        }

        return false;
    }

    public function isNullable(): bool
    {
        return ! $this->isRequired();
    }
}
