<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentLoad extends Model
{
    protected $fillable = ['shipment_id', 'shipment_type_id', 'description', 'piece_count'];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function type()
    {
        return $this->belongsTo(ShipmentType::class, 'shipment_type_id');
    }
}
