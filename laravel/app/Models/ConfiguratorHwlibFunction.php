<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A hardware function/option code (e.g. EO, NL, DT, QEL, CD) — see the
 * configurator_hwlib_functions migration for how items, links, and groups
 * relate.
 */
class ConfiguratorHwlibFunction extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'label', 'group_name', 'notes', 'sort_order', 'active'];

    protected $casts = [
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];
}
