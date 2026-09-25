<?php

namespace App\Services\Configurator;

use App\Models\ConfiguratorFrameSeries;
use App\Models\DoorFrameConfiguration;
use App\Models\Product;
use RuntimeException;

/**
 * Builds the frame parts BOM (extrusions + their attached components) for a
 * door/frame configuration from the selected catalog frame series.
 */
class FrameBomGenerator
{
    /** @var array<int, string> Notices about finish substitutions or unresolved PNs. */
    private array $warnings = [];

    public function __construct(
        private FrameFormulaEvaluator $evaluator = new FrameFormulaEvaluator,
        private FinishFallbackResolver $finishResolver = new FinishFallbackResolver,
    ) {}

    /**
     * @return array{rows: array<int, array>, warnings: array<int, string>}
     */
    public function generate(DoorFrameConfiguration $config): array
    {
        $this->warnings = [];

        $frameConfig = $config->frameConfig;
        $openingSpecs = $config->openingSpecs;

        if (! $frameConfig || ! $frameConfig->frame_series_id) {
            throw new RuntimeException('Frame configuration requires a selected frame series.');
        }
        if (! $openingSpecs) {
            throw new RuntimeException('Opening specifications are required before generating parts.');
        }

        $finish = strtoupper($openingSpecs->finish ?? '');

        $series = ConfiguratorFrameSeries::with('profiles.product', 'profiles.components.product', 'profiles.components.fasteners.product')
            ->findOrFail($frameConfig->frame_series_id);

        $width = (float) $openingSpecs->door_opening_width;
        $height = (float) $openingSpecs->door_opening_height;
        $openingType = $openingSpecs->opening_type;
        $hasTransom = (bool) $frameConfig->has_transom;
        $hasThreshold = (bool) $frameConfig->has_threshold;
        $transomGlazing = $frameConfig->transom_glazing !== null ? (float) $frameConfig->transom_glazing : null;
        $openingQty = max(1, (int) $config->quantity);

        // section_height is a fixed catalog dimension, known for every profile in
        // the series regardless of which ones end up in this config's BOM — so
        // TH/section/if_threshold terms can all resolve in a single pass.
        $sectionHeights = $series->profiles->pluck('section_height', 'role_label')
            ->map(fn ($v) => (float) $v)->all();

        $totalHeight = $this->evaluator->resolveTotalHeight(
            $height, $hasTransom, $frameConfig->total_frame_height !== null ? (float) $frameConfig->total_frame_height : null, $sectionHeights
        );

        $context = [
            'width' => $width,
            'height' => $height,
            'total_height' => $totalHeight,
            'has_threshold' => $hasThreshold,
        ];

        $applicable = $series->profiles->filter(
            fn ($profile) => $profile->appliesTo($openingType, $hasTransom, $hasThreshold, $transomGlazing)
        )->values();

        $rows = [];
        $sortOrder = 0;

        foreach ($applicable as $profile) {
            $length = $this->evaluator->evaluate($profile->formula ?? [], $context, $sectionHeights);
            $profileQty = max(1, (int) $profile->qty_per_opening) * $openingQty;

            $rows[] = [
                'part_label' => $profile->role_label,
                'product_id' => $this->resolveProductId($profile->product, $profile->product_id, $finish),
                'calculated_length' => $length,
                'quantity' => $profileQty,
                'unit_type' => 'length',
                'source_type' => 'profile',
                'is_auto_generated' => true,
                'sort_order' => $sortOrder++,
            ];

            foreach ($profile->components as $component) {
                $isLengthComponent = $component->qty_type === 'per_length';

                // Linear-feet items (gaskets, weatherstrip): total footage needed, rounded up.
                // Kept in feet (not the inches used below) because fasteners are priced "per foot
                // of gasket", and this is also the qty fallback if the component's product isn't
                // flagged length-based stock.
                $feetNeeded = $isLengthComponent
                    ? (int) ceil((float) $component->qty_per * $profileQty * $length / 12)
                    : null;

                $componentQty = match ($component->qty_type) {
                    'per_length' => $feetNeeded,
                    'per_door' => (float) $component->qty_per * $profileQty,
                    default => (float) $component->qty_per * $openingQty,
                };

                // A per_length component is cut from roll/reel stock, not counted as
                // individual eaches — carry the precise inches needed as calculated_length
                // (quantity=1), the same shape $addExtrusion uses, so
                // ConfigurationReservationBridge::quantityContribution() can reserve it as a
                // fraction of a roll (once the product's is_length_based/configurator_length
                // are set) instead of reserving N feet as N eaches.
                $rows[] = $isLengthComponent ? [
                    'part_label' => $component->label,
                    'product_id' => $this->resolveProductId($component->product, $component->product_id, $finish),
                    'calculated_length' => round((float) $component->qty_per * $profileQty * $length, 4),
                    'quantity' => 1,
                    'unit_type' => 'length',
                    'source_type' => 'component',
                    'is_auto_generated' => true,
                    'sort_order' => $sortOrder++,
                ] : [
                    'part_label' => $component->label,
                    'product_id' => $this->resolveProductId($component->product, $component->product_id, $finish),
                    'calculated_length' => null,
                    'quantity' => round($componentQty, 3),
                    'unit_type' => 'qty',
                    'source_type' => 'component',
                    'is_auto_generated' => true,
                    'sort_order' => $sortOrder++,
                ];

                foreach ($component->fasteners as $fastener) {
                    $rows[] = [
                        'part_label' => $fastener->label,
                        'product_id' => $this->resolveProductId($fastener->product, $fastener->product_id, $finish),
                        'calculated_length' => null,
                        'quantity' => round((float) $fastener->qty_per * ($feetNeeded ?? $componentQty), 3),
                        'unit_type' => 'qty',
                        'source_type' => 'fastener',
                        'is_auto_generated' => true,
                        'sort_order' => $sortOrder++,
                    ];
                }
            }
        }

        return ['rows' => $rows, 'warnings' => $this->warnings];
    }

    /**
     * Re-resolves a catalog-linked product against the opening's selected
     * finish, walking the finish fallback chain (see FinishFallbackResolver)
     * when the exact color isn't stocked for that part number. Falls back to
     * the catalog's own default product if the part number has no
     * finish-specific variants at all (e.g. hardware only ever stocked mill).
     */
    private function resolveProductId(?Product $catalogProduct, ?int $catalogProductId, string $finish): ?int
    {
        if (! $catalogProduct) {
            return $catalogProductId;
        }

        $resolved = $this->finishResolver->resolve($catalogProduct->part_number, $finish);

        if (! $resolved) {
            return $catalogProductId;
        }

        if ($resolved->finish !== $finish) {
            $this->warnings[] = "\"{$catalogProduct->part_number}\" not available in {$finish} — substituted {$resolved->finish}.";
        }

        return $resolved->id;
    }
}
