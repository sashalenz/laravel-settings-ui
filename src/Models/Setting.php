<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The values table.
 *
 * Deliberately thin and deliberately unused by the resolver, which goes through
 * the store contract instead — a model instance must never reach a cached
 * snapshot. This exists for hosts that want to query values with Eloquent, and
 * to satisfy the form lifecycle's requirement for a model instance.
 *
 * @property string $path
 * @property string $group
 * @property mixed $value
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $guarded = [];

    protected $casts = ['value' => 'array'];
}
