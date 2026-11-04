<!-- SOF Savings: EZ Estimate summary net vs calculated reservation cost, per job -->
<div class="col-12" id="sofSavingsReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-pig-money me-2"></i>SOF Savings</h3>
      <div class="ms-auto d-flex gap-2">
        <input type="text" class="form-control form-control-sm" id="sofSavingsSearch" placeholder="Search job # or name..." style="max-width: 240px;">
        <button class="btn btn-sm btn-outline-secondary" onclick="sofSavingsRefresh()" title="Re-read the Summary sheet of every reserved upload"><i class="ti ti-refresh me-1"></i>Re-read estimates</button>
        <button class="btn btn-sm btn-primary" onclick="sofSavingsDownload()"><i class="ti ti-download me-1"></i>CSV</button>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">EZ Estimate (net)</div><div class="h2 mb-0" id="sofSavingsEz">-</div>
        </div></div></div>
        <div class="col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Reservation cost</div><div class="h2 mb-0" id="sofSavingsRes">-</div>
        </div></div></div>
        <div class="col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Savings</div><div class="h2 mb-0" id="sofSavingsTotal">-</div>
        </div></div></div>
        <div class="col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Jobs compared</div><div class="h2 mb-0" id="sofSavingsJobs">-</div>
        </div></div></div>
      </div>
      <div id="sofSavingsNotes"></div>

      <div id="sofSavingsLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Reading estimate summaries&hellip; the first load can take a minute.</div>
      </div>

      <div id="sofSavingsContent" style="display: none;">
        <div class="table-responsive">
          <table class="table table-vcenter">
            <thead>
              <tr>
                <th class="sortable-report" data-sort="job_number" data-report="sofSavings" style="cursor: pointer;">Job # <span class="sort-icon"></span></th>
                <th class="sortable-report" data-sort="job_name" data-report="sofSavings" style="cursor: pointer;">Job <span class="sort-icon"></span></th>
                <th class="text-end">Uploads</th>
                <th class="text-end sortable-report" data-sort="ez_net" data-report="sofSavings" style="cursor: pointer;">EZ net <span class="sort-icon"></span></th>
                <th class="text-end sortable-report" data-sort="reservation_cost" data-report="sofSavings" style="cursor: pointer;">Reservation cost <span class="sort-icon"></span></th>
                <th class="text-end sortable-report" data-sort="savings" data-report="sofSavings" style="cursor: pointer;">Savings <span class="sort-icon"></span></th>
                <th class="text-end sortable-report" data-sort="savings_pct" data-report="sofSavings" style="cursor: pointer;">% <span class="sort-icon"></span></th>
              </tr>
            </thead>
            <tbody id="sofSavingsBody"></tbody>
          </table>
        </div>
        <div class="card-footer d-flex align-items-center" id="sofSavingsPagination" style="display: none;"></div>
      </div>
    </div>
  </div>
</div>

<script>
window.ReportModules = window.ReportModules || {};

