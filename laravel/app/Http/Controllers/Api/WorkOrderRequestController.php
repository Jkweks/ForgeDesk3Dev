<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\FdWorkOrder;
use App\Models\SystemNotification;
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
        ];
    }

    private function present(WorkOrderRequest $r): array
    {
        $r->load(['job:id,job_number,job_name,status', 'requester:id,name,email', 'files', 'workOrder:id,business_job_id,release_number']);

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
        ]);
        $r = WorkOrderRequest::create($data + ['business_job_id' => $job->id, 'status' => 'pending', 'requested_by' => $request->user()->id]);

        // In-app bell for Fabrication approvers (cleared when the request is decided or withdrawn).
        SystemNotification::create(['type' => 'work_order_request', 'level' => 'info',
            'title' => 'Work order requested',
            'message' => ($request->user()->name ?? 'A project manager').' requested a work order for '.$job->job_number.' '.$job->job_name
                .($r->release_code ? ' (release '.$r->release_code.')' : '').($r->requested_due_date ? ', needed '.$r->requested_due_date->format('M j') : '').'.',
            'data' => ['work_order_request_id' => $r->id]]);

        return response()->json($this->present($r), 201);
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
                'notes' => trim(implode("\n\n", array_filter([$r->scope, $r->notes]))) ?: null,
                'priority' => FdWorkOrder::where('archived', false)->max('priority') + 1,
            ]);
            FdWorkOrder::resequencePriorities();
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
