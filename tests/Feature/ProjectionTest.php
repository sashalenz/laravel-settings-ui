<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Models\SettingSchemaRow;
use SashaLenz\SettingsUi\Projection\SchemaProjector;
use SashaLenz\SettingsUi\Resolution\Overlay;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

beforeEach(function (): void {
    $this->runPackageMigrations();
    $this->store = arrayStore();
});

it('projects groups and fields with their place in the tree', function (): void {
    declareSettings(Group::make('comms')->label('l.comms')->icon('chat')->groups([
        Group::make('chatwoot')->groups([
            Group::make('inboxes')->fields([Field::integer('viber')]),
        ]),
    ]));

    $counts = app(SchemaProjector::class)->project();

    expect($counts)->toBe(['groups' => 3, 'fields' => 1]);

    $viber = SettingSchemaRow::where('path', 'comms.chatwoot.inboxes.viber')->first();

    expect($viber->is_group)->toBeFalse()
        ->and($viber->root)->toBe('comms')
        ->and($viber->parent_path)->toBe('comms.chatwoot.inboxes')
        ->and($viber->depth)->toBe(3);

    $root = SettingSchemaRow::roots()->first();

    expect($root->path)->toBe('comms')
        ->and($root->icon)->toBe('chat')
        ->and($root->field_count)->toBe(1);
});

it('rebuilds wholesale so a removed declaration disappears', function (): void {
    declareSettings(Group::make('selling')->fields([Field::integer('gone'), Field::integer('kept')]));
    app(SchemaProjector::class)->project();

    expect(SettingSchemaRow::count())->toBe(3);

    Settings::registry()->flush();
    declareSettings(Group::make('selling')->fields([Field::integer('kept')]));
    app(SchemaProjector::class)->project();

    expect(SettingSchemaRow::fields()->pluck('path')->all())->toBe(['selling.kept']);
});

it('records which class declared each row', function (): void {
    // The first question when a group nobody recognises shows up in the UI.
    declareSettings(Group::make('acme')->fields([Field::text('a')]));
    app(SchemaProjector::class)->project();

    expect(SettingSchemaRow::where('path', 'acme.a')->value('source'))->toContain('acme');
});

it('never feeds resolution — a stale projection cannot change a value', function (): void {
    config()->set('selling.workdays', 3);

    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));
    $this->store->put('selling.workdays', 7);

    app(SchemaProjector::class)->project();

    // Poison the projection as badly as possible.
    DB::table('setting_schema')->delete();
    DB::table('setting_schema')->insert([
        'path' => 'selling.nonsense', 'root' => 'selling', 'is_group' => false,
        'depth' => 1, 'sort_order' => 0, 'field_count' => 0,
    ]);

    app(Overlay::class)->flushAndApply();

    expect(config('selling.workdays'))->toBe(7);
});

it('is safe to truncate and rebuild', function (): void {
    declareSettings(Group::make('selling')->fields([Field::integer('a')]));

    app(SchemaProjector::class)->project();
    DB::table('setting_schema')->delete();
    $counts = app(SchemaProjector::class)->project();

    expect($counts['fields'])->toBe(1);
});
