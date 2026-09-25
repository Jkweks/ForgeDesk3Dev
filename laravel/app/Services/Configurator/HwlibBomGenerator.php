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

        $links = $config->hardwareLinks()->with('item')->get();
        if ($links->isEmpty()) {
            throw new RuntimeException('No hardware items are linked to this configuration yet.');
        }

        $finish = strtoupper($config->openingSpecs->finish ?? '');
        $rows = [];
        $sortOrder = 0;

        foreach ($links as $link) {
            $item = $link->item;

            $itemProductId = $this->resolveProduct($item->pn, $finish);
            if ($itemProductId) {
                $rows[] = [
                    'part_label' => $item->name,
                    'product_id' => $itemProductId,
                    'quantity' => $link->quantity,
                    'source_type' => 'item',
                    'hwlib_link_id' => $link->id,
                    'is_auto_generated' => true,
                    'sort_order' => $sortOrder++,
                ];
            } elseif ($item->pn) {
                $this->warnings[] = "No product found for hardware item PN \"{$item->pn}\" ({$item->name}).";
            }

            $backers = ConfiguratorHwlibItemBacker::where('item_id', $item->id)
                ->where('series', $link->series)
                ->get();

            foreach ($backers as $backerLink) {
                if (! $backerLink->pn) {
                    continue;
                }
                $backerProductId = $this->resolveHardwareProduct($backerLink->pn);
                if (! $backerProductId) {
                    $this->warnings[] = "No product found for backer PN \"{$backerLink->pn}\" ({$item->name} — {$backerLink->side}).";

                    continue;
                }

                $backerQty = round((float) $backerLink->qty * $link->quantity, 3);
                $rows[] = [
                    'part_label' => $backerLink->description ?: "{$item->name} Backer ({$backerLink->side})",
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

        return ['rows' => $rows, 'warnings' => $this->warnings];
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
