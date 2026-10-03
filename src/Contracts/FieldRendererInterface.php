<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Contracts;

use SashaLenz\SettingsUi\Schema\ResolvedField;

/**
 * Contract for rendering a declared setting field to HTML.
 *
 * Decouples the form rendering from any specific UI framework
 * (Native Tailwind Blade, Livewire, Filament, Wireforms, etc.).
 */
interface FieldRendererInterface
{
    /**
     * Render the field to HTML string.
     *
     * @param  ResolvedField  $resolved  The schema definition of the field
     * @param  string  $wireModel  The Livewire property binding path, e.g. 'form.data.chatwoot.enabled'
     * @param  mixed  $currentValue  The current value of the field
     */
    public function render(ResolvedField $resolved, string $wireModel, mixed $currentValue = null): string;

    /**
     * Whether this renderer knows how to render the given field type.
     */
    public function supports(string $type): bool;
}
