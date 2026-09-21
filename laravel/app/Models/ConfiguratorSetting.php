<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configurator-wide gap defaults singleton — always accessed through
 * {@see ConfiguratorSetting::current()}, same pattern as CompanySetting.
 */
class ConfiguratorSetting extends Model
{
    protected $fillable = ['top_gap', 'bottom_gap', 'hinge_gap', 'lock_gap'];

    protected $casts = [
        'top_gap' => 'decimal:4',
        'bottom_gap' => 'decimal:4',
        'hinge_gap' => 'decimal:4',
        'lock_gap' => 'decimal:4',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
