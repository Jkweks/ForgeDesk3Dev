<?php

namespace App\Services\Configurator;

/**
 * Handed hardware (locks, covers) is stocked per hand: P1421 -> P1421L / P1421R. Which suffix goes
 * where depends on the door:
 *
 *  - single door: from its handing (LH and RHR share the left-hand part; RH and LHR the right-hand one);
 *  - pair: each LEAF has its own hand. A link on the "active" leaf is that leaf's hand, "inactive" is
 *    the other, and "both" is one of each (a left AND a right).
 *
 * (fab_utils gave pairs no suffix at all, because its one record covered both leaves; with a sheet per
 * leaf the hand is known.)
 */
class HandedHardware
{
    /** L / R / '' (none) for a single door's handing; pairs and center-pivot carry no single suffix. */
    public static function suffix(string $handing): string
    {
        $h = strtoupper($handing);
        if (str_contains($h, 'PAIR') || str_contains($h, 'CP')) {
            return '';
        }

        return match (true) {
            str_contains($h, 'LHR') => 'R',
            str_contains($h, 'RHR') => 'L',
            str_contains($h, 'LH') => 'L',
            str_contains($h, 'RH') => 'R',
            default => '',
        };
    }

    /** Hand ('L'/'R') of the active leaf of a pair: RHRA -> right, LHRA -> left. */
    public static function activeHand(string $handing): string
    {
        return strtoupper($handing) === 'PAIR-LHRA' ? 'L' : 'R';
    }

    /**
     * The physical pieces one opening needs for a link: [suffix, quantity] per piece (quantity per
     * opening — the caller multiplies by the number of openings).
     *
     * @return array<int, array{0: string, 1: int}>
     */
    public static function variants($link, $item, bool $isPair, string $handing): array
    {
        $q = (int) $link->quantity;

        if (! $item->handed) {
            return [['', ($link->leaf === 'both' && $isPair) ? $q * 2 : $q]];
        }

        if (! $isPair) {
            return [[self::suffix($handing), $q]];
        }

        $active = self::activeHand($handing);
        $other = $active === 'L' ? 'R' : 'L';

        return match ($link->leaf) {
            'active' => [[$active, $q]],
            'inactive' => [[$other, $q]],
            default => [['L', $q], ['R', $q]],
        };
    }
}
