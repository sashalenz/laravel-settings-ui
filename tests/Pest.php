<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Schema\Group;
use SashaLenz\SettingsUi\Stores\ArrayStore;
use SashaLenz\SettingsUi\Stores\StoreManager;
use SashaLenz\SettingsUi\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Swap the default store for an in-memory one and hand it back, so a test can
 * seed stored values without touching a database.
 */
function arrayStore(): ArrayStore
{
    $store = new ArrayStore;

    config()->set('settings.default_store', 'array');
    app(StoreManager::class)->set('array', $store);

    return $store;
}

function declareSettings(Group ...$groups): void
{
    foreach ($groups as $group) {
        Settings::declare($group);
    }
}

function settingsFingerprint(): string
{
    return Settings::schema()->fingerprint();
}

/**
 * An `__PHP_Incomplete_Class` — what a cached object becomes once the class
 * that wrote it has been renamed or moved. Length is computed rather than
 * written by hand, because a serialized payload whose length prefix disagrees
 * with its class name fails to unserialize for the wrong reason entirely.
 */
function incompleteObject(string $class = 'A20\Domain\Control\Settings\Models\SettingField'): object
{
    return unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class));
}
