<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorRail extends Model
{
    use HasFactory;

    protected $fillable = [
        'rail_type', 'label', 'std_pn', 'thermal_pn', 'mon_pn', 'value_in',
        'stacked_std_pn', 'stacked_thermal_pn', 'stacked_mon_pn',
    ];

    protected $casts = [
        'value_in' => 'decimal:5',
    ];

    public function pnForSeries(string $series): ?string
    {
        return match ($series) {
            'STANDARD' => $this->std_pn,
            'THERMAL' => $this->thermal_pn,
            'MONUMENTAL' => $this->mon_pn,
            default => null,
        };
    }

    public function stackedPnForSeries(string $series): ?string
    {
        return match ($series) {
            'STANDARD' => $this->stacked_std_pn,
            'THERMAL' => $this->stacked_thermal_pn,
            'MONUMENTAL' => $this->stacked_mon_pn,
            default => null,
        };
    }

    public function isStacked(): bool
    {
        return str_contains($this->label ?? '', 'stacked');
    }
}
