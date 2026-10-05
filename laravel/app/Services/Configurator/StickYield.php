<?php

namespace App\Services\Configurator;

use App\Models\CutFlow\CutFlowSetting;
use App\Models\Product;
use App\Services\CutFlow\StickPacker;

/**
 * How many whole sticks of extrusion a set of cuts needs — the number that goes on the stock-length
 * page and into the job's reservation, so paper and inventory always agree.
 *
 * Old Optimizer: same method as fab_utils' stock list — first-fit-decreasing bin packing per part
 * number. New Optimizer: StickPacker's fewest-sticks search. Which one runs follows the CutFlow
 * "Optimizer" setting, so the stock-length page, the job reservation and the cut station all agree.
 * Either way a cut longer than the stick still costs one stick.
 */
class StickYield
{
    public const DEFAULT_STOCK_LENGTH = 252;

    /** Door extrusions without a stored stick length: base part number => inches. */
    public const STOCK_LENGTH_BY_BASE_PN = ['E6169' => 120];

    /** Stick length in inches: the product's own, else the shop default for that part number. */
    public static function stockLength(?string $partNumber, ?float $stored = null): float
    {
        if ($stored && $stored > 0) {
            return $stored;
        }
        $base = preg_replace('/-(BL|C2|DB|0R)$/i', '', (string) $partNumber);

        return (float) (self::STOCK_LENGTH_BY_BASE_PN[$base] ?? self::DEFAULT_STOCK_LENGTH);
    }

    public static function stockLengthFor(Product $product): float
    {
        return self::stockLength($product->part_number, $product->configurator_length ? (float) $product->configurator_length : null);
    }

    /**
     * @param  array<int, float>  $cuts  one entry per piece (inches)
     * @param  bool|null  $optimized  force the New Optimizer on/off; null follows the CutFlow setting
     */
    public static function sticks(array $cuts, float $stockLength, ?bool $optimized = null): int
    {
        if ($optimized ?? self::usesNewOptimizer()) {
            $tooLong = count(array_filter($cuts, fn ($cut) => $cut > $stockLength));
            $fits = array_values(array_filter($cuts, fn ($cut) => $cut <= $stockLength));

            return $tooLong + (new StickPacker)->pack($fits, $stockLength, 0.0)['count'];
        }

        return self::firstFitSticks($cuts, $stockLength);
    }

    /** The CutFlow Optimizer setting; any trouble reading it (no CutFlow DB, e.g. in tests) means Old. */
    private static function usesNewOptimizer(): bool
    {
        try {
            return CutFlowSetting::query()->first()?->selectedOptimizer() === CutFlowSetting::OPTIMIZER_NEW;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<int, float>  $cuts
     */
    private static function firstFitSticks(array $cuts, float $stockLength): int
    {
        rsort($cuts);
        $bins = [];
        foreach ($cuts as $cut) {
            if ($cut > $stockLength) {
                $bins[] = 0.0;

                continue;
            }
            $placed = false;
            foreach ($bins as $i => $remaining) {
                if ($remaining >= $cut) {
                    $bins[$i] -= $cut;
                    $placed = true;
                    break;
                }
            }
            if (! $placed) {
                $bins[] = $stockLength - $cut;
            }
        }

        return count($bins);
    }
}
