<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\Scopes\FabricationVisibleScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Project Management view of jobs: every stage (bidding, won, lost, active, ...).
 * Fabrication only ever sees a job once it is activated here.
 */
class PmJobController extends Controller
{
    /** Allowed PM stage moves. Activating a job hands it to Fabrication. */
    private const MOVES = [
        'bidding' => ['won', 'lost'],
        'won' => ['active', 'lost', 'bidding'],
        'lost' => ['bidding'],
    ];

    private function jobs()
    {
        return BusinessJob::withoutGlobalScope(FabricationVisibleScope::class);
    }

    public function index(Request $request)
    {
        $q = $this->jobs();
        if ($request->filled('status')) {
            $q->whereIn('status', array_filter(explode(',', (string) $request->status)));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn ($w) => $w->where('job_number', 'like', "%{$s}%")
                ->orWhere('job_name', 'like', "%{$s}%")
                ->orWhere('customer_name', 'like', "%{$s}%"));
        }

        return response()->json($q->orderByDesc('updated_at')->limit(500)->get()
            ->map(fn ($j) => $j->toArray() + ['status_label' => $j->status_label]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'job_number' => ['required', 'string', 'max:50', Rule::unique('business_jobs', 'job_number')->whereNull('deleted_at')],
            'job_name' => 'required|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'project_manager_id' => 'nullable|exists:users,id',
            'site_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'target_completion_date' => 'nullable|date',
        ]);

        $job = BusinessJob::create($data + ['status' => 'bidding', 'created_by_id' => auth()->id()]);

        return response()->json($job->fresh()->toArray() + ['status_label' => $job->status_label], 201);
    }

    public function setStage(Request $request, int $id)
    {
        $job = $this->jobs()->findOrFail($id);
        $stage = $request->validate(['stage' => 'required|in:bidding,won,lost,active'])['stage'];

        $allowed = self::MOVES[$job->status] ?? [];
        if (! in_array($stage, $allowed, true)) {
            return response()->json([
                'message' => "A {$job->status} job cannot move to {$stage}",
            ], 422);
        }

        $job->update(['status' => $stage]);

        return response()->json($job->fresh()->toArray() + ['status_label' => $job->status_label]);
    }
}
