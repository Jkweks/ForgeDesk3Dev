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

    public function handle(PmWorkerClient $worker, PmFileIngestor $files): void
    {
        $set = $this->set->fresh();
        $set->update(['status' => 'running', 'error' => null]);

        try {
            $result = $worker->parseDrawingSet($files->bytes($set->file));
        } catch (\Throwable $e) {
            $set->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);

            return;
        }
        if (empty($result['ok'])) {
            $set->update(['status' => 'failed', 'error' => mb_substr($result['error'] ?? 'The drawing set could not be read.', 0, 1000), 'finished_at' => now()]);

            return;
        }

        try {
            $this->store($set, $result);
        } catch (\Throwable $e) {
            // Never leave the set stuck on "running": record what went wrong so the page and the user can see it.
            $set->update(['status' => 'failed', 'error' => mb_substr('Could not store the results: '.$e->getMessage(), 0, 1000), 'finished_at' => now()]);
        }
    }

    /** Last line of defence: if the worker process itself dies, the set must not stay "running". */
    public function failed(\Throwable $e): void
    {
        $this->set->fresh()?->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
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

    private function store(DrawingSet $set, array $result): void
    {
        DB::transaction(function () use ($set, $result) {
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
                ]);
                $this->storeMarks($row, $e);
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
                'status' => 'done', 'page_count' => $result['pageCount'] ?? null, 'skipped_pages' => $result['skippedPages'] ?? 0,
                'set_finish' => is_array($result['setFinish'] ?? null) ? ($result['setFinish']['finish'] ?? null) : null,
                'finished_at' => now(),
            ]);
        });
    }
}
