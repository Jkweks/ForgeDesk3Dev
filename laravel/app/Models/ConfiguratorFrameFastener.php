<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorFrameFastener extends Model
{
    use HasFactory;

    protected $fillable = [
        'frame_component_id',
        'label',
        'product_id',
        'qty_per',
        'sort_order',
    ];

    protected $casts = [
        'qty_per' => 'decimal:3',
        'sort_order' => 'integer',
    ];

    public function frameComponent()
    {
        return $this->belongsTo(ConfiguratorFrameComponent::class, 'frame_component_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
