<?php

namespace App\Models\CutFlow;

use Illuminate\Database\Eloquent\Model;

class StickItem extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = [
        'stick_session_id',
        'part_id',
        'dimension_inches',
        'sequence',
        'status',
    ];

    protected $casts = [
        'dimension_inches' => 'decimal:3',
    ];

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function stickSession()
    {
        return $this->belongsTo(StickSession::class);
    }
}
