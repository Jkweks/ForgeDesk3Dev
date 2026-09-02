<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $po->po_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10px;
            color: #222;
            margin: 24px;
        }
        h1, h2, h3 { margin: 0; }
        .clearfix::after { content: ""; display: table; clear: both; }

        /* ---- masthead ---- */
        .masthead { width: 100%; margin-bottom: 14px; }
        .masthead td { vertical-align: top; }
        .company-name { font-size: 16px; font-weight: bold; color: #1a1a1a; }
        .company-meta { color: #555; line-height: 1.45; margin-top: 3px; white-space: pre-line; }
        .doc-title {
            text-align: right;
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #1a1a1a;
        }
        .doc-facts { text-align: right; margin-top: 6px; color: #444; line-height: 1.5; }
        .doc-facts strong { color: #111; }

        /* ---- address panels ---- */
        .panels { width: 100%; border-collapse: collapse; margin: 6px 0 14px; }
        .panels td { width: 33.33%; vertical-align: top; padding: 0 6px; }
        .panels td:first-child { padding-left: 0; }
        .panels td:last-child { padding-right: 0; }
        .panel {
            border: 1px solid #ccc;
            border-top: 3px solid #333;
            padding: 8px 10px;
            min-height: 92px;
        }
        .panel-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #666;
            margin-bottom: 4px;
        }
        .panel .name { font-weight: bold; }
        .panel .body { color: #444; line-height: 1.45; white-space: pre-line; }

        /* ---- line items ---- */
        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items th {
            background: #333;
            color: #fff;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 6px 6px;
            text-align: left;
        }
        table.items td { padding: 6px 6px; border-bottom: 1px solid #ddd; vertical-align: top; }
        table.items tr:nth-child(even) td { background: #f7f7f7; }
        .num { text-align: right; white-space: nowrap; }
        .sku { font-family: "DejaVu Sans Mono", monospace; font-weight: bold; }
        .muted { color: #777; }
        .sub { font-size: 8px; color: #777; }

        /* ---- totals ---- */
        .totals { width: 40%; margin-left: 60%; margin-top: 10px; border-collapse: collapse; }
        .totals td { padding: 4px 6px; }
        .totals .label { text-align: right; color: #555; }
        .totals .val { text-align: right; white-space: nowrap; }
        .totals .grand td { border-top: 2px solid #333; font-weight: bold; font-size: 12px; }

        .notes { margin-top: 18px; }
        .notes .panel-label { margin-bottom: 3px; }
        .notes .body { border: 1px solid #ddd; padding: 8px 10px; color: #444; white-space: pre-line; min-height: 34px; }

        .footer { margin-top: 26px; padding-top: 8px; border-top: 1px solid #ccc; color: #888; font-size: 8px; text-align: center; }
    </style>
</head>
<body>

@php
    $addr = function ($parts) { return implode("\n", array_filter(array_map('trim', $parts))); };
    $cityLine = function ($c, $s, $z) {
        return trim(implode(', ', array_filter([$c, trim(($s ?? '') . ' ' . ($z ?? ''))])));
    };
@endphp

{{-- ============ MASTHEAD ============ --}}
<table class="masthead">
    <tr>
        <td style="width:60%;">
            <div class="company-name">{{ $company->name ?? config('app.name') }}</div>
            <div class="company-meta">{{ $addr([
                $company->address_line1 ?? null,
                $company->address_line2 ?? null,
                $cityLine($company->city ?? null, $company->state ?? null, $company->zip ?? null),
                ($company && $company->phone) ? 'Phone: ' . $company->phone : null,
                ($company && $company->fax) ? 'Fax: ' . $company->fax : null,
                ($company && $company->email) ? $company->email : null,
            ]) }}</div>
        </td>
        <td style="width:40%;">
            <div class="doc-title">PURCHASE ORDER</div>
            <div class="doc-facts">
                <div><strong>PO #:</strong> {{ $po->po_number }}</div>
                <div><strong>Order date:</strong> {{ optional($po->order_date)->format('M j, Y') ?: '—' }}</div>
                <div><strong>Expected:</strong> {{ optional($po->expected_date)->format('M j, Y') ?: '—' }}</div>
                <div><strong>Status:</strong> {{ ucwords(str_replace('_', ' ', $po->status)) }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- ============ ADDRESS PANELS ============ --}}
<table class="panels">
    <tr>
        <td>
            <div class="panel">
                <div class="panel-label">Order From (Supplier)</div>
                @if ($po->supplier)
                    <div class="name">{{ $po->supplier->name }}</div>
                    <div class="body">{{ $addr([
                        $po->supplier->contact_name,
                        $po->supplier->address,
                        $cityLine($po->supplier->city, $po->supplier->state, $po->supplier->zip),
                        ($po->supplier->country && strtoupper($po->supplier->country) !== 'USA') ? $po->supplier->country : null,
                        $po->supplier->contact_phone ? 'Phone: ' . $po->supplier->contact_phone : null,
                        $po->supplier->fax ? 'Fax: ' . $po->supplier->fax : null,
                        $po->supplier->contact_email ?: null,
                    ]) }}</div>
                @else
                    <div class="muted">No supplier on file</div>
                @endif
            </div>
        </td>
        <td>
            <div class="panel">
                <div class="panel-label">Order To / Bill To</div>
                @if ($company)
                    <div class="name">{{ $company->name }}</div>
                    <div class="body">{{ $addr([
                        $company->address_line1,
                        $company->address_line2,
                        $cityLine($company->city, $company->state, $company->zip),
                        $company->phone ? 'Phone: ' . $company->phone : null,
                        $company->fax ? 'Fax: ' . $company->fax : null,
                    ]) }}</div>
                @else
                    <div class="muted">Set a primary company location in Admin → System Settings</div>
                @endif
            </div>
        </td>
        <td>
            <div class="panel">
                <div class="panel-label">Ship To</div>
                @if ($shipTo)
                    <div class="name">{{ $shipTo->name }}</div>
                    <div class="body">{{ $addr([
                        $shipTo->address_line1,
                        $shipTo->address_line2,
                        $cityLine($shipTo->city, $shipTo->state, $shipTo->zip),
                        $shipTo->phone ? 'Phone: ' . $shipTo->phone : null,
                    ]) }}</div>
                @elseif ($po->ship_to)
                    <div class="body">{{ $po->ship_to }}</div>
                @else
                    <div class="muted">Same as Order To</div>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ============ LINE ITEMS ============ --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:26px;">#</th>
            <th style="width:70px;" class="num">Qty</th>
            <th style="width:130px;">SKU / Color</th>
            <th>Description</th>
            <th style="width:78px;" class="num">Unit Price</th>
            <th style="width:82px;" class="num">Line Price</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($po->items as $i)
            @php
                $p = $i->product;
                $qty = (int) $i->quantity_ordered;
                $packSize = $p && $p->pack_size ? (int) $p->pack_size : 1;
                $unit = (float) $i->unit_cost;
                $line = $unit * $qty;
                $colorCode = $p->finish ?? null;
                $colorName = $p ? ($p->finish_name ?? null) : null;
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="num">
                    {{ number_format($qty) }}
                    @if ($packSize > 1)
                        <div class="sub">{{ $packSize }}/pack &middot; {{ number_format($qty * $packSize) }} ea</div>
                    @endif
                </td>
                <td>
                    <span class="sku">{{ $p->sku ?? '—' }}</span>
                    @if ($colorCode)
                        <div class="sub">Color {{ $colorCode }}@if ($colorName && $colorName !== $colorCode) — {{ $colorName }}@endif</div>
                    @endif
                </td>
                <td>
                    {{ $p->description ?? $i->notes ?? '—' }}
                    @if ($p && $p->part_number && $p->part_number !== $p->sku)
                        <div class="sub">Part {{ $p->part_number }}</div>
                    @endif
                    @if ($i->destination_location)
                        <div class="sub">Deliver to: {{ $i->destination_location }}</div>
                    @endif
                </td>
                <td class="num">${{ number_format($unit, 2) }}</td>
                <td class="num">${{ number_format($line, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted" style="text-align:center; padding:16px;">No line items on this purchase order.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ============ TOTALS ============ --}}
<table class="totals">
    <tr>
        <td class="label">Subtotal</td>
        <td class="val">${{ number_format($subtotal, 2) }}</td>
    </tr>
    <tr class="grand">
        <td class="label">Total ({{ $po->items->count() }} {{ \Illuminate\Support\Str::plural('line', $po->items->count()) }})</td>
        <td class="val">${{ number_format($subtotal, 2) }}</td>
    </tr>
</table>

{{-- ============ NOTES / CONTACT ============ --}}
@if ($po->notes || $po->contact_name || $po->contact_email || $po->contact_phone)
    <div class="notes clearfix">
        <div class="panel-label">Notes</div>
        <div class="body">{{ $po->notes ?: '—' }}@if ($po->contact_name || $po->contact_email || $po->contact_phone)

Buyer contact: {{ trim(($po->contact_name ?? '') . '  ' . ($po->contact_phone ?? '') . '  ' . ($po->contact_email ?? '')) }}@endif</div>
    </div>
@endif

<div class="footer">
    {{ $company->name ?? config('app.name') }} &middot; PO {{ $po->po_number }} &middot;
    Prepared {{ now()->format('M j, Y g:i A') }}@if ($po->creator) by {{ $po->creator->name }}@endif
</div>

</body>
</html>
