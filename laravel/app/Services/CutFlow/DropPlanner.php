<?php

namespace App\Services\CutFlow;

/**
 * Decides what happens to the offcut at the end of a stick for a SKU that
 * has a drop rack: scrap it (shorter than Min Drop), rack it (tagged to the
 * nearest 5" below its real length), or — when it is longer than Max Drop —
 * cut a Min Split piece off the front and re-decide on what's left, so the
 * least material is scrapped while every racked drop stays a usable size.
 *
 * When Min Split or Max Drop is not set (<= 0) the drop is never cut to size:
 * it is racked whole if it is at least Min Drop long, otherwise scrapped.
 */
class DropPlanner
{
    public const TAG_STEP = 5;

    /**
     * @param  float  $leftover  Material left after the last piece AND the kerf that separates it.
     * @return array<int, array{type: string, length: float, tag: float|null, cut_at: float|null}>
     *                                                                                             Segments in physical order. `cut_at` is where the extra saw cut goes, measured
     *                                                                                             from the start of the leftover, for a split piece (null otherwise).
     */
    public function plan(float $leftover, float $minDrop, float $minSplit, float $maxDrop, float $kerf): array
    {
        $segments = [];
        $remaining = round($leftover, 4);

        $canSplit = $minSplit > 0 && $maxDrop > 0;

        while ($canSplit && $remaining > $maxDrop) {
            $split = $minSplit;
            $rest = $remaining - $split - $kerf;

            // Splitting at Min Split would leave an unusable stub — shift the cut so the
            // remainder is exactly Min Drop, as long as the front piece stays rackable.
            if ($rest < $minDrop) {
                $split = $remaining - $kerf - $minDrop;
                $rest = $minDrop;

                if ($split < $minDrop || $split > $maxDrop) {
                    break;
                }
            }

            $segments[] = $this->rack($split, $split);
            $remaining = round($rest, 4);
        }

        $segments[] = $remaining < $minDrop
            ? ['type' => 'scrap', 'length' => round($remaining, 3), 'tag' => null, 'cut_at' => null]
            : $this->rack($remaining, null);

        return $segments;
    }

    /**
     * Tag length: floor to a multiple of 5" — never promise more than is on the rack.
     */
    public static function tagFor(float $length): float
    {
        return floor(round($length, 6) / self::TAG_STEP) * self::TAG_STEP;
    }

    protected function rack(float $length, ?float $cutAt): array
    {
        return [
            'type' => 'rack',
            'length' => round($length, 3),
            'tag' => self::tagFor($length),
            'cut_at' => $cutAt === null ? null : round($cutAt, 3),
        ];
    }
}
