<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobReservationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'product_id',
        'requested_qty',
        'committed_qty',
        'consumed_qty',
    ];

    protected $casts = [
        'reservation_id' => 'integer',
        'product_id' => 'integer',
        'requested_qty' => 'integer',
        'committed_qty' => 'integer',
        'consumed_qty' => 'integer',
    ];

    /**
     * Boot method to add model event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // After creating/updating/deleting reservation items, sync product committed quantity
        static::saved(function ($item) {
            $item->syncProductCommittedQuantity();
        });

        static::deleted(function ($item) {
            $item->syncProductCommittedQuantity();
        });
    }

    /**
     * Sync the product's quantity_committed with sum of active reservation items
     */
    public function syncProductCommittedQuantity()
    {
        if (!$this->product_id) {
            return;
        }

        $product = Product::find($this->product_id);
        if (!$product) {
            return;
        }

        // Calculate total committed from all ACTIVE reservations
        $totalCommitted = self::where('product_id', $this->product_id)
            ->whereHas('reservation', function($query) {
                $query->whereIn('status', ['active', 'in_progress', 'on_hold'])
                      ->whereNull('deleted_at');
            })
            ->sum('committed_qty');

        $product->quantity_committed = $totalCommitted;
        $product->save();
    }

    /**
     * Get the reservation this item belongs to
     */
    public function reservation()
    {
        return $this->belongsTo(JobReservation::class, 'reservation_id');
    }

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the released quantity (committed - consumed)
     */
    public function getReleasedQtyAttribute()
    {
        return $this->committed_qty - $this->consumed_qty;
    }

    /**
     * Get the shortfall (requested - committed)
     */
    public function getShortfallAttribute()
    {
        return max(0, $this->requested_qty - $this->committed_qty);
    }
}
