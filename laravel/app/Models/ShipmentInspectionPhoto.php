<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentInspectionPhoto extends Model
{
    protected $fillable = ['inspection_id', 'item_id', 'original_name', 'file_path', 'file_size', 'file_mime'];

    protected $hidden = ['file_path'];

    public function inspection()
    {
        return $this->belongsTo(ShipmentInspection::class, 'inspection_id');
    }
}
