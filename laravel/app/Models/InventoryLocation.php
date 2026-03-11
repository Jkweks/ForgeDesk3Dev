<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'storage_location_id',
        'quantity',
        'quantity_committed',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected $appends = ['quantity_available'];

    /**
     * Get the product this location belongs to
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the storage location this inventory is at
     */
    public function storageLocation()
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    /**
     * Calculate available quantity at this location
     */
    public function getQuantityAvailableAttribute()
    {
        return $this->quantity - $this->quantity_committed;
    }
}
