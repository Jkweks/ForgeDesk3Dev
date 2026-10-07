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
     * @param  string  $source  'configurator' | 'csv' — stamped on lines this call creates (an existing
     *   line keeps the source it already had, so a hand-added line never turns into a generated one).
     * @return array{job: CutJob, line_count: int}
     */
    public function ingest(array $rawRows, ?string $fallbackJobName, ?int $workOrderId = null, string $source = 'csv'): array
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
                'source' => $existing?->source ?? $source,
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

    /** Header names CutFlow understands, once normalised (see normaliseHeader()). */
    private const KNOWN_HEADERS = [
        'part_id', 'name', 'finish', 'dimension_in', 'dimension', 'qty', 'job', 'work_order', 'phase',
        'description', 'row', 'column', 'leftcutangle', 'rightcutangle',
    ];

    /** Header spellings of the ForgeDesk fabrication-package cut list, mapped onto CutFlow's names. */
    private const HEADER_ALIASES = [
        'sku' => 'part_id', 'finish_code' => 'finish', 'length' => 'dimension_in', 'quantity' => 'qty',
        'workorder' => 'work_order', 'door_tag' => 'phase', 'part_use' => 'description',
    ];

    /**
     * Column order of the ForgeDesk fabrication-package cut-list CSV, used when the file has no header
     * row: SKU, finish code, length, quantity, job, work order, door tag, 0, 0, part use, 0, 0. The
     * zero columns are not read.
     */
    private const FORGEDESK_COLUMNS = [
        0 => 'part_id', 1 => 'finish', 2 => 'dimension_in', 3 => 'qty', 4 => 'job', 5 => 'work_order',
        6 => 'phase', 9 => 'description',
    ];

    private function normaliseHeader(string $h): string
    {
        $h = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)));
        $h = preg_replace('/[\s\-]+/', '_', $h);

        return self::HEADER_ALIASES[$h] ?? $h;
    }

    /**
     * Parses an uploaded CSV file into the raw row-array shape ingest()
     * expects. Accepts either CutFlow's own header-row format (any column
     * order) or the ForgeDesk fabrication-package cut list, with or without
     * a header row: SKU, finish code, length, quantity, job, work order,
     * door tag, 0, 0, part use, 0, 0. A first row with no recognised header
     * name is treated as data and read by that fixed column order.
     *
     * @return array<int, array>
     */
    public function parseCsv(\Illuminate\Http\UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $first = fgetcsv($handle, escape: '');
        if ($first === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(fn ($h) => $this->normaliseHeader((string) $h), $first);
        $hasHeader = count(array_intersect($header, self::KNOWN_HEADERS)) > 0;

        $rawRows = [];
        $addPositional = function (array $row) use (&$rawRows) {
            $data = [];
            foreach (self::FORGEDESK_COLUMNS as $i => $key) {
                $data[$key] = $row[$i] ?? '';
            }
            $rawRows[] = $data;
        };

        if (! $hasHeader) {
            $addPositional($first);
        }

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if ($row === [null]) {
                continue; // blank line
            }
            if (! $hasHeader) {
                $addPositional($row);

                continue;
            }
            if (count($row) !== count($header)) {
                continue;
            }
            $rawRows[] = array_combine($header, $row);
        }
        fclose($handle);

        return $rawRows;
    }
}
