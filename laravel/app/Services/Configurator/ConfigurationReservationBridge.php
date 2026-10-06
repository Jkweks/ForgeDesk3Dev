<?php

namespace App\Services\Configurator;

use App\Models\BusinessJob;
use App\Models\DoorFrameConfiguration;
use App\Models\JobReservation;
use App\Models\JobReservationItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps inventory committed for a job's door/frame configurations — the same JobReservation
 * mechanism every other fulfillment path uses, so a configured opening really reduces
 * quantity_available.
 *
 * ONE reservation per job (JobReservation.source = 'configurator'), summarising every configuration
 * of that job that is reserved, released or in progress. It is recomputed from the whole job each
 * time — never patched per opening — because quantities don't add up per opening:
 *
 *  - Extrusions are committed as fractions of a stick, to the next 1/10 (as the old configurator did,
 *    and as CutFlow consumes them): the job's total cut inches for a part number over the stick length,
 *    rounded up once for the whole job — so 5 openings sharing offcuts don't each round up to a tenth.
 *    The stock-length page still prints whole sticks (StickYield); that is paper, not the reservation.
 *  - Roll/length stock (gasket, weatherstrip) is the job's total inches over the roll length, to the
 *    next 1/10.
 *  - Everything else is the summed quantity.
 *  - Special-order hardware (no product) commits nothing.
 *
 * Reserve-on-rough-sizes -> final measure -> release: the configuration stays editable ("reserved")
 * and every edit re-syncs the job; release locks it and leaves its lines committed until consumed.
 * Consumed quantity is never reduced below what has already been drawn.
 */
class ConfigurationReservationBridge
{
    /** Configuration statuses whose BOM is committed. */
    public const COUNTED_STATUSES = ['reserved', 'released', 'in_progress'];

    /**
     * Never blocks — an opening with nothing generated yet (or only some sections) can still be
     * reserved; incomplete sections come back as warnings. If nothing at all is committed for the
     * job and no reservation exists, this is a no-op (reservation: null).
     *
     * @return array{reservation: ?JobReservation, warnings: string[]}
     */
    public function reserve(DoorFrameConfiguration $config, ?User $requestedBy = null): array
    {
        $config->loadMissing(['businessJob', 'frameConfig.parts', 'doorConfigs.parts', 'hardwareParts']);

        $warnings = [];
        if ($config->includesFrame() && ! $config->frameConfig?->parts->count()) {
            $warnings[] = 'Frame parts have not been generated yet.';
        }
        if ($config->includesDoor() && $config->doorConfigs->flatMap(fn ($dc) => $dc->parts)->isEmpty()) {
            $warnings[] = 'Door parts have not been generated yet.';
        }
        if ($config->hardwareParts->isEmpty()) {
            $warnings[] = 'Hardware parts have not been generated yet.';
        }

        $reservation = $this->syncJob($config->businessJob, $requestedBy, $warnings);

        if (! $reservation) {
            $warnings[] = 'Nothing has been generated yet — no inventory has been committed. The job reservation is created automatically once parts are generated.';
        }

        return ['reservation' => $reservation, 'warnings' => $warnings];
    }

