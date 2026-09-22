<?php

namespace App\Services\CutFlow;

use App\Models\CutFlow\CutFlowSetting;
use App\Models\CutFlow\Part;
use App\Models\CutFlow\StickItem;
use App\Models\CutFlow\StickSession;
use App\Support\Dimension;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CutPlanner
{
    /**
     * How much material a saw kerf eats between two pieces on the same stick.
     */
    protected float $kerf;

    protected float $standardStockLength;

    public function __construct()
    {
        $settings = CutFlowSetting::current();
        $this->kerf = $settings->kerfInches();
        $this->standardStockLength = $settings->standardStockLength();
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

        return DB::connection('cutflow')->transaction(function () use ($lengthLabel, $lengthInches, $remaining, $picked, $name, $finish) {
            $session = StickSession::create([
                'length_label' => $lengthLabel,
                'part_name' => $name,
                'finish' => $finish,
                'length_inches' => $lengthInches,
                'waste_inches' => round($remaining, 3),
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

            return $session->fresh(['items.part']);
        });
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
        $standardLength ??= $this->standardStockLength;

        $pool = $this->unassignedPieces($name, $finish, $cutJobIds);

        $overLength = $pool->filter(fn (Part $p) => (float) $p->dimension_inches > $standardLength);
        $fitsStandard = $pool->reject(fn (Part $p) => (float) $p->dimension_inches > $standardLength);

        $bins = [];

        foreach ($fitsStandard as $part) {
            $len = (float) $part->dimension_inches;
            $placedInBin = null;

            foreach ($bins as $i => $bin) {
                $need = $bin['count'] === 0 ? $len : $len + $this->kerf;

                if ($need <= $bin['remaining']) {
                    $placedInBin = $i;
                    break;
                }
            }

            if ($placedInBin === null) {
                $bins[] = ['remaining' => $standardLength - $len, 'count' => 1];
            } else {
                $need = $bins[$placedInBin]['count'] === 0 ? $len : $len + $this->kerf;
                $bins[$placedInBin]['remaining'] -= $need;
                $bins[$placedInBin]['count']++;
            }
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
        ];
    }
}
