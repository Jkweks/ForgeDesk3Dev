<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cycle Count Report - {{ $session->session_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0 0 5px 0;
            font-size: 18px;
            color: #1a1a1a;
        }
        .header h2 {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        .info-line {
            margin-bottom: 5px;
            padding: 3px 0;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .summary-stats {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 15px;
            margin-top: 15px;
        }
        .summary-stats table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-stats td {
            padding: 5px 10px;
            text-align: center;
        }
        .summary-stats .stat-label {
            font-weight: bold;
            color: #555;
            font-size: 8px;
        }
        .summary-stats .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #1a1a1a;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .items-table th {
            background: #333;
            color: white;
            padding: 6px 4px;
            text-align: left;
            font-size: 8px;
            border: 1px solid #222;
        }
        .items-table td {
            padding: 5px 4px;
            border: 1px solid #dee2e6;
            font-size: 8px;
        }
        .items-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        .variance-positive {
            color: #28a745;
            font-weight: bold;
        }
        .variance-negative {
            color: #dc3545;
            font-weight: bold;
        }
        .variance-zero {
            color: #6c757d;
        }
        .status-badge {
            padding: 2px 6px;
            font-size: 8px;
            font-weight: bold;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-in-progress {
            background: #fff3cd;
            color: #856404;
        }
        .status-planned {
            background: #d1ecf1;
            color: #0c5460;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
            font-size: 7px;
            color: #6c757d;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>CYCLE COUNT REPORT</h1>
        <h2>{{ $session->session_number }}</h2>
    </div>

    <!-- Session Information -->
    <div style="margin-bottom: 15px;">
        <div class="info-line">
            <span class="info-label">Status:</span>
            <span class="status-badge status-{{ $session->status }}">
                {{ strtoupper(str_replace('_', ' ', $session->status)) }}
            </span>
            &nbsp;&nbsp;&nbsp;
            <span class="info-label">Category:</span>
            {{ $session->category ? $session->category->name : 'All Categories' }}
            &nbsp;&nbsp;&nbsp;
            <span class="info-label">Location:</span>
            {{ $session->location ?: 'All Locations' }}
        </div>
        <div class="info-line">
            <span class="info-label">Scheduled:</span>
            {{ $session->scheduled_date ? $session->scheduled_date->format('M d, Y') : 'N/A' }}
            &nbsp;&nbsp;&nbsp;
            <span class="info-label">Started:</span>
            {{ $session->started_at ? $session->started_at->format('M d, Y H:i') : 'Not started' }}
            &nbsp;&nbsp;&nbsp;
            <span class="info-label">Completed:</span>
            {{ $session->completed_at ? $session->completed_at->format('M d, Y H:i') : 'Not completed' }}
        </div>
        <div class="info-line">
            <span class="info-label">Assigned To:</span>
            {{ $session->assignedUser ? $session->assignedUser->name : 'Unassigned' }}
            &nbsp;&nbsp;&nbsp;
            <span class="info-label">Reviewed By:</span>
            {{ $session->reviewer ? $session->reviewer->name : 'Not reviewed' }}
            &nbsp;&nbsp;&nbsp;
            <span class="info-label">Generated:</span>
            {{ now()->format('M d, Y H:i') }}
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">TOTAL ITEMS</div>
                    <div class="stat-value">{{ $session->total_items }}</div>
                </td>
                <td>
                    <div class="stat-label">COUNTED</div>
                    <div class="stat-value">{{ $session->counted_items }}</div>
                </td>
                <td>
                    <div class="stat-label">VARIANCES</div>
                    <div class="stat-value">{{ $session->variance_items }}</div>
                </td>
                <td>
                    <div class="stat-label">ACCURACY</div>
                    <div class="stat-value">{{ $session->accuracy_percentage }}%</div>
                </td>
                <td>
                    <div class="stat-label">PROGRESS</div>
                    <div class="stat-value">{{ $session->progress_percentage }}%</div>
                </td>
            </tr>
        </table>
    </div>

    @if($session->notes)
    <div style="margin-bottom: 15px; padding: 8px; background: #fff3cd; border: 1px solid #ffc107;">
        <strong>Notes:</strong> {{ $session->notes }}
    </div>
    @endif

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">SKU</th>
                <th style="width: 20%;">Product Name</th>
                <th style="width: 10%;">Location</th>
                <th style="width: 8%;" class="text-right">System Qty</th>
                <th style="width: 8%;" class="text-right">Counted Qty</th>
                <th style="width: 8%;" class="text-right">Variance</th>
                <th style="width: 6%;" class="text-right">Var %</th>
                <th style="width: 7%;" class="text-right">Committed</th>
                <th style="width: 10%;">Counter</th>
                <th style="width: 15%;">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($session->items as $item)
                @php
                    $committedQty = $committedByProduct[$item->product_id] ?? 0;
                    $varianceClass = $item->variance > 0 ? 'variance-positive' : ($item->variance < 0 ? 'variance-negative' : 'variance-zero');
                @endphp
                <tr>
                    <td>{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td>
                        @if($item->location && $item->location->storageLocation)
                            {{ $item->location->storageLocation->name }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        {{ number_format($item->system_quantity) }}
                        <small style="color: #6c757d;">{{ $item->counting_unit }}</small>
                    </td>
                    <td class="text-right">
                        @if($item->counted_quantity !== null)
                            {{ number_format($item->counted_quantity) }}
                            <small style="color: #6c757d;">{{ $item->counting_unit }}</small>
                        @else
                            <span style="color: #999;">Not counted</span>
                        @endif
                    </td>
                    <td class="text-right {{ $varianceClass }}">
                        @if($item->counted_quantity !== null)
                            {{ $item->variance >= 0 ? '+' : '' }}{{ number_format($item->variance) }}
                            <small style="color: #6c757d;">{{ $item->counting_unit }}</small>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right {{ $varianceClass }}">
                        @if($item->counted_quantity !== null && $item->system_quantity != 0)
                            {{ number_format(abs($item->variance / $item->system_quantity * 100), 1) }}%
                        @elseif($item->counted_quantity !== null)
                            -
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if($committedQty > 0)
                            {{ number_format($committedQty) }}
                            <small style="color: #6c757d;">EA</small>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($item->counter)
                            {{ $item->counter->name }}
                        @else
                            -
                        @endif
                    </td>
                    <td style="font-size: 7px;">
                        {{ $item->count_notes ?: '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 20px; color: #999;">
                        No items in this cycle count session.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>ForgeDesk Inventory Management System | Cycle Count Report | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>
</body>
</html>
