<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Velocity Analysis Report</title>
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
        .velocity-fast {
            background: #d4edda;
            color: #155724;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 8px;
        }
        .velocity-medium {
            background: #fff3cd;
            color: #856404;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 8px;
        }
        .velocity-slow {
            background: #f8d7da;
            color: #721c24;
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
        <h1>STOCK VELOCITY ANALYSIS REPORT</h1>
        <h2>{{ $days }}-Day Analysis | Generated {{ now()->format('F d, Y H:i') }}</h2>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">FAST MOVERS</div>
                    <div class="stat-value">{{ $summary['fast_movers'] }}</div>
                </td>
                <td>
                    <div class="stat-label">MEDIUM MOVERS</div>
                    <div class="stat-value">{{ $summary['medium_movers'] }}</div>
                </td>
                <td>
                    <div class="stat-label">SLOW MOVERS</div>
                    <div class="stat-value">{{ $summary['slow_movers'] }}</div>
                </td>
                <td>
                    <div class="stat-label">TOTAL ANALYZED</div>
                    <div class="stat-value">{{ $summary['total_analyzed'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 10%;">SKU</th>
                <th style="width: 25%;">Description</th>
                <th style="width: 12%;">Category</th>
                <th style="width: 8%;" class="text-right">On Hand</th>
                <th style="width: 8%;" class="text-right">Receipts</th>
                <th style="width: 8%;" class="text-right">Shipments</th>
                <th style="width: 8%;" class="text-right">Turnover %</th>
                <th style="width: 8%;" class="text-center">Velocity</th>
                <th style="width: 8%;" class="text-right">Days to Stock Out</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td>{{ $product['sku'] }}</td>
                    <td>{{ $product['description'] }}</td>
                    <td>{{ $product['category'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($product['on_hand']) }}</td>
                    <td class="text-right">{{ number_format($product['receipts']) }}</td>
                    <td class="text-right">{{ number_format($product['shipments']) }}</td>
                    <td class="text-right">{{ number_format($product['turnover_rate'], 2) }}%</td>
                    <td class="text-center">
                        @if($product['velocity'] === 'fast')
                            <span class="velocity-fast">FAST</span>
                        @elseif($product['velocity'] === 'medium')
                            <span class="velocity-medium">MEDIUM</span>
                        @else
                            <span class="velocity-slow">SLOW</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($product['days_until_stockout'])
                            {{ number_format($product['days_until_stockout']) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px; color: #999;">
                        No velocity data found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>ForgeDesk Inventory Management System | Stock Velocity Analysis | {{ $days }}-Day Period | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>
</body>
</html>
