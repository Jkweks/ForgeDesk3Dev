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
        Every stage grouped by operator. Drag a card to reassign it — <span class="text-danger">locked</span> stages
        (waiting on an earlier step) can be moved too, so work can be planned ahead.
      </div>
      <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
        <div class="btn-group btn-group-sm" id="wq-view" title="Board or two-week schedule">
          <button type="button" class="btn active" data-view="board" onclick="wqSetView('board')"><i class="ti ti-layout-kanban me-1"></i>Board</button>
          <button type="button" class="btn" data-view="schedule" onclick="wqSetView('schedule')"><i class="ti ti-calendar-week me-1"></i>Schedule</button>
        </div>
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
        <button class="btn btn-sm btn-outline-secondary ms-auto" onclick="wqOpenCalendar()">
          <i class="ti ti-printer me-1"></i>Print calendar
        </button>
        <button class="btn btn-sm btn-outline-primary" onclick="wqOpenBulk()">
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
      <div id="wq-sched" style="display:none">
        <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
          <button class="btn btn-sm btn-outline-secondary" onclick="wqSchedShift(-7)" title="Back a week"><i class="ti ti-chevron-left"></i></button>
          <input type="date" id="wq-sched-start" class="form-control form-control-sm" style="width:auto" onchange="renderBoard()">
          <button class="btn btn-sm btn-outline-secondary" onclick="wqSchedShift(7)" title="Forward a week"><i class="ti ti-chevron-right"></i></button>
          <button class="btn btn-sm btn-outline-secondary" onclick="wqSchedShift(0)">This week</button>
          <label class="form-check form-switch mb-0 ms-2"><input class="form-check-input" type="checkbox" id="wq-sched-weekends" onchange="renderBoard()"><span class="form-check-label small">Include weekends</span></label>
          <span class="text-secondary small ms-auto">Drag a bar to move it (snaps to 2h quarter-days), drag its right edge to extend it, or drag an unassigned work order onto an operator's day.</span>
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
let wqSchedDrag = null;
let wqView = 'board';
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
  if (wqView !== 'board') q.set('view', wqView);
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
      ondragstart="wqDragStart(event, [${c.stage_ids}])" ondragend="wqDragEnd(event)">
    <div class="d-flex align-items-start gap-1">
      <div class="flex-fill"><strong>${esc(c.name)}</strong>${active}${lock} ${shared}</div>
      <button class="btn btn-sm btn-ghost-secondary p-0 px-1" title="Edit assignees"
          onclick="event.stopPropagation(); wqEditAssignees(${c.stage_id})"><i class="ti ti-user-plus"></i></button>
    </div>
    <div class="text-secondary">${esc(woLabel(c.job_name, c.release_token ?? c.release_label))}${elevSummary(c)}</div>
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
  const lead = c.elevation_count > 1 ? `${c.elevation_count} elevations` : c.elevation_tag;
  const where = esc(c.release_token ?? c.release_label);
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
function wqDragStart(e, stageIds) {
  wqDragStageId = stageIds;
  e.currentTarget.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function wqDragEnd(e) { e.currentTarget.classList.remove('dragging'); }
function wqDragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; e.currentTarget.classList.add('drop-hover'); }
function wqDragLeave(e) { e.currentTarget.classList.remove('drop-hover'); }

