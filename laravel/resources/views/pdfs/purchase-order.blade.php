<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $po->po_number }}</title>
    <style>
        @font-face {
            font-family: 'Calibri';
            src: url('{{ public_path('fonts/carlito/Carlito-Regular.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Calibri';
            src: url('{{ public_path('fonts/carlito/Carlito-Bold.ttf') }}') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Calibri';
            src: url('{{ public_path('fonts/carlito/Carlito-Italic.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: 'Calibri';
            src: url('{{ public_path('fonts/carlito/Carlito-BoldItalic.ttf') }}') format('truetype');
            font-weight: bold;
            font-style: italic;
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Calibri', 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #222;
            margin: 0.5in;
        }
        h1, h2, h3 { margin: 0; }

        /* ---- masthead ---- */
        .masthead { width: 100%; margin-bottom: 12px; }
        .masthead td { vertical-align: top; }
        .logo-cell { width: 90px; padding-right: 10px; }
        .logo-cell img { max-height: 60px; max-width: 90px; }
        .company-name { font-size: 13px; font-weight: bold; color: #1a1a1a; }
        .company-meta { color: #444; line-height: 1.15; margin-top: 1px; white-space: pre-line; }
        .doc-title {
            text-align: right;
            font-size: 22px;
            font-weight: bold;
            color: #1a1a1a;
        }
        .doc-facts { margin-top: 8px; color: #333; }
        .doc-facts table { margin-left: auto; border-collapse: collapse; }
        .doc-facts td { padding: 1px 0; line-height: 1.4; }
        .doc-facts .flabel { text-align: right; padding-right: 6px; white-space: nowrap; }
        .doc-facts .fval { text-align: left; white-space: nowrap; }

        /* ---- to / ship / ordered-by block ---- */
        .to-block { width: 100%; margin-bottom: 8px; }
        .to-block td { vertical-align: top; padding: 0; }
        .to-label { font-weight: bold; width: 34px; }
        .to-name { font-weight: bold; }
        .to-body { color: #333; line-height: 1.4; white-space: pre-line; }
        .side-label { font-weight: bold; }

        .rule { border: none; border-top: 1px solid #333; margin: 8px 0; }

        .memo-row { width: 100%; }
        .memo-row td { vertical-align: top; padding: 3px 0; }
        .memo-note { font-weight: bold; font-size: 9px; }
        .ordered-by { text-align: right; }
        .ordered-by .side-label { }

        /* ---- line items ---- */
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th {
            border-bottom: 1px solid #333;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 3px 6px;
            text-align: left;
        }
        table.items td { padding: 3px 6px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .pn { font-family: "DejaVu Sans Mono", monospace; white-space: nowrap; }
        .muted { color: #777; }
        .sub { font-size: 8px; color: #777; }

        .subtotal-row td { padding-top: 6px; font-weight: bold; }
        .subtotal-row .num { border-top: 1px solid #333; }

        /* ---- footer ---- */
        .footer-table { width: 100%; margin-top: 20px; }
        .footer-table td { vertical-align: bottom; }
        .terms { width: 62%; font-style: italic; color: #333; line-height: 1.4; font-size: 9px; }
        .totals-box { width: 38%; border: 1px solid #333; }
        .totals-box table { width: 100%; border-collapse: collapse; }
        .totals-box td { padding: 4px 8px; }
        .totals-box .label { color: #333; }
        .totals-box .val { text-align: right; white-space: nowrap; }
        .totals-box .grand { border-top: 1px solid #333; font-weight: bold; }

        .prepared { margin-top: 10px; color: #888; font-size: 8px; text-align: center; }
    </style>
</head>
<body>

@php
    $addr = function ($parts) { return implode("\n", array_filter(array_map('trim', $parts))); };
    $cityLine = function ($c, $s, $z) {
        return trim(implode(', ', array_filter([$c, trim(($s ?? '') . ' ' . ($z ?? ''))])));
    };
    // Only call out Ship-To separately when it's a real, distinct location —
    // otherwise materials are assumed to ship to the ordering company itself,
    // same as the sample template (which has no separate Ship-To block).
    $showShipTo = $shipTo && $shipTo->id !== ($company->id ?? null);
@endphp

{{-- ============ MASTHEAD ============ --}}
<table class="masthead">
    <tr>
        <td style="width:58%;">
            <table>
                <tr>
                    @if (! empty($logo))
                        <td class="logo-cell"><img src="{{ $logo }}" alt=""></td>
                    @endif
                    <td>
                        <div class="company-name">{{ $company->name ?? config('app.name') }}</div>
                        <div class="company-meta">{{ $addr([
                            $company->address_line1 ?? null,
                            $company->address_line2 ?? null,
                            $cityLine($company->city ?? null, $company->state ?? null, $company->zip ?? null),
                            ($company && $company->phone) ? $company->phone : null,
                        ]) }}</div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width:42%;">
            <div class="doc-title">Purchase Order</div>
            <div class="doc-facts">
                <table>
                    <tr><td class="flabel">Order#:</td><td class="fval">{{ $po->po_number }}</td></tr>
                    <tr><td class="flabel">Date:</td><td class="fval">{{ optional($po->order_date)->format('m/d/Y') ?: '—' }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ============ TO / SHIP TO ============ --}}
<table class="to-block">
    <tr>
        <td style="width:58%;">
            <table class="to-block"><tr>
                <td class="to-label">To:</td>
                <td>
                    @if ($po->supplier)
                        <div class="to-name">{{ $po->supplier->name }}</div>
                        <div class="to-body">{{ $addr([
                            $po->supplier->address,
                            $cityLine($po->supplier->city, $po->supplier->state, $po->supplier->zip),
                            ($po->supplier->country && strtoupper($po->supplier->country) !== 'USA') ? $po->supplier->country : null,
                        ]) }}</div>
                    @else
                        <div class="muted">No supplier on file</div>
                    @endif
                </td>
            </tr></table>
        </td>
        <td style="width:42%;">
            @if ($showShipTo)
                <div class="side-label">Ship To:</div>
                <div class="to-body">{{ $shipTo->name }}
{{ $addr([
                    $shipTo->address_line1,
                    $shipTo->address_line2,
                    $cityLine($shipTo->city, $shipTo->state, $shipTo->zip),
                ]) }}</div>
            @elseif ($po->ship_to)
                <div class="side-label">Ship To:</div>
                <div class="to-body">{{ $po->ship_to }}</div>
            @endif
        </td>
    </tr>
</table>

<hr class="rule">

{{-- ============ MEMO / ORDERED BY ============ --}}
<table class="memo-row">
    <tr>
        <td style="width:60%;">
            <div class="memo-note">Include PO number on all invoices,<br>correspondence &amp; delivered materials</div>
        </td>
        <td style="width:40%;" class="ordered-by">
            <span class="side-label">Ordered By:</span>
            {{ $po->creator->name ?? '—' }}
        </td>
    </tr>
</table>

<hr class="rule">

{{-- ============ LINE ITEMS ============ --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:100px;">SKU</th>
            <th>Description</th>
            <th style="width:36px;">UOM</th>
            <th style="width:60px;" class="num">Quantity</th>
            <th style="width:60px;" class="num">Price</th>
            <th style="width:75px;" class="num">Amount</th>
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
                <td class="pn">{{ $p->sku ?? $p->part_number ?? '—' }}</td>
                <td>
                    {{ $p->description ?? $i->notes ?? '—' }}
                    @if ($colorCode)
                        <div class="sub">Color {{ $colorCode }}@if ($colorName && $colorName !== $colorCode) — {{ $colorName }}@endif</div>
                    @endif
                    @if ($i->destination_location)
                        <div class="sub">Deliver to: {{ $i->destination_location }}</div>
                    @endif
                </td>
                <td>{{ $p->purchase_uom ?? $p->unit_of_measure ?? 'EA' }}</td>
                <td class="num">
                    {{ number_format($qty) }}
                    @if ($packSize > 1)
                        <div class="sub">{{ $packSize }}/pack &middot; {{ number_format($qty * $packSize) }} ea</div>
                    @endif
                </td>
                <td class="num">{{ number_format($unit, 2) }}</td>
                <td class="num">{{ number_format($line, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted" style="text-align:center; padding:16px;">No line items on this purchase order.</td></tr>
        @endforelse
        <tr class="subtotal-row">
            <td colspan="4"></td>
            <td class="num">Subtotal:</td>
            <td class="num">{{ number_format($subtotal, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- ============ NOTES ============ --}}
@if ($po->notes || $po->contact_name || $po->contact_email || $po->contact_phone)
    <div style="margin-top:14px;">
        <div class="side-label">Notes:</div>
        <div class="to-body">{{ $po->notes ?: '—' }}@if ($po->contact_name || $po->contact_email || $po->contact_phone)

Buyer contact: {{ trim(($po->contact_name ?? '') . '  ' . ($po->contact_phone ?? '') . '  ' . ($po->contact_email ?? '')) }}@endif</div>
    </div>
@endif

{{-- ============ FOOTER: TERMS + TOTAL ============ --}}
<table class="footer-table">
    <tr>
        <td class="terms">
            The terms and conditions on the reverse side of this purchase order are binding
            and part of this agreement. If not attached, the {{ $company->name ?? config('app.name') }} Standard Terms and
            Conditions are incorporated herein by reference and are available upon request.
        </td>
        <td style="width:4%;"></td>
        <td class="totals-box">
            <table>
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="val">{{ number_format($subtotal, 2) }}</td>
                </tr>
                <tr class="grand">
                    <td class="label">Total Order</td>
                    <td class="val">{{ number_format($subtotal, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="prepared">
    {{ $company->name ?? config('app.name') }} &middot; PO {{ $po->po_number }} &middot;
    Prepared {{ now()->format('M j, Y g:i A') }}@if ($po->creator) by {{ $po->creator->name }}@endif in ForgeDesk
</div>

</body>
</html>
