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
                <th class="text-end sortable-report" data-sort="unit_cost" data-report="obsolete" style="cursor: pointer;">List Price <span class="sort-icon"></span></th>
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

<script>
window.ReportModules = window.ReportModules || {};

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

window.ReportModules.obsolete = { load: () => loadObsoleteReport() };
</script>
