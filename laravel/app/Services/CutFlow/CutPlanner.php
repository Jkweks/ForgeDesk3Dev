<?php

namespace App\Services\CutFlow;

use App\Models\CutFlow\CutFlowSetting;
use App\Models\CutFlow\Part;
use App\Models\CutFlow\StickItem;
use App\Models\CutFlow\StickSession;
use App\Support\Dimension;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CutPlanner
{
    /**
     * How much material a saw kerf eats between two pieces on the same stick.
     */
    protected float $kerf;

    protected float $standardStockLength;

    protected string $optimizer;

    public function __construct()
    {
        $settings = CutFlowSetting::current();
        $this->kerf = $settings->kerfInches();
        $this->standardStockLength = $settings->standardStockLength();
        $this->optimizer = $settings->selectedOptimizer();
    }

    /**
     * The pool of pieces for one profile (part_id + finish), scoped to a set
     * of selected cut jobs, that aren't yet spoken for by any stick — i.e.
     * qty_remaining minus whatever is already sitting pending on a built
     * stick (whether that stick has been physically cut yet or not). This is
     * what both buildStick() and the whole-job projection draw from, so
     * building a stick immediately shrinks what the projection thinks still
     * needs a fresh length.
     *
     * Scoping to $cutJobIds is what makes "select multiple jobs, optimize
     * across the combined list" work: the same profile split across two
     * selected jobs comes back as one pool.
     *
     * Sorted largest-first so long parts always get first pick of material
     * instead of being crowded out by a small-parts-only fill.
     */
    public function unassignedPieces(string $name, ?string $finish, array $cutJobIds): Collection
    {
        if (empty($cutJobIds)) {
            return collect();
        }

        $parts = Part::where('name', $name)
            ->where('finish', $finish)
            ->whereIn('cut_job_id', $cutJobIds)
            ->where('qty_remaining', '>', 0)
            ->orderByDesc('dimension_inches')
            ->get();

        if ($parts->isEmpty()) {
            return collect();
        }

        $pendingByPart = StickItem::whereIn('part_id', $parts->pluck('id'))
            ->where('status', 'pending')
            ->selectRaw('part_id, count(*) as c')
            ->groupBy('part_id')
            ->pluck('c', 'part_id');

        $pool = collect();

        foreach ($parts as $part) {
            $assigned = (int) ($pendingByPart[$part->id] ?? 0);
            $available = max(0, $part->qty_remaining - $assigned);

            for ($i = 0; $i < $available; $i++) {
                $pool->push($part);
            }
        }

        return $pool->sortByDesc(fn (Part $p) => (float) $p->dimension_inches)->values();
    }

    /**
     * Build a cut plan for a physical stick/drop of the given length,
     * greedily pulling the largest still-unassigned pieces of the given
     * profile (part_id + finish) that fit, across all selected jobs. A
     * stick is one physical piece of raw material, so it can only ever hold
     * pieces of one profile (but that profile can be sourced from parts
     * belonging to any of the selected jobs).
     */
    public function buildStick(string $lengthLabel, string $name, ?string $finish, array $cutJobIds): StickSession
    {
        $lengthInches = Dimension::parse($lengthLabel);

        $pool = $this->unassignedPieces($name, $finish, $cutJobIds);

        $picked = $this->optimizer === CutFlowSetting::OPTIMIZER_NEW
            ? $this->pickWithNewOptimizer($pool, $lengthInches, $this->stockLengthFor($name, $finish))
            : $this->pickGreedy($pool, $lengthInches);

        $remaining = $lengthInches;
        foreach ($picked->values() as $i => $part) {
            $remaining -= (float) $part->dimension_inches + ($i === 0 ? 0 : $this->kerf);
        }

        $dropPlan = $picked->isEmpty() ? null : $this->planDrop($name, $finish, $remaining);

        return DB::connection('cutflow')->transaction(function () use ($lengthLabel, $lengthInches, $remaining, $picked, $name, $finish, $dropPlan) {
            $session = StickSession::create([
                'length_label' => $lengthLabel,
                'part_name' => $name,
                'finish' => $finish,
                'length_inches' => $lengthInches,
                'waste_inches' => round($remaining, 3),
                'drop_plan' => $dropPlan,
                'status' => 'active',
            ]);

            foreach ($picked->values() as $i => $part) {
                StickItem::create([
                    'stick_session_id' => $session->id,
                    'part_id' => $part->id,
                    'dimension_inches' => $part->dimension_inches,
                    'sequence' => $i + 1,
                    'status' => 'pending',
                ]);
            }

            // Oversized drops get cut down on the saw too — after the real pieces, no part attached.
            $sequence = $picked->count();
            foreach ($dropPlan ?? [] as $segment) {
                if ($segment['cut_at'] === null) {
                    continue;
                }

                StickItem::create([
                    'stick_session_id' => $session->id,
                    'part_id' => null,
                    'kind' => 'drop',
                    'dimension_inches' => $segment['length'],
                    'sequence' => ++$sequence,
                    'status' => 'pending',
                ]);
            }

            return $session->fresh(['items.part']);
        });
    }

    /**
     * Old Optimizer: fill this one stick largest-first with every piece that still fits.
     */
    protected function pickGreedy(Collection $pool, float $lengthInches): Collection
    {
        $remaining = $lengthInches;
        $picked = collect();

        foreach ($pool as $part) {
            $need = $picked->isEmpty()
                ? (float) $part->dimension_inches
                : (float) $part->dimension_inches + $this->kerf;

            if ($need <= $remaining) {
                $picked->push($part);
                $remaining -= $need;
            }
        }

        return $picked;
    }

    /**
     * New Optimizer: a stick of the standard length takes the next stick of a plan that packs the
     * whole pile onto as few sticks as possible; any other length (an offcut) is filled as full as
     * possible.
     */
    protected function pickWithNewOptimizer(Collection $pool, float $lengthInches, float $standardLength): Collection
    {
        $packer = new StickPacker;
        $fits = $pool->filter(fn (Part $p) => (float) $p->dimension_inches <= $lengthInches);
        $lengths = $fits->map(fn (Part $p) => (float) $p->dimension_inches)->all();

        if (empty($lengths)) {
            return collect();
        }

        $wanted = abs($lengthInches - $standardLength) < 0.001
            ? ($this->packSticks($lengths, $standardLength)['sticks'][0] ?? [])
            : $packer->bestFill($lengths, $lengthInches, $this->kerf);

        // Map the chosen lengths back onto parts from the pool.
        $available = $fits->values()->all();
        $picked = collect();

        foreach ($wanted as $length) {
            foreach ($available as $k => $part) {
                if (abs((float) $part->dimension_inches - $length) < 0.0005) {
                    $picked->push($part);
                    unset($available[$k]);
                    break;
                }
            }
        }

        return $picked->sortByDesc(fn (Part $p) => (float) $p->dimension_inches)->values();
    }

    /**
     * What to do with the offcut once a stick's pieces are cut, per the SKU's
     * drop-rack rules. Null when the SKU has no drop rack (or isn't a known
     * product), so those sticks keep today's behaviour.
     *
     * @param  float  $remaining  Stock left after the last piece, before the kerf that frees it.
     */
    protected function planDrop(string $name, ?string $finish, float $remaining): ?array
    {
        $product = Part::resolveProductFor($name, $finish);

        if (! $product?->drop_rack_enabled) {
            return null;
        }

        $min = (float) $product->minimum_drop_length;
        $split = (float) $product->drop_min_split;
        $max = (float) $product->drop_max_length;

        if ($min <= 0) {
            return null;
        }

        return (new DropPlanner)->plan($remaining - $this->kerf, $min, $split, $max, $this->kerf);
    }

    /**
     * Stick length used to estimate how many sticks a profile needs: the stock
     * length entered on the product for SKUs with a drop rack, otherwise the
     * standard stock length (288" unless the CutFlow setting overrides it).
     */
    public function stockLengthFor(string $name, ?string $finish): float
    {
        $product = Part::resolveProductFor($name, $finish);
        $length = (float) $product?->configurator_length;

        return $product?->drop_rack_enabled && $length > 0
            ? $length
            : $this->standardStockLength;
    }

    /**
     * Distinct profiles (part_id + finish) that still have unassigned
     * pieces within the selected jobs, i.e. lists an operator could still
     * pick up and work through. Includes lightweight per-profile totals for
     * the collapsed bubble summary (cheap aggregate query, not a full
     * bin-pack per profile).
     */
    public function activeProfiles(array $cutJobIds): Collection
    {
        if (empty($cutJobIds)) {
            return collect();
        }

        return Part::selectRaw('name, finish, sum(qty_remaining) as qty_remaining_total, count(*) as length_count')
            ->whereIn('cut_job_id', $cutJobIds)
            ->where('qty_remaining', '>', 0)
            ->groupBy('name', 'finish')
            ->orderBy('name')
            ->orderBy('finish')
            ->get();
    }

    /**
     * Whole-pile projection for one profile across the selected jobs: given
     * everything still unassigned, how many more standard-length sticks
     * would it take to finish it, packing largest-first (First-Fit-
     * Decreasing) so long parts always claim a stick before it fills up
     * with small parts. Pieces longer than the standard stock length can't
     * be cut from it at all — those are reported separately instead of
     * being silently folded into the stick count.
     */
    public function projectRemainingStandardSticks(string $name, ?string $finish, array $cutJobIds, ?float $standardLength = null): array
    {
        $standardLength ??= $this->stockLengthFor($name, $finish);

        $pool = $this->unassignedPieces($name, $finish, $cutJobIds);

        $overLength = $pool->filter(fn (Part $p) => (float) $p->dimension_inches > $standardLength);
        $fitsStandard = $pool->reject(fn (Part $p) => (float) $p->dimension_inches > $standardLength);

        $lowerBound = null;
        $optimal = null;

        if ($this->optimizer === CutFlowSetting::OPTIMIZER_NEW) {
            $plan = $this->packSticks($fitsStandard->map(fn (Part $p) => (float) $p->dimension_inches)->all(), $standardLength);
            $lowerBound = $plan['lowerBound'];
            $optimal = $plan['optimal'];
            $bins = array_map(fn (array $stick) => [
                'remaining' => $standardLength - array_sum($stick) - $this->kerf * (count($stick) - 1),
                'count' => count($stick),
            ], $plan['sticks']);
        } else {
            $bins = $this->firstFitBins($fitsStandard, $standardLength);
        }

        $overLengthByDimension = $overLength
            ->groupBy(fn (Part $p) => (string) $p->dimension_inches)
            ->map(fn ($group) => [
                'dimension_inches' => (float) $group->first()->dimension_inches,
                'count' => $group->count(),
            ])
            ->values();

        return [
            'standardLength' => $standardLength,
            'stickCount' => count($bins),
            'piecesOnStandardStock' => $fitsStandard->count(),
            'totalWasteInches' => round(array_sum(array_column($bins, 'remaining')), 3),
            'overLength' => $overLengthByDimension,
            'optimizer' => $this->optimizer,
            'lowerBound' => $lowerBound,
            'optimal' => $optimal,
        ];
    }

    /**
     * New Optimizer plan for a pile, cached by its contents: the dashboard re-renders constantly and the
     * search is deterministic, so the same pile at the same stock length and kerf always gives the same plan.
     *
     * @param  array<int, float>  $lengths
     */
    protected function packSticks(array $lengths, float $stockLength): array
    {
        sort($lengths);
        $key = 'cutflow:pack:'.md5(json_encode([$lengths, $stockLength, $this->kerf]));

        return Cache::remember($key, now()->addMinutes(30), fn () => (new StickPacker)->pack($lengths, $stockLength, $this->kerf));
    }

    /**
     * Old Optimizer projection: first-fit-decreasing over the whole pile.
     *
     * @return array<int, array{remaining: float, count: int}>
     */
    protected function firstFitBins(Collection $pieces, float $standardLength): array
    {
        $bins = [];

        foreach ($pieces as $part) {
            $len = (float) $part->dimension_inches;
            $placedInBin = null;

            foreach ($bins as $i => $bin) {
                if ($len + $this->kerf <= $bin['remaining']) {
                    $placedInBin = $i;
                    break;
                }
            }

            if ($placedInBin === null) {
                $bins[] = ['remaining' => $standardLength - $len, 'count' => 1];
            } else {
                $bins[$placedInBin]['remaining'] -= $len + $this->kerf;
                $bins[$placedInBin]['count']++;
            }
        }

        return $bins;
    }
}
