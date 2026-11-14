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

      <div class="card mb-3" id="detailsCard">
        <div class="card-header"><h3 class="card-title">Job details</h3><div class="card-actions"><button class="btn btn-sm" data-permission="pm.jobs.manage" onclick="toggleDetailsEdit()">Edit</button></div></div>
        <div class="card-body" id="detailsView"></div>
        <form class="card-body border-top" id="detailsForm" style="display:none" onsubmit="saveDetails(event)">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label required">Job name</label><input class="form-control" id="dName" required></div>
            <div class="col-md-6"><label class="form-label">Customer / GC</label><input class="form-control" id="dCustomer"></div>
            <div class="col-md-6"><label class="form-label">Site address</label><input class="form-control" id="dAddress"></div>
            <div class="col-md-6"><label class="form-label">Target completion</label><input type="date" class="form-control" id="dTarget"></div>
            <div class="col-md-4"><label class="form-label">Contact</label><input class="form-control" id="dContact"></div>
            <div class="col-md-4"><label class="form-label">Contact phone</label><input class="form-control" id="dPhone"></div>
            <div class="col-md-4"><label class="form-label">Contact email</label><input type="email" class="form-control" id="dEmail"></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" id="dNotes" rows="2"></textarea></div>
          </div>
          <div class="mt-3"><button class="btn btn-primary">Save</button> <button type="button" class="btn" onclick="toggleDetailsEdit()">Cancel</button></div>
        </form>
      </div>

      <div class="card mb-3" id="drawCard">
        <div class="card-header">
          <h3 class="card-title">Shop drawings</h3>
          <div class="card-actions" data-permission="pm.jobs.manage">
            <div class="input-group input-group-sm"><input type="file" accept=".pdf" class="form-control" id="drawFile"><button class="btn btn-primary" onclick="uploadDrawing()">Upload set</button></div>
          </div>
        </div>
        <div class="list-group list-group-flush" id="drawBody"><div class="list-group-item text-muted">Loading...</div></div>
        <div class="card-footer text-muted small">The set is read in the background (a large set can take a few minutes). Sizes and quantities are suggestions for work orders: nothing is applied until someone confirms it.</div>
      </div>

      <div class="card mb-3" id="woCard">
        <div class="card-header">
          <h3 class="card-title">Work order requests</h3>
          <div class="card-actions"><button class="btn btn-sm btn-primary" data-permission="pm.jobs.manage" onclick="showWoForm()">Request work order</button></div>
        </div>
        <div class="list-group list-group-flush" id="woBody"><div class="list-group-item text-muted">Loading...</div></div>
        <div class="card-footer text-muted small">Fabrication validates each request and turns it into a work order. The job must be active.</div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Submittals</h3>
          <div class="card-actions btn-list">
            <label class="form-check form-switch m-0 me-2"><input class="form-check-input" type="checkbox" id="smNeeds" onchange="loadSubmittals()"><span class="form-check-label">Needs response</span></label>
            <button class="btn btn-sm btn-primary" data-permission="pm.jobs.manage" onclick="showSubmittalForm()">+ Submittal</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>#</th><th>Name / section</th><th>Kind</th><th>POs</th><th>Sent</th><th style="width:190px">Status</th><th class="w-1"></th></tr></thead>
            <tbody id="smBody"><tr><td colspan="7" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Material forecast</h3>
          <div class="card-actions"><label class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="fcAll" onchange="loadForecast()"><span class="form-check-label">All cost codes</span></label></div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Code</th><th>Description</th><th class="text-end">Estimated</th><th class="text-end">Ordered</th><th class="text-end">Ordered %</th><th class="text-end">Planned (draft)</th><th class="text-end">Received</th><th>Status</th></tr></thead>
            <tbody id="forecastBody"><tr><td colspan="8" class="text-muted">Loading...</td></tr></tbody>
            <tfoot id="forecastFoot"></tfoot>
          </table>
        </div>
        <div class="card-footer text-muted small">Ordered and received count issued job purchase orders only. Draft POs are shown as planned. Stock and internal POs are not job material.</div>
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

<div class="modal fade" id="szModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="szTitle">Check sizes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-lg-8">
          <div class="d-flex align-items-center gap-2 mb-2"><select class="form-select form-select-sm w-auto" id="szPage" onchange="szLoadPage()"></select>
            <span class="text-muted small" id="szCap"></span></div>
          <canvas id="szCanvas" style="width:100%;display:block;background:#fff;border:1px solid var(--tblr-border-color);border-radius:4px;cursor:zoom-in" onclick="szCanvasClick(event)" onmousemove="szHoverMove(event)" onmouseleave="szSetHover(null)"></canvas>
          <div class="d-flex gap-3 small text-muted mt-2 flex-wrap">
            <span><span class="badge" style="background:rgba(40,170,70,.5);border:1px solid #1e8c37">&nbsp;</span> confirmed</span>
            <span><span class="badge" style="background:rgba(255,215,0,.55);border:1px solid #d4a00a">&nbsp;</span> read by the parser, not yet checked</span>
            <span><span class="badge" style="background:rgba(225,45,35,.45);border:1px solid #cd281e">&nbsp;</span> corrected (differs from the reading)</span>
            <span class="ms-auto">hover a value to highlight it · click the sheet to zoom</span>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="d-flex align-items-center mb-2"><h4 class="mb-0">Values on this sheet</h4>
            <button class="btn btn-sm btn-primary ms-auto" data-permission="pm.jobs.manage" onclick="szConfirmAll()">Confirm all</button></div>
          <div id="szList"></div>
          <div id="szNote" class="mt-2 small"></div>
        </div>
      </div>
    </div>
  </div></div>
