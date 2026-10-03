<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Schema;

/**
 * A field with its place in the tree worked out — the shape everything
 * downstream (stores, resolver, renderer, importer) actually consumes.
 */
final readonly class ResolvedField
{
    public function __construct(
        /** Full dotted path, e.g. "communications-services.chatwoot.inboxes.viber". */
        public string $path,
        /** Root group name — the config group this overlays. */
        public string $root,
        /** Path of the owning group; equals $root for a top-level field. */
        public string $parentPath,
        public Field $field,
        /** Class that declared the root group this field belongs to. */
        public string $source,
    ) {}

    /** Path relative to the root group — what `config()` looks up under it. */
    public function relativePath(): string
    {
        return substr($this->path, strlen($this->root) + 1);
    }

    public function storeName(string $default): string
    {
        return $this->field->store ?? $default;
    }
}
