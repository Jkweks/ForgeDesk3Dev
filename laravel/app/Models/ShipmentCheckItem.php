<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentCheckItem extends Model
{
    public const PHASES = ['pre', 'post'];

    public const INPUT_TYPES = ['pass_fail', 'number', 'text', 'choice', 'photo'];

    protected $fillable = [
        'shipment_type_id', 'phase', 'label', 'input_type', 'unit', 'options',
        'required', 'photo_required', 'critical', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'options' => 'array', 'required' => 'boolean', 'photo_required' => 'boolean',
        'critical' => 'boolean', 'is_active' => 'boolean',
    ];

    public function type()
    {
        return $this->belongsTo(ShipmentType::class, 'shipment_type_id');
    }
}
