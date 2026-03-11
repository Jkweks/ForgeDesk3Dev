<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommittedInventory extends Model
{
    use HasFactory;

    protected $table = 'committed_inventory';
    protected $fillable = [
        'product_id', 'order_id', 'order_item_id', 'quantity_committed',
        'committed_date', 'expected_release_date',
    ];

    protected $casts = [
        'committed_date' => 'date',
        'expected_release_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
