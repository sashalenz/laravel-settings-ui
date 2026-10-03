<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Contracts\SecretStore;
use SashaLenz\SettingsUi\Exceptions\SecretMissingException;
use SashaLenz\SettingsUi\Exceptions\SecretStoreNotBoundException;
use SashaLenz\SettingsUi\Exceptions\SecretUnavailableException;
use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Resolution\Overlay;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;
use SashaLenz\SettingsUi\Secrets\SecretBindings;
use SashaLenz\SettingsUi\Secrets\SecretLocation;
use SashaLenz\SettingsUi\Stores\ArrayStore;
use SashaLenz\SettingsUi\Stores\StoreManager;

beforeEach(function (): void {
    $this->runPackageMigrations();

    config()->set('settings.default_store', 'database');

    $this->backend = new FakeSecretStore;
    app()->instance(SecretStore::class, $this->backend);
});

it('reads a secret from the declared location', function (): void {
    $this->backend->items['vault/edge#token'] = 'ghp_live';

    declareSettings(Group::make('edge-server')->groups([
        Group::make('git')->fields([Field::text('token')->at('vault/edge', 'token')]),
    ]));

    expect(app(Resolver::class)->get('edge-server.git.token'))->toBe('ghp_live');
});

it('overlays a secret into config', function (): void {
    $this->backend->items['vault/edge#token'] = 'ghp_live';

    declareSettings(Group::make('edge-server')->groups([
        Group::make('git')->fields([Field::text('token')->at('vault/edge', 'token')]),
    ]));

    app(Overlay::class)->apply();

    expect(config('edge-server.git.token'))->toBe('ghp_live');
});

it('lets a database override win over the declared location', function (): void {
    // Staging and production pointing at different mounts is the case the
    // override exists for.
    $this->backend->items['vault/edge#token'] = 'declared';
    $this->backend->items['vault/staging#token'] = 'overridden';

    declareSettings(Group::make('edge-server')->fields([
        Field::text('token')->at('vault/edge', 'token'),
    ]));

    app(SecretBindings::class)->override(
        'edge-server.token',
        new SecretLocation('vault/staging', 'token'),
    );

    expect(app(Resolver::class)->get('edge-server.token'))->toBe('overridden');
});

it('reports which source a location came from', function (): void {
    declareSettings(Group::make('edge-server')->fields([
        Field::text('declared')->at('vault/a'),
        Field::text('overridden')->at('vault/b'),
        Field::text('nowhere')->secret()->store('secrets'),
    ]));

    $bindings = app(SecretBindings::class);
    $bindings->override('edge-server.overridden', new SecretLocation('vault/c'));

    expect($bindings->source(Settings::field('edge-server.declared')))->toBe('declaration')
        ->and($bindings->source(Settings::field('edge-server.overridden')))->toBe('override')
        ->and($bindings->source(Settings::field('edge-server.nowhere')))->toBeNull();
});

it('falls back to the file default during a provider outage, without reporting', function (): void {
    config()->set('edge-server.token', 'from-file');

    $this->backend->failWith = new SecretUnavailableException('provider down');

    declareSettings(Group::make('edge-server')->fields([Field::text('token')->at('vault/edge')]));

    app(Overlay::class)->apply();

    expect(config('edge-server.token'))->toBe('from-file');
});

it('falls back but surfaces a missing item, since that is a config bug', function (): void {
    config()->set('edge-server.token', 'from-file');

    $this->backend->failWith = new SecretMissingException('no such item');

    declareSettings(Group::make('edge-server')->fields([Field::text('token')->at('vault/edge')]));

    app(Overlay::class)->apply();

    expect(config('edge-server.token'))->toBe('from-file');
});

it('asks the provider once for a missing secret, not once per boot', function (): void {
    // The overlay resolves every provider-backed field on every boot. Without
    // a remembered miss, one dangling ref costs a round-trip and a report per
    // request forever — which is exactly how a 190MB log day happens.
    config()->set('edge-server.token', 'from-file');

    declareSettings(Group::make('edge-server')->fields([Field::text('token')->at('vault/edge')]));

    app(Overlay::class)->apply();
    app(Overlay::class)->apply();
    app(Overlay::class)->apply();

    expect($this->backend->reads)->toBe(1)
        ->and(config('edge-server.token'))->toBe('from-file');
});

