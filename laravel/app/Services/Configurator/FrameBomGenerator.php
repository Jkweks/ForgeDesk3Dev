<?php

namespace App\Services\Configurator;

use App\Models\ConfiguratorFrameSeries;
use App\Models\DoorFrameConfiguration;
use RuntimeException;

/**
 * Builds the frame parts BOM (extrusions + their attached components) for a
 * door/frame configuration from the selected catalog frame series.
 */
class FrameBomGenerator
{
    public function __construct(private FrameFormulaEvaluator $evaluator = new FrameFormulaEvaluator) {}

    /**
     * @return array<int, array> Rows shaped for DoorFrameFramePart::create().
     */
    public function generate(DoorFrameConfiguration $config): array
    {
        $frameConfig = $config->frameConfig;
        $openingSpecs = $config->openingSpecs;

        if (! $frameConfig || ! $frameConfig->frame_series_id) {
            throw new RuntimeException('Frame configuration requires a selected frame series.');
        }
        if (! $openingSpecs) {
            throw new RuntimeException('Opening specifications are required before generating parts.');
        }

        $series = ConfiguratorFrameSeries::with('profiles.components.fasteners')
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
                'product_id' => $profile->product_id,
                'calculated_length' => $length,
                'quantity' => $profileQty,
                'unit_type' => 'length',
                'source_type' => 'profile',
                'is_auto_generated' => true,
                'sort_order' => $sortOrder++,
            ];

            foreach ($profile->components as $component) {
                $componentQty = match ($component->qty_type) {
                    // Linear-feet items (gaskets, weatherstrip): total footage needed, rounded up.
                    'per_length' => (int) ceil((float) $component->qty_per * $profileQty * $length / 12),
                    'per_door' => (float) $component->qty_per * $profileQty,
                    default => (float) $component->qty_per * $openingQty,
                };

                $rows[] = [
                    'part_label' => $component->label,
                    'product_id' => $component->product_id,
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
                        'product_id' => $fastener->product_id,
                        'calculated_length' => null,
                        'quantity' => round((float) $fastener->qty_per * $componentQty, 3),
                        'unit_type' => 'qty',
                        'source_type' => 'fastener',
                        'is_auto_generated' => true,
                        'sort_order' => $sortOrder++,
                    ];
                }
            }
        }

        return $rows;
    }
}
