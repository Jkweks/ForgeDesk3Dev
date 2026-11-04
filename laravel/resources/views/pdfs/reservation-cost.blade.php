<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reservation Cost {{ $data['reservation']['job_number'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10px;
            color: #222;
            margin: 24px;
        }
        h1, h2, h3 { margin: 0; }

        .masthead { width: 100%; margin-bottom: 14px; }
        .masthead td { vertical-align: top; }
        .doc-title { font-size: 20px; font-weight: bold; letter-spacing: 1px; color: #1a1a1a; }
        .doc-sub { color: #555; margin-top: 3px; }
        .doc-facts { text-align: right; color: #444; line-height: 1.6; }
        .doc-facts strong { color: #111; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #eef2ff; color: #3949ab; font-size: 9px; }

        .facts-panel { border: 1px solid #ccc; border-top: 3px solid #333; padding: 8px 10px; margin: 10px 0 16px; }
        .facts-panel table { width: 100%; }
        .facts-panel td { padding: 2px 8px 2px 0; }
        .facts-label { font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: #777; }

        .section-title {
            font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px;
            color: #fff; background: #333; padding: 5px 8px; margin-top: 18px;
        }
        table.items { width: 100%; border-collapse: collapse; margin-top: 0; }
        table.items th {
            background: #eee; color: #333; font-size: 8px; text-transform: uppercase;
            letter-spacing: .5px; padding: 5px 6px; text-align: left; border-bottom: 2px solid #ccc;
        }
        table.items td { padding: 5px 6px; border-bottom: 1px solid #ddd; vertical-align: top; }
        table.items tr:nth-child(even) td { background: #f7f7f7; }
        .num { text-align: right; white-space: nowrap; }
        .pn { font-family: "DejaVu Sans Mono", monospace; font-weight: bold; }
        .muted { color: #777; }
        .empty-note { padding: 8px 6px; color: #888; font-style: italic; }

        .footer { margin-top: 26px; padding-top: 8px; border-top: 1px solid #ccc; color: #888; font-size: 8px; text-align: center; }
            .total-row td { border-top: 2px solid #333; font-weight: bold; font-size: 11px; background: #fff !important; }
        .warn { margin-top: 8px; padding: 6px 8px; background: #fff7e0; border: 1px solid #f0c36d; color: #7a5200; }
        .kv { width: 100%; margin-top: 0; border-collapse: collapse; }
        .kv td { padding: 3px 6px; border-bottom: 1px solid #eee; width: 25%; }
        .kv td.k { color: #777; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; width: 15%; }
    </style>
</head>
<body>
@php $r = $data['reservation']; $money = fn ($v, $dp = 2) => $v === null ? '—' : '$'.number_format($v, $dp); @endphp

<table class="masthead">
    <tr>
        <td style="width:60%;">
            <div class="doc-title">RESERVATION COST SUMMARY</div>
            <div class="doc-sub">{{ $r['job_number'] }} — {{ $r['job_name'] }}</div>
        </td>
        <td style="width:40%;" class="doc-facts">
            <div><strong>Reservation #{{ $r['id'] }}@if($r['release_number']) &middot; Release {{ $r['release_number'] }}@endif</strong></div>
            <div>Status: <span class="badge">{{ ucwords(str_replace('_', ' ', $r['status'])) }}</span></div>
            <div>{{ now()->format('M j, Y') }}</div>
        </td>
    </tr>
</table>

<div class="section-title">Material Cost</div>
<table class="items">
    <thead>
        <tr><th>Part</th><th>Description</th><th class="num">Qty</th><th class="num">Net price</th><th class="num">Extended</th><th class="num">Consumed</th></tr>
    </thead>
    <tbody>
        @forelse ($data['rows'] as $line)
            <tr>
                <td class="pn">{{ $line['part_number'] }}@if($line['finish'])-{{ $line['finish'] }}@endif</td>
                <td class="muted">{{ $line['description'] }}</td>
                <td class="num">{{ $line['quantity'] }}</td>
                <td class="num">
                    @if ($line['net_price'] > 0)
                        {{ $money($line['net_price'], 4) }}
                        @if ($line['pack_size'] > 1)<div class="muted">{{ $money($line['pack_net_price']) }} / pack of {{ $line['pack_size'] }}</div>@endif
                    @else
                        <span class="muted">no net price</span>
                    @endif
                </td>
                <td class="num">{{ $money($line['extended']) }}</td>
                <td class="num">{{ $line['consumed_qty'] > 0 ? $line['consumed_qty'].' ('.$money($line['consumed_extended']).')' : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-note">No line items.</td></tr>
        @endforelse
        <tr class="total-row"><td colspan="4" class="num">Material total</td><td class="num">{{ $money($data['total']) }}</td><td class="num">{{ $data['consumed_total'] > 0 ? $money($data['consumed_total']) : '—' }}</td></tr>
    </tbody>
</table>
@if ($data['missing_price'])
    <div class="warn">{{ $data['missing_price'] }} item(s) have no net price and are counted at $0 — the total is understated.</div>
@endif
<div class="muted" style="margin-top:6px;">Requested quantity x each item's Net Price (per piece for pack items).</div>

<div class="footer">Generated by ForgeDesk — {{ now()->format('M j, Y g:i A') }}</div>
</body>
</html>
