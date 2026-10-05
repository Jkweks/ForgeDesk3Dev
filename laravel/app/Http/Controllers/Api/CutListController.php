<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\Part;
use App\Models\FdWorkOrder;
use App\Support\Dimension;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * ForgeDesk-side view/edit of CutFlow cut lists (cut_jobs + parts on the
 * 'cutflow' connection). A part is editable only while it's untouched — no
 * cut logged and never planned onto a stick — so the cut-station's history
 * and stick plans can't be invalidated. Once cutting has begun a part is
 * read-only here, alongside its cut log.
 *
 * Caveat: a job linked to a work order is re-synced from the configurator on
 * every release (CutlistIngestService matches parts on name+finish+dimension
 * +use), so edits to those lines can be overwritten/duplicated by a later
 * release. The UI warns about this.
 */
class CutListController extends Controller
{
    public function index()
    {
        $jobs = CutJob::withCount('parts')
            ->withSum('parts as qty_original', 'qty_original')
            ->withSum('parts as qty_remaining', 'qty_remaining')
            ->orderByDesc('updated_at')
            ->get();

        $workOrders = FdWorkOrder::with('businessJob')
            ->whereIn('id', $jobs->pluck('work_order_id')->filter())
            ->get()->keyBy('id');

        return response()->json([
            'cut_lists' => $jobs->map(function (CutJob $job) use ($workOrders) {
                $original = (int) $job->qty_original;
                $remaining = (int) $job->qty_remaining;

                return [
                    'id' => $job->id,
                    'name' => $job->name,
                    'work_order_id' => $job->work_order_id,
                    'bom_diverged' => $job->bom_diverged_at !== null,
                    'work_order_label' => $workOrders->get($job->work_order_id)?->release_label,
                    'parts_count' => $job->parts_count,
                    'qty_original' => $original,
                    'qty_cut' => $original - $remaining,
                    'status' => $this->jobStatus($original, $remaining),
                    'updated_at' => $job->updated_at,
                ];
            }),
        ]);
    }

    public function show(int $id)
    {
        $job = CutJob::findOrFail($id);
        $parts = $job->parts()->withCount(['cutLogEntries', 'stickItems'])
            ->orderBy('name')->orderBy('finish')->orderBy('dimension_inches')->get();

        $original = (int) $parts->sum('qty_original');
        $remaining = (int) $parts->sum('qty_remaining');

        return response()->json([
            'cut_list' => [
                'id' => $job->id,
                'name' => $job->name,
                'work_order_id' => $job->work_order_id,
                'bom_diverged' => $job->bom_diverged_at !== null,
                'status' => $this->jobStatus($original, $remaining),
                'can_delete' => $parts->every(fn (Part $p) => $this->isEditable($p)),
                'parts' => $parts->map(fn (Part $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'finish' => $p->finish,
                    'dimension_inches' => (float) $p->dimension_inches,
                    'dimension_label' => Dimension::toFraction((float) $p->dimension_inches),
                    'qty_original' => $p->qty_original,
                    'qty_remaining' => $p->qty_remaining,
                    'phase' => $p->phase,
                    'description' => $p->description,
                    'left_cut_angle' => $p->left_cut_angle !== null ? (float) $p->left_cut_angle : null,
                    'right_cut_angle' => $p->right_cut_angle !== null ? (float) $p->right_cut_angle : null,
                    'status' => $p->status_label,
                    'editable' => $this->isEditable($p),
                ])->values(),
            ],
        ]);
    }

