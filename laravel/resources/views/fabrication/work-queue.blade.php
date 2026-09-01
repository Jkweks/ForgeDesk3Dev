@extends('layouts.app')

@section('title', 'Work Queue – Fabrication')

@section('styles')
.wq-board { display:flex; gap:.75rem; overflow-x:auto; padding-bottom:1rem; align-items:flex-start; }
.wq-col { min-width:280px; max-width:320px; flex:0 0 auto; background:var(--tblr-bg-surface-secondary, var(--tblr-light)); border-radius:8px; padding:.5rem; }
.wq-col.drop-hover { outline:2px dashed var(--tblr-primary, #206bc4); outline-offset:-2px; }
.wq-col-head { display:flex; align-items:center; gap:.4rem; font-weight:600; padding:.25rem .35rem .5rem; }
.wq-card { background:var(--tblr-bg-surface); border:1px solid var(--tblr-border-color, #dbe0e5); border-radius:6px; padding:.5rem .6rem; margin-bottom:.4rem; cursor:grab; font-size:.82rem; }
.wq-card:active { cursor:grabbing; }
.wq-card.dragging { opacity:.4; }
.wq-card.locked { opacity:.6; border-style:dashed; }
.wq-card.locked .wq-lock { color:var(--tblr-danger, #d63939); }
.wq-meta { display:flex; flex-wrap:wrap; gap:.35rem .5rem; align-items:center; margin-top:.3rem; }
.wq-inline { border:none; background:transparent; font:inherit; color:inherit; padding:0 .1rem; max-width:8.5rem; }
.wq-inline:focus { outline:1px solid var(--tblr-primary, #206bc4); border-radius:3px; }
@endsection

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Fabrication</div>
          <h2 class="page-title">Work Queue</h2>
        </div>
        <div class="col-auto ms-auto">
          <button class="btn btn-outline-secondary" onclick="loadBoard()">
            <i class="ti ti-refresh me-1"></i>Refresh
          </button>
        </div>
      </div>
      <div class="text-muted small mt-1">
        Every stage grouped by operator. Drag a card to reassign it — including
        <span class="text-danger">locked</span> stages (still waiting on an earlier step), so the
        workflow can be planned ahead.
      </div>
      <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
        <select id="wq-job" class="form-select form-select-sm" style="width:auto" onchange="wqOnJobChange()">
          <option value="">All jobs</option>
        </select>
        <select id="wq-wo" class="form-select form-select-sm" style="width:auto" onchange="loadBoard()">
          <option value="">All work orders</option>
        </select>
        <label class="form-check form-switch mb-0 ms-2">
          <input class="form-check-input" type="checkbox" id="wq-hide-locked" onchange="renderBoard()">
          <span class="form-check-label small">Hide locked</span>
        </label>
        <button class="btn btn-sm btn-outline-primary ms-2" onclick="wqOpenBulk()">
          <i class="ti ti-users-group me-1"></i>Bulk assign steps
        </button>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div id="wq-loading" class="text-center text-muted py-5">
        <div class="spinner-border" role="status"></div>
      </div>
      <div id="wq-board" class="wq-board" style="display:none"></div>
    </div>
  </div>
</div>

<div id="wq-bulk-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:1050; align-items:center; justify-content:center;">
  <div class="card" style="width:min(640px, 92vw); max-height:85vh; display:flex; flex-direction:column;">
    <div class="card-header">
      <h3 class="card-title">Bulk assign steps</h3>
      <button type="button" class="btn-close ms-auto" onclick="wqCloseBulk()"></button>
    </div>
    <div class="card-body" style="overflow-y:auto;">
      <div class="mb-3">
        <label class="form-label">Work order</label>
        <select id="wq-bulk-wo" class="form-select" onchange="wqBulkRenderRows()"></select>
        <div class="form-hint">
          Tick a step to change it. Every non-complete step with that name — across all elevations
          of this work order — is set to the operator(s) you pick. Select more than one to have
          them share the step (it shows in each queue; whoever completes it clears it for all).
        </div>
      </div>
      <div id="wq-bulk-rows"></div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <div id="wq-bulk-status" class="text-muted small"></div>
      <div>
        <button class="btn btn-link" onclick="wqCloseBulk()">Cancel</button>
        <button class="btn btn-primary" onclick="wqApplyBulk()">Apply</button>
      </div>
    </div>
  </div>
</div>

<div id="wq-assignee-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:1050; align-items:center; justify-content:center;">
  <div class="card" style="width:min(420px, 92vw); max-height:80vh; display:flex; flex-direction:column;">
    <div class="card-header">
      <h3 class="card-title">Assignees — <span id="wq-as-name"></span></h3>
      <button type="button" class="btn-close ms-auto" onclick="wqCloseAssignees()"></button>
    </div>
    <div class="card-body" style="overflow-y:auto;">
      <div id="wq-as-list"></div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <div id="wq-as-status" class="text-muted small"></div>
      <div>
        <button class="btn btn-link" onclick="wqCloseAssignees()">Cancel</button>
        <button class="btn btn-primary" onclick="wqSaveAssignees()">Save</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
const API = (p, o = {}) => fetch('/api/v1' + p, {
  ...o,
  credentials: 'include',
  headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', ...(o.headers || {}) },
});

let wqData = null;
let wqDragStageId = null;
let wqJobs = [];
let wqWOs = [];

function esc(s) { if (s == null) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
function isPast(d) { return d && new Date(d) < new Date(new Date().toDateString()); }

// ── Filters (job / work order) ──────────────────────────────────────────
async function wqLoadFilters() {
  try {
    const [jr, wr] = await Promise.all([
      API('/business-jobs?per_page=500'),
      API('/work-orders'),
    ]);
    wqJobs = (await jr.json()).jobs || [];
    wqWOs  = (await wr.json()).work_orders || [];
  } catch (e) { console.error(e); }

  const jobSel = document.getElementById('wq-job');
  jobSel.innerHTML = '<option value="">All jobs</option>' +
    wqJobs.map(j => `<option value="${j.id}">${esc(j.job_number)} – ${esc(j.job_name)}</option>`).join('');
  wqFillWorkOrderOptions();
}

function wqFillWorkOrderOptions() {
  const jobId = document.getElementById('wq-job').value;
  const woSel = document.getElementById('wq-wo');
  const list = jobId ? wqWOs.filter(w => String(w.business_job_id) === jobId) : wqWOs;
  woSel.innerHTML = '<option value="">All work orders</option>' +
    list.map(w => `<option value="${w.id}">${esc(w.release_label)}${w.job?.job_name ? ' – ' + esc(w.job.job_name) : ''}</option>`).join('');
}

function wqOnJobChange() {
  wqFillWorkOrderOptions();
  loadBoard();
}

function wqQueryString() {
  const woId = document.getElementById('wq-wo').value;
  const jobId = document.getElementById('wq-job').value;
  const params = new URLSearchParams();
  if (woId) params.set('work_order_id', woId);
  else if (jobId) params.set('job_id', jobId);
  const q = params.toString();
  return q ? '?' + q : '';
}

async function loadBoard(fromPoll) {
  // Don't yank focus from an inline edit / mid-drag on the auto-refresh.
  if (fromPoll === true) {
    const board = document.getElementById('wq-board');
    if (wqDragStageId != null || (document.activeElement && board.contains(document.activeElement))) return;
  }
  try {
    const r = await API('/work-queue' + wqQueryString());
    wqData = await r.json();
    renderBoard();
  } catch (e) { console.error(e); }
}

function cardHtml(c) {
  const prio = `#<input type="number" min="1" class="wq-inline" style="width:2.8rem" value="${c.priority ?? ''}"
      title="Priority — editing pins this work order" onclick="event.stopPropagation()"
      onchange="wqSetPriority(${c.work_order_id}, this.value)">`;
  const due = `<input type="date" class="wq-inline ${isPast(c.due_date) ? 'text-danger' : 'text-muted'}"
      value="${c.due_date || ''}" title="Due date" onclick="event.stopPropagation()"
      onchange="wqSetDue(${c.work_order_id}, this.value)">`;
  const active = c.status === 'in_progress' ? ' <span class="badge bg-warning-lt text-warning">active</span>' : '';
  const lock = c.gated
    ? ` <i class="ti ti-lock wq-lock" title="Blocked by &quot;${esc(c.blocking_stage_name || 'an earlier step')}&quot;"></i>`
    : '';
  const names = c.assignee_names || [];
  const shared = names.length > 1
    ? `<span class="badge bg-purple-lt text-purple" title="Shared with ${esc(names.join(', '))}">
         <i class="ti ti-users" style="font-size:.75rem"></i> ${names.length}
       </span>` : '';
  return `<div class="wq-card${c.gated ? ' locked' : ''}" draggable="true" data-stage="${c.stage_id}"
      ondragstart="wqDragStart(event, ${c.stage_id})" ondragend="wqDragEnd(event)">
    <div class="d-flex align-items-start gap-1">
      <div class="flex-fill"><strong>${esc(c.name)}</strong>${active}${lock} ${shared}</div>
      <button class="btn btn-sm btn-ghost-secondary p-0 px-1" title="Edit assignees"
          onclick="event.stopPropagation(); wqEditAssignees(${c.stage_id})"><i class="ti ti-user-plus"></i></button>
    </div>
    <div class="text-muted">${esc(c.release_label)} · ${esc(c.elevation_tag)}${c.job_name ? ' · ' + esc(c.job_name) : ''}</div>
    <div class="wq-meta">
      <span class="text-muted">${prio}</span>
      ${due}
      ${c.date_requested ? `<span class="text-muted">need ${esc(c.date_requested)}</span>` : ''}
    </div>
  </div>`;
}

function colHtml(opId, title, oldest, stages, initials, muted) {
  const hideLocked = document.getElementById('wq-hide-locked')?.checked;
  const shown = hideLocked ? stages.filter(s => !s.gated) : stages;
  const ready = stages.filter(s => !s.gated).length;
  const countLabel = ready === stages.length ? `${stages.length}` : `${ready} ready · ${stages.length}`;
  return `<div class="wq-col" data-op="${opId}"
      ondragover="wqDragOver(event)" ondragleave="wqDragLeave(event)" ondrop="wqDrop(event, ${opId})">
    <div class="wq-col-head">
      ${initials ? `<span class="badge bg-blue-lt text-blue">${esc(initials)}</span>` : '<i class="ti ti-inbox text-muted"></i>'}
      <span class="${muted ? 'text-muted' : ''}">${esc(title)}</span>
      <span class="ms-auto text-muted small">${countLabel}${oldest ? ` · oldest ${esc(oldest)}` : ''}</span>
    </div>
    <div>${shown.map(cardHtml).join('') || '<div class="text-muted small text-center py-2">—</div>'}</div>
  </div>`;
}

function renderBoard() {
  document.getElementById('wq-loading').style.display = 'none';
  const board = document.getElementById('wq-board');
  board.style.display = 'flex';
  if (!wqData) { board.innerHTML = ''; return; }

  const opById = {};
  (wqData.operators || []).forEach(o => { opById[o.user.id] = o; });

  const cols = [
    colHtml(0, 'Unassigned', wqData.unassigned.oldest_date_requested, wqData.unassigned.stages || [], null),
  ];

  (wqData.fab_users || []).forEach(u => {
    const o = opById[u.id];
    cols.push(colHtml(u.id, u.name, o?.oldest_date_requested, o?.stages || [], u.initials));
  });

  // Operators with work who are no longer active fab users.
  (wqData.operators || []).forEach(o => {
    if (!(wqData.fab_users || []).some(u => u.id === o.user.id)) {
      cols.push(colHtml(o.user.id, o.user.name + ' (inactive)', o.oldest_date_requested, o.stages, o.user.initials, true));
    }
  });

  board.innerHTML = cols.join('');
}

// ── drag & drop (hand-rolled HTML5, matches the Reorder Queue pattern) ──
function wqDragStart(e, stageId) {
  wqDragStageId = stageId;
  e.currentTarget.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function wqDragEnd(e) { e.currentTarget.classList.remove('dragging'); }
function wqDragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; e.currentTarget.classList.add('drop-hover'); }
function wqDragLeave(e) { e.currentTarget.classList.remove('drop-hover'); }

async function wqDrop(e, opId) {
  e.preventDefault();
  e.currentTarget.classList.remove('drop-hover');
  if (wqDragStageId == null) return;
  const stageId = wqDragStageId;
  wqDragStageId = null;
  try {
    const r = await API(`/work-order-stages/${stageId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ assigned_to_id: opId || null, log_message: 'Reassigned via Work Queue' }),
    });
    if (!r.ok) throw new Error('assign failed');
    await loadBoard();
  } catch (err) {
    console.error(err);
    alert('Failed to reassign the stage.');
  }
}

async function wqSetDue(woId, val) {
  try {
    await API(`/work-orders/${woId}`, {
      method: 'PATCH', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ due_date: val || null }),
    });
    await loadBoard();
  } catch (e) { console.error(e); }
}

async function wqSetPriority(woId, val) {
  try {
    await API(`/work-orders/${woId}`, {
      method: 'PATCH', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ priority: val ? parseInt(val) : null }),
    });
    await loadBoard();
  } catch (e) { console.error(e); }
}

// ── Bulk assign (all steps of a given name, across a work order) ────────
function wqAllCards() {
  if (!wqData) return [];
  const cards = [...(wqData.unassigned?.stages || [])];
  (wqData.operators || []).forEach(o => cards.push(...(o.stages || [])));
  return cards;
}

function wqOpenBulk() {
  const cards = wqAllCards();
  const byWo = new Map();
  cards.forEach(c => { if (c.work_order_id && !byWo.has(c.work_order_id)) byWo.set(c.work_order_id, c.release_label); });

  const sel = document.getElementById('wq-bulk-wo');
  const currentWo = document.getElementById('wq-wo').value;
  sel.innerHTML = [...byWo.entries()]
    .map(([id, label]) => `<option value="${id}">${esc(label)}</option>`).join('');
  if (!sel.options.length) {
    document.getElementById('wq-bulk-rows').innerHTML = '<div class="text-muted">No work orders on the board to assign from.</div>';
  } else {
    sel.value = currentWo && byWo.has(parseInt(currentWo)) ? currentWo : sel.options[0].value;
  }
  document.getElementById('wq-bulk-status').textContent = '';
  document.getElementById('wq-bulk-overlay').style.display = 'flex';
  wqBulkRenderRows();
}

function wqCloseBulk() {
  document.getElementById('wq-bulk-overlay').style.display = 'none';
}

function wqEqSet(a, b) {
  const x = [...new Set((a || []).map(Number))].sort((m, n) => m - n);
  const y = [...new Set((b || []).map(Number))].sort((m, n) => m - n);
  return x.length === y.length && x.every((v, i) => v === y[i]);
}

function wqBulkRenderRows() {
  const woId = document.getElementById('wq-bulk-wo').value;
  const container = document.getElementById('wq-bulk-rows');
  if (!woId) { container.innerHTML = ''; return; }

  const cards = wqAllCards().filter(c => String(c.work_order_id) === String(woId));
  const byName = new Map();
  cards.forEach(c => {
    if (!byName.has(c.name)) byName.set(c.name, []);
    byName.get(c.name).push(c);
  });

  const users = wqData.fab_users || [];

  container.innerHTML = [...byName.entries()].map(([name, group]) => {
    // The group's current assignees, if every step in it shares the same set.
    const first = (group[0].assignee_ids || []).map(Number);
    const uniform = group.every(c => wqEqSet(c.assignee_ids, first)) ? first : [];
    const opts = users.map(u =>
      `<option value="${u.id}" ${uniform.includes(u.id) ? 'selected' : ''}>${esc(u.name)}</option>`).join('');
    const nm = esc(name).replace(/"/g, '&quot;');
    return `<div class="row align-items-start mb-2 pb-2 border-bottom" data-row>
      <div class="col-5">
        <label class="form-check mb-0">
          <input class="form-check-input" type="checkbox" data-row-toggle
                 onchange="this.closest('[data-row]').querySelector('select').disabled = !this.checked">
          <span class="form-check-label"><strong>${esc(name)}</strong>
            <span class="text-muted small">(${group.length})</span></span>
        </label>
      </div>
      <div class="col-7">
        <select multiple size="${Math.min(Math.max(users.length, 2), 5)}"
                class="form-select form-select-sm" data-stage-name="${nm}" disabled>${opts}</select>
        <div class="form-hint">Ctrl/Cmd-click for more than one. None selected = unassign.</div>
      </div>
    </div>`;
  }).join('') || '<div class="text-muted">No steps found on this work order.</div>';
}

async function wqApplyBulk() {
  const woId = document.getElementById('wq-bulk-wo').value;
  if (!woId) return;

  const assignments = [];
  document.querySelectorAll('#wq-bulk-rows [data-row]').forEach(row => {
    if (!row.querySelector('[data-row-toggle]')?.checked) return;
    const sel = row.querySelector('select[data-stage-name]');
    assignments.push({
      stage_name: sel.dataset.stageName,
      assigned_to_ids: [...sel.selectedOptions].map(o => parseInt(o.value)),
    });
  });

  const status = document.getElementById('wq-bulk-status');
  if (!assignments.length) {
    status.textContent = 'Tick at least one step to change.';
    return;
  }

  status.textContent = 'Applying…';
  try {
    const r = await API('/work-order-stages/bulk-assign', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ work_order_id: parseInt(woId), assignments }),
    });
    if (!r.ok) throw new Error('bulk assign failed');
    const data = await r.json();
    status.textContent = `Updated ${data.updated} step(s).`;
    await loadBoard();
    setTimeout(wqCloseBulk, 700);
  } catch (e) {
    console.error(e);
    status.textContent = 'Failed to apply bulk assignment.';
  }
}

// ── Per-stage assignee editor ─────────────────────────────────────────
let wqAsStageId = null;

function wqEditAssignees(stageId) {
  const card = wqAllCards().find(c => c.stage_id === stageId);
  if (!card) return;
  wqAsStageId = stageId;
  document.getElementById('wq-as-name').textContent = card.name;
  document.getElementById('wq-as-status').textContent = '';
  const current = (card.assignee_ids || []).map(Number);
  document.getElementById('wq-as-list').innerHTML = (wqData.fab_users || []).map(u => `
    <label class="form-check">
      <input class="form-check-input" type="checkbox" value="${u.id}" ${current.includes(u.id) ? 'checked' : ''}>
      <span class="form-check-label">${esc(u.name)}</span>
    </label>`).join('') || '<div class="text-muted">No fab users.</div>';
  document.getElementById('wq-assignee-overlay').style.display = 'flex';
}

function wqCloseAssignees() {
  document.getElementById('wq-assignee-overlay').style.display = 'none';
  wqAsStageId = null;
}

async function wqSaveAssignees() {
  if (wqAsStageId == null) return;
  const ids = [...document.querySelectorAll('#wq-as-list input:checked')].map(i => parseInt(i.value));
  const status = document.getElementById('wq-as-status');
  status.textContent = 'Saving…';
  try {
    const r = await API(`/work-order-stages/${wqAsStageId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ assigned_to_ids: ids, log_message: 'Assignees changed via Work Queue' }),
    });
    if (!r.ok) throw new Error('save failed');
    await loadBoard();
    wqCloseAssignees();
  } catch (e) {
    console.error(e);
    status.textContent = 'Failed to save assignees.';
  }
}

wqLoadFilters();
loadBoard();
setInterval(() => loadBoard(true), 30000);
</script>
@endpush
