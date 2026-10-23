@extends('layouts.app')

@section('title', 'Work Queue – Fabrication')

@section('styles')
.wq-board { display:flex; gap:.75rem; overflow-x:auto; padding-bottom:1rem; align-items:flex-start; }
.wq-scroll-top { position:sticky; top:var(--fd-nav-offset); z-index:6; overflow-x:auto; overflow-y:hidden; background:var(--tblr-bg-surface); border-bottom:1px solid var(--tblr-border-color); }
.wq-scroll-top-inner { height:1px; }
.wq-col { min-width:280px; max-width:320px; flex:0 0 auto; background:var(--tblr-bg-surface-secondary); border-radius:var(--tblr-border-radius-lg); padding:.5rem; }
.wq-col.drop-hover { outline:2px dashed var(--tblr-primary); outline-offset:-2px; }
.wq-col-head { display:flex; align-items:center; gap:.4rem; font-weight:600; padding:.25rem .35rem .5rem; }
.wq-card { background:var(--tblr-bg-surface); border:1px solid var(--tblr-border-color); border-radius:var(--tblr-border-radius); padding:.5rem .6rem; margin-bottom:.4rem; cursor:grab; font-size:.85rem; }
.wq-card:active { cursor:grabbing; }
.wq-sub { font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; font-weight:600; color:var(--tblr-secondary-color); display:flex; align-items:center; gap:.4rem; margin:.5rem .25rem .3rem; }
.wq-sub:first-child { margin-top:.1rem; }
.wq-sub .ms-auto { font-weight:400; text-transform:none; letter-spacing:0; }
.wq-card.static { cursor:default; }
.wq-card.overdue { border-left:3px solid var(--tblr-danger); }
.wq-strip .wq-stat { display:flex; align-items:baseline; gap:6px; }
.wq-strip .n { font-size:1.4rem; font-weight:600; line-height:1; }
.wq-strip .l { font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:var(--tblr-secondary-color); }
.wq-card.dragging { opacity:.4; }
.wq-card.locked { opacity:.6; border-style:dashed; }
.wq-card.locked .wq-lock { color:var(--tblr-danger); }
.wq-meta { display:flex; flex-wrap:wrap; gap:.35rem .5rem; align-items:center; margin-top:.3rem; }
.wq-inline { border:none; background:transparent; font:inherit; color:inherit; padding:0 .1rem; max-width:8.5rem; }
.wq-inline:focus { outline:1px solid var(--tblr-primary); border-radius:var(--tblr-border-radius-sm); }
.wq-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1060; align-items:center; justify-content:center; }
.wq-overlay > .card { box-shadow:0 1rem 3rem rgba(0,0,0,.5); }
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
      <div class="text-secondary small mt-1">
        Every stage grouped by operator. Drag a card to reassign it — <span class="text-danger">locked</span> stages
        (waiting on an earlier step) can be moved too, so work can be planned ahead.
      </div>
      <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
        <div class="btn-group btn-group-sm" id="wq-group" title="How the board is grouped">
          <button type="button" class="btn active" data-group="operator" onclick="wqSetGroup('operator')"><i class="ti ti-user me-1"></i>By operator</button>
          <button type="button" class="btn" data-group="job" onclick="wqSetGroup('job')"><i class="ti ti-briefcase me-1"></i>By job</button>
          <button type="button" class="btn" data-group="step" onclick="wqSetGroup('step')"><i class="ti ti-list-details me-1"></i>By step</button>
        </div>
        <select id="wq-job" class="form-select form-select-sm" style="width:auto" onchange="wqOnJobChange()">
          <option value="">All jobs</option>
        </select>
        <select id="wq-wo" class="form-select form-select-sm" style="width:auto" onchange="loadBoard()">
          <option value="">All work orders</option>
        </select>
        <select id="wq-step" class="form-select form-select-sm" style="width:auto" onchange="renderBoard()">
          <option value="">All steps</option>
        </select>
        <div class="input-icon" style="min-width:180px">
          <span class="input-icon-addon"><i class="ti ti-search"></i></span>
          <input type="text" id="wq-search" class="form-control form-control-sm" placeholder="Search cards…" oninput="wqSearchDebounce()" autocomplete="off">
        </div>
        <label class="form-check form-switch mb-0 ms-1">
          <input class="form-check-input" type="checkbox" id="wq-overdue-only" onchange="renderBoard()">
          <span class="form-check-label small">Overdue only</span>
        </label>
        <label class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" id="wq-hide-locked" onchange="renderBoard()">
          <span class="form-check-label small">Hide locked</span>
        </label>
        <button class="btn btn-sm btn-outline-primary ms-auto" onclick="wqOpenBulk()">
          <i class="ti ti-users-group me-1"></i>Bulk assign steps
        </button>
        <span class="text-muted small" id="wq-updated"></span>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="card mb-3 wq-strip" id="wq-strip" style="display:none">
        <div class="card-body py-2 d-flex flex-wrap gap-4 align-items-center">
          <div class="wq-stat"><span class="n" id="wq-s-unassigned">-</span><span class="l">Unassigned</span></div>
          <div class="wq-stat"><span class="n text-success" id="wq-s-ready">-</span><span class="l">Ready</span></div>
          <div class="wq-stat"><span class="n text-secondary" id="wq-s-locked">-</span><span class="l">Locked</span></div>
          <div class="wq-stat"><span class="n text-danger" id="wq-s-overdue">-</span><span class="l">Overdue</span></div>
          <div class="wq-stat ms-auto"><span class="n" id="wq-s-ops">-</span><span class="l">Operators with work</span></div>
        </div>
      </div>
      <div id="wq-loading" class="text-center text-muted py-5">
        <div class="spinner-border" role="status"></div>
      </div>
      <div id="wq-scroll-top" class="wq-scroll-top" style="display:none">
        <div id="wq-scroll-top-inner" class="wq-scroll-top-inner"></div>
      </div>
      <div id="wq-board" class="wq-board" style="display:none"></div>
    </div>
  </div>
