<?php

namespace App\Models;

use App\Services\Configurator\PackageReportService;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorPdfTemplate extends Model
{
    protected $fillable = ['report_type', 'layout'];

    protected $casts = ['layout' => 'array'];

    /** Saved layout for a report type, or the built-in default when nothing has been saved. */
    public static function layoutFor(string $type): array
    {
        $saved = static::where('report_type', $type)->value('layout');
        $defaults = PackageReportService::DEFAULT_LAYOUTS[$type];
        $layout = is_string($saved) ? (json_decode($saved, true) ?: $defaults) : ($saved ?: $defaults);

        return static::withNewBlocks($layout, $defaults);
    }

    /**
     * A layout saved before a block existed has no entry for it, so the block would never print until the
     * layout was edited. Slot each missing default block in right after the block that precedes it in the
     * defaults (or first, when nothing precedes it).
     */
    private static function withNewBlocks(array $layout, array $defaults): array
    {
        $have = array_column($layout, 'key');
        foreach ($defaults as $i => $block) {
            if (in_array($block['key'], $have, true)) {
                continue;
            }
            $after = $i > 0 ? array_search($defaults[$i - 1]['key'], array_column($layout, 'key'), true) : false;
            array_splice($layout, $after === false ? 0 : $after + 1, 0, [$block]);
            $have = array_column($layout, 'key');
        }

        return $layout;
    }
}
