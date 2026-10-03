<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use SashaLenz\SettingsUi\Contracts\SettingsAuthorizer;
use SashaLenz\SettingsUi\Schema\ResolvedField;
use SashaLenz\SettingsUi\Schema\ResolvedGroup;

/**
 * Default authorizer: resolves configurable ability names through Laravel's
 * Gate, so a host wires its existing policy without implementing anything.
 *
 * A group or field may name its own extra ability via `->authorize()`; when it
 * does, BOTH must pass. Additive only — a declaration can narrow access, never
 * widen it past the host's own gate.
 */
final class GateAuthorizer implements SettingsAuthorizer
{
    public function __construct(
        private readonly Gate $gate,
        private readonly ConfigRepository $config,
    ) {}

    public function canView(?Authenticatable $user, ?ResolvedGroup $group = null): bool
    {
        return $this->allows($user, 'view')
            && $this->allowsDeclared($user, $group?->group->ability);
    }

    public function canManage(?Authenticatable $user, ?ResolvedGroup $group = null): bool
    {
        return $this->allows($user, 'manage')
            && $this->allowsDeclared($user, $group?->group->ability);
    }

    public function canViewHistory(?Authenticatable $user): bool
    {
        return $this->allows($user, 'history');
    }

    public function canReveal(?Authenticatable $user, ResolvedField $field): bool
    {
        return $this->allows($user, 'reveal')
            && $this->allowsDeclared($user, $field->field->ability);
    }

    private function allows(?Authenticatable $user, string $key): bool
    {
        $ability = $this->config->get("settings.abilities.{$key}");

        // An unconfigured ability means the host has not expressed a rule.
        // Deny rather than allow: an admin surface that silently opens itself
        // because a config key is missing is the wrong failure direction.
        if (! is_string($ability) || $ability === '') {
            return false;
        }

        return $this->gate->forUser($user)->allows($ability);
    }

    private function allowsDeclared(?Authenticatable $user, ?string $ability): bool
    {
        if ($ability === null || $ability === '') {
            return true;
        }

        return $this->gate->forUser($user)->allows($ability);
    }
}
