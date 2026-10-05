<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibSet extends Model
{
    use HasFactory;

    protected $fillable = ['business_job_id', 'name', 'notes', 'is_pair'];

    protected $casts = ['is_pair' => 'boolean'];

    public function businessJob()
    {
        return $this->belongsTo(BusinessJob::class);
    }

    public function setItems()
    {
        return $this->hasMany(ConfiguratorHwlibSetItem::class, 'set_id');
    }

    public function appliedConfigurations()
    {
        return $this->belongsToMany(DoorFrameConfiguration::class, 'door_frame_configuration_hwlib_sets', 'set_id', 'configuration_id')
            ->withPivot('applied_at');
    }
}
