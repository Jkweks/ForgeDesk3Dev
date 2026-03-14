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
                'release_number' => 'required|integer|min:1',
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

            // Check for duplicate job_number + release_number
            $exists = JobReservation::where('job_number', $request->job_number)
                ->where('release_number', $request->release_number)
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => 'Duplicate job reservation',
                    'message' => "Job {$request->job_number} Release {$request->release_number} already exists",
                ], 422);
            }

            // Start transaction
            DB::beginTransaction();

            try {
                // Create job reservation
                $reservation = JobReservation::create([
                    'job_number' => $request->job_number,
                    'release_number' => $request->release_number,
                    'job_name' => $request->job_name,
                    'requested_by' => $request->requested_by,
                    'needed_by' => $request->needed_by,
                    'notes' => $request->notes,
                    'status' => 'active',
                ]);

                $itemsData = [];
                $totalRequested = 0;
                $totalCommitted = 0;

                // Create reservation items
                foreach ($request->items as $item) {
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
                'file' => 'required|file|mimes:xlsx,xlsm|max:10240',
                'mode' => 'nullable|string|in:ez_estimate,generic',
            ]);

            if ($validator->fails()) {
                @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Validation failed\n", FILE_APPEND);
                Log::warning('Validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors(),
                ], 422);
            }

            $file = $request->file('file');
            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] File: {$file->getClientOriginalName()}, Size: {$file->getSize()}\n", FILE_APPEND);
            Log::info('File received', ['name' => $file->getClientOriginalName(), 'size' => $file->getSize()]);

            $mode = $request->input('mode', 'ez_estimate');
            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Mode: {$mode}\n", FILE_APPEND);

            // Load the spreadsheet with optimized filter
            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Loading spreadsheet with optimized filter...\n", FILE_APPEND);
            Log::info('Loading spreadsheet with filter');

            // Increase memory limit for large files (keep for entire request)
            ini_set('memory_limit', '512M');
            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Memory limit increased to 512M\n", FILE_APPEND);

            // Create reader and apply custom read filter
            // This only loads Stock Lengths & Accessories sheets, columns A-C, rows 11-47/11-46
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new EzEstimateReadFilter());

            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Reader created with filter, loading file...\n", FILE_APPEND);
            $spreadsheet = $reader->load($file->getRealPath());
            @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Spreadsheet loaded (memory: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB)\n", FILE_APPEND);

            if ($mode === 'ez_estimate') {
                @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Processing EZ Estimate\n", FILE_APPEND);
                return $this->checkEzEstimate($spreadsheet);
            } else {
                @file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Processing generic\n", FILE_APPEND);
                return $this->checkGenericEstimate($request, $spreadsheet);
            }

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
     * Check EZ Estimate file format
     * Reads from Stock Lengths and Accessories sheets
     */
    private function checkEzEstimate($spreadsheet)
    {
        $results = [];
        $summary = [
            'total' => 0,
            'available' => 0,
            'partial' => 0,
            'unavailable' => 0,
            'not_found' => 0,
        ];

        // Load ALL products into memory once (avoid N+1 query problem)
        Log::info('Loading all products into memory cache');
        $allProducts = Product::where('is_active', true)->get();

        // Build lookup indexes for fast searching
        $this->productCache = [];
        foreach ($allProducts as $product) {
            $sku = $product->sku;
            // Index by exact SKU
            $this->productCache[$sku] = $product;
            // Index by lowercase SKU
            $this->productCache[strtolower($sku)] = $product;
            // Index by normalized SKU (no spaces, dashes, underscores)
            $normalized = str_replace([' ', '-', '_'], '', $sku);
            $this->productCache[$normalized] = $product;
        }
        Log::info('Product cache built', ['products' => $allProducts->count()]);

        // Get all sheets
        $allSheets = $spreadsheet->getAllSheets();
        $stockLengthsSheets = [];
        $accessoriesSheets = [];

        foreach ($allSheets as $sheet) {
            $title = $sheet->getTitle();
            if (stripos($title, 'Stock Lengths') === 0) {
                $stockLengthsSheets[] = $sheet;
            } elseif (stripos($title, 'Accessories') === 0) {
                $accessoriesSheets[] = $sheet;
            }
        }

        Log::info('Found sheets', [
            'stock_lengths' => count($stockLengthsSheets),
            'accessories' => count($accessoriesSheets),
        ]);

        // Process Stock Lengths sheets (A11:C47)
        foreach ($stockLengthsSheets as $sheet) {
            Log::info('Processing Stock Lengths sheet', ['name' => $sheet->getTitle()]);
            $this->processEzEstimateSheet(
                $sheet,
                11,  // Start row
                47,  // End row
                $results,
                $summary,
                $sheet->getTitle()
            );
        }

        // Process Accessories sheets (A11:C46)
        foreach ($accessoriesSheets as $sheet) {
            Log::info('Processing Accessories sheet', ['name' => $sheet->getTitle()]);
            $this->processEzEstimateSheet(
                $sheet,
                11,  // Start row
                46,  // End row
                $results,
                $summary,
                $sheet->getTitle()
            );
        }

        return response()->json([
            'message' => 'Material check completed',
            'summary' => $summary,
            'results' => $results,
        ]);
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
                // Use getValue() instead of getCalculatedValue() to avoid formula calculation hangs
                // For .xlsm files with complex formulas, getCalculatedValue() can hang indefinitely
                // getValue() returns the cached calculated value from when the file was last saved
                $qtyCell = $sheet->getCell("A{$row}");
                $partNumberCell = $sheet->getCell("B{$row}");
                $finishCell = $sheet->getCell("C{$row}");

                $qtyPacks = floatval($qtyCell->getValue()); // Qty is in packs (can be decimal like 0.86)
                $partNumber = trim((string)$partNumberCell->getValue());
                $finish = trim((string)$finishCell->getValue());

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

                // Skip rows where part number is a formula (not user input)
                if ($partNumberCell->getDataType() === DataType::TYPE_FORMULA) {
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
                    'available_qty_packs' => $availablePacks,
                    'available_qty_eaches' => $availableEaches,
                    'shortage_packs' => $shortagePacks,
                    'shortage_eaches' => $shortageEaches,
                    'pack_size' => $packSize,
                    'has_pack_size' => $hasPackSize,
                    'status' => $status,
                    'location' => $product->location,
                    'product_id' => $product->id,
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
