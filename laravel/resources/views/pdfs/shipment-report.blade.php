<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shipment {{ $shipment->shipment_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #222; margin: 18px; }
        h1 { font-size: 18px; margin: 0; } h2 { font-size: 13px; margin: 16px 0 4px; border-bottom: 1px solid #999; padding-bottom: 2px; }
        h3 { font-size: 11px; margin: 10px 0 3px; color: #444; }
        .muted { color: #777; } .head { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; } td, th { padding: 3px 5px; vertical-align: top; text-align: left; }
        table.kv td:first-child { width: 90px; color: #666; }
        table.items td, table.items th { border: 1px solid #ddd; } table.items th { background: #eee; font-size: 9px; }
        .b { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .pass { background: #d9f2e1; color: #1b6b3a; } .fail { background: #f9d9d5; color: #a1281b; }
        .warn { background: #fdecc8; color: #8a5a00; } .na { background: #e6e6e6; color: #555; }
        .thumbs { margin-top: 3px; } .thumb { display: inline-block; width: 118px; margin: 0 6px 6px 0; vertical-align: top; }
        .thumb img { width: 112px; border: 1px solid #ccc; } .thumb a { font-size: 8px; color: #1d4e89; }
        .insp { page-break-inside: auto; } .footer { margin-top: 14px; font-size: 8px; color: #888; text-align: center; }
    </style>
</head>
<body>
@php
    $res = fn ($r) => ['pass' => ['Pass', 'pass'], 'pass_with_issues' => ['Pass with issues', 'warn'], 'fail' => ['Fail', 'fail']][$r] ?? [$r, 'na'];
    $statusLabel = ucwords(str_replace('_', ' ', $shipment->status));
    $thumb = function ($p) use ($thumbs) {
        [$src, $url] = $thumbs[$p->id] ?? [null, null];
        return [$src, $url];
    };
@endphp
<div class="head">
    <h1>Shipment {{ $shipment->shipment_number }} <span class="b {{ in_array($shipment->status, ['delivered']) ? 'pass' : (in_array($shipment->status, ['delivered_issues', 'cancelled']) ? 'fail' : 'na') }}">{{ $statusLabel }}</span></h1>
    <div class="muted">Report generated {{ now()->format('M j, Y g:i A') }}</div>
</div>

<table class="kv">
    @if ($shipment->status === 'cancelled')<tr><td>Cancelled</td><td>{{ $shipment->cancel_reason }}@if ($shipment->cancelled_at) <br>{{ $shipment->canceller?->name }} {{ $shipment->cancelled_at->format('Y-m-d') }}@endif</td></tr>@endif
    <tr><td>Pickup</td><td>{{ $shipment->pickupLocation?->name ?? '—' }}@if ($shipment->pickup_address) · {{ $shipment->pickup_address }}@endif</td></tr>
    <tr><td>Drop off</td><td>{{ $shipment->dropoffLocation?->name ?? '—' }}@if ($shipment->dropoff_address) · {{ $shipment->dropoff_address }}@endif</td></tr>
    <tr><td>{{ $shipment->jobs->count() + count($shipment->custom_jobs ?? []) > 1 ? 'Jobs' : 'Job' }}</td><td>@forelse ($shipment->jobs as $j){{ $j->job_name }} ({{ $j->job_number }})@if (! $loop->last)<br>@endif @empty @if (empty($shipment->custom_jobs)) — @endif @endforelse@foreach ($shipment->custom_jobs ?? [] as $cj)@if ($shipment->jobs->isNotEmpty() || ! $loop->first)<br>@endif{{ $cj }}@endforeach</td></tr>
    <tr><td>Ship date</td><td>{{ $shipment->ship_date?->format('M j, Y') ?? '—' }}</td></tr>
    <tr><td>Carrier</td><td>{{ $shipment->carrier ?: '—' }}</td></tr>
    <tr><td>Destination</td><td>{{ $shipment->destination ?: '—' }}</td></tr>
    <tr><td>Loads</td><td>@foreach ($shipment->loads as $l){{ $l->type->name }}@if ($l->piece_count) × {{ $l->piece_count }}@endif{{ $l->description ? ' ('.$l->description.')' : '' }}@if (! $loop->last), @endif @endforeach</td></tr>
    @if ($shipment->notes)<tr><td>Notes</td><td>{{ $shipment->notes }}</td></tr>@endif
</table>

@forelse ($inspections as $n)
    @php [$label, $cls] = $res($n->result); $byType = $n->items->groupBy(fn ($i) => $i->type_name ?: 'Other'); @endphp
    <div class="insp">
        <h2>{{ $n->phase === 'pre' ? 'Pre-shipment inspection' : 'Post-delivery inspection' }} <span class="b {{ $cls }}">{{ $label }}</span></h2>
        <div class="muted">{{ $n->performer?->name ?? $n->submitted_by_external ?? 'Unknown' }} · {{ $n->performed_at->format('M j, Y g:i A') }}{{ $n->source === 'field_app' ? ' · field app' : '' }}</div>

        @foreach ($byType as $typeName => $items)
            <h3>{{ $typeName }}</h3>
            <table class="items">
                <thead><tr><th style="width:38%">Item</th><th style="width:16%">Result</th><th>Comment / photos</th></tr></thead>
                <tbody>
                @foreach ($items as $i)
                    <tr>
                        <td>{{ $i->label }}@if ($i->critical) <span class="muted">(critical)</span>@endif</td>
                        <td>
                            @if ($i->input_type === 'pass_fail')
                                <span class="b {{ ['pass' => 'pass', 'fail' => 'fail'][$i->value] ?? 'na' }}">{{ $i->value === 'na' ? 'N/A' : ucfirst($i->value) }}</span>
                            @elseif ($i->input_type === 'photo')
                                {{ $i->value }} photo{{ $i->value === '1' ? '' : 's' }}
                            @else
                                {{ $i->value }}{{ $i->unit ? ' '.$i->unit : '' }}
                            @endif
                        </td>
                        <td>
                            {{ $i->comment }}
                            @if ($i->photos->isNotEmpty())
                                <div class="thumbs">
                                @foreach ($i->photos as $p)
                                    @php [$src, $url] = $thumb($p); @endphp
                                    <div class="thumb">
                                        @if ($src)<a href="{{ $url }}"><img src="{{ $src }}"></a><br>@endif
                                        <a href="{{ $url }}">Full size{{ $src ? '' : ' (preview unavailable)' }}</a>
                                    </div>
                                @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endforeach

        @php $general = $n->photos->whereNull('item_id'); @endphp
        @if ($general->isNotEmpty())
            <h3>Other photos</h3>
            <div class="thumbs">
            @foreach ($general as $p)
                @php [$src, $url] = $thumb($p); @endphp
                <div class="thumb">
                    @if ($src)<a href="{{ $url }}"><img src="{{ $src }}"></a><br>@endif
                    <a href="{{ $url }}">Full size{{ $src ? '' : ' (preview unavailable)' }}</a>
                </div>
            @endforeach
            </div>
        @endif
        @if ($n->notes)<p><b>Notes:</b> {{ $n->notes }}</p>@endif
    </div>
@empty
    <p class="muted" style="margin-top:14px">No inspections recorded yet.</p>
@endforelse

<div class="footer">ForgeDesk · {{ $shipment->shipment_number }} · Photo links open the full-size image and expire after 90 days.</div>
</body>
</html>
