<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Api\Reports\Concerns\GeneratesCsv;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Shipping summary across shipments (per-shipment PDFs live on ShipmentController::report). */
class ShippingReportsController extends Controller
{
    use GeneratesCsv;

    /** One row per shipment in the window, with inspection outcomes and a summary block. */
    public function shippingSummaryReport(Request $request)
    {
        abort_unless($request->user()?->hasPermission('shipments.view'), 403, 'You do not have access to shipments.');
        $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'job_id' => 'nullable|integer', 'status' => 'nullable|string|max:30',
        ]);
        $from = Carbon::parse($request->query('from', now()->subDays(30)))->toDateString();
        $to = Carbon::parse($request->query('to', now()))->toDateString();

        $q = Shipment::with(['jobs:id,job_number,job_name', 'loads.type:id,name', 'inspections.items', 'inspections.performer:id,name'])
            ->whereBetween('ship_date', [$from, $to])->orderBy('ship_date')->orderBy('shipment_number');
        if ($request->filled('status')) {
            $q->whereIn('status', array_filter(explode(',', (string) $request->query('status'))));
        }
        if ($request->filled('job_id')) {
            $q->whereHas('jobs', fn ($j) => $j->where('business_jobs.id', (int) $request->query('job_id')));
        }

        $rows = $q->get()->map(function (Shipment $s) {
            $pre = $s->inspections->where('phase', 'pre')->sortByDesc('performed_at')->first();
            $post = $s->inspections->where('phase', 'post')->sortByDesc('performed_at')->first();

            return [
                'id' => $s->id, 'shipment_number' => $s->shipment_number, 'status' => $s->status,
                'ship_date' => $s->ship_date?->format('Y-m-d'), 'destination' => $s->destination, 'carrier' => $s->carrier,
                'jobs' => $s->jobs->map(fn ($j) => trim($j->job_number.' '.$j->job_name))->implode('; '),
                'loads' => $s->loads->map(fn ($l) => $l->type->name.($l->piece_count ? ' ×'.$l->piece_count : ''))->implode(', '),
                'pre_result' => $pre?->result, 'pre_by' => $pre ? ($pre->performer?->name ?? $pre->submitted_by_external) : null,
                'post_result' => $post?->result, 'post_by' => $post ? ($post->performer?->name ?? $post->submitted_by_external) : null,
                'post_at' => $post?->performed_at?->format('Y-m-d H:i'),
                'delivery_issues' => $post ? $post->items->where('result', 'fail')->pluck('label')->implode('; ') : '',
            ];
        })->values();

        $live = $rows->where('status', '!=', 'cancelled');

        return response()->json([
            'from' => $from, 'to' => $to, 'shipments' => $rows,
            'summary' => [
                'total' => $live->count(),
                'cancelled' => $rows->where('status', 'cancelled')->count(),
                'in_transit' => $live->where('status', 'shipped')->count(),
                'delivered' => $live->whereIn('status', ['delivered', 'delivered_issues'])->count(),
                'delivered_issues' => $live->where('status', 'delivered_issues')->count(),
                'pre_failed' => $live->where('pre_result', 'fail')->count(),
                'not_pre_checked' => $live->whereIn('status', ['draft'])->count(),
            ],
        ]);
    }

    public function shippingSummaryPdf(Request $request)
    {
        $data = $this->shippingSummaryReport($request)->original;
        $pdf = Pdf::loadView('pdfs.shipping-summary-report', $data)->setPaper('letter', 'landscape');

        return $pdf->stream('shipping-summary-'.date('Y-m-d').'.pdf');
    }

    public function csvExport(string $type, Request $request)
    {
        if ($type !== 'shipping_summary') {
            return null;
        }
        $rows = collect($this->shippingSummaryReport($request)->original['shipments'])->map(fn ($r) => [
            $r['shipment_number'], $r['status'], $r['ship_date'], $r['jobs'], $r['destination'], $r['carrier'], $r['loads'],
            $r['pre_result'], $r['pre_by'], $r['post_result'], $r['post_by'], $r['post_at'], $r['delivery_issues'],
        ]);

        return $this->generateCSV($rows, 'shipping_summary', ['Shipment', 'Status', 'Ship Date', 'Jobs', 'Destination', 'Carrier', 'Loads',
            'Pre-check', 'Pre-check By', 'Delivery Check', 'Delivery Check By', 'Delivery Checked At', 'Delivery Issues']);
    }
}