// A card is job-level, so an assignment change is applied to each elevation's stage
async function wqPatchStages(stageIds, body) {
  const rs = await Promise.all(stageIds.map(id => API(`/work-order-stages/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })));
  if (rs.some(r => !r.ok)) throw new Error('assign failed');
}

async function wqDrop(e, opId) {
  e.preventDefault();
  e.currentTarget.classList.remove('drop-hover');
  if (wqDragStageId == null) return;
  const stageIds = wqDragStageId;
  wqDragStageId = null;
  try {
    await wqPatchStages(stageIds, { assigned_to_id: opId || null, log_message: 'Reassigned via Work Queue' });
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
  const addRow = (title, opId, cards, always) => {
    const wos = new Map(), uns = new Map();
    cards.filter(wqCardVisible).forEach(c => {
      const end = c.planned_completion_date || c.due_date || c.planned_start_date;
      const start = c.planned_start_date || end;
      const map = end ? wos : uns;
      const w = map.get(c.work_order_id) || map.set(c.work_order_id, {
        woId: c.work_order_id, opId, label: woLabel(c.job_name, c.release_token ?? c.release_label),
        steps: [], stageIds: [], assigneeIds: c.assignee_ids || [],
        rawStart: c.planned_start_date, rawEnd: c.planned_completion_date, start, end, locked: true,
        estMin: c.estimated_minutes, due: c.due_date,
        sq: c.planned_start_date ? (c.planned_start_quarter ?? 0) : 0, eq: c.planned_completion_date ? (c.planned_completion_quarter ?? 3) : 3,
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
        bars.push({ kind: 'wo', ref: w, s, e, label: w.label, sub: [...new Set(w.steps)].join(', ') + (w.late ? ' · overdue' : ''),
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
        oncontextmenu="wqSchedPanelMenu(event, ${i})" title="${esc(w.label)} — right-click to assign part of it"><strong>${esc(w.label)}</strong>
        <small class="${overdue ? 'text-danger' : ''}">${w.due ? 'Due ' + fmt(w.due) : 'No due date'} · ${hrs}</small>
        <small>${w.stageIds.length === all ? 'All steps unassigned' : `${w.stageIds.length} of ${all} steps unassigned`}${scopes ? ' · ' + esc(scopes) : ''}</small></div>`;
  }).join('') || '<div class="text-secondary small">Nothing unassigned.</div>';

  // Assigned work orders that have no dates yet
  const tray = opRows.flatMap(r => r.unscheduled.map(w => ({ r, w })));
  const trayHtml = tray.length ? `<div class="card mb-3"><div class="card-body py-2">
      <div class="subheader mb-1">No dates yet — drag onto a day to schedule</div>
      <div class="wq-sched-tray">${tray.map(({ r, w }) => {
        reg.push({ kind: 'wo', ref: w, keys: [] });
        return `<div class="sb wo" draggable="true" ondragstart="wqSchedDragStart(event, ${reg.length - 1})" ondragend="wqSchedDragEnd(event)"><strong>${esc(w.label)}</strong><small>${esc(r.title)}</small></div>`;
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
  const tasks = [];
  const pos = wqAbs(day, wqCellQuarter(e, e.currentTarget));
  const span = wqSchedSpan(d, pos, skipWk);
  if (!span) return;
  const S = wqFromAbs(span.s), E = wqFromAbs(span.e);

  if (d.mode === 'resizeStart') {
    if (d.kind === 'wo') {
      const w = d.ref;
      if (span.s === wqAbs(w.start, w.sq)) return;
      tasks.push(wqJson(`/work-orders/${w.woId}`, 'PATCH', { planned_start_date: S.d, planned_start_quarter: S.q, planned_completion_date: w.end, planned_completion_quarter: w.eq }));
    } else {
      if (span.s === wqAbs(d.ref.start_date, d.ref.start_quarter ?? 0)) return;
      tasks.push(wqJson(`/schedule-entries/${d.ref.id}`, 'PATCH', { start_date: S.d, start_quarter: S.q }));
    }
  } else if (d.mode === 'resize') {
    if (d.kind === 'wo') {
      const w = d.ref;
      if (span.e === wqAbs(w.end, w.eq)) return;
      tasks.push(wqJson(`/work-orders/${w.woId}`, 'PATCH', { planned_start_date: w.start, planned_start_quarter: w.sq, planned_completion_date: E.d, planned_completion_quarter: E.q }));
    } else {
      if (span.e === wqAbs(d.ref.end_date, d.ref.end_quarter ?? 3)) return;
      tasks.push(wqJson(`/schedule-entries/${d.ref.id}`, 'PATCH', { end_date: E.d, end_quarter: E.q }));
    }
  } else if (d.kind === 'assist' || d.kind === 'note') {
    const x = d.ref;
    if (opId === x.user_id && span.s === wqAbs(x.start_date, x.start_quarter ?? 0)) return;
    tasks.push(wqJson(`/schedule-entries/${x.id}`, 'PATCH', { user_id: opId, start_date: S.d, start_quarter: S.q, end_date: E.d, end_quarter: E.q }));
  } else {
    const w = d.ref;
    const reassign = opId !== w.opId;
    if (reassign) {
      // A step shared by several operators keeps the others; only this row's operator is swapped
      const ids = (w.assigneeIds || []).filter(id => id !== w.opId);
      if (opId) ids.push(opId);
      tasks.push(wqPatchStages(w.stageIds, ids.length > 1
        ? { assigned_to_ids: ids, log_message: 'Reassigned via Work Queue schedule' }
        : { assigned_to_id: ids[0] || null, log_message: 'Reassigned via Work Queue schedule' }));
    }
    const dated = d.kind === 'wo' && w.end;
    const moved = !dated || span.s !== wqAbs(w.start, w.sq) || span.e !== wqAbs(w.end, w.eq);
    if (!moved && !reassign) return;
    // No dates yet (or dragged in from the unassigned panel): length comes from the work content
    if (moved) tasks.push(wqJson(`/work-orders/${w.woId}`, 'PATCH', { planned_start_date: S.d, planned_start_quarter: S.q, planned_completion_date: E.d, planned_completion_quarter: E.q }));
  }
  try {
    await Promise.all(tasks);
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
      // A step shared by several operators keeps the others
      const w = d.ref, ids = (w.assigneeIds || []).filter(id => id !== w.opId);
      await wqPatchStages(w.stageIds, ids.length > 1
        ? { assigned_to_ids: ids, log_message: 'Unassigned via Work Queue schedule' }
        : { assigned_to_id: ids[0] || null, log_message: 'Unassigned via Work Queue schedule' });
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
  m.className = 'dropdown-menu show';
  m.style.cssText = `position:fixed;z-index:1080;left:${e.clientX}px;top:${e.clientY}px;max-height:60vh;overflow:auto`;
  items.forEach(it => {
    const el = document.createElement(it.header ? 'h6' : 'button');
    el.className = it.header ? 'dropdown-header' : 'dropdown-item ' + (it.cls || '');
    el.textContent = it.label;
    if (!it.header) el.onclick = () => { m.remove(); it.fn(); };
    m.appendChild(el);
  });
  document.body.appendChild(m);
  const close = ev => { if (!m.contains(ev.target)) { m.remove(); document.removeEventListener('click', close); } };
  setTimeout(() => document.addEventListener('click', close), 0);
}

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
  wqSchedMenu(e, [{ label: 'Assign part of job…', fn: () => wqScopeOpen(w.woId, w.label, { unassignedOnly: true }) }]);
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
  const items = [];
  if (it.kind === 'note') {
    items.push({ label: 'Edit note…', fn: async () => {
      const note = (prompt('Note:', it.ref.note) || '').trim();
      if (!note) return;
      try { await wqJson(`/schedule-entries/${it.ref.id}`, 'PATCH', { note }); } catch (err) { alert('Failed to save.'); }
      await loadBoard();
    } });
  } else {
    const woId = it.kind === 'assist' ? it.ref.work_order_id : it.ref.woId;
    items.push({ header: true, label: `Add team member for ${new Date(day + 'T00:00:00').toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' })}` });
    (wqData.fab_users || []).filter(u => u.id !== rowUser).forEach(u =>
      items.push({ label: u.name, fn: () => wqSchedCreate({ user_id: u.id, work_order_id: woId, start_date: day, end_date: day }) }));
  }
  if (it.kind === 'wo') items.push({ label: 'Assign part of job to a user…', fn: () => wqScopeOpen(it.ref.woId, it.ref.label) });
  if (it.kind === 'assist' || it.kind === 'note') items.push({ label: 'Remove', cls: 'text-danger', fn: () => wqSchedDelete(it.ref.id) });
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
        <select class="form-select" id="wq-scope-user">${(wqData.fab_users || []).map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('')}</select>
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
        await wqPatchStages(ids, { assigned_to_id: +el.querySelector('#wq-scope-user').value, log_message: 'Assigned via Work Queue schedule' });
      } catch (err) { console.error(err); alert('Failed to assign.'); }
      el.remove();
      await loadBoard();
    };
  };
  document.body.appendChild(el);
  draw();
}

