<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdStageLog extends Model
{
    protected $table = 'fd_stage_log';

    public $timestamps = false;

    protected $fillable = ['stage_id', 'user_id', 'message'];

    protected $casts = ['created_at' => 'datetime'];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(FdWoStage::class, 'stage_id');
    }
}
