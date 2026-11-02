<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShipmentCheckItem;
use App\Models\ShipmentType;
use Illuminate\Http\Request;
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
        $data = $this->itemRules($request, true);
        $data['sort_order'] ??= ($type->items()->where('phase', $data['phase'])->max('sort_order') ?? 0) + 10;

        return response()->json($type->items()->create($data), 201);
    }

    public function updateItem(Request $request, int $id)
    {
        $item = ShipmentCheckItem::findOrFail($id);
        $item->update($this->itemRules($request, false));

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
