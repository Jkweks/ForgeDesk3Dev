<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawingSetWarning extends Model
{
    public $timestamps = false;

    protected $fillable = ['drawing_set_id', 'message'];
}
