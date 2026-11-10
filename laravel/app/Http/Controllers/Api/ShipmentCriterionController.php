<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShipmentCheckItem;
use App\Models\ShipmentCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The standard-criteria library that categories (shipment types) draw their pre/post checks from. */
class ShipmentCriterionController extends Controller
{
    public function index()
    {
        return response()->json(ShipmentCriterion::withCount('checkItems')->with('checkItems:id,criterion_id,shipment_type_id,phase')
            ->orderBy('label')->get());
    }

    public function store(Request $request)
    {
        return response()->json(ShipmentCriterion::create($this->rules($request, true))->loadCount('checkItems'), 201);
    }

    /** Label/type edits flow to every category row using the criterion; the flags are only the defaults for new attachments. */
    public function update(Request $request, int $id)
    {
        $criterion = ShipmentCriterion::findOrFail($id);
        $data = $this->rules($request, false);
        if (($data['input_type'] ?? $criterion->input_type) === 'choice' && empty($data['options'] ?? $criterion->options)) {
            return response()->json(['message' => 'Choice items need at least one option.'], 422);
        }
        DB::transaction(function () use ($criterion, $data) {
            $criterion->update($data);
            $criterion->propagate();
        });

        return response()->json($criterion->fresh()->loadCount('checkItems'));
    }

    public function destroy(int $id)
    {
        $criterion = ShipmentCriterion::findOrFail($id);
        $n = $criterion->checkItems()->count();
        if ($n) {
            return response()->json(['message' => "This criterion is used by $n category checklist item".($n === 1 ? '' : 's').'. Remove it from those first (or deactivate it).'], 422);
        }
        $criterion->delete();

        return response()->json(['message' => 'Criterion deleted']);
    }

    private function rules(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'label' => [$req, 'string', 'max:255'],
            'input_type' => [$req, Rule::in(ShipmentCheckItem::INPUT_TYPES)],
            'unit' => 'nullable|string|max:20',
            'options' => 'nullable|array|max:50', 'options.*' => 'string|max:100',
            'required' => 'sometimes|boolean', 'photo_required' => 'sometimes|boolean',
            'critical' => 'sometimes|boolean', 'is_active' => 'sometimes|boolean',
        ]);
        if ($creating && ($data['input_type'] ?? null) === 'choice' && empty($data['options'])) {
            abort(response()->json(['message' => 'Choice items need at least one option.'], 422));
        }
        if (($data['input_type'] ?? null) && $data['input_type'] !== 'choice') {
            $data['options'] = null;
        }

        return $data;
    }
}
