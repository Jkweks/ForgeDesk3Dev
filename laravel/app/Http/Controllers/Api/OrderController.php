<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\CommittedInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['items.product', 'user']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->where('order_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('order_date', '<=', $request->to_date);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest('order_date')->paginate(50));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|max:255',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|max:50',
            'shipping_address' => 'nullable',
            'priority' => 'required|in:low,normal,high,urgent',
            'order_date' => 'required|date',
            'expected_ship_date' => 'nullable|date',
            'notes' => 'nullable',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'status' => 'pending',
                'priority' => $validated['priority'],
                'order_date' => $validated['order_date'],
                'expected_ship_date' => $validated['expected_ship_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $subtotal = 0;

            foreach ($validated['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $lineTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $lineTotal,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $order->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            return response()->json($order->load('items.product'), 201);
        });
    }

    public function show(Order $order)
    {
        return response()->json($order->load([
            'items.product',
            'user',
            'committedInventory.product'
        ]));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'customer_name' => 'required|max:255',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|max:50',
            'shipping_address' => 'nullable',
            'status' => 'required|in:pending,processing,ready,shipped,completed,cancelled',
            'priority' => 'required|in:low,normal,high,urgent',
            'expected_ship_date' => 'nullable|date',
            'actual_ship_date' => 'nullable|date',
            'notes' => 'nullable',
        ]);

        $order->update($validated);

        return response()->json($order->load('items.product'));
    }

    public function destroy(Order $order)
    {
        $order->committedInventory()->delete();
        $order->delete();
        return response()->json(null, 204);
    }

    public function commitInventory(Request $request, Order $order)
    {
        $validated = $request->validate([
            'expected_release_date' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($order, $validated) {
            foreach ($order->items as $item) {
                $product = $item->product;
                
                if ($product->quantity_available < $item->quantity) {
                    return response()->json([
                        'error' => "Insufficient inventory for {$product->sku}"
                    ], 422);
                }

                CommittedInventory::create([
                    'product_id' => $product->id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'quantity_committed' => $item->quantity,
                    'committed_date' => now(),
                    'expected_release_date' => $validated['expected_release_date'] ?? null,
                ]);

                $product->quantity_committed += $item->quantity;
                $product->save();
                $product->updateStatus();

                $item->quantity_committed = $item->quantity;
                $item->save();
            }

            $order->status = 'processing';
            $order->save();

            return response()->json([
                'message' => 'Inventory committed successfully',
                'order' => $order->fresh()->load('items.product', 'committedInventory')
            ]);
        });
    }

    public function releaseInventory(Request $request, Order $order)
    {
        return DB::transaction(function () use ($order) {
            foreach ($order->committedInventory as $committed) {
                $product = $committed->product;
                
                $product->quantity_committed -= $committed->quantity_committed;
                $product->save();
                $product->updateStatus();

                $committed->orderItem->quantity_committed = 0;
                $committed->orderItem->save();

                $committed->delete();
            }

            $order->status = 'pending';
            $order->save();

            return response()->json([
                'message' => 'Inventory released successfully',
                'order' => $order->fresh()->load('items.product')
            ]);
        });
    }

    public function shipOrder(Request $request, Order $order)
    {
        $validated = $request->validate([
            'actual_ship_date' => 'required|date',
        ]);

        return DB::transaction(function () use ($order, $validated) {
            foreach ($order->items as $item) {
                $product = $item->product;
                
                $product->adjustQuantity(
                    -$item->quantity,
                    'shipment',
                    $order->order_number,
                    "Shipped for order {$order->order_number}"
                );

                $product->quantity_committed -= $item->quantity_committed;
                $product->save();
                $product->updateStatus();

                $item->quantity_shipped = $item->quantity;
                $item->save();
            }

            $order->committedInventory()->delete();

            $order->update([
                'status' => 'shipped',
                'actual_ship_date' => $validated['actual_ship_date'],
            ]);

            return response()->json([
                'message' => 'Order shipped successfully',
                'order' => $order->fresh()->load('items.product')
            ]);
        });
    }
}