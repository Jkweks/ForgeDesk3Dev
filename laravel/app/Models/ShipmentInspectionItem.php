<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentInspectionItem extends Model
{
    protected $fillable = [
        'inspection_id', 'shipment_type_id', 'type_name', 'label', 'input_type', 'unit',
        'value', 'result', 'critical', 'comment',
    ];

    protected $casts = ['critical' => 'boolean'];

    public function photos()
    {
        return $this->hasMany(ShipmentInspectionPhoto::class, 'item_id');
    }
}
