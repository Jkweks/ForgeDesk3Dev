<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorDoorType extends Model
{
    use HasFactory;

    protected $fillable = [
        'series', 'stile_name', 'stile_height', 'std_top_rail_label', 'std_bot_rail_label',
        'bev_pn', 'rab_pn', 'cp_pn', 'ast_pn', 'inact_pn',
    ];

    protected $casts = [
        'stile_height' => 'decimal:4',
    ];
}
