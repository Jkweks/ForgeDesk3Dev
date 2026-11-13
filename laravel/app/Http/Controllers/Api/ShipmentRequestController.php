<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentRequest;
use App\Models\ShipmentType;
use App\Models\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Shipping requests raised from the shipping queue and approved/rejected by a manager or admin. */
class ShipmentRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = array_filter(explode(',', (string) $request->query('status', 'pending')));
        $rows = ShipmentRequest::with(['job:id,job_number,job_name,site_address', 'requester:id,name', 'decider:id,name', 'shipment:id,shipment_number'])
            ->whereIn('status', $status)->orderBy('requested_date')->orderBy('id')->limit(500)->get();
        $types = ShipmentType::pluck('name', 'id');

        return response()->json($rows->map(fn (ShipmentRequest $r) => $r->toArray() + [
            'type_names' => collect($r->shipment_type_ids ?? [])->map(fn ($id) => $types[$id] ?? null)->filter()->values(),
        ])->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'requested_date' => 'required|date',
            'job_id' => ['nullable', 'integer', Rule::exists('business_jobs', 'id')],
            'custom_job' => 'nullable|string|max:120',
            'destination' => 'nullable|string|max:255',
            'shipment_type_ids' => 'nullable|array|max:20',
            'shipment_type_ids.*' => ['integer', 'distinct', Rule::exists('shipment_types', 'id')->where('is_active', true)],
            'notes' => 'nullable|string|max:5000',
        ]);
        if (empty($data['job_id']) && blank($data['custom_job'] ?? null)) {
            return response()->json(['message' => 'Pick a job or enter a job name.', 'errors' => ['job_id' => ['Pick a job or enter a job name.']]], 422);
        }

        $r = ShipmentRequest::create($data + ['status' => 'pending', 'requested_by' => $request->user()->id]);

        // In-app only: shows in the bell for admins and anyone who can approve requests.
        $r->load('job:id,job_number,job_name');
        $what = $r->job ? $r->job->job_name.' ('.$r->job->job_number.')' : $r->custom_job;
        SystemNotification::create(['type' => 'shipment_request', 'level' => 'info',
            'title' => 'Shipping request awaiting approval',
            'message' => $request->user()->name.' requested a shipment for '.$what.' on '.$r->requested_date->format('M j').'.',
            'data' => ['shipment_request_id' => $r->id]]);

        return response()->json($r->fresh(), 201);
    }

    public function approve(Request $request, int $id)
    {
        $data = $request->validate(['ship_date' => 'nullable|date', 'decision_note' => 'nullable|string|max:2000']);

        $shipment = DB::transaction(function () use ($id, $data, $request) {
            $r = ShipmentRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($r->status === 'pending', 422, 'This request has already been '.$r->status.'.');

            $s = Shipment::create([
                'shipment_number' => Shipment::nextNumber(), 'status' => 'draft', 'created_by' => $r->requested_by ?? $request->user()->id,
                'ship_date' => $data['ship_date'] ?? $r->requested_date, 'destination' => $r->destination,
                'custom_jobs' => $r->custom_job ? [$r->custom_job] : null,
                'notes' => $r->notes,
            ]);
            if ($r->job_id) {
                $s->jobs()->sync([$r->job_id]);
            }
            foreach ($r->shipment_type_ids ?? [] as $typeId) {
                $s->loads()->create(['shipment_type_id' => $typeId]);
            }
            $this->clearNotification($r->id, $request->user()->id);
            $r->update(['status' => 'approved', 'decided_by' => $request->user()->id, 'decided_at' => now(),
                'decision_note' => $data['decision_note'] ?? null, 'shipment_id' => $s->id]);

            return $s;
        });

        return response()->json(['shipment_id' => $shipment->id, 'shipment_number' => $shipment->shipment_number]);
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate(['decision_note' => 'nullable|string|max:2000']);
        $r = ShipmentRequest::findOrFail($id);
        abort_unless($r->status === 'pending', 422, 'This request has already been '.$r->status.'.');
        $this->clearNotification($r->id, $request->user()->id);
        $r->update(['status' => 'rejected', 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $data['decision_note'] ?? null]);

        return response()->json($r->fresh());
    }

    /** The requester (or an approver) can withdraw a request that has not been decided. */
    public function withdraw(Request $request, int $id)
    {
        $r = ShipmentRequest::findOrFail($id);
        abort_unless($r->requested_by === $request->user()->id || $request->user()->hasPermission('shipments.approve'), 403, 'Only the requester can withdraw this.');
        abort_unless($r->status === 'pending', 422, 'This request has already been '.$r->status.'.');
        $this->clearNotification($r->id, $request->user()->id);
        $r->update(['status' => 'withdrawn', 'decided_by' => $request->user()->id, 'decided_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** Once a request is decided or withdrawn, its bell entry is done with. */
    private function clearNotification(int $requestId, int $userId): void
    {
        SystemNotification::active()->where('type', 'shipment_request')->get()
            ->filter(fn ($n) => ($n->data['shipment_request_id'] ?? null) === $requestId)
            ->each(fn ($n) => $n->update(['dismissed_at' => now(), 'dismissed_by_user_id' => $userId]));
    }
}