// ── Bulk assign (all steps of a given name, across a work order) ────────
function wqAllCards() {
  if (!wqData) return [];
  const cards = [...(wqData.unassigned?.stages || [])];
  (wqData.operators || []).forEach(o => cards.push(...(o.stages || [])));
  // A stage shared by several operators is listed under each of them — count it once
  const seen = new Set();
  return cards.filter(c => { const k = c.stage_ids.join(','); return !seen.has(k) && seen.add(k); });
}

function wqOpenBulk() {
  const cards = wqAllCards();
  const byWo = new Map();
  // "Job name – release" (the release token alone, not the job-number prefix)
  cards.forEach(c => {
    if (c.work_order_id && !byWo.has(c.work_order_id)) {
      byWo.set(c.work_order_id, woLabel(c.job_name, c.release_token ?? c.release_label));
    }
  });

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

function wqEditAssignees(stageId) {
  const card = wqAllCards().find(c => c.stage_id === stageId);
  if (!card) return;
  wqAsStageIds = card.stage_ids;
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
  wqAsStageIds = null;
}

async function wqSaveAssignees() {
  if (wqAsStageIds == null) return;
  const ids = [...document.querySelectorAll('#wq-as-list input:checked')].map(i => parseInt(i.value));
  const status = document.getElementById('wq-as-status');
  status.textContent = 'Saving…';
  try {
    await wqPatchStages(wqAsStageIds, { assigned_to_ids: ids, log_message: 'Assignees changed via Work Queue' });
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
  if (u.get('view') === 'schedule') wqView = 'schedule';
  await loadBoard();
})();
setInterval(() => loadBoard(true), 30000);
</script>
@endpush
