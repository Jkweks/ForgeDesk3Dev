<?php

namespace App\Services\Configurator;

use App\Models\Product;

/**
 * Central finish-substitution policy for the configurator: when the exact
 * requested finish isn't stocked for a part number, this defines which
 * finish to try next, in priority order.
 *
 * Anodized colors (BL/DB/C2) fall back to each other before mill (0R). Any
 * other finish code (a custom paint spec like E2550, or 0R itself) only
 * ever falls back to 0R — a custom color must never be silently swapped
 * for a stocked anodized color.
 */
class FinishFallbackResolver
{
    private const CHAINS = [
        'BL' => ['BL', 'DB', 'C2', '0R'],
        'DB' => ['DB', 'BL', 'C2', '0R'],
        'C2' => ['C2', 'BL', '0R'],
    ];

    /**
     * @return array<int, string> finish codes to try, in priority order
     */
    public function chainFor(string $finish): array
    {
        $finish = strtoupper($finish);

        return self::CHAINS[$finish] ?? [$finish, '0R'];
    }

    /**
     * Find the best-available Product for a part number given the desired
     * finish, walking the fallback chain. Returns null if the part number
     * doesn't exist in any finish along the chain.
     */
    public function resolve(string $partNumber, string $finish): ?Product
    {
        foreach ($this->chainFor($finish) as $candidate) {
            $product = Product::where('part_number', $partNumber)->where('finish', $candidate)->first();
            if ($product) {
                return $product;
            }
        }

        return null;
    }
}
