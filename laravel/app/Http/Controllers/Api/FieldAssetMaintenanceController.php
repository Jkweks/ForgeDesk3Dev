<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldAsset;
use App\Models\FieldAssetMaintPlan;
use App\Models\FieldAssetWorkOrder;
use App\Services\Fleet\FleetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Maintenance plans + work orders for field/fleet assets (not the shop maintenance module). */
class FieldAssetMaintenanceController extends Controller
{
    use Concerns\GatesFleetCosts;

    public function __construct(private FleetService $fleet) {}

    // ---- plans ---------------------------------------------------------------------------

    public function plans(int $assetId)
    {
        FieldAsset::findOrFail($assetId);

        return response()->json(FieldAssetMaintPlan::with('asset:id,current_meter')->where('field_asset_id', $assetId)->orderBy('title')->get());
    }

    public function storePlan(Request $request, int $assetId)
    {
        $asset = FieldAsset::findOrFail($assetId);
        $data = $this->planRules($request);
        $data['field_asset_id'] = $asset->id;
        $data['starts_on'] = $data['starts_on'] ?? now()->toDateString();
        $data['starts_meter'] = $data['starts_meter'] ?? $asset->current_meter;

        $plan = FieldAssetMaintPlan::create($data);

        return response()->json($this->fleet->recalcPlan($plan), 201);
    }

    public function updatePlan(Request $request, int $id)
    {
        $plan = FieldAssetMaintPlan::findOrFail($id);
        $plan->update($this->planRules($request, true));

        return response()->json($this->fleet->recalcPlan($plan->fresh()));
    }

    public function destroyPlan(int $id)
    {
        FieldAssetMaintPlan::findOrFail($id)->delete();

        return response()->json(['message' => 'Plan deleted']);
    }

    /** Create plans on an asset from a type's template checklist-style definitions. */
    public function applyTemplate(Request $request, int $assetId)
    {
        $asset = FieldAsset::findOrFail($assetId);
        $data = $request->validate([
            'plans' => 'required|array|min:1|max:50',
            'plans.*.title' => 'required|string|max:255',
            'plans.*.interval_days' => 'nullable|integer|min:1',
            'plans.*.interval_meter' => 'nullable|numeric|min:1',
        ]);

        $created = collect($data['plans'])->map(function ($p) use ($asset) {
            if (empty($p['interval_days']) && empty($p['interval_meter'])) {
                return null;
            }

            return $this->fleet->recalcPlan(FieldAssetMaintPlan::create($p + [
                'field_asset_id' => $asset->id, 'starts_on' => now()->toDateString(), 'starts_meter' => $asset->current_meter,
            ]));
        })->filter()->values();

        return response()->json($created, 201);
    }

    // ---- work orders ---------------------------------------------------------------------

