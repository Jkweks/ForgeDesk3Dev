<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'status',
        'order_date',
        'expected_date',
        'received_date',
        'total_amount',
        'notes',
        'ship_to',
        'contact_name',
        'contact_email',
        'contact_phone',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Computed properties
    public function getIsFullyReceivedAttribute()
    {
        return $this->items()->whereColumn('quantity_received', '<', 'quantity_ordered')->count() === 0;
    }

    public function getIsPartiallyReceivedAttribute()
    {
        return $this->items()->where('quantity_received', '>', 0)->count() > 0
            && !$this->is_fully_received;
    }

    public function getTotalReceivedAttribute()
    {
        return $this->items()->sum('quantity_received');
    }

    public function getTotalOrderedAttribute()
    {
        return $this->items()->sum('quantity_ordered');
    }

    public function getReceiveProgressAttribute()
    {
        $total = $this->total_ordered;
        if ($total == 0) return 0;
        return round(($this->total_received / $total) * 100, 1);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['submitted', 'approved', 'partially_received']);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'submitted']);
    }

    public function scopeReceivable($query)
    {
        return $query->whereIn('status', ['approved', 'partially_received']);
    }

    // Generate next PO number
    public static function generatePoNumber()
    {
        $year = date('Y');
        $lastPo = self::where('po_number', 'like', "PO-{$year}-%")
            ->orderBy('po_number', 'desc')
            ->first();

        if ($lastPo) {
            $lastNum = intval(substr($lastPo->po_number, -4));
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return "PO-{$year}-" . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
}
