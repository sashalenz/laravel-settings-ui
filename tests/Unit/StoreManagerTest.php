<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Contracts\SettingsStore;
use SashaLenz\SettingsUi\Exceptions\StoreNotWritableException;
use SashaLenz\SettingsUi\Exceptions\UnknownStoreException;
use SashaLenz\SettingsUi\Stores\ArrayStore;
use SashaLenz\SettingsUi\Stores\NullStore;
use SashaLenz\SettingsUi\Stores\StoreManager;

it('resolves the configured default', function (): void {
    config()->set('settings.default_store', 'array');

    expect(app(StoreManager::class)->store())->toBeInstanceOf(ArrayStore::class);
});

it('follows a config change made after it was constructed', function (): void {
    // The manager is built during boot, well before anything can express a
    // preference — freezing the default at construction would make the answer
    // depend on container timing.
    $manager = app(StoreManager::class);

    config()->set('settings.default_store', 'null');

    expect($manager->store())->toBeInstanceOf(NullStore::class);
});

it('names an unknown store and lists what does exist', function (): void {
    expect(fn () => app(StoreManager::class)->store('nope'))
        ->toThrow(UnknownStoreException::class, 'database');
});

it('lets a host register its own driver', function (): void {
    $manager = app(StoreManager::class);

    $manager->extend('memory', fn (): SettingsStore => new ArrayStore(['selling.a' => 1]));
    $manager->configure('scratch', ['driver' => 'memory']);

    expect($manager->store('scratch')->get('selling.a'))->toBe(1);
});

it('reuses one instance per store name', function (): void {
    $manager = app(StoreManager::class);

    expect($manager->store('array'))->toBe($manager->store('array'));
});

it('refuses to write to the null store rather than pretending', function (): void {
    // Telling an operator "saved" when nothing was saved is worse than failing.
    expect(fn () => (new NullStore)->put('selling.a', 1))
        ->toThrow(StoreNotWritableException::class);
});

it('reports whether a store can be written to', function (): void {
    expect((new NullStore)->writable())->toBeFalse()
        ->and((new ArrayStore)->writable())->toBeTrue();
});

it('scopes an array store load to one root group', function (): void {
    $store = new ArrayStore(['selling.a' => 1, 'selling.b' => 2, 'stock.c' => 3]);

    expect($store->load('selling'))->toBe(['selling.a' => 1, 'selling.b' => 2]);
});
