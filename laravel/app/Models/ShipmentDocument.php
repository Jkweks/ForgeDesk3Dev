<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A signed shipping ticket (PDF or image) attached to a shipment by the office. */
class ShipmentDocument extends Model
{
    protected $fillable = ['shipment_id', 'kind', 'original_name', 'file_path', 'file_mime', 'file_size', 'uploaded_by'];

    protected $hidden = ['file_path'];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
