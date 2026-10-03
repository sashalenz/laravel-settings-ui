<?php

declare(strict_types=1);

use Generated\Settings\CommsSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundTrip\Settings\CiSettings;
use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\Import\DeclarationPrinter;
use SashaLenz\SettingsUi\Import\LegacyImporter;

beforeEach(function (): void {
    Schema::create('setting_fields', function (Blueprint $table): void {
        $table->id();
        $table->string('group');
        $table->string('key');
        $table->string('type');
        $table->string('label')->nullable();
        $table->text('help')->nullable();
        $table->string('rules')->nullable();
        $table->text('default_value')->nullable();
        $table->text('value')->nullable();
        $table->text('options')->nullable();
        $table->string('model_class')->nullable();
        $table->boolean('is_encrypted')->default(false);
        $table->string('secret_provider')->nullable();
        $table->string('secret_ref')->nullable();
        $table->string('secret_field')->nullable();
        $table->integer('sort_order')->default(0);
    });

    $this->seed = function (array $row): void {
        DB::table('setting_fields')->insert($row + [
            'group' => 'selling',
            'type' => 'text',
            'label' => 'A label',
            'is_encrypted' => false,
            'sort_order' => 0,
        ]);
    };

    $this->importer = new LegacyImporter(DB::connection());
});

it('recovers nesting from dotted keys', function (): void {
    ($this->seed)(['group' => 'comms', 'key' => 'chatwoot.inboxes.viber', 'type' => 'number']);
    ($this->seed)(['group' => 'comms', 'key' => 'chatwoot.enabled', 'type' => 'boolean']);

    $roots = $this->importer->import();
    $chatwoot = $roots['comms']->children['chatwoot'];

    expect(array_keys($roots))->toBe(['comms'])
        ->and($chatwoot->rows)->toHaveCount(1)
        ->and($chatwoot->children)->toHaveKey('inboxes')
        ->and($chatwoot->children['inboxes']->rows[0]->key)->toBe('chatwoot.inboxes.viber');
});

it('handles arbitrary depth', function (): void {
    // The real table's deepest key is five segments; the model must not assume
    // the two levels a mockup happens to show.
    ($this->seed)(['group' => 'd', 'key' => 'a.b.c.polling_enabled', 'type' => 'boolean']);

    $node = $this->importer->import()['d']->children['a']->children['b']->children['c'];

    expect($node->rows[0]->path())->toBe('d.a.b.c.polling_enabled');
});

it('flags a row marked both encrypted and provider-backed, and prefers the provider', function (): void {
    // A live row in the app this was extracted from. The runtime checks the
    // secret ref first, so the encrypted flag never applied — but the seeder
    // keeps re-asserting it, so nothing ever surfaced the contradiction.
    ($this->seed)([
        'group' => 'telegram-bots',
        'key' => 'bots.staff.token',
        'is_encrypted' => true,
        'secret_provider' => 'ovh',
        'secret_ref' => 'secret/settings/telegram-bots',
        'secret_field' => 'bots.staff.token',
    ]);

    $roots = $this->importer->import();
    $row = $roots['telegram-bots']->children['bots']->children['staff']->rows[0];

    expect($row->encrypted)->toBeFalse()
        ->and($row->isProviderBacked())->toBeTrue()
        ->and($this->importer->warnings())->toHaveCount(1)
        ->and($this->importer->warnings()[0]['level'])->toBe('warning');
});

it('flags a default_value that would change meaning under the new precedence', function (): void {
    ($this->seed)(['key' => 'workdays', 'type' => 'number', 'default_value' => '3', 'value' => null]);

    $this->importer->import();

    expect($this->importer->warnings())->toHaveCount(1)
        ->and($this->importer->warnings()[0]['message'])->toContain('precedence');
});

it('stays quiet when a default_value is shadowed by a stored value', function (): void {
    // The live table's shape: every default_value sits behind a stored value,
    // so none of them can change meaning on cutover.
    ($this->seed)(['key' => 'workdays', 'type' => 'number', 'default_value' => '3', 'value' => '7']);

    $this->importer->import();

    expect($this->importer->warnings())->toBe([]);
});

