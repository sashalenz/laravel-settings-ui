<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Database\Seeders\SettingsSeeder;
use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

beforeEach(function (): void {
    $this->runPackageMigrations();
});

it('seeds declared default values safely when not present', function (): void {
    Settings::declare(
        Group::make('billing')->fields([
            Field::text('currency')->default('USD'),
            Field::boolean('tax_enabled')->default(true),
            Field::integer('invoice_due_days')->default(14),
        ])
    );

    $seeder = new class extends SettingsSeeder
    {
        protected array $settings = [
            'billing.currency' => 'EUR', // custom override
        ];
    };

    $resolver = app(Resolver::class);
    expect($resolver->has('billing.currency'))->toBeFalse();

    $seededCount = $seeder->syncMissing();
    expect($seededCount)->toBe(3);

    expect($resolver->get('billing.currency'))->toBe('EUR')
        ->and($resolver->get('billing.tax_enabled'))->toBe(true)
        ->and($resolver->get('billing.invoice_due_days'))->toBe(14);
});

it('does not overwrite existing values in syncMissing mode', function (): void {
    Settings::declare(
        Group::make('app')->fields([
            Field::text('name')->default('DefaultApp'),
        ])
    );

    $resolver = app(Resolver::class);
    $resolver->put('app.name', 'MyCustomApp');

    $seeder = new class extends SettingsSeeder
    {
        protected array $settings = [
            'app.name' => 'OverriddenApp',
        ];
    };

    $seeder->syncMissing();

    expect($resolver->get('app.name'))->toBe('MyCustomApp');
});

it('force overwrites existing values when forceSync is called', function (): void {
    Settings::declare(
        Group::make('app')->fields([
            Field::text('name')->default('DefaultApp'),
        ])
    );

    $resolver = app(Resolver::class);
    $resolver->put('app.name', 'MyCustomApp');

    $seeder = new class extends SettingsSeeder
    {
        protected array $settings = [
            'app.name' => 'OverriddenApp',
        ];
    };

    $seeder->forceSync();

    expect($resolver->get('app.name'))->toBe('OverriddenApp');
});
