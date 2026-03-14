<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MachineTooling;
use App\Models\Product;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MachineToolingController extends Controller
{
    /**
     * Get all tooling for a machine
     */
    public function index(Request $request, $machineId)
    {
        $machine = Machine::findOrFail($machineId);

        $query = MachineTooling::with(['product', 'machine', 'installationRecord', 'replacementRecord'])
            ->where('machine_id', $machineId);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by active only
        if ($request->boolean('active_only')) {
            $query->active();
        }

        // Filter by needs attention
        if ($request->boolean('needs_attention')) {
            $query->needsAttention();
        }

        $tooling = $query->orderBy('location_on_machine')->get();

        return response()->json([
            'tooling' => $tooling,
            'machine' => $machine,
        ]);
    }

    /**
     * Get all active tooling across all machines
     */
    public function all(Request $request)
    {
        $query = MachineTooling::with(['product', 'machine', 'installationRecord'])
            ->active();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by needs attention
        if ($request->boolean('needs_attention')) {
            $query->needsAttention();
        }

        // Filter by machine type
        if ($request->has('machine_type_id')) {
            $query->whereHas('machine', function ($q) use ($request) {
                $q->where('machine_type_id', $request->machine_type_id);
            });
        }

        $tooling = $query->orderBy('machine_id')
            ->orderBy('location_on_machine')
            ->get();

        return response()->json([
            'tooling' => $tooling,
        ]);
    }

    /**
     * Get all tool products with their installation status
     */
    public function inventory(Request $request)
    {
        // Get all tool products (consumable_tool and asset_tool)
        $query = Product::where(function ($q) {
            $q->whereIn('tool_type', ['consumable_tool', 'asset_tool']);
        })->where('is_active', true);

        // Filter by tool type
        if ($request->has('tool_type')) {
            $query->where('tool_type', $request->tool_type);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        // Search by SKU or description
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('part_number', 'like', "%{$search}%");
            });
        }

        $tools = $query->with([
            'categories',
            'supplier',
            'activeTooling.machine'
        ])->get();

        // Add installation status to each tool
        $tools = $tools->map(function ($tool) {
            $activeInstallations = $tool->activeTooling;

            $tool->installation_status = $activeInstallations->isEmpty() ? 'available' : 'installed';
            $tool->installations_count = $activeInstallations->count();
            $tool->installed_locations = $activeInstallations->map(function ($installation) {
                return [
                    'id' => $installation->id,
                    'machine_id' => $installation->machine_id,
                    'machine_name' => $installation->machine->name ?? 'Unknown',
                    'location_on_machine' => $installation->location_on_machine,
                    'status' => $installation->status,
                    'tool_life_percentage' => $installation->tool_life_percentage,
                ];
            });

            return $tool;
        });

        // Filter by installation status
        if ($request->has('installation_status')) {
            $tools = $tools->filter(function ($tool) use ($request) {
                return $tool->installation_status === $request->installation_status;
            })->values();
        }

        return response()->json([
            'tools' => $tools,
        ]);
    }

    /**
     * Get a specific tooling record
     */
    public function show($id)
    {
        $tooling = MachineTooling::with([
            'product',
            'machine',
            'installationRecord',
            'replacementRecord'
        ])->findOrFail($id);

        return response()->json($tooling);
    }

    /**
     * Install a new tool on a machine
     */
    public function store(Request $request, $machineId)
    {
        $machine = Machine::findOrFail($machineId);

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'location_on_machine' => 'required|string|max:255',
            'installed_at' => 'required|date',
            'installed_by' => 'nullable|string|max:255',
            'maintenance_record_id' => 'nullable|exists:maintenance_records,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($request->product_id);

        // Check if product is a tool
        if (!$product->isTool()) {
            return response()->json([
                'error' => 'The selected product is not classified as a tool.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create tooling record
            $tooling = MachineTooling::create([
                'machine_id' => $machineId,
                'product_id' => $request->product_id,
                'location_on_machine' => $request->location_on_machine,
                'installed_at' => $request->installed_at,
                'installed_by' => $request->installed_by ?? auth()->user()->name ?? 'System',
                'maintenance_record_id' => $request->maintenance_record_id,
                'tool_life_used' => 0,
                'status' => 'active',
                'notes' => $request->notes,
            ]);

            // If this is a consumable tool, decrease inventory
            if ($product->isConsumableTool()) {
                $product->adjustQuantity(-1, 'issue',
                    "Installed on {$machine->name} at {$request->location_on_machine}",
                    "Tool installation for machine tooling ID: {$tooling->id}"
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Tool installed successfully',
                'tooling' => $tooling->load(['product', 'machine']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to install tool',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update tool life used
     */
    public function updateToolLife(Request $request, $id)
    {
        $tooling = MachineTooling::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tool_life_used' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tooling->tool_life_used = $request->tool_life_used;
        $tooling->save(); // This will trigger calculateRemainingLife() in the model

        return response()->json([
            'message' => 'Tool life updated successfully',
            'tooling' => $tooling->load(['product', 'machine']),
        ]);
    }

    /**
     * Replace a tool
     */
    public function replace(Request $request, $id)
    {
        $oldTooling = MachineTooling::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tool_life_used' => 'required|numeric|min:0',
            'new_product_id' => 'required|exists:products,id',
            'maintenance_record_id' => 'nullable|exists:maintenance_records,id',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $newProduct = Product::findOrFail($request->new_product_id);

        // Check if new product is a tool
        if (!$newProduct->isTool()) {
            return response()->json([
                'error' => 'The replacement product is not classified as a tool.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update old tooling with final life data
            $oldTooling->tool_life_used = $request->tool_life_used;
            $oldTooling->markAsReplaced(
                $request->maintenance_record_id,
                auth()->user()->name ?? 'System'
            );

            // Create new tooling record
            $newTooling = MachineTooling::create([
                'machine_id' => $oldTooling->machine_id,
                'product_id' => $request->new_product_id,
                'location_on_machine' => $oldTooling->location_on_machine,
                'installed_at' => now(),
                'installed_by' => auth()->user()->name ?? 'System',
                'maintenance_record_id' => $request->maintenance_record_id,
                'tool_life_used' => 0,
                'status' => 'active',
                'notes' => $request->notes,
            ]);

            // If this is a consumable tool, decrease inventory
            if ($newProduct->isConsumableTool()) {
                $newProduct->adjustQuantity(-1, 'issue',
                    "Replaced tool on {$oldTooling->machine->name} at {$oldTooling->location_on_machine}",
                    "Tool replacement for machine tooling ID: {$newTooling->id}"
                );
            }

            // Add to maintenance record if provided
            if ($request->maintenance_record_id) {
                $maintenanceRecord = MaintenanceRecord::find($request->maintenance_record_id);

                $toolsReplaced = $maintenanceRecord->tools_replaced ?? [];
                $toolsReplaced[] = [
                    'machine_tooling_id' => $oldTooling->id,
                    'old_product_id' => $oldTooling->product_id,
                    'new_product_id' => $request->new_product_id,
                    'old_tool_life_used' => $request->tool_life_used,
                    'location_on_machine' => $oldTooling->location_on_machine,
                    'reason' => $request->reason,
                    'new_tooling_id' => $newTooling->id,
                ];

                $maintenanceRecord->tools_replaced = $toolsReplaced;
                $maintenanceRecord->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Tool replaced successfully',
                'old_tooling' => $oldTooling->load(['product', 'machine']),
                'new_tooling' => $newTooling->load(['product', 'machine']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to replace tool',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a tool from machine (without replacement)
     */
    public function remove(Request $request, $id)
    {
        $tooling = MachineTooling::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tool_life_used' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            if ($request->has('tool_life_used')) {
                $tooling->tool_life_used = $request->tool_life_used;
            }

            $tooling->status = 'replaced';
            $tooling->removed_at = now();
            $tooling->removed_by = auth()->user()->name ?? 'System';
            $tooling->notes = ($tooling->notes ? $tooling->notes . "\n\n" : '') .
                             "Removal reason: " . ($request->reason ?? 'Not specified');
            $tooling->save();

            DB::commit();

            return response()->json([
                'message' => 'Tool removed successfully',
                'tooling' => $tooling->load(['product', 'machine']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to remove tool',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tooling statistics
     */
    public function statistics(Request $request)
    {
        $stats = [
            'total_active_tools' => MachineTooling::active()->count(),
            'tools_needing_attention' => MachineTooling::needsAttention()->count(),
            'tools_warning' => MachineTooling::where('status', 'warning')->count(),
            'tools_needs_replacement' => MachineTooling::where('status', 'needs_replacement')->count(),
        ];

        // Group by machine
        $byMachine = MachineTooling::with('machine')
            ->active()
            ->get()
            ->groupBy('machine_id')
            ->map(function ($tooling, $machineId) {
                $machine = $tooling->first()->machine;
                return [
                    'machine_id' => $machineId,
                    'machine_name' => $machine->name,
                    'total_tools' => $tooling->count(),
                    'needs_attention' => $tooling->where('status', 'warning')->count() +
                                        $tooling->where('status', 'needs_replacement')->count(),
                ];
            })
            ->values();

        $stats['by_machine'] = $byMachine;

        return response()->json($stats);
    }

    /**
     * Get compatible tools for a machine
     */
    public function compatibleTools(Request $request, $machineId)
    {
        $machine = Machine::findOrFail($machineId);

        $query = Product::where('is_active', true)
            ->whereIn('tool_type', ['consumable_tool', 'asset_tool']);

        // Filter by machine type compatibility if machine has a type
        if ($machine->machine_type_id) {
            $query->where(function ($q) use ($machine) {
                $q->whereJsonContains('compatible_machine_types', $machine->machine_type_id)
                  ->orWhereNull('compatible_machine_types');
            });
        }

        // Filter by tool type
        if ($request->has('tool_type')) {
            $query->where('tool_type', $request->tool_type);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        $tools = $query->with('categories')->get();

        return response()->json([
            'tools' => $tools,
            'machine' => $machine,
        ]);
    }

    /**
     * Get tool life units
     */
    public function toolLifeUnits()
    {
        return response()->json(Product::$toolLifeUnits);
    }

    /**
     * Get tool types
     */
    public function toolTypes()
    {
        return response()->json(Product::$toolTypes);
    }
}
