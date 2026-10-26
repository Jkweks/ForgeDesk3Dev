<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldAssetType extends Model
{
    protected $fillable = ['name', 'category', 'meter_type', 'default_checklist', 'auto_out_of_service_on_fail', 'is_active'];

    protected $casts = [
        'default_checklist' => 'array',
        'auto_out_of_service_on_fail' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function assets()
    {
        return $this->hasMany(FieldAsset::class);
    }
}
