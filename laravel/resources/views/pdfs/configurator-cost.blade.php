<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Opening Cost {{ $config->businessJob->job_number }}</title>
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

@php
    $frameConfig = $config->frameConfig;
    $frameParts = $frameConfig?->parts ?? collect();
    $doorConfigs = $config->doorConfigs;
    $hardwareParts = $config->hardwareParts;
    $spec = $config->openingSpecs;
    $money = fn ($v, $dp = 2) => $v === null ? '—' : '$'.number_format($v, $dp);
    $label = fn ($v) => $v === null || $v === '' ? '—' : ucwords(str_replace('_', ' ', (string) $v));
    $partNo = fn ($p) => $p->product ? $p->product->part_number.($p->product->finish ? '-'.$p->product->finish : '') : '—';
@endphp

<table class="masthead">
    <tr>
        <td style="width:60%;">
            <div class="doc-title">OPENING COST SUMMARY</div>
            <div class="doc-sub">{{ $config->businessJob->job_number }} — {{ $config->businessJob->job_name }}</div>
        </td>
        <td style="width:40%;" class="doc-facts">
            <div><strong>Configuration #{{ $config->id }}</strong></div>
            <div>Status: <span class="badge">{{ $config->status_label }}</span></div>
            @if ($config->workOrder)
                <div>Work Order: {{ $config->workOrder->release_token }}</div>
            @endif
            <div>{{ now()->format('M j, Y') }}</div>
        </td>
    </tr>
</table>

<div class="section-title">Opening</div>
<table class="kv">
    <tr>
        <td class="k">Door tag(s)</td><td>{{ $config->doors->pluck('door_tag')->implode(', ') ?: '—' }}</td>
        <td class="k">Scope</td><td>{{ $config->scope_label }}</td>
    </tr>
    <tr>
        <td class="k">Quantity</td><td>{{ $config->quantity }}</td>
        <td class="k">Type</td><td>{{ $spec?->opening_type_label ?? '—' }}</td>
    </tr>
    @if ($spec)
    <tr>
        <td class="k">Opening size</td><td>{{ $spec->door_opening_width }}" &times; {{ $spec->door_opening_height }}"</td>
        <td class="k">Handing</td><td>{{ $spec->hand_label }}</td>
    </tr>
    <tr>
        <td class="k">Finish</td><td>{{ $spec->finish_label }}</td>
        <td class="k">Hinging</td><td>{{ $spec->hinging_label }}@if($spec->butt_hinge_count) ({{ $spec->butt_hinge_count }} butt hinges)@endif</td>
    </tr>
    <tr>
        <td class="k">Opening angle</td><td>{{ $spec->opening_angle ? $spec->opening_angle.'°' : '—' }}</td>
        <td class="k">Glazing</td><td>{{ $label($spec->glazing) }}</td>
    </tr>
    @endif
</table>

