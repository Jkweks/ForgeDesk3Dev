<?php

namespace App\Services\Configurator;

use App\Models\ConfiguratorDoorType;
use App\Models\ConfiguratorGlassSpec;
use App\Models\ConfiguratorMidLug;
use App\Models\ConfiguratorRail;
use App\Models\ConfiguratorRailLug;
use App\Models\ConfiguratorSettingBlockKit;
use App\Models\ConfiguratorTieRod;
use App\Models\DoorFrameConfiguration;
use App\Models\Product;
use RuntimeException;

/**
 * Builds the door parts BOM (extrusions + hardware components) for a
 * door/frame configuration's door leaf(ves).
 *
 * This is a direct port of fab_utils' door calculator (configurator/
 * door-calculator.html `calculate()`) — a bespoke procedural algorithm, not a
 * generic formula DSL like the frame catalog. Every branch here mirrors a
 * specific line in that JS function; see the source for the original if this
 * ever needs re-verifying against fab_utils.
 */
class DoorBomGenerator
{
    /** @var array<int, string> PNs that couldn't be matched to a Product, so their BOM row was skipped. */
    private array $warnings = [];

    /**
     * @return array{rows: array<int, array>, warnings: array<int, string>}
     */
    public function generate(DoorFrameConfiguration $config): array
    {
        $this->warnings = [];

        $doorConfig = $config->doorConfigs->first();
        $openingSpecs = $config->openingSpecs;

        if (! $doorConfig) {
            throw new RuntimeException('Door configuration is required before generating parts.');
        }
        if (! $openingSpecs) {
            throw new RuntimeException('Opening specifications are required before generating parts.');
        }

        $qty = max(1, (int) $config->quantity);
        $series = $doorConfig->door_series;
        $stile = $doorConfig->stile_width;
        $width = (float) $openingSpecs->door_opening_width;
        $height = (float) $openingSpecs->door_opening_height;
        $handing = $doorConfig->handing;
        $hingeType = $doorConfig->hinge_type;
        $glassThk = $doorConfig->glazing;
        $botGap = (float) $doorConfig->bottom_gap;
        $finish = strtoupper($openingSpecs->finish ?? '');
        $midQty = (int) $doorConfig->mid_qty;
        $midLoc1 = (float) ($doorConfig->mid_loc1 ?? 0);
        $midLoc2 = (float) ($doorConfig->mid_loc2 ?? 0);

        $doorType = ConfiguratorDoorType::where('series', $series)->where('stile_name', $stile)->first();
        if (! $doorType) {
            throw new RuntimeException("No door type catalog entry for {$series} / {$stile}.");
        }

        $pair = $this->isPair($handing);
        $centerPivot = $hingeType === 'CENTER PIVOTS';
        $stileHeight = (float) $doorType->stile_height;

        $topRail = ConfiguratorRail::where('rail_type', 'top')->where('label', $doorConfig->top_rail_label)->first();
        $botRail = ConfiguratorRail::where('rail_type', 'bot')->where('label', $doorConfig->bot_rail_label)->first();
        $midRail = $doorConfig->mid_rail_label
            ? ConfiguratorRail::where('rail_type', 'mid')->where('label', $doorConfig->mid_rail_label)->first()
            : null;

        $topRailPn = $topRail?->pnForSeries($series);
        $botRailPn = $botRail?->pnForSeries($series);
        $midRailPn = $midRail?->pnForSeries($series);

        $topRailVal = $topRail ? (float) $topRail->value_in : 0.0;
        $botRailVal = $botRail ? (float) $botRail->value_in : 0.0;
        $midRailVal = $midRail ? (float) $midRail->value_in : 0.0;

        $botRailStacked = $botRail?->isStacked() ?? false;
        $midRailStacked = $midRail?->isStacked() ?? false;

        // ---- Extrusion lengths ----
        $deduct = $centerPivot ? ($stileHeight * 2 + 0.125 + 0.125) : ($stileHeight * 2 + 0.125 + 0.0625);
        $railLen = $pair ? (($width / 2) - $deduct) : ($width - $deduct);
        $stileLen = $height - $botGap - 0.125;

        $topQty = $pair ? $qty * 2 : $qty;
        $botQty = $pair ? $qty * 2 : $qty;

        $midExtQty = 0;
        if ($midQty > 0) {
            $midExtQty = $midRailStacked
                ? ($pair ? ($midQty * $qty * 2) * 2 : ($midQty * $qty) * 2)
                : ($pair ? $midQty * $qty * 2 : $midQty * $qty);
        }

        $hingeStileQty = $pair ? $qty * 2 : $qty;
        $lockStileQty = $pair ? 0 : $qty;
        $astragalStileQty = $pair ? $qty : 0;
        $inactiveStileQty = $pair ? $qty : 0;
        $astragalQty = $pair ? $qty : 0;

        $stackedBotPn = null;
        $stackedBotQty = 0;
        if ($botRailStacked) {
            $stackedBotQty = $pair ? $qty * 2 : $qty;
            $stackedBotPn = $botRail->stackedPnForSeries($series);
        }

        $hingeStilePn = match (true) {
            in_array($hingeType, ['BUTT HINGES', 'OFFSET PIVOTS'], true) => $doorType->bev_pn,
            $hingeType === 'CONTINUOUS HINGE' => $doorType->rab_pn,
            default => $doorType->cp_pn ?: $doorType->bev_pn,
        };
        $lockStilePn = $centerPivot ? ($doorType->cp_pn ?: $doorType->bev_pn) : $doorType->bev_pn;
        $astragalStilePn = $doorType->ast_pn;
        $inactiveStilePn = $doorType->inact_pn;
        $astragalPn = $pair ? 'E1152' : null;

        $glassData = $glassThk ? ConfiguratorGlassSpec::where('thickness', $glassThk)->first() : null;
        $glassStopPn = $glassData->stop_pn ?? null;
        $stopHeight = $glassData ? (float) $glassData->stop_height : 0.0;

        $vStop1Len = $vStop2Len = $vStop3Len = null;
        if ($glassData) {
            if ($midQty === 0) {
                $vStop1Len = $height - 0.125 - $botGap - $topRailVal - $botRailVal - 0.03125;
            } elseif ($midQty >= 1) {
                $vStop1Len = $midLoc1 - $botGap - ($midRailVal / 2) - $botRailVal - 0.03125;
            }
            if ($midQty === 1) {
                $vStop2Len = $height - 0.125 - $topRailVal - $midLoc1 - ($midRailVal / 2) - 0.03125;
            } elseif ($midQty === 2) {
                $vStop2Len = $midLoc2 - ($midRailVal / 2) - $midLoc1 - ($midRailVal / 2) - 0.03125;
            }
            if ($midQty === 2) {
                $vStop3Len = $height - $topRailVal - $midLoc2 - ($midRailVal / 2) - 0.03125;
            }
        }
        $vStop1Qty = $pair ? 4 * $qty * 2 : 4 * $qty;
        $vStop2Qty = $midQty > 0 ? ($pair ? 4 * $qty * 2 : 4 * $qty) : 0;
        $vStop3Qty = $midQty > 1 ? ($pair ? 4 * $qty * 2 : 4 * $qty) : 0;

        $hStopQtyBase = $midQty === 0
            ? ($pair ? $qty * 4 * 2 : $qty * 4)
            : ($pair ? ($midQty + $qty) * 4 * 2 : ($midQty + $qty) * 4);
        $hStopLen = null;
        if ($glassData) {
            $hStopLen = $railLen - ($stopHeight * 2) - ($stopHeight == 0.5 ? 0.03125 : 0);
        }

        // ---- Hardware components ----
        $topLugPn = $topRailPn ? ConfiguratorRailLug::where('rail_pn', $topRailPn)->value('lug_pn') : null;
        $botLugPn = $botRailPn ? ConfiguratorRailLug::where('rail_pn', $botRailPn)->value('lug_pn') : null;
        $topLugQty = $pair ? $qty * 4 : $qty * 2;
        $botLugQty = $pair ? $qty * 4 : $qty * 2;

        $midLugPn = null;
        $midLugQty = 0;
        $midF1Pn = null;
        $midF1Qty = 0.0;
        $midF2Pn = null;
        $midF2Qty = 0.0;
        if ($midQty > 0 && $midRailPn && $ml = ConfiguratorMidLug::where('rail_pn', $midRailPn)->first()) {
            $midLugPn = $ml->lug_pn;
            $midLugQty = $pair ? $qty * $midQty * 4 : $qty * $midQty * 2;
            $midF1Pn = $ml->f1_pn;
            $midF1Qty = (float) $ml->f1_qty * $midLugQty;
            $midF2Pn = $ml->f2_pn;
            $midF2Qty = (float) $ml->f2_qty * $midLugQty;
        }

        $stackedBotLugPn = null;
        $stackedBotLugQty = 0;
        $stackedF1Pn = null;
        $stackedF1Qty = 0.0;
        $stackedF2Pn = null;
        $stackedF2Qty = 0.0;
        if ($botRailStacked && $stackedBotPn && $sl = ConfiguratorMidLug::where('rail_pn', $stackedBotPn)->first()) {
            $stackedBotLugPn = $sl->lug_pn;
            $stackedBotLugQty = $pair ? $qty * 2 : $qty;
            $stackedF1Pn = $sl->f1_pn;
            $stackedF1Qty = (float) $sl->f1_qty * $stackedBotLugQty;
            $stackedF2Pn = $sl->f2_pn;
            $stackedF2Qty = (float) $sl->f2_qty * $stackedBotLugQty;
        }

        $stackedClipQty = ($midRailStacked ? ($pair ? $qty * $midQty * 2 : $qty * $midQty) : 0)
            + ($botRailStacked ? ($pair ? $qty * 2 : $qty) : 0);

        $gasketPn = null;
        $gasketQty = 0;
        $gasket2Pn = null;
        $gasket2Qty = 0;
        if ($glassData) {
            $hsl = $hStopLen ?? 0;
            $v1 = $vStop1Len ?? 0;
            $v2 = $vStop2Len ?? 0;
            $v3 = $vStop3Len ?? 0;
            $k64PerLeaf = ((($hsl + $hsl * $midQty + ($v1 + $v2 + $v3)) * 2) + 6) * 2;
            $leaves = $pair ? 2 : 1;
            $k64 = $k64PerLeaf * $qty * $leaves;
            $gasketPn = $glassData->gasket_pn;
            $gasketQty = (int) ceil((float) $glassData->gasket_qty_factor * $k64);
            if ($glassData->gasket2_pn) {
                $gasket2Pn = $glassData->gasket2_pn;
                $gasket2Qty = (int) ceil((float) $glassData->gasket_qty_factor * $k64);
            }
        }

        // ---- Tie rod ----
        $tieRodOffset = $stile === 'NARROW STILE' ? 1.8125 : 2.5625;
        $tieRodLen = $railLen + $tieRodOffset;
        $tieRodPn = $this->lookupTieRod($stile, $series, $width);
        $tieRodQty = $pair ? $qty * 4 : $qty * 2;
        $tieRodNutQty = $pair ? $qty * 8 : $qty * 4;

        // ---- Setting block kit ----
        $sbk = ConfiguratorSettingBlockKit::where('series', $series)->where('glass_thickness', $glassThk)->first();
        $sbkPn = $sbk->kit1_pn ?? null;
        $sbk2Pn = $sbk->kit2_pn ?? null;
        $handingType = in_array($handing, ['LH (INSWING)', 'LHR', 'RH (INSWING)', 'RHR', 'CP SINGLE'], true) ? 'S' : 'P';
        $sbkQty = $handingType === 'S' ? $qty : $qty * 2;
        $sbk2Qty = 0;
        if ($midQty > 0) {
            $sbk2Qty = match (true) {
                $midQty === 1 && $handingType === 'S' => $qty,
                $midQty === 1 && $handingType === 'P' => $qty * 2,
                $midQty === 2 && $handingType === 'S' => $qty * 2,
                $midQty === 2 && $handingType === 'P' => $qty * 4,
                default => 0,
            };
        }

        // ---- Assemble rows ----
        $rows = [];
        $sortOrder = 0;

        $addExtrusion = function (string $label, ?string $pn, float|int $qtyVal, ?float $len) use (&$rows, &$sortOrder, $finish) {
            if (! $pn || $qtyVal <= 0) {
                return;
            }
            $productId = $this->resolveExtrusionProduct($pn, $finish);
            if (! $productId) {
                return;
            }
            $rows[] = [
                'part_label' => $label,
                'product_id' => $productId,
                'calculated_length' => $len !== null ? round($len, 4) : null,
                'quantity' => round((float) $qtyVal, 3),
                'unit_type' => 'length',
                'source_type' => 'extrusion',
                'is_auto_generated' => true,
                'sort_order' => $sortOrder++,
            ];
        };

        $addComponent = function (string $label, ?string $pn, float|int $qtyVal) use (&$rows, &$sortOrder) {
            if (! $pn || $pn === '—' || $qtyVal <= 0) {
                return;
            }
            $productId = $this->resolveComponentProduct($pn);
            if (! $productId) {
                return;
            }
            $rows[] = [
                'part_label' => $label,
                'product_id' => $productId,
                'calculated_length' => null,
                'quantity' => round((float) $qtyVal, 3),
                'unit_type' => 'qty',
                'source_type' => 'component',
                'is_auto_generated' => true,
                'sort_order' => $sortOrder++,
            ];
        };

        $addExtrusion('Top Rail', $topRailPn, $topQty, $railLen);
        $addExtrusion('Bottom Rail', $botRailPn, $botQty, $railLen);
        $addExtrusion('Midrail', $midRailPn, $midExtQty, $railLen);
        $addExtrusion('Hinge Stile', $hingeStilePn, $hingeStileQty, $stileLen);
        $addExtrusion('Lock Stile', $lockStilePn, $lockStileQty, $stileLen);
        $addExtrusion('Active Astragal Stile', $astragalStilePn, $astragalStileQty, $stileLen);
        $addExtrusion('Inactive Meeting Stile', $inactiveStilePn, $inactiveStileQty, $stileLen);
        $addExtrusion('Astragal', $astragalPn, $astragalQty, $stileLen);
        $addExtrusion('Stacked Bottom Rail', $stackedBotPn, $stackedBotQty, $railLen);
        $addExtrusion('Vertical Glass Stops #1', $glassStopPn, $vStop1Qty, $vStop1Len);
        $addExtrusion('Vertical Glass Stops #2', $glassStopPn, $vStop2Qty, $vStop2Len);
        $addExtrusion('Vertical Glass Stops #3', $glassStopPn, $vStop3Qty, $vStop3Len);
        $addExtrusion('Horizontal Glass Stops', $glassStopPn, $hStopQtyBase, $hStopLen);

        $addComponent('Top Rail Lug', $topLugPn, $topLugQty);
        $addComponent('Bottom Rail Lug', $botLugPn, $botLugQty);
        $addComponent('Midrail Lug', $midLugPn, $midLugQty);
        $addComponent('Fasteners #1 (Mid)', $midF1Pn, $midF1Qty);
        $addComponent('Fasteners #2 (Mid)', $midF2Pn, $midF2Qty);
        $addComponent('Stacked Bot Rail Lug', $stackedBotLugPn, $stackedBotLugQty);
        $addComponent('Fasteners #1 (Stacked)', $stackedF1Pn, $stackedF1Qty);
        $addComponent('Fasteners #2 (Stacked)', $stackedF2Pn, $stackedF2Qty);
        $addComponent('Stacked Rail Clip', 'P1173-0R', $stackedClipQty);
        $addComponent('Gasket', $gasketPn, $gasketQty);
        $addComponent('Gasket #2', $gasket2Pn, $gasket2Qty);
        $addComponent('Tie Rod', $tieRodPn, $tieRodQty);
        $addComponent('Tie Rod Nuts', 'S081-0R', $tieRodNutQty);
        $addComponent('Setting Block Kit', $sbkPn, $sbkQty);
        if ($midQty > 0) {
            $addComponent('Setting Block Kit #2', $sbk2Pn, $sbk2Qty);
        }

        return ['rows' => $rows, 'warnings' => $this->warnings];
    }

