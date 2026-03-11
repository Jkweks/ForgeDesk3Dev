<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reorder Recommendations Report</title>
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
            font-size: 12px;
            color: #666;
        }
        .summary-stats {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 15px;
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
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .priority-high {
            background: #f8d7da;
            color: #721c24;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 8px;
        }
        .priority-medium {
            background: #fff3cd;
            color: #856404;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 8px;
        }
        .priority-low {
            background: #d1ecf1;
            color: #0c5460;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 8px;
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
        <h1>REORDER RECOMMENDATIONS REPORT</h1>
        <h2>Generated {{ now()->format('F d, Y H:i') }}</h2>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">TOTAL ITEMS</div>
                    <div class="stat-value">{{ $summary['total_items'] }}</div>
                </td>
                <td>
                    <div class="stat-label">HIGH PRIORITY</div>
                    <div class="stat-value">{{ $summary['high_priority'] }}</div>
                </td>
                <td>
                    <div class="stat-label">MEDIUM PRIORITY</div>
                    <div class="stat-value">{{ $summary['medium_priority'] }}</div>
                </td>
                <td>
                    <div class="stat-label">ESTIMATED VALUE</div>
                    <div class="stat-value">${{ number_format($summary['total_estimated_value'], 2) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 10%;">SKU</th>
                <th style="width: 22%;">Description</th>
                <th style="width: 12%;">Supplier</th>
                <th style="width: 7%;" class="text-right">Available</th>
                <th style="width: 7%;" class="text-right">Reorder Pt</th>
                <th style="width: 7%;" class="text-right">Target</th>
                <th style="width: 7%;" class="text-right">Suggested</th>
                <th style="width: 7%;" class="text-right">Unit Cost</th>
                <th style="width: 8%;" class="text-right">Est. Value</th>
                <th style="width: 7%;" class="text-center">Priority</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recommendations as $rec)
                @php
                    // Determine priority based on priority_score
                    if ($rec['priority_score'] >= 100) {
                        $priority = 'high';
                        $priorityLabel = 'HIGH';
                    } elseif ($rec['priority_score'] >= 50) {
                        $priority = 'medium';
                        $priorityLabel = 'MEDIUM';
                    } else {
                        $priority = 'low';
                        $priorityLabel = 'LOW';
                    }
                @endphp
                <tr>
                    <td>{{ $rec['sku'] }}</td>
                    <td>{{ $rec['description'] }}</td>
                    <td>{{ $rec['supplier'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($rec['available_display']) }} {{ $rec['counting_unit'] ?? 'ea' }}</td>
                    <td class="text-right">{{ number_format($rec['reorder_point_display']) }} {{ $rec['counting_unit'] ?? 'ea' }}</td>
                    <td class="text-right">{{ number_format($rec['target_display']) }} {{ $rec['counting_unit'] ?? 'ea' }}</td>
                    <td class="text-right">{{ number_format($rec['recommended_order_qty_display']) }} {{ $rec['counting_unit'] ?? 'ea' }}</td>
                    <td class="text-right">${{ number_format($rec['display_cost'], 2) }}</td>
                    <td class="text-right">${{ number_format($rec['recommended_order_value'], 2) }}</td>
                    <td class="text-center">
                        @if($priority === 'high')
                            <span class="priority-high">{{ $priorityLabel }}</span>
                        @elseif($priority === 'medium')
                            <span class="priority-medium">{{ $priorityLabel }}</span>
                        @else
                            <span class="priority-low">{{ $priorityLabel }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 20px; color: #999;">
                        No reorder recommendations found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>ForgeDesk Inventory Management System | Reorder Recommendations | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>
</body>
</html>
