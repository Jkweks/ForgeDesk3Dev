<?php

namespace App\Services\Configurator;

/**
 * Evaluates a frame profile's cut-length formula, in the same term format
 * used by the fab_utils frame calculator this was ported from:
 *
 *   [{"var": "W", "sign": 1}, {"var": "fixed", "sign": -1, "value": 2.0}, ...]
 *
 * Term vars:
 *  - W / H        — opening width / height
 *  - TH           — total frame height (resolved by the caller: the user-entered
 *                   value when a transom is present, otherwise H + the Door
 *                   Head profile's section_height)
 *  - fixed        — a constant given in "value"
 *  - if_threshold — the series' Threshold profile's section_height, only when
 *                   has_threshold is true (0 otherwise)
 *  - section:<Role Label> — another profile's *section_height* (a fixed catalog
 *                   dimension, e.g. jamb depth) — NOT that profile's computed
 *                   cut length. section_height is looked up across every
 *                   profile in the series, regardless of whether that profile
 *                   is itself included in this configuration's BOM.
 */
class FrameFormulaEvaluator
{
    /**
     * @param  array  $formula  List of term arrays.
     * @param  array{width: float, height: float, total_height: float, has_threshold: bool}  $context
     * @param  array<string, float>  $sectionHeights  role_label => section_height, for every profile in the series.
     */
    public function evaluate(array $formula, array $context, array $sectionHeights = []): float
    {
        $total = 0.0;

        foreach ($formula as $term) {
            $sign = ($term['sign'] ?? 1) < 0 ? -1 : 1;
            $var = $term['var'] ?? 'fixed';

            if (str_starts_with($var, 'section:')) {
                $ref = substr($var, strlen('section:'));
                $amount = (float) ($sectionHeights[$ref] ?? 0);
            } else {
                $amount = match ($var) {
                    'W' => (float) ($context['width'] ?? 0),
                    'H' => (float) ($context['height'] ?? 0),
                    'TH' => (float) ($context['total_height'] ?? 0),
                    'if_threshold' => ! empty($context['has_threshold']) ? (float) ($sectionHeights['Threshold'] ?? 0) : 0.0,
                    default => (float) ($term['value'] ?? 0),
                };
            }

            $total += $sign * $amount;
        }

        return round($total, 4);
    }

    /**
     * Resolve TH (total frame height): the user-entered value when a transom
     * is present (falling back to H if left blank), otherwise H plus the
     * series' Door Head profile's section_height.
     *
     * @param  array<string, float>  $sectionHeights  role_label => section_height
     */
    public function resolveTotalHeight(float $height, bool $hasTransom, ?float $userTotalHeight, array $sectionHeights): float
    {
        if ($hasTransom) {
            return $userTotalHeight ?: $height;
        }

        return $height + (float) ($sectionHeights['Door Head'] ?? 0);
    }
}
