<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

beforeEach(function (): void {
    $this->runPackageMigrations();
});

it('seeds settings via artisan settings:seed command', function (): void {
    Settings::declare(
        Group::make('notifications')->fields([
            Field::boolean('email_enabled')->default(true),
            Field::boolean('sms_enabled')->default(false),
        ])
    );

    $resolver = app(Resolver::class);
    expect($resolver->has('notifications.email_enabled'))->toBeFalse();

    Artisan::call('settings:seed');

    expect($resolver->has('notifications.email_enabled'))->toBeTrue()
        ->and($resolver->get('notifications.email_enabled'))->toBe(true)
        ->and($resolver->get('notifications.sms_enabled'))->toBe(false);
});
