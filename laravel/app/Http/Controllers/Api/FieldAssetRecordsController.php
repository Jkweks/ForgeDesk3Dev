<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldAsset;
use App\Models\FieldAssetCompliance;
use App\Models\FieldAssetInspection;
use App\Services\Fleet\FleetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Inspections + compliance records for field/fleet assets. */
class FieldAssetRecordsController extends Controller
{
    public function __construct(private FleetService $fleet) {}

    // ---- inspections ---------------------------------------------------------------------

    public function inspections(int $assetId)
    {
        FieldAsset::findOrFail($assetId);

        return response()->json(FieldAssetInspection::with(['items', 'performedBy:id,name'])
            ->where('field_asset_id', $assetId)->orderByDesc('performed_at')->limit(100)->get());
    }

    /** Blank checklist for the asset's type, to prefill the inspection form. */
    public function checklist(int $assetId)
    {
        $asset = FieldAsset::with('type')->findOrFail($assetId);

        return response()->json([
            'template_name' => $asset->type->name.' inspection',
            'items' => $asset->type->default_checklist ?? [],
            'auto_out_of_service_on_fail' => $asset->type->auto_out_of_service_on_fail,
        ]);
    }

    public function storeInspection(Request $request, int $assetId)
    {
        $asset = FieldAsset::with('type')->findOrFail($assetId);
        $data = $request->validate([
            'template_name' => 'nullable|string|max:255',
            'performed_at' => 'nullable|date|before_or_equal:now',
            'meter' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'create_work_order' => 'boolean',
            'mark_out_of_service' => 'boolean',
            'items' => 'required|array|min:1|max:100',
            'items.*.label' => 'required|string|max:255',
            'items.*.critical' => 'boolean',
            'items.*.result' => ['required', Rule::in(['pass', 'fail', 'na'])],
            'items.*.comment' => 'nullable|string|max:1000',
        ]);

        $result = FieldAssetInspection::resultFor($data['items']);

        $inspection = DB::transaction(function () use ($asset, $data, $result, $request) {
            $inspection = FieldAssetInspection::create([
                'field_asset_id' => $asset->id,
                'template_name' => $data['template_name'] ?? null,
                'performed_by_user_id' => $request->user()->id,
                'performed_at' => $data['performed_at'] ?? now(),
                'meter' => $data['meter'] ?? null,
                'result' => $result,
                'notes' => $data['notes'] ?? null,
                'source' => 'office',
            ]);
            $inspection->items()->createMany(array_map(fn ($i) => [
                'label' => $i['label'], 'critical' => (bool) ($i['critical'] ?? false),
                'result' => $i['result'], 'comment' => $i['comment'] ?? null,
            ], $data['items']));

            if (isset($data['meter']) && ($asset->current_meter === null || (float) $data['meter'] >= (float) $asset->current_meter)) {
                $this->fleet->recordMeter($asset, (float) $data['meter'], 'inspection', $request->user()->id);
            }

            return $inspection;
        });

        $workOrder = null;
        if ($result !== 'pass' && ($data['create_work_order'] ?? true)) {
            $workOrder = $this->fleet->defectWorkOrder($inspection);
        }
        if ($result === 'fail' && ($data['mark_out_of_service'] ?? $asset->type->auto_out_of_service_on_fail)
            && ! in_array($asset->status, FieldAsset::INACTIVE_STATUSES, true)) {
            $asset->update(['status' => 'out_of_service']);
        }

        return response()->json(['inspection' => $inspection->load('items'), 'work_order' => $workOrder, 'asset_status' => $asset->fresh()->status], 201);
    }

    public function createWorkOrderFromInspection(int $id)
    {
        $inspection = FieldAssetInspection::findOrFail($id);
        if ($inspection->items()->where('result', 'fail')->doesntExist()) {
            return response()->json(['message' => 'This inspection has no failed items.'], 422);
        }

        return response()->json($this->fleet->defectWorkOrder($inspection, 'office'), 201);
    }

    // ---- compliance ----------------------------------------------------------------------

    public function compliance(int $assetId)
    {
        FieldAsset::findOrFail($assetId);

        return response()->json(FieldAssetCompliance::where('field_asset_id', $assetId)->orderBy('expires_on')->get());
    }

    public function storeCompliance(Request $request, int $assetId)
    {
        FieldAsset::findOrFail($assetId);

        return response()->json(FieldAssetCompliance::create($this->complianceRules($request) + ['field_asset_id' => $assetId]), 201);
    }

    public function updateCompliance(Request $request, int $id)
    {
        $row = FieldAssetCompliance::findOrFail($id);
        $row->update($this->complianceRules($request, true));

        return response()->json($row->fresh());
    }

    public function destroyCompliance(int $id)
    {
        FieldAssetCompliance::findOrFail($id)->delete();

        return response()->json(['message' => 'Compliance record deleted']);
    }

    private function complianceRules(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'kind' => [$partial ? 'sometimes' : 'required', Rule::in(FieldAssetCompliance::KINDS)],
            'reference_number' => 'nullable|string|max:100',
            'issued_on' => 'nullable|date', 'expires_on' => 'nullable|date|after_or_equal:issued_on',
            'issuer' => 'nullable|string|max:255', 'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);
    }
}
