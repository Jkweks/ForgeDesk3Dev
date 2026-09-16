<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quality Analytics Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0 0 5px 0; font-size: 18px; color: #1a1a1a; }
        .header h2 { margin: 0; font-size: 12px; color: #666; }
        .section-header { background: #333; color: white; padding: 5px 8px; font-size: 10px; font-weight: bold; margin-top: 16px; margin-bottom: 4px; }
        .chart-wrap { text-align: center; margin-bottom: 8px; }
        .chart-img { display: inline-block; width: auto; height: auto; max-width: 100%; max-height: 260px; }
        table.items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background: #666; color: white; padding: 4px 5px; text-align: left; font-size: 7px; border: 1px solid #555; }
        .items-table td { padding: 4px 5px; border: 1px solid #dee2e6; font-size: 8px; }
        .items-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .page-break { page-break-before: always; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #dee2e6; font-size: 7px; color: #6c757d; text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <h1>QUALITY ANALYTICS REPORT</h1>
        <h2>Generated {{ now()->format('F d, Y H:i') }}</h2>
    </div>

    <div class="section-header">INCIDENT RATE BY MONTH</div>
    @if ($incidentChart)
        <div class="chart-wrap"><img class="chart-img" src="{{ $incidentChart }}"></div>
    @endif
    <table class="items-table">
        <thead>
            <tr>
                <th>Month</th>
                <th class="text-right">Joints Completed</th>
                <th class="text-right">Cases</th>
                <th class="text-right">Incident Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($incidentRows as $row)
                <tr>
                    <td>{{ $row['month_label'] }}</td>
                    <td class="text-right">{{ number_format($row['joint_count']) }}</td>
                    <td class="text-right">{{ number_format($row['case_count']) }}</td>
                    <td class="text-right">{{ isset($row['incident_rate']) ? $row['incident_rate'].'%' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center" style="color:#999; padding: 8px;">No data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>

    <div class="section-header">PROBLEM TYPES (ROLLING 13 WEEKS)</div>
    @if ($problemTypesChart)
        <div class="chart-wrap"><img class="chart-img" src="{{ $problemTypesChart }}"></div>
    @endif
    <table class="items-table">
        <thead>
            <tr>
                <th>Problem Type</th>
                <th class="text-right">Count</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($problemTypeRows as $row)
                <tr>
                    <td>{{ $row['problem_type'] }}</td>
                    <td class="text-right">{{ number_format($row['count']) }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="text-center" style="color:#999; padding: 8px;">No data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>

    <div class="section-header">WEEKLY CASES (13-WEEK TREND)</div>
    @if ($weeklyChart)
        <div class="chart-wrap"><img class="chart-img" src="{{ $weeklyChart }}"></div>
    @endif
    <table class="items-table">
        <thead>
            <tr>
                <th>Week</th>
                <th class="text-right">Cases</th>
                <th class="text-right">13-Week Trend</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($weeklyRows as $row)
                <tr>
                    <td>{{ $row['week'] }} ({{ $row['week_start'] }})</td>
                    <td class="text-right">{{ number_format($row['case_count']) }}</td>
                    <td class="text-right">{{ $row['trend_value'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center" style="color:#999; padding: 8px;">No data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>ForgeDesk Fabrication System | Quality Analytics Report | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>

</body>
</html>
