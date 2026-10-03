<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

/**
 * The fingerprint is what makes cache keys self-invalidating. If it stops
 * moving when the schema moves, a deploy can read back a snapshot describing
 * settings that no longer exist — the failure mode that bricked a deploy in the
 * host app it was extracted from.
 */
it('is stable for an unchanged schema', function (): void {
    Settings::declare(Group::make('selling')->fields([Field::integer('a')]));

    $first = Settings::schema()->fingerprint();

    Settings::registry()->flush();
    Settings::declare(Group::make('selling')->fields([Field::integer('a')]));

    expect(Settings::schema()->fingerprint())->toBe($first);
});

it('ignores declaration order', function (): void {
    Settings::declare(Group::make('a')->fields([Field::integer('x')]));
    Settings::declare(Group::make('b')->fields([Field::integer('y')]));
    $first = Settings::schema()->fingerprint();

    Settings::registry()->flush();
    Settings::declare(Group::make('b')->fields([Field::integer('y')]));
    Settings::declare(Group::make('a')->fields([Field::integer('x')]));

    expect(Settings::schema()->fingerprint())->toBe($first);
});

it('moves when a field is added', function (): void {
    Settings::declare(Group::make('selling')->fields([Field::integer('a')]));
    $first = Settings::schema()->fingerprint();

    Settings::registry()->flush();
    Settings::declare(Group::make('selling')->fields([Field::integer('a'), Field::integer('b')]));

    expect(Settings::schema()->fingerprint())->not->toBe($first);
});

it('moves when a field changes type', function (): void {
    Settings::declare(Group::make('selling')->fields([Field::integer('a')]));
    $first = Settings::schema()->fingerprint();

    Settings::registry()->flush();
    Settings::declare(Group::make('selling')->fields([Field::text('a')]));

    expect(Settings::schema()->fingerprint())->not->toBe($first);
});

it('moves when a field is re-homed into a nested group', function (): void {
    Settings::declare(Group::make('selling')->fields([Field::integer('workdays')]));
    $first = Settings::schema()->fingerprint();

    Settings::registry()->flush();
    Settings::declare(
        Group::make('selling')->groups([
            Group::make('reservation')->fields([Field::integer('workdays')]),
        ])
    );

    expect(Settings::schema()->fingerprint())->not->toBe($first);
});

it('moves when a field switches store or becomes a secret', function (): void {
    Settings::declare(Group::make('edge-server')->fields([Field::text('token')]));
    $first = Settings::schema()->fingerprint();

    Settings::registry()->flush();
    Settings::declare(Group::make('edge-server')->fields([
        Field::text('token')->at('a20/settings', 'edge_git_token'),
    ]));

    expect(Settings::schema()->fingerprint())->not->toBe($first);
});
