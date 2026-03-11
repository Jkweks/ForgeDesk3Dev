<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers
     */
    public function index(Request $request)
    {
        $query = Supplier::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Search by name, code, or contact name
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        // Filter by country
        if ($request->has('country') && $request->country !== '') {
            $query->where('country', $request->country);
        }

        // Include products count
        $query->withCount('products');

        // Include products if requested
        if ($request->boolean('with_products')) {
            $query->with('products');
        }

        // Get all suppliers without pagination if requested
        $perPage = $request->get('per_page', 50);

        if ($perPage === 'all') {
            $suppliers = $query->orderBy('name')->get();
            return response()->json($suppliers);
        }

        $suppliers = $query->orderBy('name')->paginate($perPage);

        return response()->json($suppliers);
    }

    /**
     * Store a newly created supplier
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255|unique:suppliers,code',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'default_lead_time_days' => 'nullable|integer|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $supplier = Supplier::create($request->all());

        $supplier->loadCount('products');

        return response()->json([
            'message' => 'Supplier created successfully',
            'supplier' => $supplier
        ], 201);
    }

    /**
     * Display the specified supplier
     */
    public function show(Supplier $supplier)
    {
        $supplier->load('products');
        $supplier->loadCount('products');

        // Calculate supplier statistics
        $stats = [
            'total_products' => $supplier->products()->count(),
            'active_products' => $supplier->products()->where('is_active', true)->count(),
            'total_inventory_value' => $supplier->products()
                ->sum(DB::raw('quantity_on_hand * unit_cost')),
            'low_stock_items' => $supplier->products()
                ->whereIn('status', ['low_stock', 'critical'])
                ->count(),
        ];

        return response()->json([
            'supplier' => $supplier,
            'stats' => $stats,
        ]);
    }

    /**
     * Update the specified supplier
     */
    public function update(Request $request, Supplier $supplier)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:255|unique:suppliers,code,' . $supplier->id,
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'default_lead_time_days' => 'nullable|integer|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $supplier->update($request->all());
        $supplier->loadCount('products');

        return response()->json([
            'message' => 'Supplier updated successfully',
            'supplier' => $supplier
        ]);
    }

    /**
     * Remove the specified supplier
     */
    public function destroy(Supplier $supplier)
    {
        // Check if supplier has products
        if ($supplier->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete supplier with associated products',
                'products_count' => $supplier->products()->count()
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Supplier deleted successfully'
        ]);
    }

    /**
     * Get all distinct countries
     */
    public function countries()
    {
        $countries = Supplier::whereNotNull('country')
            ->distinct()
            ->pluck('country')
            ->filter()
            ->sort()
            ->values();

        return response()->json($countries);
    }

    /**
     * Get supplier statistics
     */
    public function statistics()
    {
        $stats = [
            'total_suppliers' => Supplier::count(),
            'active_suppliers' => Supplier::where('is_active', true)->count(),
            'suppliers_with_products' => Supplier::has('products')->count(),
            'total_products_from_suppliers' => Product::whereNotNull('supplier_id')->count(),
            'countries_count' => Supplier::distinct('country')->count('country'),
        ];

        // Top suppliers by product count
        $topSuppliers = Supplier::withCount('products')
            ->orderBy('products_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'products_count' => $supplier->products_count,
                ];
            });

        return response()->json([
            'stats' => $stats,
            'top_suppliers' => $topSuppliers,
        ]);
    }

    /**
     * Get products for a specific supplier
     */
    public function products(Supplier $supplier)
    {
        $products = $supplier->products()
            ->with(['category', 'inventoryLocations'])
            ->orderBy('sku')
            ->paginate(50);

        return response()->json($products);
    }

    /**
     * Bulk operations (activate/deactivate/delete)
     */
    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete',
            'supplier_ids' => 'required|array',
            'supplier_ids.*' => 'exists:suppliers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $suppliers = Supplier::whereIn('id', $request->supplier_ids);

        switch ($request->action) {
            case 'activate':
                $suppliers->update(['is_active' => true]);
                $message = 'Suppliers activated successfully';
                break;

            case 'deactivate':
                $suppliers->update(['is_active' => false]);
                $message = 'Suppliers deactivated successfully';
                break;

            case 'delete':
                // Check for products
                foreach ($suppliers->get() as $supplier) {
                    if ($supplier->products()->count() > 0) {
                        return response()->json([
                            'message' => 'Cannot delete suppliers with associated products'
                        ], 422);
                    }
                }
                $suppliers->delete();
                $message = 'Suppliers deleted successfully';
                break;
        }

        return response()->json([
            'message' => $message
        ]);
    }

    /**
     * Get supplier contact information
     * This endpoint is used for auto-populating contact info in product forms
     */
    public function contacts(Supplier $supplier)
    {
        return response()->json([
            'contact_name' => $supplier->contact_name,
            'contact_email' => $supplier->contact_email,
            'contact_phone' => $supplier->contact_phone,
            'default_lead_time_days' => $supplier->default_lead_time_days,
        ]);
    }

    /**
     * Get low stock report for supplier
     */
    public function lowStockReport(Supplier $supplier)
    {
        $products = $supplier->products()
            ->whereIn('status', ['low_stock', 'critical'])
            ->with(['category', 'inventoryLocations'])
            ->orderBy('status')
            ->orderBy('sku')
            ->get();

        return response()->json([
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'contact_email' => $supplier->contact_email,
            ],
            'low_stock_products' => $products,
            'total_items' => $products->count(),
        ]);
    }
}
