<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Exceptions;

/** Transient: the provider is unreachable right now. Expected; not reported. */
final class SecretUnavailableException extends SettingsException {}
