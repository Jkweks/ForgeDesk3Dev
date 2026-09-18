<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quality Reports</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0 0 5px 0; font-size: 18px; color: #1a1a1a; }
        .header h2 { margin: 0; font-size: 12px; color: #666; }
        table.items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background: #666; color: white; padding: 4px 5px; text-align: left; font-size: 7px; border: 1px solid #555; }
        .items-table td { padding: 4px 5px; border: 1px solid #dee2e6; font-size: 8px; }
        .items-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .text-center { text-align: center; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #dee2e6; font-size: 7px; color: #6c757d; text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <h1>QUALITY REPORTS</h1>
        <h2>Generated {{ now()->format('F d, Y H:i') }} | {{ $reports->count() }} report(s)</h2>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Report Date</th>
                <th>Job</th>
                <th>Work Order</th>
                <th>Elevation</th>
                <th>Replacement?</th>
                <th>Problem Type</th>
                <th>Reported By</th>
                <th>Status</th>
                <th>Verified By</th>
                <th>Verified At</th>
                <th>Reviewed By</th>
                <th>Reviewed At</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reports as $r)
                <tr>
                    <td>{{ $r->report_date?->toDateString() }}</td>
                    <td>{{ $jobNames[$r->id] }}</td>
                    <td>{{ $r->workOrder?->releaseLabel() }}</td>
                    <td>{{ $elevationTags[$r->id] }}</td>
                    <td>{{ $r->replacement_needed === true ? 'Yes' : ($r->replacement_needed === false ? 'No' : '') }}</td>
                    <td>{{ $r->problem_type }}</td>
                    <td>{{ $r->inspector_name }}</td>
                    <td>{{ $r->status }}</td>
                    <td>{{ $r->verifier?->name }}</td>
                    <td>{{ $r->verified_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $r->reviewer?->name }}</td>
                    <td>{{ $r->reviewed_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center" style="color:#999; padding: 8px;">No quality reports found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>ForgeDesk Fabrication System | Quality Reports | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>

</body>
</html>
