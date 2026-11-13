<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibBacker extends Model
{
    use HasFactory;

    protected $fillable = ['pn', 'description', 'length', 'notes', 'active', 'sort_order'];

    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer', 'length' => 'decimal:4'];

    public function fasteners()
    {
        return $this->hasMany(ConfiguratorHwlibBackerFastener::class, 'backer_id')->orderBy('sort_order');
    }
}
