<?php

namespace App\Services\CutFlow;

use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\Part;
use App\Support\Dimension;

/**
 * Shared ingest: raw string-keyed rows (CSV-shaped, whichever caller
 * produced them) -> upserted CutJob + Part rows. Used by both the browser
 * CSV-upload path (ImportController::store()) and ForgeDesk's own release
 * flow (Configurator\CutFlowExportService), now that both live in the same
 * process — no HTTP round trip needed either way.
 *
 * A profile (part_id + finish) is a raw extrusion SKU — the same part_id in
 * two different finishes is physically different stock and can't be cut
 * from one another, so identity within a job is (cut_job_id, name, finish,
 * dimension, description). Description ("use", e.g. Head/Sill/Jamb) is part
 * of that identity on purpose — a head and a sill of the same profile can
 * easily land on the same dimension, and without it they'd merge into one
 * Part row, so the printed label would show one piece's use on both pieces'
 * stickers. The same profile+dimension+use appearing on multiple lines
 * within one job (e.g. split across phases) has its quantities summed
 * rather than overwritten; the same profile+dimension on two different jobs
 * stays two separate rows since they're not interchangeable stock pulls.
 */
class CutlistIngestService
{
    /**
     * @param  array<int, array>  $rawRows  Each row: part_id/name, finish,
     *   dimension_in/dimension, qty, and optionally job, work_order, phase,
     *   description, row, column, leftcutangle, rightcutangle.
     * @param  int|null  $workOrderId  ForgeDesk's FdWorkOrder::id, when this
     *   ingest is driven by a work-order release rather than a manual CSV
     *   upload — the match/create key for the CutJob instead of its name, so
     *   a work order that gets re-exported (a second opening released, a
     *   quantity corrected) updates the *same* CutJob rather than spawning a
     *   duplicate one keyed on a name that might not even be spelled the
     *   same way twice. Every previously-imported line not present in this
     *   call is left alone (never deleted) — a partial re-export (e.g. one
     *   opening released at a time) only ever adds to or corrects a job,
     *   never drops lines that came from an earlier call.
     * @return array{job: CutJob, line_count: int}
     */
    public function ingest(array $rawRows, ?string $fallbackJobName, ?int $workOrderId = null): array
    {
        $lines = collect();
        $jobName = null;

        foreach ($rawRows as $data) {
            $name = trim((string) ($data['part_id'] ?? $data['name'] ?? ''));
            $finish = trim((string) ($data['finish'] ?? '')) ?: null;
            $qty = (int) ($data['qty'] ?? 0);
            $dimension = round(Dimension::parse((string) ($data['dimension_in'] ?? $data['dimension'] ?? '0')), 3);

            if ($name === '' || $qty <= 0 || $dimension <= 0) {
                continue;
            }

            $jobName ??= trim((string) ($data['job'] ?? '')) ?: $fallbackJobName;

            $workOrder = trim((string) ($data['work_order'] ?? '')) ?: null;
            $phase = trim((string) ($data['phase'] ?? '')) ?: null;
            $description = trim((string) ($data['description'] ?? '')) ?: null;
            $rowNum = is_numeric($data['row'] ?? null) ? (int) $data['row'] : null;
            $column = is_numeric($data['column'] ?? null) ? (int) $data['column'] : null;
            $leftAngle = is_numeric($data['leftcutangle'] ?? null) ? (float) $data['leftcutangle'] : null;
            $rightAngle = is_numeric($data['rightcutangle'] ?? null) ? (float) $data['rightcutangle'] : null;

            $key = $name.'|'.$finish.'|'.$dimension.'|'.$description;

            $lines->put($key, [
                'name' => $name,
                'finish' => $finish,
                'dimension_inches' => $dimension,
                'qty' => ($lines->get($key)['qty'] ?? 0) + $qty,
                'work_order' => $workOrder,
                'phase' => $phase,
                'description' => $description,
                'row' => $rowNum,
                'column' => $column,
                'left_cut_angle' => $leftAngle,
                'right_cut_angle' => $rightAngle,
            ]);
        }

        $jobName ??= 'Untitled Job';

        $job = $workOrderId
            ? CutJob::updateOrCreate(['work_order_id' => $workOrderId], ['name' => $jobName])
            : CutJob::firstOrCreate(['name' => $jobName]);

        foreach ($lines as $line) {
            $identity = [
                'cut_job_id' => $job->id,
                'name' => $line['name'],
                'finish' => $line['finish'],
                'dimension_inches' => $line['dimension_inches'],
                'description' => $line['description'],
            ];

            // Preserve already-recorded cut progress on a re-import: only a
            // *change* in quantity shifts qty_remaining (by the same delta),
            // it's never simply reset to the freshly-parsed qty. Otherwise
            // releasing a second opening on a work order that's already
            // partway cut would silently wipe out what the crew already cut
            // on the first opening's lines.
            $existing = Part::where($identity)->first();
            $qtyRemaining = $existing
                ? max(0, $existing->qty_remaining + ($line['qty'] - $existing->qty_original))
                : $line['qty'];

            Part::updateOrCreate($identity, [
                'qty_original' => $line['qty'],
                'qty_remaining' => $qtyRemaining,
                'work_order' => $line['work_order'],
                'phase' => $line['phase'],
                'row' => $line['row'],
                'column' => $line['column'],
                'left_cut_angle' => $line['left_cut_angle'],
                'right_cut_angle' => $line['right_cut_angle'],
            ]);
        }

        return ['job' => $job, 'line_count' => $lines->count()];
    }

    /**
     * Parses an uploaded CSV file into the raw row-array shape ingest()
     * expects. Shared by the browser upload path and can be reused wherever
     * else a CutFlow-format CSV needs parsing.
     *
     * @return array<int, array>
     */
    public function parseCsv(\Illuminate\Http\UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle, escape: ''));

        $rawRows = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $rawRows[] = array_combine($header, $row);
        }
        fclose($handle);

        return $rawRows;
    }
}
