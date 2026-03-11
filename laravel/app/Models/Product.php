<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku', 'part_number', 'finish', 'description', 'long_description',
        'category_id',
        'unit_cost', 'unit_price', 'net_cost', 'pricing_category',
        'finish_multiplier', 'category_multiplier', 'price_per_length', 'price_per_package',
        'quantity_on_hand', 'quantity_committed',
        'minimum_quantity', 'reorder_point', 'safety_stock', 'average_daily_use',
        'on_order_qty', 'maximum_quantity', 'unit_of_measure',
        'pack_size', 'purchase_uom', 'stock_uom', 'min_order_qty', 'order_multiple',
        'supplier_id', 'supplier_sku', 'supplier_contact', 'lead_time_days',
        'manufacturer', 'manufacturer_part_number',
        'is_active', 'is_discontinued', 'status', 'product_type',
        'configurator_available', 'configurator_type', 'configurator_use_path',
        'dimension_height', 'dimension_depth',
        'tool_type', 'tool_life_max', 'tool_life_unit', 'tool_life_warning_threshold',
        'compatible_machine_types', 'tool_specifications',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'net_cost' => 'decimal:2',
        'finish_multiplier' => 'decimal:4',
        'category_multiplier' => 'decimal:4',
        'price_per_length' => 'decimal:2',
        'price_per_package' => 'decimal:2',
        'average_daily_use' => 'decimal:2',
        'dimension_height' => 'decimal:2',
        'dimension_depth' => 'decimal:2',
        'is_active' => 'boolean',
        'is_discontinued' => 'boolean',
        'configurator_available' => 'boolean',
        'tool_life_max' => 'decimal:2',
        'compatible_machine_types' => 'array',
        'tool_specifications' => 'array',
    ];

    protected $appends = [
        'quantity_available',
        'suggested_order_qty',
        'days_until_stockout',
        'quantity_on_hand_packs',
        'quantity_available_packs',
        'counting_unit',
        'pack_cost',
        'pack_price',
    ];

    // Finish codes configuration
    public static $finishCodes = [
        'C2' => 'Clear Anodized',
        'DB' => 'Dark Bronze Anodized',
        'BL' => 'Black Anodized',
        '0R' => 'Mill/Unfinished'
        ];

    // UOM configuration
    public static $unitOfMeasures = [
        'EA' => 'Each',
        'CASE' => 'Case',
        'GAL' => 'Gallon',
        'FT' => 'Foot',
        'ROLL' => 'Roll',
        'PKG' => 'Package',
    ];

    // Tool type configuration
    public static $toolTypes = [
        'consumable_tool' => 'Machine Tooling',
        'asset_tool' => 'Maintenance Asset',
    ];

    // Tool life unit configuration
    public static $toolLifeUnits = [
        'seconds' => 'Seconds',
        'minutes' => 'Minutes',
        'hours' => 'Hours',
        'cycles' => 'Cycles',
        'parts' => 'Parts',
        'meters' => 'Meters',
    ];

    // Product type configuration
    public static $productTypes = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'special_order' => 'Special Order',
        'obsolete' => 'Obsolete',
    ];

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function committedInventory()
    {
        return $this->hasMany(CommittedInventory::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @deprecated Use categories() instead. Single category relationship kept for backward compatibility.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Many-to-many relationship with categories
     * Products can belong to multiple categories/systems
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get the primary category for this product
     */
    public function primaryCategory()
    {
        return $this->categories()->wherePivot('is_primary', true)->first();
    }

    /**
     * Get all category names as a comma-separated string
     */
    public function getCategoryNamesAttribute()
    {
        return $this->categories->pluck('name')->join(', ');
    }

    public function inventoryLocations()
    {
        return $this->hasMany(InventoryLocation::class);
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Calculate total quantity on hand from all inventory locations
     * This is the source of truth for inventory quantities
     */
    public function calculateQuantityOnHandFromLocations()
    {
        return $this->inventoryLocations()->sum('quantity');
    }

    /**
     * Calculate total committed quantity from all inventory locations
     */
    public function calculateQuantityCommittedFromLocations()
    {
        return $this->inventoryLocations()->sum('quantity_committed');
    }

    /**
     * Recalculate and update quantity_on_hand and quantity_committed from inventory locations
     * Call this after updating inventory_locations to sync the product totals
     * This is the source of truth for product quantities
     */
    public function recalculateQuantitiesFromLocations()
    {
        // Use single query for efficiency
        $totals = $this->inventoryLocations()
            ->selectRaw('
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(quantity_committed), 0) as total_committed
            ')
            ->first();

        $this->quantity_on_hand = $totals->total_quantity ?? 0;
        $this->quantity_committed = $totals->total_committed ?? 0;
        $this->save();

        $this->updateStatus();

        return $this;
    }

    /**
     * Get reservation items for this product (through job_reservation_items table)
     */
    public function reservationItems()
    {
        return $this->hasMany(JobReservationItem::class);
    }

    /**
     * Get active reservation items (where reservation status is active, in_progress, or on_hold)
     */
    public function activeReservationItems()
    {
        return $this->reservationItems()
            ->whereHas('reservation', function($query) {
                $query->whereIn('status', ['active', 'in_progress', 'on_hold']);
            });
    }

    /**
     * Get job reservations through reservation items
     * @deprecated Use reservationItems() for the new fulfillment schema
     */
    public function jobReservations()
    {
        return $this->hasManyThrough(
            JobReservation::class,
            JobReservationItem::class,
            'product_id',       // Foreign key on job_reservation_items
            'id',               // Foreign key on job_reservations
            'id',               // Local key on products
            'reservation_id'    // Local key on job_reservation_items
        );
    }

    /**
     * Calculate committed quantity from active reservations (real-time)
     */
    public function getCommittedFromReservationsAttribute()
    {
        return $this->activeReservationItems()->sum('committed_qty');
    }

    public function requiredParts()
    {
        return $this->hasMany(RequiredPart::class, 'parent_product_id');
    }

    public function usedInProducts()
    {
        return $this->hasMany(RequiredPart::class, 'required_product_id');
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function cycleCountItems()
    {
        return $this->hasMany(CycleCountItem::class);
    }

    public function machineTooling()
    {
        return $this->hasMany(MachineTooling::class);
    }

    public function activeTooling()
    {
        return $this->machineTooling()->whereIn('status', ['active', 'warning', 'needs_replacement']);
    }

    public function getQuantityAvailableAttribute()
    {
        return $this->quantity_on_hand - $this->committed_from_reservations;
    }

    public function updateStatus()
    {
        $available = $this->quantity_available;

        // Critical: Over-committed (negative available quantity)
        if ($available < 0) {
            $this->status = 'critical';
        }
        // Low Stock: Between reorder point and 0 (or below reorder point if set)
        elseif ($this->reorder_point && $available < $this->reorder_point) {
            $this->status = 'low_stock';
        }
        // In Stock: Above reorder point, or positive quantity if no reorder point set
        else {
            $this->status = 'in_stock';
        }

        $this->save();
    }

    public function adjustQuantity($quantity, $type, $reference = null, $notes = null)
    {
        $quantityBefore = $this->quantity_on_hand;
        $this->quantity_on_hand += $quantity;
        $this->save();

        InventoryTransaction::create([
            'product_id' => $this->id,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $this->quantity_on_hand,
            'reference_number' => $reference,
            'notes' => $notes,
            'user_id' => auth()->id(),
            'transaction_date' => now(),
        ]);

        $this->updateStatus();
    }

    /**
     * Generate SKU from part number and finish
     */
    public static function generateSku($partNumber, $finish = null)
    {
        if ($finish) {
            return strtoupper($partNumber . '-' . $finish);
        }
        return strtoupper($partNumber);
    }

    /**
     * Auto-generate and set SKU if part_number is set
     */
    public function setSkuFromPartNumber()
    {
        if ($this->part_number) {
            $this->sku = self::generateSku($this->part_number, $this->finish);
        }
    }

    /**
     * Calculate suggested order quantity based on reorder point logic
     */
    public function getSuggestedOrderQtyAttribute()
    {
        // If below reorder point, suggest ordering up to maximum quantity (or 2x reorder point if no max)
        if ($this->reorder_point && $this->quantity_available <= $this->reorder_point) {
            $targetQty = $this->maximum_quantity ?: ($this->reorder_point * 2);
            $suggestedQty = $targetQty - $this->quantity_on_hand - $this->on_order_qty;

            // Round up to order multiple if specified
            if ($this->order_multiple && $this->order_multiple > 1) {
                $suggestedQty = ceil($suggestedQty / $this->order_multiple) * $this->order_multiple;
            }

            // Ensure at least minimum order quantity
            if ($this->min_order_qty && $suggestedQty < $this->min_order_qty) {
                $suggestedQty = $this->min_order_qty;
            }

            return max(0, $suggestedQty);
        }

        return 0;
    }

    /**
     * Calculate days until stockout based on average daily use
     */
    public function getDaysUntilStockoutAttribute()
    {
        if ($this->average_daily_use && $this->average_daily_use > 0) {
            return round($this->quantity_available / $this->average_daily_use, 1);
        }
        return null;
    }

    /**
     * Calculate reorder point using safety stock and lead time
     * Reorder Point = (Average Daily Use × Lead Time) + Safety Stock
     */
    public function calculateReorderPoint()
    {
        if ($this->average_daily_use && $this->lead_time_days) {
            $calculatedReorderPoint = ($this->average_daily_use * $this->lead_time_days);

            if ($this->safety_stock) {
                $calculatedReorderPoint += $this->safety_stock;
            }

            return round($calculatedReorderPoint);
        }

        return $this->safety_stock ?: 0;
    }

    /**
     * Auto-update reorder point
     */
    public function updateReorderPoint()
    {
        $this->reorder_point = $this->calculateReorderPoint();
        $this->save();
    }

    /**
     * Convert quantity between UOMs (Purchase UOM to Stock UOM)
     */
    public function convertPurchaseToStock($purchaseQty)
    {
        if ($this->pack_size && $this->pack_size > 0) {
            return $purchaseQty * $this->pack_size;
        }
        return $purchaseQty;
    }

    /**
     * Convert quantity between UOMs (Stock UOM to Purchase UOM)
     */
    public function convertStockToPurchase($stockQty)
    {
        if ($this->pack_size && $this->pack_size > 0) {
            return $stockQty / $this->pack_size;
        }
        return $stockQty;
    }

    /**
     * Check if product is tracked in packs (has a pack_size > 1)
     */
    public function hasPackSize()
    {
        return $this->pack_size && $this->pack_size > 1;
    }

    /**
     * Get the effective pack size (1 if no pack defined)
     */
    public function getEffectivePackSize()
    {
        return $this->hasPackSize() ? $this->pack_size : 1;
    }

    /**
     * Calculate the number of full packs for a given quantity in eaches
     * This is used for on-hand inventory (floor - only count complete packs)
     */
    public function eachesToFullPacks($eachesQty)
    {
        if (!$this->hasPackSize()) {
            return $eachesQty;
        }
        return (int) floor($eachesQty / $this->pack_size);
    }

    /**
     * Calculate the number of packs needed to fulfill a quantity in eaches
     * This is used for committed quantities (ceil - need full packs to cover the requirement)
     */
    public function eachesToPacksNeeded($eachesQty)
    {
        if (!$this->hasPackSize()) {
            return $eachesQty;
        }
        return (int) ceil($eachesQty / $this->pack_size);
    }

    /**
     * Convert packs to eaches
     */
    public function packsToEaches($packsQty)
    {
        if (!$this->hasPackSize()) {
            return $packsQty;
        }
        return $packsQty * $this->pack_size;
    }

    /**
     * Get quantity on hand in packs (full packs only)
     */
    public function getQuantityOnHandPacksAttribute()
    {
        return $this->eachesToFullPacks($this->quantity_on_hand);
    }

    /**
     * Get committed quantity in packs (packs needed to fulfill commitments)
     * Note: This uses the quantity_committed field. For real-time calculations,
     * use getCommittedPacksFromReservations()
     */
    public function getQuantityCommittedPacksAttribute()
    {
        return $this->eachesToPacksNeeded($this->quantity_committed);
    }

    /**
     * Get committed packs from active reservations (real-time calculation)
     */
    public function getCommittedPacksFromReservationsAttribute()
    {
        $totalEaches = $this->activeReservationItems()->sum('committed_qty');
        return $this->eachesToPacksNeeded($totalEaches);
    }

    /**
     * Get available quantity in packs (on-hand packs minus committed packs)
     */
    public function getQuantityAvailablePacksAttribute()
    {
        $onHandPacks = $this->quantity_on_hand_packs;
        $committedPacks = $this->committed_packs_from_reservations;
        return $onHandPacks - $committedPacks;
    }

    /**
     * Get the counting unit label for this product
     * Returns 'packs' if has pack_size, otherwise the stock UOM or 'eaches'
     */
    public function getCountingUnitAttribute()
    {
        if ($this->hasPackSize()) {
            return $this->purchase_uom ?: 'packs';
        }
        return $this->stock_uom ?: 'EA';
    }

    /**
     * Get cost per pack
     * Note: unit_cost already represents pack cost for pack products
     */
    public function getPackCostAttribute()
    {
        return $this->unit_cost;
    }

    /**
     * Get price per pack
     * Note: unit_price already represents pack price for pack products
     */
    public function getPackPriceAttribute()
    {
        return $this->unit_price;
    }

    /**
     * Check if product needs reordering
     */
    public function needsReorder()
    {
        if (!$this->reorder_point) {
            return false;
        }

        $availableWithOnOrder = $this->quantity_available + $this->on_order_qty;
        return $availableWithOnOrder <= $this->reorder_point;
    }

    /**
     * Get finish name from code
     */
    public function getFinishNameAttribute()
    {
        if ($this->finish && isset(self::$finishCodes[$this->finish])) {
            return self::$finishCodes[$this->finish];
        }
        return $this->finish;
    }

    /**
     * Get UOM name
     */
    public function getUomNameAttribute()
    {
        if ($this->unit_of_measure && isset(self::$unitOfMeasures[$this->unit_of_measure])) {
            return self::$unitOfMeasures[$this->unit_of_measure];
        }
        return $this->unit_of_measure;
    }

    /**
     * Get purchase UOM name
     */
    public function getPurchaseUomNameAttribute()
    {
        if ($this->purchase_uom && isset(self::$unitOfMeasures[$this->purchase_uom])) {
            return self::$unitOfMeasures[$this->purchase_uom];
        }
        return $this->purchase_uom;
    }

    /**
     * Get stock UOM name
     */
    public function getStockUomNameAttribute()
    {
        if ($this->stock_uom && isset(self::$unitOfMeasures[$this->stock_uom])) {
            return self::$unitOfMeasures[$this->stock_uom];
        }
        return $this->stock_uom;
    }

    /**
     * Check if product is a tool (consumable or asset)
     */
    public function isTool()
    {
        return in_array($this->tool_type, ['consumable_tool', 'asset_tool']);
    }

    /**
     * Check if product is a consumable tool (tracks tool life)
     */
    public function isConsumableTool()
    {
        return $this->tool_type === 'consumable_tool';
    }

    /**
     * Check if product is an asset tool (no tool life tracking)
     */
    public function isAssetTool()
    {
        return $this->tool_type === 'asset_tool';
    }

    /**
     * Get tool type name
     */
    public function getToolTypeNameAttribute()
    {
        if ($this->tool_type && isset(self::$toolTypes[$this->tool_type])) {
            return self::$toolTypes[$this->tool_type];
        }
        return null;
    }

    /**
     * Get tool life unit name
     */
    public function getToolLifeUnitNameAttribute()
    {
        if ($this->tool_life_unit && isset(self::$toolLifeUnits[$this->tool_life_unit])) {
            return self::$toolLifeUnits[$this->tool_life_unit];
        }
        return $this->tool_life_unit;
    }

    /**
     * Check if tool is compatible with a machine type
     */
    public function isCompatibleWithMachine($machineTypeId)
    {
        if (!$this->isTool() || !$this->compatible_machine_types) {
            return false;
        }
        return in_array($machineTypeId, $this->compatible_machine_types);
    }

    /**
     * Get formatted tool specifications for display
     */
    public function getFormattedSpecificationsAttribute()
    {
        if (!$this->tool_specifications) {
            return null;
        }

        $specs = [];
        foreach ($this->tool_specifications as $key => $value) {
            $specs[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
        }

        return implode(' | ', $specs);
    }

    /**
     * Check if product is obsolete
     */
    public function isObsolete()
    {
        return $this->product_type === 'obsolete';
    }

    /**
     * Check if product should be hidden from display
     * Obsolete products with 0 on_hand and 0 available should be hidden
     */
    public function shouldBeHidden()
    {
        return $this->isObsolete()
            && $this->quantity_on_hand == 0
            && $this->quantity_available == 0;
    }

    /**
     * Get product type name
     */
    public function getProductTypeNameAttribute()
    {
        if ($this->product_type && isset(self::$productTypes[$this->product_type])) {
            return self::$productTypes[$this->product_type];
        }
        return $this->product_type;
    }

    /**
     * Scope to exclude hidden products (obsolete with 0 quantity)
     */
    public function scopeVisible($query)
    {
        return $query->where(function($q) {
            $q->where('product_type', '!=', 'obsolete')
              ->orWhere(function($subQ) {
                  $subQ->where('product_type', 'obsolete')
                       ->where(function($zeroCheck) {
                           $zeroCheck->where('quantity_on_hand', '!=', 0)
                                     ->orWhereRaw('(quantity_on_hand - COALESCE((SELECT SUM(committed_qty) FROM job_reservation_items WHERE job_reservation_items.product_id = products.id AND EXISTS (SELECT 1 FROM job_reservations WHERE job_reservations.id = job_reservation_items.reservation_id AND job_reservations.status IN (?, ?, ?))), 0)) != 0', ['active', 'in_progress', 'on_hold']);
                       });
              });
        });
    }
}