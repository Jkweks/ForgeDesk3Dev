<?php

namespace App\Services\Shipping;

use App\Models\BusinessJob;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\Shipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ties shipments to work orders: the auto-created draft, attaching elevations, splitting a work order
 * across trips, and deriving each elevation's delivery state from the shipments it sits on.
 */
class WorkOrderShipmentService
{
    /** Shipments in these statuses still need the elevation to be delivered. */
    public const OPEN = ['draft', 'pre_check_done', 'shipped'];

    /** Shipments in these statuses have put the material on site. */
    public const RECEIVED = ['awaiting_verification', 'delivered', 'delivered_issues'];

    /** Editable membership: elevations can still be added/removed/moved. */
    public const EDITABLE = ['draft', 'pre_check_done'];

    /** Job numbers starting with these are not given an automatic draft (shipments can still be added by hand). */
    public const EXEMPT_JOB_PREFIXES = ['3'];

    public static function jobIsExempt(?BusinessJob $job): bool
    {
        $n = trim((string) $job?->job_number);

        return $n !== '' && collect(self::EXEMPT_JOB_PREFIXES)->contains(fn ($p) => str_starts_with($n, $p));
    }

    public function createDraftFor(FdWorkOrder $wo): ?Shipment
    {
        $job = $wo->businessJob;
        if (! $job || self::jobIsExempt($job) || $wo->shipments()->exists()) {
            return null;
        }

        return DB::transaction(function () use ($wo, $job) {
            $s = Shipment::create([
                'shipment_number' => Shipment::nextNumber(), 'status' => 'draft', 'work_order_id' => $wo->id,
                'ship_date' => $wo->due_date?->toDateString(), 'destination' => $job->site_address ?: null,
                'created_by' => auth()->id(),
            ]);
            $s->jobs()->sync([$job->id]);

            return $s;
        });
    }

    /** A new elevation joins the work order's earliest still-draft shipment (and its type's default category). */
    public function attachElevation(FdWoElevation $elevation): void
    {
        $draft = Shipment::where('work_order_id', $elevation->work_order_id)->where('status', 'draft')->orderBy('id')->first();
        if (! $draft) {
            return;
        }
        $draft->elevations()->syncWithoutDetaching([$elevation->id]);
        $this->ensureDefaultCategory($draft, $elevation);
    }

    /** @return bool true when a category was added */
    public function ensureDefaultCategory(Shipment $shipment, FdWoElevation $elevation): bool
    {
        $typeId = $elevation->elevationType?->default_shipment_type_id;
        if ($typeId && \App\Models\ShipmentType::whereKey($typeId)->where('is_active', true)->exists()) {
            return $shipment->loads()->firstOrCreate(['shipment_type_id' => $typeId])->wasRecentlyCreated;
        }

        return false;
    }

    /**
     * Sibling draft for the same work order/jobs/destination/categories; `$elevationIds` move onto it.
     *
     * @param  int[]  $elevationIds
     */
    public function split(Shipment $source, array $elevationIds, ?int $userId): Shipment
    {
        return DB::transaction(function () use ($source, $elevationIds, $userId) {
            $new = Shipment::create([
                'shipment_number' => Shipment::nextNumber(), 'status' => 'draft', 'work_order_id' => $source->work_order_id,
                'destination' => $source->destination, 'custom_jobs' => $source->custom_jobs, 'carrier' => $source->carrier, 'created_by' => $userId,
                'pickup_location_id' => $source->pickup_location_id, 'pickup_address' => $source->pickup_address, 'pickup_lat' => $source->pickup_lat, 'pickup_lng' => $source->pickup_lng,
                'dropoff_location_id' => $source->dropoff_location_id, 'dropoff_address' => $source->dropoff_address, 'dropoff_lat' => $source->dropoff_lat, 'dropoff_lng' => $source->dropoff_lng,
            ]);
            $new->jobs()->sync($source->jobs()->pluck('business_jobs.id')->all());
            foreach ($source->loads as $l) {
                $new->loads()->create(['shipment_type_id' => $l->shipment_type_id]);
            }
            if ($elevationIds) {
                $this->move($source, $new, $elevationIds);
            }

            return $new;
        });
    }

