<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    /**
     * List all purchase orders
     */
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'items', 'creator']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('order_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('order_date', '<=', $request->date_to);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->orderBy('order_date', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($orders);
    }

    /**
     * Get a single purchase order with all details
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'supplier',
            'items.product',
            'creator',
            'approver'
        ]);

        return response()->json($purchaseOrder);
    }

    /**
     * Create a new purchase order
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
            'ship_to' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.destination_location' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate PO number
            $poNumber = PurchaseOrder::generatePoNumber();

            // Create purchase order
            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $request->supplier_id,
                'status' => 'draft',
                'order_date' => $request->order_date,
                'expected_date' => $request->expected_date,
                'notes' => $request->notes,
                'ship_to' => $request->ship_to,
                'created_by' => auth()->id(),
            ]);

            // Create PO items
            $totalAmount = 0;
            foreach ($request->items as $itemData) {
                $item = $po->items()->create([
                    'product_id' => $itemData['product_id'],
                    'quantity_ordered' => $itemData['quantity'],
                    'quantity_received' => 0,
                    'unit_cost' => $itemData['unit_cost'],
                    'total_cost' => $itemData['quantity'] * $itemData['unit_cost'],
                    'destination_location' => $itemData['destination_location'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $totalAmount += $item->total_cost;

                // Update product on_order_qty
                $product = Product::find($itemData['product_id']);
                $product->on_order_qty = ($product->on_order_qty ?? 0) + $itemData['quantity'];
                $product->save();
            }

            // Update PO total
            $po->total_amount = $totalAmount;
            $po->save();

            DB::commit();

            $po->load(['supplier', 'items.product', 'creator']);

            return response()->json([
                'message' => 'Purchase order created successfully',
                'purchase_order' => $po
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a purchase order
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Only draft orders can be fully edited
        if (!in_array($purchaseOrder->status, ['draft'])) {
            return response()->json([
                'message' => 'Only draft purchase orders can be edited'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'order_date' => 'sometimes|required|date',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'ship_to' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $purchaseOrder->update($request->only([
            'supplier_id',
            'order_date',
            'expected_date',
            'notes',
            'ship_to',
        ]));

        return response()->json([
            'message' => 'Purchase order updated successfully',
            'purchase_order' => $purchaseOrder->load(['supplier', 'items.product'])
        ]);
    }

    /**
     * Submit purchase order for approval
     */
    public function submit(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft orders can be submitted'
            ], 422);
        }

        if ($purchaseOrder->items()->count() === 0) {
            return response()->json([
                'message' => 'Cannot submit order with no items'
            ], 422);
        }

        $purchaseOrder->update(['status' => 'submitted']);

        return response()->json([
            'message' => 'Purchase order submitted successfully',
            'purchase_order' => $purchaseOrder
        ]);
    }

    /**
     * Approve purchase order
     */
    public function approve(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'submitted') {
            return response()->json([
                'message' => 'Only submitted orders can be approved'
            ], 422);
        }

        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Purchase order approved successfully',
            'purchase_order' => $purchaseOrder
        ]);
    }

    /**
     * Receive items from purchase order
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (!in_array($purchaseOrder->status, ['approved', 'partially_received'])) {
            return response()->json([
                'message' => 'Order must be approved before receiving'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.storage_location_id' => 'nullable|exists:storage_locations,id',
            'items.*.notes' => 'nullable|string',
            'received_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $receivedDate = $request->received_date ?? now();

            foreach ($request->items as $itemData) {
                $poItem = PurchaseOrderItem::findOrFail($itemData['item_id']);

                // Verify item belongs to this PO
                if ($poItem->purchase_order_id !== $purchaseOrder->id) {
                    throw new \Exception("Item does not belong to this purchase order");
                }

                $quantityToReceive = $itemData['quantity'];
                $remaining = $poItem->quantity_ordered - $poItem->quantity_received;

                if ($quantityToReceive > $remaining) {
                    throw new \Exception("Cannot receive more than ordered for item {$poItem->product->sku}");
                }

                // Update PO item
                $poItem->quantity_received += $quantityToReceive;
                // Note: destination_location is a text field for reference, not updated here
                $poItem->save();

                // Update product inventory
                $product = $poItem->product;
                $quantityBefore = $product->quantity_on_hand;
                $product->quantity_on_hand += $quantityToReceive;
                $product->on_order_qty = max(0, ($product->on_order_qty ?? 0) - $quantityToReceive);
                $product->save();

                // Create inventory transaction
                InventoryTransaction::create([
                    'product_id' => $product->id,
                    'type' => 'receipt',
                    'quantity' => $quantityToReceive,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $product->quantity_on_hand,
                    'reference_number' => $purchaseOrder->po_number,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $purchaseOrder->id,
                    'notes' => $itemData['notes'] ?? "Received from PO {$purchaseOrder->po_number}",
                    'user_id' => auth()->id(),
                    'transaction_date' => $receivedDate,
                ]);

                // Update storage location quantity if specified
                if (isset($itemData['storage_location_id'])) {
                    $location = $product->inventoryLocations()
                        ->where('storage_location_id', $itemData['storage_location_id'])
                        ->first();

                    if ($location) {
                        $location->quantity += $quantityToReceive;
                        $location->save();
                    } else {
                        $product->inventoryLocations()->create([
                            'storage_location_id' => $itemData['storage_location_id'],
                            'quantity' => $quantityToReceive,
                            'is_primary' => false,
                        ]);
                    }
                }
            }

            // Update PO status
            if ($purchaseOrder->is_fully_received) {
                $purchaseOrder->update([
                    'status' => 'received',
                    'received_date' => $receivedDate,
                ]);
            } else {
                $purchaseOrder->update([
                    'status' => 'partially_received',
                ]);
            }

            DB::commit();

            $purchaseOrder->load(['items.product', 'supplier']);

            return response()->json([
                'message' => 'Items received successfully',
                'purchase_order' => $purchaseOrder
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error receiving items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel purchase order
     */
    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status === 'cancelled') {
            return response()->json([
                'message' => 'Order is already cancelled'
            ], 422);
        }

        if ($purchaseOrder->status === 'received') {
            return response()->json([
                'message' => 'Cannot cancel fully received orders'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Release on_order quantities
            foreach ($purchaseOrder->items as $item) {
                $unreceived = $item->quantity_ordered - $item->quantity_received;
                if ($unreceived > 0) {
                    $product = $item->product;
                    $product->on_order_qty = max(0, ($product->on_order_qty ?? 0) - $unreceived);
                    $product->save();
                }
            }

            $purchaseOrder->update(['status' => 'cancelled']);

            DB::commit();

            return response()->json([
                'message' => 'Purchase order cancelled successfully',
                'purchase_order' => $purchaseOrder
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error cancelling purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete purchase order (only drafts)
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft orders can be deleted'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Release on_order quantities
            foreach ($purchaseOrder->items as $item) {
                $product = $item->product;
                $product->on_order_qty = max(0, ($product->on_order_qty ?? 0) - $item->quantity_ordered);
                $product->save();
            }

            $purchaseOrder->delete();

            DB::commit();

            return response()->json([
                'message' => 'Purchase order deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error deleting purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get open purchase orders
     */
    public function open()
    {
        $orders = PurchaseOrder::with(['supplier', 'items.product'])
            ->open()
            ->orderBy('order_date', 'asc')
            ->get();

        return response()->json($orders);
    }

    /**
     * Get statistics
     */
    public function statistics()
    {
        $stats = [
            'total_orders' => PurchaseOrder::count(),
            'draft' => PurchaseOrder::where('status', 'draft')->count(),
            'submitted' => PurchaseOrder::where('status', 'submitted')->count(),
            'approved' => PurchaseOrder::where('status', 'approved')->count(),
            'partially_received' => PurchaseOrder::where('status', 'partially_received')->count(),
            'received' => PurchaseOrder::where('status', 'received')->count(),
            'cancelled' => PurchaseOrder::where('status', 'cancelled')->count(),
            'total_value' => PurchaseOrder::whereIn('status', ['approved', 'partially_received', 'received'])
                ->sum('total_amount'),
            'pending_value' => PurchaseOrder::whereIn('status', ['approved', 'partially_received'])
                ->sum('total_amount'),
        ];

        return response()->json($stats);
    }
}
