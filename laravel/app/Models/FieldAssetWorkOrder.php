<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldAssetWorkOrder extends Model
{
    use SoftDeletes;

    public const TYPES = ['preventive', 'repair', 'inspection_defect', 'recall', 'other'];

    public const STATUSES = ['open', 'scheduled', 'in_progress', 'waiting_parts', 'completed', 'cancelled'];

    public const OPEN_STATUSES = ['open', 'scheduled', 'in_progress', 'waiting_parts'];

    public const COST_FIELDS = ['labor_cost', 'parts_cost', 'outside_cost', 'total_cost'];

    protected $fillable = [
        'field_asset_id', 'plan_id', 'type', 'status', 'priority', 'title', 'description',
        'reported_on', 'scheduled_for', 'completed_on', 'meter_at_service', 'performed_by', 'vendor',
        'labor_hours', 'labor_cost', 'parts_cost', 'outside_cost', 'downtime_days', 'invoice_number', 'notes',
        'wo_number', 'reported_by_user_id', 'reported_by_external', 'source', 'inspection_id',
    ];

    protected $casts = [
        'reported_on' => 'date:Y-m-d', 'scheduled_for' => 'date:Y-m-d', 'completed_on' => 'date:Y-m-d',
        'meter_at_service' => 'decimal:1', 'labor_hours' => 'decimal:2',
        'labor_cost' => 'decimal:2', 'parts_cost' => 'decimal:2', 'outside_cost' => 'decimal:2',
    ];

    protected $appends = ['total_cost'];

    public function asset()
    {
        return $this->belongsTo(FieldAsset::class, 'field_asset_id');
    }

    public function plan()
    {
        return $this->belongsTo(FieldAssetMaintPlan::class, 'plan_id');
    }

    public function items()
    {
        return $this->hasMany(FieldAssetWoItem::class, 'work_order_id');
    }

    public function documents()
    {
        return $this->hasMany(FieldAssetDocument::class, 'work_order_id');
    }

    public function getTotalCostAttribute(): string
    {
        return number_format((float) $this->labor_cost + (float) $this->parts_cost + (float) $this->outside_cost, 2, '.', '');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public static function nextNumber(): string
    {
        $max = static::withTrashed()->where('wo_number', 'like', 'FWO-%')->pluck('wo_number')
            ->map(fn ($n) => (int) substr($n, 4))->max() ?? 0;

        return sprintf('FWO-%05d', $max + 1);
    }
}
