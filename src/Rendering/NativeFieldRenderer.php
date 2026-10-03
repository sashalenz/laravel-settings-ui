<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Rendering;

use Closure;
use Illuminate\Support\Facades\View;
use SashaLenz\SettingsUi\Contracts\FieldRendererInterface;
use SashaLenz\SettingsUi\Schema\FieldType;
use SashaLenz\SettingsUi\Schema\ResolvedField;

/**
 * Native Blade + Tailwind CSS field renderer.
 * Zero proprietary dependencies.
 */
final class NativeFieldRenderer implements FieldRendererInterface
{
    /** @var array<string, Closure(ResolvedField, string, mixed): string> */
    private array $custom = [];

    public function register(string $type, Closure $renderer): void
    {
        $this->custom[$type] = $renderer;
    }

    public function supports(string $type): bool
    {
        return isset($this->custom[$type]) || FieldType::tryFrom($type) !== null;
    }

    public function render(ResolvedField $resolved, string $wireModel, mixed $currentValue = null): string
    {
        $type = $resolved->field->type;

        if (isset($this->custom[$type])) {
            return ($this->custom[$type])($resolved, $wireModel, $currentValue);
        }

        return View::make('settings::fields.default', [
            'resolved' => $resolved,
            'wireModel' => $wireModel,
            'currentValue' => $currentValue,
        ])->render();
    }
}
