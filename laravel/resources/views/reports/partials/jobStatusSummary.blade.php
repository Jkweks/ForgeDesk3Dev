<!-- Job Status Summary Report -->
<div class="col-12" id="jobStatusSummaryReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-clipboard-list me-2"></i>Job Status Summary</h3>
      <div class="ms-auto d-flex gap-2">
        <select class="form-select form-select-sm" id="jobStatusFilter" style="max-width: 160px;" onchange="loadJobStatusSummaryReport()">
          <option value="">Active + On Hold</option>
          <option value="active">Active Only</option>
          <option value="on_hold">On Hold Only</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <button class="btn btn-sm btn-outline-primary" onclick="exportReportPdf('job-status-summary')">
          <i class="ti ti-file-type-pdf me-1"></i>PDF
        </button>
        <button class="btn btn-sm btn-primary" onclick="exportReport('job_status_summary')">
          <i class="ti ti-download me-1"></i>CSV
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-6 col-md">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Total Jobs</div>
              <div class="h2 mb-0" id="jobStatusTotal">-</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Active</div>
              <div class="h2 mb-0 text-success" id="jobStatusActive">-</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">On Hold</div>
              <div class="h2 mb-0 text-warning" id="jobStatusOnHold">-</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">At Risk</div>
              <div class="h2 mb-0 text-danger" id="jobStatusAtRisk">-</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Avg Material Fulfillment</div>
              <div class="h2 mb-0 text-info" id="jobStatusAvgFulfillment">-</div>
            </div>
          </div>
        </div>
      </div>

      <div id="jobStatusLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Loading report...</div>
      </div>

      <div id="jobStatusContent" style="display: none;">
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Job #</th>
                <th>Job Name</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Superintendent</th>
                <th>Target Completion</th>
                <th class="text-center">Days</th>
                <th class="text-center">Material %</th>
                <th class="text-center">Open Reservations</th>
                <th class="text-center">WOs (Active/Hold/Done)</th>
              </tr>
            </thead>
            <tbody id="jobStatusBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.ReportModules = window.ReportModules || {};

// Job Status Summary Report
async function loadJobStatusSummaryReport() {
  try {
    document.getElementById('jobStatusLoading').style.display = 'block';
    document.getElementById('jobStatusContent').style.display = 'none';

    const status = document.getElementById('jobStatusFilter').value;
    const query = status ? `?status=${encodeURIComponent(status)}` : '';
    const response = await authenticatedFetch(`/reports/job-status-summary${query}`);

    document.getElementById('jobStatusTotal').textContent = response.summary.total_jobs;
    document.getElementById('jobStatusActive').textContent = response.summary.active_jobs;
    document.getElementById('jobStatusOnHold').textContent = response.summary.on_hold_jobs;
    document.getElementById('jobStatusAtRisk').textContent = response.summary.at_risk_jobs;
    document.getElementById('jobStatusAvgFulfillment').textContent =
      response.summary.avg_material_fulfillment_pct !== null ? `${response.summary.avg_material_fulfillment_pct}%` : 'N/A';

    const tbody = document.getElementById('jobStatusBody');
    if (response.jobs.length === 0) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No jobs found.</td></tr>';
    } else {
      tbody.innerHTML = response.jobs.map(job => `
        <tr>
          <td>${escapeHtml(job.job_number)}</td>
          <td>${escapeHtml(job.job_name)}</td>
          <td>${escapeHtml(job.customer_name || '—')}</td>
          <td><span class="badge bg-secondary-lt text-secondary">${escapeHtml(job.status.replace('_', ' '))}</span></td>
          <td>${escapeHtml(job.superintendent || '—')}</td>
          <td>${job.target_completion_date ?? '—'}</td>
          <td class="text-center ${job.is_at_risk ? 'text-danger fw-bold' : ''}">${job.days_until_completion ?? '—'}</td>
          <td class="text-center">${job.material_fulfillment_pct !== null ? job.material_fulfillment_pct + '%' : 'N/A'}</td>
          <td class="text-center">${job.open_reservation_count}</td>
          <td class="text-center">${job.work_orders_active} / ${job.work_orders_on_hold} / ${job.work_orders_complete}</td>
        </tr>`).join('');
    }

    document.getElementById('jobStatusLoading').style.display = 'none';
    document.getElementById('jobStatusContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading job status summary report:', error);
    showNotification('Error loading job status summary report', 'danger');
  }
}

window.ReportModules.jobStatusSummary = { load: () => loadJobStatusSummaryReport() };
</script>
