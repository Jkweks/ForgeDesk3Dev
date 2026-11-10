<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\Shipment;
use App\Services\Shipping\WorkOrderShipmentService as Svc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Which work-order elevations ride on which shipment: selection, splitting into trips, partial-delivery tags. */
class ShipmentElevationController extends Controller
{
    public function __construct(private Svc $svc) {}

    /** Every elevation the shipment can draw from, with its delivery state across all shipments. */
    public function index(int $id)
    {
        return response()->json($this->payload(Shipment::findOrFail($id)));
    }

    /** Replace the shipment's elevation set (only while it hasn't shipped). */
    public function sync(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        $this->assertEditable($shipment);
        $data = $request->validate(['elevation_ids' => 'present|array|max:2000', 'elevation_ids.*' => 'integer|distinct']);
        $ids = collect($data['elevation_ids']);

        $allowedWo = $this->svc->candidateWorkOrders($shipment)->pluck('id');
        $elevs = FdWoElevation::with('elevationType')->whereIn('id', $ids)->get();
        if ($elevs->count() !== $ids->count() || $elevs->contains(fn ($e) => ! $allowedWo->contains($e->work_order_id))) {
            return response()->json(['message' => 'Some elevations do not belong to this shipment\'s work orders.'], 422);
        }

        $states = $this->svc->states($ids);
        foreach ($elevs as $e) {
            $s = $states[$e->id];
            $elsewhere = collect($s['shipments'])->first(fn ($x) => $x['id'] !== $shipment->id && $x['status'] === 'shipped');
            if ($s['state'] === 'delivered' && ! collect($s['shipments'])->contains('id', $shipment->id)) {
                return response()->json(['message' => ($e->elevation_tag ?: 'An elevation').' has already been delivered.'], 422);
            }
            if ($elsewhere) {
                return response()->json(['message' => ($e->elevation_tag ?: 'An elevation').' is already on the truck with '.$elsewhere['number'].'.'], 422);
            }
        }

        DB::transaction(function () use ($shipment, $ids, $elevs) {
            // Picking an elevation that sits on another not-yet-shipped shipment moves it here.
            DB::table('shipment_elevations')->whereIn('elevation_id', $ids)->where('shipment_id', '!=', $shipment->id)
                ->whereIn('shipment_id', Shipment::whereIn('status', Svc::EDITABLE)->select('id'))->delete();
            $shipment->elevations()->sync($ids->all());
            $added = false;
            foreach ($elevs as $e) {
                $added = $this->svc->ensureDefaultCategory($shipment, $e) || $added;
            }
            // A new category means the passed pre-check no longer covers the checklist.
            if ($added && $shipment->status === 'pre_check_done') {
                $shipment->update(['status' => 'draft']);
            }
        });

        return response()->json($this->payload($shipment->fresh()));
    }

    /** Partial-delivery tag + note on one elevation of this shipment (editable until the delivery is verified). */
    public function flag(Request $request, int $id, int $elevationId)
    {
        $shipment = Shipment::findOrFail($id);
        if (in_array($shipment->status, ['cancelled', 'delivered', 'delivered_issues'], true)) {
            return response()->json(['message' => 'This shipment is closed.'], 422);
        }
        $data = $request->validate(['partial' => 'required|boolean', 'partial_note' => 'nullable|string|max:1000']);
        if (! $shipment->elevations()->where('fd_wo_elevations.id', $elevationId)->exists()) {
            return response()->json(['message' => 'That elevation is not on this shipment.'], 404);
        }
        if ($data['partial'] && blank($data['partial_note'] ?? null)) {
            return response()->json(['message' => 'Add a note saying what was delivered and what is still outstanding.'], 422);
        }
        $shipment->elevations()->updateExistingPivot($elevationId, [
            'partial' => $data['partial'], 'partial_note' => $data['partial'] ? trim($data['partial_note']) : null,
        ]);

        return response()->json($this->payload($shipment));
    }

