<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\History;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * One recorded change. Already redacted by the time it reaches a recorder —
 * a recorder must never have to remember to redact.
 */
final readonly class SettingChangeEvent
{
    public function __construct(
        public string $path,
        public mixed $oldValue,
        public mixed $newValue,
        public bool $redacted,
        public string $source,
        public ?Authenticatable $causer,
    ) {}
}
