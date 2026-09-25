<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PurchaseOrderSubmittedForApproval;
use App\Models\CompanyLocation;
use App\Models\CompanySetting;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
class PurchaseOrderController extends Controller
{
    /** Statuses where the PO — its header, addresses, and line items — may still be edited. */
    private const EDITABLE_STATUSES = ['draft', 'submitted'];

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
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
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
            'approver',
            'assignedApprover',
            'shipToLocation',
        ]);

        return response()->json($purchaseOrder);
    }

    /**
     * Users eligible to be picked as a PO's approver (anyone with
     * orders.approve permission) — for the approver-selection dropdown.
     */
    public function eligibleApprovers()
    {
        $users = User::query()
            ->active()
            ->withPermission('orders.approve')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }

    /**
     * Create a new purchase order
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'po_number' => 'nullable|string|max:50|unique:purchase_orders,po_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
            'ship_to' => 'nullable|string',
            'ship_to_location_id' => 'nullable|exists:company_locations,id',
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
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Use provided PO number or auto-generate one
            $poNumber = $request->filled('po_number')
                ? trim($request->po_number)
                : PurchaseOrder::generatePoNumber();

            // Create purchase order
            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $request->supplier_id,
                'status' => 'draft',
                'order_date' => $request->order_date,
                'expected_date' => $request->expected_date,
                'notes' => $request->notes,
                'ship_to' => $request->ship_to,
                'ship_to_location_id' => $request->ship_to_location_id,
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
                'purchase_order' => $po,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error creating purchase order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a purchase order
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Editable up to (but not including) approval.
        if (! in_array($purchaseOrder->status, self::EDITABLE_STATUSES)) {
            return response()->json([
                'message' => 'Only draft or submitted purchase orders can be edited',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'order_date' => 'sometimes|required|date',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'ship_to' => 'nullable|string',
            'ship_to_location_id' => 'nullable|exists:company_locations,id',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'approver_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $purchaseOrder->update($request->only([
            'supplier_id',
            'order_date',
            'expected_date',
            'notes',
            'ship_to',
            'ship_to_location_id',
            'contact_name',
            'contact_email',
            'contact_phone',
            'approver_id',
        ]));

        return response()->json([
            'message' => 'Purchase order updated successfully',
            'purchase_order' => $purchaseOrder->load(['supplier', 'items.product', 'creator', 'approver', 'assignedApprover', 'shipToLocation']),
        ]);
    }

    /**
     * Submit purchase order for approval
     */
    public function submit(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft orders can be submitted',
            ], 422);
        }

        if ($purchaseOrder->items()->count() === 0) {
            return response()->json([
                'message' => 'Cannot submit order with no items',
            ], 422);
        }

        $purchaseOrder->update(['status' => 'submitted']);

        $purchaseOrder->load('assignedApprover');
        if ($purchaseOrder->assignedApprover?->email) {
            Mail::to($purchaseOrder->assignedApprover->email)
                ->send(new PurchaseOrderSubmittedForApproval($purchaseOrder));
        }

        return response()->json([
            'message' => 'Purchase order submitted successfully',
            'purchase_order' => $purchaseOrder,
        ]);
    }

    /**
     * Approve purchase order
     */
    public function approve(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'submitted') {
            return response()->json([
                'message' => 'Only submitted orders can be approved',
            ], 422);
        }

        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Purchase order approved successfully',
            'purchase_order' => $purchaseOrder,
        ]);
    }

    /**
     * Receive items from purchase order
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->status, ['approved', 'partially_received'])) {
            return response()->json([
                'message' => 'Order must be approved before receiving',
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
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $receivedDate = $request->received_date ?? now();

            foreach ($request->items as $itemData) {
                $poItem = PurchaseOrderItem::findOrFail($itemData['item_id']);

                // Verify item belongs to this PO
                if ($poItem->purchase_order_id !== $purchaseOrder->id) {
                    throw new \Exception('Item does not belong to this purchase order');
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
                $product->on_order_qty = max(0, ($product->on_order_qty ?? 0) - $quantityToReceive);
                $product->save();

                // Update storage location quantity, then recalculate quantity_on_hand
                // from inventory_locations (the canonical source of truth) instead of
                // incrementing quantity_on_hand directly — this self-corrects any
                // pre-existing drift instead of compounding it.
                if (! empty($itemData['storage_location_id'])) {
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
                } else {
                    // No specific location provided — add to the primary location so the
                    // location table remains the source of truth for quantity_on_hand.
                    $primaryLocation = $product->inventoryLocations()
                        ->orderBy('is_primary', 'desc')
                        ->orderBy('id', 'asc')
                        ->first();

                    if ($primaryLocation) {
                        $primaryLocation->quantity += $quantityToReceive;
                        $primaryLocation->save();
                    } else {
                        // No locations exist yet (new/unplaced product). Create an
                        // Unassigned location record so the location table stays as
                        // the source of truth and recalculateQuantitiesFromLocations()
                        // never resets this receipt to zero.
                        $unassigned = StorageLocation::where('code', 'UNASSIGNED')->first();
                        $product->inventoryLocations()->create([
                            'storage_location_id' => $unassigned?->id ?? null,
                            'quantity' => $quantityToReceive,
                            'is_primary' => true,
                        ]);
                    }
                }

                $product->recalculateQuantitiesFromLocations();

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
                'purchase_order' => $purchaseOrder,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error receiving items',
                'error' => $e->getMessage(),
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
                'message' => 'Order is already cancelled',
            ], 422);
        }

        if ($purchaseOrder->status === 'received') {
            return response()->json([
                'message' => 'Cannot cancel fully received orders',
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
                'purchase_order' => $purchaseOrder,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error cancelling purchase order',
                'error' => $e->getMessage(),
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
                'message' => 'Only draft orders can be deleted',
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
                'message' => 'Purchase order deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error deleting purchase order',
                'error' => $e->getMessage(),
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

    /**
     * Add a line item to an editable (draft/submitted) purchase order
     */
    public function addItem(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->status, self::EDITABLE_STATUSES)) {
            return response()->json(['message' => 'Line items can only be added to draft or submitted purchase orders'], 422);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0',
            'destination_location' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $item = $purchaseOrder->items()->create([
                'product_id' => $request->product_id,
                'quantity_ordered' => $request->quantity,
                'quantity_received' => 0,
                'unit_cost' => $request->unit_cost,
                'total_cost' => $request->quantity * $request->unit_cost,
                'destination_location' => $request->destination_location,
                'notes' => $request->notes,
            ]);

            $product = Product::find($request->product_id);
            $product->on_order_qty = ($product->on_order_qty ?? 0) + $request->quantity;
            $product->save();

            $purchaseOrder->total_amount = $purchaseOrder->items()->sum('total_cost');
            $purchaseOrder->save();

            DB::commit();

            return response()->json([
                'message' => 'Line item added successfully',
                'purchase_order' => $purchaseOrder->load(['supplier', 'items.product', 'creator', 'approver']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Failed to add line item'], 500);
        }
    }

    /**
     * Update quantity / unit cost / destination of a line item on an editable
     * (draft/submitted) purchase order. Keeps the product's on_order_qty and the
     * PO total in sync.
     */
    public function updateItem(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item)
    {
        if (! in_array($purchaseOrder->status, self::EDITABLE_STATUSES)) {
            return response()->json(['message' => 'Line items can only be edited on draft or submitted purchase orders'], 422);
        }

        if ($item->purchase_order_id !== $purchaseOrder->id) {
            return response()->json(['message' => 'Item does not belong to this purchase order'], 422);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'sometimes|required|integer|min:1',
            'unit_cost' => 'sometimes|required|numeric|min:0',
            'destination_location' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $newQty = $request->has('quantity') ? (int) $request->quantity : (int) $item->quantity_ordered;

        if ($newQty < $item->quantity_received) {
            return response()->json([
                'message' => "Quantity cannot be less than the {$item->quantity_received} already received",
            ], 422);
        }

        DB::beginTransaction();
        try {
            $delta = $newQty - (int) $item->quantity_ordered;

            $item->fill([
                'quantity_ordered' => $newQty,
                'unit_cost' => $request->has('unit_cost') ? $request->unit_cost : $item->unit_cost,
                'destination_location' => $request->has('destination_location') ? $request->destination_location : $item->destination_location,
                'notes' => $request->has('notes') ? $request->notes : $item->notes,
            ]);
            $item->save(); // model boot() recomputes total_cost

            if ($delta !== 0) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->on_order_qty = max(0, ($product->on_order_qty ?? 0) + $delta);
                    $product->save();
                }
            }

            $purchaseOrder->total_amount = $purchaseOrder->items()->sum('total_cost');
            $purchaseOrder->save();

            DB::commit();

            return response()->json([
                'message' => 'Line item updated successfully',
                'purchase_order' => $purchaseOrder->load(['supplier', 'items.product', 'creator', 'approver', 'shipToLocation']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Failed to update line item'], 500);
        }
    }

    /**
     * Render a purchase order as a printable PDF. Works for any supplier.
     *
     * Header / order-from block  = the primary CompanyLocation
     * Ship-to block              = the PO's chosen CompanyLocation, else its
     *                              free-text ship_to, else the primary location
     * Line pricing               = each item's captured unit_cost, which the UI
     *                              seeds from the product's net (calculated)
     *                              price — never list price
     */
    public function exportPdf(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.product', 'creator', 'approver', 'shipToLocation']);

        $primary = CompanyLocation::primaryLocation();
        $shipTo = $purchaseOrder->shipToLocation ?: $primary;

        $subtotal = $purchaseOrder->items->sum(fn ($i) => (float) $i->unit_cost * (int) $i->quantity_ordered);

        // Embed the company logo as a data URI — dompdf can't reliably resolve
        // storage paths or remote URLs.
        $logo = null;
        $logoPath = CompanySetting::current()->logo_path;
        if ($logoPath && \Storage::disk('public')->exists($logoPath)) {
            $logo = 'data:'.(\Storage::disk('public')->mimeType($logoPath) ?: 'image/png')
                .';base64,'.base64_encode(\Storage::disk('public')->get($logoPath));
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.purchase-order', [
            'po' => $purchaseOrder,
            'company' => $primary,
            'shipTo' => $shipTo,
            'subtotal' => $subtotal,
            'logo' => $logo,
        ]);

        $pdf->setPaper('letter', 'portrait');

        $filename = 'PO_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $purchaseOrder->po_number).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export a Tubelite purchase order as an EZ Estimate-format Excel file
     */
    public function exportEzEstimate(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.product']);

        if (! str_contains(strtolower($purchaseOrder->supplier->name ?? ''), 'tubelite')) {
            return response()->json(['message' => 'EZ Estimate export is only available for Tubelite purchase orders'], 422);
        }

        // Separate items by product type (mirroring the import's SL/Accessory distinction)
        $slItems = [];
        $accessoryItems = [];

        foreach ($purchaseOrder->items as $item) {
            $sku = strtoupper($item->product->sku ?? '');
            if (preg_match('/^(A|E|M|T)/', $sku)) {
                $slItems[] = $item;
            } else {
                $accessoryItems[] = $item;
            }
        }

        $templatePath = storage_path('app/templates/ez_estimate_template.xlsm');

        if (! file_exists($templatePath)) {
            \Log::error('EZ Estimate export failed: template file missing', ['path' => $templatePath]);

            return response()->json(['message' => 'EZ Estimate template is missing on the server'], 500);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'po_ez_').'.xlsm';

        try {
            if (! copy($templatePath, $tempFile)) {
                throw new \Exception('Unable to copy EZ Estimate template');
            }

            // Stock Lengths: 3 pages, input rows 11-47 (37 rows/page), columns A=Qty, B=Part#, C=Finish
            // Accessories: 3 pages, input rows 11-46 (36 rows/page), columns A=Qty, B=Part#, C=Finish
            // — plain input cells on both; the PO's quantity_ordered is written
            // as-is (no pack/eaches conversion — Accessories!A/B/C are normally
            // formulas pulling a converted quantity from CALCULATIONS, but we
            // overwrite them directly so the PO quantity is exactly what shows).
            $updates = [];
            $this->collectEzEstimateCellUpdates($updates, ['Stock Lengths', 'Stock Lengths (2)', 'Stock Lengths (3)'], 11, 47, $slItems);
            $this->collectEzEstimateCellUpdates($updates, ['Accessories', 'Accessories (2)', 'Accessories (3)'], 11, 46, $accessoryItems);

            // The template is a 70+ sheet, macro-enabled, formula-heavy workbook.
            // A full PhpSpreadsheet load/write round trip cannot losslessly
            // reconstruct it (drops VBA, mangles pivots/defined names, etc.),
            // which is what produced Excel's "file is corrupted" prompt. Instead,
            // patch only the target worksheet XML parts directly inside the zip
            // container, leaving every other byte of the package untouched.
            $this->patchEzEstimateWorkbook($tempFile, $updates);

            $filename = 'EZ_Estimate_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $purchaseOrder->po_number).'_'.date('Ymd').'.xlsm';

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            \Log::error('EZ Estimate export failed', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error exporting EZ Estimate file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build the Qty/Part#/Finish cell updates for consecutive EZ Estimate
     * template pages (Stock Lengths or Accessories, each spanning up to 3
     * sheets), keyed by sheet name then cell reference. On Accessories these
     * columns are normally formulas pulling a pack-converted quantity from
     * CALCULATIONS, but per the PO these are overwritten directly with the
     * PO's own quantity/part/finish, with no unit conversion. The remaining
     * columns (description/price/etc.) are left as live formulas so pricing
     * still recalculates from the template's own price sheets.
     */
    private function collectEzEstimateCellUpdates(array &$updates, array $sheetNames, int $startRow, int $endRow, array $items): void
    {
        $rowsPerPage = $endRow - $startRow + 1;
        $capacity = $rowsPerPage * count($sheetNames);

        if (count($items) > $capacity) {
            \Log::warning('EZ Estimate export: item count exceeds template capacity, truncating', [
                'sheets' => $sheetNames,
                'capacity' => $capacity,
                'item_count' => count($items),
            ]);
            $items = array_slice($items, 0, $capacity);
        }

        $itemIndex = 0;
        foreach ($sheetNames as $sheetName) {
            for ($row = $startRow; $row <= $endRow && $itemIndex < count($items); $row++, $itemIndex++) {
                $item = $items[$itemIndex];
                $product = $item->product;

                $updates[$sheetName]["A{$row}"] = ['type' => 'n', 'value' => $item->quantity_ordered];
                $updates[$sheetName]["B{$row}"] = ['type' => 's', 'value' => $product->part_number ?? $product->sku ?? ''];
                $updates[$sheetName]["C{$row}"] = ['type' => 's', 'value' => $product->finish ?? ''];
            }
        }
    }

    /**
     * Patch the given worksheet cell values directly into an EZ Estimate
     * .xlsm's zip container, without loading/rewriting the workbook through
     * PhpSpreadsheet. Only the worksheet XML parts named in $updatesBySheetName
     * are touched; every other part of the package (macros, other sheets,
     * styles, pivots, defined names, etc.) is left byte-for-byte as-is.
     */
    private function patchEzEstimateWorkbook(string $filePath, array $updatesBySheetName): void
    {
        $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $zip = new \ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new \Exception('Unable to open EZ Estimate template as a zip archive');
        }

        try {
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

            if ($workbookXml === false || $relsXml === false) {
                throw new \Exception('EZ Estimate template is missing workbook parts');
            }

            $workbookDoc = new \DOMDocument;
            $workbookDoc->loadXML($workbookXml);
            $relsDoc = new \DOMDocument;
            $relsDoc->loadXML($relsXml);

            $relTargets = [];
            foreach ($relsDoc->getElementsByTagName('Relationship') as $rel) {
                $relTargets[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
            }

            $sheetParts = [];
            foreach ($workbookDoc->getElementsByTagNameNS($mainNs, 'sheet') as $sheetEl) {
                $rId = $sheetEl->getAttributeNS($relNs, 'id');
                if ($rId === '' || ! isset($relTargets[$rId])) {
                    continue;
                }

                $target = ltrim($relTargets[$rId], '/');
                if (! str_starts_with($target, 'xl/')) {
                    $target = 'xl/'.$target;
                }

                $sheetParts[$sheetEl->getAttribute('name')] = $target;
            }

            foreach ($updatesBySheetName as $sheetName => $cellUpdates) {
                if (! isset($sheetParts[$sheetName])) {
                    throw new \Exception("EZ Estimate template sheet not found: {$sheetName}");
                }

                $partPath = $sheetParts[$sheetName];
                $sheetXml = $zip->getFromName($partPath);
                if ($sheetXml === false) {
                    throw new \Exception("EZ Estimate template worksheet part not found: {$partPath}");
                }

                $patchedXml = $this->patchWorksheetXml($sheetXml, $cellUpdates);

                if (! $zip->addFromString($partPath, $patchedXml)) {
                    throw new \Exception("Failed to write patched worksheet: {$partPath}");
                }
            }

            $this->forceFullCalcOnLoad($zip, $workbookDoc, $mainNs);
            $this->stripCalcChain($zip, $relsDoc);
        } finally {
            $zip->close();
        }
    }

    /**
     * Set calcPr fullCalcOnLoad="1" in xl/workbook.xml so Excel recalculates
     * every formula the moment the file opens, instead of trusting the
     * cached <v> values that were sitting in the template's formula cells
     * (e.g. Accessories!A11/B11, and everything downstream of CALCULATIONS)
     * before we overwrote their inputs. Without this, those cells keep
     * showing their old cached result until something (e.g. the user
     * retyping a cell) triggers Excel's own dirty-tracking — which for a
     * cross-sheet chain this size doesn't reliably happen on its own,
     * especially once calcChain.xml (Excel's calc-order cache) is gone.
     */
    private function forceFullCalcOnLoad(\ZipArchive $zip, \DOMDocument $workbookDoc, string $mainNs): void
    {
        $calcPr = $workbookDoc->getElementsByTagNameNS($mainNs, 'calcPr')->item(0);
        if (! $calcPr) {
            throw new \Exception('EZ Estimate template is missing its calcPr element');
        }
        $calcPr->setAttribute('fullCalcOnLoad', '1');

        $zip->addFromString('xl/workbook.xml', $workbookDoc->saveXML());
    }

    /**
     * Remove xl/calcChain.xml and every reference to it (its workbook
     * relationship and its [Content_Types].xml override). calcChain.xml is
     * just a cached calculation-order hint — Excel rebuilds it fine on open
     * with none present — but any edit made outside Excel invalidates its
     * cached ordering, and a stale-but-still-referenced calcChain is what
     * triggers Excel's "we found a problem... removed records: formula from
     * /xl/calcChain.xml" repair prompt on open. Dropping it (and its
     * references, so nothing dangles) avoids that prompt entirely.
     */
    private function stripCalcChain(\ZipArchive $zip, \DOMDocument $relsDoc): void
    {
        if ($zip->locateName('xl/calcChain.xml') === false) {
            return;
        }

        $zip->deleteName('xl/calcChain.xml');

        foreach ($relsDoc->getElementsByTagName('Relationship') as $rel) {
            if (rtrim($rel->getAttribute('Target'), '/') === 'calcChain.xml') {
                $rel->parentNode->removeChild($rel);
                break;
            }
        }
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relsDoc->saveXML());

        $contentTypesXml = $zip->getFromName('[Content_Types].xml');
        if ($contentTypesXml !== false) {
            $contentTypesDoc = new \DOMDocument;
            $contentTypesDoc->loadXML($contentTypesXml);
            foreach ($contentTypesDoc->getElementsByTagName('Override') as $override) {
                if ($override->getAttribute('PartName') === '/xl/calcChain.xml') {
                    $override->parentNode->removeChild($override);
                    break;
                }
            }
            $zip->addFromString('[Content_Types].xml', $contentTypesDoc->saveXML());
        }
    }

    /**
     * Set values on specific cells of a single worksheet XML part, creating
     * rows/cells that don't already exist. Any existing formula/value on a
     * touched cell is replaced; every other cell in the sheet is left as-is.
     * Strings are written as inline strings so the shared-string table
     * (used by every other sheet) never needs to be touched.
     */
    private function patchWorksheetXml(string $xml, array $cellUpdates): string
    {
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

        $doc = new \DOMDocument;
        $doc->preserveWhiteSpace = true;
        $doc->loadXML($xml);

        $sheetData = $doc->getElementsByTagNameNS($ns, 'sheetData')->item(0);
        if (! $sheetData) {
            throw new \Exception('sheetData not found in worksheet XML');
        }

        $byRow = [];
        foreach ($cellUpdates as $ref => $update) {
            preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
            $byRow[(int) $m[2]][$m[1]] = $update;
        }

        foreach ($byRow as $rowNum => $cols) {
            $rowEl = null;
            foreach ($sheetData->getElementsByTagNameNS($ns, 'row') as $existingRow) {
                if ((int) $existingRow->getAttribute('r') === $rowNum) {
                    $rowEl = $existingRow;
                    break;
                }
            }

            if (! $rowEl) {
                $rowEl = $doc->createElementNS($ns, 'row');
                $rowEl->setAttribute('r', (string) $rowNum);

                $before = null;
                foreach ($sheetData->getElementsByTagNameNS($ns, 'row') as $existingRow) {
                    if ((int) $existingRow->getAttribute('r') > $rowNum) {
                        $before = $existingRow;
                        break;
                    }
                }
                $before ? $sheetData->insertBefore($rowEl, $before) : $sheetData->appendChild($rowEl);
            }

            foreach ($cols as $col => $update) {
                $cellRef = $col.$rowNum;
                $colIndex = $this->columnLetterToIndex($col);

                $cEl = null;
                foreach ($rowEl->getElementsByTagNameNS($ns, 'c') as $existingCell) {
                    if ($existingCell->getAttribute('r') === $cellRef) {
                        $cEl = $existingCell;
                        break;
                    }
                }

                if (! $cEl) {
                    $cEl = $doc->createElementNS($ns, 'c');
                    $cEl->setAttribute('r', $cellRef);

                    $before = null;
                    foreach ($rowEl->getElementsByTagNameNS($ns, 'c') as $existingCell) {
                        if ($this->columnLetterToIndex(preg_replace('/\d+/', '', $existingCell->getAttribute('r'))) > $colIndex) {
                            $before = $existingCell;
                            break;
                        }
                    }
                    $before ? $rowEl->insertBefore($cEl, $before) : $rowEl->appendChild($cEl);
                } else {
                    while ($cEl->firstChild) {
                        $cEl->removeChild($cEl->firstChild);
                    }
                }

                if ($update['type'] === 'n') {
                    $cEl->removeAttribute('t');
                    $cEl->appendChild($doc->createElementNS($ns, 'v', (string) $update['value']));
                } else {
                    $cEl->setAttribute('t', 'inlineStr');
                    $isEl = $doc->createElementNS($ns, 'is');
                    $tEl = $doc->createElementNS($ns, 't');
                    $tEl->appendChild($doc->createTextNode((string) $update['value']));
                    $isEl->appendChild($tEl);
                    $cEl->appendChild($isEl);
                }
            }
        }

        return $doc->saveXML();
    }

    private function columnLetterToIndex(string $col): int
    {
        $index = 0;
        foreach (str_split($col) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }

        return $index;
    }

    /**
     * Remove a line item from a draft purchase order
     */
    public function removeItem(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item)
    {
        if (! in_array($purchaseOrder->status, self::EDITABLE_STATUSES)) {
            return response()->json(['message' => 'Line items can only be removed from draft or submitted purchase orders'], 422);
        }

        if ($item->purchase_order_id !== $purchaseOrder->id) {
            return response()->json(['message' => 'Item does not belong to this purchase order'], 422);
        }

        DB::beginTransaction();
        try {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->on_order_qty = max(0, ($product->on_order_qty ?? 0) - $item->quantity_ordered);
                $product->save();
            }

            $item->delete();

            $purchaseOrder->total_amount = $purchaseOrder->items()->sum('total_cost');
            $purchaseOrder->save();

            DB::commit();

            return response()->json([
                'message' => 'Line item removed successfully',
                'purchase_order' => $purchaseOrder->load(['supplier', 'items.product', 'creator', 'approver']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Failed to remove line item'], 500);
        }
    }
}
