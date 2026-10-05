<?php

use App\Http\Controllers\Api\Reports\FabricationReportsController;
use App\Http\Controllers\Api\Reports\InventoryReportsController;
use App\Http\Controllers\Api\Reports\JobReportsController;

/*
 * Reports registry — drives the Reports page menu (grouped by category, searchable, permission-filtered) and
 * its /reports/{key} deep links. See docs/plans/reports-registry.md.
 *
 *  key         the id used by showReport() and the URL; also the report card's DOM id (`{key}Report`)
 *  title/icon/color   menu button (Tabler icon name without "ti-", Tabler colour name)
 *  category    a key of 'categories' below
 *  permission  hidden in the UI without it (server-side checks stay on the API endpoints)
 *  controller  the domain controller (Api\Reports\*) that serves it
 *  data        GET /api/v1/reports/{uri} => controller action; needs reports.view
 *  export      GET /api/v1/reports/{uri} => controller action (PDF/CSV downloads); also needs reports.export
 *  csv         the ?type= value for GET /reports/export, served by the controller's csvExport()
 *
 * Each report is a self-contained resources/views/reports/partials/{key}.blade.php: its card markup plus a
 * script that registers window.ReportModules[key].load. A new report = a registry entry + a partial.
 */
return [
    'categories' => [
        'inventory' => 'Inventory',
        'fabrication' => 'Fabrication',
        'jobs' => 'Jobs',
    ],

    'reports' => [
        ['key' => 'lowStock', 'title' => 'Low Stock', 'icon' => 'alert-triangle', 'color' => 'primary', 'category' => 'inventory', 'controller' => InventoryReportsController::class, 'data' => ['low-stock' => 'lowStockReport'], 'export' => ['low-stock/pdf' => 'lowStockPdf'], 'csv' => 'low_stock'],
        ['key' => 'committed', 'title' => 'Committed', 'icon' => 'lock', 'color' => 'info', 'category' => 'inventory', 'controller' => InventoryReportsController::class, 'data' => ['committed-parts' => 'committedPartsReport'], 'export' => ['committed-parts/pdf' => 'committedPartsPdf'], 'csv' => 'committed'],
        ['key' => 'velocity', 'title' => 'Velocity', 'icon' => 'trending-up', 'color' => 'success', 'category' => 'inventory', 'controller' => InventoryReportsController::class, 'data' => ['velocity' => 'stockVelocityAnalysis'], 'export' => ['velocity/pdf' => 'velocityAnalysisPdf'], 'csv' => 'velocity'],
        ['key' => 'reorder', 'title' => 'Reorder', 'icon' => 'shopping-cart', 'color' => 'warning', 'category' => 'inventory', 'controller' => InventoryReportsController::class, 'data' => ['reorder-recommendations' => 'reorderRecommendations'], 'export' => ['reorder-recommendations/pdf' => 'reorderRecommendationsPdf'], 'csv' => 'reorder'],
        ['key' => 'obsolete', 'title' => 'Obsolete', 'icon' => 'archive', 'color' => 'danger', 'category' => 'inventory', 'controller' => InventoryReportsController::class, 'data' => ['obsolete' => 'obsoleteInventory'], 'export' => ['obsolete/pdf' => 'obsoleteInventoryPdf'], 'csv' => 'obsolete'],
        ['key' => 'usage', 'title' => 'Usage', 'icon' => 'activity', 'color' => 'secondary', 'category' => 'inventory', 'controller' => InventoryReportsController::class, 'data' => ['usage-analytics' => 'usageAnalytics'], 'export' => ['usage-analytics/pdf' => 'usageAnalyticsPdf']],
        ['key' => 'monthlyStatement', 'title' => 'Monthly Statement', 'icon' => 'calendar-stats', 'color' => 'cyan', 'category' => 'inventory', 'controller' => InventoryReportsController::class, 'data' => ['monthly-statement' => 'monthlyInventoryStatement'], 'export' => ['monthly-statement/pdf' => 'monthlyInventoryStatementPdf'], 'csv' => 'monthly_statement'],
        ['key' => 'inventory', 'title' => 'Inventory', 'icon' => 'packages', 'color' => 'purple', 'category' => 'inventory', 'controller' => InventoryReportsController::class, 'data' => ['inventory/data' => 'inventoryReportData'], 'export' => ['inventory/csv' => 'exportInventoryCsv', 'inventory/pdf' => 'inventoryReportPdf']],
        ['key' => 'storageLocation', 'title' => 'Storage Locations', 'icon' => 'building-warehouse', 'color' => 'teal', 'category' => 'inventory', 'controller' => InventoryReportsController::class, 'data' => ['storage-locations' => 'storageLocationReport'], 'export' => ['storage-locations/pdf' => 'storageLocationPdf']],
        ['key' => 'workOrderBacklog', 'title' => 'WO Backlog', 'icon' => 'list-details', 'color' => 'orange', 'category' => 'fabrication', 'controller' => FabricationReportsController::class, 'data' => ['work-order-backlog' => 'workOrderBacklogReport'], 'export' => ['work-order-backlog/pdf' => 'workOrderBacklogPdf'], 'csv' => 'work_order_backlog'],
        ['key' => 'jointsCompleted', 'title' => 'Joints Completed', 'icon' => 'git-merge', 'color' => 'lime', 'category' => 'fabrication', 'controller' => FabricationReportsController::class, 'data' => ['joints-completed' => 'jointsCompletedReport'], 'export' => ['joints-completed/pdf' => 'jointsCompletedPdf'], 'csv' => 'joints_completed'],
        ['key' => 'jobStatusSummary', 'title' => 'Job Status', 'icon' => 'clipboard-list', 'color' => 'indigo', 'category' => 'jobs', 'controller' => JobReportsController::class, 'data' => ['job-status-summary' => 'jobStatusSummaryReport'], 'export' => ['job-status-summary/pdf' => 'jobStatusSummaryPdf'], 'csv' => 'job_status_summary'],
        ['key' => 'materialUsage', 'title' => 'Material Usage', 'icon' => 'ruler-measure', 'color' => 'azure', 'category' => 'jobs', 'controller' => JobReportsController::class, 'data' => ['work-order-material-usage' => 'workOrderMaterialUsageReport'], 'export' => []],
    ],
];
