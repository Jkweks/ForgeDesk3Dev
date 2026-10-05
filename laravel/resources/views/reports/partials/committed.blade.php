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

<script>
window.ReportModules = window.ReportModules || {};

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

window.ReportModules.committed = { load: () => loadCommittedReport() };
</script>
