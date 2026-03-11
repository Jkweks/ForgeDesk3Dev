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
        'sort_order',
    ];

    protected $casts = [
        'calculated_length' => 'decimal:2',
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

    /**
     * Calculate length based on opening specs and formula
     * This is a placeholder - implement actual calculation logic
     */
    public function calculateLength()
    {
        $config = $this->frameConfig;
        $openingSpecs = $config->configuration->openingSpecs;

        if (!$openingSpecs) {
            return null;
        }

        // Placeholder calculation - implement actual formulas based on part type
        // This would typically use formulas stored in product.tool_specifications
        switch ($this->part_label) {
            case 'hinge_jamb':
            case 'lock_jamb':
                return $openingSpecs->door_opening_height + 1.5;

            case 'door_head':
                return $openingSpecs->door_opening_width + 2.0;

            default:
                return null;
        }
    }
}
