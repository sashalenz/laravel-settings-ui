<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Support;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use SashaLenz\SettingsUi\Http\Livewire\SettingSchemaTable;
use SashaLenz\SettingsUi\Http\Livewire\SettingsGroupForm;
use SashaLenz\SettingsUi\Http\Livewire\SettingsHistoryTable;
use SashaLenz\SettingsUi\Http\Livewire\SettingsTable;

/**
 * Route-name registry.
 *
 * Package routes carry RELATIVE names (`index`, `group`, `history`, `schema`)
 * and the host supplies the prefix by mounting them inside its own
 * `Route::as(...)` group — so the package cannot know the full name up front.
 *
 * It does not guess, either. There are two exact ways to learn the real name:
 *
 * 1. `Settings::routes()` reads back what Laravel assigned and calls
 *    {@see self::register()}. Free, but only happens when the route FILE runs.
 * 2. Failing that, the router is searched by the component class each route
 *    points at. Exact for the same reason (1) is: the class is the package's
 *    own and cannot belong to a host route.
 *
 * (2) exists because `route:cache` is not a no-op here. A cached application
 * loads `bootstrap/cache/routes-*.php` and never evaluates the host's route
 * files, so `Settings::routes()` never runs and the registry stays empty — the
 * links then all render as `#` and every button in the UI silently does
 * nothing. That failure only ever appears on a cached (i.e. production)
 * deploy, which is the worst place to discover it.
 *
 * What is still refused is name-shape matching: searching for "something
 * ending in .index" would cheerfully match an unrelated `admin.orders.index`,
 * and a link that goes somewhere wrong is worse than one that goes nowhere.
 */
final class Routes
{
    /**
     * The component each route points at. Under `route:cache` the compiled
     * route table keeps its action, so this survives where the route file does
     * not.
     *
     * @var array<string, class-string>
     */
    private const ACTIONS = [
        'index' => SettingsTable::class,
        'history' => SettingsHistoryTable::class,
        'schema' => SettingSchemaTable::class,
        'group' => SettingsGroupForm::class,
    ];

    /** @var array<string, string> relative name => full registered name */
    private static array $names = [];

    /** @var array<string, string>|null memoised result of the router scan */
    private static ?array $discovered = null;

    public static function register(string $relative, string $full): void
    {
        self::$names[$relative] = $full;
    }

    public static function forget(): void
    {
        self::$names = [];
        self::$discovered = null;
    }

    public static function has(string $relative): bool
    {
        return self::name($relative) !== null;
    }

    public static function name(string $relative): ?string
    {
        return self::$names[$relative] ?? self::discovered()[$relative] ?? null;
    }

    public static function index(): string
    {
        return self::to('index');
    }

    public static function group(string $group): string
    {
        return self::to('group', ['group' => $group]);
    }

    public static function history(): string
    {
        return self::to('history');
    }

    public static function schema(): string
    {
        return self::to('schema');
    }

    /** @param  array<string, mixed>  $parameters */
    public static function to(string $relative, array $parameters = []): string
    {
        $name = self::name($relative);

        // A '#' link is a visible nuisance; an exception thrown mid-render
        // takes the page down. When the host has not mounted the routes, the
        // nuisance is the right failure.
        return $name === null ? '#' : route($name, $parameters);
    }

    /** @return array<string, string> */
    private static function discovered(): array
    {
        $found = self::$discovered ?? self::discover();

        // Only a scan that found something is worth remembering. An empty one
        // means the router had not been populated yet — memoising that would
        // pin every later lookup to null for the rest of the process.
        if ($found !== []) {
            self::$discovered = $found;
        }

        return $found;
    }

    /** @return array<string, string> */
    private static function discover(): array
    {
        if (! app()->bound('router')) {
            return [];
        }

        /** @var array<class-string, string> $relativeByAction */
        $relativeByAction = array_flip(self::ACTIONS);

        /** @var Router $router */
        $router = app('router');

        $found = [];

        /** @var Route $route */
        foreach ($router->getRoutes()->getRoutes() as $route) {
            $relative = $relativeByAction[$route->getActionName()] ?? null;
            $name = $route->getName();

            if ($relative !== null && is_string($name) && $name !== '') {
                $found[$relative] = $name;
            }
        }

        return $found;
    }
}
