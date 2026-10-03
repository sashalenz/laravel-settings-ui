<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\History\Recorders;

use Illuminate\Database\ConnectionInterface;
use SashaLenz\SettingsUi\Contracts\RecordsChanges;
use SashaLenz\SettingsUi\History\SettingChangeEvent;

/** Writes to the package's own `setting_changes` table. */
final class DatabaseRecorder implements RecordsChanges
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'setting_changes',
    ) {}

    public function record(SettingChangeEvent $event): void
    {
        $causer = $event->causer;

        $this->connection->table($this->table)->insert([
            'path' => $event->path,
            'old_value' => $this->encode($event->oldValue),
            'new_value' => $this->encode($event->newValue),
            'redacted' => $event->redacted,
            'source' => $event->source,
            'causer_type' => $causer !== null ? $causer::class : null,
            'causer_id' => $causer?->getAuthIdentifier(),
            'created_at' => $this->connection->raw('CURRENT_TIMESTAMP'),
        ]);
    }

    private function encode(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
