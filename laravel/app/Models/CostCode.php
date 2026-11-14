<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCode extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $fillable = ['code', 'category', 'description', 'when_to_use'];
}
