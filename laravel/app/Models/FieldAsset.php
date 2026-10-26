<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldAsset extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'in_service_job', 'out_of_service', 'in_repair', 'sold', 'retired'];

    /** Retired/sold assets drop out of due lists and the field app snapshot. */
    public const INACTIVE_STATUSES = ['sold', 'retired'];

    /** Fields hidden from users without fleet.costs.view. */
    public const COST_FIELDS = ['purchase_cost'];

    protected $fillable = [
        'asset_number', 'name', 'field_asset_type_id', 'status', 'make', 'model', 'year',
        'serial_number', 'vin', 'license_plate', 'plate_state', 'purchase_date', 'purchase_cost',
        'vendor', 'warranty_expires_on', 'assigned_user_id', 'home_location_id',
        'current_location_text', 'current_business_job_id', 'parent_asset_id', 'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'warranty_expires_on' => 'date:Y-m-d',
        'purchase_cost' => 'decimal:2',
        'current_meter' => 'decimal:1',
        'meter_updated_at' => 'datetime',
    ];

    public function type()
    {
        return $this->belongsTo(FieldAssetType::class, 'field_asset_type_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function homeLocation()
    {
        return $this->belongsTo(CompanyLocation::class, 'home_location_id');
    }

    public function job()
    {
        return $this->belongsTo(BusinessJob::class, 'current_business_job_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_asset_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_asset_id');
    }

    public function meterReadings()
    {
        return $this->hasMany(FieldAssetMeterReading::class)->orderByDesc('read_at')->orderByDesc('id');
    }

    public function plans()
    {
        return $this->hasMany(FieldAssetMaintPlan::class);
    }

    public function workOrders()
    {
        return $this->hasMany(FieldAssetWorkOrder::class);
    }

    public function inspections()
    {
        return $this->hasMany(FieldAssetInspection::class);
    }

    public function compliance()
    {
        return $this->hasMany(FieldAssetCompliance::class);
    }

    public function documents()
    {
        return $this->hasMany(FieldAssetDocument::class);
    }

    public function scopeInService($query)
    {
        return $query->whereNotIn('status', self::INACTIVE_STATUSES);
    }

    /** Next asset tag, e.g. FA-0012. */
    public static function nextAssetNumber(): string
    {
        $max = static::withTrashed()->where('asset_number', 'like', 'FA-%')->pluck('asset_number')
            ->map(fn ($n) => (int) substr($n, 3))->max() ?? 0;

        return sprintf('FA-%04d', $max + 1);
    }
}
