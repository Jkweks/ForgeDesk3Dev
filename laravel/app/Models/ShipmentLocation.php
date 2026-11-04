<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A named pickup / drop-off place. Presets carry a fixed address; "requires_address" ones (Jobsite) take one per shipment. */
class ShipmentLocation extends Model
{
    protected $fillable = ['name', 'kind', 'requires_address', 'saves_visits', 'address', 'lat', 'lng', 'is_active', 'sort_order'];

    protected $casts = ['requires_address' => 'boolean', 'saves_visits' => 'boolean', 'is_active' => 'boolean', 'lat' => 'float', 'lng' => 'float'];
}
