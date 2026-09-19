<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorTieRod extends Model
{
    use HasFactory;

    protected $fillable = ['pn', 'min_len', 'max_len', 'series', 'mid_val'];

    protected $casts = [
        'min_len' => 'decimal:4',
        'max_len' => 'decimal:4',
        'mid_val' => 'decimal:4',
    ];
}
