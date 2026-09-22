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
        'butt_hinge_count',
        'hinge_spacing_standard_id',
        'finish',
        'glazing',
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

    public function hingeSpacingStandard()
    {
        return $this->belongsTo(ConfiguratorHingeSpacingStandard::class, 'hinge_spacing_standard_id');
    }

    /**
     * Computed hinge prep locations for the Opening tab preview and cut-sheet
     * PDF — only meaningful for butt hinges with a count + standard set.
     *
     * @return array<int, array{index: int, distance_from_top: float, label: string}>
     */
    public function hingeLocations(): array
    {
        if ($this->hinging !== 'butt' || ! $this->butt_hinge_count || ! $this->hinge_spacing_standard_id) {
            return [];
        }

        $standard = $this->hingeSpacingStandard ?? $this->hingeSpacingStandard()->first();
        if (! $standard) {
            return [];
        }

        $bottomGap = (float) ConfiguratorSetting::current()->bottom_gap;

        return $standard->locations((float) $this->door_opening_height, (int) $this->butt_hinge_count, $bottomGap);
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
     * The door config's `handing` value (LH (INSWING)/RH (INSWING)/LHR/RHR/
     * CP SINGLE/PAIR-RHRA/PAIR-LHRA/CP PAIR), derived from opening type +
     * hand + hinging so it never needs entering a second time on the Door
     * tab. Center-pivot hinging takes priority over hand, matching the old
     * per-door "CP SINGLE"/"CP PAIR" handing values, which carried no
     * separate hand information of their own.
     */
    public function deriveDoorHanding(): string
    {
        $isPair = $this->opening_type === 'pair';
        if ($this->hinging === 'pivot_center') {
            return $isPair ? 'CP PAIR' : 'CP SINGLE';
        }
        if ($isPair) {
            return $this->hand_pair === 'lhra_active' ? 'PAIR-LHRA' : 'PAIR-RHRA';
        }

        return match ($this->hand_single) {
            'rh_inswing' => 'RH (INSWING)',
            'lhr' => 'LHR',
            'rhr' => 'RHR',
            default => 'LH (INSWING)',
        };
    }

    /**
     * The door config's `hinge_type` value, derived from opening hinging so
     * it never needs entering a second time on the Door tab.
     */
    public function deriveHingeType(): string
    {
        return match ($this->hinging) {
            'butt' => 'BUTT HINGES',
            'pivot_offset' => 'OFFSET PIVOTS',
            'pivot_center' => 'CENTER PIVOTS',
            default => 'CONTINUOUS HINGE',
        };
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
