<?php

namespace App\Services\Configurator;

use App\Models\Product;

/**
 * Inventory is stocked as the individual pieces, not the kits, so a BOM line for a kit part
 * number is replaced by its component pieces (quantities multiplied through) before it is
 * saved/reserved. Piece mapping is fab_utils' (HWLIB_GLASS_BLOCK_KITS / expandKits):
 *
 *   P1928A -> 3 x P1929A + 1 x P5919
 *   P1928C -> 3 x P1929B + 1 x P5920
 *
 * Pieces take the kit line's finish (walking the usual finish fallback chain).
 */
class KitExpander
{
    public const KITS = [
        'P1928A' => [['pn' => 'P1929A', 'qty' => 3, 'label' => 'Glass Block Spacer'], ['pn' => 'P5919', 'qty' => 1, 'label' => 'Glass Block Corner']],
        'P1928C' => [['pn' => 'P1929B', 'qty' => 3, 'label' => 'Glass Block Spacer'], ['pn' => 'P5920', 'qty' => 1, 'label' => 'Glass Block Corner']],
    ];

    public function __construct(private FinishFallbackResolver $finishResolver = new FinishFallbackResolver) {}

    /**
     * @param  array<int, array>  $rows  generator rows (part_label/product_id/quantity/...)
     * @param  array<int, string>  $warnings  appended to when a piece has no product
     * @return array<int, array>
     */
    public function expand(array $rows, array &$warnings = []): array
    {
        $productIds = array_filter(array_column($rows, 'product_id'));
        $products = $productIds ? Product::whereIn('id', $productIds)->get(['id', 'part_number', 'finish'])->keyBy('id') : collect();

        $out = [];
        foreach ($rows as $row) {
            $product = $products->get($row['product_id'] ?? null);
            $kit = $product ? (self::KITS[strtoupper($product->part_number)] ?? null) : null;
            if (! $kit) {
                $out[] = $row;

                continue;
            }

            $finish = $product->finish ?: '0R';
            $pieces = [];
            foreach ($kit as $piece) {
                $pieceProduct = $this->finishResolver->resolve($piece['pn'], $finish);
                if (! $pieceProduct) {
                    $pieces = null;
                    $warnings[] = "Kit {$product->part_number} could not be expanded — no product for piece {$piece['pn']}; kept as the kit.";
                    break;
                }
                $pieces[] = [$piece, $pieceProduct];
            }

            if ($pieces === null) {
                $out[] = $row;

                continue;
            }

            foreach ($pieces as [$piece, $pieceProduct]) {
                $out[] = array_merge($row, [
                    'part_label' => "{$piece['label']} (from kit {$product->part_number})",
                    'product_id' => $pieceProduct->id,
                    'calculated_length' => null,
                    'quantity' => round((float) $row['quantity'] * $piece['qty'], 3),
                    'unit_type' => 'qty',
                ]);
            }
        }

        foreach ($out as $i => &$row) {
            $row['sort_order'] = $i;
        }

        return $out;
    }
}
