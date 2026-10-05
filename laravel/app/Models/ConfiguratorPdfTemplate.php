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

        return is_string($saved) ? (json_decode($saved, true) ?: PackageReportService::DEFAULT_LAYOUTS[$type]) : ($saved ?: PackageReportService::DEFAULT_LAYOUTS[$type]);
    }
}
