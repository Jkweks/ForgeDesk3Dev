<?php

namespace App\Services\CutFlow;

use App\Models\CutlistRuleSetting;
use Throwable;

/**
 * The sill quarter-point configuration as the service sees it: config/sill_quarter_points.php (the defaults)
 * with the admin's overrides from the Development > Cut-list rules page laid over the top.
 */
class SillQuarterPointSettings
{
    public const ROLE_DEFAULTS = [
        'sill' => 'SILL',
        'female_vertical' => 'FEMALE VERTICAL',
        'left_jamb' => 'LEFT JAMB',
        'right_jamb' => 'RIGHT JAMB',
        'threshold' => 'THRESHOLD',
        'receptor' => 'SILL RECEPTORS CHANNEL',
    ];

    public const DOOR_TAG_DEFAULTS = ['{SIDELITE JAMB}', '{TERMINATING JAMB}'];

    /** key => [type, min, max]; the editable scalar settings. */
    public const SCALARS = [
        'left_gap' => ['float', 0, 12], 'right_gap' => ['float', 0, 12], 'vertical_width' => ['float', 0, 24],
        'default_jamb_width' => ['float', 0, 24], 'comp_jamb_width' => ['float', 0, 24], 'receptor_gap' => ['float', 0, 12],
        'flag_tolerance' => ['float', 0, 12], 'knee_wall_tolerance' => ['float', 0, 12],
        'fraction_denominator' => ['int', 2, 128],
    ];

    public static function defaults(): array
    {
        $cfg = config('sill_quarter_points');
        $cfg['roles'] = ($cfg['roles'] ?? []) + self::ROLE_DEFAULTS;
        $cfg['door_tags'] = $cfg['door_tags'] ?? self::DOOR_TAG_DEFAULTS;

        return $cfg;
    }

    public static function overrides(): array
    {
        try {
            return CutlistRuleSetting::all()->pluck('value', 'key')->all();
        } catch (Throwable) {
            return []; // table not migrated yet (or no DB in a unit test): defaults only
        }
    }

    public static function effective(): array
    {
        $cfg = self::defaults();
        $over = self::overrides();
        foreach ($over as $key => $value) {
            if (in_array($key, ['roles', 'part_widths'], true) && is_array($value)) {
                $cfg[$key] = $value + ($cfg[$key] ?? []);
            } elseif ($value !== null && (array_key_exists($key, self::SCALARS) || $key === 'door_tags')) {
                $cfg[$key] = $value;
            }
        }

        return $cfg;
    }
}
