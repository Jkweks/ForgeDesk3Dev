<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoorFrameFramePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'frame_config_id',
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
        'calculated_length' => 'decimal:2',
        'quantity' => 'decimal:3',
        'is_auto_generated' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the frame config this part belongs to
     */
    public function frameConfig()
    {
        return $this->belongsTo(DoorFrameFrameConfig::class, 'frame_config_id');
    }

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get formatted part label
     */
    public function getFormattedLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->part_label));
    }

}
