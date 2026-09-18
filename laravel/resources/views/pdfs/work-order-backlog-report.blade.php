<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Work Order Backlog Report</title>
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
        .badge-overdue { color: #c0392b; font-weight: bold; }
        .badge-hold { color: #b8860b; font-weight: bold; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #dee2e6; font-size: 7px; color: #6c757d; text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <h1>WORK ORDER BACKLOG / QUEUE REPORT</h1>
        <h2>Generated {{ now()->format('F d, Y H:i') }}</h2>
    </div>

    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">TOTAL OPEN</div>
                    <div class="stat-value">{{ $summary['total_open'] }}</div>
                </td>
                <td>
                    <div class="stat-label">ACTIVE</div>
                    <div class="stat-value">{{ $summary['active_count'] }}</div>
                </td>
                <td>
                    <div class="stat-label">ON HOLD</div>
                    <div class="stat-value">{{ $summary['on_hold_count'] }}</div>
                </td>
                <td>
                    <div class="stat-label">OVERDUE</div>
                    <div class="stat-value" style="color: {{ $summary['overdue_count'] > 0 ? '#c0392b' : '#27ae60' }};">{{ $summary['overdue_count'] }}</div>
                </td>
                <td>
                    <div class="stat-label">DUE THIS WEEK</div>
                    <div class="stat-value">{{ $summary['due_this_week'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%;">Pri</th>
                <th style="width: 10%;">Release</th>
                <th style="width: 8%;">Job #</th>
                <th style="width: 20%;">Job Name</th>
                <th style="width: 7%;">Status</th>
                <th style="width: 8%;">Due Date</th>
                <th style="width: 6%;" class="text-center">Days</th>
                <th style="width: 15%;">Assigned To</th>
                <th style="width: 8%;" class="text-center">Elevations</th>
                <th style="width: 6%;" class="text-center">Open Steps</th>
            </tr>
        </thead>
        <tbody>
            @forelse($workOrders as $wo)
                <tr>
                    <td>{{ $wo['priority'] ?? '—' }}</td>
                    <td>{{ $wo['release_label'] }}</td>
                    <td>{{ $wo['job_number'] ?? '—' }}</td>
                    <td>{{ $wo['job_name'] ?? '—' }}</td>
                    <td class="{{ $wo['status'] === 'on_hold' ? 'badge-hold' : '' }}">{{ ucfirst($wo['status']) }}</td>
                    <td>{{ $wo['due_date'] ?? '—' }}</td>
                    <td class="text-center {{ $wo['is_overdue'] ? 'badge-overdue' : '' }}">{{ $wo['days_until_due'] ?? '—' }}</td>
                    <td>{{ $wo['assigned_users']->implode(', ') ?: '—' }}</td>
                    <td class="text-center">{{ $wo['elevations_complete_count'] }}/{{ $wo['elevation_count'] }}</td>
                    <td class="text-center">{{ $wo['open_steps_count'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="color:#999; padding: 8px;">No open work orders found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>ForgeDesk Fabrication System | Work Order Backlog Report | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>

</body>
</html>