    private function isPair(?string $handing): bool
    {
        return in_array($handing, ['PAIR-RHRA', 'PAIR-LHRA', 'CP PAIR'], true);
    }

    /**
     * Tie rod PN lookup: DB min_len/max_len represent a door-width range
     * (min < width <= max). Falls back to P022V for widths beyond 48" that
     * aren't yet in the catalog.
     */
    private function lookupTieRod(string $stileWidth, string $series, float $doorWidth): ?string
    {
        $seriesLabels = [
            'STANDARD NARROW STILE' => 'NARROW STILE',
            'STANDARD MEDIUM STILE' => 'MEDIUM STILE',
            'STANDARD MEDIUM STILE 4in.' => 'MEDIUM STILE 4in.',
            'STANDARD WIDE STILE' => 'WIDE STILE',
            'MONUMENTAL NARROW STILE' => 'MONUMENTAL NARROW STILE',
            'MONUMENTAL MEDIUM STILE' => 'MONUMENTAL MEDIUM STILE',
            'MONUMENTAL WIDE STILE' => 'MONUMENTAL WIDE STILE',
            'THERMAL NARROW STILE' => 'THERMAL NARROW STILE',
            'THERMAL MEDIUM STILE' => 'THERMAL MEDIUM STILE',
            'THERMAL WIDE STILE' => 'THERMAL WIDE STILE',
        ];
        $label = $seriesLabels[$series.' '.$stileWidth] ?? '';

        $match = ConfiguratorTieRod::where('series', $label)
            ->where('min_len', '<', $doorWidth)
            ->where('max_len', '>=', $doorWidth)
            ->first();

        if ($match) {
            return $match->pn.'-0R';
        }

        return $doorWidth > 48 ? 'P022V-0R' : null;
    }

