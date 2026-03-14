<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CycleCountSession;
use App\Models\CycleCountItem;
use App\Models\Product;
use App\Models\InventoryLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class CycleCountController extends Controller
{
    /**
     * List all cycle count sessions
     */
    public function index(Request $request)
    {
        $query = CycleCountSession::with(['category', 'assignedUser', 'reviewer']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by location
        if ($request->has('location')) {
            $query->where('location', $request->location);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('scheduled_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('scheduled_date', '<=', $request->date_to);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('session_number', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $sessions = $query->orderBy('scheduled_date', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($sessions);
    }

    /**
     * Get a single cycle count session with all details
     */
    public function show($id)
    {
        $cycleCountSession = CycleCountSession::find($id);

        if (!$cycleCountSession) {
            return response()->json([
                'message' => 'Cycle count session not found'
            ], 404);
        }

        $cycleCountSession->load([
            'category',
            'assignedUser',
            'reviewer',
            'items.product',
            'items.location.storageLocation',
            'items.counter'
        ]);

        return response()->json($cycleCountSession);
    }

    /**
     * Create a new cycle count session
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'nullable|string',
            'storage_location_ids' => 'nullable|array',
            'storage_location_ids.*' => 'exists:storage_locations,id',
            'category_id' => 'nullable|exists:categories,id',
            'scheduled_date' => 'required|date',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'product_ids' => 'nullable|array', // Specific products to count
            'product_ids.*' => 'exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate session number
            $sessionNumber = CycleCountSession::generateSessionNumber();

            // Expand storage location IDs to include all descendants
            $allStorageLocationIds = [];
            if ($request->has('storage_location_ids') && is_array($request->storage_location_ids)) {
                $selectedLocations = \App\Models\StorageLocation::whereIn('id', $request->storage_location_ids)->get();

                foreach ($selectedLocations as $location) {
                    // Get this location and all its descendants
                    $allStorageLocationIds = array_merge($allStorageLocationIds, $location->getDescendantIds(true));
                }

                // Remove duplicates
                $allStorageLocationIds = array_unique($allStorageLocationIds);
            }

            // Create session
            $session = CycleCountSession::create([
                'session_number' => $sessionNumber,
                'location' => $request->location,
                'storage_location_ids' => $request->has('storage_location_ids') ? $request->storage_location_ids : null,
                'category_id' => $request->category_id,
                'status' => 'planned',
                'scheduled_date' => $request->scheduled_date,
                'assigned_to' => $request->assigned_to ?? auth()->id(),
                'notes' => $request->notes,
            ]);

            // Determine which products to include
            if ($request->has('product_ids') && is_array($request->product_ids) && count($request->product_ids) > 0) {
                // Specific products
                $products = Product::whereIn('id', $request->product_ids)->get();
            } else {
                // All products matching criteria
                $query = Product::where('is_active', true);

                if ($request->category_id) {
                    $query->where('category_id', $request->category_id);
                }

                // Filter by storage locations if selected (includes descendants)
                if (count($allStorageLocationIds) > 0) {
                    $query->whereHas('inventoryLocations', function($q) use ($allStorageLocationIds) {
                        $q->whereIn('storage_location_id', $allStorageLocationIds);
                    });
                }

                $products = $query->get();
            }

            // Validate we have products to count
            if ($products->count() === 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No products found matching the specified criteria. Please adjust your filters or select specific products.',
                    'error' => 'No products to count'
                ], 422);
            }

            // Create cycle count items
            foreach ($products as $product) {
                // Initialize location variable
                $location = null;

                // Get system quantity (in eaches from database)
                if (count($allStorageLocationIds) > 0) {
                    // Count products in each selected storage location (including descendants)
                    $inventoryLocations = $product->inventoryLocations()
                        ->whereIn('storage_location_id', $allStorageLocationIds)
                        ->get();

                    foreach ($inventoryLocations as $invLoc) {
                        $systemQtyEaches = $invLoc->quantity ?? 0;

                        // Convert to packs if product has pack_size > 1
                        $systemQtyForCount = $product->hasPackSize()
                            ? $product->eachesToFullPacks($systemQtyEaches)
                            : $systemQtyEaches;

                        $session->items()->create([
                            'product_id' => $product->id,
                            'location_id' => $invLoc->id,
                            'system_quantity' => $systemQtyForCount,
                            'counted_quantity' => null,
                            'variance' => 0,
                            'variance_status' => 'pending',
                        ]);
                    }
                } else {
                    // Product-level count (no location filter)
                    $systemQtyEaches = $product->quantity_on_hand ?? 0;

                    // Convert to packs if product has pack_size > 1
                    $systemQtyForCount = $product->hasPackSize()
                        ? $product->eachesToFullPacks($systemQtyEaches)
                        : $systemQtyEaches;

                    $session->items()->create([
                        'product_id' => $product->id,
                        'location_id' => null,
                        'system_quantity' => $systemQtyForCount,
                        'counted_quantity' => null,
                        'variance' => 0,
                        'variance_status' => 'pending',
                    ]);
                }
            }

            DB::commit();

            $session->load(['items.product', 'assignedUser']);

            return response()->json([
                'message' => 'Cycle count session created successfully',
                'session' => $session
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Log the full error for debugging
            \Log::error('Cycle count creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'message' => 'Error creating cycle count session',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ], 500);
        }
    }

    /**
     * Start a cycle count session
     */
    public function start($id)
    {
        $cycleCountSession = CycleCountSession::findOrFail($id);

        if ($cycleCountSession->status !== 'planned') {
            return response()->json([
                'message' => 'Only planned sessions can be started'
            ], 422);
        }

        $cycleCountSession->start();

        return response()->json([
            'message' => 'Cycle count session started',
            'session' => $cycleCountSession
        ]);
    }

    /**
     * Record count for an item
     */
    public function recordCount(Request $request, $id)
    {
        $cycleCountSession = CycleCountSession::findOrFail($id);

        if (!in_array($cycleCountSession->status, ['planned', 'in_progress'])) {
            return response()->json([
                'message' => 'Session must be planned or in progress to record counts'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:cycle_count_items,id',
            'counted_quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $item = CycleCountItem::findOrFail($request->item_id);

        // Verify item belongs to this session
        if ($item->session_id !== $cycleCountSession->id) {
            return response()->json([
                'message' => 'Item does not belong to this session'
            ], 422);
        }

        // Start session if not already started
        if ($cycleCountSession->status === 'planned') {
            $cycleCountSession->start();
        }

        // Record the count
        $item->recordCount(
            $request->counted_quantity,
            auth()->id(),
            $request->notes
        );

        return response()->json([
            'message' => 'Count recorded successfully',
            'item' => $item->load(['product', 'location'])
        ]);
    }

    /**
     * Approve variances and create adjustments
     */
    public function approveVariances(Request $request, $id)
    {
        $cycleCountSession = CycleCountSession::findOrFail($id);

        if ($cycleCountSession->status !== 'in_progress') {
            return response()->json([
                'message' => 'Session must be in progress to approve variances'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'exists:cycle_count_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $adjustedItems = [];

            foreach ($request->item_ids as $itemId) {
                $item = CycleCountItem::findOrFail($itemId);

                // Verify item belongs to this session
                if ($item->session_id !== $cycleCountSession->id) {
                    throw new \Exception('Invalid item ID');
                }

                // Approve variance
                $transaction = $item->approveVariance(auth()->id());
                $adjustedItems[] = $item;
            }

            DB::commit();

            return response()->json([
                'message' => 'Variances approved and adjustments created',
                'items' => $adjustedItems
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error approving variances',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete a cycle count session
     */
    public function complete($id)
    {
        $cycleCountSession = CycleCountSession::findOrFail($id);

        if ($cycleCountSession->status !== 'in_progress') {
            return response()->json([
                'message' => 'Only in-progress sessions can be completed'
            ], 422);
        }

        // Check if all items have been counted
        $uncounted = $cycleCountSession->items()
            ->whereNull('counted_quantity')
            ->count();

        if ($uncounted > 0) {
            return response()->json([
                'message' => "Cannot complete session: {$uncounted} items have not been counted"
            ], 422);
        }

        // Check if all variances have been reviewed
        $pending = $cycleCountSession->items()
            ->where('variance', '!=', 0)
            ->where('variance_status', 'pending')
            ->count();

        if ($pending > 0) {
            return response()->json([
                'message' => "Cannot complete session: {$pending} variances require review"
            ], 422);
        }

        $cycleCountSession->complete(auth()->id());

        return response()->json([
            'message' => 'Cycle count session completed successfully',
            'session' => $cycleCountSession->load(['items.product'])
        ]);
    }

    /**
     * Cancel a cycle count session
     */
    public function cancel($id)
    {
        $cycleCountSession = CycleCountSession::findOrFail($id);

        if ($cycleCountSession->status === 'completed') {
            return response()->json([
                'message' => 'Cannot cancel completed sessions'
            ], 422);
        }

        $cycleCountSession->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Cycle count session cancelled',
            'session' => $cycleCountSession
        ]);
    }

    /**
     * Get variance report for a session
     */
    public function varianceReport($id)
    {
        $cycleCountSession = CycleCountSession::findOrFail($id);

        $items = $cycleCountSession->items()
            ->with(['product', 'location', 'counter'])
            ->where('variance', '!=', 0)
            ->orderBy('variance', 'desc')
            ->get();

        $summary = [
            'total_items_counted' => $cycleCountSession->counted_items,
            'items_with_variance' => $cycleCountSession->variance_items,
            'total_variance' => $cycleCountSession->total_variance,
            'accuracy_percentage' => $cycleCountSession->accuracy_percentage,
            'positive_variance' => $items->where('variance', '>', 0)->sum('variance'),
            'negative_variance' => $items->where('variance', '<', 0)->sum('variance'),
            'adjustments_created' => $cycleCountSession->items()->where('adjustment_created', true)->count(),
        ];

        return response()->json([
            'session' => $cycleCountSession,
            'variances' => $items,
            'summary' => $summary,
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics()
    {
        $stats = [
            'total_sessions' => CycleCountSession::count(),
            'planned' => CycleCountSession::where('status', 'planned')->count(),
            'in_progress' => CycleCountSession::where('status', 'in_progress')->count(),
            'completed' => CycleCountSession::where('status', 'completed')->count(),
            'cancelled' => CycleCountSession::where('status', 'cancelled')->count(),
            'active_sessions' => CycleCountSession::active()->count(),
            'items_counted_this_month' => CycleCountItem::whereHas('session', function($q) {
                $q->whereMonth('completed_at', date('m'))
                  ->whereYear('completed_at', date('Y'));
            })->whereNotNull('counted_quantity')->count(),
            'accuracy_this_month' => $this->calculateMonthlyAccuracy(),
        ];

        return response()->json($stats);
    }

    /**
     * Get active cycle count sessions
     */
    public function active()
    {
        $sessions = CycleCountSession::with(['assignedUser', 'items'])
            ->active()
            ->orderBy('scheduled_date', 'asc')
            ->get();

        return response()->json($sessions);
    }

    /**
     * Calculate average accuracy for current month
     */
    private function calculateMonthlyAccuracy()
    {
        $completedSessions = CycleCountSession::where('status', 'completed')
            ->whereMonth('completed_at', date('m'))
            ->whereYear('completed_at', date('Y'))
            ->get();

        if ($completedSessions->count() === 0) {
            return 100;
        }

        $totalAccuracy = $completedSessions->sum('accuracy_percentage');
        return round($totalAccuracy / $completedSessions->count(), 1);
    }

    /**
     * Generate PDF report for a cycle count session
     */
    public function generatePdf($id)
    {
        $session = CycleCountSession::with([
            'category',
            'assignedUser',
            'reviewer',
            'items.product',
            'items.location.storageLocation',
            'items.counter'
        ])->findOrFail($id);

        // Get committed quantities for comparison
        $committedByProduct = DB::table('job_reservation_items as ri')
            ->join('job_reservations as r', 'ri.reservation_id', '=', 'r.id')
            ->whereIn('r.status', ['active', 'in_progress', 'on_hold'])
            ->whereNull('r.deleted_at')
            ->select('ri.product_id', DB::raw('SUM(ri.committed_qty) as committed_qty'))
            ->groupBy('ri.product_id')
            ->pluck('committed_qty', 'product_id')
            ->toArray();

        $data = [
            'session' => $session,
            'committedByProduct' => $committedByProduct,
        ];

        $pdf = Pdf::loadView('pdfs.cycle-count-report', $data);

        // Set paper size and orientation
        $pdf->setPaper('a4', 'landscape');

        $filename = "cycle-count-{$session->session_number}.pdf";

        return $pdf->download($filename);
    }
}
