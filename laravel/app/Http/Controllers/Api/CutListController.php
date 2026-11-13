<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\Part;
use App\Models\FdUser;
use App\Models\FdWorkOrder;
use App\Services\CutFlow\CutConsumptionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Services\CutFlow\SillQuarterPointService;
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
                    'job_name' => $workOrders->get($job->work_order_id)?->businessJob?->job_name,
                    'work_order_label' => $workOrders->get($job->work_order_id)?->releaseLabel(),
                    'parts_count' => $job->parts_count,
                    'qty_original' => $original,
                    'qty_cut' => $original - $remaining,
                    'status' => $this->jobStatus($original, $remaining),
                    'updated_at' => $job->updated_at,
                ];
            }),
        ]);
    }

    /** Active work orders that don't have a cut list yet, for the "New cut list" picker. */
    public function workOrderOptions()
    {
        $taken = CutJob::whereNotNull('work_order_id')->pluck('work_order_id')->all();

        $orders = FdWorkOrder::active()->with('businessJob')
            ->whereNotIn('id', $taken)
            ->orderByDesc('id')->limit(500)->get();

        return response()->json([
            'work_orders' => $orders->map(fn (FdWorkOrder $wo) => [
                'id' => $wo->id,
                'label' => $wo->releaseLabel(),
                'job_name' => $wo->businessJob?->job_name,
                'name' => trim(($wo->businessJob?->job_name ?: 'Work order').' '.$wo->releaseLabel()),
            ])->values(),
        ]);
    }

    /**
     * Create an empty cut list, either for a work order (one list per work order — the
     * configurator ingest keys on work_order_id) or standalone with just a name.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'work_order_id' => 'nullable|integer',
            'name' => 'nullable|string|max:255',
        ]);

        $workOrder = null;
        if (! empty($data['work_order_id'])) {
            $workOrder = FdWorkOrder::with('businessJob')->find($data['work_order_id']);
            if (! $workOrder) {
                throw ValidationException::withMessages(['work_order_id' => 'Work order not found.']);
            }
            if ($existing = CutJob::where('work_order_id', $workOrder->id)->first()) {
                return response()->json([
                    'error' => 'That work order already has a cut list.',
                    'existing_id' => $existing->id,
                ], 422);
            }
        }

        $name = trim($data['name'] ?? '');
        if ($name === '' && $workOrder) {
            $name = trim(($workOrder->businessJob?->job_name ?: 'Work order').' '.$workOrder->releaseLabel());
        }
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Enter a name or choose a work order.']);
        }
        if (CutJob::where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'Another cut list already has that name.']);
        }

        $job = CutJob::create(['name' => $name, 'work_order_id' => $workOrder?->id]);

        return response()->json(['message' => 'Cut list created.', 'id' => $job->id], 201);
    }

    public function show(int $id, SillQuarterPointService $sillReport)
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
                'has_sill_report' => $sillReport->hasStoredUpload($job->id),
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

    /** Active shop users, for the "who cut it" picker. */
    public function cutters()
    {
        return response()->json(['users' => FdUser::where('active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    /**
     * Tag pieces as cut on another saw: one cut-log entry per piece (operator + date given by the user,
     * no stick or label), qty_remaining drawn down, and stock consumed exactly as a cut at the station
     * (CutConsumptionService: SOF work orders deduct inventory, every job feeds the material usage report).
     * Cut-station stats count these; times are not meaningful (stamped at midday of the chosen date).
     * Body: operator_id, cut_date (Y-m-d, not future), parts: [{part_id, qty}] (omit parts = everything remaining).
     */
    public function markCut(Request $request, int $id, CutConsumptionService $consumption)
    {
        $data = $request->validate([
            'operator_id' => 'required|integer',
            'cut_date' => 'required|date_format:Y-m-d',
            'parts' => 'nullable|array',
            'parts.*.part_id' => 'required|integer',
            'parts.*.qty' => 'required|integer|min:1',
        ]);

        $job = CutJob::findOrFail($id);
        $operator = FdUser::find($data['operator_id']);
        if (! $operator) {
            throw ValidationException::withMessages(['operator_id' => 'Choose who cut these.']);
        }

        $cutAt = Carbon::createFromFormat('Y-m-d', $data['cut_date'], 'America/New_York')->setTime(12, 0);
        if ($data['cut_date'] > now('America/New_York')->toDateString()) {
            throw ValidationException::withMessages(['cut_date' => 'The cut date can\'t be in the future.']);
        }
        $cutAt->setTimezone('UTC');

        $parts = $job->parts()->get()->keyBy('id');
        $requested = isset($data['parts'])
            ? collect($data['parts'])->groupBy('part_id')->map(fn ($rows) => (int) $rows->sum('qty'))
            : $parts->filter(fn (Part $p) => $p->qty_remaining > 0)->map(fn (Part $p) => (int) $p->qty_remaining);

        if ($requested->isEmpty()) {
            throw ValidationException::withMessages(['parts' => 'Nothing left to mark as cut.']);
        }
        foreach ($requested as $partId => $qty) {
            $part = $parts->get($partId);
            if (! $part) {
                throw ValidationException::withMessages(['parts' => 'A line doesn\'t belong to this cut list.']);
            }
            if ($qty > $part->qty_remaining) {
                throw ValidationException::withMessages(['parts' => "{$part->name}: only {$part->qty_remaining} left to cut, can't mark {$qty}."]);
            }
        }

        $count = 0;
        foreach ($requested as $partId => $qty) {
            $part = $parts->get($partId);
            for ($i = 0; $i < $qty; $i++) {
                $part->decrement('qty_remaining');
                \App\Services\CutFlow\CutStepSync::afterPartCut($part);
                $entry = new CutLogEntry([
                    'uuid' => (string) Str::uuid(),
                    'part_id' => $part->id,
                    'part_name' => $part->name,
                    'finish' => $part->finish,
                    'operator_id' => $operator->id,
                    'operator_name' => $operator->name,
                    'cut_job_id' => $job->id,
                    'job_name' => $job->name,
                    'work_order' => $part->work_order,
                    'phase' => $part->phase,
                    'description' => $part->description,
                    'dimension_inches' => $part->dimension_inches,
                    'stick_length_label' => 'Other saw',
                    'type' => 'planned',
                    'is_external' => true,
                ]);
                $entry->created_at = $cutAt;
                $entry->updated_at = $cutAt;
                $entry->save();

                try {
                    $consumption->recordCut($entry);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Cut list: failed to record stock consumption for external cut', ['cut_log_entry_id' => $entry->id, 'message' => $e->getMessage()]);
                }
                $count++;
            }
        }

        return response()->json(['message' => "{$count} piece(s) marked as cut by {$operator->name}.", 'count' => $count]);
    }

    /** PDF of sill start/end/quarter points (and receptor check) built from the CSV stored at upload. */
    public function sillQuarterPoints(int $id, SillQuarterPointService $service)
    {
        $job = CutJob::findOrFail($id);
        $report = $service->buildForCutJob($job->id);
        abort_if($report === null, 404, 'No sill data was stored for this cut list. Re-upload the cut list CSV.');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.sill-quarter-points', ['report' => $report, 'title' => $job->name]);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('SillQuarterPoints_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $job->name).'.pdf');
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
                'is_external' => $e->is_external,
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

    /** Part numbers + finish codes for the bulk-add grid's pickers. */
    public function partOptions()
    {
        return response()->json([
            'part_numbers' => \App\Models\Product::query()
                ->whereNotNull('part_number')->where('part_number', '!=', '')
                ->distinct()->orderBy('part_number')->pluck('part_number'),
            'finishes' => \App\Models\Product::$finishCodes,
        ]);
    }

    /**
     * Bulk add: many part/finish groups, each with many qty + length rows, in one transaction.
     * Rows repeating the same part+finish+length are summed; a line that already exists on the
     * list (same identity as a hand-added line) has the quantity added to it.
     */
    public function bulkStoreParts(Request $request, int $id)
    {
        $job = CutJob::findOrFail($id);
        $v = $request->validate([
            'groups' => 'required|array|min:1|max:200',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.finish' => 'nullable|string|max:255',
            'groups.*.rows' => 'required|array|min:1|max:500',
            'groups.*.rows.*.qty' => 'required|integer|min:1|max:100000',
            'groups.*.rows.*.length' => 'required|string|max:50',
        ]);

        $lines = [];
        foreach ($v['groups'] as $gi => $group) {
            $name = trim($group['name']);
            $finish = trim($group['finish'] ?? '') ?: null;
            foreach ($group['rows'] as $ri => $row) {
                $raw = str_replace(['"', '”', '″'], '', $row['length']);
                $raw = preg_replace('/(\d)\s*-\s*(\d+\s*\/)/', '$1 $2', $raw);
                $dim = round(Dimension::parse($raw), 3);
                if ($dim <= 0) {
                    throw ValidationException::withMessages([
                        "groups.$gi.rows.$ri.length" => "{$name}: \"{$row['length']}\" is not a valid length (e.g. 96, 48.375 or 96 3/8).",
                    ]);
                }
                $key = $name.'|'.$finish.'|'.$dim;
                $lines[$key] = [
                    'name' => $name, 'finish' => $finish, 'dimension_inches' => $dim,
                    'qty' => ($lines[$key]['qty'] ?? 0) + (int) $row['qty'],
                ];
            }
        }

        $created = 0;
        $merged = 0;
        \Illuminate\Support\Facades\DB::connection('cutflow')->transaction(function () use ($job, $lines, &$created, &$merged) {
            foreach ($lines as $l) {
                $existing = Part::where('cut_job_id', $job->id)
                    ->where('name', $l['name'])->where('finish', $l['finish'])
                    ->where('dimension_inches', $l['dimension_inches'])
                    ->whereNull('description')->whereNull('phase')->whereNull('row')->whereNull('column')
                    ->first();

                if ($existing) {
                    $existing->update([
                        'qty_original' => $existing->qty_original + $l['qty'],
                        'qty_remaining' => $existing->qty_remaining + $l['qty'],
                    ]);
                    $merged++;
                } else {
                    $job->parts()->create([
                        'name' => $l['name'], 'finish' => $l['finish'], 'dimension_inches' => $l['dimension_inches'],
                        'qty_original' => $l['qty'], 'qty_remaining' => $l['qty'], 'source' => 'manual',
                    ]);
                    $created++;
                }
            }
        });

        $this->markDiverged($job);

        return response()->json([
            'message' => "Added {$created} new line(s)".($merged ? ", updated {$merged} existing" : '').'.',
            'created' => $created, 'merged' => $merged,
        ], 201);
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

    /** Mirrors the parts table's unique profile+dimension+use+elevation identity (hand-added lines have no row/column). */
    private function assertUnique(int $jobId, array $data, ?int $ignorePartId = null): void
    {
        $dupe = Part::where('cut_job_id', $jobId)
            ->where('name', $data['name'])
            ->where('finish', $data['finish'])
            ->where('dimension_inches', $data['dimension_inches'])
            ->where('description', $data['description'])
            ->where('phase', $data['phase'])
            ->whereNull('row')
            ->whereNull('column')
            ->when($ignorePartId, fn ($q) => $q->where('id', '!=', $ignorePartId))
            ->exists();

        if ($dupe) {
            throw ValidationException::withMessages([
                'name' => 'A line with the same profile, finish, length, use and elevation already exists — edit its quantity instead.',
            ]);
        }
    }
}
