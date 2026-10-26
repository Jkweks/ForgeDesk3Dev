<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldAssetCompliance extends Model
{
    use SoftDeletes;

    protected $table = 'field_asset_compliance';

    public const KINDS = [
        'registration', 'insurance', 'dot_inspection', 'state_inspection', 'crane_cert',
        'load_test', 'swing_stage_cert', 'calibration', 'warranty', 'other',
    ];

    protected $fillable = ['field_asset_id', 'kind', 'reference_number', 'issued_on', 'expires_on', 'issuer', 'cost', 'notes'];

    protected $casts = ['issued_on' => 'date:Y-m-d', 'expires_on' => 'date:Y-m-d', 'cost' => 'decimal:2'];

    protected $appends = ['days_until_expiry'];

    public function asset()
    {
        return $this->belongsTo(FieldAsset::class, 'field_asset_id');
    }

    public function documents()
    {
        return $this->hasMany(FieldAssetDocument::class, 'compliance_id');
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->expires_on ? (int) now()->startOfDay()->diffInDays($this->expires_on->startOfDay(), false) : null;
    }
}
