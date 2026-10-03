<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use SashaLenz\SettingsUi\Contracts\SettingsAuthorizer;
use SashaLenz\SettingsUi\History\ChangeRecorder;
use SashaLenz\SettingsUi\Models\SettingChange;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\SettingsManager;

/**
 * Audit change history table with revert capabilities.
 * Independent of proprietary wiretables packages.
 */
class SettingsHistoryTable extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(app(SettingsAuthorizer::class)->canViewHistory(auth()->user()), 403);
    }

    public function revert(int $changeId): void
    {
        abort_unless(app(SettingsAuthorizer::class)->canManage(auth()->user()), 403);

        $change = SettingChange::find($changeId);
        if (! $change) {
            return;
        }

        $field = app(SettingsManager::class)->field($change->path);
        if ($field === null) {
            session()->flash('error', "Setting [{$change->path}] is no longer declared.");

            return;
        }

        if ($change->redacted) {
            session()->flash('error', 'Redacted secret values cannot be automatically reverted.');

            return;
        }

        $current = app(Resolver::class)->get($change->path);
        app(Resolver::class)->put($change->path, $change->old_value);
        app(ChangeRecorder::class)->record($field, $current, $change->old_value, 'revert');

        session()->flash('status', "Reverted [{$change->path}] successfully.");
    }

    public function render(): View
    {
        $query = SettingChange::query()->latest('id');

        if ($this->search !== '') {
            $query->where('path', 'like', "%{$this->search}%");
        }

        $changes = $query->paginate(20);
        $layout = config('settings.ui.layout', 'settings::layouts.app');

        $view = view('settings::history', [
            'changes' => $changes,
            'title' => __('settings::settings.history.title'),
        ]);

        return $layout ? $view->layout($layout) : $view;
    }
}
