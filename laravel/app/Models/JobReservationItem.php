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
        'requested_qty' => 'decimal:4',
        'committed_qty' => 'decimal:4',
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
     * Sync the product's quantity_committed with bin-packed sum of active reservation items.
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

        $quantities = self::where('product_id', $this->product_id)
            ->whereHas('reservation', function ($query) {
                $query->whereIn('status', ['active', 'in_progress', 'on_hold'])
                      ->whereNull('deleted_at');
            })
            ->pluck('committed_qty')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $product->quantity_committed = self::computeCommitted($quantities);
        $product->save();
        $product->updateStatus();
    }

    /**
     * FFD bin-packing: pack fractional stick quantities into unit-capacity bins.
     *
     * Each "bin" is one stock-length stick. Items may not be split across sticks.
     * All sealed bins (not the last opened one) count as 1.0; the last bin counts
     * as its actual fill rounded up to the nearest 0.1.
     *
     * Examples:
     *   [0.8, 0.3]        → bins [0.8+0.2?No → 0.8 | 0.3] → 1 + ceil(0.3×10)/10 = 1.3
     *   [0.8, 0.3, 0.2]   → bins [0.8+0.2=1.0 | 0.3]      → 1 + ceil(0.3×10)/10 = 1.3
     *   [0.5, 0.5, 0.3]   → bins [0.5+0.5=1.0 | 0.3]      → 1 + 0.3 = 1.3
     */
    public static function computeCommitted(array $quantities): float
    {
        if (empty($quantities)) {
            return 0.0;
        }

        // Expand any qty > 1.0 into whole sticks + remainder
        $items = [];
        foreach ($quantities as $rawQty) {
            $qty = round((float) $rawQty, 6);
            if ($qty <= 0.000001) {
                continue;
            }
            $full = (int) floor($qty);
            $rem  = round($qty - $full, 6);
            for ($i = 0; $i < $full; $i++) {
                $items[] = 1.0;
            }
            if ($rem > 0.000001) {
                $items[] = $rem;
            }
        }

        if (empty($items)) {
            return 0.0;
        }

        // First-Fit Decreasing: largest items placed first to minimise bin count
        rsort($items);

        $bins = [];
        foreach ($items as $item) {
            $placed = false;
            foreach ($bins as &$binUsed) {
                if (round($binUsed + $item, 6) <= 1.000001) {
                    $binUsed = round($binUsed + $item, 6);
                    $placed  = true;
                    break;
                }
            }
            unset($binUsed);
            if (!$placed) {
                $bins[] = round($item, 6);
            }
        }

        $n        = count($bins);
        $lastUsed = $bins[$n - 1];

        // Sealed bins each count as 1.0; last bin rounds up to nearest 0.1
        return ($n - 1) + ceil(round($lastUsed * 10, 6)) / 10;
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
