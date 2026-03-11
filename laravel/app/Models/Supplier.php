<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'website',
        'notes',
        'default_lead_time_days',
        'minimum_order_amount',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'minimum_order_amount' => 'decimal:2',
    ];

    /**
     * Get all products from this supplier
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get all purchase orders for this supplier
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
