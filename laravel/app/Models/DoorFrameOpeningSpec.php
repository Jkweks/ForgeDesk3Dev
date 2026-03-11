<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoorFrameOpeningSpec extends Model
{
    use HasFactory;

    protected $fillable = [
        'configuration_id',
        'opening_type',
        'hand_single',
        'hand_pair',
        'door_opening_width',
        'door_opening_height',
        'hinging',
        'finish',
    ];

    protected $casts = [
        'door_opening_width' => 'decimal:2',
        'door_opening_height' => 'decimal:2',
    ];

    protected $appends = [
        'opening_type_label',
        'hinging_label',
        'finish_label',
        'hand_label',
    ];

    // Configuration arrays
    public static $openingTypes = [
        'single' => 'Single',
        'pair' => 'Pair',
    ];

    public static $handSingleOptions = [
        'lh_inswing' => 'LH Inswing',
        'rh_inswing' => 'RH Inswing',
        'lhr' => 'LHR',
        'rhr' => 'RHR',
    ];

    public static $handPairOptions = [
        'rhr_active' => 'RHR Active',
        'lhra_active' => 'LHRA Active',
    ];

    public static $hingingOptions = [
        'continuous' => 'Continuous',
        'butt' => 'Butt',
        'pivot_offset' => 'Pivot Offset',
        'pivot_center' => 'Pivot Center',
    ];

    public static $finishOptions = [
        'c2' => 'C2 - Clear Anodized',
        'db' => 'DB - Dark Bronze',
        'bl' => 'BL - Black',
    ];

    /**
     * Get the configuration this spec belongs to
     */
    public function configuration()
    {
        return $this->belongsTo(DoorFrameConfiguration::class, 'configuration_id');
    }

    /**
     * Get formatted opening type label
     */
    public function getOpeningTypeLabelAttribute()
    {
        return self::$openingTypes[$this->opening_type] ?? $this->opening_type;
    }

    /**
     * Get formatted hinging label
     */
    public function getHingingLabelAttribute()
    {
        return self::$hingingOptions[$this->hinging] ?? $this->hinging;
    }

    /**
     * Get formatted finish label
     */
    public function getFinishLabelAttribute()
    {
        return self::$finishOptions[$this->finish] ?? strtoupper($this->finish);
    }

    /**
     * Get formatted hand label based on opening type
     */
    public function getHandLabelAttribute()
    {
        if ($this->opening_type === 'single') {
            return self::$handSingleOptions[$this->hand_single] ?? $this->hand_single;
        } elseif ($this->opening_type === 'pair') {
            return self::$handPairOptions[$this->hand_pair] ?? $this->hand_pair;
        }
        return null;
    }

    /**
     * Check if dimensions are below recommended minimums
     */
    public function hasWarnings()
    {
        $warnings = [];

        if ($this->door_opening_width < 30) {
            $warnings[] = 'Width below 30" - verify with fabrication';
        }

        if ($this->door_opening_height < 70) {
            $warnings[] = 'Height below 70" - potential building code issue';
        }

        return $warnings;
    }
}
