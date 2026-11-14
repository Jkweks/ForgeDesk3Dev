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
        $s->refresh()->load(['revisions', 'responses.file', 'samples', 'purchaseOrders:id,po_number', 'items.file:id,original_name', 'packages.file:id,original_name,size']);

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

    // ---- package builder ----

    /** Add a document to the package: a library document, or an uploaded PDF. */
    public function addItem(Request $request, int $jobId, int $id, PmFileIngestor $files)
    {
        $s = $this->submittal($jobId, $id);
        $request->validate([
            'product_document_id' => 'required_without:file|nullable|integer|exists:pm_product_documents,id',
            'file' => 'required_without:product_document_id|nullable|file|max:51200|extensions:pdf',
            'label' => 'nullable|string|max:255',
        ]);

        if ($request->filled('product_document_id')) {
            $doc = \App\Models\PmProductDocument::with(['file', 'product:id,name'])->findOrFail($request->product_document_id);
            $fileId = $doc->pm_file_id;
            $label = $request->input('label') ?: trim($doc->product->name.' — '.($doc->label ?: $doc->file->original_name));
        } else {
            [$file] = $files->registerUpload($request->file('file'), 'submittal_item', $s->job, $request->user());
            $fileId = $file->id;
            $label = $request->input('label') ?: $file->original_name;
        }
        $s->items()->create([
            'pm_file_id' => $fileId, 'pm_product_document_id' => $doc->id ?? null, 'label' => $label,
            'sort_order' => ($s->items()->max('sort_order') ?? -1) + 1,
        ]);

        return response()->json($this->present($s), 201);
    }

    public function removeItem(int $jobId, int $id, int $itemId)
    {
        $s = $this->submittal($jobId, $id);
        $s->items()->findOrFail($itemId)->delete();

        return response()->json($this->present($s));
    }

    public function reorderItems(Request $request, int $jobId, int $id)
    {
        $s = $this->submittal($jobId, $id);
        $order = $request->validate(['order' => 'required|array', 'order.*' => 'integer'])['order'];
        $own = $s->items()->pluck('id')->all();
        abort_unless(count($order) === count($own) && ! array_diff($order, $own), 422, 'The order must list every item once.');
        foreach ($order as $i => $itemId) {
            $s->items()->whereKey($itemId)->update(['sort_order' => $i]);
        }

        return response()->json($this->present($s));
    }

    /** Cover sheet + every item, merged into one PDF and stored as a numbered package. */
    public function buildPackage(Request $request, int $jobId, int $id, PmFileIngestor $files, \App\Services\Pm\PmWorkerClient $worker)
    {
        $s = $this->submittal($jobId, $id);
        $items = $s->items()->with('file')->get();
        if ($items->isEmpty()) {
            return response()->json(['message' => 'Add at least one document first.'], 422);
        }
        $notPdf = $items->first(fn ($i) => strtolower(pathinfo($i->file->original_name, PATHINFO_EXTENSION)) !== 'pdf');
        if ($notPdf) {
            return response()->json(['message' => "\"{$notPdf->label}\" is not a PDF, so it cannot be merged into the package."], 422);
        }

        $cover = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.pm-submittal-cover', [
            'submittal' => $s, 'job' => $s->job, 'items' => $items,
            'preparedBy' => $request->user()->name ?? $request->user()->email, 'date' => now()->format('F j, Y'),
        ])->output();

        try {
            $merged = $worker->mergePdfs(array_merge([$cover], $items->map(fn ($i) => $files->bytes($i->file))->all()));
        } catch (\App\Services\Pm\PmWorkerDataException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Http\Client\ConnectionException|\RuntimeException $e) {
            return response()->json(['message' => 'The PDF worker is unavailable: '.$e->getMessage()], 503);
        }

        $rev = $s->revision_number ? "_Rev{$s->revision_number}" : '';
        $name = "Submittal_{$s->job->job_number}_{$s->number}{$rev}.pdf";
        $file = $files->registerBytes($merged, $name, 'submittal_package', $s->job, $request->user());
        $s->packages()->create([
            'revision_number' => $s->revision_number, 'pm_file_id' => $file->id,
            'item_count' => $items->count(), 'created_by' => $request->user()->id,
        ]);

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
