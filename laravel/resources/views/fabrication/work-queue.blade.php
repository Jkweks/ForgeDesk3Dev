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
.wq-card .wq-more { opacity:.45; }
.wq-card:hover .wq-more { opacity:1; }
.wq-ctx .dropdown-item { display:flex; align-items:center; gap:.5rem; }
.wq-ctx .dropdown-item i { width:1rem; text-align:center; color:var(--tblr-secondary-color); }
.wq-facts { display:grid; grid-template-columns:repeat(auto-fill, minmax(11rem, 1fr)); gap:.6rem 1rem; }
.wq-facts .k { font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--tblr-secondary-color); }
.wq-matrix th, .wq-matrix td { text-align:center; vertical-align:middle; white-space:nowrap; }
.wq-matrix th:first-child, .wq-matrix td:first-child { text-align:left; position:sticky; left:0; background:var(--tblr-bg-surface); z-index:1; }
.wq-matrix thead th { cursor:pointer; position:sticky; top:0; background:var(--tblr-bg-surface); z-index:2; }
.wq-matrix thead th:first-child { z-index:3; cursor:default; }
.wq-matrix td input { width:1.15rem; height:1.15rem; cursor:pointer; }
.wq-stage-pill { display:inline-block; margin:0 .2rem .2rem 0; }
@media print {
  /* Only while the calendar is open: drop everything else from the layout (not just hide it,
     or it still occupies pages) so the calendar starts at the top of page 1 */
  body.wq-printing #loginPage, body.wq-printing #app > :not(#wq-cal-overlay) { display:none !important; }
  body.wq-printing, body.wq-printing #app { display:block !important; background:#fff !important; }
  body.wq-printing #wq-cal-overlay { position:static !important; display:block !important; width:auto !important; background:#fff !important; overflow:visible !important; }
  body.wq-printing #wq-cal-overlay > .card { box-shadow:none !important; border:0 !important; width:auto !important; max-height:none !important; display:block !important; }
  #wq-cal-overlay .d-print-none { display:none !important; }
  #wq-cal-overlay .card-body { overflow:visible !important; padding:0 !important; }
  /* Both weeks share a page when they fit; otherwise the second week moves to its own page whole */
  .wq-cal { break-inside:avoid; page-break-inside:avoid; font-size:.66rem; }
  .wq-cal tr { break-inside:avoid; page-break-inside:avoid; }
  .wq-cal tbody td { height:2.1rem; padding:.1rem .25rem; }
  .wq-cal .it { margin-bottom:.1rem; padding:.05rem .2rem; }
  .wq-sg.print { break-inside:avoid; page-break-inside:avoid; font-size:.66rem; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .wq-sg-row { break-inside:avoid; page-break-inside:avoid; }
  @page { size: landscape; margin: .4in; }
}
.wq-sched-wrap { overflow-x:auto; }
.wq-sched-panel { width:14rem; flex:none; max-height:80vh; overflow-y:auto; }
.wq-sched-panel .sb { margin-bottom:.35rem; }
.wq-sched-panel.drop-hover { outline:2px dashed var(--tblr-primary); outline-offset:2px; background:var(--tblr-primary-lt); }
.wq-sg { min-width:800px; margin-bottom:.75rem; font-size:.8rem; border:1px solid var(--tblr-border-color); }
.wq-sg-head, .wq-sg-row { display:grid; }
.wq-sg-head > div { background:var(--tblr-bg-surface-secondary); text-align:center; font-weight:600; padding:.25rem; border-right:1px solid var(--tblr-border-color); }
.wq-sg-head > div.today { background:var(--tblr-primary-lt); }
.wq-sg-row { border-top:1px solid var(--tblr-border-color); grid-auto-rows:minmax(3rem, auto); }
.wq-sg-row .op { font-weight:600; background:var(--tblr-bg-surface-secondary); padding:.25rem .4rem; }
.wq-sg .wkend { background:var(--tblr-bg-surface-tertiary, var(--tblr-bg-surface-secondary)); }
.wq-sg-row .cell { border-left:1px solid var(--tblr-border-color); min-height:3rem; }
.wq-sg-row .cell.today { background:var(--tblr-primary-lt); }
.wq-sg-row .cell { position:relative; }
.is-dragging .wq-sg-row .cell { background-image:repeating-linear-gradient(to right, transparent 0, transparent calc(25% - 1px), rgba(128,128,128,.22) calc(25% - 1px), rgba(128,128,128,.22) 25%); }
.wq-sg-row .cell.drop-span { --f:0%; --t:100%; background-image:linear-gradient(to right, transparent var(--f), var(--tblr-primary-lt) var(--f), var(--tblr-primary-lt) var(--t), transparent var(--t)), repeating-linear-gradient(to right, transparent 0, transparent calc(25% - 1px), rgba(128,128,128,.22) calc(25% - 1px), rgba(128,128,128,.22) 25%); box-shadow:inset 0 2px 0 var(--tblr-primary-lt), inset 0 -2px 0 var(--tblr-primary-lt); }
.wq-sg-row .cell.drop-end::after { content:attr(data-tip); position:absolute; right:2px; bottom:2px; z-index:3; padding:0 .35rem; font-size:.68rem; white-space:nowrap; border-radius:3px; background:var(--tblr-primary); color:#fff; pointer-events:none; }
.wq-sg-row .cell.drop-hover { outline:2px dashed var(--tblr-primary); outline-offset:-2px; background:var(--tblr-primary-lt); }
.wq-sg-row .sb { position:relative; z-index:1; margin:2px; }
.is-dragging .wq-sg-row .sb { pointer-events:none; }
.sb { padding:.2rem .5rem .2rem .5rem; border:1px solid var(--tblr-border-color); border-left:3px solid var(--tblr-primary); border-radius:var(--tblr-border-radius-sm); background:var(--tblr-bg-surface); cursor:grab; line-height:1.25; overflow:hidden; }
.sb:active { cursor:grabbing; }
.sb.late { border-left-color:var(--tblr-danger); }
.sb.locked { border-left-style:dashed; opacity:.75; }
.sb.assist { border-left-color:var(--tblr-success); background:var(--tblr-success-lt); }
.sb.note { border-left-color:var(--tblr-yellow); background:var(--tblr-yellow-lt); }
.sb.dragging { opacity:.35; }
.sb strong, .sb small { display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.sb small { color:var(--tblr-secondary-color); }
.sb .lz { position:absolute; top:0; left:0; bottom:0; width:.5rem; cursor:ew-resize; background:rgba(0,0,0,.08); }
.sb .rz { position:absolute; top:0; right:0; bottom:0; width:.5rem; cursor:ew-resize; background:rgba(0,0,0,.08); }
.wq-sched-tray { display:flex; flex-wrap:wrap; gap:.35rem; align-items:flex-start; }
.wq-sched-tray .it { width:11rem; margin:0; border-left-color:var(--tblr-secondary); }
.wq-sg.print { color:#000; background:#fff; border-color:#999; min-width:0; }
.wq-sg.print .wq-sg-head > div { background:#eee; color:#000; border-color:#999; }
.wq-sg.print .wq-sg-head > div.today, .wq-sg.print .cell.today { background:#cfe2ff; }
.wq-sg.print .wq-sg-row { border-color:#999; grid-auto-rows:minmax(2.1rem, auto); }
.wq-sg.print .op { background:#eee; color:#000; }
.wq-sg.print .wkend { background:#f6f6f6; }
.wq-sg.print .cell { border-color:#999; min-height:2.1rem; }
.wq-sg.print .sb { background:#f1f6fd; color:#000; border-color:#aac; border-left-color:#206bc4; cursor:default; padding:.05rem .3rem; }
.wq-sg.print .sb.late { border-left-color:#d63939; background:#fdf0f0; }
.wq-sg.print .sb.assist { border-left-color:#2fb344; background:#e6f6ea; }
.wq-sg.print .sb.note { border-left-color:#f59f00; background:#fff6d6; }
.wq-sg.print .sb small { color:#444; }
.wq-cal { width:100%; border-collapse:collapse; table-layout:fixed; font-size:.72rem; }
.wq-cal th, .wq-cal td { border:1px solid #999; padding:.2rem .3rem; vertical-align:top; }
.wq-cal th { background:#eee; text-align:center; color:#000; }
.wq-cal th.wkend, .wq-cal td.wkend { background:#f6f6f6; }
.wq-cal th.today { background:#cfe2ff; }
.wq-cal td.op { width:7rem; font-weight:600; background:#eee; color:#000; }
.wq-cal tbody td { height:3.2rem; }
.wq-cal .it { margin-bottom:.2rem; padding:.1rem .25rem; border-left:3px solid #206bc4; background:#f1f6fd; color:#000; break-inside:avoid; }
.wq-cal .it.late { border-left-color:#d63939; background:#fdf0f0; }
.wq-cal .it.locked { border-left-style:dashed; opacity:.75; }
.wq-cal .it small { display:block; color:#444; }
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
        Drag a card or bar to hand it to another operator — hold <kbd>Ctrl</kbd> while dropping to share it instead.
        Everyone else on a shared step stays put, and each operator has their own dates on the schedule.
        <strong>Right-click</strong> anything for work order details, dates and operators.
      </div>
      <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
        <div class="btn-group btn-group-sm" id="wq-view" title="Board or two-week schedule">
          <button type="button" class="btn" data-view="board" onclick="wqSetView('board')"><i class="ti ti-layout-kanban me-1"></i>Board</button>
          <button type="button" class="btn active" data-view="schedule" onclick="wqSetView('schedule')"><i class="ti ti-calendar-week me-1"></i>Schedule</button>
        </div>
        <div class="btn-group btn-group-sm" id="wq-group" title="How the board is grouped">
          <button type="button" class="btn active" data-group="operator" onclick="wqSetGroup('operator')"><i class="ti ti-user me-1"></i>By operator</button>
          <button type="button" class="btn" data-group="job" onclick="wqSetGroup('job')"><i class="ti ti-briefcase me-1"></i>By job</button>
          <button type="button" class="btn" data-group="step" onclick="wqSetGroup('step')"><i class="ti ti-list-details me-1"></i>By step</button>
        </div>
        <select id="wq-job" class="form-select form-select-sm" style="width:auto" onchange="wqOnJobChange()" data-sort>
          <option value="">All jobs</option>
        </select>
        <select id="wq-wo" class="form-select form-select-sm" style="width:auto" onchange="loadBoard()" data-sort>
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
        <button class="btn btn-sm btn-outline-secondary ms-auto" onclick="wqOpenCalendar()">
          <i class="ti ti-printer me-1"></i>Print calendar
        </button>
        <button class="btn btn-sm btn-outline-primary" onclick="wqAssignOpen()" data-permission="fabrication.work-orders.edit">
          <i class="ti ti-users-group me-1"></i>Assign operators
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
      <div id="wq-sched" style="display:none">
        <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
          <button class="btn btn-sm btn-outline-secondary" onclick="wqSchedShift(-7)" title="Back a week"><i class="ti ti-chevron-left"></i></button>
          <input type="date" id="wq-sched-start" class="form-control form-control-sm" style="width:auto" onchange="renderBoard()">
          <button class="btn btn-sm btn-outline-secondary" onclick="wqSchedShift(7)" title="Forward a week"><i class="ti ti-chevron-right"></i></button>
          <button class="btn btn-sm btn-outline-secondary" onclick="wqSchedShift(0)">This week</button>
          <label class="form-check form-switch mb-0 ms-2"><input class="form-check-input" type="checkbox" id="wq-sched-weekends" onchange="renderBoard()"><span class="form-check-label small">Include weekends</span></label>
          <span class="text-secondary small ms-auto">Drag a bar to move it (snaps to 2h quarter-days) or its edges to resize; each operator's bar moves on its own. Ctrl-drop on another row shares the work order.</span>
        </div>
        <div id="wq-sched-body" class="wq-sched-wrap"></div>
      </div>
    </div>
  </div>
</div>

<div id="wq-cal-overlay" class="wq-overlay" style="display:none; overflow:auto;">
  <div class="card" style="width:min(1400px, 96vw); max-height:94vh; display:flex; flex-direction:column;">
    <div class="card-header d-print-none gap-2 flex-wrap">
      <h3 class="card-title">Two-week calendar</h3>
      <label class="small">Starting <input type="date" id="wq-cal-start" class="form-control form-control-sm d-inline-block" style="width:auto" onchange="wqRenderCalendar()"></label>
      <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="wq-cal-weekends" onchange="wqRenderCalendar()"><span class="form-check-label small">Include weekends</span></label>
      <button class="btn btn-primary btn-sm ms-auto" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
      <button type="button" class="btn-close" onclick="wqCloseCalendar()"></button>
    </div>
    <div class="card-body" style="overflow:auto;" id="wq-cal-body"></div>
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
let wqSchedDrag = null;
let wqView = 'schedule';
let wqJobs = [];
let wqWOs = [];

// Job names and release numbers are what people go by; the job number is only a trailing reference
const jobLabel = (name, num) => name ? (num ? `${name} (${num})` : name) : (num || 'No job');
const woLabel = (name, token) => name ? `${name} – ${token ?? ''}`.replace(/ – $/, '') : String(token ?? '');

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
    const hay = `${c.name} ${c.release_label || ''} ${(c.elevation_tags || [c.elevation_tag]).join(' ')} ${c.job_name || ''}`.toLowerCase();
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
  if (wqView !== 'schedule') q.set('view', wqView);
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
      .map(j => `<option value="${j.id}">${esc(jobLabel(j.job_name, j.job_number))}</option>`)
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
    list.map(w => `<option value="${w.id}">${esc(woLabel(w.job?.job_name, w.release_token ?? w.release_label))}</option>`).join('');
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
    if (wqDragStageId != null || wqSchedDrag || (document.activeElement && board.contains(document.activeElement))) return;
  }
  try {
    const r = await API('/work-queue' + wqQueryString());
    wqData = await r.json();
    renderBoard();
  } catch (e) { console.error(e); }
}

// Job-level cards cover several elevations; list the tags on hover
function elevSummary(c) {
  if (!(c.elevation_count > 1)) return ` · ${esc(c.elevation_tag)}`;
  return ` · <span title="${esc((c.elevation_tags || []).join(', '))}">${c.elevation_count} elevations</span>`;
}

// Elevations with no steps: nothing to assign or tick off, just a Complete button
function stagelessCardHtml(c, prio, due) {
  return `<div class="wq-card${isPast(c.due_date) ? ' overdue' : ''}" data-wo="${c.work_order_id}" data-elevs="${c.elevation_ids}">
    <div class="d-flex align-items-start gap-1">
      <div class="flex-fill"><strong>${esc(woLabel(c.job_name, c.release_token ?? c.release_label))}</strong>
        <span class="text-secondary small">${elevSummary(c).replace(/^ · /, '· ')}</span></div>
      <button class="btn btn-sm btn-success py-0" data-permission="fabrication.work-orders.edit"
          onclick="event.stopPropagation(); wqCompleteStageless(this, [${c.elevation_ids}])"><i class="ti ti-check"></i> Complete</button>
    </div>
    <div class="wq-meta">
      ${prio ? `<span class="text-secondary">${prio}</span>` : ''}
      ${due || ''}
      ${c.date_requested ? `<span class="text-secondary">need ${esc(c.date_requested)}</span>` : ''}
    </div>
  </div>`;
}

async function wqCompleteStageless(btn, elevationIds) {
  btn.disabled = true;
  try {
    for (const id of elevationIds) {
      const r = await API(`/elevations/${id}/complete-all-stages`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: '{}',
      });
      if (!r.ok) throw new Error((await r.json().catch(() => ({}))).error || 'Failed to complete');
    }
    loadBoard();
  } catch (e) {
    btn.disabled = false;
    alert(e.message);
  }
}

const wqMoreBtn = '<button type="button" class="btn btn-sm btn-ghost-secondary p-0 px-1 wq-more" title="More — same as right-click"><i class="ti ti-dots-vertical"></i></button>';

function cardHtml(c, opId) {
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
  // Who else is on this step, so a shared card reads the same in every column it appears in
  const others = (c.assignee_ids || []).filter(id => id !== opId).map(wqUser);
  const shared = opId && others.length
    ? `<span class="text-secondary small" title="Shared with ${esc(others.map(u => u.name).join(', '))}"><i class="ti ti-users"></i> with</span> `
      + others.map(u => `<span class="badge bg-purple-lt text-purple" title="${esc(u.name)}">${esc(u.initials || u.name)}</span>`).join(' ') : '';
  if (c.stageless) return stagelessCardHtml(c, prio, due);
  return `<div class="wq-card${c.gated ? ' locked' : ''}${isPast(c.due_date) ? ' overdue' : ''}" draggable="true" data-stage="${c.stage_id}" data-wo="${c.work_order_id}"
      ondragstart="wqDragStart(event, [${c.stage_ids}])" ondragend="wqDragEnd(event)">
    <div class="d-flex align-items-start gap-1">
      <div class="flex-fill"><strong>${esc(c.name)}</strong>${active}${lock}</div>
      ${wqMoreBtn}
    </div>
    <div class="text-secondary">${esc(woLabel(c.job_name, c.release_token ?? c.release_label))}${elevSummary(c)}</div>
    <div class="wq-meta">
      <span class="text-secondary">${prio}</span>
      ${due}
      ${c.date_requested ? `<span class="text-secondary">need ${esc(c.date_requested)}</span>` : ''}
    </div>
    ${shared ? `<div class="wq-meta">${shared}</div>` : ''}
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
    <div>${shown.map(c => cardHtml(c, opId)).join('') || '<div class="text-secondary small text-center py-2">—</div>'}</div>
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
  cards.forEach(c => { const sid = c.stage_id ?? Infinity; if (!first.has(c.name) || sid < first.get(c.name)) first.set(c.name, sid); });
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
  const lead = c.elevation_count > 1 ? `${c.elevation_count} elevations` : c.elevation_tag;
  const where = esc(c.release_token ?? c.release_label);
  if (c.stageless) return stagelessCardHtml(c, '', '');
  return `<div class="wq-card static${c.gated ? ' locked' : ''}${isPast(c.due_date) ? ' overdue' : ''}" data-stage="${c.stage_id}" data-wo="${c.work_order_id}">
    <div class="d-flex align-items-start gap-1">
      <div class="flex-fill"><strong>${esc(lead)}</strong>${active}${lock}
        <span class="text-secondary small ms-1">${where}</span></div>
      ${wqMoreBtn}
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
  const jobTitle = c => jobLabel(c.job_name, c.job_number);
  const prio = list => Math.min(...list.map(c => c.priority ?? Infinity));
  const cols = [];

  if (wqGroup === 'job') {
    const jobs = [...by(cards, jobKey).values()].sort((a, b) =>
      prio(a) - prio(b) || String(a[0].job_name || '').localeCompare(String(b[0].job_name || '')));
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
  document.querySelectorAll('#wq-view [data-view]').forEach(b => b.classList.toggle('active', b.dataset.view === wqView));
  document.getElementById('wq-group').style.display = wqView === 'board' ? '' : 'none';
  document.getElementById('wq-sched').style.display = wqView === 'schedule' ? '' : 'none';
  if (wqView === 'schedule') {
    board.style.display = 'none';
    document.getElementById('wq-scroll-top').style.display = 'none';
    if (wqData) { wqUpdateStrip(); wqSyncUrl(); wqRenderSchedule(); }
    return;
  }
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
let wqDragFrom = 0;
function wqDragStart(e, stageIds) {
  wqDragStageId = stageIds;
  wqDragFrom = +(e.currentTarget.closest('.wq-col')?.dataset.op || 0);
  e.currentTarget.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function wqDragEnd(e) { e.currentTarget.classList.remove('dragging'); wqDragStageId = null; }
function wqDragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; e.currentTarget.classList.add('drop-hover'); }
function wqDragLeave(e) { e.currentTarget.classList.remove('drop-hover'); }

// Assignment changes go through one endpoint that edits each stage's own assignee set, so moving
// one operator off a shared step never disturbs the others on it.
const wqReassign = (stageIds, body) => wqJson('/work-queue/reassign', 'POST', { stage_ids: stageIds, ...body });
const wqSetWindow = (woId, opId, S, E) => wqJson(`/work-queue/work-orders/${woId}/operators/${opId}/schedule`, 'PUT',
  { start_date: S.d, start_quarter: S.q, end_date: E.d, end_quarter: E.q });

// Drop on another column: that operator takes over from the column the card came from.
// Ctrl/Cmd-drop adds them alongside instead (shared step).
async function wqDrop(e, opId) {
  e.preventDefault();
  e.currentTarget.classList.remove('drop-hover');
  if (wqDragStageId == null) return;
  const stageIds = wqDragStageId, from = wqDragFrom, share = e.ctrlKey || e.metaKey;
  wqDragStageId = null;
  if (opId === from) return;
  try {
    await wqReassign(stageIds, { remove_user_id: share ? null : (from || null), add_user_ids: opId ? [opId] : [] });
  } catch (err) {
    console.error(err);
    alert('Failed to reassign the step.');
  }
  await loadBoard();
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

// ── Printable two-week calendar: operators in rows, dates in columns ─────
// Steps have no dates of their own, so a work order's steps are placed across
// its planned start → planned completion (or on its due date when that's all
// there is). Open work past its end date lands in the first column, in red.
const wqYmd = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

function wqOpenCalendar() {
  const inp = document.getElementById('wq-cal-start');
  if (!inp.value) {
    const d = new Date();
    d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); // Monday of this week
    inp.value = wqYmd(d);
  }
  document.getElementById('wq-cal-overlay').style.display = 'flex';
  document.body.classList.add('wq-printing');
  wqRenderCalendar();
}
function wqCloseCalendar() {
  document.getElementById('wq-cal-overlay').style.display = 'none';
  document.body.classList.remove('wq-printing');
}

// Shared by the printable calendar and the drag-to-schedule view
function wqCalModel(startVal, weekends) {
  const days = [];
  const d0 = new Date(startVal + 'T00:00:00');
  for (let i = 0; i < 14; i++) {
    const d = new Date(d0); d.setDate(d.getDate() + i);
    if (weekends || (d.getDay() !== 0 && d.getDay() !== 6)) days.push(d);
  }
  const first = wqYmd(d0), last = wqYmd(new Date(new Date(d0).setDate(d0.getDate() + 13)));

  // operator → work order → { label, steps[], start, end }, honouring the board's filters
  // A work order whose every step sits with one person doesn't need its steps spelled out on that person's row
  const owners = new Map();
  wqAllCards().forEach(c => {
    const ids = c.assignee_ids?.length ? c.assignee_ids : (c.assigned_to_id ? [c.assigned_to_id] : []);
    const o = owners.get(c.work_order_id) || owners.set(c.work_order_id, new Set()).get(c.work_order_id);
    if (ids.length === 1) o.add(ids[0]); else o.add(ids.length ? 'shared' : 'none');
  });
  const soleOwner = woId => { const o = owners.get(woId); return o && o.size === 1 && ![...o][0].toString().match(/^(shared|none)$/); };

  const rows = [];
  const opsOf = wqWoOperators();
  const addRow = (title, opId, cards, always) => {
    const wos = new Map(), uns = new Map();
    cards.filter(wqCardVisible).forEach(c => {
      // This operator's own window when they have one, else the work order's planned dates
      const win = opId ? wqWin(c.work_order_id, opId) : null;
      const end = win ? win.end_date : (c.planned_completion_date || c.due_date || c.planned_start_date);
      const start = win ? win.start_date : (c.planned_start_date || end);
      const map = end ? wos : uns;
      const w = map.get(c.work_order_id) || map.set(c.work_order_id, {
        woId: c.work_order_id, opId, label: woLabel(c.job_name, c.release_token ?? c.release_label),
        steps: [], stageIds: [], start, end, locked: true,
        others: [...(opsOf.get(c.work_order_id) || [])].filter(id => id !== opId),
        estMin: c.estimated_minutes, due: c.due_date,
        sq: win ? win.start_quarter : (c.planned_start_date ? (c.planned_start_quarter ?? 0) : 0),
        eq: win ? win.end_quarter : (c.planned_completion_date ? (c.planned_completion_quarter ?? 3) : 3),
        allOne: soleOwner(c.work_order_id),
      }).get(c.work_order_id);
      w.steps.push(c.name);
      w.stageIds.push(...c.stage_ids);
      if (!c.gated) w.locked = false;
    });
    const list = [...wos.values()].filter(w => w.end >= first || w.start <= last).map(w => {
      const late = w.end < first;
      return { ...w, late, from: late ? first : (w.start < first ? first : w.start), to: w.end > last ? last : w.end };
    }).filter(w => w.from <= last);
    if (list.length || uns.size || always) rows.push({ title, opId, list, unscheduled: [...uns.values()], always });
  };
  addRow('Unassigned', 0, wqData.unassigned?.stages || []);
  const opCards = new Map((wqData.operators || []).map(o => [o.user.id, o]));
  (wqData.fab_users || []).forEach(u => addRow(u.name, u.id, opCards.get(u.id)?.stages || [], true));
  (wqData.operators || []).filter(o => !(wqData.fab_users || []).some(u => u.id === o.user.id))
    .forEach(o => addRow(o.user.name + ' (inactive)', o.user.id, o.stages || []));
  return { days, first, last, d0, rows };
}

function wqRenderCalendar() {
  if (!wqData) return;
  const startVal = document.getElementById('wq-cal-start').value;
  if (!startVal) return;
  const weekends = document.getElementById('wq-cal-weekends').checked;
  const { days, first, last, d0, rows } = wqCalModel(startVal, weekends);
  const today = wqYmd(new Date());

  const fmt = k => new Date(k + 'T00:00:00').toLocaleDateString([], { month: 'short', day: 'numeric' });
  const wk2 = wqYmd(new Date(d0.getFullYear(), d0.getMonth(), d0.getDate() + 7));
  const wk = d => d.getDay() % 6 === 0 ? 'wkend' : '';

  // One grid per week, stacked, so each prints at a readable width; same bars as the Schedule view
  const opRows = rows.filter(r => r.opId);
  const entries = wqData?.schedule_entries || [];
  const weekTable = wkDays => wqWeekGrid(wkDays, opRows, entries, { interactive: false, extraRows: ['Saw 1', 'Saw 2'] });

  const unassignedList = [...new Set((wqData?.unassigned?.stages || []).filter(wqCardVisible).map(c => woLabel(c.job_name, c.release_token ?? c.release_label) + (c.due_date ? ` (due ${fmt(c.due_date)})` : '')))].join(', ');
  const unscheduled = new Set();
  wqAllCards().filter(wqCardVisible).forEach(c => { if (!(c.planned_completion_date || c.due_date || c.planned_start_date)) unscheduled.add(woLabel(c.job_name, c.release_token ?? c.release_label)); });

  document.getElementById('wq-cal-body').innerHTML = `
    <div class="d-flex justify-content-between mb-2"><strong>Fabrication schedule — ${fmt(first)} to ${fmt(last)}</strong><span class="small">Printed ${new Date().toLocaleDateString()}</span></div>
    ${weekTable(days.filter(d => wqYmd(d) < wk2))}
    <div style="height:.75rem"></div>
    ${weekTable(days.filter(d => wqYmd(d) >= wk2))}
    <div class="small mt-2">Bars span planned start → planned completion in 2-hour blocks (or sit on the due date). <span style="color:#d63939">Red</span> = past its end date; dashed = waiting on an earlier step; green = assist; yellow = note.
    ${unassignedList ? `<br>Unassigned: ${esc(unassignedList)}` : ''}
    ${unscheduled.size ? `<br>No dates set (not shown): ${esc([...unscheduled].join(', '))}` : ''}</div>`;
}

// ── Interactive schedule: same layout as the printed calendar, but cards drag ──
// Dropping on another day shifts the work order's planned start/completion by
// the same number of days (a work order with no planned dates gets a one-day
// span on the drop day). Dropping on another operator's row reassigns that row's
// steps of the work order. Both can happen in one drop.
function wqSetView(v) { wqView = v; renderBoard(); }

const wqDayDiff = (a, b) => Math.round((new Date(b + 'T00:00:00') - new Date(a + 'T00:00:00')) / 86400000);
const wqAddDays = (k, n) => { const d = new Date(k + 'T00:00:00'); d.setDate(d.getDate() + n); return wqYmd(d); };

function wqSchedMonday(d) { d = new Date(d); d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); return wqYmd(d); }
function wqSchedShift(n) {
  const inp = document.getElementById('wq-sched-start');
  inp.value = n === 0 ? wqSchedMonday(new Date()) : wqAddDays(inp.value || wqSchedMonday(new Date()), n);
  renderBoard();
}

// Positions on the schedule are quarter-days (2h of an 8h day): absolute slot = day number * 4 + quarter (0-3)
const wqDayNum = k => Math.round(Date.UTC(+k.slice(0, 4), +k.slice(5, 7) - 1, +k.slice(8, 10)) / 86400000);
const wqAbs = (k, q) => wqDayNum(k) * 4 + q;
const wqFromAbs = a => ({ d: new Date(Math.floor(a / 4) * 86400000).toISOString().slice(0, 10), q: ((a % 4) + 4) % 4 });
// Work content -> schedule length in quarters: hours rounded to the nearest 2h block (24h = 12 quarters = 3 days)
const wqSchedQuarters = est => est ? Math.max(1, Math.round(est / 120)) : 4;
// Last slot of a block of n quarters starting at `start`, stepping over Sat/Sun when weekends are hidden
function wqEndFrom(start, n, skipWeekends) {
  let pos = start, left = n - 1;
  while (left > 0) {
    pos++;
    const g = new Date(Math.floor(pos / 4) * 86400000).getUTCDay();
    if (skipWeekends && (g === 0 || g === 6)) continue;
    left--;
  }
  return pos;
}
const wqCellQuarter = (e, cell) => { const r = cell.getBoundingClientRect(); return Math.min(3, Math.max(0, Math.floor((e.clientX - r.left) / r.width * 4))); };

// One week of the schedule as a grid: operators down the side, multi-day bars across quarter-day positions.
// Interactive (drag / right-click, registering each bar in o.reg) for the board; static for the printout.
function wqWeekGrid(wkDays, opRows, entries, o) {
  const today = wqYmd(new Date());
  const fmt = k => new Date(k + 'T00:00:00').toLocaleDateString([], { month: 'short', day: 'numeric' });
  const wk = d => d.getDay() % 6 === 0 ? 'wkend' : '';
    const keys = wkDays.map(wqYmd), n = keys.length;
    // Visible column holding a date; weekend dates hidden from the grid snap inward
    const colFrom = k => { for (let i = 0; i < n; i++) if (keys[i] >= k) return i; return n; };
    const colTo = k => { for (let i = n - 1; i >= 0; i--) if (keys[i] <= k) return i; return -1; };
    const cols = `grid-template-columns: 8rem repeat(${n}, minmax(0, 1fr))`;
    const head = `<div class="wq-sg-head" style="${cols}"><div>Week of ${fmt(keys[0])}</div>${wkDays.map((d, i) =>
      `<div class="${wk(d)}${keys[i] === today ? ' today' : ''}">${d.toLocaleDateString([], { weekday: 'short' })}<br>${fmt(keys[i])}</div>`).join('')}</div>`;

    const body = opRows.map(r => {
      const bars = [];
      r.list.forEach(w => {
        const s = w.late ? w.from : w.start, e = w.late ? w.from : w.end;
        const withOthers = w.others?.length ? ' · with ' + w.others.map(id => wqUser(id).initials || wqUser(id).name).join(', ') : '';
        bars.push({ kind: 'wo', ref: w, s, e, label: w.label, sub: [...new Set(w.steps)].join(', ') + withOthers + (w.late ? ' · overdue' : ''),
          cls: 'wo' + (w.late ? ' late' : '') + (w.locked ? ' locked' : ''), canResize: !w.late, sq: w.late ? 0 : w.sq, eq: w.late ? 3 : w.eq });
      });
      entries.filter(x => x.user_id === r.opId).forEach(x => bars.push({
        kind: x.work_order_id ? 'assist' : 'note', ref: x, s: x.start_date, e: x.end_date,
        label: x.work_order_id ? x.label : x.note, sub: x.work_order_id ? 'Assist' : '', cls: x.work_order_id ? 'assist' : 'note', canResize: true, sq: x.start_quarter ?? 0, eq: x.end_quarter ?? 3,
      }));
      const placed = bars.map(b => ({ ...b, a: colFrom(b.s < keys[0] ? keys[0] : b.s), b: colTo(b.e > keys[n - 1] ? keys[n - 1] : b.e) }))
        .filter(b => b.a < n && b.b >= 0 && b.a <= b.b);
      // Quarter-day extents inside this week, so back-to-back jobs on one day can share a lane (job order)
      placed.forEach(b => {
        b.qs = keys[b.a] === b.s ? wqAbs(b.s, b.sq) : wqAbs(keys[b.a], 0);
        b.qe = keys[b.b] === b.e ? wqAbs(b.e, b.eq) : wqAbs(keys[b.b], 3);
      });
      placed.sort((x, y) => x.qs - y.qs || y.qe - x.qe);
      const laneEnds = [];
      placed.forEach(b => {
        let l = laneEnds.findIndex(end => end < b.qs);
        if (l < 0) l = laneEnds.push(-Infinity) - 1;
        laneEnds[l] = b.qe; b.lane = l + 1;
      });
      const lanes = Math.max(1, laneEnds.length);
      const cells = [];
      for (let l = 1; l <= lanes; l++) keys.forEach((k, i) => cells.push(
        `<div class="cell ${wk(wkDays[i])}${k === today ? ' today' : ''}" style="grid-row:${l};grid-column:${i + 2}"${!o.interactive ? '' : ` data-op="${r.opId}" data-day="${k}" data-lane="${l}"
          ondragover="wqSchedOver(event)" ondragleave="wqSchedLeave(event)" ondrop="wqSchedDrop(event, ${r.opId}, '${k}')"
          oncontextmenu="wqSchedCellMenu(event, ${r.opId}, '${k}')"`}></div>`));
      const barHtml = placed.map(b => {
        let i = -1;
        if (o.interactive) { o.reg.push({ kind: b.kind, ref: b.ref, keys: keys.slice(b.a, b.b + 1), opId: r.opId }); i = o.reg.length - 1; }
        const handle = !o.interactive ? '' : (b.canResize && b.e <= keys[n - 1] && b.e >= keys[0]
          ? `<span class="rz" draggable="true" title="Drag to change the end" ondragstart="wqSchedResizeStart(event, ${i}, 'end')" ondragend="wqSchedDragEnd(event)"></span>` : '')
          + (b.canResize && keys[b.a] === b.s
          ? `<span class="lz" draggable="true" title="Drag to change the start" ondragstart="wqSchedResizeStart(event, ${i}, 'start')" ondragend="wqSchedDragEnd(event)"></span>` : '');
        // Part-day start/end: inset the bar by the unused quarters (percent margins resolve against the spanned area)
        const nCols = b.b - b.a + 1;
        const inL = keys[b.a] === b.s ? b.sq / 4 / nCols * 100 : 0, inR = keys[b.b] === b.e ? (3 - b.eq) / 4 / nCols * 100 : 0;
        return `<div class="sb ${b.cls}" style="grid-row:${b.lane};grid-column:${b.a + 2} / ${b.b + 3};margin-left:calc(${inL}% + 2px);margin-right:calc(${inR}% + 2px)"${!o.interactive ? '' : ` draggable="true"
          ondragstart="wqSchedDragStart(event, ${i})" ondragend="wqSchedDragEnd(event)" oncontextmenu="wqSchedBarMenu(event, ${i})"`}
          title="${esc(b.label || '')}${b.sub ? ': ' + esc(b.sub) : ''}"><strong>${esc(b.label || '')}</strong>${b.sub ? `<small>${esc(b.sub)}</small>` : ''}${handle}</div>`;
      }).join('');
      return `<div class="wq-sg-row" style="${cols}"><div class="op" style="grid-row:1 / span ${lanes};grid-column:1">${esc(r.title)}</div>${cells.join('')}${barHtml}</div>`;
    }).join('');
    // Blank rows to pencil in by hand (print only)
    const blank = (o.extraRows || []).map(n => `<div class="wq-sg-row" style="${cols}"><div class="op">${esc(n)}</div>${keys.map((k, i) => `<div class="cell ${wk(wkDays[i])}" style="grid-row:1;grid-column:${i + 2}"></div>`).join('')}</div>`).join('');
    return `<div class="wq-sg${o.interactive ? '' : ' print'}">${head}${body}${blank}</div>`;
}

function wqRenderSchedule() {
  const inp = document.getElementById('wq-sched-start');
  if (!inp.value) inp.value = wqSchedMonday(new Date());
  const weekends = document.getElementById('wq-sched-weekends').checked;
  const { days, first, last, d0, rows } = wqCalModel(inp.value, weekends);
  document.getElementById('wq-sched-body').classList.remove('is-dragging');
  const today = wqYmd(new Date());
  const fmt = k => new Date(k + 'T00:00:00').toLocaleDateString([], { month: 'short', day: 'numeric' });
  const wk = d => d.getDay() % 6 === 0 ? 'wkend' : '';
  const wk2 = wqYmd(new Date(d0.getFullYear(), d0.getMonth(), d0.getDate() + 7));
  const entries = wqData?.schedule_entries || [];
  const opRows = rows.filter(r => r.opId);

  // Items are looked up by index on drag start, so no data has to be serialised into the markup
  const reg = [];

  // Left panel: every work order whose steps all sit unassigned
  const pm = new Map();
  (wqData?.unassigned?.stages || []).filter(wqCardVisible).forEach(c => {
    const p = pm.get(c.work_order_id) || pm.set(c.work_order_id, {
      woId: c.work_order_id, opId: 0, label: woLabel(c.job_name, c.release_token ?? c.release_label),
      stageIds: [], steps: [], scopes: [], assigneeIds: [], estMin: c.estimated_minutes, due: c.due_date,
    }).get(c.work_order_id);
    p.stageIds.push(...c.stage_ids);
    p.scopes.push(...(c.stage_scopes || []));
    p.steps.push(c.name);
  });
  const panel = [...pm.values()].sort((a, b) => (a.due || '9999').localeCompare(b.due || '9999'));
  const panelHtml = panel.map(w => {
    reg.push({ kind: 'panel', ref: w, keys: [] });
    const hrs = w.estMin ? `${(w.estMin / 60).toFixed(1)} h` : 'no estimate';
    const overdue = w.due && w.due < today;
    const i = reg.length - 1;
    // How much of the job is still unassigned, by scope
    const all = wqAllCards().filter(c => c.work_order_id === w.woId).reduce((n, c) => n + c.stage_ids.length, 0);
    const scopes = [...new Set(w.scopes)].join(', ');
    return `<div class="sb wo" draggable="true" ondragstart="wqSchedDragStart(event, ${i})" ondragend="wqSchedDragEnd(event)"
        oncontextmenu="wqSchedPanelMenu(event, ${i})" title="${esc(w.label)} — right-click for details, dates and operators"><strong>${esc(w.label)}</strong>
        <small class="${overdue ? 'text-danger' : ''}">${w.due ? 'Due ' + fmt(w.due) : 'No due date'} · ${hrs}</small>
        <small>${w.stageIds.length === all ? 'All steps unassigned' : `${w.stageIds.length} of ${all} steps unassigned`}${scopes ? ' · ' + esc(scopes) : ''}</small></div>`;
  }).join('') || '<div class="text-secondary small">Nothing unassigned.</div>';

  // Assigned work orders that have no dates yet
  const tray = opRows.flatMap(r => r.unscheduled.map(w => ({ r, w })));
  const trayHtml = tray.length ? `<div class="card mb-3"><div class="card-body py-2">
      <div class="subheader mb-1">No dates yet — drag onto a day to schedule</div>
      <div class="wq-sched-tray">${tray.map(({ r, w }) => {
        reg.push({ kind: 'wo', ref: w, keys: [] });
        return `<div class="sb wo" draggable="true" ondragstart="wqSchedDragStart(event, ${reg.length - 1})" ondragend="wqSchedDragEnd(event)" oncontextmenu="wqSchedBarMenu(event, ${reg.length - 1})"><strong>${esc(w.label)}</strong><small>${esc(r.title)}</small></div>`;
      }).join('')}</div></div></div>` : '';

  const weekBlock = wkDays => wqWeekGrid(wkDays, opRows, entries, { reg, interactive: true });

  wqSchedReg = reg;
  document.getElementById('wq-sched-body').innerHTML = `<div class="d-flex gap-3 align-items-start">
      <div class="wq-sched-panel" ondragover="wqSchedOver(event)" ondragleave="wqSchedLeave(event)" ondrop="wqSchedDropPanel(event)"><div class="subheader mb-2">Unassigned (${panel.length}) <span class="text-secondary fw-normal">· drop here to unassign</span></div>${panelHtml}</div>
      <div class="flex-grow-1" style="min-width:0">${trayHtml}
        <div class="wq-sched-wrap">${weekBlock(days.filter(d => wqYmd(d) < wk2))}${weekBlock(days.filter(d => wqYmd(d) >= wk2))}</div>
        <div class="small text-secondary">${fmt(first)} – ${fmt(last)} · <span class="text-danger">Red</span> = past its end date; dashed = waiting on an earlier step; green = assist; yellow = note. Right-click a bar or a day for more.</div>
      </div></div>`;
}
let wqSchedReg = [];

function wqSchedGrabDay(e, it) {
  if (!it.keys.length) return null;
  const r = e.currentTarget.getBoundingClientRect();
  return it.keys[Math.min(it.keys.length - 1, Math.max(0, Math.floor((e.clientX - r.left) / r.width * it.keys.length)))];
}
// Quarter-day slot under the cursor when a bar is picked up
function wqSchedGrabAbs(e, it) {
  if (!it.keys.length) return null;
  const cell = document.elementsFromPoint(e.clientX, e.clientY).find(x => x.classList?.contains('cell') && x.dataset.day);
  return cell ? wqAbs(cell.dataset.day, wqCellQuarter(e, cell)) : wqAbs(it.keys[0], 0);
}
function wqSchedDragStart(e, i) {
  const it = wqSchedReg[i];
  wqSchedDrag = { ...it, mode: 'move', grab: wqSchedGrabAbs(e, it) };
  e.currentTarget.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', String(i)); // Firefox won't start a drag without data
  // Let the drop land on the day cells underneath the bars (after the drag image is captured)
  setTimeout(() => document.getElementById('wq-sched-body').classList.add('is-dragging'), 0);
}
function wqSchedResizeStart(e, i, edge) {
  e.stopPropagation();
  wqSchedDrag = { ...wqSchedReg[i], mode: edge === 'start' ? 'resizeStart' : 'resize' };
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', 'resize');
  setTimeout(() => document.getElementById('wq-sched-body').classList.add('is-dragging'), 0);
}
function wqSchedDragEnd(e) {
  e.currentTarget.classList.remove('dragging');
  document.getElementById('wq-sched-body')?.classList.remove('is-dragging');
  wqSchedClearPreview();
  wqSchedDrag = null;
}
function wqSchedOver(e) {
  if (!wqSchedDrag) return;
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  if (e.currentTarget.dataset.day) wqSchedPreview(e, e.currentTarget);
  else e.currentTarget.classList.add('drop-hover');
}

// Where the dragged item would land if dropped at slot `pos`: { s, e } as absolute quarter slots, or null
function wqSchedSpan(d, pos, skipWk) {
  if (d.mode === 'resizeStart') {
    const e = d.kind === 'wo' ? wqAbs(d.ref.end, d.ref.eq) : wqAbs(d.ref.end_date, d.ref.end_quarter ?? 3);
    return pos > e ? null : { s: pos, e };
  }
  if (d.mode === 'resize') {
    const s = d.kind === 'wo' ? wqAbs(d.ref.start, d.ref.sq) : wqAbs(d.ref.start_date, d.ref.start_quarter ?? 0);
    return pos < s ? null : { s, e: pos };
  }
  if (d.kind === 'assist' || d.kind === 'note') {
    const x = d.ref, delta = d.grab != null ? pos - d.grab : 0;
    return { s: wqAbs(x.start_date, x.start_quarter ?? 0) + delta, e: wqAbs(x.end_date, x.end_quarter ?? 3) + delta };
  }
  const w = d.ref;
  if (d.kind === 'wo' && w.end) {
    const s0 = wqAbs(w.start, w.sq), e0 = wqAbs(w.end, w.eq), delta = pos - (w.late ? e0 : d.grab);
    return { s: s0 + delta, e: e0 + delta };
  }
  return { s: pos, e: wqEndFrom(pos, wqSchedQuarters(w.estMin), skipWk) };
}

// Live preview while dragging: shade exactly the quarter-days the item would cover if dropped here
// and label where it ends. Uses the same maths as the drop.
let wqSchedPrev = '';
function wqSchedClearPreview() {
  wqSchedPrev = '';
  document.querySelectorAll('#wq-sched-body .drop-span').forEach(c => {
    c.classList.remove('drop-span', 'drop-end'); c.removeAttribute('data-tip'); c.style.removeProperty('--f'); c.style.removeProperty('--t');
  });
}
function wqSchedPreview(e, cell) {
  const d = wqSchedDrag, op = cell.dataset.op, lane = cell.dataset.lane;
  const pos = wqAbs(cell.dataset.day, wqCellQuarter(e, cell));
  if (wqSchedPrev === `${op}|${pos}|${lane}`) return;
  wqSchedClearPreview();
  wqSchedPrev = `${op}|${pos}|${lane}`;
  const span = wqSchedSpan(d, pos, !document.getElementById('wq-sched-weekends').checked);
  if (!span) return;
  const S = wqFromAbs(span.s), E = wqFromAbs(span.e);
  let endCell = null;
  document.querySelectorAll(`#wq-sched-body .cell[data-op="${op}"]`).forEach(c => {
    const k = c.dataset.day;
    if (k < S.d || k > E.d) return;
    if (c.dataset.lane !== lane && !(c.parentElement !== cell.parentElement && c.dataset.lane === '1')) return;
    c.classList.add('drop-span');
    c.style.setProperty('--f', (k === S.d ? S.q * 25 : 0) + '%');
    c.style.setProperty('--t', (k === E.d ? (E.q + 1) * 25 : 100) + '%');
    if (!endCell || k > endCell.dataset.day) endCell = c;
  });
  if (endCell) {
    endCell.classList.add('drop-end');
    const len = (span.e - span.s + 1) * 2;
    endCell.dataset.tip = `${len}h · ends ` + new Date(E.d + 'T00:00:00').toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' })
      + (E.q < 3 ? ` after ${(E.q + 1) * 2}h` : '');
  }
}
function wqSchedLeave(e) { e.currentTarget.classList.remove('drop-hover'); }

const wqJson = (url, method, body) => API(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
  .then(r => { if (!r.ok) throw new Error(method + ' ' + url + ' failed'); return r; });

async function wqSchedDrop(e, opId, day) {
  e.preventDefault();
  e.currentTarget.classList.remove('drop-hover');
  document.getElementById('wq-sched-body').classList.remove('is-dragging');
  wqSchedClearPreview();
  if (!wqSchedDrag) return;
  const d = wqSchedDrag;
  wqSchedDrag = null;
  const skipWk = !document.getElementById('wq-sched-weekends').checked;
  const share = e.ctrlKey || e.metaKey;
  const pos = wqAbs(day, wqCellQuarter(e, e.currentTarget));
  const span = wqSchedSpan(d, pos, skipWk);
  if (!span) return;
  const S = wqFromAbs(span.s), E = wqFromAbs(span.e);
  let run; // the write(s) this drop needs, in order

  if (d.mode === 'resizeStart') {
    if (d.kind === 'wo') {
      const w = d.ref;
      if (span.s === wqAbs(w.start, w.sq)) return;
      run = () => wqSetWindow(w.woId, w.opId, S, { d: w.end, q: w.eq });
    } else {
      if (span.s === wqAbs(d.ref.start_date, d.ref.start_quarter ?? 0)) return;
      run = () => wqJson(`/schedule-entries/${d.ref.id}`, 'PATCH', { start_date: S.d, start_quarter: S.q });
    }
  } else if (d.mode === 'resize') {
    if (d.kind === 'wo') {
      const w = d.ref;
      if (span.e === wqAbs(w.end, w.eq)) return;
      run = () => wqSetWindow(w.woId, w.opId, { d: w.start, q: w.sq }, E);
    } else {
      if (span.e === wqAbs(d.ref.end_date, d.ref.end_quarter ?? 3)) return;
      run = () => wqJson(`/schedule-entries/${d.ref.id}`, 'PATCH', { end_date: E.d, end_quarter: E.q });
    }
  } else if (d.kind === 'assist' || d.kind === 'note') {
    const x = d.ref;
    if (opId === x.user_id && span.s === wqAbs(x.start_date, x.start_quarter ?? 0)) return;
    run = () => wqJson(`/schedule-entries/${x.id}`, 'PATCH', { user_id: opId, start_date: S.d, start_quarter: S.q, end_date: E.d, end_quarter: E.q });
  } else {
    // A work order bar (or one dragged in from the unassigned list / no-dates tray). Only this
    // operator's steps and this operator's dates change; anyone else on the work order stays put.
    const w = d.ref;
    const reassign = opId !== w.opId;
    const dated = d.kind === 'wo' && w.end;
    const moved = !dated || span.s !== wqAbs(w.start, w.sq) || span.e !== wqAbs(w.end, w.eq);
    if (!moved && !reassign) return;
    run = async () => {
      // Ctrl-drop on another row shares the steps instead of handing them over
      if (reassign) await wqReassign(w.stageIds, { remove_user_id: share ? null : (w.opId || null), add_user_ids: [opId] });
      // No dates yet: the length comes from the work content. A plain hand-over keeps its dates server-side.
      if (moved || (reassign && share)) await wqSetWindow(w.woId, opId, S, E);
    };
  }
  try {
    await run();
  } catch (err) {
    console.error(err);
    alert('Failed to update the schedule.');
  }
  await loadBoard();
}

// Dropping a bar back on the unassigned list takes that operator off its steps (an assist or note is just removed)
async function wqSchedDropPanel(e) {
  e.preventDefault();
  e.currentTarget.classList.remove('drop-hover');
  document.getElementById('wq-sched-body').classList.remove('is-dragging');
  const d = wqSchedDrag;
  wqSchedDrag = null;
  if (!d || d.mode !== 'move' || d.kind === 'panel') return;
  try {
    if (d.kind === 'wo') {
      // Co-assignees keep the steps; only this row's operator comes off
      await wqReassign(d.ref.stageIds, { remove_user_id: d.ref.opId });
    } else {
      await wqJson(`/schedule-entries/${d.ref.id}`, 'DELETE', {});
    }
  } catch (err) {
    console.error(err);
    alert('Failed to update the schedule.');
  }
  await loadBoard();
}

// ── Right-click menus: notes on a day, assists on a work order ──
function wqSchedMenu(e, items) {
  e.preventDefault();
  e.stopPropagation();
  document.getElementById('wq-sched-ctx')?.remove();
  const m = document.createElement('div');
  m.id = 'wq-sched-ctx';
  m.className = 'dropdown-menu show wq-ctx';
  m.style.cssText = `position:fixed;z-index:1080;left:${e.clientX}px;top:${e.clientY}px;max-height:70vh;overflow:auto`;
  items.forEach(it => {
    if (it.divider) { const hr = document.createElement('div'); hr.className = 'dropdown-divider'; m.appendChild(hr); return; }
    const el = document.createElement(it.header ? 'h6' : 'button');
    el.className = it.header ? 'dropdown-header' : 'dropdown-item ' + (it.cls || '');
    if (it.icon) { const i = document.createElement('i'); i.className = 'ti ti-' + it.icon; el.appendChild(i); }
    el.appendChild(document.createTextNode(it.label));
    if (!it.header) el.onclick = () => { m.remove(); it.fn(); };
    m.appendChild(el);
  });
  document.body.appendChild(m);
  // Keep it on screen
  const r = m.getBoundingClientRect();
  if (r.right > innerWidth - 8) m.style.left = Math.max(8, innerWidth - r.width - 8) + 'px';
  if (r.bottom > innerHeight - 8) m.style.top = Math.max(8, innerHeight - r.height - 8) + 'px';
  const close = ev => { if (!m.contains(ev.target)) { m.remove(); document.removeEventListener('mousedown', close); } };
  setTimeout(() => document.addEventListener('mousedown', close), 0);
}

const wqCanEdit = () => typeof hasPermission !== 'function' || hasPermission('fabrication.work-orders.edit');

// The work-order part of every right-click menu (cards, bars, the unassigned list)
function wqWoMenuItems(woId, extra = []) {
  const c = wqAllCards().find(x => x.work_order_id === woId);
  const items = [
    { header: true, label: c ? woLabel(c.job_name, c.release_token ?? c.release_label) : 'Work order' },
    { label: 'Work order details…', icon: 'file-description', fn: () => wqWoOpen(woId) },
  ];
  if (wqCanEdit()) {
    items.push({ label: 'Set dates…', icon: 'calendar-event', fn: () => wqDatesOpen(woId) });
    if (wqAllCards().some(x => x.work_order_id === woId && !x.stageless)) items.push({ label: 'Assign operators…', icon: 'users', fn: () => wqAssignOpen(woId) });
    items.push(...extra);
  }
  items.push({ divider: true });
  const sel = document.getElementById('wq-wo');
  if (sel.value !== String(woId) && [...sel.options].some(o => o.value === String(woId))) {
    items.push({ label: 'Show only this work order', icon: 'filter', fn: () => { sel.value = String(woId); loadBoard(); } });
  } else if (sel.value === String(woId)) {
    items.push({ label: 'Show all work orders', icon: 'filter-off', fn: () => { sel.value = ''; loadBoard(); } });
  }
  items.push({ label: 'Open in Work Orders', icon: 'external-link', fn: () => window.open('/fabrication/work-orders?wo=' + woId, '_blank') });
  return items;
}

// Board cards: right-click, or the ⋮ button
function wqCardMenu(e, cardEl) {
  const woId = +cardEl.dataset.wo;
  if (!woId) return;
  const stageId = cardEl.dataset.stage ? +cardEl.dataset.stage : null;
  const opId = +(cardEl.closest('.wq-col')?.dataset.op || 0);
  wqSchedMenu(e, wqWoMenuItems(woId, stageId ? [{ label: 'Operators on this step…', icon: 'user-plus', fn: () => wqEditAssignees(stageId, opId) }] : []));
}
document.getElementById('wq-board').addEventListener('contextmenu', e => {
  const card = e.target.closest('.wq-card');
  if (!card || e.target.closest('input, select, textarea')) return;
  wqCardMenu(e, card);
});
document.getElementById('wq-board').addEventListener('click', e => {
  const more = e.target.closest('.wq-more');
  if (more) wqCardMenu(e, more.closest('.wq-card'));
});
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  const menu = document.getElementById('wq-sched-ctx');
  if (menu) { menu.remove(); return; }
  const dyn = [...document.querySelectorAll('.wq-dyn')].pop();
  if (dyn) dyn.remove();
});

async function wqSchedCreate(body) {
  try { await wqJson('/schedule-entries', 'POST', body); } catch (err) { console.error(err); alert('Failed to save.'); }
  await loadBoard();
}
async function wqSchedDelete(id) {
  try { await wqJson(`/schedule-entries/${id}`, 'DELETE', {}); } catch (err) { console.error(err); alert('Failed to delete.'); }
  await loadBoard();
}

function wqSchedPanelMenu(e, i) {
  const w = wqSchedReg[i].ref;
  wqSchedMenu(e, wqWoMenuItems(w.woId, [{ label: 'Assign part of job…', icon: 'layout-list', fn: () => wqScopeOpen(w.woId, w.label, { unassignedOnly: true }) }]));
}

function wqSchedCellMenu(e, opId, day) {
  wqSchedMenu(e, [{ label: 'Add note / time off…', fn: () => {
    const note = (prompt('Note for this day (e.g. Day off, Out early):') || '').trim();
    if (note) wqSchedCreate({ user_id: opId, start_date: day, end_date: day, note });
  } }]);
}

function wqSchedBarMenu(e, i) {
  const it = wqSchedReg[i];
  const day = wqSchedGrabDay(e, it) || it.keys[0];
  const rowUser = it.opId;
  const edit = wqCanEdit();
  if (it.kind === 'note') {
    if (!edit) return;
    wqSchedMenu(e, [
      { label: 'Edit note…', icon: 'pencil', fn: async () => {
        const note = (prompt('Note:', it.ref.note) || '').trim();
        if (!note) return;
        try { await wqJson(`/schedule-entries/${it.ref.id}`, 'PATCH', { note }); } catch (err) { alert('Failed to save.'); }
        await loadBoard();
      } },
      { label: 'Remove', icon: 'trash', cls: 'text-danger', fn: () => wqSchedDelete(it.ref.id) },
    ]);
    return;
  }
  const woId = it.kind === 'assist' ? it.ref.work_order_id : it.ref.woId;
  const items = wqWoMenuItems(woId, it.kind === 'wo'
    ? [{ label: 'Assign part of job to a user…', icon: 'layout-list', fn: () => wqScopeOpen(it.ref.woId, it.ref.label) }] : []);
  if (edit && it.kind === 'assist') items.push({ label: 'Remove assist', icon: 'trash', cls: 'text-danger', fn: () => wqSchedDelete(it.ref.id) });
  if (edit && day) {
    items.push({ divider: true });
    items.push({ header: true, label: `Add a helper for ${new Date(day + 'T00:00:00').toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' })}` });
    (wqData.fab_users || []).filter(u => u.id !== rowUser).forEach(u =>
      items.push({ label: u.name, fn: () => wqSchedCreate({ user_id: u.id, work_order_id: woId, start_date: day, end_date: day }) }));
  }
  wqSchedMenu(e, items);
}

// ── Part-of-a-job assignment: hand some scopes (SF / doors / frames...) or steps to one user ──
function wqScopeOpen(woId, label, opts = {}) {
  // Every board stage of this work order, once each
  const stages = new Map();
  wqAllCards().filter(c => c.work_order_id === woId).forEach(c => c.stage_ids.forEach((id, i) => stages.set(id, {
    id, scope: c.stage_scopes?.[i] || 'Other', step: c.name, names: c.assignee_names || [],
    open: !(c.assignee_ids?.length || c.assigned_to_id),
  })));
  if (!stages.size) { alert('No open steps on this work order.'); return; }
  document.getElementById('wq-scope-overlay')?.remove();
  const el = document.createElement('div');
  el.id = 'wq-scope-overlay';
  el.className = 'wq-overlay';
  el.style.display = 'flex';
  let tab = opts.tab || 'scope';
  const groupsFor = key => {
    const g = new Map();
    stages.forEach(v => {
      const x = g.get(v[key]) || g.set(v[key], { ids: [], names: new Set(), open: 0 }).get(v[key]);
      x.ids.push(v.id); v.names.forEach(n => x.names.add(n)); if (v.open) x.open++;
    });
    return [...g.entries()];
  };
  let list = [];
  const draw = () => {
    const only = el.querySelector('#wq-scope-open')?.checked ?? !!opts.unassignedOnly;
    list = groupsFor(tab === 'scope' ? 'scope' : 'step');
    el.innerHTML = `<div class="card" style="width:min(480px, 94vw); max-height:90vh; display:flex; flex-direction:column">
      <div class="card-header"><h3 class="card-title">Assign part of ${esc(label)}</h3></div>
      <div class="card-body overflow-auto">
        <div class="btn-group btn-group-sm mb-2">
          <button type="button" class="btn ${tab === 'scope' ? 'active' : ''}" data-tab="scope">By scope</button>
          <button type="button" class="btn ${tab === 'step' ? 'active' : ''}" data-tab="step">By step</button>
        </div>
        ${list.map(([name, g], i) => `
          <label class="form-check d-flex align-items-center gap-2 py-1">
            <input class="form-check-input mt-0 wq-scope-chk" type="checkbox" value="${i}" ${opts.unassignedOnly && g.open ? 'checked' : ''}>
            <span class="flex-fill">${esc(name)} <span class="text-secondary small">· ${g.ids.length} step${g.ids.length === 1 ? '' : 's'}</span></span>
            ${g.open ? `<span class="badge bg-red-lt">${g.open} unassigned</span>` : ''}
            <span class="text-secondary small">${g.names.size ? esc([...g.names].join(', ')) : ''}</span>
          </label>`).join('')}
        <label class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" id="wq-scope-open" ${only ? 'checked' : ''}><span class="form-check-label small">Only steps nobody has yet</span></label>
        <div class="subheader mt-3 mb-1">Assign to</div>
        <select class="form-select" id="wq-scope-user" data-sort>${(wqData.fab_users || []).map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('')}</select>
        <div class="text-secondary small mt-2">Replaces whoever is on the chosen steps; the rest of the work order is left as is.</div>
      </div>
      <div class="card-footer d-flex justify-content-end gap-2">
        <button class="btn" id="wq-scope-cancel">Cancel</button>
        <button class="btn btn-primary" id="wq-scope-apply">Assign</button>
      </div></div>`;
    el.querySelectorAll('[data-tab]').forEach(b => b.onclick = () => { opts = { ...opts, unassignedOnly: el.querySelector('#wq-scope-open').checked }; tab = b.dataset.tab; draw(); });
    el.querySelector('#wq-scope-cancel').onclick = () => el.remove();
    el.querySelector('#wq-scope-apply').onclick = async () => {
      const onlyOpen = el.querySelector('#wq-scope-open').checked;
      const ids = [...new Set([...el.querySelectorAll('.wq-scope-chk:checked')].flatMap(c => list[+c.value][1].ids))]
        .filter(id => !onlyOpen || stages.get(id).open);
      if (!ids.length) return;
      try {
        await wqReassign(ids, { set_user_ids: [+el.querySelector('#wq-scope-user').value] });
      } catch (err) { console.error(err); alert('Failed to assign.'); }
      el.remove();
      await loadBoard();
    };
  };
  document.body.appendChild(el);
  draw();
}

// Every card on the board once
function wqAllCards() {
  if (!wqData) return [];
  const cards = [...(wqData.unassigned?.stages || [])];
  (wqData.operators || []).forEach(o => cards.push(...(o.stages || [])));
  // A stage shared by several operators is listed under each of them — count it once
  const seen = new Set();
  return cards.filter(c => { const k = c.stageless ? 'e' + c.elevation_ids.join(',') : c.stage_ids.join(','); return !seen.has(k) && seen.add(k); });
}

// ── Lookups shared by the menus and dialogs ─────────────────────────────
function wqUser(id) {
  return (wqData?.fab_users || []).find(u => u.id === id)
    || (wqData?.operators || []).find(o => o.user.id === id)?.user
    || { id, name: 'Unknown', initials: '?' };
}

// One operator's own planning window on a work order, if they have one
function wqWin(woId, userId) {
  return (wqData?.operator_windows || []).find(w => w.work_order_id === woId && w.user_id === userId) || null;
}

// Every open stage on the board once, with exactly who is on it (a card only carries the union for its step)
function wqStageIndex() {
  const idx = new Map();
  const add = (c, uid) => (c.stage_ids || []).forEach((id, i) => {
    const s = idx.get(id) || idx.set(id, {
      id, woId: c.work_order_id, step: c.name, scope: c.stage_scopes?.[i] || 'Other', users: new Set(),
    }).get(id);
    if (uid) s.users.add(uid);
  });
  (wqData?.unassigned?.stages || []).forEach(c => add(c, 0));
  (wqData?.operators || []).forEach(o => (o.stages || []).forEach(c => add(c, o.user.id)));
  return idx;
}

// work order id → Set of operator ids holding open steps on it
function wqWoOperators() {
  const m = new Map();
  wqStageIndex().forEach(s => { const set = m.get(s.woId) || m.set(s.woId, new Set()).get(s.woId); s.users.forEach(u => set.add(u)); });
  return m;
}

function wqDialog(id, width, html) {
  document.getElementById(id)?.remove();
  const el = document.createElement('div');
  el.id = id;
  el.className = 'wq-overlay wq-dyn';
  el.style.display = 'flex';
  el.innerHTML = `<div class="card" style="width:min(${width}px, 94vw); max-height:90vh; display:flex; flex-direction:column">${html}</div>`;
  el.addEventListener('mousedown', e => { if (e.target === el) el.remove(); });
  document.body.appendChild(el);
  return el;
}

const wqFmtDay = k => k ? new Date(k + 'T00:00:00').toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' }) : '—';
const wqHrs = m => m == null ? '—' : `${(m / 60).toFixed(1)} h`;

// ── Work order details (read-only summary, straight from the work order) ──
const WQ_STAGE_CLS = { complete: 'bg-green-lt text-green', in_progress: 'bg-yellow-lt text-yellow', blocked: 'bg-red-lt text-red', on_hold: 'bg-orange-lt text-orange', not_required: 'bg-secondary-lt text-secondary text-decoration-line-through', pending: 'bg-secondary-lt text-secondary' };

async function wqWoOpen(woId) {
  const el = wqDialog('wq-wo-overlay', 960, '<div class="card-body text-center py-5"><div class="spinner-border" role="status"></div></div>');
  const card = el.querySelector('.card');
  let wo;
  try {
    const r = await API('/work-orders/' + woId, { headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error('load failed');
    wo = await r.json();
  } catch (err) {
    console.error(err);
    card.innerHTML = '<div class="card-body"><div class="text-danger mb-3">Could not load this work order.</div><button class="btn" data-close>Close</button></div>';
    card.querySelector('[data-close]').onclick = () => el.remove();
    return;
  }
  if (!el.isConnected) return;

  const edit = wqCanEdit();
  const title = woLabel(wo.job?.job_name, wo.release_token);
  const overdue = isPast(wo.due_date) && wo.status !== 'complete';
  const fact = (k, v) => `<div><div class="k">${k}</div><div>${v}</div></div>`;
  const ops = [...(wqWoOperators().get(woId) || [])].map(wqUser);
  const opRows = ops.map(u => {
    const w = wqWin(woId, u.id);
    const from = w ? w.start_date : wo.planned_start_date, to = w ? w.end_date : wo.planned_completion_date;
    return `<span class="badge bg-blue-lt text-blue me-1 mb-1" title="${esc(u.name)}">${esc(u.name)}${to ? ` · ${esc(wqFmtDay(from || to))} → ${esc(wqFmtDay(to))}` : ''}</span>`;
  }).join('') || '<span class="text-secondary">Nobody yet</span>';

  const steps = (wo.steps || []).map(s =>
    `<span class="badge wq-stage-pill ${WQ_STAGE_CLS[s.status] || WQ_STAGE_CLS.pending}" title="${esc(s.status.replace('_', ' '))}${s.completed_by_name ? ' · ' + esc(s.completed_by_name) : ''}">${esc(s.name)}</span>`).join('');

  const elevs = (wo.elevations || []).map(e => {
    const stages = (e.stages || []).map(s => {
      const who = (s.assignee_names || []).join(', ');
      const done = s.status === 'complete' && s.completed_by_name ? ` · done by ${s.completed_by_name}` : '';
      return `<span class="badge wq-stage-pill ${WQ_STAGE_CLS[s.status] || WQ_STAGE_CLS.pending}" title="${esc(s.status.replace('_', ' '))}${who ? ' · ' + esc(who) : ''}${esc(done)}">${esc(s.name)}${who && !['complete', 'not_required'].includes(s.status) ? ` <span class="fw-normal">· ${esc(who)}</span>` : ''}</span>`;
    }).join('') || '<span class="text-secondary small">No steps</span>';
    return `<tr class="${e.date_completed ? 'text-secondary' : ''}">
      <td class="text-nowrap">${e.date_completed ? '<i class="ti ti-check text-green me-1"></i>' : ''}<strong>${esc(e.elevation_tag || '—')}</strong></td>
      <td>${esc(e.elevation_type?.name || '')}</td>
      <td class="text-end">${e.quantity ?? ''}</td>
      <td class="text-nowrap">${e.date_completed ? 'done ' + esc(wqFmtDay(e.date_completed)) : esc(wqFmtDay(e.date_requested))}</td>
      <td>${stages}</td>
    </tr>`;
  }).join('');

  const drawings = (wo.drawings || []).map(d =>
    `<a class="btn btn-sm btn-outline-secondary me-1 mb-1" href="${esc(d.download_url)}" target="_blank" rel="noopener"><i class="ti ti-file me-1"></i>${esc(d.original_name)}</a>`).join('');
  const blockers = (wo.completion_blockers || []).map(b => `<li>${esc(typeof b === 'string' ? b : (b.message || JSON.stringify(b)))}</li>`).join('');

  card.innerHTML = `
    <div class="card-header">
      <div>
        <h3 class="card-title mb-0">${esc(title)} <span class="badge bg-secondary-lt text-secondary ms-1">${esc(wo.status_label || wo.status || '')}</span>
          ${wo.priority != null ? `<span class="badge bg-blue-lt text-blue ms-1">#${wo.priority}${wo.priority_locked ? ' <i class="ti ti-pin"></i>' : ''}</span>` : ''}</h3>
        <div class="text-secondary small">${esc(wo.release_label || '')}${wo.job?.project_manager ? ' · PM ' + esc(wo.job.project_manager) : ''}${wo.job?.superintendent ? ' · Super ' + esc(wo.job.superintendent) : ''}</div>
      </div>
      <button type="button" class="btn-close ms-auto" data-close></button>
    </div>
    <div class="card-body" style="overflow-y:auto">
      <div class="wq-facts mb-3">
        ${fact('Due', `<span class="${overdue ? 'text-danger fw-bold' : ''}">${esc(wqFmtDay(wo.due_date))}</span>`)}
        ${fact('Planned', wo.planned_completion_date || wo.planned_start_date ? `${esc(wqFmtDay(wo.planned_start_date || wo.planned_completion_date))} → ${esc(wqFmtDay(wo.planned_completion_date))}` : '<span class="text-secondary">Not scheduled</span>')}
        ${fact('Issued', esc(wqFmtDay(wo.date_issued)))}
        ${fact('Estimate', `${wqHrs(wo.estimated_minutes)} <span class="text-secondary small">(${wqHrs(wo.estimated_minutes_remaining)} left)</span>`)}
        ${fact('Elevations', `${(wo.elevations || []).filter(e => e.date_completed).length} of ${(wo.elevations || []).length} complete`)}
        ${fact('Material', esc(wo.material_delivery || '—'))}
      </div>
      <div class="mb-3"><div class="subheader mb-1">Operators on open steps</div>${opRows}</div>
      ${wo.notes ? `<div class="mb-3"><div class="subheader mb-1">Notes</div><div style="white-space:pre-wrap">${esc(wo.notes)}</div></div>` : ''}
      ${steps ? `<div class="mb-3"><div class="subheader mb-1">Job steps</div>${steps}</div>` : ''}
      ${blockers ? `<div class="mb-3"><div class="subheader mb-1">Before it can be completed</div><ul class="mb-0 small">${blockers}</ul></div>` : ''}
      <div class="subheader mb-1">Elevations</div>
      <div class="table-responsive mb-3"><table class="table table-sm table-vcenter mb-0">
        <thead><tr><th>Tag</th><th>Type</th><th class="text-end">Qty</th><th>Needed</th><th>Steps</th></tr></thead>
        <tbody>${elevs || '<tr><td colspan="5" class="text-secondary">No elevations.</td></tr>'}</tbody>
      </table></div>
      ${drawings ? `<div class="subheader mb-1">Drawings</div><div>${drawings}</div>` : ''}
    </div>
    <div class="card-footer d-flex flex-wrap gap-2">
      ${edit ? `<button class="btn btn-outline-primary" data-act="dates"><i class="ti ti-calendar-event me-1"></i>Set dates</button>
      <button class="btn btn-outline-primary" data-act="assign"><i class="ti ti-users me-1"></i>Assign operators</button>` : ''}
      <a class="btn btn-outline-secondary ms-auto" href="/fabrication/work-orders?wo=${woId}" target="_blank" rel="noopener"><i class="ti ti-external-link me-1"></i>Open in Work Orders</a>
      <button class="btn" data-close>Close</button>
    </div>`;
  card.querySelectorAll('[data-close]').forEach(b => b.onclick = () => el.remove());
  card.querySelector('[data-act="dates"]')?.addEventListener('click', () => { el.remove(); wqDatesOpen(woId); });
  card.querySelector('[data-act="assign"]')?.addEventListener('click', () => { el.remove(); wqAssignOpen(woId); });
}

// ── Dates: due date and planned window; one window per operator when the work order is shared ──
function wqDatesOpen(woId) {
  const c = wqAllCards().find(x => x.work_order_id === woId);
  if (!c) return;
  const ops = [...(wqWoOperators().get(woId) || [])].map(wqUser);
  const split = ops.length > 1;
  const woWin = { s: c.planned_start_date || '', e: c.planned_completion_date || '', sq: c.planned_start_quarter ?? 0, eq: c.planned_completion_quarter ?? 3 };
  const eff = uid => { const w = wqWin(woId, uid); return w ? { s: w.start_date, e: w.end_date, sq: w.start_quarter, eq: w.end_quarter } : { ...woWin }; };

  const el = wqDialog('wq-dates-overlay', 560, `
    <div class="card-header"><div><h3 class="card-title mb-0">Dates</h3><div class="text-secondary small">${esc(woLabel(c.job_name, c.release_token ?? c.release_label))}</div></div>
      <button type="button" class="btn-close ms-auto" data-close></button></div>
    <div class="card-body" style="overflow-y:auto">
      <div class="row g-2 mb-3">
        <div class="col-sm-4"><label class="form-label">Due date</label><input type="date" class="form-control" id="wq-d-due" value="${c.due_date || ''}"></div>
        <div class="col-sm-4"><label class="form-label">${split ? 'Everyone starts' : 'Planned start'}</label><input type="date" class="form-control" id="wq-d-start" value="${split ? '' : woWin.s}"></div>
        <div class="col-sm-4"><label class="form-label">${split ? 'Everyone finishes' : 'Planned finish'}</label><input type="date" class="form-control" id="wq-d-end" value="${split ? '' : woWin.e}"></div>
      </div>
      ${split ? `<div class="subheader mb-1">Per operator</div>
        <div class="text-secondary small mb-2">Each operator has their own dates on the schedule. Fill the two fields above to give everyone the same ones.</div>
        ${ops.map(u => { const w = eff(u.id); return `<div class="row g-2 align-items-center mb-2" data-op="${u.id}">
          <div class="col-4 text-truncate" title="${esc(u.name)}"><span class="badge bg-blue-lt text-blue me-1">${esc(u.initials || '?')}</span>${esc(u.name)}</div>
          <div class="col-4"><input type="date" class="form-control form-control-sm" data-f="s" value="${w.s}"></div>
          <div class="col-4"><input type="date" class="form-control form-control-sm" data-f="e" value="${w.e}"></div>
        </div>`; }).join('')}` : (ops.length ? `<div class="text-secondary small">On ${esc(ops[0].name)}'s row of the schedule.</div>` : '<div class="text-secondary small">Nobody is assigned yet — these dates apply once someone is.</div>')}
    </div>
    <div class="card-footer d-flex align-items-center gap-2">
      <div class="text-danger small flex-fill" id="wq-d-status"></div>
      <button class="btn btn-link" data-close>Cancel</button>
      <button class="btn btn-primary" id="wq-d-save">Save</button>
    </div>`);
  el.querySelectorAll('[data-close]').forEach(b => b.onclick = () => el.remove());
  const $ = q => el.querySelector(q);
  if (split) {
    $('#wq-d-start').onchange = ev => el.querySelectorAll('[data-f="s"]').forEach(i => { i.value = ev.target.value; });
    $('#wq-d-end').onchange = ev => el.querySelectorAll('[data-f="e"]').forEach(i => { i.value = ev.target.value; });
  }

  $('#wq-d-save').onclick = async () => {
    const status = $('#wq-d-status');
    const due = $('#wq-d-due').value;
    const dueBody = due !== (c.due_date || '') ? { due_date: due || null } : {};
    const writes = [];
    if (!split) {
      let s = $('#wq-d-start').value, e = $('#wq-d-end').value;
      if (s && !e) e = s;
      if (e && !s) s = e;
      if (s > e) { status.textContent = 'The finish is before the start.'; return; }
      const body = { ...dueBody };
      if (s !== woWin.s || e !== woWin.e) {
        Object.assign(body, {
          planned_start_date: s || null, planned_start_quarter: s && s === woWin.s ? woWin.sq : 0,
          planned_completion_date: e || null, planned_completion_quarter: e && e === woWin.e ? woWin.eq : 3,
        });
      }
      if (Object.keys(body).length) writes.push(() => wqJson(`/work-orders/${woId}`, 'PATCH', body));
    } else {
      if (Object.keys(dueBody).length) writes.push(() => wqJson(`/work-orders/${woId}`, 'PATCH', dueBody));
      for (const row of el.querySelectorAll('[data-op]')) {
        const uid = +row.dataset.op, was = eff(uid);
        let s = row.querySelector('[data-f="s"]').value, e = row.querySelector('[data-f="e"]').value;
        if (s && !e) e = s;
        if (e && !s) s = e;
        if (!s) { if (was.s || was.e) { status.textContent = `Give ${wqUser(uid).name} a start and finish (dates can be moved, not cleared, here).`; return; } continue; }
        if (s > e) { status.textContent = `${wqUser(uid).name}: the finish is before the start.`; return; }
        // Untouched rows are left alone; the server pins them where they are when someone else's dates change
        if (s === was.s && e === was.e) continue;
        writes.push(() => wqSetWindow(woId, uid, { d: s, q: s === was.s ? was.sq : 0 }, { d: e, q: e === was.e ? was.eq : 3 }));
      }
    }
    if (!writes.length) { el.remove(); return; }
    $('#wq-d-save').disabled = true;
    try {
      for (const w of writes) await w(); // in order: each operator write re-derives the work order's envelope
      el.remove();
    } catch (err) {
      console.error(err);
      status.textContent = 'Failed to save the dates.';
      $('#wq-d-save').disabled = false;
    }
    await loadBoard();
  };
}

// ── Assign operators: one work order, steps down the side, operators across ──
// Tick = on every elevation's stage of that step; dash = on some of them. Only the boxes you change are written.
function wqAssignOpen(woId) {
  const wos = new Map();
  wqAllCards().filter(c => !c.stageless && c.work_order_id).forEach(c => {
    if (!wos.has(c.work_order_id)) wos.set(c.work_order_id, woLabel(c.job_name, c.release_token ?? c.release_label));
  });
  if (!wos.size) { alert('No open steps on the board to assign.'); return; }
  woId = +woId || +document.getElementById('wq-wo').value || 0;
  if (!wos.has(woId)) woId = wos.keys().next().value;

  const el = wqDialog('wq-assign-overlay', 860, '');
  const card = el.querySelector('.card');
  let steps = [];

  const draw = () => {
    const mine = [...wqStageIndex().values()].filter(s => s.woId === woId);
    const byStep = new Map();
    mine.forEach(s => (byStep.get(s.step) || byStep.set(s.step, []).get(s.step)).push(s));
    steps = [...byStep.entries()].map(([name, list]) => ({ name, list, first: Math.min(...list.map(s => s.id)) })).sort((a, b) => a.first - b.first);
    // Active fab users, plus anyone inactive still holding one of these steps
    const users = [...(wqData.fab_users || [])];
    mine.forEach(s => s.users.forEach(uid => { if (!users.some(u => u.id === uid)) users.push(wqUser(uid)); }));
    const gated = new Set(wqAllCards().filter(c => c.work_order_id === woId && c.gated).map(c => c.name));

    card.innerHTML = `
      <div class="card-header gap-2">
        <h3 class="card-title">Assign operators</h3>
        <select class="form-select form-select-sm" id="wq-a-wo" style="width:auto; max-width:60%">${[...wos.entries()].sort((a, b) => a[1].localeCompare(b[1])).map(([id, label]) => `<option value="${id}" ${id === woId ? 'selected' : ''}>${esc(label)}</option>`).join('')}</select>
        <button type="button" class="btn-close ms-auto" data-close></button>
      </div>
      <div class="card-body p-0" style="overflow:auto">
        <table class="table table-sm table-hover wq-matrix mb-0">
          <thead><tr><th>Step</th>${users.map(u => `<th data-col="${u.id}" title="${esc(u.name)} — click to tick or clear the whole column">${esc(u.initials || u.name)}<div class="fw-normal small text-secondary">${esc(u.name.split(' ')[0])}</div></th>`).join('')}</tr></thead>
          <tbody>${steps.map((st, i) => `<tr>
            <td><strong>${esc(st.name)}</strong> <span class="text-secondary small">· ${st.list.length} elevation${st.list.length === 1 ? '' : 's'}</span>${gated.has(st.name) ? ' <i class="ti ti-lock text-secondary" title="Waiting on an earlier step"></i>' : ''}</td>
            ${users.map(u => {
              const n = st.list.filter(s => s.users.has(u.id)).length;
              const init = n === 0 ? 'none' : n === st.list.length ? 'all' : 'some';
              return `<td><input type="checkbox" class="form-check-input m-0" data-step="${i}" data-user="${u.id}" data-init="${init}" ${init === 'all' ? 'checked' : ''}></td>`;
            }).join('')}
          </tr>`).join('') || `<tr><td colspan="${users.length + 1}" class="text-secondary">No open steps on this work order.</td></tr>`}</tbody>
        </table>
      </div>
      <div class="card-footer d-flex align-items-center gap-2">
        <div class="text-secondary small flex-fill" id="wq-a-status">Tick several operators on a step to share it — it shows in each of their queues, and whoever completes it clears it for all.</div>
        <button class="btn btn-link" data-scope>By scope…</button>
        <button class="btn btn-link" data-close>Cancel</button>
        <button class="btn btn-primary" id="wq-a-apply">Apply</button>
      </div>`;
    card.querySelectorAll('input[data-init="some"]').forEach(cb => { cb.indeterminate = true; });
    card.querySelectorAll('[data-close]').forEach(b => b.onclick = () => el.remove());
    card.querySelector('#wq-a-wo').onchange = ev => { woId = +ev.target.value; draw(); };
    card.querySelector('[data-scope]').onclick = () => { el.remove(); wqScopeOpen(woId, wos.get(woId)); };
    card.querySelectorAll('th[data-col]').forEach(th => th.onclick = () => {
      const boxes = [...card.querySelectorAll(`input[data-user="${th.dataset.col}"]`)];
      const on = !boxes.every(b => b.checked && !b.indeterminate);
      boxes.forEach(b => { b.checked = on; b.indeterminate = false; });
    });
    card.querySelector('#wq-a-apply').onclick = apply;
  };

  const apply = async () => {
    const jobs = [];
    steps.forEach((st, i) => {
      const adds = [], removes = [];
      card.querySelectorAll(`input[data-step="${i}"]`).forEach(cb => {
        const now = cb.indeterminate ? 'some' : cb.checked ? 'all' : 'none';
        if (now === cb.dataset.init) return;
        (now === 'all' ? adds : removes).push(+cb.dataset.user);
      });
      const ids = st.list.map(s => s.id);
      if (adds.length) jobs.push(() => wqReassign(ids, { add_user_ids: adds }));
      removes.forEach(uid => jobs.push(() => wqReassign(ids, { remove_user_id: uid })));
    });
    if (!jobs.length) { el.remove(); return; }
    const btn = card.querySelector('#wq-a-apply'), status = card.querySelector('#wq-a-status');
    btn.disabled = true;
    status.textContent = 'Applying…';
    try {
      for (const j of jobs) await j();
      el.remove();
    } catch (err) {
      console.error(err);
      status.textContent = 'Failed to apply — refresh and check what changed.';
      btn.disabled = false;
    }
    await loadBoard();
  };

  draw();
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
  const cards = wqAllCards().filter(c => !c.stageless && c.business_job_id === jobId && (!wo || String(c.work_order_id) === wo));
  if (!cards.length) return;
  const order = wqStepOrder(cards);
  const items = order.map(n => {
    const g = cards.filter(c => c.name === n);
    return { key: n, label: n, count: g.length, ready: g.filter(c => !c.gated).length };
  });
  const j = cards[0];
  wqGrp = { mode: 'job', jobId, items };
  wqGrpShow(`Assign ${jobLabel(j.job_name, j.job_number)}`,
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
    label: jobLabel(g[0].job_name, g[0].job_number),
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
let wqAsStageIds = null;

function wqEditAssignees(stageId, opId) {
  // The card as it appears in the column it was opened from, then the same step across every column
  const col = opId ? (wqData.operators || []).find(o => o.user.id === opId)?.stages : null;
  const card = (col || []).find(c => c.stage_id === stageId) || wqAllCards().find(c => c.stage_id === stageId);
  if (!card) return;
  const idx = wqStageIndex();
  wqAsStageIds = [...idx.values()].filter(s => s.woId === card.work_order_id && s.step === card.name).map(s => s.id);
  if (!wqAsStageIds.length) wqAsStageIds = card.stage_ids;
  document.getElementById('wq-as-name').textContent = `${card.name} · ${woLabel(card.job_name, card.release_token ?? card.release_label)}`;
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
  wqAsStageIds = null;
}

async function wqSaveAssignees() {
  if (wqAsStageIds == null) return;
  const ids = [...document.querySelectorAll('#wq-as-list input:checked')].map(i => parseInt(i.value));
  const status = document.getElementById('wq-as-status');
  status.textContent = 'Saving…';
  try {
    await wqReassign(wqAsStageIds, { set_user_ids: ids });
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
  if (u.get('view') === 'board') wqView = 'board';
  await loadBoard();
})();
setInterval(() => loadBoard(true), 30000);
</script>
@endpush
