<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibCategoryVariable extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'variable_id', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function category()
    {
        return $this->belongsTo(ConfiguratorHwlibCategory::class, 'category_id');
    }

    public function variable()
    {
        return $this->belongsTo(ConfiguratorHwlibVariable::class, 'variable_id');
    }
}
