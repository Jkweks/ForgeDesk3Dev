<?php

namespace App\Models\CutFlow;

use Illuminate\Database\Eloquent\Model;

class StickSession extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = [
        'length_label',
        'part_name',
        'finish',
        'length_inches',
        'waste_inches',
        'drop_plan',
        'status',
        'cancelled_at',
    ];

    protected $casts = [
        'length_inches' => 'decimal:3',
        'waste_inches' => 'decimal:3',
        'drop_plan' => 'array',
        'cancelled_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(StickItem::class)->orderBy('sequence');
    }

    public function currentItem(): ?StickItem
    {
        return $this->items()->where('status', 'pending')->orderBy('sequence')->first();
    }
}
