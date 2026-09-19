<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorGlassSpec extends Model
{
    use HasFactory;

    protected $fillable = [
        'thickness', 'stop_pn', 'gasket_pn', 'gasket2_pn', 'gasket_qty_factor', 'stop_height',
    ];

    protected $casts = [
        'gasket_qty_factor' => 'decimal:2',
        'stop_height' => 'decimal:2',
    ];
}
