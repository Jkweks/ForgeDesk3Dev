@extends('layouts.app')

@section('content')
<div class="container-xl">
  <!-- Page header -->
  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">
          <i class="ti ti-chart-bar me-2"></i>Reports & Analytics
        </h2>
        <div class="text-muted mt-1">Inventory insights and analysis tools</div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
          <button class="btn btn-icon" onclick="refreshAllReports()">
            <i class="ti ti-refresh"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Report Navigation -->
  <div class="page-body">
    <div class="row row-deck row-cards">
      <!-- Report Selector Cards -->
      <div class="col-12">
        <div class="card">
          <div class="card-body">
            <div class="row g-2">
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-primary w-100" onclick="showReport('lowStock')">
                  <i class="ti ti-alert-triangle me-1"></i>
                  Low Stock
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-info w-100" onclick="showReport('committed')">
                  <i class="ti ti-lock me-1"></i>
                  Committed
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-success w-100" onclick="showReport('velocity')">
                  <i class="ti ti-trending-up me-1"></i>
                  Velocity
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-warning w-100" onclick="showReport('reorder')">
                  <i class="ti ti-shopping-cart me-1"></i>
                  Reorder
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-danger w-100" onclick="showReport('obsolete')">
                  <i class="ti ti-archive me-1"></i>
                  Obsolete
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-secondary w-100" onclick="showReport('usage')">
                  <i class="ti ti-activity me-1"></i>
                  Usage
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-cyan w-100" onclick="showReport('monthlyStatement')">
                  <i class="ti ti-calendar-stats me-1"></i>
                  Monthly Statement
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-purple w-100" onclick="showReport('inventory')">
                  <i class="ti ti-packages me-1"></i>
                  Inventory
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Low Stock Report -->
      <div class="col-12" id="lowStockReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-alert-triangle me-2"></i>Low Stock & Critical Items</h3>
            <div class="ms-auto d-flex gap-2">
              <button class="btn btn-sm btn-outline-primary" onclick="exportReportPdf('low-stock')">
                <i class="ti ti-file-type-pdf me-1"></i>PDF
              </button>
              <button class="btn btn-sm btn-primary" onclick="exportReport('low_stock')">
                <i class="ti ti-download me-1"></i>CSV
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Low Stock Items</div>
                    <div class="h2 mb-0" id="lowStockCount">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Critical Items</div>
                    <div class="h2 mb-0 text-danger" id="criticalCount">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Affected</div>
                    <div class="h2 mb-0" id="totalAffected">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Value at Risk</div>
                    <div class="h2 mb-0" id="valueAtRisk">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="lowStockLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="lowStockContent" style="display: none;">
              <div class="mb-3">
                <input type="text" class="form-control form-control-sm" id="lowStockSearch" placeholder="Search SKU or description..." style="max-width: 300px;">
              </div>
              <div class="table-responsive">
                <table class="table table-vcenter" id="lowStockTable">
                  <thead>
                    <tr>
                      <th class="sortable-report" data-sort="sku" data-report="lowStock" style="cursor: pointer;">SKU <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="description" data-report="lowStock" style="cursor: pointer;">Description <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="category" data-report="lowStock" style="cursor: pointer;">Category <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="on_hand" data-report="lowStock" style="cursor: pointer;">On Hand <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="available" data-report="lowStock" style="cursor: pointer;">Available <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="minimum" data-report="lowStock" style="cursor: pointer;">Minimum <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="status" data-report="lowStock" style="cursor: pointer;">Status <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="total_value" data-report="lowStock" style="cursor: pointer;">Value <span class="sort-icon"></span></th>
                    </tr>
                  </thead>
                  <tbody id="lowStockTableBody"></tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center" id="lowStockPagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Committed Parts Report -->
      <div class="col-12" id="committedReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-lock me-2"></i>Committed Parts</h3>
            <div class="ms-auto d-flex gap-2">
              <button class="btn btn-sm btn-outline-primary" onclick="exportReportPdf('committed-parts')">
                <i class="ti ti-file-type-pdf me-1"></i>PDF
              </button>
              <button class="btn btn-sm btn-primary" onclick="exportReport('committed')">
                <i class="ti ti-download me-1"></i>CSV
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Products with Commitments</div>
                    <div class="h2 mb-0" id="committedProductsCount">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Qty Committed</div>
                    <div class="h2 mb-0" id="totalQtyCommitted">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Value Committed</div>
                    <div class="h2 mb-0" id="valueCommitted">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="committedLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="committedContent" style="display: none;">
              <div class="mb-3">
                <input type="text" class="form-control form-control-sm" id="committedSearch" placeholder="Search SKU or description..." style="max-width: 300px;">
              </div>
              <div class="table-responsive">
                <table class="table table-vcenter" id="committedTable">
                  <thead>
                    <tr>
                      <th class="sortable-report" data-sort="sku" data-report="committed" style="cursor: pointer;">SKU <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="description" data-report="committed" style="cursor: pointer;">Description <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="category" data-report="committed" style="cursor: pointer;">Category <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="committed" data-report="committed" style="cursor: pointer;">Committed <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="available" data-report="committed" style="cursor: pointer;">Available <span class="sort-icon"></span></th>
                      <th>Reservations</th>
                    </tr>
                  </thead>
                  <tbody id="committedTableBody"></tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center" id="committedPagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Velocity Analysis Report -->
      <div class="col-12" id="velocityReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-trending-up me-2"></i>Stock Velocity Analysis</h3>
            <div class="ms-auto d-flex gap-2">
              <select class="form-select form-select-sm" id="velocityDays" onchange="loadVelocityReport()">
                <option value="30">Last 30 Days</option>
                <option value="60">Last 60 Days</option>
                <option value="90" selected>Last 90 Days</option>
                <option value="180">Last 180 Days</option>
              </select>
              <button class="btn btn-sm btn-outline-primary" onclick="exportReportPdf('velocity')">
                <i class="ti ti-file-type-pdf me-1"></i>PDF
              </button>
              <button class="btn btn-sm btn-primary" onclick="exportReport('velocity')">
                <i class="ti ti-download me-1"></i>CSV
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Fast Movers</div>
                    <div class="h2 mb-0 text-success" id="fastMovers">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Medium Movers</div>
                    <div class="h2 mb-0 text-info" id="mediumMovers">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Slow Movers</div>
                    <div class="h2 mb-0 text-warning" id="slowMovers">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Analyzed</div>
                    <div class="h2 mb-0" id="totalAnalyzed">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="velocityLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="velocityContent" style="display: none;">
              <div class="mb-3">
                <input type="text" class="form-control form-control-sm" id="velocitySearch" placeholder="Search SKU or description..." style="max-width: 300px;">
              </div>
              <div class="table-responsive">
                <table class="table table-vcenter" id="velocityTable">
                  <thead>
                    <tr>
                      <th class="sortable-report" data-sort="sku" data-report="velocity" style="cursor: pointer;">SKU <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="description" data-report="velocity" style="cursor: pointer;">Description <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="category" data-report="velocity" style="cursor: pointer;">Category <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="on_hand" data-report="velocity" style="cursor: pointer;">On Hand <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="receipts" data-report="velocity" style="cursor: pointer;">Receipts <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="shipments" data-report="velocity" style="cursor: pointer;">Shipments <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="turnover_rate" data-report="velocity" style="cursor: pointer;">Turnover % <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="velocity" data-report="velocity" style="cursor: pointer;">Velocity <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="days_until_stockout" data-report="velocity" style="cursor: pointer;">Days to Stockout <span class="sort-icon"></span></th>
                    </tr>
                  </thead>
                  <tbody id="velocityTableBody"></tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center" id="velocityPagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Reorder Recommendations Report -->
      <div class="col-12" id="reorderReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-shopping-cart me-2"></i>Reorder Recommendations</h3>
            <div class="ms-auto d-flex gap-2">
              <button class="btn btn-sm btn-outline-primary" onclick="exportReportPdf('reorder-recommendations')">
                <i class="ti ti-file-type-pdf me-1"></i>PDF
              </button>
              <button class="btn btn-sm btn-primary" onclick="exportReport('reorder')">
                <i class="ti ti-download me-1"></i>CSV
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Items to Reorder</div>
                    <div class="h2 mb-0" id="itemsToReorder">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Critical Items</div>
                    <div class="h2 mb-0 text-danger" id="criticalReorderItems">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Order Value</div>
                    <div class="h2 mb-0" id="totalOrderValue">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="reorderLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="reorderContent" style="display: none;">
              <div class="mb-3">
                <input type="text" class="form-control form-control-sm" id="reorderSearch" placeholder="Search SKU or description..." style="max-width: 300px;">
              </div>
              <div class="table-responsive">
                <table class="table table-vcenter" id="reorderTable">
                  <thead>
                    <tr>
                      <th class="sortable-report" data-sort="sku" data-report="reorder" style="cursor: pointer;">SKU <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="description" data-report="reorder" style="cursor: pointer;">Description <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="supplier" data-report="reorder" style="cursor: pointer;">Supplier <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="available" data-report="reorder" style="cursor: pointer;">Available <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="reorder_point" data-report="reorder" style="cursor: pointer;">Reorder Point <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="shortage" data-report="reorder" style="cursor: pointer;">Shortage <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="recommended_order_qty" data-report="reorder" style="cursor: pointer;">Recommended Qty <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="recommended_order_value" data-report="reorder" style="cursor: pointer;">Est. Cost <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="status" data-report="reorder" style="cursor: pointer;">Status <span class="sort-icon"></span></th>
                    </tr>
                  </thead>
                  <tbody id="reorderTableBody"></tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center" id="reorderPagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Obsolete Inventory Report -->
      <div class="col-12" id="obsoleteReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-archive me-2"></i>Obsolete Inventory</h3>
            <div class="ms-auto d-flex gap-2">
              <select class="form-select form-select-sm" id="obsoleteDays" onchange="loadObsoleteReport()">
                <option value="90">90 Days</option>
                <option value="180" selected>180 Days</option>
                <option value="365">365 Days</option>
              </select>
              <button class="btn btn-sm btn-outline-primary" onclick="exportReportPdf('obsolete')">
                <i class="ti ti-file-type-pdf me-1"></i>PDF
              </button>
              <button class="btn btn-sm btn-primary" onclick="exportReport('obsolete')">
                <i class="ti ti-download me-1"></i>CSV
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Obsolete Candidates</div>
                    <div class="h2 mb-0" id="obsoleteItems">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Used in BOM</div>
                    <div class="h2 mb-0" id="usedInBom">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Value at Risk</div>
                    <div class="h2 mb-0" id="obsoleteValue">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="obsoleteLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="obsoleteContent" style="display: none;">
              <div class="mb-3">
                <input type="text" class="form-control form-control-sm" id="obsoleteSearch" placeholder="Search SKU or description..." style="max-width: 300px;">
              </div>
              <div class="table-responsive">
                <table class="table table-vcenter" id="obsoleteTable">
                  <thead>
                    <tr>
                      <th class="sortable-report" data-sort="sku" data-report="obsolete" style="cursor: pointer;">SKU <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="description" data-report="obsolete" style="cursor: pointer;">Description <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="category" data-report="obsolete" style="cursor: pointer;">Category <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="on_hand" data-report="obsolete" style="cursor: pointer;">On Hand <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="unit_cost" data-report="obsolete" style="cursor: pointer;">Unit Cost <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="total_value" data-report="obsolete" style="cursor: pointer;">Total Value <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="last_shipment_date" data-report="obsolete" style="cursor: pointer;">Last Used <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-sort="days_since_last_use" data-report="obsolete" style="cursor: pointer;">Days Inactive <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-sort="is_used_in_bom" data-report="obsolete" style="cursor: pointer;">In BOM <span class="sort-icon"></span></th>
                    </tr>
                  </thead>
                  <tbody id="obsoleteTableBody"></tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center" id="obsoletePagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Usage Analytics Report -->
      <div class="col-12" id="usageReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-activity me-2"></i>Usage Analytics</h3>
            <div class="ms-auto d-flex gap-2">
              <select class="form-select form-select-sm" id="usageDays" onchange="loadUsageReport()">
                <option value="7">Last 7 Days</option>
                <option value="30" selected>Last 30 Days</option>
                <option value="60">Last 60 Days</option>
                <option value="90">Last 90 Days</option>
              </select>
              <button class="btn btn-sm btn-outline-primary" onclick="exportReportPdf('usage-analytics')">
                <i class="ti ti-file-type-pdf me-1"></i>PDF
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Receipts</div>
                    <div class="h2 mb-0 text-success" id="totalReceipts">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Shipments</div>
                    <div class="h2 mb-0 text-danger" id="totalShipments">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Adjustments</div>
                    <div class="h2 mb-0" id="totalAdjustments">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Period</div>
                    <div class="h2 mb-0" id="periodDays">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="usageLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="usageContent" style="display: none;">
              <div class="row">
                <div class="col-md-6">
                  <h4>Activity by Date</h4>
                  <div class="table-responsive">
                    <table class="table table-sm">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th class="text-end">Receipts</th>
                          <th class="text-end">Shipments</th>
                          <th class="text-end">Transactions</th>
                        </tr>
                      </thead>
                      <tbody id="usageByDateBody"></tbody>
                    </table>
                  </div>
                </div>
                <div class="col-md-6">
                  <h4>Activity by Category</h4>
                  <div class="table-responsive">
                    <table class="table table-sm">
                      <thead>
                        <tr>
                          <th>Category</th>
                          <th class="text-end">Receipts</th>
                          <th class="text-end">Shipments</th>
                          <th class="text-end">Transactions</th>
                        </tr>
                      </thead>
                      <tbody id="usageByCategoryBody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Monthly Inventory Statement Report -->
      <div class="col-12" id="monthlyStatementReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-calendar-stats me-2"></i>Monthly Inventory Statement</h3>
            <div class="ms-auto d-flex gap-2">
              <select class="form-select form-select-sm" id="statementMonth" onchange="loadMonthlyStatementReport()">
                <option value="1">January</option>
                <option value="2">February</option>
                <option value="3">March</option>
                <option value="4">April</option>
                <option value="5">May</option>
                <option value="6">June</option>
                <option value="7">July</option>
                <option value="8">August</option>
                <option value="9">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
              </select>
              <select class="form-select form-select-sm" id="statementYear" onchange="loadMonthlyStatementReport()">
                <option value="2024">2024</option>
                <option value="2025">2025</option>
                <option value="2026" selected>2026</option>
                <option value="2027">2027</option>
              </select>
              <button class="btn btn-sm btn-outline-primary" onclick="exportMonthlyStatementPdf()">
                <i class="ti ti-file-type-pdf me-1"></i>PDF
              </button>
              <button class="btn btn-sm btn-primary" onclick="exportReport('monthly_statement')">
                <i class="ti ti-download me-1"></i>CSV
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Products</div>
                    <div class="h2 mb-0" id="statementProducts">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Beginning Value</div>
                    <div class="h2 mb-0 text-info" id="statementBeginValue">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Ending Value</div>
                    <div class="h2 mb-0 text-success" id="statementEndValue">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Value Change</div>
                    <div class="h2 mb-0" id="statementValueChange">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="statementLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading statement...</div>
            </div>

            <div id="statementContent" style="display: none;">
              <div class="table-responsive">
                <table class="table table-sm table-hover">
                  <thead>
                    <tr>
                      <th>SKU</th>
                      <th>Description</th>
                      <th>Category</th>
                      <th class="text-end">Begin</th>
                      <th class="text-end">Additions</th>
                      <th class="text-end">Deductions</th>
                      <th class="text-end">Ending</th>
                      <th class="text-end">Change</th>
                      <th class="text-end">End Value</th>
                    </tr>
                  </thead>
                  <tbody id="statementBody"></tbody>
                </table>
              </div>

              <div class="mt-3">
                <h5>Transaction Totals</h5>
                <div class="row">
                  <div class="col-md-6">
                    <div class="table-responsive">
                      <table class="table table-sm">
                        <thead>
                          <tr>
                            <th>Additions</th>
                            <th class="text-end">Quantity</th>
                          </tr>
                        </thead>
                        <tbody id="statementAdditionsBody"></tbody>
                      </table>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="table-responsive">
                      <table class="table table-sm">
                        <thead>
                          <tr>
                            <th>Deductions</th>
                            <th class="text-end">Quantity</th>
                          </tr>
                        </thead>
                        <tbody id="statementDeductionsBody"></tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Inventory Report -->
      <div class="col-12" id="inventoryReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-packages me-2"></i>Inventory Report</h3>
            <div class="ms-auto d-flex gap-2">
              <button class="btn btn-sm btn-outline-primary" onclick="exportInventoryPdf()">
                <i class="ti ti-file-type-pdf me-1"></i>PDF
              </button>
              <button class="btn btn-sm btn-primary" onclick="exportInventoryCsv()">
                <i class="ti ti-download me-1"></i>CSV
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Products</div>
                    <div class="h2 mb-0" id="inventoryProductsCount">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Available Qty</div>
                    <div class="h2 mb-0 text-info" id="inventoryAvailableQty">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Value (List)</div>
                    <div class="h2 mb-0 text-success" id="inventoryValueList">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Value (Net)</div>
                    <div class="h2 mb-0 text-primary" id="inventoryValueNet">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="inventoryLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading inventory...</div>
            </div>

            <div id="inventoryContent" style="display: none;">
              <div class="table-responsive">
                <table class="table table-sm table-hover">
                  <thead>
                    <tr>
                      <th class="sortable-report" data-report="inventory" data-sort="part_number" style="cursor: pointer;">Part # <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-report="inventory" data-sort="finish" style="cursor: pointer;">Finish <span class="sort-icon"></span></th>
                      <th class="sortable-report" data-report="inventory" data-sort="description" style="cursor: pointer;">Description <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-report="inventory" data-sort="unit_cost" style="cursor: pointer;">Unit Cost <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-report="inventory" data-sort="net_cost" style="cursor: pointer;">Net Cost <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-report="inventory" data-sort="pack_size" style="cursor: pointer;">Pack <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-report="inventory" data-sort="on_hand" style="cursor: pointer;">On Hand <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-report="inventory" data-sort="committed" style="cursor: pointer;">Commit <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-report="inventory" data-sort="available" style="cursor: pointer;">Avail <span class="sort-icon"></span></th>
                      <th>UOM</th>
                      <th class="text-end sortable-report" data-report="inventory" data-sort="available_value_list" style="cursor: pointer;">Val (List) <span class="sort-icon"></span></th>
                      <th class="text-end sortable-report" data-report="inventory" data-sort="available_value_net" style="cursor: pointer;">Val (Net) <span class="sort-icon"></span></th>
                    </tr>
                  </thead>
                  <tbody id="inventoryTableBody"></tbody>
                </table>
              </div>

              <div class="card-footer d-flex align-items-center" id="inventoryPagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let currentReport = null;

