<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoorFrameConfigurationDoor extends Model
{
    use HasFactory;

    protected $fillable = [
        'configuration_id',
        'door_tag',
    ];

    /**
     * Get the configuration this door belongs to
     */
    public function configuration()
    {
        return $this->belongsTo(DoorFrameConfiguration::class, 'configuration_id');
    }
}
