<?php

namespace App\Services\Pm;

use App\Models\BidEstimate;
use App\Models\BidEstimateOverride;
use App\Models\CostCode;

/**
 * The estimate as the PM sees it: stored lines with their manual row decisions applied.
 * Overrides live in their own table keyed by (section, sheet row), so they survive a re-import.
 */
class BidEstimatePresenter
{
    /** Keep the camelCase shape the PM Suite pages used, so the API stays stable. */
    public function present(BidEstimate $estimate): array
    {
        $codes = CostCode::pluck('description', 'code');
        $overrides = BidEstimateOverride::where('business_job_id', $estimate->business_job_id)
            ->where('sheet', $estimate->sheet)->get()->keyBy(fn ($o) => $o->section.'|'.$o->row);

        $lines = [];
        $review = [];
        $decided = [];
        foreach ($estimate->lines as $l) {
            $ov = $overrides[$l->section.'|'.$l->row] ?? null;
            if ($ov) {
                $decided[] = ['section' => $l->section, 'row' => $l->row, 'action' => $ov->action, 'costCode' => $ov->cost_code,
                    'description' => $l->description, 'total' => $l->total === null ? null : (float) $l->total];
            }
            if ($ov && $ov->action === 'ignore') {
                continue;
            }
            $row = [
                'section' => $l->section, 'row' => $l->row, 'who' => $l->who, 'costCode' => $l->cost_code,
                'description' => $l->description, 'qty' => $l->qty === null ? null : (float) $l->qty,
                'unitPrice' => $l->unit_price === null ? null : (float) $l->unit_price,
                'total' => $l->total === null ? null : (float) $l->total, 'confidence' => $l->confidence,
            ];
            if ($l->additional_cost_code) {
                $row['additionalCostCode'] = $l->additional_cost_code;
            }
            if ($ov && $ov->action === 'fix') {
                $row['costCode'] = $ov->cost_code;
                $row['confidence'] = 'pm_fixed';
            } elseif ($l->review_reason) {
                $row['reviewReason'] = $l->review_reason;
                $review[] = $row;
            }
            if ($row['costCode'] !== null) {
                $lines[] = $row;
            }
        }

        $byCode = [];
        foreach ($lines as $l) {
            $c = $l['costCode'];
            $byCode[$c] ??= ['total' => 0.0, 'lineCount' => 0, 'qty' => 0.0];
            $byCode[$c]['total'] += $l['total'] ?? 0;
            $byCode[$c]['qty'] += $l['qty'] ?? 0;
            $byCode[$c]['lineCount']++;
        }
        foreach ($byCode as $c => &$agg) {
            $agg['total'] = round($agg['total'], 2);
            $agg['qty'] = round($agg['qty'], 2);
            $agg['description'] = $codes[$c] ?? '(not on master list)';
        }
        unset($agg);
        ksort($byCode);

        return [
            'id' => $estimate->id,
            'business_job_id' => $estimate->business_job_id,
            'sheet' => $estimate->sheet,
            'imported_at' => $estimate->updated_at,
            'parserRevision' => $estimate->parser_revision,
            'parserStale' => $estimate->parser_revision < BidEstimateImporter::CURRENT_PARSER_REVISION,
            'meta' => array_filter([
                'projectName' => $estimate->project_name, 'gc' => $estimate->gc,
                'architect' => $estimate->architect, 'due' => $estimate->due,
            ], fn ($v) => $v !== null),
            'summary' => array_filter([
                'contractAmount' => $estimate->contract_amount, 'dollarsPerSquareFootGlass' => $estimate->dollars_per_sf_glass,
                'material' => $estimate->material, 'tax' => $estimate->tax, 'large_sub' => $estimate->large_sub,
                'other' => $estimate->other, 'labor' => $estimate->labor, 'material_overhead' => $estimate->material_overhead,
            ], fn ($v) => $v !== null),
            'lines' => $lines,
            'adjustments' => $estimate->adjustments->map->only(['section', 'row', 'description', 'rate', 'base', 'amount'])->all(),
            'byCostCode' => $byCode,
            'reviewNeeded' => $review,
            'decisions' => $decided,
            'warnings' => $estimate->warnings->map(fn ($w) => array_filter([
                'type' => $w->type, 'section' => $w->section, 'computedFromLines' => $w->computed_from_lines,
                'statedInSheet' => $w->stated_in_sheet, 'note' => $w->note,
            ], fn ($v) => $v !== null))->all(),
            'lineCount' => count($lines),
            'reviewCount' => count($review),
        ];
    }
}
