<?php

namespace App\Services\Pm;

use App\Models\BidEstimate;
use App\Models\BusinessJob;
use App\Models\CostCode;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;

/**
 * Per-job comparison of what the estimate expected against what has been ordered and received,
 * by cost code. Estimated comes from the imported BidEstimate (with the PM's row decisions applied);
 * ordered and received come from job POs only. Stock POs and internal POs are deliberately ignored:
 * they are not job material (docs/plans/pm-suite-integration.md, inventory model constraint).
 *
 * "Ordered" counts issued POs (issue stage past draft). Draft POs are reported separately so the PM
 * can see what is planned but not yet placed. Voided POs never count.
 */
class MaterialForecastService
{
    public function __construct(private BidEstimatePresenter $presenter) {}

    /**
     * @param  string[]|null  $sheets  estimate tabs to include; null = every imported tab (contract + change orders)
     * @param  string|null  $category  cost code category to keep (default material); null = all
     */
    public function forJob(BusinessJob $job, ?array $sheets = null, ?string $category = 'material'): array
    {
        $codes = CostCode::all()->keyBy('code');
        $rows = [];
        $ensure = function (int $code) use (&$rows, $codes): void {
            $rows[$code] ??= [
                'costCode' => $code,
                'description' => $codes[$code]->description ?? '(not on master list)',
                'category' => $codes[$code]->category ?? null,
                'estimatedQty' => 0.0, 'estimatedTotal' => 0.0,
                'orderedQty' => 0, 'orderedTotal' => 0.0,
                'draftQty' => 0, 'draftTotal' => 0.0,
                'receivedQty' => 0, 'receivedTotal' => 0.0,
            ];
        };

        $estimates = BidEstimate::with(['lines', 'adjustments', 'warnings'])
            ->where('business_job_id', $job->id)
            ->when($sheets, fn ($q) => $q->whereIn('sheet', $sheets))->get();
        foreach ($estimates as $estimate) {
            foreach ($this->presenter->present($estimate)['byCostCode'] as $code => $agg) {
                $ensure((int) $code);
                $rows[(int) $code]['estimatedQty'] += $agg['qty'];
                $rows[(int) $code]['estimatedTotal'] += $agg['total'];
            }
        }

        $items = PurchaseOrderItem::query()
            ->whereDoesntHave('children')          // groups are headings; only leaf lines carry quantity and price
            ->whereNotNull('cost_code')
            ->whereHas('purchaseOrder', fn ($q) => $q->where('type', PurchaseOrder::TYPE_JOB)
                ->where('business_job_id', $job->id)->whereNull('voided_at'))
            ->with('purchaseOrder:id,issue_stage')
            ->get();
        foreach ($items as $item) {
            $code = (int) $item->cost_code;
            $ensure($code);
            if ($item->purchaseOrder->issue_stage === 'draft') {
                $rows[$code]['draftQty'] += $item->quantity_ordered;
                $rows[$code]['draftTotal'] += (float) $item->total_cost;
            } else {
                $rows[$code]['orderedQty'] += $item->quantity_ordered;
                $rows[$code]['orderedTotal'] += (float) $item->total_cost;
                $rows[$code]['receivedQty'] += $item->quantity_received;
                $rows[$code]['receivedTotal'] += $item->quantity_received * (float) $item->unit_cost;
            }
        }

        $out = [];
        foreach ($rows as $r) {
            if ($category !== null && $r['category'] !== $category) {
                continue;
            }
            $r['estimatedQty'] = round($r['estimatedQty'], 2);
            $r['estimatedTotal'] = round($r['estimatedTotal'], 2);
            $r['orderedTotal'] = round($r['orderedTotal'], 2);
            $r['draftTotal'] = round($r['draftTotal'], 2);
            $r['receivedTotal'] = round($r['receivedTotal'], 2);
            $r['orderedPct'] = $r['estimatedTotal'] > 0 ? round($r['orderedTotal'] / $r['estimatedTotal'] * 100, 1) : null;
            $r['receivedPct'] = $r['orderedQty'] > 0 ? round($r['receivedQty'] / $r['orderedQty'] * 100, 1) : null;
            $r['status'] = $this->status($r);
            $out[] = $r;
        }
        usort($out, fn ($a, $b) => $a['costCode'] <=> $b['costCode']);

        $sum = fn (string $k) => round(array_sum(array_column($out, $k)), 2);

        return [
            'business_job_id' => $job->id,
            'sheets' => $estimates->pluck('sheet')->values()->all(),
            'category' => $category,
            'rows' => $out,
            'totals' => [
                'estimatedTotal' => $sum('estimatedTotal'), 'orderedTotal' => $sum('orderedTotal'),
                'draftTotal' => $sum('draftTotal'), 'receivedTotal' => $sum('receivedTotal'),
            ],
        ];
    }

    /** One word per row for the PM: where this cost code stands. */
    private function status(array $r): string
    {
        if ($r['estimatedTotal'] <= 0 && $r['orderedTotal'] <= 0 && $r['draftTotal'] <= 0) {
            return 'none';
        }
        if ($r['estimatedTotal'] > 0 && $r['orderedTotal'] > $r['estimatedTotal'] * 1.0001) {
            return 'over_budget';
        }
        if ($r['orderedTotal'] <= 0) {
            return $r['draftTotal'] > 0 ? 'draft_only' : 'not_ordered';
        }
        if ($r['orderedQty'] > 0 && $r['receivedQty'] >= $r['orderedQty']) {
            return 'received';
        }

        return $r['receivedQty'] > 0 ? 'partially_received' : 'ordered';
    }
}