// Show specific report
function showReport(reportType) {
  // Hide all reports
  document.querySelectorAll('[id$="Report"]').forEach(el => el.style.display = 'none');

  // Show selected report
  const reportMap = {
    'lowStock': 'lowStockReport',
    'committed': 'committedReport',
    'velocity': 'velocityReport',
    'reorder': 'reorderReport',
    'obsolete': 'obsoleteReport',
    'usage': 'usageReport',
    'monthlyStatement': 'monthlyStatementReport',
    'inventory': 'inventoryReport'
  };

  const reportId = reportMap[reportType];
  if (reportId) {
    document.getElementById(reportId).style.display = 'block';
    currentReport = reportType;

    // Load the report data
    switch(reportType) {
      case 'lowStock':
        loadLowStockReport();
        break;
      case 'committed':
        loadCommittedReport();
        break;
      case 'velocity':
        loadVelocityReport();
        break;
      case 'reorder':
        loadReorderReport();
        break;
      case 'obsolete':
        loadObsoleteReport();
        break;
      case 'usage':
        loadUsageReport();
        break;
      case 'monthlyStatement':
        loadMonthlyStatementReport();
        break;
      case 'inventory':
        loadInventoryReport();
        break;
    }
  }
}

// Low Stock Report
async function loadLowStockReport(page = 1) {
  try {
    document.getElementById('lowStockLoading').style.display = 'block';
    document.getElementById('lowStockContent').style.display = 'none';

    const response = await authenticatedFetch('/reports/low-stock');

    // Update summary cards
    document.getElementById('lowStockCount').textContent = response.summary.low_stock_count;
    document.getElementById('criticalCount').textContent = response.summary.critical_count;
    document.getElementById('totalAffected').textContent = response.summary.total_affected;
    document.getElementById('valueAtRisk').textContent = formatCurrency(response.summary.estimated_value_at_risk);

    // Store full data for pagination
    const allItems = [...response.low_stock, ...response.critical];
    reportPaginationState.lowStock = allItems;

    // Render paginated table
    renderLowStockTable(page);

    document.getElementById('lowStockLoading').style.display = 'none';
    document.getElementById('lowStockContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading low stock report:', error);
    showNotification('Error loading low stock report', 'danger');
  }
}

