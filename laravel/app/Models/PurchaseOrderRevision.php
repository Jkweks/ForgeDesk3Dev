<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderRevision extends Model
{
    protected $fillable = [
        'purchase_order_id', 'revision_number', 'supplier_id', 'issue_stage', 'expected_date',
        'cost_code', 'notes', 'total_amount', 'created_by',
    ];

    protected $casts = ['expected_date' => 'date', 'total_amount' => 'decimal:2'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines()
    {
        return $this->hasMany(PurchaseOrderRevisionLine::class)->orderBy('sort_order')->orderBy('id');
    }
}