</div>

<div id="wq-bulk-overlay" class="wq-overlay" style="display:none;">
  <div class="card" style="width:min(640px, 92vw); max-height:85vh; display:flex; flex-direction:column;">
    <div class="card-header">
      <h3 class="card-title">Bulk assign steps</h3>
      <button type="button" class="btn-close ms-auto" onclick="wqCloseBulk()"></button>
    </div>
    <div class="card-body" style="overflow-y:auto;">
      <div class="mb-3">
        <label class="form-label">Work order</label>
        <select id="wq-bulk-wo" class="form-select" onchange="wqBulkRenderRows()"></select>
        <div class="form-text">
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

<div id="wq-grp-overlay" class="wq-overlay" style="display:none;">
  <div class="card" style="width:min(560px, 92vw); max-height:85vh; display:flex; flex-direction:column;">
    <div class="card-header">
      <div>
        <h3 class="card-title mb-0" id="wq-grp-title">Assign</h3>
        <div class="text-muted small" id="wq-grp-sub"></div>
      </div>
      <button type="button" class="btn-close ms-auto" onclick="wqGrpClose()"></button>
    </div>
    <div class="card-body" style="overflow-y:auto;">
      <div class="d-flex align-items-center mb-1">
        <div class="subheader" id="wq-grp-items-label">Include</div>
        <label class="form-check mb-0 ms-auto small"><input class="form-check-input" type="checkbox" id="wq-grp-all" checked onchange="wqGrpToggleAll(this.checked)"><span class="form-check-label">All</span></label>
      </div>
      <div id="wq-grp-items" class="mb-3"></div>
      <div class="subheader mb-1">Assign to <span class="text-muted fw-normal text-lowercase">(pick several to share; none = unassign)</span></div>
      <div id="wq-grp-ops" class="d-flex flex-wrap gap-2"></div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <div id="wq-grp-status" class="text-muted small"></div>
      <div>
        <button class="btn btn-link" onclick="wqGrpClose()">Cancel</button>
        <button class="btn btn-primary" id="wq-grp-apply" onclick="wqGrpApply()">Assign</button>
      </div>
    </div>
  </div>
</div>

<div id="wq-assignee-overlay" class="wq-overlay" style="display:none;">
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

let wqSearchTimer;
let pendingStep = '';
function wqSearchDebounce() { clearTimeout(wqSearchTimer); wqSearchTimer = setTimeout(renderBoard, 200); }

