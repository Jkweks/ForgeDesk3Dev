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
                <th class="text-end sortable-report" data-report="inventory" data-sort="unit_cost" style="cursor: pointer;">List Price <span class="sort-icon"></span></th>
                <th class="text-end sortable-report" data-report="inventory" data-sort="net_cost" style="cursor: pointer;">Net Price <span class="sort-icon"></span></th>
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

<script>
window.ReportModules = window.ReportModules || {};

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

window.ReportModules.inventory = { load: () => loadInventoryReport() };
</script>