    /**
     * Recompute the job's reservation from all of its counted configurations.
     *
     * @param  string[]  $warnings
     */
    public function syncJob(BusinessJob $job, ?User $requestedBy = null, array &$warnings = []): ?JobReservation
    {
        $configs = DoorFrameConfiguration::with([
            'frameConfig.parts.product', 'doorConfigs.parts.product', 'hardwareParts.product',
        ])->where('business_job_id', $job->id)->whereIn('status', self::COUNTED_STATUSES)->get();

        $desired = $this->desiredQuantities($configs, $warnings);

        return DB::transaction(function () use ($job, $requestedBy, $configs, $desired, &$warnings) {
            $reservation = JobReservation::where('business_job_id', $job->id)->where('source', 'configurator')->first();

            if (! $reservation) {
                if ($desired->isEmpty()) {
                    $this->linkConfigurations($configs, null);

                    return null;
                }
                $reservation = JobReservation::create([
                    'business_job_id' => $job->id,
                    'job_number' => $job->job_number,
                    'job_name' => $job->job_name,
                    'requested_by' => $requestedBy?->name ?? 'Configurator',
                    'requested_by_id' => $requestedBy?->id,
                    'status' => 'active',
                    'source' => 'configurator',
                    'notes' => 'Auto-managed from this job\'s door/frame configurations (reserved + released). Edit the configurations, not this reservation.',
                ]);
            }

            $this->syncItems($reservation, $desired, $warnings);

            // Revive a reservation that was emptied earlier; stand one down when nothing is left to hold.
            $reservation->refresh();
            $holding = $reservation->items()->exists();
            if ($holding && $reservation->status === 'cancelled') {
                $reservation->status = 'active';
                $reservation->save();
            } elseif (! $holding && ! in_array($reservation->status, ['cancelled', 'fulfilled'], true)) {
                $reservation->status = 'cancelled';
                $reservation->save();
            }

            $this->linkConfigurations($configs, $holding ? $reservation : null);

            return $holding ? $reservation : null;
        });
    }

    /**
     * What the job would be short of if this configuration were committed on top of what the job
     * already holds: needed vs (on hand not committed elsewhere + what this job's reservation already
     * holds of that product). Read-only — nothing is reserved.
     *
     * @param  string[]  $warnings
     * @return array<int, array{product_id: int, part_number: string, needed: float, available: float}>
     */
    public function shortages(DoorFrameConfiguration $config, array &$warnings = []): array
    {
        $counted = DoorFrameConfiguration::with(['frameConfig.parts.product', 'doorConfigs.parts.product', 'hardwareParts.product'])
            ->where('business_job_id', $config->business_job_id)
            ->where(fn ($q) => $q->whereIn('status', self::COUNTED_STATUSES)->orWhere('id', $config->id))
            ->get();

        $desired = $this->desiredQuantities($counted, $warnings);
        $held = JobReservation::where('business_job_id', $config->business_job_id)->where('source', 'configurator')->first()
            ?->items()->get()->keyBy('product_id') ?? collect();

        $products = Product::whereIn('id', $desired->keys())->get()->keyBy('id');
        $short = [];
        foreach ($desired as $pid => $needed) {
            $available = (float) $products[$pid]->quantity_available + (float) ($held->get($pid)?->committed_qty ?? 0);
            if ($needed > $available + 0.0001) {
                $short[] = ['product_id' => $pid, 'part_number' => $products[$pid]->sku, 'needed' => $needed, 'available' => max(0, $available)];
            }
        }

        return $short;
    }

