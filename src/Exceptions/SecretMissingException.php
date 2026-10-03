<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Exceptions;

/** The item does not exist at that location. A configuration bug, so it IS reported. */
final class SecretMissingException extends SettingsException {}
