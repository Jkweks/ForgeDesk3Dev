<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Submittal;
use App\Models\SubmittalSample;
use App\Services\Pm\PmFileIngestor;
use App\Services\Pm\PmJobScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Submittal register for a Project Management job (docs/plans/pm-suite-integration.md, feature #7). */
class SubmittalController extends Controller
{
    private function job(int $jobId)
    {
        return PmJobScope::find(auth()->user(), $jobId);
    }

    private function submittal(int $jobId, int $id): Submittal
    {
        return Submittal::where('business_job_id', $this->job($jobId)->id)->findOrFail($id);
    }

    private function present(Submittal $s): array
    {
        $s->refresh()->load(['revisions', 'responses.file', 'samples', 'purchaseOrders:id,po_number']);

        return $s->toArray();
    }

    public function index(Request $request, int $jobId)
    {
        $job = $this->job($jobId);
        $q = Submittal::where('business_job_id', $job->id)->with(['samples', 'purchaseOrders:id,po_number'])
            ->withCount('responses')->orderBy('number');
        if ($request->filled('kind')) {
            $q->where('kind', $request->kind);
        }
        if ($request->boolean('needs_response')) {
            $q->where(fn ($w) => $w->where('status', 'pending')->orWhereHas('samples', fn ($s) => $s->where('status', 'pending')));
        }

        return response()->json($q->get());
    }

    public function show(int $jobId, int $id)
    {
        return response()->json($this->present($this->submittal($jobId, $id)));
    }

    public function store(Request $request, int $jobId)
    {
        $job = $this->job($jobId);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'spec_section' => 'nullable|string|max:50',
            'kind' => ['nullable', Rule::in(Submittal::KINDS)],
            'sent_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $s = DB::transaction(function () use ($job, $data) {
            // Lock the job's existing rows (no aggregate: Postgres refuses FOR UPDATE with max()), then number in PHP.
            $numbers = Submittal::where('business_job_id', $job->id)->lockForUpdate()->pluck('number');
            $next = ($numbers->map(fn ($n) => (int) $n)->max() ?? 0) + 1;

            return Submittal::create($data + [
                'business_job_id' => $job->id, 'number' => str_pad((string) $next, 3, '0', STR_PAD_LEFT),
                'kind' => $data['kind'] ?? 'docs', 'created_by' => auth()->id(),
            ]);
        });

        return response()->json($this->present($s), 201);
    }

    public function update(Request $request, int $jobId, int $id)
    {
        $s = $this->submittal($jobId, $id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'spec_section' => 'nullable|string|max:50',
            'kind' => ['sometimes', Rule::in(Submittal::KINDS)],
            'status' => ['sometimes', Rule::in(Submittal::STATUSES)],
            'sent_at' => 'nullable|date',
            'returned_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
        $s->update($data);

        return response()->json($this->present($s));
    }

    public function destroy(int $jobId, int $id)
    {
        $this->submittal($jobId, $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /** Record the current round and start the next revision (status back to pending, dates cleared). */
    public function revise(int $jobId, int $id)
    {
        $s = $this->submittal($jobId, $id);
        DB::transaction(function () use ($s) {
            $s->revisions()->create([
                'revision_number' => $s->revision_number, 'status' => $s->status, 'sent_at' => $s->sent_at,
                'returned_at' => $s->returned_at, 'notes' => $s->notes, 'created_by' => auth()->id(),
            ]);
            $s->update(['revision_number' => $s->revision_number + 1, 'status' => 'pending', 'sent_at' => null, 'returned_at' => null]);
        });

        return response()->json($this->present($s));
    }

    /** Attach a GC/AE response document; sets the submittal's status and returned date. */
    public function addResponse(Request $request, int $jobId, int $id, PmFileIngestor $files)
    {
        $s = $this->submittal($jobId, $id);
        $data = $request->validate([
            'file' => 'nullable|file|max:51200',
            'status' => ['required', Rule::in(array_diff(Submittal::STATUSES, ['pending']))],
            'returned_at' => 'required|date',
            'responded_by' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $fileId = null;
        if ($request->hasFile('file')) {
            [$file] = $files->registerUpload($request->file('file'), 'submittal_response', $s->job, $request->user());
            $fileId = $file->id;
        }
        DB::transaction(function () use ($s, $data, $fileId) {
            $s->responses()->create([
                'revision_number' => $s->revision_number, 'pm_file_id' => $fileId, 'status' => $data['status'],
                'returned_at' => $data['returned_at'], 'responded_by' => $data['responded_by'] ?? null,
                'notes' => $data['notes'] ?? null, 'created_by' => auth()->id(),
            ]);
            $s->update(['status' => $data['status'], 'returned_at' => $data['returned_at']]);
        });

        return response()->json($this->present($s), 201);
    }

    // ---- samples ----

    public function addSample(Request $request, int $jobId, int $id)
    {
        $s = $this->submittal($jobId, $id);
        $data = $request->validate(['description' => 'required|string|max:255', 'manufacturer' => 'nullable|string|max:255', 'notes' => 'nullable|string']);
        $s->samples()->create($data);
        if ($s->kind === 'docs') {
            $s->update(['kind' => 'docs_samples']);
        }

        return response()->json($this->present($s), 201);
    }

    public function updateSample(Request $request, int $jobId, int $id, int $sampleId)
    {
        $s = $this->submittal($jobId, $id);
        $sample = SubmittalSample::where('submittal_id', $s->id)->findOrFail($sampleId);
        $sample->update($request->validate([
            'description' => 'sometimes|required|string|max:255', 'manufacturer' => 'nullable|string|max:255',
            'status' => ['sometimes', Rule::in(Submittal::STATUSES)], 'returned_at' => 'nullable|date', 'notes' => 'nullable|string',
        ]));

        return response()->json($this->present($s));
    }

    public function removeSample(int $jobId, int $id, int $sampleId)
    {
        $s = $this->submittal($jobId, $id);
        SubmittalSample::where('submittal_id', $s->id)->findOrFail($sampleId)->delete();

        return response()->json($this->present($s));
    }

    // ---- linked job POs ----

    public function linkPo(Request $request, int $jobId, int $id)
    {
        $s = $this->submittal($jobId, $id);
        $poId = $request->validate(['purchase_order_id' => 'required|integer'])['purchase_order_id'];
        // Only job POs of the same job can be linked
        $po = PurchaseOrder::jobType()->where('business_job_id', $s->business_job_id)->findOrFail($poId);
        $s->purchaseOrders()->syncWithoutDetaching([$po->id]);

        return response()->json($this->present($s));
    }

    public function unlinkPo(int $jobId, int $id, int $poId)
    {
        $s = $this->submittal($jobId, $id);
        $s->purchaseOrders()->detach($poId);

        return response()->json($this->present($s));
    }
}
