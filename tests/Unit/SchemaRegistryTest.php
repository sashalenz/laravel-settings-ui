<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Exceptions\InvalidPathException;
use SashaLenz\SettingsUi\Exceptions\SchemaConflictException;
use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

it('composes a full dotted path from nested groups', function (): void {
    Settings::declare(
        Group::make('communications-services')->groups([
            Group::make('chatwoot')
                ->fields([Field::boolean('enabled')])
                ->groups([
                    Group::make('inboxes')->fields([Field::integer('viber')]),
                ]),
        ])
    );

    expect(Settings::paths())->toBe([
        'communications-services.chatwoot.enabled',
        'communications-services.chatwoot.inboxes.viber',
    ]);
});

it('keeps a20 config paths byte-identical to the flat keys they replace', function (): void {
    // The migration premise: nesting is free because the structure was already
    // in the dotted keys. If this ever drifts, every consumer config() call in
    // the host breaks silently.
    Settings::declare(
        Group::make('order-intake-services')->groups([
            Group::make('avto_pro')
                ->fields([Field::text('base_url')])
                ->groups([
                    Group::make('pull')->fields([Field::boolean('dry_run')]),
                ]),
        ])
    );

    expect(Settings::declared('order-intake-services.avto_pro.base_url'))->toBeTrue()
        ->and(Settings::declared('order-intake-services.avto_pro.pull.dry_run'))->toBeTrue();
});

it('records root, parent and relative path on every field', function (): void {
    Settings::declare(
        Group::make('selling')->groups([
            Group::make('reservation')->fields([Field::integer('workdays')]),
        ])
    );

    $field = Settings::field('selling.reservation.workdays');

    expect($field)->not->toBeNull()
        ->and($field->root)->toBe('selling')
        ->and($field->parentPath)->toBe('selling.reservation')
        ->and($field->relativePath())->toBe('reservation.workdays');
});

it('orders by declaration when no order is given', function (): void {
    Settings::declare(
        Group::make('stock')->fields([
            Field::integer('c'),
            Field::integer('a'),
            Field::integer('b'),
        ])
    );

    expect(array_map(
        fn ($field) => $field->field->key,
        Settings::group('stock')->fields,
    ))->toBe(['c', 'a', 'b']);
});

it('puts explicitly ordered items first, unordered after in declaration order', function (): void {
    Settings::declare(
        Group::make('stock')->fields([
            Field::integer('c'),
            Field::integer('a')->order(0),
            Field::integer('b'),
        ])
    );

    expect(array_map(
        fn ($field) => $field->field->key,
        Settings::group('stock')->fields,
    ))->toBe(['a', 'c', 'b']);
});

it('sorts several explicitly ordered items among themselves', function (): void {
    Settings::declare(
        Group::make('stock')->fields([
            Field::integer('c')->order(20),
            Field::integer('a')->order(10),
            Field::integer('b'),
        ])
    );

    expect(array_map(
        fn ($field) => $field->field->key,
        Settings::group('stock')->fields,
    ))->toBe(['a', 'c', 'b']);
});

it('pins a position when two declarations merge into one group', function (): void {
    // Provider boot order decides who declares first, so ->order() is the only
    // way for a host to place its own field above a package's.
    Settings::declare(Group::make('comms')->fields([Field::text('from_package')]));
    Settings::declare(Group::make('comms')->fields([Field::text('from_app')->order(0)]));

    expect(array_map(
        fn ($field) => $field->field->key,
        Settings::group('comms')->fields,
    ))->toBe(['from_app', 'from_package']);
});

it('orders nested groups the same way as fields', function (): void {
    Settings::declare(
        Group::make('comms')->groups([
            Group::make('olx'),
            Group::make('chatwoot')->order(0),
            Group::make('ria'),
        ])
    );

    expect(array_map(
        fn ($group) => $group->group->name,
        Settings::group('comms')->groups,
    ))->toBe(['chatwoot', 'olx', 'ria']);
});

it('resolves nesting depth for the renderer', function (): void {
    Settings::declare(
        Group::make('a')->groups([
            Group::make('b')->groups([
                Group::make('c')->fields([Field::text('d')]),
            ]),
        ])
    );

    $root = Settings::group('a');

    expect($root->depth)->toBe(0)
        ->and($root->groups[0]->depth)->toBe(1)
        ->and($root->groups[0]->groups[0]->depth)->toBe(2)
        ->and($root->fieldCount())->toBe(1);
});

it('rejects a dotted field key and says how to nest instead', function (): void {
    expect(fn () => Field::text('chatwoot.enabled'))
        ->toThrow(InvalidPathException::class, 'Nesting is expressed with nested groups');
});

it('rejects a dotted group name', function (): void {
    expect(fn () => Group::make('a.b'))->toThrow(InvalidPathException::class);
});

it('rejects a segment that is not a usable path token', function (): void {
    expect(fn () => Field::text('Foo Bar'))->toThrow(InvalidPathException::class);
});

it('accepts dashes in a root group name', function (): void {
    // a20 has nova-poshta-api, order-intake-services, marketplace-services.
    expect(Group::make('nova-poshta-api')->name)->toBe('nova-poshta-api');
});

it('throws when two declarations claim the same field path', function (): void {
    Settings::declare(Group::make('selling')->fields([Field::integer('a')]), 'App\\First');
    Settings::declare(Group::make('selling')->fields([Field::integer('a')]), 'Vendor\\Second');

    expect(fn () => Settings::paths())
        ->toThrow(SchemaConflictException::class, 'App\\First');
});

it('throws when a path is both a group and a field', function (): void {
    Settings::declare(
        Group::make('selling')
            ->fields([Field::text('reservation')])
            ->groups([Group::make('reservation')->fields([Field::integer('workdays')])])
    );

    expect(fn () => Settings::paths())->toThrow(SchemaConflictException::class);
});

it('merges two declarations of the same root group instead of overwriting', function (): void {
    Settings::declare(Group::make('selling')->label('a')->fields([Field::integer('one')]));
    Settings::declare(Group::make('selling')->fields([Field::integer('two')]));

    expect(Settings::paths())->toBe(['selling.one', 'selling.two'])
        ->and(Settings::group('selling')->group->label)->toBe('a');
});

it('merges nested groups by name across declarations', function (): void {
    Settings::declare(
        Group::make('comms')->groups([Group::make('chatwoot')->fields([Field::boolean('enabled')])])
    );
    Settings::declare(
        Group::make('comms')->groups([Group::make('chatwoot')->fields([Field::text('token')])])
    );

    expect(Settings::paths())->toBe(['comms.chatwoot.enabled', 'comms.chatwoot.token'])
        ->and(Settings::group('comms')->groups)->toHaveCount(1);
});

it('reports where each root group came from', function (): void {
    Settings::declare(Group::make('selling')->fields([Field::integer('a')]), 'acme/foo');

    expect(Settings::provenance())->toBe([['source' => 'acme/foo', 'root' => 'selling']]);
});