// Filters the board applies client-side (job / work order are server-side)
function wqCardVisible(c) {
  const q = (document.getElementById('wq-search')?.value || '').trim().toLowerCase();
  const step = document.getElementById('wq-step')?.value;
  if (step && c.name !== step) return false;
  if (document.getElementById('wq-overdue-only')?.checked && !isPast(c.due_date)) return false;
  if (document.getElementById('wq-hide-locked')?.checked && c.gated) return false;
  if (q) {
    const hay = `${c.name} ${c.release_label || ''} ${c.elevation_tag || ''} ${c.job_name || ''}`.toLowerCase();
    if (!q.split(/\s+/).every(t => hay.includes(t))) return false;
  }
  return true;
}

function wqUpdateStrip() {
  const all = wqAllCards();
  const unassigned = (wqData?.unassigned?.stages || []).length;
  document.getElementById('wq-s-unassigned').textContent = unassigned;
  document.getElementById('wq-s-ready').textContent = all.filter(c => !c.gated).length;
  document.getElementById('wq-s-locked').textContent = all.filter(c => c.gated).length;
  document.getElementById('wq-s-overdue').textContent = all.filter(c => isPast(c.due_date)).length;
  document.getElementById('wq-s-ops').textContent = (wqData?.operators || []).filter(o => (o.stages || []).length).length;
  document.getElementById('wq-strip').style.display = '';
  document.getElementById('wq-updated').textContent = 'Updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  // Step filter lists whichever step names exist on the board right now
  const sel = document.getElementById('wq-step');
  const cur = sel.value;
  const names = [...new Set(all.map(c => c.name))].sort((a, b) => a.localeCompare(b));
  sel.innerHTML = '<option value="">All steps</option>' + names.map(n => `<option value="${esc(n).replace(/"/g, '&quot;')}">${esc(n)}</option>`).join('');
  const want = cur || pendingStep;
  if (want && names.includes(want)) sel.value = want;
  pendingStep = '';
}

function wqSyncUrl() {
  const q = new URLSearchParams();
  const set = (k, v) => v && q.set(k, v);
  set('job', document.getElementById('wq-job').value);
  set('wo', document.getElementById('wq-wo').value);
  set('step', document.getElementById('wq-step').value);
  if (wqGroup !== 'operator') q.set('group', wqGroup);
  set('q', document.getElementById('wq-search').value.trim());
  if (document.getElementById('wq-overdue-only').checked) q.set('overdue', '1');
  if (document.getElementById('wq-hide-locked').checked) q.set('hidelocked', '1');
  history.replaceState(null, '', location.pathname + (q.toString() ? '?' + q : ''));
}

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

  // The queue only concerns active work orders — restrict the job (and work
  // order) pickers to jobs that have at least one. `/work-orders` already
  // excludes archived ones; older rows with no status fall back to "active".
  const isActiveWo = w => (w.status ?? 'active') === 'active';
  const activeJobIds = new Set(wqWOs.filter(isActiveWo).map(w => String(w.business_job_id)));

  const jobSel = document.getElementById('wq-job');
  jobSel.innerHTML = '<option value="">All jobs</option>' +
    wqJobs
      .filter(j => activeJobIds.has(String(j.id)))
      .map(j => `<option value="${j.id}">${esc(j.job_number)} – ${esc(j.job_name)}</option>`)
      .join('');
  wqFillWorkOrderOptions();
}

