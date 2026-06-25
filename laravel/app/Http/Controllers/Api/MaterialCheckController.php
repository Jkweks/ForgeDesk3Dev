<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\JobReservation;
use App\Models\JobReservationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class MaterialCheckController extends Controller
{
    /**
     * In-memory product cache to avoid N+1 queries
     */
    private $productCache = [];

    /**
     * Test endpoint
     */
    public function test()
    {
        return response()->json([
            'message' => 'MaterialCheckController is working',
            'phpspreadsheet_installed' => class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory'),
        ]);
    }

    /**
     * Commit materials to a job reservation
     */
    public function commitMaterials(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'job_number' => 'required|string|max:100',
                'release_number' => 'nullable|integer|min:1',
                'job_name' => 'required|string|max:255',
                'requested_by' => 'required|string|max:255',
                'needed_by' => 'nullable|date',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.part_number' => 'required|string',
                'items.*.finish' => 'nullable|string',
                'items.*.sku' => 'nullable|string',
                'items.*.requested_qty' => 'required|integer|min:1',
                'items.*.committed_qty' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors(),
                ], 422);
            }

            // Start transaction
            DB::beginTransaction();

            try {
                // Create job reservation — release_number auto-assigned by model if not provided
                $reservation = JobReservation::create([
                    'job_number' => $request->job_number,
                    'release_number' => $request->release_number ?: null,
                    'job_name' => $request->job_name,
                    'requested_by' => $request->requested_by,
                    'needed_by' => $request->needed_by,
                    'notes' => $request->notes,
                    'status' => 'active',
                ]);

                $itemsData = [];
                $totalRequested = 0;
                $totalCommitted = 0;

                // Merge duplicate product_ids by summing quantities (e.g. same product in multiple estimate rows)
                $mergedItems = [];
                foreach ($request->items as $item) {
                    $pid = $item['product_id'];
                    if (isset($mergedItems[$pid])) {
                        $mergedItems[$pid]['requested_qty'] += $item['requested_qty'];
                        $mergedItems[$pid]['committed_qty'] += $item['committed_qty'];
                    } else {
                        $mergedItems[$pid] = $item;
                    }
                }

                // Create reservation items
                foreach ($mergedItems as $item) {
                    // Get current availability before commitment
                    $product = Product::find($item['product_id']);
                    $availableBefore = $product->quantity_available;

                    // Create reservation item
                    JobReservationItem::create([
                        'reservation_id' => $reservation->id,
                        'product_id' => $item['product_id'],
                        'requested_qty' => $item['requested_qty'],
                        'committed_qty' => $item['committed_qty'],
                        'consumed_qty' => 0,
                    ]);

                    // Refresh product to get updated availability (view recalculates)
                    $product->refresh();
                    $availableAfter = $product->quantity_available;

                    $totalRequested += $item['requested_qty'];
                    $totalCommitted += $item['committed_qty'];

                    $itemsData[] = [
                        'product_id' => $item['product_id'],
                        'part_number' => $item['part_number'],
                        'finish' => $item['finish'],
                        'sku' => $item['sku'],
                        'description' => $product->description,
                        'requested_qty' => $item['requested_qty'],
                        'committed_qty' => $item['committed_qty'],
                        'available_before' => $availableBefore,
                        'available_after' => $availableAfter,
                        'location' => $product->location,
                    ];
                }

                DB::commit();

                Log::info('Material commitment created', [
                    'reservation_id' => $reservation->id,
                    'job_number' => $reservation->job_number,
                    'release_number' => $reservation->release_number,
                    'items_count' => count($itemsData),
                ]);

                return response()->json([
                    'message' => 'Materials committed successfully',
                    'reservation' => [
                        'id' => $reservation->id,
                        'job_number' => $reservation->job_number,
                        'release_number' => $reservation->release_number,
                        'job_name' => $reservation->job_name,
                        'status' => $reservation->status,
                        'total_requested' => $totalRequested,
                        'total_committed' => $totalCommitted,
                    ],
                    'items' => $itemsData,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Material commitment failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Failed to commit materials',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check materials from uploaded estimate file against inventory
     */
    public function checkMaterials(Request $request)
    {
        try {
            // Enable error logging to file
            $debugLog = storage_path('logs/material-check-debug.log');
            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Material check started\n", FILE_APPEND);

            Log::info('Material check started');

            // Check if file was uploaded
            if (!$request->hasFile('file')) {
                @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] No file in request\n", FILE_APPEND);
                return response()->json([
                    'error' => 'No file uploaded',
                    'request_has_file' => $request->hasFile('file'),
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'file'  => 'required|file|mimes:xlsx,xlsm,csv|max:10240',
                'file2' => 'nullable|file|mimes:xlsx,xlsm,csv|max:10240',
                'mode'  => 'nullable|string|in:ez_estimate,generic',
            ]);

            if ($validator->fails()) {
                @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Validation failed\n", FILE_APPEND);
                Log::warning('Validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors(),
                ], 422);
            }

            $file     = $request->file('file');
            $mode     = $request->input('mode', 'ez_estimate');
            $file1Ext = strtolower($file->getClientOriginalExtension());
            $boneyardSharedOnly = filter_var($request->input('boneyard_shared_only', false), FILTER_VALIDATE_BOOLEAN);

            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] File: {$file->getClientOriginalName()}, Size: {$file->getSize()}, Ext: {$file1Ext}\n", FILE_APPEND);
            Log::info('File received', ['name' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'ext' => $file1Ext]);

            ini_set('memory_limit', '512M');

            // Legacy generic mode — single Excel file, early return
            if ($mode !== 'ez_estimate' && $file1Ext !== 'csv') {
                @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Processing generic\n", FILE_APPEND);
                $reader = IOFactory::createReaderForFile($file->getRealPath());
                $reader->setReadDataOnly(true);
                $reader->setReadFilter(new EzEstimateReadFilter());
                $spreadsheet = $reader->load($file->getRealPath());
                return $this->checkGenericEstimate($request, $spreadsheet);
            }

            // Process file 1 (CSV or EZ Estimate)
            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Processing file 1 as " . ($file1Ext === 'csv' ? 'CSV' : 'EZ Estimate') . "\n", FILE_APPEND);
            if ($file1Ext === 'csv') {
                $data1 = $this->runCsvEstimateCheck($file, $boneyardSharedOnly);
            } else {
                $reader = IOFactory::createReaderForFile($file->getRealPath());
                $reader->setReadDataOnly(true);
                $reader->setReadFilter(new EzEstimateReadFilter());
                $spreadsheet = $reader->load($file->getRealPath());
                @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Spreadsheet loaded (memory: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB)\n", FILE_APPEND);
                $data1 = $this->runEzEstimateCheck($spreadsheet, $boneyardSharedOnly);
            }

            // Process optional file 2
            if ($request->hasFile('file2')) {
                $file2     = $request->file('file2');
                $file2Ext  = strtolower($file2->getClientOriginalExtension());
                @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Loading file 2: {$file2->getClientOriginalName()} ({$file2Ext})\n", FILE_APPEND);
                Log::info('Loading file 2', ['name' => $file2->getClientOriginalName(), 'ext' => $file2Ext]);

                if ($file2Ext === 'csv') {
                    $data2 = $this->runCsvEstimateCheck($file2, $boneyardSharedOnly);
                } else {
                    $reader2 = IOFactory::createReaderForFile($file2->getRealPath());
                    $reader2->setReadDataOnly(true);
                    $reader2->setReadFilter(new EzEstimateReadFilter());
                    $spreadsheet2 = $reader2->load($file2->getRealPath());
                    @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Second spreadsheet loaded\n", FILE_APPEND);
                    $data2 = $this->runEzEstimateCheck($spreadsheet2, $boneyardSharedOnly);
                }

                $mergedResults = $this->mergeCheckResults($data1['results'], $data2['results']);
                $mergedSummary = $this->rebuildSummary($mergedResults);

                return response()->json([
                    'message'              => 'Material check completed',
                    'summary'              => $mergedSummary,
                    'results'              => $mergedResults,
                    'boneyard_shared_only' => $boneyardSharedOnly,
                    'file_count'           => 2,
                    'file1_name'           => $file->getClientOriginalName(),
                    'file2_name'           => $file2->getClientOriginalName(),
                ]);
            }

            return response()->json([
                'message'              => 'Material check completed',
                'summary'              => $data1['summary'],
                'results'              => $data1['results'],
                'boneyard_shared_only' => $boneyardSharedOnly,
                'file_count'           => 1,
                'file1_name'           => $file->getClientOriginalName(),
            ]);

        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] PhpSpreadsheet error: {$e->getMessage()}\n", FILE_APPEND);
            Log::error('Excel file read error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Failed to read the Excel file: ' . $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], 500);
        } catch (\Exception $e) {
            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Exception: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n", FILE_APPEND);
            Log::error('Material check error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Material check failed: ' . $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'trace_preview' => substr($e->getTraceAsString(), 0, 500),
            ], 500);
        } catch (\Throwable $e) {
            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Fatal error: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n", FILE_APPEND);
            return response()->json([
                'error' => 'Fatal error: ' . $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Build the in-memory product lookup cache (no-op if already populated).
     * Shared by both runEzEstimateCheck and runCsvEstimateCheck so two-file
     * requests only hit the DB once.
     */
    private function buildProductCache(bool $boneyardSharedOnly): void
    {
        if (!empty($this->productCache)) {
            return;
        }

        Log::info('Loading products into memory cache', ['boneyard_shared_only' => $boneyardSharedOnly]);
        $productQuery = Product::where('is_active', true);
        if ($boneyardSharedOnly) {
            $productQuery->where(function ($q) {
                $q->where('nonsof', false)->orWhere('is_shared', true);
            });
        }
        $allProducts = $productQuery->get();

        foreach ($allProducts as $product) {
            $sku = $product->sku;
            $this->productCache[$sku] = $product;
            $this->productCache[strtolower($sku)] = $product;
            $normalized = str_replace([' ', '-', '_'], '', $sku);
            $this->productCache[$normalized] = $product;
        }
        Log::info('Product cache built', ['products' => $allProducts->count()]);
    }

    /**
     * Process an EZ Estimate spreadsheet and return raw data (no HTTP response).
     * Used internally so results from two files can be merged before responding.
     */
    private function runEzEstimateCheck($spreadsheet, bool $boneyardSharedOnly): array
    {
        $results = [];
        $summary = ['total' => 0, 'available' => 0, 'partial' => 0, 'unavailable' => 0, 'not_found' => 0];

        $this->buildProductCache($boneyardSharedOnly);

        $allSheets = $spreadsheet->getAllSheets();
        $stockLengthsSheets = [];
        $accessoriesSheets  = [];

        foreach ($allSheets as $sheet) {
            $title = $sheet->getTitle();
            if (stripos($title, 'Stock Lengths') === 0) {
                $stockLengthsSheets[] = $sheet;
            } elseif (stripos($title, 'Accessories') === 0) {
                $accessoriesSheets[] = $sheet;
            }
        }

        Log::info('Found sheets', ['stock_lengths' => count($stockLengthsSheets), 'accessories' => count($accessoriesSheets)]);

        foreach ($stockLengthsSheets as $sheet) {
            Log::info('Processing Stock Lengths sheet', ['name' => $sheet->getTitle()]);
            $this->processEzEstimateSheet($sheet, 11, 47, $results, $summary, $sheet->getTitle());
        }
        foreach ($accessoriesSheets as $sheet) {
            Log::info('Processing Accessories sheet', ['name' => $sheet->getTitle()]);
            $this->processEzEstimateSheet($sheet, 11, 46, $results, $summary, $sheet->getTitle());
        }

        return ['results' => $results, 'summary' => $summary];
    }

    /**
     * Merge results from two EZ Estimate files.
     * Items with the same SKU have their required quantities summed;
     * status and shortage are recalculated against the combined total.
     * A 'file' key (1, 2, or 'both') is added to each item.
     */
    private function mergeCheckResults(array $results1, array $results2): array
    {
        $merged = [];
        $index  = [];

        foreach ($results1 as $item) {
            $key = $item['sku'] ?? $item['part_number'];
            $item['file'] = 1;
            $index[$key]  = count($merged);
            $merged[]     = $item;
        }

        foreach ($results2 as $item) {
            $key = $item['sku'] ?? $item['part_number'];
            if (isset($index[$key])) {
                $i = $index[$key];
                $merged[$i]['required_qty_packs']  = ($merged[$i]['required_qty_packs']  ?? 0) + ($item['required_qty_packs']  ?? 0);
                $merged[$i]['required_qty_eaches'] = ($merged[$i]['required_qty_eaches'] ?? 0) + ($item['required_qty_eaches'] ?? 0);
                $merged[$i]['file'] = 'both';
                // Recalculate shortage and status against combined requirement
                $availEaches    = $merged[$i]['available_qty_eaches'] ?? 0;
                $reqEaches      = $merged[$i]['required_qty_eaches'];
                $shortageEaches = max(0, $reqEaches - $availEaches);
                $packSize       = $merged[$i]['pack_size'] ?? 1;
                $hasPacks       = $packSize > 1;
                $merged[$i]['shortage_eaches'] = $shortageEaches;
                $merged[$i]['shortage_packs']  = $hasPacks ? (int) ceil($shortageEaches / $packSize) : $shortageEaches;
                if ($merged[$i]['status'] !== 'not_found') {
                    if ($availEaches <= 0) {
                        $merged[$i]['status'] = 'unavailable';
                    } elseif ($availEaches < $reqEaches) {
                        $merged[$i]['status'] = 'partial';
                    } else {
                        $merged[$i]['status'] = 'available';
                    }
                }
            } else {
                $item['file'] = 2;
                $index[$key]  = count($merged);
                $merged[]     = $item;
            }
        }

        return $merged;
    }

    /**
     * Rebuild summary counts from a (possibly merged) results array.
     */
    private function rebuildSummary(array $results): array
    {
        $summary = ['total' => 0, 'available' => 0, 'partial' => 0, 'unavailable' => 0, 'not_found' => 0];
        foreach ($results as $item) {
            $summary['total']++;
            $status = $item['status'] ?? 'not_found';
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            } else {
                $summary['not_found']++;
            }
        }
        return $summary;
    }

    /**
     * Returns the effective value of a cell: for formula cells, returns the
     * Excel-cached calculated value (getOldCalculatedValue) to avoid re-evaluating
     * cross-sheet references that aren't loaded. For plain cells, returns getValue().
     */
    private function getCellEffectiveValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): mixed
    {
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            return $cell->getOldCalculatedValue();
        }
        return $cell->getValue();
    }

    /**
     * Process a single EZ Estimate sheet
     * Columns: A=Qty (in packs, decimals allowed), B=Part Number, C=Finish
     *
     * Quantities are entered in packs (e.g., 0.86 = 86 eaches from a 100-pack)
     * Availability is compared in eaches but displayed in both packs and eaches
     */
    private function processEzEstimateSheet($sheet, $startRow, $endRow, &$results, &$summary, $sheetName)
    {
        $debugLog = storage_path('logs/material-check-debug.log');

        // Log first few rows for debugging
        $debugSampleRows = 3;
        $debugCounter = 0;

        for ($row = $startRow; $row <= $endRow; $row++) {
            try {
                $qtyCell = $sheet->getCell("A{$row}");
                $partNumberCell = $sheet->getCell("B{$row}");
                $finishCell = $sheet->getCell("C{$row}");

                $qtyPacks = floatval($this->getCellEffectiveValue($qtyCell));
                $partNumber = trim((string)$this->getCellEffectiveValue($partNumberCell));
                $finish = trim((string)$this->getCellEffectiveValue($finishCell));

                // Debug logging for first few rows
                if ($debugCounter < $debugSampleRows) {
                    $cellType = $partNumberCell->getDataType();
                    $isFormula = ($cellType === DataType::TYPE_FORMULA);
                    @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Sample Row {$row} in {$sheetName}: QtyPacks='{$qtyPacks}', Part='{$partNumber}', Finish='{$finish}', IsFormula={$isFormula}\n", FILE_APPEND);
                    $debugCounter++;
                }

                // Skip rows where quantity is 0 or negative
                if ($qtyPacks <= 0) {
                    continue;
                }

                // Skip rows where part number is empty
                if (empty($partNumber)) {
                    continue;
                }

                // Default null/empty finish to "0R" (Mill/Unfinished)
                if (empty($finish)) {
                    $finish = '0R';
                }

                // Combine Part Number and Finish to create SKU (format: PartNumber-Finish)
                $sku = $partNumber . '-' . $finish;

                $summary['total']++;

                // Look up the part in inventory
                $product = $this->findProduct($sku);

                if (!$product) {
                    $results[] = [
                        'part_number' => $partNumber,
                        'finish' => $finish,
                        'sku' => $sku,
                        'description' => '',
                        'required_qty_packs' => $qtyPacks,
                        'required_qty_eaches' => 0,
                        'available_qty_packs' => 0,
                        'available_qty_eaches' => 0,
                        'shortage_packs' => $qtyPacks,
                        'shortage_eaches' => 0,
                        'pack_size' => 1,
                        'status' => 'not_found',
                        'location' => null,
                        'sheet' => $sheetName,
                        'row' => $row,
                    ];
                    $summary['not_found']++;
                    continue;
                }

                // Get pack size (default to 1 if not set)
                $packSize = $product->pack_size ?? 1;
                $hasPackSize = $packSize > 1;

                // Convert required packs to eaches
                // e.g., 0.86 packs * 100 pack_size = 86 eaches
                $requiredEaches = $hasPackSize
                    ? (int) ceil($qtyPacks * $packSize)
                    : (int) ceil($qtyPacks);

                // Calculate availability in eaches (from fulfillment system)
                $availableEaches = $product->quantity_available ?? $product->quantity_on_hand ?? 0;

                // Convert available to packs (full packs only)
                $availablePacks = $hasPackSize
                    ? floor($availableEaches / $packSize)
                    : $availableEaches;

                // Calculate shortage
                $shortageEaches = max(0, $requiredEaches - $availableEaches);
                $shortagePacks = $hasPackSize
                    ? ceil($shortageEaches / $packSize)
                    : $shortageEaches;

                // Determine status based on eaches comparison
                $status = 'available';
                if ($availableEaches <= 0) {
                    $status = 'unavailable';
                    $summary['unavailable']++;
                } elseif ($availableEaches < $requiredEaches) {
                    $status = 'partial';
                    $summary['partial']++;
                } else {
                    $summary['available']++;
                }

                $results[] = [
                    'part_number' => $partNumber,
                    'finish' => $finish,
                    'sku' => $sku,
                    'description' => $product->description,
                    'required_qty_packs' => $qtyPacks,
                    'required_qty_eaches' => $requiredEaches,
                    'on_hand' => $product->quantity_on_hand ?? 0,
                    'committed' => $product->quantity_committed ?? 0,
                    'on_order' => $product->on_order_qty ?? 0,
                    'available_qty_packs' => $availablePacks,
                    'available_qty_eaches' => $availableEaches,
                    'shortage_packs' => $shortagePacks,
                    'shortage_eaches' => $shortageEaches,
                    'pack_size' => $packSize,
                    'has_pack_size' => $hasPackSize,
                    'status' => $status,
                    'location' => $product->location,
                    'product_id' => $product->id,
                    'nonsof' => (bool) $product->nonsof,
                    'is_shared' => (bool) $product->is_shared,
                    'sheet' => $sheetName,
                    'row' => $row,
                ];
            } catch (\Exception $e) {
                // Log error but continue processing other rows
                @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Error processing row {$row} in {$sheetName}: {$e->getMessage()}\n", FILE_APPEND);
                Log::warning("Error processing row", [
                    'sheet' => $sheetName,
                    'row' => $row,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Completed processing {$sheetName}\n", FILE_APPEND);
        Log::info("Completed processing sheet", ['name' => $sheetName]);
    }

    /**
     * Process a CSV estimate file and return raw data (no HTTP response).
     *
     * Expected columns (positional, 0-indexed; first row is header and skipped):
     *   0 – Quantity (eaches)
     *   1 – (ignored)
     *   2 – Part Number
     *   3 – Finish code (optional; defaults to '0R' when absent or blank)
     *
     * SKU is built as "{part_number}-{finish}" to match the EZ Estimate convention.
     */
    private function runCsvEstimateCheck($file, bool $boneyardSharedOnly): array
    {
        $results  = [];
        $summary  = ['total' => 0, 'available' => 0, 'partial' => 0, 'unavailable' => 0, 'not_found' => 0];
        $debugLog = storage_path('logs/material-check-debug.log');

        $this->buildProductCache($boneyardSharedOnly);

        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            throw new \Exception('Could not open CSV file for reading.');
        }

        $rowNumber = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip header row
            if ($rowNumber === 1) {
                @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] CSV header: " . implode(', ', array_map('trim', $row)) . "\n", FILE_APPEND);
                continue;
            }

            $qty        = isset($row[0]) ? floatval(trim($row[0])) : 0;
            $partNumber = isset($row[2]) ? trim($row[2]) : '';
            $finish     = (isset($row[3]) && trim($row[3]) !== '') ? trim($row[3]) : '0R';

            if ($qty <= 0 || empty($partNumber)) {
                continue;
            }

            $sku = $partNumber . '-' . $finish;
            $summary['total']++;

            $product = $this->findProduct($sku);

            if (!$product) {
                $results[] = [
                    'part_number'          => $partNumber,
                    'finish'               => $finish,
                    'sku'                  => $sku,
                    'description'          => '',
                    'required_qty_packs'   => 0,
                    'required_qty_eaches'  => (int) ceil($qty),
                    'on_hand'              => null,
                    'committed'            => null,
                    'on_order'             => null,
                    'available_qty_packs'  => 0,
                    'available_qty_eaches' => 0,
                    'shortage_packs'       => 0,
                    'shortage_eaches'      => (int) ceil($qty),
                    'pack_size'            => 1,
                    'has_pack_size'        => false,
                    'status'               => 'not_found',
                    'location'             => null,
                    'sheet'                => 'CSV',
                    'row'                  => $rowNumber,
                ];
                $summary['not_found']++;
                continue;
            }

            $packSize    = $product->pack_size ?? 1;
            $hasPackSize = $packSize > 1;

            // CSV quantities are in eaches
            $requiredEaches = (int) ceil($qty);
            $requiredPacks  = $hasPackSize ? round($qty / $packSize, 4) : 0;

            $availableEaches = $product->quantity_available ?? $product->quantity_on_hand ?? 0;
            $availablePacks  = $hasPackSize ? (int) floor($availableEaches / $packSize) : $availableEaches;

            $shortageEaches = max(0, $requiredEaches - $availableEaches);
            $shortagePacks  = $hasPackSize ? (int) ceil($shortageEaches / $packSize) : $shortageEaches;

            $status = 'available';
            if ($availableEaches <= 0) {
                $status = 'unavailable';
                $summary['unavailable']++;
            } elseif ($availableEaches < $requiredEaches) {
                $status = 'partial';
                $summary['partial']++;
            } else {
                $summary['available']++;
            }

            $results[] = [
                'part_number'          => $partNumber,
                'finish'               => $finish,
                'sku'                  => $sku,
                'description'          => $product->description,
                'required_qty_packs'   => $requiredPacks,
                'required_qty_eaches'  => $requiredEaches,
                'on_hand'              => $product->quantity_on_hand ?? 0,
                'committed'            => $product->quantity_committed ?? 0,
                'on_order'             => $product->on_order_qty ?? 0,
                'available_qty_packs'  => $availablePacks,
                'available_qty_eaches' => $availableEaches,
                'shortage_packs'       => $shortagePacks,
                'shortage_eaches'      => $shortageEaches,
                'pack_size'            => $packSize,
                'has_pack_size'        => $hasPackSize,
                'status'               => $status,
                'location'             => $product->location,
                'product_id'           => $product->id,
                'nonsof'               => (bool) $product->nonsof,
                'is_shared'            => (bool) $product->is_shared,
                'sheet'                => 'CSV',
                'row'                  => $rowNumber,
            ];
        }

        fclose($handle);

        @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] CSV processed: {$rowNumber} rows, {$summary['total']} items\n", FILE_APPEND);
        Log::info('CSV estimate processed', ['rows' => $rowNumber, 'items' => $summary['total']]);

        return ['results' => $results, 'summary' => $summary];
    }

    /**
     * Check generic estimate file format (original implementation)
     */
    private function checkGenericEstimate(Request $request, $spreadsheet)
    {
            $sheetName = $request->input('sheet_name');
            $headerRow = $request->input('header_row', 1);
            $dataStartRow = $request->input('data_start_row', $headerRow + 1);
            $partNumberColumn = $request->input('part_number_column', 'Part Number');
            $quantityColumn = $request->input('quantity_column', 'Quantity');
            $descriptionColumn = $request->input('description_column', 'Description');

            // Select the worksheet
            if ($sheetName) {
                try {
                    $worksheet = $spreadsheet->getSheetByName($sheetName);
                    if (!$worksheet) {
                        $availableSheets = array_map(fn($sheet) => $sheet->getTitle(), $spreadsheet->getAllSheets());
                        return response()->json([
                            'error' => "Sheet '{$sheetName}' not found. Available sheets: " . implode(', ', $availableSheets),
                        ], 400);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => "Failed to load sheet '{$sheetName}': " . $e->getMessage(),
                    ], 400);
                }
            } else {
                $worksheet = $spreadsheet->getActiveSheet();
            }

            $rows = $worksheet->toArray();
            Log::info('Spreadsheet loaded', ['sheet' => $worksheet->getTitle(), 'row_count' => count($rows)]);

            if (empty($rows)) {
                return response()->json([
                    'error' => 'The uploaded file is empty',
                ], 400);
            }

            // Get headers from specified row (1-indexed)
            if ($headerRow > count($rows)) {
                return response()->json([
                    'error' => "Header row {$headerRow} is beyond the file's row count (" . count($rows) . ")",
                ], 400);
            }

            $headers = $rows[$headerRow - 1]; // Convert to 0-indexed
            $headers = array_map(fn($h) => trim((string)$h), $headers);

            // Remove rows before data start
            $rows = array_slice($rows, $dataStartRow - 1); // Convert to 0-indexed

            // Find column indexes - support both column names and letters (A, B, C, etc.)
            $partNumberIndex = $this->resolveColumnIndex($partNumberColumn, $headers);
            $quantityIndex = $this->resolveColumnIndex($quantityColumn, $headers);
            $descriptionIndex = $this->resolveColumnIndex($descriptionColumn, $headers);

            if ($partNumberIndex === false) {
                return response()->json([
                    'error' => "Column '{$partNumberColumn}' not found in the file. Available columns: " . implode(', ', array_filter($headers)),
                ], 400);
            }

            if ($quantityIndex === false) {
                return response()->json([
                    'error' => "Column '{$quantityColumn}' not found in the file. Available columns: " . implode(', ', array_filter($headers)),
                ], 400);
            }

            // Process each row
            $results = [];
            $summary = [
                'total' => 0,
                'available' => 0,
                'partial' => 0,
                'unavailable' => 0,
                'not_found' => 0,
            ];

            foreach ($rows as $rowIndex => $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $partNumber = isset($row[$partNumberIndex]) ? trim($row[$partNumberIndex]) : null;
                $requiredQty = isset($row[$quantityIndex]) ? floatval($row[$quantityIndex]) : 0;
                $description = ($descriptionIndex !== false && isset($row[$descriptionIndex]))
                    ? trim($row[$descriptionIndex])
                    : null;

                // Skip if no part number or quantity
                if (empty($partNumber) || $requiredQty <= 0) {
                    continue;
                }

                $summary['total']++;

                // Look up the part in inventory
                $product = $this->findProduct($partNumber);

                if (!$product) {
                    $results[] = [
                        'part_number' => $partNumber,
                        'description' => $description,
                        'required_quantity' => $requiredQty,
                        'available_quantity' => 0,
                        'shortage' => $requiredQty,
                        'status' => 'not_found',
                        'location' => null,
                    ];
                    $summary['not_found']++;
                    continue;
                }

                // Calculate availability
                $availableQty = $product->quantity_available ?? $product->quantity_on_hand ?? 0;
                $shortage = max(0, $requiredQty - $availableQty);

                // Determine status
                $status = 'available';
                if ($availableQty <= 0) {
                    $status = 'unavailable';
                    $summary['unavailable']++;
                } elseif ($availableQty < $requiredQty) {
                    $status = 'partial';
                    $summary['partial']++;
                } else {
                    $summary['available']++;
                }

                $results[] = [
                    'part_number' => $partNumber,
                    'description' => $description ?: $product->description,
                    'required_quantity' => $requiredQty,
                    'available_quantity' => $availableQty,
                    'shortage' => $shortage,
                    'status' => $status,
                    'location' => $product->location,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                ];
            }

            return response()->json([
                'message' => 'Material check completed',
                'summary' => $summary,
                'results' => $results,
            ]);
    }

    /**
     * Resolve column reference to array index
     * Supports column names ("Part Number") or column letters ("A", "I", "AA", etc.)
     */
    private function resolveColumnIndex(string $column, array $headers)
    {
        // Try exact name match first
        $index = array_search($column, $headers);
        if ($index !== false) {
            return $index;
        }

        // Try case-insensitive name match
        $lowerColumn = strtolower($column);
        foreach ($headers as $idx => $header) {
            if (strtolower($header) === $lowerColumn) {
                return $idx;
            }
        }

        // Try as column letter (A, B, C... AA, AB, etc.)
        $letterIndex = $this->columnLetterToIndex($column);
        if ($letterIndex !== false && $letterIndex < count($headers)) {
            return $letterIndex;
        }

        return false;
    }

    /**
     * Convert Excel column letter to zero-based array index
     * A=0, B=1, ... Z=25, AA=26, AB=27, etc.
     */
    private function columnLetterToIndex(string $letter): int|false
    {
        $letter = strtoupper(trim($letter));

        if ($letter === '' || !ctype_alpha($letter)) {
            return false;
        }

        $index = 0;
        $length = strlen($letter);

        for ($i = 0; $i < $length; $i++) {
            $char = ord($letter[$i]);
            if ($char < 65 || $char > 90) { // A-Z
                return false;
            }
            $index = ($index * 26) + ($char - 64);
        }

        return $index - 1; // Convert to 0-based index
    }

    /**
     * Generate a PDF report from material check results
     */
    public function generateReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'results'            => 'required|array',
            'summary'            => 'nullable|array',
            'title'              => 'nullable|string|max:255',
            'boneyard_shared_only' => 'nullable|boolean',
            'file_count'         => 'nullable|integer',
            'file1_name'         => 'nullable|string|max:255',
            'file2_name'         => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $results = $request->input('results', []);
        $summary = $request->input('summary', [
            'total' => count($results),
            'available' => 0,
            'partial' => 0,
            'unavailable' => 0,
            'not_found' => 0,
        ]);
        $title             = $request->input('title', 'Material Check Report');
        $boneyardSharedOnly = $request->boolean('boneyard_shared_only', false);
        $fileCount         = $request->integer('file_count', 1);
        $file1Name         = $request->input('file1_name', '');
        $file2Name         = $request->input('file2_name', '');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.material-check-report', [
            'results'            => $results,
            'summary'            => $summary,
            'title'              => $title,
            'boneyard_shared_only' => $boneyardSharedOnly,
            'file_count'         => $fileCount,
            'file1_name'         => $file1Name,
            'file2_name'         => $file2Name,
            'generated_at'       => now()->format('F d, Y H:i'),
        ]);

        $pdf->setPaper('letter', 'landscape');

        $filename = 'material-check-report-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Find a product by part number/SKU using in-memory cache
     * Tries multiple approaches to find the best match
     */
    private function findProduct($partNumber)
    {
        // Try exact match on SKU first
        if (isset($this->productCache[$partNumber])) {
            return $this->productCache[$partNumber];
        }

        // Try case-insensitive match on SKU
        $lowerPartNumber = strtolower($partNumber);
        if (isset($this->productCache[$lowerPartNumber])) {
            return $this->productCache[$lowerPartNumber];
        }

        // Try removing leading zeros
        $cleanPartNumber = ltrim($partNumber, '0');
        if (isset($this->productCache[$cleanPartNumber])) {
            return $this->productCache[$cleanPartNumber];
        }

        // Try matching with spaces and dashes removed
        $normalizedPartNumber = str_replace([' ', '-', '_'], '', $partNumber);
        if (isset($this->productCache[$normalizedPartNumber])) {
            return $this->productCache[$normalizedPartNumber];
        }

        return null;
    }
}

/**
 * Read filter to only load Stock Lengths and Accessories sheets
 * Only loads columns A-C in the specific row ranges
 */
class EzEstimateReadFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
{
    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        // Only load sheets that start with "Stock Lengths" or "Accessories"
        if (stripos($worksheetName, 'Stock Lengths') !== 0 && stripos($worksheetName, 'Accessories') !== 0) {
            return false;
        }

        // Only load columns A, B, C
        if (!in_array($columnAddress, ['A', 'B', 'C'])) {
            return false;
        }

        // For Stock Lengths sheets: load rows 11-47
        if (stripos($worksheetName, 'Stock Lengths') === 0) {
            return ($row >= 11 && $row <= 47);
        }

        // For Accessories sheets: load rows 11-46
        if (stripos($worksheetName, 'Accessories') === 0) {
            return ($row >= 11 && $row <= 46);
        }

        return false;
    }
}
