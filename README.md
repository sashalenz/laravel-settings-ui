# Laravel Settings UI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sashalenz/laravel-settings-ui.svg?style=flat-square)](https://packagist.org/packages/sashalenz/laravel-settings-ui)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sashalenz/laravel-settings-ui/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sashalenz/laravel-settings-ui/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/sashalenz/laravel-settings-ui.svg?style=flat-square)](https://packagist.org/packages/sashalenz/laravel-settings-ui)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

Declarative, nestable runtime settings for Laravel — fluent schema, pluggable stores, secure secrets, resilient cache, transparent `config()` overlay, and a bundled responsive admin UI built with Livewire and Tailwind CSS.

---

## ✨ Features

- **Code-Authoritative Declarations**: Settings are defined in strongly typed PHP classes, never in the database.
- **Zero-Coupling `config()` Overlay**: Application code continues to read `config('group.key')` without any dependency on the settings machinery.
- **Deep Hierarchical Nesting**: Groups, subgroups, cards, sections, and fields mirror full dotted paths (`group.section.key`).
- **Pluggable Storage Backends**: Database, Encrypted Database, File (JSON/YAML), and external Secret Stores (Vault, AWS Secrets Manager, etc.).
- **Pluggable & Secure Secrets Engine**:
  - Masked values in HTML and Livewire states.
  - Zero-plaintext leaks in logs and exception traces.
  - Rate-limited and permission-gated "Click to Reveal" action.
- **Resilient & Stampede-Protected Cache**:
  - Stores primitive values only (zero risk of `__PHP_Incomplete_Class` serialization bugs).
  - Schema fingerprinting & version-stamped keys.
  - Cache stampede prevention via cache locks.
  - Queue worker / Octane auto-refresh hooks.
- **Full Audit History & Revert**:
  - Automatically records user, timestamp, before/after values, and source.
  - One-click rollback to prior versions.
- **Modular & Domain Discovery**:
  - Register settings from packages, modules, or domains (`Settings::register(...)` or `Settings::discover(...)`).
  - Composer `extra.laravel-settings` discovery.
- **Seeder Engine & CLI**:
  - `SettingsSeeder` for idempotent seeding (`syncMissing()` and `forceSync()`).
  - Artisan commands: `settings:sync`, `settings:seed`, `settings:doctor`, `settings:clear-cache`.

---

## 📦 Installation

Install via Composer:

```bash
composer require sashalenz/laravel-settings-ui
```

Publish configuration and migrations:

```bash
php artisan vendor:publish --tag="settings-config"
php artisan vendor:publish --tag="settings-migrations"
php artisan migrate
```

---

## 🚀 Quick Start

### 1. Declare a Setting Schema

Create a schema class implementing `DeclaresSettings`:

```php
namespace App\Settings;

use SashaLenz\SettingsUi\Contracts\DeclaresSettings;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

final class GeneralSettings implements DeclaresSettings
{
    public function schema(): Group
    {
        return Group::make('general')
            ->label('General Settings')
            ->icon('heroicon-o-cog')
            ->fields([
                Field::text('site_name')
                    ->label('Website Name')
                    ->default('My App')
                    ->rules(['required', 'string', 'max:255']),

                Field::boolean('maintenance_mode')
                    ->label('Maintenance Mode')
                    ->default(false),

                Field::text('api_token')
                    ->label('API Secret Token')
                    ->secret()
                    ->store('secrets'),
            ]);
    }
}
```

### 2. Register the Schema

In your `config/settings.php`:

```php
'schemas' => [
    \App\Settings\GeneralSettings::class,
],
```

Or programmatically in any `ServiceProvider`:

```php
use SashaLenz\SettingsUi\Facades\Settings;

public function boot(): void
{
    Settings::register([
        \App\Settings\GeneralSettings::class,
    ]);

    // Or discover all settings across domain modules:
    Settings::discover([
        app_path('Domain/*/Settings'),
    ]);
}
```

### 3. Read Values Transparently

```php
// Stored database value automatically overlays config file default:
$siteName = config('general.site_name');

// Or using the Settings facade:
$siteName = Settings::get('general.site_name');
```

---

## 🔐 Secrets Management

Mark fields as secret to encrypt them and prevent plaintext exposure in forms and logs:

```php
Field::text('stripe_secret')
    ->secret()
    ->store('secrets')
```

### Supported Drivers:
- **`encrypted-db`**: Encrypted locally in your database using Laravel's `APP_KEY`.
- **`env`**: Resolves from `.env` environment variables.
- **`vault`**: HashiCorp Vault KV v1/v2 engine.
- **`aws`**: AWS Secrets Manager.

---

## 🌱 Seeding Settings

Seed declared defaults safely without overwriting existing settings:

```php
use SashaLenz\SettingsUi\Database\Seeders\SettingsSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SettingsSeeder::class);
    }
}
```

Or via Artisan CLI:

```bash
php artisan settings:seed
php artisan settings:seed --group=general --force
```

---

## 🖥 Admin UI

Mount the routes in your routes file (e.g., `routes/web.php`):

```php
use SashaLenz\SettingsUi\Facades\Settings;

Route::prefix('admin')->middleware(['web', 'auth'])->group(function () {
    Settings::routes();
});
```

Available routes:
- `/admin/settings` — List of setting groups
- `/admin/settings/{group}` — Group editor form
- `/admin/settings/history` — Audit change log & revert
- `/admin/settings/schema` — Read-only schema inspector

---

## 🧪 Testing

```bash
composer test
composer format
composer analyse
```

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
