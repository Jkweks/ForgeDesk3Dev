<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguratorHwlibItem;
use App\Models\ConfiguratorHwlibLink;
use App\Models\ConfiguratorHwlibLinkValue;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameConfigurationDoor;
use App\Models\DoorFrameDoorConfig;
use App\Models\DoorFrameDoorPart;
use App\Models\DoorFrameFrameConfig;
use App\Models\DoorFrameFramePart;
use App\Models\DoorFrameHardwarePart;
use App\Models\DoorFrameOpeningSpec;
use App\Services\Configurator\DoorBomGenerator;
use App\Services\Configurator\FrameBomGenerator;
use App\Services\Configurator\HwlibBomGenerator;
use App\Services\Configurator\HwlibResolver;
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
                ->with(['businessJob', 'doors', 'createdBy', 'workOrder', 'openingSpecs', 'frameConfig', 'doorConfigs'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($config) {
                    return [
                        'id' => $config->id,
                        'business_job_id' => $config->business_job_id,
                        'job_number' => $config->businessJob->job_number,
                        'job_name' => $config->businessJob->job_name,
                        'job_scope' => $config->job_scope,
                        'scope_label' => $config->scope_label,
                        'quantity' => $config->quantity,
                        'status' => $config->status,
                        'status_label' => $config->status_label,
                        'door_tags' => $config->doors->pluck('door_tag')->implode(', '),
                        'is_complete' => $config->isComplete(),
                        'can_edit' => $config->canEdit(),
                        'work_order_id' => $config->work_order_id,
                        'work_order_release_token' => $config->workOrder?->release_token,
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
                'workOrder',
                'jobReservation',
                'doors',
                'openingSpecs.hingeSpacingStandard',
                'frameConfig.frameSeries.frameSystem',
                'frameConfig.parts.product',
                'doorConfigs.parts.product',
                'hardwareLinks.item.category', 'hardwareLinks.item.subcategory', 'hardwareLinks.functions',
                'hardwareParts.product',
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
    /**
     * Returns door tags that either repeat within $tags itself, or already
     * belong to another configuration on this job — one physical opening
     * should only ever be claimed by one DoorFrameConfiguration, since a
     * second claimant makes ElevationConfigurationMatcher::
     * findMatchingConfiguration() match ambiguously (it just takes the
     * first row it finds) once a work-order elevation tries to link up.
     */
    private function conflictingDoorTags(int $businessJobId, array $tags): array
    {
        $withinRequest = array_unique(array_diff_assoc($tags, array_unique($tags)));

        $existing = DoorFrameConfigurationDoor::whereIn('door_tag', $tags)
            ->whereHas('configuration', fn ($q) => $q->where('business_job_id', $businessJobId))
            ->pluck('door_tag')
            ->all();

        return array_values(array_unique(array_merge($withinRequest, $existing)));
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'business_job_id' => 'required|exists:business_jobs,id',
                'job_scope' => 'required|in:door_and_frame,frame_only,door_only',
                'door_tags' => 'required|array|min:1',
                'door_tags.*' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $conflicts = $this->conflictingDoorTags((int) $request->business_job_id, $request->door_tags);
            if (! empty($conflicts)) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['door_tags' => ['Door tag(s) '.implode(', ', $conflicts).' already belong to another configuration on this job.']],
                ], 422);
            }

            DB::beginTransaction();

            // Door tags are this configuration's identity — quantity is
            // always just how many of them there are (one set of parts per
            // physical opening), never entered independently.
            $config = DoorFrameConfiguration::create([
                'business_job_id' => $request->business_job_id,
                'job_scope' => $request->job_scope,
                'quantity' => count($request->door_tags),
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
     * Bulk-duplicate a configuration: each entry in `duplicates` becomes a new
     * configuration cloning this one's opening/frame/door/hardware data (with
     * that entry's own door tags and any field overrides — e.g. flipped hand),
     * then has its BOM generated immediately from the cloned data. Optionally
     * links the source and every new copy as a group (`link: true`) so the
     * UI can warn before an edit is allowed to quietly diverge one of them.
     */
    public function duplicate(
        Request $request,
        $id,
        FrameBomGenerator $frameGen,
        DoorBomGenerator $doorGen,
        HwlibBomGenerator $hwGen,
        \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge
    ) {
        $source = DoorFrameConfiguration::with(['openingSpecs', 'frameConfig', 'doorConfigs', 'hardwareLinks.values'])
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'duplicates' => 'required|array|min:1|max:50',
            'duplicates.*.door_tags' => 'required|array|min:1',
            'duplicates.*.door_tags.*' => 'required|string|max:50',
            'duplicates.*.overrides' => 'nullable|array',
            'duplicates.*.overrides.opening_specs' => 'nullable|array',
            'duplicates.*.overrides.frame_config' => 'nullable|array',
            'duplicates.*.overrides.door_config' => 'nullable|array',
            'link' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $allTags = collect($request->duplicates)->pluck('door_tags')->flatten()->all();
        $conflicts = $this->conflictingDoorTags($source->business_job_id, $allTags);
        if (! empty($conflicts)) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['duplicates' => ['Door tag(s) '.implode(', ', $conflicts).' already belong to another configuration on this job.']],
            ], 422);
        }

        $groupId = null;
        if ($request->boolean('link')) {
            $groupId = $source->duplicate_group_id ?: (string) \Illuminate\Support\Str::uuid();
        }

        $created = [];

        DB::beginTransaction();

        try {
            if ($groupId && ! $source->duplicate_group_id) {
                $source->duplicate_group_id = $groupId;
                $source->save();
            }

            foreach ($request->duplicates as $entry) {
                $overrides = $entry['overrides'] ?? [];

                $new = DoorFrameConfiguration::create([
                    'business_job_id' => $source->business_job_id,
                    'job_scope' => $source->job_scope,
                    'quantity' => count($entry['door_tags']),
                    'duplicate_group_id' => $groupId,
                    'status' => 'draft',
                    'notes' => $source->notes,
                    'created_by_id' => auth()->id(),
                ]);

                foreach ($entry['door_tags'] as $tag) {
                    DoorFrameConfigurationDoor::create(['configuration_id' => $new->id, 'door_tag' => $tag]);
                }

                if ($source->openingSpecs) {
                    DoorFrameOpeningSpec::create(array_merge(
                        $source->openingSpecs->only([
                            'opening_type', 'hand_single', 'hand_pair',
                            'door_opening_width', 'door_opening_height', 'hinging', 'finish',
                        ]),
                        $overrides['opening_specs'] ?? [],
                        ['configuration_id' => $new->id]
                    ));
                }

                if ($source->includesFrame() && $source->frameConfig) {
                    DoorFrameFrameConfig::create(array_merge(
                        $source->frameConfig->only([
                            'frame_system_product_id', 'frame_series_id', 'glazing',
                            'has_transom', 'has_threshold', 'transom_glazing', 'total_frame_height',
                        ]),
                        $overrides['frame_config'] ?? [],
                        ['configuration_id' => $new->id]
                    ));
                }

                if ($source->includesDoor()) {
                    foreach ($source->doorConfigs as $dc) {
                        DoorFrameDoorConfig::create(array_merge(
                            $dc->only([
                                'door_series', 'stile_width', 'leaf_type', 'handing', 'hinge_type',
                                'opening_angle', 'bottom_gap', 'top_rail_label', 'bot_rail_label',
                                'mid_rail_label', 'mid_qty', 'mid_loc1', 'mid_loc2', 'glazing', 'preset',
                            ]),
                            $overrides['door_config'] ?? [],
                            ['configuration_id' => $new->id]
                        ));
                    }
                }

                foreach ($source->hardwareLinks as $link) {
                    $newLink = ConfiguratorHwlibLink::create([
                        'configuration_id' => $new->id,
                        'item_id' => $link->item_id,
                        'source_set_id' => $link->source_set_id,
                        'quantity' => $link->quantity,
                        'notes' => $link->notes,
                        'series' => $link->series,
                        'leaf' => $link->leaf,
                    ]);
                    foreach ($link->values as $v) {
                        ConfiguratorHwlibLinkValue::create([
                            'link_id' => $newLink->id,
                            'variable_id' => $v->variable_id,
                            'value_text' => $v->value_text,
                        ]);
                    }
                }

                $created[] = $new;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to duplicate configuration', [
                'source_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to duplicate configuration',
                'message' => $e->getMessage(),
            ], 500);
        }

        // Generate BOM immediately for each copy, same as the "always generate
        // on first run through" behavior — best-effort per config/section so
        // one missing piece (e.g. no hardware yet) never blocks the others.
        foreach ($created as $new) {
            $fresh = $new->fresh(['frameConfig', 'doorConfigs', 'openingSpecs', 'hardwareLinks']);

            $silent = new Request();

            try {
                if ($fresh->includesFrame() && $fresh->frameConfig) {
                    $this->generateFrameParts($silent, $fresh->id, $frameGen, $reservationBridge);
                }
            } catch (\Throwable $e) {
                Log::warning('Duplicate: failed to generate frame parts', ['config_id' => $fresh->id, 'message' => $e->getMessage()]);
            }

            try {
                if ($fresh->includesDoor() && $fresh->doorConfigs->isNotEmpty()) {
                    $this->generateDoorParts($silent, $fresh->id, $doorGen, $reservationBridge);
                }
            } catch (\Throwable $e) {
                Log::warning('Duplicate: failed to generate door parts', ['config_id' => $fresh->id, 'message' => $e->getMessage()]);
            }

            try {
                if ($fresh->hardwareLinks->isNotEmpty()) {
                    $this->generateHardwareParts($silent, $fresh->id, $hwGen, $reservationBridge);
                }
            } catch (\Throwable $e) {
                Log::warning('Duplicate: failed to generate hardware parts', ['config_id' => $fresh->id, 'message' => $e->getMessage()]);
            }
        }

        Log::info('Configuration bulk-duplicated', [
            'source_id' => $id,
            'created_ids' => collect($created)->pluck('id'),
            'linked' => (bool) $groupId,
        ]);

        return response()->json([
            'message' => count($created).' configuration(s) duplicated',
            'configurations' => collect($created)->map(fn ($c) => [
                'id' => $c->id,
                'door_tags' => $c->fresh('doors')->doors->pluck('door_tag')->values(),
            ]),
        ], 201);
    }

    /**
     * Break the duplicate-group link — either just this configuration
     * (`scope: single`) or the whole group at once (`scope: all`). Used
     * right before saving an edit to a linked configuration, so a change
     * meant for just one copy never silently drifts unnoticed from siblings
     * that are still supposed to be identical.
     */
    public function unlink(Request $request, $id)
    {
        $config = DoorFrameConfiguration::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'scope' => 'required|in:single,all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! $config->duplicate_group_id) {
            return response()->json(['message' => 'Configuration is not linked to any group.']);
        }

        if ($request->scope === 'all') {
            DoorFrameConfiguration::where('duplicate_group_id', $config->duplicate_group_id)
                ->update(['duplicate_group_id' => null]);
        } else {
            $config->update(['duplicate_group_id' => null]);
        }

        return response()->json(['message' => 'Configuration unlinked successfully']);
    }

    /**
     * Update opening specifications (Step 1)
     */
    public function updateOpeningSpecs(Request $request, $id, \App\Services\Configurator\ElevationConfigurationMatcher $matcher)
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
                'job_scope' => 'nullable|in:door_and_frame,frame_only,door_only',
                'opening_type' => 'required|in:single,pair',
                'hand_single' => 'nullable|required_if:opening_type,single|in:lh_inswing,rh_inswing,lhr,rhr',
                'hand_pair' => 'nullable|required_if:opening_type,pair|in:rhr_active,lhra_active',
                'door_opening_width' => 'required|numeric|min:0|max:999.99',
                'door_opening_height' => 'required|numeric|min:0|max:999.99',
                'hinging' => 'required|in:continuous,butt,pivot_offset,pivot_center',
                'butt_hinge_count' => 'nullable|required_if:hinging,butt|integer|min:2|max:20',
                'hinge_spacing_standard_id' => 'nullable|required_if:hinging,butt|exists:configurator_hinge_spacing_standards,id',
                'finish' => 'required|in:c2,db,bl',
                'glazing' => 'nullable|string|exists:configurator_glass_specs,thickness',
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
                    'butt_hinge_count' => $request->hinging === 'butt' ? $request->butt_hinge_count : null,
                    'hinge_spacing_standard_id' => $request->hinging === 'butt' ? $request->hinge_spacing_standard_id : null,
                    'finish' => $request->finish,
                    'glazing' => $request->glazing,
                ]
            );

            $scopeChanged = $request->filled('job_scope') && $request->job_scope !== $config->job_scope;
            if ($scopeChanged) {
                $config->job_scope = $request->job_scope;
                $config->save();
            }

            DB::commit();

            $config->load('openingSpecs.hingeSpacingStandard');

            // Scope/pair-vs-single may have changed which elevations this opening
            // needs. Best-effort, mirroring the pattern used on release(): a config
            // not yet tied to a work order is a no-op inside syncElevationsForConfiguration(),
            // and elevations that no longer fit are never deleted — only warned about.
            $warnings = [];
            if ($scopeChanged) {
                try {
                    $sync = $matcher->syncElevationsForConfiguration($config);
                    foreach ($sync['orphaned'] as $orphan) {
                        $warnings[] = "Elevation \"{$orphan->elevation_tag}\" no longer matches this opening's scope — review it on the work order.";
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to sync elevations after opening spec change', [
                        'config_id' => $id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Opening specifications saved successfully',
                'opening_specs' => $this->formatOpeningSpecs($config->openingSpecs),
                'warnings' => $warnings,
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
                'has_transom' => 'required|boolean',
                'has_threshold' => 'required|boolean',
                'transom_glazing' => 'nullable|required_if:has_transom,true|in:0.25,0.5,1.0',
                'total_frame_height' => 'nullable|required_if:has_transom,true|numeric|min:0',
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
    public function generateFrameParts(Request $request, $id, FrameBomGenerator $generator, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
    {
        $config = DoorFrameConfiguration::with(['frameConfig', 'openingSpecs'])->findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
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

        // A dry run for the "save and continue" flow to diff against what's
        // currently saved before silently overwriting a manually-tweaked BOM.
        if ($request->boolean('preview')) {
            return response()->json([
                'preview' => true,
                'parts' => collect($result['rows'])->map(fn ($r) => $this->formatPreviewRow($r)),
                'warnings' => $result['warnings'],
            ]);
        }

        DB::beginTransaction();

        try {
            DoorFrameFramePart::where('frame_config_id', $config->frameConfig->id)
                ->where('is_auto_generated', true)
                ->delete();

            foreach ($result['rows'] as $row) {
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
        $this->syncReservationIfReserved($config, $reservationBridge);

        return response()->json([
            'message' => 'Frame parts generated successfully',
            'parts' => $config->frameConfig->parts->map(fn ($p) => $this->formatPart($p)),
            'warnings' => $result['warnings'],
        ]);
    }

    /**
     * Override a single generated (or manual) part's product / length / quantity.
     */
    public function updateFramePart(Request $request, $id, $partId, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
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
        $this->syncReservationIfReserved($config, $reservationBridge);

        return response()->json([
            'message' => 'Part updated successfully',
            'part' => $this->formatPart($part),
        ]);
    }

    /**
     * Remove a single part (manual rows only — auto-generated rows should be
     * removed by adjusting the catalog and re-running generateFrameParts).
     */
    public function destroyFramePart($id, $partId, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
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
        $this->syncReservationIfReserved($config, $reservationBridge);

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
                'opening_angle' => 'nullable|integer|min:1|max:180',
                'top_rail_label' => 'required|string',
                'bot_rail_label' => 'required|string',
                'mid_rail_label' => 'nullable|string',
                'mid_qty' => 'nullable|integer|min:0|max:2',
                'mid_loc1' => 'required_if:mid_qty,1,2|nullable|numeric',
                'mid_loc2' => 'required_if:mid_qty,2|nullable|numeric',
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

            // Handing/hinge type/glazing/bottom gap are driven by the Opening
            // tab (and the global gap settings) now, not re-entered here —
            // still mirrored onto the door config row so existing reads of
            // it (formatDoorConfig(), duplication) keep working unchanged.
            $doorConfig = DoorFrameDoorConfig::create([
                'configuration_id' => $config->id,
                'door_series' => $request->door_series,
                'stile_width' => $request->stile_width,
                'leaf_type' => 'single',
                'handing' => $config->openingSpecs?->deriveDoorHanding(),
                'hinge_type' => $config->openingSpecs?->deriveHingeType(),
                'opening_angle' => $request->opening_angle ?? 90,
                'bottom_gap' => \App\Models\ConfiguratorSetting::current()->bottom_gap,
                'top_rail_label' => $request->top_rail_label,
                'bot_rail_label' => $request->bot_rail_label,
                'mid_rail_label' => $request->mid_rail_label,
                'mid_qty' => $request->mid_qty ?? 0,
                'mid_loc1' => $request->mid_loc1,
                'mid_loc2' => $request->mid_loc2,
                'glazing' => $config->openingSpecs?->glazing,
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
    public function generateDoorParts(Request $request, $id, DoorBomGenerator $generator, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
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

        if ($request->boolean('preview')) {
            return response()->json([
                'preview' => true,
                'parts' => collect($result['rows'])->map(fn ($r) => $this->formatPreviewRow($r)),
                'warnings' => $result['warnings'],
            ]);
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
        $this->syncReservationIfReserved($config, $reservationBridge);

        return response()->json([
            'message' => 'Door parts generated successfully',
            'parts' => $doorConfig->parts->map(fn ($p) => $this->formatPart($p)),
            'warnings' => $result['warnings'],
        ]);
    }

    /**
     * Override a single generated (or manual) door part's product / length / quantity.
     */
    public function updateDoorPart(Request $request, $id, $partId, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
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
        $this->syncReservationIfReserved($config, $reservationBridge);

        return response()->json([
            'message' => 'Part updated successfully',
            'part' => $this->formatPart($part),
        ]);
    }

    /**
     * Remove a single manual door part row.
     */
    public function destroyDoorPart($id, $partId, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
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
        $this->syncReservationIfReserved($config, $reservationBridge);

        return response()->json(['message' => 'Part removed successfully']);
    }

    /**
     * Release configuration to production
     */
    public function release(
        $id,
        \App\Services\Configurator\ElevationConfigurationMatcher $matcher,
        \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge
    ) {
        try {
            $config = DoorFrameConfiguration::with([
                'openingSpecs',
                'frameConfig',
                'doorConfigs',
                'doors',
            ])->findOrFail($id);

            if (! in_array($config->status, ['draft', 'reserved'])) {
                return response()->json([
                    'error' => 'Invalid status',
                    'message' => 'Only draft or reserved configurations can be released',
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

            // A configuration built ahead of production scheduling may not have
            // a work order yet — try to pick one up now, in case one has since
            // been created (going-forward matching normally handles this from
            // the elevation side, but this covers the reverse timing too). Do
            // this before the work-order gate below, since a successful match
            // here is what lets release proceed.
            $workOrder = null;
            try {
                $workOrder = $matcher->linkConfigurationToWorkOrder($config);
            } catch (\Throwable $e) {
                Log::warning('Failed to auto-link configuration to a work order on release', [
                    'config_id' => $id,
                    'message' => $e->getMessage(),
                ]);
            }

            if (! $config->work_order_id) {
                return response()->json([
                    'error' => 'No work order',
                    'message' => 'Configuration must be tied to a work order before it can be released.',
                ], 422);
            }

            $config->status = 'released';
            $config->save();

            // Commit the generated BOM against real inventory the same way
            // every other fulfillment path does. Best-effort: a config with
            // no parts generated yet (frame/door config saved but "Generate"
            // never clicked) shouldn't be blocked from releasing — it just
            // won't have a reservation until parts exist and this is re-run.
            // If the configuration was already "reserved", this syncs its
            // existing reservation rather than creating a second one.
            $reservation = null;
            try {
                $reservation = $reservationBridge->reserve($config, auth()->user())['reservation'];
            } catch (\Throwable $e) {
                Log::warning('Failed to auto-create job reservation on release', [
                    'config_id' => $id,
                    'message' => $e->getMessage(),
                ]);
            }

            // Push the work order's full released cut-list to CutFlow —
            // best-effort, same pattern as the reservation above. Exports the
            // *whole* work order (every released opening on it), not just
            // this configuration, since CutFlow's ingest merges/updates one
            // CutJob per work order rather than one per opening.
            $cutFlowResult = null;
            try {
                $cutFlowResult = app(\App\Services\Configurator\CutFlowExportService::class)->exportWorkOrder($workOrder);
            } catch (\Throwable $e) {
                Log::warning('Failed to export cut list to CutFlow on release', [
                    'config_id' => $id,
                    'work_order_id' => $workOrder?->id,
                    'message' => $e->getMessage(),
                ]);
            }

            Log::info('Configuration released', [
                'config_id' => $id,
                'released_by' => auth()->id(),
                'work_order_id' => $workOrder?->id,
                'job_reservation_id' => $reservation?->id,
                'cutflow_sent' => $cutFlowResult['sent'] ?? false,
            ]);

            return response()->json([
                'message' => 'Configuration released successfully',
                'configuration' => [
                    'id' => $config->id,
                    'status' => $config->status,
                    'status_label' => $config->status_label,
                    'work_order_id' => $config->work_order_id,
                    'work_order_release_token' => $workOrder?->release_token,
                    'job_reservation_id' => $reservation?->id,
                    'job_reservation_number' => $reservation?->reservation_id,
                    'cutflow_sent' => $cutFlowResult['sent'] ?? false,
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
     * Reserve a draft configuration's current BOM against real inventory —
     * unlike release(), this leaves the configuration editable ("reserved"),
     * and further edits/regenerated parts keep syncing into the same
     * reservation (see syncReservationIfReserved()). Also usable to manually
     * re-trigger a sync while already reserved.
     */
    public function reserveConfiguration($id, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
    {
        $config = DoorFrameConfiguration::findOrFail($id);

        if (! in_array($config->status, ['draft', 'reserved'])) {
            return response()->json([
                'error' => 'Cannot reserve',
                'message' => 'Only a draft or already-reserved configuration can be reserved.',
            ], 422);
        }

        // Never blocks on incomplete/ungenerated sections — reserve() only
        // returns warnings for those; a config with nothing generated at all
        // anywhere still transitions to "reserved" with no reservation yet
        // (one gets created automatically by the sync below once parts exist).
        $result = $reservationBridge->reserve($config, auth()->user());

        if ($config->status === 'draft') {
            $config->status = 'reserved';
            $config->save();
        }

        return response()->json([
            'message' => 'Configuration reserved',
            'warnings' => $result['warnings'],
            'configuration' => [
                'id' => $config->id,
                'status' => $config->status,
                'status_label' => $config->status_label,
                'job_reservation_id' => $result['reservation']?->id,
                'job_reservation_number' => $result['reservation']?->reservation_id,
            ],
        ]);
    }

    /**
     * Back out of "reserved" to "draft" — cancels the linked reservation
     * (releasing its committed inventory the same way any other cancelled
     * reservation does, via JobReservation's own status-change hooks) rather
     * than leaving inventory committed under a config that no longer claims
     * to be reserved.
     */
    public function unreserveConfiguration($id)
    {
        $config = DoorFrameConfiguration::findOrFail($id);

        if ($config->status !== 'reserved') {
            return response()->json([
                'error' => 'Cannot unreserve',
                'message' => 'Only a reserved configuration can be moved back to draft.',
            ], 422);
        }

        if ($config->job_reservation_id) {
            $reservation = $config->jobReservation;
            if ($reservation && ! in_array($reservation->status, ['fulfilled', 'cancelled'])) {
                $reservation->status = 'cancelled';
                $reservation->save();
            }
        }

        $config->status = 'draft';
        $config->job_reservation_id = null;
        $config->save();

        return response()->json([
            'message' => 'Configuration moved back to draft',
            'configuration' => [
                'id' => $config->id,
                'status' => $config->status,
                'status_label' => $config->status_label,
            ],
        ]);
    }

    /**
     * Manually (re)trigger reservation creation — for a configuration that
     * was released before its BOM was generated. No-op (returns the existing
     * reservation) if one already exists.
     */
    public function createReservation($id, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
    {
        $config = DoorFrameConfiguration::findOrFail($id);

        if ($config->status !== 'released') {
            return response()->json([
                'error' => 'Cannot reserve',
                'message' => 'Only a released configuration can be committed against inventory.',
            ], 422);
        }

        $reservation = $reservationBridge->reserve($config, auth()->user())['reservation'];

        if (! $reservation) {
            return response()->json([
                'error' => 'Nothing to reserve',
                'message' => 'No parts have been generated yet — generate the frame/door/hardware parts first.',
            ], 422);
        }

        return response()->json([
            'message' => 'Reservation created successfully',
            'job_reservation_id' => $reservation->id,
            'job_reservation_number' => $reservation->reservation_id,
        ]);
    }

    /**
     * If this configuration is "reserved" (editable, possibly already
     * committed against inventory), queue a sync of its current BOM into the
     * linked reservation — creating one now if this is the first time any
     * parts exist to reserve. Called after any endpoint that changes
     * generated parts. No-op if the configuration isn't reserved.
     *
     * Queued rather than run inline: ConfigurationReservationBridge::reserve()
     * reloads the whole BOM tree and mutates reservation items one at a time,
     * each cascading into a Product save + committed-quantity recalculation —
     * expensive enough on every single part edit to be the main source of the
     * configurator's "laggy while editing" complaints. See
     * SyncConfigurationReservationJob.
     */
    private function syncReservationIfReserved(
        DoorFrameConfiguration $config,
        \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge
    ): void {
        if ($config->status !== 'reserved') {
            return;
        }

        \App\Jobs\SyncConfigurationReservationJob::dispatch($config->id, auth()->id());
    }

    /**
     * Export a combined cut-sheet PDF (frame + door + hardware BOM).
     */
    public function exportPdf($id)
    {
        $config = DoorFrameConfiguration::with([
            'businessJob',
            'workOrder',
            'doors',
            'openingSpecs.hingeSpacingStandard',
            'frameConfig.frameSeries.frameSystem',
            'frameConfig.parts.product',
            'doorConfigs.parts.product',
            'hardwareParts.product',
            'hardwareParts.hwlibLink.functions',
        ])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.configurator-cut-sheet', [
            'config' => $config,
        ]);
        $pdf->setPaper('letter', 'portrait');

        $tags = $config->doors->pluck('door_tag')->implode('-') ?: $config->id;
        $filename = 'CutSheet_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $config->businessJob->job_number.'_'.$tags).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export the same cut-sheet data (frame + door + hardware BOM) as a flat
     * CSV — Job / Work Order / Elevation / Length / Quantity / Part Number /
     * Color per row, for import into other tools.
     */
    public function exportCsv($id)
    {
        $config = DoorFrameConfiguration::with([
            'businessJob',
            'workOrder',
            'doors',
            'frameConfig.parts.product',
            'doorConfigs.parts.product',
        ])->findOrFail($id);

        $job = $config->businessJob->job_number;
        $workOrder = $config->workOrder->release_token ?? '';
        $elevation = $config->doors->pluck('door_tag')->implode(', ');

        // Cut list only — the actual lineal stock to cut, not the qty-based
        // components/fasteners riding along with it or the hardware BOM.
        $rows = collect()
            ->concat($config->frameConfig?->parts ?? [])
            ->concat($config->doorConfigs->flatMap(fn ($dc) => $dc->parts))
            ->reject(fn ($part) => $part->source_type === 'component')
            ->map(function ($part) use ($job, $workOrder, $elevation) {
                $product = $part->product;
                $partNumber = $product
                    ? $product->part_number.($product->finish ? '-'.$product->finish : '')
                    : '';

                return [
                    $job,
                    $workOrder,
                    $elevation,
                    $part->unit_type === 'length' ? number_format($part->calculated_length, 3) : '',
                    $part->quantity,
                    $partNumber,
                    $product?->finish_name ?? '',
                ];
            });

        $tags = $config->doors->pluck('door_tag')->implode('-') ?: $config->id;
        $filename = 'CutList_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $config->businessJob->job_number.'_'.$tags).'.csv';

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Job', 'Work Order', 'Elevation', 'Length', 'Quantity', 'Part Number', 'Color']);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
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
            'work_order' => $config->workOrder ? [
                'id' => $config->workOrder->id,
                'release_token' => $config->workOrder->release_token,
                'status' => $config->workOrder->status,
            ] : null,
            'job_reservation' => $config->jobReservation ? [
                'id' => $config->jobReservation->id,
                'reservation_id' => $config->jobReservation->reservation_id,
                'status' => $config->jobReservation->status,
            ] : null,
            'job_scope' => $config->job_scope,
            'scope_label' => $config->scope_label,
            'quantity' => $config->quantity,
            'status' => $config->status,
            'status_label' => $config->status_label,
            'notes' => $config->notes,
            'duplicate_group_id' => $config->duplicate_group_id,
            'linked_siblings' => $config->duplicate_group_id
                ? $config->linkedSiblings()->with('doors')->get()->map(fn ($s) => [
                    'id' => $s->id,
                    'door_tags' => $s->doors->pluck('door_tag')->values(),
                ])->values()
                : [],
            'door_tags' => $config->doors->map(fn ($d) => $d->door_tag),
            'opening_specs' => $config->openingSpecs ? $this->formatOpeningSpecs($config->openingSpecs) : null,
            'frame_config' => $config->frameConfig ? $this->formatFrameConfig($config->frameConfig) : null,
            'door_config' => $config->doorConfigs->first() ? $this->formatDoorConfig($config->doorConfigs->first()) : null,
            'hardware_links' => $config->hardwareLinks->map(fn ($l) => $this->formatHardwareLink($l)),
            'hardware_parts' => $config->hardwareParts->map(fn ($p) => $this->formatPart($p)),
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
            'butt_hinge_count' => $specs->butt_hinge_count,
            'hinge_spacing_standard_id' => $specs->hinge_spacing_standard_id,
            'hinge_spacing_standard' => $specs->hingeSpacingStandard,
            'hinge_locations' => $specs->hingeLocations(),
            'finish' => $specs->finish,
            'finish_label' => $specs->finish_label,
            'glazing' => $specs->glazing,
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

    /**
     * Helper: Format a not-yet-persisted generator row (part_label/product_id/
     * calculated_length/quantity/unit_type) the same shape as formatPart(),
     * for the "save and continue" preview diff — never touches the DB.
     */
    private function formatPreviewRow(array $row)
    {
        $product = \App\Models\Product::find($row['product_id'] ?? null);

        return [
            'part_label' => $row['part_label'] ?? null,
            'product' => $product ? [
                'id' => $product->id,
                'part_number' => $product->part_number,
                'finish' => $product->finish,
                'description' => $product->description,
            ] : null,
            'calculated_length' => $row['calculated_length'] ?? null,
            'quantity' => $row['quantity'] ?? null,
            'unit_type' => $row['unit_type'] ?? null,
        ];
    }

    /**
     * Helper: Format a hardware link (without resolved variable values —
     * see resolvedHardwareValues() for those, fetched separately since
     * resolution requires walking every link on the configuration).
     */
    private function formatHardwareLink($link)
    {
        return [
            'id' => $link->id,
            'item' => [
                'id' => $link->item->id,
                'name' => $link->item->name,
                'manufacturer' => $link->item->manufacturer,
                'model_number' => $link->item->model_number,
                'pn' => $link->item->pn,
                'category' => [
                    'id' => $link->item->category->id,
                    'name' => $link->item->category->name,
                ],
            ],
            'quantity' => $link->quantity,
            'series' => $link->series,
            'leaf' => $link->leaf,
            'notes' => $link->notes,
            'functions' => $link->functions->map(fn ($f) => [
                'id' => $f->id,
                'code' => $f->code,
                'label' => $f->label,
                'group_name' => $f->group_name,
            ]),
        ];
    }

    /**
     * Add a hardware item to a configuration.
     */
    public function addHardwareLink(Request $request, $id)
    {
        $config = DoorFrameConfiguration::findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:configurator_hwlib_items,id',
            'quantity' => 'nullable|integer|min:1',
            'series' => 'nullable|in:Standard,Thermal,Monumental',
            'leaf' => 'nullable|in:both,active,inactive',
            'notes' => 'nullable|string',
            'values' => 'nullable|array',
            'values.*.variable_id' => 'required_with:values|exists:configurator_hwlib_variables,id',
            'values.*.value_text' => 'nullable|string',
            'function_ids' => 'nullable|array',
            'function_ids.*' => 'integer|exists:configurator_hwlib_functions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $quantity = $request->quantity ?? 1;

        // Butt hinge quantity is driven by the Opening tab's hinge count, not
        // hand-entered per hardware link — force it server-side too, since the
        // frontend field is read-only but the API itself isn't.
        $item = ConfiguratorHwlibItem::with('category')->find($request->item_id);
        if ($item && preg_match('/butt hinge/i', $item->category->name ?? '')) {
            $buttHingeCount = $config->openingSpecs?->butt_hinge_count;
            if ($buttHingeCount) {
                $quantity = $buttHingeCount;
            }
        }

        $link = ConfiguratorHwlibLink::create([
            'configuration_id' => $config->id,
            'item_id' => $request->item_id,
            'quantity' => $quantity,
            'series' => $request->series ?? 'Standard',
            'leaf' => $request->leaf ?? 'both',
            'notes' => $request->notes,
        ]);

        foreach ($request->input('values', []) as $value) {
            if (($value['value_text'] ?? '') === '') {
                continue;
            }
            ConfiguratorHwlibLinkValue::create([
                'link_id' => $link->id,
                'variable_id' => $value['variable_id'],
                'value_text' => $value['value_text'],
            ]);
        }

        if ($request->filled('function_ids')) {
            $link->functions()->sync($request->input('function_ids'));
        }

        $link->load('item.category', 'item.subcategory', 'functions');

        return response()->json([
            'message' => 'Hardware item added successfully',
            'hardware_link' => $this->formatHardwareLink($link),
        ], 201);
    }

    /**
     * Update a hardware link's quantity/series/leaf/overrides.
     */
    public function updateHardwareLink(Request $request, $id, $linkId)
    {
        $config = DoorFrameConfiguration::findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        $link = ConfiguratorHwlibLink::where('configuration_id', $config->id)->findOrFail($linkId);

        $validator = Validator::make($request->all(), [
            'quantity' => 'nullable|integer|min:1',
            'series' => 'nullable|in:Standard,Thermal,Monumental',
            'leaf' => 'nullable|in:both,active,inactive',
            'notes' => 'nullable|string',
            'values' => 'nullable|array',
            'values.*.variable_id' => 'required_with:values|exists:configurator_hwlib_variables,id',
            'values.*.value_text' => 'nullable|string',
            'function_ids' => 'nullable|array',
            'function_ids.*' => 'integer|exists:configurator_hwlib_functions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $link->update($request->only(['quantity', 'series', 'leaf', 'notes']));

        if ($request->has('values')) {
            ConfiguratorHwlibLinkValue::where('link_id', $link->id)->delete();
            foreach ($request->input('values', []) as $value) {
                if (($value['value_text'] ?? '') === '') {
                    continue;
                }
                ConfiguratorHwlibLinkValue::create([
                    'link_id' => $link->id,
                    'variable_id' => $value['variable_id'],
                    'value_text' => $value['value_text'],
                ]);
            }
        }

        if ($request->has('function_ids')) {
            $link->functions()->sync($request->input('function_ids', []));
        }

        $link->load('item.category', 'item.subcategory', 'functions');

        return response()->json([
            'message' => 'Hardware item updated successfully',
            'hardware_link' => $this->formatHardwareLink($link),
        ]);
    }

    /**
     * Remove a hardware item from a configuration.
     */
    public function destroyHardwareLink($id, $linkId)
    {
        $config = DoorFrameConfiguration::findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        $link = ConfiguratorHwlibLink::where('configuration_id', $config->id)->findOrFail($linkId);
        $link->delete();

        return response()->json(['message' => 'Hardware item removed successfully']);
    }

    /**
     * Resolved prep-location values for every hardware link on this
     * configuration (report-worthy variables only).
     */
    public function resolvedHardwareValues($id)
    {
        $config = DoorFrameConfiguration::with(['hardwareLinks.item.category', 'hardwareLinks.item.subcategory', 'openingSpecs', 'doorConfigs', 'frameConfig'])
            ->findOrFail($id);

        $resolver = new HwlibResolver($config);
        $resolvedByLink = $resolver->resolveAll();

        $variables = \App\Models\ConfiguratorHwlibVariable::whereIn('code', collect($resolvedByLink)->flatMap(fn ($v) => array_keys($v))->unique())
            ->get()->keyBy('code');

        $out = [];
        foreach ($config->hardwareLinks as $link) {
            $rows = [];
            foreach ($resolvedByLink[$link->id] ?? [] as $code => $result) {
                $variable = $variables->get($code);
                if (! $variable || ! $variable->show_in_report) {
                    continue;
                }
                $rows[] = [
                    'code' => $code,
                    'label' => $variable->label,
                    'value' => $result['value'],
                    'overridden' => $result['overridden'],
                    'unit' => $variable->unit,
                ];
            }
            $out[] = [
                'link_id' => $link->id,
                'item_name' => $link->item->name,
                'values' => $rows,
            ];
        }

        return response()->json(['links' => $out]);
    }

    /**
     * Auto-generate the hardware BOM (item + backers + fasteners) from every
     * linked hardware item. Replaces previously auto-generated rows; manual
     * rows are untouched.
     */
    public function generateHardwareParts(Request $request, $id, HwlibBomGenerator $generator, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
    {
        $config = DoorFrameConfiguration::with(['hardwareLinks.item', 'openingSpecs'])->findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
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

        if ($request->boolean('preview')) {
            return response()->json([
                'preview' => true,
                'parts' => collect($result['rows'])->map(fn ($r) => $this->formatPreviewRow($r)),
                'warnings' => $result['warnings'],
            ]);
        }

        DB::beginTransaction();

        try {
            DoorFrameHardwarePart::where('configuration_id', $config->id)
                ->where('is_auto_generated', true)
                ->delete();

            foreach ($result['rows'] as $row) {
                $row['configuration_id'] = $config->id;
                DoorFrameHardwarePart::create($row);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to generate hardware parts', [
                'config_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate hardware parts',
                'message' => $e->getMessage(),
            ], 500);
        }

        $config->load('hardwareParts.product');
        $this->syncReservationIfReserved($config, $reservationBridge);

        return response()->json([
            'message' => 'Hardware parts generated successfully',
            'parts' => $config->hardwareParts->map(fn ($p) => $this->formatPart($p)),
            'warnings' => $result['warnings'],
        ]);
    }

    /**
     * Override a single generated (or manual) hardware part's product/quantity.
     */
    public function updateHardwarePart(Request $request, $id, $partId, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
    {
        $config = DoorFrameConfiguration::findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        $part = DoorFrameHardwarePart::where('configuration_id', $config->id)->findOrFail($partId);

        $validator = Validator::make($request->all(), [
            'product_id' => 'sometimes|exists:products,id',
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
        $this->syncReservationIfReserved($config, $reservationBridge);

        return response()->json([
            'message' => 'Part updated successfully',
            'part' => $this->formatPart($part),
        ]);
    }

    /**
     * Remove a single manual hardware part row.
     */
    public function destroyHardwarePart($id, $partId, \App\Services\Configurator\ConfigurationReservationBridge $reservationBridge)
    {
        $config = DoorFrameConfiguration::findOrFail($id);

        if (! $config->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        $part = DoorFrameHardwarePart::where('configuration_id', $config->id)->findOrFail($partId);

        if ($part->is_auto_generated) {
            return response()->json([
                'error' => 'Cannot delete part',
                'message' => 'Auto-generated parts can only be removed by adjusting the catalog and regenerating.',
            ], 422);
        }

        $part->delete();
        $this->syncReservationIfReserved($config, $reservationBridge);

        return response()->json(['message' => 'Part removed successfully']);
    }
}
