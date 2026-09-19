<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorFrameComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'frame_profile_id',
        'label',
        'product_id',
        'qty_type',
        'qty_per',
        'sort_order',
    ];

    protected $casts = [
        'qty_per' => 'decimal:3',
        'sort_order' => 'integer',
    ];

    public static $qtyTypes = [
        'per_opening' => 'Per Opening',
        'per_door' => 'Per Door',
        'per_length' => 'Per Length (ft)',
    ];

    public function frameProfile()
    {
        return $this->belongsTo(ConfiguratorFrameProfile::class, 'frame_profile_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function fasteners()
    {
        return $this->hasMany(ConfiguratorFrameFastener::class, 'frame_component_id')->orderBy('sort_order');
    }
}
