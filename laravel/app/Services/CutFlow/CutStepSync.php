<?php

namespace App\Services\CutFlow;

use App\Models\BusinessJob;
use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\CutPhaseLink;
use App\Models\CutFlow\Part;
use App\Models\DoorFrameConfiguration;
use App\Models\FdStageLog;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use Illuminate\Support\Facades\DB;

/**
 * Keeps an elevation's "Cut" step in step with its cut-list lines:
 *  - the first piece cut on any of the elevation's lines moves a pending Cut step to in progress;
 *  - once every one of its lines is cut, the step is completed.
 *
 * Lines are tied to elevations by their `phase` (the elevation / door tag on the cut list):
 *  1. the door/frame configuration's door tags joined with "/" (what the configurator export writes),
 *  2. otherwise the elevation's own tag, compared ignoring case and spaces (uploaded cut lists),
 *  3. otherwise a phase made of several tags ("E1/E2") where every part is an elevation tag.
 * 4. a manual link made on the cut list (CutPhaseLink), for tags that match nothing above.
 * Several elevations carrying the same tag all share those lines.
 *
 * A cut list with no work_order_id (a plain upload) is tied to its work order by its name, which for
 * release-driven lists is "<job number>-<release token>" (e.g. 3260901-R2).
 *
 * Only moves a step forward: a step already complete/not_required is left alone, only a pending step is
 * auto-started (on hold / blocked stay as they are until the cut list is finished), and re-queuing a piece
 * (recut) does not reopen it.
 */
class CutStepSync
{
    private const CUT_STEP_NAMES = ['cut', 'cutting'];

    /** Call after any change that drew down a part's qty_remaining. */
    public static function afterPartCut(?Part $part): void
    {
        if (! $part || ! $part->phase) {
            return;
        }

        $job = CutJob::find($part->cut_job_id);
        if (! $job) {
            return;
        }
        $workOrderId = self::workOrderIdFor($job);
        if (! $workOrderId) {
            return;
        }

        $jobIds = $job->work_order_id
            ? CutJob::where('work_order_id', $workOrderId)->pluck('id')
            : collect([$job->id]);
        $parts = Part::whereIn('cut_job_id', $jobIds)->whereNotNull('phase')->get(['id', 'cut_job_id', 'phase', 'qty_original', 'qty_remaining']);

        $match = self::phaseMatcher($workOrderId, $jobIds->all());
        $targets = $match($part->phase, (int) $part->cut_job_id);
        if (! $targets) {
            return;
        }

        $phaseCache = [];
        foreach ($targets as $elevationId) {
            $mine = $parts->filter(function ($p) use ($match, $elevationId, &$phaseCache) {
                $phaseCache[$p->cut_job_id.'|'.$p->phase] ??= $match($p->phase, (int) $p->cut_job_id);

                return in_array($elevationId, $phaseCache[$p->cut_job_id.'|'.$p->phase], true);
            });

            $done = $mine->sum('qty_remaining') <= 0;
            $started = $mine->contains(fn ($p) => $p->qty_remaining < $p->qty_original);

            FdWoStage::where('elevation_id', $elevationId)
                ->whereIn(DB::raw('lower(name)'), self::CUT_STEP_NAMES)
                ->whereNotIn('status', FdWoStage::TERMINAL)
                ->get()
                ->each(function (FdWoStage $stage) use ($done, $started) {
                    if ($done) {
                        $stage->update(['status' => 'complete', 'completed_at' => now(), 'started_at' => $stage->started_at ?? now()]);
                        self::log($stage, 'Cut step completed automatically: cut list finished.');
                    } elseif ($started && $stage->status === 'pending') {
                        $stage->update(['status' => 'in_progress', 'started_at' => $stage->started_at ?? now()]);
                        self::log($stage, 'Cut step started automatically: cutting has begun on this cut list.');
                    }
                });
        }
    }

    private static function log(FdWoStage $stage, string $message): void
    {
        FdStageLog::create(['stage_id' => $stage->id, 'user_id' => null, 'message' => $message]);
    }

    /** The work order a cut list belongs to: its link, else resolved from a "<job number>-<release token>" name. */
    public static function workOrderIdFor(CutJob $job): ?int
    {
        if ($job->work_order_id) {
            return (int) $job->work_order_id;
        }
        if (! preg_match('/^(.+)-([^-]+)$/', trim((string) $job->name), $m)) {
            return null;
        }
        $businessJob = BusinessJob::where('job_number', trim($m[1]))->first();
        if (! $businessJob) {
            return null;
        }
        $token = mb_strtoupper(trim($m[2]));
        $wo = FdWorkOrder::where('business_job_id', $businessJob->id)->get()
            ->first(fn ($w) => mb_strtoupper($w->release_token) === $token);

        return $wo?->id;
    }

    /** Re-evaluate every elevation a cut list touches (after links change). */
    public static function syncJob(CutJob $job): void
    {
        Part::where('cut_job_id', $job->id)->whereNotNull('phase')->get()->unique('phase')
            ->each(fn (Part $p) => self::afterPartCut($p));
    }

    public static function norm(?string $s): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', (string) $s));
    }

    /**
     * @param  int[]  $jobIds  cut lists whose manual links count
     * @return \Closure(string, int, bool=): int[]  elevation ids a phase (cut-list elevation tag) refers to;
     *                                              the optional flag leaves manual links out (what matches on its own)
     */
    public static function phaseMatcher(int $workOrderId, array $jobIds = []): \Closure
    {
        $manual = $jobIds ? CutPhaseLink::whereIn('cut_job_id', $jobIds)->get()
            ->groupBy(fn ($l) => $l->cut_job_id.'|'.$l->phase)->map(fn ($g) => $g->pluck('elevation_id')->map(fn ($i) => (int) $i)->all()) : collect();

        $elevations = FdWoElevation::where('work_order_id', $workOrderId)->get(['id', 'elevation_tag', 'door_frame_configuration_id']);
        $configTags = DoorFrameConfiguration::with('doors')->where('work_order_id', $workOrderId)->get()
            ->mapWithKeys(fn ($c) => [$c->id => self::norm($c->doors->pluck('door_tag')->implode('/'))]);
        $byTag = $elevations->filter(fn ($e) => filled($e->elevation_tag))->groupBy(fn ($e) => self::norm($e->elevation_tag));

        return function (string $phase, int $jobId = 0, bool $withManual = true) use ($elevations, $configTags, $byTag, $manual): array {
            $n = self::norm($phase);
            if ($n === '') {
                return [];
            }
            $ids = $elevations->filter(fn ($e) => $e->door_frame_configuration_id && ($configTags[$e->door_frame_configuration_id] ?? null) === $n)
                ->pluck('id')->all();
            foreach ($byTag->get($n, []) as $e) {
                $ids[] = $e->id;
            }
            if (! $ids) {
                $segments = array_values(array_filter(preg_split('#[/,;&]+#', $n)));
                if (count($segments) > 1 && collect($segments)->every(fn ($s) => $byTag->has($s))) {
                    foreach ($segments as $s) {
                        foreach ($byTag->get($s) as $e) {
                            $ids[] = $e->id;
                        }
                    }
                }
            }

            if ($withManual) {
                $ids = array_merge($ids, $manual->get($jobId.'|'.$n, []));
            }

            return array_values(array_unique($ids));
        };
    }
}
