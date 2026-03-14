<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InventoryTransactionController extends Controller
{
    /**
     * Get all transactions with filtering
     */
    public function index(Request $request)
    {
        $query = InventoryTransaction::with(['product', 'user']);

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by type
        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Search by reference number or notes
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Order by most recent first
        $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc');

        $perPage = $request->get('per_page', 50);

        if ($perPage === 'all') {
            return response()->json($query->get());
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Get transactions for a specific product
     */
    public function productTransactions(Product $product, Request $request)
    {
        $query = $product->inventoryTransactions()->with('user');

        // Filter by type
        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc');

        $perPage = $request->get('per_page', 50);

        if ($perPage === 'all') {
            return response()->json($query->get());
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Get a specific transaction
     */
    public function show(InventoryTransaction $transaction)
    {
        $transaction->load(['product', 'user']);
        return response()->json($transaction);
    }

    /**
     * Get transaction statistics
     */
    public function statistics(Request $request)
    {
        $query = InventoryTransaction::query();

        // Apply filters if provided
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        // Get total transactions
        $total = (clone $query)->count();

        // Get this month's transactions
        $thisMonth = (clone $query)
            ->whereYear('transaction_date', now()->year)
            ->whereMonth('transaction_date', now()->month)
            ->count();

        // Get today's transactions
        $today = (clone $query)
            ->whereDate('transaction_date', now()->toDateString())
            ->count();

        // Get most active type
        $byType = (clone $query)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->orderBy('count', 'desc')
            ->get();

        $mostActiveType = null;
        if ($byType->isNotEmpty()) {
            $topType = $byType->first();
            $typeLabels = [
                'receipt' => 'Receipt',
                'shipment' => 'Shipment',
                'adjustment' => 'Adjustment',
                'transfer' => 'Transfer',
                'return' => 'Return',
                'cycle_count' => 'Cycle Count',
                'job_issue' => 'Job Issue',
                'issue' => 'Issue',
            ];
            $mostActiveType = [
                'type' => $topType->type,
                'label' => $typeLabels[$topType->type] ?? $topType->type,
                'count' => $topType->count,
            ];
        }

        $stats = [
            'total' => $total,
            'this_month' => $thisMonth,
            'today' => $today,
            'most_active_type' => $mostActiveType,
            'by_type' => $byType->pluck('count', 'type'),
        ];

        return response()->json($stats);
    }

    /**
     * Get transaction types
     */
    public function types()
    {
        return response()->json([
            'receipt' => 'Receipt',
            'shipment' => 'Shipment',
            'adjustment' => 'Adjustment',
            'transfer' => 'Transfer',
            'return' => 'Return',
            'cycle_count' => 'Cycle Count',
            'job_issue' => 'Job Issue',
            'issue' => 'Issue',
            'job_material_transfer' => 'Job Material Transfer',
        ]);
    }

    /**
     * Log a transaction (helper method called by other controllers)
     */
    public static function logTransaction(array $data)
    {
        return InventoryTransaction::create([
            'product_id' => $data['product_id'],
            'type' => $data['type'],
            'quantity' => $data['quantity'],
            'quantity_before' => $data['quantity_before'],
            'quantity_after' => $data['quantity_after'],
            'reference_number' => $data['reference_number'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'user_id' => $data['user_id'] ?? Auth::id(),
            'transaction_date' => $data['transaction_date'] ?? now(),
        ]);
    }

    /**
     * Export transactions to CSV
     */
    public function export(Request $request)
    {
        $query = InventoryTransaction::with(['product', 'user']);

        // Apply same filters as index
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        if ($request->has('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->get();

        $filename = 'inventory_transactions_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Date',
                'Type',
                'Product SKU',
                'Product Description',
                'Quantity',
                'Before',
                'After',
                'Reference',
                'User',
                'Notes',
            ]);

            // CSV Rows
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->transaction_date->format('Y-m-d H:i:s'),
                    ucfirst($transaction->type),
                    $transaction->product->sku ?? '',
                    $transaction->product->description ?? '',
                    $transaction->quantity >= 0 ? '+' . $transaction->quantity : $transaction->quantity,
                    $transaction->quantity_before,
                    $transaction->quantity_after,
                    $transaction->reference_number ?? '',
                    $transaction->user->name ?? '',
                    $transaction->notes ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get recent activity across all products
     */
    public function recentActivity(Request $request)
    {
        $limit = $request->get('limit', 20);

        $transactions = InventoryTransaction::with(['product', 'user'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($transactions);
    }

    /**
     * Get transactions grouped by date
     */
    public function timeline(Request $request)
    {
        $query = InventoryTransaction::with(['product', 'user']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Group by date
        $transactions = $query->orderBy('transaction_date', 'desc')
            ->get()
            ->groupBy(function($transaction) {
                return $transaction->transaction_date->format('Y-m-d');
            });

        return response()->json($transactions);
    }

    /**
     * Create a manual transaction (supports multi-part transactions)
     */
    public function createManual(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:issue,return,receipt,adjustment,transfer,cycle_count,shipment,job_issue,job_material_transfer',
            'transaction_date' => 'required|date',
            'reference_number' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $transactions = [];
            $errors = [];

            // Process each product in the transaction
            foreach ($validated['products'] as $productData) {
                $product = Product::findOrFail($productData['product_id']);

                // Record quantity before
                $quantityBefore = $product->quantity_on_hand;

                // Determine if transaction removes or adds inventory
                // Issue, shipment, and job_issue remove from inventory (negative)
                // All others add to inventory (positive)
                $removesInventory = in_array($validated['type'], ['issue', 'shipment', 'job_issue']);
                $quantityChange = $removesInventory ? -$productData['quantity'] : $productData['quantity'];

                // Update inventory locations (source of truth)
                if ($quantityChange > 0) {
                    // Adding inventory - add to primary location (or create if doesn't exist)
                    $primaryLocation = $product->inventoryLocations()->where('is_primary', true)->first();

                    if (!$primaryLocation) {
                        // Get or create a default primary location
                        $defaultLocation = \App\Models\StorageLocation::firstOrCreate(
                            ['code' => 'DEFAULT'],
                            ['name' => 'Default Storage', 'type' => 'warehouse', 'is_active' => true]
                        );

                        $primaryLocation = $product->inventoryLocations()->create([
                            'storage_location_id' => $defaultLocation->id,
                            'quantity' => 0,
                            'quantity_committed' => 0,
                            'is_primary' => true,
                        ]);
                    }

                    $primaryLocation->quantity += $quantityChange;
                    $primaryLocation->save();

                } elseif ($quantityChange < 0) {
                    // Removing inventory - remove from primary first, then secondary locations
                    $remainingToRemove = abs($quantityChange);

                    // Get locations ordered by primary first, then by available quantity
                    $locations = $product->inventoryLocations()
                        ->orderByRaw('is_primary DESC')
                        ->orderByRaw('(quantity - quantity_committed) DESC')
                        ->get();

                    foreach ($locations as $location) {
                        if ($remainingToRemove <= 0) break;

                        $availableAtLocation = $location->quantity - $location->quantity_committed;
                        $toRemoveFromLocation = min($remainingToRemove, $availableAtLocation);

                        if ($toRemoveFromLocation > 0) {
                            $location->quantity -= $toRemoveFromLocation;
                            $location->save();
                            $remainingToRemove -= $toRemoveFromLocation;
                        }
                    }

                    // If still have remaining to remove, force remove from locations with inventory
                    if ($remainingToRemove > 0) {
                        foreach ($locations as $location) {
                            if ($remainingToRemove <= 0) break;

                            if ($location->quantity > 0) {
                                $toRemoveFromLocation = min($remainingToRemove, $location->quantity);
                                $location->quantity -= $toRemoveFromLocation;
                                $location->save();
                                $remainingToRemove -= $toRemoveFromLocation;
                            }
                        }
                    }
                }

                // Recalculate product totals from locations (source of truth)
                $product->recalculateQuantitiesFromLocations();
                $product->refresh();
                $quantityAfter = $product->quantity_on_hand;

                // Prevent negative inventory
                if ($quantityAfter < 0) {
                    $errors[] = "Insufficient inventory for {$product->sku}. Available: {$quantityBefore}, Requested: {$productData['quantity']}";
                    continue;
                }

                // Create transaction record for this product
                $transaction = InventoryTransaction::create([
                    'product_id' => $product->id,
                    'type' => $validated['type'],
                    'quantity' => $quantityChange,  // Store negative for issue, positive for receipt/return
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'reference_number' => $validated['reference_number'],
                    'reference_type' => 'manual',
                    'reference_id' => null,
                    'notes' => $validated['notes'],
                    'user_id' => Auth::id(),
                    'transaction_date' => $validated['transaction_date'],
                ]);

                // Update product status
                $product->updateStatus();

                $transactions[] = $transaction;
            }

            // If any errors occurred, rollback
            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Transaction failed',
                    'errors' => $errors
                ], 422);
            }

            DB::commit();

            // Load relationships on each transaction
            foreach ($transactions as $transaction) {
                $transaction->load(['product', 'user']);
            }

            return response()->json([
                'message' => count($transactions) > 1
                    ? count($transactions) . ' transactions created successfully'
                    : 'Transaction created successfully',
                'transactions' => $transactions
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
