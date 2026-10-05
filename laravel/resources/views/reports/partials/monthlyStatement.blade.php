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

<script>
window.ReportModules = window.ReportModules || {};

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

window.ReportModules.monthlyStatement = { load: () => loadMonthlyStatementReport() };
</script>
