<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmProduct extends Model
{
    protected $fillable = ['pm_manufacturer_id', 'pm_product_category_id', 'name', 'model', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function manufacturer()
    {
        return $this->belongsTo(PmManufacturer::class, 'pm_manufacturer_id');
    }

    public function category()
    {
        return $this->belongsTo(PmProductCategory::class, 'pm_product_category_id');
    }

    public function documents()
    {
        return $this->hasMany(PmProductDocument::class)->with('file:id,original_name,size,sha256');
    }
}
