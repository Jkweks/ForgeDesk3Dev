<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Job Status Summary Report</title>
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
        table.items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background: #666; color: white; padding: 4px 5px; text-align: left; font-size: 7px; border: 1px solid #555; }
        .items-table td { padding: 4px 5px; border: 1px solid #dee2e6; font-size: 8px; }
        .items-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge-risk { color: #c0392b; font-weight: bold; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #dee2e6; font-size: 7px; color: #6c757d; text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <h1>JOB STATUS SUMMARY REPORT</h1>
        <h2>Generated {{ now()->format('F d, Y H:i') }}</h2>
    </div>

    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">TOTAL JOBS</div>
                    <div class="stat-value">{{ $summary['total_jobs'] }}</div>
                </td>
                <td>
                    <div class="stat-label">ACTIVE</div>
                    <div class="stat-value">{{ $summary['active_jobs'] }}</div>
                </td>
                <td>
                    <div class="stat-label">ON HOLD</div>
                    <div class="stat-value">{{ $summary['on_hold_jobs'] }}</div>
                </td>
                <td>
                    <div class="stat-label">AT RISK</div>
                    <div class="stat-value" style="color: {{ $summary['at_risk_jobs'] > 0 ? '#c0392b' : '#27ae60' }};">{{ $summary['at_risk_jobs'] }}</div>
                </td>
                <td>
                    <div class="stat-label">AVG MATERIAL FULFILLMENT</div>
                    <div class="stat-value">{{ $summary['avg_material_fulfillment_pct'] !== null ? $summary['avg_material_fulfillment_pct'].'%' : 'N/A' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">Job #</th>
                <th style="width: 16%;">Job Name</th>
                <th style="width: 12%;">Customer</th>
                <th style="width: 7%;">Status</th>
                <th style="width: 10%;">Superintendent</th>
                <th style="width: 9%;">Target Completion</th>
                <th style="width: 6%;" class="text-center">Days</th>
                <th style="width: 8%;" class="text-center">Material %</th>
                <th style="width: 8%;" class="text-center">Reservations Open</th>
                <th style="width: 16%;" class="text-center">Work Orders (Active/Hold/Done)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($jobs as $job)
                <tr>
                    <td>{{ $job['job_number'] }}</td>
                    <td>{{ $job['job_name'] }}</td>
                    <td>{{ $job['customer_name'] ?? '—' }}</td>
                    <td>{{ ucfirst($job['status']) }}</td>
                    <td>{{ $job['superintendent'] ?? '—' }}</td>
                    <td>{{ $job['target_completion_date'] ?? '—' }}</td>
                    <td class="text-center {{ $job['is_at_risk'] ? 'badge-risk' : '' }}">{{ $job['days_until_completion'] ?? '—' }}</td>
                    <td class="text-center">{{ $job['material_fulfillment_pct'] !== null ? $job['material_fulfillment_pct'].'%' : 'N/A' }}</td>
                    <td class="text-center">{{ $job['open_reservation_count'] }}</td>
                    <td class="text-center">{{ $job['work_orders_active'] }} / {{ $job['work_orders_on_hold'] }} / {{ $job['work_orders_complete'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="color:#999; padding: 8px;">No jobs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>ForgeDesk ERP | Job Status Summary Report | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>

</body>
</html>
