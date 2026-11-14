@extends('layouts.app')

@section('title', 'Job - Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle"><a href="/pm/jobs">Jobs</a></div>
          <h1 class="page-title" id="jobTitle">Job</h1>
        </div>
        <div class="col-auto ms-auto"><span class="badge" id="jobStage"></span></div>
      </div>
    </div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Estimate</h3></div>
        <div class="card-body">
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Imported sheets</label>
              <select class="form-select" id="sheetPick"><option value="">None imported yet</option></select>
            </div>
            <div class="col-md-5" data-permission="pm.jobs.manage">
              <label class="form-label">Upload BidEstimate workbook (.xls / .xlsx)</label>
              <div class="input-group">
                <input type="file" class="form-control" id="estFile" accept=".xls,.xlsx,.xlsm">
                <button class="btn" id="estUploadBtn" onclick="uploadEstimate()">Upload</button>
              </div>
            </div>
            <div class="col-md-3" id="importBox" style="display:none" data-permission="pm.jobs.manage">
              <label class="form-label">Tab to import</label>
              <div class="input-group">
                <select class="form-select" id="importSheet"></select>
                <button class="btn btn-primary" onclick="importSheet()">Import</button>
              </div>
            </div>
          </div>
          <div id="estNotice" class="mt-2"></div>
        </div>
      </div>

      <div id="estimateBody" style="display:none">
        <div class="row row-cards mb-3" id="estSummary"></div>
        <div id="estStale" class="alert alert-warning" style="display:none">This sheet was imported with an older parser. Re-import it to pick up parser fixes.</div>

        <div class="card mb-3" id="reviewCard" style="display:none">
          <div class="card-header"><h3 class="card-title">Needs your decision <span class="badge bg-orange-lt ms-2" id="reviewCount"></span></h3></div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>Row</th><th>Description</th><th class="text-end">Total</th><th>Why</th><th>Cost code</th><th class="w-1"></th></tr></thead>
              <tbody id="reviewBody"></tbody>
            </table>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">By cost code</h3></div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>Code</th><th>Description</th><th class="text-end">Lines</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr></thead>
              <tbody id="codeBody"></tbody>
            </table>
          </div>
        </div>

        <div class="card mb-3" id="decisionsCard" style="display:none">
          <div class="card-header"><h3 class="card-title">Your decisions</h3></div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table"><tbody id="decisionsBody"></tbody></table>
          </div>
        </div>

        <div class="card mb-3" id="warningsCard" style="display:none">
          <div class="card-header"><h3 class="card-title">Parser warnings</h3></div>
          <div class="card-body" id="warningsBody"></div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
  const JOB_ID = {{ (int) $jobId }};
  const money = (n) => n == null ? '—' : '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const REASONS = { no_cost_code_found: 'No cost code found', code_not_on_master_list: 'Code not on master list', recovered_from_malformed_code_prefix: 'Code recovered from a typo' };
  let costCodes = [], uploadedFile = null, currentSheet = null, canManage = false;

  const esc = (s) => escapeHtml(String(s ?? ''));
  const post = (url, body) => authenticatedFetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  const base = () => `/pm/jobs/${JOB_ID}/estimates`;

  function notice(msg, type = 'info') { document.getElementById('estNotice').innerHTML = msg ? `<div class="alert alert-${type} mb-0">${esc(msg)}</div>` : ''; }

  async function loadJob() {
    const job = await authenticatedFetch(`/pm/jobs/${JOB_ID}`);
    document.getElementById('jobTitle').textContent = `${job.job_number} — ${job.job_name}`;
    const b = document.getElementById('jobStage');
    b.textContent = job.status_label || job.status; b.className = 'badge bg-blue-lt';
  }

  async function loadSheets(select) {
    const sheets = await authenticatedFetch(base());
    const pick = document.getElementById('sheetPick');
    pick.innerHTML = sheets.length ? sheets.map(s => `<option value="${esc(s.sheet)}">${esc(s.sheet)} — ${money(s.contract_amount)}${s.review_count ? ` (${s.review_count} to review)` : ''}</option>`).join('') : '<option value="">None imported yet</option>';
    const target = select || currentSheet || (sheets[0] && sheets[0].sheet);
    if (target) { pick.value = target; await showSheet(target); }
  }

  async function showSheet(sheet) {
    if (!sheet) { document.getElementById('estimateBody').style.display = 'none'; return; }
    currentSheet = sheet;
    render(await authenticatedFetch(`${base()}/${encodeURIComponent(sheet)}`));
  }

  function render(e) {
    document.getElementById('estimateBody').style.display = '';
    document.getElementById('estStale').style.display = e.parserStale ? '' : 'none';
    const s = e.summary || {};
    const tiles = [['Contract amount', s.contractAmount], ['$ / sf glass', s.dollarsPerSquareFootGlass], ['Material', s.material], ['Large sub', s.large_sub], ['Other', s.other], ['Labor', s.labor]];
    document.getElementById('estSummary').innerHTML = tiles.filter(t => t[1] != null).map(([l, v]) =>
      `<div class="col-6 col-md-2"><div class="card"><div class="card-body"><div class="subheader">${l}</div><div class="h3 mb-0">${money(v)}</div></div></div></div>`).join('');

    document.getElementById('codeBody').innerHTML = Object.entries(e.byCostCode).map(([code, a]) =>
      `<tr><td>${esc(code)}</td><td>${esc(a.description)}</td><td class="text-end">${a.lineCount}</td><td class="text-end">${a.qty}</td><td class="text-end">${money(a.total)}</td></tr>`).join('')
      || '<tr><td colspan="5" class="text-muted">No coded lines.</td></tr>';

    const opts = costCodes.map(c => `<option value="${c.code}">${c.code} — ${esc(c.description)}</option>`).join('');
    document.getElementById('reviewCard').style.display = e.reviewNeeded.length ? '' : 'none';
    document.getElementById('reviewCount').textContent = e.reviewNeeded.length;
    document.getElementById('reviewBody').innerHTML = e.reviewNeeded.map((r, i) => `<tr>
      <td>${esc(r.section)} ${r.row}</td><td>${esc(r.description)}</td><td class="text-end">${money(r.total)}</td>
      <td>${esc(REASONS[r.reviewReason] || r.reviewReason)}${r.costCode ? ` (${esc(r.costCode)})` : ''}</td>
      <td><select class="form-select form-select-sm" id="fix-${i}"><option value="">Choose code…</option>${opts}</select></td>
      <td class="text-nowrap" data-permission="pm.jobs.manage">
        <button class="btn btn-sm btn-primary me-1" onclick="decide('${r.section}', ${r.row}, 'fix', ${i})">Fix</button>
        <button class="btn btn-sm" onclick="decide('${r.section}', ${r.row}, 'ignore')">Ignore</button></td></tr>`).join('');

    document.getElementById('decisionsCard').style.display = e.decisions.length ? '' : 'none';
    document.getElementById('decisionsBody').innerHTML = e.decisions.map(d => `<tr>
      <td>${esc(d.section)} ${d.row}</td><td>${esc(d.description)}</td><td class="text-end">${money(d.total)}</td>
      <td>${d.action === 'fix' ? 'Coded ' + esc(d.costCode) : 'Ignored'}</td>
      <td class="w-1" data-permission="pm.jobs.manage"><button class="btn btn-sm" onclick="restore('${d.section}', ${d.row})">Undo</button></td></tr>`).join('');

    document.getElementById('warningsCard').style.display = e.warnings.length ? '' : 'none';
    document.getElementById('warningsBody').innerHTML = e.warnings.map(w => `<div class="mb-1">${esc(w.type)}${w.section ? ' · ' + esc(w.section) : ''}
      ${w.computedFromLines != null ? `— lines add to ${money(w.computedFromLines)}, sheet says ${money(w.statedInSheet)}` : ''} ${w.note ? esc(w.note) : ''}</div>`).join('');
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  async function decide(section, row, action, i) {
    const body = { section, row, action };
    if (action === 'fix') {
      body.cost_code = document.getElementById('fix-' + i).value;
      if (!body.cost_code) { notice('Choose a cost code first.', 'warning'); return; }
    }
    try { render(await post(`${base()}/${encodeURIComponent(currentSheet)}/override`, body)); notice(''); }
    catch (e) { notice(e.message || 'Could not save that decision', 'danger'); }
  }

  async function restore(section, row) {
    try { render(await authenticatedFetch(`${base()}/${encodeURIComponent(currentSheet)}/override/${section}/${row}`, { method: 'DELETE' })); }
    catch (e) { notice(e.message || 'Could not undo', 'danger'); }
  }

  async function uploadEstimate() {
    const f = document.getElementById('estFile').files[0];
    if (!f) { notice('Choose a workbook first.', 'warning'); return; }
    const fd = new FormData(); fd.append('file', f);
    const btn = document.getElementById('estUploadBtn'); btn.disabled = true; btn.textContent = 'Uploading…';
    try {
      const r = await authenticatedUpload(`${base()}/upload`, fd);
      const d = await r.json().catch(() => ({}));
      if (!r.ok) { notice(d.message || (d.errors ? Object.values(d.errors).flat().join(' ') : 'Upload failed'), 'danger'); return; }
      uploadedFile = d.file;
      document.getElementById('importSheet').innerHTML = d.sheets.map(s => `<option>${esc(s)}</option>`).join('');
      document.getElementById('importBox').style.display = '';
      notice(d.already_uploaded ? 'This exact file was uploaded before. Choose a tab to import.' : 'Uploaded. Choose a tab to import.', 'info');
    } catch (e) { notice(e.message || 'Upload failed', 'danger'); }
    finally { btn.disabled = false; btn.textContent = 'Upload'; }
  }

  async function importSheet() {
    const sheet = document.getElementById('importSheet').value;
    try {
      const d = await post(`${base()}/import`, { pm_file_id: uploadedFile.id, sheet });
      const msg = { imported: 'Imported.', reimported: 'Re-imported with the new file.', unchanged: 'Nothing changed since the last import, so nothing was re-read.' }[d.outcome];
      await loadSheets(sheet); notice(msg, 'success');
    } catch (e) { notice(e.message || 'Import failed', 'danger'); }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('sheetPick').addEventListener('change', (ev) => showSheet(ev.target.value));
    try {
      [costCodes] = await Promise.all([authenticatedFetch('/pm/cost-codes'), loadJob()]);
      await loadSheets();
    } catch (e) { console.error(e); notice(e.message || 'Could not load this job', 'danger'); }
  });
</script>
@endsection
