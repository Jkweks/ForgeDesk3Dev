<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldAsset;
use App\Models\FieldAssetType;
use App\Models\FieldAssetWorkOrder;
use App\Models\FieldSyncLog;
use App\Services\Fleet\FieldSync;
use App\Services\Fleet\FleetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FleetController extends Controller
{
    use Concerns\GatesFleetCosts;

    public function __construct(private FleetService $fleet, private FieldSync $sync) {}

    public function dashboard(Request $request)
    {
        $upcoming = $this->fleet->upcoming(90);
        $byStatus = FieldAsset::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $out = [
            'assets_by_status' => $byStatus,
            'total_assets' => FieldAsset::inService()->count(),
            'overdue_plans' => collect($upcoming['plans'])->where('state', 'overdue')->count(),
            'due_soon_plans' => collect($upcoming['plans'])->where('state', 'due_soon')->count(),
            'expired_compliance' => collect($upcoming['compliance'])->where('state', 'expired')->count(),
            'expiring_compliance' => collect($upcoming['compliance'])->where('state', 'expiring')->count(),
            'open_work_orders' => FieldAssetWorkOrder::open()->count(),
            'inbox_pending' => FieldSyncLog::where('status', 'needs_review')->whereNull('resolved_at')->count(),
            'sync' => $this->sync->status(),
            'plans' => array_slice($upcoming['plans'], 0, 10),
            'compliance' => array_slice($upcoming['compliance'], 0, 10),
        ];

        if ($this->canSeeCosts($request)) {
            $out['cost_12_months'] = (float) FieldAssetWorkOrder::where('status', 'completed')
                ->whereDate('completed_on', '>=', now()->subYear()->toDateString())
                ->selectRaw('coalesce(sum(coalesce(labor_cost,0)+coalesce(parts_cost,0)+coalesce(outside_cost,0)),0) as t')->value('t');
        }

        return response()->json($out);
    }

    public function upcoming(Request $request)
    {
        return response()->json($this->fleet->upcoming(min((int) $request->query('days', 90), 365)));
    }

    // ---- types ---------------------------------------------------------------------------

    public function types()
    {
        return response()->json(FieldAssetType::withCount('assets')->orderBy('name')->get());
    }

    public function storeType(Request $request)
    {
        return response()->json(FieldAssetType::create($this->typeRules($request)), 201);
    }

    public function updateType(Request $request, int $id)
    {
        $type = FieldAssetType::findOrFail($id);
        $type->update($this->typeRules($request, $type));

        return response()->json($type->fresh());
    }

    public function destroyType(int $id)
    {
        $type = FieldAssetType::withCount('assets')->findOrFail($id);
        if ($type->assets_count > 0) {
            return response()->json(['message' => 'This type is in use. Deactivate it instead.'], 422);
        }
        $type->delete();

        return response()->json(['message' => 'Type deleted']);
    }

    private function typeRules(Request $request, ?FieldAssetType $type = null): array
    {
        $req = $type ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:100', Rule::unique('field_asset_types', 'name')->ignore($type?->id)],
            'category' => ['sometimes', Rule::in(['vehicle', 'trailer', 'heavy_equipment', 'access_equipment', 'storage', 'power_tools', 'other'])],
            'meter_type' => ['sometimes', Rule::in(['odometer_miles', 'engine_hours', 'none'])],
            'default_checklist' => 'nullable|array|max:100',
            'default_checklist.*.label' => 'required|string|max:255',
            'default_checklist.*.critical' => 'boolean',
            'auto_out_of_service_on_fail' => 'boolean',
            'is_active' => 'boolean',
        ]);
    }

    // ---- field inbox ---------------------------------------------------------------------

    public function inbox(Request $request)
    {
        $q = FieldSyncLog::query();
        if ($request->query('status') !== 'all') {
            $q->whereIn('status', ['needs_review', 'rejected'])->whereNull('resolved_at');
        }

        return response()->json($q->orderByDesc('id')->paginate(50));
    }

    /** Accept a held meter reading as a correction, or dismiss any held/rejected item. */
    public function resolveInbox(Request $request, int $id)
    {
        $log = FieldSyncLog::whereNull('resolved_at')->findOrFail($id);
        $data = $request->validate(['action' => ['required', Rule::in(['apply_meter', 'dismiss'])]]);

        if ($data['action'] === 'apply_meter') {
            $asset = FieldAsset::where('asset_number', $log->asset_number)->first();
            $reading = $log->payload['reading'] ?? null;
            if ($log->kind !== 'meter' || ! $asset || ! is_numeric($reading) || $log->status !== 'needs_review') {
                return response()->json(['message' => 'Only held meter readings can be applied.'], 422);
            }
            $row = $this->fleet->recordMeter($asset, (float) $reading, 'field_app', $request->user()->id, true);
            $log->target_type = 'meter_reading';
            $log->target_id = $row->id;
        }

        $log->fill(['resolved_by' => $request->user()->id, 'resolved_at' => now()])->save();

        return response()->json($log);
    }
}
