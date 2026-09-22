<?php

namespace App\Models\CutFlow;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings table for the cut station. Always accessed through
 * {@see CutFlowSetting::current()} — same singleton pattern as
 * CompanySetting/ConfiguratorSetting elsewhere in this app.
 */
class CutFlowSetting extends Model
{
    protected $connection = 'cutflow';

    protected $table = 'cutflow_settings';

    protected $fillable = [
        'cut_sensor_active',
        'show_cut_toast',
        'kerf_inches',
        'standard_stock_length',
    ];

    protected $casts = [
        'cut_sensor_active' => 'boolean',
        'show_cut_toast' => 'boolean',
        'kerf_inches' => 'decimal:3',
        'standard_stock_length' => 'decimal:3',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function kerfInches(): float
    {
        return $this->kerf_inches !== null
            ? (float) $this->kerf_inches
            : (float) config('cutflow.kerf_inches', 0.125);
    }

    public function standardStockLength(): float
    {
        return $this->standard_stock_length !== null
            ? (float) $this->standard_stock_length
            : (float) config('cutflow.standard_stock_length', 288);
    }
}
