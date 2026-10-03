<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Support\Routes;

/**
 * Mount the routes the way a host does, then drop the in-memory registry to
 * simulate a `route:cache`d process: the compiled route table is loaded, but
 * the route FILE that populated the registry never runs.
 */
function mountSettingsRoutes(): void
{
    Route::as('admin.control.settings.')
        ->prefix('control/settings')
        ->group(static fn () => Settings::routes());

    Route::getRoutes()->refreshNameLookups();
}

beforeEach(function (): void {
    Routes::forget();
});

it('resolves link targets from the registry the route file fills', function (): void {
    mountSettingsRoutes();

    expect(Routes::index())->toEndWith('/control/settings')
        ->and(Routes::group('stock'))->toEndWith('/control/settings/stock')
        ->and(Routes::history())->toEndWith('/control/settings/history')
        ->and(Routes::schema())->toEndWith('/control/settings/schema');
});

it('still resolves them when the route file never ran because routes are cached', function (): void {
    mountSettingsRoutes();

    // What `route:cache` does to this class: the routes exist, the file that
    // registered their names does not run. Every link used to fall back to '#'
    // here, so the whole settings UI rendered dead buttons in production only.
    Routes::forget();

    expect(Routes::name('group'))->toBe('admin.control.settings.group')
        ->and(Routes::group('stock'))->toEndWith('/control/settings/stock')
        ->and(Routes::index())->toEndWith('/control/settings')
        ->and(Routes::history())->toEndWith('/control/settings/history')
        ->and(Routes::schema())->toEndWith('/control/settings/schema');
});

it('honours whatever name prefix the host mounted under', function (): void {
    Route::as('backoffice.knobs.')
        ->prefix('backoffice/knobs')
        ->group(static fn () => Settings::routes());

    Route::getRoutes()->refreshNameLookups();

    Routes::forget();

    expect(Routes::name('index'))->toBe('backoffice.knobs.index')
        ->and(Routes::group('stock'))->toEndWith('/backoffice/knobs/stock');
});

it('falls back to # rather than throwing when the host mounted nothing', function (): void {
    expect(Routes::index())->toBe('#')
        ->and(Routes::group('stock'))->toBe('#')
        ->and(Routes::has('group'))->toBeFalse();
});

it('does not memoise an empty scan, so mounting later still resolves', function (): void {
    // Probing before the host mounts anything must not pin every later lookup
    // to null for the rest of the process.
    expect(Routes::name('index'))->toBeNull();

    mountSettingsRoutes();
    Routes::forget();

    expect(Routes::name('index'))->toBe('admin.control.settings.index');
});
