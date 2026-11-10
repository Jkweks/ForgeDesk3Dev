<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShipmentCheckItem;
use App\Models\ShipmentCriterion;
use App\Models\ShipmentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Admin-managed shipment types (Metal, Glass, Doors, ...) and their pre/post checklist items. */
class ShipmentTypeController extends Controller
{
    public function index()
    {
        return response()->json(ShipmentType::with('items')->orderBy('sort_order')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:shipment_types,name', 'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ]);
        $data['sort_order'] ??= (ShipmentType::max('sort_order') ?? 0) + 10;

        return response()->json(ShipmentType::create($data)->load('items'), 201);
    }

    public function update(Request $request, int $id)
    {
        $type = ShipmentType::findOrFail($id);
        $type->update($request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('shipment_types', 'name')->ignore($type->id)],
            'is_active' => 'sometimes|boolean', 'sort_order' => 'sometimes|integer|min:0|max:100000',
        ]));

        return response()->json($type->load('items'));
    }

    /** Types already used by a shipment are kept (deactivate instead) so history stays intact. */
    public function destroy(int $id)
    {
        $type = ShipmentType::findOrFail($id);
        if (\App\Models\ShipmentLoad::where('shipment_type_id', $type->id)->exists()) {
            return response()->json(['message' => 'This type is used by shipments. Deactivate it instead.'], 422);
        }
        $type->delete();

        return response()->json(['message' => 'Type deleted']);
    }

    public function storeItem(Request $request, int $typeId)
    {
        $type = ShipmentType::findOrFail($typeId);

        // Normal path: attach a standard criterion from the library to this category + phase.
        if ($request->filled('criterion_id')) {
            $data = $request->validate([
                'criterion_id' => 'required|integer|exists:shipment_criteria,id', 'phase' => ['required', Rule::in(ShipmentCheckItem::PHASES)],
                'required' => 'sometimes|boolean', 'photo_required' => 'sometimes|boolean', 'critical' => 'sometimes|boolean',
            ]);
            if ($type->items()->where('phase', $data['phase'])->where('criterion_id', $data['criterion_id'])->exists()) {
                return response()->json(['message' => 'That criterion is already on this checklist.'], 422);
            }
            $criterion = ShipmentCriterion::findOrFail($data['criterion_id']);
            $row = $criterion->itemDefaults() + ['sort_order' => ($type->items()->where('phase', $data['phase'])->max('sort_order') ?? 0) + 10];

            return response()->json($type->items()->create(array_merge($row, $data)), 201);
        }

        // Inline path: define a new criterion on the spot (it joins the library too).
        $data = $this->itemRules($request, true);
        $data['sort_order'] ??= ($type->items()->where('phase', $data['phase'])->max('sort_order') ?? 0) + 10;
        $criterion = ShipmentCriterion::create(collect($data)->only(['label', 'input_type', 'unit', 'options', 'required', 'photo_required', 'critical'])->all());
        $data['criterion_id'] = $criterion->id;

        return response()->json($type->items()->create($data), 201);
    }

    /** Drag-and-drop: `ids` is the full new order of one phase's items. */
    public function reorderItems(Request $request, int $typeId)
    {
        $type = ShipmentType::findOrFail($typeId);
        $data = $request->validate([
            'phase' => ['required', Rule::in(ShipmentCheckItem::PHASES)],
            'ids' => 'required|array|max:200', 'ids.*' => 'integer|distinct',
        ]);
        $mine = $type->items()->where('phase', $data['phase'])->pluck('id');
        if ($mine->diff($data['ids'])->isNotEmpty() || collect($data['ids'])->diff($mine)->isNotEmpty()) {
            return response()->json(['message' => 'The list is out of date. Reload and try again.'], 422);
        }
        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $pos => $id) {
                ShipmentCheckItem::whereKey($id)->update(['sort_order' => ($pos + 1) * 10]);
            }
        });

        return response()->json($type->load('items'));
    }

    public function updateItem(Request $request, int $id)
    {
        $item = ShipmentCheckItem::findOrFail($id);
        $data = $this->itemRules($request, false);
        // Wording and answer type belong to the library criterion (edited there, so every category stays in step).
        if ($item->criterion_id) {
            $data = collect($data)->except(['label', 'input_type', 'unit', 'options'])->all();
        }
        $item->update($data);

        return response()->json($item->fresh());
    }

    /** Inspections snapshot label/type, so deleting an item never alters history. */
    public function destroyItem(int $id)
    {
        ShipmentCheckItem::findOrFail($id)->delete();

        return response()->json(['message' => 'Item deleted']);
    }

    private function itemRules(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'phase' => [$req, Rule::in(ShipmentCheckItem::PHASES)],
            'label' => [$req, 'string', 'max:255'],
            'input_type' => [$req, Rule::in(ShipmentCheckItem::INPUT_TYPES)],
            'unit' => 'nullable|string|max:20',
            'options' => 'nullable|array|max:50', 'options.*' => 'string|max:100',
            'required' => 'sometimes|boolean', 'photo_required' => 'sometimes|boolean',
            'critical' => 'sometimes|boolean', 'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ]);
        if (($data['input_type'] ?? null) === 'choice' && empty($data['options'])) {
            abort(response()->json(['message' => 'Choice items need at least one option.'], 422));
        }

        return $data;
    }
}
