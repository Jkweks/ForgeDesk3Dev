<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Record that job-PO material arrived. Never moves stock. */
class PurchaseOrderReceipt extends Model
{
    protected $fillable = ['purchase_order_id', 'purchase_order_item_id', 'quantity', 'received_date', 'received_by', 'flagged', 'notes'];

    protected $casts = ['received_date' => 'date', 'flagged' => 'boolean', 'quantity' => 'decimal:2'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}
