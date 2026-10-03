<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use SashaLenz\SettingsUi\Contracts\SettingsAuthorizer;
use SashaLenz\SettingsUi\Models\SettingSchemaRow;

/**
 * Overview table listing all root settings groups and key counts.
 * Independent of proprietary wiretables packages.
 */
class SettingsTable extends Component
{
    public string $search = '';

    public function mount(): void
    {
        abort_unless(app(SettingsAuthorizer::class)->canView(auth()->user()), 403);
    }

    public function render(): View
    {
        $query = SettingSchemaRow::query()
            ->where('kind', 'root')
            ->orderBy('order')
            ->orderBy('label_key');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('label_key', 'like', "%{$this->search}%")
                    ->orWhere('path', 'like', "%{$this->search}%");
            });
        }

        $rows = $query->paginate(25);
        $layout = config('settings.ui.layout', 'settings::layouts.app');

        $view = view('settings::index', [
            'rows' => $rows,
            'title' => __('settings::settings.title'),
        ]);

        return $layout ? $view->layout($layout) : $view;
    }
}
