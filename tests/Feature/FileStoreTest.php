<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Resolution\Overlay;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;
use SashaLenz\SettingsUi\Stores\FileStore;

beforeEach(function (): void {
    $this->file = sys_get_temp_dir().'/settings-'.bin2hex(random_bytes(6)).'/settings.json';

    config()->set('settings.default_store', 'file');
    config()->set('settings.stores.file', ['driver' => 'file', 'path' => $this->file]);
});

afterEach(function (): void {
    @unlink($this->file);
    @rmdir(dirname($this->file));
});

it('round-trips through a json file', function (): void {
    declareSettings(Group::make('selling')->groups([
        Group::make('reservation')->fields([Field::integer('workdays')]),
    ]));

    app(Resolver::class)->put('selling.reservation.workdays', 7);
    app(Overlay::class)->flushAndApply();

    expect(config('selling.reservation.workdays'))->toBe(7)
        ->and(json_decode((string) file_get_contents($this->file), true))
        ->toBe(['selling.reservation.workdays' => 7]);
});

it('starts empty when the file does not exist yet', function (): void {
    expect((new FileStore('/nonexistent/path/settings.json'))->load('selling'))->toBe([]);
});

it('treats an unreadable file as nothing stored rather than crashing', function (): void {
    // Boot on file defaults and let the operator fix the JSON; throwing here
    // would take down every request.
    mkdir(dirname($this->file), 0755, true);
    file_put_contents($this->file, '{ this is not json');

    expect((new FileStore($this->file))->load('selling'))->toBe([]);
});

it('writes atomically and leaves no temp files behind', function (): void {
    $store = new FileStore($this->file);
    $store->put('selling.a', 1);
    $store->put('selling.b', 2);

    $leftovers = glob(dirname($this->file).'/*.tmp') ?: [];

    expect($leftovers)->toBe([])
        ->and($store->load('selling'))->toBe(['selling.a' => 1, 'selling.b' => 2]);
});

it('forgets a path', function (): void {
    $store = new FileStore($this->file);
    $store->put('selling.a', 1);
    $store->forget('selling.a');

    expect($store->get('selling.a'))->toBeNull();
});

it('scopes load to one root group', function (): void {
    $store = new FileStore($this->file);
    $store->put('selling.a', 1);
    $store->put('stock.b', 2);

    expect($store->load('selling'))->toBe(['selling.a' => 1]);
});

it('lists orphans', function (): void {
    $store = new FileStore($this->file);
    $store->put('selling.kept', 1);
    $store->put('selling.gone', 2);

    expect($store->orphans(['selling.kept']))->toBe(['selling.gone']);
});
