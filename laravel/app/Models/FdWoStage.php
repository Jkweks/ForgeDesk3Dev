<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FdWoStage extends Model
{
    protected $table = 'fd_wo_stages';

    protected $fillable = [
        'work_order_id', 'elevation_id', 'template_id', 'name', 'description',
        'sort_order', 'status', 'assigned_to_id', 'started_at', 'completed_at', 'notes',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($stage) {
            if ($stage->isDirty('status')) {
                $stage->workOrder->load('elevations.stages');
                $stage->workOrder->businessJob?->syncAutoStatus();
            }
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(FdWorkOrder::class, 'work_order_id');
    }

    public function elevation(): BelongsTo
    {
        return $this->belongsTo(FdWoElevation::class, 'elevation_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'assigned_to_id');
    }

    public function log(): HasMany
    {
        return $this->hasMany(FdStageLog::class, 'stage_id')->orderBy('created_at');
    }
}
