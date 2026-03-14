<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Committed Parts Report</title>
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
        <h1>COMMITTED PARTS REPORT</h1>
        <h2>Generated {{ now()->format('F d, Y H:i') }}</h2>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">UNIQUE PARTS</div>
                    <div class="stat-value">{{ $summary['unique_parts'] }}</div>
                </td>
                <td>
                    <div class="stat-label">TOTAL COMMITTED</div>
                    <div class="stat-value">{{ number_format($summary['total_committed_quantity']) }}</div>
                </td>
                <td>
                    <div class="stat-label">TOTAL VALUE</div>
                    <div class="stat-value">${{ number_format($summary['total_committed_value'], 2) }}</div>
                </td>
                <td>
                    <div class="stat-label">ACTIVE JOBS</div>
                    <div class="stat-value">{{ $summary['active_jobs'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 10%;">SKU</th>
                <th style="width: 30%;">Description</th>
                <th style="width: 12%;">Category</th>
                <th style="width: 8%;" class="text-right">On Hand</th>
                <th style="width: 8%;" class="text-right">Committed</th>
                <th style="width: 8%;" class="text-right">Available</th>
                <th style="width: 8%;" class="text-right">Unit Cost</th>
                <th style="width: 10%;" class="text-right">Total Value</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td>{{ $product['sku'] }}</td>
                    <td>{{ $product['description'] }}</td>
                    <td>{{ $product['category'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($product['on_hand']) }}</td>
                    <td class="text-right">{{ number_format($product['committed']) }}</td>
                    <td class="text-right">{{ number_format($product['available']) }}</td>
                    <td class="text-right">${{ number_format($product['display_cost'], 2) }}</td>
                    <td class="text-right">${{ number_format($product['committed_display'] * $product['display_cost'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px; color: #999;">
                        No committed parts found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>ForgeDesk Inventory Management System | Committed Parts Report | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>
</body>
</html>
