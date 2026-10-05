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

<script>
window.ReportModules = window.ReportModules || {};

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

window.ReportModules.lowStock = { load: () => loadLowStockReport() };
</script>