    /** @param int[] $elevationIds */
    public function move(Shipment $from, Shipment $to, array $elevationIds): void
    {
        $rows = DB::table('shipment_elevations')->where('shipment_id', $from->id)->whereIn('elevation_id', $elevationIds)->get();
        foreach ($rows as $r) {
            DB::table('shipment_elevations')->where('id', $r->id)->delete();
            DB::table('shipment_elevations')->updateOrInsert(
                ['shipment_id' => $to->id, 'elevation_id' => $r->elevation_id],
                ['partial' => false, 'partial_note' => null, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    /**
     * Delivery state per elevation id, from the shipments it sits on (cancelled/deleted ones ignored):
     * delivered | in_transit | scheduled | partial | unscheduled. `partial` = was delivered only in part and is
     * on no open shipment, so it is still waiting for another trip. `partial_history` stays true once any
     * received shipment tagged it partial.
     *
     * @param  iterable<int>  $elevationIds
     * @return array<int, array{state: string, partial_history: bool, shipments: list<array{id:int,number:string,status:string,partial:bool}>}>
     */
    public function states(iterable $elevationIds): array
    {
        $ids = collect($elevationIds)->unique()->values();
        $rows = $ids->isEmpty() ? collect() : DB::table('shipment_elevations as se')
            ->join('shipments as s', 's.id', '=', 'se.shipment_id')
            ->whereIn('se.elevation_id', $ids)->whereNull('s.deleted_at')->where('s.status', '!=', 'cancelled')
            ->orderBy('s.id')->get(['se.elevation_id', 'se.partial', 's.id as shipment_id', 's.shipment_number', 's.status']);

        $out = [];
        foreach ($ids as $id) {
            $mine = $rows->where('elevation_id', $id);
            $received = $mine->whereIn('status', self::RECEIVED);
            $open = $mine->whereIn('status', self::OPEN);
            $state = match (true) {
                $received->contains(fn ($r) => ! $r->partial) => 'delivered',
                $open->contains(fn ($r) => $r->status === 'shipped') => 'in_transit',
                $open->isNotEmpty() => 'scheduled',
                $received->isNotEmpty() => 'partial',
                default => 'unscheduled',
            };
            $out[$id] = [
                'state' => $state,
                'partial_history' => $received->contains(fn ($r) => (bool) $r->partial),
                'shipments' => $mine->map(fn ($r) => ['id' => (int) $r->shipment_id, 'number' => $r->shipment_number, 'status' => $r->status, 'partial' => (bool) $r->partial])->values()->all(),
            ];
        }

        return $out;
    }

    /** "Trip n of m" for shipments of the same work order (cancelled ones don't count). */
    public function tripLabels(Collection $shipments): array
    {
        $woIds = $shipments->pluck('work_order_id')->filter()->unique();
        if ($woIds->isEmpty()) {
            return [];
        }
        $all = Shipment::whereIn('work_order_id', $woIds)->where('status', '!=', 'cancelled')->orderBy('id')->get(['id', 'work_order_id'])->groupBy('work_order_id');
        $labels = [];
        foreach ($all as $group) {
            if ($group->count() < 2) {
                continue;
            }
            foreach ($group->values() as $i => $s) {
                $labels[$s->id] = 'Trip '.($i + 1).' of '.$group->count();
            }
        }

        return $labels;
    }

    /** Work orders a shipment can draw elevations from: its own work order plus those of its jobs. */
    public function candidateWorkOrders(Shipment $shipment): Collection
    {
        $jobIds = $shipment->jobs()->pluck('business_jobs.id');

        return FdWorkOrder::with('businessJob:id,job_number,job_name')
            ->where(fn ($q) => $q->when($shipment->work_order_id, fn ($w) => $w->orWhere('id', $shipment->work_order_id))
                ->orWhereIn('business_job_id', $jobIds))
            ->where('archived', false)->orderBy('business_job_id')->orderBy('release_number')->get();
    }
}
