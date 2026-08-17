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
        'requested_qty' => 'decimal:1',
        'committed_qty' => 'decimal:1',
        'consumed_qty' => 'decimal:1',
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

        $product->recalculateCommittedQuantity();
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

    /**
     * Calculate committed material using greedy bin-packing into 1.0-unit sticks.
     *
     * Cuts are packed largest-first into sticks of length 1.0. If the leftover space
     * on a stick is smaller than the smallest remaining cut, that space is counted as
     * committed waste (unusable for other jobs).
     *
     * Example: cuts=[1.0, 0.5, 0.3, 0.3, 0.3]
     *   stick1 → 1.0 (full)
     *   stick2 → 0.5 + 0.3 = 0.8, leftover 0.2 < 0.3 → waste → commit 1.0
     *   stick3 → 0.3 + 0.3 = 0.6, leftover 0.4 ≥ 0.3 → commit 0.6
     *   total = 2.6
     */
    public static function binAwareCommitted(int $productId): float
    {
        $items = self::where('product_id', $productId)
            ->whereHas('reservation', fn($q) =>
                $q->whereIn('status', ['active', 'in_progress', 'on_hold'])
                  ->whereNull('deleted_at')
            )
            ->orderByDesc('committed_qty')
            ->pluck('committed_qty')
            ->map(fn($v) => (float) $v)
            ->toArray();

        if (empty($items)) {
            return 0.0;
        }

        $stickLength = 1.0;
        $minItem = min($items);
        $bins = []; // each entry = used length on that stick

        foreach ($items as $item) {
            $placed = false;
            foreach ($bins as &$used) {
                $space = round(($stickLength - $used) * 10) / 10;
                if ($space >= $item - 0.00001) {
                    $used = round(($used + $item) * 10) / 10;
                    $placed = true;
                    break;
                }
            }
            unset($used);
            if (!$placed) {
                $bins[] = round($item * 10) / 10;
            }
        }

        $total = 0.0;
        foreach ($bins as $used) {
            $remaining = round(($stickLength - $used) * 10) / 10;
            if ($remaining > 0 && $remaining < $minItem - 0.00001) {
                $total += $stickLength; // leftover too small to cut — commit whole stick
            } else {
                $total += $used;
            }
        }

        return round($total * 10) / 10;
    }
}
