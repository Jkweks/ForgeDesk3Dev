<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldAsset;
use App\Models\FieldAssetType;
use App\Models\User;
use App\Services\Fleet\FleetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class FieldAssetController extends Controller
{
    use Concerns\GatesFleetCosts;

    public function __construct(private FleetService $fleet) {}

    public function index(Request $request)
    {
        $q = FieldAsset::with(['type:id,name,category,meter_type', 'assignedUser:id,name', 'job:id,job_number,job_name'])
            ->withCount(['workOrders as open_work_orders_count' => fn ($w) => $w->open()]);

        if ($s = trim((string) $request->query('search'))) {
            $like = '%'.mb_strtolower(str_replace(['%', '_'], ['\%', '\_'], $s)).'%';
            $q->where(function ($w) use ($like) {
                foreach (['asset_number', 'name', 'vin', 'license_plate', 'serial_number'] as $col) {
                    $w->orWhereRaw("lower({$col}) like ?", [$like]);
                }
            });
        }
        if ($request->filled('type_id')) {
            $q->where('field_asset_type_id', $request->query('type_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        } elseif (! $request->boolean('include_inactive')) {
            $q->inService();
        }
        if ($request->filled('assigned_user_id')) {
            $q->where('assigned_user_id', $request->query('assigned_user_id'));
        }
        if ($request->filled('job_id')) {
            $q->where('current_business_job_id', $request->query('job_id'));
        }
        if ($request->boolean('overdue_only')) {
            $q->whereHas('plans', fn ($p) => $p->where('is_active', true)->where(fn ($w) => $w
                ->whereDate('next_due_at', '<', now()->toDateString())
                ->orWhereColumn('next_due_meter', '<=', 'field_assets.current_meter')));
        }

        $rows = $q->orderBy('asset_number')->paginate(min((int) $request->query('per_page', 50), 200));
        if (! $this->canSeeCosts($request)) {
            $rows->getCollection()->each->makeHidden(FieldAsset::COST_FIELDS);
        }

        return response()->json($rows);
    }

    public function filterOptions()
    {
        return response()->json([
            'types' => FieldAssetType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'category', 'meter_type']),
            'statuses' => FieldAsset::STATUSES,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'next_asset_number' => FieldAsset::nextAssetNumber(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $asset = FieldAsset::with(['type', 'assignedUser:id,name', 'homeLocation:id,name', 'job:id,job_number,job_name', 'parent:id,asset_number,name', 'children:id,asset_number,name,parent_asset_id,status'])
            ->findOrFail($id);
        if (! $this->canSeeCosts($request)) {
            $asset->makeHidden(FieldAsset::COST_FIELDS);
        }

        return response()->json($asset);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['asset_number'] = $data['asset_number'] ?? FieldAsset::nextAssetNumber();
        $data = $this->withoutCosts($request, $data, FieldAsset::COST_FIELDS);

        $asset = FieldAsset::create($data);
        if ($request->filled('current_meter')) {
            $this->fleet->recordMeter($asset, (float) $request->input('current_meter'), 'manual', $request->user()->id);
        }

        return response()->json($asset->fresh('type'), 201);
    }

    public function update(Request $request, int $id)
    {
        $asset = FieldAsset::findOrFail($id);
        $data = $this->withoutCosts($request, $this->validated($request, $asset), FieldAsset::COST_FIELDS);
        $asset->update($data);

        return response()->json($asset->fresh('type'));
    }

    public function destroy(int $id)
    {
        $asset = FieldAsset::findOrFail($id);
        $asset->children()->update(['parent_asset_id' => null]);
        $asset->delete(); // soft delete; files/documents are kept

        return response()->json(['message' => 'Asset deleted']);
    }

    public function meter(Request $request, int $id)
    {
        $asset = FieldAsset::findOrFail($id);
        $data = $request->validate([
            'reading' => 'required|numeric|min:0',
            'is_correction' => 'boolean',
            'read_at' => 'nullable|date|before_or_equal:now',
        ]);

        try {
            $row = $this->fleet->recordMeter($asset, (float) $data['reading'], 'manual', $request->user()->id,
                (bool) ($data['is_correction'] ?? false), isset($data['read_at']) ? \Carbon\Carbon::parse($data['read_at']) : null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['reading' => $row, 'asset' => $asset->fresh()]);
    }

    public function meterHistory(int $id)
    {
        return response()->json(FieldAsset::findOrFail($id)->meterReadings()->limit(100)->get());
    }

    public function status(Request $request, int $id)
    {
        $asset = FieldAsset::findOrFail($id);
        $data = $request->validate([
            'status' => ['required', Rule::in(FieldAsset::STATUSES)],
            'note' => 'nullable|string|max:500',
        ]);
        $asset->update(['status' => $data['status']]);

        return response()->json($asset->fresh());
    }

    /** Merged history: work orders, inspections, compliance, meter readings. */
    public function timeline(Request $request, int $id)
    {
        $asset = FieldAsset::findOrFail($id);
        $costs = $this->canSeeCosts($request);
        $events = collect();

        foreach ($asset->workOrders()->get() as $w) {
            $events->push(['at' => ($w->completed_on ?? $w->reported_on ?? $w->created_at)->format('Y-m-d'), 'kind' => 'work_order', 'id' => $w->id,
                'title' => "{$w->wo_number} · {$w->title}", 'detail' => ucfirst(str_replace('_', ' ', $w->status)).($costs ? ' · $'.$w->total_cost : '')]);
        }
        foreach ($asset->inspections()->get() as $i) {
            $events->push(['at' => $i->performed_at->format('Y-m-d'), 'kind' => 'inspection', 'id' => $i->id,
                'title' => 'Inspection: '.($i->template_name ?: 'General'), 'detail' => str_replace('_', ' ', $i->result).' · '.$i->source]);
        }
        foreach ($asset->compliance()->get() as $c) {
            $events->push(['at' => ($c->issued_on ?? $c->created_at)->format('Y-m-d'), 'kind' => 'compliance', 'id' => $c->id,
                'title' => ucfirst(str_replace('_', ' ', $c->kind)).' recorded', 'detail' => $c->expires_on ? 'Expires '.$c->expires_on->format('Y-m-d') : '']);
        }
        foreach ($asset->meterReadings()->limit(50)->get() as $m) {
            $events->push(['at' => $m->read_at->format('Y-m-d'), 'kind' => 'meter', 'id' => $m->id,
                'title' => 'Meter '.number_format((float) $m->reading, 1), 'detail' => $m->source.($m->is_correction ? ' (correction)' : '')]);
        }

        return response()->json($events->sortByDesc('at')->values()->take(200));
    }

    private function validated(Request $request, ?FieldAsset $asset = null): array
    {
        return $request->validate([
            'asset_number' => ['nullable', 'string', 'max:50', Rule::unique('field_assets', 'asset_number')->ignore($asset?->id)->withoutTrashed()],
            'name' => [$asset ? 'sometimes' : 'required', 'string', 'max:255'],
            'field_asset_type_id' => [$asset ? 'sometimes' : 'required', 'exists:field_asset_types,id'],
            'status' => ['sometimes', Rule::in(FieldAsset::STATUSES)],
            'make' => 'nullable|string|max:100', 'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'serial_number' => 'nullable|string|max:100', 'vin' => 'nullable|string|max:50',
            'license_plate' => 'nullable|string|max:30', 'plate_state' => 'nullable|string|max:8',
            'purchase_date' => 'nullable|date', 'purchase_cost' => 'nullable|numeric|min:0',
            'vendor' => 'nullable|string|max:255', 'warranty_expires_on' => 'nullable|date',
            'assigned_user_id' => 'nullable|exists:users,id',
            'home_location_id' => 'nullable|exists:company_locations,id',
            'current_location_text' => 'nullable|string|max:255',
            'current_business_job_id' => 'nullable|exists:business_jobs,id',
            'parent_asset_id' => ['nullable', 'exists:field_assets,id', Rule::notIn($asset ? [$asset->id] : [])],
            'notes' => 'nullable|string|max:5000',
        ]);
    }
}
