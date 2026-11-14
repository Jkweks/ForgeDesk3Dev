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