it('errors on a key that is both a field and a path prefix', function (): void {
    ($this->seed)(['key' => 'reservation', 'type' => 'text']);
    ($this->seed)(['key' => 'reservation.workdays', 'type' => 'number']);

    $this->importer->import();

    expect($this->importer->warnings())->not->toBeEmpty()
        ->and(collect($this->importer->warnings())->pluck('level'))->toContain('error');
});

it('errors on an unknown type but still emits something usable', function (): void {
    ($this->seed)(['key' => 'weird', 'type' => 'colour_picker']);

    $roots = $this->importer->import();
    $source = (new DeclarationPrinter('App\\Settings'))->print($roots['selling']);

    expect($this->importer->warnings()[0]['level'])->toBe('error')
        ->and($source)->toContain("Field::custom('weird', 'colour_picker')");
});

it('generates syntactically valid, loadable PHP', function (): void {
    ($this->seed)(['group' => 'comms', 'key' => 'chatwoot.inboxes.viber', 'type' => 'number', 'rules' => 'nullable|integer|min:1', 'sort_order' => 5]);
    ($this->seed)(['group' => 'comms', 'key' => 'chatwoot.token', 'is_encrypted' => true]);
    ($this->seed)(['group' => 'comms', 'key' => 'mode', 'type' => 'select', 'options' => json_encode(['a' => 'Alpha', 'b' => 'Beta'])]);

    $source = (new DeclarationPrinter('Generated\\Settings'))->print($this->importer->import()['comms']);

    $file = tempnam(sys_get_temp_dir(), 'decl').'.php';
    file_put_contents($file, $source);

    require $file;

    $schema = (new CommsSettings)->schema();

    expect($schema->name)->toBe('comms');

    unlink($file);
});

it('round-trips the declaration back to the same config paths', function (): void {
    // The whole premise of the migration: nesting is free because the paths
    // come out identical. If this drifts, every config() call site in the host
    // breaks silently.
    ($this->seed)(['group' => 'ci', 'key' => 'a.b.c', 'type' => 'text']);
    ($this->seed)(['group' => 'ci', 'key' => 'a.d', 'type' => 'number']);
    ($this->seed)(['group' => 'ci', 'key' => 'e', 'type' => 'boolean']);

    $source = (new DeclarationPrinter('RoundTrip\\Settings'))->print($this->importer->import()['ci']);

    $file = tempnam(sys_get_temp_dir(), 'decl').'.php';
    file_put_contents($file, $source);
    require $file;
    unlink($file);

    declareSettings((new CiSettings)->schema());

    $paths = Settings::paths();
    sort($paths);

    // Same SET of config paths — traversal order is a rendering concern, but
    // a missing or renamed path would break a consumer's config() call.
    expect($paths)->toBe(['ci.a.b.c', 'ci.a.d', 'ci.e']);
});

it('extracts labels as translation keys with the literals alongside', function (): void {
    ($this->seed)(['key' => 'workdays', 'type' => 'number', 'label' => 'Робочі дні', 'help' => 'Скільки днів']);

    $printer = new DeclarationPrinter('App\\Settings');
    $source = $printer->print($this->importer->import()['selling']);

    expect($source)->toContain("->label('settings.selling.workdays.label')")
        ->and($source)->not->toContain('Робочі дні')
        ->and($printer->translations())->toMatchArray([
            'settings.selling.workdays.label' => 'Робочі дні',
            'settings.selling.workdays.help' => 'Скільки днів',
        ]);
});

it('can emit literal labels instead when asked', function (): void {
    ($this->seed)(['key' => 'workdays', 'type' => 'number', 'label' => 'Робочі дні']);

    $source = (new DeclarationPrinter('App\\Settings', literalLabels: true))
        ->print($this->importer->import()['selling']);

    expect($source)->toContain("->label('Робочі дні')");
});

it('names classes from dashed group names', function (): void {
    $printer = new DeclarationPrinter('App\\Settings');

    expect($printer->className('communications-services'))->toBe('CommunicationsServicesSettings')
        ->and($printer->className('nova-poshta-api'))->toBe('NovaPoshtaApiSettings');
});

it('restricts the import to named groups', function (): void {
    ($this->seed)(['group' => 'selling', 'key' => 'a']);
    ($this->seed)(['group' => 'stock', 'key' => 'b']);

    expect(array_keys($this->importer->import(['stock'])))->toBe(['stock']);
});
