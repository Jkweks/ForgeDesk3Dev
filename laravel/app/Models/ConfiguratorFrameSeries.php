<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorFrameSeries extends Model
{
    use HasFactory;

    protected $fillable = ['frame_system_id', 'name', 'code', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function frameSystem()
    {
        return $this->belongsTo(ConfiguratorFrameSystem::class, 'frame_system_id');
    }

    public function profiles()
    {
        return $this->hasMany(ConfiguratorFrameProfile::class, 'frame_series_id')->orderBy('sort_order');
    }
}
