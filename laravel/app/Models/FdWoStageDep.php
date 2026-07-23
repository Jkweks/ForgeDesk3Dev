<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdWoStageDep extends Model
{
    protected $table = 'fd_wo_stage_deps';

    protected $fillable = ['stage_id', 'depends_on_stage_id'];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(FdWoStage::class, 'stage_id');
    }

    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(FdWoStage::class, 'depends_on_stage_id');
    }
}
