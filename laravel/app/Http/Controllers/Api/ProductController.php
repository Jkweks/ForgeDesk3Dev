<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->with(['inventoryLocations.storageLocation', 'supplier', 'categories']);

        if ($request->filled('search')) {
            // Loose, "regex-style" matching: split the query into whitespace
            // tokens; every token must appear (case-insensitively) in one of the
            // searchable columns, in any order. Each token is also matched with
            // separators stripped, so "ab-123 blk" finds SKU "AB123BLK".
            $terms = preg_split('/\s+/', trim($request->search), -1, PREG_SPLIT_NO_EMPTY);
            $cols = ['sku', 'part_number', 'description', 'finish'];
            $stripExpr = fn (string $col) => "REPLACE(REPLACE(REPLACE(REPLACE(LOWER($col), '-', ''), ' ', ''), '.', ''), '/', '')";

            foreach ($terms as $term) {
                $like = '%'.mb_strtolower($term).'%';
                $likeStripped = '%'.preg_replace('/[^a-z0-9]/', '', mb_strtolower($term)).'%';

                $query->where(function ($q) use ($cols, $like, $likeStripped, $stripExpr) {
                    foreach ($cols as $col) {
                        $q->orWhereRaw("LOWER($col) LIKE ?", [$like])
                            ->orWhereRaw($stripExpr($col).' LIKE ?', [$likeStripped]);
                    }
                });
            }
        }

        if ($request->has('status')) {
            $statuses = array_filter(array_map('trim', explode(',', $request->status)));
            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } else {
                $query->where('status', $statuses[0]);
            }
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_special_order')) {
            $query->where('is_special_order', $request->boolean('is_special_order'));
        }

        if ($request->has('nonsof')) {
            $query->where('nonsof', $request->boolean('nonsof'));
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('location')) {
            $query->where('location', $request->location);
        }

        $perPage = min((int) $request->get('per_page', 50), 500);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Basic info
            'sku' => 'nullable|unique:products|max:255',
            'part_number' => 'nullable|max:255',
            'finish' => 'nullable|max:50',
            'description' => 'required|max:255',
            'long_description' => 'nullable',
            'category_id' => 'nullable|exists:categories,id', // Deprecated, kept for backward compatibility
            'category_ids' => 'nullable|array', // New: array of category IDs
            'category_ids.*' => 'exists:categories,id',
            'primary_category_id' => 'nullable|exists:categories,id', // Which category is primary
            'location' => 'nullable|max:255',

            // Pricing
            'unit_cost' => 'required|numeric|min:0',

            // Quantities
            'quantity_on_hand' => 'required|integer|min:0',
            'minimum_quantity' => 'required|integer|min:0',
            'maximum_quantity' => 'nullable|integer|min:0',
            'reorder_point' => 'nullable|integer|min:0',
            'safety_stock' => 'nullable|integer|min:0',
            'on_order_qty' => 'nullable|integer|min:0',
            'average_daily_use' => 'nullable|numeric|min:0',

            // UOM and Pack
            'unit_of_measure' => 'required|max:10',
            'pack_size' => 'nullable|integer|min:1',
            'purchase_uom' => 'nullable|max:10',
            'stock_uom' => 'nullable|max:10',
            'min_order_qty' => 'nullable|integer|min:1',
            'order_multiple' => 'nullable|integer|min:1',

            // Supplier
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_sku' => 'nullable|max:255',
            'lead_time_days' => 'nullable|integer|min:0',

            // Manufacturer
            'manufacturer' => 'nullable|max:255',
            'manufacturer_part_number' => 'nullable|max:255',

            // Tool type and tooling fields
            'tool_type' => 'nullable|in:consumable_tool,asset_tool',
            'tool_life_max' => 'nullable|numeric|min:0',
            'tool_life_unit' => 'nullable|in:seconds,minutes,hours,cycles,parts,meters',
            'tool_life_warning_threshold' => 'nullable|integer|min:0|max:100',
            'compatible_machine_types' => 'nullable|array',
            'compatible_machine_types.*' => 'integer',
            'tool_specifications' => 'nullable|array',

            // Status
            'is_active' => 'nullable|boolean',
            'is_special_order' => 'nullable|boolean',
        ]);

        // Auto-generate SKU if part_number is provided but not SKU
        if (! empty($validated['part_number']) && empty($validated['sku'])) {
            $validated['sku'] = Product::generateSku(
                $validated['part_number'],
                $validated['finish'] ?? null
            );
        }

        // Auto-calculate reorder point if not provided
        if (empty($validated['reorder_point']) && ! empty($validated['average_daily_use']) && ! empty($validated['lead_time_days'])) {
            $validated['reorder_point'] = round(
                ($validated['average_daily_use'] * $validated['lead_time_days']) +
                ($validated['safety_stock'] ?? 0)
            );
        }

        $product = Product::create($validated);
        $product->updateStatus();

        // Handle multiple categories
        if ($request->has('category_ids') && is_array($request->category_ids)) {
            $primaryCategoryId = $request->primary_category_id ?? $request->category_ids[0] ?? null;

            $syncData = [];
            foreach ($request->category_ids as $categoryId) {
                $syncData[$categoryId] = ['is_primary' => ($categoryId == $primaryCategoryId)];
            }

            $product->categories()->sync($syncData);
        } elseif ($request->has('category_id') && $request->category_id) {
            // Backward compatibility: if single category_id provided, use it as primary
            $product->categories()->sync([$request->category_id => ['is_primary' => true]]);
        }

        // Reload product with categories
        $product->load('categories');

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        // Load relationships carefully to avoid errors
        $product->load([
            'categories',
            'supplier',
            'inventoryLocations.storageLocation',
        ]);

        // Try to load optional relationships that may not exist in all databases
        try {
            $product->load([
                'jobReservations',
                'inventoryTransactions',
            ]);
        } catch (\Exception $e) {
            // Silently continue if these relationships don't exist
        }

        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            // Basic info
            'sku' => ['nullable', 'max:255', Rule::unique('products')->ignore($product->id)],
            'part_number' => 'nullable|max:255',
            'finish' => 'nullable|max:50',
            'description' => 'required|max:255',
            'long_description' => 'nullable',
            'category_id' => 'nullable|exists:categories,id', // Deprecated, kept for backward compatibility
            'category_ids' => 'nullable|array', // New: array of category IDs
            'category_ids.*' => 'exists:categories,id',
            'primary_category_id' => 'nullable|exists:categories,id', // Which category is primary
            'location' => 'nullable|max:255',

            // Pricing
            'unit_cost' => 'required|numeric|min:0',

            // Quantities
            'minimum_quantity' => 'required|integer|min:0',
            'maximum_quantity' => 'nullable|integer|min:0',
            'reorder_point' => 'nullable|integer|min:0',
            'safety_stock' => 'nullable|integer|min:0',
            'on_order_qty' => 'nullable|integer|min:0',
            'average_daily_use' => 'nullable|numeric|min:0',

            // UOM and Pack
            'unit_of_measure' => 'required|max:10',
            'pack_size' => 'nullable|integer|min:1',
            'purchase_uom' => 'nullable|max:10',
            'stock_uom' => 'nullable|max:10',
            'min_order_qty' => 'nullable|integer|min:1',
            'order_multiple' => 'nullable|integer|min:1',

            // Supplier
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_sku' => 'nullable|max:255',
            'lead_time_days' => 'nullable|integer|min:0',

            // Manufacturer
            'manufacturer' => 'nullable|max:255',
            'manufacturer_part_number' => 'nullable|max:255',

            // Tool type and tooling fields
            'tool_type' => 'nullable|in:consumable_tool,asset_tool',
            'tool_life_max' => 'nullable|numeric|min:0',
            'tool_life_unit' => 'nullable|in:seconds,minutes,hours,cycles,parts,meters',
            'tool_life_warning_threshold' => 'nullable|integer|min:0|max:100',
            'compatible_machine_types' => 'nullable|array',
            'compatible_machine_types.*' => 'integer',
            'tool_specifications' => 'nullable|array',

            // Status
            'is_active' => 'nullable|boolean',
            'nonsof' => 'nullable|boolean',
            'cp_part' => 'nullable|boolean',
            'is_shared' => 'nullable|boolean',
            'is_special_order' => 'nullable|boolean',
        ]);

        // Auto-generate SKU if part_number changed
        if (isset($validated['part_number']) && empty($validated['sku'])) {
            $validated['sku'] = Product::generateSku(
                $validated['part_number'],
                $validated['finish'] ?? $product->finish
            );
        }

        // Auto-calculate reorder point if relevant fields changed
        if (empty($validated['reorder_point']) &&
            (isset($validated['average_daily_use']) || isset($validated['lead_time_days']) || isset($validated['safety_stock']))) {
            $avgDailyUse = $validated['average_daily_use'] ?? $product->average_daily_use;
            $leadTime = $validated['lead_time_days'] ?? $product->lead_time_days;
            $safetyStock = $validated['safety_stock'] ?? $product->safety_stock ?? 0;

            if ($avgDailyUse && $leadTime) {
                $validated['reorder_point'] = round(($avgDailyUse * $leadTime) + $safetyStock);
            }
        }

        $product->update($validated);
        $product->updateStatus();

        // Handle multiple categories
        if ($request->has('category_ids')) {
            if (is_array($request->category_ids) && ! empty($request->category_ids)) {
                $primaryCategoryId = $request->primary_category_id ?? $request->category_ids[0] ?? null;

                $syncData = [];
                foreach ($request->category_ids as $categoryId) {
                    $syncData[$categoryId] = ['is_primary' => ($categoryId == $primaryCategoryId)];
                }

                $product->categories()->sync($syncData);
            } else {
                // If empty array provided, remove all categories
                $product->categories()->sync([]);
            }
        } elseif ($request->has('category_id')) {
            // Backward compatibility: if single category_id provided, use it as primary
            if ($request->category_id) {
                $product->categories()->sync([$request->category_id => ['is_primary' => true]]);
            } else {
                $product->categories()->sync([]);
            }
        }

        // Reload product with categories
        $product->load('categories');

        return response()->json($product);
    }

    /**
     * Update physical properties used by the configurator (stock length,
     * weight per inch). These don't vary by finish, so the values are
     * propagated to every product sharing this part_number, not just the
     * row being edited from.
     */
    public function updateConfiguratorSpecs(Request $request, Product $product)
    {
        $validated = $request->validate([
            'configurator_length' => 'nullable|numeric|min:0',
            'configurator_weight_per_inch' => 'nullable|numeric|min:0',
        ]);

        if (! $product->part_number) {
            $product->update($validated);

            return response()->json(['product' => $product->fresh(), 'linked_products' => []]);
        }

        Product::where('part_number', $product->part_number)->update($validated);

        $linked = Product::where('part_number', $product->part_number)->get(['id', 'sku', 'part_number', 'finish', 'description']);

        return response()->json([
            'product' => $product->fresh(),
            'linked_products' => $linked,
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(null, 204);
    }

    public function adjustInventory(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:receipt,shipment,adjustment,transfer,return',
            'reference_number' => 'nullable|max:255',
            'notes' => 'nullable',
        ]);

        $product->adjustQuantity(
            $validated['quantity'],
            $validated['type'],
            $validated['reference_number'] ?? null,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Inventory adjusted successfully',
            'product' => $product->fresh(),
        ]);
    }

    public function issueToJob(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'job_name' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Check if there's enough available quantity
        if ($product->quantity_available < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient available quantity',
                'errors' => ['quantity' => ['Not enough available inventory']],
            ], 422);
        }

        // Record quantity before adjustment
        $quantityBefore = $product->quantity_on_hand;

        // Deduct from storage locations (primary first, then secondary by available qty)
        $remainingToRemove = $validated['quantity'];

        $locations = $product->inventoryLocations()
            ->orderByRaw('is_primary DESC')
            ->orderByRaw('(quantity - quantity_committed) DESC')
            ->get();

        foreach ($locations as $location) {
            if ($remainingToRemove <= 0) {
                break;
            }
            $available = $location->quantity - $location->quantity_committed;
            $deduct = min($remainingToRemove, $available);
            if ($deduct > 0) {
                $location->quantity -= $deduct;
                $location->save();
                $remainingToRemove -= $deduct;
            }
        }

        // Force-deduct any remainder from locations that still have stock
        if ($remainingToRemove > 0) {
            foreach ($locations as $location) {
                if ($remainingToRemove <= 0) {
                    break;
                }
                if ($location->quantity > 0) {
                    $deduct = min($remainingToRemove, $location->quantity);
                    $location->quantity -= $deduct;
                    $location->save();
                    $remainingToRemove -= $deduct;
                }
            }
        }

        // Recalculate product totals from locations (source of truth)
        $product->recalculateQuantitiesFromLocations();
        $product->refresh();

        $quantityAfter = $product->quantity_on_hand;

        // Create inventory transaction
        InventoryTransaction::create([
            'product_id' => $product->id,
            'type' => 'job_issue',
            'quantity' => -$validated['quantity'], // Negative because it's being removed
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reference_number' => $validated['job_name'],
            'reference_type' => 'job',
            'reference_id' => null,
            'notes' => "Issued to job: {$validated['job_name']}".
                       ($validated['notes'] ? "\n".$validated['notes'] : ''),
            'user_id' => auth()->id(),
            'transaction_date' => now(),
        ]);

        // Update product status
        $product->updateStatus();

        return response()->json([
            'message' => 'Material issued to job successfully',
            'product' => $product->fresh(),
        ]);
    }

    public function getTransactions(Product $product)
    {
        $transactions = $product->inventoryTransactions()
            ->with('user')
            ->latest('transaction_date')
            ->paginate(50);

        return response()->json($transactions);
    }

    /**
     * Get finish codes configuration
     */
    public function getFinishCodes()
    {
        $finishCodes = [];
        foreach (Product::$finishCodes as $code => $name) {
            $finishCodes[] = ['code' => $code, 'name' => $name];
        }

        return response()->json($finishCodes);
    }

    /**
     * Get UOM configuration
     */
    public function getUnitOfMeasures()
    {
        $uoms = [];
        foreach (Product::$unitOfMeasures as $code => $name) {
            $uoms[] = ['code' => $code, 'name' => $name];
        }

        return response()->json($uoms);
    }

    /**
     * Calculate reorder point for a product
     */
    public function calculateReorderPoint(Product $product)
    {
        $calculatedReorderPoint = $product->calculateReorderPoint();

        return response()->json([
            'calculated_reorder_point' => $calculatedReorderPoint,
            'current_reorder_point' => $product->reorder_point,
            'average_daily_use' => $product->average_daily_use,
            'lead_time_days' => $product->lead_time_days,
            'safety_stock' => $product->safety_stock,
        ]);
    }

    public function uploadPhoto(Request $request, Product $product)
    {
        $request->validate([
            'photo' => 'required|image|max:10240', // max 10MB
        ]);

        // Delete old photo if exists
        if ($product->photo_path) {
            Storage::disk('public')->delete($product->photo_path);
        }

        $path = $request->file('photo')->store("product-photos/{$product->id}", 'public');

        $product->update(['photo_path' => $path]);

        return response()->json([
            'photo_path' => $product->photo_path,
            'photo_url' => $product->photo_url,
        ]);
    }

    public function deletePhoto(Product $product)
    {
        if ($product->photo_path) {
            Storage::disk('public')->delete($product->photo_path);
            $product->update(['photo_path' => null]);
        }

        return response()->json(['message' => 'Photo deleted']);
    }

    public function refreshAllStatuses()
    {
        $count = 0;
        Product::withoutTrashed()->chunkById(100, function ($products) use (&$count) {
            foreach ($products as $product) {
                $product->recalculateQuantitiesFromLocations();
                $product->recalculateOnOrderFromPurchaseOrders();
                $count++;
            }
        });

        return response()->json([
            'refreshed' => $count,
            'message' => "Status recalculated for {$count} products.",
        ]);
    }
}
