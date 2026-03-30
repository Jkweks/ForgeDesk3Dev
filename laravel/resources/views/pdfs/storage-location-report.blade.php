<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Storage Location Contents Report</title>
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
        .location-block {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .location-header {
            background: #333;
            color: white;
            padding: 5px 8px;
            font-size: 10px;
            font-weight: bold;
        }
        .location-meta {
            font-size: 8px;
            color: #aaa;
            font-weight: normal;
            margin-left: 8px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            background: #666;
            color: white;
            padding: 4px 5px;
            text-align: left;
            font-size: 7px;
            border: 1px solid #555;
        }
        .items-table td {
            padding: 4px 5px;
            border: 1px solid #dee2e6;
            font-size: 8px;
        }
        .items-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .location-total {
            background: #eaeaea;
            font-weight: bold;
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

    <div class="header">
        <h1>STORAGE LOCATION CONTENTS REPORT</h1>
        <h2>Generated {{ now()->format('F d, Y H:i') }}</h2>
    </div>

    <div class="summary-stats">
        <table>
            <tr>
                <td>
                    <div class="stat-label">LOCATIONS WITH STOCK</div>
                    <div class="stat-value">{{ $summary['total_locations'] }}</div>
                </td>
                <td>
                    <div class="stat-label">TOTAL LINE ITEMS</div>
                    <div class="stat-value">{{ number_format($summary['total_line_items']) }}</div>
                </td>
                <td>
                    <div class="stat-label">TOTAL QUANTITY</div>
                    <div class="stat-value">{{ number_format($summary['total_qty']) }}</div>
                </td>
            </tr>
        </table>
    </div>

    @forelse($locations as $location)
        <div class="location-block">
            <div class="location-header">
                {{ $location['name'] }}
                @if($location['code'])
                    <span class="location-meta">[ {{ $location['code'] }} ]</span>
                @endif
                @if($location['full_path'] && $location['full_path'] !== $location['name'])
                    <span class="location-meta">{{ $location['full_path'] }}</span>
                @endif
                @php
                    $address = collect([
                        $location['aisle']    ? 'Aisle ' . $location['aisle']    : null,
                        $location['bay']      ? 'Bay '   . $location['bay']      : null,
                        $location['level']    ? 'Level '  . $location['level']    : null,
                        $location['position'] ? 'Pos '   . $location['position'] : null,
                    ])->filter()->implode(' ');
                @endphp
                @if($address)
                    <span class="location-meta">— {{ $address }}</span>
                @endif
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">Part #</th>
                        <th style="width: 35%;">Description</th>
                        <th style="width: 10%;">SKU</th>
                        <th style="width: 8%;" class="text-right">Quantity</th>
                        <th style="width: 5%;" class="text-center">UOM</th>
                        <th style="width: 8%;" class="text-center">Primary</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($location['items'] as $item)
                        <tr>
                            <td>{{ $item['part_number'] ?? '—' }}</td>
                            <td>{{ $item['description'] }}</td>
                            <td>{{ $item['sku'] }}</td>
                            <td class="text-right">{{ number_format($item['quantity']) }}</td>
                            <td class="text-center">{{ $item['uom'] }}</td>
                            <td class="text-center">{{ $item['is_primary'] ? 'Yes' : '' }}</td>
                        </tr>
                    @endforeach
                    <tr class="location-total">
                        <td colspan="3" class="text-right">Location Total</td>
                        <td class="text-right">{{ number_format($location['total_qty']) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <p style="text-align:center; color:#999; margin-top:40px;">No storage locations with inventory found.</p>
    @endforelse

    <div class="footer">
        <p>ForgeDesk Inventory Management System | Storage Location Contents Report | Generated {{ now()->format('M d, Y H:i:s') }}</p>
    </div>

</body>
</html>