</div>

<div class="modal fade" id="woModal" tabindex="-1">
  <div class="modal-dialog"><form class="modal-content" onsubmit="saveWo(event)">
    <div class="modal-header"><h5 class="modal-title">Request a work order</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2 mb-3"><div class="col-6"><label class="form-label">Release code</label><input class="form-control" id="woCode" placeholder="e.g. A"></div>
        <div class="col-6"><label class="form-label">Needed by</label><input type="date" class="form-control" id="woDue"></div></div>
      <div class="mb-3"><label class="form-label">Scope</label><textarea class="form-control" id="woScope" rows="3" placeholder="Elevations, openings, what is being released"></textarea></div>
      <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" id="woNotes" rows="2"></textarea></div>
      <div class="mb-1"><label class="form-label">Drawings</label><input type="file" class="form-control" id="woFiles" multiple></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Send request</button></div>
  </form></div>
</div>

<div class="modal fade" id="smModal" tabindex="-1">
  <div class="modal-dialog"><form class="modal-content" onsubmit="saveSubmittal(event)">
    <div class="modal-header"><h5 class="modal-title">New submittal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label required">Name</label><input class="form-control" id="smName" required></div>
      <div class="row g-2"><div class="col-6"><label class="form-label">Spec section</label><input class="form-control" id="smSpec" placeholder="08 41 13"></div>
      <div class="col-6"><label class="form-label">Kind</label><select class="form-select" id="smKind"><option value="docs">Docs</option><option value="sample">Sample</option><option value="docs_samples">Docs + samples</option></select></div></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
  </form></div>
</div>

<div class="modal fade" id="smRespModal" tabindex="-1">
  <div class="modal-dialog"><form class="modal-content" onsubmit="saveResponse(event)">
    <div class="modal-header"><h5 class="modal-title">Record GC/AE response</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label required">Response</label><select class="form-select" id="rStatus" required></select></div>
      <div class="row g-2 mb-3"><div class="col-6"><label class="form-label required">Returned</label><input type="date" class="form-control" id="rReturned" required></div>
      <div class="col-6"><label class="form-label">Responded by</label><input class="form-control" id="rBy"></div></div>
      <div class="mb-3"><label class="form-label">Marked-up document</label><input type="file" class="form-control" id="rFile"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save response</button></div>
  </form></div>
</div>

@include('partials.pm-modal-helpers')

