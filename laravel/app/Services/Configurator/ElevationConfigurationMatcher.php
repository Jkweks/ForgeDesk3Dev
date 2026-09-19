<?php

namespace App\Services\Configurator;

use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameConfigurationDoor;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * Ensures every Door/Frame elevation on a work order has a matching
 * DoorFrameConfiguration (the configurator's job-level record), grouped by
 * opening rather than by raw elevation row.
 *
 * Real work-order data shows pair openings recorded as two Door elevations
 * with "-LH"/"-RH" suffixes against a single unsuffixed Frame elevation
 * (e.g. Door "13B-LH" + Door "13B-RH" + Frame "13B" — all one opening). This
 * strips that suffix to group elevations by their shared base tag before
 * matching, since the configurator's job_scope (door_and_frame/frame_only/
 * door_only) and multi-tag door_tags list already model exactly this.
 *
 * Idempotent on existing data: an opening already covered by a configuration
 * (matched by any of its door_tags on the same business job) is never
 * duplicated. A pre-existing configuration for a job that was configured
 * ahead of production scheduling — before any work order existed — gets its
 * work_order_id backfilled the first time a matching elevation shows up,
 * rather than being left permanently unlinked.
 */
class ElevationConfigurationMatcher
{
    /**
     * @return array{created: int, skipped: int, groups: array}
     */
    public function syncWorkOrder(FdWorkOrder $workOrder): array
    {
        $elevations = FdWoElevation::where('work_order_id', $workOrder->id)
            ->with('elevationType')
            ->whereHas('elevationType', fn ($q) => $q->whereIn('name', ['Door', 'Frame']))
            ->get();

        $groups = $this->groupByOpening($elevations);

        $created = 0;
        $skipped = 0;

        foreach ($groups as $baseTag => $group) {
            $tags = array_merge($group['door'], $group['frame']);

            $existing = $this->findMatchingConfiguration($workOrder->business_job_id, $tags);
            if ($existing) {
                if ($existing->work_order_id === null) {
                    $existing->update(['work_order_id' => $workOrder->id]);
                }
                $skipped++;

                continue;
            }

            $this->createConfiguration($workOrder, $baseTag, $group);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'groups' => array_keys($groups)];
    }

    /**
     * @return array<string, array{door: array<int, string>, frame: array<int, string>, quantity: int}>
     */
    private function groupByOpening($elevations): array
    {
        $groups = [];

        foreach ($elevations as $elevation) {
            $type = $elevation->elevationType->name;
            $tag = trim((string) $elevation->elevation_tag);
            if ($tag === '') {
                continue;
            }

            $baseTag = $type === 'Door' ? $this->stripLeafSuffix($tag) : $tag;

            $groups[$baseTag] ??= ['door' => [], 'frame' => [], 'quantity' => 1];
            $groups[$baseTag]['quantity'] = max($groups[$baseTag]['quantity'], (int) ($elevation->quantity ?? 1));

            if ($type === 'Door') {
                $groups[$baseTag]['door'][] = $tag;
            } else {
                $groups[$baseTag]['frame'][] = $tag;
            }
        }

        return $groups;
    }

    /**
     * Strips a trailing "-LH"/"-RH"/"-L"/"-R" leaf suffix so a pair's two
     * door elevations resolve to the same base opening tag as their frame.
     */
    private function stripLeafSuffix(string $tag): string
    {
        return preg_replace('/-(LH|RH|L|R)$/i', '', $tag);
    }

    /**
     * The reverse direction: given a configuration (possibly pre-made for a
     * job well before any work order existed), search every Door/Frame
     * elevation on that same business job for one whose opening tag matches
     * this configuration's door_tags, and tie the two together if found.
     * Used at release time so a long-since-configured opening picks up its
     * work order as soon as one shows up, without waiting on the elevation
     * side to trigger the match first.
     */
    public function linkConfigurationToWorkOrder(DoorFrameConfiguration $config): ?FdWorkOrder
    {
        if ($config->work_order_id) {
            return $config->workOrder;
        }

        $tags = $config->doors->pluck('door_tag')->all();
        if (empty($tags)) {
            return null;
        }

        $baseTags = array_map(fn ($t) => $this->stripLeafSuffix($t), $tags);

        $elevation = FdWoElevation::with('workOrder')
            ->whereHas('workOrder', fn ($q) => $q->where('business_job_id', $config->business_job_id))
            ->whereHas('elevationType', fn ($q) => $q->whereIn('name', ['Door', 'Frame']))
            ->where(function ($q) use ($tags, $baseTags) {
                $q->whereIn('elevation_tag', $tags)->orWhereIn('elevation_tag', $baseTags);
            })
            ->first();

        if (! $elevation) {
            return null;
        }

        $config->update(['work_order_id' => $elevation->work_order_id]);

        return $elevation->workOrder;
    }

    public function findMatchingConfiguration(int $businessJobId, array $tags): ?DoorFrameConfiguration
    {
        $door = DoorFrameConfigurationDoor::whereIn('door_tag', $tags)
            ->whereHas('configuration', fn ($q) => $q->where('business_job_id', $businessJobId))
            ->with('configuration')
            ->first();

        return $door?->configuration;
    }

    private function createConfiguration(FdWorkOrder $workOrder, string $baseTag, array $group): void
    {
        $hasDoor = ! empty($group['door']);
        $hasFrame = ! empty($group['frame']);

        $jobScope = match (true) {
            $hasDoor && $hasFrame => 'door_and_frame',
            $hasDoor => 'door_only',
            default => 'frame_only',
        };

        // A frame-only opening has no door leaf tag of its own — use the
        // shared base tag as the configuration's sole identifying tag.
        $tags = $hasDoor ? $group['door'] : [$baseTag];

        DB::transaction(function () use ($workOrder, $jobScope, $group, $tags) {
            $config = DoorFrameConfiguration::create([
                'business_job_id' => $workOrder->business_job_id,
                'work_order_id' => $workOrder->id,
                'job_scope' => $jobScope,
                'quantity' => $group['quantity'],
                'status' => 'draft',
                'notes' => "Auto-matched from work order #{$workOrder->id} ({$workOrder->release_token}).",
            ]);

            foreach ($tags as $tag) {
                DoorFrameConfigurationDoor::create([
                    'configuration_id' => $config->id,
                    'door_tag' => $tag,
                ]);
            }
        });
    }
}
