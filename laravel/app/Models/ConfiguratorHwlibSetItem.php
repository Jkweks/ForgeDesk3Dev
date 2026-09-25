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

    /** Functions selected for this hardware item on this set. */
    public function functions()
    {
        return $this->belongsToMany(
            ConfiguratorHwlibFunction::class,
            'configurator_hwlib_set_item_functions',
            'set_item_id',
            'function_id'
        )->orderBy('configurator_hwlib_functions.sort_order')->orderBy('configurator_hwlib_functions.label');
    }
}
