<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Contracts;

use SashaLenz\SettingsUi\History\SettingChangeEvent;

/**
 * A sink for setting changes.
 *
 * One method, so a host can bridge its existing audit log with a few lines
 * without adopting this package's storage. Implementations must not throw —
 * and the dispatcher assumes they might anyway.
 */
interface RecordsChanges
{
    public function record(SettingChangeEvent $event): void;
}