it('keeps re-asking during a provider outage, so recovery is immediate', function (): void {
    // A miss is a stable fact; an outage is weather. Remembering the second
    // would strand the app on file defaults after the provider came back.
    declareSettings(Group::make('edge-server')->fields([Field::text('token')->at('vault/edge')]));

    $this->backend->failWith = new SecretUnavailableException('provider down');

    expect(app(Resolver::class)->get('edge-server.token'))->toBeNull();

    $this->backend->failWith = null;
    $this->backend->items['vault/edge'] = 'back-online';

    expect(app(Resolver::class)->get('edge-server.token'))->toBe('back-online');
});

it('serves a found secret from cache without re-reading', function (): void {
    $this->backend->items['vault/edge'] = 'ghp_live';

    declareSettings(Group::make('edge-server')->fields([Field::text('token')->at('vault/edge')]));

    expect(app(Resolver::class)->get('edge-server.token'))->toBe('ghp_live')
        ->and(app(Resolver::class)->get('edge-server.token'))->toBe('ghp_live')
        ->and($this->backend->reads)->toBe(1);
});

it('never batches secrets into a shared snapshot', function (): void {
    $this->backend->items['vault/a'] = 'secret-a';

    declareSettings(Group::make('edge-server')->fields([
        Field::text('token')->at('vault/a'),
        Field::integer('timeout')->store('array'),
    ]));

    app()->make(StoreManager::class)->set('array', new ArrayStore(['edge-server.timeout' => 5]));

    $snapshot = app(Resolver::class)->snapshot('edge-server');

    // The secret must not be in the cached, cross-process snapshot.
    expect($snapshot)->toBe(['edge-server.timeout' => 5]);
});

it('writes through to the provider', function (): void {
    declareSettings(Group::make('edge-server')->fields([Field::text('token')->at('vault/edge', 'token')]));

    app(Resolver::class)->put('edge-server.token', 'rotated');

    expect($this->backend->items['vault/edge#token'])->toBe('rotated');
});

it('drops its cached copy after a write so the next read is fresh', function (): void {
    $this->backend->items['vault/edge'] = 'before';

    declareSettings(Group::make('edge-server')->fields([Field::text('token')->at('vault/edge')]));

    expect(app(Resolver::class)->get('edge-server.token'))->toBe('before');

    app(Resolver::class)->put('edge-server.token', 'after');

    expect(app(Resolver::class)->get('edge-server.token'))->toBe('after');
});

it('explains itself when no secret backend is bound', function (): void {
    app()->forgetInstance(SecretStore::class);
    app()->offsetUnset(SecretStore::class);

    declareSettings(Group::make('edge-server')->fields([Field::text('token')->at('vault/edge')]));

    expect(fn () => app(Resolver::class)->put('edge-server.token', 'x'))
        ->toThrow(SecretStoreNotBoundException::class, 'no SashaLenz\SettingsUi\Contracts\SecretStore implementation');
});

it('treats encrypted and provider-backed as mutually exclusive', function (): void {
    // ->at() implies the secrets store; ->encrypted() is an at-rest concern of
    // the database store. A field declaring both would be ambiguous.
    $field = Field::text('token')->at('vault/edge');

    expect($field->encrypted)->toBeFalse()
        ->and($field->store)->toBe('secrets')
        ->and($field->isProviderBacked())->toBeTrue();
});

final class FakeSecretStore implements SecretStore
{
    /** @var array<string, string> */
    public array $items = [];

    public ?Throwable $failWith = null;

    public int $reads = 0;

    public function get(SecretLocation $location): ?string
    {
        $this->reads++;

        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        return $this->items[$this->key($location)] ?? null;
    }

    public function put(SecretLocation $location, string $value): void
    {
        $this->items[$this->key($location)] = $value;
    }

    public function forget(SecretLocation $location): void
    {
        unset($this->items[$this->key($location)]);
    }

    public function healthy(): bool
    {
        return $this->failWith === null;
    }

    private function key(SecretLocation $location): string
    {
        return $location->ref.($location->field !== null ? '#'.$location->field : '');
    }
}
