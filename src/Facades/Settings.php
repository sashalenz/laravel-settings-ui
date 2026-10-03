<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Facades;

use Illuminate\Support\Facades\Facade;
use SashaLenz\SettingsUi\Schema\ResolvedField;
use SashaLenz\SettingsUi\Schema\ResolvedGroup;
use SashaLenz\SettingsUi\Schema\Schema;
use SashaLenz\SettingsUi\Schema\SchemaRegistry;
use SashaLenz\SettingsUi\SettingsManager;

/**
 * @method static void declare(string|\SashaLenz\SettingsUi\Contracts\DeclaresSettings|\SashaLenz\SettingsUi\Schema\Group $schema, ?string $source = null)
 * @method static void register(list<class-string<\SashaLenz\SettingsUi\Contracts\DeclaresSettings>|\SashaLenz\SettingsUi\Contracts\DeclaresSettings|\SashaLenz\SettingsUi\Schema\Group> $schemas, ?string $source = null)
 * @method static void discover(list<string> $paths, ?string $source = 'discovery')
 * @method static Schema schema()
 * @method static SchemaRegistry registry()
 * @method static list<string> groups()
 * @method static ResolvedGroup|null group(string $name)
 * @method static ResolvedField|null field(string $path)
 * @method static list<string> paths()
 * @method static bool declared(string $path)
 * @method static list<array{source: string, root: string}> provenance()
 *
 * @see SettingsManager
 */
final class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsManager::class;
    }
}
