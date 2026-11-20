<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\DrawingElevation;
use App\Models\DrawingSet;
use App\Models\FdWorkOrder;
use App\Models\SystemNotification;
use App\Models\WorkOrderRequestChange;
use App\Services\Pm\WorkOrderElevationSeeder;
use App\Models\WorkOrderRequest;
use App\Services\Pm\PmFileIngestor;
use App\Services\Pm\PmJobScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Work orders requested by Project Management and validated by Fabrication (feature #15).
 * PM side: raise / list / withdraw / attach drawings (job scope applies). Fabrication side: review
 * with validation checks, then approve (creates the FdWorkOrder through the normal flow) or reject.
 */
class WorkOrderRequestController extends Controller
{
    /** Only jobs Fabrication can see (activated) can have work orders requested. */
    private function activeJob(int $jobId): BusinessJob
    {
        $job = PmJobScope::find(auth()->user(), $jobId);
        abort_if(in_array($job->status, BusinessJob::PM_ONLY_STATUSES, true), 422, 'Activate the job before requesting a work order.');
        abort_if(in_array($job->status, ['cancelled', 'completed'], true), 422, "A {$job->status} job cannot take new work orders.");

        return $job;
    }

    /** What Fabrication's existing setup says about this request. */
    private function checks(WorkOrderRequest $r): array
    {
        $job = $r->job;
        $dupe = WorkOrderRequest::where('business_job_id', $r->business_job_id)->where('id', '!=', $r->id)
            ->whereIn('status', ['pending', 'approved'])
            ->when($r->release_code, fn ($q) => $q->where('release_code', $r->release_code), fn ($q) => $q->whereRaw('1 = 0'))->exists();
        $codeTaken = $r->release_code && FdWorkOrder::where('business_job_id', $r->business_job_id)->where('release_code', $r->release_code)->exists();
        $hasWork = $job && ($job->jobReservations()->exists() || \App\Models\DoorFrameConfiguration::where('business_job_id', $job->id)->exists());

        return [
            ['key' => 'job_active', 'ok' => $job && ! in_array($job->status, [...BusinessJob::PM_ONLY_STATUSES, 'cancelled', 'completed', 'on_hold'], true),
                'text' => 'Job is active'],
            ['key' => 'release_code_free', 'ok' => ! $dupe && ! $codeTaken, 'text' => 'Release code is not already used on this job'],
            ['key' => 'has_demand', 'ok' => $hasWork, 'text' => 'Job has reservations or door/frame configurations', 'info' => true],
            ['key' => 'has_drawings', 'ok' => $r->files->isNotEmpty(), 'text' => 'Drawings attached', 'info' => true],
            ['key' => 'has_elevations', 'ok' => $r->elevations->isNotEmpty(), 'text' => 'Elevations and quantities listed from an approved drawing', 'info' => true],
        ];
    }

    private function present(WorkOrderRequest $r): array
    {
        $r->load(['job:id,job_number,job_name,status', 'requester:id,name,email', 'files', 'elevations', 'changes', 'drawingSet.file:id,original_name', 'workOrder:id,business_job_id,release_number']);

        return $r->toArray() + ['checks' => $this->checks($r)];
    }

    // ---- Project Management side ----

    public function forJob(Request $request, int $jobId)
    {
        $job = PmJobScope::find($request->user(), $jobId);

        return response()->json(WorkOrderRequest::where('business_job_id', $job->id)->orderByDesc('id')->get()->map(fn ($r) => $this->present($r)));
    }

    public function store(Request $request, int $jobId)
    {
        $job = $this->activeJob($jobId);
        $data = $request->validate([
            'release_code' => 'nullable|string|max:50', 'requested_due_date' => 'nullable|date',
            'scope' => 'nullable|string|max:5000', 'notes' => 'nullable|string|max:5000',
            'drawing_set_id' => 'nullable|integer',
            'elevations' => 'nullable|array|max:200',
            'elevations.*.drawing_elevation_id' => 'required|integer|distinct',
            'elevations.*.quantity' => 'required|integer|min:1|max:65000',
        ]);
        $lines = $data['elevations'] ?? [];
        unset($data['elevations']);
        $set = null;
        if ($lines && empty($data['drawing_set_id'])) {
            abort(422, 'Choose the drawing these elevations come from.');
        }
        if (! empty($data['drawing_set_id'])) {
            $set = DrawingSet::where('business_job_id', $job->id)->where('status', 'done')->with('file')->find($data['drawing_set_id']);
            abort_unless($set, 422, 'That drawing set is not one of this job\'s finished drawings.');
        }
        $elevs = $this->checkedElevations($set, $lines);

        $r = DB::transaction(function () use ($data, $job, $request, $set, $elevs) {
            $r = WorkOrderRequest::create($data + ['business_job_id' => $job->id, 'status' => 'pending', 'requested_by' => $request->user()->id]);
            foreach ($elevs as [$e, $qty]) {
                // a snapshot, because a re-read of the drawing set replaces its elevation rows
                $r->elevations()->create(['drawing_elevation_id' => $e->id, 'label' => $e->label, 'quantity' => $qty, 'total_reqd' => $e->total_reqd,
                    'ro_w' => $e->ro_w, 'ro_h' => $e->ro_h, 'system' => $e->system, 'finish' => $e->finish, 'arch_ref' => $e->arch_ref]);
            }
            if ($set) {      // the drawing goes with the request, so Fabrication opens the same sheets
                $r->files()->create(['pm_file_id' => $set->pm_file_id, 'label' => 'Shop drawings: '.($set->file->original_name ?? 'drawing set')]);
            }

            return $r;
        });

        // In-app bell for Fabrication approvers (cleared when the request is decided or withdrawn).
        SystemNotification::create(['type' => 'work_order_request', 'level' => 'info',
            'title' => 'Work order requested',
            'message' => ($request->user()->name ?? 'A project manager').' requested a work order for '.$job->job_number.' '.$job->job_name
                .($r->release_code ? ' (release '.$r->release_code.')' : '').($r->requested_due_date ? ', needed '.$r->requested_due_date->format('M j') : '').'.',
            'data' => ['work_order_request_id' => $r->id]]);

        return response()->json($this->present($r), 201);
    }

    /**
     * The chosen elevations as [elevation, quantity] pairs. Only APPROVED elevations of the chosen set can be released
     * (nothing reaches a work order before someone approves it), and a quantity cannot exceed what the drawing calls for
     * less what other pending or approved requests already cover.
     *
     * @return array<int, array{0: DrawingElevation, 1: int}>
     */
    private function checkedElevations(?DrawingSet $set, array $lines, ?int $exceptRequestId = null): array
    {
        $out = [];
        foreach ($lines as $l) {
            $e = $set ? DrawingElevation::with('workOrderLines')->where('drawing_set_id', $set->id)->find($l['drawing_elevation_id']) : null;
            abort_unless($e, 422, 'An elevation you chose is not on that drawing set.');
            abort_unless($e->review_status === 'approved', 422, "Elevation {$e->label} has not been approved yet.");
            $qty = (int) $l['quantity'];
            if ($e->total_reqd !== null) {
                $left = max(0, (int) $e->total_reqd - $e->requestedQuantity($exceptRequestId));
                abort_if($qty > $left, 422, "Elevation {$e->label}: {$qty} requested, but only {$left} of the {$e->total_reqd} the drawing calls for are not already requested.");
            }
            $out[] = [$e, $qty];
        }

        return $out;
    }

    public function addFile(Request $request, int $id, PmFileIngestor $files)
    {
        $r = WorkOrderRequest::findOrFail($id);
        PmJobScope::find($request->user(), $r->business_job_id);
        abort_unless($r->status === 'pending', 422, 'Only pending requests can take new files.');
        $request->validate(['file' => 'required|file|max:51200', 'label' => 'nullable|string|max:255']);

        [$file] = $files->registerUpload($request->file('file'), 'work_order_drawing', $r->job, $request->user());
        $r->files()->create(['pm_file_id' => $file->id, 'label' => $request->input('label')]);

        return response()->json($this->present($r), 201);
    }

    public function withdraw(Request $request, int $id)
    {
        $r = WorkOrderRequest::findOrFail($id);
        PmJobScope::find($request->user(), $r->business_job_id);
        abort_unless($r->status === 'pending', 422, 'This request has already been '.$r->status.'.');
        $r->update(['status' => 'withdrawn', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
        $this->clearNotification($r->id, $request->user()->id);

        return response()->json($this->present($r));
    }

    // ---- Fabrication side ----

    public function index(Request $request)
    {
        $status = array_filter(explode(',', (string) $request->query('status', 'pending')));
        $rows = WorkOrderRequest::whereIn('status', $status)->orderBy('requested_due_date')->orderBy('id')->limit(300)->get();

        return response()->json($rows->map(fn ($r) => $this->present($r))->values());
    }

    /**
     * Fabrication edits a PENDING request before accepting it: release code, needed-by date, scope, notes and the released
     * elevations with their quantities. Every change is kept (who, what, why) and the PM who asked is told.
     */
    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'release_code' => 'nullable|string|max:50', 'requested_due_date' => 'nullable|date',
            'scope' => 'nullable|string|max:5000', 'notes' => 'nullable|string|max:5000',
            'elevations' => 'nullable|array|max:200',
            'elevations.*.drawing_elevation_id' => 'required|integer|distinct',
            'elevations.*.quantity' => 'required|integer|min:1|max:65000',
            'change_note' => 'nullable|string|max:2000',
        ]);

        $r = DB::transaction(function () use ($id, $data, $request) {
            $r = WorkOrderRequest::whereKey($id)->lockForUpdate()->with(['elevations', 'job', 'drawingSet.file'])->firstOrFail();
            abort_unless($r->status === 'pending', 422, 'This request has already been '.$r->status.', so it can no longer be edited.');
            $changes = [];

            // plain fields
            $new = ['release_code' => ($data['release_code'] ?? null) ?: null, 'requested_due_date' => $data['requested_due_date'] ?? null,
                'scope' => ($data['scope'] ?? null) ?: null, 'notes' => ($data['notes'] ?? null) ?: null];
            $label = ['release_code' => 'Release code', 'requested_due_date' => 'Needed by', 'scope' => 'Scope', 'notes' => 'Notes'];
            foreach ($new as $field => $value) {
                if (! $request->has($field)) {
                    continue;                                  // not sent: left as it is
                }
                $old = $field === 'requested_due_date' ? $r->requested_due_date?->toDateString() : $r->{$field};
                $value = $field === 'requested_due_date' && $value ? \Carbon\Carbon::parse($value)->toDateString() : $value;
                if ((string) $old === (string) $value) {
                    continue;
                }
                $changes[] = in_array($field, ['scope', 'notes'], true) ? "{$label[$field]} changed" : "{$label[$field]}: ".($old ?: 'none').' → '.($value ?: 'none');
                $r->{$field} = $value;
            }
            if ($r->release_code && $r->isDirty('release_code')) {
                $taken = FdWorkOrder::where('business_job_id', $r->business_job_id)->where('release_code', $r->release_code)->exists()
                    || WorkOrderRequest::where('business_job_id', $r->business_job_id)->where('id', '!=', $r->id)->whereIn('status', ['pending', 'approved'])->where('release_code', $r->release_code)->exists();
                abort_if($taken, 422, "Release code {$r->release_code} is already used on this job.");
            }

            // the released elevations
            if ($request->has('elevations')) {
                abort_unless($r->drawing_set_id, 422, 'This request is not tied to a drawing, so it has no elevations to edit.');
                $lines = $data['elevations'] ?? [];
                $checked = collect($this->checkedElevations($r->drawingSet, $lines, $r->id))->keyBy(fn ($p) => $p[0]->id);
                $have = $r->elevations->keyBy('drawing_elevation_id');
                foreach ($have as $eid => $line) {
                    if (! $checked->has($eid)) {
                        $changes[] = "Removed {$line->label} ({$line->quantity})";
                        $line->delete();
                    }
                }
                foreach ($checked as $eid => [$e, $qty]) {
                    if ($line = $have->get($eid)) {
                        if ((int) $line->quantity !== $qty) {
                            $changes[] = "{$line->label}: quantity {$line->quantity} → {$qty}";
                            $line->update(['quantity' => $qty]);
                        }
                    } else {
                        $changes[] = "Added {$e->label} × {$qty}";
                        $r->elevations()->create(['drawing_elevation_id' => $e->id, 'label' => $e->label, 'quantity' => $qty, 'total_reqd' => $e->total_reqd,
                            'ro_w' => $e->ro_w, 'ro_h' => $e->ro_h, 'system' => $e->system, 'finish' => $e->finish, 'arch_ref' => $e->arch_ref]);
                    }
                }
            }

            abort_if(! $changes, 422, 'Nothing was changed.');
            $r->save();
            $log = WorkOrderRequestChange::create(['work_order_request_id' => $r->id, 'user_id' => $request->user()->id,
                'summary' => implode("\n", $changes), 'note' => ($data['change_note'] ?? null) ?: null]);
            $this->notifyRequester($r, $request->user(), $log);

            return $r;
        });

        return response()->json($this->present($r->fresh()));
    }

    /** Tell the PM who asked, in their own bell (not the shared Fabrication feed). Nobody is told of their own edit. */
    private function notifyRequester(WorkOrderRequest $r, \App\Models\User $editor, WorkOrderRequestChange $log): void
    {
        if (! $r->requested_by || $r->requested_by === $editor->id) {
            return;
        }
        $job = $r->job;
        SystemNotification::create(['user_id' => $r->requested_by, 'type' => 'work_order_request_changed', 'level' => 'warning',
            'title' => 'Your work order request was changed',
            'message' => ($editor->name ?? 'Fabrication').' changed your request for '.($job ? $job->job_number.' '.$job->job_name : 'a job')
                .($r->release_code ? ' (release '.$r->release_code.')' : '').":\n".$log->summary.($log->note ? "\nNote: ".$log->note : ''),
            'data' => ['work_order_request_id' => $r->id, 'pm_job_id' => $r->business_job_id, 'for_pm' => true]]);
    }

    /** What an editor can release from the request's drawing: its approved elevations, with how many are free and how many this request holds. */
    public function availableElevations(int $id)
    {
        $r = WorkOrderRequest::with('elevations')->findOrFail($id);
        if (! $r->drawing_set_id) {
            return response()->json([]);
        }
        $mine = $r->elevations->keyBy('drawing_elevation_id');

        return response()->json(DrawingElevation::with('workOrderLines')->where('drawing_set_id', $r->drawing_set_id)->where('review_status', 'approved')->orderBy('page')->orderBy('id')->get()
            ->map(fn ($e) => ['id' => $e->id, 'label' => $e->label, 'system' => $e->system, 'finish' => $e->finish, 'arch_ref' => $e->arch_ref, 'ro_w' => $e->ro_w, 'ro_h' => $e->ro_h,
                'total_reqd' => $e->total_reqd, 'in_request' => $mine->get($e->id)?->quantity,
                'free' => $e->total_reqd === null ? null : max(0, (int) $e->total_reqd - $e->requestedQuantity($r->id))])->values());
    }

    public function approve(Request $request, int $id)
    {
        $data = $request->validate(['due_date' => 'nullable|date', 'decision_note' => 'nullable|string|max:2000']);

        $wo = DB::transaction(function () use ($id, $data, $request) {
            $r = WorkOrderRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($r->status === 'pending', 422, 'This request has already been '.$r->status.'.');
            $job = BusinessJob::findOrFail($r->business_job_id); // global scope: must be visible to Fabrication
            abort_if(in_array($job->status, ['cancelled', 'completed', 'on_hold'], true), 422, "The job is {$job->status}.");
            abort_if($r->release_code && FdWorkOrder::where('business_job_id', $job->id)->where('release_code', $r->release_code)->exists(), 422, 'That release code is already used on this job.');

            $wo = FdWorkOrder::create([
                'business_job_id' => $job->id,
                'release_number' => FdWorkOrder::where('business_job_id', $job->id)->max('release_number') + 1,
                'release_code' => $r->release_code,
                'date_issued' => today(),
                'due_date' => $data['due_date'] ?? $r->requested_due_date,
                'notes' => trim(implode("\n\n", array_filter([$this->elevationText($r), $r->scope, $r->notes]))) ?: null,
                'priority' => FdWorkOrder::where('archived', false)->max('priority') + 1,
            ]);
            FdWorkOrder::resequencePriorities();
            app(WorkOrderElevationSeeder::class)->seed($r, $wo, $data['due_date'] ?? null);     // the released elevations become the work order's elevations
            $r->update(['status' => 'approved', 'decided_by' => $request->user()->id, 'decided_at' => now(),
                'decision_note' => $data['decision_note'] ?? null, 'fd_work_order_id' => $wo->id]);
            $this->clearNotification($r->id, $request->user()->id);

            return $wo;
        });

        return response()->json(['work_order_id' => $wo->id, 'release_number' => $wo->release_number]);
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate(['decision_note' => 'required|string|max:2000']);
        $r = WorkOrderRequest::findOrFail($id);
        abort_unless($r->status === 'pending', 422, 'This request has already been '.$r->status.'.');
        $r->update(['status' => 'rejected', 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $data['decision_note']]);
        $this->clearNotification($r->id, $request->user()->id);

        return response()->json($this->present($r));
    }

    /** The released elevations as lines for the work order's notes. */
    private function elevationText(WorkOrderRequest $r): ?string
    {
        $r->loadMissing('elevations', 'drawingSet.file');
        if ($r->elevations->isEmpty()) {
            return null;
        }

        return 'Elevations from '.($r->drawingSet?->file?->original_name ?? 'the drawings').":\n".$r->elevations->map(fn ($l) => '- '.$l->summary())->implode("\n");
    }

    private function clearNotification(int $requestId, int $userId): void
    {
        SystemNotification::active()->where('type', 'work_order_request')->get()
            ->filter(fn ($n) => ($n->data['work_order_request_id'] ?? null) === $requestId)
            ->each(fn ($n) => $n->update(['dismissed_at' => now(), 'dismissed_by_user_id' => $userId]));
    }

    /** Drawings attached to a request, for the reviewer. */
    public function downloadFile(int $id, int $fileId, PmFileIngestor $files)
    {
        $link = \App\Models\WorkOrderRequestFile::where('work_order_request_id', $id)->with('file')->findOrFail($fileId);
        $bytes = $files->bytes($link->file);

        return response()->streamDownload(fn () => print ($bytes), $link->file->original_name);
    }
}
