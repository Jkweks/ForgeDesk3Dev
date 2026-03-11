<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoorFrameFrameConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'configuration_id',
        'frame_system_product_id',
        'glazing',
        'has_transom',
        'transom_glazing',
        'total_frame_height',
    ];

    protected $casts = [
        'has_transom' => 'boolean',
        'total_frame_height' => 'decimal:2',
    ];

    protected $appends = [
        'glazing_label',
        'transom_glazing_label',
    ];

    // Glazing options
    public static $glazingOptions = [
        '0.25' => '1/4"',
        '0.5' => '1/2"',
        '1.0' => '1"',
    ];

    /**
     * Get the configuration this frame config belongs to
     */
    public function configuration()
    {
        return $this->belongsTo(DoorFrameConfiguration::class, 'configuration_id');
    }

    /**
     * Get the frame system product
     */
    public function frameSystemProduct()
    {
        return $this->belongsTo(Product::class, 'frame_system_product_id');
    }

    /**
     * Get all frame parts
     */
    public function parts()
    {
        return $this->hasMany(DoorFrameFramePart::class, 'frame_config_id');
    }

    /**
     * Get formatted glazing label
     */
    public function getGlazingLabelAttribute()
    {
        return self::$glazingOptions[$this->glazing] ?? $this->glazing;
    }

    /**
     * Get formatted transom glazing label
     */
    public function getTransomGlazingLabelAttribute()
    {
        if (!$this->has_transom || !$this->transom_glazing) {
            return null;
        }
        return self::$glazingOptions[$this->transom_glazing] ?? $this->transom_glazing;
    }
}
