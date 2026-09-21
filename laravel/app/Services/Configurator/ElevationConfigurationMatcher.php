<?php

namespace App\Services\Configurator;

use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameConfigurationDoor;
use App\Models\FdElevationType;
use App\Models\FdStageTemplate;
use App\Models\FdStageTemplateSet;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ensures every Door/Frame elevation on a work order has a matching
 * DoorFrameConfiguration (the configurator's job-level record), grouped by
 * opening rather than by raw elevation row — and keeps a real, stored link
 * (FdWoElevation.door_frame_configuration_id) between the two in both
 * directions, rather than only being able to re-derive the relationship by
 * matching tag strings at call time.
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
            $rows = $group['door']->merge($group['frame']);
            $tags = $rows->pluck('elevation_tag')->all();

            $existing = $this->findMatchingConfiguration($workOrder->business_job_id, $tags);
            if ($existing) {
                if ($existing->work_order_id === null) {
                    $existing->update(['work_order_id' => $workOrder->id]);
                }
                // Elevations for one opening often get created one at a time
                // (LH, then RH, then Frame) — each create fires this sync via
                // FdWoElevation::booted(), so the *first* one may auto-create
                // a config from an incomplete group (e.g. door_only, one
                // tag). Widen the existing config to match as siblings show
                // up; never narrows what's already there.
                $this->widenConfigurationToGroup($existing, $group);
                $this->stampConfigurationOnElevations($rows, $existing);
                $skipped++;

                continue;
            }

            $config = $this->createConfiguration($workOrder, $baseTag, $group);
            $this->stampConfigurationOnElevations($rows, $config);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'groups' => array_keys($groups)];
    }

    /**
     * @param  array{door: Collection<int, FdWoElevation>, frame: Collection<int, FdWoElevation>}  $group
     */
    private function widenConfigurationToGroup(DoorFrameConfiguration $config, array $group): void
    {
        if (! $config->canEdit()) {
            return;
        }

        $hasDoor = $group['door']->isNotEmpty();
        $hasFrame = $group['frame']->isNotEmpty();

        $widenedScope = match (true) {
            $config->job_scope === 'door_and_frame' => 'door_and_frame',
            $hasDoor && $hasFrame => 'door_and_frame',
            $hasDoor && $config->job_scope === 'frame_only' => 'door_and_frame',
            $hasFrame && $config->job_scope === 'door_only' => 'door_and_frame',
            default => $config->job_scope,
        };

        if ($widenedScope !== $config->job_scope) {
            $config->update(['job_scope' => $widenedScope]);
        }

        if ($hasDoor) {
            $config->loadMissing('doors');
            $existingTags = $config->doors->pluck('door_tag')->all();
            $newTags = array_diff($group['door']->pluck('elevation_tag')->all(), $existingTags);
            foreach ($newTags as $tag) {
                DoorFrameConfigurationDoor::create(['configuration_id' => $config->id, 'door_tag' => $tag]);
            }
            if (! empty($newTags)) {
                $config->unsetRelation('doors');
                $config->update(['quantity' => $config->doors()->count()]);
            }
        }
    }

    private function stampConfigurationOnElevations(Collection $elevations, DoorFrameConfiguration $config): void
    {
        foreach ($elevations as $elevation) {
            if ($elevation->door_frame_configuration_id !== $config->id) {
                $elevation->update(['door_frame_configuration_id' => $config->id]);
            }
        }
    }

    /**
     * @return array<string, array{door: Collection<int, FdWoElevation>, frame: Collection<int, FdWoElevation>}>
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

            $groups[$baseTag] ??= ['door' => collect(), 'frame' => collect()];

            if ($type === 'Door') {
                $groups[$baseTag]['door']->push($elevation);
            } else {
                $groups[$baseTag]['frame']->push($elevation);
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
     * this configuration's door_tags, and tie the two together if found —
     * including every sibling elevation in that same opening (e.g. both
     * leaves of a pair plus its frame), not just the one that matched.
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

        $elevation = FdWoElevation::with('workOrder', 'elevationType')
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

        // Stamp every sibling elevation in the same opening group too (the
        // match above only guarantees finding one of possibly 2-3 rows).
        $workOrderElevations = FdWoElevation::where('work_order_id', $elevation->work_order_id)
            ->with('elevationType')
            ->whereHas('elevationType', fn ($q) => $q->whereIn('name', ['Door', 'Frame']))
            ->get();
        $groups = $this->groupByOpening($workOrderElevations);
        $matchedBaseTag = $elevation->elevationType->name === 'Door'
            ? $this->stripLeafSuffix($elevation->elevation_tag)
            : $elevation->elevation_tag;
        if (isset($groups[$matchedBaseTag])) {
            $this->stampConfigurationOnElevations(
                $groups[$matchedBaseTag]['door']->merge($groups[$matchedBaseTag]['frame']),
                $config
            );
        }

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

    /**
     * @param  array{door: Collection<int, FdWoElevation>, frame: Collection<int, FdWoElevation>}  $group
     */
    private function createConfiguration(FdWorkOrder $workOrder, string $baseTag, array $group): DoorFrameConfiguration
    {
        $hasDoor = $group['door']->isNotEmpty();
        $hasFrame = $group['frame']->isNotEmpty();

        $jobScope = match (true) {
            $hasDoor && $hasFrame => 'door_and_frame',
            $hasDoor => 'door_only',
            default => 'frame_only',
        };

        // A frame-only opening has no door leaf tag of its own — use the
        // shared base tag as the configuration's sole identifying tag.
        // Quantity is always just how many door tags there are — one set of
        // parts per physical opening, same invariant as manual creation.
        $tags = $hasDoor ? $group['door']->pluck('elevation_tag')->all() : [$baseTag];

        return DB::transaction(function () use ($workOrder, $jobScope, $tags) {
            $config = DoorFrameConfiguration::create([
                'business_job_id' => $workOrder->business_job_id,
                'work_order_id' => $workOrder->id,
                'job_scope' => $jobScope,
                'quantity' => count($tags),
                'status' => 'draft',
                'notes' => "Auto-matched from work order #{$workOrder->id} ({$workOrder->release_token}).",
            ]);

            foreach ($tags as $tag) {
                DoorFrameConfigurationDoor::create([
                    'configuration_id' => $config->id,
                    'door_tag' => $tag,
                ]);
            }

            return $config;
        });
    }

    /**
     * Pulls an already-configured, not-yet-scheduled opening into a work
     * order's Door Schedule: creates its Door/Frame elevation row(s) (same
     * shape createConfiguration()'s callers already produce) and links both
     * sides. Throws if the configuration is already tied to a work order or
     * belongs to a different business job — callers should check first.
     *
     * @return Collection<int, FdWoElevation>
     */
    public function attachConfigurationToWorkOrder(DoorFrameConfiguration $config, FdWorkOrder $workOrder): Collection
    {
        if ($config->work_order_id) {
            throw new \RuntimeException('This configuration is already tied to a work order.');
        }
        if ($config->business_job_id !== $workOrder->business_job_id) {
            throw new \RuntimeException('This configuration belongs to a different job.');
        }

        return DB::transaction(function () use ($config, $workOrder) {
            $created = $this->createElevationRowsFor($config, $workOrder);
            $config->update(['work_order_id' => $workOrder->id]);

            return $created;
        });
    }

    /**
     * Keeps a configuration's linked elevations in sync with its *current*
     * job_scope/door_tags after they've changed post-creation (e.g.
     * single-to-pair, or scope narrowing). Only ever creates rows that are
     * missing — an elevation that no longer fits the new shape is left
     * exactly as-is (it may carry real completed production work) and
     * reported back under "orphaned" for a human to act on.
     *
     * @return array{created: Collection<int, FdWoElevation>, orphaned: Collection<int, FdWoElevation>}
     */
    public function syncElevationsForConfiguration(DoorFrameConfiguration $config): array
    {
        if (! $config->work_order_id) {
            return ['created' => collect(), 'orphaned' => collect()];
        }

        $workOrder = $config->workOrder;
        $config->loadMissing('doors');

        $desiredDoorTags = $config->includesDoor() ? $config->doors->pluck('door_tag')->all() : [];
        $desiredFrameTag = $config->includesFrame()
            ? ($config->doors->first()->door_tag ?? null)
            : null;

        $linked = FdWoElevation::where('door_frame_configuration_id', $config->id)
            ->with('elevationType')
            ->get();
        $linkedDoorTags = $linked->filter(fn ($e) => $e->elevationType?->name === 'Door')->pluck('elevation_tag')->all();
        $linkedFrameTags = $linked->filter(fn ($e) => $e->elevationType?->name === 'Frame')->pluck('elevation_tag')->all();

        $missingDoorTags = array_diff($desiredDoorTags, $linkedDoorTags);
        $needsFrame = $desiredFrameTag !== null && ! in_array($desiredFrameTag, $linkedFrameTags, true);

        $created = collect();

        if (! empty($missingDoorTags) || $needsFrame) {
            $doorTypeId = FdElevationType::where('name', 'Door')->value('id');
            $frameTypeId = FdElevationType::where('name', 'Frame')->value('id');

            DB::transaction(function () use (&$created, $missingDoorTags, $needsFrame, $desiredFrameTag, $doorTypeId, $frameTypeId, $workOrder, $config) {
                foreach ($missingDoorTags as $tag) {
                    if (! $doorTypeId) {
                        continue;
                    }
                    $created->push($this->createElevationRow($workOrder, [
                        'elevation_tag' => $tag,
                        'elevation_type_id' => $doorTypeId,
                        'door_frame_configuration_id' => $config->id,
                    ]));
                }
                if ($needsFrame && $frameTypeId) {
                    $created->push($this->createElevationRow($workOrder, [
                        'elevation_tag' => $desiredFrameTag,
                        'elevation_type_id' => $frameTypeId,
                        'door_frame_configuration_id' => $config->id,
                    ]));
                }
            });
        }

        // Linked rows that no longer fit the current desired shape — never
        // deleted, only reported.
        $orphaned = $linked->filter(function ($e) use ($desiredDoorTags, $desiredFrameTag) {
            if ($e->elevationType?->name === 'Door') {
                return ! in_array($e->elevation_tag, $desiredDoorTags, true);
            }
            if ($e->elevationType?->name === 'Frame') {
                return $e->elevation_tag !== $desiredFrameTag;
            }

            return false;
        })->values();

        return ['created' => $created, 'orphaned' => $orphaned];
    }

    /**
     * @return Collection<int, FdWoElevation>
     */
    private function createElevationRowsFor(DoorFrameConfiguration $config, FdWorkOrder $workOrder): Collection
    {
        $config->loadMissing('doors');
        $rows = collect();

        $doorTypeId = FdElevationType::where('name', 'Door')->value('id');
        $frameTypeId = FdElevationType::where('name', 'Frame')->value('id');

        if ($config->includesDoor() && $doorTypeId) {
            foreach ($config->doors as $door) {
                $rows->push($this->createElevationRow($workOrder, [
                    'elevation_tag' => $door->door_tag,
                    'elevation_type_id' => $doorTypeId,
                    'door_frame_configuration_id' => $config->id,
                ]));
            }
        }

        if ($config->includesFrame() && $frameTypeId) {
            $frameTag = $config->doors->first()->door_tag ?? null;
            if ($frameTag) {
                $rows->push($this->createElevationRow($workOrder, [
                    'elevation_tag' => $frameTag,
                    'elevation_type_id' => $frameTypeId,
                    'door_frame_configuration_id' => $config->id,
                ]));
            }
        }

        return $rows;
    }

    /**
     * The shared elevation-creation path — same template-set resolution,
     * joint_qty defaulting, and stage seeding as ElevationController::store()
     * (which now delegates here), so an elevation created from the
     * configurator side is production-ready exactly like a manually-added
     * one, not a bare row.
     */
    public function createElevationRow(FdWorkOrder $workOrder, array $attributes): FdWoElevation
    {
        $elevationTypeId = $attributes['elevation_type_id'] ?? null;
        $quantity = $attributes['quantity'] ?? 1;

        $setId = $attributes['template_set_id'] ?? null;
        if ($elevationTypeId && ! $setId) {
            $setId = FdStageTemplateSet::where('elevation_type_id', $elevationTypeId)
                ->where('is_default', true)->value('id')
                ?? FdStageTemplateSet::where('elevation_type_id', $elevationTypeId)
                    ->orderBy('sort_order')->value('id');
        }

        $joinQty = array_key_exists('joint_qty', $attributes) && $attributes['joint_qty'] !== null
            ? (int) $attributes['joint_qty']
            : $this->defaultJointQty($elevationTypeId, $quantity);

        return DB::transaction(function () use ($workOrder, $attributes, $elevationTypeId, $setId, $quantity, $joinQty) {
            $elevation = FdWoElevation::create([
                'work_order_id' => $workOrder->id,
                'elevation_type_id' => $elevationTypeId,
                'template_set_id' => $setId,
                'door_frame_configuration_id' => $attributes['door_frame_configuration_id'] ?? null,
                'elevation_tag' => $attributes['elevation_tag'],
                'quantity' => $quantity,
                'joint_qty' => $joinQty,
                'date_requested' => $attributes['date_requested'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'scope' => $attributes['scope'] ?? 'assemble',
            ]);

            if ($setId) {
                $templates = FdStageTemplate::where('template_set_id', $setId)->orderBy('sort_order')->get();
                foreach ($templates as $tpl) {
                    FdWoStage::create([
                        'elevation_id' => $elevation->id,
                        'work_order_id' => null,
                        'template_id' => $tpl->id,
                        'name' => $tpl->name,
                        'description' => $tpl->description,
                        'sort_order' => $tpl->sort_order,
                        'phase' => $tpl->phase,
                        'blocks_next' => $tpl->blocks_next ?? true,
                        'minutes_per_joint' => $tpl->minutes_per_joint,
                        'status' => 'pending',
                        'assigned_to_id' => $tpl->default_user_id,
                    ]);
                }
            }

            return $elevation;
        });
    }

    /** quantity x the elevation type's standard_joint_count (e.g. 6 per door, 3 per frame), or null when the type has no standard set. */
    private function defaultJointQty(?int $elevationTypeId, int $quantity): ?int
    {
        if (! $elevationTypeId) {
            return null;
        }

        $standard = FdElevationType::find($elevationTypeId)?->standard_joint_count;

        return $standard !== null ? $quantity * $standard : null;
    }
}
