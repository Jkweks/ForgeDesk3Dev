<?php

namespace App\Services\CutFlow;

use App\Models\CutFlow\CutConsumption;
use App\Models\CutFlow\CutJob;
use App\Models\FdWorkOrder;
use App\Models\Product;

/**
 * Stock lengths cut per work order, rolled up per job, from the cut_consumptions ledger. Covers every work
 * order — SOF ones (which also drew inventory) and job-specific material ones (which did not) — so what was
 * actually cut can be compared with what was ordered for the job. Work orders with no cuts yet are left out.
 */
class MaterialUsageReport
{
    /**
     * @param  ?string  $search  matches job number or name
     * @return array{jobs: array, total_stock_lengths: float}
     */
    public function build(?string $search = null, ?int $businessJobId = null, ?int $workOrderId = null): array
    {
        $workOrders = FdWorkOrder::with('businessJob:id,job_number,job_name')
            ->when($businessJobId, fn ($q) => $q->where('business_job_id', $businessJobId))
            ->when($workOrderId, fn ($q) => $q->whereKey($workOrderId))
            ->when($search, fn ($q) => $q->whereHas('businessJob', fn ($j) => $j
                ->where('job_number', 'like', "%{$search}%")->orWhere('job_name', 'like', "%{$search}%")))
            ->orderBy('business_job_id')->orderBy('release_number')
            ->get();

        $cutJobs = CutJob::whereIn('work_order_id', $workOrders->pluck('id'))->pluck('work_order_id', 'id');
        $rows = CutConsumption::whereIn('cut_job_id', $cutJobs->keys())
            ->selectRaw('cut_job_id, product_id, SUM(stock_fraction) as stock_lengths, COUNT(*) as cuts')
            ->groupBy('cut_job_id', 'product_id')
            ->get();

        $products = Product::withTrashed()->whereIn('id', $rows->pluck('product_id')->unique())->get()->keyBy('id');

        // [work order id][product id] => [lengths, cuts]
        $perWorkOrder = [];
        foreach ($rows as $r) {
            $cell = &$perWorkOrder[$cutJobs[$r->cut_job_id]][$r->product_id];
            $cell['lengths'] = ($cell['lengths'] ?? 0) + (float) $r->stock_lengths;
            $cell['cuts'] = ($cell['cuts'] ?? 0) + (int) $r->cuts;
            unset($cell);
        }

        $line = fn (int $productId, array $v) => [
            'product_id' => $productId,
            'sku' => $products[$productId]->sku ?? null,
            'name' => $products[$productId]->name ?? null,
            'finish' => $products[$productId]->finish ?? null,
            'stock_lengths' => round($v['lengths'], 2),
            'cuts' => $v['cuts'],
        ];
        $bySku = fn (array $a, array $b) => strcmp((string) $a['sku'], (string) $b['sku']);

        $jobs = [];
        foreach ($workOrders as $wo) {
            if (empty($perWorkOrder[$wo->id])) {
                continue;
            }

            $lines = [];
            foreach ($perWorkOrder[$wo->id] as $productId => $v) {
                $lines[] = $line($productId, $v);
                $cell = &$jobs[$wo->business_job_id]['summary'][$productId];
                $cell['lengths'] = ($cell['lengths'] ?? 0) + $v['lengths'];
                $cell['cuts'] = ($cell['cuts'] ?? 0) + $v['cuts'];
                unset($cell);
            }
            usort($lines, $bySku);

            $jobs[$wo->business_job_id]['job'] = $wo->businessJob;
            $jobs[$wo->business_job_id]['work_orders'][] = [
                'work_order_id' => $wo->id,
                'release_number' => $wo->release_number,
                'material_delivery' => $wo->material_delivery,
                'drew_inventory' => CutConsumptionService::tagDrawsFromInventory($wo->material_delivery),
                'products' => $lines,
                'total_stock_lengths' => round(array_sum(array_column($lines, 'stock_lengths')), 2),
            ];
        }

        $out = [];
        foreach ($jobs as $jobId => $j) {
            $summary = [];
            foreach ($j['summary'] as $productId => $v) {
                $summary[] = $line($productId, $v);
            }
            usort($summary, $bySku);

            $out[] = [
                'business_job_id' => $jobId,
                'job_number' => $j['job']?->job_number,
                'job_name' => $j['job']?->job_name,
                'work_orders' => $j['work_orders'],
                'summary' => $summary,
                'total_stock_lengths' => round(array_sum(array_column($summary, 'stock_lengths')), 2),
            ];
        }

        return [
            'jobs' => $out,
            'total_stock_lengths' => round(array_sum(array_column($out, 'total_stock_lengths')), 2),
        ];
    }
}
