<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use SashaLenz\SettingsUi\Schema\ResolvedField;
use SashaLenz\SettingsUi\Schema\ResolvedGroup;

/**
 * The package never decides what a permission is — it asks the host.
 *
 * That is the whole point of the seam: a host's permission model (roles,
 * abilities, tokens, tenancy) is none of this package's business, and any
 * attempt to encode one here would be wrong somewhere. Every gate in the UI
 * funnels through these four questions.
 *
 * `$group` is passed so a host CAN gate per group if it wants to. A host whose
 * convention is a single umbrella permission simply ignores the argument —
 * nesting does not change its permission model.
 */
interface SettingsAuthorizer
{
    public function canView(?Authenticatable $user, ?ResolvedGroup $group = null): bool;

    public function canManage(?Authenticatable $user, ?ResolvedGroup $group = null): bool;

    public function canViewHistory(?Authenticatable $user): bool;

    /**
     * Reading a secret back is a separate question from being allowed to
     * replace it — a host may well let an operator rotate a token without
     * letting them see the current one.
     */
    public function canReveal(?Authenticatable $user, ResolvedField $field): bool;
}
