<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdStageTemplateDep extends Model
{
    protected $table = 'fd_stage_template_deps';

    protected $fillable = ['template_id', 'depends_on_template_id'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(FdStageTemplate::class, 'template_id');
    }

    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(FdStageTemplate::class, 'depends_on_template_id');
    }
}
