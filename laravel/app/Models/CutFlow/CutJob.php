<?php

namespace App\Models\CutFlow;

use Illuminate\Database\Eloquent\Model;

class CutJob extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = [
        'name',
        'work_order_id',
    ];

    public function parts()
    {
        return $this->hasMany(Part::class);
    }
}
