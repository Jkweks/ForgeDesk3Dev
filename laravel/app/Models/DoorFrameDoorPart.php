<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoorFrameDoorPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'door_config_id',
        'part_label',
        'product_id',
        'calculated_length',
        'quantity',
        'unit_type',
        'source_type',
        'is_auto_generated',
        'sort_order',
    ];

    protected $casts = [
        'calculated_length' => 'decimal:4',
        'quantity' => 'decimal:3',
        'is_auto_generated' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function doorConfig()
    {
        return $this->belongsTo(DoorFrameDoorConfig::class, 'door_config_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getFormattedLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->part_label));
    }
}
