<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     * Supports hierarchical structure
     */
    public function index(Request $request)
    {
        $query = Category::query();

        // Filter by parent (null for root categories, ID for subcategories)
        if ($request->has('parent_id')) {
            if ($request->parent_id === 'null' || $request->parent_id === '') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        // Filter by system classification
        if ($request->has('system') && $request->system !== '') {
            $query->where('system', $request->system);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Search by name or code
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Include subcategories count
        $query->withCount('children');

        // Include products count
        $query->withCount('products');

        // Load parent relationship if needed
        if ($request->boolean('with_parent')) {
            $query->with('parent');
        }

        // Load children relationship if needed
        if ($request->boolean('with_children')) {
            $query->with('children');
        }

        // Get hierarchical tree structure
        if ($request->boolean('tree')) {
            $categories = $query->whereNull('parent_id')
                               ->with('descendants')
                               ->orderBy('sort_order')
                               ->orderBy('name')
                               ->get();

            return response()->json($categories);
        }

        // Standard pagination
        $perPage = $request->get('per_page', 50);

        if ($perPage === 'all') {
            $categories = $query->orderBy('sort_order')
                               ->orderBy('name')
                               ->get();
            return response()->json($categories);
        }

        $categories = $query->orderBy('sort_order')
                           ->orderBy('name')
                           ->paginate($perPage);

        return response()->json($categories);
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255|unique:categories,code',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'system' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $category = Category::create($request->all());

        // Load relationships
        $category->load('parent', 'children');
        $category->loadCount('products');

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ], 201);
    }

    /**
     * Display the specified category
     */
    public function show(Category $category)
    {
        $category->load('parent', 'children', 'products');
        $category->loadCount('products', 'children');

        return response()->json($category);
    }

    /**
     * Update the specified category
     */
    public function update(Request $request, Category $category)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:255|unique:categories,code,' . $category->id,
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'system' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent circular parent relationships
        if ($request->has('parent_id') && $request->parent_id) {
            if ($this->wouldCreateCircularReference($category, $request->parent_id)) {
                return response()->json([
                    'message' => 'Cannot set parent - would create circular reference'
                ], 422);
            }
        }

        $category->update($request->all());
        $category->load('parent', 'children');
        $category->loadCount('products', 'children');

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    /**
     * Remove the specified category
     */
    public function destroy(Category $category)
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with associated products',
                'products_count' => $category->products()->count()
            ], 422);
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories',
                'children_count' => $category->children()->count()
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Get category tree structure
     */
    public function tree(Request $request)
    {
        $query = Category::whereNull('parent_id');

        // Filter by system classification
        if ($request->has('system') && $request->system !== '') {
            $query->where('system', $request->system);
        }

        // Search by name or code
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $categories = $query
            ->with(['descendants' => function ($query) use ($request) {
                $query->withCount('products');

                // Apply same filters to descendants
                if ($request->has('system') && $request->system !== '') {
                    $query->where('system', $request->system);
                }
                if ($request->has('search') && $request->search !== '') {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('code', 'like', "%{$search}%");
                    });
                }
            }])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($this->buildTree($categories));
    }

    /**
     * Build nested tree structure with all descendants
     */
    private function buildTree($categories)
    {
        return $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'parent_id' => $category->parent_id,
                'system' => $category->system,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
                'products_count' => $category->products_count ?? 0,
                'children_count' => $category->children->count(),
                'children' => $category->children->count() > 0 ? $this->buildTree($category->children()->withCount('products')->orderBy('sort_order')->orderBy('name')->get()) : [],
            ];
        });
    }

    /**
     * Get all distinct system classifications
     */
    public function systems()
    {
        $systems = Category::whereNotNull('system')
            ->distinct()
            ->pluck('system')
            ->filter()
            ->values();

        return response()->json($systems);
    }

    /**
     * Bulk update category sort orders
     */
    public function updateSortOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.sort_order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->categories as $categoryData) {
            Category::where('id', $categoryData['id'])
                ->update(['sort_order' => $categoryData['sort_order']]);
        }

        return response()->json([
            'message' => 'Sort order updated successfully'
        ]);
    }

    /**
     * Bulk operations (activate/deactivate/delete)
     */
    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete',
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $categories = Category::whereIn('id', $request->category_ids);

        switch ($request->action) {
            case 'activate':
                $categories->update(['is_active' => true]);
                $message = 'Categories activated successfully';
                break;

            case 'deactivate':
                $categories->update(['is_active' => false]);
                $message = 'Categories deactivated successfully';
                break;

            case 'delete':
                // Check for products or children
                foreach ($categories->get() as $category) {
                    if ($category->products()->count() > 0 || $category->children()->count() > 0) {
                        return response()->json([
                            'message' => 'Cannot delete categories with products or subcategories'
                        ], 422);
                    }
                }
                $categories->delete();
                $message = 'Categories deleted successfully';
                break;
        }

        return response()->json([
            'message' => $message
        ]);
    }

    /**
     * Check if setting a parent would create a circular reference
     */
    private function wouldCreateCircularReference($category, $newParentId)
    {
        // Can't be its own parent
        if ($category->id == $newParentId) {
            return true;
        }

        // Check if new parent is a descendant
        $parent = Category::find($newParentId);
        while ($parent) {
            if ($parent->id == $category->id) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }
}
