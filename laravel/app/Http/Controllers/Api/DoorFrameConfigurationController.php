<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameConfigurationDoor;
use App\Models\DoorFrameOpeningSpec;
use App\Models\DoorFrameFrameConfig;
use App\Models\DoorFrameFramePart;
use App\Models\DoorFrameDoorConfig;
use App\Models\DoorFrameDoorPart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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
                'frameConfig.frameSystemProduct',
                'frameConfig.parts.product',
                'doorConfigs.doorSystemProduct',
                'doorConfigs.stileProduct',
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

            if (!$config->canEdit()) {
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

            if (!$config->includesFrame()) {
                return response()->json([
                    'error' => 'Invalid operation',
                    'message' => 'Job scope does not include frame',
                ], 422);
            }

            if (!$config->canEdit()) {
                return response()->json([
                    'error' => 'Cannot edit configuration',
                    'message' => 'Configuration is not in editable status',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'frame_system_product_id' => 'required|exists:products,id',
                'glazing' => 'required|in:0.25,0.5,1.0',
                'has_transom' => 'required|boolean',
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
                    'frame_system_product_id' => $request->frame_system_product_id,
                    'glazing' => $request->glazing,
                    'has_transom' => $request->has_transom,
                    'transom_glazing' => $request->has_transom ? $request->transom_glazing : null,
                    'total_frame_height' => $request->has_transom ? $request->total_frame_height : null,
                ]
            );

            DB::commit();

            $frameConfig->load('frameSystemProduct');

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

            if (!$config->frameConfig) {
                return response()->json([
                    'error' => 'Frame configuration not found',
                    'message' => 'Please configure frame settings first',
                ], 422);
            }

            if (!$config->canEdit()) {
                return response()->json([
                    'error' => 'Cannot edit configuration',
                    'message' => 'Configuration is not in editable status',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'parts' => 'required|array',
                'parts.*.part_label' => 'required|string',
                'parts.*.product_id' => 'required|exists:products,id',
                'parts.*.sort_order' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            // Delete existing parts
            DoorFrameFramePart::where('frame_config_id', $config->frameConfig->id)->delete();

            // Create new parts
            foreach ($request->parts as $index => $partData) {
                $part = DoorFrameFramePart::create([
                    'frame_config_id' => $config->frameConfig->id,
                    'part_label' => $partData['part_label'],
                    'product_id' => $partData['product_id'],
                    'sort_order' => $partData['sort_order'] ?? $index,
                ]);

                // Calculate length
                $part->calculated_length = $part->calculateLength();
                $part->save();
            }

            DB::commit();

            $config->frameConfig->load('parts.product');

            return response()->json([
                'message' => 'Frame parts saved successfully',
                'parts' => $config->frameConfig->parts->map(fn($p) => $this->formatPart($p)),
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
     * Update door configuration (Step 3)
     */
    public function updateDoorConfig(Request $request, $id)
    {
        try {
            $config = DoorFrameConfiguration::with('openingSpecs')->findOrFail($id);

            if (!$config->includesDoor()) {
                return response()->json([
                    'error' => 'Invalid operation',
                    'message' => 'Job scope does not include door',
                ], 422);
            }

            if (!$config->canEdit()) {
                return response()->json([
                    'error' => 'Cannot edit configuration',
                    'message' => 'Configuration is not in editable status',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'door_configs' => 'required|array|min:1',
                'door_configs.*.door_system_product_id' => 'required|exists:products,id',
                'door_configs.*.leaf_type' => 'required|in:single,active,inactive',
                'door_configs.*.stile_product_id' => 'nullable|exists:products,id',
                'door_configs.*.glazing' => 'required|in:0.25,0.5,1.0',
                'door_configs.*.preset' => 'nullable|in:standard,ws_continuous,ws_butt',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            // Delete existing door configs
            DoorFrameDoorConfig::where('configuration_id', $config->id)->delete();

            // Create new door configs
            foreach ($request->door_configs as $doorData) {
                DoorFrameDoorConfig::create([
                    'configuration_id' => $config->id,
                    'door_system_product_id' => $doorData['door_system_product_id'],
                    'leaf_type' => $doorData['leaf_type'],
                    'stile_product_id' => $doorData['stile_product_id'] ?? null,
                    'glazing' => $doorData['glazing'],
                    'preset' => $doorData['preset'] ?? null,
                ]);
            }

            DB::commit();

            $config->load('doorConfigs.doorSystemProduct', 'doorConfigs.stileProduct');

            return response()->json([
                'message' => 'Door configurations saved successfully',
                'door_configs' => $config->doorConfigs->map(fn($d) => $this->formatDoorConfig($d)),
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
            if (!empty($errors)) {
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
            'door_tags' => $config->doors->map(fn($d) => $d->door_tag),
            'opening_specs' => $config->openingSpecs ? $this->formatOpeningSpecs($config->openingSpecs) : null,
            'frame_config' => $config->frameConfig ? $this->formatFrameConfig($config->frameConfig) : null,
            'door_configs' => $config->doorConfigs->map(fn($d) => $this->formatDoorConfig($d)),
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
            'frame_system_product' => [
                'id' => $frameConfig->frameSystemProduct->id,
                'part_number' => $frameConfig->frameSystemProduct->part_number,
                'description' => $frameConfig->frameSystemProduct->description,
            ],
            'glazing' => $frameConfig->glazing,
            'glazing_label' => $frameConfig->glazing_label,
            'has_transom' => $frameConfig->has_transom,
            'transom_glazing' => $frameConfig->transom_glazing,
            'transom_glazing_label' => $frameConfig->transom_glazing_label,
            'total_frame_height' => $frameConfig->total_frame_height,
            'parts' => $frameConfig->parts->map(fn($p) => $this->formatPart($p)),
        ];
    }

    /**
     * Helper: Format door config
     */
    private function formatDoorConfig($doorConfig)
    {
        return [
            'id' => $doorConfig->id,
            'door_system_product' => [
                'id' => $doorConfig->doorSystemProduct->id,
                'part_number' => $doorConfig->doorSystemProduct->part_number,
                'description' => $doorConfig->doorSystemProduct->description,
            ],
            'leaf_type' => $doorConfig->leaf_type,
            'leaf_type_label' => $doorConfig->leaf_type_label,
            'stile_product' => $doorConfig->stileProduct ? [
                'id' => $doorConfig->stileProduct->id,
                'part_number' => $doorConfig->stileProduct->part_number,
                'description' => $doorConfig->stileProduct->description,
            ] : null,
            'glazing' => $doorConfig->glazing,
            'glazing_label' => $doorConfig->glazing_label,
            'preset' => $doorConfig->preset,
            'preset_label' => $doorConfig->preset_label,
            'parts' => $doorConfig->parts->map(fn($p) => $this->formatPart($p)),
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
            'is_auto_generated' => $part->is_auto_generated ?? false,
            'sort_order' => $part->sort_order,
        ];
    }
}
