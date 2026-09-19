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
 * Idempotent and side-effect-free on existing data: an opening already
 * covered by a configuration (matched by any of its door_tags on the same
 * business job) is left alone, never duplicated or overwritten.
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

            if ($this->alreadyMatched($workOrder->business_job_id, $tags)) {
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

    private function alreadyMatched(int $businessJobId, array $tags): bool
    {
        return DoorFrameConfigurationDoor::whereIn('door_tag', $tags)
            ->whereHas('configuration', fn ($q) => $q->where('business_job_id', $businessJobId))
            ->exists();
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
