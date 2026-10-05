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

<script>
window.ReportModules = window.ReportModules || {};

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

window.ReportModules.usage = { load: () => loadUsageReport() };
</script>
