<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'subcategory_id', 'name', 'manufacturer', 'model_number', 'pn', 'notes',
        'active', 'needs_review', 'vos_standard', 'finishes', 'min_width', 'max_width',
        'min_height', 'max_height', 'field_install', 'by_others', 'handed', 'push_pull',
        'default_strike_item_id', 'default_cover_item_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'needs_review' => 'boolean',
        'vos_standard' => 'boolean',
        'finishes' => 'array',
        'min_width' => 'decimal:4',
        'max_width' => 'decimal:4',
        'min_height' => 'decimal:4',
        'max_height' => 'decimal:4',
        'field_install' => 'boolean',
        'by_others' => 'boolean',
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

    /**
     * The category's prep variables that apply to this item. A variable tagged push or pull is left
     * out for an item on the other side (a push bar is not asked for pull values); an item with no
     * side keeps every variable.
     */
    public function applicableVariables()
    {
        $variables = $this->category?->variables ?? collect();
        if (! $this->push_pull) {
            return $variables;
        }

        return $variables->filter(fn ($v) => ! $v->push_pull || $v->push_pull === $this->push_pull)->values();
    }

    public function values()
    {
        return $this->hasMany(ConfiguratorHwlibItemValue::class, 'item_id');
    }

    /** Functions this item can be ordered with (its allowed picklist). */
    public function functions()
    {
        return $this->belongsToMany(
            ConfiguratorHwlibFunction::class,
            'configurator_hwlib_item_functions',
            'item_id',
            'function_id'
        )->orderBy('configurator_hwlib_functions.sort_order')->orderBy('configurator_hwlib_functions.label');
    }

    public function documents()
    {
        return $this->hasMany(ConfiguratorHwlibItemDocument::class, 'item_id')->orderBy('doc_type')->orderBy('title');
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