    /**
     * Extrusion PNs (E/A-prefix) need the config's selected finish (ForgeDesk
     * keeps finish as its own Product column, unlike fab_utils' PN-suffix
     * convention) — falls back to any finish if that exact variant isn't stocked.
     */
    private function resolveExtrusionProduct(string $pn, string $finish): ?int
    {
        $product = Product::where('part_number', $pn)->where('finish', $finish)->first()
            ?? Product::where('part_number', $pn)->first();

        if (! $product) {
            $this->warnings[] = "No product found for extrusion PN \"{$pn}\".";
        }

        return $product?->id;
    }

    /**
     * Hardware PNs sometimes carry a baked-in finish suffix in the catalog
     * data itself (e.g. "P797-0R") — split it off and match ForgeDesk's
     * separate part_number/finish columns.
     */
    private function resolveComponentProduct(string $pn): ?int
    {
        if (preg_match('/^(.*)-(BL|C2|DB|0R)$/', $pn, $m)) {
            [$base, $finish] = [$m[1], $m[2]];
            $product = Product::where('part_number', $base)->where('finish', $finish)->first()
                ?? Product::where('part_number', $base)->first();
        } else {
            $product = Product::where('part_number', $pn)->first();
        }

        if (! $product) {
            $this->warnings[] = "No product found for component PN \"{$pn}\".";
        }

        return $product?->id;
    }
}
