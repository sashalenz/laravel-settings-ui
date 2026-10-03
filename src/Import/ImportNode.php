<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Import;

/**
 * A node in the tree recovered from a flat table's dotted keys.
 *
 * This is where the migration actually happens: `chatwoot.inboxes.viber` was
 * always a three-level structure, it just had nowhere to live. Splitting the
 * key turns it into groups the UI can card up and order, without a single
 * config() call site changing — the resolved path is identical either way.
 */
final class ImportNode
{
    /** @var array<string, self> child groups, keyed by segment */
    public array $children = [];

    /** @var list<LegacyRow> fields directly on this node */
    public array $rows = [];

    public function __construct(
        public readonly string $name,
        public readonly string $path,
    ) {}

    public function child(string $segment): self
    {
        return $this->children[$segment] ??= new self($segment, $this->path.'.'.$segment);
    }

    public function add(LegacyRow $row): void
    {
        $this->rows[] = $row;
    }

    /** Lowest sort_order anywhere beneath this node — used to order the group itself. */
    public function order(): int
    {
        $orders = array_map(static fn (LegacyRow $row): int => $row->order, $this->rows);

        foreach ($this->children as $child) {
            $orders[] = $child->order();
        }

        return $orders === [] ? 0 : min($orders);
    }

    /** @return list<self> children ordered the way their contents are */
    public function orderedChildren(): array
    {
        $children = array_values($this->children);

        usort($children, static fn (self $a, self $b): int => [$a->order(), $a->name] <=> [$b->order(), $b->name]);

        return $children;
    }

    /** @return list<LegacyRow> */
    public function orderedRows(): array
    {
        $rows = $this->rows;

        usort($rows, static fn (LegacyRow $a, LegacyRow $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        return $rows;
    }

    public function fieldCount(): int
    {
        $count = count($this->rows);

        foreach ($this->children as $child) {
            $count += $child->fieldCount();
        }

        return $count;
    }
}
