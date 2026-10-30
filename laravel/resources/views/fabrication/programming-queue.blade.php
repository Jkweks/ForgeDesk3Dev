@extends('layouts.app')

@section('title', 'Programming Queue – Fabrication')

@section('styles')
.pq-row.gated { opacity:.6; }
.pq-row.overdue td:first-child { box-shadow:inset 3px 0 0 var(--tblr-danger); }
.pq-steps { display:flex; flex-wrap:wrap; gap:.25rem; }
.pq-step { font-size:.7rem; padding:.05rem .4rem; border-radius:999px; border:1px solid var(--tblr-border-color); color:var(--tblr-secondary-color); white-space:nowrap; }
.pq-step.complete { background:var(--tblr-success-lt); color:var(--tblr-success); border-color:transparent; }
.pq-step.in_progress { background:var(--tblr-blue-lt); color:var(--tblr-blue); border-color:transparent; }
.pq-step.is-this { font-weight:700; border-color:var(--tblr-primary); }
.pq-stat { display:flex; align-items:baseline; gap:6px; }
.pq-stat .n { font-size:1.4rem; font-weight:600; line-height:1; }
.pq-stat .l { font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:var(--tblr-secondary-color); }
.pq-cal { width:100%; border-collapse:collapse; table-layout:fixed; margin-bottom:.75rem; }
.pq-cal th, .pq-cal td { border:1px solid var(--tblr-border-color); padding:.25rem; vertical-align:top; }
.pq-cal th { background:var(--tblr-bg-surface-secondary); text-align:center; font-weight:600; }
.pq-cal th.today { background:var(--tblr-primary-lt); }
.pq-cal td { height:7rem; font-size:.78rem; }
.pq-cal td.today { background:var(--tblr-primary-lt); }
.pq-cal td.past { background:var(--tblr-bg-surface-secondary); }
.pq-cal .wk { width:3.5rem; text-align:center; font-weight:600; color:var(--tblr-secondary-color); background:var(--tblr-bg-surface-secondary); }
.pq-who { float:right; margin-left:.3rem; font-size:.68rem; font-weight:600; color:var(--tblr-secondary-color); }
.pq-chip { display:block; padding:.2rem .45rem; margin-bottom:2px; border:1px solid var(--tblr-border-color); border-left:3px solid var(--tblr-primary); border-radius:var(--tblr-border-radius-sm); background:var(--tblr-bg-surface); line-height:1.25; color:inherit; text-decoration:none; }
.pq-chip.late { border-left-color:var(--tblr-danger); }
.pq-chip.progress { border-left-color:var(--tblr-blue); background:var(--tblr-blue-lt); }
.pq-chip strong, .pq-chip small { display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pq-chip small { color:var(--tblr-secondary-color); }
.pq-toast { position:fixed; bottom:1rem; right:1rem; z-index:2000; max-width:360px; }
@endsection

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Fabrication</div>
          <h2 class="page-title">Programming Queue</h2>
        </div>
        <div class="col-auto ms-auto d-flex gap-2 align-items-center">
          <div class="btn-group btn-group-sm" id="pq-view" title="List or schedule">
            <button class="btn btn-outline-primary active" data-v="list" onclick="pqSetView('list')">List</button>
            <button class="btn btn-outline-primary" data-v="schedule" onclick="pqSetView('schedule')">Schedule</button>
          </div>
          <span class="text-muted small" id="pq-updated"></span>
          <button class="btn btn-outline-secondary" onclick="pqLoad()"><i class="ti ti-refresh me-1"></i>Refresh</button>
        </div>
      </div>
      <div class="text-secondary small mt-1">
        Every elevation (line item) with a CNC programming step still to do, ranked by work-order priority.
        <span class="text-danger">Locked</span> lines are waiting on an earlier step but can still be assigned.
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
        <select id="pq-assignee" class="form-select form-select-sm" style="width:auto" onchange="pqRender()"></select>
        <select id="pq-status" class="form-select form-select-sm" style="width:auto" onchange="pqRender()">
          <option value="">All statuses</option>
          <option value="pending">Not started</option>
          <option value="in_progress">In progress</option>
          <option value="complete">Complete</option>
        </select>
        <input type="text" id="pq-search" class="form-control form-control-sm" style="width:14rem" placeholder="Search job, release, line…" oninput="pqRender()" autocomplete="off">
        <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="pq-hide-locked" onchange="pqRender()"><span class="form-check-label small">Hide locked</span></label>
        <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="pq-done" onchange="pqLoad()"><span class="form-check-label small">Show completed (14 days)</span></label>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-4 py-2">
          <div class="pq-stat"><span class="n" id="pq-s-unassigned">-</span><span class="l">Unassigned</span></div>
          <div class="pq-stat"><span class="n text-success" id="pq-s-ready">-</span><span class="l">Ready</span></div>
          <div class="pq-stat"><span class="n text-primary" id="pq-s-progress">-</span><span class="l">In progress</span></div>
          <div class="pq-stat"><span class="n text-secondary" id="pq-s-locked">-</span><span class="l">Locked</span></div>
          <div class="pq-stat"><span class="n text-danger" id="pq-s-overdue">-</span><span class="l">Overdue</span></div>
        </div>
      </div>
      <div id="pq-sched" style="display:none">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
          <button class="btn btn-sm btn-outline-secondary" onclick="pqShift(-7)"><i class="ti ti-chevron-left"></i></button>
          <input type="date" id="pq-sched-start" class="form-control form-control-sm" style="width:auto" onchange="pqRender()">
          <button class="btn btn-sm btn-outline-secondary" onclick="pqShift(7)"><i class="ti ti-chevron-right"></i></button>
          <button class="btn btn-sm btn-outline-secondary" onclick="pqToday()">Today</button>
          <span class="text-secondary small ms-2">Programming is due the working day before each work order's planned fab start. Change the fab start on the Work Queue schedule.</span>
        </div>
        <div class="table-responsive"><div id="pq-sched-body"></div></div>
        <div id="pq-sched-unsched" class="mt-3"></div>
      </div>
      <div class="card" id="pq-list">
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Job / release</th><th>Line</th><th>Steps</th><th>Requested</th><th>Due</th>
                <th>Programmer</th><th>Status</th><th class="text-end"></th>
              </tr>
            </thead>
            <tbody id="pq-body"><tr><td colspan="8" class="text-center text-muted py-5">Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<div id="pq-toast" class="pq-toast"></div>
@endsection

@push('scripts')
<script>
const API = (p, o = {}) => fetch('/api/v1' + p, {
  ...o,
  credentials: 'include',
  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', ...(o.headers || {}) },
});

let pqRows = [];
let pqView = 'list';
let pqUsers = [];

function esc(s) { if (s == null) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
function isPast(d) { return d && new Date(d) < new Date(new Date().toDateString()); }
function fmtDate(d) { return d ? new Date(d + 'T00:00:00').toLocaleDateString([], { month: 'short', day: 'numeric' }) : '—'; }
function pqToast(msg, kind = 'danger') {
  const el = document.getElementById('pq-toast');
  el.innerHTML = `<div class="alert alert-${kind} mb-0">${esc(msg)}</div>`;
  setTimeout(() => { el.innerHTML = ''; }, 5000);
}

async function pqLoad(fromPoll) {
  // Don't yank an open programmer dropdown out from under someone on the auto-refresh.
  if (fromPoll === true && document.activeElement?.closest('#pq-body')) return;
  const q = document.getElementById('pq-done').checked ? '?include_done=1' : '';
  try {
    const r = await API('/programming-queue' + q);
    if (!r.ok) throw new Error(r.status);
    const d = await r.json();
    pqRows = d.rows || [];
    pqUsers = d.fab_users || [];
    pqFillAssignees();
    document.getElementById('pq-updated').textContent = 'Updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    pqRender();
  } catch (e) {
    console.error(e);
    document.getElementById('pq-body').innerHTML = '<tr><td colspan="8" class="text-center text-danger py-5">Could not load the programming queue.</td></tr>';
  }
}

function pqFillAssignees() {
  const sel = document.getElementById('pq-assignee');
  const cur = sel.value;
  sel.innerHTML = '<option value="">All programmers</option><option value="none">Unassigned</option>' +
    pqUsers.map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('');
  sel.value = cur;
}

function pqVisible(r) {
  const who = document.getElementById('pq-assignee').value;
  const st = document.getElementById('pq-status').value;
  const q = document.getElementById('pq-search').value.trim().toLowerCase();
  if (who === 'none' && r.assignee_ids.length) return false;
  if (who && who !== 'none' && !r.assignee_ids.includes(parseInt(who))) return false;
  if (st && r.status !== st) return false;
  if (document.getElementById('pq-hide-locked').checked && r.gated) return false;
  if (q) {
    const hay = `${r.job_name || ''} ${r.job_number || ''} ${r.release_label} ${r.elevation_tag} ${r.elevation_type || ''}`.toLowerCase();
    if (!q.split(/\s+/).every(t => hay.includes(t))) return false;
  }
  return true;
}

function pqRender() {
  document.getElementById('pq-list').style.display = pqView === 'list' ? '' : 'none';
  document.getElementById('pq-sched').style.display = pqView === 'schedule' ? '' : 'none';
  if (pqView === 'schedule') pqRenderSchedule();
  const open = pqRows.filter(r => r.status !== 'complete');
  document.getElementById('pq-s-unassigned').textContent = open.filter(r => !r.assignee_ids.length).length;
  document.getElementById('pq-s-ready').textContent = open.filter(r => !r.gated && r.status === 'pending').length;
  document.getElementById('pq-s-progress').textContent = open.filter(r => r.status === 'in_progress').length;
  document.getElementById('pq-s-locked').textContent = open.filter(r => r.gated).length;
  document.getElementById('pq-s-overdue').textContent = open.filter(r => isPast(r.due_date)).length;

  const rows = pqRows.filter(pqVisible);
  document.getElementById('pq-body').innerHTML = rows.length ? rows.map(pqRowHtml).join('')
    : '<tr><td colspan="8" class="text-center text-muted py-5">Nothing waiting for programming.</td></tr>';
}

function pqRowHtml(r) {
  const done = r.status === 'complete';
  const overdue = !done && isPast(r.due_date);
  const badge = { pending: 'bg-secondary-lt', in_progress: 'bg-blue-lt', complete: 'bg-success-lt' }[r.status] || 'bg-secondary-lt';
  const label = { pending: 'Not started', in_progress: 'In progress', complete: 'Complete' }[r.status] || r.status;
  const steps = r.steps.map(s => `<span class="pq-step ${s.status}${s.is_this ? ' is-this' : ''}">${esc(s.name)}</span>`).join('');
  const who = `<select class="form-select form-select-sm" style="min-width:9rem" data-permission="fabrication.work-orders.edit" onchange="pqAssign(${r.stage_id}, this.value)">
      <option value="">Unassigned</option>${pqUsers.map(u => `<option value="${u.id}"${r.assignee_ids[0] === u.id ? ' selected' : ''}>${esc(u.name)}</option>`).join('')}
    </select>` + (r.assignee_ids.length > 1 ? `<div class="small text-muted">+ ${esc(r.assignee_names.slice(1).join(', '))}</div>` : '');
  let action = '';
  if (r.status === 'pending') action = `<button class="btn btn-sm btn-primary" data-permission="fabrication.work-orders.edit" onclick="pqSet(${r.stage_id}, 'in_progress')">Start</button>`;
  else if (r.status === 'in_progress') action = `<button class="btn btn-sm btn-success" data-permission="fabrication.work-orders.edit" onclick="pqSet(${r.stage_id}, 'complete')">Complete</button>`;
  else action = `<button class="btn btn-sm btn-outline-secondary" data-permission="fabrication.work-orders.edit" onclick="pqSet(${r.stage_id}, 'pending')">Reopen</button>`;

  return `<tr class="pq-row${r.gated && !done ? ' gated' : ''}${overdue ? ' overdue' : ''}">
    <td><a href="/fabrication/work-orders?wo=${r.work_order_id}" class="fw-medium">${esc(r.job_name || r.job_number || 'No job')}</a>
        <div class="small text-muted">${esc(r.release_label)}${r.priority != null ? ` · #${esc(r.priority)}` : ''}</div></td>
    <td>${esc(r.elevation_tag)}${r.elevation_type ? `<div class="small text-muted">${esc(r.elevation_type)}${r.quantity > 1 ? ` · qty ${esc(r.quantity)}` : ''}</div>` : ''}
        ${r.notes ? `<div class="small text-muted text-truncate" style="max-width:16rem" title="${esc(r.notes).replace(/"/g, '&quot;')}">${esc(r.notes)}</div>` : ''}</td>
    <td><div class="pq-steps">${steps}</div>${r.gated && !done ? `<div class="small text-danger mt-1"><i class="ti ti-lock"></i> Waiting on ${esc(r.blocking_stage_name)}</div>` : ''}</td>
    <td>${fmtDate(r.date_requested)}</td>
    <td class="${overdue ? 'text-danger fw-medium' : ''}">${fmtDate(r.due_date)}</td>
    <td>${who}</td>
    <td><span class="badge ${badge}">${label}</span></td>
    <td class="text-end text-nowrap">${action}</td>
  </tr>`;
}

async function pqPatch(id, body) {
  const r = await API('/work-order-stages/' + id, { method: 'PATCH', body: JSON.stringify(body) });
  if (!r.ok) {
    const d = await r.json().catch(() => ({}));
    throw new Error(d.message || 'Update failed.');
  }
}

async function pqSet(id, status) {
  try { await pqPatch(id, { status, log_message: 'Status changed via Programming Queue' }); }
  catch (e) { pqToast(e.message); }
  pqLoad();
}

async function pqAssign(id, userId) {
  try { await pqPatch(id, { assigned_to_ids: userId ? [parseInt(userId)] : [], log_message: 'Programmer changed via Programming Queue' }); }
  catch (e) { pqToast(e.message); }
  pqLoad();
}

// ── Schedule ────────────────────────────────────────────────────────────
const ymd = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const parseYmd = s => new Date(s + 'T00:00:00');
function mondayOf(d) { const x = new Date(d); x.setHours(0, 0, 0, 0); x.setDate(x.getDate() - ((x.getDay() + 6) % 7)); return x; }
function fmtHours(m) { return m == null ? '' : (m / 60).toFixed(m % 60 ? 1 : 0) + ' h'; }

function pqSetView(v) {
  pqView = v;
  document.querySelectorAll('#pq-view button').forEach(b => b.classList.toggle('active', b.dataset.v === v));
  const u = new URL(location.href);
  v === 'schedule' ? u.searchParams.set('view', 'schedule') : u.searchParams.delete('view');
  history.replaceState(null, '', u);
  pqRender();
}
function pqShift(days) { const el = document.getElementById('pq-sched-start'); const d = parseYmd(el.value); d.setDate(d.getDate() + days); el.value = ymd(d); pqRender(); }
function pqToday() { document.getElementById('pq-sched-start').value = ymd(mondayOf(new Date())); pqRender(); }

function pqRenderSchedule() {
  const startEl = document.getElementById('pq-sched-start');
  if (!startEl.value) startEl.value = ymd(mondayOf(new Date()));
  const monday = mondayOf(parseYmd(startEl.value));
  const days = [];
  for (let w = 0; w < 2; w++) for (let i = 0; i < 5; i++) { const d = new Date(monday); d.setDate(d.getDate() + w * 7 + i); days.push(ymd(d)); }
  const todayStr = ymd(new Date());
  const winStart = days[0], winEnd = days[days.length - 1];

  // One chip per work order per programmer per programming day: all of a release's lines to program together
  const open = pqRows.filter(r => r.status !== 'complete' && pqVisible(r));
  const chips = new Map();
  const unsched = new Map();
  for (const r of open) {
    const key0 = r.work_order_id;
    if (!r.programming_date) {
      const u = unsched.get(key0) || { r, lines: 0, minutes: 0 };
      u.lines++; u.minutes += r.programming_minutes || 0;
      unsched.set(key0, u);
      continue;
    }
    const who = r.assignee_ids.length ? r.assignee_ids : [0];
    for (const uid of who) {
      const key = `${uid}|${key0}`;
      const c = chips.get(key) || { uid, r, lines: 0, minutes: 0, started: false, ids: [] };
      c.lines++; c.minutes += r.programming_minutes || 0; c.started = c.started || r.status === 'in_progress';
      chips.set(key, c);
    }
  }

  const inits = new Map(pqUsers.map(u => [u.id, u.initials || u.name]));
  const chipHtml = c => {
    const late = c.r.programming_date < todayStr;
    const fab = c.r.planned_start_date ? fmtDate(c.r.planned_start_date) : '—';
    const who = c.uid ? inits.get(c.uid) : '?';
    return `<a class="pq-chip${late ? ' late' : ''}${c.started ? ' progress' : ''}" href="/fabrication/work-orders?wo=${c.r.work_order_id}"
        title="${esc(c.r.job_name || '')} ${esc(c.r.release_label)} — fab starts ${fab}${c.r.fab_estimated_minutes ? `, est. fab ${fmtHours(c.r.fab_estimated_minutes)}` : ''}">
      <span class="pq-who" title="${c.uid ? 'Programmer' : 'Unassigned'}">${esc(who)}</span>
      <strong>${esc(c.r.job_name || c.r.job_number || 'No job')}</strong>
      <small>${esc(c.r.release_label)} · ${c.lines} line${c.lines > 1 ? 's' : ''}${c.minutes ? ' · ' + fmtHours(c.minutes) : ''}</small>
      <small>Fab ${fab}${c.r.fab_estimated_minutes ? ' · ' + fmtHours(c.r.fab_estimated_minutes) : ''}</small>
    </a>`;
  };
  const byDay = new Map();
  const pastDue = [];
  for (const c of chips.values()) {
    if (c.r.programming_date < winStart) pastDue.push(c);
    else (byDay.get(c.r.programming_date) || byDay.set(c.r.programming_date, []).get(c.r.programming_date)).push(c);
  }
  const weekTable = (wkDays, n) => `<table class="pq-cal"><thead><tr><th class="wk">Wk ${n}</th>${wkDays.map(d => {
      const dt = parseYmd(d);
      return `<th class="${d === todayStr ? 'today' : ''}">${dt.toLocaleDateString([], { weekday: 'short' })} <span class="fw-normal">${dt.toLocaleDateString([], { month: 'short', day: 'numeric' })}</span></th>`;
    }).join('')}</tr></thead><tbody><tr><td class="wk"></td>${wkDays.map(d =>
      `<td class="${d === todayStr ? 'today' : d < todayStr ? 'past' : ''}">${(byDay.get(d) || []).map(chipHtml).join('')}</td>`).join('')}</tr></tbody></table>`;

  const body = (pastDue.length ? `<div class="card mb-3 border-danger"><div class="card-header"><h4 class="card-title text-danger mb-0">Past due (${pastDue.length})</h4></div><div class="card-body d-flex flex-wrap gap-2">${pastDue.map(c => `<div style="width:13rem">${chipHtml(c)}</div>`).join('')}</div></div>` : '') +
    weekTable(days.slice(0, 5), 1) + weekTable(days.slice(5), 2);
  document.getElementById('pq-sched-body').innerHTML = body;

  // Work orders with no planned fab start cannot be placed
  const beyond = [...chips.values()].filter(c => c.r.programming_date > winEnd).length;
  const items = [...unsched.values()];
  document.getElementById('pq-sched-unsched').innerHTML =
    (items.length ? `<div class="card"><div class="card-header"><h4 class="card-title mb-0">No planned fab start (${items.length})</h4></div><div class="card-body d-flex flex-wrap gap-2">${items.map(u => `<a class="pq-chip" style="width:13rem" href="/fabrication/work-orders?wo=${u.r.work_order_id}"><strong>${esc(u.r.job_name || u.r.job_number || 'No job')}</strong><small>${esc(u.r.release_label)} · ${u.lines} line${u.lines > 1 ? 's' : ''}${u.minutes ? ' · ' + fmtHours(u.minutes) : ''}</small><small>${u.r.due_date ? 'Due ' + fmtDate(u.r.due_date) : 'No due date'}</small></a>`).join('')}</div></div>` : '') +
    (beyond ? `<div class="text-secondary small mt-2">${beyond} more work order${beyond > 1 ? 's are' : ' is'} scheduled after this window.</div>` : '');
}

if (new URLSearchParams(location.search).get('view') === 'schedule') { pqView = 'schedule'; document.querySelectorAll('#pq-view button').forEach(b => b.classList.toggle('active', b.dataset.v === 'schedule')); }
pqLoad();
setInterval(() => pqLoad(true), 30000);
</script>
@endpush
