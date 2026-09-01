<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Shop Floor — ForgeDesk</title>
  <link href="{{ asset('assets/tabler/css/tabler.min.css') }}" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" rel="stylesheet">
  <style>
    body { background: var(--tblr-bg-surface); font-size: 15px; -webkit-tap-highlight-color: transparent; overflow-x: clip; }

    /* ── Header ── */
    .sf-header {
      position: sticky; top: 0; z-index: 100;
      background: var(--tblr-bg-surface);
      border-bottom: 1px solid var(--tblr-border-color);
      padding: .55rem 1rem;
      display: flex; align-items: center; gap: .75rem;
    }
    .sf-logo { font-weight: 700; font-size: 1.05rem; letter-spacing: .03em; white-space: nowrap; }
    .sf-logo .accent { color: var(--tblr-primary); }
    .sf-logo .sub { font-weight: 400; color: var(--tblr-secondary); font-size: .88rem; margin-left: .3rem; }

    /* ── Filter bar — sticky directly below header ── */
    .sf-filters {
      position: sticky; top: 0; z-index: 90;   /* JS sets real top after measuring header */
      padding: .45rem 1rem;
      display: flex; align-items: center; gap: .4rem; flex-wrap: wrap;
      border-bottom: 1px solid var(--tblr-border-color);
      background: var(--tblr-bg-surface-secondary, var(--tblr-light));
    }
    .sf-filter-label { font-size: .75rem; color: var(--tblr-secondary); white-space: nowrap; }

    /* ── Pills ── */
    .pill {
      padding: .28rem .75rem; border-radius: 20px;
      border: 1.5px solid var(--tblr-border-color);
      background: transparent; font-size: .78rem; cursor: pointer; white-space: nowrap;
      color: var(--tblr-body-color);
      transition: background .1s, border-color .1s, color .1s;
    }
    .pill.active { background: var(--tblr-primary); border-color: var(--tblr-primary); color: #fff; }

    /* ── WO table ── */
    .sf-table { width: 100%; border-collapse: collapse; }
    .sf-table th {
      font-size: .68rem; text-transform: uppercase; letter-spacing: .06em;
      color: var(--tblr-secondary); font-weight: 600;
      padding: .45rem .9rem;
      border-bottom: 2px solid var(--tblr-border-color);
      background: var(--tblr-bg-surface);
      position: sticky; top: 0; z-index: 10; /* JS sets real top after measuring */
    }

    /* ── WO summary row ── */
    .wo-row {
      cursor: pointer;
      transition: background .1s;
    }
    .wo-row:hover { background: var(--tblr-active-bg, rgba(0,0,0,.025)); }
    .wo-row td {
      padding: .75rem .9rem;
      border-bottom: 1px solid var(--tblr-border-color);
      vertical-align: middle;
    }
    .wo-row.expanded td { border-bottom: none; }
    .wo-chevron {
      transition: transform .18s;
      color: var(--tblr-secondary);
    }
    .wo-row.expanded .wo-chevron { transform: rotate(90deg); }

    /* ── Progress bar ── */
    .sf-progress {
      height: 6px; border-radius: 3px;
      background: var(--tblr-border-color);
      overflow: hidden; width: 80px;
    }
    .sf-progress-fill {
      height: 100%; border-radius: 3px;
      background: var(--tblr-success);
      transition: width .3s;
    }

    /* ── Expanded detail panel ── */
    .wo-detail-row { display: none; }
    .wo-detail-row.open { display: table-row; }
    .wo-detail-cell {
      padding: 0 0 .5rem 2.5rem !important;
      border-bottom: 2px solid var(--tblr-border-color);
      background: var(--tblr-bg-surface-secondary, var(--tblr-light));
    }

    /* ── Elevation block ── */
    .elev-block {
      padding: .55rem .75rem .55rem 0;
      display: flex; align-items: flex-start; gap: .75rem; flex-wrap: wrap;
      border-bottom: 1px solid var(--tblr-border-color);
    }
    .elev-block:last-child { border-bottom: none; }
    .elev-meta { min-width: 150px; padding-top: .15rem; }
    .elev-tag { font-weight: 700; font-size: .95rem; }
    .elev-stages { display: flex; gap: .4rem; flex-wrap: wrap; align-items: center; padding-top: .1rem; }

    /* ── Stage buttons (big tap targets) ── */
    .stage-btn {
      display: inline-flex; flex-direction: column; align-items: center;
      padding: .4rem .7rem; border-radius: 8px; border: none;
      cursor: pointer; min-width: 88px;
      transition: filter .1s, transform .07s;
      line-height: 1.3;
    }
    .stage-btn:active { transform: scale(.94); filter: brightness(.88); }
    .stage-btn .sname { font-size: .65rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; opacity: .75; }
    .stage-btn .sstatus { font-size: .78rem; font-weight: 700; }
    .stage-btn.pending     { background: var(--tblr-gray-200, #e9ecef); color: var(--tblr-gray-700, #495057); }
    .stage-btn.in_progress { background: #fff3cd; color: #664d03; }
    .stage-btn.complete    { background: #d1e7dd; color: #0a3622; }
    .stage-btn.blocked     { background: #f8d7da; color: #58151c; }
    .stage-btn.on_hold     { background: #fde3c4; color: #7a3f00; border: 2px dashed #b96a00; }
    [data-bs-theme="dark"] .stage-btn.pending     { background: #343a40; color: #adb5bd; }
    [data-bs-theme="dark"] .stage-btn.in_progress { background: #3d2e00; color: #ffc107; }
    [data-bs-theme="dark"] .stage-btn.complete    { background: #051b11; color: #75b798; }
    [data-bs-theme="dark"] .stage-btn.blocked     { background: #2c0b0e; color: #ea868f; }
    [data-bs-theme="dark"] .stage-btn.on_hold     { background: #3d2503; color: #f5a623; border: 2px dashed #f5a623; }

    /* ── Status summary chips ── */
    .sf-chip {
      font-size: .72rem; padding: .15rem .45rem; border-radius: 4px;
      font-weight: 600; white-space: nowrap;
    }
    .sf-chip.in_progress { background: #fff3cd; color: #664d03; }
    .sf-chip.blocked     { background: #f8d7da; color: #58151c; }
    .sf-chip.on_hold     { background: #fde3c4; color: #7a3f00; }
    [data-bs-theme="dark"] .sf-chip.in_progress { background: #3d2e00; color: #ffc107; }
    [data-bs-theme="dark"] .sf-chip.blocked     { background: #2c0b0e; color: #ea868f; }
    [data-bs-theme="dark"] .sf-chip.on_hold     { background: #3d2503; color: #f5a623; }

    /* ── not_required stage ── */
    .stage-btn.not_required { background: var(--tblr-blue-lt, #dce7f9); color: var(--tblr-blue, #2c5fc3); }
    [data-bs-theme="dark"] .stage-btn.not_required { background: #0d1f3c; color: #7aa7e9; }
    .stage-btn.locked { opacity: .5; }
    .stage-btn.locked:active { transform: none; filter: none; }

    /* ── PIN overlay ── */
    .sf-pin-overlay {
      position: fixed; inset: 0; z-index: 9999;
      background: rgba(0,0,0,.55); backdrop-filter: blur(3px);
      display: flex; align-items: center; justify-content: center;
    }
    .sf-pin-card {
      background: var(--tblr-bg-surface);
      border-radius: 12px; padding: 2rem 2.5rem;
      width: min(380px, 90vw); text-align: center;
      box-shadow: 0 8px 32px rgba(0,0,0,.25);
    }
    .sf-pin-card h3 { margin-bottom: 1.25rem; font-size: 1.2rem; }
    .sf-pin-input {
      width: 100%; font-size: 1.5rem; letter-spacing: .3em; text-align: center;
      border: 2px solid var(--tblr-border-color); border-radius: 8px;
      padding: .6rem 1rem; background: var(--tblr-bg-surface);
      color: var(--tblr-body-color); outline: none; margin-bottom: 1rem;
    }
    .sf-pin-input:focus { border-color: var(--tblr-primary); }
    .sf-pin-error { color: var(--tblr-danger); font-size: .85rem; min-height: 1.2em; margin-top: .5rem; }

    /* ── Misc ── */
    .sf-loading { text-align: center; padding: 5rem; }
    .sf-empty   { text-align: center; padding: 4rem; color: var(--tblr-secondary); }
    @media (max-width: 600px) {
      .hide-sm { display: none !important; }
    }
  </style>
</head>
<body>
<script src="{{ asset('assets/tabler/js/tabler-theme.min.js') }}"></script>
<script src="{{ asset('js/fab-shared.js') }}"></script>

<!-- ── PIN login overlay ── -->
<div class="sf-pin-overlay" id="sf-pin-overlay">
  <div class="sf-pin-card">
    <h3>Shop Floor Login</h3>
    <input class="sf-pin-input" type="password" id="sf-pin-input" inputmode="numeric"
      maxlength="8" placeholder="••••" autocomplete="off"
      onkeydown="if(event.key==='Enter')pinLogin()">
    <button class="btn btn-primary w-100" onclick="pinLogin()">Login</button>
    <div class="sf-pin-error" id="sf-pin-error"></div>
  </div>
</div>

<!-- ── Elevation complete prompt ── -->
<div id="sf-elev-complete-prompt" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:4000;align-items:center;justify-content:center;">
  <div style="background:var(--tblr-bg-surface);border-radius:12px;padding:2rem;width:min(380px,90vw);box-shadow:0 8px 32px rgba(0,0,0,.3);text-align:center">
    <div style="font-size:2.5rem;margin-bottom:.75rem">✅</div>
    <h4 style="margin-bottom:.5rem">All stages done!</h4>
    <p style="color:var(--tblr-secondary);margin-bottom:1.5rem">
      Mark <strong id="sf-elev-cp-tag"></strong> as complete?
      <br><small id="sf-elev-cp-user" style="font-size:.8rem"></small>
    </p>
    <div class="d-flex gap-2 justify-content-center">
      <button class="btn btn-ghost-secondary" onclick="sfDismissElevComplete()">Not Yet</button>
      <button class="btn btn-success" onclick="sfConfirmElevComplete()">Mark Complete</button>
    </div>
  </div>
</div>

<!-- ── Elevation reopen prompt ── -->
<div id="sf-elev-reopen-prompt" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:4000;align-items:center;justify-content:center;">
  <div style="background:var(--tblr-bg-surface);border-radius:12px;padding:2rem;width:min(380px,90vw);box-shadow:0 8px 32px rgba(0,0,0,.3);text-align:center">
    <div style="font-size:2.5rem;margin-bottom:.75rem">⚠️</div>
    <h4 style="margin-bottom:.5rem">Elevation is complete</h4>
    <p style="color:var(--tblr-secondary);margin-bottom:1.5rem">
      <strong id="sf-elev-rp-tag"></strong> is already marked complete.<br>Do you want to reopen it?
    </p>
    <div class="d-flex gap-2 justify-content-center">
      <button class="btn btn-ghost-secondary" onclick="sfDismissElevReopen()">Cancel</button>
      <button class="btn btn-warning" onclick="sfConfirmElevReopen()">Yes, Reopen</button>
    </div>
  </div>
</div>

<!-- ── On-hold stage prompt ── -->
<div id="sf-hold-prompt" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:4000;align-items:center;justify-content:center;">
  <div style="background:var(--tblr-bg-surface);border-radius:12px;padding:2rem;width:min(380px,90vw);box-shadow:0 8px 32px rgba(0,0,0,.3);text-align:center">
    <div style="font-size:2.5rem;margin-bottom:.75rem">🚧</div>
    <h4 style="margin-bottom:.5rem">Stage is on hold</h4>
    <p style="color:var(--tblr-secondary);margin-bottom:1.5rem">
      <strong id="sf-hold-name"></strong> was put on hold by the office.<br>Take it off hold and start work?
    </p>
    <div class="d-flex gap-2 justify-content-center">
      <button class="btn btn-ghost-secondary" onclick="sfDismissHold()">Cancel</button>
      <button class="btn btn-warning" onclick="sfConfirmHold()">Yes, Take Off Hold</button>
    </div>
  </div>
</div>

<!-- ── Header ── -->
<div class="sf-header">
  <div class="sf-logo">Forge<span class="accent">Desk</span><span class="sub">Shop Floor</span></div>
  <div class="ms-auto d-flex align-items-center gap-3">
    <span class="text-muted small" id="sf-clock"></span>
    <span class="text-muted small" id="sf-wo-count"></span>
    <span id="sf-user-badge" style="display:none" class="d-flex align-items-center gap-2">
      <span class="badge bg-blue-lt text-blue" id="sf-user-name"></span>
      <button class="btn btn-sm btn-ghost-secondary" onclick="sfLogout()" title="Log out">
        <i class="ti ti-logout"></i>
      </button>
    </span>
  </div>
</div>

<!-- ── Filter bar ── -->
<div class="sf-filters">
  <div class="d-flex gap-1 me-2">
    <button class="pill active" id="view-all" onclick="sfSetView('all', this)">All Work Orders</button>
    <button class="pill" id="view-mine" onclick="sfSetView('mine', this)">My Work</button>
  </div>
  <span class="sf-filter-label" id="user-pills-label">User:</span>
  <div id="user-pills" class="d-flex gap-1 flex-wrap">
    <button class="pill active" data-uid="" onclick="setUser('', this)">All</button>
  </div>
  <div class="ms-auto d-flex gap-1">
    <button class="pill" id="btn-hide-done" onclick="toggleHideDone(this)">Hide Complete</button>
  </div>
</div>

<!-- ── Loading ── -->
<div id="sf-loading" class="sf-loading">
  <div class="spinner-border text-primary" role="status"></div>
  <p class="mt-2 text-muted">Loading…</p>
</div>

<!-- ── Table ── -->
<div id="sf-content" style="display:none">
  <table class="sf-table">
    <thead>
      <tr>
        <th style="width:36px"></th>
        <th style="width:44px" class="hide-sm">#</th>
        <th style="width:110px">Release</th>
        <th>Job</th>
        <th class="hide-sm" style="width:130px">Assigned</th>
        <th style="width:110px">Elevations</th>
        <th style="width:130px">Progress</th>
        <th class="hide-sm" style="width:120px">Status</th>
      </tr>
    </thead>
    <tbody id="sf-tbody"></tbody>
  </table>
  <div id="sf-empty" class="sf-empty" style="display:none">
    <i class="ti ti-clipboard-check" style="font-size:3rem;opacity:.25"></i>
    <p class="mt-2">No work orders to show</p>
  </div>
</div>

<!-- ── My Work queue ── -->
<div id="sf-myqueue" style="display:none;padding:.5rem 1rem 2rem"></div>

<script>
// ── State ─────────────────────────────────────────────────────────────────
let sfWOs      = [];
let sfUsers    = [];
let activeUser = '';
let hideDone   = false;
let sfFabUser  = null;  // { user_id, name, initials, role }
let sfView     = 'all'; // 'all' | 'mine'
let sfQueue    = [];
const expandedWOs = new Set();

const STATUS_LABEL = { pending: 'Pending', in_progress: 'In Progress', complete: 'Complete', blocked: 'Blocked', not_required: 'N/R', on_hold: 'On Hold' };

const API = (path, opts = {}) =>
  fetch('/api/v1' + path, {
    ...opts,
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      ...(opts.headers || {}),
    },
  });

// ── Clock ──────────────────────────────────────────────────────────────────
function updateClock() {
  document.getElementById('sf-clock').textContent =
    new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
updateClock(); setInterval(updateClock, 10000);

// ── Sticky offset calibration ──────────────────────────────────────────────
// Measure actual rendered heights of the header and filter bar, then set
// the correct `top` on both the filter bar and table column headers so
// nothing overlaps during scroll.
function calibrateSticky() {
  const header  = document.querySelector('.sf-header');
  const filters = document.querySelector('.sf-filters');
  if (!header || !filters) return;
  const hH = header.getBoundingClientRect().height;
  filters.style.top = hH + 'px';
  const fH = filters.getBoundingClientRect().height;
  document.querySelectorAll('.sf-table th').forEach(th => {
    th.style.top = (hH + fH) + 'px';
  });
}
// Run after fonts/layout settle, and on resize
window.addEventListener('load',   calibrateSticky);
window.addEventListener('resize', calibrateSticky);

// ── PIN auth ───────────────────────────────────────────────────────────────
function sfLoadSession() {
  try {
    const stored = sessionStorage.getItem('sf_fab_user');
    if (stored) sfFabUser = JSON.parse(stored);
  } catch {}
}

function sfApplySession() {
  const overlay = document.getElementById('sf-pin-overlay');
  const badge   = document.getElementById('sf-user-badge');
  const nameEl  = document.getElementById('sf-user-name');
  if (sfFabUser) {
    overlay.style.display = 'none';
    badge.style.display   = '';
    nameEl.textContent    = sfFabUser.initials || sfFabUser.name;
  } else {
    overlay.style.display = '';
    badge.style.display   = 'none';
    document.getElementById('sf-pin-input').focus();
  }
}

async function pinLogin() {
  const pin  = document.getElementById('sf-pin-input').value.trim();
  const errEl = document.getElementById('sf-pin-error');
  errEl.textContent = '';
  if (!pin) return;
  const btn = document.querySelector('.sf-pin-card .btn-primary');
  btn.disabled = true;
  try {
    const r = await API('/shop/fab-pin-login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ pin }),
    });
    if (!r.ok) { errEl.textContent = 'Invalid PIN. Try again.'; document.getElementById('sf-pin-input').value = ''; return; }
    sfFabUser = await r.json();
    sessionStorage.setItem('sf_fab_user', JSON.stringify(sfFabUser));
    sfApplySession();
  } catch (e) {
    errEl.textContent = 'Login failed. Try again.';
  } finally {
    btn.disabled = false;
  }
}

function sfLogout() {
  sfFabUser = null;
  sessionStorage.removeItem('sf_fab_user');
  sfApplySession();
}

// ── Init ───────────────────────────────────────────────────────────────────
async function init() {
  sfLoadSession();
  sfApplySession();
  const [woRes, userRes] = await Promise.all([API('/shop/work-orders'), API('/shop/fab-users')]);
  sfWOs   = (await woRes.json()).work_orders || [];
  sfUsers = (await userRes.json()).users      || [];
  buildUserPills();
  document.getElementById('sf-loading').style.display  = 'none';
  document.getElementById('sf-content').style.display  = '';
  render();
}

// ── User pills ─────────────────────────────────────────────────────────────
function buildUserPills() {
  const container = document.getElementById('user-pills');
  container.innerHTML = '<button class="pill active" data-uid="" onclick="setUser(\'\', this)">All</button>';
  sfUsers.forEach(u => {
    const btn = document.createElement('button');
    btn.className   = 'pill';
    btn.dataset.uid = u.id;
    btn.title       = u.name;
    btn.textContent = u.initials || u.name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
    btn.onclick     = () => setUser(u.id, btn);
    container.appendChild(btn);
  });
}

function setUser(uid, btn) {
  activeUser = String(uid);
  document.querySelectorAll('.pill[data-uid]').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  render();
}

function sfSetView(view, btn) {
  if (view === 'mine' && !sfFabUser) {
    document.getElementById('sf-pin-overlay').style.display = 'flex';
    return;
  }
  sfView = view;
  document.getElementById('view-all').classList.toggle('active', view === 'all');
  document.getElementById('view-mine').classList.toggle('active', view === 'mine');
  // The operator filter only makes sense on the full board.
  const showUserFilter = view === 'all';
  document.getElementById('user-pills').style.display = showUserFilter ? '' : 'none';
  document.getElementById('user-pills-label').style.display = showUserFilter ? '' : 'none';
  document.getElementById('btn-hide-done').style.display = showUserFilter ? '' : 'none';
  reload();
}

function toggleHideDone(btn) {
  hideDone = !hideDone;
  btn.classList.toggle('active', hideDone);
  render();
}

// ── Render ─────────────────────────────────────────────────────────────────
function render() {
  document.getElementById('sf-myqueue').style.display = 'none';
  document.getElementById('sf-content').style.display = '';
  const tbody = document.getElementById('sf-tbody');
  let   woCount = 0;
  const rows = [];

  sfWOs.forEach(wo => {
    const allStages = wo.elevations.flatMap(e => e.stages.map(s => ({ ...s, elev: e })));

    // User filter: match WO-level assignment OR stage-level assignment
    let woVisible = true;
    if (activeUser) {
      const woAssigned = (wo.assigned_users || []).some(u => String(u.id) === activeUser);
      const stageAssigned = allStages.some(s =>
        String(s.assigned_to_id) === activeUser ||
        (s.assignee_ids || []).map(String).includes(activeUser));
      woVisible = woAssigned || stageAssigned;
    }
    if (!woVisible) return;

    const visStages = allStages.filter(s => !(hideDone && ['complete','not_required'].includes(s.status)));
    if (!visStages.length && hideDone) return;

    woCount++;
    const totalStages = allStages.length;
    const doneStages  = allStages.filter(s => ['complete','not_required'].includes(s.status)).length;
    const inProg      = allStages.filter(s => s.status === 'in_progress').length;
    const blocked     = allStages.filter(s => s.status === 'blocked').length;
    const onHold      = allStages.filter(s => s.status === 'on_hold').length;
    const pct         = totalStages ? Math.round((doneStages / totalStages) * 100) : 0;

    const chips = [
      inProg  ? `<span class="sf-chip in_progress">${inProg} active</span>` : '',
      blocked ? `<span class="sf-chip blocked">${blocked} blocked</span>` : '',
      onHold  ? `<span class="sf-chip on_hold">${onHold} on hold</span>` : '',
    ].filter(Boolean).join(' ');

    const assignedBadges = (wo.assigned_users || []).map(u =>
      `<span style="font-size:.68rem;padding:.1rem .35rem;border-radius:4px;background:var(--tblr-blue-lt,#dce7f9);color:var(--tblr-blue,#2c5fc3);font-weight:600" title="${esc(u.name)}">${esc(u.initials || u.name.slice(0,2))}</span>`
    ).join(' ') || '<span class="text-muted small">—</span>';

    const priorityBadge = wo.priority != null
      ? `<span style="font-size:.72rem;padding:.1rem .4rem;border-radius:4px;background:var(--tblr-gray-200,#e9ecef);color:var(--tblr-secondary)">${wo.priority}</span>`
      : '<span class="text-muted small">—</span>';

    const isOpen = expandedWOs.has(wo.id);
    rows.push(`
      <tr class="wo-row${isOpen ? ' expanded' : ''}" onclick="toggleWO(${wo.id})">
        <td class="ps-3" style="width:36px">
          <i class="ti ti-chevron-right wo-chevron" style="${isOpen ? 'transform:rotate(90deg)' : ''}"></i>
        </td>
        <td class="text-muted small hide-sm">${priorityBadge}</td>
        <td><strong>${esc(wo.release_label)}</strong></td>
        <td>${esc(wo.job_name)}</td>
        <td class="hide-sm">${assignedBadges}</td>
        <td class="text-muted small">${wo.elevations.length} elevation${wo.elevations.length !== 1 ? 's' : ''}</td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="sf-progress"><div class="sf-progress-fill" style="width:${pct}%"></div></div>
            <span class="text-muted small">${doneStages}/${totalStages}</span>
          </div>
        </td>
        <td class="hide-sm">${chips || '<span class="text-muted small">—</span>'}</td>
      </tr>
      <tr class="wo-detail-row${isOpen ? ' open' : ''}" id="wo-detail-${wo.id}">
        <td colspan="8" class="wo-detail-cell">
          ${wo.elevations.map(e => elevBlock(e)).join('')}
        </td>
      </tr>`);
  });

  document.getElementById('sf-wo-count').textContent = `${woCount} WO${woCount !== 1 ? 's' : ''}`;

  if (!rows.length) {
    tbody.innerHTML = '';
    document.getElementById('sf-empty').style.display = '';
  } else {
    document.getElementById('sf-empty').style.display = 'none';
    tbody.innerHTML = rows.join('');
  }
  calibrateSticky();
}

function elevBlock(e) {
  const typeColor = e.elevation_type?.color || '#6b7280';
  const typeName  = e.elevation_type?.name  || '—';
  const typeBadge = `<span class="badge" style="background:${esc(typeColor)};font-size:.65rem">${esc(typeName)}</span>`;
  const scopeBadge = e.scope === 'kit'
    ? '<span class="badge bg-orange-lt" style="font-size:.65rem">Kit</span>' : '';
  const dateLabel = e.date_requested
    ? `<span class="small ${isPast(e.date_requested) ? 'text-danger' : 'text-muted'}">${e.date_requested}</span>` : '';

  const allStages = e.stages || [];
  const stagesBtns = allStages.map(s => {
    const who = s.completed_by_name || s.assigned_name || '';
    const blocker = sfStageBlocker(s, allStages);
    const tip  = blocker
      ? `Blocked by “${blocker.name}”`
      : (who ? `${who} · tap to advance` : 'tap to advance');
    const byLine = s.completed_by_name
      ? `<span style="font-size:.6rem;opacity:.7">${esc(s.completed_by_name)}</span>`
      : '';
    return `<button class="stage-btn ${s.status}${blocker ? ' locked' : ''}"
        onclick="cycleStage(event, ${s.id})"
        title="${esc(tip)}">
      <span class="sname">${esc(s.name)}</span>
      <span class="sstatus">${blocker ? '🔒 ' : ''}${STATUS_LABEL[s.status] || s.status}</span>
      ${byLine}
    </button>`;
  }).join('');

  const openCount = (e.stages || []).filter(s => s.status === 'pending' || s.status === 'in_progress').length;
  const bulkBtn = openCount > 0
    ? `<button class="stage-btn" style="background:var(--tblr-success-lt,#d1e7dd);color:var(--tblr-success,#0a3622);border:2px solid var(--tblr-success,#2fb344)"
          onclick="bulkCompleteElevStages(event, ${e.id})"
          title="Mark all remaining stages complete">
        <span class="sname">All Stages</span>
        <span class="sstatus">✓ Complete All</span>
      </button>`
    : '';

  return `<div class="elev-block">
    <div class="elev-meta">
      <div class="elev-tag">${esc(e.elevation_tag)} ${scopeBadge}</div>
      <div class="d-flex gap-1 mt-1 flex-wrap">${typeBadge}${dateLabel}</div>
    </div>
    <div class="elev-stages">${stagesBtns || '<span class="text-muted small">No stages</span>'}${bulkBtn}</div>
  </div>`;
}

function isPast(dateStr) {
  return dateStr && new Date(dateStr) < new Date(new Date().toDateString());
}

// The first earlier blocking stage in the same elevation that isn't done yet,
// or null. Mirrors StageGateService::blockingStageFor on the server.
function sfStageBlocker(stage, siblings) {
  const done = st => st === 'complete' || st === 'not_required';
  return (siblings || []).find(p =>
    p.id !== stage.id &&
    p.blocks_next &&
    p.sort_order < stage.sort_order &&
    !done(p.status)
  ) || null;
}

function sfIsManager() {
  return sfFabUser && (sfFabUser.role === 'manager' || sfFabUser.role === 'admin');
}

function esc(str) {
  if (str == null) return '';
  const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML;
}

// ── Expand / collapse WO ───────────────────────────────────────────────────
function toggleWO(woId) {
  if (expandedWOs.has(woId)) expandedWOs.delete(woId);
  else expandedWOs.add(woId);
  // Toggle without full re-render for snappiness
  const row    = document.querySelector(`.wo-row[onclick="toggleWO(${woId})"]`);
  const detail = document.getElementById(`wo-detail-${woId}`);
  if (!row || !detail) return;
  const opening = expandedWOs.has(woId);
  row.classList.toggle('expanded', opening);
  detail.classList.toggle('open', opening);
}

// ── Stage cycling ──────────────────────────────────────────────────────────
let _sfLastCycledStageId = null;
let _sfLastCycledStatus  = null;

async function cycleStage(event, stageId) {
  event.stopPropagation();
  const btn = event.currentTarget;

  // If the stage is on hold, require explicit confirmation before clearing it —
  // a single tap should never silently erase a PM's hold.
  if (btn.classList.contains('on_hold')) {
    const sname = btn.querySelector('.sname')?.textContent || 'This stage';
    const ok = await sfHoldPrompt(sname);
    if (!ok) return;
  }

  // Sequential gate: a blocking earlier stage isn't done. Workers are stopped;
  // managers/admins may override (logged server-side).
  let override = false;
  const gStage = sfFindStage(stageId);
  const gElev  = sfFindElevForStage(stageId);
  const gBlocker = gStage && gElev ? sfStageBlocker(gStage, gElev.stages || []) : null;
  if (gBlocker) {
    if (!sfIsManager()) {
      fabToast(`Blocked by “${gBlocker.name}” — ask a manager to override.`, 'error');
      return;
    }
    const ok = await fabConfirm({
      title: 'Override gate',
      message: `“${gBlocker.name}” isn’t complete. Proceed with this stage anyway?`,
      confirmLabel: 'Override',
      confirmClass: 'btn-warning',
    });
    if (!ok) return;
    override = true;
  }

  // Find the elevation for this stage to check if it's complete
  const elev = sfFindElevForStage(stageId);
  if (elev && elev.date_completed) {
    // Elevation is marked complete — confirm intent to reopen
    const ok = await sfElevReopenPrompt(elev);
    if (!ok) return;
    // Clear elevation completion before cycling stage
    await API(`/shop/elevations/${elev.id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ date_completed: null, completed_by_id: null }),
    });
  }

  btn.disabled = true; btn.style.opacity = '.55';
  try {
    const payload = {};
    if (sfFabUser) payload.fab_user_id = sfFabUser.user_id;
    if (override)  payload.override = true;
    const r = await API(`/shop/stages/${stageId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const result = await r.json();
    if (!r.ok) {
      fabToast(result.code === 'stage_gated'
        ? `Blocked by “${result.blocking_stage?.name || 'an earlier stage'}”.`
        : (result.message || 'Failed to update stage.'), 'error');
      btn.disabled = false; btn.style.opacity = '';
      return;
    }
    _sfLastCycledStageId = stageId;
    _sfLastCycledStatus  = result.status;
    await reload();
    sfCheckElevationCompletion();
  } catch (e) {
    console.error(e);
    fabToast('Failed to update stage — check your connection and try again.', 'error');
    btn.disabled = false; btn.style.opacity = '';
  }
}

async function bulkCompleteElevStages(event, elevId) {
  event.stopPropagation();
  const btn = event.currentTarget;

  const elev = sfWOs.flatMap(wo => wo.elevations || []).find(e => e.id === elevId);
  const tag = elev ? elev.elevation_tag : 'this elevation';

  const ok = await fabConfirm({
    title: 'Complete All Stages',
    message: `Mark all remaining stages on ${tag} complete? Blocked or on-hold stages are left untouched.`,
    confirmLabel: 'Mark All Complete',
    confirmClass: 'btn-success',
  });
  if (!ok) return;

  btn.disabled = true; btn.style.opacity = '.55';
  try {
    const send = async (override) => {
      const payload = {};
      if (sfFabUser) payload.fab_user_id = sfFabUser.user_id;
      if (override)  payload.override = true;
      const r = await API(`/shop/elevations/${elevId}/complete-stages`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      return { r, data: await r.json().catch(() => ({})) };
    };

    let { r, data } = await send(false);

    if (!r.ok && data.code === 'stage_gated' && sfIsManager()) {
      const ok = await fabConfirm({
        title: 'Override gate',
        message: `“${data.blocking_stage?.name || 'An earlier stage'}” isn’t complete. Complete all remaining stages anyway?`,
        confirmLabel: 'Override & Complete',
        confirmClass: 'btn-warning',
      });
      if (ok) ({ r, data } = await send(true));
    }

    if (!r.ok) {
      fabToast(data.code === 'stage_gated'
        ? `Blocked by “${data.blocking_stage?.name || 'an earlier stage'}”.`
        : (data.message || 'Failed to complete stages.'), 'error');
      btn.disabled = false; btn.style.opacity = '';
      return;
    }

    await reload();
    sfCheckElevationCompletionById(elevId);
    fabToast('Stages marked complete.', 'success');
  } catch (e) {
    console.error(e);
    fabToast('Failed to complete stages — check your connection and try again.', 'error');
    btn.disabled = false; btn.style.opacity = '';
  }
}

// ── Elevation helpers ──────────────────────────────────────────────────────
function sfFindElevForStage(stageId) {
  for (const wo of sfWOs) {
    for (const e of wo.elevations || []) {
      if ((e.stages || []).some(s => s.id === stageId)) return e;
    }
  }
  return null;
}

function sfFindStage(stageId) {
  const elev = sfFindElevForStage(stageId);
  return elev ? (elev.stages || []).find(s => s.id === stageId) || null : null;
}

function sfCheckElevationCompletion() {
  if (!_sfLastCycledStageId) return;
  const elev = sfFindElevForStage(_sfLastCycledStageId);
  if (!elev) return;
  sfCheckElevationCompletionById(elev.id);
}

function sfCheckElevationCompletionById(elevId) {
  const elev = sfWOs.flatMap(wo => wo.elevations || []).find(e => e.id === elevId);
  if (!elev || !(elev.stages || []).length) return;
  const TERMINAL = ['complete', 'not_required'];
  const allTerminal = elev.stages.every(s => TERMINAL.includes(s.status));
  if (allTerminal && !elev.date_completed) {
    sfShowElevCompletePrompt(elev);
  }
}

// ── Elevation complete/reopen overlays ─────────────────────────────────────
let _sfElevPromptId     = null;
let _sfElevReopenResolve = null;

function sfShowElevCompletePrompt(elev) {
  _sfElevPromptId = elev.id;
  document.getElementById('sf-elev-cp-tag').textContent = elev.elevation_tag;
  document.getElementById('sf-elev-cp-user').textContent =
    sfFabUser ? `Logged in as: ${sfFabUser.name}` : '';
  document.getElementById('sf-elev-complete-prompt').style.display = 'flex';
}

async function sfElevReopenPrompt(elev) {
  document.getElementById('sf-elev-rp-tag').textContent = elev.elevation_tag;
  document.getElementById('sf-elev-reopen-prompt').style.display = 'flex';
  return new Promise(resolve => { _sfElevReopenResolve = resolve; });
}

async function sfConfirmElevComplete() {
  if (!_sfElevPromptId) return;
  const fabId = sfFabUser?.user_id || null;
  try {
    await API(`/shop/elevations/${_sfElevPromptId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        date_completed:  new Date().toISOString().slice(0, 10),
        completed_by_id: fabId,
      }),
    });
  } catch (e) {
    console.error(e);
    fabToast('Failed to mark elevation complete — check your connection and try again.', 'error');
  }
  document.getElementById('sf-elev-complete-prompt').style.display = 'none';
  _sfElevPromptId = null;
  await reload();
}

function sfDismissElevComplete() {
  document.getElementById('sf-elev-complete-prompt').style.display = 'none';
  _sfElevPromptId = null;
}

function sfConfirmElevReopen() {
  document.getElementById('sf-elev-reopen-prompt').style.display = 'none';
  if (_sfElevReopenResolve) { _sfElevReopenResolve(true); _sfElevReopenResolve = null; }
}

function sfDismissElevReopen() {
  document.getElementById('sf-elev-reopen-prompt').style.display = 'none';
  if (_sfElevReopenResolve) { _sfElevReopenResolve(false); _sfElevReopenResolve = null; }
}

// ── On-hold confirm overlay ────────────────────────────────────────────────
let _sfHoldResolve = null;

async function sfHoldPrompt(stageName) {
  document.getElementById('sf-hold-name').textContent = stageName;
  document.getElementById('sf-hold-prompt').style.display = 'flex';
  return new Promise(resolve => { _sfHoldResolve = resolve; });
}

function sfConfirmHold() {
  document.getElementById('sf-hold-prompt').style.display = 'none';
  if (_sfHoldResolve) { _sfHoldResolve(true); _sfHoldResolve = null; }
}

function sfDismissHold() {
  document.getElementById('sf-hold-prompt').style.display = 'none';
  if (_sfHoldResolve) { _sfHoldResolve(false); _sfHoldResolve = null; }
}

async function reload() {
  try {
    if (sfView === 'mine' && sfFabUser) {
      const r = await API(`/shop/my-queue?fab_user_id=${sfFabUser.user_id}`);
      sfQueue = (await r.json()).queue || [];
      renderMyQueue();
      return;
    }
    const r = await API('/shop/work-orders');
    sfWOs = (await r.json()).work_orders || [];
    render();
  } catch (e) {
    console.error(e);
  }
}

function renderMyQueue() {
  document.getElementById('sf-content').style.display = 'none';
  const box = document.getElementById('sf-myqueue');
  box.style.display = '';
  document.getElementById('sf-wo-count').textContent =
    `${sfQueue.length} task${sfQueue.length !== 1 ? 's' : ''}`;

  if (!sfQueue.length) {
    box.innerHTML = `<div class="sf-empty"><i class="ti ti-checklist" style="font-size:3rem;opacity:.25"></i>
      <p class="mt-2">Nothing in your queue — you're all caught up.</p></div>`;
    return;
  }

  box.innerHTML = sfQueue.map(t => {
    const due = t.due_date
      ? `<span class="small ${isPast(t.due_date) ? 'text-danger' : 'text-muted'}">due ${t.due_date}</span>` : '';
    const prio = t.priority != null
      ? `<span style="font-size:.7rem;padding:.05rem .35rem;border-radius:4px;background:var(--tblr-gray-200,#e9ecef);color:var(--tblr-secondary)">#${t.priority}</span>` : '';
    const mates = (t.assignee_names || []).filter(n => n && n !== sfFabUser?.name);
    const shared = mates.length
      ? `<span class="badge bg-purple-lt" style="font-size:.62rem" title="Working with ${esc(mates.join(', '))}">
           <i class="ti ti-users"></i> with ${esc(mates.join(', '))}</span>` : '';
    return `<button class="stage-btn ${t.status}" style="display:block;width:100%;text-align:left;margin-bottom:.5rem;padding:.7rem .9rem"
        onclick="cycleStage(event, ${t.stage_id})">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        ${prio}
        <strong>${esc(t.release_label)}</strong>
        <span class="text-muted small">${esc(t.job_name || '')}</span>
        <span class="badge bg-blue-lt" style="font-size:.62rem">${esc(t.elevation_tag)}</span>
        ${due}
        ${shared}
        <span class="ms-auto sstatus">${STATUS_LABEL[t.status] || t.status}</span>
      </div>
      <div class="sname mt-1">${esc(t.name)}</div>
    </button>`;
  }).join('');
}

// ── Auto-refresh every 60 s ────────────────────────────────────────────────
setInterval(reload, 60000);

init();
</script>
</body>
</html>
