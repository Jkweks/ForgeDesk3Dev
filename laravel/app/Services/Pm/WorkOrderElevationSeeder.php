<?php

namespace App\Services\Pm;

use App\Models\FdElevationType;
use App\Models\FdStageTemplateSet;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\WorkOrderRequest;
use App\Services\Configurator\ElevationConfigurationMatcher;

/**
 * Turns the elevations a work order request released into the Fabrication work order's own elevations. Goes through the
 * shared creation path the elevation editor and the configurator use, so each one gets its stage tier, joint count and
 * stages exactly as a hand-made elevation would. Each request line remembers the elevation it became, so running this
 * again (a back-fill, a retried approval) never makes a second copy.
 */
class WorkOrderElevationSeeder
{
    public function __construct(private ElevationConfigurationMatcher $matcher) {}

    /** @return int how many elevations were created */
    public function seed(WorkOrderRequest $request, FdWorkOrder $workOrder, ?string $dateRequested = null): int
    {
        $request->loadMissing('elevations', 'drawingSet.file');
        $from = $request->drawingSet?->file?->original_name;
        $date = $dateRequested ?? $request->requested_due_date?->toDateString();
        $made = 0;

        foreach ($request->elevations as $line) {
            if ($line->fd_wo_elevation_id && FdWoElevation::whereKey($line->fd_wo_elevation_id)->exists()) {
                continue;
            }
            $typeId = $this->typeFor($line->system);
            $elevation = $this->matcher->createElevationRow($workOrder, [
                'elevation_tag' => mb_substr($line->label, 0, 100),
                'elevation_type_id' => $typeId,
                'template_set_id' => $this->tierFor($typeId, $line->system),      // null = the type's default tier
                'quantity' => $line->quantity,
                'date_requested' => $date,
                'notes' => 'Released'.($from ? " from {$from}" : '').': '.$line->summary(),
            ]);
            $line->update(['fd_wo_elevation_id' => $elevation->id]);
            $made++;
        }
        if ($made) {
            $workOrder->recalcDueDateFromElevations();       // elevation dates drive the work order's due date and ranking
            FdWorkOrder::resequencePriorities();
        }

        return $made;
    }

    /**
     * The stage tier of that type named in the system text: "TUBELITE TU14000 SERIES SF" picks the type's "TU14000" tier, so the
     * elevation arrives with the stages Fabrication actually runs. Every word of the tier's name must be in the text and the
     * tier with the most words wins ("T14000 I/O" is not "TU14000"); none matching leaves it to the type's default tier.
     */
    public function tierFor(?int $typeId, ?string $system): ?int
    {
        if (! $typeId || ! $system) {
            return null;
        }
        $words = collect(preg_split('/[^a-z0-9]+/i', mb_strtolower($system)))->filter()->flip();
        $best = null;
        $bestWords = 0;
        foreach (FdStageTemplateSet::where('elevation_type_id', $typeId)->orderBy('sort_order')->orderBy('id')->get() as $set) {
            $tokens = collect(preg_split('/[^a-z0-9]+/i', mb_strtolower($set->name)))->filter()->values();
            if ($tokens->isNotEmpty() && $tokens->every(fn ($t) => $words->has($t)) && $tokens->count() > $bestWords) {
                $best = $set;
                $bestWords = $tokens->count();
            }
        }

        return $best?->id;
    }

    /** The elevation type named in the drawing's system text ("... SERIES SF (2" x 4 1/2")" is SF), or null when none is. */
    public function typeFor(?string $system): ?int
    {
        $words = collect(preg_split('/[^a-z0-9]+/i', mb_strtolower((string) $system)))->filter()->flip();
        foreach (FdElevationType::where('active', true)->orderBy('sort_order')->orderBy('id')->get() as $type) {
            foreach ($type->matchTerms() as $term) {
                if ($term !== 'other' && $words->has($term)) {
                    return $type->id;
                }
            }
        }

        return null;
    }
}
