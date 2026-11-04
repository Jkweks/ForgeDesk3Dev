<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Api\Reports\Concerns\GeneratesCsv;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

/** ForgeDesk system reports: data-quality checks on the records themselves (not operational activity). */
class SystemReportsController extends Controller
{
    use GeneratesCsv;

    /** Active products with no photo. The same definition the "Parts Without an Image" dashboard widget counts. */
    public static function missingImagesQuery()
    {
        return Product::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('photo_path')->orWhere('photo_path', ''));
    }

    private function missingImageRows()
    {
        return self::missingImagesQuery()
            ->with(['categories:id,name', 'supplier:id,name'])
            ->orderBy('sku')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'part_number' => $p->part_number,
                'finish' => $p->finish,
                'description' => $p->description,
                'category' => $p->primaryCategory()?->name,
                'supplier' => $p->supplier?->name,
                'on_hand' => $p->quantity_on_hand,
                'status' => $p->status,
            ]);
    }

    public function missingImagesReport(Request $request)
    {
        $rows = $this->missingImageRows();
        $total = Product::where('is_active', true)->count();

        return response()->json([
            'summary' => [
                'missing' => $rows->count(),
                'total_active' => $total,
                'with_image' => $total - $rows->count(),
                'percent_missing' => $total ? round($rows->count() / $total * 100, 1) : 0,
            ],
            'products' => $rows->values(),
        ]);
    }

    public function csvExport(string $type, Request $request)
    {
        if ($type !== 'missing_images') {
            return null;
        }
        $rows = $this->missingImageRows()->map(fn ($r) => [
            $r['sku'], $r['part_number'], $r['finish'], $r['description'], $r['category'], $r['supplier'], $r['on_hand'], $r['status'],
        ]);

        return $this->generateCSV($rows, 'parts_without_image', ['SKU', 'Part #', 'Finish', 'Description', 'Category', 'Supplier', 'On Hand', 'Status']);
    }
}
