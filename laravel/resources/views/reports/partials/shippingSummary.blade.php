<!-- Shipping Summary Report -->
<div class="col-12" id="shippingSummaryReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-truck-delivery me-2"></i>Shipping Summary</h3>
      <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
        <input type="date" class="form-control form-control-sm" id="shipRepFrom" style="max-width: 150px;" onchange="loadShippingSummaryReport()" title="Ship date from">
        <input type="date" class="form-control form-control-sm" id="shipRepTo" style="max-width: 150px;" onchange="loadShippingSummaryReport()" title="Ship date to">
        <select class="form-select form-select-sm" id="shipRepStatus" style="max-width: 170px;" onchange="loadShippingSummaryReport()">
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="pre_check_done">Pre-check done</option>
          <option value="shipped">Shipped</option>
          <option value="delivered">Delivered</option>
          <option value="delivered_issues">Delivered w/ issues</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select class="form-select form-select-sm" id="shipRepJob" style="max-width: 220px;" onchange="loadShippingSummaryReport()"><option value="">All jobs</option></select>
        <button class="btn btn-sm btn-outline-primary" onclick="shipRepDownload('pdf')"><i class="ti ti-file-type-pdf me-1"></i>PDF</button>
        <button class="btn btn-sm btn-primary" onclick="shipRepDownload('csv')"><i class="ti ti-download me-1"></i>CSV</button>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-3" id="shipRepStats"></div>
      <div id="shipRepLoading" class="text-center py-4"><div class="spinner-border" role="status"></div></div>
      <div id="shipRepContent" style="display: none;">
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead><tr><th>Shipment</th><th>Ship date</th><th>Job(s)</th><th>Loads</th><th>Status</th><th>Pre-check</th><th>Delivery check</th><th>Delivery issues</th></tr></thead>
            <tbody id="shipRepBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.ReportModules = window.ReportModules || {};

const SHIP_REP_STATUS = { draft: ['Draft', 'secondary'], pre_check_done: ['Ready', 'azure'], shipped: ['In transit', 'cyan'], delivered: ['Delivered', 'success'], delivered_issues: ['Delivered – issues', 'danger'], cancelled: ['Cancelled', 'secondary'] };
const SHIP_REP_RESULT = { pass: ['Pass', 'success'], pass_with_issues: ['Issues', 'warning'], fail: ['Fail', 'danger'] };
let shipRepInit = false;

function shipRepQuery() {
  const p = new URLSearchParams();
  [['from', 'shipRepFrom'], ['to', 'shipRepTo'], ['status', 'shipRepStatus'], ['job_id', 'shipRepJob']].forEach(([k, id]) => {
    const v = document.getElementById(id).value; if (v) p.set(k, v);
  });
  return p.toString();
}

async function loadShippingSummaryReport() {
  try {
    if (!shipRepInit) {
      shipRepInit = true;
      const to = new Date(), from = new Date(Date.now() - 30 * 864e5), iso = d => d.toISOString().slice(0, 10);
      document.getElementById('shipRepFrom').value = iso(from);
      document.getElementById('shipRepTo').value = iso(to);
      try {
        const jobs = await authenticatedFetch('/shipments/job-options');
        document.getElementById('shipRepJob').insertAdjacentHTML('beforeend', jobs.map(j => `<option value="${j.id}">${escapeHtml(j.job_number)} · ${escapeHtml(j.job_name)}</option>`).join(''));
      } catch (e) { /* job filter is optional */ }
    }
    document.getElementById('shipRepLoading').style.display = 'block';
    document.getElementById('shipRepContent').style.display = 'none';
    const r = await authenticatedFetch('/reports/shipping-summary?' + shipRepQuery());
    const s = r.summary;
    const stat = (label, val, cls = '') => `<div class="col-6 col-md"><div class="card card-sm"><div class="card-body"><div class="text-muted">${label}</div><div class="h2 mb-0 ${cls}">${val}</div></div></div></div>`;
    document.getElementById('shipRepStats').innerHTML = stat('Shipments', s.total) + stat('In transit', s.in_transit, 'text-info') + stat('Delivered', s.delivered, 'text-success') +
      stat('Delivered with issues', s.delivered_issues, s.delivered_issues ? 'text-danger' : '') + stat('Failed pre-check', s.pre_failed, s.pre_failed ? 'text-danger' : '') + stat('Cancelled', s.cancelled, 'text-muted');
    const badge = (m, k) => { const x = m[k]; return x ? `<span class="badge bg-${x[1]}-lt">${x[0]}</span>` : '<span class="text-muted">—</span>'; };
    document.getElementById('shipRepBody').innerHTML = r.shipments.length ? r.shipments.map(x => `<tr style="cursor:pointer" onclick="location.href='/shipments/${x.id}'">
      <td class="fw-bold text-nowrap">${escapeHtml(x.shipment_number)}</td><td>${x.ship_date ?? '—'}</td><td>${escapeHtml(x.jobs || '—')}</td><td>${escapeHtml(x.loads)}</td>
      <td>${badge(SHIP_REP_STATUS, x.status)}</td><td>${badge(SHIP_REP_RESULT, x.pre_result)}</td><td>${badge(SHIP_REP_RESULT, x.post_result)}</td>
      <td class="text-danger">${escapeHtml(x.delivery_issues || '')}</td></tr>`).join('')
      : '<tr><td colspan="8" class="text-center text-muted py-4">No shipments in this range.</td></tr>';
    document.getElementById('shipRepLoading').style.display = 'none';
    document.getElementById('shipRepContent').style.display = 'block';
  } catch (e) {
    console.error(e);
    showNotification('Error loading shipping summary', 'danger');
  }
}

// Session-cookie auth: fetch the file with credentials, then open (PDF) or save (CSV) the blob.
async function shipRepDownload(kind) {
  const url = kind === 'pdf' ? '/api/v1/reports/shipping-summary/pdf?' : '/api/v1/reports/export?type=shipping_summary&';
  try {
    const res = await fetch(url + shipRepQuery(), { credentials: 'include', headers: { 'Accept': kind === 'pdf' ? 'application/pdf' : 'text/csv' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const blobUrl = URL.createObjectURL(await res.blob());
    if (kind === 'pdf') window.open(blobUrl, '_blank');
    else { const a = document.createElement('a'); a.href = blobUrl; a.download = 'shipping_summary.csv'; a.click(); }
    setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
  } catch (e) { showNotification('Could not export the report', 'danger'); }
}

window.ReportModules.shippingSummary = { load: () => loadShippingSummaryReport() };
</script>
