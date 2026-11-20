<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportDrawingSet;
use App\Models\DrawingElevation;
use App\Models\DrawingSet;
use App\Services\Pm\DrawingChecks;
use App\Services\Pm\DrawingConfidence;
use App\Services\Pm\PmFileIngestor;
use App\Services\Pm\PmJobScope;
use Illuminate\Http\Request;

/** Shop-drawing sets for a Project Management job (feature #21). Results are suggestions, never auto-applied. */
class DrawingSetController extends Controller
{
    private function set(Request $request, int $jobId, int $id): DrawingSet
    {
        $job = PmJobScope::find($request->user(), $jobId);

        return DrawingSet::where('business_job_id', $job->id)->findOrFail($id);
    }

    private function present(DrawingSet $s, bool $full = false): array
    {
        $s->load('file:id,original_name,size');
        if ($full) {
            $s->load(['elevations.dims', 'elevations.glassTags', 'elevations.sheets.breaks', 'elevations.marks', 'elevations.axisChecks', 'warnings', 'glazingTags']);
        }
        $out = $s->toArray();
        if ($full) {
            $out['elevations'] = $s->elevations->map(fn ($e) => $this->presentElevation($e))->all();
        }

        return $out;
    }

    /** An elevation with its checks and confidence (overall and per value). */
    private function presentElevation(DrawingElevation $e): array
    {
        $e->loadMissing(['marks', 'axisChecks', 'sheets.breaks', 'workOrderLines']);
        $axes = $e->axisChecks->keyBy('axis');
        $out = $e->toArray();
        unset($out['work_order_lines']);
        $out['run_checks'] = \App\Services\Pm\DrawingRunChecks::forElevation($e);     // do the sheets of a long run agree with each other
        $out['requested_qty'] = $e->requestedQuantity();     // already covered by pending / approved work order requests
        $out['marks'] = $e->marks->map(fn ($m) => $m->toArray() + ['confidence' => DrawingConfidence::forMark($m, $axes[substr($m->kind, -1)]->state ?? null)])->all();
        $out['axis_checks'] = $e->axisChecks->map(fn ($c) => $c->toArray())->all();
        $out['confidence'] = DrawingConfidence::forElevation($e);
        $out['unresolved_axes'] = DrawingChecks::unresolved($e);

        return $out;
    }

    public function index(Request $request, int $jobId)
    {
        $job = PmJobScope::find($request->user(), $jobId);

        return response()->json(DrawingSet::where('business_job_id', $job->id)->withCount('elevations')->with(['file:id,original_name,size', 'glazingTags'])->orderByDesc('id')->get());
    }

    public function show(Request $request, int $jobId, int $id)
    {
        return response()->json($this->present($this->set($request, $jobId, $id), true));
    }

    /** Upload a drawing set PDF and queue the import (a large set takes minutes). */
    public function store(Request $request, int $jobId, PmFileIngestor $files)
    {
        $job = PmJobScope::find($request->user(), $jobId);
        $request->validate(['file' => 'required|file|max:204800|extensions:pdf']);

        [$file] = $files->registerUpload($request->file('file'), 'drawing_set', $job, $request->user());
        $set = DrawingSet::firstOrCreate(
            ['business_job_id' => $job->id, 'pm_file_id' => $file->id],
            ['status' => 'queued', 'created_by' => $request->user()->id]
        );
        if ($set->wasRecentlyCreated) {
            ImportDrawingSet::dispatch($set);
        }

        return response()->json($this->present($set->fresh()) + ['already_uploaded' => ! $set->wasRecentlyCreated], $set->wasRecentlyCreated ? 202 : 200);
    }

