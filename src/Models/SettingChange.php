<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $path
 * @property mixed $old_value
 * @property mixed $new_value
 * @property bool $redacted
 * @property string $source
 */
class SettingChange extends Model
{
    protected $table = 'setting_changes';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'redacted' => 'boolean',
        'created_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForPath(Builder $query, string $path): Builder
    {
        return $query->where('path', $path);
    }

    /**
     * A redacted row never stored the old value, so there is nothing to put
     * back — the revert action is disabled rather than hidden, so the absence
     * has a visible reason.
     */
    public function isRevertable(): bool
    {
        return ! $this->redacted;
    }
}
