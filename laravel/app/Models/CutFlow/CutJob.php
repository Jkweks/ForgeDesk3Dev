<?php

namespace App\Models\CutFlow;

use Illuminate\Database\Eloquent\Model;

class CutJob extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = [
        'name',
        'work_order_id',
        'bom_diverged_at',
    ];

    protected $casts = ['bom_diverged_at' => 'datetime'];

    public function parts()
    {
        return $this->hasMany(Part::class);
    }
}
