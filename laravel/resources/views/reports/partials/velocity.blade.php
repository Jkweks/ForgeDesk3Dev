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

<script>
window.ReportModules = window.ReportModules || {};

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

window.ReportModules.velocity = { load: () => loadVelocityReport() };
</script>
