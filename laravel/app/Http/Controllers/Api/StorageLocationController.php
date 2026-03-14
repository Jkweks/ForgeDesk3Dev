<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorageLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StorageLocationController extends Controller
{
    /**
     * Display a listing of storage locations
     */
    public function index(Request $request)
    {
        $query = StorageLocation::query();

        // Filter by active status
        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by parent (including root locations)
        if ($request->has('parent_id')) {
            if ($request->parent_id === 'null' || $request->parent_id === '') {
                $query->roots();
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        // Filter by depth
        if ($request->has('depth')) {
            $query->atDepth($request->depth);
        }

        // Search by name or code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Load relationships
        if ($request->has('with_children') && $request->with_children) {
            $query->with('children');
        }

        if ($request->has('with_parent') && $request->with_parent) {
            $query->with('parent');
        }

        // Order
        $query->ordered();

        // Pagination
        $perPage = $request->get('per_page', 50);
        if ($perPage === 'all') {
            $locations = $query->get();
            return response()->json($locations);
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Get hierarchical tree structure
     */
    public function tree(Request $request)
    {
        $query = StorageLocation::roots();

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        $locations = $query->with('descendants')->ordered()->get();

        return response()->json($this->buildTree($locations));
    }

    /**
     * Build nested tree structure
     */
    private function buildTree($locations)
    {
        return $locations->map(function ($location) {
            return [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'type' => $location->type,
                'depth' => $location->depth,
                'parent_id' => $location->parent_id,
                'is_active' => $location->is_active,
                'has_children' => $location->has_children,
                'full_path' => $location->full_path,
                'description' => $location->description,
                'capacity' => $location->capacity,
                'capacity_unit' => $location->capacity_unit,
                'children' => $location->children->count() > 0 ? $this->buildTree($location->children) : [],
            ];
        });
    }

    /**
     * Get locations with inventory usage statistics
     */
    public function withStats(Request $request)
    {
        $query = StorageLocation::query()->with('inventoryLocations.product');

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        $locations = $query->ordered()->get();

        // Add statistics to each location
        $locationsWithStats = $locations->map(function ($location) {
            // Get all descendant location IDs (including self)
            $descendantIds = $location->getDescendantIds(true);

            // Get inventory from this location and all descendants
            $inventoryLocs = \App\Models\InventoryLocation::with('product')
                ->whereIn('storage_location_id', $descendantIds)
                ->get();

            // Calculate statistics with pack-aware pricing
            $totalQuantityEaches = 0;
            $totalCommittedEaches = 0;
            $totalValue = 0;

            foreach ($inventoryLocs as $il) {
                $product = $il->product;
                if (!$product) continue;

                $qtyEaches = $il->quantity ?? 0;
                $committedEaches = $il->quantity_committed ?? 0;

                $totalQuantityEaches += $qtyEaches;
                $totalCommittedEaches += $committedEaches;

                // Calculate value: convert eaches to packs for pack products
                if ($product->pack_size && $product->pack_size > 1) {
                    $qtyPacks = $qtyEaches / $product->pack_size;
                    $totalValue += $qtyPacks * ($product->unit_cost ?? 0);
                } else {
                    $totalValue += $qtyEaches * ($product->unit_cost ?? 0);
                }
            }

            return [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'type' => $location->type,
                'description' => $location->description,
                'aisle' => $location->aisle,
                'bay' => $location->bay,
                'level' => $location->level,
                'position' => $location->position,
                'full_address' => $location->full_address,
                'capacity' => $location->capacity,
                'capacity_unit' => $location->capacity_unit,
                'is_active' => $location->is_active,
                'sort_order' => $location->sort_order,
                'notes' => $location->notes,
                'created_at' => $location->created_at,
                'updated_at' => $location->updated_at,
                'stats' => [
                    'products_count' => $inventoryLocs->count(),
                    'total_quantity_eaches' => $totalQuantityEaches,
                    'total_committed_eaches' => $totalCommittedEaches,
                    'total_available_eaches' => $totalQuantityEaches - $totalCommittedEaches,
                    'total_value' => round($totalValue, 2),
                ],
            ];
        });

        return response()->json($locationsWithStats);
    }

    /**
     * Store a newly created storage location
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:storage_locations,name',
            'code' => 'nullable|string|max:50|unique:storage_locations,code',
            'type' => 'required|in:aisle,rack,shelf,bin,warehouse,zone,other',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:storage_locations,id',
            'aisle' => 'nullable|string|max:50',
            'bay' => 'nullable|string|max:50',
            'level' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:50',
            'capacity' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Auto-generate code if not provided
        if (empty($request->code)) {
            $request->merge(['code' => $this->generateLocationCode($request->name)]);
        }

        $location = StorageLocation::create($validator->validated());

        return response()->json($location, 201);
    }

    /**
     * Display the specified storage location
     */
    public function show(StorageLocation $storageLocation)
    {
        // Get all descendant location IDs (including self)
        $descendantIds = $storageLocation->getDescendantIds(true);

        // Get inventory from this location and all descendants
        $inventoryLocs = \App\Models\InventoryLocation::with('product')
            ->whereIn('storage_location_id', $descendantIds)
            ->get();

        // Calculate statistics with pack-aware pricing
        $totalQuantityEaches = 0;
        $totalCommittedEaches = 0;
        $totalValue = 0;

        foreach ($inventoryLocs as $il) {
            $product = $il->product;
            if (!$product) continue;

            $qtyEaches = $il->quantity ?? 0;
            $committedEaches = $il->quantity_committed ?? 0;

            $totalQuantityEaches += $qtyEaches;
            $totalCommittedEaches += $committedEaches;

            // Calculate value: convert eaches to packs for pack products
            if ($product->pack_size && $product->pack_size > 1) {
                $qtyPacks = $qtyEaches / $product->pack_size;
                $totalValue += $qtyPacks * ($product->unit_cost ?? 0);
            } else {
                $totalValue += $qtyEaches * ($product->unit_cost ?? 0);
            }
        }

        $stats = [
            'products_count' => $inventoryLocs->count(),
            'total_quantity_eaches' => $totalQuantityEaches,
            'total_committed_eaches' => $totalCommittedEaches,
            'total_available_eaches' => $totalQuantityEaches - $totalCommittedEaches,
            'total_value' => round($totalValue, 2),
        ];

        // Load direct inventory locations (not aggregated) for detail view
        $storageLocation->load('inventoryLocations.product');

        return response()->json([
            'location' => $storageLocation,
            'stats' => $stats,
            'inventory_locations' => $storageLocation->inventoryLocations,
            'aggregated_stats_include_children' => true,
        ]);
    }

    /**
     * Update the specified storage location
     */
    public function update(Request $request, StorageLocation $storageLocation)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:storage_locations,name,' . $storageLocation->id,
            'code' => 'nullable|string|max:50|unique:storage_locations,code,' . $storageLocation->id,
            'type' => 'sometimes|required|in:aisle,rack,shelf,bin,warehouse,zone,other',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:storage_locations,id',
            'aisle' => 'nullable|string|max:50',
            'bay' => 'nullable|string|max:50',
            'level' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:50',
            'capacity' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        // Prevent circular references
        if ($request->has('parent_id') && $request->parent_id) {
            if ($request->parent_id == $storageLocation->id) {
                return response()->json(['errors' => ['parent_id' => ['A location cannot be its own parent.']]], 422);
            }

            // Check if the new parent is a descendant of this location
            $parent = StorageLocation::find($request->parent_id);
            if ($parent && $parent->path && str_contains($parent->path, (string)$storageLocation->id)) {
                return response()->json(['errors' => ['parent_id' => ['Cannot move a location under one of its own descendants.']]], 422);
            }
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $storageLocation->update($validator->validated());

        return response()->json($storageLocation);
    }

    /**
     * Remove the specified storage location
     */
    public function destroy(StorageLocation $storageLocation)
    {
        // Check if location is being used
        if ($storageLocation->inventoryLocations()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete location that is currently in use. Please remove all inventory from this location first.',
                'inventory_count' => $storageLocation->inventoryLocations()->count(),
            ], 422);
        }

        $storageLocation->delete();

        return response()->json(['message' => 'Storage location deleted successfully']);
    }

    /**
     * Get available location names for autocomplete
     */
    public function locationNames(Request $request)
    {
        $query = StorageLocation::active();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $locations = $query->ordered()->limit(50)->pluck('name');

        return response()->json($locations);
    }

    /**
     * Generate a location code from name
     */
    private function generateLocationCode($name)
    {
        // Remove special characters and convert to uppercase
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));

        // Limit to 10 characters
        $code = substr($code, 0, 10);

        // Ensure uniqueness
        $originalCode = $code;
        $counter = 1;
        while (StorageLocation::where('code', $code)->exists()) {
            $code = $originalCode . $counter;
            $counter++;
        }

        return $code;
    }
}
