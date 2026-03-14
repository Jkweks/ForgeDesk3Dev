<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequiredPart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequiredPartsController extends Controller
{
    /**
     * Get all required parts (BOM) for a product
     */
    public function index(Product $product)
    {
        $requiredParts = $product->requiredParts()
            ->with('requiredProduct')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json($requiredParts);
    }

    /**
     * Add a required part to a product
     */
    public function store(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'required_product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0',
            'finish_policy' => 'required|in:same_as_parent,specific,any',
            'specific_finish' => 'nullable|string',
            'is_optional' => 'boolean',
            'notes' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent circular dependencies
        if ($this->wouldCreateCircularDependency($product->id, $request->required_product_id)) {
            return response()->json([
                'message' => 'Cannot add this part - would create circular dependency'
            ], 422);
        }

        // Check if this required part already exists
        $existing = $product->requiredParts()
            ->where('required_product_id', $request->required_product_id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'This part is already in the BOM'
            ], 422);
        }

        $requiredPart = $product->requiredParts()->create($request->all());
        $requiredPart->load('requiredProduct');

        return response()->json([
            'message' => 'Required part added successfully',
            'required_part' => $requiredPart
        ], 201);
    }

    /**
     * Update a required part
     */
    public function update(Request $request, Product $product, RequiredPart $requiredPart)
    {
        // Verify the required part belongs to this product
        if ($requiredPart->parent_product_id !== $product->id) {
            return response()->json([
                'message' => 'Required part does not belong to this product'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'sometimes|required|numeric|min:0',
            'finish_policy' => 'sometimes|required|in:same_as_parent,specific,any',
            'specific_finish' => 'nullable|string',
            'is_optional' => 'boolean',
            'notes' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $requiredPart->update($request->all());
        $requiredPart->load('requiredProduct');

        return response()->json([
            'message' => 'Required part updated successfully',
            'required_part' => $requiredPart
        ]);
    }

    /**
     * Remove a required part
     */
    public function destroy(Product $product, RequiredPart $requiredPart)
    {
        // Verify the required part belongs to this product
        if ($requiredPart->parent_product_id !== $product->id) {
            return response()->json([
                'message' => 'Required part does not belong to this product'
            ], 404);
        }

        $requiredPart->delete();

        return response()->json([
            'message' => 'Required part removed successfully'
        ]);
    }

    /**
     * Get BOM explosion - calculate total material requirements
     * This recursively expands the BOM to show all nested parts needed
     */
    public function explosion(Product $product, Request $request)
    {
        $quantity = $request->get('quantity', 1);
        $finish = $request->get('finish', $product->finish);

        $explosion = $this->explodeBOM($product, $quantity, $finish);

        return response()->json([
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'description' => $product->description,
                'quantity_requested' => $quantity,
                'finish' => $finish,
            ],
            'total_parts' => count($explosion),
            'parts' => $explosion,
            'summary' => $this->summarizeExplosion($explosion),
        ]);
    }

    /**
     * Check availability of all parts in BOM
     */
    public function checkAvailability(Product $product, Request $request)
    {
        $quantity = $request->get('quantity', 1);
        $finish = $request->get('finish', $product->finish);

        $explosion = $this->explodeBOM($product, $quantity, $finish);
        $availability = [];
        $allAvailable = true;

        foreach ($explosion as $part) {
            $partProduct = Product::find($part['product_id']);
            $required = $part['total_quantity'];
            $available = $partProduct->quantity_available;
            $isAvailable = $available >= $required;

            if (!$isAvailable) {
                $allAvailable = false;
            }

            $availability[] = [
                'product_id' => $part['product_id'],
                'sku' => $part['sku'],
                'description' => $part['description'],
                'required' => $required,
                'available' => $available,
                'shortage' => max(0, $required - $available),
                'is_available' => $isAvailable,
            ];
        }

        return response()->json([
            'all_available' => $allAvailable,
            'parts' => $availability,
        ]);
    }

    /**
     * Update sort order for required parts
     */
    public function updateSortOrder(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'parts' => 'required|array',
            'parts.*.id' => 'required|exists:required_parts,id',
            'parts.*.sort_order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->parts as $partData) {
            RequiredPart::where('id', $partData['id'])
                ->where('parent_product_id', $product->id)
                ->update(['sort_order' => $partData['sort_order']]);
        }

        return response()->json([
            'message' => 'Sort order updated successfully'
        ]);
    }

    /**
     * Get all products that use this product as a required part
     */
    public function whereUsed(Product $product)
    {
        $usedIn = $product->usedInProducts()
            ->with('parentProduct')
            ->get()
            ->map(function ($requiredPart) {
                return [
                    'id' => $requiredPart->parentProduct->id,
                    'sku' => $requiredPart->parentProduct->sku,
                    'description' => $requiredPart->parentProduct->description,
                    'quantity' => $requiredPart->quantity,
                    'finish_policy' => $requiredPart->finish_policy,
                ];
            });

        return response()->json($usedIn);
    }

    /**
     * Recursively explode BOM
     */
    private function explodeBOM($product, $quantity, $finish, $level = 0, &$visited = [])
    {
        $explosion = [];

        // Prevent infinite loops
        if (isset($visited[$product->id])) {
            return $explosion;
        }
        $visited[$product->id] = true;

        $requiredParts = $product->requiredParts()->with('requiredProduct')->get();

        foreach ($requiredParts as $requiredPart) {
            $requiredProduct = $requiredPart->requiredProduct;
            $partQuantity = $requiredPart->quantity * $quantity;

            // Determine finish based on policy
            $partFinish = $this->determineFinish($requiredPart, $finish);

            $explosion[] = [
                'product_id' => $requiredProduct->id,
                'sku' => $requiredProduct->sku,
                'description' => $requiredProduct->description,
                'quantity_per_unit' => $requiredPart->quantity,
                'total_quantity' => $partQuantity,
                'finish' => $partFinish,
                'finish_policy' => $requiredPart->finish_policy,
                'is_optional' => $requiredPart->is_optional,
                'level' => $level + 1,
                'parent_sku' => $product->sku,
            ];

            // Recursively explode this part's BOM
            $subExplosion = $this->explodeBOM($requiredProduct, $partQuantity, $partFinish, $level + 1, $visited);
            $explosion = array_merge($explosion, $subExplosion);
        }

        return $explosion;
    }

    /**
     * Determine finish based on policy
     */
    private function determineFinish($requiredPart, $parentFinish)
    {
        switch ($requiredPart->finish_policy) {
            case 'same_as_parent':
                return $parentFinish;
            case 'specific':
                return $requiredPart->specific_finish;
            case 'any':
            default:
                return null;
        }
    }

    /**
     * Summarize explosion by consolidating duplicate parts
     */
    private function summarizeExplosion($explosion)
    {
        $summary = [];

        foreach ($explosion as $part) {
            $key = $part['product_id'] . '-' . ($part['finish'] ?? 'any');

            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'product_id' => $part['product_id'],
                    'sku' => $part['sku'],
                    'description' => $part['description'],
                    'finish' => $part['finish'],
                    'total_quantity' => 0,
                ];
            }

            $summary[$key]['total_quantity'] += $part['total_quantity'];
        }

        return array_values($summary);
    }

    /**
     * Check if adding this part would create a circular dependency
     */
    private function wouldCreateCircularDependency($parentId, $childId, &$visited = [])
    {
        // If we're trying to add a parent as its own child
        if ($parentId == $childId) {
            return true;
        }

        // Prevent infinite loops
        if (isset($visited[$childId])) {
            return false;
        }
        $visited[$childId] = true;

        // Check if the child product has the parent in its BOM
        $childProduct = Product::find($childId);
        if (!$childProduct) {
            return false;
        }

        $childParts = $childProduct->requiredParts()->pluck('required_product_id')->toArray();

        if (in_array($parentId, $childParts)) {
            return true;
        }

        // Recursively check children
        foreach ($childParts as $grandChildId) {
            if ($this->wouldCreateCircularDependency($parentId, $grandChildId, $visited)) {
                return true;
            }
        }

        return false;
    }
}
