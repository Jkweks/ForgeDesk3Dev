<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            margin: 15px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 16px;
            color: #1a1a1a;
        }
        .header h2 {
            margin: 0;
            font-size: 10px;
            color: #666;
            font-weight: normal;
        }
        .mode-banner {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 5px 8px;
            margin-bottom: 10px;
            font-size: 8px;
            color: #856404;
        }
        .summary-stats {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 8px;
            margin-bottom: 12px;
        }
        .summary-stats table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-stats td {
            padding: 4px 8px;
            text-align: center;
        }
        .stat-label {
            font-weight: bold;
            color: #555;
            font-size: 7px;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #1a1a1a;
        }
        .stat-available { color: #198754; }
        .stat-partial   { color: #856404; }
        .stat-unavail   { color: #dc3545; }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .items-table th {
            background: #333;
            color: white;
            padding: 5px 3px;
            text-align: left;
            font-size: 7px;
            border: 1px solid #222;
        }
        .items-table td {
            padding: 4px 3px;
            border: 1px solid #dee2e6;
            font-size: 7.5px;
            vertical-align: middle;
        }
        .items-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-danger { color: #dc3545; }
        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
            color: white;
        }
        .badge-available   { background: #198754; }
        .badge-partial     { background: #ffc107; color: #333; }
        .badge-unavailable { background: #dc3545; }
        .badge-not-found   { background: #6c757d; }
        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #dee2e6;
            font-size: 7px;
            color: #6c757d;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ strtoupper($title) }}</h1>
        <h2>Generated {{ $generated_at }}</h2>
    </div>

    @if($boneyard_shared_only)
    <div class="mode-banner">
        <strong>Boneyard &amp; Shared mode</strong> — Results show only Boneyard items and Shared Components. Parts available only in regular stock are reported as Not Found.
    </div>
    @endif

    @if(($file_count ?? 1) > 1)
    <div style="background:#d1ecf1;border:1px solid #bee5eb;padding:5px 8px;margin-bottom:10px;font-size:8px;color:#0c5460;">
        <strong>Combined from 2 files</strong> — File 1: {{ $file1_name ?? '' }} &nbsp;|&nbsp; File 2: {{ $file2_name ?? '' }}. Quantities for matching parts have been summed.
    </div>
    @endif

    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">Total Items</div>
                    <div class="stat-value">{{ $summary['total'] ?? count($results) }}</div>
                </td>
                <td>
                    <div class="stat-label">Available</div>
                    <div class="stat-value stat-available">{{ $summary['available'] ?? 0 }}</div>
                </td>
                <td>
                    <div class="stat-label">Partial</div>
                    <div class="stat-value stat-partial">{{ $summary['partial'] ?? 0 }}</div>
                </td>
                <td>
                    <div class="stat-label">Unavailable</div>
                    <div class="stat-value stat-unavail">{{ ($summary['unavailable'] ?? 0) + ($summary['not_found'] ?? 0) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">Status</th>
                @if(($file_count ?? 1) > 1)
                <th style="width: 5%;" class="text-center">Source</th>
                @endif
                <th style="width: {{ ($file_count ?? 1) > 1 ? '9%' : '10%' }};">Part #</th>
                <th style="width: 7%;">Finish</th>
                <th style="width: {{ ($file_count ?? 1) > 1 ? '18%' : '22%' }};">Description</th>
                <th style="width: 7%;" class="text-right">On Hand</th>
                <th style="width: 6%;" class="text-right">Committed</th>
                <th style="width: 6%;" class="text-right">Available</th>
                <th style="width: 6%;" class="text-right">On Order</th>
                <th style="width: 8%;" class="text-right">Qty Requested</th>
                <th style="width: 8%;" class="text-right">Qty Reserved</th>
                <th style="width: 8%;" class="text-right">Shortage</th>
            </tr>
        </thead>
        <tbody>
            @forelse($results as $item)
                @php
                    $status   = $item['status'] ?? 'not_found';
                    $packSize = $item['pack_size'] ?? 1;
                    $hasPacks = $packSize > 1;

                    $onHand    = $item['on_hand']    ?? null;
                    $committed = $item['committed']  ?? null;
                    $onOrder   = $item['on_order']   ?? null;

                    $reqEaches   = $item['required_qty_eaches'] ?? ($item['required_quantity'] ?? 0);
                    $reqPacks    = $item['required_qty_packs']  ?? 0;
                    $availEaches = $item['available_qty_eaches'] ?? ($item['available_quantity'] ?? 0);
                    $availPacks  = $item['available_qty_packs']  ?? 0;
                    $shortEaches = $item['shortage_eaches'] ?? ($item['shortage'] ?? 0);
                    $shortPacks  = $item['shortage_packs']  ?? 0;

                    // Qty Reserved = qty requested (what would be / was reserved for this job)
                    $qtyReserved = ($status !== 'not_found') ? $reqEaches : null;
                @endphp
                <tr>
                    <td class="text-center">
                        @if($status === 'available')
                            <span class="badge badge-available">Available</span>
                        @elseif($status === 'partial')
                            <span class="badge badge-partial">Partial</span>
                        @elseif($status === 'unavailable')
                            <span class="badge badge-unavailable">Out of Stock</span>
                        @else
                            <span class="badge badge-not-found">Not Found</span>
                        @endif
                    </td>
                    @if(($file_count ?? 1) > 1)
                    <td class="text-center">
                        @php $fileSource = $item['file'] ?? 1; @endphp
                        @if($fileSource === 'both')
                            <span class="badge" style="background:#333;color:#fff;">Both</span>
                        @elseif($fileSource == 2)
                            <span class="badge" style="background:#6f42c1;color:#fff;">File 2</span>
                        @else
                            <span class="badge" style="background:#0d6efd;color:#fff;">File 1</span>
                        @endif
                    </td>
                    @endif
                    <td><strong>{{ $item['part_number'] ?? '-' }}</strong></td>
                    <td>{{ $item['finish'] ?? '-' }}</td>
                    <td>{{ $item['description'] ?? '-' }}</td>
                    <td class="text-right">
                        @if($onHand !== null)
                            {{ number_format($onHand) }}
                            @if($hasPacks)<br><small>{{ number_format(floor($onHand / $packSize)) }} pk</small>@endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if($committed !== null)
                            {{ number_format($committed) }}
                            @if($hasPacks)<br><small>{{ number_format(floor($committed / $packSize)) }} pk</small>@endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if($status !== 'not_found')
                            {{ number_format($availEaches) }}
                            @if($hasPacks)<br><small>{{ number_format($availPacks) }} pk</small>@endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if($onOrder !== null)
                            {{ number_format($onOrder) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        {{ number_format($reqEaches) }}
                        @if($hasPacks)<br><small>{{ number_format($reqPacks, 2) }} pk</small>@endif
                    </td>
                    <td class="text-right">
                        @if($qtyReserved !== null)
                            {{ number_format($qtyReserved) }}
                            @if($hasPacks)<br><small>{{ number_format($reqPacks, 2) }} pk</small>@endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if($shortEaches > 0)
                            <span class="text-danger">{{ number_format($shortEaches) }}</span>
                            @if($hasPacks)<br><small class="text-danger">{{ number_format($shortPacks) }} pk</small>@endif
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ ($file_count ?? 1) > 1 ? 12 : 11 }}" class="text-center" style="padding: 20px; color: #999;">
                        No results found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>ForgeDesk Manufacturing ERP | Material Check Report | Generated {{ $generated_at }}</p>
    </div>
</body>
</html>
