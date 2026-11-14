@extends('layouts.app')

@section('title', 'Job Purchase Order - Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle"><a href="/pm/purchase-orders">Job Purchase Orders</a></div>
          <h1 class="page-title" id="poTitle">New purchase order</h1>
        </div>
        <div class="col-auto ms-auto" id="poBadges"></div>
      </div>
    </div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div id="poNotice" class="mb-3"></div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Order</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label required">Job</label><select class="form-select" id="fJob"></select></div>
            <div class="col-md-4"><label class="form-label required">Vendor</label><select class="form-select" id="fVendor"></select></div>
            <div class="col-md-4"><label class="form-label">Cost code (PO default)</label><select class="form-select" id="fCode"></select></div>
            <div class="col-md-3"><label class="form-label required">Order date</label><input type="date" class="form-control" id="fDate"></div>
            <div class="col-md-3"><label class="form-label">Expected date</label><input type="date" class="form-control" id="fExpected"></div>
            <div class="col-md-3" id="stageNewBox"><label class="form-label">Issue stage</label><select class="form-select" id="fStageNew"></select></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" id="fNotes" rows="2"></textarea></div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Lines</h3>
          <div class="card-actions btn-list" id="lineActions">
            <button class="btn btn-sm" onclick="addLine()">+ Line</button>
            <button class="btn btn-sm" onclick="addGroup()">+ Group</button>
            <button class="btn btn-sm" onclick="openEstimatePicker()">Add from estimate</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Description</th><th style="width:90px">Qty</th><th style="width:120px">Unit cost</th><th style="width:170px">Cost code</th><th class="text-end" style="width:120px">Total</th><th class="w-1"></th></tr></thead>
            <tbody id="linesBody"></tbody>
            <tfoot><tr class="fw-bold"><td colspan="4" class="text-end">Total</td><td class="text-end" id="poTotal">$0.00</td><td></td></tr></tfoot>
          </table>
        </div>
        <div class="card-footer d-flex gap-2 flex-wrap" id="poButtons"></div>
      </div>

      <div class="card mb-3" id="receiveCard" style="display:none">
        <div class="card-header"><h3 class="card-title">Receiving</h3></div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Line</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th style="width:110px">Receive now</th></tr></thead>
            <tbody id="receiveBody"></tbody>
          </table>
        </div>
        <div class="card-footer d-flex align-items-center gap-2">
          <input type="date" class="form-control w-auto" id="rDate">
          <button class="btn btn-primary" onclick="recordReceipt()" data-permission="orders.receive">Record receipt</button>
          <span class="text-muted small">Records that material arrived. It does not change stock.</span>
        </div>
      </div>

      <div class="card mb-3" id="historyCard" style="display:none">
        <div class="card-header"><h3 class="card-title">Revisions and receipts</h3></div>
        <div class="card-body" id="historyBody"></div>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="estModal" tabindex="-1">
  <div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add lines from the estimate</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2 mb-3">
        <div class="col-md-4"><select class="form-select" id="estSheet"></select></div>
        <div class="col-md-5"><select class="form-select" id="estCode"></select></div>
      </div>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th></th><th>Section</th><th>Supplier tag</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit</th><th class="text-end">Total</th></tr></thead><tbody id="estBody"></tbody></table></div>
      <div class="text-muted small">Quantities are whole numbers. A line with a fractional estimate quantity (for example square feet) is added as 1 at its total, with the estimate quantity noted in the description.</div>
    </div>
    <div class="modal-footer"><button class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="addEstimateLines()">Add selected</button></div>
  </div></div>
</div>

<script>
  const PO_ID = @json($poId);
  const IS_NEW = PO_ID === 'new';
  const STAGES = { draft: 'Draft', 'submittal-request': 'Submittal request', 'material-release': 'Material release', 'subcontract-submittal-request': 'Subcontract submittal request', 'subcontract-material-release': 'Subcontract material release' };
  const money = (n) => '$' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const esc = (s) => escapeHtml(String(s ?? ''));
  const post = (url, body, method = 'POST') => authenticatedFetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) });

  let po = null, lines = [], costCodes = [], locked = false, estLines = [];

  const blankLine = () => ({ description: '', kind: null, quantity: 1, unit_cost: 0, cost_code: '', notes: '', children: [] });
  function addLine() { lines.push(blankLine()); renderLines(); }
  function addGroup() { const g = blankLine(); g.kind = 'group'; g.children = [blankLine()]; lines.push(g); renderLines(); }
  function addChild(i) { lines[i].children.push(blankLine()); renderLines(); }
  function removeLine(i, j) { if (j == null) lines.splice(i, 1); else lines[i].children.splice(j, 1); renderLines(); }
  const lineTotal = (l) => (l.children && l.children.length) ? l.children.reduce((s, c) => s + lineTotal(c), 0) : (Number(l.quantity) || 0) * (Number(l.unit_cost) || 0);

  function codeOptions(selected) {
    return '<option value="">PO default</option>' + costCodes.map(c => `<option value="${c.code}" ${String(selected) === String(c.code) ? 'selected' : ''}>${c.code} — ${esc(c.description)}</option>`).join('');
  }

  function rowHtml(l, i, j) {
    const isChild = j != null, group = !isChild && l.children && l.children.length;
    const ref = isChild ? `lines[${i}].children[${j}]` : `lines[${i}]`;
    const dis = locked ? 'disabled' : '';
    return `<tr>
      <td class="${isChild ? 'ps-5' : ''}"><input class="form-control form-control-sm" ${dis} value="${esc(l.description)}" oninput="${ref}.description=this.value" placeholder="${group ? 'Group heading' : 'Description'}"></td>
      <td>${group ? '' : `<input type="number" min="0" step="1" class="form-control form-control-sm" ${dis} value="${l.quantity}" oninput="${ref}.quantity=this.value;updateTotals()">`}</td>
      <td>${group ? '' : `<input type="number" min="0" step="0.01" class="form-control form-control-sm" ${dis} value="${l.unit_cost}" oninput="${ref}.unit_cost=this.value;updateTotals()">`}</td>
      <td><select class="form-select form-select-sm" ${dis} onchange="${ref}.cost_code=this.value">${codeOptions(l.cost_code)}</select></td>
      <td class="text-end" data-total="${i}-${j == null ? '' : j}">${money(lineTotal(l))}</td>
      <td class="text-nowrap">${locked ? '' : `${group ? `<button class="btn btn-sm btn-ghost-primary" onclick="addChild(${i})">+ sub</button>` : ''}<button class="btn btn-sm btn-ghost-danger" onclick="removeLine(${i}, ${j == null ? 'null' : j})">&times;</button>`}</td></tr>`;
  }

  function renderLines() {
    document.getElementById('linesBody').innerHTML = lines.map((l, i) => rowHtml(l, i, null) + (l.children || []).map((c, j) => rowHtml(c, i, j)).join('')).join('')
      || '<tr><td colspan="6" class="text-muted">No lines yet.</td></tr>';
    updateTotals();
  }
  function updateTotals() {
    document.getElementById('poTotal').textContent = money(lines.reduce((s, l) => s + lineTotal(l), 0));
    lines.forEach((l, i) => {
      const cell = document.querySelector(`[data-total="${i}-"]`); if (cell) cell.textContent = money(lineTotal(l));
      (l.children || []).forEach((c, j) => { const cc = document.querySelector(`[data-total="${i}-${j}"]`); if (cc) cc.textContent = money(lineTotal(c)); });
    });
  }

  const notice = (msg, type = 'info') => { document.getElementById('poNotice').innerHTML = msg ? `<div class="alert alert-${type} mb-0">${esc(msg)}</div>` : ''; };
  const apiErr = (e) => e.message || 'Something went wrong';

  function payload() {
    const clean = (l) => ({ description: l.description, kind: l.kind || null, quantity: parseInt(l.quantity) || 0, unit_cost: Number(l.unit_cost) || 0, cost_code: l.cost_code || null, notes: l.notes || null });
    return {
      supplier_id: +fVendor.value, business_job_id: +fJob.value, order_date: fDate.value, expected_date: fExpected.value || null,
      cost_code: fCode.value || null, notes: fNotes.value || null,
      lines: lines.map(l => (l.children && l.children.length) ? { ...clean(l), children: l.children.map(clean) } : clean(l)),
    };
  }

  async function save() {
    try {
      if (IS_NEW) {
        const body = payload(); body.issue_stage = fStageNew.value || 'draft';
        const d = await post('/job-purchase-orders', body);
        location.href = '/pm/purchase-orders/' + d.purchase_order.id;
      } else {
        po = await post(`/job-purchase-orders/${PO_ID}`, payload(), 'PUT');
        fill(po); notice('Saved.', 'success');
      }
    } catch (e) { notice(apiErr(e), 'danger'); }
  }

  async function act(path, body, okMsg) {
    try { po = await post(`/job-purchase-orders/${PO_ID}/${path}`, body); fill(po); notice(okMsg, 'success'); }
    catch (e) { notice(apiErr(e), 'danger'); }
  }
  function setStage() { act('stage', { issue_stage: document.getElementById('stageSel').value }, 'Stage updated.'); }
  function revise() { if (confirm('Save the current lines as a revision and start the next one?')) act('revise', {}, 'Revision recorded.'); }
  function closePo() { act('close', {}, 'Closed.'); }
  function reopenPo() { act('reopen', {}, 'Reopened.'); }
  function voidPo() { const r = prompt('Reason for voiding this PO (required):'); if (r) act('void', { reason: r }, 'Voided.'); }

  async function recordReceipt() {
    const receipts = [...document.querySelectorAll('[data-rcv]')].map(el => ({ item_id: +el.dataset.rcv, quantity: parseInt(el.value) || 0 })).filter(r => r.quantity > 0);
    if (!receipts.length) { notice('Enter a quantity to receive.', 'warning'); return; }
    await act('receive', { receipts, received_date: document.getElementById('rDate').value || null }, 'Receipt recorded.');
  }

  function leaves(p) { return p.lines.flatMap(l => (l.children && l.children.length) ? l.children.map(c => ({ ...c, group: l.description })) : [l]); }

  function fill(p) {
    po = p;
    locked = !!(p.voided_at || p.closed_at);
    document.getElementById('poTitle').textContent = `PO ${p.po_number}${p.revision_number ? ' — Rev ' + p.revision_number : ''}`;
    document.getElementById('poBadges').innerHTML = `<span class="badge bg-blue-lt me-1">${esc(STAGES[p.issue_stage] || p.issue_stage)}</span><span class="badge bg-secondary-lt">${esc(p.status)}</span>${p.closed_at ? ' <span class="badge bg-secondary">Closed</span>' : ''}${p.voided_at ? ' <span class="badge bg-red">Voided</span>' : ''}`;
    fJob.value = p.business_job_id; fVendor.value = p.supplier_id; fCode.value = p.cost_code || '';
    fDate.value = (p.order_date || '').slice(0, 10); fExpected.value = (p.expected_date || '').slice(0, 10); fNotes.value = p.notes || '';
    document.getElementById('stageNewBox').style.display = 'none';
    [fJob, fVendor, fCode, fDate, fExpected, fNotes].forEach(el => el.disabled = locked);
    lines = p.lines.map(l => ({ description: l.description || '', kind: l.kind, quantity: l.quantity_ordered, unit_cost: l.unit_cost, cost_code: l.cost_code || '', notes: l.notes || '',
      children: (l.children || []).map(c => ({ description: c.description || '', kind: c.kind, quantity: c.quantity_ordered, unit_cost: c.unit_cost, cost_code: c.cost_code || '', notes: c.notes || '', children: [] })) }));
    document.getElementById('lineActions').style.display = locked ? 'none' : '';
    renderLines();

    const b = [];
    if (!locked) {
      b.push(`<button class="btn btn-primary" onclick="save()" data-permission="orders.edit">Save</button>`);
      b.push(`<select class="form-select w-auto" id="stageSel">${Object.entries(STAGES).map(([k, v]) => `<option value="${k}" ${k === p.issue_stage ? 'selected' : ''}>${v}</option>`).join('')}</select><button class="btn" onclick="setStage()" data-permission="orders.edit">Set stage</button>`);
      b.push(`<button class="btn" onclick="revise()" data-permission="orders.edit">New revision</button>`);
      b.push(`<button class="btn" onclick="closePo()" data-permission="orders.edit">Close</button>`);
    } else if (p.closed_at && !p.voided_at) b.push(`<button class="btn" onclick="reopenPo()" data-permission="orders.edit">Reopen</button>`);
    if (!p.voided_at) b.push(`<button class="btn btn-outline-danger ms-auto" onclick="voidPo()" data-permission="orders.edit">Void</button>`);
    document.getElementById('poButtons').innerHTML = b.join('');
    if (p.void_reason) notice('Voided: ' + p.void_reason, 'danger');

    const canReceive = p.issue_stage !== 'draft' && !locked;
    document.getElementById('receiveCard').style.display = canReceive ? '' : 'none';
    document.getElementById('receiveBody').innerHTML = leaves(p).map(l => `<tr><td>${l.group ? esc(l.group) + ' › ' : ''}${esc(l.description)}</td><td class="text-end">${l.quantity_ordered}</td><td class="text-end">${l.quantity_received}</td><td><input type="number" min="0" step="1" class="form-control form-control-sm" data-rcv="${l.id}"></td></tr>`).join('');

    const hist = [];
    (p.revisions || []).forEach(r => hist.push(`<div>Rev ${r.revision_number} — ${money(r.total_amount)} · ${r.lines.length} lines · ${esc((r.created_at || '').slice(0, 10))}</div>`));
    (p.receipts || []).forEach(r => hist.push(`<div>Received ${r.quantity} on ${esc((r.received_date || '').slice(0, 10))}${r.flagged ? ' <span class="badge bg-orange-lt">Flagged (over order)</span>' : ''}${r.notes ? ' — ' + esc(r.notes) : ''}</div>`));
    document.getElementById('historyCard').style.display = hist.length ? '' : 'none';
    document.getElementById('historyBody').innerHTML = hist.join('');
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  // ---- add lines from the estimate ----
  async function openEstimatePicker() {
    if (!fJob.value) { notice('Choose the job first.', 'warning'); return; }
    const sheets = await authenticatedFetch(`/pm/jobs/${fJob.value}/estimates`);
    if (!sheets.length) { notice('This job has no imported estimate yet. Import one on the job page.', 'warning'); return; }
    estSheet.innerHTML = sheets.map(s => `<option>${esc(s.sheet)}</option>`).join('');
    await loadEstimateSheet();
    safeShowModal('estModal');
  }
  async function loadEstimateSheet() {
    const e = await authenticatedFetch(`/pm/jobs/${fJob.value}/estimates/${encodeURIComponent(estSheet.value)}`);
    estLines = e.lines;
    const codes = Object.entries(e.byCostCode);
    estCode.innerHTML = codes.map(([c, a]) => `<option value="${c}">${c} — ${esc(a.description)} (${money(a.total)})</option>`).join('');
    if (fCode.value && e.byCostCode[fCode.value]) estCode.value = fCode.value;
    renderEstimateLines();
  }
  function renderEstimateLines() {
    const rows = estLines.map((l, idx) => ({ l, idx })).filter(x => String(x.l.costCode) === estCode.value);
    estBody.innerHTML = rows.map(({ l, idx }) => `<tr><td><input type="checkbox" class="form-check-input" data-est="${idx}" checked></td><td>${esc(l.section)}</td><td>${esc(l.who)}</td><td>${esc(l.description)}</td><td class="text-end">${l.qty ?? ''}</td><td class="text-end">${money(l.unitPrice)}</td><td class="text-end">${money(l.total)}</td></tr>`).join('');
  }
  function addEstimateLines() {
    [...document.querySelectorAll('[data-est]:checked')].forEach(cb => {
      const l = estLines[+cb.dataset.est], whole = Number.isInteger(Number(l.qty)) && Number(l.qty) > 0;
      lines.push({ description: whole ? l.description : `${l.description} (est. ${l.qty} @ ${money(l.unitPrice)})`, kind: null,
        quantity: whole ? l.qty : 1, unit_cost: whole ? l.unitPrice : l.total, cost_code: String(l.costCode), notes: '', children: [] });
    });
    if (!fCode.value) fCode.value = estCode.value;
    renderLines(); safeHideModal('estModal');
  }

  document.addEventListener('DOMContentLoaded', async () => {
    try {
      const [jobs, vendors, codes] = await Promise.all([authenticatedFetch('/pm/jobs'), authenticatedFetch('/pm/vendors'), authenticatedFetch('/pm/cost-codes')]);
      costCodes = codes;
      fJob.innerHTML = '<option value="">Choose…</option>' + jobs.map(j => `<option value="${j.id}">${esc(j.job_number)} — ${esc(j.job_name)}</option>`).join('');
      fVendor.innerHTML = '<option value="">Choose…</option>' + vendors.map(v => `<option value="${v.id}">${esc(v.name)}</option>`).join('');
      fCode.innerHTML = codeOptions('').replace('PO default', 'None');
      fStageNew.innerHTML = Object.entries(STAGES).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
      fDate.value = new Date().toISOString().slice(0, 10);
      estSheet.addEventListener('change', loadEstimateSheet); estCode.addEventListener('change', renderEstimateLines);
      rDate.value = new Date().toISOString().slice(0, 10);
      if (IS_NEW) {
        const pre = new URLSearchParams(location.search).get('job'); if (pre) fJob.value = pre;
        lines = [blankLine()]; renderLines();
        document.getElementById('poButtons').innerHTML = '<button class="btn btn-primary" onclick="save()" data-permission="orders.create">Create PO</button>';
      } else {
        fill((await authenticatedFetch(`/job-purchase-orders/${PO_ID}`)));
      }
      if (typeof applyActionPermissions === 'function') applyActionPermissions();
    } catch (e) { console.error(e); notice(apiErr(e), 'danger'); }
  });
</script>
@endsection
