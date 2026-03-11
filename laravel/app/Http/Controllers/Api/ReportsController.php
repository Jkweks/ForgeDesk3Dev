<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * Get low stock and critical stock report
     */
    public function lowStockReport(Request $request)
    {
        $lowStock = Product::where('status', 'low_stock')
            ->where('is_active', true)
            ->with(['category', 'supplier', 'inventoryLocations'])
            ->get()
            ->map(function ($product) {
                return $this->enrichProductData($product);
            });

        $critical = Product::where('status', 'critical')
            ->where('is_active', true)
            ->with(['category', 'supplier', 'inventoryLocations'])
            ->get()
            ->map(function ($product) {
                return $this->enrichProductData($product);
            });

        return response()->json([
            'low_stock' => $lowStock,
            'critical' => $critical,
            'summary' => [
                'low_stock_count' => $lowStock->count(),
                'critical_count' => $critical->count(),
                'total_affected' => $lowStock->count() + $critical->count(),
                'estimated_value_at_risk' => $lowStock->sum('total_value') + $critical->sum('total_value'),
            ],
        ]);
    }

    /**
     * Get committed parts report
     * Uses the fulfillment system (job_reservation_items) to calculate committed quantities
     */
    public function committedPartsReport(Request $request)
    {
        // Get products that have active reservation items
        // Active statuses per fulfillment process: 'active', 'in_progress', 'on_hold'
        $committedProducts = Product::where('is_active', true)
            ->whereHas('reservationItems', function($query) {
                $query->whereHas('reservation', function($resQuery) {
                    $resQuery->whereIn('status', ['active', 'in_progress', 'on_hold']);
                });
            })
            ->with(['category', 'supplier', 'reservationItems' => function($query) {
                $query->whereHas('reservation', function($resQuery) {
                    $resQuery->whereIn('status', ['active', 'in_progress', 'on_hold']);
                })->with('reservation');
            }])
            ->get()
            ->map(function ($product) {
                $data = $this->enrichProductData($product);

                // Calculate committed quantity from active reservation items (in eaches)
                $committedQty = $product->reservationItems->sum('committed_qty');
                $data['committed'] = $committedQty;
                $data['committed_packs'] = $product->eachesToPacksNeeded($committedQty);
                $data['committed_display'] = $product->hasPackSize() ? $data['committed_packs'] : $committedQty;
                $data['available'] = $product->quantity_on_hand - $committedQty;
                $data['available_packs'] = $product->hasPackSize() ? $product->eachesToPacksNeeded($data['available']) : $data['available'];
                $data['available_display'] = $product->hasPackSize() ? $data['available_packs'] : $data['available'];
                $data['pack_size'] = $product->pack_size;
                $data['has_pack_size'] = $product->hasPackSize();
                $data['counting_unit'] = $product->counting_unit;
                $data['on_hand_packs'] = $product->quantity_on_hand_packs;

                // Map reservation items to reservation details with pack calculations
                $data['reservations'] = $product->reservationItems->map(function ($item) use ($product) {
                    return [
                        'id' => $item->reservation->id,
                        'job_number' => $item->reservation->job_number,
                        'release_number' => $item->reservation->release_number,
                        'job_name' => $item->reservation->job_name,
                        'quantity' => $item->committed_qty,
                        'quantity_packs' => $product->eachesToPacksNeeded($item->committed_qty),
                        'requested_qty' => $item->requested_qty,
                        'consumed_qty' => $item->consumed_qty,
                        'status' => $item->reservation->status,
                        'needed_by' => $item->reservation->needed_by,
                    ];
                });
                return $data;
            });

        return response()->json([
            'committed_products' => $committedProducts,
            'summary' => [
                'total_products' => $committedProducts->count(),
                'total_quantity_committed' => $committedProducts->sum('committed'),
                'total_value_committed' => $committedProducts->sum(function($p) {
                    return $p['committed_display'] * $p['display_cost'];
                }),
            ],
        ]);
    }

    /**
     * Stock velocity analysis
     */
    public function stockVelocityAnalysis(Request $request)
    {
        $days = $request->get('days', 90);
        $since = Carbon::now()->subDays($days);

        // Eager load products with their transactions in a single query
        $products = Product::where('is_active', true)
            ->with([
                'category',
                'supplier',
                'transactions' => function($query) use ($since) {
                    $query->where('transaction_date', '>=', $since);
                }
            ])
            ->get();

        $velocityData = $products->map(function ($product) use ($since) {
            // Use pre-loaded transactions (no additional query)
            $transactions = $product->transactions;

            $receipts = $transactions->where('type', 'receipt')->sum('quantity');

            // Count all inventory removals as shipments (negative quantity transactions)
            // This includes: shipment, issue, job_issue, and negative adjustments
            $shipments = abs($transactions->filter(function($t) {
                return $t->quantity < 0;
            })->sum('quantity'));

            $adjustments = $transactions->where('type', 'adjustment')->sum('quantity');

            $turnoverRate = $product->quantity_on_hand > 0
                ? round(($shipments / max($product->quantity_on_hand, 1)) * 100, 2)
                : 0;

            // Classify velocity
            $velocity = 'slow';
            if ($turnoverRate > 200) $velocity = 'fast';
            elseif ($turnoverRate > 100) $velocity = 'medium';

            // Days until stockout
            $daysUntilStockout = null;
            if ($product->average_daily_use > 0) {
                $daysUntilStockout = round($product->quantity_available / $product->average_daily_use);
            }

            // Use pack-based quantities if applicable
            $onHandDisplay = $product->hasPackSize() ? $product->quantity_on_hand_packs : $product->quantity_on_hand;
            $availableDisplay = $product->hasPackSize() ? $product->quantity_available_packs : $product->quantity_available;

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'description' => $product->description,
                'category' => $product->category?->name,
                'on_hand' => $product->quantity_on_hand,
                'on_hand_display' => $onHandDisplay,
                'available' => $product->quantity_available,
                'available_display' => $availableDisplay,
                'receipts' => $receipts,
                'shipments' => $shipments,
                'turnover_rate' => $turnoverRate,
                'velocity' => $velocity,
                'average_daily_use' => $product->average_daily_use,
                'days_until_stockout' => $daysUntilStockout,
                'pack_size' => $product->pack_size,
                'counting_unit' => $product->counting_unit,
            ];
        });

        // Sort by velocity (fastest first)
        $sorted = $velocityData->sortByDesc('turnover_rate')->values();

        return response()->json([
            'products' => $sorted,
            'summary' => [
                'fast_movers' => $sorted->where('velocity', 'fast')->count(),
                'medium_movers' => $sorted->where('velocity', 'medium')->count(),
                'slow_movers' => $sorted->where('velocity', 'slow')->count(),
                'total_analyzed' => $sorted->count(),
            ],
        ]);
    }

    /**
     * Reorder recommendations
     */
    public function reorderRecommendations(Request $request)
    {
        $products = Product::where('is_active', true)
            ->where(function($query) {
                // Products at or below reorder point (using actual DB columns)
                $query->whereRaw('(quantity_on_hand - quantity_committed) <= reorder_point')
                    // Or products below minimum
                    ->orWhereRaw('(quantity_on_hand - quantity_committed) <= minimum_quantity');
            })
            ->with(['category', 'supplier'])
            ->get()
            ->map(function ($product) {
                return $this->enrichProductData($product, true);
            })
            ->sortByDesc('priority_score')
            ->values();

        return response()->json([
            'recommendations' => $products,
            'summary' => [
                'items_to_reorder' => $products->count(),
                'total_order_value' => $products->sum('recommended_order_value'),
                'critical_items' => $products->where('status', 'critical')->count(),
            ],
        ]);
    }

    /**
     * Obsolete inventory detection
     */
    public function obsoleteInventory(Request $request)
    {
        $inactiveDays = $request->get('inactive_days', 180);
        $since = Carbon::now()->subDays($inactiveDays);

        // Eager load products with last shipment transaction in a single query
        $products = Product::where('is_active', true)
            ->where('is_discontinued', false)
            ->with([
                'category',
                'supplier',
                'transactions' => function($query) {
                    $query->where('type', 'shipment')
                        ->orderBy('transaction_date', 'desc')
                        ->limit(1);
                }
            ])
            ->withCount('usedInProducts') // Single query for BOM usage count
            ->get();

        $obsoleteCandidates = $products->filter(function ($product) use ($since) {
            // Use pre-loaded last shipment transaction (no additional query)
            $lastTransaction = $product->transactions->first();

            if (!$lastTransaction) {
                return true; // Never shipped = potentially obsolete
            }

            return $lastTransaction->transaction_date < $since;
        })->map(function ($product) {
            // Use pre-loaded last shipment (no additional query)
            $lastShipment = $product->transactions->first();

            $daysSinceLastUse = $lastShipment
                ? Carbon::now()->diffInDays($lastShipment->transaction_date)
                : 999;

            // Use pack-based quantities and pricing if applicable
            $displayQuantity = $product->hasPackSize() ? $product->quantity_on_hand_packs : $product->quantity_on_hand;
            $displayCost = $product->hasPackSize() ? $product->pack_cost : $product->unit_cost;

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'description' => $product->description,
                'category' => $product->category?->name,
                'on_hand' => $product->quantity_on_hand,
                'on_hand_display' => $displayQuantity,
                'unit_cost' => $product->unit_cost,
                'display_cost' => $displayCost,
                'total_value' => $displayQuantity * $displayCost,
                'last_shipment_date' => $lastShipment?->transaction_date,
                'days_since_last_use' => $daysSinceLastUse,
                'is_used_in_bom' => $product->used_in_products_count > 0, // Use pre-loaded count
                'pack_size' => $product->pack_size,
                'counting_unit' => $product->counting_unit,
            ];
        })->values();

        return response()->json([
            'obsolete_candidates' => $obsoleteCandidates,
            'summary' => [
                'total_items' => $obsoleteCandidates->count(),
                'total_value_at_risk' => $obsoleteCandidates->sum('total_value'),
                'used_in_bom' => $obsoleteCandidates->where('is_used_in_bom', true)->count(),
            ],
        ]);
    }

    /**
     * Usage analytics over time
     */
    public function usageAnalytics(Request $request)
    {
        $days = $request->get('days', 30);
        $since = Carbon::now()->subDays($days);

        $transactions = InventoryTransaction::where('transaction_date', '>=', $since)
            ->with('product')
            ->get();

        // Group by date
        $byDate = $transactions->groupBy(function ($transaction) {
            return Carbon::parse($transaction->transaction_date)->format('Y-m-d');
        })->map(function ($dayTransactions, $date) {
            return [
                'date' => $date,
                'receipts' => $dayTransactions->where('type', 'receipt')->sum('quantity'),
                'shipments' => abs($dayTransactions->where('type', 'shipment')->sum('quantity')),
                'adjustments' => $dayTransactions->where('type', 'adjustment')->count(),
                'cycle_counts' => $dayTransactions->where('type', 'cycle_count')->count(),
                'total_transactions' => $dayTransactions->count(),
            ];
        })->values();

        // Group by category
        $byCategory = $transactions->groupBy(function ($transaction) {
            return $transaction->product->category?->name ?? 'Uncategorized';
        })->map(function ($categoryTransactions, $category) {
            return [
                'category' => $category,
                'receipts' => $categoryTransactions->where('type', 'receipt')->sum('quantity'),
                'shipments' => abs($categoryTransactions->where('type', 'shipment')->sum('quantity')),
                'transaction_count' => $categoryTransactions->count(),
            ];
        })->values();

        return response()->json([
            'by_date' => $byDate,
            'by_category' => $byCategory,
            'summary' => [
                'total_receipts' => $transactions->where('type', 'receipt')->sum('quantity'),
                'total_shipments' => abs($transactions->where('type', 'shipment')->sum('quantity')),
                'total_adjustments' => $transactions->where('type', 'adjustment')->count(),
                'period_days' => $days,
            ],
        ]);
    }

    /**
     * Monthly inventory statement report
     * Shows beginning inventory, additions, deductions, and ending inventory for a month
     */
    public function monthlyInventoryStatement(Request $request)
    {
        // Get month and year from request or default to current month
        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        // Calculate date range for the month
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        // Load ALL products (active or inactive) to capture complete inventory value
        // Products are filtered later based on whether they have inventory/transactions
        $products = Product::with([
                'category',
                'supplier',
                'transactions' => function($query) use ($startDate, $endDate) {
                    $query->whereBetween('transaction_date', [$startDate, $endDate])
                        ->orderBy('transaction_date', 'asc');
                }
            ])
            ->get();

        $statementData = $products->map(function ($product) use ($startDate, $endDate) {
            // Use pre-loaded transactions (no additional query)
            $transactions = $product->transactions;

            // Calculate beginning inventory as of the end of the previous month
            // Find the last transaction before the start of this month
            $lastTransactionBeforeMonth = InventoryTransaction::where('product_id', $product->id)
                ->where('transaction_date', '<', $startDate)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            // Determine beginning inventory:
            // 1. If there's a transaction before this month, use its quantity_after
            // 2. If not, but there's a transaction in this month, use quantity_before of first transaction
            //    (this captures initial inventory for products in their first active month)
            // 3. If no transactions before or in month, check if product was created before this month
            //    and has a future transaction (captures initial inventory from products created earlier)
            // 4. If no transactions at all, use 0
            if ($lastTransactionBeforeMonth) {
                $beginningInventory = $lastTransactionBeforeMonth->quantity_after;
            } elseif ($transactions->isNotEmpty()) {
                $beginningInventory = $transactions->first()->quantity_before;
            } else {
                // Check if product was created before this month and has any future transactions
                $firstTransactionEver = InventoryTransaction::where('product_id', $product->id)
                    ->orderBy('transaction_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();

                if ($firstTransactionEver && $product->created_at < $startDate) {
                    // Product was created before this month and has a future transaction
                    // Use quantity_before of first transaction as beginning inventory
                    $beginningInventory = $firstTransactionEver->quantity_before;
                } elseif (!$firstTransactionEver && $product->created_at < $startDate && $product->quantity_on_hand > 0) {
                    // Product was created before this month, has no transactions at all,
                    // but has current inventory - use current quantity_on_hand
                    // This captures products created with initial inventory but no transaction history yet
                    $beginningInventory = $product->quantity_on_hand;
                } else {
                    $beginningInventory = 0;
                }
            }

            // Calculate additions (positive quantity changes)
            $receipts = $transactions->where('type', 'receipt')->sum('quantity');
            $returns = $transactions->where('type', 'return')->sum('quantity');
            $jobMaterialTransfers = $transactions->where('type', 'job_material_transfer')->sum('quantity');
            $positiveAdjustments = $transactions->where('type', 'adjustment')->filter(function($t) {
                return $t->quantity > 0;
            })->sum('quantity');

            // Calculate deductions (negative quantity changes)
            $shipments = abs($transactions->where('type', 'shipment')->sum('quantity'));
            $jobIssues = abs($transactions->whereIn('type', ['job_issue', 'fulfillment'])->sum('quantity'));
            $issues = abs($transactions->where('type', 'issue')->sum('quantity'));
            $negativeAdjustments = abs($transactions->where('type', 'adjustment')->filter(function($t) {
                return $t->quantity < 0;
            })->sum('quantity'));

            // Calculate totals
            $totalAdditions = $receipts + $returns + $jobMaterialTransfers + $positiveAdjustments;
            $totalDeductions = $shipments + $jobIssues + $issues + $negativeAdjustments;

            // Calculate ending inventory
            // For current/future months: use actual quantity_on_hand (source of truth)
            // For past months: use transaction history for month-to-month consistency
            $now = Carbon::now()->endOfDay();

            if ($endDate >= $now) {
                // Viewing current or future month - use actual current inventory
                $endingInventory = $product->quantity_on_hand;
            } else {
                // Viewing past month - use transaction history
                $lastTransactionInOrBeforeMonth = InventoryTransaction::where('product_id', $product->id)
                    ->where('transaction_date', '<=', $endDate)
                    ->orderBy('transaction_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastTransactionInOrBeforeMonth) {
                    $endingInventory = $lastTransactionInOrBeforeMonth->quantity_after;
                } elseif ($beginningInventory > 0) {
                    // No transactions in or before this month, but had beginning inventory
                    // Ending = beginning (no changes during the month)
                    $endingInventory = $beginningInventory;
                } else {
                    // No transactions in/before month, and no beginning inventory
                    // Check if product was created during/before this month and has future transactions
                    // This captures initial inventory for products created in this month with delayed first transaction
                    $firstTransactionEver = InventoryTransaction::where('product_id', $product->id)
                        ->orderBy('transaction_date', 'asc')
                        ->orderBy('id', 'asc')
                        ->first();

                    if ($firstTransactionEver && $product->created_at <= $endDate) {
                        // Product was created during/before this month and has a future transaction
                        // Use quantity_before of first transaction as ending inventory
                        $endingInventory = $firstTransactionEver->quantity_before;
                    } elseif (!$firstTransactionEver && $product->created_at <= $endDate && $product->quantity_on_hand > 0) {
                        // Product was created before/during this month, has no transactions at all,
                        // but has current inventory - use current quantity_on_hand
                        // This captures products created with initial inventory but no transaction history yet
                        $endingInventory = $product->quantity_on_hand;
                    } else {
                        $endingInventory = 0;
                    }
                }
            }

            // Net change
            $netChange = $endingInventory - $beginningInventory;

            // Use pack-based quantities and pricing if applicable
            $displayQuantity = $product->hasPackSize() ? $product->quantity_on_hand_packs : $product->quantity_on_hand;
            $displayCost = $product->hasPackSize() ? $product->pack_cost : $product->unit_cost;

            // Convert all quantities to packs if applicable
            $beginningDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($beginningInventory) : $beginningInventory;
            $endingDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($endingInventory) : $endingInventory;
            $receiptsDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($receipts) : $receipts;
            $returnsDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($returns) : $returns;
            $jobMaterialTransfersDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($jobMaterialTransfers) : $jobMaterialTransfers;
            $shipmentsDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($shipments) : $shipments;
            $jobIssuesDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($jobIssues) : $jobIssues;
            $issuesDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($issues) : $issues;
            $positiveAdjustmentsDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($positiveAdjustments) : $positiveAdjustments;
            $negativeAdjustmentsDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($negativeAdjustments) : $negativeAdjustments;
            $totalAdditionsDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($totalAdditions) : $totalAdditions;
            $totalDeductionsDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($totalDeductions) : $totalDeductions;
            $netChangeDisplay = $product->hasPackSize() ? $product->eachesToFullPacks($netChange) : $netChange;

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'description' => $product->description,
                'category' => $product->category?->name,
                'supplier' => $product->supplier?->name,

                // Beginning inventory
                'beginning_inventory' => $beginningInventory,
                'beginning_inventory_display' => $beginningDisplay,

                // Additions breakdown
                'receipts' => $receipts,
                'receipts_display' => $receiptsDisplay,
                'returns' => $returns,
                'returns_display' => $returnsDisplay,
                'job_material_transfers' => $jobMaterialTransfers,
                'job_material_transfers_display' => $jobMaterialTransfersDisplay,
                'positive_adjustments' => $positiveAdjustments,
                'positive_adjustments_display' => $positiveAdjustmentsDisplay,
                'total_additions' => $totalAdditions,
                'total_additions_display' => $totalAdditionsDisplay,

                // Deductions breakdown
                'shipments' => $shipments,
                'shipments_display' => $shipmentsDisplay,
                'job_issues' => $jobIssues,
                'job_issues_display' => $jobIssuesDisplay,
                'issues' => $issues,
                'issues_display' => $issuesDisplay,
                'negative_adjustments' => $negativeAdjustments,
                'negative_adjustments_display' => $negativeAdjustmentsDisplay,
                'total_deductions' => $totalDeductions,
                'total_deductions_display' => $totalDeductionsDisplay,

                // Ending inventory
                'ending_inventory' => $endingInventory,
                'ending_inventory_display' => $endingDisplay,
                'net_change' => $netChange,
                'net_change_display' => $netChangeDisplay,

                // Pricing and totals
                'unit_cost' => $product->unit_cost,
                'display_cost' => $displayCost,
                'beginning_value' => $beginningDisplay * $displayCost,
                'ending_value' => $endingDisplay * $displayCost,
                'value_change' => ($endingDisplay * $displayCost) - ($beginningDisplay * $displayCost),

                // Pack info
                'pack_size' => $product->pack_size,
                'has_pack_size' => $product->hasPackSize(),
                'counting_unit' => $product->counting_unit,

                // Transaction count
                'transaction_count' => $transactions->count(),
            ];
        })
        // First, filter to products with inventory or activity to ensure complete financial calculations
        ->filter(function($item) {
            return $item['beginning_inventory'] > 0 || $item['ending_inventory'] > 0 || $item['transaction_count'] > 0;
        })
        ->values();

        // Calculate summary statistics from ALL products (includes products with inventory but no transactions)
        // This ensures accurate financial totals including static inventory
        $summary = [
            'month' => $startDate->format('F Y'),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'products_count' => $statementData->count(),
            'products_with_transactions' => $statementData->where('transaction_count', '>', 0)->count(),
            'total_beginning_value' => $statementData->sum('beginning_value'),
            'total_ending_value' => $statementData->sum('ending_value'),
            'total_value_change' => $statementData->sum('value_change'),
            'total_receipts' => $statementData->sum('receipts_display'),
            'total_shipments' => $statementData->sum('shipments_display'),
            'total_job_material_transfers' => $statementData->sum('job_material_transfers_display'),
            'total_job_issues' => $statementData->sum('job_issues_display'),
            'total_issues' => $statementData->sum('issues_display'),
            'total_returns' => $statementData->sum('returns_display'),
            'total_adjustments_positive' => $statementData->sum('positive_adjustments_display'),
            'total_adjustments_negative' => $statementData->sum('negative_adjustments_display'),
        ];

        // Filter displayed list to only show products with transactions during the month
        // Financial totals still include all inventory
        $statementData = $statementData->filter(function($item) {
            return $item['transaction_count'] > 0;
        })->values();

        return response()->json([
            'statement' => $statementData,
            'summary' => $summary,
        ]);
    }

    /**
     * Export report to CSV
     */
    public function exportReport(Request $request)
    {
        $reportType = $request->get('type', 'low_stock');

        switch ($reportType) {
            case 'low_stock':
                return $this->exportLowStock();
            case 'committed':
                return $this->exportCommitted();
            case 'velocity':
                return $this->exportVelocity($request);
            case 'reorder':
                return $this->exportReorder();
            case 'obsolete':
                return $this->exportObsolete($request);
            case 'monthly_statement':
                return $this->exportMonthlyStatement($request);
            default:
                return response()->json(['message' => 'Invalid report type'], 400);
        }
    }

    // Helper methods
    private function enrichProductData($product, $includeRecommendations = false)
    {
        // Use pack-based pricing and quantities when available
        $displayQuantity = $product->hasPackSize() ? $product->quantity_on_hand_packs : $product->quantity_on_hand;
        $displayCost = $product->hasPackSize() ? $product->pack_cost : $product->unit_cost;
        $displayPrice = $product->hasPackSize() ? $product->pack_price : $product->unit_price;

        // Convert reorder_point and minimum to packs if applicable
        $reorderPointDisplay = $product->hasPackSize() ? $product->eachesToPacksNeeded($product->reorder_point) : $product->reorder_point;
        $minimumDisplay = $product->hasPackSize() ? $product->eachesToPacksNeeded($product->minimum_quantity) : $product->minimum_quantity;

        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'description' => $product->description,
            'category' => $product->category?->name,
            'supplier' => $product->supplier?->name,
            'on_hand' => $product->quantity_on_hand,
            'on_hand_packs' => $product->quantity_on_hand_packs,
            'on_hand_display' => $displayQuantity,
            'committed' => $product->quantity_committed,
            'committed_packs' => $product->quantity_committed_packs,
            'committed_display' => $product->hasPackSize() ? $product->quantity_committed_packs : $product->quantity_committed,
            'available' => $product->quantity_available,
            'available_packs' => $product->quantity_available_packs,
            'available_display' => $product->hasPackSize() ? $product->quantity_available_packs : $product->quantity_available,
            'minimum' => $product->minimum_quantity,
            'minimum_display' => $minimumDisplay,
            'reorder_point' => $product->reorder_point,
            'reorder_point_display' => $reorderPointDisplay,
            'unit_cost' => $product->unit_cost,
            'unit_price' => $product->unit_price,
            'pack_cost' => $product->pack_cost,
            'pack_price' => $product->pack_price,
            'display_cost' => $displayCost,
            'display_price' => $displayPrice,
            'total_value' => $displayQuantity * $displayCost,
            'status' => $product->status,
            'lead_time_days' => $product->lead_time_days,
            'pack_size' => $product->pack_size,
            'has_pack_size' => $product->hasPackSize(),
            'counting_unit' => $product->counting_unit,
        ];

        if ($includeRecommendations) {
            $shortage = max(0, $product->reorder_point - $product->quantity_available);
            $recommendedQty = $shortage + ($product->safety_stock ?? 0);
            $target = $product->reorder_point + $shortage;

            // Convert shortage, recommended qty, and target to packs if applicable
            $shortageDisplay = $product->hasPackSize() ? $product->eachesToPacksNeeded($shortage) : $shortage;
            $recommendedQtyDisplay = $product->hasPackSize() ? $product->eachesToPacksNeeded($recommendedQty) : $recommendedQty;
            $targetDisplay = $product->hasPackSize() ? $product->eachesToPacksNeeded($target) : $target;

            $data['shortage'] = $shortage;
            $data['shortage_display'] = $shortageDisplay;
            $data['target'] = $target;
            $data['target_display'] = $targetDisplay;
            $data['recommended_order_qty'] = $recommendedQty;
            $data['recommended_order_qty_display'] = $recommendedQtyDisplay;
            $data['recommended_order_value'] = $recommendedQtyDisplay * $displayCost;
            $data['days_until_stockout'] = $product->days_until_stockout;

            // Priority score (higher = more urgent)
            $priorityScore = 0;
            if ($product->status === 'critical') $priorityScore += 100;
            elseif ($product->status === 'low_stock') $priorityScore += 50;

            if ($product->days_until_stockout && $product->days_until_stockout < 7) {
                $priorityScore += 50;
            }

            $data['priority_score'] = $priorityScore;
        }

        return $data;
    }

    private function exportLowStock()
    {
        $data = $this->lowStockReport(request());
        $items = collect($data->original['low_stock'])->concat($data->original['critical']);

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['supplier'] ?? '',
                $item['on_hand_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['available_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['minimum'] ?? '',
                $item['status'],
                number_format($item['total_value'], 2),
            ];
        });

        return $this->generateCSV($csvData, 'low_stock_report', [
            'SKU', 'Description', 'Category', 'Supplier', 'On Hand', 'Available',
            'Minimum', 'Status', 'Value'
        ]);
    }

    private function generateCSV($data, $filename, $headers)
    {
        $filename = $filename . '_' . date('Y-m-d_His') . '.csv';

        $callback = function() use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($data as $row) {
                fputcsv($file, array_values((array)$row));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function exportCommitted()
    {
        $data = $this->committedPartsReport(request());
        $items = collect($data->original['committed_products']);

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['supplier'] ?? '',
                $item['on_hand_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['committed_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['available_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                number_format($item['display_cost'], 2),
                number_format($item['total_value'], 2),
            ];
        });

        return $this->generateCSV($csvData, 'committed_parts_report', [
            'SKU', 'Description', 'Category', 'Supplier', 'On Hand', 'Committed',
            'Available', 'Unit Cost', 'Total Value'
        ]);
    }

    private function exportVelocity($request)
    {
        $data = $this->stockVelocityAnalysis($request);
        $items = collect($data->original['products']);

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['on_hand_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['available_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['receipts'],
                $item['shipments'],
                $item['turnover_rate'] . '%',
                ucfirst($item['velocity']),
                $item['days_until_stockout'] ?? 'N/A',
            ];
        });

        return $this->generateCSV($csvData, 'velocity_analysis_report', [
            'SKU', 'Description', 'Category', 'On Hand', 'Available', 'Receipts',
            'Shipments', 'Turnover Rate', 'Velocity', 'Days Until Stockout'
        ]);
    }

    private function exportReorder()
    {
        $data = $this->reorderRecommendations(request());
        $items = collect($data->original['recommendations']);

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['supplier'] ?? '',
                $item['available_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['reorder_point_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['shortage_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['recommended_order_qty_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                number_format($item['recommended_order_value'], 2),
                ucfirst($item['status']),
                $item['lead_time_days'] ?? '',
            ];
        });

        return $this->generateCSV($csvData, 'reorder_recommendations_report', [
            'SKU', 'Description', 'Category', 'Supplier', 'Available', 'Reorder Point',
            'Shortage', 'Recommended Qty', 'Recommended Value', 'Status', 'Lead Time Days'
        ]);
    }

    private function exportObsolete($request)
    {
        $data = $this->obsoleteInventory($request);
        $items = collect($data->original['obsolete_candidates']);

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['on_hand_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                number_format($item['display_cost'], 2),
                number_format($item['total_value'], 2),
                $item['last_shipment_date'] ?? 'Never',
                $item['days_since_last_use'] ?? 'N/A',
                $item['is_used_in_bom'] ? 'Yes' : 'No',
            ];
        });

        return $this->generateCSV($csvData, 'obsolete_inventory_report', [
            'SKU', 'Description', 'Category', 'On Hand', 'Unit Cost', 'Total Value',
            'Last Shipment Date', 'Days Since Last Use', 'Used in BOM'
        ]);
    }

    /**
     * Generate PDF for Low Stock report
     */
    public function lowStockPdf(Request $request)
    {
        $data = $this->lowStockReport($request);
        $reportData = $data->original;

        $products = collect($reportData['low_stock'])->merge($reportData['critical']);
        $summary = [
            'critical_stock' => $reportData['summary']['critical_count'],
            'low_stock' => $reportData['summary']['low_stock_count'],
            'total_items' => $reportData['summary']['total_affected'],
            'total_value' => $reportData['summary']['estimated_value_at_risk'],
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.low-stock-report', [
            'products' => $products,
            'summary' => $summary
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('low-stock-report-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Committed Parts report
     */
    public function committedPartsPdf(Request $request)
    {
        $data = $this->committedPartsReport($request);
        $reportData = $data->original;

        // Get unique active job count
        $activeJobs = collect($reportData['committed_products'])
            ->flatMap(function($product) {
                return $product['reservations'] ?? [];
            })
            ->pluck('id')
            ->unique()
            ->count();

        $summary = [
            'unique_parts' => $reportData['summary']['total_products'],
            'total_committed_quantity' => $reportData['summary']['total_quantity_committed'],
            'total_committed_value' => $reportData['summary']['total_value_committed'],
            'active_jobs' => $activeJobs,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.committed-parts-report', [
            'products' => $reportData['committed_products'],
            'summary' => $summary
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('committed-parts-report-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Velocity Analysis report
     */
    public function velocityAnalysisPdf(Request $request)
    {
        $data = $this->stockVelocityAnalysis($request);
        $reportData = $data->original;
        $days = $request->get('days', 90);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.velocity-analysis-report', [
            'products' => $reportData['products'],
            'summary' => $reportData['summary'],
            'days' => $days
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('velocity-analysis-report-' . $days . 'd-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Reorder Recommendations report
     */
    public function reorderRecommendationsPdf(Request $request)
    {
        $data = $this->reorderRecommendations($request);
        $reportData = $data->original;

        $recommendations = collect($reportData['recommendations']);

        // Calculate priority counts for summary
        $highPriority = $recommendations->filter(function($rec) {
            return $rec['priority_score'] >= 100;
        })->count();

        $mediumPriority = $recommendations->filter(function($rec) {
            return $rec['priority_score'] >= 50 && $rec['priority_score'] < 100;
        })->count();

        $summary = [
            'total_items' => $reportData['summary']['items_to_reorder'],
            'high_priority' => $highPriority,
            'medium_priority' => $mediumPriority,
            'total_estimated_value' => $reportData['summary']['total_order_value'],
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.reorder-recommendations-report', [
            'recommendations' => $recommendations,
            'summary' => $summary
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('reorder-recommendations-report-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Obsolete Inventory report
     */
    public function obsoleteInventoryPdf(Request $request)
    {
        $data = $this->obsoleteInventory($request);
        $reportData = $data->original;
        $inactive_days = $request->get('inactive_days', 90);

        $candidates = collect($reportData['obsolete_candidates'])->map(function($candidate) {
            return array_merge($candidate, [
                'last_activity_date' => $candidate['last_shipment_date'],
                'days_since_activity' => $candidate['days_since_last_use'],
            ]);
        });

        $totalQuantity = $candidates->sum('on_hand');
        $avgDaysInactive = $candidates->count() > 0
            ? round($candidates->avg('days_since_last_use'))
            : 0;

        $summary = [
            'obsolete_count' => $reportData['summary']['total_items'],
            'total_quantity' => $totalQuantity,
            'total_value' => $reportData['summary']['total_value_at_risk'],
            'average_days_inactive' => $avgDaysInactive,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.obsolete-inventory-report', [
            'candidates' => $candidates,
            'summary' => $summary,
            'inactive_days' => $inactive_days
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('obsolete-inventory-report-' . $inactive_days . 'd-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Usage Analytics report
     */
    public function usageAnalyticsPdf(Request $request)
    {
        $data = $this->usageAnalytics($request);
        $reportData = $data->original;
        $days = $request->get('days', 30);
        $since = Carbon::now()->subDays($days);

        // Get all transactions for more detailed breakdown
        $allTransactions = InventoryTransaction::where('transaction_date', '>=', $since)
            ->with('product.category')
            ->get();

        // Transform by_date to include issues (negative quantity transactions)
        $byDate = collect($reportData['by_date'])->mapWithKeys(function($dayData) use ($allTransactions) {
            $date = $dayData['date'];
            $dayTrans = $allTransactions->filter(function($t) use ($date) {
                return Carbon::parse($t->transaction_date)->format('Y-m-d') === $date;
            });

            return [$date => [
                'total' => $dayTrans->count(),
                'receipts' => $dayTrans->where('type', 'receipt')->sum('quantity'),
                'shipments' => abs($dayTrans->where('type', 'shipment')->sum('quantity')),
                'issues' => abs($dayTrans->whereIn('type', ['issue', 'job_issue'])->sum('quantity')),
                'adjustments' => $dayTrans->where('type', 'adjustment')->count(),
            ]];
        });

        // Transform by_category to include product_count and percentage
        $totalTransactions = $allTransactions->count();
        $byCategory = $allTransactions->groupBy(function($t) {
            return $t->product->category?->name ?? 'Uncategorized';
        })->map(function($catTrans, $category) use ($totalTransactions) {
            return [
                'transaction_count' => $catTrans->count(),
                'product_count' => $catTrans->pluck('product_id')->unique()->count(),
                'percentage' => $totalTransactions > 0
                    ? round(($catTrans->count() / $totalTransactions) * 100, 1)
                    : 0,
            ];
        });

        // Build summary
        $uniqueProducts = $allTransactions->pluck('product_id')->unique()->count();
        $activeCategories = $allTransactions->pluck('product.category.id')->filter()->unique()->count();

        $summary = [
            'total_transactions' => $totalTransactions,
            'unique_products' => $uniqueProducts,
            'daily_average' => $days > 0 ? round($totalTransactions / $days, 1) : 0,
            'active_categories' => $activeCategories,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.usage-analytics-report', [
            'by_date' => $byDate,
            'by_category' => $byCategory,
            'summary' => $summary,
            'days' => $days
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('usage-analytics-report-' . $days . 'd-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Monthly Inventory Statement report
     */
    public function monthlyInventoryStatementPdf(Request $request)
    {
        $data = $this->monthlyInventoryStatement($request);
        $reportData = $data->original;

        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.monthly-inventory-statement-report', [
            'statement' => $reportData['statement'],
            'summary' => $reportData['summary']
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('monthly-inventory-statement-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf');
    }

    private function exportMonthlyStatement($request)
    {
        $data = $this->monthlyInventoryStatement($request);
        $items = collect($data->original['statement']);

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['beginning_inventory_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['receipts_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['returns_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['job_material_transfers_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['positive_adjustments_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['total_additions_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['shipments_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['job_issues_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['issues_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['negative_adjustments_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['total_deductions_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['ending_inventory_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['net_change_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                number_format($item['display_cost'], 2),
                number_format($item['beginning_value'], 2),
                number_format($item['ending_value'], 2),
                number_format($item['value_change'], 2),
            ];
        });

        $month = $data->original['summary']['month'];
        return $this->generateCSV($csvData, 'monthly_inventory_statement_' . str_replace(' ', '_', $month), [
            'SKU', 'Description', 'Category',
            'Beginning Inventory', 'Receipts', 'Returns', 'Job Material Transfers', 'Positive Adjustments', 'Total Additions',
            'Shipments', 'Job Issues', 'Issues', 'Negative Adjustments', 'Total Deductions',
            'Ending Inventory', 'Net Change',
            'Unit Cost', 'Beginning Value', 'Ending Value', 'Value Change'
        ]);
    }

    /**
     * Export inventory report as CSV
     */
    public function exportInventoryCsv(Request $request)
    {
        $query = Product::query()->with('supplier');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $products = $query->get();

        // Get committed quantities from fulfillment system
        $committedByProduct = DB::table('job_reservation_items as ri')
            ->join('job_reservations as r', 'ri.reservation_id', '=', 'r.id')
            ->whereIn('r.status', ['active', 'in_progress', 'on_hold'])
            ->whereNull('r.deleted_at')
            ->select('ri.product_id', DB::raw('SUM(ri.committed_qty) as committed_qty'))
            ->groupBy('ri.product_id')
            ->pluck('committed_qty', 'product_id')
            ->toArray();

        $filename = 'inventory_report_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($products, $committedByProduct) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Part Number',
                'Finish',
                'Description',
                'Unit Cost',
                'Net Cost',
                'Pack Size',
                'Qty On Hand',
                'Qty Committed',
                'Qty Available',
                'Unit of Measure',
                'Available Value (List)',
                'Available Value (Net)',
            ]);

            foreach ($products as $product) {
                $committedQty = $committedByProduct[$product->id] ?? 0;

                // Calculate pack-based quantities if pack_size > 1
                if ($product->pack_size && $product->pack_size > 1) {
                    $onHandQty = floor($product->quantity_on_hand / $product->pack_size);
                    $committedQtyDisplay = ceil($committedQty / $product->pack_size);
                    $availableQty = $onHandQty - $committedQtyDisplay; // Allow negative for over-commitment
                } else {
                    $onHandQty = $product->quantity_on_hand;
                    $committedQtyDisplay = $committedQty;
                    $availableQty = $product->quantity_on_hand - $committedQty; // Allow negative for over-commitment
                }

                // Calculate available values based on pack quantities displayed
                $availableValueList = $product->unit_cost * $availableQty;
                $availableValueNet = ($product->net_cost ?? 0) * $availableQty;

                fputcsv($file, [
                    $product->part_number ?? '',
                    $product->finish ?? '',
                    $product->description,
                    number_format($product->unit_cost, 2, '.', ''),
                    number_format($product->net_cost ?? 0, 2, '.', ''),
                    $product->pack_size ?? 1,
                    $onHandQty,
                    $committedQtyDisplay,
                    $availableQty,
                    $product->unit_of_measure,
                    number_format($availableValueList, 2, '.', ''),
                    number_format($availableValueNet, 2, '.', ''),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get Inventory Report data as JSON
     */
    public function inventoryReportData(Request $request)
    {
        $query = Product::query()->with('supplier');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $products = $query->get();

        // Get committed quantities from fulfillment system
        $committedByProduct = DB::table('job_reservation_items as ri')
            ->join('job_reservations as r', 'ri.reservation_id', '=', 'r.id')
            ->whereIn('r.status', ['active', 'in_progress', 'on_hold'])
            ->whereNull('r.deleted_at')
            ->select('ri.product_id', DB::raw('SUM(ri.committed_qty) as committed_qty'))
            ->groupBy('ri.product_id')
            ->pluck('committed_qty', 'product_id')
            ->toArray();

        $inventoryData = $products->map(function ($product) use ($committedByProduct) {
            $committedQty = $committedByProduct[$product->id] ?? 0;

            // Calculate pack-based quantities if pack_size > 1
            if ($product->pack_size && $product->pack_size > 1) {
                $onHandQty = floor($product->quantity_on_hand / $product->pack_size);
                $committedQtyDisplay = ceil($committedQty / $product->pack_size);
                $availableQty = max(0, $onHandQty - $committedQtyDisplay);
            } else {
                $onHandQty = $product->quantity_on_hand;
                $committedQtyDisplay = $committedQty;
                $availableQty = max(0, $product->quantity_on_hand - $committedQty);
            }

            // Calculate available values
            $availableValueList = $product->unit_cost * $availableQty;
            $availableValueNet = ($product->net_cost ?? 0) * $availableQty;

            return [
                'part_number' => $product->part_number ?? '',
                'finish' => $product->finish ?? '',
                'description' => $product->description,
                'unit_cost' => $product->unit_cost,
                'net_cost' => $product->net_cost ?? 0,
                'pack_size' => $product->pack_size ?? 1,
                'on_hand' => $onHandQty,
                'committed' => $committedQtyDisplay,
                'available' => $availableQty,
                'unit_of_measure' => $product->unit_of_measure,
                'available_value_list' => $availableValueList,
                'available_value_net' => $availableValueNet,
            ];
        });

        $summary = [
            'total_products' => $inventoryData->count(),
            'total_value_list' => $inventoryData->sum('available_value_list'),
            'total_value_net' => $inventoryData->sum('available_value_net'),
            'total_available_qty' => $inventoryData->sum('available'),
        ];

        return response()->json([
            'products' => $inventoryData->values(),
            'summary' => $summary
        ]);
    }

    /**
     * Generate PDF for Inventory Report
     */
    public function inventoryReportPdf(Request $request)
    {
        $query = Product::query()->with('supplier');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $products = $query->get();

        // Get committed quantities from fulfillment system
        $committedByProduct = DB::table('job_reservation_items as ri')
            ->join('job_reservations as r', 'ri.reservation_id', '=', 'r.id')
            ->whereIn('r.status', ['active', 'in_progress', 'on_hold'])
            ->whereNull('r.deleted_at')
            ->select('ri.product_id', DB::raw('SUM(ri.committed_qty) as committed_qty'))
            ->groupBy('ri.product_id')
            ->pluck('committed_qty', 'product_id')
            ->toArray();

        $inventoryData = $products->map(function ($product) use ($committedByProduct) {
            $committedQty = $committedByProduct[$product->id] ?? 0;

            // Calculate pack-based quantities if pack_size > 1
            if ($product->pack_size && $product->pack_size > 1) {
                $onHandQty = floor($product->quantity_on_hand / $product->pack_size);
                $committedQtyDisplay = ceil($committedQty / $product->pack_size);
                $availableQty = max(0, $onHandQty - $committedQtyDisplay);
            } else {
                $onHandQty = $product->quantity_on_hand;
                $committedQtyDisplay = $committedQty;
                $availableQty = max(0, $product->quantity_on_hand - $committedQty);
            }

            // Calculate available values
            $availableValueList = $product->unit_cost * $availableQty;
            $availableValueNet = ($product->net_cost ?? 0) * $availableQty;

            return [
                'part_number' => $product->part_number ?? '',
                'finish' => $product->finish ?? '',
                'description' => $product->description,
                'unit_cost' => $product->unit_cost,
                'net_cost' => $product->net_cost ?? 0,
                'pack_size' => $product->pack_size ?? 1,
                'on_hand' => $onHandQty,
                'committed' => $committedQtyDisplay,
                'available' => $availableQty,
                'unit_of_measure' => $product->unit_of_measure,
                'available_value_list' => $availableValueList,
                'available_value_net' => $availableValueNet,
            ];
        });

        $summary = [
            'total_products' => $inventoryData->count(),
            'total_value_list' => $inventoryData->sum('available_value_list'),
            'total_value_net' => $inventoryData->sum('available_value_net'),
            'total_available_qty' => $inventoryData->sum('available'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.inventory-report', [
            'products' => $inventoryData,
            'summary' => $summary
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('inventory-report-' . date('Y-m-d') . '.pdf');
    }
}