    /** Cut history for a list — what's been cut, by whom, when. */
    public function log(int $id)
    {
        $entries = CutLogEntry::where('cut_job_id', $id)
            ->orderByDesc('created_at')->limit(1000)->get();

        return response()->json([
            'entries' => $entries->map(fn (CutLogEntry $e) => [
                'id' => $e->id,
                'part_name' => $e->part_name,
                'finish' => $e->finish,
                'description' => $e->description,
                'dimension_inches' => (float) $e->dimension_inches,
                'dimension_label' => Dimension::toFraction((float) $e->dimension_inches),
                'stick_length_label' => $e->stick_length_label,
                'operator_name' => $e->operator_name,
                'is_recut' => $e->is_recut,
                'cut_at' => $e->cut_at_local?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $job = CutJob::findOrFail($id);

        if (CutJob::where('name', $data['name'])->where('id', '!=', $id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Another cut list already has that name.']);
        }

        $job->update($data);

        return response()->json(['message' => 'Cut list renamed.', 'name' => $job->name]);
    }

    public function destroy(int $id)
    {
        $job = CutJob::with('parts')->findOrFail($id);
        $parts = $job->parts()->withCount(['cutLogEntries', 'stickItems'])->get();

        if ($parts->contains(fn (Part $p) => ! $this->isEditable($p))) {
            return response()->json(['error' => 'This cut list has been (partly) cut and can no longer be deleted.'], 422);
        }

        $job->delete(); // parts cascade

        return response()->json(['message' => 'Cut list deleted.']);
    }

    public function storePart(Request $request, int $id)
    {
        $job = CutJob::findOrFail($id);
        $data = $this->validatePart($request);

        $this->assertUnique($job->id, $data);

        $part = $job->parts()->create($data + ['qty_remaining' => $data['qty_original'], 'source' => 'manual']);
        $this->markDiverged($job);

        return response()->json(['message' => 'Line added.', 'id' => $part->id], 201);
    }

    public function updatePart(Request $request, int $id, int $partId)
    {
        $part = Part::where('cut_job_id', $id)->withCount(['cutLogEntries', 'stickItems'])->findOrFail($partId);

        if (! $this->isEditable($part)) {
            return response()->json(['error' => 'This line has already been cut or planned on a stick and can no longer be edited.'], 422);
        }

        $data = $this->validatePart($request);
        $this->assertUnique($id, $data, $part->id);

        $part->update($data + ['qty_remaining' => $data['qty_original']]);
        $this->markDiverged(CutJob::find($id));

        return response()->json(['message' => 'Line updated.']);
    }

    public function destroyPart(int $id, int $partId)
    {
        $part = Part::where('cut_job_id', $id)->withCount(['cutLogEntries', 'stickItems'])->findOrFail($partId);

        if (! $this->isEditable($part)) {
            return response()->json(['error' => 'This line has already been cut or planned on a stick and can no longer be deleted.'], 422);
        }

        $part->delete();
        $this->markDiverged(CutJob::find($id));

        return response()->json(['message' => 'Line deleted.']);
    }

    /**
     * A hand edit to a work order's cut list is allowed (rare fixes) but means the list no longer
     * matches the configurations' BOM — remembered here so those configurations can say so.
     */
    private function markDiverged(?CutJob $job): void
    {
        if ($job && $job->work_order_id && ! $job->bom_diverged_at) {
            $job->update(['bom_diverged_at' => now()]);
        }
    }

    private function isEditable(Part $part): bool
    {
        return $part->qty_remaining === $part->qty_original
            && ($part->cut_log_entries_count ?? $part->cutLogEntries()->count()) === 0
            && ($part->stick_items_count ?? $part->stickItems()->count()) === 0;
    }

    private function jobStatus(int $original, int $remaining): string
    {
        if ($original > 0 && $remaining <= 0) {
            return 'Done';
        }

        return $remaining < $original ? 'In progress' : 'Not cut';
    }

    private function validatePart(Request $request): array
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'finish' => 'nullable|string|max:255',
            'dimension' => 'required|string|max:50',
            'qty' => 'required|integer|min:1|max:100000',
            'phase' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'left_cut_angle' => 'nullable|numeric|between:-90,90',
            'right_cut_angle' => 'nullable|numeric|between:-90,90',
        ]);

        $dimension = round(Dimension::parse($v['dimension']), 3);
        if ($dimension <= 0) {
            throw ValidationException::withMessages(['dimension' => 'Enter a length greater than zero (e.g. 96, 48.375 or 96 3/8).']);
        }

        return [
            'name' => trim($v['name']),
            'finish' => trim($v['finish'] ?? '') ?: null,
            'dimension_inches' => $dimension,
            'qty_original' => (int) $v['qty'],
            'phase' => trim($v['phase'] ?? '') ?: null,
            'description' => trim($v['description'] ?? '') ?: null,
            'left_cut_angle' => $v['left_cut_angle'] ?? null,
            'right_cut_angle' => $v['right_cut_angle'] ?? null,
        ];
    }

    /** Mirrors the parts table's unique profile+dimension+use identity. */
    private function assertUnique(int $jobId, array $data, ?int $ignorePartId = null): void
    {
        $dupe = Part::where('cut_job_id', $jobId)
            ->where('name', $data['name'])
            ->where('finish', $data['finish'])
            ->where('dimension_inches', $data['dimension_inches'])
            ->where('description', $data['description'])
            ->when($ignorePartId, fn ($q) => $q->where('id', '!=', $ignorePartId))
            ->exists();

        if ($dupe) {
            throw ValidationException::withMessages([
                'name' => 'A line with the same profile, finish, length and use already exists — edit its quantity instead.',
            ]);
        }
    }
}
