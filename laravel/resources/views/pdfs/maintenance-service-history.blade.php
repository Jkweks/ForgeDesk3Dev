<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Maintenance Service History</title>
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
        <h1>MAINTENANCE SERVICE HISTORY</h1>
        <h2>
            {{ $summary['machine'] }}
            @if($summary['date_from'] || $summary['date_to'])
                | {{ $summary['date_from'] ?? 'Beginning' }} &ndash; {{ $summary['date_to'] ?? 'Present' }}
            @endif
            | Generated {{ now()->format('F d, Y H:i') }}
        </h2>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">TOTAL SERVICE RECORDS</div>
                    <div class="stat-value">{{ $summary['total_records'] }}</div>
                </td>
                <td>
                    <div class="stat-label">TOTAL DOWNTIME</div>
                    <div class="stat-value">{{ number_format($summary['total_downtime_minutes'] / 60, 1) }}h</div>
                </td>
                <td>
                    <div class="stat-label">TOTAL LABOR HOURS</div>
                    <div class="stat-value">{{ number_format($summary['total_labor_hours'], 1) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Records Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 9%;">Date</th>
                <th style="width: 14%;">Machine</th>
                <th style="width: 16%;">Task</th>
                <th style="width: 11%;">Performed By</th>
                <th style="width: 8%;" class="text-right">Downtime</th>
                <th style="width: 8%;" class="text-right">Labor Hrs</th>
                <th style="width: 34%;">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
                <tr>
                    <td>{{ $record->performed_at ? \Carbon\Carbon::parse($record->performed_at)->format('m/d/Y') : '-' }}</td>
                    <td>{{ $record->machine->name ?? 'Unknown' }}</td>
                    <td>{{ $record->task->title ?? 'Unplanned' }}</td>
                    <td>{{ $record->performer->name ?? '-' }}</td>
                    <td class="text-right">{{ $record->downtime_minutes ? $record->downtime_minutes.' min' : '-' }}</td>
                    <td class="text-right">{{ $record->labor_hours ?? '-' }}</td>
                    <td>{{ $record->notes ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #999;">
                        No service records found for the selected filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>ForgeDesk Maintenance | Service History Report | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>
</body>
</html>