function renderLowStockTable(page = 1) {
  const processedData = getProcessedReportData('lowStock');
  const pagination = paginateData(processedData, page);
  const tbody = document.getElementById('lowStockTableBody');

  updateReportSortIcons('lowStock');

  if (processedData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No low stock items</td></tr>';
    document.getElementById('lowStockPagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => `
      <tr>
        <td><strong>${escapeHtml(item.sku)}</strong></td>
        <td>${escapeHtml(item.description)}</td>
        <td>${item.category || '-'}</td>
        <td class="text-end">${item.on_hand}</td>
        <td class="text-end">${item.available}</td>
        <td class="text-end">${item.minimum}</td>
        <td>${getStatusBadge(item.status)}</td>
        <td class="text-end">${formatCurrency(item.total_value)}</td>
      </tr>
    `).join('');

    renderReportPagination('lowStockPagination', pagination, renderLowStockTable);
  }
}

// Committed Parts Report
async function loadCommittedReport(page = 1) {
  try {
    document.getElementById('committedLoading').style.display = 'block';
    document.getElementById('committedContent').style.display = 'none';

    const response = await authenticatedFetch('/reports/committed-parts');

    // Update summary cards
    document.getElementById('committedProductsCount').textContent = response.summary.total_products;
    document.getElementById('totalQtyCommitted').textContent = response.summary.total_quantity_committed;
    document.getElementById('valueCommitted').textContent = formatCurrency(response.summary.total_value_committed);

    // Store full data for pagination
    reportPaginationState.committed = response.committed_products;

    // Render paginated table
    renderCommittedTable(page);

    document.getElementById('committedLoading').style.display = 'none';
    document.getElementById('committedContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading committed report:', error);
    showNotification('Error loading committed report', 'danger');
  }
}

function renderCommittedTable(page = 1) {
  const processedData = getProcessedReportData('committed');
  const pagination = paginateData(processedData, page);
  const tbody = document.getElementById('committedTableBody');

  updateReportSortIcons('committed');

  if (processedData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No committed parts</td></tr>';
    document.getElementById('committedPagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => {
      const packSize = item.pack_size || 1;
      const hasPackSize = packSize > 1;
      return `
      <tr>
        <td><strong>${escapeHtml(item.sku)}</strong></td>
        <td>${escapeHtml(item.description)}${hasPackSize ? ` <small class="text-muted">(${packSize}/pack)</small>` : ''}</td>
        <td>${item.category || '-'}</td>
        <td class="text-end">${formatPackCommitted(item)}</td>
        <td class="text-end">${formatPackAvailable(item)}</td>
        <td>
          ${item.reservations.map(r => {
            const statusBadge = {
              'active': 'text-bg-info',
              'in_progress': 'text-bg-primary',
              'on_hold': 'text-bg-warning'
            }[r.status] || 'text-bg-secondary';
            const qtyDisplay = hasPackSize ? `${r.quantity_packs || Math.ceil(r.quantity / packSize)} pk` : r.quantity;
            const qtyTitle = hasPackSize ? `${r.quantity} eaches` : '';
            return `<span class="badge ${statusBadge} me-1" title="${r.job_name || ''} ${qtyTitle}">${r.job_number}-${r.release_number || 1}: ${qtyDisplay}</span>`;
          }).join('')}
        </td>
      </tr>
    `;}).join('');

    renderReportPagination('committedPagination', pagination, renderCommittedTable);
  }
}

// Velocity Analysis Report
async function loadVelocityReport(page = 1) {
  try {
    const days = document.getElementById('velocityDays').value;
    document.getElementById('velocityLoading').style.display = 'block';
    document.getElementById('velocityContent').style.display = 'none';

    const response = await authenticatedFetch(`/reports/velocity?days=${days}`);

    // Update summary cards
    document.getElementById('fastMovers').textContent = response.summary.fast_movers;
    document.getElementById('mediumMovers').textContent = response.summary.medium_movers;
    document.getElementById('slowMovers').textContent = response.summary.slow_movers;
    document.getElementById('totalAnalyzed').textContent = response.summary.total_analyzed;

    // Store full data for pagination
    reportPaginationState.velocity = response.products;

    // Render paginated table
    renderVelocityTable(page);

    document.getElementById('velocityLoading').style.display = 'none';
    document.getElementById('velocityContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading velocity report:', error);
    showNotification('Error loading velocity report', 'danger');
  }
}

function renderVelocityTable(page = 1) {
  const processedData = getProcessedReportData('velocity');
  const pagination = paginateData(processedData, page);
  const tbody = document.getElementById('velocityTableBody');

  updateReportSortIcons('velocity');

  if (processedData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No data</td></tr>';
    document.getElementById('velocityPagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => {
      const velocityBadge = {
        'fast': '<span class="badge text-bg-success">Fast</span>',
        'medium': '<span class="badge text-bg-info">Medium</span>',
        'slow': '<span class="badge text-bg-warning">Slow</span>'
      }[item.velocity];

      return `
        <tr>
          <td><strong>${escapeHtml(item.sku)}</strong></td>
          <td>${escapeHtml(item.description)}</td>
          <td>${item.category || '-'}</td>
          <td class="text-end">${item.on_hand}</td>
          <td class="text-end">${item.receipts}</td>
          <td class="text-end">${item.shipments}</td>
          <td class="text-end">${item.turnover_rate}%</td>
          <td>${velocityBadge}</td>
          <td class="text-end">${item.days_until_stockout || '-'}</td>
        </tr>
      `;
    }).join('');

    renderReportPagination('velocityPagination', pagination, renderVelocityTable);
  }
}

// Reorder Recommendations Report
async function loadReorderReport(page = 1) {
  try {
    document.getElementById('reorderLoading').style.display = 'block';
    document.getElementById('reorderContent').style.display = 'none';

    const response = await authenticatedFetch('/reports/reorder-recommendations');

    // Update summary cards
    document.getElementById('itemsToReorder').textContent = response.summary.items_to_reorder;
    document.getElementById('criticalReorderItems').textContent = response.summary.critical_items;
    document.getElementById('totalOrderValue').textContent = formatCurrency(response.summary.total_order_value);

    // Store full data for pagination
    reportPaginationState.reorder = response.recommendations;

    // Render paginated table
    renderReorderTable(page);

    document.getElementById('reorderLoading').style.display = 'none';
    document.getElementById('reorderContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading reorder report:', error);
    showNotification('Error loading reorder report', 'danger');
  }
}

function renderReorderTable(page = 1) {
  const processedData = getProcessedReportData('reorder');
  const pagination = paginateData(processedData, page);
  const tbody = document.getElementById('reorderTableBody');

  updateReportSortIcons('reorder');

  if (processedData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No reorder recommendations</td></tr>';
    document.getElementById('reorderPagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => `
      <tr>
        <td><strong>${escapeHtml(item.sku)}</strong></td>
        <td>${escapeHtml(item.description)}</td>
        <td>${item.supplier || '-'}</td>
        <td class="text-end">${item.available}</td>
        <td class="text-end">${item.reorder_point}</td>
        <td class="text-end text-danger">${item.shortage}</td>
        <td class="text-end"><strong>${item.recommended_order_qty}</strong></td>
        <td class="text-end">${formatCurrency(item.recommended_order_value)}</td>
        <td>${getStatusBadge(item.status)}</td>
      </tr>
    `).join('');

    renderReportPagination('reorderPagination', pagination, renderReorderTable);
  }
}

// Obsolete Inventory Report
async function loadObsoleteReport(page = 1) {
  try {
    const days = document.getElementById('obsoleteDays').value;
    document.getElementById('obsoleteLoading').style.display = 'block';
    document.getElementById('obsoleteContent').style.display = 'none';

    const response = await authenticatedFetch(`/reports/obsolete?inactive_days=${days}`);

    // Update summary cards
    document.getElementById('obsoleteItems').textContent = response.summary.total_items;
    document.getElementById('usedInBom').textContent = response.summary.used_in_bom;
    document.getElementById('obsoleteValue').textContent = formatCurrency(response.summary.total_value_at_risk);

    // Store full data for pagination
    reportPaginationState.obsolete = response.obsolete_candidates;

    // Render paginated table
    renderObsoleteTable(page);

    document.getElementById('obsoleteLoading').style.display = 'none';
    document.getElementById('obsoleteContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading obsolete report:', error);
    showNotification('Error loading obsolete report', 'danger');
  }
}

function renderObsoleteTable(page = 1) {
  const processedData = getProcessedReportData('obsolete');
  const pagination = paginateData(processedData, page);
  const tbody = document.getElementById('obsoleteTableBody');

  updateReportSortIcons('obsolete');

  if (processedData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No obsolete items found</td></tr>';
    document.getElementById('obsoletePagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => `
      <tr>
        <td><strong>${escapeHtml(item.sku)}</strong></td>
        <td>${escapeHtml(item.description)}</td>
        <td>${item.category || '-'}</td>
        <td class="text-end">${item.on_hand}</td>
        <td class="text-end">${formatCurrency(item.unit_cost)}</td>
        <td class="text-end">${formatCurrency(item.total_value)}</td>
        <td>${item.last_shipment_date || 'Never'}</td>
        <td class="text-end">${item.days_since_last_use}</td>
        <td>${item.is_used_in_bom ? '<span class="badge text-bg-info">Yes</span>' : '-'}</td>
      </tr>
    `).join('');

    renderReportPagination('obsoletePagination', pagination, renderObsoleteTable);
  }
}

// Usage Analytics Report
async function loadUsageReport() {
  try {
    const days = document.getElementById('usageDays').value;
    document.getElementById('usageLoading').style.display = 'block';
    document.getElementById('usageContent').style.display = 'none';

    const response = await authenticatedFetch(`/reports/usage-analytics?days=${days}`);

    // Update summary cards
    document.getElementById('totalReceipts').textContent = response.summary.total_receipts;
    document.getElementById('totalShipments').textContent = response.summary.total_shipments;
    document.getElementById('totalAdjustments').textContent = response.summary.total_adjustments;
    document.getElementById('periodDays').textContent = response.summary.period_days + ' days';

    // Render by date table
    const dateBody = document.getElementById('usageByDateBody');
    if (response.by_date.length === 0) {
      dateBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data</td></tr>';
    } else {
      dateBody.innerHTML = response.by_date.map(item => `
        <tr>
          <td>${item.date}</td>
          <td class="text-end text-success">${item.receipts}</td>
          <td class="text-end text-danger">${item.shipments}</td>
          <td class="text-end">${item.total_transactions}</td>
        </tr>
      `).join('');
    }

    // Render by category table
    const categoryBody = document.getElementById('usageByCategoryBody');
    if (response.by_category.length === 0) {
      categoryBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data</td></tr>';
    } else {
      categoryBody.innerHTML = response.by_category.map(item => `
        <tr>
          <td>${item.category}</td>
          <td class="text-end text-success">${item.receipts}</td>
          <td class="text-end text-danger">${item.shipments}</td>
          <td class="text-end">${item.transaction_count}</td>
        </tr>
      `).join('');
    }

    document.getElementById('usageLoading').style.display = 'none';
    document.getElementById('usageContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading usage report:', error);
    showNotification('Error loading usage report', 'danger');
  }
}

// Monthly Inventory Statement Report
async function loadMonthlyStatementReport() {
  try {
    const month = document.getElementById('statementMonth').value;
    const year = document.getElementById('statementYear').value;
    document.getElementById('statementLoading').style.display = 'block';
    document.getElementById('statementContent').style.display = 'none';

    const response = await authenticatedFetch(`/reports/monthly-statement?month=${month}&year=${year}`);

    // Update summary cards
    document.getElementById('statementProducts').textContent = response.summary.products_count;
    document.getElementById('statementBeginValue').textContent = '$' + parseFloat(response.summary.total_beginning_value).toFixed(2);
    document.getElementById('statementEndValue').textContent = '$' + parseFloat(response.summary.total_ending_value).toFixed(2);

    const valueChange = parseFloat(response.summary.total_value_change);
    const valueChangeEl = document.getElementById('statementValueChange');
    valueChangeEl.textContent = (valueChange >= 0 ? '+' : '') + '$' + valueChange.toFixed(2);
    valueChangeEl.className = 'h2 mb-0 ' + (valueChange >= 0 ? 'text-success' : 'text-danger');

    // Render main statement table
    const statementBody = document.getElementById('statementBody');
    if (response.statement.length === 0) {
      statementBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No activity for this period</td></tr>';
    } else {
      statementBody.innerHTML = response.statement.map(item => {
        const netChange = parseFloat(item.net_change_display);
        const changeClass = netChange >= 0 ? 'text-success' : 'text-danger';
        return `
          <tr>
            <td>${item.sku}</td>
            <td>${item.description}</td>
            <td>${item.category || '-'}</td>
            <td class="text-end">${parseFloat(item.beginning_inventory_display).toFixed(0)}</td>
            <td class="text-end text-success">${parseFloat(item.total_additions_display).toFixed(0)}</td>
            <td class="text-end text-danger">${parseFloat(item.total_deductions_display).toFixed(0)}</td>
            <td class="text-end">${parseFloat(item.ending_inventory_display).toFixed(0)}</td>
            <td class="text-end ${changeClass}">${(netChange >= 0 ? '+' : '')}${netChange.toFixed(0)}</td>
            <td class="text-end">$${parseFloat(item.ending_value).toFixed(2)}</td>
          </tr>
        `;
      }).join('');
    }

    // Render additions breakdown
    const additionsBody = document.getElementById('statementAdditionsBody');
    additionsBody.innerHTML = `
      <tr>
        <td>Receipts</td>
        <td class="text-end text-success">${parseFloat(response.summary.total_receipts).toFixed(0)}</td>
      </tr>
      <tr>
        <td>Returns</td>
        <td class="text-end text-success">${parseFloat(response.summary.total_returns).toFixed(0)}</td>
      </tr>
      <tr>
        <td>Job Material Transfers</td>
        <td class="text-end text-success">${parseFloat(response.summary.total_job_material_transfers).toFixed(0)}</td>
      </tr>
      <tr>
        <td>Positive Adjustments</td>
        <td class="text-end text-success">${parseFloat(response.summary.total_adjustments_positive).toFixed(0)}</td>
      </tr>
    `;

    // Render deductions breakdown
    const deductionsBody = document.getElementById('statementDeductionsBody');
    deductionsBody.innerHTML = `
      <tr>
        <td>Shipments</td>
        <td class="text-end text-danger">${parseFloat(response.summary.total_shipments).toFixed(0)}</td>
      </tr>
      <tr>
        <td>Job Issues</td>
        <td class="text-end text-danger">${parseFloat(response.summary.total_job_issues).toFixed(0)}</td>
      </tr>
      <tr>
        <td>Issues</td>
        <td class="text-end text-danger">${parseFloat(response.summary.total_issues).toFixed(0)}</td>
      </tr>
      <tr>
        <td>Negative Adjustments</td>
        <td class="text-end text-danger">${parseFloat(response.summary.total_adjustments_negative).toFixed(0)}</td>
      </tr>
    `;

    document.getElementById('statementLoading').style.display = 'none';
    document.getElementById('statementContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading monthly statement:', error);
    showNotification('Error loading monthly statement', 'danger');
  }
}

// Export Monthly Statement PDF
async function exportMonthlyStatementPdf() {
  try {
    const month = document.getElementById('statementMonth').value;
    const year = document.getElementById('statementYear').value;

    showNotification('Generating PDF report...', 'info');

    const response = await fetch(`${API_BASE}/reports/monthly-statement/pdf?month=${month}&year=${year}`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Accept': 'application/pdf'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to generate PDF');
    }

    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = downloadUrl;
    a.download = `monthly-statement-${month}-${year}-${new Date().toISOString().split('T')[0]}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(downloadUrl);

    showNotification('PDF report generated successfully', 'success');
  } catch (error) {
    console.error('Error exporting PDF:', error);
    showNotification('Error exporting PDF', 'danger');
  }
}

// Inventory Report
async function loadInventoryReport(page = 1) {
  try {
    document.getElementById('inventoryLoading').style.display = 'block';
    document.getElementById('inventoryContent').style.display = 'none';

    const response = await authenticatedFetch('/reports/inventory/data');

    // Update summary cards
    document.getElementById('inventoryProductsCount').textContent = response.summary.total_products;
    document.getElementById('inventoryAvailableQty').textContent = response.summary.total_available_qty.toLocaleString();
    document.getElementById('inventoryValueList').textContent = formatCurrency(response.summary.total_value_list);
    document.getElementById('inventoryValueNet').textContent = formatCurrency(response.summary.total_value_net);

    // Store full data for pagination
    reportPaginationState.inventory = response.products;

    // Render paginated table
    renderInventoryTable(page);

    document.getElementById('inventoryLoading').style.display = 'none';
    document.getElementById('inventoryContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading inventory report:', error);
    showNotification('Error loading inventory report', 'danger');
  }
}

function renderInventoryTable(page = 1) {
  const processedData = getProcessedReportData('inventory');
  const pagination = paginateData(processedData, page);
  const tbody = document.getElementById('inventoryTableBody');

  updateReportSortIcons('inventory');

  if (processedData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted">No inventory items</td></tr>';
    document.getElementById('inventoryPagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => `
      <tr>
        <td><strong>${escapeHtml(item.part_number || '')}</strong></td>
        <td>${escapeHtml(item.finish || '')}</td>
        <td>${escapeHtml(item.description)}</td>
        <td class="text-end">${formatCurrency(item.unit_cost)}</td>
        <td class="text-end">${formatCurrency(item.net_cost)}</td>
        <td class="text-end">${item.pack_size}</td>
        <td class="text-end">${item.on_hand.toLocaleString()}</td>
        <td class="text-end">${item.committed.toLocaleString()}</td>
        <td class="text-end">${item.available.toLocaleString()}</td>
        <td>${escapeHtml(item.unit_of_measure || '')}</td>
        <td class="text-end">${formatCurrency(item.available_value_list)}</td>
        <td class="text-end">${formatCurrency(item.available_value_net)}</td>
      </tr>
    `).join('');

    renderReportPagination('inventoryPagination', pagination, renderInventoryTable);
  }
}

// Export Inventory CSV
async function exportInventoryCsv() {
  try {
    showNotification('Generating CSV export...', 'info');

    const response = await fetch(`${API_BASE}/reports/inventory/csv`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Accept': 'text/csv'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to generate CSV');
    }

    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = downloadUrl;
    a.download = `inventory-report-${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(downloadUrl);

    showNotification('CSV export completed', 'success');
  } catch (error) {
    console.error('Error exporting inventory CSV:', error);
    showNotification('Error exporting CSV', 'danger');
  }
}

// Export Inventory PDF
async function exportInventoryPdf() {
  try {
    showNotification('Generating PDF report...', 'info');

    const response = await fetch(`${API_BASE}/reports/inventory/pdf`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Accept': 'application/pdf'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to generate PDF');
    }

    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = downloadUrl;
    a.download = `inventory-report-${new Date().toISOString().split('T')[0]}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(downloadUrl);

    showNotification('PDF report generated successfully', 'success');
  } catch (error) {
    console.error('Error exporting inventory PDF:', error);
    showNotification('Error exporting PDF', 'danger');
  }
}

// Export report
async function exportReport(type) {
  try {
    let url = `${API_BASE}/reports/export?type=${type}`;

    // Add month/year parameters for monthly statement report
    if (type === 'monthly_statement') {
      const month = document.getElementById('statementMonth').value;
      const year = document.getElementById('statementYear').value;
      url += `&month=${month}&year=${year}`;
    }

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Accept': 'text/csv'
      }
    });

    if (!response.ok) {
      throw new Error('Export failed');
    }

    // Get the blob from response
    const blob = await response.blob();

    // Create download link
    const url2 = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url2;
    a.download = `${type}_report_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url2);

    showNotification('Report exported successfully', 'success');
  } catch (error) {
    console.error('Error exporting report:', error);
    showNotification('Error exporting report', 'danger');
  }
}

// Export report as PDF
async function exportReportPdf(type) {
  try {
    let url = `${API_BASE}/reports/${type}/pdf`;

    // Add parameters for reports that have time range selectors
    const params = new URLSearchParams();
    if (type === 'velocity') {
      const days = document.getElementById('velocityDays')?.value || 90;
      params.append('days', days);
    } else if (type === 'obsolete') {
      const days = document.getElementById('obsoleteDays')?.value || 180;
      params.append('inactive_days', days);
    } else if (type === 'usage-analytics') {
      const days = document.getElementById('usageDays')?.value || 30;
      params.append('days', days);
    }

    if (params.toString()) {
      url += '?' + params.toString();
    }

    showNotification('Generating PDF report...', 'info');

    // Fetch PDF with authorization header
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Accept': 'application/pdf'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to generate PDF');
    }

    // Get the blob from response
    const blob = await response.blob();

    // Create download link
    const downloadUrl = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = downloadUrl;

    // Generate filename based on report type
    const reportNames = {
      'low-stock': 'low-stock-report',
      'committed-parts': 'committed-parts-report',
      'velocity': 'velocity-analysis-report',
      'reorder-recommendations': 'reorder-recommendations-report',
      'obsolete': 'obsolete-inventory-report',
      'usage-analytics': 'usage-analytics-report'
    };

    a.download = `${reportNames[type] || type}-${new Date().toISOString().split('T')[0]}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(downloadUrl);

    showNotification('PDF report downloaded successfully', 'success');
  } catch (error) {
    console.error('Error exporting PDF report:', error);
    showNotification('Error generating PDF report', 'danger');
  }
}

// Refresh all visible reports
function refreshAllReports() {
  if (currentReport) {
    showReport(currentReport);
  }
}

// ============================================
// Client-Side Pagination, Sorting & Search
// ============================================
const ITEMS_PER_PAGE = 50;
const reportPaginationState = {};
const reportSortState = {
  lowStock: { sortBy: 'sku', sortDir: 'asc' },
  committed: { sortBy: 'sku', sortDir: 'asc' },
  velocity: { sortBy: 'sku', sortDir: 'asc' },
  reorder: { sortBy: 'sku', sortDir: 'asc' },
  obsolete: { sortBy: 'sku', sortDir: 'asc' },
  inventory: { sortBy: 'part_number', sortDir: 'asc' }
};
const reportSearchState = {
  lowStock: '',
  committed: '',
  velocity: '',
  reorder: '',
  obsolete: '',
  inventory: ''
};

// Debounce timer for search
let reportSearchDebounceTimer = null;

// Sort data by column
function sortReportData(data, sortBy, sortDir) {
  return [...data].sort((a, b) => {
    let aVal = a[sortBy];
    let bVal = b[sortBy];

    // Handle null/undefined values
    if (aVal === null || aVal === undefined) aVal = '';
    if (bVal === null || bVal === undefined) bVal = '';

    // Handle numeric sorting
    if (typeof aVal === 'number' && typeof bVal === 'number') {
      return sortDir === 'asc' ? aVal - bVal : bVal - aVal;
    }

    // Handle string sorting
    const aStr = String(aVal).toLowerCase();
    const bStr = String(bVal).toLowerCase();
    if (sortDir === 'asc') {
      return aStr.localeCompare(bStr);
    } else {
      return bStr.localeCompare(aStr);
    }
  });
}

// Filter data by search term
function filterReportData(data, searchTerm) {
  if (!searchTerm) return data;
  const term = searchTerm.toLowerCase();
  return data.filter(item => {
    const sku = (item.sku || '').toLowerCase();
    const description = (item.description || '').toLowerCase();
    return sku.includes(term) || description.includes(term);
  });
}

// Handle report column sort click
function handleReportSort(reportName, column) {
  const state = reportSortState[reportName];
  if (state.sortBy === column) {
    state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
  } else {
    state.sortBy = column;
    state.sortDir = 'asc';
  }
  updateReportSortIcons(reportName);
  renderReportByName(reportName, 1);
}

// Update sort icons for a report
function updateReportSortIcons(reportName) {
  const state = reportSortState[reportName];
  document.querySelectorAll(`.sortable-report[data-report="${reportName}"]`).forEach(th => {
    const icon = th.querySelector('.sort-icon');
    const column = th.dataset.sort;
    if (column === state.sortBy) {
      icon.innerHTML = state.sortDir === 'asc'
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1"><path d="M12 5l0 14"/><path d="M18 11l-6 -6"/><path d="M6 11l6 -6"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1"><path d="M12 5l0 14"/><path d="M18 13l-6 6"/><path d="M6 13l6 6"/></svg>';
    } else {
      icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1 text-muted"><path d="M8 9l4 -4l4 4"/><path d="M16 15l-4 4l-4 -4"/></svg>';
    }
  });
}

// Handle report search
function handleReportSearch(reportName, searchTerm) {
  clearTimeout(reportSearchDebounceTimer);
  reportSearchDebounceTimer = setTimeout(() => {
    reportSearchState[reportName] = searchTerm;
    renderReportByName(reportName, 1);
  }, 300);
}

// Render report by name
function renderReportByName(reportName, page) {
  switch(reportName) {
    case 'lowStock': renderLowStockTable(page); break;
    case 'committed': renderCommittedTable(page); break;
    case 'velocity': renderVelocityTable(page); break;
    case 'reorder': renderReorderTable(page); break;
    case 'obsolete': renderObsoleteTable(page); break;
    case 'inventory': renderInventoryTable(page); break;
  }
}

// Get filtered and sorted data for a report
function getProcessedReportData(reportName) {
  let data = reportPaginationState[reportName] || [];
  const searchTerm = reportSearchState[reportName];
  const { sortBy, sortDir } = reportSortState[reportName];

  // Filter by search
  data = filterReportData(data, searchTerm);

  // Sort
  data = sortReportData(data, sortBy, sortDir);

  return data;
}

function paginateData(data, page, perPage = ITEMS_PER_PAGE) {
  const startIndex = (page - 1) * perPage;
  const endIndex = startIndex + perPage;
  return {
    data: data.slice(startIndex, endIndex),
    currentPage: page,
    lastPage: Math.ceil(data.length / perPage),
    total: data.length,
    from: data.length > 0 ? startIndex + 1 : 0,
    to: Math.min(endIndex, data.length)
  };
}

function renderReportPagination(containerId, pagination, onPageChange) {
  const container = document.getElementById(containerId);
  if (!container || !pagination || pagination.total === 0) {
    if (container) container.style.display = 'none';
    return;
  }

  const { currentPage, lastPage, total, from, to } = pagination;

  if (lastPage <= 1) {
    container.style.display = 'none';
    return;
  }

  // Build page numbers - show max 7 pages
  const pageNumbers = getReportPageNumbers(currentPage, lastPage, 7);

  let html = `
    <p class="m-0 text-muted">Showing ${from} to ${to} of ${total.toLocaleString()} items</p>
    <ul class="pagination m-0 ms-auto">
      <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage - 1}" tabindex="-1" ${currentPage === 1 ? 'aria-disabled="true"' : ''}>
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
            <path d="M15 6l-6 6l6 6"></path>
          </svg>
        </a>
      </li>
  `;

  pageNumbers.forEach(pageNum => {
    if (pageNum === '...') {
      html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    } else {
      html += `
        <li class="page-item ${pageNum === currentPage ? 'active' : ''}">
          <a class="page-link" href="#" data-page="${pageNum}">${pageNum}</a>
        </li>
      `;
    }
  });

  html += `
      <li class="page-item ${currentPage === lastPage ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage + 1}" ${currentPage === lastPage ? 'aria-disabled="true"' : ''}>
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
            <path d="M9 6l6 6l-6 6"></path>
          </svg>
        </a>
      </li>
    </ul>
  `;

  container.innerHTML = html;
  container.style.display = 'flex';

  // Add click handlers
  container.querySelectorAll('.page-link[data-page]').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const page = parseInt(link.dataset.page);
      if (page >= 1 && page <= lastPage && page !== currentPage) {
        onPageChange(page);
      }
    });
  });
}

function getReportPageNumbers(current, last, maxVisible) {
  if (last <= maxVisible) {
    return Array.from({length: last}, (_, i) => i + 1);
  }

  const pages = [];
  const half = Math.floor(maxVisible / 2);

  if (current <= half + 1) {
    for (let i = 1; i <= maxVisible - 2; i++) pages.push(i);
    pages.push('...');
    pages.push(last);
  } else if (current >= last - half) {
    pages.push(1);
    pages.push('...');
    for (let i = last - maxVisible + 3; i <= last; i++) pages.push(i);
  } else {
    pages.push(1);
    pages.push('...');
    for (let i = current - 1; i <= current + 1; i++) pages.push(i);
    pages.push('...');
    pages.push(last);
  }

  return pages;
}

// ============================================
// Helper functions
// ============================================
function getStatusBadge(status) {
  const badges = {
    'in_stock': '<span class="badge text-bg-success">In Stock</span>',
    'low_stock': '<span class="badge text-bg-warning">Low Stock</span>',
    'critical': '<span class="badge text-bg-danger">Critical</span>',
    'out_of_stock': '<span class="badge text-bg-dark">Out of Stock</span>'
  };
  return badges[status] || status;
}

function formatCurrency(value) {
  // Use the global formatPrice function which handles permission-based masking
  if (typeof formatPrice === 'function') {
    return formatPrice(value);
  }
  // Fallback if formatPrice not available
  return '$' + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/**
 * Format committed quantity showing packs if applicable
 * Shows "X packs" with eaches in tooltip when pack_size > 1
 */
function formatPackCommitted(item) {
  const committed = item.committed || 0;
  const committedPacks = item.committed_packs || committed;
  const packSize = item.pack_size || 1;
  const hasPackSize = packSize > 1;

  if (committed === 0) {
    return hasPackSize ? '0 packs' : '0';
  }

  if (hasPackSize) {
    const packLabel = committedPacks === 1 ? 'pack' : 'packs';
    return `<span title="${committed} eaches (${packSize}/pack)">${committedPacks} ${packLabel}</span>`;
  }

  return committed.toLocaleString();
}

/**
 * Format on-hand quantity showing packs if applicable
 */
function formatPackOnHand(item) {
  const onHand = item.on_hand || 0;
  const onHandPacks = item.on_hand_packs || onHand;
  const packSize = item.pack_size || 1;
  const hasPackSize = packSize > 1;

  if (hasPackSize) {
    const packLabel = onHandPacks === 1 ? 'pack' : 'packs';
    return `<span title="${onHand} eaches (${packSize}/pack)">${onHandPacks} ${packLabel}</span>`;
  }

  return onHand.toLocaleString();
}

/**
 * Format available quantity showing packs if applicable
 */
function formatPackAvailable(item) {
  const available = item.available || 0;
  const availablePacks = item.available_packs || available;
  const packSize = item.pack_size || 1;
  const hasPackSize = packSize > 1;

  if (hasPackSize) {
    const packLabel = availablePacks === 1 ? 'pack' : 'packs';
    return `<span title="${available} eaches">${availablePacks} ${packLabel}</span>`;
  }

  return available.toLocaleString();
}

// Load first report on page load
document.addEventListener('DOMContentLoaded', () => {
  showReport('lowStock');

  // Add event listeners for sortable headers
  document.querySelectorAll('.sortable-report').forEach(th => {
    th.addEventListener('click', function() {
      const column = this.dataset.sort;
      const report = this.dataset.report;
      if (column && report) {
        handleReportSort(report, column);
      }
    });
  });

  // Add event listeners for search inputs
  const searchInputs = {
    'lowStockSearch': 'lowStock',
    'committedSearch': 'committed',
    'velocitySearch': 'velocity',
    'reorderSearch': 'reorder',
    'obsoleteSearch': 'obsolete'
  };

  Object.entries(searchInputs).forEach(([inputId, reportName]) => {
    const input = document.getElementById(inputId);
    if (input) {
      input.addEventListener('input', (e) => {
        handleReportSearch(reportName, e.target.value.trim());
      });
    }
  });
});
</script>
@endsection
