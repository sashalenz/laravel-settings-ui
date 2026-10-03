<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\History;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Auth;
use SashaLenz\SettingsUi\Contracts\RecordsChanges;
use SashaLenz\SettingsUi\Schema\ResolvedField;
use Throwable;

/**
 * Builds the change event, redacts it, and fans it out to every configured
 * recorder.
 *
 * Two rules, both learned the hard way:
 *
 *  1. **The audit can never break the write.** An audit-log write that ran
 *     inside the save transaction once rolled back an operator's value change
 *     because the log's database was unreachable — the save reported success
 *     and silently reverted. Every recorder is therefore isolated behind its
 *     own try/catch, and callers invoke this only after their transaction has
 *     committed.
 *
 *  2. **A secret's value is never recorded.** Not the old one, not the new one,
 *     not a hash. The row says a secret changed, who changed it and when. That
 *     is the whole useful content of the audit anyway.
 */
final class ChangeRecorder
{
    /** @var list<RecordsChanges>|null */
    private ?array $recorders = null;

    /** @param  list<class-string<RecordsChanges>>  $configured */
    public function __construct(
        private readonly Container $container,
        private readonly array $configured = [],
        private readonly bool $enabled = true,
    ) {}

    public function record(ResolvedField $field, mixed $before, mixed $after, string $source = 'ui'): void
    {
        if (! $this->enabled) {
            return;
        }

        $redacted = $field->field->secret;

        $this->dispatch(new SettingChangeEvent(
            path: $field->path,
            oldValue: $redacted ? null : $before,
            newValue: $redacted ? null : $after,
            redacted: $redacted,
            source: $source,
            causer: Auth::user(),
        ));
    }

    /**
     * Someone looked at a secret.
     *
     * Recorded as a redacted row with its own source, because "who read this
     * token, and when" is exactly the question an audit gets asked after a
     * credential leaks — and it is unanswerable if only writes are logged.
     */
    public function recordReveal(ResolvedField $field): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->dispatch(new SettingChangeEvent(
            path: $field->path,
            oldValue: null,
            newValue: null,
            redacted: true,
            source: 'reveal',
            causer: Auth::user(),
        ));
    }

    public function dispatch(SettingChangeEvent $event): void
    {
        foreach ($this->resolveRecorders() as $recorder) {
            try {
                $recorder->record($event);
            } catch (Throwable $e) {
                // One failing sink must not stop the others, and must never
                // surface to the operator as a failed save.
                report($e);
            }
        }
    }

    /** Add a sink at runtime — a host bridging its own audit log. */
    public function extend(RecordsChanges $recorder): void
    {
        $this->resolveRecorders();

        $this->recorders[] = $recorder;
    }

    /** @return list<RecordsChanges> */
    private function resolveRecorders(): array
    {
        if ($this->recorders !== null) {
            return $this->recorders;
        }

        $this->recorders = [];

        foreach ($this->configured as $class) {
            try {
                $recorder = $this->container->make($class);
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            if ($recorder instanceof RecordsChanges) {
                $this->recorders[] = $recorder;
            }
        }

        return $this->recorders;
    }
}
