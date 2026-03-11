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
        'is_auto_generated',
        'sort_order',
    ];

    protected $casts = [
        'calculated_length' => 'decimal:2',
        'is_auto_generated' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the door config this part belongs to
     */
    public function doorConfig()
    {
        return $this->belongsTo(DoorFrameDoorConfig::class, 'door_config_id');
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
        $config = $this->doorConfig;
        $openingSpecs = $config->configuration->openingSpecs;

        if (!$openingSpecs) {
            return null;
        }

        // Placeholder calculation - implement actual formulas based on part type
        // This would typically use formulas stored in product.tool_specifications
        $isPair = $openingSpecs->opening_type === 'pair';
        $width = $isPair ? ($openingSpecs->door_opening_width / 2) : $openingSpecs->door_opening_width;
        $height = $openingSpecs->door_opening_height;

        switch ($this->part_label) {
            case 'hinge_rail':
            case 'hinge_rail_a':
            case 'hinge_rail_b':
            case 'lock_rail':
                return $height - 2.0;

            case 'top_rail':
            case 'bottom_rail':
                return $width - 1.0;

            default:
                return null;
        }
    }
}
