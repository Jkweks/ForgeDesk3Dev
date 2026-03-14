<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoorFrameDoorConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'configuration_id',
        'door_system_product_id',
        'leaf_type',
        'stile_product_id',
        'glazing',
        'preset',
    ];

    protected $appends = [
        'leaf_type_label',
        'glazing_label',
        'preset_label',
    ];

    // Configuration arrays
    public static $leafTypes = [
        'single' => 'Single',
        'active' => 'Active Leaf',
        'inactive' => 'Inactive Leaf',
    ];

    public static $glazingOptions = [
        '0.25' => '1/4"',
        '0.5' => '1/2"',
        '1.0' => '1"',
    ];

    public static $presetOptions = [
        'standard' => 'Standard',
        'ws_continuous' => 'WS - Continuous Hinge',
        'ws_butt' => 'WS - Butt Hinge',
    ];

    /**
     * Get the configuration this door config belongs to
     */
    public function configuration()
    {
        return $this->belongsTo(DoorFrameConfiguration::class, 'configuration_id');
    }

    /**
     * Get the door system product
     */
    public function doorSystemProduct()
    {
        return $this->belongsTo(Product::class, 'door_system_product_id');
    }

    /**
     * Get the stile product
     */
    public function stileProduct()
    {
        return $this->belongsTo(Product::class, 'stile_product_id');
    }

    /**
     * Get all door parts for this leaf
     */
    public function parts()
    {
        return $this->hasMany(DoorFrameDoorPart::class, 'door_config_id');
    }

    /**
     * Get formatted leaf type label
     */
    public function getLeafTypeLabelAttribute()
    {
        return self::$leafTypes[$this->leaf_type] ?? $this->leaf_type;
    }

    /**
     * Get formatted glazing label
     */
    public function getGlazingLabelAttribute()
    {
        return self::$glazingOptions[$this->glazing] ?? $this->glazing;
    }

    /**
     * Get formatted preset label
     */
    public function getPresetLabelAttribute()
    {
        return self::$presetOptions[$this->preset] ?? $this->preset;
    }

    /**
     * Get glazing-driven parts based on glazing selection
     */
    public function getGlazingDrivenParts()
    {
        $parts = [];

        switch ($this->glazing) {
            case '0.25':
                $parts = [
                    'interior_glass_stop' => 'E7410',
                    'exterior_glass_stop' => 'E7410',
                    'interior_glass_vinyl' => 'P0017',
                    'exterior_glass_vinyl' => 'P0017',
                ];
                break;

            case '0.5':
                $parts = [
                    'interior_glass_stop' => 'E7926',
                    'exterior_glass_stop' => 'E7926',
                    'interior_glass_vinyl' => 'P0017',
                    'exterior_glass_vinyl' => 'P912',
                ];
                break;

            case '1.0':
                $parts = [
                    'interior_glass_stop' => 'E6422',
                    'exterior_glass_stop' => 'E6422',
                    'interior_glass_vinyl' => 'P0017',
                    'exterior_glass_vinyl' => 'P0017',
                ];
                break;
        }

        return $parts;
    }
}
