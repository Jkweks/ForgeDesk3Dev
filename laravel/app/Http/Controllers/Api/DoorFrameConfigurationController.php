<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameConfigurationDoor;
use App\Models\DoorFrameDoorConfig;
use App\Models\DoorFrameDoorPart;
use App\Models\DoorFrameFrameConfig;
use App\Models\DoorFrameFramePart;
use App\Models\DoorFrameOpeningSpec;
use App\Services\Configurator\DoorBomGenerator;
use App\Services\Configurator\FrameBomGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class DoorFrameConfigurationController extends Controller
{
    /**
     * List all configurations
     */
    public function index(Request $request)
    {
        try {
            $query = DoorFrameConfiguration::query();

            // Filter by job
            if ($request->has('business_job_id')) {
                $query->where('business_job_id', $request->business_job_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $configurations = $query
                ->with(['businessJob', 'doors', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($config) {
                    return [
                        'id' => $config->id,
                        'business_job_id' => $config->business_job_id,
                        'job_number' => $config->businessJob->job_number,
                        'job_name' => $config->businessJob->job_name,
                        'configuration_name' => $config->configuration_name,
                        'job_scope' => $config->job_scope,
                        'scope_label' => $config->scope_label,
                        'quantity' => $config->quantity,
                        'status' => $config->status,
                        'status_label' => $config->status_label,
                        'door_tags' => $config->doors->pluck('door_tag')->implode(', '),
                        'is_complete' => $config->isComplete(),
                        'can_edit' => $config->canEdit(),
                        'created_at' => $config->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'configurations' => $configurations,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list configurations', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to list configurations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed configuration
     */
    public function show($id)
    {
        try {
            $config = DoorFrameConfiguration::with([
                'businessJob',
                'doors',
                'openingSpecs',
                'frameConfig.frameSeries.frameSystem',
                'frameConfig.parts.product',
                'doorConfigs.parts.product',
                'createdBy',
            ])->findOrFail($id);

            return response()->json([
                'configuration' => $this->formatConfigurationDetail($config),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch configuration', [
                'config_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch configuration',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Create new configuration
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'business_job_id' => 'required|exists:business_jobs,id',
                'configuration_name' => 'nullable|string|max:255',
                'job_scope' => 'required|in:door_and_frame,frame_only,door_only',
                'quantity' => 'required|integer|min:1',
                'door_tags' => 'required|array|min:1',
                'door_tags.*' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            // Create configuration
            $config = DoorFrameConfiguration::create([
                'business_job_id' => $request->business_job_id,
                'configuration_name' => $request->configuration_name,
                'job_scope' => $request->job_scope,
                'quantity' => $request->quantity,
                'status' => 'draft',
                'notes' => $request->notes,
                'created_by_id' => auth()->id(),
            ]);

            // Create door tags
            foreach ($request->door_tags as $tag) {
                DoorFrameConfigurationDoor::create([
                    'configuration_id' => $config->id,
                    'door_tag' => $tag,
                ]);
            }

            DB::commit();

            Log::info('Configuration created', [
                'config_id' => $config->id,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Configuration created successfully',
                'configuration' => [
                    'id' => $config->id,
                    'job_scope' => $config->job_scope,
                    'status' => $config->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create configuration', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'error' => 'Failed to create configuration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update opening specifications (Step 1)
     */
    public function updateOpeningSpecs(Request $request, $id)
    {
        try {
            $config = DoorFrameConfiguration::findOrFail($id);

            if (! $config->canEdit()) {
                return response()->json([
                    'error' => 'Cannot edit configuration',
                    'message' => 'Configuration is not in editable status',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'opening_type' => 'required|in:single,pair',
                'hand_single' => 'required_if:opening_type,single|in:lh_inswing,rh_inswing,lhr,rhr',
                'hand_pair' => 'required_if:opening_type,pair|in:rhr_active,lhra_active',
                'door_opening_width' => 'required|numeric|min:0|max:999.99',
                'door_opening_height' => 'required|numeric|min:0|max:999.99',
                'hinging' => 'required|in:continuous,butt,pivot_offset,pivot_center',
                'finish' => 'required|in:c2,db,bl',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            DoorFrameOpeningSpec::updateOrCreate(
                ['configuration_id' => $config->id],
                [
                    'opening_type' => $request->opening_type,
                    'hand_single' => $request->opening_type === 'single' ? $request->hand_single : null,
                    'hand_pair' => $request->opening_type === 'pair' ? $request->hand_pair : null,
                    'door_opening_width' => $request->door_opening_width,
                    'door_opening_height' => $request->door_opening_height,
                    'hinging' => $request->hinging,
                    'finish' => $request->finish,
                ]
            );

            DB::commit();

            $config->load('openingSpecs');

            return response()->json([
                'message' => 'Opening specifications saved successfully',
                'opening_specs' => $this->formatOpeningSpecs($config->openingSpecs),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update opening specs', [
                'config_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update opening specifications',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update frame configuration (Step 2)
     */
    public function updateFrameConfig(Request $request, $id)
    {
        try {
            $config = DoorFrameConfiguration::findOrFail($id);

            if (! $config->includesFrame()) {
                return response()->json([
                    'error' => 'Invalid operation',
                    'message' => 'Job scope does not include frame',
                ], 422);
            }

            if (! $config->canEdit()) {
                return response()->json([
                    'error' => 'Cannot edit configuration',
                    'message' => 'Configuration is not in editable status',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'frame_series_id' => 'required|exists:configurator_frame_series,id',
                'glazing' => 'required|in:0.25,0.5,1.0',
                'has_transom' => 'required|boolean',
                'has_threshold' => 'required|boolean',
                'transom_glazing' => 'required_if:has_transom,true|in:0.25,0.5,1.0',
                'total_frame_height' => 'required_if:has_transom,true|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            $frameConfig = DoorFrameFrameConfig::updateOrCreate(
                ['configuration_id' => $config->id],
                [
                    'frame_series_id' => $request->frame_series_id,
                    'glazing' => $request->glazing,
                    'has_transom' => $request->has_transom,
                    'has_threshold' => $request->has_threshold,
                    'transom_glazing' => $request->has_transom ? $request->transom_glazing : null,
                    'total_frame_height' => $request->has_transom ? $request->total_frame_height : null,
                ]
            );

            DB::commit();

            $frameConfig->load('frameSeries.frameSystem');

            return response()->json([
                'message' => 'Frame configuration saved successfully',
                'frame_config' => $this->formatFrameConfig($frameConfig),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update frame config', [
                'config_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update frame configuration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update frame parts
     */
    public function updateFrameParts(Request $request, $id)
    {
        try {
            $config = DoorFrameConfiguration::with('frameConfig')->findOrFail($id);

            if (! $config->frameConfig) {
                return response()->json([
                    'error' => 'Frame configuration not found',
                    'message' => 'Please configure frame settings first',
                ], 422);
            }

            if (! $config->canEdit()) {
                return response()->json([
                    'error' => 'Cannot edit configuration',
                    'message' => 'Configuration is not in editable status',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'parts' => 'required|array',
                'parts.*.part_label' => 'required|string',
                'parts.*.product_id' => 'required|exists:products,id',
                'parts.*.calculated_length' => 'nullable|numeric',
                'parts.*.quantity' => 'nullable|numeric|min:0',
                'parts.*.unit_type' => 'nullable|in:length,qty',
                'parts.*.sort_order' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            // Delete existing manually-entered parts; auto-generated ones are managed via /generate-parts
            DoorFrameFramePart::where('frame_config_id', $config->frameConfig->id)
                ->where('is_auto_generated', false)
                ->delete();

            foreach ($request->parts as $index => $partData) {
                DoorFrameFramePart::create([
                    'frame_config_id' => $config->frameConfig->id,
                    'part_label' => $partData['part_label'],
                    'product_id' => $partData['product_id'],
                    'calculated_length' => $partData['calculated_length'] ?? null,
                    'quantity' => $partData['quantity'] ?? 1,
                    'unit_type' => $partData['unit_type'] ?? 'length',
                    'source_type' => 'manual',
                    'is_auto_generated' => false,
                    'sort_order' => $partData['sort_order'] ?? $index,
                ]);
            }

            DB::commit();

            $config->frameConfig->load('parts.product');

            return response()->json([
                'message' => 'Frame parts saved successfully',
                'parts' => $config->frameConfig->parts->map(fn ($p) => $this->formatPart($p)),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update frame parts', [
                'config_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update frame parts',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Auto-generate frame parts (extrusions, components, fasteners) from the
     * selected catalog frame series. Replaces previously auto-generated rows;
     * manually-added rows are left untouched.
     */
    public function generateFrameParts($id, FrameBomGenerator $generator)
    {
        $config = DoorFrameConfiguration::with(['frameConfig', 'openingSpecs'])->findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        try {
            $rows = $generator->generate($config);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => 'Cannot generate parts',
                'message' => $e->getMessage(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            DoorFrameFramePart::where('frame_config_id', $config->frameConfig->id)
                ->where('is_auto_generated', true)
                ->delete();

            foreach ($rows as $row) {
                $row['frame_config_id'] = $config->frameConfig->id;
                DoorFrameFramePart::create($row);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to generate frame parts', [
                'config_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate frame parts',
                'message' => $e->getMessage(),
            ], 500);
        }

        $config->frameConfig->load('parts.product');

        return response()->json([
            'message' => 'Frame parts generated successfully',
            'parts' => $config->frameConfig->parts->map(fn ($p) => $this->formatPart($p)),
        ]);
    }

    /**
     * Override a single generated (or manual) part's product / length / quantity.
     */
    public function updateFramePart(Request $request, $id, $partId)
    {
        $config = DoorFrameConfiguration::with('frameConfig')->findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        $part = DoorFrameFramePart::where('frame_config_id', $config->frameConfig?->id)
            ->findOrFail($partId);

        $validator = Validator::make($request->all(), [
            'product_id' => 'sometimes|exists:products,id',
            'calculated_length' => 'sometimes|nullable|numeric',
            'quantity' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $part->fill($validator->validated());
        $part->save();
        $part->load('product');

        return response()->json([
            'message' => 'Part updated successfully',
            'part' => $this->formatPart($part),
        ]);
    }

    /**
     * Remove a single part (manual rows only — auto-generated rows should be
     * removed by adjusting the catalog and re-running generateFrameParts).
     */
    public function destroyFramePart($id, $partId)
    {
        $config = DoorFrameConfiguration::with('frameConfig')->findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        $part = DoorFrameFramePart::where('frame_config_id', $config->frameConfig?->id)
            ->findOrFail($partId);

        if ($part->is_auto_generated) {
            return response()->json([
                'error' => 'Cannot delete part',
                'message' => 'Auto-generated parts can only be removed by adjusting the catalog and regenerating.',
            ], 422);
        }

        $part->delete();

        return response()->json(['message' => 'Part removed successfully']);
    }

    /**
     * Update door configuration (Step 3)
     */
    public function updateDoorConfig(Request $request, $id)
    {
        try {
            $config = DoorFrameConfiguration::with('openingSpecs')->findOrFail($id);

            if (! $config->includesDoor()) {
                return response()->json([
                    'error' => 'Invalid operation',
                    'message' => 'Job scope does not include door',
                ], 422);
            }

            if (! $config->canEdit()) {
                return response()->json([
                    'error' => 'Cannot edit configuration',
                    'message' => 'Configuration is not in editable status',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'door_series' => 'required|in:STANDARD,THERMAL,MONUMENTAL',
                'stile_width' => 'required|string|exists:configurator_door_types,stile_name',
                'handing' => 'required|in:LH (INSWING),RH (INSWING),LHR,RHR,CP SINGLE,PAIR-RHRA,PAIR-LHRA,CP PAIR',
                'hinge_type' => 'required|in:BUTT HINGES,OFFSET PIVOTS,CONTINUOUS HINGE,CENTER PIVOTS',
                'opening_angle' => 'nullable|integer|min:1|max:180',
                'bottom_gap' => 'nullable|numeric|min:0',
                'top_rail_label' => 'required|string',
                'bot_rail_label' => 'required|string',
                'mid_rail_label' => 'nullable|string',
                'mid_qty' => 'nullable|integer|min:0|max:2',
                'mid_loc1' => 'required_if:mid_qty,1,2|nullable|numeric',
                'mid_loc2' => 'required_if:mid_qty,2|nullable|numeric',
                'glazing' => 'nullable|string|exists:configurator_glass_specs,thickness',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            // One door config per configuration — replaces any previous one.
            DoorFrameDoorConfig::where('configuration_id', $config->id)->delete();

            $doorConfig = DoorFrameDoorConfig::create([
                'configuration_id' => $config->id,
                'door_series' => $request->door_series,
                'stile_width' => $request->stile_width,
                'leaf_type' => 'single',
                'handing' => $request->handing,
                'hinge_type' => $request->hinge_type,
                'opening_angle' => $request->opening_angle ?? 90,
                'bottom_gap' => $request->bottom_gap ?? 0.6875,
                'top_rail_label' => $request->top_rail_label,
                'bot_rail_label' => $request->bot_rail_label,
                'mid_rail_label' => $request->mid_rail_label,
                'mid_qty' => $request->mid_qty ?? 0,
                'mid_loc1' => $request->mid_loc1,
                'mid_loc2' => $request->mid_loc2,
                'glazing' => $request->glazing,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Door configuration saved successfully',
                'door_config' => $this->formatDoorConfig($doorConfig),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update door config', [
                'config_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update door configuration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Auto-generate door parts (extrusions + hardware) from the catalog.
     * Replaces previously auto-generated rows; manually-added rows are untouched.
     */
    public function generateDoorParts($id, DoorBomGenerator $generator)
    {
        $config = DoorFrameConfiguration::with(['doorConfigs', 'openingSpecs'])->findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        $doorConfig = $config->doorConfigs->first();
        if (! $doorConfig) {
            return response()->json([
                'error' => 'Cannot generate parts',
                'message' => 'Door configuration not found. Please configure door settings first.',
            ], 422);
        }

        try {
            $result = $generator->generate($config);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => 'Cannot generate parts',
                'message' => $e->getMessage(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            DoorFrameDoorPart::where('door_config_id', $doorConfig->id)
                ->where('is_auto_generated', true)
                ->delete();

            foreach ($result['rows'] as $row) {
                $row['door_config_id'] = $doorConfig->id;
                DoorFrameDoorPart::create($row);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to generate door parts', [
                'config_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate door parts',
                'message' => $e->getMessage(),
            ], 500);
        }

        $doorConfig->load('parts.product');

        return response()->json([
            'message' => 'Door parts generated successfully',
            'parts' => $doorConfig->parts->map(fn ($p) => $this->formatPart($p)),
            'warnings' => $result['warnings'],
        ]);
    }

    /**
     * Override a single generated (or manual) door part's product / length / quantity.
     */
    public function updateDoorPart(Request $request, $id, $partId)
    {
        $config = DoorFrameConfiguration::with('doorConfigs')->findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        $doorConfigIds = $config->doorConfigs->pluck('id');
        $part = DoorFrameDoorPart::whereIn('door_config_id', $doorConfigIds)->findOrFail($partId);

        $validator = Validator::make($request->all(), [
            'product_id' => 'sometimes|exists:products,id',
            'calculated_length' => 'sometimes|nullable|numeric',
            'quantity' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $part->fill($validator->validated());
        $part->save();
        $part->load('product');

        return response()->json([
            'message' => 'Part updated successfully',
            'part' => $this->formatPart($part),
        ]);
    }

    /**
     * Remove a single manual door part row.
     */
    public function destroyDoorPart($id, $partId)
    {
        $config = DoorFrameConfiguration::with('doorConfigs')->findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        $doorConfigIds = $config->doorConfigs->pluck('id');
        $part = DoorFrameDoorPart::whereIn('door_config_id', $doorConfigIds)->findOrFail($partId);

        if ($part->is_auto_generated) {
            return response()->json([
                'error' => 'Cannot delete part',
                'message' => 'Auto-generated parts can only be removed by adjusting the catalog and regenerating.',
            ], 422);
        }

        $part->delete();

        return response()->json(['message' => 'Part removed successfully']);
    }

    /**
     * Release configuration to production
     */
    public function release($id)
    {
        try {
            $config = DoorFrameConfiguration::with([
                'openingSpecs',
                'frameConfig',
                'doorConfigs',
            ])->findOrFail($id);

            if ($config->status !== 'draft') {
                return response()->json([
                    'error' => 'Invalid status',
                    'message' => 'Only draft configurations can be released',
                ], 422);
            }

            $errors = $config->getValidationErrors();
            if (! empty($errors)) {
                return response()->json([
                    'error' => 'Configuration incomplete',
                    'message' => 'Please complete all required sections',
                    'validation_errors' => $errors,
                ], 422);
            }

            $config->status = 'released';
            $config->save();

            Log::info('Configuration released', [
                'config_id' => $id,
                'released_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Configuration released successfully',
                'configuration' => [
                    'id' => $config->id,
                    'status' => $config->status,
                    'status_label' => $config->status_label,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to release configuration', [
                'config_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to release configuration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper: Format configuration detail
     */
    private function formatConfigurationDetail($config)
    {
        return [
            'id' => $config->id,
            'business_job' => [
                'id' => $config->businessJob->id,
                'job_number' => $config->businessJob->job_number,
                'job_name' => $config->businessJob->job_name,
            ],
            'configuration_name' => $config->configuration_name,
            'job_scope' => $config->job_scope,
            'scope_label' => $config->scope_label,
            'quantity' => $config->quantity,
            'status' => $config->status,
            'status_label' => $config->status_label,
            'notes' => $config->notes,
            'door_tags' => $config->doors->map(fn ($d) => $d->door_tag),
            'opening_specs' => $config->openingSpecs ? $this->formatOpeningSpecs($config->openingSpecs) : null,
            'frame_config' => $config->frameConfig ? $this->formatFrameConfig($config->frameConfig) : null,
            'door_configs' => $config->doorConfigs->map(fn ($d) => $this->formatDoorConfig($d)),
            'is_complete' => $config->isComplete(),
            'can_edit' => $config->canEdit(),
            'validation_errors' => $config->getValidationErrors(),
            'created_at' => $config->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $config->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Helper: Format opening specs
     */
    private function formatOpeningSpecs($specs)
    {
        return [
            'opening_type' => $specs->opening_type,
            'opening_type_label' => $specs->opening_type_label,
            'hand_single' => $specs->hand_single,
            'hand_pair' => $specs->hand_pair,
            'hand_label' => $specs->hand_label,
            'door_opening_width' => $specs->door_opening_width,
            'door_opening_height' => $specs->door_opening_height,
            'hinging' => $specs->hinging,
            'hinging_label' => $specs->hinging_label,
            'finish' => $specs->finish,
            'finish_label' => $specs->finish_label,
            'warnings' => $specs->hasWarnings(),
        ];
    }

    /**
     * Helper: Format frame config
     */
    private function formatFrameConfig($frameConfig)
    {
        return [
            'frame_series' => $frameConfig->frameSeries ? [
                'id' => $frameConfig->frameSeries->id,
                'name' => $frameConfig->frameSeries->name,
                'code' => $frameConfig->frameSeries->code,
                'frame_system' => [
                    'id' => $frameConfig->frameSeries->frameSystem->id,
                    'name' => $frameConfig->frameSeries->frameSystem->name,
                ],
            ] : null,
            'glazing' => $frameConfig->glazing,
            'glazing_label' => $frameConfig->glazing_label,
            'has_transom' => $frameConfig->has_transom,
            'has_threshold' => $frameConfig->has_threshold,
            'transom_glazing' => $frameConfig->transom_glazing,
            'transom_glazing_label' => $frameConfig->transom_glazing_label,
            'total_frame_height' => $frameConfig->total_frame_height,
            'parts' => $frameConfig->parts->map(fn ($p) => $this->formatPart($p)),
        ];
    }

    /**
     * Helper: Format door config
     */
    private function formatDoorConfig($doorConfig)
    {
        return [
            'id' => $doorConfig->id,
            'door_series' => $doorConfig->door_series,
            'stile_width' => $doorConfig->stile_width,
            'handing' => $doorConfig->handing,
            'handing_label' => DoorFrameDoorConfig::$handingOptions[$doorConfig->handing] ?? $doorConfig->handing,
            'hinge_type' => $doorConfig->hinge_type,
            'hinge_type_label' => DoorFrameDoorConfig::$hingeTypeOptions[$doorConfig->hinge_type] ?? $doorConfig->hinge_type,
            'opening_angle' => $doorConfig->opening_angle,
            'bottom_gap' => $doorConfig->bottom_gap,
            'top_rail_label' => $doorConfig->top_rail_label,
            'bot_rail_label' => $doorConfig->bot_rail_label,
            'mid_rail_label' => $doorConfig->mid_rail_label,
            'mid_qty' => $doorConfig->mid_qty,
            'mid_loc1' => $doorConfig->mid_loc1,
            'mid_loc2' => $doorConfig->mid_loc2,
            'glazing' => $doorConfig->glazing,
            'is_pair' => $doorConfig->isPair(),
            'parts' => $doorConfig->parts->map(fn ($p) => $this->formatPart($p)),
        ];
    }

    /**
     * Helper: Format part
     */
    private function formatPart($part)
    {
        return [
            'id' => $part->id,
            'part_label' => $part->part_label,
            'formatted_label' => $part->formatted_label,
            'product' => [
                'id' => $part->product->id,
                'part_number' => $part->product->part_number,
                'finish' => $part->product->finish,
                'description' => $part->product->description,
            ],
            'calculated_length' => $part->calculated_length,
            'quantity' => $part->quantity,
            'unit_type' => $part->unit_type,
            'source_type' => $part->source_type,
            'is_auto_generated' => $part->is_auto_generated,
            'sort_order' => $part->sort_order,
        ];
    }
}
