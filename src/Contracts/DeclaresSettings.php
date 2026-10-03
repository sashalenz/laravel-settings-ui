<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Contracts;

use SashaLenz\SettingsUi\Schema\Group;

/**
 * Implemented by anything that contributes settings — an app's domain class or
 * a third-party package's own declaration.
 *
 * Resolved through the container, so a declaration may inject whatever it
 * needs to build its options (an enum, a repository). It must stay cheap:
 * `schema()` runs on the first schema access of a request.
 */
interface DeclaresSettings
{
    public function schema(): Group;
}
