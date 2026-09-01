<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdStageTemplate extends Model
{
    protected $table = 'fd_stage_templates';

    protected $fillable = [
        'elevation_type_id', 'template_set_id', 'job_type', 'name', 'description',
        'sort_order', 'blocks_next', 'default_user_id',
    ];

    protected $casts = [
        'blocks_next' => 'boolean',
    ];

    public function elevationType(): BelongsTo
    {
        return $this->belongsTo(FdElevationType::class, 'elevation_type_id');
    }

    public function templateSet(): BelongsTo
    {
        return $this->belongsTo(FdStageTemplateSet::class, 'template_set_id');
    }

    public function defaultUser(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'default_user_id');
    }
}
