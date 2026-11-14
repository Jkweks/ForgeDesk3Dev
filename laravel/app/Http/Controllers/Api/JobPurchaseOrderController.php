<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Job-specific purchase orders (type = job), ported from PM Suite.
 * They are ordered per job and never touch stock: no on_order_qty, no inventory
 * locations. Receiving only records that material arrived.
 */
class JobPurchaseOrderController extends Controller
{
    private const LINE_RULES = [
        'lines' => 'required|array|min:1',
        'lines.*.description' => 'required|string',
        'lines.*.kind' => 'nullable|string|max:30',
        'lines.*.quantity' => 'nullable|integer|min:0',
        'lines.*.unit_cost' => 'nullable|numeric|min:0',
        'lines.*.cost_code' => 'nullable|string|max:255',
        'lines.*.notes' => 'nullable|string',
        'lines.*.children' => 'nullable|array',
        'lines.*.children.*.description' => 'required|string',
        'lines.*.children.*.kind' => 'nullable|string|max:30',
        'lines.*.children.*.quantity' => 'required|integer|min:0',
        'lines.*.children.*.unit_cost' => 'required|numeric|min:0',
        'lines.*.children.*.cost_code' => 'nullable|string|max:255',
        'lines.*.children.*.notes' => 'nullable|string',
    ];

    public function index(Request $request)
    {
        $q = PurchaseOrder::jobType()->with(['supplier', 'job']);
        if (($ids = \App\Services\Pm\PmJobScope::visibleIds($request->user())) !== null) {
            $q->whereIn('business_job_id', $ids);
        }
        if ($request->filled('business_job_id')) {
            $q->where('business_job_id', $request->business_job_id);
        }
        if ($request->filled('issue_stage')) {
            $q->where('issue_stage', $request->issue_stage);
        }
        if ($request->filled('supplier_id')) {
            $q->where('supplier_id', $request->supplier_id);
        }
        if (! $request->boolean('include_voided')) {
            $q->whereNull('voided_at');
        }

        return response()->json($q->orderByDesc('order_date')->orderByDesc('id')->paginate((int) $request->get('per_page', 50)));
    }

