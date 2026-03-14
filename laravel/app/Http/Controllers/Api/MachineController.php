<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MachineType;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    public function index(Request $request)
    {
        $query = Machine::with(['machineType', 'maintenanceTasks']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('equipment_type', 'like', "%{$search}%")
                  ->orWhere('manufacturer', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%");
            });
        }

        if ($request->has('location')) {
            $query->where('location', $request->location);
        }

        if ($request->has('equipment_type')) {
            $query->where('equipment_type', $request->equipment_type);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'equipment_type' => 'required|max:255',
            'machine_type_id' => 'nullable|exists:machine_types,id',
            'manufacturer' => 'nullable|max:255',
            'model' => 'nullable|max:255',
            'serial_number' => 'nullable|max:255',
            'location' => 'nullable|max:255',
            'documents' => 'nullable|array',
            'notes' => 'nullable',
        ]);

        $machine = Machine::create($validated);

        return response()->json($machine->load('machineType'), 201);
    }

    public function show(Machine $machine)
    {
        return response()->json($machine->load([
            'machineType',
            'maintenanceTasks',
            'maintenanceRecords.task',
            'assets'
        ]));
    }

    public function update(Request $request, Machine $machine)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'equipment_type' => 'required|max:255',
            'machine_type_id' => 'nullable|exists:machine_types,id',
            'manufacturer' => 'nullable|max:255',
            'model' => 'nullable|max:255',
            'serial_number' => 'nullable|max:255',
            'location' => 'nullable|max:255',
            'documents' => 'nullable|array',
            'notes' => 'nullable',
        ]);

        $machine->update($validated);

        return response()->json($machine->load('machineType'));
    }

    public function destroy(Machine $machine)
    {
        $machine->delete();
        return response()->json(null, 204);
    }

    public function getTypes()
    {
        return response()->json(MachineType::all());
    }
}
