<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cut Sheet {{ $config->businessJob->job_number }}</title>
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
    </style>
</head>
<body>

@php
    $frameParts = $config->frameConfig?->parts ?? collect();
    $doorParts = $config->doorConfigs->flatMap(fn ($dc) => $dc->parts);
    $hardwareParts = $config->hardwareParts;
@endphp

<table class="masthead">
    <tr>
        <td style="width:60%;">
            <div class="doc-title">DOOR / FRAME CUT SHEET</div>
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

<div class="facts-panel">
    <table>
        <tr>
            <td style="width:20%">
                <div class="facts-label">Door Tag(s)</div>
                {{ $config->doors->pluck('door_tag')->implode(', ') ?: '—' }}
            </td>
            <td style="width:15%">
                <div class="facts-label">Scope</div>
                {{ $config->scope_label }}
            </td>
            <td style="width:10%">
                <div class="facts-label">Qty</div>
                {{ $config->quantity }}
            </td>
            @if ($config->openingSpecs)
                <td style="width:20%">
                    <div class="facts-label">Opening</div>
                    {{ $config->openingSpecs->door_opening_width }}" &times; {{ $config->openingSpecs->door_opening_height }}"
                    ({{ $config->openingSpecs->opening_type_label }})
                </td>
                <td style="width:15%">
                    <div class="facts-label">Handing</div>
                    {{ $config->openingSpecs->hand_label }}
                </td>
                <td style="width:20%">
                    <div class="facts-label">Finish</div>
                    {{ $config->openingSpecs->finish_label }}
                </td>
            @endif
        </tr>
    </table>
</div>

@if ($config->includesFrame())
    <div class="section-title">Frame Parts</div>
    <table class="items">
        <thead>
            <tr><th>Part</th><th>PN</th><th>Description</th><th class="num">Length</th><th class="num">Qty</th></tr>
        </thead>
        <tbody>
            @forelse ($frameParts as $part)
                <tr>
                    <td>{{ $part->formatted_label }}</td>
                    <td class="pn">{{ $part->product->part_number }}@if($part->product->finish)-{{ $part->product->finish }}@endif</td>
                    <td class="muted">{{ $part->product->description }}</td>
                    <td class="num">{{ $part->unit_type === 'length' ? number_format($part->calculated_length, 3).'"' : '—' }}</td>
                    <td class="num">{{ $part->unit_type === 'qty' ? $part->quantity : $part->quantity }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-note">No frame parts generated yet.</td></tr>
            @endforelse
        </tbody>
    </table>
@endif

@if ($config->includesDoor() && $config->openingSpecs && count($config->openingSpecs->hingeLocations()))
    <div class="section-title">Hinge Prep Locations — {{ $config->openingSpecs->hingeSpacingStandard->name }} Standard</div>
    <table class="items">
        <thead>
            <tr><th>Hinge</th><th class="num">Distance from Door Top</th></tr>
        </thead>
        <tbody>
            @foreach ($config->openingSpecs->hingeLocations() as $loc)
                <tr>
                    <td>{{ $loc['label'] }}</td>
                    <td class="num">{{ number_format($loc['distance_from_top'], 3) }}"</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($config->includesDoor())
    <div class="section-title">Door Parts</div>
    <table class="items">
        <thead>
            <tr><th>Part</th><th>PN</th><th>Description</th><th class="num">Length</th><th class="num">Qty</th></tr>
        </thead>
        <tbody>
            @forelse ($doorParts as $part)
                <tr>
                    <td>{{ $part->formatted_label }}</td>
                    <td class="pn">{{ $part->product->part_number }}@if($part->product->finish)-{{ $part->product->finish }}@endif</td>
                    <td class="muted">{{ $part->product->description }}</td>
                    <td class="num">{{ $part->unit_type === 'length' ? number_format($part->calculated_length, 3).'"' : '—' }}</td>
                    <td class="num">{{ $part->quantity }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-note">No door parts generated yet.</td></tr>
            @endforelse
        </tbody>
    </table>
@endif

<div class="section-title">Hardware</div>
<table class="items">
    <thead>
        <tr><th>Part</th><th>PN</th><th>Description</th><th class="num">Qty</th><th>Source</th></tr>
    </thead>
    <tbody>
        @forelse ($hardwareParts as $part)
            <tr>
                <td>{{ $part->formatted_label }}</td>
                <td class="pn">{{ $part->product->part_number }}@if($part->product->finish)-{{ $part->product->finish }}@endif</td>
                <td class="muted">{{ $part->product->description }}</td>
                <td class="num">{{ $part->quantity }}</td>
                <td class="muted">{{ ucfirst($part->source_type) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty-note">No hardware linked yet.</td></tr>
        @endforelse
    </tbody>
</table>

@if ($config->notes)
    <div class="section-title">Notes</div>
    <div style="padding:8px 6px; white-space:pre-line;">{{ $config->notes }}</div>
@endif

<div class="footer">Generated by ForgeDesk Configurator — {{ now()->format('M j, Y g:i A') }}</div>

</body>
</html>
