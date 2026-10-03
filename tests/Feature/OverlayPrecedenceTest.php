<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Resolution\Overlay;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

/**
 * D5: stored value → host's config/{group}.php → declaration ->default() → null.
 *
 * The middle rung is the one that looks like an optimisation and is actually
 * the rule. In the app this package was extracted from, 54 of 95 knobs hold no
 * stored value and ride their file default through exactly this path — if the
 * overlay ever starts writing nulls or eagerly applying declaration defaults,
 * all 54 change value silently on deploy.
 */
beforeEach(function (): void {
    $this->store = arrayStore();
});

it('lets a stored value win over the file default', function (): void {
    config()->set('selling.reservation.workdays', 3);

    declareSettings(Group::make('selling')->groups([
        Group::make('reservation')->fields([Field::integer('workdays')]),
    ]));

    $this->store->put('selling.reservation.workdays', 7);

    app(Overlay::class)->apply();

    expect(config('selling.reservation.workdays'))->toBe(7);
});

it('leaves the file default standing when nothing is stored', function (): void {
    config()->set('selling.reservation.workdays', 3);

    declareSettings(Group::make('selling')->groups([
        Group::make('reservation')->fields([Field::integer('workdays')]),
    ]));

    app(Overlay::class)->apply();

    expect(config('selling.reservation.workdays'))->toBe(3);
});

it('does not let a declaration default trample the file default', function (): void {
    config()->set('selling.reservation.workdays', 3);

    declareSettings(Group::make('selling')->groups([
        Group::make('reservation')->fields([Field::integer('workdays')->default(99)]),
    ]));

    app(Overlay::class)->apply();

    expect(config('selling.reservation.workdays'))->toBe(3);
});

it('applies the declaration default only where the host has no config key', function (): void {
    // The case this rung exists for: a package ships settings into a host that
    // never created a config file for that group.
    declareSettings(Group::make('acme-package')->fields([
        Field::integer('timeout')->default(30),
    ]));

    app(Overlay::class)->apply();

    expect(config('acme-package.timeout'))->toBe(30);
});

it('resolves to null when there is no value, no file default and no declaration default', function (): void {
    declareSettings(Group::make('acme-package')->fields([Field::text('token')]));

    app(Overlay::class)->apply();

    expect(config('acme-package.token'))->toBeNull();
});

it('never writes a null over an existing config key', function (): void {
    config()->set('selling.mode', 'file');

    declareSettings(Group::make('selling')->fields([Field::text('mode')]));

    $this->store->put('selling.mode', null);

    app(Overlay::class)->apply();

    expect(config('selling.mode'))->toBe('file');
});

it('overlays nested groups at their full dotted path', function (): void {
    declareSettings(Group::make('communications-services')->groups([
        Group::make('chatwoot')->groups([
            Group::make('inboxes')->fields([Field::integer('viber')]),
        ]),
    ]));

    $this->store->put('communications-services.chatwoot.inboxes.viber', 12);

    app(Overlay::class)->apply();

    expect(config('communications-services.chatwoot.inboxes.viber'))->toBe(12);
});

it('casts to the type the declaration names', function (): void {
    declareSettings(Group::make('selling')->fields([
        Field::integer('count'),
        Field::boolean('flag'),
        Field::decimal('rate'),
        Field::text('label'),
    ]));

    $this->store->put('selling.count', '42');
    $this->store->put('selling.flag', 1);
    $this->store->put('selling.rate', '0.5');
    $this->store->put('selling.label', 7);

    app(Overlay::class)->apply();

    expect(config('selling.count'))->toBe(42)
        ->and(config('selling.flag'))->toBeTrue()
        ->and(config('selling.rate'))->toBe(0.5)
        ->and(config('selling.label'))->toBe('7');
});

it('distinguishes stored from defaulted', function (): void {
    declareSettings(Group::make('selling')->fields([
        Field::integer('stored'),
        Field::integer('defaulted')->default(5),
    ]));

    $this->store->put('selling.stored', 1);

    $resolver = app(Resolver::class);

    expect($resolver->has('selling.stored'))->toBeTrue()
        ->and($resolver->has('selling.defaulted'))->toBeFalse();
});

it('keeps the rest of a group overlaying when one value is unreadable', function (): void {
    // Ciphertext that no longer decrypts (rotated APP_KEY, copied row). The
    // field falls back to its file default; its neighbours must not.
    config()->set('selling.secret', 'from-file');

    declareSettings(Group::make('selling')->fields([
        Field::text('secret')->encrypted(),
        Field::integer('other'),
    ]));

    $this->store->put('selling.secret', 'not-actually-ciphertext');
    $this->store->put('selling.other', 5);

    app(Overlay::class)->apply();

    expect(config('selling.secret'))->toBe('from-file')
        ->and(config('selling.other'))->toBe(5);
});

it('round-trips an encrypted value', function (): void {
    declareSettings(Group::make('selling')->fields([Field::text('token')->encrypted()]));

    app(Resolver::class)->put('selling.token', 'super-secret');

    expect($this->store->get('selling.token'))->not->toBe('super-secret');

    app(Overlay::class)->apply();

    expect(config('selling.token'))->toBe('super-secret');
});

it('boots on file defaults when the database is unreachable', function (): void {
    config()->set('selling.workdays', 3);
    config()->set('settings.stores.database', ['driver' => 'database', 'table' => 'no_such_table']);
    config()->set('settings.default_store', 'database');

    Settings::declare(Group::make('selling')->fields([Field::integer('workdays')]));

    app(Overlay::class)->apply();

    expect(config('selling.workdays'))->toBe(3);
});