    public function index(Request $request)
    {
        $q = FieldAssetWorkOrder::with('asset:id,asset_number,name');
        if ($request->filled('asset_id')) {
            $q->where('field_asset_id', $request->query('asset_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        } elseif ($request->boolean('open_only')) {
            $q->open();
        }
        if ($request->filled('type')) {
            $q->where('type', $request->query('type'));
        }

        $rows = $q->orderByRaw("case status when 'completed' then 1 when 'cancelled' then 2 else 0 end")
            ->orderByDesc('id')->paginate(min((int) $request->query('per_page', 50), 200));
        if (! $this->canSeeCosts($request)) {
            $rows->getCollection()->each->makeHidden(FieldAssetWorkOrder::COST_FIELDS);
        }

        return response()->json($rows);
    }

    public function show(Request $request, int $id)
    {
        $wo = FieldAssetWorkOrder::with(['asset:id,asset_number,name', 'items', 'documents', 'plan:id,title'])->findOrFail($id);
        if (! $this->canSeeCosts($request)) {
            $wo->makeHidden(FieldAssetWorkOrder::COST_FIELDS);
        }

        return response()->json($wo);
    }

    public function store(Request $request)
    {
        $data = $this->woRules($request);
        $data = $this->withoutCosts($request, $data, FieldAssetWorkOrder::COST_FIELDS);
        $data += [
            'wo_number' => FieldAssetWorkOrder::nextNumber(), 'reported_by_user_id' => $request->user()->id,
            'reported_on' => $data['reported_on'] ?? now()->toDateString(), 'source' => 'office',
        ];
        $wo = FieldAssetWorkOrder::create($data);
        $this->syncItems($wo, $request);
        $this->applyAssetStatus($wo);

        return response()->json($wo->fresh('items'), 201);
    }

    public function update(Request $request, int $id)
    {
        $wo = FieldAssetWorkOrder::findOrFail($id);
        $data = $this->withoutCosts($request, $this->woRules($request, true), FieldAssetWorkOrder::COST_FIELDS);
        if ($wo->status === 'completed' && ($data['status'] ?? 'completed') !== 'completed') {
            return response()->json(['message' => 'Completed work orders cannot be reopened.'], 422);
        }
        $wo->update($data);
        $this->syncItems($wo, $request);
        $this->applyAssetStatus($wo);

        return response()->json($wo->fresh('items'));
    }

    public function complete(Request $request, int $id)
    {
        $wo = FieldAssetWorkOrder::findOrFail($id);
        if (in_array($wo->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => "Work order is already {$wo->status}."], 422);
        }
        $data = $this->withoutCosts($request, $request->validate([
            'completed_on' => 'nullable|date|before_or_equal:today',
            'meter_at_service' => 'nullable|numeric|min:0',
            'performed_by' => 'nullable|string|max:255', 'vendor' => 'nullable|string|max:255',
            'labor_hours' => 'nullable|numeric|min:0', 'labor_cost' => 'nullable|numeric|min:0',
            'parts_cost' => 'nullable|numeric|min:0', 'outside_cost' => 'nullable|numeric|min:0',
            'downtime_days' => 'nullable|integer|min:0', 'invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:5000',
        ]), FieldAssetWorkOrder::COST_FIELDS);

        $wo = $this->fleet->completeWorkOrder($wo, $data, $request->user()->id);
        $this->syncItems($wo, $request);

        return response()->json($wo->fresh(['items', 'plan']));
    }

    public function destroy(int $id)
    {
        FieldAssetWorkOrder::findOrFail($id)->delete();

        return response()->json(['message' => 'Work order deleted']);
    }

    // ---- helpers -------------------------------------------------------------------------

    private function planRules(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'title' => [$req, 'string', 'max:255'], 'description' => 'nullable|string|max:2000',
            'interval_days' => 'nullable|integer|min:1|max:3650', 'interval_meter' => 'nullable|numeric|min:1',
            'warn_days' => 'nullable|integer|min:0|max:365', 'warn_meter' => 'nullable|numeric|min:0',
            'starts_on' => 'nullable|date', 'starts_meter' => 'nullable|numeric|min:0',
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'default_vendor' => 'nullable|string|max:255', 'estimated_cost' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);
        if (! $partial && empty($data['interval_days']) && empty($data['interval_meter'])) {
            abort(response()->json(['message' => 'A plan needs a day interval, a meter interval, or both.', 'errors' => ['interval_days' => ['Required']]], 422));
        }

        return $data;
    }

    private function woRules(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'field_asset_id' => [$req, 'exists:field_assets,id'],
            'plan_id' => 'nullable|exists:field_asset_maint_plans,id',
            'type' => ['sometimes', Rule::in(FieldAssetWorkOrder::TYPES)],
            'status' => ['sometimes', Rule::in(array_diff(FieldAssetWorkOrder::STATUSES, ['completed']))],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'title' => [$req, 'string', 'max:255'], 'description' => 'nullable|string|max:5000',
            'reported_on' => 'nullable|date', 'scheduled_for' => 'nullable|date',
            'meter_at_service' => 'nullable|numeric|min:0',
            'performed_by' => 'nullable|string|max:255', 'vendor' => 'nullable|string|max:255',
            'labor_hours' => 'nullable|numeric|min:0', 'labor_cost' => 'nullable|numeric|min:0',
            'parts_cost' => 'nullable|numeric|min:0', 'outside_cost' => 'nullable|numeric|min:0',
            'downtime_days' => 'nullable|integer|min:0', 'invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:5000',
        ]);
    }

    private function syncItems(FieldAssetWorkOrder $wo, Request $request): void
    {
        if (! $request->has('items')) {
            return;
        }
        $items = $request->validate([
            'items' => 'array|max:100', 'items.*.description' => 'required|string|max:255',
            'items.*.qty' => 'nullable|numeric|min:0', 'items.*.unit_cost' => 'nullable|numeric|min:0',
        ])['items'];

        $wo->items()->delete();
        foreach ($items as $i) {
            $wo->items()->create([
                'description' => $i['description'], 'qty' => $i['qty'] ?? 1,
                'unit_cost' => $this->canSeeCosts($request) ? ($i['unit_cost'] ?? null) : null,
            ]);
        }
    }

    /** An asset with an in-progress/waiting repair is flagged in_repair (never overrides retired/sold). */
    private function applyAssetStatus(FieldAssetWorkOrder $wo): void
    {
        $asset = $wo->asset;
        if ($wo->type !== 'preventive' && in_array($wo->status, ['in_progress', 'waiting_parts'], true)
            && ! in_array($asset->status, [...FieldAsset::INACTIVE_STATUSES, 'out_of_service'], true)) {
            $asset->update(['status' => 'in_repair']);
        }
    }
}
