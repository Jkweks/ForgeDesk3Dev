<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'product_id',
        'location_id',
        'system_quantity',
        'counted_quantity',
        'variance',
        'variance_status',
        'count_notes',
        'counted_by',
        'counted_at',
        'adjustment_created',
        'transaction_id',
    ];

    protected $casts = [
        'system_quantity' => 'integer',
        'counted_quantity' => 'integer',
        'variance' => 'integer',
        'counted_at' => 'datetime',
        'adjustment_created' => 'boolean',
    ];

    protected $appends = ['counting_unit', 'pack_size', 'system_quantity_eaches', 'counted_quantity_eaches'];

    // Relationships
    public function session()
    {
        return $this->belongsTo(CycleCountSession::class, 'session_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    public function counter()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function transaction()
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    // Computed properties

    /**
     * Get the pack size for this item's product
     */
    public function getPackSizeAttribute()
    {
        return $this->product?->pack_size ?? 1;
    }

    /**
     * Check if this product is counted in packs
     */
    public function hasPackSize()
    {
        return $this->pack_size > 1;
    }

    /**
     * Get the counting unit label
     */
    public function getCountingUnitAttribute()
    {
        if ($this->hasPackSize()) {
            return $this->product?->purchase_uom ?? 'packs';
        }
        return $this->product?->stock_uom ?? 'EA';
    }

    /**
     * Get system quantity in eaches (converts from packs if applicable)
     */
    public function getSystemQuantityEachesAttribute()
    {
        if ($this->hasPackSize()) {
            return $this->system_quantity * $this->pack_size;
        }
        return $this->system_quantity;
    }

    /**
     * Get counted quantity in eaches (converts from packs if applicable)
     */
    public function getCountedQuantityEachesAttribute()
    {
        if ($this->counted_quantity === null) {
            return null;
        }
        if ($this->hasPackSize()) {
            return $this->counted_quantity * $this->pack_size;
        }
        return $this->counted_quantity;
    }

    /**
     * Get variance in eaches
     */
    public function getVarianceEachesAttribute()
    {
        if ($this->hasPackSize()) {
            return $this->variance * $this->pack_size;
        }
        return $this->variance;
    }

    public function getVariancePercentageAttribute()
    {
        if ($this->system_quantity == 0) {
            return $this->counted_quantity > 0 ? 100 : 0;
        }
        return round(($this->variance / $this->system_quantity) * 100, 1);
    }

    public function getIsAccurateAttribute()
    {
        return $this->variance == 0;
    }

    public function getNeedsReviewAttribute()
    {
        // For pack-based items, use a smaller threshold (e.g., 1 pack)
        // For each-based items, use 5 eaches
        $threshold = $this->hasPackSize() ? 1 : 5;
        return abs($this->variance) > $threshold;
    }

    // Record count
    public function recordCount($countedQuantity, $userId, $notes = null)
    {
        $variance = $countedQuantity - $this->system_quantity;

        // Determine variance status
        $varianceStatus = 'pending';
        if ($variance == 0) {
            $varianceStatus = 'within_tolerance';
        } elseif (abs($variance) > 5) { // Configurable threshold
            $varianceStatus = 'requires_review';
        } else {
            $varianceStatus = 'within_tolerance';
        }

        $this->update([
            'counted_quantity' => $countedQuantity,
            'variance' => $variance,
            'variance_status' => $varianceStatus,
            'count_notes' => $notes,
            'counted_by' => $userId,
            'counted_at' => now(),
        ]);

        return $this;
    }

    // Approve variance and create adjustment
    public function approveVariance($userId)
    {
        if ($this->variance == 0) {
            $this->update(['variance_status' => 'approved']);
            return null;
        }

        // Get values in eaches (convert from packs if applicable)
        $varianceEaches = $this->variance_eaches;
        $systemEaches = $this->system_quantity_eaches;
        $countedEaches = $this->counted_quantity_eaches;

        // Build notes with pack information if applicable
        $notes = $this->hasPackSize()
            ? "Cycle count adjustment: Expected {$this->system_quantity} packs ({$systemEaches} ea), Counted {$this->counted_quantity} packs ({$countedEaches} ea). " . ($this->count_notes ?? '')
            : "Cycle count adjustment: Expected {$this->system_quantity}, Counted {$this->counted_quantity}. " . ($this->count_notes ?? '');

        // Cycle counts are now location-based
        // Update the specific inventory location quantity (in eaches)
        if ($this->location_id) {
            $location = $this->location;
            $location->quantity = $countedEaches;
            $location->save();

            // Recalculate product quantity_on_hand as sum of all locations
            $product = $this->product;
            $previousQtyOnHand = $product->quantity_on_hand;
            $product->recalculateQuantitiesFromLocations();

            // Calculate the total variance for the transaction record
            $totalVarianceEaches = $product->quantity_on_hand - $previousQtyOnHand;

            // Create inventory adjustment transaction (in eaches)
            $transaction = InventoryTransaction::create([
                'product_id' => $this->product_id,
                'type' => 'cycle_count',
                'quantity' => $totalVarianceEaches,
                'quantity_before' => $previousQtyOnHand,
                'quantity_after' => $product->quantity_on_hand,
                'reference_number' => $this->session->session_number,
                'reference_type' => 'cycle_count',
                'reference_id' => $this->session_id,
                'notes' => $notes . " [Location-based count: {$location->storageLocation->name}]",
                'user_id' => $userId,
                'transaction_date' => now(),
            ]);

            $this->update([
                'variance_status' => 'approved',
                'adjustment_created' => true,
                'transaction_id' => $transaction->id,
            ]);

            return $transaction;
        } else {
            // Legacy: product-level count without location (should not happen going forward)
            // Still supported for backward compatibility
            $product = $this->product;

            // Create inventory adjustment transaction (in eaches)
            $transaction = InventoryTransaction::create([
                'product_id' => $this->product_id,
                'type' => 'cycle_count',
                'quantity' => $varianceEaches,
                'quantity_before' => $systemEaches,
                'quantity_after' => $countedEaches,
                'reference_number' => $this->session->session_number,
                'reference_type' => 'cycle_count',
                'reference_id' => $this->session_id,
                'notes' => $notes . " [Legacy product-level count]",
                'user_id' => $userId,
                'transaction_date' => now(),
            ]);

            // Update product quantity (in eaches)
            $product->quantity_on_hand = $countedEaches;
            $product->save();

            $this->update([
                'variance_status' => 'approved',
                'adjustment_created' => true,
                'transaction_id' => $transaction->id,
            ]);

            return $transaction;
        }
    }
}
