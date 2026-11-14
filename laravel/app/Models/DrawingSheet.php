<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawingSheet extends Model
{
    public $timestamps = false;

    protected $fillable = ['drawing_elevation_id', 'page', 'width', 'height', 'title_x0', 'title_top', 'title_x1', 'title_bottom'];
}
