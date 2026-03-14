<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryLocationController extends Controller
{
    /**
     * Get all locations for a specific product
     */
    public function index(Product $product)
    {
        try {
            $locations = $product->inventoryLocations()
                ->with('storageLocation')
                ->orderBy('is_primary', 'desc')
                ->get();

            // Ensure all locations have valid data
            $locations = $locations->map(function($location) {
                // Ensure numeric values are not null
                $location->quantity = $location->quantity ?? 0;
                $location->quantity_committed = $location->quantity_committed ?? 0;

                return $location;
            });

            return response()->json($locations);
        } catch (\Exception $e) {
            \Log::error('Error loading product locations', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty array to prevent frontend crashes
            // Frontend will show error notification but continue functioning
            return response()->json([]);
        }
    }

    /**
     * Add a new location to a product
     */
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'storage_location_id' => 'required|exists:storage_locations,id',
            'quantity' => 'required|integer|min:0',
            'quantity_committed' => 'nullable|integer|min:0',
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        // Check if location already exists for this product
        $exists = $product->inventoryLocations()
            ->where('storage_location_id', $validated['storage_location_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This storage location already exists for this product',
                'errors' => ['storage_location_id' => ['Storage location already exists']]
            ], 422);
        }

        // If this is marked as primary, unset other primary locations
        if ($validated['is_primary'] ?? false) {
            $product->inventoryLocations()->update(['is_primary' => false]);
        }

        $location = $product->inventoryLocations()->create([
            'storage_location_id' => $validated['storage_location_id'],
            'quantity' => $validated['quantity'],
            'quantity_committed' => $validated['quantity_committed'] ?? 0,
            'is_primary' => $validated['is_primary'] ?? false,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Recalculate product total quantities
        $this->updateProductTotals($product);

        // Load storage location for response
        $location->load('storageLocation');

        return response()->json($location, 201);
    }

    /**
     * Update a specific location
     */
    public function update(Request $request, Product $product, InventoryLocation $location)
    {
        // Verify the location belongs to this product
        if ($location->product_id !== $product->id) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        $validated = $request->validate([
            'storage_location_id' => 'required|exists:storage_locations,id',
            'quantity' => 'required|integer|min:0',
            'quantity_committed' => 'nullable|integer|min:0',
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        // Check if new storage location conflicts with another location
        if ($validated['storage_location_id'] !== $location->storage_location_id) {
            $exists = $product->inventoryLocations()
                ->where('storage_location_id', $validated['storage_location_id'])
                ->where('id', '!=', $location->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'This storage location already exists for this product',
                    'errors' => ['storage_location_id' => ['Storage location already exists']]
                ], 422);
            }
        }

        // If this is marked as primary, unset other primary locations
        if ($validated['is_primary'] ?? false) {
            $product->inventoryLocations()
                ->where('id', '!=', $location->id)
                ->update(['is_primary' => false]);
        }

        $location->update($validated);

        // Recalculate product total quantities
        $this->updateProductTotals($product);

        // Load storage location for response
        $location->load('storageLocation');

        return response()->json($location);
    }

    /**
     * Delete a location
     */
    public function destroy(Product $product, InventoryLocation $location)
    {
        // Verify the location belongs to this product
        if ($location->product_id !== $product->id) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        // Prevent deletion if there's quantity at this location
        if ($location->quantity > 0) {
            return response()->json([
                'message' => 'Cannot delete location with inventory. Please transfer or adjust quantity to zero first.',
                'errors' => ['quantity' => ['Location has inventory']]
            ], 422);
        }

        $location->delete();

        // Recalculate product total quantities
        $this->updateProductTotals($product);

        return response()->json(['message' => 'Location deleted successfully'], 200);
    }

    /**
     * Transfer inventory between locations
     */
    public function transfer(Request $request, Product $product)
    {
        $validated = $request->validate([
            'from_location_id' => 'required|exists:inventory_locations,id',
            'to_location_id' => 'required|exists:inventory_locations,id|different:from_location_id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $fromLocation = InventoryLocation::findOrFail($validated['from_location_id']);
        $toLocation = InventoryLocation::findOrFail($validated['to_location_id']);

        // Verify both locations belong to this product
        if ($fromLocation->product_id !== $product->id || $toLocation->product_id !== $product->id) {
            return response()->json(['message' => 'Invalid locations'], 404);
        }

        // Check if source location has enough available quantity
        if ($fromLocation->quantity_available < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient available quantity at source location',
                'errors' => ['quantity' => ['Not enough available inventory']]
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Capture product quantity before transfer
            $quantityBefore = $product->quantity_on_hand;

            // Reduce from source
            $fromLocation->quantity -= $validated['quantity'];
            $fromLocation->save();

            // Add to destination
            $toLocation->quantity += $validated['quantity'];
            $toLocation->save();

            // Product total quantity doesn't change in transfer, but we log it for audit trail
            $quantityAfter = $product->quantity_on_hand;

            // Load storage locations for transaction notes
            $fromLocation->load('storageLocation');
            $toLocation->load('storageLocation');

            $fromLocationName = $fromLocation->storageLocation->name ?? 'Unknown';
            $toLocationName = $toLocation->storageLocation->name ?? 'Unknown';

            // Create inventory transaction
            InventoryTransaction::create([
                'product_id' => $product->id,
                'type' => 'transfer',
                'quantity' => $validated['quantity'],
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reference_number' => "Transfer: {$fromLocationName} → {$toLocationName}",
                'reference_type' => 'location_transfer',
                'reference_id' => $toLocation->id,
                'notes' => "Transferred {$validated['quantity']} from {$fromLocationName} to {$toLocationName}" .
                           ($validated['notes'] ? "\n" . $validated['notes'] : ''),
                'user_id' => auth()->id(),
                'transaction_date' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Inventory transferred successfully',
                'from_location' => $fromLocation->fresh(),
                'to_location' => $toLocation->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Transfer failed'], 500);
        }
    }

    /**
     * Adjust quantity at a specific location
     */
    public function adjust(Request $request, Product $product, InventoryLocation $location)
    {
        // Verify the location belongs to this product
        if ($location->product_id !== $product->id) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:receipt,adjustment,cycle_count,return',
            'notes' => 'nullable|string',
        ]);

        $locationQuantityBefore = $location->quantity;
        $location->quantity += $validated['quantity'];

        if ($location->quantity < 0) {
            return response()->json([
                'message' => 'Adjustment would result in negative quantity',
                'errors' => ['quantity' => ['Invalid adjustment']]
            ], 422);
        }

        // Capture product quantity before adjustment
        $productQuantityBefore = $product->quantity_on_hand;

        $location->save();

        // Recalculate product total quantities
        $this->updateProductTotals($product);

        // Capture product quantity after adjustment
        $product->refresh();
        $productQuantityAfter = $product->quantity_on_hand;

        // Load storage location for transaction notes
        $location->load('storageLocation');
        $locationName = $location->storageLocation->name ?? 'Unknown';

        // Create inventory transaction
        InventoryTransaction::create([
            'product_id' => $product->id,
            'type' => $validated['type'],
            'quantity' => $validated['quantity'],
            'quantity_before' => $productQuantityBefore,
            'quantity_after' => $productQuantityAfter,
            'reference_number' => "Location: {$locationName}",
            'reference_type' => 'location_adjustment',
            'reference_id' => $location->id,
            'notes' => "Adjusted quantity at {$locationName} by {$validated['quantity']} (Location: {$locationQuantityBefore} → {$location->quantity})" .
                       ($validated['notes'] ? "\n" . $validated['notes'] : ''),
            'user_id' => auth()->id(),
            'transaction_date' => now(),
        ]);

        return response()->json([
            'message' => 'Quantity adjusted successfully',
            'location' => $location->fresh(),
            'quantity_before' => $locationQuantityBefore,
            'quantity_after' => $location->quantity,
        ]);
    }

    /**
     * Get location statistics
     */
    public function statistics(Product $product)
    {
        $locations = $product->inventoryLocations()->with('storageLocation')->get();

        $stats = [
            'total_locations' => $locations->count(),
            'total_quantity' => $locations->sum('quantity'),
            'total_committed' => $locations->sum('quantity_committed'),
            'total_available' => $locations->sum(function($loc) {
                return $loc->quantity - $loc->quantity_committed;
            }),
            'locations' => $locations->map(function($loc) {
                return [
                    'id' => $loc->id,
                    'storage_location_id' => $loc->storage_location_id,
                    'storage_location' => $loc->storageLocation,
                    'quantity' => $loc->quantity,
                    'quantity_committed' => $loc->quantity_committed,
                    'quantity_available' => $loc->quantity_available,
                    'is_primary' => $loc->is_primary,
                    'percentage' => 0, // Will be calculated below
                ];
            }),
        ];

        // Calculate percentage distribution
        if ($stats['total_quantity'] > 0) {
            $stats['locations'] = $stats['locations']->map(function($loc) use ($stats) {
                $loc['percentage'] = round(($loc['quantity'] / $stats['total_quantity']) * 100, 1);
                return $loc;
            });
        }

        return response()->json($stats);
    }

    /**
     * Get all storage locations being used in inventory
     * This returns the hierarchical storage locations that have inventory
     */
    public function getAllLocations()
    {
        $storageLocationIds = InventoryLocation::select('storage_location_id')
            ->distinct()
            ->pluck('storage_location_id');

        $locations = \App\Models\StorageLocation::whereIn('id', $storageLocationIds)
            ->orderBy('name')
            ->get();

        return response()->json($locations);
    }

    /**
     * Helper: Update product totals from locations
     */
    private function updateProductTotals(Product $product)
    {
        $totals = $product->inventoryLocations()
            ->selectRaw('
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(quantity_committed), 0) as total_committed
            ')
            ->first();

        $product->quantity_on_hand = $totals->total_quantity ?? 0;
        $product->quantity_committed = $totals->total_committed ?? 0;
        $product->save();

        $product->updateStatus();
    }
}
