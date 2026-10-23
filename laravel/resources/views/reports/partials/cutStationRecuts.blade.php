@once
  @include('reports.partials._cutStationUi')
@endonce

<!-- Recut Analysis Report -->
<div class="col-12" id="cutStationRecutsReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-refresh-alert me-2"></i>Recut Analysis</h3>
      <div class="ms-auto d-flex gap-2">
        <input type="date" class="form-control form-control-sm" id="csRecutStart" style="max-width: 150px;">
        <input type="date" class="form-control form-control-sm" id="csRecutEnd" style="max-width: 150px;">
        <button class="btn btn-sm btn-outline-secondary" onclick="loadCutStationRecutsReport()">
          <i class="ti ti-refresh me-1"></i>Apply
        </button>
        <button class="btn btn-sm btn-primary" onclick="CutStationUi.csv('cut_station_recuts', 'csRecut')" data-permission="reports.export">
          <i class="ti ti-download me-1"></i>CSV
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="text-muted small mb-3">
        Recut rate = pieces flagged for recut &divide; pieces cut. A recut is credited to whoever logged it, which is not necessarily who made the bad cut.
      </div>
      <div class="row mb-3">
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Pieces Cut</div><div class="h2 mb-0" id="csRecutTotal">-</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Recuts</div><div class="h2 mb-0 text-danger" id="csRecutCount">-</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Recut Rate</div><div class="h2 mb-0 text-danger" id="csRecutRate">-</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Jobs with Recuts</div><div class="h2 mb-0 text-warning" id="csRecutJobs">-</div>
        </div></div></div>
      </div>

      <div id="csRecutLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Loading report...</div>
      </div>

      <div id="csRecutContent" style="display: none;">
        <h4>By Job</h4>
        <div class="table-responsive mb-4">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr><th>Job</th><th>Work Order</th><th class="text-end">Cuts</th><th class="text-end">Recuts</th><th class="text-end">Recut Rate</th></tr>
            </thead>
            <tbody id="csRecutJobBody"></tbody>
          </table>
        </div>

        <div class="row">
          <div class="col-lg-5">
            <h4>By Operator</h4>
            <div class="table-responsive mb-4">
              <table class="table table-sm table-vcenter card-table">
                <thead><tr><th>Operator</th><th class="text-end">Cuts</th><th class="text-end">Recuts</th><th class="text-end">Recut Rate</th></tr></thead>
                <tbody id="csRecutOperatorBody"></tbody>
              </table>
            </div>
          </div>
          <div class="col-lg-7">
            <h4>By Profile</h4>
            <div class="table-responsive">
              <table class="table table-sm table-vcenter card-table">
                <thead><tr><th>Profile</th><th>Finish</th><th class="text-end">Cuts</th><th class="text-end">Recuts</th><th class="text-end">Recut Rate</th></tr></thead>
                <tbody id="csRecutProfileBody"></tbody>
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

async function loadCutStationRecutsReport() {
  const ui = window.CutStationUi;
  try {
    ui.show('csRecut', false);
    const r = await authenticatedFetch(`/reports/cut-station/recuts${ui.query('csRecut')}`);
    ui.syncDates('csRecut', r.summary);

    document.getElementById('csRecutTotal').textContent = ui.num(r.summary.total_cuts);
    document.getElementById('csRecutCount').textContent = ui.num(r.summary.recuts);
    document.getElementById('csRecutRate').textContent = ui.pct(r.summary.recut_rate);
    document.getElementById('csRecutJobs').textContent = `${ui.num(r.summary.jobs_with_recuts)} of ${ui.num(r.summary.jobs)}`;

    const cells = (x) => `
      <td class="text-end">${ui.num(x.cuts)}</td>
      <td class="text-end">${ui.num(x.recuts)}</td>
      <td class="text-end">${ui.rateBadge(x.recut_rate)}</td>`;

    ui.fill('csRecutJobBody', r.by_job, 5, j => `
      <tr><td>${escapeHtml(j.job)}</td><td>${escapeHtml(j.work_order || '')}</td>${cells(j)}</tr>`);
    ui.fill('csRecutOperatorBody', r.by_operator, 4, o => `<tr><td>${escapeHtml(o.operator)}</td>${cells(o)}</tr>`);
    ui.fill('csRecutProfileBody', r.by_profile, 5, p => `
      <tr><td>${escapeHtml(p.part_name)}</td><td>${escapeHtml(p.finish || '')}</td>${cells(p)}</tr>`);

    ui.show('csRecut', true);
  } catch (error) {
    console.error('Error loading recut analysis report:', error);
    showNotification('Error loading recut analysis report', 'danger');
  }
}

window.ReportModules.cutStationRecuts = { load: loadCutStationRecutsReport };
</script>
