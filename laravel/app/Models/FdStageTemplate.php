<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FdStageTemplate extends Model
{
    protected $table = 'fd_stage_templates';

    protected $fillable = ['elevation_type_id', 'job_type', 'name', 'description', 'sort_order', 'default_user_id'];

    public function elevationType(): BelongsTo
    {
        return $this->belongsTo(FdElevationType::class, 'elevation_type_id');
    }

    public function defaultUser(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'default_user_id');
    }

    public function dependsOn(): BelongsToMany
    {
        return $this->belongsToMany(
            FdStageTemplate::class,
            'fd_stage_template_deps',
            'template_id',
            'depends_on_template_id'
        );
    }
}
