<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoorFrameDoorConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'configuration_id',
        'door_series',
        'stile_width',
        'leaf_type',
        'handing',
        'hinge_type',
        'opening_angle',
        'bottom_gap',
        'top_rail_label',
        'bot_rail_label',
        'mid_rail_label',
        'mid_qty',
        'mid_loc1',
        'mid_loc2',
        'glazing',
        'preset',
    ];

    protected $casts = [
        'opening_angle' => 'integer',
        'bottom_gap' => 'decimal:4',
        'mid_qty' => 'integer',
        'mid_loc1' => 'decimal:4',
        'mid_loc2' => 'decimal:4',
    ];

    // Handing values, matching fab_utils' door-calculator (isPair()/isCenterPivot()).
    public static $handingOptions = [
        'LH (INSWING)' => 'LH Inswing',
        'RH (INSWING)' => 'RH Inswing',
        'LHR' => 'LHR',
        'RHR' => 'RHR',
        'CP SINGLE' => 'Center Pivot Single',
        'PAIR-RHRA' => 'Pair - RHRA Active',
        'PAIR-LHRA' => 'Pair - LHRA Active',
        'CP PAIR' => 'Center Pivot Pair',
    ];

    public static $hingeTypeOptions = [
        'BUTT HINGES' => 'Butt Hinges',
        'OFFSET PIVOTS' => 'Offset Pivots',
        'CONTINUOUS HINGE' => 'Continuous Hinge',
        'CENTER PIVOTS' => 'Center Pivots',
    ];

    public static $doorSeriesOptions = [
        'STANDARD' => 'Standard',
        'THERMAL' => 'Thermal',
        'MONUMENTAL' => 'Monumental',
    ];

    public function configuration()
    {
        return $this->belongsTo(DoorFrameConfiguration::class, 'configuration_id');
    }

    public function parts()
    {
        return $this->hasMany(DoorFrameDoorPart::class, 'door_config_id');
    }

    public function isPair(): bool
    {
        return in_array($this->handing, ['PAIR-RHRA', 'PAIR-LHRA', 'CP PAIR'], true);
    }

    public function isCenterPivot(): bool
    {
        return $this->hinge_type === 'CENTER PIVOTS';
    }
}
