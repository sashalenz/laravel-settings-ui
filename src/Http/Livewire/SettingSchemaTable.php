<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use SashaLenz\SettingsUi\Contracts\SettingsAuthorizer;
use SashaLenz\SettingsUi\Models\SettingSchemaRow;

/**
 * Diagnostic table of all declared setting paths and metadata.
 * Independent of proprietary wiretables packages.
 */
class SettingSchemaTable extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(app(SettingsAuthorizer::class)->canView(auth()->user()), 403);
    }

    public function render(): View
    {
        $query = SettingSchemaRow::query()
            ->fields()
            ->orderBy('path');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('path', 'like', "%{$this->search}%")
                    ->orWhere('source', 'like', "%{$this->search}%")
                    ->orWhere('type', 'like', "%{$this->search}%");
            });
        }

        $fields = $query->paginate(30);
        $layout = config('settings.ui.layout', 'settings::layouts.app');

        $view = view('settings::schema', [
            'fields' => $fields,
            'title' => __('settings::settings.schema.title'),
        ]);

        return $layout ? $view->layout($layout) : $view;
    }
}
