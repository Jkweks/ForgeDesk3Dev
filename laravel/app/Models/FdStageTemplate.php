<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdStageTemplate extends Model
{
    protected $table = 'fd_stage_templates';

    protected $fillable = ['elevation_type_id', 'job_type', 'name', 'description', 'sort_order'];

    public function elevationType(): BelongsTo
    {
        return $this->belongsTo(FdElevationType::class, 'elevation_type_id');
    }
}
