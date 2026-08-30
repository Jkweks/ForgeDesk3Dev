<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\InventoryTransaction;
use App\Models\StorageLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
            'po_number'   => 'nullable|string|max:50|unique:purchase_orders,po_number',
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
                $product->on_order_qty = max(0, ($product->on_order_qty ?? 0) - $quantityToReceive);
                $product->save();

                // Update storage location quantity, then recalculate quantity_on_hand
                // from inventory_locations (the canonical source of truth) instead of
                // incrementing quantity_on_hand directly — this self-corrects any
                // pre-existing drift instead of compounding it.
                if (!empty($itemData['storage_location_id'])) {
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
                            'quantity'            => $quantityToReceive,
                            'is_primary'          => true,
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

    /**
     * Add a line item to a draft purchase order
     */
    public function addItem(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json(['message' => 'Line items can only be added to draft purchase orders'], 422);
        }

        $validator = Validator::make($request->all(), [
            'product_id'           => 'required|exists:products,id',
            'quantity'             => 'required|integer|min:1',
            'unit_cost'            => 'required|numeric|min:0',
            'destination_location' => 'nullable|string',
            'notes'                => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $item = $purchaseOrder->items()->create([
                'product_id'           => $request->product_id,
                'quantity_ordered'     => $request->quantity,
                'quantity_received'    => 0,
                'unit_cost'            => $request->unit_cost,
                'total_cost'           => $request->quantity * $request->unit_cost,
                'destination_location' => $request->destination_location,
                'notes'                => $request->notes,
            ]);

            $product = Product::find($request->product_id);
            $product->on_order_qty = ($product->on_order_qty ?? 0) + $request->quantity;
            $product->save();

            $purchaseOrder->total_amount = $purchaseOrder->items()->sum('total_cost');
            $purchaseOrder->save();

            DB::commit();

            return response()->json([
                'message'        => 'Line item added successfully',
                'purchase_order' => $purchaseOrder->load(['supplier', 'items.product', 'creator', 'approver']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add line item'], 500);
        }
    }

    /**
     * Export a Tubelite purchase order as an EZ Estimate-format Excel file
     */
    public function exportEzEstimate(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.product']);

        if (!str_contains(strtolower($purchaseOrder->supplier->name ?? ''), 'tubelite')) {
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

        if (!file_exists($templatePath)) {
            \Log::error('EZ Estimate export failed: template file missing', ['path' => $templatePath]);

            return response()->json(['message' => 'EZ Estimate template is missing on the server'], 500);
        }

        try {
            // The EZ Estimate template is a ~40-sheet, formula-heavy workbook;
            // loading/writing it through PhpSpreadsheet peaks around 650MB, well
            // above the app's default memory_limit.
            @ini_set('memory_limit', '1024M');

            $reader = IOFactory::createReaderForFile($templatePath);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($templatePath);

            // Stock Lengths: 3 pages, input rows 11-47 (37 rows/page), columns A=Qty, B=Part#, C=Finish
            $this->fillEzEstimateSheets(
                $spreadsheet,
                ['Stock Lengths', 'Stock Lengths (2)', 'Stock Lengths (3)'],
                11,
                47,
                $slItems
            );

            // Accessories: 3 pages, input rows 11-46 (36 rows/page), columns A=Qty, B=Part#, C=Finish
            $this->fillEzEstimateSheets(
                $spreadsheet,
                ['Accessories', 'Accessories (2)', 'Accessories (3)'],
                11,
                46,
                $accessoryItems
            );

            $spreadsheet->setActiveSheetIndexByName('Stock Lengths');

            $filename = 'EZ_Estimate_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $purchaseOrder->po_number) . '_' . date('Ymd') . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), 'po_ez_');

            $writer = new Xlsx($spreadsheet);
            // This workbook's formula graph is too large/complex for PhpSpreadsheet's
            // calculation engine to re-evaluate reliably on save (it errors out deep in
            // unrelated sheets). Skip recalculation entirely and let Excel recalculate
            // on open — we only need our overwritten cells to carry through as values.
            $writer->setPreCalculateFormulas(false);
            $writer->save($tempFile);

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
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
     * Write Qty/Part#/Finish values into consecutive EZ Estimate template pages
     * (Stock Lengths or Accessories, each spanning up to 3 sheets).
     *
     * These cells are formula-driven in the template (they feed a separate
     * internal Tubelite tool via the CALCULATIONS sheet), but for this export
     * they're overwritten directly with values from the purchase order. The
     * remaining columns (description/price/etc.) are left as live formulas
     * so pricing still recalculates from the template's own price sheets.
     */
    private function fillEzEstimateSheets(Spreadsheet $spreadsheet, array $sheetNames, int $startRow, int $endRow, array $items): void
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
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (!$sheet) {
                throw new \Exception("EZ Estimate template sheet not found: {$sheetName}");
            }

            for ($row = $startRow; $row <= $endRow && $itemIndex < count($items); $row++, $itemIndex++) {
                $item = $items[$itemIndex];
                $product = $item->product;

                $sheet->setCellValue("A{$row}", $item->quantity_ordered);
                $sheet->setCellValue("B{$row}", $product->part_number ?? $product->sku ?? '');
                $sheet->setCellValue("C{$row}", $product->finish ?? '');
            }
        }
    }

    /**
     * Remove a line item from a draft purchase order
     */
    public function removeItem(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item)
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json(['message' => 'Line items can only be removed from draft purchase orders'], 422);
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
                'message'        => 'Line item removed successfully',
                'purchase_order' => $purchaseOrder->load(['supplier', 'items.product', 'creator', 'approver']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to remove line item'], 500);
        }
    }
}
