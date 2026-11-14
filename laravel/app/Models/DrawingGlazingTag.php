<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawingGlazingTag extends Model
{
    public $timestamps = false;

    protected $fillable = ['drawing_set_id', 'tag', 'makeup'];
}
