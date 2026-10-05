<!-- Work Order Backlog / Queue Report -->
<div class="col-12" id="workOrderBacklogReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-list-details me-2"></i>Work Order Backlog / Queue</h3>
      <div class="ms-auto d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" onclick="exportReportPdf('work-order-backlog')">
          <i class="ti ti-file-type-pdf me-1"></i>PDF
        </button>
        <button class="btn btn-sm btn-primary" onclick="exportReport('work_order_backlog')">
          <i class="ti ti-download me-1"></i>CSV
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-6 col-md">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Total Open</div>
              <div class="h2 mb-0" id="woBacklogTotal">-</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Active</div>
              <div class="h2 mb-0 text-success" id="woBacklogActive">-</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">On Hold</div>
              <div class="h2 mb-0 text-warning" id="woBacklogOnHold">-</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Overdue</div>
              <div class="h2 mb-0 text-danger" id="woBacklogOverdue">-</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Due This Week</div>
              <div class="h2 mb-0 text-info" id="woBacklogDueSoon">-</div>
            </div>
          </div>
        </div>
      </div>

      <div id="woBacklogLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Loading report...</div>
      </div>

      <div id="woBacklogContent" style="display: none;">
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Pri</th>
                <th>Release</th>
                <th>Job</th>
                <th>Status</th>
                <th>Due Date</th>
                <th class="text-center">Days</th>
                <th>Assigned To</th>
                <th class="text-center">Elevations</th>
                <th class="text-center">Open Steps</th>
              </tr>
            </thead>
            <tbody id="woBacklogBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.ReportModules = window.ReportModules || {};

// Work Order Backlog / Queue Report
async function loadWorkOrderBacklogReport() {
  try {
    document.getElementById('woBacklogLoading').style.display = 'block';
    document.getElementById('woBacklogContent').style.display = 'none';

    const response = await authenticatedFetch('/reports/work-order-backlog');

    document.getElementById('woBacklogTotal').textContent = response.summary.total_open;
    document.getElementById('woBacklogActive').textContent = response.summary.active_count;
    document.getElementById('woBacklogOnHold').textContent = response.summary.on_hold_count;
    const overdueEl = document.getElementById('woBacklogOverdue');
    overdueEl.textContent = response.summary.overdue_count;
    document.getElementById('woBacklogDueSoon').textContent = response.summary.due_this_week;

    const tbody = document.getElementById('woBacklogBody');
    if (response.work_orders.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No open work orders found.</td></tr>';
    } else {
      tbody.innerHTML = response.work_orders.map(wo => `
        <tr>
          <td>${wo.priority ?? '—'}</td>
          <td>${escapeHtml(wo.release_label)}</td>
          <td>${escapeHtml(wo.job_number || '—')} <span class="text-muted small">${escapeHtml(wo.job_name || '')}</span></td>
          <td><span class="badge ${wo.status === 'on_hold' ? 'bg-warning-lt text-warning' : 'bg-success-lt text-success'}">${escapeHtml(wo.status.replace('_', ' '))}</span></td>
          <td>${wo.due_date ?? '—'}</td>
          <td class="text-center ${wo.is_overdue ? 'text-danger fw-bold' : ''}">${wo.days_until_due ?? '—'}</td>
          <td>${escapeHtml(wo.assigned_users.join(', ') || '—')}</td>
          <td class="text-center">${wo.elevations_complete_count}/${wo.elevation_count}</td>
          <td class="text-center">${wo.open_steps_count}</td>
        </tr>`).join('');
    }

    document.getElementById('woBacklogLoading').style.display = 'none';
    document.getElementById('woBacklogContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading work order backlog report:', error);
    showNotification('Error loading work order backlog report', 'danger');
  }
}

window.ReportModules.workOrderBacklog = { load: () => loadWorkOrderBacklogReport() };
</script>
