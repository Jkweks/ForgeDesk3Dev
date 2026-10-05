<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorMidLug extends Model
{
    use HasFactory;

    protected $fillable = ['rail_pn', 'lug_pn', 'f1_pn', 'f1_qty', 'f2_pn', 'f2_qty'];

    protected $casts = [
        'f1_qty' => 'decimal:2',
        'f2_qty' => 'decimal:2',
    ];
}
