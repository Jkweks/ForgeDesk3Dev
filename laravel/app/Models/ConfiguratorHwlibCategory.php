<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function categoryVariables()
    {
        return $this->hasMany(ConfiguratorHwlibCategoryVariable::class, 'category_id')->orderBy('sort_order');
    }

    public function variables()
    {
        return $this->belongsToMany(ConfiguratorHwlibVariable::class, 'configurator_hwlib_category_variables', 'category_id', 'variable_id')
            ->withPivot('sort_order')->orderBy('configurator_hwlib_category_variables.sort_order');
    }

    public function items()
    {
        return $this->hasMany(ConfiguratorHwlibItem::class, 'category_id')->orderBy('name');
    }
}
