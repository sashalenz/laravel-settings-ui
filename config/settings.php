<?php

declare(strict_types=1);
use SashaLenz\SettingsUi\History\Recorders\DatabaseRecorder;

return [

    /*
    |--------------------------------------------------------------------------
    | Schema declarations
    |--------------------------------------------------------------------------
    |
    | Classes implementing SashaLenz\SettingsUi\Contracts\DeclaresSettings. These are
    | merged with anything registered imperatively via Settings::declare() and
    | with auto-discovered package schemas (see below).
    |
    */

    'schemas' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Package auto-discovery
    |--------------------------------------------------------------------------
    |
    | Packages may advertise their schemas through composer's `extra` block:
    |
    |     "extra": { "laravel-settings": { "schemas": ["Acme\\Foo\\FooSettings"] } }
    |
    | Because discovered settings surface in the host's admin UI, discovery is
    | explicit about provenance — `settings:doctor` reports which root group
    | came from where. Disable globally, or exclude individual packages.
    |
    */

    'auto_discover' => true,

    'dont_discover' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Config overlay
    |--------------------------------------------------------------------------
    |
    | When enabled, resolved values are pushed over Laravel's config repository
    | on boot, so consumer code reads plain config('group.key') and never talks
    | to this package. A null resolved value is skipped, which is what lets a
    | valueless setting fall through to its config/{group}.php default.
    |
    */

    'overlay' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stores
    |--------------------------------------------------------------------------
    |
    | `default` backs any field that does not declare ->store(). The `secrets`
    | store additionally needs a SashaLenz\SettingsUi\Contracts\SecretStore binding.
    |
    */

    'default_store' => 'database',

    'stores' => [
        'database' => ['driver' => 'database', 'table' => 'settings'],
        'file' => ['driver' => 'file', 'path' => null],
        'secrets' => ['driver' => 'secrets'],
        'array' => ['driver' => 'array'],
        'null' => ['driver' => 'null'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Snapshots hold primitives only — never serialized models — under keys
    | stamped with the package's cache version and a hash of the declared
    | schema, so a deploy that changes declarations invalidates by itself and
    | no stale-shape payload can ever be read back.
    |
    */

    'cache' => [
        'enabled' => true,
        'store' => null,
        'prefix' => 'settings',
        'ttl' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue workers
    |--------------------------------------------------------------------------
    |
    | Long-lived workers hold the boot-time overlay. With `refresh` on, a
    | Queue::looping hook compares a single version stamp and re-applies the
    | overlay only when it actually moved. Off by default — the documented
    | alternative is `queue:restart` on settings change.
    |
    */

    'workers' => [
        'refresh' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | History
    |--------------------------------------------------------------------------
    |
    | Change records are written after commit and never inside the transaction
    | that persists the value — an audit failure must not be able to roll back
    | an operator's save.
    |
    */

    'history' => [
        'enabled' => true,
        'queue' => false,
        'recorders' => [
            DatabaseRecorder::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI
    |--------------------------------------------------------------------------
    |
    | `layout` is the host's shell view. Routes are NEVER self-registered —
    | mount them yourself with Settings::routes() inside whatever prefix and
    | middleware group you want.
    |
    */

    'ui' => [
        'layout' => null,
        'route_prefix' => 'settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | The package never decides what a permission is — it asks the host. The
    | default authorizer resolves these ability names through Laravel's Gate.
    |
    */

    'abilities' => [
        'view' => 'view-settings',
        'manage' => 'manage-settings',
        'history' => 'view-settings',
        'reveal' => 'manage-settings',
    ],

];
