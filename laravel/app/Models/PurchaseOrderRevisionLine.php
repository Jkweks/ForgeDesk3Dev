<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderRevisionLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'purchase_order_revision_id', 'parent_line_id', 'sort_order', 'description', 'kind',
        'quantity_ordered', 'unit_cost', 'total_cost', 'cost_code', 'notes',
    ];
}
