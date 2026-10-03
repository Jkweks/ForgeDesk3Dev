<?php

namespace App\Services\Configurator;

use App\Models\DoorFrameConfiguration;
use Illuminate\Support\Collection;

/**
 * Builds the 4" x 2" door-leaf checklist label *data* — renderer-agnostic on purpose. Today the
 * payload is laid out on label sheets by the /config/labels page; later the same payload can be
 * turned into ZPL and sent to the Zebra via tiger-bridge without touching this logic.
 *
 * Port of fab_utils' door-labels.html "Leaf Checklist": one label per (physical door, leaf) —
 * a pair prints an LH and an RH leaf, a single prints one — carrying job/WO, the door tag, the
 * hand, a physical-verification checklist and an initials line.
 *
 * Hand is shown as fill (LH/LHR = solid) vs outline (RH/RHR), not colour, so it survives
 * black-and-white sheets and thermal labels alike.
 */
class DoorLabelService
{
    /** Verification items printed on every leaf's label. */
    public const CHECKLIST_ITEMS = ['Glass Stops', 'Set Blocks', 'Hinge Screws', 'Glass Jack'];

    /** Hardware categories that add their own checklist item when linked to the opening. */
    public const CHECKLIST_HARDWARE_CATEGORIES = ['Strike', 'EPT', 'Overhead Stop'];

    /**
     * @param  Collection<int, DoorFrameConfiguration>  $configs
     * @return array<int, array>
     */
    public function build(Collection $configs): array
    {
        $configs->loadMissing([
            'businessJob', 'workOrder', 'doors', 'openingSpecs',
            'doorConfigs.parts', 'hardwareLinks.item.category',
        ]);

        $entries = [];
        foreach ($configs as $config) {
            if (! $config->includesDoor() || ! $config->doorConfigs->count()) {
                continue;
            }

            // One set per physical door (a config's qty is its number of door tags).
            $tags = $config->doors->pluck('door_tag')->filter()->values();
            if ($tags->isEmpty()) {
                $tags = collect(["Config #{$config->id}"]);
            }

            foreach ($tags as $tag) {
                $entries[] = ['config' => $config, 'tag' => (string) $tag];
            }
        }

        usort($entries, fn ($a, $b) => $this->compare($a, $b));

        $labels = [];
        foreach ($entries as ['config' => $config, 'tag' => $tag]) {
            $this->leafLabels($labels, $config, $tag);
        }

        return $labels;
    }

    private function leafLabels(array &$labels, DoorFrameConfiguration $config, string $tag): void
    {
        $doorConfig = $config->doorConfigs->first();
        $hasMid = $doorConfig->parts->contains(fn ($p) => strtoupper((string) $p->part_label) === 'MIDRAIL');
        $linkedCategories = $config->hardwareLinks->map(fn ($l) => $l->item?->category?->name)->filter()->all();
        $hardware = array_values(array_filter(
            self::CHECKLIST_HARDWARE_CATEGORIES,
            fn ($c) => in_array($c, $linkedCategories, true)
        ));
        $items = [...($hasMid ? ['Midrail'] : []), ...self::CHECKLIST_ITEMS, ...$hardware];

        foreach ($this->handInfo($config->openingSpecs?->deriveDoorHanding()) as $hand) {
            $labels[] = [
                'job' => $this->jobLine($config),
                'door' => $tag,
                'hand' => $hand['hand'],
                'side' => $hand['side'],
                'pair' => $hand['pair'],
                'inswing' => $hand['inswing'],
                'items' => $items,
            ];
        }
    }

    /**
     * One entry per leaf. A pair prints an LH and an RH leaf (which leaf is active doesn't change
     * which is which); a single prints its one hand; center-pivot singles have no hinge side.
     *
     * @return array<int, array{hand: ?string, side: ?string, inswing: bool, pair: bool}>
     */
    public function handInfo(?string $handing): array
    {
        return match ($handing) {
            'PAIR-RHRA', 'PAIR-LHRA', 'PAIR', 'CP PAIR' => [
                ['hand' => 'LH', 'side' => 'L', 'inswing' => false, 'pair' => true],
                ['hand' => 'RH', 'side' => 'R', 'inswing' => false, 'pair' => true],
            ],
            'LH (INSWING)' => [['hand' => 'LH', 'side' => 'L', 'inswing' => true, 'pair' => false]],
            'LHR' => [['hand' => 'LHR', 'side' => 'L', 'inswing' => false, 'pair' => false]],
            'RH (INSWING)' => [['hand' => 'RH', 'side' => 'R', 'inswing' => true, 'pair' => false]],
            'RHR' => [['hand' => 'RHR', 'side' => 'R', 'inswing' => false, 'pair' => false]],
            default => [['hand' => null, 'side' => null, 'inswing' => false, 'pair' => false]],
        };
    }

    private function jobLine(DoorFrameConfiguration $config): string
    {
        $job = $config->businessJob;
        $name = $job?->job_name ?: $job?->job_number;

        return trim(implode(' · ', array_filter([
            $name,
            $config->workOrder ? 'WO#'.$config->workOrder->release_token : null,
        ])));
    }

    /** Group a run physically: job, then work order, then door tag (natural order: 9 < 10). */
    private function compare(array $a, array $b): int
    {
        $key = fn ($e) => [
            (string) ($e['config']->businessJob?->job_name ?? ''),
            (string) ($e['config']->workOrder?->release_token ?? ''),
            $e['tag'],
        ];
        [$ka, $kb] = [$key($a), $key($b)];
        foreach ([0, 1, 2] as $i) {
            $c = strnatcasecmp($ka[$i], $kb[$i]);
            if ($c !== 0) {
                return $c;
            }
        }

        return 0;
    }
}