    /**
     * @param  Collection<int, DoorFrameConfiguration>  $configs
     * @return Collection<int, float> product_id => quantity to commit
     */
    public function desiredQuantities(Collection $configs, array &$warnings = []): Collection
    {
        $cuts = [];   // product_id => [inches, ...]   (extrusions -> sticks)
        $inches = []; // product_id => total inches    (roll stock)
        $units = [];  // product_id => quantity

        foreach ($configs as $config) {
            $parts = collect()
                ->merge($config->frameConfig?->parts ?? [])
                ->merge($config->doorConfigs->flatMap(fn ($dc) => $dc->parts))
                ->merge($config->hardwareParts)
                ->filter(fn ($p) => $p->product_id && $p->product); // special-order hardware has no product

            foreach ($parts as $part) {
                $pid = $part->product_id;
                $isLength = $part->unit_type === 'length' && (float) $part->calculated_length > 0;

                if ($isLength && in_array($part->source_type, ['extrusion', 'profile'], true)) {
                    for ($i = 0; $i < (int) round((float) $part->quantity); $i++) {
                        $cuts[$pid][] = (float) $part->calculated_length;
                    }
                } elseif ($isLength && $part->product->is_length_based && (float) $part->product->configurator_length > 0) {
                    $inches[$pid] = ($inches[$pid] ?? 0) + (float) $part->calculated_length * (float) $part->quantity;
                } else {
                    if ($isLength) {
                        $note = "{$part->product->part_number} is cut to length but has no roll length set — reserved as one each per line.";
                        if (! in_array($note, $warnings, true)) {
                            $warnings[] = $note;
                        }
                    }
                    $units[$pid] = ($units[$pid] ?? 0) + (float) $part->quantity;
                }
            }
        }

        $products = Product::whereIn('id', array_unique([...array_keys($cuts), ...array_keys($inches), ...array_keys($units)]))->get()->keyBy('id');

        $desired = collect();
        foreach ($cuts as $pid => $lengths) {
            // Fractions of a stick to the next 1/10 — the old configurator's precision, and the same
            // unit CutFlow draws the reservation down in (cut shares of a stick, to 1/10). Offcuts
            // are shared across the whole job's cuts, so the job's total inches over the stick length
            // is rounded up once, not per line. A cut longer than the stick still costs whole sticks.
            $stock = StickYield::stockLengthFor($products[$pid]);
            $total = 0.0;
            foreach ($lengths as $length) {
                $total += $length > $stock ? ceil($length / $stock) * $stock : $length;
            }
            $desired[$pid] = ceil(round($total / $stock * 10, 6)) / 10;
        }
        foreach ($inches as $pid => $total) {
            // 1/10th-of-a-roll granularity, the same unit JobReservationItem::binAwareCommitted() packs in.
            $desired[$pid] = ($desired[$pid] ?? 0) + ceil($total / (float) $products[$pid]->configurator_length * 10) / 10;
        }
        foreach ($units as $pid => $qty) {
            $desired[$pid] = ($desired[$pid] ?? 0) + $qty;
        }

        return $desired->filter(fn ($q) => $q > 0);
    }

    /**
     * Diffs the desired product/quantity map against the reservation's current items, mutating one
     * JobReservationItem at a time (never a bulk delete-then-reinsert) so its saved/deleted model
     * hooks keep Product::quantity_committed/quantity_available correctly in sync — the same
     * per-item CRUD convention JobReservationController uses.
     */
    private function syncItems(JobReservation $reservation, Collection $desired, array &$warnings): void
    {
        $existingByProduct = $reservation->items()->get()->keyBy('product_id');

        foreach ($desired as $productId => $qty) {
            $item = $existingByProduct->get($productId);

            if (! $item) {
                JobReservationItem::create([
                    'reservation_id' => $reservation->id,
                    'product_id' => $productId,
                    'requested_qty' => $qty,
                    'committed_qty' => $qty,
                    'consumed_qty' => 0,
                ]);

                continue;
            }

            $newQty = max((float) $qty, (float) $item->consumed_qty);
            if ($newQty > (float) $qty) {
                $warnings[] = "Could not reduce product #{$productId} below the {$item->consumed_qty} already consumed.";
            }

            if ((float) $item->requested_qty !== $newQty || (float) $item->committed_qty !== $newQty) {
                $item->requested_qty = $newQty;
                $item->committed_qty = $newQty;
                $item->save();
            }
        }

        foreach ($existingByProduct as $productId => $item) {
            if ($desired->has($productId)) {
                continue;
            }

            if ((float) $item->consumed_qty > 0) {
                $warnings[] = "Kept product #{$productId} reserved — already partially consumed.";

                continue;
            }

            $item->delete();
        }
    }

    /** Point every counted configuration at the job reservation (and release any that no longer count). */
    private function linkConfigurations(Collection $counted, ?JobReservation $reservation): void
    {
        $ids = $counted->pluck('id');
        if ($reservation) {
            DoorFrameConfiguration::whereIn('id', $ids)->update(['job_reservation_id' => $reservation->id]);
            DoorFrameConfiguration::where('business_job_id', $reservation->business_job_id)
                ->whereNotIn('id', $ids)->where('job_reservation_id', $reservation->id)->update(['job_reservation_id' => null]);
        } else {
            DoorFrameConfiguration::whereIn('id', $ids)->update(['job_reservation_id' => null]);
        }
    }
}
