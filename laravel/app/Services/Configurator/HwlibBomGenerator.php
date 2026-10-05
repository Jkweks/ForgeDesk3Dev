<?php

namespace App\Services\Configurator;

use App\Models\ConfiguratorHwlibItemBacker;
use App\Models\DoorFrameConfiguration;
use App\Models\Product;
use RuntimeException;

/**
 * Builds the hardware BOM (items + their backers + the backers' fasteners)
 * for every hwlib link attached to a configuration.
 */
class HwlibBomGenerator
{
    /** @var array<int, string> PNs that couldn't be matched to a Product. */
    private array $warnings = [];

    public function __construct(private FinishFallbackResolver $finishResolver = new FinishFallbackResolver) {}

    /**
     * @return array{rows: array<int, array>, warnings: array<int, string>}
     */
    public function generate(DoorFrameConfiguration $config): array
    {
        $this->warnings = [];

        $links = $config->relationLoaded('hardwareLinks')
            ? $config->hardwareLinks->loadMissing('item')
            : $config->hardwareLinks()->with('item')->get();
        if ($links->isEmpty()) {
            throw new RuntimeException('No hardware items are linked to this configuration yet.');
        }

        $finish = strtoupper($config->openingSpecs->finish ?? '');
        $isPair = ($config->openingSpecs->opening_type ?? null) === 'pair';
        // Links are per opening; a configuration with N door tags is N openings of the same hardware.
        $openingQty = max(1, (int) $config->quantity);
        $handing = $config->openingSpecs->deriveDoorHanding();

        // Backers are keyed to the door or the frame; only the sides this opening's scope
        // actually includes get pulled (a frame-only job must not get door backers).
        $sides = array_values(array_filter([
            $config->includesDoor() ? 'door' : null,
            $config->includesFrame() ? 'frame' : null,
        ]));
        $rows = [];
        $sortOrder = 0;

        foreach ($links as $link) {
            $item = $link->item;

            // A link flagged for "both" leaves is per leaf: on a pair that's two of everything
            // (fab_utils' effectiveLinkQty). 'active'/'inactive' links name one leaf already.
            $linkQty = (($link->leaf === 'both' && $isPair) ? $link->quantity * 2 : $link->quantity) * $openingQty;

            // Handed items (locks, covers) are stocked per hand (P1421 -> P1421L / P1421R); on a pair
            // each leaf has its own hand, so a link on both leaves is one of each (see HandedHardware).
            foreach (HandedHardware::variants($link, $item, $isPair, $handing) as [$hand, $variantQty]) {
                $pieceQty = $variantQty * $openingQty;
                $stockPn = $item->pn ? $item->pn.$hand : null;

                $itemProductId = $this->resolveProduct($stockPn, $finish);
                if ($itemProductId) {
                    $rows[] = [
                        'part_label' => $item->name,
                        'product_id' => $itemProductId,
                        'quantity' => $pieceQty,
                        'source_type' => 'item',
                        'hwlib_link_id' => $link->id,
                        'is_auto_generated' => true,
                        'sort_order' => $sortOrder++,
                    ];
                } else {
                    // No stock product: custom-ordered for the job (non-VOS-standard hardware).
                    // Listed by name/manufacturer/model on the job's hardware list instead of
                    // being dropped; it commits nothing against inventory.
                    $rows[] = [
                        'part_label' => $item->name,
                        'manufacturer' => $item->manufacturer,
                        'model_number' => ($item->model_number ?: $item->pn).$hand ?: null,
                        'product_id' => null,
                        'quantity' => $pieceQty,
                        'source_type' => 'item',
                        'hwlib_link_id' => $link->id,
                        'is_auto_generated' => true,
                        'sort_order' => $sortOrder++,
                    ];
                    if ($item->pn) {
                        $this->warnings[] = "Hardware item \"{$item->name}\" (PN {$stockPn}) has no stock product — listed as special-order hardware.";
                    }
                }
            }

            $backers = ConfiguratorHwlibItemBacker::where('item_id', $item->id)
                ->where('series', $link->series)
                ->whereIn('side', $sides)
                ->get();

            foreach ($backers as $backerLink) {
                // Catalog-imported item-backer rows carry no PN/description of their own — they
                // point at a shared backer record that does. An explicit override on the row wins.
                $backerPn = $backerLink->pn ?: $backerLink->backer?->pn;
                $backerDescription = $backerLink->description ?: $backerLink->backer?->description;
                if (! $backerPn) {
                    continue;
                }
                $backerProductId = $this->resolveHardwareProduct($backerPn);
                if (! $backerProductId) {
                    $this->warnings[] = "No product found for backer PN \"{$backerPn}\" ({$item->name} — {$backerLink->side}).";

                    continue;
                }

                $backerQty = round((float) $backerLink->qty * $linkQty, 3);
                $rows[] = [
                    'part_label' => $backerDescription ?: "{$item->name} Backer ({$backerLink->side})",
                    'product_id' => $backerProductId,
                    'quantity' => $backerQty,
                    'source_type' => 'backer',
                    'is_auto_generated' => true,
                    'sort_order' => $sortOrder++,
                ];

                if ($backerLink->backer_id) {
                    foreach ($backerLink->backer->fasteners as $backerFastener) {
                        $fastenerProductId = $this->resolveHardwareProduct($backerFastener->fastener->pn);
                        if (! $fastenerProductId) {
                            $this->warnings[] = "No product found for fastener PN \"{$backerFastener->fastener->pn}\".";

                            continue;
                        }

                        $rows[] = [
                            'part_label' => $backerFastener->fastener->description ?: $backerFastener->fastener->pn,
                            'product_id' => $fastenerProductId,
                            'quantity' => round((float) $backerFastener->qty * $backerQty, 3),
                            'source_type' => 'fastener',
                            'is_auto_generated' => true,
                            'sort_order' => $sortOrder++,
                        ];
                    }
                }
            }
        }

        $rows = app(KitExpander::class)->expand($rows, $this->warnings);

        return ['rows' => $rows, 'warnings' => $this->warnings];
    }