let sofSavingsSearchTerm = '';
const sofMoney = v => v == null ? '—' : (v < 0 ? '-$' : '$') + Math.abs(Number(v)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

async function loadSofSavingsReport(refresh = false) {
  try {
    document.getElementById('sofSavingsLoading').style.display = 'block';
    document.getElementById('sofSavingsContent').style.display = 'none';
    const r = await authenticatedFetch('/reports/job-sof-savings' + (refresh ? '?refresh=1' : ''));
    const s = r.summary;
    document.getElementById('sofSavingsEz').textContent = sofMoney(s.total_ez_net);
    document.getElementById('sofSavingsRes').textContent = sofMoney(s.total_reservation_cost);
    const tot = document.getElementById('sofSavingsTotal');
    tot.textContent = sofMoney(s.total_savings) + (s.savings_pct != null ? ` (${s.savings_pct}%)` : '');
    tot.className = 'h2 mb-0 ' + (s.total_savings >= 0 ? 'text-success' : 'text-danger');
    document.getElementById('sofSavingsJobs').textContent = s.jobs;
    const notes = [];
    if (s.pending_reads) notes.push(`<div class="alert alert-info py-2">${s.pending_reads} more estimate(s) still need reading — <a href="#" onclick="loadSofSavingsReport();return false">load again</a> to continue.</div>`);
    if (s.missing_price_items) notes.push(`<div class="alert alert-warning py-2">${s.missing_price_items} reservation item(s) have no net price and count as $0, so reservation cost is understated (and savings overstated) for those jobs.</div>`);
    if (r.errors.length) notes.push(`<div class="alert alert-warning py-2"><strong>${r.errors.length} upload(s) could not be read:</strong><ul class="mb-0">${r.errors.map(e => `<li>${escapeHtml(e.job_number)} — ${escapeHtml(e.document)}: ${escapeHtml(e.error)}</li>`).join('')}</ul></div>`);
    document.getElementById('sofSavingsNotes').innerHTML = notes.join('');
    reportPaginationState.sofSavings = r.jobs.filter(j => j.uploads > 0);
    renderSofSavingsTable(1);
    document.getElementById('sofSavingsLoading').style.display = 'none';
    document.getElementById('sofSavingsContent').style.display = 'block';
  } catch (e) {
    console.error('Error loading SOF savings report:', e);
    document.getElementById('sofSavingsLoading').style.display = 'none';
    showNotification('Error loading the report', 'danger');
  }
}

function renderSofSavingsTable(page = 1) {
  const term = sofSavingsSearchTerm.toLowerCase();
  let data = getProcessedReportData('sofSavings');
  if (term) data = data.filter(j => [j.job_number, j.job_name].some(v => (v || '').toLowerCase().includes(term)));
  const pagination = paginateData(data, page);
  const tbody = document.getElementById('sofSavingsBody');
  updateReportSortIcons('sofSavings');
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No jobs with a reserved EZ Estimate upload.</td></tr>';
    document.getElementById('sofSavingsPagination').style.display = 'none';
    return;
  }
  tbody.innerHTML = pagination.data.map(j => `
    <tr>
      <td><strong>${escapeHtml(j.job_number)}</strong></td>
      <td>${escapeHtml(j.job_name || '')}${j.missing_price ? ` <span class="badge bg-yellow-lt" title="${j.missing_price} item(s) without a net price">${j.missing_price} unpriced</span>` : ''}</td>
      <td class="text-end">${j.uploads}</td>
      <td class="text-end">${sofMoney(j.ez_net)}</td>
      <td class="text-end">${sofMoney(j.reservation_cost)}</td>
      <td class="text-end ${j.savings >= 0 ? 'text-success' : 'text-danger'}">${sofMoney(j.savings)}</td>
      <td class="text-end ${j.savings >= 0 ? 'text-success' : 'text-danger'}">${j.savings_pct != null ? j.savings_pct + '%' : '—'}</td>
    </tr>`).join('');
  renderReportPagination('sofSavingsPagination', pagination, renderSofSavingsTable);
}

function sofSavingsRefresh() {
  if (confirm('Re-read the Summary sheet of every reserved EZ Estimate upload? This can take a while.')) loadSofSavingsReport(true);
}

async function sofSavingsDownload() {
  try {
    const res = await fetch('/api/v1/reports/export?type=sof_savings', { credentials: 'include', headers: { 'Accept': 'text/csv' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const url = URL.createObjectURL(await res.blob());
    const a = document.createElement('a'); a.href = url; a.download = 'sof_savings.csv'; a.click();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  } catch (e) { showNotification('Could not export the report', 'danger'); }
}

document.getElementById('sofSavingsSearch').addEventListener('input', e => {
  sofSavingsSearchTerm = e.target.value.trim();
  renderSofSavingsTable(1);
});

window.ReportModules.sofSavings = { load: () => loadSofSavingsReport() };
</script>
