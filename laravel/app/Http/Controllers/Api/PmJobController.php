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
        return \App\Services\Pm\PmJobScope::query(auth()->user());
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

    public function show(int $id)
    {
        $j = $this->jobs()->findOrFail($id);

        return response()->json($j->toArray() + ['status_label' => $j->status_label]);
    }

    /** Estimated vs ordered vs received per cost code for one job (job POs only). */
    public function forecast(Request $request, int $id, \App\Services\Pm\MaterialForecastService $forecast)
    {
        $job = $this->jobs()->findOrFail($id);
        $sheets = $request->filled('sheets') ? array_filter(explode(',', (string) $request->sheets)) : null;
        $category = $request->input('category', 'material');

        return response()->json($forecast->forJob($job, $sheets, $category === 'all' ? null : $category));
    }

    /** Vendors a PM can order job material from (internal vendors are for internal POs only). */
    public function vendors()
    {
        return response()->json(\App\Models\Supplier::where('is_active', true)->where('is_internal', false)
            ->orderBy('name')->get(['id', 'name', 'code', 'bidestimate_code']));
    }

    /** Which vendor each estimate tag ("Supplier" column text) refers to: BidEstimate code, learned alias, code or name. */
    public function matchVendors(Request $request)
    {
        $tags = array_values(array_unique(array_filter((array) $request->query('tags', []), fn ($t) => is_string($t) && trim($t) !== '')));
        $out = [];
        foreach (array_slice($tags, 0, 100) as $tag) {
            $s = \App\Models\Supplier::findByEstimateTag($tag);
            $out[$tag] = $s && $s->is_active && ! $s->is_internal ? ['id' => $s->id, 'name' => $s->name] : null;
        }

        return response()->json($out);
    }

    /** Remember that an estimate tag means this vendor ("Remember for future estimates"). */
    public function learnAlias(Request $request, int $supplierId)
    {
        $supplier = \App\Models\Supplier::where('is_internal', false)->findOrFail($supplierId);
        $alias = trim($request->validate(['alias' => 'required|string|max:255'])['alias']);
        $key = \App\Models\SupplierAlias::normalize($alias);
        abort_if($key === '', 422, 'That tag is empty.');

        $owner = \App\Models\SupplierAlias::where('alias_normalized', $key)->first();
        if ($owner && $owner->supplier_id !== $supplier->id) {
            return response()->json(['message' => "\"{$alias}\" already belongs to {$owner->supplier->name}."], 422);
        }
        \App\Models\SupplierAlias::firstOrCreate(['alias_normalized' => $key], ['supplier_id' => $supplier->id, 'alias' => $alias, 'source' => 'bidestimate']);

        return response()->json(['ok' => true, 'supplier' => $supplier->only(['id', 'name'])]);
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

        $me = auth()->user();
        if (! empty($data['project_manager_id']) && (int) $data['project_manager_id'] !== $me->id && ! $me->hasPermission('pm.act_for_others')) {
            return response()->json(['message' => 'You can only create jobs for yourself.', 'errors' => ['project_manager_id' => ['Not allowed to act for another project manager.']]], 403);
        }
        $data['project_manager_id'] ??= $me->id;

        $job = BusinessJob::create($data + ['status' => 'bidding', 'origin' => 'pm', 'created_by_id' => $me->id]);

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
