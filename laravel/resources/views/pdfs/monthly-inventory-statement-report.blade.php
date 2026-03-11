<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Inventory Statement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 7px;
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
            font-size: 14px;
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
            padding: 4px 2px;
            text-align: left;
            font-size: 7px;
            border: 1px solid #222;
        }
        .items-table td {
            padding: 3px 2px;
            border: 1px solid #dee2e6;
            font-size: 7px;
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
        .section-header {
            background: #4a4a4a;
            color: white;
            font-weight: bold;
        }
        .positive {
            color: #28a745;
        }
        .negative {
            color: #dc3545;
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
        <h1>MONTHLY INVENTORY STATEMENT</h1>
        <h2>{{ $summary['month'] }} | Generated {{ now()->format('F d, Y H:i') }}</h2>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">PRODUCTS</div>
                    <div class="stat-value">{{ $summary['products_count'] }}</div>
                </td>
                <td>
                    <div class="stat-label">BEGINNING VALUE</div>
                    <div class="stat-value">${{ number_format($summary['total_beginning_value'], 2) }}</div>
                </td>
                <td>
                    <div class="stat-label">ENDING VALUE</div>
                    <div class="stat-value">${{ number_format($summary['total_ending_value'], 2) }}</div>
                </td>
                <td>
                    <div class="stat-label">VALUE CHANGE</div>
                    <div class="stat-value {{ $summary['total_value_change'] >= 0 ? 'positive' : 'negative' }}">
                        {{ $summary['total_value_change'] >= 0 ? '+' : '' }}${{ number_format($summary['total_value_change'], 2) }}
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="stat-label">RECEIPTS</div>
                    <div class="stat-value">{{ number_format($summary['total_receipts']) }}</div>
                </td>
                <td>
                    <div class="stat-label">SHIPMENTS</div>
                    <div class="stat-value">{{ number_format($summary['total_shipments']) }}</div>
                </td>
                <td>
                    <div class="stat-label">JOB MATERIAL TRANSFERS</div>
                    <div class="stat-value">{{ number_format($summary['total_job_material_transfers']) }}</div>
                </td>
                <td>
                    <div class="stat-label">JOB ISSUES</div>
                    <div class="stat-value">{{ number_format($summary['total_job_issues']) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">SKU</th>
                <th style="width: 15%;">Description</th>
                <th style="width: 8%;">Category</th>
                <th style="width: 5%;" class="text-right">Begin</th>
                <th colspan="4" class="text-center section-header">ADDITIONS</th>
                <th style="width: 4%;" class="text-right">Total Add</th>
                <th colspan="4" class="text-center section-header">DEDUCTIONS</th>
                <th style="width: 4%;" class="text-right">Total Ded</th>
                <th style="width: 5%;" class="text-right">Ending</th>
                <th style="width: 4%;" class="text-right">Change</th>
                <th style="width: 5%;" class="text-right">End Value</th>
            </tr>
            <tr>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th style="width: 4%;" class="text-right">Rcpt</th>
                <th style="width: 4%;" class="text-right">Return</th>
                <th style="width: 4%;" class="text-right">Job Mat</th>
                <th style="width: 4%;" class="text-right">Adj+</th>
                <th></th>
                <th style="width: 4%;" class="text-right">Ship</th>
                <th style="width: 4%;" class="text-right">JobIss</th>
                <th style="width: 4%;" class="text-right">Issue</th>
                <th style="width: 4%;" class="text-right">Adj-</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($statement as $item)
                <tr>
                    <td>{{ $item['sku'] }}</td>
                    <td>{{ $item['description'] }}</td>
                    <td>{{ $item['category'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($item['beginning_inventory_display']) }}</td>
                    <td class="text-right">{{ $item['receipts_display'] > 0 ? number_format($item['receipts_display']) : '-' }}</td>
                    <td class="text-right">{{ $item['returns_display'] > 0 ? number_format($item['returns_display']) : '-' }}</td>
                    <td class="text-right">{{ $item['job_material_transfers_display'] > 0 ? number_format($item['job_material_transfers_display']) : '-' }}</td>
                    <td class="text-right">{{ $item['positive_adjustments_display'] > 0 ? number_format($item['positive_adjustments_display']) : '-' }}</td>
                    <td class="text-right">{{ number_format($item['total_additions_display']) }}</td>
                    <td class="text-right">{{ $item['shipments_display'] > 0 ? number_format($item['shipments_display']) : '-' }}</td>
                    <td class="text-right">{{ $item['job_issues_display'] > 0 ? number_format($item['job_issues_display']) : '-' }}</td>
                    <td class="text-right">{{ $item['issues_display'] > 0 ? number_format($item['issues_display']) : '-' }}</td>
                    <td class="text-right">{{ $item['negative_adjustments_display'] > 0 ? number_format($item['negative_adjustments_display']) : '-' }}</td>
                    <td class="text-right">{{ number_format($item['total_deductions_display']) }}</td>
                    <td class="text-right">{{ number_format($item['ending_inventory_display']) }}</td>
                    <td class="text-right {{ $item['net_change_display'] >= 0 ? 'positive' : 'negative' }}">
                        {{ $item['net_change_display'] >= 0 ? '+' : '' }}{{ number_format($item['net_change_display']) }}
                    </td>
                    <td class="text-right">${{ number_format($item['ending_value'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="17" class="text-center" style="padding: 20px; color: #999;">
                        No inventory activity found for this month.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>ForgeDesk Inventory Management System | Monthly Inventory Statement | {{ $summary['month'] }} | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>
</body>
</html>
