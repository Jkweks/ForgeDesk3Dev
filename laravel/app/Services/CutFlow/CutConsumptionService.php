<?php

namespace App\Services\CutFlow;

use App\Models\CutFlow\CutConsumption;
use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\Part;
use App\Models\FdWorkOrder;
use App\Models\InventoryTransaction;
use App\Models\JobReservation;
use App\Models\Product;
use App\Services\Configurator\StickYield;
use App\Services\InventoryDeductor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cutting draws stock down, cut by cut, as a PORTION OF A STOCK LENGTH:
 *
 *      consumed = cut inches / the product's stock length (252", 288", 120"...)
 *
 * — never a percentage of whatever stick happened to be on the saw. A 25" cut uses the same share of a
 * stock length whether it came off a full 252" stick or a 75" drop (drops are remnants of stock).
 * Only SOF work orders draw inventory and move reservations (drawsFromInventory); the ledger is kept for all of them.
 * Saw kerf and unusable offcuts aren't counted; they surface in cycle counts.
 *
 * Each real cut (a planned cut, not a recut marker or a manual keypad entry) is one row in
 * cut_consumptions. From that ledger, never from running increments (so rounding can't drift):
 *
 *  - the job's configurator reservation item for that extrusion = its cut lists' total, to 1/10 stock length
 *    (committed - consumed shrinks, the reservation moves to in-progress; using more than reserved raises
 *    committed to match);
 *  - inventory on hand drops by the product's total to 1/10, less what earlier cuts already took
 *    (InventoryDeductor — same location/transaction ledger as completing a reservation).
 *
 * A re-cut costs material twice, correctly: the first piece was spoiled, and cutting it again is a second cut.
 * Non-extrusion lines (hardware, components) aren't cut at the station; the normal reservation completion
 * consumes them.
 */
class CutConsumptionService
{
    /** Legacy reference on every cut-station deduction; now only the fallback when a cut job has no name. */
    public const REFERENCE = 'CUT-STATION';

    /** Every cut-station ledger row's notes start with this — how earlier deductions are found now that the reference is the job name. */
    public const NOTES_PREFIX = 'Cut station';

    public function __construct(private InventoryDeductor $inventory) {}

    public function recordCut(CutLogEntry $entry): void
    {
        if ($entry->type !== 'planned' || $entry->is_recut || ! $entry->part_id || ! $entry->cut_job_id) {
            return; // manual keypad cuts and recut markers consume nothing
        }
        if (CutConsumption::where('cut_log_entry_id', $entry->id)->exists()) {
            return;
        }

        $product = Part::resolveProductFor($entry->part_name, $entry->finish);
        if (! $product) {
            Log::warning('Cut station: cut matches no product — stock not consumed', ['cut_log_entry_id' => $entry->id, 'part_name' => $entry->part_name, 'finish' => $entry->finish]);

            return;
        }

        $fraction = round((float) $entry->dimension_inches / StickYield::stockLengthFor($product), 4);

        DB::connection('cutflow')->transaction(function () use ($entry, $product, $fraction) {
            CutConsumption::create([
                'cut_log_entry_id' => $entry->id,
                'cut_job_id' => $entry->cut_job_id,
                'product_id' => $product->id,
                'stock_fraction' => $fraction,
            ]);

            DB::transaction(function () use ($entry, $product) {
                if (! self::drawsFromInventory((int) $entry->cut_job_id)) {
                    return; // job-specific material: logged above, nothing to deduct or reserve against
                }
                $this->syncInventory($product, $entry);
                $this->syncReservationItem((int) $entry->cut_job_id, $product->id);
            });
        });
    }

    /**
     * Whether a cut job's cuts come out of inventory: only when its work order is tagged SOF (stock-on-floor
     * material). Anything else — "In Shop", a delivery date, no tag yet, or a cut list with no work order —
     * is job-specific material ordered outside ForgeDesk: its cuts are still logged in cut_consumptions for
     * the material usage report, but never touch inventory or the job's reservation.
     */
    public static function drawsFromInventory(int $cutJobId): bool
    {
        $workOrderId = CutJob::whereKey($cutJobId)->value('work_order_id');
        $delivery = $workOrderId ? FdWorkOrder::whereKey($workOrderId)->value('material_delivery') : null;

        return self::tagDrawsFromInventory($delivery);
    }

    public static function tagDrawsFromInventory(?string $materialDelivery): bool
    {
        return $materialDelivery !== null && strcasecmp(trim($materialDelivery), 'SOF') === 0;
    }

    /** On hand falls to (total consumed, to 1/10) — deducting only what earlier cuts haven't already. */
    private function syncInventory(Product $product, CutLogEntry $entry): void
    {
        $drawingJobIds = CutConsumption::where('product_id', $product->id)->distinct()->pluck('cut_job_id')
            ->filter(fn ($id) => self::drawsFromInventory((int) $id));
        $total = round((float) CutConsumption::where('product_id', $product->id)->whereIn('cut_job_id', $drawingJobIds)->sum('stock_fraction'), 1);
        $already = round(-1 * (float) InventoryTransaction::where('product_id', $product->id)
            ->where('type', 'fulfillment')
            ->where(fn ($q) => $q->where('reference_number', self::REFERENCE)->orWhere('notes', 'like', self::NOTES_PREFIX.'%'))
            ->sum('quantity'), 1);
        $delta = round($total - $already, 1);

        if ($delta > 0) {
            $reference = trim((string) CutJob::find($entry->cut_job_id)?->labelJobName()) ?: self::REFERENCE;
            $operator = trim((string) $entry->operator_name);
            $by = $operator !== '' && $operator !== 'Unknown' ? " ({$operator})" : '';

            $this->inventory->deduct(
                $product,
                $delta,
                $reference,
                self::NOTES_PREFIX."{$by}: {$delta} stock length(s) of {$product->sku} (cut length / stock length)",
            ); // no user_id: the log shows these as fulfilled by CutFlow (InventoryTransaction::user_display_name)
        }
    }

    /** Set the job reservation item's consumed quantity from the ledger (all of the job's cut lists). */
    public function syncReservationItem(int $cutJobId, int $productId): void
    {
        $workOrderId = CutJob::whereKey($cutJobId)->value('work_order_id');
        $businessJobId = $workOrderId ? FdWorkOrder::whereKey($workOrderId)->value('business_job_id') : null;
        if (! $businessJobId) {
            return;
        }

        $reservation = JobReservation::where('business_job_id', $businessJobId)->where('source', 'configurator')->first();
        $item = $reservation?->items()->where('product_id', $productId)->first();
        if (! $item) {
            return; // this extrusion isn't part of the job's reservation (e.g. an uploaded list)
        }

        $jobCutIds = CutJob::whereIn('work_order_id', FdWorkOrder::where('business_job_id', $businessJobId)->pluck('id'))->pluck('id');
        $consumed = round((float) CutConsumption::whereIn('cut_job_id', $jobCutIds)->where('product_id', $productId)->sum('stock_fraction'), 1);

        if ($consumed > (float) $item->committed_qty) {
            Log::info('Cut station over-consumption: committed raised to match', ['product_id' => $productId, 'committed' => $item->committed_qty, 'consumed' => $consumed]);
            $item->committed_qty = $consumed;
            $item->requested_qty = max((float) $item->requested_qty, $consumed);
        }
        $item->consumed_qty = $consumed;
        $item->save();

        if ($reservation->status === 'active') {
            $reservation->status = 'in_progress';
            $reservation->save();
        }
    }
}
