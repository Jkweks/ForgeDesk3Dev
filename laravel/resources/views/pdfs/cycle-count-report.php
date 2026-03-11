<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cycle Count Report - <?php echo htmlspecialchars($session->session_number); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            padding: 20px;
        }

        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 20pt;
            margin-bottom: 5px;
        }

        .header-info {
            display: table;
            width: 100%;
            margin-top: 10px;
        }

        .header-column {
            display: table-cell;
            width: 33%;
            vertical-align: top;
        }

        .info-row {
            margin-bottom: 3px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th {
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
        }

        td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 9pt;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .variance-positive {
            color: #28a745;
            font-weight: bold;
        }

        .variance-negative {
            color: #dc3545;
            font-weight: bold;
        }

        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8pt;
            display: inline-block;
        }

        .status-within-tolerance {
            background-color: #d4edda;
            color: #155724;
        }

        .status-requires-review {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-pending {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .status-approved {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .summary {
            margin-top: 20px;
            border-top: 2px solid #333;
            padding-top: 10px;
        }

        .summary-grid {
            display: table;
            width: 100%;
        }

        .summary-item {
            display: table-cell;
            width: 25%;
            padding: 10px;
            text-align: center;
        }

        .summary-value {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .summary-label {
            font-size: 9pt;
            color: #666;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }

        .skipped-row {
            background-color: #f5f5f5;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Cycle Count Report</h1>
        <div class="header-info">
            <div class="header-column">
                <div class="info-row">
                    <span class="info-label">Session Number:</span>
                    <span><?php echo htmlspecialchars($session->session_number); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span><?php echo ucfirst(str_replace('_', ' ', $session->status)); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Scheduled Date:</span>
                    <span><?php echo $session->scheduled_date->format('M d, Y'); ?></span>
                </div>
            </div>
            <div class="header-column">
                <div class="info-row">
                    <span class="info-label">Location:</span>
                    <span><?php echo $session->location ?: 'All Locations'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Assigned To:</span>
                    <span><?php echo $session->assignedUser ? $session->assignedUser->name : 'Unassigned'; ?></span>
                </div>
                <?php if ($session->completed_at): ?>
                <div class="info-row">
                    <span class="info-label">Completed:</span>
                    <span><?php echo $session->completed_at->format('M d, Y g:i A'); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="header-column">
                <div class="info-row">
                    <span class="info-label">Total Items:</span>
                    <span><?php echo $session->total_items; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Items Counted:</span>
                    <span><?php echo $session->counted_items; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Progress:</span>
                    <span><?php echo $session->progress_percentage; ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">SKU</th>
                <th style="width: 20%;">Description</th>
                <th style="width: 10%;">Location</th>
                <th class="text-right" style="width: 8%;">System Qty<br><small>(Before)</small></th>
                <th class="text-right" style="width: 8%;">Counted Qty<br><small>(After)</small></th>
                <th class="text-right" style="width: 8%;">Variance</th>
                <th class="text-center" style="width: 8%;">Status</th>
                <th style="width: 20%;">Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($session->items as $item): ?>
                <?php
                    $isSkipped = $item->counted_quantity === null;
                    $rowClass = $isSkipped ? 'skipped-row' : '';
                    $variance = $item->variance;
                    $varianceClass = '';
                    if ($variance > 0) $varianceClass = 'variance-positive';
                    elseif ($variance < 0) $varianceClass = 'variance-negative';

                    // Get pack info
                    $packSize = $item->pack_size;
                    $hasPackSize = $packSize > 1;
                    $unitLabel = $hasPackSize ? ' packs' : '';
                    $packInfo = $hasPackSize ? " ({$packSize}/pack)" : '';

                    // Status badge class
                    $statusClass = 'status-' . str_replace('_', '-', $item->variance_status);
                    $statusText = ucfirst(str_replace('_', ' ', $item->variance_status));

                    // Location display
                    $locationDisplay = '-';
                    if ($item->location) {
                        if ($item->location->storageLocation) {
                            $locationDisplay = $item->location->storageLocation->name;
                        } else {
                            $locationDisplay = $item->location->location;
                        }
                    }
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td><?php echo htmlspecialchars($item->product->sku); ?></td>
                    <td><?php echo htmlspecialchars($item->product->description) . $packInfo; ?></td>
                    <td><?php echo htmlspecialchars($locationDisplay); ?></td>
                    <td class="text-right"><?php echo $item->system_quantity . $unitLabel; ?></td>
                    <td class="text-right">
                        <?php echo $isSkipped ? 'Skipped' : ($item->counted_quantity . $unitLabel); ?>
                    </td>
                    <td class="text-right <?php echo $varianceClass; ?>">
                        <?php
                            if ($isSkipped) {
                                echo '-';
                            } else {
                                echo ($variance > 0 ? '+' : '') . $variance . $unitLabel;
                            }
                        ?>
                    </td>
                    <td class="text-center">
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($item->count_notes ?: ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value"><?php echo $session->total_items; ?></div>
                <div class="summary-label">Total Items</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $session->counted_items; ?></div>
                <div class="summary-label">Items Counted</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $session->variance_items; ?></div>
                <div class="summary-label">Items with Variance</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $session->accuracy_percentage; ?>%</div>
                <div class="summary-label">Accuracy</div>
            </div>
        </div>
    </div>

    <?php if ($session->notes): ?>
    <div style="margin-top: 20px; padding: 10px; background-color: #f9f9f9; border-left: 4px solid #007bff;">
        <div style="font-weight: bold; margin-bottom: 5px;">Session Notes:</div>
        <div><?php echo nl2br(htmlspecialchars($session->notes)); ?></div>
    </div>
    <?php endif; ?>

    <div class="footer">
        <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        <p>ForgeDesk Inventory Management System</p>
    </div>
</body>
</html>