@if ($config->includesDoor())
    <div class="section-title">Door</div>
    @forelse ($doorConfigs as $dc)
        <table class="kv">
            <tr>
                <td class="k">Leaf</td><td>{{ $label($dc->leaf) }}</td>
                <td class="k">Series</td><td>{{ $dc->door_series ?? '—' }}@if($dc->stile_width) ({{ $dc->stile_width }})@endif</td>
            </tr>
            <tr>
                <td class="k">Leaf type</td><td>{{ $label($dc->leaf_type) }}</td>
                <td class="k">Handing</td><td>{{ $label($dc->handing) }}</td>
            </tr>
            <tr>
                <td class="k">Hinge type</td><td>{{ $label($dc->hinge_type) }}</td>
                <td class="k">Glazing</td><td>{{ $label($dc->glazing) }}</td>
            </tr>
            <tr>
                <td class="k">Rails (top / bottom)</td><td>{{ $dc->top_rail_label ?? '—' }} / {{ $dc->bot_rail_label ?? '—' }}</td>
                <td class="k">Bottom gap</td><td>{{ $dc->bottom_gap !== null ? $dc->bottom_gap.'"' : '—' }}</td>
            </tr>
            @if ($dc->mid_qty)
            <tr>
                <td class="k">Mid rails</td><td>{{ $dc->mid_qty }} &times; {{ $dc->mid_rail_label ?? '—' }}</td>
                <td class="k">Locations</td><td>{{ collect([$dc->mid_loc1, $dc->mid_loc2])->filter()->implode(', ') ?: '—' }}</td>
            </tr>
            @endif
        </table>
        <table class="items">
            <thead><tr><th>Part</th><th>PN</th><th>Description</th><th class="num">Length</th><th class="num">Qty</th></tr></thead>
            <tbody>
                @forelse ($dc->parts as $part)
                    <tr>
                        <td>{{ $part->formatted_label }}</td>
                        <td class="pn">{{ $partNo($part) }}</td>
                        <td class="muted">{{ $part->product?->description }}</td>
                        <td class="num">{{ $part->unit_type === 'length' ? number_format($part->calculated_length, 3).'"' : '—' }}</td>
                        <td class="num">{{ $part->quantity }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-note">No door parts generated yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    @empty
        <div class="empty-note">No door configured yet.</div>
    @endforelse
@endif

@if ($config->includesFrame())
    <div class="section-title">Frame</div>
    @if ($frameConfig)
        <table class="kv">
            <tr>
                <td class="k">System / series</td><td>{{ $frameConfig->frameSeries?->frameSystem?->name ?? '—' }} / {{ $frameConfig->frameSeries?->name ?? '—' }}</td>
                <td class="k">Glazing</td><td>{{ $frameConfig->glazing_label ?? '—' }}</td>
            </tr>
            <tr>
                <td class="k">Transom</td><td>{{ $frameConfig->has_transom ? 'Yes'.($frameConfig->transom_glazing_label ? ' ('.$frameConfig->transom_glazing_label.')' : '') : 'No' }}</td>
                <td class="k">Threshold</td><td>{{ $frameConfig->has_threshold ? 'Yes' : 'No' }}</td>
            </tr>
            <tr>
                <td class="k">Total frame height</td><td>{{ $frameConfig->total_frame_height !== null ? $frameConfig->total_frame_height.'"' : '—' }}</td>
                <td class="k"></td><td></td>
            </tr>
        </table>
    @endif
    <table class="items">
        <thead><tr><th>Part</th><th>PN</th><th>Description</th><th class="num">Length</th><th class="num">Qty</th></tr></thead>
        <tbody>
            @forelse ($frameParts as $part)
                <tr>
                    <td>{{ $part->formatted_label }}</td>
                    <td class="pn">{{ $partNo($part) }}</td>
                    <td class="muted">{{ $part->product?->description }}</td>
                    <td class="num">{{ $part->unit_type === 'length' ? number_format($part->calculated_length, 3).'"' : '—' }}</td>
                    <td class="num">{{ $part->quantity }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-note">No frame parts generated yet.</td></tr>
            @endforelse
        </tbody>
    </table>
@endif

<div class="section-title">Hardware</div>
<table class="items">
    <thead><tr><th>Part</th><th>PN</th><th>Description</th><th class="num">Qty</th><th>Source</th></tr></thead>
    <tbody>
        @forelse ($hardwareParts as $part)
            <tr>
                <td>{{ $part->formatted_label }}</td>
                <td class="pn">{{ $part->product ? $partNo($part) : (trim(($part->manufacturer ?? '').' '.($part->model_number ?? '')) ?: 'Special order') }}</td>
                <td class="muted">{{ $part->product?->description ?? ($part->product ? '' : 'Special order — not priced') }}</td>
                <td class="num">{{ $part->quantity }}</td>
                <td class="muted">{{ ucfirst($part->source_type) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty-note">No hardware linked yet.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Material Cost</div>
<table class="items">
    <thead>
        <tr><th>Part</th><th>Description</th><th class="num">Qty</th><th class="num">Net price</th><th class="num">Extended</th></tr>
    </thead>
    <tbody>
        @forelse ($cost['rows'] as $r)
            <tr>
                <td class="pn">{{ $r['part_number'] }}@if($r['finish'])-{{ $r['finish'] }}@endif</td>
                <td class="muted">{{ $r['description'] }}</td>
                <td class="num">{{ $r['quantity'] }}</td>
                <td class="num">
                    @if ($r['net_price'] > 0)
                        {{ $money($r['net_price'], 4) }}
                        @if ($r['pack_size'] > 1)<div class="muted">{{ $money($r['pack_net_price']) }} / pack of {{ $r['pack_size'] }}</div>@endif
                    @else
                        <span class="muted">no net price</span>
                    @endif
                </td>
                <td class="num">{{ $money($r['extended']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty-note">No parts generated yet.</td></tr>
        @endforelse
        <tr class="total-row"><td colspan="4" class="num">Material total</td><td class="num">{{ $money($cost['total']) }}</td></tr>
    </tbody>
</table>
@if ($cost['missing_price'])
    <div class="warn">{{ $cost['missing_price'] }} item(s) have no net price and are counted at $0 — the total is understated.</div>
@endif
@if ($cost['unpriced_special_order'])
    <div class="warn">{{ $cost['unpriced_special_order'] }} special-order hardware item(s) have no inventory product and are not priced.</div>
@endif
@foreach ($cost['warnings'] as $w)
    <div class="warn">{{ $w }}</div>
@endforeach
<div class="muted" style="margin-top:6px;">Quantities match the material list (extrusions and roll stock in tenths of a stick/roll). Price is each item's Net Price per stock unit — per piece for pack items.</div>

<div class="footer">Generated by ForgeDesk Configurator — {{ now()->format('M j, Y g:i A') }}</div>

</body>
</html>
