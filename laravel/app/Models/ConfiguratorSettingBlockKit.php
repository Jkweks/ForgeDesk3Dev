<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorSettingBlockKit extends Model
{
    use HasFactory;

    protected $fillable = ['series', 'glass_thickness', 'kit1_pn', 'kit2_pn'];
}
