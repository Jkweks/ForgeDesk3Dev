<!-- Joints Completed Report -->
<div class="col-12" id="jointsCompletedReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-git-merge me-2"></i>Joints Completed</h3>
      <div class="ms-auto d-flex gap-2">
        <input type="date" class="form-control form-control-sm" id="jointsStartDate" style="max-width: 150px;">
        <input type="date" class="form-control form-control-sm" id="jointsEndDate" style="max-width: 150px;">
        <button class="btn btn-sm btn-outline-secondary" onclick="loadJointsCompletedReport()">
          <i class="ti ti-refresh me-1"></i>Apply
        </button>
        <button class="btn btn-sm btn-outline-primary" onclick="exportReportPdf('joints-completed')">
          <i class="ti ti-file-type-pdf me-1"></i>PDF
        </button>
        <button class="btn btn-sm btn-primary" onclick="exportReport('joints_completed')">
          <i class="ti ti-download me-1"></i>CSV
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-6 col-md-4">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Total Joints Completed</div>
              <div class="h2 mb-0" id="jointsTotal">-</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Elevations Completed</div>
              <div class="h2 mb-0 text-info" id="jointsElevations">-</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Top System</div>
              <div class="h3 mb-0 text-success" id="jointsTopSystem">-</div>
            </div>
          </div>
        </div>
      </div>

      <div id="jointsCompletedLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Loading report...</div>
      </div>

      <div id="jointsCompletedContent" style="display: none;">
        <h4>Breakdown by System</h4>
        <div class="table-responsive mb-4">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>System</th>
                <th class="text-end">Joints Completed</th>
                <th class="text-end">Elevations</th>
                <th class="text-end">Jobs</th>
                <th class="text-end">% of Total</th>
              </tr>
            </thead>
            <tbody id="jointsBySystemBody"></tbody>
          </table>
        </div>

        <h4>Breakdown by Complexity Tier</h4>
        <div class="table-responsive mb-4">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Tier</th>
                <th class="text-end">Joints Completed</th>
                <th class="text-end">Elevations</th>
              </tr>
            </thead>
            <tbody id="jointsByTierBody"></tbody>
          </table>
        </div>

        <h4>Daily Totals</h4>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Date</th>
                <th class="text-end">Joints Completed</th>
                <th class="text-end">Elevations</th>
              </tr>
            </thead>
            <tbody id="jointsByDateBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.ReportModules = window.ReportModules || {};

// Joints Completed Report
async function loadJointsCompletedReport() {
  try {
    document.getElementById('jointsCompletedLoading').style.display = 'block';
    document.getElementById('jointsCompletedContent').style.display = 'none';

    const startDate = document.getElementById('jointsStartDate').value;
    const endDate = document.getElementById('jointsEndDate').value;
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    const query = params.toString() ? `?${params.toString()}` : '';

    const response = await authenticatedFetch(`/reports/joints-completed${query}`);

    if (!startDate) document.getElementById('jointsStartDate').value = response.summary.start_date;
    if (!endDate) document.getElementById('jointsEndDate').value = response.summary.end_date;

    document.getElementById('jointsTotal').textContent = response.summary.total_joints.toLocaleString();
    document.getElementById('jointsElevations').textContent = response.summary.total_elevations.toLocaleString();
    document.getElementById('jointsTopSystem').textContent = response.summary.top_system || 'N/A';

    const bySystemBody = document.getElementById('jointsBySystemBody');
    bySystemBody.innerHTML = response.by_system.length === 0
      ? '<tr><td colspan="5" class="text-center text-muted py-3">No completed joints in this date range.</td></tr>'
      : response.by_system.map(row => `
          <tr>
            <td>${escapeHtml(row.system)}</td>
            <td class="text-end">${row.joints.toLocaleString()}</td>
            <td class="text-end">${row.elevation_count.toLocaleString()}</td>
            <td class="text-end">${row.job_count.toLocaleString()}</td>
            <td class="text-end">${row.percent_of_total}%</td>
          </tr>`).join('');

    const byTierBody = document.getElementById('jointsByTierBody');
    byTierBody.innerHTML = response.by_tier.length === 0
      ? '<tr><td colspan="3" class="text-center text-muted py-3">No completed joints in this date range.</td></tr>'
      : response.by_tier.map(row => `
          <tr>
            <td>${escapeHtml(row.tier)}</td>
            <td class="text-end">${row.joints.toLocaleString()}</td>
            <td class="text-end">${row.elevation_count.toLocaleString()}</td>
          </tr>`).join('');

    const byDateBody = document.getElementById('jointsByDateBody');
    byDateBody.innerHTML = response.by_date.length === 0
      ? '<tr><td colspan="3" class="text-center text-muted py-3">No completed joints in this date range.</td></tr>'
      : response.by_date.map(row => `
          <tr>
            <td>${row.date}</td>
            <td class="text-end">${row.joints.toLocaleString()}</td>
            <td class="text-end">${row.elevation_count.toLocaleString()}</td>
          </tr>`).join('');

    document.getElementById('jointsCompletedLoading').style.display = 'none';
    document.getElementById('jointsCompletedContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading joints completed report:', error);
    showNotification('Error loading joints completed report', 'danger');
  }
}

window.ReportModules.jointsCompleted = { load: loadJointsCompletedReport };
</script>
