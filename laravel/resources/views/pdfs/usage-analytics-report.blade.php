<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Usage Analytics Report</title>
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
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #ccc;
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
        <h1>USAGE ANALYTICS REPORT</h1>
        <h2>{{ $days }}-Day Analysis | Generated {{ now()->format('F d, Y H:i') }}</h2>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">TOTAL TRANSACTIONS</div>
                    <div class="stat-value">{{ number_format($summary['total_transactions']) }}</div>
                </td>
                <td>
                    <div class="stat-label">UNIQUE PRODUCTS</div>
                    <div class="stat-value">{{ number_format($summary['unique_products']) }}</div>
                </td>
                <td>
                    <div class="stat-label">DAILY AVERAGE</div>
                    <div class="stat-value">{{ number_format($summary['daily_average'], 1) }}</div>
                </td>
                <td>
                    <div class="stat-label">ACTIVE CATEGORIES</div>
                    <div class="stat-value">{{ number_format($summary['active_categories']) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Daily Activity -->
    <div class="section-title">Daily Transaction Activity</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 20%;">Date</th>
                <th style="width: 15%;" class="text-right">Transactions</th>
                <th style="width: 15%;" class="text-right">Receipts</th>
                <th style="width: 15%;" class="text-right">Shipments</th>
                <th style="width: 15%;" class="text-right">Issues</th>
                <th style="width: 20%;" class="text-right">Adjustments</th>
            </tr>
        </thead>
        <tbody>
            @forelse($by_date as $date => $data)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</td>
                    <td class="text-right">{{ number_format($data['total']) }}</td>
                    <td class="text-right">{{ number_format($data['receipts']) }}</td>
                    <td class="text-right">{{ number_format($data['shipments']) }}</td>
                    <td class="text-right">{{ number_format($data['issues']) }}</td>
                    <td class="text-right">{{ number_format($data['adjustments']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px; color: #999;">
                        No transaction data found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Category Activity -->
    <div class="section-title">Activity by Category</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40%;">Category</th>
                <th style="width: 20%;" class="text-right">Transactions</th>
                <th style="width: 20%;" class="text-right">Products Affected</th>
                <th style="width: 20%;" class="text-right">% of Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($by_category as $category => $data)
                <tr>
                    <td>{{ $category ?: 'Uncategorized' }}</td>
                    <td class="text-right">{{ number_format($data['transaction_count']) }}</td>
                    <td class="text-right">{{ number_format($data['product_count']) }}</td>
                    <td class="text-right">{{ number_format($data['percentage'], 1) }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="padding: 20px; color: #999;">
                        No category data found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>ForgeDesk Inventory Management System | Usage Analytics ({{ $days }} days) | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>
</body>
</html>
