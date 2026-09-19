<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorRailLug extends Model
{
    use HasFactory;

    protected $fillable = ['rail_pn', 'lug_pn'];
}
