<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Services\Pm\PmJobScope;
use Illuminate\Http\Request;

/**
 * Receiving overview for job POs (feature #3): what is still expected, and what was flagged on arrival.
 * Records only: receiving here never touches stock (docs/plans/pm-suite-integration.md).
 */
class PmReceivingController extends Controller
{
    private function pos(Request $request)
    {
        $q = PurchaseOrder::jobType()->whereNull('voided_at')->whereNull('closed_at')->where('issue_stage', '!=', 'draft');
        if (($ids = PmJobScope::visibleIds($request->user())) !== null) {
            $q->whereIn('business_job_id', $ids);
        }
        foreach (['business_job_id', 'supplier_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }

        return $q;
    }

    /** Issued job POs with lines still to receive, oldest expected date first. */
    public function outstanding(Request $request)
    {
        $pos = $this->pos($request)
            ->with(['supplier:id,name', 'job:id,job_number,job_name', 'items' => fn ($q) => $q->whereDoesntHave('children')
                ->whereColumn('quantity_received', '<', 'quantity_ordered')->orderBy('sort_order')])
            ->whereHas('items', fn ($q) => $q->whereDoesntHave('children')->whereColumn('quantity_received', '<', 'quantity_ordered'))
            ->orderByRaw('expected_date IS NULL')->orderBy('expected_date')->orderBy('id')
            ->limit(300)->get();

        return response()->json($pos->map(fn ($po) => [
            'id' => $po->id, 'po_number' => $po->po_number, 'expected_date' => $po->expected_date?->toDateString(),
            'overdue' => $po->expected_date !== null && $po->expected_date->isBefore(today()),
            'supplier' => $po->supplier?->name, 'job' => $po->job?->only(['id', 'job_number', 'job_name']),
            'lines' => $po->items->map(fn ($i) => [
                'id' => $i->id, 'description' => $i->description, 'cost_code' => $i->cost_code,
                'quantity_ordered' => $i->quantity_ordered, 'quantity_received' => $i->quantity_received,
            ])->values(),
        ])->values());
    }

    /** Receipts flagged on arrival (over-received or marked by the receiver). */
    public function flagged(Request $request)
    {
        $q = PurchaseOrderReceipt::where('flagged', true)->with(['purchaseOrder:id,po_number,business_job_id,supplier_id', 'purchaseOrder.supplier:id,name', 'item:id,description'])
            ->orderByDesc('id')->limit(200);
        if (($ids = PmJobScope::visibleIds($request->user())) !== null) {
            $q->whereHas('purchaseOrder', fn ($p) => $p->whereIn('business_job_id', $ids));
        }

        return response()->json($q->get()->map(fn ($r) => [
            'id' => $r->id, 'po_id' => $r->purchase_order_id, 'po_number' => $r->purchaseOrder->po_number,
            'supplier' => $r->purchaseOrder->supplier?->name, 'line' => $r->item?->description,
            'quantity' => $r->quantity, 'received_date' => $r->received_date?->toDateString(), 'notes' => $r->notes,
        ]));
    }
}
