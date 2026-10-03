<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use SashaLenz\SettingsUi\Resolution\Overlay;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;
use SashaLenz\SettingsUi\Stores\DatabaseStore;

beforeEach(function (): void {
    $this->runPackageMigrations();

    $this->store = new DatabaseStore(DB::connection());
});

it('round-trips a value', function (): void {
    $this->store->put('selling.workdays', 7);

    expect($this->store->get('selling.workdays'))->toBe(7);
});

it('stores json so structured values survive', function (): void {
    $this->store->put('delivery-services.local-delivery.slots', ['morning' => '09:00', 'evening' => '18:00']);

    expect($this->store->get('delivery-services.local-delivery.slots'))
        ->toBe(['morning' => '09:00', 'evening' => '18:00']);
});

it('loads a whole group in one query', function (): void {
    $this->store->put('selling.a', 1);
    $this->store->put('selling.b', 2);
    $this->store->put('stock.c', 3);

    DB::enableQueryLog();
    $loaded = $this->store->load('selling');
    DB::disableQueryLog();

    expect($loaded)->toBe(['selling.a' => 1, 'selling.b' => 2])
        ->and(DB::getQueryLog())->toHaveCount(1);
});

it('derives the group column from the path root', function (): void {
    $this->store->put('communications-services.chatwoot.inboxes.viber', 12);

    expect(DB::table('settings')->value('group'))->toBe('communications-services');
});

it('updates in place rather than inserting a duplicate', function (): void {
    $this->store->put('selling.workdays', 7);
    $this->store->put('selling.workdays', 9);

    expect(DB::table('settings')->count())->toBe(1)
        ->and($this->store->get('selling.workdays'))->toBe(9);
});

it('returns null for a path it does not hold', function (): void {
    expect($this->store->get('selling.nothing'))->toBeNull();
});

it('omits missing paths from load rather than padding with nulls', function (): void {
    // A padded null would look like a stored value and stop the overlay from
    // falling through to the host's file default.
    $this->store->put('selling.a', 1);

    expect($this->store->load('selling'))->toBe(['selling.a' => 1]);
});

it('lists orphans left behind by a deleted declaration', function (): void {
    $this->store->put('selling.kept', 1);
    $this->store->put('selling.retired', 2);
    $this->store->put('stock.gone', 3);

    expect($this->store->orphans(['selling.kept']))->toBe(['selling.retired', 'stock.gone']);
});

it('prunes many orphans in one statement', function (): void {
    $this->store->put('selling.a', 1);
    $this->store->put('selling.b', 2);
    $this->store->put('selling.c', 3);

    expect($this->store->forgetMany(['selling.a', 'selling.b']))->toBe(2)
        ->and(DB::table('settings')->count())->toBe(1);
});

it('forgets a path', function (): void {
    $this->store->put('selling.workdays', 7);
    $this->store->forget('selling.workdays');

    expect($this->store->get('selling.workdays'))->toBeNull();
});

it('drops the row instead of storing null when a value is cleared', function (): void {
    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    app(Resolver::class)->put('selling.workdays', 7);
    app(Resolver::class)->put('selling.workdays', null);

    // A null row would read back as "stored", shadowing the file default.
    expect(DB::table('settings')->count())->toBe(0);
});

it('overlays end to end through the real table', function (): void {
    config()->set('selling.reservation.workdays', 3);

    declareSettings(Group::make('selling')->groups([
        Group::make('reservation')->fields([Field::integer('workdays')]),
    ]));

    app(Overlay::class)->apply();
    expect(config('selling.reservation.workdays'))->toBe(3);

    app(Resolver::class)->put('selling.reservation.workdays', 7);
    app(Overlay::class)->flushAndApply();

    expect(config('selling.reservation.workdays'))->toBe(7);
});

it('picks up a change made by another process on refresh', function (): void {
    // The queue-worker case: a long-lived process holding a boot-time overlay
    // while an operator saves from the web UI.
    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    app(Resolver::class)->put('selling.workdays', 3);
    app(Overlay::class)->apply();
    expect(config('selling.workdays'))->toBe(3);

    $this->store->put('selling.workdays', 9);

    app(Overlay::class)->flushAndApply();

    expect(config('selling.workdays'))->toBe(9);
});
