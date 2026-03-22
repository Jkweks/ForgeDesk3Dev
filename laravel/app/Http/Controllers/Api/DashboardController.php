<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get committed quantities grouped by product ID (single query, reused everywhere)
     */
    private function getCommittedByProduct(): array
    {
        return DB::table('job_reservation_items as ri')
            ->join('job_reservations as r', 'ri.reservation_id', '=', 'r.id')
            ->whereIn('r.status', ['active', 'in_progress', 'on_hold'])
            ->whereNull('r.deleted_at')
            ->select('ri.product_id', DB::raw('SUM(ri.committed_qty) as committed_qty'))
            ->groupBy('ri.product_id')
            ->pluck('committed_qty', 'product_id')
            ->toArray();
    }

    /**
     * Calculate total committed units in packs via a single SQL aggregate
     */
    private function calcUnitsCommitted($categoryId = null): float
    {
        $query = DB::table('job_reservation_items as ri')
            ->join('job_reservations as r', 'ri.reservation_id', '=', 'r.id')
            ->join('products as p', 'ri.product_id', '=', 'p.id')
            ->whereIn('r.status', ['active', 'in_progress', 'on_hold'])
            ->whereNull('r.deleted_at')
            ->where('p.is_active', true);

        if ($categoryId) {
            $query->whereExists(function ($sub) use ($categoryId) {
                $sub->select(DB::raw(1))
                    ->from('category_product as cp')
                    ->whereColumn('cp.product_id', 'p.id')
                    ->where('cp.category_id', $categoryId);
            });
        }

        return (float) ($query->selectRaw(
            'SUM(CASE WHEN p.pack_size > 1 THEN CEIL(ri.committed_qty / p.pack_size) ELSE ri.committed_qty END) as total'
        )->value('total') ?? 0);
    }

    public function index(Request $request)
    {
        $categoryId = $request->get('category_id');
        $perPage    = $request->get('per_page', 50);
        $sortBy     = $request->get('sort_by', 'sku');
        $sortDir    = $request->get('sort_dir', 'asc');
        $search     = $request->get('search');

        // Validate sort column to prevent SQL injection
        $allowedSortColumns = ['sku', 'description', 'quantity_on_hand', 'quantity_committed', 'quantity_available', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'sku';
        }
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        // Single committed-by-product query — reused for both stats and row enrichment
        $committedByProduct = $this->getCommittedByProduct();

        // Base query for stats (apply filters once)
        $statsQuery = Product::where('is_active', true);
        if ($categoryId) {
            $statsQuery->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }
        if ($search) {
            $searchLower = strtolower($search);
            $statsQuery->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(status) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        // ONE query for all count stats + on-hand sum (replaces 4 separate queries)
        $statRow = (clone $statsQuery)->selectRaw("
            COUNT(*) as skus_tracked,
            SUM(CASE WHEN pack_size > 1 THEN FLOOR(quantity_on_hand / pack_size) ELSE COALESCE(quantity_on_hand, 0) END) as units_on_hand,
            SUM(CASE WHEN status IN ('low_stock', 'critical') THEN 1 ELSE 0 END) as low_stock_alerts,
            SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical_count
        ")->first();

        $unitsOnHand   = (float) ($statRow->units_on_hand ?? 0);
        $unitsCommitted = $this->calcUnitsCommitted($categoryId);

        $stats = [
            'skus_tracked'    => (int) ($statRow->skus_tracked ?? 0),
            'units_on_hand'   => $unitsOnHand,
            'units_committed' => $unitsCommitted,
            'units_available' => max(0, $unitsOnHand - $unitsCommitted),
            'low_stock_alerts' => (int) ($statRow->low_stock_alerts ?? 0),
            'critical_count'  => (int) ($statRow->critical_count ?? 0),
            'pending_orders'  => Order::whereIn('status', ['pending', 'processing'])->count(),
        ];

        $inventoryQuery = Product::with(['inventoryLocations', 'categories'])
            ->where('is_active', true);

        if ($categoryId) {
            $inventoryQuery->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        if ($search) {
            $searchLower = strtolower($search);
            $inventoryQuery->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(status) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        // Enrich callback — shared between both branches
        $enrich = function ($product) use ($committedByProduct) {
            $committedQty  = $committedByProduct[$product->id] ?? 0;
            $committedPacks = $product->eachesToPacksNeeded($committedQty);
            $onHandPacks   = $product->eachesToFullPacks($product->quantity_on_hand);

            $product->quantity_committed       = $committedQty;
            $product->quantity_committed_packs  = $committedPacks;
            $product->quantity_available        = $product->quantity_on_hand - $committedQty;
            $product->quantity_available_packs  = max(0, $onHandPacks - $committedPacks);
            return $product;
        };

        // Handle sorting - for calculated fields, use a DB-level correlated subquery
        if (in_array($sortBy, ['quantity_committed', 'quantity_available'])) {
            $committedSubquery = "COALESCE((
                SELECT SUM(ri.committed_qty)
                FROM job_reservation_items ri
                INNER JOIN job_reservations r ON ri.reservation_id = r.id
                WHERE ri.product_id = products.id
                  AND r.status IN ('active', 'in_progress', 'on_hold')
                  AND r.deleted_at IS NULL
            ), 0)";

            $inventoryQuery->selectRaw("products.*, {$committedSubquery} as committed_qty_computed");

            if ($sortBy === 'quantity_committed') {
                $inventoryQuery->orderByRaw("committed_qty_computed {$sortDir}");
            } else {
                $inventoryQuery->orderByRaw("(products.quantity_on_hand - committed_qty_computed) {$sortDir}");
            }
        } else {
            $inventoryQuery->orderBy($sortBy, $sortDir);
        }

        $inventory = $inventoryQuery->paginate($perPage);
        $inventory->getCollection()->transform($enrich);

        return response()->json([
            'stats'     => $stats,
            'inventory' => $inventory,
        ]);
    }

    public function inventoryByStatus(Request $request, $status)
    {
        $categoryId = $request->get('category_id');
        $perPage    = $request->get('per_page', 50);
        $sortBy     = $request->get('sort_by', 'sku');
        $sortDir    = $request->get('sort_dir', 'asc');
        $search     = $request->get('search');

        $allowedSortColumns = ['sku', 'description', 'quantity_on_hand', 'quantity_committed', 'quantity_available', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'sku';
        }
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        $committedByProduct = $this->getCommittedByProduct();

        $query = Product::where('status', $status)
            ->where('is_active', true)
            ->when($categoryId, function ($q) use ($categoryId) {
                return $q->whereHas('categories', function ($sq) use ($categoryId) {
                    $sq->where('categories.id', $categoryId);
                });
            })
            ->when($search, function ($q) use ($search) {
                $searchLower = strtolower($search);
                return $q->where(function ($sq) use ($searchLower) {
                    $sq->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                       ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                       ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"])
                       ->orWhereRaw('LOWER(status) LIKE ?', ["%{$searchLower}%"]);
                });
            })
            ->with('categories');

        $enrich = function ($product) use ($committedByProduct) {
            $committedQty  = $committedByProduct[$product->id] ?? 0;
            $committedPacks = $product->eachesToPacksNeeded($committedQty);
            $onHandPacks   = $product->eachesToFullPacks($product->quantity_on_hand);

            $product->quantity_committed       = $committedQty;
            $product->quantity_committed_packs  = $committedPacks;
            $product->quantity_available        = $product->quantity_on_hand - $committedQty;
            $product->quantity_available_packs  = max(0, $onHandPacks - $committedPacks);
            return $product;
        };

        if (in_array($sortBy, ['quantity_committed', 'quantity_available'])) {
            $committedSubquery = "COALESCE((
                SELECT SUM(ri.committed_qty)
                FROM job_reservation_items ri
                INNER JOIN job_reservations r ON ri.reservation_id = r.id
                WHERE ri.product_id = products.id
                  AND r.status IN ('active', 'in_progress', 'on_hold')
                  AND r.deleted_at IS NULL
            ), 0)";

            $query->selectRaw("products.*, {$committedSubquery} as committed_qty_computed");

            if ($sortBy === 'quantity_committed') {
                $query->orderByRaw("committed_qty_computed {$sortDir}");
            } else {
                $query->orderByRaw("(products.quantity_on_hand - committed_qty_computed) {$sortDir}");
            }
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $products = $query->paginate($perPage);
        $products->getCollection()->transform($enrich);

        return response()->json($products);
    }

    public function stats()
    {
        $statRow = Product::where('is_active', true)->selectRaw("
            COUNT(*) as skus_tracked,
            SUM(CASE WHEN pack_size > 1 THEN FLOOR(quantity_on_hand / pack_size) ELSE COALESCE(quantity_on_hand, 0) END) as units_on_hand,
            SUM(CASE WHEN status IN ('low_stock', 'critical') THEN 1 ELSE 0 END) as low_stock_alerts,
            SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical_count
        ")->first();

        $unitsOnHand    = (float) ($statRow->units_on_hand ?? 0);
        $unitsCommitted = $this->calcUnitsCommitted();

        return response()->json([
            'skus_tracked'    => (int) ($statRow->skus_tracked ?? 0),
            'units_on_hand'   => $unitsOnHand,
            'units_committed' => $unitsCommitted,
            'units_available' => max(0, $unitsOnHand - $unitsCommitted),
            'low_stock_alerts' => (int) ($statRow->low_stock_alerts ?? 0),
            'critical_count'  => (int) ($statRow->critical_count ?? 0),
            'pending_orders'  => Order::whereIn('status', ['pending', 'processing'])->count(),
        ]);
    }
}
