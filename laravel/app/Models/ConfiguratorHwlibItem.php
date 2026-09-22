<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'subcategory_id', 'name', 'manufacturer', 'model_number', 'pn', 'notes',
        'active', 'vos_standard', 'finishes', 'min_width', 'max_width',
        'min_height', 'max_height', 'field_install', 'handed',
        'default_strike_item_id', 'default_cover_item_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'vos_standard' => 'boolean',
        'finishes' => 'array',
        'min_width' => 'decimal:4',
        'max_width' => 'decimal:4',
        'min_height' => 'decimal:4',
        'max_height' => 'decimal:4',
        'field_install' => 'boolean',
        'handed' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ConfiguratorHwlibCategory::class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(ConfiguratorHwlibSubcategory::class, 'subcategory_id');
    }

    public function values()
    {
        return $this->hasMany(ConfiguratorHwlibItemValue::class, 'item_id');
    }

    public function backers()
    {
        return $this->hasMany(ConfiguratorHwlibItemBacker::class, 'item_id')->orderBy('sort_order');
    }

    public function defaultStrikeItem()
    {
        return $this->belongsTo(self::class, 'default_strike_item_id');
    }

    public function defaultCoverItem()
    {
        return $this->belongsTo(self::class, 'default_cover_item_id');
    }
}
