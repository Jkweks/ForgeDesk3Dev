<?php

namespace App\Services\Configurator;

use App\Models\DoorFrameConfiguration;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Material cost of an opening: the same stock quantities the job reservation would commit
 * (ConfigurationReservationBridge::desiredQuantities — extrusions in tenths of a stick, roll stock
 * in tenths of a roll, everything else summed) priced at each product's net price (products.net_cost,
 * the "Net Price" in item information). The price is per stock unit — stick, roll or each — except pack products, whose net price is per pack
 * and is divided by pack_size because their BOM quantities are in eaches.
 *
 * Products with no net price are priced at 0 and reported in `missing_price` so the total is never
 * silently presented as complete. Special-order hardware (no product) has no price source and is
 * counted in `unpriced_special_order`.
 */
class ConfigurationCostEstimator
{
    public function __construct(private ConfigurationReservationBridge $bridge) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: float, missing_price: int, unpriced_special_order: int, warnings: string[]}
     */
    public function estimate(DoorFrameConfiguration $config): array
    {
        $config->load(['frameConfig.parts.product', 'doorConfigs.parts.product', 'hardwareParts.product']);

        $warnings = [];
        $desired = $this->bridge->desiredQuantities(new Collection([$config]), $warnings);
        $products = Product::whereIn('id', $desired->keys())->get()->keyBy('id');

        $total = 0.0;
        $missing = 0;
        $rows = $desired->map(function ($qty, $pid) use ($products, &$total, &$missing) {
            $p = $products[$pid];
            // Net price is per pack for pack products (pack_size > 1) while BOM quantities are in eaches,
            // so price each piece at net / pack_size. Other products are priced per stock unit.
            $packSize = $p->hasPackSize() ? (int) $p->pack_size : 1;
            $packNet = $p->net_cost !== null ? (float) $p->net_cost : null;
            $net = $p->netUnitPrice();
            $ext = $net !== null ? round((float) $qty * $net, 2) : null;
            if ($net === null || $net <= 0) {
                $missing++;
            }
            $total += $ext ?? 0;

            return [
                'product_id' => $pid,
                'part_number' => $p->part_number ?: $p->sku,
                'description' => $p->description,
                'finish' => $p->finish,
                'uom' => $p->unit_of_measure,
                'quantity' => round((float) $qty, 2),
                'net_price' => $net !== null ? round($net, 4) : null,
                'pack_size' => $packSize,
                'pack_net_price' => $packSize > 1 ? $packNet : null,
                'extended' => $ext,
            ];
        })->sortBy('part_number')->values()->all();

        return [
            'rows' => $rows,
            'total' => round($total, 2),
            'missing_price' => $missing,
            'unpriced_special_order' => $config->hardwareParts->whereNull('product_id')->count(),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}
