<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Attaches a hardware item to a door/frame configuration (opening). This is
 * ForgeDesk's equivalent of fab_utils' hwlib_saved_config_links.
 */
class ConfiguratorHwlibLink extends Model
{
    use HasFactory;

    protected $fillable = ['configuration_id', 'item_id', 'source_set_id', 'quantity', 'notes', 'series', 'leaf'];

    protected $casts = ['quantity' => 'integer'];

    public function configuration()
    {
        return $this->belongsTo(DoorFrameConfiguration::class, 'configuration_id');
    }

    public function item()
    {
        return $this->belongsTo(ConfiguratorHwlibItem::class, 'item_id');
    }

    public function sourceSet()
    {
        return $this->belongsTo(ConfiguratorHwlibSet::class, 'source_set_id');
    }

    public function values()
    {
        return $this->hasMany(ConfiguratorHwlibLinkValue::class, 'link_id');
    }

    /** Functions actually selected for this hardware item on this configuration. */
    public function functions()
    {
        return $this->belongsToMany(
            ConfiguratorHwlibFunction::class,
            'configurator_hwlib_link_functions',
            'link_id',
            'function_id'
        )->orderBy('configurator_hwlib_functions.sort_order')->orderBy('configurator_hwlib_functions.label');
    }
}
