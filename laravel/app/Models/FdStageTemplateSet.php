<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A complexity tier for an elevation type (e.g. "Standard", "Advanced"),
 * owning an ordered list of stage templates.
 */
class FdStageTemplateSet extends Model
{
    protected $table = 'fd_stage_template_sets';

    protected $fillable = ['elevation_type_id', 'name', 'sort_order', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function elevationType(): BelongsTo
    {
        return $this->belongsTo(FdElevationType::class, 'elevation_type_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(FdStageTemplate::class, 'template_set_id')->orderBy('sort_order');
    }
}
