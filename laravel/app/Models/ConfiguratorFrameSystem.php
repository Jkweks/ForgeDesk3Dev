<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorFrameSystem extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function series()
    {
        return $this->hasMany(ConfiguratorFrameSeries::class, 'frame_system_id')->orderBy('sort_order');
    }
}
