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

        $product->quantity_committed = self::binAwareCommitted($this->product_id);
        $product->save();
        $product->updateStatus();
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
     * Calculate total committed material for a product using bin-packing logic.
     *
     * A raw sum (e.g. 1.0 + 4×0.3 = 2.2) underestimates when cuts leave waste on a
     * stick. This packs items greedily into inventory locations (treating each location's
     * on-hand quantity as the bin capacity) and counts any leftover space that is too
     * small for the smallest item as committed waste.
     *
     * Example: items=[1.0, 0.3, 0.3, 0.3, 0.3], bins=[1.0, 1.0, 1.0]
     *   bin1 → 1.0 used (exact fit)
     *   bin2 → 0.9 used, 0.1 left — too small for 0.3 → waste, committed = 1.0
     *   bin3 → 0.3 used
     *   total = 1.0 + 1.0 + 0.3 = 2.3
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

        $locations = InventoryLocation::where('product_id', $productId)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderByDesc('quantity')
            ->get();

        if ($locations->isEmpty()) {
            return round(array_sum($items) * 10) / 10;
        }

        $bins = $locations->map(fn($loc) => (float) $loc->quantity)->toArray();
        $binUsed = array_fill(0, count($bins), 0.0);
        $minItem = min($items);
        $unplaced = [];

        foreach ($items as $item) {
            $placed = false;
            for ($b = 0; $b < count($bins); $b++) {
                $space = round(($bins[$b] - $binUsed[$b]) * 10) / 10;
                if ($space >= $item - 0.00001) {
                    $binUsed[$b] = round(($binUsed[$b] + $item) * 10) / 10;
                    $placed = true;
                    break;
                }
            }
            if (!$placed) {
                $unplaced[] = $item;
            }
        }

        $total = 0.0;
        for ($b = 0; $b < count($bins); $b++) {
            if ($binUsed[$b] <= 0) continue;
            $remaining = round(($bins[$b] - $binUsed[$b]) * 10) / 10;
            // Waste: leftover space is positive but smaller than the smallest item
            if ($remaining > 0 && $remaining < $minItem - 0.00001) {
                $total += $bins[$b]; // commit the whole bin including waste
            } else {
                $total += $binUsed[$b];
            }
        }

        $total += array_sum($unplaced);

        return round($total * 10) / 10;
    }
}
