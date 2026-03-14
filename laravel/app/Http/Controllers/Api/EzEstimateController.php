<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EzEstimateController extends Controller
{
    /**
     * Debug endpoint to check supplier data
     */
    public function debug(Request $request)
    {
        $suppliers = DB::table('suppliers')
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        $sampleProducts = Product::with('supplier')
            ->select('id', 'sku', 'part_number', 'supplier_id', 'finish')
            ->limit(10)
            ->get()
            ->map(function($p) {
                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'part_number' => $p->part_number,
                    'supplier' => $p->supplier?->name,
                    'finish' => $p->finish,
                ];
            })
            ->toArray();

        $tubeliteCount = Product::join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
            ->whereRaw('LOWER(suppliers.name) = ?', ['tubelite'])
            ->count();

        $tubeliteSamples = Product::join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
            ->whereRaw('LOWER(suppliers.name) = ?', ['tubelite'])
            ->select('products.id', 'products.sku', 'products.part_number', 'suppliers.name as supplier', 'products.finish')
            ->limit(10)
            ->get()
            ->toArray();

        return response()->json([
            'total_products' => Product::count(),
            'suppliers' => $suppliers,
            'tubelite_count' => $tubeliteCount,
            'tubelite_samples' => $tubeliteSamples,
            'sample_products' => $sampleProducts,
        ]);
    }

    /**
     * Upload and process EZ Estimate file
     */
    public function upload(Request $request)
    {
        try {
            // Validate file upload
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
            ]);

            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file uploaded',
                ], 400);
            }

            $file = $request->file('file');

            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File upload failed: ' . $file->getErrorMessage(),
                ], 400);
            }

            // Ensure directory exists
            $directory = storage_path('app/ez_estimates');
            if (!file_exists($directory)) {
                if (!mkdir($directory, 0775, true)) {
                    throw new \Exception('Failed to create storage directory');
                }
            }

            DB::beginTransaction();

            // Get file extension, fallback to xlsx if not available
            $extension = $file->getClientOriginalExtension();
            if (empty($extension)) {
                $extension = $file->extension(); // Try alternate method
            }
            if (empty($extension)) {
                $extension = 'xlsx'; // Default fallback
            }

            $fileName = 'ez_estimate_' . time() . '.' . $extension;

            // Store file in storage/app/ez_estimates
            $path = $file->storeAs('ez_estimates', $fileName);

            if (!$path) {
                throw new \Exception('Failed to store uploaded file');
            }

            // Get the full filesystem path and verify it exists
            $fullPath = Storage::path($path);

            if (!file_exists($fullPath)) {
                throw new \Exception("File was not created at: {$fullPath}");
            }

            if (!is_readable($fullPath)) {
                throw new \Exception("File exists but is not readable: {$fullPath}");
            }

            // Parse and process the file
            $result = $this->processEzEstimate($fullPath);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'EZ Estimate processed successfully',
                'file_path' => $path,
                'stats' => $result,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('EZ Estimate upload failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process EZ Estimate: ' . $e->getMessage(),
                'debug' => [
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }

    /**
     * Process EZ Estimate Excel file and update pricing
     */
    private function processEzEstimate($filePath)
    {
        // Increase memory and time limits for large Excel files
        $oldMemoryLimit = ini_get('memory_limit');
        $oldTimeLimit = ini_get('max_execution_time');

        try {
            ini_set('memory_limit', '1024M'); // Increase to 1GB
            ini_set('max_execution_time', '300'); // 5 minutes

            // Verify file exists and is readable
            if (!file_exists($filePath)) {
                throw new \Exception("File does not exist: {$filePath}");
            }

            if (!is_readable($filePath)) {
                throw new \Exception("File is not readable: {$filePath}");
            }

            // Create reader with memory-efficient settings
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false); // Only read cell data, skip formatting
            $reader->setReadEmptyCells(false); // Skip empty cells to save memory

            // Load the spreadsheet with optimized settings
            $spreadsheet = $reader->load($filePath);

            if (!$spreadsheet) {
                throw new \Exception("Failed to load Excel file");
            }

            // Parse data from all worksheets
            $slFormulas = $this->parseSLFormulas($spreadsheet);
            $pFormulas = $this->parsePFormulas($spreadsheet);
            $finishCodes = $this->parseFinishCodes($spreadsheet);
            $multipliers = $this->parseMultipliers($spreadsheet);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            throw new \Exception("Excel parsing error: " . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception("Failed to process Excel file: " . $e->getMessage());
        }

        $stats = [
            'stock_length_updated' => 0,
            'accessory_updated' => 0,
            'errors' => [],
        ];

        // Update Stock Length products (A, E, M, T)
        foreach ($slFormulas as $partData) {
            try {
                $updated = $this->updateStockLengthPricing(
                    $partData,
                    $finishCodes,
                    $multipliers
                );
                $stats['stock_length_updated'] += $updated;
            } catch (\Exception $e) {
                $stats['errors'][] = "Stock Length {$partData['part_number']}: {$e->getMessage()}";
            }
        }

        // Update Accessory products (P, S)
        foreach ($pFormulas as $partData) {
            try {
                $updated = $this->updateAccessoryPricing($partData, $multipliers);
                $stats['accessory_updated'] += $updated;
            } catch (\Exception $e) {
                $stats['errors'][] = "Accessory {$partData['part_number']}: {$e->getMessage()}";
            }
        }

        return $stats;
    }

    /**
     * Parse SL Formulas worksheet
     */
    private function parseSLFormulas($spreadsheet)
    {
        $worksheet = $spreadsheet->getSheetByName('SL Formulas');
        if (!$worksheet) {
            throw new \Exception('SL Formulas worksheet not found');
        }

        $data = [];
        $highestRow = $worksheet->getHighestRow();

        // Start from row 2 to skip header
        for ($row = 2; $row <= $highestRow; $row++) {
            $pricingCategory = $worksheet->getCell("A{$row}")->getValue();
            $partNumber = $worksheet->getCell("C{$row}")->getValue();
            $pricePerLength = $worksheet->getCell("G{$row}")->getOldCalculatedValue();

            // Skip empty rows or rows without a price in column G
            // (there may be duplicate entries, only use rows with actual pricing)
            if (empty($partNumber) || empty($pricePerLength) || $pricePerLength == 0) {
                continue;
            }

            $data[] = [
                'pricing_category' => $pricingCategory,
                'part_number' => trim($partNumber),
                'price_per_length' => (float) $pricePerLength,
            ];
        }

        return $data;
    }

    /**
     * Parse P Formulas worksheet
     */
    private function parsePFormulas($spreadsheet)
    {
        $worksheet = $spreadsheet->getSheetByName('P Formulas');
        if (!$worksheet) {
            throw new \Exception('P Formulas worksheet not found');
        }

        $data = [];
        $highestRow = $worksheet->getHighestRow();

        // Start from row 2 to skip header
        for ($row = 2; $row <= $highestRow; $row++) {
            $pricingCategory = $worksheet->getCell("A{$row}")->getValue();
            $partNumber = $worksheet->getCell("C{$row}")->getValue();
            $pricePerPackage = $worksheet->getCell("I{$row}")->getOldCalculatedValue(); // Column I, not H

            // Skip empty rows or rows without a price in column I
            if (empty($partNumber) || empty($pricePerPackage) || $pricePerPackage == 0) {
                continue;
            }

            $data[] = [
                'pricing_category' => $pricingCategory,
                'part_number' => trim($partNumber),
                'price_per_package' => (float) $pricePerPackage,
            ];
        }

        return $data;
    }

    /**
     * Parse Finish Codes worksheet
     */
    private function parseFinishCodes($spreadsheet)
    {
        $worksheet = $spreadsheet->getSheetByName('Finish Codes');
        if (!$worksheet) {
            throw new \Exception('Finish Codes worksheet not found');
        }

        $data = [];
        $highestRow = $worksheet->getHighestRow();

        // Start from row 2 to skip header
        for ($row = 2; $row <= $highestRow; $row++) {
            $finishCode = $worksheet->getCell("F{$row}")->getValue();
            $finishMultiplier = $worksheet->getCell("H{$row}")->getValue();

            // Skip empty rows
            if (empty($finishCode)) {
                continue;
            }

            $data[trim($finishCode)] = (float) $finishMultiplier;
        }

        return $data;
    }

    /**
     * Parse Multipliers worksheet
     */
    private function parseMultipliers($spreadsheet)
    {
        $worksheet = $spreadsheet->getSheetByName('Multipliers');
        if (!$worksheet) {
            throw new \Exception('Multipliers worksheet not found');
        }

        $data = [];

        // Read rows 4-12 as specified
        for ($row = 4; $row <= 12; $row++) {
            $categories = $worksheet->getCell("B{$row}")->getValue();
            $multiplier = $worksheet->getCell("D{$row}")->getValue();

            // Skip empty rows
            if (empty($categories)) {
                continue;
            }

            // Categories can be comma-separated or space-separated
            $categoryList = preg_split('/[,\s]+/', trim($categories));

            foreach ($categoryList as $category) {
                $category = trim($category);
                if (!empty($category)) {
                    $data[$category] = (float) $multiplier;
                }
            }
        }

        return $data;
    }

    /**
     * Update pricing for Stock Length products (A, E, M, T)
     * Formula: price per length * finish multiplier * category multiplier = net cost
     */
    private function updateStockLengthPricing($partData, $finishCodes, $multipliers)
    {
        $partNumber = $partData['part_number'];
        $pricePerLength = $partData['price_per_length'];
        $pricingCategory = $partData['pricing_category'];

        // Find all products with this part number
        // These will have different finish codes in their SKU
        $products = Product::join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
            ->where('products.part_number', $partNumber)
            ->whereRaw('LOWER(suppliers.name) = ?', ['tubelite'])
            ->where(function($query) {
                $query->where('products.sku', 'LIKE', 'A%')
                      ->orWhere('products.sku', 'LIKE', 'E%')
                      ->orWhere('products.sku', 'LIKE', 'M%')
                      ->orWhere('products.sku', 'LIKE', 'T%');
            })
            ->select('products.*')
            ->get();

        $updatedCount = 0;

        foreach ($products as $product) {
            // Extract finish code from SKU (two digits after the -)
            $finishCode = $product->finish;

            // Get finish multiplier (default to 1.0 if not found)
            $finishMultiplier = $finishCodes[$finishCode] ?? 1.0;

            // Get category multiplier
            // Special case: If pricing category is "F" (but not "F3"), use "F1" multiplier
            $categoryKey = $pricingCategory;
            if ($pricingCategory === 'F') {
                $categoryKey = 'F1';
            }
            $categoryMultiplier = $multipliers[$categoryKey] ?? 1.0;

            // Calculate net cost
            $netCost = $pricePerLength * $finishMultiplier * $categoryMultiplier;

            // Update product
            $product->update([
                'price_per_length' => $pricePerLength,
                'pricing_category' => $pricingCategory,
                'finish_multiplier' => $finishMultiplier,
                'category_multiplier' => $categoryMultiplier,
                'net_cost' => round($netCost, 2),
            ]);

            $updatedCount++;
        }

        return $updatedCount;
    }

    /**
     * Update pricing for Accessory products (P, S)
     * Formula: price per unit * category multiplier = net cost
     */
    private function updateAccessoryPricing($partData, $multipliers)
    {
        $partNumber = $partData['part_number'];
        $pricePerPackage = $partData['price_per_package'];
        $pricingCategory = $partData['pricing_category'];

        // Find all products with this part number
        $products = Product::join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
            ->where('products.part_number', $partNumber)
            ->whereRaw('LOWER(suppliers.name) = ?', ['tubelite'])
            ->where(function($query) {
                $query->where('products.sku', 'LIKE', 'P%')
                      ->orWhere('products.sku', 'LIKE', 'S%');
            })
            ->select('products.*')
            ->get();

        $updatedCount = 0;

        foreach ($products as $product) {
            // Get category multiplier
            // Special case: If pricing category is "F" (but not "F3"), use "F1" multiplier
            $categoryKey = $pricingCategory;
            if ($pricingCategory === 'F') {
                $categoryKey = 'F1';
            }
            $categoryMultiplier = $multipliers[$categoryKey] ?? 1.0;

            // Calculate net cost
            $netCost = $pricePerPackage * $categoryMultiplier;

            // Update product
            $product->update([
                'price_per_package' => $pricePerPackage,
                'pricing_category' => $pricingCategory,
                'category_multiplier' => $categoryMultiplier,
                'net_cost' => round($netCost, 2),
            ]);

            $updatedCount++;
        }

        return $updatedCount;
    }

    /**
     * Test endpoint to verify PhpSpreadsheet is available
     */
    public function test()
    {
        try {
            // Check if PhpSpreadsheet is available
            $readerClass = \PhpOffice\PhpSpreadsheet\IOFactory::class;

            return response()->json([
                'success' => true,
                'message' => 'PhpSpreadsheet is available',
                'class' => $readerClass,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PhpSpreadsheet error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test endpoint to show pricing calculation for specific part numbers
     */
    public function testPricing(Request $request)
    {
        try {
            $partNumbers = ['S449', 'E14144'];

            // Get the most recent EZ Estimate file
            $files = Storage::files('ez_estimates');
            if (empty($files)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No EZ Estimate file found. Please upload one first.',
                ], 404);
            }

            usort($files, function($a, $b) {
                return Storage::lastModified($b) - Storage::lastModified($a);
            });
            $latestFile = $files[0];
            $fullPath = Storage::path($latestFile);

            // Load spreadsheet
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($fullPath);

            // Parse all worksheets
            $slFormulas = $this->parseSLFormulas($spreadsheet);
            $pFormulas = $this->parsePFormulas($spreadsheet);
            $finishCodes = $this->parseFinishCodes($spreadsheet);
            $multipliers = $this->parseMultipliers($spreadsheet);

            $results = [];

            foreach ($partNumbers as $partNumber) {
                $result = [
                    'part_number' => $partNumber,
                    'type' => null,
                    'excel_data' => null,
                    'database_products' => [],
                    'calculations' => [],
                ];

                // Check if it's in SL Formulas (A, E, M, T parts)
                $slData = collect($slFormulas)->firstWhere('part_number', $partNumber);
                if ($slData) {
                    $result['type'] = 'Stock Length (A/E/M/T)';
                    $result['excel_data'] = $slData;

                    // Find matching products
                    $products = Product::join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
                        ->where('products.part_number', $partNumber)
                        ->whereRaw('LOWER(suppliers.name) = ?', ['tubelite'])
                        ->where(function($query) {
                            $query->where('products.sku', 'LIKE', 'A%')
                                  ->orWhere('products.sku', 'LIKE', 'E%')
                                  ->orWhere('products.sku', 'LIKE', 'M%')
                                  ->orWhere('products.sku', 'LIKE', 'T%');
                        })
                        ->select('products.*', 'suppliers.name as supplier_name')
                        ->get();

                    foreach ($products as $product) {
                        $finishCode = $product->finish;
                        $finishMultiplier = $finishCodes[$finishCode] ?? 1.0;

                        // Special case: If pricing category is "F" (but not "F3"), use "F1" multiplier
                        $categoryKey = $slData['pricing_category'];
                        if ($slData['pricing_category'] === 'F') {
                            $categoryKey = 'F1';
                        }
                        $categoryMultiplier = $multipliers[$categoryKey] ?? 1.0;
                        $netCost = $slData['price_per_length'] * $finishMultiplier * $categoryMultiplier;

                        $result['database_products'][] = [
                            'sku' => $product->sku,
                            'finish' => $finishCode,
                            'current_net_cost' => $product->net_cost,
                        ];

                        $result['calculations'][] = [
                            'sku' => $product->sku,
                            'formula' => 'price_per_length × finish_multiplier × category_multiplier',
                            'price_per_length' => $slData['price_per_length'],
                            'finish_code' => $finishCode,
                            'finish_multiplier' => $finishMultiplier,
                            'pricing_category' => $slData['pricing_category'],
                            'category_multiplier' => $categoryMultiplier,
                            'calculation' => "{$slData['price_per_length']} × {$finishMultiplier} × {$categoryMultiplier}",
                            'net_cost' => round($netCost, 2),
                        ];
                    }
                }

                // Check if it's in P Formulas (P, S parts)
                $pData = collect($pFormulas)->firstWhere('part_number', $partNumber);
                if ($pData) {
                    $result['type'] = 'Accessory (P/S)';
                    $result['excel_data'] = $pData;

                    // Find matching products
                    $products = Product::join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
                        ->where('products.part_number', $partNumber)
                        ->whereRaw('LOWER(suppliers.name) = ?', ['tubelite'])
                        ->where(function($query) {
                            $query->where('products.sku', 'LIKE', 'P%')
                                  ->orWhere('products.sku', 'LIKE', 'S%');
                        })
                        ->select('products.*', 'suppliers.name as supplier_name')
                        ->get();

                    foreach ($products as $product) {
                        // Special case: If pricing category is "F" (but not "F3"), use "F1" multiplier
                        $categoryKey = $pData['pricing_category'];
                        if ($pData['pricing_category'] === 'F') {
                            $categoryKey = 'F1';
                        }
                        $categoryMultiplier = $multipliers[$categoryKey] ?? 1.0;
                        $netCost = $pData['price_per_package'] * $categoryMultiplier;

                        $result['database_products'][] = [
                            'sku' => $product->sku,
                            'current_net_cost' => $product->net_cost,
                        ];

                        $result['calculations'][] = [
                            'sku' => $product->sku,
                            'formula' => 'price_per_package × category_multiplier',
                            'price_per_package' => $pData['price_per_package'],
                            'pricing_category' => $pData['pricing_category'],
                            'category_multiplier' => $categoryMultiplier,
                            'calculation' => "{$pData['price_per_package']} × {$categoryMultiplier}",
                            'net_cost' => round($netCost, 2),
                        ];
                    }
                }

                if (!$slData && !$pData) {
                    $result['error'] = 'Part number not found in EZ Estimate file';
                }

                $results[] = $result;
            }

            return response()->json([
                'success' => true,
                'file' => basename($latestFile),
                'finish_codes_sample' => array_slice($finishCodes, 0, 5, true),
                'multipliers' => $multipliers,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Get current EZ Estimate file info
     */
    public function getCurrentFile()
    {
        try {
            $files = Storage::files('ez_estimates');

            if (empty($files)) {
                return response()->json([
                    'success' => true,
                    'file' => null,
                ]);
            }

            // Get the most recent file
            usort($files, function($a, $b) {
                return Storage::lastModified($b) - Storage::lastModified($a);
            });

            $latestFile = $files[0];

            return response()->json([
                'success' => true,
                'file' => [
                    'path' => $latestFile,
                    'name' => basename($latestFile),
                    'size' => Storage::size($latestFile),
                    'uploaded_at' => date('Y-m-d H:i:s', Storage::lastModified($latestFile)),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get EZ Estimate file info', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get file info',
            ], 500);
        }
    }

    /**
     * Get pricing statistics
     */
    public function getStats()
    {
        try {
            $stats = [
                'total_products' => Product::join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
                    ->whereRaw('LOWER(suppliers.name) = ?', ['tubelite'])
                    ->count(),
                'with_net_cost' => Product::join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
                    ->whereRaw('LOWER(suppliers.name) = ?', ['tubelite'])
                    ->whereNotNull('products.net_cost')
                    ->count(),
                'stock_length' => Product::join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
                    ->whereRaw('LOWER(suppliers.name) = ?', ['tubelite'])
                    ->where(function($query) {
                        $query->where('products.sku', 'LIKE', 'A%')
                              ->orWhere('products.sku', 'LIKE', 'E%')
                              ->orWhere('products.sku', 'LIKE', 'M%')
                              ->orWhere('products.sku', 'LIKE', 'T%');
                    })
                    ->count(),
                'accessories' => Product::join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
                    ->whereRaw('LOWER(suppliers.name) = ?', ['tubelite'])
                    ->where(function($query) {
                        $query->where('products.sku', 'LIKE', 'P%')
                              ->orWhere('products.sku', 'LIKE', 'S%');
                    })
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get pricing stats', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get stats',
            ], 500);
        }
    }
}