function wqFillWorkOrderOptions() {
  const jobId = document.getElementById('wq-job').value;
  const woSel = document.getElementById('wq-wo');
  const isActiveWo = w => (w.status ?? 'active') === 'active';
  let list = wqWOs.filter(isActiveWo);
  if (jobId) list = list.filter(w => String(w.business_job_id) === jobId);
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
  const due = `<input type="date" class="wq-inline ${isPast(c.due_date) ? 'text-danger' : 'text-secondary'}"
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
  return `<div class="wq-card${c.gated ? ' locked' : ''}${isPast(c.due_date) ? ' overdue' : ''}" draggable="true" data-stage="${c.stage_id}"
      ondragstart="wqDragStart(event, ${c.stage_id})" ondragend="wqDragEnd(event)">
    <div class="d-flex align-items-start gap-1">
      <div class="flex-fill"><strong>${esc(c.name)}</strong>${active}${lock} ${shared}</div>
      <button class="btn btn-sm btn-ghost-secondary p-0 px-1" title="Edit assignees"
          onclick="event.stopPropagation(); wqEditAssignees(${c.stage_id})"><i class="ti ti-user-plus"></i></button>
    </div>
    <div class="text-secondary">${esc(c.release_label)} · ${esc(c.elevation_tag)}${c.job_name ? ' · ' + esc(c.job_name) : ''}</div>
    <div class="wq-meta">
      <span class="text-secondary">${prio}</span>
      ${due}
      ${c.date_requested ? `<span class="text-secondary">need ${esc(c.date_requested)}</span>` : ''}
    </div>
  </div>`;
}

function colHtml(opId, title, oldest, stages, initials, muted) {
  const shown = stages.filter(wqCardVisible);
  const ready = stages.filter(s => !s.gated).length;
  const countLabel = ready === stages.length ? `${stages.length}` : `${ready} ready · ${stages.length}`;
  return `<div class="wq-col" data-op="${opId}"
      ondragover="wqDragOver(event)" ondragleave="wqDragLeave(event)" ondrop="wqDrop(event, ${opId})">
    <div class="wq-col-head">
      ${initials ? `<span class="badge bg-blue-lt text-blue">${esc(initials)}</span>` : '<i class="ti ti-inbox text-secondary"></i>'}
      <span class="${muted ? 'text-secondary' : ''}">${esc(title)}</span>
      <span class="ms-auto text-secondary small">${countLabel}${oldest ? ` · oldest ${esc(oldest)}` : ''}</span>
    </div>
    <div>${shown.map(cardHtml).join('') || '<div class="text-secondary small text-center py-2">—</div>'}</div>
  </div>`;
}

let wqGroup = 'operator';

function wqSetGroup(g) {
  wqGroup = g;
  renderBoard();
}

// Steps in workflow order: stages are created in sequence, so the lowest stage id seen per name approximates it
function wqStepOrder(cards) {
  const first = new Map();
  cards.forEach(c => { if (!first.has(c.name) || c.stage_id < first.get(c.name)) first.set(c.name, c.stage_id); });
  return [...first.entries()].sort((a, b) => a[1] - b[1]).map(e => e[0]);
}

function groupedCardHtml(c, users, mode) {
  const ops = (c.assignee_ids || []).map(id => users[id]).filter(Boolean)
    .map(u => `<span class="badge bg-blue-lt text-blue" title="${esc(u.name)}">${esc(u.initials || u.name)}</span>`).join(' ')
    || '<span class="badge bg-secondary-lt text-secondary">unassigned</span>';
  const active = c.status === 'in_progress' ? ' <span class="badge bg-warning-lt text-warning">active</span>' : '';
  const lock = c.gated
    ? ` <i class="ti ti-lock wq-lock" title="Blocked by &quot;${esc(c.blocking_stage_name || 'an earlier step')}&quot;"></i>` : '';
  // The column/sub-header already names the step (or job), so the card leads with what's left
  const lead = c.elevation_tag;
  const where = esc(c.release_label);
  return `<div class="wq-card static${c.gated ? ' locked' : ''}${isPast(c.due_date) ? ' overdue' : ''}" data-stage="${c.stage_id}">
    <div class="d-flex align-items-start gap-1">
      <div class="flex-fill"><strong>${esc(lead)}</strong>${active}${lock}
        <span class="text-secondary small ms-1">${where}</span></div>
      <button class="btn btn-sm btn-ghost-secondary p-0 px-1" title="Edit assignees"
          onclick="event.stopPropagation(); wqEditAssignees(${c.stage_id})"><i class="ti ti-user-plus"></i></button>
    </div>
    <div class="wq-meta">
      ${ops}
      ${c.due_date ? `<span class="small ${isPast(c.due_date) ? 'text-danger' : 'text-secondary'}">due ${esc(c.due_date)}</span>` : ''}
      ${c.date_requested ? `<span class="text-secondary small">need ${esc(c.date_requested)}</span>` : ''}
      ${c.priority != null ? `<span class="text-secondary small">#${c.priority}</span>` : ''}
    </div>
  </div>`;
}

function grpBtn(attrs, title) {
  return `<button type="button" class="btn btn-sm btn-ghost-primary p-0 px-1 wq-grp-btn" ${attrs} title="${esc(title)}" data-permission="fabrication.work-orders.edit"><i class="ti ti-user-plus"></i></button>`;
}

function subHtml(label, group, users, mode, act) {
  const ready = group.filter(c => !c.gated).length;
  return `<div class="wq-sub"><span>${esc(label)}</span><span class="ms-auto">${ready === group.length ? group.length : `${ready} ready · ${group.length}`}</span>${act || ''}</div>`
    + group.map(c => groupedCardHtml(c, users, mode)).join('');
}

// "By job": one column per job, steps grouped inside. "By step": one column per step, jobs grouped inside.
function renderGrouped(board) {
  const users = {};
  (wqData.fab_users || []).forEach(u => { users[u.id] = u; });
  (wqData.operators || []).forEach(o => { users[o.user.id] = users[o.user.id] || o.user; });

  const all = wqAllCards();
  const cards = all.filter(wqCardVisible);
  const stepOrder = wqStepOrder(all);
  const by = (list, keyFn) => list.reduce((m, c) => { const k = keyFn(c); (m.get(k) || m.set(k, []).get(k)).push(c); return m; }, new Map());
  const jobKey = c => c.business_job_id ?? 0;
  const jobTitle = c => c.job_number ? `${c.job_number} – ${c.job_name || ''}` : (c.job_name || 'No job');
  const prio = list => Math.min(...list.map(c => c.priority ?? Infinity));
  const cols = [];

  if (wqGroup === 'job') {
    const jobs = [...by(cards, jobKey).values()].sort((a, b) =>
      prio(a) - prio(b) || String(a[0].job_number).localeCompare(String(b[0].job_number), undefined, { numeric: true }));
    jobs.forEach(list => {
      const steps = by(list, c => c.name);
      const jid = list[0].business_job_id;
      const body = stepOrder.filter(n => steps.has(n)).map(n => subHtml(n, steps.get(n), users, 'job',
        jid ? grpBtn(`data-grp="job" data-job="${jid}" data-step="${esc(n).replace(/"/g, '&quot;')}"`, `Assign "${n}" for this job`) : '')).join('');
      cols.push(colShell(jobTitle(list[0]), list, body,
        jid ? grpBtn(`data-grp="job" data-job="${jid}"`, 'Assign this whole job, or pick steps') : ''));
    });
  } else {
    const steps = by(cards, c => c.name);
    stepOrder.filter(n => steps.has(n)).forEach(n => {
      const list = steps.get(n);
      const jobs = [...by(list, jobKey).values()].sort((a, b) => prio(a) - prio(b));
      const nm = esc(n).replace(/"/g, '&quot;');
      const body = jobs.map(g => subHtml(jobTitle(g[0]), g, users, 'step',
        g[0].business_job_id ? grpBtn(`data-grp="step" data-step="${nm}" data-job="${g[0].business_job_id}"`, `Assign "${n}" for this job`) : '')).join('');
      cols.push(colShell(n, list, body, grpBtn(`data-grp="step" data-step="${nm}"`, 'Assign this step across jobs')));
    });
  }

  board.innerHTML = cols.join('') || '<div class="text-secondary text-center w-100 py-5">Nothing matches these filters.</div>';
  if (typeof applyActionPermissions === 'function') applyActionPermissions();
}

