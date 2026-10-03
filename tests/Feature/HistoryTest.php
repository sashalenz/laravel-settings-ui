<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use SashaLenz\SettingsUi\Contracts\RecordsChanges;
use SashaLenz\SettingsUi\Facades\Settings;
use SashaLenz\SettingsUi\History\ChangeRecorder;
use SashaLenz\SettingsUi\History\Recorders\DatabaseRecorder;
use SashaLenz\SettingsUi\History\SettingChangeEvent;
use SashaLenz\SettingsUi\Models\SettingChange;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\Group;

beforeEach(function (): void {
    $this->runPackageMigrations();
    $this->store = arrayStore();
});

it('records a change with both values', function (): void {
    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    app(ChangeRecorder::class)->record(Settings::field('selling.workdays'), 3, 7);

    $change = SettingChange::first();

    expect($change->path)->toBe('selling.workdays')
        ->and($change->old_value)->toBe(3)
        ->and($change->new_value)->toBe(7)
        ->and($change->redacted)->toBeFalse()
        ->and($change->source)->toBe('ui');
});

it('never stores a secret value, in either direction', function (): void {
    declareSettings(Group::make('selling')->fields([Field::text('token')->encrypted()]));

    app(ChangeRecorder::class)->record(Settings::field('selling.token'), 'old-secret', 'new-secret');

    $change = SettingChange::first();

    expect($change->redacted)->toBeTrue()
        ->and($change->old_value)->toBeNull()
        ->and($change->new_value)->toBeNull();

    // Belt and braces: the secret must not appear anywhere in the row.
    expect(json_encode(DB::table('setting_changes')->first()))
        ->not->toContain('secret');
});

it('cannot fail the write it is auditing', function (): void {
    // The incident this guards: an audit write inside the save transaction
    // rolled back an operator's value change when the log's database was
    // unreachable — the save reported success and silently reverted.
    config()->set('settings.history.recorders', [ExplodingRecorder::class]);
    app()->forgetInstance(ChangeRecorder::class);

    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    app(Resolver::class)->put('selling.workdays', 7);

    expect(fn () => app(ChangeRecorder::class)->record(Settings::field('selling.workdays'), 3, 7))
        ->not->toThrow(Throwable::class)
        ->and(app(Resolver::class)->get('selling.workdays'))->toBe(7);
});

it('keeps the other sinks working when one throws', function (): void {
    config()->set('settings.history.recorders', [
        ExplodingRecorder::class,
        DatabaseRecorder::class,
    ]);
    app()->forgetInstance(ChangeRecorder::class);

    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    app(ChangeRecorder::class)->record(Settings::field('selling.workdays'), 3, 7);

    expect(SettingChange::count())->toBe(1);
});

it('lets a host bridge its own audit log', function (): void {
    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    $seen = [];
    app(ChangeRecorder::class)->extend(new class($seen) implements RecordsChanges
    {
        public function __construct(private array &$seen) {}

        public function record(SettingChangeEvent $event): void
        {
            $this->seen[] = $event->path;
        }
    });

    app(ChangeRecorder::class)->record(Settings::field('selling.workdays'), 3, 7);

    expect($seen)->toBe(['selling.workdays']);
});

it('can be turned off entirely', function (): void {
    config()->set('settings.history.enabled', false);
    app()->forgetInstance(ChangeRecorder::class);

    declareSettings(Group::make('selling')->fields([Field::integer('workdays')]));

    app(ChangeRecorder::class)->record(Settings::field('selling.workdays'), 3, 7);

    expect(SettingChange::count())->toBe(0);
});

it('marks a redacted row as not revertable', function (): void {
    $redacted = new SettingChange(['path' => 'a.b', 'redacted' => true]);
    $plain = new SettingChange(['path' => 'a.b', 'redacted' => false]);

    // A secret's old value was never stored, so there is nothing to restore.
    expect($redacted->isRevertable())->toBeFalse()
        ->and($plain->isRevertable())->toBeTrue();
});

final class ExplodingRecorder implements RecordsChanges
{
    public function record(SettingChangeEvent $event): void
    {
        throw new RuntimeException('audit database is down');
    }
}

it('records who revealed a secret, without the value', function (): void {
    // After a credential leaks, "who read this token and when" is the question
    // an audit gets asked — and only logging writes makes it unanswerable.
    declareSettings(Group::make('selling')->fields([Field::text('token')->encrypted()]));

    app(ChangeRecorder::class)->recordReveal(Settings::field('selling.token'));

    $change = SettingChange::first();

    expect($change->source)->toBe('reveal')
        ->and($change->redacted)->toBeTrue()
        ->and($change->old_value)->toBeNull()
        ->and($change->new_value)->toBeNull();
});
