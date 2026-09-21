<?php

namespace App\Services\Configurator;

use App\Models\DoorFrameConfiguration;
use App\Models\FdWorkOrder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pushes a work order's lineal cut-list (every released opening's frame +
 * door extrusions) directly into CutFlow over HTTP — no manual CSV
 * export/re-upload. See CutFlow's ImportController::apiImport() for the
 * receiving side.
 *
 * One CutFlow CutJob per ForgeDesk work order (`forgedesk_job_id` =
 * "wo-{id}"), not per configuration — a release re-exports the *whole*
 * work order's current released cut-list every time, and CutFlow's ingest
 * is designed to merge/update that job in place (matched on
 * forgedesk_job_id) rather than create a duplicate, preserving any cut
 * progress already recorded there.
 */
class CutFlowExportService
{
    /**
     * @return array{sent: bool, reason?: string, cut_job_id?: int, cut_job_name?: string, line_count?: int}
     */
    public function exportWorkOrder(FdWorkOrder $workOrder): array
    {
        $rows = $this->buildRows($workOrder);
        if ($rows->isEmpty()) {
            return ['sent' => false, 'reason' => 'No released lineal cut-list parts to export yet.'];
        }

        return $this->sendRows($workOrder, $rows->values()->all());
    }

    /**
     * Sends a caller-supplied row set (e.g. a manually-uploaded "other"
     * cutlist not sourced from the configurator) into the same CutFlow
     * CutJob this work order's configurator-driven export uses. CutFlow's
     * ingest only ever adds/updates the lines given in one call — it never
     * drops lines from a *previous* call — so this always merges with
     * whatever's already there rather than replacing it.
     *
     * @param  array<int, array>  $rows
     * @return array{sent: bool, reason?: string, cut_job_id?: int, cut_job_name?: string, line_count?: int}
     */
    public function sendRows(FdWorkOrder $workOrder, array $rows): array
    {
        if (empty($rows)) {
            return ['sent' => false, 'reason' => 'No cut-list rows to send.'];
        }

        $baseUrl = config('services.cutflow.base_url');
        if (! $baseUrl) {
            throw new RuntimeException('CutFlow base URL is not configured (CUTFLOW_BASE_URL).');
        }

        $job = $workOrder->businessJob;
        $jobName = trim(($job?->job_number ?? 'Job').'-'.$workOrder->release_token);

        $response = Http::timeout(10)
            ->withHeaders(array_filter([
                'X-ForgeDesk-Token' => config('services.cutflow.import_token'),
            ]))
            ->post(rtrim($baseUrl, '/').'/api/forgedesk/cutlist', [
                'forgedesk_job_id' => 'wo-'.$workOrder->id,
                'job_name' => $jobName,
                'rows' => $rows,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('CutFlow rejected the export ('.$response->status().'): '.$response->body());
        }

        return ['sent' => true] + $response->json();
    }

    /**
     * Parses a manually-uploaded "other" cutlist CSV (same column
     * conventions CutFlow's own ImportController accepts: part_id/name,
     * finish, dimension_in/dimension, qty, phase, description, row, column,
     * leftcutangle, rightcutangle) into row arrays ready for sendRows().
     * The CSV's own `job`/`work_order` columns, if present, are ignored —
     * this work order is always the authoritative source for both.
     *
     * @return array<int, array>
     */
    public function parseCsv(\Illuminate\Http\UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle, escape: ''));

        $rows = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $data = array_combine($header, $row);

            $name = trim((string) ($data['part_id'] ?? $data['name'] ?? ''));
            $qty = (int) ($data['qty'] ?? 0);
            $dimension = trim((string) ($data['dimension_in'] ?? $data['dimension'] ?? ''));
            if ($name === '' || $qty <= 0 || $dimension === '') {
                continue;
            }

            $rows[] = array_filter([
                'part_id' => $name,
                'finish' => trim((string) ($data['finish'] ?? '')) ?: null,
                'dimension_in' => $dimension,
                'qty' => $qty,
                'phase' => trim((string) ($data['phase'] ?? '')) ?: null,
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'row' => is_numeric($data['row'] ?? null) ? (int) $data['row'] : null,
                'column' => is_numeric($data['column'] ?? null) ? (int) $data['column'] : null,
                'leftcutangle' => is_numeric($data['leftcutangle'] ?? null) ? (float) $data['leftcutangle'] : null,
                'rightcutangle' => is_numeric($data['rightcutangle'] ?? null) ? (float) $data['rightcutangle'] : null,
            ], fn ($v) => $v !== null);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Every released/in-progress/completed configuration's lineal (length-
     * based) frame + door extrusions for this work order — the same "cut
     * list only" filter DoorFrameConfigurationController::exportCsv() uses
     * (component/fastener rows and hardware are never lineal stock cuts).
     */
    private function buildRows(FdWorkOrder $workOrder): \Illuminate\Support\Collection
    {
        $configs = DoorFrameConfiguration::with([
            'doors', 'frameConfig.parts.product', 'doorConfigs.parts.product',
        ])
            ->where('work_order_id', $workOrder->id)
            ->whereIn('status', ['released', 'in_progress', 'completed'])
            ->get();

        $rows = collect();

        foreach ($configs as $config) {
            $elevation = $config->doors->pluck('door_tag')->implode('/');

            $parts = collect()
                ->concat($config->frameConfig?->parts ?? [])
                ->concat($config->doorConfigs->flatMap(fn ($dc) => $dc->parts))
                ->filter(fn ($p) => $p->source_type !== 'component' && $p->unit_type === 'length' && $p->calculated_length > 0);

            foreach ($parts as $part) {
                $product = $part->product;
                if (! $product) {
                    continue;
                }

                $rows->push([
                    'part_id' => $product->part_number,
                    'finish' => $product->finish,
                    'dimension_in' => (string) $part->calculated_length,
                    'qty' => max(1, (int) round($part->quantity)),
                    'work_order' => $workOrder->release_token,
                    'phase' => $elevation,
                    'description' => $part->part_label,
                ]);
            }
        }

        return $rows;
    }
}
