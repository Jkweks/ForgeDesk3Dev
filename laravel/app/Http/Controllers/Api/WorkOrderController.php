<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\FdWoStatusLog;
use App\Services\WorkOrderCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = FdWorkOrder::with([
                'businessJob',
                'assignedUsers',
                // Light columns only — enough for the time-estimate roll-up.
                'elevations:id,work_order_id,joint_qty,template_set_id,date_completed',
                'elevations.stages:id,elevation_id,minutes_per_joint,status',
                'elevations.templateSet:id,minutes_per_joint',
                'steps:id,work_order_id,status',
            ])
                ->withCount([
                    'elevations',
                    'elevations as elevations_complete' => fn ($q) => $q->whereNotNull('date_completed'),
                ])
                ->withMin('elevations as elevation_due_first', 'date_requested')
                ->withMax('elevations as elevation_due_last', 'date_requested');

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
                ->map(fn ($wo) => $this->formatWo($wo));

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
                'completedByUser',
                'statusLog.user',
                'drawings',
                'steps.completedBy',
                'elevations.elevationType',
                'elevations.completedBy',
                'elevations.templateSet',
                'elevations.stages.assignedTo',
                'elevations.stages.completedBy',
            ])->findOrFail($id);

            $data = $this->formatWo($wo);
            $data['status_log'] = $wo->statusLog->map(fn ($l) => [
                'id' => $l->id,
                'from_status' => $l->from_status,
                'to_status' => $l->to_status,
                'note' => $l->note,
                'user_name' => $l->user?->name,
                'created_at' => $l->created_at?->toIso8601String(),
            ])->values();
            $data['steps'] = $wo->steps->map(fn ($s) => [
                'id' => $s->id,
                'work_order_id' => $s->work_order_id,
                'name' => $s->name,
                'sort_order' => $s->sort_order,
                'status' => $s->status,
                'completed_by_id' => $s->completed_by_id,
                'completed_by_name' => $s->completedBy?->name,
                'completed_at' => $s->completed_at?->toIso8601String(),
            ])->values();
            $data['drawings'] = $wo->drawings->map(fn ($d) => [
                'id' => $d->id,
                'original_name' => $d->original_name,
                'file_size' => $d->file_size,
                'file_mime' => $d->file_mime,
                'download_url' => "/api/v1/work-orders/{$wo->id}/drawings/{$d->id}/download",
                'created_at' => $d->created_at->toIso8601String(),
            ])->values();
            $data['elevations'] = $wo->elevations->map(fn ($e) => $this->formatElevation($e))->values();

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
            $nextRelease = FdWorkOrder::where('business_job_id', $request->business_job_id)->max('release_number') + 1;
            $nextPriority = FdWorkOrder::where('archived', false)->max('priority') + 1;

            $releaseCode = trim((string) $request->input('release_code'));

            $wo = FdWorkOrder::create([
                'business_job_id' => $request->business_job_id,
                'release_number' => $nextRelease,
                'release_code' => $releaseCode !== '' ? mb_substr($releaseCode, 0, 50) : null,
                'date_issued' => $request->date_issued,
                'due_date' => $request->due_date,
                'material_delivery' => $request->material_delivery,
                'notes' => $request->notes,
                'priority' => $nextPriority,
            ]);

            // Slot the new WO into the due-date ranking.
            FdWorkOrder::resequencePriorities();

            $job = BusinessJob::find($request->business_job_id);
            $releaseLabel = $job ? "{$job->job_number}-{$wo->release_token}" : "{$wo->release_token}";

            return response()->json([
                'id' => $wo->id,
                'release_number' => $wo->release_number,
                'release_code' => $wo->release_code,
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
                'date_issued', 'due_date', 'planned_start_date', 'planned_completion_date',
                'material_delivery', 'estimated_minutes_override',
                'notes', 'priority', 'priority_locked',
            ]));

            // Empty string from the form reverts to the computed roll-up.
            if ($request->has('estimated_minutes_override') && ! $request->filled('estimated_minutes_override')) {
                $wo->estimated_minutes_override = null;
            }

            // Custom release code — blank reverts the label to "R{release_number}".
            if ($request->has('release_code')) {
                $code = trim((string) $request->input('release_code'));
                $wo->release_code = $code !== '' ? mb_substr($code, 0, 50) : null;
            }

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
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'integer|distinct',
        ])['ordered_ids'];

        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $id) {
                FdWorkOrder::whereKey($id)->update([
                    'priority' => $i + 1,
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

    /**
     * Drive the work order's lifecycle status.
     *
     * Body: { status: active|on_hold|complete, note?: string }
     *  - on_hold  requires a note (the reason for the hold).
     *  - complete requires every elevation complete AND every WO step done;
     *    otherwise 422 with the outstanding items. Stamps completed_at /
     *    completed_by_user_id and rolls the parent job's auto-status.
     * Every transition writes an fd_wo_status_log row.
     */
    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(FdWorkOrder::STATUSES)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $note = trim((string) ($data['note'] ?? '')) ?: null;

        if ($data['status'] === 'on_hold' && $note === null) {
            return response()->json([
                'error' => 'A note is required when placing a work order on hold.',
                'code' => 'note_required',
            ], 422);
        }

        try {
            $wo = FdWorkOrder::with(['elevations.stages', 'steps', 'businessJob'])->findOrFail($id);
            $from = $wo->status;
            $to = $data['status'];

            if ($to === 'complete' && $from !== 'complete' && ! $wo->isReadyToComplete()) {
                return response()->json([
                    'error' => 'This work order is not ready to be completed.',
                    'code' => 'not_ready',
                    'blockers' => $wo->completionBlockers(),
                ], 422);
            }

            DB::transaction(function () use ($wo, $request, $from, $to, $note) {
                $wo->status = $to;

                if ($to === 'complete') {
                    $wo->completed_at = $wo->completed_at ?? now();
                    $wo->completed_by_user_id = $wo->completed_by_user_id ?? $request->user()?->id;
                } elseif ($from === 'complete') {
                    // Re-opening: clear the completion bookkeeping so a later
                    // completion re-stamps and the email can be offered again.
                    $wo->completed_at = null;
                    $wo->completed_by_user_id = null;
                    $wo->completion_email_sent_at = null;
                }

                $wo->save();

                FdWoStatusLog::create([
                    'work_order_id' => $wo->id,
                    'user_id' => $request->user()?->id,
                    'from_status' => $from,
                    'to_status' => $to,
                    'note' => $note,
                ]);
            });

            // Roll WO completion up into the parent job's status (no-op when the
            // job is on hold/cancelled or other WOs/reservations are still open).
            $wo->businessJob?->syncAutoStatus();

            $fresh = FdWorkOrder::with(['businessJob', 'assignedUsers', 'elevations.stages', 'steps'])->findOrFail($id);

            return response()->json([
                'updated' => $id,
                'work_order' => $this->formatWo($fresh),
            ]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@updateStatus failed', ['id' => $id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to update work order status'], 500);
        }
    }

    /**
     * Send the "work order completed" notice to the job's project manager and
     * the admins. Manager / admin app users only — this is the escape hatch for
     * when a non-manager confirmed the completion. Body: { note?: string }.
     */
    public function sendCompletionEmail(Request $request, int $id, WorkOrderCompletionService $completion)
    {
        if (! in_array($request->user()?->role, ['admin', 'manager'], true)) {
            return response()->json(['error' => 'Only a manager or admin can send the completion email.'], 403);
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $note = trim((string) ($data['note'] ?? '')) ?: null;

        try {
            $wo = FdWorkOrder::with('businessJob')->findOrFail($id);

            if ($wo->status !== 'complete') {
                return response()->json([
                    'error' => 'The work order must be marked complete before its completion email can be sent.',
                    'code' => 'not_complete',
                ], 422);
            }

            $recipients = $completion->send($wo, $note, $request->user()?->name);

            if ($recipients === []) {
                return response()->json([
                    'error' => 'No recipients found — link a project manager to the job or add an active admin.',
                    'code' => 'no_recipients',
                ], 422);
            }

            $wo->completion_email_sent_at = now();
            $wo->save();

            FdWoStatusLog::create([
                'work_order_id' => $wo->id,
                'user_id' => $request->user()?->id,
                'from_status' => 'complete',
                'to_status' => 'complete',
                'note' => 'Completion email sent to '.implode(', ', $recipients)
                    .($note ? " — {$note}" : ''),
            ]);

            return response()->json(['sent' => true, 'recipients' => $recipients]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@sendCompletionEmail failed', ['id' => $id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to send completion email'], 500);
        }
    }

    public function updateAssignments(Request $request, int $id)
    {
        try {
            $wo = FdWorkOrder::with('elevations.stages.assignees')->findOrFail($id);

            $newIds = collect($request->input('user_ids', []))
                ->map(fn ($v) => (int) $v)->filter()->unique()->values();
            $oldIds = $wo->assignedUsers()->pluck('fd_users.id')->map(fn ($v) => (int) $v);

            $added = $newIds->diff($oldIds);   // now on the WO, weren't before
            $removed = $oldIds->diff($newIds);   // dropped from the WO

            DB::transaction(function () use ($wo, $newIds, $added, $removed) {
                $wo->assignedUsers()->sync($newIds->all());

                if ($added->isEmpty() && $removed->isEmpty()) {
                    return;
                }

                // Push the delta down to every still-open elevation stage: added
                // workers join the queue, removed workers drop out of it.
                foreach ($wo->elevations as $elev) {
                    foreach ($elev->stages as $stage) {
                        if (in_array($stage->status, FdWoStage::TERMINAL, true)) {
                            continue;
                        }

                        $current = $stage->assignees->pluck('id')->map(fn ($v) => (int) $v);
                        if ($current->isEmpty() && $stage->assigned_to_id) {
                            $current = collect([(int) $stage->assigned_to_id]);
                        }

                        $next = $current->diff($removed)->merge($added)->unique()->sort()->values();

                        if ($next->all() !== $current->sort()->values()->all()) {
                            $stage->syncAssignees($next->all());
                        }
                    }
                }
            });

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
            $path = $request->file('file')->getRealPath();
            $spreadsheet = IOFactory::load($path);

            $sheet = null;
            foreach ($spreadsheet->getAllSheets() as $s) {
                if (strtoupper(trim($s->getTitle())) === 'WO') {
                    $sheet = $s;
                    break;
                }
            }

            if (! $sheet) {
                return response()->json(['error' => 'No sheet named "WO" found in this file.'], 422);
            }

            $rows = $sheet->toArray(null, true, true, false);

            // Find header row: look for a row containing "Type" and "Elevation" (or "Elev")
            $headerRow = null;
            $typeCol = null;
            $tagCol = null;
            $qtyCol = null;
            $fabCol = null;

            foreach ($rows as $rowIdx => $row) {
                $colMap = [];
                foreach ($row as $ci => $cell) {
                    $norm = strtolower(trim((string) $cell));
                    if (str_starts_with($norm, 'type')) {
                        $colMap['type'] = $ci;
                    }
                    if (str_starts_with($norm, 'elev')) {
                        $colMap['tag'] = $ci;
                    }
                    if (str_starts_with($norm, 'qty') || str_starts_with($norm, 'quan')) {
                        $colMap['qty'] = $ci;
                    }
                    if ($norm === 'fab') {
                        $colMap['fab'] = $ci;
                    }
                }
                if (isset($colMap['type'], $colMap['tag'])) {
                    $headerRow = $rowIdx;
                    $typeCol = $colMap['type'];
                    $tagCol = $colMap['tag'];
                    $qtyCol = $colMap['qty'] ?? null;
                    $fabCol = $colMap['fab'] ?? null;
                    break;
                }
            }

            if ($headerRow === null) {
                return response()->json(['error' => 'Could not find header row with "Type" and "Elevation" columns.'], 422);
            }

            // Division: find the "Division" label anywhere in the block above the
            // elevation header; its value is the first non-empty cell to the
            // right on the same row. If that comes up empty, fall back to the
            // first digit of the job-number cell (same label-then-right rule).
            $division = null;
            $jobNumber = null;
            $jobName = null;
            $projectManager = null;
            $superintendent = null;
            $jobLabels = ['job number', 'job no', 'job no.', 'job #', 'job#', 'job number:', 'job #:'];
            $jobNameLabels = ['job name', 'project', 'project name', 'project title'];
            $pmLabels = ['project manager', 'pm', 'p.m.', 'proj mgr', 'project mgr', 'projectmanager'];
            $superLabels = ['superintendent', 'super', 'supt', 'supt.', 'site superintendent', 'job superintendent', 'field superintendent'];
            foreach (array_slice($rows, 0, $headerRow) as $row) {
                foreach ($row as $colIdx => $cell) {
                    $norm = rtrim(strtolower(trim((string) $cell)), ':');
                    if ($norm === '') {
                        continue;
                    }
                    if ($division === null && str_starts_with($norm, 'division')) {
                        $division = $this->firstValueRightOf($row, (int) $colIdx);
                    }
                    if ($jobNumber === null && in_array($norm, $jobLabels, true)) {
                        $jobNumber = $this->firstValueRightOf($row, (int) $colIdx);
                    }
                    if ($jobName === null && in_array($norm, $jobNameLabels, true)) {
                        $jobName = $this->firstValueRightOf($row, (int) $colIdx);
                    }
                    if ($projectManager === null && in_array($norm, $pmLabels, true)) {
                        $projectManager = $this->firstValueRightOf($row, (int) $colIdx);
                    }
                    if ($superintendent === null && in_array($norm, $superLabels, true)) {
                        $superintendent = $this->firstValueRightOf($row, (int) $colIdx);
                    }
                }
            }

            if (($division === null || $division === '') && $jobNumber !== null
                && preg_match('/\d/', $jobNumber, $m)) {
                $division = $m[0];
            }

            // Parse data rows after the header
            $elevations = [];
            for ($i = $headerRow + 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $type = trim((string) ($row[$typeCol] ?? ''));
                $tag = trim((string) ($row[$tagCol] ?? ''));
                if ($type === '' && $tag === '') {
                    continue;
                }

                $qty = $qtyCol !== null ? intval($row[$qtyCol] ?? 1) : 1;
                $qty = max(1, $qty);

                $fab = $fabCol !== null ? strtolower(trim((string) ($row[$fabCol] ?? ''))) : '';
                $scope = ($fab === 'kit') ? 'kit' : 'assemble';

                $elevations[] = [
                    'type' => $type ?: null,
                    'tag' => $tag,
                    'quantity' => $qty,
                    'fab' => $fab,
                    'scope' => $scope,
                ];
            }

            return response()->json([
                'division' => $division !== '' ? $division : null,
                'job_number' => $jobNumber,
                'job_name' => $jobName !== '' ? $jobName : null,
                'project_manager' => $projectManager !== '' ? $projectManager : null,
                'superintendent' => $superintendent !== '' ? $superintendent : null,
                'elevations' => $elevations,
            ]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@parseExcel failed', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to parse Excel file: '.$e->getMessage()], 500);
        }
    }

    /**
     * First non-empty, trimmed cell value to the right of $fromCol in a row
     * (0-indexed array from Worksheet::toArray). Null when the rest of the row
     * is blank. Merged cells read as empty past their anchor, so this skips
     * them naturally.
     */
    private function firstValueRightOf(array $row, int $fromCol): ?string
    {
        $vals = array_values($row);
        for ($i = $fromCol + 1, $n = count($vals); $i < $n; $i++) {
            $v = trim((string) ($vals[$i] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return null;
    }

    private function normDate($v): ?string
    {
        if (! $v) {
            return null;
        }

        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : Carbon::parse($v)->format('Y-m-d');
    }

    private function formatWo(FdWorkOrder $wo): array
    {
        $job = $wo->relationLoaded('businessJob') ? $wo->businessJob : $wo->businessJob()->first();

        $users = $wo->relationLoaded('assignedUsers') ? $wo->assignedUsers : collect();

        // Earliest / latest elevation "Requested" date — from the aggregate on the
        // list query, or straight off the loaded elevations on the detail call.
        $dueFirst = $this->normDate(
            $wo->elevation_due_first
                ?? ($wo->relationLoaded('elevations') ? $wo->elevations->min('date_requested') : null)
        );
        $dueLast = $this->normDate(
            $wo->elevation_due_last
                ?? ($wo->relationLoaded('elevations') ? $wo->elevations->max('date_requested') : null)
        );

        $estimate = $wo->estimateMinutes();
        $remaining = $wo->estimateRemainingMinutes();

        $canAssessCompletion = $wo->relationLoaded('elevations') && $wo->relationLoaded('steps');
        $isReady = $canAssessCompletion ? $wo->isReadyToComplete() : null;

        return [
            'id' => $wo->id,
            'business_job_id' => $wo->business_job_id,
            'status' => $wo->status,
            'status_label' => ['active' => 'Active', 'on_hold' => 'On Hold', 'complete' => 'Complete'][$wo->status] ?? $wo->status,
            'completed_at' => $wo->completed_at?->toIso8601String(),
            'completed_by_id' => $wo->completed_by_user_id,
            'completed_by_name' => $wo->relationLoaded('completedByUser') ? $wo->completedByUser?->name : null,
            'completion_email_sent_at' => $wo->completion_email_sent_at?->toIso8601String(),
            'is_ready_to_complete' => $isReady,
            'completion_blockers' => ($canAssessCompletion && ! $isReady) ? $wo->completionBlockers() : [],
            'release_number' => $wo->release_number,
            'release_code' => $wo->release_code,
            'release_label' => $job ? "{$job->job_number}-{$wo->release_token}" : "{$wo->release_token}",
            'date_issued' => $wo->date_issued?->format('Y-m-d'),
            'due_date' => $wo->due_date?->format('Y-m-d'),
            'due_date_first' => $dueFirst ?? $wo->due_date?->format('Y-m-d'),
            'due_date_last' => $dueLast ?? $wo->due_date?->format('Y-m-d'),
            'planned_start_date' => $wo->planned_start_date?->format('Y-m-d'),
            'planned_completion_date' => $wo->planned_completion_date?->format('Y-m-d'),
            'material_delivery' => $wo->material_delivery,
            'estimated_minutes' => $estimate['effective'],
            'estimated_minutes_computed' => $estimate['computed'],
            'estimated_minutes_override' => $estimate['override'],
            'estimated_minutes_remaining' => $remaining['effective'],
            'joint_qty_total' => $wo->relationLoaded('elevations')
                ? ($wo->elevations->sum(fn ($e) => (int) $e->joint_qty) ?: null)
                : null,
            'notes' => $wo->notes,
            'archived' => $wo->archived,
            'priority' => $wo->priority,
            'priority_locked' => (bool) $wo->priority_locked,
            'elevation_count' => $wo->elevations_count ?? 0,
            'elevations_complete' => $wo->elevations_complete ?? 0,
            'assigned_users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => $u->initials,
            ])->values(),
            'job' => $job ? [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'job_name' => $job->job_name,
                'project_manager' => $job->project_manager,
                'project_manager_id' => $job->project_manager_id,
                'superintendent' => $job->superintendent,
                'superintendent_id' => $job->superintendent_id,
                'division' => substr($job->job_number ?? '', 0, 1) ?: '—',
            ] : null,
            'created_at' => $wo->created_at->toIso8601String(),
            'updated_at' => $wo->updated_at->toIso8601String(),
        ];
    }

    private function formatElevation($e): array
    {
        $stages = $e->relationLoaded('stages') ? $e->stages : collect();

        $estimate = $e->estimateMinutes();
        $remaining = $e->remainingMinutes();

        return [
            'id' => $e->id,
            'elevation_type_id' => $e->elevation_type_id,
            'template_set_id' => $e->template_set_id,
            'elevation_type' => $e->elevationType ? [
                'id' => $e->elevationType->id,
                'name' => $e->elevationType->name,
                'color' => $e->elevationType->color,
            ] : null,
            'elevation_tag' => $e->elevation_tag,
            'quantity' => $e->quantity,
            'joint_qty' => $e->joint_qty,
            'minutes_per_joint' => round($estimate['rate'], 2),
            'estimated_minutes' => $estimate['effective'],
            'estimated_minutes_remaining' => $remaining['effective'],
            'date_requested' => $e->date_requested?->format('Y-m-d'),
            'date_completed' => $e->date_completed?->format('Y-m-d'),
            'completed_by_id' => $e->completed_by_id,
            'completed_by_name' => $e->completedBy?->name,
            'notes' => $e->notes,
            'scope' => $e->scope ?? 'assemble',
            'stage_count' => $stages->count(),
            'stages_done' => $stages->whereIn('status', ['complete', 'not_required'])->count(),
            'stages_active' => $stages->where('status', 'in_progress')->count(),
            'stages_blocked' => $stages->where('status', 'blocked')->count(),
            'stages' => $stages->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'status' => $s->status,
                'sort_order' => $s->sort_order,
                'phase' => $s->phase,
                'blocks_next' => (bool) $s->blocks_next,
                'minutes_per_joint' => $s->minutes_per_joint !== null ? (float) $s->minutes_per_joint : null,
                'assigned_name' => $s->assignedTo?->name,
                'completed_by_id' => $s->completed_by_id,
                'completed_by_name' => $s->completedBy?->name,
                'started_at' => $s->started_at?->toIso8601String(),
                'completed_at' => $s->completed_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
