<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmManufacturer extends Model
{
    protected $fillable = ['name', 'website', 'notes'];

    public function products()
    {
        return $this->hasMany(PmProduct::class);
    }
}
