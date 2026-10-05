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

<script>
window.ReportModules = window.ReportModules || {};

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

window.ReportModules.reorder = { load: () => loadReorderReport() };
</script>
