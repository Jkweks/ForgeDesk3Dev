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
      await Promise.all([loadSheets(), loadForecast(), loadSubmittals()]);
    } catch (e) { console.error(e); notice(e.message || 'Could not load this job', 'danger'); }
  });
</script>
@endsection
