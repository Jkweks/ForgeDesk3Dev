<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawingElevationDim extends Model
{
    public $timestamps = false;

    protected $fillable = ['drawing_elevation_id', 'axis', 'position', 'value'];
}
