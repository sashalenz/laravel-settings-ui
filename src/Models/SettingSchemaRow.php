<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent view over the derived schema projection.
 *
 * Read-only by convention — the only writer is the projector. Exists so
 * Wiretables has a builder to query; see the migration for why that is the
 * whole justification for the table.
 *
 * @property string $path
 * @property string $root
 * @property ?string $parent_path
 * @property bool $is_group
 * @property int $depth
 * @property ?string $type
 * @property ?string $store
 * @property ?string $label_key
 * @property ?string $icon
 * @property bool $is_secret
 * @property int $sort_order
 * @property int $field_count
 * @property ?string $source
 */
class SettingSchemaRow extends Model
{
    protected $table = 'setting_schema';

    protected $guarded = [];

    protected $casts = [
        'is_group' => 'boolean',
        'is_secret' => 'boolean',
        'depth' => 'integer',
        'sort_order' => 'integer',
        'field_count' => 'integer',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->where('is_group', true)->whereNull('parent_path');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFields(Builder $query): Builder
    {
        return $query->where('is_group', false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInRoot(Builder $query, string $root): Builder
    {
        return $query->where('root', $root);
    }
}