<script>
  const JOB_ID = {{ (int) $jobId }};
  const money = (n) => n == null ? '—' : '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const REASONS = { no_cost_code_found: 'No cost code found', code_not_on_master_list: 'Code not on master list', recovered_from_malformed_code_prefix: 'Code recovered from a typo' };
  let costCodes = [], uploadedFile = null, currentSheet = null;

  const esc = (s) => escapeHtml(String(s ?? ''));
  const post = (url, body) => authenticatedFetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  const base = () => `/pm/jobs/${JOB_ID}/estimates`;

  function notice(msg, type = 'info') { document.getElementById('estNotice').innerHTML = msg ? `<div class="alert alert-${type} mb-0">${esc(msg)}</div>` : ''; }

  let jobData = null;
  function renderDetails(job) {
    jobData = job;
    const row = (l, v) => v ? `<div class="col-md-4 mb-2"><div class="text-muted small">${l}</div><div>${esc(v)}</div></div>` : '';
    detailsView.innerHTML = `<div class="row">${row('Customer / GC', job.customer_name)}${row('Site', job.site_address)}${row('Target completion', (job.target_completion_date || '').slice(0, 10))}
      ${row('Contact', [job.contact_name, job.contact_phone, job.contact_email].filter(Boolean).join(' · '))}${row('Source', job.origin === 'pm' ? 'PM bid' : 'Created in Fabrication')}${row('Notes', job.notes)}</div>`
      || '<div class="text-muted">No details yet.</div>';
  }
  function toggleDetailsEdit() {
    const f = detailsForm, show = f.style.display === 'none';
    f.style.display = show ? '' : 'none';
    if (show && jobData) { dName.value = jobData.job_name || ''; dCustomer.value = jobData.customer_name || ''; dAddress.value = jobData.site_address || ''; dTarget.value = (jobData.target_completion_date || '').slice(0, 10);
      dContact.value = jobData.contact_name || ''; dPhone.value = jobData.contact_phone || ''; dEmail.value = jobData.contact_email || ''; dNotes.value = jobData.notes || ''; }
  }
  async function saveDetails(e) {
    e.preventDefault();
    try {
      const job = await post(`/pm/jobs/${JOB_ID}`, { job_name: dName.value, customer_name: dCustomer.value || null, site_address: dAddress.value || null, target_completion_date: dTarget.value || null,
        contact_name: dContact.value || null, contact_phone: dPhone.value || null, contact_email: dEmail.value || null, notes: dNotes.value || null }, 'PUT');
      renderDetails(job); document.getElementById('jobTitle').textContent = `${job.job_number} — ${job.job_name}`; detailsForm.style.display = 'none'; notice('Saved.', 'success');
    } catch (err) { notice(err.message || 'Could not save', 'danger'); }
  }

  async function loadJob() {
    const job = await authenticatedFetch(`/pm/jobs/${JOB_ID}`);
    renderDetails(job);
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

  const FC_STATUS = {
    none: ['—', 'bg-secondary-lt'], not_ordered: ['Not ordered', 'bg-orange-lt'], draft_only: ['Draft only', 'bg-yellow-lt'],
    ordered: ['Ordered', 'bg-blue-lt'], partially_received: ['Partly received', 'bg-cyan-lt'], received: ['Received', 'bg-green-lt'],
    over_budget: ['Over estimate', 'bg-red-lt'],
  };

  async function loadForecast() {
    const all = document.getElementById('fcAll').checked;
    const f = await authenticatedFetch(`/pm/jobs/${JOB_ID}/forecast${all ? '?category=all' : ''}`);
    const body = document.getElementById('forecastBody');
    body.innerHTML = f.rows.length ? f.rows.map(r => {
      const [label, cls] = FC_STATUS[r.status] || [r.status, 'bg-secondary-lt'];
      return `<tr><td>${r.costCode}</td><td>${esc(r.description)}</td>
        <td class="text-end">${money(r.estimatedTotal)}<div class="text-muted small">${r.estimatedQty} qty</div></td>
        <td class="text-end">${money(r.orderedTotal)}<div class="text-muted small">${r.orderedQty} qty</div></td>
        <td class="text-end">${r.orderedPct == null ? '—' : r.orderedPct + '%'}</td>
        <td class="text-end">${r.draftTotal ? money(r.draftTotal) : '—'}</td>
        <td class="text-end">${money(r.receivedTotal)}<div class="text-muted small">${r.receivedPct == null ? '—' : r.receivedPct + '% of ordered'}</div></td>
        <td><span class="badge ${cls}">${label}</span></td></tr>`;
    }).join('') : '<tr><td colspan="8" class="text-muted">Import an estimate or add job purchase orders to see the forecast.</td></tr>';
    const t = f.totals;
    document.getElementById('forecastFoot').innerHTML = f.rows.length ? `<tr class="fw-bold"><td colspan="2">Total</td><td class="text-end">${money(t.estimatedTotal)}</td><td class="text-end">${money(t.orderedTotal)}</td><td></td><td class="text-end">${money(t.draftTotal)}</td><td class="text-end">${money(t.receivedTotal)}</td><td></td></tr>` : '';
  }

  // ---- shop drawings ----
  let drawTimer = null;
  const drawSetsCache = {};
  const szBadge = (e) => { const m = e.marks || [], c = m.filter(x => x.status === 'confirmed').length, r = m.filter(x => x.status === 'mismatch').length;
    return `<span class="badge ${r ? 'bg-red-lt' : (c === m.length ? 'bg-green-lt' : 'bg-yellow-lt')}">${c}/${m.length} checked${r ? ', ' + r + ' corrected' : ''}</span>`; };
  const DR_STATUS = { queued: ['Queued', 'bg-secondary-lt'], running: ['Reading…', 'bg-blue-lt'], done: ['Ready', 'bg-green-lt'], failed: ['Failed', 'bg-red-lt'] };
  const dim = (v) => v == null ? '—' : String(+v);
  // ---- visual size check (the sheet with each read dimension drawn over it) ----
  const SZ_KIND = { ro_w: 'R.O. width', fs_w: 'F.S. width', ro_h: 'R.O. height', fs_h: 'F.S. height', do_w: 'Door opening width', do_h: 'Door opening height', dlo_w: 'D.L.O. width', dlo_h: 'D.L.O. height' };
  const SZ_COLORS = { confirmed: ['rgba(40,170,70,0.28)', '#1e8c37'], suggested: ['rgba(255,215,0,0.34)', '#d4a00a'], mismatch: ['rgba(225,45,35,0.28)', '#cd281e'] };
  let sz = null;   // { setId, elev, page, img, hover, selected, zoom }

  // inches -> 38 1/4" (nearest 1/16)
  function fmtIn(v) { v = Number(v); const w = Math.floor(v), s = Math.round((v - w) * 16); const W = s === 16 ? w + 1 : w, S = s === 16 ? 0 : s;
    if (!S) return W + '"'; let n = S, d = 16; while (n % 2 === 0) { n /= 2; d /= 2; } return (W ? W + ' ' : '') + n + '/' + d + '"'; }
  // "38 1/4", "38-1/4", "38.25" -> 38.25
  function parseIn(t) { t = String(t).replace(/["”]/g, '').trim(); if (!t) return NaN; const m = t.match(/^(\d+(?:\.\d+)?)?[\s-]*(?:(\d+)\/(\d+))?$/); if (!m || (!m[1] && !m[2])) return NaN;
    return (m[1] ? parseFloat(m[1]) : 0) + (m[2] ? parseInt(m[2]) / parseInt(m[3]) : 0); }

  function szElev() { return drawSetsCache[sz.setId].elevations.find(e => e.id === sz.elevId); }
  function szMarksOnPage() { return szElev().marks.filter(m => m.page === sz.page); }

  async function openSheetViewer(setId, elevId) {
    const e = drawSetsCache[setId].elevations.find(x => x.id === elevId);
    sz = { setId, elevId, page: e.sheets.length ? e.sheets[0].page : null, img: null, hover: null, selected: null, zoom: null };
    szTitle.textContent = `Check sizes — elevation ${e.label}`;
    szPage.innerHTML = e.sheets.map(s => `<option value="${s.page}">Sheet ${s.page}</option>`).join('');
    szNote.innerHTML = e.sheets.length > 1 ? `<div class="text-muted">This elevation runs across ${e.sheets.length} sheets. Check each one.</div>` : '';
    safeShowModal('szModal');
    setTimeout(szLoadPage, 150);   // let the modal lay out so the canvas has a width
  }

  function szLoadPage() {
    sz.page = parseInt(szPage.value || sz.page); sz.zoom = null; sz.selected = null; sz.img = null; sz.error = null;
    const sheet = szElev().sheets.find(s => s.page === sz.page);
    szCap.textContent = sheet ? 'Loading the sheet…' : '';
    szRenderList(); szDraw();
    const img = new Image();
    img.onload = () => { sz.img = img; szCap.textContent = `Sheet ${sz.page}`; szDraw(); };
    img.onerror = () => { sz.error = true; szCap.textContent = 'The sheet image could not be loaded (is the drawing worker running?). The values are still listed.'; szDraw(); };
    img.src = `${location.origin}/api/v1/pm/jobs/${JOB_ID}/drawing-sets/${sz.setId}/pages/${sz.page}/image`;
  }

  function szDraw() {
    const cv = szCanvas, sheet = szElev().sheets.find(s => s.page === sz.page);
    if (!sheet) return;
    const W = cv.clientWidth || 800, ratio = sheet.height / sheet.width, dpr = window.devicePixelRatio || 1;
    cv.width = W * dpr; cv.height = W * ratio * dpr; cv.style.height = (W * ratio) + 'px';
    const ctx = cv.getContext('2d'), k = (cv.width / sheet.width);        // page points -> canvas pixels
    ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cv.width, cv.height);
    const z = sz.zoom;
    if (z) { ctx.translate(cv.width / 2 - z.cx * k * z.scale, cv.height / 2 - z.cy * k * z.scale); ctx.scale(z.scale, z.scale); }
    if (sz.img) ctx.drawImage(sz.img, 0, 0, cv.width, cv.height);
    szMarksOnPage().forEach(m => {
      const [fill, line] = SZ_COLORS[m.status] || SZ_COLORS.suggested, pad = 3;
      const x = m.x0 * k - pad, y = m.top * k - pad, w = (m.x1 - m.x0) * k + pad * 2, h = (m.bottom - m.top) * k + pad * 2;
      const hot = sz.hover === m.id || sz.selected === m.id;
      ctx.fillStyle = fill; ctx.fillRect(x, y, w, h);
      ctx.lineWidth = hot ? 3 : 1.5; ctx.strokeStyle = line; ctx.strokeRect(x, y, w, h);
      if (hot) { ctx.shadowColor = line; ctx.shadowBlur = 12; ctx.strokeRect(x, y, w, h); ctx.shadowBlur = 0; }
    });
  }

  function szRenderList() {
    const marks = szMarksOnPage();
    szList.innerHTML = marks.length ? marks.map(m => {
      const [, line] = SZ_COLORS[m.status] || SZ_COLORS.suggested;
      return `<div class="border rounded p-2 mb-2" style="border-left:4px solid ${line} !important" onmouseenter="szSetHover(${m.id})" onmouseleave="szSetHover(null)" onclick="szSelect(${m.id})">
        <div class="d-flex align-items-center"><div><div class="small text-muted">${esc(SZ_KIND[m.kind] || m.kind)}${/^dlo/.test(m.kind) ? ' #' + (m.idx + 1) : ''}</div>
          <strong>${fmtIn(m.value)}</strong>${m.status === 'mismatch' ? ` <span class="text-danger">→ ${fmtIn(m.confirmed_value)}</span>` : ''}</div>
          <span class="ms-auto text-nowrap" data-permission="pm.jobs.manage">
            <button class="btn btn-sm ${m.status === 'confirmed' ? 'btn-success' : ''}" onclick="event.stopPropagation(); szMark([${m.id}], 'confirm')">✓</button>
            <button class="btn btn-sm" onclick="event.stopPropagation(); szCorrect(${m.id})">Correct</button>
            ${m.status !== 'suggested' ? `<button class="btn btn-sm btn-ghost-secondary" onclick="event.stopPropagation(); szMark([${m.id}], 'clear')">Clear</button>` : ''}</span></div></div>`;
    }).join('') : '<div class="text-muted small">No dimensions were located on this sheet.</div>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  function szSetHover(id) { sz.hover = id; szDraw(); }
  function szHoverMove(ev) {
    const cv = szCanvas, r = cv.getBoundingClientRect(), sheet = szElev().sheets.find(s => s.page === sz.page);
    if (!sheet || sz.zoom) return;
    const px = (ev.clientX - r.left) / r.width * sheet.width, py = (ev.clientY - r.top) / r.height * sheet.height;
    const hit = szMarksOnPage().find(m => px >= m.x0 - 3 && px <= m.x1 + 3 && py >= m.top - 3 && py <= m.bottom + 3);
    if ((hit ? hit.id : null) !== sz.hover) szSetHover(hit ? hit.id : null);
  }
  function szSelect(id) {
    const m = szMarksOnPage().find(x => x.id === id); if (!m) return;
    sz.selected = id; sz.zoom = sz.zoom ? null : { cx: (m.x0 + m.x1) / 2, cy: (m.top + m.bottom) / 2, scale: 2.6 }; szDraw();
  }
  function szCanvasClick(ev) {
    if (sz.zoom) { sz.zoom = null; szDraw(); return; }
    const r = szCanvas.getBoundingClientRect(), sheet = szElev().sheets.find(s => s.page === sz.page);
    sz.zoom = { cx: (ev.clientX - r.left) / r.width * sheet.width, cy: (ev.clientY - r.top) / r.height * sheet.height, scale: 2.6 }; szDraw();
  }

  async function szMark(ids, action, value) {
    try {
      const upd = await post(`/pm/jobs/${JOB_ID}/drawing-sets/${sz.setId}/marks`, { ids, action, value });
      const e = szElev(); upd.forEach(u => { const i = e.marks.findIndex(m => m.id === u.id); if (i >= 0) e.marks[i] = u; });
      szRenderList(); szDraw(); szNote.innerHTML = '';
      toggleDrawing(sz.setId, true).catch(() => {});   // refresh the checked-count badges behind the dialog
    } catch (err) { szNote.innerHTML = `<div class="text-danger">${esc(err.message || 'Could not save')}</div>`; }
  }
  function szCorrect(id) {
    const m = szMarksOnPage().find(x => x.id === id), t = prompt(`What is the real ${SZ_KIND[m.kind]}? (the drawing read ${fmtIn(m.value)})`, fmtIn(m.confirmed_value ?? m.value));
    if (t === null) return;
    const v = parseIn(t); if (isNaN(v)) { szNote.innerHTML = '<div class="text-danger">Enter inches like 38 1/4 or 38.25.</div>'; return; }
    szMark([id], 'correct', v);
  }
  function szConfirmAll() { const ids = szMarksOnPage().filter(m => m.status === 'suggested').map(m => m.id); if (ids.length) szMark(ids, 'confirm'); }

  async function loadDrawings() {
    const sets = await authenticatedFetch(`/pm/jobs/${JOB_ID}/drawing-sets`);
    drawBody.innerHTML = sets.length ? sets.map(s => { const [l, c] = DR_STATUS[s.status] || [s.status, 'bg-secondary-lt'];
      return `<div class="list-group-item"><div class="d-flex align-items-center gap-2"><span class="badge ${c}">${l}</span>
        <a href="#" onclick="toggleDrawing(${s.id}); return false;">${esc(s.file ? s.file.original_name : 'Drawing set')}</a>
        <span class="text-muted small">${s.status === 'done' ? `${s.elevations_count} elevations · ${s.page_count} pages` : ''}${s.error ? ' · ' + esc(s.error) : ''}</span>
        <span class="ms-auto text-nowrap" data-permission="pm.jobs.manage"><button class="btn btn-sm" onclick="reimportDrawing(${s.id})" ${s.status === 'running' ? 'disabled' : ''}>Read again</button>
          <button class="btn btn-sm btn-ghost-danger" onclick="deleteDrawing(${s.id})">&times;</button></span></div>
        <div id="drawd-${s.id}" style="display:none" class="mt-2"></div></div>`; }).join('') : '<div class="list-group-item text-muted">No drawing sets yet.</div>';
    clearTimeout(drawTimer);
    if (sets.some(s => s.status === 'queued' || s.status === 'running')) drawTimer = setTimeout(loadDrawings, 5000);
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }
  async function toggleDrawing(id, keep) {
    const box = document.getElementById('drawd-' + id);
    if (!box) return;
    if (!keep && box.style.display !== 'none') { box.style.display = 'none'; return; }
    const s = await authenticatedFetch(`/pm/jobs/${JOB_ID}/drawing-sets/${id}`);
    drawSetsCache[id] = s;
    box.style.display = '';
    box.innerHTML = s.status !== 'done' ? '<div class="text-muted small">Not ready yet.</div>' : `
      ${s.set_finish ? `<div class="small mb-1">Set finish: ${esc(s.set_finish)}</div>` : ''}
      <div class="table-responsive"><table class="table table-sm table-vcenter"><thead><tr><th>Elevation</th><th>Sheet</th><th>System / finish</th><th class="text-end">Qty (thus/opp/total)</th><th>R.O. W × H</th><th>F.S. W × H</th><th>D.L.O. widths</th><th>Glass</th><th></th></tr></thead><tbody>
      ${s.elevations.map(e => `<tr><td>${esc(e.label)}<div class="text-muted small">${esc(e.arch_ref || '')}</div></td><td>${e.page ?? ''}</td><td>${esc(e.system || '')}<div class="text-muted small">${esc(e.finish || '')}</div></td>
        <td class="text-end">${e.thus_reqd ?? '—'} / ${e.opp_reqd ?? '—'} / ${e.total_reqd ?? '—'}</td><td>${dim(e.ro_w)} × ${dim(e.ro_h)}</td><td>${dim(e.fs_w)} × ${dim(e.fs_h)}</td>
        <td>${e.dims.filter(d => d.axis === 'w').map(d => dim(d.value)).join(', ')}</td><td>${e.glass_tags.map(t => esc(t.tag)).join(', ')}</td><td class="text-nowrap">${(e.marks || []).length ? `<button class="btn btn-sm" onclick="openSheetViewer(${s.id}, ${e.id})">Check sizes</button> ${szBadge(e)}` : '<span class="text-muted small">no sheet marks</span>'}</td></tr>`).join('') || '<tr><td colspan="9" class="text-muted">No elevations were found.</td></tr>'}
      </tbody></table></div>
      ${s.warnings.length ? `<div class="small text-warning">${s.warnings.map(w => esc(w.message)).join('<br>')}</div>` : ''}`;
  }
  async function uploadDrawing() {
    const f = drawFile.files[0]; if (!f) { notice('Choose a PDF first.', 'warning'); return; }
    const fd = new FormData(); fd.append('file', f);
    const r = await authenticatedUpload(`/pm/jobs/${JOB_ID}/drawing-sets`, fd);
    const d = await r.json().catch(() => ({}));
    if (!r.ok) { notice(d.message || (d.errors ? Object.values(d.errors).flat().join(' ') : 'Upload failed'), 'danger'); return; }
    drawFile.value = ''; notice(d.already_uploaded ? 'That set was already uploaded.' : 'Uploaded. It is being read in the background.', 'info'); loadDrawings();
  }
  async function reimportDrawing(id) { try { await post(`/pm/jobs/${JOB_ID}/drawing-sets/${id}/reimport`, {}); loadDrawings(); } catch (e) { notice(e.message, 'danger'); } }
  async function deleteDrawing(id) { if (confirm('Delete this drawing set and its results?')) { try { await authenticatedFetch(`/pm/jobs/${JOB_ID}/drawing-sets/${id}`, { method: 'DELETE' }); loadDrawings(); } catch (e) { notice(e.message, 'danger'); } } }

  // ---- work order requests ----
  const WO_STATUS = { pending: ['Pending', 'bg-orange-lt'], approved: ['Approved', 'bg-green-lt'], rejected: ['Rejected', 'bg-red-lt'], withdrawn: ['Withdrawn', 'bg-secondary-lt'] };
  async function loadWo() {
    const rows = await authenticatedFetch(`/pm/jobs/${JOB_ID}/work-order-requests`);
    woBody.innerHTML = rows.length ? rows.map(r => { const [l, c] = WO_STATUS[r.status] || [r.status, 'bg-secondary-lt'];
      return `<div class="list-group-item"><div class="d-flex align-items-center gap-2"><span class="badge ${c}">${l}</span>
        <strong>${esc(r.release_code || 'Release')}</strong><span class="text-muted">${r.requested_due_date ? 'needed ' + esc(r.requested_due_date) : ''}</span>
        <span class="ms-auto">${r.status === 'pending' ? `<button class="btn btn-sm" data-permission="pm.jobs.manage" onclick="withdrawWo(${r.id})">Withdraw</button>` : ''}</span></div>
        ${r.scope ? `<div class="small mt-1">${esc(r.scope)}</div>` : ''}
        ${r.files.length ? `<div class="small text-muted mt-1">${r.files.length} drawing(s) attached</div>` : ''}
        ${r.decision_note ? `<div class="small mt-1"><em>Fabrication:</em> ${esc(r.decision_note)}</div>` : ''}
        ${r.work_order ? `<div class="small mt-1">Work order created (release ${r.work_order.release_number}).</div>` : ''}</div>`; }).join('') : '<div class="list-group-item text-muted">No requests yet.</div>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }
  function showWoForm() { document.querySelector('#woModal form').reset(); safeShowModal('woModal'); }
  async function saveWo(e) {
    e.preventDefault();
    try {
      const r = await post(`/pm/jobs/${JOB_ID}/work-order-requests`, { release_code: woCode.value || null, requested_due_date: woDue.value || null, scope: woScope.value || null, notes: woNotes.value || null });
      for (const f of woFiles.files) { const fd = new FormData(); fd.append('file', f); await authenticatedUpload(`/pm/work-order-requests/${r.id}/files`, fd); }
      safeHideModal('woModal'); notice('Request sent to Fabrication.', 'success'); loadWo();
    } catch (err) { notice(err.message || 'Could not send the request', 'danger'); }
  }
  async function withdrawWo(id) { if (confirm('Withdraw this request?')) { try { await post(`/pm/work-order-requests/${id}/withdraw`, {}); loadWo(); } catch (e) { notice(e.message, 'danger'); } } }

  // ---- submittals ----
  const SM_STATUS = { pending: 'Pending', approved: 'Approved', approved_as_noted: 'Approved as noted', revise_resubmit: 'Revise & resubmit', rejected: 'Rejected', for_record_only: 'For record only' };
  const SM_KIND = { docs: 'Docs', sample: 'Sample', docs_samples: 'Docs + samples' };
  const smBase = () => `/pm/jobs/${JOB_ID}/submittals`;
  let smOpen = null, smRespFor = null, jobPos = [];

  async function loadSubmittals() {
    const needs = document.getElementById('smNeeds').checked;
    const rows = await authenticatedFetch(smBase() + (needs ? '?needs_response=1' : ''));
    const body = document.getElementById('smBody');
    body.innerHTML = rows.length ? rows.map(s => `<tr style="cursor:pointer" onclick="toggleSubmittal(${s.id})">
      <td>${esc(s.number)}${s.revision_number ? ` <span class="badge bg-azure-lt">Rev ${s.revision_number}</span>` : ''}</td>
      <td>${esc(s.name)}<div class="text-muted small">${esc(s.spec_section || '')}</div></td><td>${SM_KIND[s.kind] || s.kind}</td>
      <td>${(s.purchase_orders || []).map(p => `<a href="/pm/purchase-orders/${p.id}" onclick="event.stopPropagation()">${esc(p.po_number)}</a>`).join(', ')}</td>
      <td>${esc((s.sent_at || '').slice(0, 10))}</td>
      <td onclick="event.stopPropagation()"><select class="form-select form-select-sm" data-permission="pm.jobs.manage" onchange="setSmStatus(${s.id}, this.value)">${Object.entries(SM_STATUS).map(([k, v]) => `<option value="${k}" ${k === s.status ? 'selected' : ''}>${v}</option>`).join('')}</select></td>
      <td class="text-nowrap" onclick="event.stopPropagation()" data-permission="pm.jobs.manage"><button class="btn btn-sm" onclick="reviseSm(${s.id})">Revise</button></td></tr>
      <tr id="smd-${s.id}" style="display:none"><td colspan="7" class="bg-body-tertiary" id="smdc-${s.id}"></td></tr>`).join('') : '<tr><td colspan="7" class="text-muted">No submittals yet.</td></tr>';
    if (smOpen) toggleSubmittal(smOpen, true);
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  async function toggleSubmittal(id, keep) {
    const row = document.getElementById('smd-' + id); if (!row) return;
    if (!keep && row.style.display !== 'none') { row.style.display = 'none'; smOpen = null; return; }
    document.querySelectorAll('[id^="smd-"]').forEach(r => r.style.display = 'none');
    const s = await authenticatedFetch(`${smBase()}/${id}`);
    smOpen = id; row.style.display = '';
    if (!jobPos.length) { try { jobPos = (await authenticatedFetch(`/job-purchase-orders?business_job_id=${JOB_ID}`)).data || []; } catch (e) {} }
    const linked = new Set((s.purchase_orders || []).map(p => p.id));
    document.getElementById('smdc-' + id).innerHTML = `<div class="row g-3">
      <div class="col-md-4"><h4>GC/AE responses</h4>${(s.responses || []).map(r => `<div class="mb-2">${SM_STATUS[r.status] || r.status} · ${esc((r.returned_at || '').slice(0, 10))}${r.responded_by ? ' · ' + esc(r.responded_by) : ''}${r.file ? `<div class="text-muted small">${esc(r.file.original_name)} (Rev ${r.revision_number})</div>` : ''}</div>`).join('') || '<div class="text-muted">None yet.</div>'}
        <button class="btn btn-sm mt-1" data-permission="pm.jobs.manage" onclick="showResponse(${id})">+ Record response</button></div>
      <div class="col-md-4"><h4>Samples</h4>${(s.samples || []).map(x => `<div class="mb-2 d-flex gap-2 align-items-center"><span class="flex-fill">${esc(x.description)}${x.manufacturer ? ' · ' + esc(x.manufacturer) : ''}</span>
        <select class="form-select form-select-sm w-auto" data-permission="pm.jobs.manage" onchange="setSampleStatus(${id}, ${x.id}, this.value)">${Object.entries(SM_STATUS).map(([k, v]) => `<option value="${k}" ${k === x.status ? 'selected' : ''}>${v}</option>`).join('')}</select>
        <button class="btn btn-sm btn-ghost-danger" data-permission="pm.jobs.manage" onclick="delSample(${id}, ${x.id})">&times;</button></div>`).join('') || '<div class="text-muted">None.</div>'}
        <button class="btn btn-sm mt-1" data-permission="pm.jobs.manage" onclick="addSampleRow(${id})">+ Sample</button></div>
      <div class="col-md-4"><h4>Purchase orders</h4>${(s.purchase_orders || []).map(p => `<div class="mb-1"><a href="/pm/purchase-orders/${p.id}">${esc(p.po_number)}</a> <button class="btn btn-sm btn-ghost-danger" data-permission="pm.jobs.manage" onclick="unlinkPo(${id}, ${p.id})">&times;</button></div>`).join('') || '<div class="text-muted">None linked.</div>'}
        <div class="input-group input-group-sm mt-1" data-permission="pm.jobs.manage"><select class="form-select" id="linkPo-${id}"><option value="">Link a PO…</option>${jobPos.filter(p => !linked.has(p.id)).map(p => `<option value="${p.id}">${esc(p.po_number)}</option>`).join('')}</select><button class="btn" onclick="linkPo(${id})">Link</button></div></div></div>
      <h4 class="mt-3">Package</h4>
      <div class="row g-3"><div class="col-md-7">
        ${(s.items || []).map((it, k, arr) => `<div class="d-flex align-items-center gap-1 mb-1"><span class="text-muted me-1">${k + 1}.</span><span class="flex-fill">${esc(it.label)}</span>
          <button class="btn btn-sm btn-ghost-secondary" data-permission="pm.jobs.manage" ${k === 0 ? 'disabled' : ''} onclick="moveItem(${id}, ${k}, -1)">↑</button>
          <button class="btn btn-sm btn-ghost-secondary" data-permission="pm.jobs.manage" ${k === arr.length - 1 ? 'disabled' : ''} onclick="moveItem(${id}, ${k}, 1)">↓</button>
          <button class="btn btn-sm btn-ghost-danger" data-permission="pm.jobs.manage" onclick="removeItem(${id}, ${it.id})">&times;</button></div>`).join('') || '<div class="text-muted">No documents in this package yet.</div>'}
        <div data-permission="pm.jobs.manage" class="mt-2">
          <div class="input-group input-group-sm mb-1"><select class="form-select" id="libProd-${id}" onchange="loadLibDocs(${id})"><option value="">Library product…</option></select>
            <select class="form-select" id="libDoc-${id}"><option value="">Document…</option></select><button class="btn" onclick="addLibItem(${id})">Add</button></div>
          <div class="input-group input-group-sm"><input type="file" accept=".pdf" class="form-control" id="itemFile-${id}"><input class="form-control" id="itemLabel-${id}" placeholder="Label (optional)"><button class="btn" onclick="addFileItem(${id})">Upload PDF</button></div>
        </div></div>
        <div class="col-md-5"><button class="btn btn-primary btn-sm mb-2" data-permission="pm.jobs.manage" onclick="buildPackage(${id})">Build package PDF</button>
          ${(s.packages || []).map(pk => `<div><a href="${location.origin}/api/v1/pm/files/${pk.pm_file_id}/download" target="_blank">${esc(pk.file.original_name)}</a> <span class="text-muted small">${pk.item_count} docs · ${esc((pk.created_at || '').slice(0, 10))}</span></div>`).join('') || '<div class="text-muted">Not built yet.</div>'}</div></div>
      <div class="mt-3 text-end"><button class="btn btn-sm btn-ghost-danger" data-permission="pm.jobs.manage" onclick="deleteSm(${id})">Delete submittal</button></div>`;
    fillLibProducts(id);
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  const smCall = async (path, body, method = 'POST') => { try { await post(smBase() + path, body, method); } catch (e) { notice(e.message || 'Could not save', 'danger'); return false; } return true; };
  let libProducts = null;
  async function fillLibProducts(id) {
    if (!libProducts) { try { libProducts = await authenticatedFetch('/pm/library/products'); } catch (e) { libProducts = []; } }
    const sel = document.getElementById('libProd-' + id);
    if (sel) sel.innerHTML = '<option value="">Library product…</option>' + libProducts.filter(p => p.documents_count > 0).map(p => `<option value="${p.id}">${esc(p.manufacturer ? p.manufacturer.name + ' — ' : '')}${esc(p.name)}</option>`).join('');
  }
  async function loadLibDocs(id) {
    const pid = document.getElementById('libProd-' + id).value, sel = document.getElementById('libDoc-' + id);
    if (!pid) { sel.innerHTML = '<option value="">Document…</option>'; return; }
    const p = await authenticatedFetch('/pm/library/products/' + pid);
    sel.innerHTML = p.documents.map(d => `<option value="${d.id}">${esc(d.kind)} — ${esc(d.label || d.file.original_name)}</option>`).join('');
  }
  async function addLibItem(id) { const v = document.getElementById('libDoc-' + id).value; if (v && await smCall(`/${id}/items`, { product_document_id: +v })) toggleSubmittal(id, true); }
  async function addFileItem(id) {
    const f = document.getElementById('itemFile-' + id).files[0]; if (!f) { notice('Choose a PDF first.', 'warning'); return; }
    const fd = new FormData(); fd.append('file', f); const l = document.getElementById('itemLabel-' + id).value; if (l) fd.append('label', l);
    const r = await authenticatedUpload(`${smBase()}/${id}/items`, fd);
    if (!r.ok) { const d = await r.json().catch(() => ({})); notice(d.message || 'Could not add the PDF', 'danger'); return; }
    toggleSubmittal(id, true);
  }
  async function removeItem(id, itemId) { try { await authenticatedFetch(`${smBase()}/${id}/items/${itemId}`, { method: 'DELETE' }); toggleSubmittal(id, true); } catch (e) { notice(e.message, 'danger'); } }
  async function moveItem(id, idx, dir) {
    const s = await authenticatedFetch(`${smBase()}/${id}`), ids = s.items.map(i => i.id), j = idx + dir;
    [ids[idx], ids[j]] = [ids[j], ids[idx]];
    if (await smCall(`/${id}/items/reorder`, { order: ids })) toggleSubmittal(id, true);
  }
  async function buildPackage(id) {
    notice('Building the package…', 'info');
    if (await smCall(`/${id}/package`)) { notice('Package built.', 'success'); toggleSubmittal(id, true); }
  }
  async function setSmStatus(id, status) { if (await smCall(`/${id}`, { status }, 'PUT')) loadSubmittals(); }
  async function reviseSm(id) { if (confirm('Record this round and start the next revision?') && await smCall(`/${id}/revise`)) loadSubmittals(); }
  async function deleteSm(id) { if (confirm('Delete this submittal and its history?')) { try { await authenticatedFetch(`${smBase()}/${id}`, { method: 'DELETE' }); smOpen = null; loadSubmittals(); } catch (e) { notice(e.message, 'danger'); } } }
  async function setSampleStatus(id, sid, status) { if (await smCall(`/${id}/samples/${sid}`, { status }, 'PUT')) loadSubmittals(); }
  async function delSample(id, sid) { try { await authenticatedFetch(`${smBase()}/${id}/samples/${sid}`, { method: 'DELETE' }); loadSubmittals(); } catch (e) { notice(e.message, 'danger'); } }
  async function addSampleRow(id) { const d = prompt('Sample description:'); if (d && await smCall(`/${id}/samples`, { description: d })) loadSubmittals(); }
  async function linkPo(id) { const v = document.getElementById('linkPo-' + id).value; if (v && await smCall(`/${id}/purchase-orders`, { purchase_order_id: +v })) loadSubmittals(); }
  async function unlinkPo(id, poId) { try { await authenticatedFetch(`${smBase()}/${id}/purchase-orders/${poId}`, { method: 'DELETE' }); loadSubmittals(); } catch (e) { notice(e.message, 'danger'); } }

  function showSubmittalForm() { document.querySelector('#smModal form').reset(); safeShowModal('smModal'); }
  async function saveSubmittal(e) {
    e.preventDefault();
    if (await smCall('', { name: smName.value, spec_section: smSpec.value || null, kind: smKind.value })) { safeHideModal('smModal'); loadSubmittals(); }
  }
  function showResponse(id) {
    smRespFor = id; document.querySelector('#smRespModal form').reset();
    rStatus.innerHTML = Object.entries(SM_STATUS).filter(([k]) => k !== 'pending').map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
    rReturned.value = new Date().toISOString().slice(0, 10); safeShowModal('smRespModal');
  }
  async function saveResponse(e) {
    e.preventDefault();
    const fd = new FormData(); fd.append('status', rStatus.value); fd.append('returned_at', rReturned.value);
    if (rBy.value) fd.append('responded_by', rBy.value); if (rFile.files[0]) fd.append('file', rFile.files[0]);
    const r = await authenticatedUpload(`${smBase()}/${smRespFor}/responses`, fd);
    if (!r.ok) { const d = await r.json().catch(() => ({})); notice(d.message || 'Could not save the response', 'danger'); return; }
    safeHideModal('smRespModal'); loadSubmittals();
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
      await Promise.all([loadSheets(sheet), loadForecast()]); notice(msg, 'success');
    } catch (e) { notice(e.message || 'Import failed', 'danger'); }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('sheetPick').addEventListener('change', (ev) => showSheet(ev.target.value));
    try {
      [costCodes] = await Promise.all([authenticatedFetch('/pm/cost-codes'), loadJob()]);
      await Promise.all([loadSheets(), loadForecast(), loadSubmittals(), loadWo(), loadDrawings()]);
    } catch (e) { console.error(e); notice(e.message || 'Could not load this job', 'danger'); }
  });
</script>
@endsection
