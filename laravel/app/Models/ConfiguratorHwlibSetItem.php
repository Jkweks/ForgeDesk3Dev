<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibSetItem extends Model
{
    use HasFactory;

    protected $fillable = ['set_id', 'item_id', 'quantity', 'notes', 'series', 'leaf'];

    protected $casts = ['quantity' => 'integer'];

    public function set()
    {
        return $this->belongsTo(ConfiguratorHwlibSet::class, 'set_id');
    }

    public function item()
    {
        return $this->belongsTo(ConfiguratorHwlibItem::class, 'item_id');
    }

    public function values()
    {
        return $this->hasMany(ConfiguratorHwlibSetItemValue::class, 'set_item_id');
    }
}
