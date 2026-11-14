<?php

namespace App\Services\Pm;

use App\Models\BidEstimate;
use App\Models\BidEstimateOverride;
use App\Models\CostCode;

/**
 * The estimate as the PM sees it: parsed data with their manual row decisions applied.
 * Overrides live in their own table keyed by sheet row, so they survive a re-import.
 */
class BidEstimatePresenter
{
    public function present(BidEstimate $estimate): array
    {
        $data = $estimate->data;
        $lines = $data['lines'] ?? [];
        $review = $data['reviewNeeded'] ?? [];
        $codes = CostCode::pluck('description', 'code');

        $overrides = BidEstimateOverride::where('business_job_id', $estimate->business_job_id)
            ->where('sheet', $estimate->sheet)->get()->keyBy('row');

        $ignored = [];
        foreach ($overrides as $row => $ov) {
            if ($ov->action === 'ignore') {
                $ignored[$row] = true;
            }
        }

        // Lines the parser could not code (not in `lines`) become real lines once fixed.
        $byRow = [];
        foreach ($lines as $l) {
            $byRow[$l['row']] = $l;
        }
        foreach ($review as $r) {
            $ov = $overrides[$r['row']] ?? null;
            if ($ov && $ov->action === 'fix' && ! isset($byRow[$r['row']])) {
                $byRow[$r['row']] = $r;
            }
        }
        $lines = array_values($byRow);

        foreach ($lines as &$l) {
            $ov = $overrides[$l['row']] ?? null;
            if ($ov && $ov->action === 'fix') {
                $l['costCode'] = $ov->cost_code;
                $l['confidence'] = 'pm_fixed';
                unset($l['reviewReason']);
            }
        }
        unset($l);

        $lines = array_values(array_filter($lines, fn ($l) => ! isset($ignored[$l['row']])));
        $review = array_values(array_filter($review, fn ($r) => ! isset($overrides[$r['row']])));

        $byCode = [];
        foreach ($lines as $l) {
            $c = $l['costCode'];
            $byCode[$c] ??= ['total' => 0.0, 'lineCount' => 0, 'qty' => 0.0];
            $byCode[$c]['total'] += (float) ($l['total'] ?? 0);
            $byCode[$c]['qty'] += (float) ($l['qty'] ?? 0);
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
            'meta' => $data['meta'] ?? null,
            'summary' => $data['summary'] ?? null,
            'lines' => $lines,
            'adjustments' => $data['adjustments'] ?? [],
            'byCostCode' => $byCode,
            'reviewNeeded' => $review,
            'warnings' => $data['warnings'] ?? [],
            'lineCount' => count($lines),
            'reviewCount' => count($review),
        ];
    }
}
