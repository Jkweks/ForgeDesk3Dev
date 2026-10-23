@once
  @include('reports.partials._cutStationUi')
@endonce

<!-- Cut Station Productivity Report -->
<div class="col-12" id="cutStationProductivityReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-scissors me-2"></i>Cut Station Productivity</h3>
      <div class="ms-auto d-flex gap-2">
        <input type="date" class="form-control form-control-sm" id="csProdStart" style="max-width: 150px;">
        <input type="date" class="form-control form-control-sm" id="csProdEnd" style="max-width: 150px;">
        <button class="btn btn-sm btn-outline-secondary" onclick="loadCutStationProductivityReport()">
          <i class="ti ti-refresh me-1"></i>Apply
        </button>
        <button class="btn btn-sm btn-primary" onclick="CutStationUi.csv('cut_station_productivity', 'csProd')" data-permission="reports.export">
          <i class="ti ti-download me-1"></i>CSV
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="text-muted small mb-3">
        Counts pieces cut at the saw. Drops, scrap tags and label reprints are not cuts; a recut is flagged when the piece is marked bad and its redo counts as a cut. Days and hours are shop time (Eastern).
      </div>
      <div class="row mb-3">
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Total Cuts</div><div class="h2 mb-0" id="csProdTotal">-</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Avg per Active Day</div><div class="h2 mb-0 text-info" id="csProdAvg">-</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Recut Rate</div><div class="h2 mb-0 text-danger" id="csProdRecut">-</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Busiest Day</div><div class="h3 mb-0 text-success" id="csProdBusiest">-</div>
        </div></div></div>
      </div>

      <div id="csProdLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Loading report...</div>
      </div>

      <div id="csProdContent" style="display: none;">
        <h4>Cuts per Operator</h4>
        <div class="table-responsive mb-4">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Operator</th>
                <th class="text-end">Cuts</th>
                <th style="width: 18%"></th>
                <th class="text-end">Recuts</th>
                <th class="text-end">Recut Rate</th>
                <th class="text-end">Manual Cuts</th>
                <th class="text-end">Inches Cut</th>
                <th class="text-end">Active Days</th>
                <th class="text-end">Avg / Active Day</th>
                <th>Last Cut</th>
              </tr>
            </thead>
            <tbody id="csProdOperatorBody"></tbody>
          </table>
        </div>

        <div class="row">
          <div class="col-lg-6">
            <h4>Cuts by Hour of Day</h4>
            <div class="table-responsive mb-4">
              <table class="table table-sm table-vcenter card-table">
                <thead><tr><th>Hour</th><th style="width: 60%"></th><th class="text-end">Cuts</th></tr></thead>
                <tbody id="csProdHourBody"></tbody>
              </table>
            </div>
          </div>
          <div class="col-lg-6">
            <h4>Daily Totals</h4>
            <div class="table-responsive">
              <table class="table table-sm table-vcenter card-table">
                <thead>
                  <tr><th>Date</th><th class="text-end">Cuts</th><th class="text-end">Recuts</th><th class="text-end">Operators</th></tr>
                </thead>
                <tbody id="csProdDateBody"></tbody>
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

async function loadCutStationProductivityReport() {
  const ui = window.CutStationUi;
  try {
    ui.show('csProd', false);
    const r = await authenticatedFetch(`/reports/cut-station/productivity${ui.query('csProd')}`);
    ui.syncDates('csProd', r.summary);

    document.getElementById('csProdTotal').textContent = ui.num(r.summary.total_cuts);
    document.getElementById('csProdAvg').textContent = ui.num(r.summary.avg_per_active_day, 1);
    document.getElementById('csProdRecut').textContent = ui.pct(r.summary.recut_rate);
    document.getElementById('csProdBusiest').textContent = r.summary.busiest_day || 'N/A';

    const maxOperator = Math.max(0, ...r.by_operator.map(o => o.cuts));
    ui.fill('csProdOperatorBody', r.by_operator, 10, o => `
      <tr>
        <td>${escapeHtml(o.operator)}</td>
        <td class="text-end">${ui.num(o.cuts)}</td>
        <td>${ui.bar(o.cuts, maxOperator)}</td>
        <td class="text-end">${ui.num(o.recuts)}</td>
        <td class="text-end">${ui.rateBadge(o.recut_rate)}</td>
        <td class="text-end">${ui.num(o.manual_cuts)}</td>
        <td class="text-end">${ui.num(o.inches, 1)}</td>
        <td class="text-end">${ui.num(o.active_days)}</td>
        <td class="text-end">${ui.num(o.avg_per_active_day, 1)}</td>
        <td>${escapeHtml(o.last_cut || '')}</td>
      </tr>`);

    const maxHour = Math.max(0, ...r.by_hour.map(h => h.cuts));
    ui.fill('csProdHourBody', r.by_hour.filter(h => h.cuts > 0), 3, h => `
      <tr><td>${h.label}</td><td>${ui.bar(h.cuts, maxHour, 'azure')}</td><td class="text-end">${ui.num(h.cuts)}</td></tr>`);

    ui.fill('csProdDateBody', r.by_date, 4, d => `
      <tr><td>${d.date}</td><td class="text-end">${ui.num(d.cuts)}</td><td class="text-end">${ui.num(d.recuts)}</td><td class="text-end">${ui.num(d.operators)}</td></tr>`);

    ui.show('csProd', true);
  } catch (error) {
    console.error('Error loading cut station productivity report:', error);
    showNotification('Error loading cut station productivity report', 'danger');
  }
}

window.ReportModules.cutStationProductivity = { load: loadCutStationProductivityReport };
</script>