    public function show(PurchaseOrder $jobPurchaseOrder)
    {
        return response()->json($this->present($this->job($jobPurchaseOrder)));
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), array_merge([
            'po_number' => 'nullable|string|max:50|unique:purchase_orders,po_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'business_job_id' => 'required|exists:business_jobs,id',
            'issue_stage' => 'nullable|in:'.implode(',', PurchaseOrder::ISSUE_STAGES),
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'cost_code' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ], self::LINE_RULES));
        if ($v->fails()) {
            return $this->invalid($v);
        }
        if ($err = $this->rejectInternalVendor($request->supplier_id)) {
            return $err;
        }
        \App\Services\Pm\PmJobScope::find($request->user(), (int) $request->business_job_id); // 404 for jobs outside your scope

        $po = DB::transaction(function () use ($request) {
            $stage = $request->input('issue_stage', 'draft');
            $po = PurchaseOrder::create([
                'po_number' => $request->filled('po_number') ? trim($request->po_number) : PurchaseOrder::generatePoNumber(),
                'supplier_id' => $request->supplier_id,
                'type' => PurchaseOrder::TYPE_JOB,
                'business_job_id' => $request->business_job_id,
                'issue_stage' => $stage,
                'status' => $stage === 'draft' ? 'draft' : 'approved',
                'order_date' => $request->order_date,
                'expected_date' => $request->expected_date,
                'cost_code' => $request->cost_code,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);
            $this->writeLines($po, $request->input('lines'));

            return $po;
        });

        return response()->json(['message' => 'Job purchase order created', 'purchase_order' => $this->present($this->job($po))], 201);
    }

    public function update(Request $request, PurchaseOrder $jobPurchaseOrder)
    {
        if ($blocked = $this->blocked($jobPurchaseOrder)) {
            return $blocked;
        }
        $v = Validator::make($request->all(), array_merge([
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'business_job_id' => 'sometimes|exists:business_jobs,id',
            'order_date' => 'sometimes|date',
            'expected_date' => 'nullable|date',
            'cost_code' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ], ['lines' => 'sometimes'] + array_map(fn ($r) => str_replace('required|', 'sometimes|', $r), self::LINE_RULES)));
        if ($v->fails()) {
            return $this->invalid($v);
        }
        if ($request->filled('supplier_id') && ($err = $this->rejectInternalVendor($request->supplier_id))) {
            return $err;
        }

        DB::transaction(function () use ($request, $jobPurchaseOrder) {
            $jobPurchaseOrder->update($request->only(['supplier_id', 'business_job_id', 'order_date', 'expected_date', 'cost_code', 'notes']));
            if ($request->has('lines')) {
                if ($jobPurchaseOrder->receipts()->exists()) {
                    abort(response()->json(['message' => 'Lines cannot be replaced once material has been received'], 422));
                }
                $jobPurchaseOrder->items()->delete();
                $this->writeLines($jobPurchaseOrder, $request->input('lines'));
            }
        });

        return response()->json($this->present($this->job($jobPurchaseOrder->fresh())));
    }

    public function setStage(Request $request, PurchaseOrder $jobPurchaseOrder)
    {
        if ($blocked = $this->blocked($jobPurchaseOrder)) {
            return $blocked;
        }
        $v = Validator::make($request->all(), ['issue_stage' => 'required|in:'.implode(',', PurchaseOrder::ISSUE_STAGES)]);
        if ($v->fails()) {
            return $this->invalid($v);
        }
        $stage = $request->issue_stage;
        $jobPurchaseOrder->update([
            'issue_stage' => $stage,
            'status' => $stage === 'draft' ? 'draft' : ($jobPurchaseOrder->status === 'draft' ? 'approved' : $jobPurchaseOrder->status),
        ]);

        return response()->json($this->present($this->job($jobPurchaseOrder)));
    }

    /** Snapshot the current lines, then bump the revision number. */
    public function revise(PurchaseOrder $jobPurchaseOrder)
    {
        if ($blocked = $this->blocked($jobPurchaseOrder)) {
            return $blocked;
        }
        DB::transaction(function () use ($jobPurchaseOrder) {
            $po = $this->job($jobPurchaseOrder);
            $revision = $po->revisions()->create([
                'revision_number' => $po->revision_number,
                'supplier_id' => $po->supplier_id,
                'issue_stage' => $po->issue_stage,
                'expected_date' => $po->expected_date,
                'cost_code' => $po->cost_code,
                'notes' => $po->notes,
                'total_amount' => $po->total_amount,
                'created_by' => auth()->id(),
            ]);
            foreach ($po->items as $i => $group) {
                $parent = $revision->lines()->create($this->revisionLine($group, $i));
                foreach ($group->children as $j => $child) {
                    $revision->lines()->create($this->revisionLine($child, $j) + ['parent_line_id' => $parent->id]);
                }
            }
            $po->increment('revision_number');
        });

        return response()->json($this->present($this->job($jobPurchaseOrder->fresh())));
    }

    /** Record that material arrived. Never touches stock or inventory locations. */
    public function receive(Request $request, PurchaseOrder $jobPurchaseOrder)
    {
        if ($blocked = $this->blocked($jobPurchaseOrder)) {
            return $blocked;
        }
        if ($jobPurchaseOrder->issue_stage === 'draft') {
            return response()->json(['message' => 'Order has not been issued yet'], 422);
        }
        $v = Validator::make($request->all(), [
            'received_date' => 'nullable|date',
            'receipts' => 'required|array|min:1',
            'receipts.*.item_id' => 'required|integer',
            'receipts.*.quantity' => 'required|integer|min:1',
            'receipts.*.flagged' => 'boolean',
            'receipts.*.notes' => 'nullable|string',
        ]);
        if ($v->fails()) {
            return $this->invalid($v);
        }

        DB::transaction(function () use ($request, $jobPurchaseOrder) {
            foreach ($request->receipts as $r) {
                $item = PurchaseOrderItem::where('purchase_order_id', $jobPurchaseOrder->id)
                    ->whereDoesntHave('children')->lockForUpdate()->find($r['item_id']);
                if (! $item) {
                    abort(response()->json(['message' => "Line {$r['item_id']} is not a receivable line on this order"], 422));
                }
                $over = ($item->quantity_received + $r['quantity']) > $item->quantity_ordered;
                $jobPurchaseOrder->receipts()->create([
                    'purchase_order_item_id' => $item->id,
                    'quantity' => $r['quantity'],
                    'received_date' => $request->input('received_date', now()->toDateString()),
                    'received_by' => auth()->id(),
                    'flagged' => ($r['flagged'] ?? false) || $over,
                    'notes' => $r['notes'] ?? null,
                ]);
                $item->quantity_received += $r['quantity'];
                $item->save();
            }

            $leaves = $jobPurchaseOrder->items()->whereDoesntHave('children')->get();
            $all = $leaves->every(fn ($i) => $i->quantity_received >= $i->quantity_ordered);
            $any = $leaves->contains(fn ($i) => $i->quantity_received > 0);
            $jobPurchaseOrder->update(['status' => $all ? 'received' : ($any ? 'partially_received' : $jobPurchaseOrder->status)]);
            if ($all) {
                $jobPurchaseOrder->update(['received_date' => now()->toDateString()]);
            }
        });

        return response()->json($this->present($this->job($jobPurchaseOrder->fresh())));
    }

    public function close(PurchaseOrder $jobPurchaseOrder)
    {
        if ($blocked = $this->blocked($jobPurchaseOrder)) {
            return $blocked;
        }
        $jobPurchaseOrder->update(['closed_at' => now(), 'closed_by' => auth()->id()]);

        return response()->json($this->present($this->job($jobPurchaseOrder)));
    }

    public function reopen(PurchaseOrder $jobPurchaseOrder)
    {
        $jobPurchaseOrder->update(['closed_at' => null, 'closed_by' => null]);

        return response()->json($this->present($this->job($jobPurchaseOrder)));
    }

    /** A real cancel state with a required reason, not a delete. */
    public function void(Request $request, PurchaseOrder $jobPurchaseOrder)
    {
        $v = Validator::make($request->all(), ['reason' => 'required|string|max:1000']);
        if ($v->fails()) {
            return $this->invalid($v);
        }
        if ($jobPurchaseOrder->isVoided()) {
            return response()->json(['message' => 'Already voided'], 422);
        }
        $jobPurchaseOrder->update([
            'voided_at' => now(), 'voided_by' => auth()->id(), 'void_reason' => $request->reason, 'status' => 'cancelled',
        ]);

        return response()->json($this->present($this->job($jobPurchaseOrder)));
    }

    // ---- helpers -----------------------------------------------------------

    private function job(PurchaseOrder $po): PurchaseOrder
    {
        abort_unless($po->isJob(), 404);
        $ids = \App\Services\Pm\PmJobScope::visibleIds(auth()->user());
        abort_if($ids !== null && ! in_array($po->business_job_id, $ids, true), 404);

        return $po->load(['supplier', 'job', 'items' => fn ($q) => $q->whereNull('parent_item_id')->orderBy('sort_order')->orderBy('id'),
            'items.children', 'receipts', 'revisions.lines']);
    }

    private function revisionLine(PurchaseOrderItem $i, int $order): array
    {
        return [
            'sort_order' => $order, 'description' => $i->description, 'kind' => $i->kind,
            'quantity_ordered' => $i->quantity_ordered, 'unit_cost' => $i->unit_cost,
            'total_cost' => $i->total_cost, 'cost_code' => $i->cost_code, 'notes' => $i->notes,
        ];
    }

    private function present(PurchaseOrder $po): array
    {
        $data = $po->toArray();
        $data['lines'] = $data['items'];
        unset($data['items']);

        return $data;
    }

    private function blocked(PurchaseOrder $po)
    {
        abort_unless($po->isJob(), 404);
        $ids = \App\Services\Pm\PmJobScope::visibleIds(auth()->user());
        abort_if($ids !== null && ! in_array($po->business_job_id, $ids, true), 404);
        if ($po->isVoided()) {
            return response()->json(['message' => 'Voided orders cannot be changed'], 422);
        }
        if ($po->closed_at) {
            return response()->json(['message' => 'Closed orders cannot be changed; reopen it first'], 422);
        }

        return null;
    }

    private function rejectInternalVendor($supplierId)
    {
        if (\App\Models\Supplier::whereKey($supplierId)->where('is_internal', true)->exists()) {
            return response()->json(['message' => 'Internal vendors are used for internal purchase orders, not job purchase orders'], 422);
        }

        return null;
    }

    private function invalid($validator)
    {
        return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
    }

    /** Top-level lines may be groups (with children); only leaf lines carry price and receiving. */
    private function writeLines(PurchaseOrder $po, array $lines): void
    {
        $total = 0;
        foreach (array_values($lines) as $i => $line) {
            $children = $line['children'] ?? [];
            $parent = $po->items()->create([
                'description' => $line['description'],
                'kind' => $line['kind'] ?? null,
                'sort_order' => $i,
                'quantity_ordered' => $children ? 0 : (int) ($line['quantity'] ?? 0),
                'quantity_received' => 0,
                'unit_cost' => $children ? 0 : ($line['unit_cost'] ?? 0),
                'total_cost' => $children ? 0 : round(($line['quantity'] ?? 0) * ($line['unit_cost'] ?? 0), 2),
                'cost_code' => $line['cost_code'] ?? $po->cost_code,
                'notes' => $line['notes'] ?? null,
            ]);
            if (! $children) {
                $total += $parent->total_cost;
            }
            foreach (array_values($children) as $j => $child) {
                $c = $po->items()->create([
                    'parent_item_id' => $parent->id,
                    'description' => $child['description'],
                    'kind' => $child['kind'] ?? null,
                    'sort_order' => $j,
                    'quantity_ordered' => (int) $child['quantity'],
                    'quantity_received' => 0,
                    'unit_cost' => $child['unit_cost'],
                    'total_cost' => round($child['quantity'] * $child['unit_cost'], 2),
                    'cost_code' => $child['cost_code'] ?? $line['cost_code'] ?? $po->cost_code,
                    'notes' => $child['notes'] ?? null,
                ]);
                $total += $c->total_cost;
            }
        }
        $po->update(['total_amount' => $total]);
    }
}
