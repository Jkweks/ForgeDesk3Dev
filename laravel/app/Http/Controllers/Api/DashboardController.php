<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\JobReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Calculate committed quantities from the fulfillment system (job_reservation_items)
     * Only includes reservations with active statuses: active, in_progress, on_hold
     */
    private function getCommittedFromFulfillment($categoryId = null)
    {
        $query = DB::table('job_reservation_items as ri')
            ->join('job_reservations as r', 'ri.reservation_id', '=', 'r.id')
            ->join('products as p', 'ri.product_id', '=', 'p.id')
            ->whereIn('r.status', ['active', 'in_progress', 'on_hold'])
            ->whereNull('r.deleted_at')
            ->where('p.is_active', true);

        if ($categoryId) {
            // Filter by category using the category_product pivot table
            $query->whereExists(function ($subquery) use ($categoryId) {
                $subquery->select(DB::raw(1))
                    ->from('category_product as cp')
                    ->whereColumn('cp.product_id', 'p.id')
                    ->where('cp.category_id', $categoryId);
            });
        }

        return $query->sum('ri.committed_qty');
    }

    /**
     * Get committed quantities grouped by product ID
     */
    private function getCommittedByProduct()
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
     * Calculate units in packs (for products with pack_size) or eaches (for products without)
     */
    private function calculateUnitsAsPacks($query)
    {
        $products = (clone $query)->get(['id', 'quantity_on_hand', 'pack_size']);
        $total = 0;

        foreach ($products as $product) {
            if ($product->pack_size && $product->pack_size > 1) {
                // Count in packs (floor to get full packs only)
                $total += floor($product->quantity_on_hand / $product->pack_size);
            } else {
                // Count in eaches
                $total += $product->quantity_on_hand ?? 0;
            }
        }

        return $total;
    }

    /**
     * Calculate committed units in packs
     */
    private function calculateCommittedAsPacks($query)
    {
        $committedByProduct = $this->getCommittedByProduct();
        $products = (clone $query)->get(['id', 'pack_size']);
        $total = 0;

        foreach ($products as $product) {
            $committedQty = $committedByProduct[$product->id] ?? 0;

            if ($product->pack_size && $product->pack_size > 1) {
                // Count in packs (ceil to get packs needed)
                $total += ceil($committedQty / $product->pack_size);
            } else {
                // Count in eaches
                $total += $committedQty;
            }
        }

        return $total;
    }

    public function index(Request $request)
    {
        $categoryId = $request->get('category_id');
        $perPage = $request->get('per_page', 50);
        $sortBy = $request->get('sort_by', 'sku');
        $sortDir = $request->get('sort_dir', 'asc');
        $search = $request->get('search');

        // Validate sort column to prevent SQL injection
        $allowedSortColumns = ['sku', 'description', 'quantity_on_hand', 'quantity_committed', 'quantity_available', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'sku';
        }
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        // Get committed quantities from fulfillment system
        $committedByProduct = $this->getCommittedByProduct();

        // Base query for stats (apply category filter if present)
        $statsQuery = Product::where('is_active', true);
        if ($categoryId) {
            $statsQuery->whereHas('categories', function($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }
        if ($search) {
            $searchLower = strtolower($search);
            $statsQuery->where(function($q) use ($searchLower) {
                $q->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        // Calculate units in packs (or eaches for non-packed items)
        $unitsOnHand = $this->calculateUnitsAsPacks($statsQuery);
        $unitsCommitted = $this->calculateCommittedAsPacks($statsQuery);

        $stats = [
            'skus_tracked' => (clone $statsQuery)->count(),
            'units_on_hand' => $unitsOnHand,
            'units_committed' => $unitsCommitted,
            'units_available' => max(0, $unitsOnHand - $unitsCommitted),
            'low_stock_alerts' => (clone $statsQuery)->where(function($q) {
                $q->where('status', 'low_stock')->orWhere('status', 'critical');
            })->count(),
            'critical_count' => (clone $statsQuery)->where('status', 'critical')->count(),
            'pending_orders' => Order::whereIn('status', ['pending', 'processing'])->count(),
        ];

        $inventoryQuery = Product::with(['inventoryLocations', 'categories'])
            ->where('is_active', true);

        if ($categoryId) {
            $inventoryQuery->whereHas('categories', function($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        if ($search) {
            $searchLower = strtolower($search);
            $inventoryQuery->where(function($q) use ($searchLower) {
                $q->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        // Handle sorting - for calculated fields, we need to sort after fetching
        if (in_array($sortBy, ['quantity_committed', 'quantity_available'])) {
            // Fetch all matching products first, enrich, then sort and paginate manually
            $allProducts = $inventoryQuery->get();

            // Enrich with committed quantities (eaches and packs)
            $allProducts->transform(function ($product) use ($committedByProduct) {
                $committedQty = $committedByProduct[$product->id] ?? 0;
                $committedPacks = $product->eachesToPacksNeeded($committedQty);
                $onHandPacks = $product->eachesToFullPacks($product->quantity_on_hand);

                $product->quantity_committed = $committedQty;
                $product->quantity_committed_packs = $committedPacks;
                $product->quantity_available = $product->quantity_on_hand - $committedQty;
                $product->quantity_available_packs = max(0, $onHandPacks - $committedPacks);
                return $product;
            });

            // Sort by the calculated field
            $allProducts = $sortDir === 'asc'
                ? $allProducts->sortBy($sortBy)->values()
                : $allProducts->sortByDesc($sortBy)->values();

            // Manual pagination
            $total = $allProducts->count();
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;
            $items = $allProducts->slice($offset, $perPage)->values();

            $inventory = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            // Standard database sorting
            $inventory = $inventoryQuery->orderBy($sortBy, $sortDir)->paginate($perPage);

            // Enrich inventory items with real-time committed quantities from fulfillment (eaches and packs)
            $inventory->getCollection()->transform(function ($product) use ($committedByProduct) {
                $committedQty = $committedByProduct[$product->id] ?? 0;
                $committedPacks = $product->eachesToPacksNeeded($committedQty);
                $onHandPacks = $product->eachesToFullPacks($product->quantity_on_hand);

                $product->quantity_committed = $committedQty;
                $product->quantity_committed_packs = $committedPacks;
                $product->quantity_available = $product->quantity_on_hand - $committedQty;
                $product->quantity_available_packs = max(0, $onHandPacks - $committedPacks);
                return $product;
            });
        }

        $lowStock = Product::where('status', 'low_stock')
            ->where('is_active', true)
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->whereHas('categories', function($sq) use ($categoryId) {
                    $sq->where('categories.id', $categoryId);
                });
            })
            ->get();

        $criticalStock = Product::where('status', 'critical')
            ->where('is_active', true)
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->whereHas('categories', function($sq) use ($categoryId) {
                    $sq->where('categories.id', $categoryId);
                });
            })
            ->get();

        // Get committed parts from fulfillment system
        $committedParts = Product::whereHas('reservationItems', function($query) {
                $query->whereHas('reservation', function($resQuery) {
                    $resQuery->whereIn('status', ['active', 'in_progress', 'on_hold']);
                });
            })
            ->with(['reservationItems' => function($query) {
                $query->whereHas('reservation', function($resQuery) {
                    $resQuery->whereIn('status', ['active', 'in_progress', 'on_hold']);
                })->with('reservation');
            }])
            ->where('is_active', true)
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->whereHas('categories', function($sq) use ($categoryId) {
                    $sq->where('categories.id', $categoryId);
                });
            })
            ->get()
            ->map(function ($product) {
                $committedQty = $product->reservationItems->sum('committed_qty');
                $committedPacks = $product->eachesToPacksNeeded($committedQty);
                $onHandPacks = $product->eachesToFullPacks($product->quantity_on_hand);

                $product->quantity_committed = $committedQty;
                $product->quantity_committed_packs = $committedPacks;
                $product->quantity_available = $product->quantity_on_hand - $committedQty;
                $product->quantity_available_packs = max(0, $onHandPacks - $committedPacks);
                return $product;
            });

        return response()->json([
            'stats' => $stats,
            'inventory' => $inventory,
            'low_stock' => $lowStock,
            'critical_stock' => $criticalStock,
            'committed_parts' => $committedParts,
        ]);
    }

    public function inventoryByStatus(Request $request, $status)
    {
        $categoryId = $request->get('category_id');
        $perPage = $request->get('per_page', 50);
        $sortBy = $request->get('sort_by', 'sku');
        $sortDir = $request->get('sort_dir', 'asc');
        $search = $request->get('search');

        // Validate sort column
        $allowedSortColumns = ['sku', 'description', 'quantity_on_hand', 'quantity_committed', 'quantity_available', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'sku';
        }
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        // Get committed quantities from fulfillment system
        $committedByProduct = $this->getCommittedByProduct();

        $query = Product::where('status', $status)
            ->where('is_active', true)
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->whereHas('categories', function($sq) use ($categoryId) {
                    $sq->where('categories.id', $categoryId);
                });
            })
            ->when($search, function($q) use ($search) {
                $searchLower = strtolower($search);
                return $q->where(function($sq) use ($searchLower) {
                    $sq->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                       ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                       ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"]);
                });
            })
            ->with('categories');

        // Handle sorting for calculated fields
        if (in_array($sortBy, ['quantity_committed', 'quantity_available'])) {
            $allProducts = $query->get();

            $allProducts->transform(function ($product) use ($committedByProduct) {
                $committedQty = $committedByProduct[$product->id] ?? 0;
                $committedPacks = $product->eachesToPacksNeeded($committedQty);
                $onHandPacks = $product->eachesToFullPacks($product->quantity_on_hand);

                $product->quantity_committed = $committedQty;
                $product->quantity_committed_packs = $committedPacks;
                $product->quantity_available = $product->quantity_on_hand - $committedQty;
                $product->quantity_available_packs = max(0, $onHandPacks - $committedPacks);
                return $product;
            });

            $allProducts = $sortDir === 'asc'
                ? $allProducts->sortBy($sortBy)->values()
                : $allProducts->sortByDesc($sortBy)->values();

            $total = $allProducts->count();
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;
            $items = $allProducts->slice($offset, $perPage)->values();

            $products = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $products = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

            $products->getCollection()->transform(function ($product) use ($committedByProduct) {
                $committedQty = $committedByProduct[$product->id] ?? 0;
                $committedPacks = $product->eachesToPacksNeeded($committedQty);
                $onHandPacks = $product->eachesToFullPacks($product->quantity_on_hand);

                $product->quantity_committed = $committedQty;
                $product->quantity_committed_packs = $committedPacks;
                $product->quantity_available = $product->quantity_on_hand - $committedQty;
                $product->quantity_available_packs = max(0, $onHandPacks - $committedPacks);
                return $product;
            });
        }

        return response()->json($products);
    }

    public function stats()
    {
        // Calculate in packs (or eaches for non-packed items)
        $query = Product::where('is_active', true);
        $unitsOnHand = $this->calculateUnitsAsPacks($query);
        $unitsCommitted = $this->calculateCommittedAsPacks($query);

        return response()->json([
            'skus_tracked' => Product::where('is_active', true)->count(),
            'units_on_hand' => $unitsOnHand,
            'units_committed' => $unitsCommitted,
            'units_available' => max(0, $unitsOnHand - $unitsCommitted),
            'low_stock_alerts' => Product::whereIn('status', ['low_stock', 'critical'])->count(),
            'critical_count' => Product::where('status', 'critical')->count(),
            'pending_orders' => Order::whereIn('status', ['pending', 'processing'])->count(),
        ]);
    }
}