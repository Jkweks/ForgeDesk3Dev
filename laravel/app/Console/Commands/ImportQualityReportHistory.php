<?php

namespace App\Console\Commands;

use App\Models\QualityJointHistory;
use App\Models\QualityReport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * One-time backfill of quality-defect history predating this feature: the
 * "Quality Reports" sheet of docs/Quality Defects Database.xlsx (tracked
 * externally, never matched to a real elevation — job/elevation are kept as
 * reference text only) plus the monthly joint-count overrides for the
 * months before per-elevation joint_qty tracking existed. Safe to re-run —
 * both the spreadsheet rows (by their Case # embedded in extracted_fields)
 * and the joint-history months are upserted, not duplicated.
 */
class ImportQualityReportHistory extends Command
{
    protected $signature = 'quality:import-history {path : Path to the Quality Defects Database.xlsx file}';

    protected $description = 'Import historical quality-defect rows and pre-changeover monthly joint counts';

    /**
     * Given directly by the business, not derived from the spreadsheet:
     * calendar-month joint totals for the months before ForgeDesk's own
     * per-elevation joint_qty tracking became the source of truth on
     * 2026-09-16. Any FdWoElevation data for these months is incomplete and
     * is overridden entirely, not added to.
     */
    private const JOINT_HISTORY = [
        '2026-01' => 1242,
        '2026-02' => 1388,
        '2026-03' => 2724,
        '2026-04' => 1286,
        '2026-05' => 2459,
        '2026-06' => 1299,
        '2026-07' => 3454,
        '2026-08' => 3339,
        '2026-09' => 629,
    ];

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return 1;
        }

        $this->importJointHistory();
        $this->importQualityReports($path);

        return 0;
    }

    private function importJointHistory(): void
    {
        foreach (self::JOINT_HISTORY as $month => $jointCount) {
            QualityJointHistory::updateOrCreate(
                ['month' => $month],
                ['joint_count' => $jointCount, 'note' => 'Pre-changeover historical total (changeover 2026-09-16)']
            );
        }
        $this->info('Joint history: '.count(self::JOINT_HISTORY).' month(s) seeded.');
    }

    private function importQualityReports(string $path): void
    {
        $sheet = IOFactory::load($path)->getSheetByName('Quality Reports');
        if (! $sheet) {
            $this->error('Sheet "Quality Reports" not found in the workbook.');

            return;
        }

        $highestRow = $sheet->getHighestRow();
        $imported = 0;
        $skipped = 0;

        // Row 3 is the header; data starts at row 4 (see columns in the class docblock).
        for ($row = 4; $row <= $highestRow; $row++) {
            $cells = [];
            foreach ($sheet->getRowIterator($row, $row) as $sheetRow) {
                foreach ($sheetRow->getCellIterator() as $cell) {
                    $cells[] = $cell->getFormattedValue();
                }
            }
            [$caseNumber, $date, $job, $elevation, $replacement, $problemType, $supplier, $description, $reportedBy, $builtBy] = array_pad($cells, 10, null);

            if (empty($date)) {
                continue;
            }

            $reportDate = $this->parseDate($date);
            if (! $reportDate) {
                $skipped++;

                continue;
            }

            $caseNumber = $caseNumber !== null && $caseNumber !== '' ? (string) $caseNumber : "row-{$row}";

            if (QualityReport::where('extracted_fields->historical_case_number', $caseNumber)->exists()) {
                $skipped++;

                continue;
            }

            QualityReport::create([
                'status' => 'verified',
                'report_date' => $reportDate->toDateString(),
                'inspector_name' => $reportedBy ?: null,
                'problem_type' => $problemType ?: null,
                'replacement_needed' => $this->parseYesNo($replacement),
                'issue_description' => $description ?: null,
                'elevation_tag_guess' => $elevation ?: null,
                'auto_matched' => false,
                'verified_at' => $reportDate,
                'extracted_fields' => [
                    'job_text' => $job ?: null,
                    'elevation_tag_guess' => $elevation ?: null,
                    'supplier' => $supplier ?: null,
                    'built_by' => $builtBy ?: null,
                    'historical_case_number' => $caseNumber,
                    'source' => 'historical_import',
                ],
                'created_at' => $reportDate,
                'updated_at' => $reportDate,
            ]);
            $imported++;
        }

        $this->info("Quality reports: {$imported} imported, {$skipped} skipped (already imported or unparseable).");
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseYesNo(?string $value): ?bool
    {
        return match (mb_strtolower(trim((string) $value))) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }
}