    /**
     * The sheet page as an image, for the size-check viewer. Rendered by the worker once per set, page and
     * resolution, then served from storage.
     */
    public function pageImage(Request $request, int $jobId, int $id, int $page, PmFileIngestor $files, \App\Services\Pm\PmWorkerClient $worker)
    {
        $set = $this->set($request, $jobId, $id);
        $dpi = max(60, min(160, (int) $request->query('dpi', 110)));
        abort_unless($set->elevations()->whereHas('sheets', fn ($q) => $q->where('page', $page))->exists(), 404);

        $path = "pm_files/pages/{$set->file->sha256}-p{$page}-{$dpi}.png";
        $disk = \Illuminate\Support\Facades\Storage::disk(PmFileIngestor::DISK);
        if (! $disk->exists($path)) {
            try {
                $disk->put($path, $worker->pageImage($files->bytes($set->file), $page, $dpi));
            } catch (\App\Services\Pm\PmWorkerDataException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            } catch (\Illuminate\Http\Client\ConnectionException|\RuntimeException $e) {
                return response()->json(['message' => 'The drawing worker is unavailable: '.$e->getMessage()], 503);
            }
        }

        return response($disk->get($path), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Confirm what the parser read (no body), correct it (confirmed_value), or clear the decision (confirmed_value null).
     * Several marks can be done at once with `ids` (confirm all on a sheet).
     */
    public function confirmMarks(Request $request, int $jobId, int $id, DrawingChecks $checks)
    {
        $set = $this->set($request, $jobId, $id);
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer',
            'action' => 'required|in:confirm,correct,clear', 'value' => 'required_if:action,correct|nullable|numeric|min:0|max:100000',
        ]);
        $marks = \App\Models\DrawingDimMark::whereIn('id', $data['ids'])
            ->whereHas('elevation', fn ($q) => $q->where('drawing_set_id', $set->id))->get();
        abort_if($marks->count() !== count(array_unique($data['ids'])), 404);
        abort_if($data['action'] === 'correct' && $marks->count() !== 1, 422, 'Correct one value at a time.');

        foreach ($marks as $m) {
            $m->update($data['action'] === 'clear'
                ? ['confirmed_value' => null, 'confirmed_by' => null, 'confirmed_at' => null]
                : ['confirmed_value' => $data['action'] === 'confirm' ? $m->value : $data['value'], 'confirmed_by' => $request->user()->id, 'confirmed_at' => now()]);
        }

        // a changed value may close (or break) a frame: re-run the importer's own check for each axis touched
        foreach ($marks->groupBy('drawing_elevation_id') as $elevId => $group) {
            $elev = DrawingElevation::with(['marks', 'printedMembers', 'axisChecks'])->find($elevId);
            foreach ($group->map(fn ($m) => substr($m->kind, -1))->unique() as $axis) {
                try {
                    $checks->recheck($elev->fresh(['marks', 'printedMembers', 'axisChecks']), $axis);
                } catch (\Throwable $e) {
                    report($e);   // the worker being down must not lose the person's confirmation
                }
            }
        }

        return response()->json(\App\Models\DrawingDimMark::whereIn('id', $data['ids'])->get()->values());
    }

    /** Read it again, e.g. after a parser update or a failure. */
    public function reimport(Request $request, int $jobId, int $id)
    {
        $set = $this->set($request, $jobId, $id);
        // A set stuck on 'running' (the worker died) can be re-read once it has been silent for longer than any import takes.
        abort_if($set->status === 'running' && $set->updated_at->gt(now()->subMinutes(20)), 422, 'This set is being read right now.');
        $set->update(['status' => 'queued', 'error' => null]);
        ImportDrawingSet::dispatch($set);

        return response()->json($this->present($set->fresh()), 202);
    }