    /** New sibling draft for the same work order; `elevation_ids` (optional) move onto it. */
    public function split(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        $this->assertEditable($shipment);
        $data = $request->validate(['elevation_ids' => 'nullable|array|max:2000', 'elevation_ids.*' => 'integer|distinct']);
        $ids = $data['elevation_ids'] ?? [];
        $mine = $shipment->elevations()->pluck('fd_wo_elevations.id');
        if (collect($ids)->diff($mine)->isNotEmpty()) {
            return response()->json(['message' => 'Only elevations on this shipment can be moved.'], 422);
        }
        $new = $this->svc->split($shipment, $ids, $request->user()->id);

        return response()->json(['id' => $new->id, 'shipment_number' => $new->shipment_number], 201);
    }

    /** Elevations of open work orders that are on no open shipment (not yet scheduled, or short-delivered). */
    public function unscheduled()
    {
        $wos = FdWorkOrder::with(['businessJob:id,job_number,job_name', 'elevations.elevationType:id,name,color'])
            ->where('archived', false)->whereIn('status', FdWorkOrder::OPEN_STATUSES)->get()
            ->filter(fn ($wo) => ! Svc::jobIsExempt($wo->businessJob) || $wo->shipments()->exists()
                || DB::table('shipment_elevations')->whereIn('elevation_id', $wo->elevations->pluck('id'))->exists());

        $states = $this->svc->states($wos->flatMap(fn ($wo) => $wo->elevations->pluck('id')));
        $out = [];
        foreach ($wos as $wo) {
            $rows = $wo->elevations->filter(fn ($e) => in_array($states[$e->id]['state'], ['unscheduled', 'partial'], true));
            if ($rows->isEmpty()) {
                continue;
            }
            $out[] = [
                'work_order_id' => $wo->id, 'label' => trim(($wo->businessJob?->job_number ?? '').' '.($wo->businessJob?->job_name ?? '')).' · '.$wo->release_token,
                'due_date' => $wo->due_date?->format('Y-m-d'),
                'draft_shipment_id' => $wo->shipments()->whereIn('status', ['draft', 'pre_check_done'])->orderBy('id')->value('id'),
                'elevations' => $rows->map(fn ($e) => [
                    'id' => $e->id, 'tag' => $e->elevation_tag, 'type' => $e->elevationType?->name, 'quantity' => $e->quantity,
                    'state' => $states[$e->id]['state'],
                ])->values(),
            ];
        }

        return response()->json($out);
    }

    private function assertEditable(Shipment $shipment): void
    {
        if (! in_array($shipment->status, Svc::EDITABLE, true)) {
            abort(response()->json(['message' => 'Elevations can only be changed before the shipment ships.'], 422));
        }
    }

    private function payload(Shipment $shipment): array
    {
        $onThis = $shipment->elevations()->get()->keyBy('id');
        $rows = collect();
        $wos = $this->svc->candidateWorkOrders($shipment);
        $elevs = FdWoElevation::with('elevationType:id,name,color')->whereIn('work_order_id', $wos->pluck('id'))->orderBy('work_order_id')->orderBy('id')->get();
        $states = $this->svc->states($elevs->pluck('id')->merge($onThis->keys()));

        foreach ($wos as $wo) {
            $rows->push([
                'work_order_id' => $wo->id,
                'label' => trim(($wo->businessJob?->job_number ?? '').' '.($wo->businessJob?->job_name ?? '')).' · '.$wo->release_token,
                'elevations' => $elevs->where('work_order_id', $wo->id)->map(function ($e) use ($onThis, $states, $shipment) {
                    $st = $states[$e->id];

                    return [
                        'id' => $e->id, 'tag' => $e->elevation_tag, 'type' => $e->elevationType?->name, 'color' => $e->elevationType?->color,
                        'quantity' => $e->quantity, 'state' => $st['state'], 'partial_history' => $st['partial_history'],
                        'on_this' => $onThis->has($e->id),
                        'partial' => (bool) $onThis->get($e->id)?->pivot->partial, 'partial_note' => $onThis->get($e->id)?->pivot->partial_note,
                        'other_shipments' => collect($st['shipments'])->reject(fn ($x) => $x['id'] === $shipment->id)->values(),
                    ];
                })->values(),
            ]);
        }

        return ['editable' => in_array($shipment->status, Svc::EDITABLE, true), 'work_orders' => $rows->values()];
    }
}
