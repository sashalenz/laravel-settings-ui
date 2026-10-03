<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Discovery\PackageSchemaDiscovery;

function fakeVendor(array $packages): string
{
    $vendor = sys_get_temp_dir().'/settings-discovery-'.bin2hex(random_bytes(6));

    mkdir($vendor.'/composer', 0777, true);
    file_put_contents(
        $vendor.'/composer/installed.json',
        json_encode(['packages' => $packages], JSON_THROW_ON_ERROR),
    );

    return $vendor;
}

it('finds schemas advertised through composer extra', function (): void {
    $vendor = fakeVendor([
        ['name' => 'acme/foo', 'extra' => ['laravel-settings' => ['schemas' => ['Acme\\Foo\\FooSettings']]]],
        ['name' => 'acme/bar'],
    ]);

    expect((new PackageSchemaDiscovery($vendor))->discover())
        ->toBe(['acme/foo' => ['Acme\\Foo\\FooSettings']]);
});

it('honours dont_discover', function (): void {
    $vendor = fakeVendor([
        ['name' => 'acme/foo', 'extra' => ['laravel-settings' => ['schemas' => ['Acme\\Foo\\FooSettings']]]],
    ]);

    expect((new PackageSchemaDiscovery($vendor))->discover(['acme/foo']))->toBe([]);
});

it('degrades to nothing when there is no installed.json', function (): void {
    // A package consumed via a path repository, or a fresh clone before
    // composer install — must not fatal a boot.
    expect((new PackageSchemaDiscovery('/nonexistent/vendor'))->discover())->toBe([]);
});

it('ignores a package advertising an empty schema list', function (): void {
    $vendor = fakeVendor([
        ['name' => 'acme/foo', 'extra' => ['laravel-settings' => ['schemas' => []]]],
    ]);

    expect((new PackageSchemaDiscovery($vendor))->discover())->toBe([]);
});

it('caches the manifest and reuses it', function (): void {
    $vendor = fakeVendor([
        ['name' => 'acme/foo', 'extra' => ['laravel-settings' => ['schemas' => ['Acme\\Foo\\FooSettings']]]],
    ]);

    $manifest = sys_get_temp_dir().'/settings-manifest-'.bin2hex(random_bytes(6)).'.php';
    $discovery = new PackageSchemaDiscovery($vendor, $manifest);

    $discovery->discover();

    expect(is_file($manifest))->toBeTrue();

    // Remove the source of truth: a cached read must still answer, which is
    // what keeps discovery off the hot path once warmed.
    unlink($vendor.'/composer/installed.json');

    expect($discovery->discover())->toBe(['acme/foo' => ['Acme\\Foo\\FooSettings']]);

    $discovery->forget();

    expect(is_file($manifest))->toBeFalse();
});

it('survives an unwritable manifest directory', function (): void {
    $vendor = fakeVendor([
        ['name' => 'acme/foo', 'extra' => ['laravel-settings' => ['schemas' => ['Acme\\Foo\\FooSettings']]]],
    ]);

    $discovery = new PackageSchemaDiscovery($vendor, '/nonexistent/dir/manifest.php');

    expect($discovery->discover())->toBe(['acme/foo' => ['Acme\\Foo\\FooSettings']]);
});
