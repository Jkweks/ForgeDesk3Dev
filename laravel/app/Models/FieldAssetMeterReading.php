<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldAssetMeterReading extends Model
{
    protected $fillable = ['field_asset_id', 'reading', 'read_at', 'recorded_by', 'recorded_by_external', 'source', 'is_correction'];

    protected $casts = ['reading' => 'decimal:1', 'read_at' => 'datetime', 'is_correction' => 'boolean'];

    public function asset()
    {
        return $this->belongsTo(FieldAsset::class, 'field_asset_id');
    }
}
