<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdJobStep;
use App\Models\FdWorkOrder;
use App\Services\StageGateService;
use App\Services\StageOverrideResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JobStepController extends Controller
{
    public function __construct(
        private StageGateService $gate,
        private StageOverrideResolver $overrides,
    ) {}

    public function index(int $workOrderId)
    {
        $wo    = FdWorkOrder::findOrFail($workOrderId);
        $steps = $wo->steps()->with('completedBy')->get();

        return response()->json(['steps' => $steps->map(fn($s) => $this->fmt($s))]);
    }

    public function completeAll(Request $request, int $workOrderId)
    {
        $wo = FdWorkOrder::findOrFail($workOrderId);
        $resolution = $this->overrides->resolve($request);

        // completed_by_id FKs fd_users; only set it when a real fab user is named.
        $completedById = $request->filled('completed_by_id')
            ? \App\Models\FdUser::whereKey($request->completed_by_id)->value('id')
            : null;

        // Skip steps already terminal (complete/not_required) and steps on hold —
        // a bulk action should never silently clear a hold placed by the office.
        // Complete in sort order so the sequential gate clears as we go.
        $steps = $wo->steps()->where('status', 'pending')->orderBy('sort_order')->get();

        try {
            DB::transaction(function () use ($steps, $resolution, $completedById) {
                foreach ($steps as $step) {
                    $this->gate->guardJobStepTransition(
                        $step,
                        'complete',
                        $resolution['allowed'],
                        $this->overrides->jobStepLogger($step, $resolution),
                    );

                    $step->status          = 'complete';
                    $step->completed_at    = now();
                    $step->completed_by_id = $completedById;
                    $step->save();
                }
            });
        } catch (\App\Exceptions\StageGatedException $e) {
            return $e->render();
        }

        $all = $wo->steps()->with('completedBy')->get();

        return response()->json([
            'updated' => $steps->count(),
            'steps'   => $all->map(fn($s) => $this->fmt($s)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'work_order_id' => 'required|integer|exists:fd_work_orders,id',
            'name'          => 'required|string|max:255',
        ]);

        $max  = FdJobStep::where('work_order_id', $request->work_order_id)->max('sort_order') ?? 0;
        $step = FdJobStep::create([
            'work_order_id' => $request->work_order_id,
            'name'          => $request->name,
            'sort_order'    => $max + 1,
            'status'        => 'pending',
        ]);

        return response()->json(['step' => $this->fmt($step)], 201);
    }

    public function update(Request $request, int $id)
    {
        $step = FdJobStep::findOrFail($id);

        $request->validate([
            'status'   => ['sometimes', Rule::in(FdJobStep::STATUSES)],
            'override' => 'sometimes|boolean',
        ]);

        if ($request->has('status')) {
            $resolution = $this->overrides->resolve($request);
            $this->gate->guardJobStepTransition(
                $step,
                $request->status,
                $resolution['allowed'],
                $this->overrides->jobStepLogger($step, $resolution),
            );

            $step->status = $request->status;
            if ($request->status === 'complete') {
                $step->completed_at = now();
                // completed_by_id FKs fd_users; only set when a real fab user is named.
                $step->completed_by_id = $request->filled('completed_by_id')
                    ? \App\Models\FdUser::whereKey($request->completed_by_id)->value('id')
                    : null;
            } else {
                $step->completed_at    = null;
                $step->completed_by_id = null;
            }
        }

        if ($request->has('name'))       $step->name       = $request->name;
        if ($request->has('sort_order')) $step->sort_order = $request->sort_order;

        $step->save();

        return response()->json(['step' => $this->fmt($step->fresh('completedBy'))]);
    }

    public function destroy(int $id)
    {
        FdJobStep::findOrFail($id)->delete();
        return response()->json(['deleted' => $id]);
    }

    private function fmt(FdJobStep $s): array
    {
        return [
            'id'                => $s->id,
            'work_order_id'     => $s->work_order_id,
            'name'              => $s->name,
            'sort_order'        => $s->sort_order,
            'status'            => $s->status,
            'completed_by_id'   => $s->completed_by_id,
            'completed_by_name' => $s->completedBy?->name,
            'completed_at'      => $s->completed_at?->toIso8601String(),
        ];
    }
}
