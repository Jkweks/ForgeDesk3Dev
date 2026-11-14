<?php

namespace App\Jobs;

use App\Models\DrawingElevation;
use App\Models\DrawingSet;
use App\Services\Pm\PmFileIngestor;
use App\Services\Pm\PmWorkerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/** Reads a drawing set through the worker and stores its elevations. Safe to re-run: results are replaced. */
class ImportDrawingSet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1000;

    public int $tries = 1;

    public function __construct(public DrawingSet $set) {}

    public function handle(PmWorkerClient $worker, PmFileIngestor $files, \App\Services\Pm\DrawingChecks $checks): void
    {
        $set = $this->set->fresh();
        $set->update(['status' => 'running', 'error' => null]);

        try {
            $result = $this->read($worker, $files->bytes($set->file), $set);
        } catch (\Throwable $e) {
            $set->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);

            return;
        }
        if (empty($result['ok'])) {
            $set->update(['status' => 'failed', 'error' => mb_substr($result['error'] ?? 'The drawing set could not be read.', 0, 1000), 'finished_at' => now()]);

            return;
        }

        try {
            $this->store($set, $result, $checks);
        } catch (\Throwable $e) {
            // Never leave the set stuck on "running": record what went wrong so the page and the user can see it.
            $set->update(['status' => 'failed', 'error' => mb_substr('Could not store the results: '.$e->getMessage(), 0, 1000), 'finished_at' => now()]);
        }
    }

    /** Run the read in the worker and follow it: progress is written to the set so the page can show a bar. */
    private function read(PmWorkerClient $worker, string $pdf, DrawingSet $set): array
    {
        $id = $worker->startDrawingJob($pdf);
        $deadline = time() + 900;
        while (true) {
            $st = $worker->drawingJob($id);
            if ($st === null) {
                throw new \RuntimeException('The drawing worker lost this job (it may have restarted). Use Read again.');
            }
            $set->update(['progress_done' => (int) $st['done'], 'progress_count' => (int) $st['count'], 'progress_note' => $st['note'] ? mb_substr((string) $st['note'], 0, 250) : null]);
            if ($st['state'] === 'done') {
                return $worker->drawingJob($id, true)['result'] ?? throw new \RuntimeException('The worker returned no result.');
            }
            if ($st['state'] === 'failed') {
                throw new \RuntimeException($st['error'] ?: 'The drawing could not be read.');
            }
            if (time() > $deadline) {
                throw new \RuntimeException('Reading this set took too long and was stopped.');
            }
            usleep((int) (config('services.pm_worker.poll_ms', 2000) * 1000));
        }
    }

    /** Last line of defence: if the worker process itself dies, the set must not stay "running". */
    public function failed(\Throwable $e): void
    {
        $this->set->fresh()?->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
    }

    /** @return array<string, array{review: array, marks: array, axes: array}> keyed by elevation label */
    private function snapshotDecisions(DrawingSet $set): array
    {
        $out = [];
        foreach ($set->elevations()->with(['marks', 'axisChecks'])->get() as $e) {
            $out[$e->label] = [
                'review' => ['status' => $e->review_status, 'by' => $e->reviewed_by, 'at' => $e->reviewed_at],
                'marks' => $e->marks->filter(fn ($m) => $m->confirmed_value !== null)->mapWithKeys(fn ($m) => ["{$m->page}|{$m->kind}|{$m->idx}" => [
                    'value' => (float) $m->value, 'confirmed' => $m->confirmed_value, 'by' => $m->confirmed_by, 'at' => $m->confirmed_at]])->all(),
                'axes' => $e->axisChecks->filter(fn ($c) => $c->resolution)->mapWithKeys(fn ($c) => [$c->axis => [
                    'resolution' => $c->resolution, 'reason' => $c->resolution_reason, 'by' => $c->resolved_by, 'at' => $c->resolved_at]])->all(),
            ];
        }

        return $out;
    }

    /** A decision survives only where the parser still reads the same value there; otherwise it was about something else. */
    private function restoreDecisions(DrawingElevation $row, ?array $prev, \App\Services\Pm\DrawingChecks $checks): void
    {
        if (! $prev) {
            return;
        }
        $touched = [];
        foreach ($row->marks()->get() as $m) {
            $p = $prev['marks']["{$m->page}|{$m->kind}|{$m->idx}"] ?? null;
            if ($p && abs($p['value'] - (float) $m->value) < 0.0001) {
                $m->update(['confirmed_value' => $p['confirmed'], 'confirmed_by' => $p['by'], 'confirmed_at' => $p['at']]);
                $touched[substr($m->kind, -1)] = true;
            }
        }
        foreach (array_keys($touched) as $axis) {
            $checks->recheck($row->fresh(['marks', 'printedMembers', 'axisChecks']), $axis);
        }
        foreach ($prev['axes'] as $axis => $r) {
            $c = $row->axisChecks()->where('axis', $axis)->first();
            if ($c && $c->ok === false && ! $c->resolution) {      // still does not close: the decision still applies
                $c->update(['resolution' => $r['resolution'], 'resolution_reason' => $r['reason'], 'resolved_by' => $r['by'], 'resolved_at' => $r['at']]);
            }
        }
        if (($prev['review']['status'] ?? 'pending') !== 'pending') {
            $row->update(['review_status' => $prev['review']['status'], 'reviewed_by' => $prev['review']['by'], 'reviewed_at' => $prev['review']['at']]);
        }
    }

    /** The parser returns dicts for some fields (glass tags carry their page position); keep the value we store scalar. */
    private static function scalar(mixed $v, string $key = 'tag'): ?string
    {
        if (is_array($v)) {
            $v = $v[$key] ?? null;
        }

        return is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;
    }

    /**
     * Where each dimension is printed, per sheet. A multi-sheet elevation carries `sheetDims` (one entry per sheet);
     * a single-sheet one has just its own `dims` and `pageSize`.
     */
    private function storeMarks(DrawingElevation $row, array $e): void
    {
        $sheets = $e['sheetDims'] ?? [['page' => $e['page'] ?? null, 'pageSize' => $e['pageSize'] ?? null, 'dims' => $e['dims'] ?? null, 'titleRect' => $e['titleRect'] ?? null]];
        $single = ['roW' => 'ro_w', 'fsW' => 'fs_w', 'roH' => 'ro_h', 'fsH' => 'fs_h', 'doW' => 'do_w', 'doH' => 'do_h'];
        $lists = ['dloWidths' => 'dlo_w', 'dloHeights' => 'dlo_h'];

        foreach ($sheets as $sh) {
            $page = (int) ($sh['page'] ?? 0);
            $size = $sh['pageSize'] ?? null;
            $dims = $sh['dims'] ?? null;
            if (! $page || ! is_array($size) || count($size) < 2 || ! is_array($dims)) {
                continue;
            }
            $t = is_array($sh['titleRect'] ?? null) && count($sh['titleRect']) === 4 ? $sh['titleRect'] : [null, null, null, null];
            $row->sheets()->create(['page' => $page, 'width' => $size[0], 'height' => $size[1],
                'title_x0' => $t[0], 'title_top' => $t[1], 'title_x1' => $t[2], 'title_bottom' => $t[3]]);

            $add = function (string $kind, int $idx, $d) use ($row, $page) {
                if (! is_array($d) || ! isset($d['v']) || ! is_array($d['rect'] ?? null) || count($d['rect']) !== 4 || ! is_numeric($d['v'])) {
                    return;
                }
                [$x0, $top, $x1, $bottom] = $d['rect'];
                $row->marks()->create(['page' => $page, 'kind' => $kind, 'idx' => $idx, 'value' => $d['v'],
                    'x0' => $x0, 'top' => $top, 'x1' => $x1, 'bottom' => $bottom]);
            };
            foreach ($single as $key => $kind) {
                $add($kind, 0, $dims[$key] ?? null);
            }
            foreach ($lists as $key => $kind) {
                foreach (array_values($dims[$key] ?? []) as $i => $d) {
                    $add($kind, $i, $d);
                }
            }
        }
    }

    private function store(DrawingSet $set, array $result, \App\Services\Pm\DrawingChecks $checks): void
    {
        $carry = $this->snapshotDecisions($set);   // a re-read keeps what people already decided
        DB::transaction(function () use ($set, $result, $checks, $carry) {
            $set->elevations()->delete();
            $set->warnings()->delete();
            foreach ($result['elevations'] ?? [] as $e) {
                $row = DrawingElevation::create([
                    'drawing_set_id' => $set->id, 'page' => $e['page'] ?? null, 'label' => (string) ($e['label'] ?? '?'),
                    'arch_ref' => self::scalar($e['archRef'] ?? null, 'text'), 'system' => self::scalar($e['system'] ?? null, 'text'),
                    'finish' => self::scalar($e['finish'] ?? null, 'text'), 'finish_code' => self::scalar($e['finishCode'] ?? null, 'text'),
                    'thus_reqd' => $e['thusReqd'] ?? null, 'opp_reqd' => $e['oppReqd'] ?? null, 'total_reqd' => $e['totalReqd'] ?? null,
                    'ro_w' => $e['roW'] ?? null, 'fs_w' => $e['fsW'] ?? null, 'ro_h' => $e['roH'] ?? null, 'fs_h' => $e['fsH'] ?? null,
                    'door_w' => $e['doW'] ?? null, 'door_h' => $e['doH'] ?? null,
                    'multi_sheet' => (bool) ($e['multiSheet'] ?? false),
                    'manual_takeoff' => is_array($e['manualTakeoff'] ?? null) ? mb_substr(($e['manualTakeoff']['reason'] ?? 'manual').': '.implode(', ', (array) ($e['manualTakeoff']['tags'] ?? [])), 0, 250) : null,
                ]);
                $this->storeMarks($row, $e);
                $checks->storeFromImport($row, $e);
                $this->restoreDecisions($row, $carry[$row->label] ?? null, $checks);
                foreach (['w' => $e['dloWidths'] ?? [], 'h' => $e['dloHeights'] ?? []] as $axis => $values) {
                    foreach (array_values($values) as $i => $v) {
                        $row->dims()->create(['axis' => $axis, 'position' => $i, 'value' => $v]);
                    }
                }
                foreach (array_unique(array_filter(array_map(fn ($t) => self::scalar($t), $e['glassTags'] ?? []))) as $tag) {
                    $row->glassTags()->create(['tag' => $tag]);
                }
            }
            foreach ($result['warnings'] ?? [] as $w) {
                $set->warnings()->create(['message' => (string) $w]);
            }
            $set->update([
                'status' => 'done', 'progress_done' => $result['pageCount'] ?? 0, 'progress_count' => $result['pageCount'] ?? 0, 'page_count' => $result['pageCount'] ?? null, 'skipped_pages' => $result['skippedPages'] ?? 0,
                'set_finish' => is_array($result['setFinish'] ?? null) ? ($result['setFinish']['finish'] ?? null) : null,
                'finished_at' => now(),
            ]);
        });
    }
}
