<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FdWoElevation extends Model
{
    protected $table = 'fd_wo_elevations';

    protected $fillable = [
        'work_order_id', 'elevation_type_id', 'elevation_tag',
        'quantity', 'date_requested', 'date_completed', 'completed_by_id', 'notes',
    ];

    protected $casts = [
        'date_requested' => 'date',
        'date_completed' => 'date',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(FdWorkOrder::class, 'work_order_id');
    }

    public function elevationType(): BelongsTo
    {
        return $this->belongsTo(FdElevationType::class, 'elevation_type_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'completed_by_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(FdWoStage::class, 'elevation_id')->orderBy('sort_order');
    }
}
