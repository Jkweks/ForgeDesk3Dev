<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmProductCategory extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function products()
    {
        return $this->hasMany(PmProduct::class);
    }
}
