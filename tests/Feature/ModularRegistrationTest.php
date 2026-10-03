<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

it('allows batch registering multiple schemas', function (): void {
    Settings::register([
        Group::make('security')->fields([Field::boolean('two_factor_required')->default(true)]),
        Group::make('integrations')->fields([Field::text('slack_webhook')]),
    ], source: 'test-suite');

    expect(Settings::groups())->toContain('security', 'integrations')
        ->and(Settings::declared('security.two_factor_required'))->toBeTrue()
        ->and(Settings::declared('integrations.slack_webhook'))->toBeTrue();
});
