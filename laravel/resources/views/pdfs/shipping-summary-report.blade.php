<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shipping Summary</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 18px; color: #333; }
        .header { text-align: center; margin-bottom: 14px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        .header h1 { margin: 0 0 4px; font-size: 18px; } .header h2 { margin: 0; font-size: 11px; color: #666; font-weight: normal; }
        .stats { background: #f0f0f0; border: 1px solid #ccc; margin-bottom: 12px; width: 100%; border-collapse: collapse; }
        .stats td { padding: 6px; text-align: center; } .stats .l { font-size: 7px; font-weight: bold; color: #555; } .stats .v { font-size: 15px; font-weight: bold; }
        table.t { width: 100%; border-collapse: collapse; } .t th { background: #666; color: #fff; padding: 4px; text-align: left; font-size: 8px; }
        .t td { padding: 4px; border: 1px solid #dee2e6; vertical-align: top; } .t tbody tr:nth-child(even) { background: #f8f9fa; }
        .pass { color: #1b6b3a; font-weight: bold; } .fail { color: #a1281b; font-weight: bold; } .warn { color: #8a5a00; font-weight: bold; }
        .footer { margin-top: 14px; font-size: 7px; color: #888; text-align: center; }
    </style>
</head>
<body>
@php
    $res = fn ($r) => ['pass' => ['Pass', 'pass'], 'pass_with_issues' => ['Issues', 'warn'], 'fail' => ['Fail', 'fail']][$r] ?? ['—', ''];
@endphp
<div class="header">
    <h1>SHIPPING SUMMARY</h1>
    <h2>Ship dates {{ $from }} to {{ $to }} · Generated {{ now()->format('F d, Y H:i') }}</h2>
</div>
<table class="stats"><tr>
    @foreach ([['SHIPMENTS', $summary['total']], ['IN TRANSIT', $summary['in_transit']], ['DELIVERED', $summary['delivered']], ['DELIVERED WITH ISSUES', $summary['delivered_issues']], ['FAILED PRE-CHECK', $summary['pre_failed']], ['CANCELLED', $summary['cancelled']]] as [$l, $v])
        <td><div class="l">{{ $l }}</div><div class="v">{{ $v }}</div></td>
    @endforeach
</tr></table>
<table class="t">
    <thead><tr><th>Shipment</th><th>Ship date</th><th>Job(s)</th><th>Destination</th><th>Loads</th><th>Status</th><th>Pre-check</th><th>Delivery check</th><th>Delivery issues</th></tr></thead>
    <tbody>
    @forelse ($shipments as $r)
        @php [$pl, $pc] = $res($r['pre_result']); [$dl, $dc] = $res($r['post_result']); @endphp
        <tr>
            <td><b>{{ $r['shipment_number'] }}</b></td><td>{{ $r['ship_date'] }}</td><td>{{ $r['jobs'] }}</td><td>{{ $r['destination'] }}</td><td>{{ $r['loads'] }}</td>
            <td>{{ ucwords(str_replace('_', ' ', $r['status'])) }}</td><td class="{{ $pc }}">{{ $pl }}</td><td class="{{ $dc }}">{{ $dl }}</td><td>{{ $r['delivery_issues'] }}</td>
        </tr>
    @empty
        <tr><td colspan="9" style="text-align:center;color:#888;padding:14px">No shipments in this range.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="footer">ForgeDesk shipping summary</div>
</body>
</html>
