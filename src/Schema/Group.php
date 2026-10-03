<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Schema;

use Closure;
use SashaLenz\SettingsUi\Exceptions\InvalidPathException;

/**
 * A node in the settings tree.
 *
 * A root group is a config group — `Group::make('selling')` overlays
 * `config('selling.*')`. Every nested group is BOTH a real dot-segment in the
 * resolved path AND a rendering boundary, which is the whole point: the
 * structure that used to hide inside dotted keys (`chatwoot.inboxes.viber`)
 * becomes something the UI can card up and the operator can order.
 */
final class Group
{
    public private(set) string $name;

    public private(set) ?string $label = null;

    public private(set) ?string $description = null;

    public private(set) ?string $icon = null;

    public private(set) ?int $order = null;

    public private(set) bool $wide = false;

    public private(set) ?Closure $visibleWhen = null;

    public private(set) ?string $ability = null;

    /** @var list<Field> */
    public private(set) array $fields = [];

    /** @var list<self> */
    public private(set) array $groups = [];

    private function __construct(string $name)
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $name) !== 1) {
            throw InvalidPathException::segment($name, 'group');
        }

        $this->name = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /** Translation key; used literally when it does not resolve. */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    /** Render this group's card full-width instead of in the column flow. */
    public function wide(): self
    {
        $this->wide = true;

        return $this;
    }

    public function when(Closure $condition): self
    {
        $this->visibleWhen = $condition;

        return $this;
    }

    public function authorize(string $ability): self
    {
        $this->ability = $ability;

        return $this;
    }

    /**
     * @param  list<Field>  $fields
     */
    public function fields(array $fields): self
    {
        foreach ($fields as $field) {
            $this->fields[] = $field;
        }

        return $this;
    }

    /**
     * @param  list<self>  $groups
     */
    public function groups(array $groups): self
    {
        foreach ($groups as $group) {
            $this->groups[] = $group;
        }

        return $this;
    }

    /** True when this node holds nothing renderable at all. */
    public function isEmpty(): bool
    {
        if ($this->fields !== []) {
            return false;
        }

        foreach ($this->groups as $group) {
            if (! $group->isEmpty()) {
                return false;
            }
        }

        return true;
    }
}
