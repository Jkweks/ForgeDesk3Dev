<?php

namespace App\Services;

use App\Models\JobDocument;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Reads the money figures off the "Summary" sheet of an EZ Estimate workbook. Only that sheet is loaded
 * (the workbook has ~70), and formula cells use Excel's cached result so nothing is recalculated.
 *
 *   F92  Total Net All Systems — the like-for-like figure for material cost (before freight/warranty)
 *   F95  Freight
 *   F107 Grand Total
 *   F111 Total List Price
 */
class EzEstimateSummaryReader
{
    /** Documents to read in one report request; the rest are picked up on the next load. */
    public const INLINE_LIMIT = 15;

    /** @return array{total_net: float, freight: ?float, grand_total: ?float, list_price: ?float} */
    public function read(string $absolutePath): array
    {
        $reader = new Xlsx;
        $reader->setReadDataOnly(false);
        $reader->setLoadSheetsOnly(['Summary']);
        $sheet = $reader->load($absolutePath)->getSheetByName('Summary');
        if (! $sheet) {
            throw new \RuntimeException('No Summary sheet in this workbook.');
        }

        $num = function (string $cell) use ($sheet): ?float {
            $c = $sheet->getCell($cell);
            $v = $c->getDataType() === DataType::TYPE_FORMULA ? $c->getOldCalculatedValue() : $c->getValue();

            return is_numeric($v) ? round((float) $v, 2) : null;
        };

        $net = $num('F92');
        if ($net === null) {
            throw new \RuntimeException('Summary total net (F92) is not a number — the estimate may still say "finish set up required".');
        }

        return ['total_net' => $net, 'freight' => $num('F95'), 'grand_total' => $num('F107'), 'list_price' => $num('F111')];
    }

    /** Read a stored document and cache the figures (or the reason it failed) on it. */
    public function sync(JobDocument $doc): void
    {
        try {
            $path = Storage::disk('local')->path($doc->file_path);
            if (! is_file($path)) {
                throw new \RuntimeException('Uploaded file is missing from storage.');
            }
            $r = $this->read($path);
            $doc->forceFill([
                'ez_total_net' => $r['total_net'], 'ez_freight' => $r['freight'],
                'ez_grand_total' => $r['grand_total'], 'ez_list_price' => $r['list_price'],
                'ez_summary_error' => null,
            ]);
        } catch (\Throwable $e) {
            $doc->forceFill(['ez_total_net' => null, 'ez_summary_error' => mb_substr($e->getMessage(), 0, 250)]);
        }
        $doc->forceFill(['ez_summary_read_at' => now()])->save();
    }
}