function colShell(title, list, body, act) {
  const ready = list.filter(c => !c.gated).length;
  const overdue = list.filter(c => isPast(c.due_date)).length;
  return `<div class="wq-col" style="min-width:300px">
    <div class="wq-col-head">
      <span class="text-truncate" title="${esc(title)}">${esc(title)}</span>
      ${act || ''}
      <span class="ms-auto text-secondary small text-nowrap">${ready === list.length ? list.length : `${ready} ready · ${list.length}`}${overdue ? ` · <span class="text-danger">${overdue} overdue</span>` : ''}</span>
    </div>
    <div>${body}</div>
  </div>`;
}

function renderBoard() {
  document.getElementById('wq-loading').style.display = 'none';
  const board = document.getElementById('wq-board');
  board.style.display = 'flex';
  if (!wqData) { board.innerHTML = ''; return; }

  document.querySelectorAll('#wq-group [data-group]').forEach(b => b.classList.toggle('active', b.dataset.group === wqGroup));
  if (wqGroup !== 'operator') {
    renderGrouped(board);
    wqUpdateStrip();
    wqSyncUrl();
    wqSyncTopScroll();
    return;
  }

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
  wqUpdateStrip();
  wqSyncUrl();
  wqSyncTopScroll();
}

// A sticky mirror scrollbar pinned to the top of the viewport, so left/right
// navigation stays reachable even when the tallest column pushes the board's
// own (bottom) scrollbar far down the page.
function wqSyncTopScroll() {
    const board = document.getElementById('wq-board');
    const bar = document.getElementById('wq-scroll-top');
    const inner = document.getElementById('wq-scroll-top-inner');
    if (!board || !bar || !inner) return;

    const overflow = board.scrollWidth > board.clientWidth + 1;
    bar.style.display = overflow ? 'block' : 'none';
    inner.style.width = board.scrollWidth + 'px';

    if (!bar.dataset.bound) {
        bar.addEventListener('scroll', () => { board.scrollLeft = bar.scrollLeft; });
        board.addEventListener('scroll', () => { bar.scrollLeft = board.scrollLeft; });
        window.addEventListener('resize', wqSyncTopScroll);
        bar.dataset.bound = '1';
    }
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
  // A stage shared by several operators is listed under each of them — count it once
  const seen = new Set();
  return cards.filter(c => !seen.has(c.stage_id) && seen.add(c.stage_id));
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
        <div class="form-text">Ctrl/Cmd-click for more than one. None selected = unassign.</div>
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

// ── Group assign: a whole job, or chosen steps of it (and a step across jobs) ──
let wqGrp = null;

document.getElementById('wq-board').addEventListener('click', e => {
  const b = e.target.closest('.wq-grp-btn');
  if (!b) return;
  e.stopPropagation();
  const job = b.dataset.job ? parseInt(b.dataset.job) : null;
  b.dataset.grp === 'job' ? wqGrpOpenJob(job, b.dataset.step || null) : wqGrpOpenStep(b.dataset.step, job);
});

// With a single work order selected in the filter, stay inside it rather than touching the whole job
function wqGrpScope(jobId) {
  const wo = document.getElementById('wq-wo').value;
  return wo ? { work_order_id: parseInt(wo) } : { job_id: jobId };
}

function wqGrpOpenJob(jobId, presetStep) {
  const wo = document.getElementById('wq-wo').value;
  const cards = wqAllCards().filter(c => c.business_job_id === jobId && (!wo || String(c.work_order_id) === wo));
  if (!cards.length) return;
  const order = wqStepOrder(cards);
  const items = order.map(n => {
    const g = cards.filter(c => c.name === n);
    return { key: n, label: n, count: g.length, ready: g.filter(c => !c.gated).length };
  });
  const j = cards[0];
  wqGrp = { mode: 'job', jobId, items };
  wqGrpShow(`Assign ${j.job_number ? j.job_number + ' – ' : ''}${j.job_name || 'job'}`,
    (wo ? 'Limited to the work order selected in the filter.' : 'All work orders of this job.') + ' Includes locked steps; finished steps are left alone.',
    'Steps to include', presetStep ? [presetStep] : items.map(i => i.key));
}

function wqGrpOpenStep(stepName, presetJob) {
  const wo = document.getElementById('wq-wo').value;
  const cards = wqAllCards().filter(c => c.name === stepName && (!wo || String(c.work_order_id) === wo));
  if (!cards.length) return;
  const byJob = new Map();
  cards.forEach(c => { const k = c.business_job_id ?? 0; (byJob.get(k) || byJob.set(k, []).get(k)).push(c); });
  const items = [...byJob.entries()].filter(([k]) => k).map(([k, g]) => ({
    key: String(k), jobId: k, count: g.length, ready: g.filter(c => !c.gated).length,
    label: g[0].job_number ? `${g[0].job_number} – ${g[0].job_name || ''}` : (g[0].job_name || 'Job'),
  }));
  wqGrp = { mode: 'step', stepName, items };
  wqGrpShow(`Assign "${stepName}"`, 'Across the jobs you tick below. Includes locked steps; finished steps are left alone.', 'Jobs to include',
    presetJob ? [String(presetJob)] : items.map(i => i.key));
}

function wqGrpShow(title, sub, itemsLabel, checkedKeys) {
  document.getElementById('wq-grp-title').textContent = title;
  document.getElementById('wq-grp-sub').textContent = sub;
  document.getElementById('wq-grp-items-label').textContent = itemsLabel;
  document.getElementById('wq-grp-status').textContent = '';
  document.getElementById('wq-grp-items').innerHTML = wqGrp.items.map(i => `
    <label class="form-check d-flex align-items-center mb-1">
      <input class="form-check-input me-2" type="checkbox" value="${esc(i.key).replace(/"/g, '&quot;')}" ${checkedKeys.includes(i.key) ? 'checked' : ''} onchange="wqGrpSyncAll()">
      <span class="form-check-label flex-fill">${esc(i.label)}</span>
      <span class="text-muted small">${i.ready === i.count ? i.count : `${i.ready} ready · ${i.count}`}</span>
    </label>`).join('');
  document.getElementById('wq-grp-ops').innerHTML = (wqData.fab_users || []).map(u => `
    <label class="form-selectgroup-item">
      <input type="checkbox" class="form-selectgroup-input" value="${u.id}">
      <span class="form-selectgroup-label">${esc(u.name)}</span>
    </label>`).join('') || '<div class="text-muted">No fab users.</div>';
  wqGrpSyncAll();
  document.getElementById('wq-grp-overlay').style.display = 'flex';
}

function wqGrpSyncAll() {
  const boxes = [...document.querySelectorAll('#wq-grp-items input')];
  document.getElementById('wq-grp-all').checked = boxes.length > 0 && boxes.every(b => b.checked);
}
function wqGrpToggleAll(on) { document.querySelectorAll('#wq-grp-items input').forEach(b => { b.checked = on; }); }
function wqGrpClose() { document.getElementById('wq-grp-overlay').style.display = 'none'; wqGrp = null; }

async function wqGrpApply() {
  if (!wqGrp) return;
  const keys = [...document.querySelectorAll('#wq-grp-items input:checked')].map(b => b.value);
  const ids = [...document.querySelectorAll('#wq-grp-ops input:checked')].map(b => parseInt(b.value));
  const status = document.getElementById('wq-grp-status');
  if (!keys.length) { status.textContent = 'Tick at least one to include.'; return; }
  const chosen = wqGrp.items.filter(i => keys.includes(i.key));
  const total = chosen.reduce((n, i) => n + i.count, 0);
  if (!ids.length && !confirm(`Unassign all ${total} selected step(s)?`)) return;

  const btn = document.getElementById('wq-grp-apply');
  btn.disabled = true;
  status.textContent = 'Assigning…';
  try {
    let updated = 0;
    const post = async body => {
      const r = await API('/work-order-stages/bulk-assign', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
      });
      if (!r.ok) throw new Error('bulk assign failed');
      updated += (await r.json()).updated || 0;
    };
    if (wqGrp.mode === 'job') {
      await post({ ...wqGrpScope(wqGrp.jobId), assignments: chosen.map(i => ({ stage_name: i.key, assigned_to_ids: ids })) });
    } else {
      for (const i of chosen) {
        await post({ ...wqGrpScope(i.jobId), assignments: [{ stage_name: wqGrp.stepName, assigned_to_ids: ids }] });
      }
    }
    status.textContent = `Updated ${updated} step(s).`;
    await loadBoard();
    setTimeout(wqGrpClose, 600);
  } catch (e) {
    console.error(e);
    status.textContent = 'Failed to assign. Nothing may have changed — refresh and check.';
  } finally {
    btn.disabled = false;
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

(async function wqInit() {
  const u = new URLSearchParams(location.search);
  await wqLoadFilters();
  if (u.get('job')) { document.getElementById('wq-job').value = u.get('job'); wqFillWorkOrderOptions(); }
  if (u.get('wo')) document.getElementById('wq-wo').value = u.get('wo');
  if (u.get('q')) document.getElementById('wq-search').value = u.get('q');
  document.getElementById('wq-overdue-only').checked = u.get('overdue') === '1';
  document.getElementById('wq-hide-locked').checked = u.get('hidelocked') === '1';
  if (u.get('step')) pendingStep = u.get('step');
  if (['job', 'step'].includes(u.get('group'))) wqGroup = u.get('group');
  await loadBoard();
})();
setInterval(() => loadBoard(true), 30000);
</script>
@endpush
