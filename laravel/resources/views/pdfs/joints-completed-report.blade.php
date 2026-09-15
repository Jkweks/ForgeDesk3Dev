<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Joints Completed Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0 0 5px 0; font-size: 18px; color: #1a1a1a; }
        .header h2 { margin: 0; font-size: 12px; color: #666; }
        .summary-stats { background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; }
        .summary-stats table { width: 100%; border-collapse: collapse; }
        .summary-stats td { padding: 5px 10px; text-align: center; }
        .summary-stats .stat-label { font-weight: bold; color: #555; font-size: 8px; }
        .summary-stats .stat-value { font-size: 16px; font-weight: bold; color: #1a1a1a; }
        .section-header { background: #333; color: white; padding: 5px 8px; font-size: 10px; font-weight: bold; margin-top: 16px; margin-bottom: 4px; }
        table.items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background: #666; color: white; padding: 4px 5px; text-align: left; font-size: 7px; border: 1px solid #555; }
        .items-table td { padding: 4px 5px; border: 1px solid #dee2e6; font-size: 8px; }
        .items-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #dee2e6; font-size: 7px; color: #6c757d; text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <h1>JOINTS COMPLETED REPORT</h1>
        <h2>{{ $summary['start_date'] }} — {{ $summary['end_date'] }} | Generated {{ now()->format('F d, Y H:i') }}</h2>
    </div>

    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">TOTAL JOINTS COMPLETED</div>
                    <div class="stat-value">{{ number_format($summary['total_joints']) }}</div>
                </td>
                <td>
                    <div class="stat-label">ELEVATIONS COMPLETED</div>
                    <div class="stat-value">{{ number_format($summary['total_elevations']) }}</div>
                </td>
                <td>
                    <div class="stat-label">TOP SYSTEM</div>
                    <div class="stat-value" style="font-size:12px;">{{ $summary['top_system'] ?? 'N/A' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-header">BREAKDOWN BY SYSTEM</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 30%;">System</th>
                <th style="width: 20%;" class="text-right">Joints Completed</th>
                <th style="width: 20%;" class="text-right">Elevations</th>
                <th style="width: 15%;" class="text-right">Jobs</th>
                <th style="width: 15%;" class="text-right">% of Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bySystem as $row)
                <tr>
                    <td>{{ $row['system'] }}</td>
                    <td class="text-right">{{ number_format($row['joints']) }}</td>
                    <td class="text-right">{{ number_format($row['elevation_count']) }}</td>
                    <td class="text-right">{{ number_format($row['job_count']) }}</td>
                    <td class="text-right">{{ $row['percent_of_total'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center" style="color:#999; padding: 8px;">No completed joints in this date range.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-header">BREAKDOWN BY COMPLEXITY TIER</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40%;">Tier</th>
                <th style="width: 30%;" class="text-right">Joints Completed</th>
                <th style="width: 30%;" class="text-right">Elevations</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byTier as $row)
                <tr>
                    <td>{{ $row['tier'] }}</td>
                    <td class="text-right">{{ number_format($row['joints']) }}</td>
                    <td class="text-right">{{ number_format($row['elevation_count']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center" style="color:#999; padding: 8px;">No completed joints in this date range.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-header">DAILY TOTALS</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;">Date</th>
                <th style="width: 25%;" class="text-right">Joints Completed</th>
                <th style="width: 25%;" class="text-right">Elevations</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byDate as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td class="text-right">{{ number_format($row['joints']) }}</td>
                    <td class="text-right">{{ number_format($row['elevation_count']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center" style="color:#999; padding: 8px;">No completed joints in this date range.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>ForgeDesk Fabrication System | Joints Completed Report | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>

</body>
</html>
