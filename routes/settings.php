<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SashaLenz\SettingsUi\Http\Livewire\SettingSchemaTable;
use SashaLenz\SettingsUi\Http\Livewire\SettingsGroupForm;
use SashaLenz\SettingsUi\Http\Livewire\SettingsHistoryTable;
use SashaLenz\SettingsUi\Http\Livewire\SettingsTable;
use SashaLenz\SettingsUi\Support\Routes;

/*
|--------------------------------------------------------------------------
| Settings routes
|--------------------------------------------------------------------------
|
| NOT loaded by the service provider. Mount them yourself:
|
|     Route::prefix('control')->middleware(['web', 'auth:admin'])->group(
|         fn () => Settings::routes(),
|     );
|
| Names are relative; the enclosing group supplies the prefix.
|
*/

$definitions = [
    'index' => Route::get('/', SettingsTable::class)->name('index'),
    'history' => Route::get('history', SettingsHistoryTable::class)->name('history'),
    'schema' => Route::get('schema', SettingSchemaTable::class)->name('schema'),

    // Dashes are deliberate: config group names routinely carry them
    // (nova-poshta-api, marketplace-services, order-intake-services).
    'group' => Route::get('{group}', SettingsGroupForm::class)
        ->where('group', '[a-z][a-z0-9_-]*')
        ->name('group'),
];

// Read back the name Laravel actually assigned — it includes whatever `as`
// prefix the host's route group applied — so links resolve without guessing.
foreach ($definitions as $relative => $route) {
    Routes::register($relative, (string) $route->getName());
}
