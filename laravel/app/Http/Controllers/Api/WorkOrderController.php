<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWorkOrder;
use App\Models\BusinessJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = FdWorkOrder::with(['businessJob', 'assignedUsers'])
                ->withCount([
                    'elevations',
                    'elevations as elevations_complete' => fn($q) => $q->whereNotNull('date_completed'),
                ]);

            $archived = $request->boolean('archived', false);
            $query->where('archived', $archived);

            if ($request->filled('job_id')) {
                $query->where('business_job_id', $request->job_id);
            }

            if ($request->filled('q')) {
                $q = $request->q;
                $query->whereHas('businessJob', function ($sub) use ($q) {
                    $sub->where('job_number', 'like', "%{$q}%")
                        ->orWhere('job_name', 'like', "%{$q}%");
                });
            }

            if ($request->filled('material')) {
                $mat = $request->material;
                if ($mat === 'in_shop') {
                    $query->where('material_delivery', 'In Shop');
                } elseif ($mat === 'sof') {
                    $query->where('material_delivery', 'SOF');
                } elseif ($mat === 'pending') {
                    $query->whereNull('material_delivery');
                }
            }

            $workOrders = $query
                ->orderByRaw('priority IS NULL, priority ASC')
                ->orderBy('date_issued', 'desc')
                ->get()
                ->map(fn($wo) => $this->formatWo($wo));

            return response()->json(['work_orders' => $workOrders]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@index failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to load work orders'], 500);
        }
    }

    public function show(int $id)
    {
        try {
            $wo = FdWorkOrder::with([
                'businessJob',
                'assignedUsers',
                'drawings',
                'steps.completedBy',
                'elevations.elevationType',
                'elevations.completedBy',
                'elevations.stages.assignedTo',
                'elevations.stages.completedBy',
            ])->findOrFail($id);

            $data = $this->formatWo($wo);
            $data['steps'] = $wo->steps->map(fn($s) => [
                'id'                => $s->id,
                'work_order_id'     => $s->work_order_id,
                'name'              => $s->name,
                'sort_order'        => $s->sort_order,
                'status'            => $s->status,
                'completed_by_id'   => $s->completed_by_id,
                'completed_by_name' => $s->completedBy?->name,
                'completed_at'      => $s->completed_at?->toIso8601String(),
            ])->values();
            $data['drawings']   = $wo->drawings->map(fn($d) => [
                'id'            => $d->id,
                'original_name' => $d->original_name,
                'file_size'     => $d->file_size,
                'file_mime'     => $d->file_mime,
                'download_url'  => "/api/v1/work-orders/{$wo->id}/drawings/{$d->id}/download",
                'created_at'    => $d->created_at->toIso8601String(),
            ])->values();
            $data['elevations'] = $wo->elevations->map(fn($e) => $this->formatElevation($e))->values();

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@show failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Work order not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_job_id' => 'required|integer|exists:business_jobs,id',
        ]);

        try {
            $nextRelease  = FdWorkOrder::where('business_job_id', $request->business_job_id)->max('release_number') + 1;
            $nextPriority = FdWorkOrder::where('archived', false)->max('priority') + 1;

            $wo = FdWorkOrder::create([
                'business_job_id'   => $request->business_job_id,
                'release_number'    => $nextRelease,
                'date_issued'       => $request->date_issued,
                'due_date'          => $request->due_date,
                'material_delivery' => $request->material_delivery,
                'notes'             => $request->notes,
                'priority'          => $nextPriority,
            ]);

            // Slot the new WO into the due-date ranking.
            FdWorkOrder::resequencePriorities();

            $job = BusinessJob::find($request->business_job_id);
            $releaseLabel = $job ? "{$job->job_number}-R{$wo->release_number}" : "R{$wo->release_number}";

            return response()->json([
                'id'            => $wo->id,
                'release_number' => $wo->release_number,
                'release_label' => $releaseLabel,
            ], 201);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@store failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create work order'], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $wo = FdWorkOrder::findOrFail($id);
            $wo->fill($request->only([
                'date_issued', 'due_date', 'material_delivery', 'notes', 'priority', 'priority_locked',
            ]));

            // Typing an explicit priority number is a manual pin.
            if ($request->has('priority') && ! $request->has('priority_locked')) {
                $wo->priority_locked = true;
            }
            $wo->save();

            // Re-derive the ranking for everything else (due-date move, pin/unpin).
            if ($request->hasAny(['due_date', 'priority', 'priority_locked'])) {
                FdWorkOrder::resequencePriorities();
            }

            return response()->json(['updated' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update work order'], 500);
        }
    }

    /**
     * Rebuild the whole priority ranking from due dates on demand
     * ("Recalc from due dates" button). Optionally clear every lock first.
     */
    public function resequencePriority(Request $request)
    {
        if ($request->boolean('clear_locks')) {
            FdWorkOrder::where('archived', false)->update(['priority_locked' => false]);
        }

        FdWorkOrder::resequencePriorities();

        return response()->json(['ok' => true]);
    }

    /**
     * Apply a hand-dragged order from the Reorder Queue: each listed WO is pinned
     * (priority_locked) at its new 1-based position, in one transaction.
     */
    public function reorder(Request $request)
    {
        $ids = $request->validate([
            'ordered_ids'   => 'required|array|min:1',
            'ordered_ids.*' => 'integer|distinct',
        ])['ordered_ids'];

        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $id) {
                FdWorkOrder::whereKey($id)->update([
                    'priority'        => $i + 1,
                    'priority_locked' => true,
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function destroy(int $id)
    {
        try {
            $wo = FdWorkOrder::findOrFail($id);
            $wo->archived = true;
            $wo->save();

            return response()->json(['archived' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to archive work order'], 500);
        }
    }

    public function updateAssignments(Request $request, int $id)
    {
        try {
            $wo = FdWorkOrder::findOrFail($id);
            $userIds = $request->input('user_ids', []);
            $wo->assignedUsers()->sync($userIds);
            return response()->json(['updated' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@updateAssignments failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update assignments'], 500);
        }
    }

    public function parseExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        try {
            $path       = $request->file('file')->getRealPath();
            $spreadsheet = IOFactory::load($path);

            $sheet = null;
            foreach ($spreadsheet->getAllSheets() as $s) {
                if (strtoupper(trim($s->getTitle())) === 'WO') {
                    $sheet = $s;
                    break;
                }
            }

            if (!$sheet) {
                return response()->json(['error' => 'No sheet named "WO" found in this file.'], 422);
            }

            $rows = $sheet->toArray(null, true, true, false);

            // Row 1: scan for "Division" label; value is the next non-empty cell in the same row
            $division = null;
            $row1     = $rows[0] ?? [];
            foreach ($row1 as $colIdx => $cell) {
                if (strtolower(trim((string) $cell)) === 'division') {
                    for ($next = $colIdx + 1; $next < count($row1); $next++) {
                        $val = trim((string) ($row1[$next] ?? ''));
                        if ($val !== '') {
                            $division = $val;
                            break;
                        }
                    }
                    break;
                }
            }

            // Find header row: look for a row containing "Type" and "Elevation" (or "Elev")
            $headerRow   = null;
            $typeCol     = null;
            $tagCol      = null;
            $qtyCol      = null;
            $fabCol      = null;

            foreach ($rows as $rowIdx => $row) {
                $colMap = [];
                foreach ($row as $ci => $cell) {
                    $norm = strtolower(trim((string) $cell));
                    if (str_starts_with($norm, 'type'))       $colMap['type']     = $ci;
                    if (str_starts_with($norm, 'elev'))       $colMap['tag']      = $ci;
                    if (str_starts_with($norm, 'qty') || str_starts_with($norm, 'quan')) $colMap['qty'] = $ci;
                    if ($norm === 'fab')                       $colMap['fab']      = $ci;
                }
                if (isset($colMap['type'], $colMap['tag'])) {
                    $headerRow = $rowIdx;
                    $typeCol   = $colMap['type'];
                    $tagCol    = $colMap['tag'];
                    $qtyCol    = $colMap['qty']  ?? null;
                    $fabCol    = $colMap['fab']  ?? null;
                    break;
                }
            }

            if ($headerRow === null) {
                return response()->json(['error' => 'Could not find header row with "Type" and "Elevation" columns.'], 422);
            }

            // Parse data rows after the header
            $elevations = [];
            for ($i = $headerRow + 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $type = trim((string) ($row[$typeCol] ?? ''));
                $tag  = trim((string) ($row[$tagCol]  ?? ''));
                if ($type === '' && $tag === '') continue;

                $qty = $qtyCol !== null ? intval($row[$qtyCol] ?? 1) : 1;
                $qty = max(1, $qty);

                $fab  = $fabCol !== null ? strtolower(trim((string) ($row[$fabCol] ?? ''))) : '';
                $scope = ($fab === 'kit') ? 'kit' : 'assemble';

                $elevations[] = [
                    'type'     => $type ?: null,
                    'tag'      => $tag,
                    'quantity' => $qty,
                    'fab'      => $fab,
                    'scope'    => $scope,
                ];
            }

            return response()->json([
                'division'   => $division,
                'elevations' => $elevations,
            ]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@parseExcel failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to parse Excel file: ' . $e->getMessage()], 500);
        }
    }

    private function formatWo(FdWorkOrder $wo): array
    {
        $job = $wo->relationLoaded('businessJob') ? $wo->businessJob : $wo->businessJob()->first();

        $users = $wo->relationLoaded('assignedUsers') ? $wo->assignedUsers : collect();

        return [
            'id'                  => $wo->id,
            'business_job_id'     => $wo->business_job_id,
            'release_number'      => $wo->release_number,
            'release_label'       => $job ? "{$job->job_number}-R{$wo->release_number}" : "R{$wo->release_number}",
            'date_issued'         => $wo->date_issued?->format('Y-m-d'),
            'due_date'            => $wo->due_date?->format('Y-m-d'),
            'material_delivery'   => $wo->material_delivery,
            'notes'               => $wo->notes,
            'archived'            => $wo->archived,
            'priority'            => $wo->priority,
            'priority_locked'     => (bool) $wo->priority_locked,
            'elevation_count'     => $wo->elevations_count ?? 0,
            'elevations_complete' => $wo->elevations_complete ?? 0,
            'assigned_users'      => $users->map(fn($u) => [
                'id'       => $u->id,
                'name'     => $u->name,
                'initials' => $u->initials,
            ])->values(),
            'job'                 => $job ? [
                'id'              => $job->id,
                'job_number'      => $job->job_number,
                'job_name'        => $job->job_name,
                'project_manager' => $job->project_manager,
                'division'        => substr($job->job_number ?? '', 0, 1) ?: '—',
            ] : null,
            'created_at'          => $wo->created_at->toIso8601String(),
            'updated_at'          => $wo->updated_at->toIso8601String(),
        ];
    }

    private function formatElevation($e): array
    {
        $stages = $e->relationLoaded('stages') ? $e->stages : collect();

        return [
            'id'                => $e->id,
            'elevation_type_id' => $e->elevation_type_id,
            'template_set_id'   => $e->template_set_id,
            'elevation_type'    => $e->elevationType ? [
                'id'    => $e->elevationType->id,
                'name'  => $e->elevationType->name,
                'color' => $e->elevationType->color,
            ] : null,
            'elevation_tag'     => $e->elevation_tag,
            'quantity'          => $e->quantity,
            'date_requested'    => $e->date_requested?->format('Y-m-d'),
            'date_completed'    => $e->date_completed?->format('Y-m-d'),
            'completed_by_id'   => $e->completed_by_id,
            'completed_by_name' => $e->completedBy?->name,
            'notes'             => $e->notes,
            'scope'             => $e->scope ?? 'assemble',
            'stage_count'       => $stages->count(),
            'stages_done'       => $stages->whereIn('status', ['complete', 'not_required'])->count(),
            'stages_active'     => $stages->where('status', 'in_progress')->count(),
            'stages_blocked'    => $stages->where('status', 'blocked')->count(),
            'stages'            => $stages->map(fn($s) => [
                'id'                => $s->id,
                'name'              => $s->name,
                'status'            => $s->status,
                'sort_order'        => $s->sort_order,
                'blocks_next'       => (bool) $s->blocks_next,
                'assigned_name'     => $s->assignedTo?->name,
                'completed_by_id'   => $s->completed_by_id,
                'completed_by_name' => $s->completedBy?->name,
                'started_at'        => $s->started_at?->toIso8601String(),
                'completed_at'      => $s->completed_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
