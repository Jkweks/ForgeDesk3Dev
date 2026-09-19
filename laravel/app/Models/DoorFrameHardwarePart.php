<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoorFrameHardwarePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'configuration_id', 'part_label', 'product_id', 'quantity',
        'source_type', 'is_auto_generated', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'is_auto_generated' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function configuration()
    {
        return $this->belongsTo(DoorFrameConfiguration::class, 'configuration_id');
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
