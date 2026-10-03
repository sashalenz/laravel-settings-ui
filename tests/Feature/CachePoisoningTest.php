<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Resolution\Overlay;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\Resolution\SettingsCache;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

/**
 * R3 — the regression this package is built around.
 *
 * In the app it was extracted from, snapshots held serialized Eloquent models.
 * A deploy that moved that model class left every cached blob thawing as
 * `__PHP_Incomplete_Class`, which fatalled on the first property access — on
 * every request. `cache:clear` could not recover it, because clearing the cache
 * boots the same provider that crashes. Recovery meant unlinking Redis keys by
 * hand on production.
 *
 * Two independent defences, tested separately, because either alone would be a
 * single point of failure:
 *   1. payloads are primitives, validated on read, so a poisoned entry is a
 *      miss rather than a landmine;
 *   2. keys carry a schema fingerprint, so an entry written for a different
 *      schema is unreachable rather than merely survivable.
 */
beforeEach(function (): void {
    $this->store = arrayStore();
});

it('treats a poisoned snapshot as a miss and rebuilds it', function (): void {
    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    $this->store->put('selling.workdays', 7);

    $key = app(SettingsCache::class)->key('selling', settingsFingerprint());

    // Exactly what a class-move deploy used to leave behind.
    Cache::forever($key, ['selling.workdays' => incompleteObject()]);

    expect(fn () => app(Overlay::class)->apply())->not->toThrow(Throwable::class)
        ->and(config('selling.workdays'))->toBe(7);
});

it('repairs the poisoned entry rather than re-reading it forever', function (): void {
    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    $this->store->put('selling.workdays', 7);

    $key = app(SettingsCache::class)->key('selling', settingsFingerprint());
    Cache::forever($key, ['selling.workdays' => incompleteObject()]);

    app(Resolver::class)->snapshot('selling');

    expect(Cache::get($key))->toBe(['selling.workdays' => 7]);
});

it('rejects any object reaching a payload, not just incomplete ones', function (): void {
    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    $this->store->put('selling.workdays', 7);

    $key = app(SettingsCache::class)->key('selling', settingsFingerprint());
    Cache::forever($key, ['selling.workdays' => new stdClass]);

    expect(app(Resolver::class)->snapshot('selling'))->toBe(['selling.workdays' => 7]);
});

it('caches nothing but primitives', function (): void {
    declareSettings(Group::make('selling')->fields([
        Field::integer('count'),
        Field::keyValue('slots'),
        Field::boolean('flag'),
    ]));

    $this->store->put('selling.count', 3);
    $this->store->put('selling.slots', ['morning' => '09:00']);
    $this->store->put('selling.flag', true);

    app(Resolver::class)->snapshot('selling');

    $payload = Cache::get(app(SettingsCache::class)->key('selling', settingsFingerprint()));

    array_walk_recursive($payload, function (mixed $leaf): void {
        expect(is_object($leaf))->toBeFalse();
    });

    expect($payload)->toBe([
        'selling.count' => 3,
        'selling.slots' => ['morning' => '09:00'],
        'selling.flag' => true,
    ]);
});

it('cannot reach an entry written for a different schema', function (): void {
    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    $this->store->put('selling.workdays', 7);
    app(Resolver::class)->snapshot('selling');

    $oldKey = app(SettingsCache::class)->key('selling', settingsFingerprint());

    // Re-type the field: same path, different schema.
    Settings::registry()->flush();
    declareSettings(Group::make('selling')->fields([Field::text('workdays')]));

    expect(app(SettingsCache::class)->key('selling', settingsFingerprint()))->not->toBe($oldKey);
});

it('bumps the version stamp on every write so workers can notice', function (): void {
    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    $before = app(Resolver::class)->version();

    app(Resolver::class)->put('selling.workdays', 7);

    expect(app(Resolver::class)->version())->toBeGreaterThan($before);
});
