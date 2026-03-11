<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportExportController extends Controller
{
    public function exportProducts(Request $request)
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

        $filename = 'products_export_' . date('Y-m-d_His') . '.csv';
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

    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_shift($rows);

        $results = [
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                if (count($row) !== count($header)) {
                    $results['errors']++;
                    $results['details'][] = "Row {$rowNumber}: Column count mismatch";
                    continue;
                }

                $data = array_combine($header, $row);

                $validator = Validator::make($data, [
                    'SKU' => 'required|max:255',
                    'Description' => 'required|max:255',
                    'Unit Cost' => 'required|numeric|min:0',
                    'Unit Price' => 'required|numeric|min:0',
                    'Quantity On Hand' => 'required|integer|min:0',
                    'Minimum Quantity' => 'required|integer|min:0',
                    'Unit of Measure' => 'required|max:10',
                ]);

                if ($validator->fails()) {
                    $results['errors']++;
                    $results['details'][] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                $product = Product::where('sku', $data['SKU'])->first();

                if ($product) {
                    $product->update([
                        'description' => $data['Description'],
                        'long_description' => $data['Long Description'] ?? null,
                        'category' => $data['Category'] ?? null,
                        'unit_cost' => $data['Unit Cost'],
                        'unit_price' => $data['Unit Price'],
                        'quantity_on_hand' => $data['Quantity On Hand'],
                        'minimum_quantity' => $data['Minimum Quantity'],
                        'maximum_quantity' => $data['Maximum Quantity'] ?? null,
                        'unit_of_measure' => $data['Unit of Measure'],
                        'supplier' => $data['Supplier'] ?? null,
                        'supplier_sku' => $data['Supplier SKU'] ?? null,
                        'lead_time_days' => $data['Lead Time Days'] ?? null,
                        'is_active' => ($data['Active'] ?? 'Yes') === 'Yes',
                    ]);
                    $product->updateStatus();
                    $results['success']++;
                } else {
                    $product = Product::create([
                        'sku' => $data['SKU'],
                        'description' => $data['Description'],
                        'long_description' => $data['Long Description'] ?? null,
                        'category' => $data['Category'] ?? null,
                        'unit_cost' => $data['Unit Cost'],
                        'unit_price' => $data['Unit Price'],
                        'quantity_on_hand' => $data['Quantity On Hand'],
                        'minimum_quantity' => $data['Minimum Quantity'],
                        'maximum_quantity' => $data['Maximum Quantity'] ?? null,
                        'unit_of_measure' => $data['Unit of Measure'],
                        'supplier' => $data['Supplier'] ?? null,
                        'supplier_sku' => $data['Supplier SKU'] ?? null,
                        'lead_time_days' => $data['Lead Time Days'] ?? null,
                        'is_active' => ($data['Active'] ?? 'Yes') === 'Yes',
                    ]);
                    $product->updateStatus();
                    $results['success']++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import completed',
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportOrders(Request $request)
    {
        $query = Order::with(['items.product']);

        if ($request->has('from_date')) {
            $query->where('order_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('order_date', '<=', $request->to_date);
        }

        $orders = $query->get();

        $filename = 'orders_export_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($orders) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Order Number', 'Customer Name', 'Customer Email', 'Status',
                'Priority', 'Order Date', 'Expected Ship Date', 'Subtotal',
                'Total', 'SKU', 'Product', 'Quantity', 'Unit Price', 'Line Total',
            ]);

            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    fputcsv($file, [
                        $order->order_number,
                        $order->customer_name,
                        $order->customer_email,
                        $order->status,
                        $order->priority,
                        $order->order_date->format('Y-m-d'),
                        $order->expected_ship_date?->format('Y-m-d'),
                        $order->subtotal,
                        $order->total,
                        $item->product->sku,
                        $item->product->description,
                        $item->quantity,
                        $item->unit_price,
                        $item->line_total,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadTemplate(Request $request)
    {
        $type = $request->get('type', 'products');

        $filename = $type . '_import_template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($type) {
            $file = fopen('php://output', 'w');
            
            if ($type === 'products') {
                fputcsv($file, [
                    'SKU', 'Description', 'Long Description', 'Category',
                    'Unit Cost', 'Unit Price', 'Quantity On Hand', 'Minimum Quantity',
                    'Maximum Quantity', 'Unit of Measure', 'Supplier', 'Supplier SKU',
                    'Lead Time Days', 'Active',
                ]);

                // Sample row
                fputcsv($file, [
                    'SAMPLE-001', 'Sample Product', 'Detailed description here',
                    'Hardware', '10.00', '25.00', '100', '20',
                    '500', 'EA', 'Acme Supplies', 'ACME-123', '14', 'Yes',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}