    /**
     * fab_utils' handingLRSuffix(): LH and RHR share the physical left-hand part; RH and LHR the
     * right-hand part. Pairs and center-pivot carry no single L/R suffix.
     */
    public function handingSuffix(string $handing): string
    {
        return HandedHardware::suffix($handing);
    }

    /**
     * Standard hardware items follow the config's selected finish, walking
     * the finish fallback chain (see FinishFallbackResolver) when the exact
     * finish isn't stocked for that PN.
     */
    private function resolveProduct(?string $pn, string $wantFinish): ?int
    {
        if (! $pn) {
            return null;
        }

        $product = $this->finishResolver->resolve($pn, $wantFinish);

        if ($product && $product->finish !== strtoupper($wantFinish)) {
            $this->warnings[] = "Hardware item PN \"{$pn}\" not available in {$wantFinish} — substituted {$product->finish}.";
        }

        return $product?->id;
    }

    /**
     * Backer/fastener PNs sometimes carry a baked-in finish suffix (e.g.
     * "P797-0R") — same convention as the door catalog. Walks the finish
     * fallback chain from that baked-in finish if it isn't stocked.
     */
    private function resolveHardwareProduct(?string $pn): ?int
    {
        if (! $pn) {
            return null;
        }

        if (preg_match('/^(.*)-(BL|C2|DB|0R)$/', $pn, $m)) {
            [$base, $finish] = [$m[1], $m[2]];
            $product = $this->finishResolver->resolve($base, $finish);

            if ($product && $product->finish !== $finish) {
                $this->warnings[] = "Backer/fastener PN \"{$pn}\" not available — substituted {$product->finish}.";
            }
        } else {
            $product = Product::where('part_number', $pn)->first();
        }

        return $product?->id;
    }
}
