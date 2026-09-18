<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\JobDocument;
use App\Models\JobReservation;
use App\Models\JobReservationItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BusinessJobController extends Controller
{
    /**
     * List all business jobs
     */
    public function index(Request $request)
    {
        try {
            $query = BusinessJob::query();

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search by job number or name
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('job_number', 'like', "%{$search}%")
                        ->orWhere('job_name', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            }

            $jobs = $query
                ->with('createdBy')
                ->withCount(['jobReservations', 'workOrders'])
                ->orderByRaw("CASE WHEN status IN ('completed', 'cancelled') THEN 1 ELSE 0 END")
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'job_number' => $job->job_number,
                        'job_name' => $job->job_name,
                        'customer_name' => $job->customer_name,
                        'project_manager' => $job->project_manager,
                        'project_manager_id' => $job->project_manager_id,
                        'superintendent' => $job->superintendent,
                        'superintendent_id' => $job->superintendent_id,
                        'division' => substr($job->job_number ?? '', 0, 1) ?: '—',
                        'status' => $job->status,
                        'status_label' => $job->status_label,
                        'start_date' => $job->start_date?->format('Y-m-d'),
                        'target_completion_date' => $job->target_completion_date?->format('Y-m-d'),
                        'days_until_completion' => $job->days_until_completion,
                        'reservations_count' => $job->job_reservations_count,
                        'work_orders_count' => $job->work_orders_count,
                        'created_by' => $job->createdBy ? [
                            'id' => $job->createdBy->id,
                            'name' => $job->createdBy->name,
                        ] : null,
                        'created_at' => $job->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'jobs' => $jobs,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list jobs', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Failed to list jobs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed job information
     */
    public function show($id)
    {
        try {
            $job = BusinessJob::with([
                'createdBy',
                'jobReservations',
            ])->findOrFail($id);

            // The door/frame configurator is an optional module; don't let a
            // job fetch 500 just because those tables aren't present.
            $configCount = 0;
            try {
                $configCount = $job->doorFrameConfigurations()->count();
            } catch (\Throwable $e) {
                Log::warning('doorFrameConfigurations count skipped', ['job_id' => $id, 'message' => $e->getMessage()]);
            }

            return response()->json([
                'job' => [
                    'id' => $job->id,
                    'job_number' => $job->job_number,
                    'job_name' => $job->job_name,
                    'customer_name' => $job->customer_name,
                    'project_manager' => $job->project_manager,
                    'project_manager_id' => $job->project_manager_id,
                    'superintendent' => $job->superintendent,
                    'superintendent_id' => $job->superintendent_id,
                    'site_address' => $job->site_address,
                    'contact_name' => $job->contact_name,
                    'contact_phone' => $job->contact_phone,
                    'contact_email' => $job->contact_email,
                    'status' => $job->status,
                    'status_label' => $job->status_label,
                    'start_date' => $job->start_date?->format('Y-m-d'),
                    'target_completion_date' => $job->target_completion_date?->format('Y-m-d'),
                    'actual_completion_date' => $job->actual_completion_date?->format('Y-m-d'),
                    'notes' => $job->notes,
                    'created_by' => $job->createdBy ? [
                        'id' => $job->createdBy->id,
                        'name' => $job->createdBy->name,
                    ] : null,
                    'configurations_count' => $configCount,
                    'reservations_count' => $job->jobReservations->count(),
                    'created_at' => $job->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $job->updated_at?->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Job not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch job', [
                'job_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch job',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create new job
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'job_number' => 'required|string|max:100|unique:business_jobs,job_number',
                'job_name' => 'required|string|max:255',
                'customer_name' => 'nullable|string|max:255',
                'site_address' => 'nullable|string|max:500',
                'contact_name' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:50',
                'contact_email' => 'nullable|email|max:255',
                'status' => 'nullable|in:active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'target_completion_date' => 'nullable|date',
                'notes' => 'nullable|string',
                'project_manager_id' => 'nullable|integer|exists:users,id',
                'superintendent_id' => 'nullable|integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $pm = \App\Models\User::resolvePersonField($request->project_manager_id, $request->project_manager);
            $super = \App\Models\User::resolvePersonField($request->superintendent_id, $request->superintendent);

            $job = BusinessJob::create([
                'job_number' => $request->job_number,
                'job_name' => $request->job_name,
                'customer_name' => $request->customer_name,
                'project_manager' => $pm['label'],
                'project_manager_id' => $pm['id'],
                'superintendent' => $super['label'],
                'superintendent_id' => $super['id'],
                'site_address' => $request->site_address,
                'contact_name' => $request->contact_name,
                'contact_phone' => $request->contact_phone,
                'contact_email' => $request->contact_email,
                'status' => $request->status ?? 'active',
                'start_date' => $request->start_date,
                'target_completion_date' => $request->target_completion_date,
                'notes' => $request->notes,
                'created_by_id' => auth()->id(),
            ]);

            Log::info('Job created', [
                'job_id' => $job->id,
                'job_number' => $job->job_number,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Job created successfully',
                'job' => [
                    'id' => $job->id,
                    'job_number' => $job->job_number,
                    'job_name' => $job->job_name,
                    'customer_name' => $job->customer_name,
                    'project_manager' => $job->project_manager,
                    'project_manager_id' => $job->project_manager_id,
                    'superintendent' => $job->superintendent,
                    'superintendent_id' => $job->superintendent_id,
                    'status' => $job->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create job', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'error' => 'Failed to create job',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update existing job
     */
    public function update(Request $request, $id)
    {
        try {
            $job = BusinessJob::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'job_number' => 'sometimes|required|string|max:100|unique:business_jobs,job_number,'.$id,
                'job_name' => 'sometimes|required|string|max:255',
                'customer_name' => 'nullable|string|max:255',
                'site_address' => 'nullable|string|max:500',
                'contact_name' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:50',
                'contact_email' => 'nullable|email|max:255',
                'status' => 'nullable|in:active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'target_completion_date' => 'nullable|date',
                'actual_completion_date' => 'nullable|date',
                'notes' => 'nullable|string',
                'project_manager_id' => 'sometimes|nullable|integer|exists:users,id',
                'superintendent_id' => 'sometimes|nullable|integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Changing a job's identity fields (once it exists) needs jobs.edit-core.
            // Lighter edits (status / notes) only need jobs.edit.
            if ($this->coreFieldsChanged($request, $job) && ! $request->user()?->hasPermission('jobs.edit-core')) {
                return response()->json([
                    'message' => 'Editing the job number, name, customer, project manager or dates requires the "Jobs: Edit Core Details" permission.',
                ], 403);
            }

            $oldJobNumber = $job->job_number;

            $job->fill($request->only([
                'job_number',
                'job_name',
                'customer_name',
                'site_address',
                'contact_name',
                'contact_phone',
                'contact_email',
                'status',
                'start_date',
                'target_completion_date',
                'actual_completion_date',
                'notes',
            ]));

            // Project manager: an explicit `project_manager_id` (even null) is
            // authoritative; a bare `project_manager` string is the legacy path.
            if ($request->has('project_manager_id')) {
                $pm = \App\Models\User::resolvePersonField($request->input('project_manager_id'), $request->input('project_manager'));
                $job->project_manager = $pm['label'];
                $job->project_manager_id = $pm['id'];
            } elseif ($request->has('project_manager')) {
                $pm = \App\Models\User::resolvePersonField(null, $request->input('project_manager'));
                $job->project_manager = $pm['label'];
                $job->project_manager_id = $pm['id'];
            }

            // Superintendent: same rule — an explicit `superintendent_id` (even
            // null) wins; a bare `superintendent` string is the legacy path.
            if ($request->has('superintendent_id')) {
                $super = \App\Models\User::resolvePersonField($request->input('superintendent_id'), $request->input('superintendent'));
                $job->superintendent = $super['label'];
                $job->superintendent_id = $super['id'];
            } elseif ($request->has('superintendent')) {
                $super = \App\Models\User::resolvePersonField(null, $request->input('superintendent'));
                $job->superintendent = $super['label'];
                $job->superintendent_id = $super['id'];
            }

            $renamedReservations = 0;
            DB::transaction(function () use ($job, $oldJobNumber, &$renamedReservations) {
                $job->save();

                // Reservations key on the job_number string — carry the rename across.
                if ($job->job_number !== $oldJobNumber && $oldJobNumber) {
                    $renamedReservations = JobReservation::where(function ($q) use ($job, $oldJobNumber) {
                        $q->where('business_job_id', $job->id)->orWhere('job_number', $oldJobNumber);
                    })->update(['job_number' => $job->job_number]);
                }
            });

            Log::info('Job updated', [
                'job_id' => $job->id,
                'updated_by' => auth()->id(),
                'job_number_changed' => $job->job_number !== $oldJobNumber ? "{$oldJobNumber} → {$job->job_number}" : null,
                'reservations_renamed' => $renamedReservations,
            ]);

            return response()->json([
                'message' => $renamedReservations > 0
                    ? "Job updated. Renamed {$renamedReservations} linked reservation(s) to {$job->job_number}."
                    : 'Job updated successfully',
                'reservations_renamed' => $renamedReservations,
                'job' => [
                    'id' => $job->id,
                    'job_number' => $job->job_number,
                    'job_name' => $job->job_name,
                    'status' => $job->status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update job', [
                'job_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update job',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * True when the request would change one of a job's identity fields — the
     * ones gated behind jobs.edit-core.
     */
    private function coreFieldsChanged(Request $request, BusinessJob $job): bool
    {
        $stringFields = [
            'job_number', 'job_name', 'customer_name', 'site_address',
            'contact_name', 'contact_phone', 'contact_email',
        ];
        foreach ($stringFields as $field) {
            if ($request->has($field) && trim((string) $request->input($field)) !== trim((string) $job->{$field})) {
                return true;
            }
        }

        foreach (['start_date', 'target_completion_date'] as $field) {
            if ($request->has($field)) {
                $new = $request->input($field) ? \Illuminate\Support\Carbon::parse($request->input($field))->format('Y-m-d') : null;
                $old = optional($job->{$field})->format('Y-m-d');
                if ($new !== $old) {
                    return true;
                }
            }
        }

        if ($request->has('project_manager_id')
            && (int) $request->input('project_manager_id') !== (int) $job->project_manager_id) {
            return true;
        }
        if ($request->has('project_manager') && ! $request->has('project_manager_id')
            && trim((string) $request->input('project_manager')) !== trim((string) $job->project_manager)) {
            return true;
        }

        if ($request->has('superintendent_id')
            && (int) $request->input('superintendent_id') !== (int) $job->superintendent_id) {
            return true;
        }
        if ($request->has('superintendent') && ! $request->has('superintendent_id')
            && trim((string) $request->input('superintendent')) !== trim((string) $job->superintendent)) {
            return true;
        }

        return false;
    }

    /**
     * Delete job (soft delete)
     */
    public function destroy($id)
    {
        try {
            $job = BusinessJob::findOrFail($id);

            // Check if job has configurations (optional module — tolerate absence).
            try {
                $configCount = $job->doorFrameConfigurations()->count();
            } catch (\Throwable $e) {
                $configCount = 0;
            }
            if ($configCount > 0) {
                return response()->json([
                    'error' => 'Cannot delete job with existing configurations',
                    'message' => "This job has {$configCount} configuration(s). Please delete them first.",
                ], 422);
            }

            // Check if job has reservations
            if ($job->jobReservations()->count() > 0) {
                return response()->json([
                    'error' => 'Cannot delete job with existing reservations',
                    'message' => 'This job has '.$job->jobReservations()->count().' reservation(s). Please remove them first.',
                ], 422);
            }

            $job->delete();

            Log::info('Job deleted', [
                'job_id' => $id,
                'deleted_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Job deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete job', [
                'job_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete job',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all reservations for a specific job
     */
    public function getReservations($jobId)
    {
        try {
            $job = BusinessJob::findOrFail($jobId);

            $reservations = $job->jobReservations()
                ->with('items.product')
                ->orderBy('reservation_id', 'desc')
                ->get()
                ->map(function ($reservation) {
                    return [
                        'id' => $reservation->id,
                        'reservation_id' => $reservation->reservation_id,
                        'job_number' => $reservation->job_number,
                        'job_name' => $reservation->job_name,
                        'status' => $reservation->status,
                        'status_label' => $reservation->status_label,
                        'requested_by' => $reservation->requested_by,
                        'requested_by_id' => $reservation->requested_by_id,
                        'needed_by' => $reservation->needed_by?->format('Y-m-d'),
                        'items_count' => $reservation->items->count(),
                        'total_requested' => $reservation->total_requested,
                        'total_committed' => $reservation->total_committed,
                        'total_consumed' => $reservation->total_consumed,
                        'created_at' => $reservation->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $reservation->updated_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'job' => [
                    'id' => $job->id,
                    'job_number' => $job->job_number,
                    'job_name' => $job->job_name,
                ],
                'reservations' => $reservations,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch job reservations', [
                'job_id' => $jobId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch reservations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific reservation by its sequential ID within a job
     */
    public function getReservation($jobId, $reservationId)
    {
        try {
            $job = BusinessJob::findOrFail($jobId);

            $reservation = JobReservation::where('business_job_id', $jobId)
                ->where('reservation_id', $reservationId)
                ->with('items.product')
                ->firstOrFail();

            $items = $reservation->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'requested_qty' => $item->requested_qty,
                    'committed_qty' => $item->committed_qty,
                    'consumed_qty' => $item->consumed_qty,
                    'product' => [
                        'id' => $item->product->id,
                        'sku' => $item->product->sku,
                        'part_number' => $item->product->part_number,
                        'finish' => $item->product->finish,
                        'description' => $item->product->description,
                        'quantity_on_hand' => $item->product->quantity_on_hand,
                        'quantity_available' => $item->product->quantity_available,
                    ],
                ];
            });

            return response()->json([
                'reservation' => [
                    'id' => $reservation->id,
                    'reservation_id' => $reservation->reservation_id,
                    'business_job_id' => $reservation->business_job_id,
                    'job_number' => $reservation->job_number,
                    'job_name' => $reservation->job_name,
                    'status' => $reservation->status,
                    'status_label' => $reservation->status_label,
                    'requested_by' => $reservation->requested_by,
                    'requested_by_id' => $reservation->requested_by_id,
                    'needed_by' => $reservation->needed_by?->format('Y-m-d'),
                    'notes' => $reservation->notes,
                    'created_at' => $reservation->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $reservation->updated_at->format('Y-m-d H:i:s'),
                ],
                'items' => $items,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Reservation not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch reservation', [
                'job_id' => $jobId,
                'reservation_id' => $reservationId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch reservation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new reservation for a job
     */
    public function createReservation(Request $request, $jobId)
    {
        try {
            $job = BusinessJob::findOrFail($jobId);

            $validator = Validator::make($request->all(), [
                'job_name' => 'nullable|string|max:255',
                'requested_by' => 'required_without:requested_by_id|nullable|string|max:255',
                'requested_by_id' => 'nullable|integer|exists:users,id',
                'needed_by' => 'nullable|date',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.requested_qty' => 'required|numeric|min:0',
                'items.*.committed_qty' => 'nullable|numeric|min:0',
                'material_check_file_token' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $requestedBy = \App\Models\User::resolvePersonField($request->requested_by_id, $request->requested_by);

            DB::beginTransaction();

            // Create the reservation
            $reservation = JobReservation::create([
                'business_job_id' => $job->id,
                'job_number' => $job->job_number,
                'job_name' => $request->job_name ?? $job->job_name,
                'requested_by' => $requestedBy['label'] ?? '',
                'requested_by_id' => $requestedBy['id'],
                'needed_by' => $request->needed_by,
                'notes' => $request->notes,
                'status' => 'active',
                // release_number auto-assigned by JobReservation::creating() boot hook
            ]);

            // Merge duplicate product_ids by summing quantities
            $mergedItems = [];
            foreach ($request->items as $itemData) {
                $pid = $itemData['product_id'];
                if (isset($mergedItems[$pid])) {
                    $mergedItems[$pid]['requested_qty'] += $itemData['requested_qty'];
                    $mergedItems[$pid]['committed_qty'] = ($mergedItems[$pid]['committed_qty'] ?? 0) + ($itemData['committed_qty'] ?? $itemData['requested_qty']);
                } else {
                    $mergedItems[$pid] = $itemData;
                }
            }

            // Add items — commit full requested qty to allow negative stock for reorder flagging
            foreach ($mergedItems as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);

                $requestedQty = $itemData['requested_qty'];
                $committedQty = $itemData['committed_qty'] ?? $requestedQty;

                JobReservationItem::create([
                    'reservation_id' => $reservation->id,
                    'product_id' => $product->id,
                    'requested_qty' => $requestedQty,
                    'committed_qty' => $committedQty,
                    'consumed_qty' => 0,
                ]);
            }

            $this->attachMaterialCheckDocument($request, $job, $reservation);

            DB::commit();

            Log::info('Job reservation created', [
                'job_id' => $job->id,
                'reservation_id' => $reservation->reservation_id,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Reservation created successfully',
                'reservation' => [
                    'id' => $reservation->id,
                    'reservation_id' => $reservation->reservation_id,
                    'job_number' => $reservation->job_number,
                    'status' => $reservation->status,
                ],
                'warnings' => [],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create reservation', [
                'job_id' => $jobId,
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'error' => 'Failed to create reservation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Claims the file staged by MaterialCheckController::stageMaterialCheckFile()
     * (the estimate/CSV this reservation's material check ran against) and
     * attaches it to the job as a JobDocument tied to this reservation — see
     * JobReservation::archiveLinkedDocuments() for what happens to it if the
     * reservation is later cancelled/deleted. Silently a no-op when no token
     * was passed, or the staged file already expired/was claimed — a missing
     * source file should never block the reservation itself.
     */
    private function attachMaterialCheckDocument(Request $request, BusinessJob $job, JobReservation $reservation): void
    {
        $token = $request->input('material_check_file_token');
        if (! $token) {
            return;
        }

        $staged = Cache::pull("material_check_staging:{$token}");
        if (! $staged || ! Storage::disk('local')->exists($staged['path'])) {
            return;
        }

        $ext = strtolower(pathinfo($staged['path'], PATHINFO_EXTENSION) ?: 'dat');
        $docType = in_array($ext, ['xlsx', 'xlsm'], true) ? 'ez_estimate' : 'other';
        $newPath = "job_documents/{$job->id}/".Str::uuid().".{$ext}";

        Storage::disk('local')->move($staged['path'], $newPath);

        JobDocument::create([
            'business_job_id' => $job->id,
            'job_reservation_id' => $reservation->id,
            'doc_type' => $docType,
            'label' => 'Material check',
            'original_name' => $staged['original_name'],
            'file_path' => $newPath,
            'file_size' => $staged['file_size'],
            'file_mime' => $staged['file_mime'],
            'uploaded_by' => $request->user()?->id,
        ]);
    }

    /**
     * Update reservation status
     */
    public function updateReservationStatus(Request $request, $jobId, $reservationId)
    {
        try {
            $job = BusinessJob::findOrFail($jobId);

            $reservation = JobReservation::where('business_job_id', $jobId)
                ->where('reservation_id', $reservationId)
                ->firstOrFail();

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:active,in_progress,fulfilled,on_hold,cancelled',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $oldStatus = $reservation->status;
            $newStatus = $request->status;

            // Update status (the model will handle inventory updates via boot method)
            $reservation->status = $newStatus;
            $reservation->save();
            $reservation->businessJob?->syncAutoStatus();

            Log::info('Reservation status updated', [
                'job_id' => $jobId,
                'reservation_id' => $reservationId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Status updated successfully',
                'reservation' => [
                    'id' => $reservation->id,
                    'reservation_id' => $reservation->reservation_id,
                    'status' => $reservation->status,
                    'status_label' => $reservation->status_label,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update reservation status', [
                'job_id' => $jobId,
                'reservation_id' => $reservationId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a reservation
     */
    public function deleteReservation($jobId, $reservationId)
    {
        try {
            $job = BusinessJob::findOrFail($jobId);

            $reservation = JobReservation::where('business_job_id', $jobId)
                ->where('reservation_id', $reservationId)
                ->firstOrFail();

            // Check if can delete
            if (in_array($reservation->status, ['in_progress', 'fulfilled'])) {
                return response()->json([
                    'error' => 'Cannot delete reservation',
                    'message' => "Cannot delete reservation in '{$reservation->status}' status",
                ], 422);
            }

            $reservation->delete();

            Log::info('Reservation deleted', [
                'job_id' => $jobId,
                'reservation_id' => $reservationId,
                'deleted_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Reservation deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete reservation', [
                'job_id' => $jobId,
                'reservation_id' => $reservationId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete reservation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List fabrication work orders for a job
     */
    public function getWorkOrders($jobId)
    {
        $job = BusinessJob::findOrFail($jobId);
        $workOrders = $job->workOrders()
            ->with(['steps.completedBy'])
            ->withCount(['elevations', 'elevations as elevations_complete' => fn ($q) => $q->whereNotNull('date_completed')])
            ->get()
            ->map(fn ($wo) => [
                'id' => $wo->id,
                'release_number' => $wo->release_number,
                'release_code' => $wo->release_code,
                'release_label' => "{$job->job_number}-{$wo->release_token}",
                'date_issued' => $wo->date_issued?->format('Y-m-d'),
                'material_delivery' => $wo->material_delivery,
                'notes' => $wo->notes,
                'archived' => $wo->archived,
                'elevation_count' => $wo->elevations_count ?? 0,
                'elevations_complete' => $wo->elevations_complete ?? 0,
                'created_at' => $wo->created_at->toIso8601String(),
                'steps' => $wo->steps->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'sort_order' => $s->sort_order,
                    'status' => $s->status,
                    'completed_by_name' => $s->completedBy?->name,
                    'completed_at' => $s->completed_at?->toIso8601String(),
                ])->values(),
            ]);

        return response()->json(['work_orders' => $workOrders]);
    }

    /**
     * List inventory transactions for a job
     */
    public function getTransactions($jobId)
    {
        try {
            $job = BusinessJob::findOrFail($jobId);

            $transactions = \App\Models\InventoryTransaction::with(['product', 'user'])
                ->where('business_job_id', $jobId)
                ->orderBy('transaction_date', 'desc')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'type_label' => ucwords(str_replace('_', ' ', $t->type)),
                    'quantity' => $t->quantity,
                    'quantity_before' => $t->quantity_before,
                    'quantity_after' => $t->quantity_after,
                    'notes' => $t->notes,
                    'reference_number' => $t->reference_number,
                    'transaction_date' => $t->transaction_date->format('Y-m-d H:i'),
                    'product' => $t->product ? [
                        'id' => $t->product->id,
                        'sku' => $t->product->sku,
                        'part_number' => $t->product->part_number,
                        'finish' => $t->product->finish,
                        'description' => $t->product->description,
                    ] : null,
                    'user_name' => $t->user?->name,
                ]);

            return response()->json([
                'job' => ['id' => $job->id, 'job_number' => $job->job_number],
                'transactions' => $transactions,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch job transactions', ['job_id' => $jobId, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to fetch transactions', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a manual inventory transaction for a job
     */
    public function createTransaction(Request $request, $jobId)
    {
        try {
            $job = BusinessJob::findOrFail($jobId);

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:products,id',
                'type' => 'required|in:job_issue,receipt,adjustment,job_material_transfer',
                'quantity' => 'required|integer|min:1',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $product = \App\Models\Product::findOrFail($request->product_id);

            // Determine signed quantity based on type
            $qty = abs((int) $request->quantity);
            if (in_array($request->type, ['job_issue'])) {
                $qty = -$qty;  // Issues reduce stock
            }

            $qtyBefore = $product->quantity_on_hand;
            $qtyAfter = $qtyBefore + $qty;

            DB::beginTransaction();

            $transaction = \App\Models\InventoryTransaction::create([
                'product_id' => $product->id,
                'type' => $request->type,
                'quantity' => $qty,
                'quantity_before' => $qtyBefore,
                'quantity_after' => $qtyAfter,
                'business_job_id' => $job->id,
                'reference_number' => $job->job_number,
                'reference_type' => 'business_job',
                'reference_id' => $job->id,
                'notes' => $request->notes,
                'user_id' => auth()->id(),
                'transaction_date' => now(),
            ]);

            // Update product quantity via inventory locations canonical path
            $location = \App\Models\InventoryLocation::where('product_id', $product->id)
                ->orderBy('is_primary', 'desc')
                ->orderBy('id', 'asc')
                ->first();
            if ($location) {
                $location->quantity = max(0, $location->quantity + $qty);
                $location->save();
            } else {
                // No location row exists yet for this product — create an "Unassigned"
                // location so inventory_locations stays the source of truth instead of
                // drifting from a direct quantity_on_hand write.
                $unassigned = \App\Models\StorageLocation::where('code', 'UNASSIGNED')->first();
                \App\Models\InventoryLocation::create([
                    'product_id' => $product->id,
                    'storage_location_id' => $unassigned?->id ?? null,
                    'quantity' => max(0, $qty),
                    'is_primary' => true,
                ]);
            }
            $product->recalculateQuantitiesFromLocations();

            DB::commit();

            Log::info('Job transaction created', [
                'job_id' => $job->id,
                'transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'type' => $request->type,
                'quantity' => $qty,
            ]);

            return response()->json([
                'message' => 'Transaction created successfully',
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'quantity' => $transaction->quantity,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create job transaction', ['job_id' => $jobId, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to create transaction', 'message' => $e->getMessage()], 500);
        }
    }
}
