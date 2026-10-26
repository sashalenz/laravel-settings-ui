<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use SashaLenz\SettingsUi\Contracts\SettingsAuthorizer;
use SashaLenz\SettingsUi\Http\Livewire\SettingsGroupForm;
use SashaLenz\SettingsUi\Models\SettingChange;
use SashaLenz\SettingsUi\Resolution\Overlay;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

beforeEach(function (): void {
    $this->runPackageMigrations();
    $this->store = arrayStore();
    $authorizer = Mockery::mock(SettingsAuthorizer::class);
    $authorizer->shouldReceive('canView', 'canManage')->andReturn(true);
    app()->instance(SettingsAuthorizer::class, $authorizer);
});

it('saves edited data and records history', function (): void {
    declareSettings(Group::make('site')->fields([Field::text('title')]));
    $this->store->put('site.title', 'Old');
    Livewire::test(SettingsGroupForm::class, ['group' => 'site'])
        ->set('data.title', 'New')->call('save')->assertHasNoErrors();
    expect($this->store->get('site.title'))->toBe('New');
    expect(SettingChange::first()->old_value)->toBe('Old')
        ->and(SettingChange::first()->new_value)->toBe('New');
});

it('preserves untouched stored fields', function (): void {
    declareSettings(Group::make('site')->fields([Field::text('title'), Field::text('other')]));
    $this->store->put('site.title', 'Old');
    $this->store->put('site.other', 'Keep');
    Livewire::test(SettingsGroupForm::class, ['group' => 'site'])
        ->set('data.title', 'New')->call('save')->assertHasNoErrors();
    expect($this->store->get('site.other'))->toBe('Keep');
    expect(SettingChange::count())->toBe(1);
});

it('adds removes reorders and saves collection rows', function (bool $objects): void {
    $field = $objects
        ? Field::repeater('items')->schema([Field::text('label')->rules('required|string')])
        : Field::list('items');
    declareSettings(Group::make('site')->groups([Group::make('links')->fields([$field])]));
    $a = $objects ? ['label' => 'A'] : 'A';
    $b = $objects ? ['label' => 'B'] : 'B';
    $test = Livewire::test(SettingsGroupForm::class, ['group' => 'site'])
        ->call('addRow', 'links.items')->set('data.links.items.0', $a)
        ->call('addRow', 'links.items')->set('data.links.items.1', $b)
        ->call('moveRow', 'links.items', 1, 0)
        ->assertSet('data.links.items', [$b, $a])
        ->call('removeRow', 'links.items', 1)
        ->assertSet('data.links.items', [$b])
        ->call('save')->assertHasNoErrors();
    expect($this->store->get('site.links.items'))->toBe([$b]);
    expect(SettingChange::first()->new_value)->toBe([$b]);
    app(Overlay::class)->apply();
    expect(config('site.links.items'))->toBe([$b]);
    view()->share('errors', new ViewErrorBag);
    expect($test->instance()->renderField('form.data.links.items'))->toContain('data.links.items.0');
})->with([false, true]);

it('validates nested fields and collection limits', function (): void {
    declareSettings(Group::make('site')->fields([
        Field::repeater('links')->minItems(1)->maxItems(2)->schema([
            Field::text('label')->rules('required|string'),
            Field::text('url')->rules('required|url'),
        ]),
    ]));
    Livewire::test(SettingsGroupForm::class, ['group' => 'site'])
        ->call('save')->assertHasErrors(['data.links' => 'min'])
        ->call('addRow', 'links')
        ->call('save')->assertHasErrors(['data.links.0.label' => 'required', 'data.links.0.url' => 'required'])
        ->set('data.links.0.url', 'bad')->call('save')->assertHasErrors(['data.links.0.url' => 'url'])
        ->call('removeRow', 'links', 0)->assertCount('data.links', 1)
        ->call('addRow', 'links')->call('addRow', 'links')->assertCount('data.links', 2)
        ->set('data.links', [[], [], []])->call('save')->assertHasErrors(['data.links' => 'max']);
    expect(SettingChange::count())->toBe(0);
});

it('rejects non-string list entries and invalid row action paths', function (): void {
    declareSettings(Group::make('site')->fields([Field::list('tags'), Field::text('title')]));
    Livewire::test(SettingsGroupForm::class, ['group' => 'site'])
        ->set('data.tags', [['bad']])->call('save')->assertHasErrors(['data.tags.0' => 'string']);
    Livewire::test(SettingsGroupForm::class, ['group' => 'site'])->call('addRow', 'title')->assertNotFound();
});
