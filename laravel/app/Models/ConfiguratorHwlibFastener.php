<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibFastener extends Model
{
    use HasFactory;

    protected $fillable = ['pn', 'description', 'notes', 'active', 'sort_order'];

    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];
}
