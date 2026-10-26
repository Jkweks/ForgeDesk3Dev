<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldAssetMaintPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'field_asset_id', 'title', 'description', 'interval_days', 'interval_meter', 'warn_days',
        'warn_meter', 'starts_on', 'starts_meter', 'priority', 'default_vendor', 'estimated_cost', 'is_active',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d', 'last_done_at' => 'date:Y-m-d', 'next_due_at' => 'date:Y-m-d',
        'interval_meter' => 'decimal:1', 'warn_meter' => 'decimal:1', 'starts_meter' => 'decimal:1',
        'last_done_meter' => 'decimal:1', 'next_due_meter' => 'decimal:1',
        'estimated_cost' => 'decimal:2', 'is_active' => 'boolean',
    ];

    protected $appends = ['due_state'];

    public function asset()
    {
        return $this->belongsTo(FieldAsset::class, 'field_asset_id');
    }

    public function workOrders()
    {
        return $this->hasMany(FieldAssetWorkOrder::class, 'plan_id');
    }

    /** ok | due_soon | overdue — by calendar and/or meter, whichever trips first. */
    public function getDueStateAttribute(): string
    {
        if (! $this->is_active) {
            return 'ok';
        }
        $meter = $this->asset?->current_meter;
        $today = now()->startOfDay();
        $state = 'ok';

        if ($this->next_due_at) {
            if ($this->next_due_at->lt($today)) {
                return 'overdue';
            }
            if ($this->next_due_at->lte($today->copy()->addDays((int) $this->warn_days))) {
                $state = 'due_soon';
            }
        }
        if ($this->next_due_meter !== null && $meter !== null) {
            if ((float) $meter >= (float) $this->next_due_meter) {
                return 'overdue';
            }
            if ($this->warn_meter !== null && (float) $meter >= (float) $this->next_due_meter - (float) $this->warn_meter) {
                $state = 'due_soon';
            }
        }

        return $state;
    }
}
