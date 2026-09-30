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
        'bridge_url',
        'bridge_token',
        'bridge_fake',
        'tablet_allowed_ips',
    ];

    protected $casts = [
        'cut_sensor_active' => 'boolean',
        'show_cut_toast' => 'boolean',
        'kerf_inches' => 'decimal:3',
        'standard_stock_length' => 'decimal:3',
        'bridge_fake' => 'boolean',
    ];

    protected $hidden = [
        'bridge_token',
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

    public function bridgeUrl(): string
    {
        return $this->bridge_url !== null && $this->bridge_url !== ''
            ? $this->bridge_url
            : (string) config('services.tiger_bridge.url', 'http://127.0.0.1:9111');
    }

    public function bridgeToken(): string
    {
        return $this->bridge_token !== null && $this->bridge_token !== ''
            ? $this->bridge_token
            : (string) config('services.tiger_bridge.token', '');
    }

    public function bridgeFake(): bool
    {
        return (bool) $this->bridge_fake;
    }

    /**
     * @return array<int, string>
     */
    public function tabletAllowedIps(): array
    {
        if ($this->tablet_allowed_ips === null || $this->tablet_allowed_ips === '') {
            return config('cutflow.tablet_allowed_ips', []);
        }

        return array_values(array_filter(array_map(
            'trim',
            explode(',', $this->tablet_allowed_ips)
        )));
    }
}