    public function destroy(Request $request, int $jobId, int $id)
    {
        $this->set($request, $jobId, $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
    // ---- review workflow across all of a job's drawing sets ----

    /** Every elevation of every set of this job, with checks and confidence, attention-first. */
    public function jobElevations(Request $request, int $jobId)
    {
        $job = PmJobScope::find($request->user(), $jobId);
        $elevs = DrawingElevation::whereHas('set', fn ($q) => $q->where('business_job_id', $job->id)->where('status', 'done'))
            ->with(['set.file:id,original_name', 'marks', 'axisChecks', 'sheets.breaks', 'glassTags'])->get()
            ->map(function ($e) {
                $p = $this->presentElevation($e);
                $p['set'] = ['id' => $e->set->id, 'file' => $e->set->file?->original_name];

                return $p;
            });

        $rank = ['flagged' => 0, 'low' => 1, 'medium' => 2, 'high' => 3];
        $sorted = $elevs->sortBy(fn ($p) => [$p['review_status'] === 'pending' ? 0 : 1, $p['unresolved_axes'] ? 0 : 1, $rank[$p['confidence']['level']] ?? 9, $p['label']])->values();
        $pending = $elevs->where('review_status', 'pending');

        return response()->json([
            'summary' => [
                'total' => $elevs->count(), 'pending' => $pending->count(),
                'approved' => $elevs->where('review_status', 'approved')->count(), 'excluded' => $elevs->where('review_status', 'excluded')->count(),
                'needs_attention' => $pending->filter(fn ($p) => $p['unresolved_axes'] || $p['confidence']['level'] === 'low')->count(),
                'high' => $pending->where('confidence.level', 'high')->count(),
                'average_confidence' => $elevs->count() ? (int) round($elevs->avg('confidence.score')) : null,
            ],
            'elevations' => $sorted,
        ]);
    }

    private function elevation(Request $request, int $jobId, int $setId, int $id): DrawingElevation
    {
        $set = $this->set($request, $jobId, $setId);

        return DrawingElevation::with(['marks', 'axisChecks', 'sheets.breaks'])->where('drawing_set_id', $set->id)->findOrFail($id);
    }

    /** Approve, exclude or reopen one elevation. A frame that does not close blocks approval until it is dealt with. */
    public function review(Request $request, int $jobId, int $setId, int $id)
    {
        $e = $this->elevation($request, $jobId, $setId, $id);
        $action = $request->validate(['action' => 'required|in:approve,exclude,reopen'])['action'];
        if ($action === 'approve' && ($axes = DrawingChecks::unresolved($e))) {
            return response()->json(['message' => 'The '.implode(' and ', array_map(fn ($a) => $a === 'w' ? 'width' : 'height', $axes))
                .' frame does not close. Correct a value, keep it unverified, or accept it with a reason first.', 'unresolved_axes' => $axes], 422);
        }
        $status = ['approve' => 'approved', 'exclude' => 'excluded', 'reopen' => 'pending'][$action];
        $e->update(['review_status' => $status, 'reviewed_by' => $status === 'pending' ? null : $request->user()->id, 'reviewed_at' => $status === 'pending' ? null : now()]);

        return response()->json($this->presentElevation($e->fresh(['marks', 'axisChecks', 'sheets.breaks'])));
    }

    /** Approve every pending elevation at or above a confidence level whose frames are not left unresolved. */
    public function bulkReview(Request $request, int $jobId)
    {
        $job = PmJobScope::find($request->user(), $jobId);
        $data = $request->validate(['min_level' => 'required|in:high,medium', 'ids' => 'nullable|array', 'ids.*' => 'integer']);
        $floor = $data['min_level'] === 'high' ? DrawingConfidence::HIGH : DrawingConfidence::MEDIUM;

        $approved = 0;
        $skipped = [];
        $q = DrawingElevation::whereHas('set', fn ($s) => $s->where('business_job_id', $job->id)->where('status', 'done'))
            ->where('review_status', 'pending')->with(['marks', 'axisChecks']);
        if (! empty($data['ids'])) {
            $q->whereIn('id', $data['ids']);
        }
        foreach ($q->get() as $e) {
            $c = DrawingConfidence::forElevation($e);
            if (DrawingChecks::unresolved($e)) {
                $skipped[] = ['label' => $e->label, 'why' => 'frame does not close'];
            } elseif ($c['score'] < $floor) {
                $skipped[] = ['label' => $e->label, 'why' => "confidence {$c['level']} ({$c['score']})"];
            } else {
                $e->update(['review_status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
                $approved++;
            }
        }

        return response()->json(['approved' => $approved, 'skipped' => $skipped]);
    }

    /** What to do with a frame that does not close: keep it unverified, or accept it with a reason. */
    public function resolveAxis(Request $request, int $jobId, int $setId, int $id, string $axis)
    {
        abort_unless(in_array($axis, ['w', 'h'], true), 404);
        $e = $this->elevation($request, $jobId, $setId, $id);
        $check = $e->axisChecks->firstWhere('axis', $axis);
        abort_unless($check, 404);
        abort_unless($check->ok === false, 422, 'Only a frame that does not close needs this.');
        $data = $request->validate(['mode' => 'required|in:unverified,accepted', 'reason' => 'required_if:mode,accepted|nullable|string|max:500']);

        $check->update(['resolution' => $data['mode'], 'resolution_reason' => $data['mode'] === 'accepted' ? trim($data['reason']) : null,
            'resolved_by' => $request->user()->id, 'resolved_at' => now()]);

        return response()->json($this->presentElevation($e->fresh(['marks', 'axisChecks', 'sheets.breaks'])));
    }

    public function clearAxisResolution(Request $request, int $jobId, int $setId, int $id, string $axis)
    {
        abort_unless(in_array($axis, ['w', 'h'], true), 404);
        $e = $this->elevation($request, $jobId, $setId, $id);
        $e->axisChecks()->where('axis', $axis)->update(['resolution' => null, 'resolution_reason' => null, 'resolved_by' => null, 'resolved_at' => null]);

        return response()->json($this->presentElevation($e->fresh(['marks', 'axisChecks', 'sheets.breaks'])));
    }

    /** Several drawing files at once (a full package): each is registered and queued; identical files are not read twice. */
    public function bulkStore(Request $request, int $jobId, PmFileIngestor $files)
    {
        $job = PmJobScope::find($request->user(), $jobId);
        $request->validate(['files' => 'required|array|min:1|max:40', 'files.*' => 'file|max:204800|extensions:pdf']);

        $out = [];
        foreach ($request->file('files') as $upload) {
            [$file] = $files->registerUpload($upload, 'drawing_set', $job, $request->user());
            $set = DrawingSet::firstOrCreate(['business_job_id' => $job->id, 'pm_file_id' => $file->id], ['status' => 'queued', 'created_by' => $request->user()->id]);
            if ($set->wasRecentlyCreated) {
                ImportDrawingSet::dispatch($set);
            }
            $out[] = $this->present($set->fresh()) + ['already_uploaded' => ! $set->wasRecentlyCreated];
        }

        return response()->json($out, 202);
    }
}
