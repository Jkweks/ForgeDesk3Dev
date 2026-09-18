<?php

use App\Models\QualityJointHistory;
use App\Models\QualityReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

/**
 * Durable, deploy-time counterpart to `php artisan quality:import-history`:
 * that command needs the "Quality Defects Database.xlsx" workbook on disk
 * (tracked only under docs/, which isn't copied into the app image), so a
 * fresh install/production deploy would otherwise never get this data. This
 * migration embeds the already-verified output of that command — the same
 * 112 historical case rows and 8 pre-changeover monthly joint totals — so
 * `php artisan migrate` alone reproduces it anywhere. Idempotent: re-running
 * upserts by historical_case_number / month, same as the command.
 */
return new class extends Migration
{
    /**
     * Calendar-month joint totals for the months before ForgeDesk's own
     * per-elevation joint_qty tracking became the source of truth on
     * 2026-09-16. Given directly by the business, not derived from the
     * spreadsheet.
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
        '2026-09' => 629, // Partial month: 9/1 through the 9/16 changeover only.
    ];

    public function up(): void
    {
        foreach (self::JOINT_HISTORY as $month => $jointCount) {
            QualityJointHistory::updateOrCreate(
                ['month' => $month],
                ['joint_count' => $jointCount, 'note' => 'Pre-changeover historical total (changeover 2026-09-16)']
            );
        }

        $path = __DIR__.'/data/quality_report_history_2026_09.json';
        $rows = json_decode(file_get_contents($path), true) ?? [];

        foreach ($rows as $row) {
            $reportDate = $row['report_date'] ? Carbon::parse($row['report_date']) : null;

            QualityReport::updateOrCreate(
                ['extracted_fields->historical_case_number' => $row['historical_case_number']],
                [
                    'status' => 'verified',
                    'report_date' => $reportDate?->toDateString(),
                    'inspector_name' => $row['inspector_name'],
                    'problem_type' => $row['problem_type'],
                    'replacement_needed' => $row['replacement_needed'],
                    'issue_description' => $row['issue_description'],
                    'elevation_tag_guess' => $row['elevation_tag_guess'],
                    'auto_matched' => false,
                    'verified_at' => $reportDate,
                    'extracted_fields' => [
                        'job_text' => $row['job_text'],
                        'elevation_tag_guess' => $row['elevation_tag_guess'],
                        'supplier' => $row['supplier'],
                        'built_by' => $row['built_by'],
                        'historical_case_number' => $row['historical_case_number'],
                        'source' => 'historical_import',
                    ],
                    'created_at' => $reportDate,
                    'updated_at' => $reportDate,
                ]
            );
        }
    }

    public function down(): void
    {
        QualityReport::where('extracted_fields->source', 'historical_import')->delete();

        foreach (array_keys(self::JOINT_HISTORY) as $month) {
            QualityJointHistory::where('month', $month)
                ->where('note', 'Pre-changeover historical total (changeover 2026-09-16)')
                ->delete();
        }
    }
};
