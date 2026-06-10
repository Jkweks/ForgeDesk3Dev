<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\FdJobStep;
use Illuminate\Http\Request;

class JobStepController extends Controller
{
    public function index(int $jobId)
    {
        $job   = BusinessJob::findOrFail($jobId);
        $steps = $job->steps()->with('completedBy')->get();

        return response()->json(['steps' => $steps->map(fn($s) => $this->fmt($s))]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_job_id' => 'required|integer|exists:business_jobs,id',
            'name'            => 'required|string|max:255',
        ]);

        $max  = FdJobStep::where('business_job_id', $request->business_job_id)->max('sort_order') ?? 0;
        $step = FdJobStep::create([
            'business_job_id' => $request->business_job_id,
            'name'            => $request->name,
            'sort_order'      => $max + 1,
            'status'          => 'pending',
        ]);

        return response()->json(['step' => $this->fmt($step)], 201);
    }

    public function update(Request $request, int $id)
    {
        $step = FdJobStep::findOrFail($id);

        if ($request->has('status')) {
            $step->status = $request->status;
            if ($request->status === 'complete') {
                $step->completed_at    = now();
                $step->completed_by_id = $request->completed_by_id ?? null;
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
            'business_job_id'   => $s->business_job_id,
            'name'              => $s->name,
            'sort_order'        => $s->sort_order,
            'status'            => $s->status,
            'completed_by_id'   => $s->completed_by_id,
            'completed_by_name' => $s->completedBy?->name,
            'completed_at'      => $s->completed_at?->toIso8601String(),
        ];
    }
}
