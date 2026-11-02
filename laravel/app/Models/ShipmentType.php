<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentType extends Model
{
    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(ShipmentCheckItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function itemsFor(string $phase)
    {
        return $this->items()->where('phase', $phase)->where('is_active', true);
    }
}
