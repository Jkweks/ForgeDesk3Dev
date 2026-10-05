<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibSubcategory extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'name', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function category()
    {
        return $this->belongsTo(ConfiguratorHwlibCategory::class, 'category_id');
    }

    public function items()
    {
        return $this->hasMany(ConfiguratorHwlibItem::class, 'subcategory_id');
    }
}
