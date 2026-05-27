<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Shop Floor — ForgeDesk</title>
  <link href="{{ asset('assets/tabler/css/tabler.min.css') }}" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" rel="stylesheet">
  <style>
    body { background: var(--tblr-bg-surface); font-size: 15px; }

    /* ── Header ── */
    .sf-header {
      position: sticky; top: 0; z-index: 100;
      background: var(--tblr-bg-surface);
      border-bottom: 1px solid var(--tblr-border-color);
      padding: .55rem 1rem;
      display: flex; align-items: center; gap: 1rem;
    }
    .sf-logo { font-weight: 700; font-size: 1.05rem; letter-spacing: .03em; white-space: nowrap; }
    .sf-logo .accent { color: var(--tblr-primary); }
    .sf-logo .sub { font-weight: 400; color: var(--tblr-secondary); font-size: .88rem; margin-left: .25rem; }

    /* ── Filter bar ── */
    .sf-filters {
      padding: .5rem 1rem;
      display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;
      border-bottom: 1px solid var(--tblr-border-color);
      background: var(--tblr-bg-surface-secondary, var(--tblr-light));
    }
    .sf-filter-label { font-size: .78rem; color: var(--tblr-secondary); white-space: nowrap; }

    /* ── Pill buttons ── */
    .pill {
      padding: .32rem .8rem; border-radius: 20px;
      border: 1.5px solid var(--tblr-border-color);
      background: transparent; font-size: .8rem; cursor: pointer; white-space: nowrap;
      transition: background .12s, border-color .12s, color .12s;
      -webkit-tap-highlight-color: transparent;
      color: var(--tblr-body-color);
    }
    .pill.active {
      background: var(--tblr-primary); border-color: var(--tblr-primary); color: #fff;
    }
    .pill.active-secondary {
      background: var(--tblr-secondary); border-color: var(--tblr-secondary); color: #fff;
    }

    /* ── Table ── */
    .sf-table { width: 100%; border-collapse: collapse; }
    .sf-table th {
      font-size: .68rem; text-transform: uppercase; letter-spacing: .06em;
      color: var(--tblr-secondary); font-weight: 600;
      padding: .5rem .75rem; border-bottom: 2px solid var(--tblr-border-color);
      background: var(--tblr-bg-surface);
      position: sticky; top: 54px; z-index: 10;
    }
    .sf-table td {
      padding: .7rem .75rem;
      border-bottom: 1px solid var(--tblr-border-color);
      vertical-align: middle;
    }
    .sf-table tbody tr:last-child td { border-bottom: none; }
    .sf-table tbody tr:hover { background: var(--tblr-active-bg, rgba(0,0,0,.02)); }

    /* ── WO group header rows ── */
    .sf-group td {
      font-weight: 600; font-size: .75rem; letter-spacing: .04em; text-transform: uppercase;
      padding: .35rem .75rem;
      background: var(--tblr-bg-surface-secondary, var(--tblr-light));
      border-top: 2px solid var(--tblr-border-color);
      color: var(--tblr-secondary);
    }

    /* ── Status buttons (big tap targets) ── */
    .sf-btn {
      display: inline-flex; align-items: center; gap: .35rem;
      padding: .5rem 1rem; border-radius: 6px; border: none;
      font-size: .82rem; font-weight: 600; cursor: pointer;
      min-width: 128px; justify-content: center;
      transition: transform .06s, filter .1s;
      -webkit-tap-highlight-color: transparent;
    }
    .sf-btn:active { transform: scale(.95); filter: brightness(.88); }
    .sf-btn:disabled { opacity: .5; cursor: default; }
    .sf-btn.pending     { background: var(--tblr-gray-200,#e9ecef); color: var(--tblr-gray-700,#495057); }
    .sf-btn.in_progress { background: #fff3cd; color: #664d03; }
    .sf-btn.complete    { background: #d1e7dd; color: #0a3622; }
    .sf-btn.blocked     { background: #f8d7da; color: #58151c; }
    [data-bs-theme="dark"] .sf-btn.pending     { background: #2e3338; color: #adb5bd; }
    [data-bs-theme="dark"] .sf-btn.in_progress { background: #3d3000; color: #ffc107; }
    [data-bs-theme="dark"] .sf-btn.complete    { background: #051b11; color: #75b798; }
    [data-bs-theme="dark"] .sf-btn.blocked     { background: #2c0b0e; color: #ea868f; }

    /* ── Assign button ── */
    .sf-assign {
      background: none; border: 1px dashed var(--tblr-border-color);
      border-radius: 4px; padding: .22rem .55rem; font-size: .73rem;
      cursor: pointer; color: var(--tblr-secondary);
      -webkit-tap-highlight-color: transparent;
    }
    .sf-assign:hover { border-color: var(--tblr-primary); color: var(--tblr-primary); }

    /* ── Assign modal overlay ── */
    #assignModal {
      position: fixed; inset: 0; z-index: 1050;
      background: rgba(0,0,0,.4);
      display: none; align-items: center; justify-content: center;
    }
    #assignModal.open { display: flex; }
    .assign-box {
      background: var(--tblr-bg-surface); border-radius: 10px;
      padding: 1.25rem 1.25rem 1rem; width: 320px; max-width: 95vw;
      box-shadow: 0 8px 40px rgba(0,0,0,.25);
    }
    .assign-user-opt {
      display: flex; align-items: center; gap: .6rem;
      padding: .45rem .6rem; border-radius: 6px;
      cursor: pointer; border: 1.5px solid transparent;
      transition: border-color .1s, background .1s;
    }
    .assign-user-opt.selected {
      border-color: var(--tblr-primary);
      background: rgba(32,107,196,.08);
    }
    .assign-avatar {
      width: 30px; height: 30px; border-radius: 50%;
      background: var(--tblr-primary); color: #fff;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: .68rem; font-weight: 700; flex-shrink: 0;
    }

    /* ── Empty / loading ── */
    .sf-loading, .sf-empty-msg {
      text-align: center; padding: 4rem 1rem;
      color: var(--tblr-secondary);
    }

    @media (max-width: 600px) {
      .hide-xs { display: none !important; }
    }
  </style>
</head>
<body>
<script src="{{ asset('assets/tabler/js/tabler-theme.min.js') }}"></script>

<!-- ── Header ── -->
<div class="sf-header">
  <div class="sf-logo">Forge<span class="accent">Desk</span><span class="sub">Shop Floor</span></div>
  <div class="ms-auto d-flex align-items-center gap-3">
    <span class="text-muted small" id="sf-count"></span>
    <span class="text-muted small hide-xs" id="sf-clock"></span>
  </div>
</div>

<!-- ── Filter bar ── -->
<div class="sf-filters">
  <span class="sf-filter-label">User:</span>
  <div id="user-pills" class="d-flex gap-1 flex-wrap"></div>

  <span class="sf-filter-label ms-2 hide-xs">Stage:</span>
  <div id="stage-pills" class="d-flex gap-1 flex-wrap hide-xs"></div>

  <div class="ms-auto d-flex gap-1">
    <button class="pill" id="btn-hide-done" onclick="toggleHideDone()">
      <i class="ti ti-eye-off me-1"></i>Hide Done
    </button>
  </div>
</div>

<!-- ── Content ── -->
<div id="sf-loading" class="sf-loading">
  <div class="spinner-border text-primary" role="status"></div>
  <p class="mt-2">Loading shop floor…</p>
</div>

<div id="sf-content" style="display:none">
  <div class="table-responsive">
    <table class="sf-table">
      <thead>
        <tr>
          <th style="width:105px">WO</th>
          <th>Elevation</th>
          <th class="hide-xs" style="width:80px">Type</th>
          <th style="width:130px">Stage</th>
          <th class="hide-xs" style="width:105px">Requested</th>
          <th style="width:100px">Assigned</th>
          <th style="width:145px">Status</th>
        </tr>
      </thead>
      <tbody id="sf-tbody"></tbody>
    </table>
  </div>
  <div id="sf-empty" class="sf-empty-msg" style="display:none">
    <i class="ti ti-check" style="font-size:2.5rem;opacity:.3"></i>
    <p class="mt-2 mb-0">No stages match the current filters.</p>
  </div>
</div>

<!-- ── Assign modal ── -->
<div id="assignModal">
  <div class="assign-box">
    <h5 class="mb-3">Assign Stage</h5>
    <input type="hidden" id="assign-stage-id">
    <div id="assign-user-list" class="d-flex flex-column gap-1 mb-3"
         style="max-height:280px;overflow-y:auto"></div>
    <div class="d-flex justify-content-end gap-2 pt-1 border-top">
      <button class="btn btn-ghost-secondary btn-sm" onclick="closeAssignModal()">Cancel</button>
      <button class="btn btn-primary btn-sm" onclick="saveAssignment()">Assign</button>
    </div>
  </div>
</div>

<script>
// ── State ──────────────────────────────────────────────────────────────────
let sfWOs             = [];
let sfUsers           = [];
let activeUser        = '';
let activeStageFilter = '';
let hideDone          = false;
let assignPick        = null;

const STATUS_LABEL = { pending: 'Pending', in_progress: 'In Progress', complete: 'Complete', blocked: 'Blocked' };
const STATUS_ICON  = { pending: 'ti-circle', in_progress: 'ti-player-play', complete: 'ti-circle-check', blocked: 'ti-alert-circle' };
const STATUS_NEXT  = { pending: 'Start', in_progress: 'Complete', complete: 'Reset', blocked: 'Reset' };

const SHOP = (path, opts = {}) =>
  fetch('/api/v1' + path, { ...opts, headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) } });

// ── Clock ──────────────────────────────────────────────────────────────────
(function clock() {
  document.getElementById('sf-clock').textContent =
    new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  setTimeout(clock, 10000);
})();

// ── Boot ───────────────────────────────────────────────────────────────────
async function init() {
  const [woRes, uRes] = await Promise.all([SHOP('/shop/work-orders'), SHOP('/shop/fab-users')]);
  sfWOs   = (await woRes.json()).work_orders || [];
  sfUsers = (await uRes.json()).users        || [];
  buildUserPills();
  buildStagePills();
  document.getElementById('sf-loading').style.display = 'none';
  document.getElementById('sf-content').style.display = '';
  render();
}

// ── User pills ─────────────────────────────────────────────────────────────
function buildUserPills() {
  const c = document.getElementById('user-pills');
  c.innerHTML = '';
  makeUserPill(c, '', 'All', true);
  sfUsers.forEach(u => makeUserPill(c, u.id, u.initials || initials(u.name), false, u.name));
}
function makeUserPill(container, uid, label, active, title) {
  const b = document.createElement('button');
  b.className   = 'pill' + (active ? ' active' : '');
  b.textContent = label;
  if (title) b.title = title;
  b.onclick = () => { activeUser = uid; document.querySelectorAll('.pill[data-type=user]').forEach(p => p.classList.remove('active')); b.classList.add('active'); render(); };
  b.dataset.type = 'user';
  container.appendChild(b);
}

// ── Stage name pills ───────────────────────────────────────────────────────
function buildStagePills() {
  const names = new Set();
  sfWOs.forEach(wo => wo.elevations.forEach(e => e.stages.forEach(s => names.add(s.name))));
  const c = document.getElementById('stage-pills');
  c.innerHTML = '';
  names.forEach(name => {
    const b = document.createElement('button');
    b.className   = 'pill';
    b.textContent = name;
    b.dataset.type = 'stage';
    b.onclick = () => {
      const was = activeStageFilter === name;
      activeStageFilter = was ? '' : name;
      document.querySelectorAll('.pill[data-type=stage]').forEach(p => p.classList.remove('active-secondary'));
      if (!was) b.classList.add('active-secondary');
      render();
    };
    c.appendChild(b);
  });
}

function toggleHideDone() {
  hideDone = !hideDone;
  document.getElementById('btn-hide-done').classList.toggle('active-secondary', hideDone);
  render();
}

// ── Render ─────────────────────────────────────────────────────────────────
function render() {
  const tbody = document.getElementById('sf-tbody');
  const rows  = [];
  let total   = 0;

  sfWOs.forEach(wo => {
    let headerAdded = false;
    wo.elevations.forEach(e => {
      e.stages.forEach(s => {
        if (activeUser        && String(s.assigned_to_id) !== String(activeUser)) return;
        if (activeStageFilter && s.name !== activeStageFilter)                     return;
        if (hideDone          && s.status === 'complete')                           return;
        if (!headerAdded) { rows.push(groupRow(wo)); headerAdded = true; }
        rows.push(stageRow(wo, e, s));
        total++;
      });
    });
  });

  document.getElementById('sf-count').textContent = `${total} stage${total !== 1 ? 's' : ''}`;
  document.getElementById('sf-empty').style.display = rows.length ? 'none' : '';
  tbody.innerHTML = rows.join('');
}

function groupRow(wo) {
  return `<tr class="sf-group">
    <td colspan="7">${esc(wo.release_label)}&ensp;·&ensp;${esc(wo.job_name)}</td>
  </tr>`;
}

function stageRow(wo, e, s) {
  const color = e.elevation_type?.color || '#6b7280';
  const type  = e.elevation_type?.name  || '—';
  const scopeTag = e.scope === 'kit' ? '<span class="badge bg-orange-lt ms-1" style="font-size:.65rem">Kit</span>' : '';
  const dateCell = e.date_requested
    ? `<span class="${isPast(e.date_requested) ? 'text-danger fw-semibold' : 'text-muted'} small">${e.date_requested}</span>`
    : '<span class="text-muted small">—</span>';
  const assignCell = s.assigned_name
    ? `<span class="badge bg-secondary-lt text-secondary" style="font-size:.72rem"
           title="${esc(s.assigned_name)}"
           onclick="openAssignModal(${s.id})" style="cursor:pointer">${esc(s.assigned_initials || s.assigned_name)}</span>`
    : `<button class="sf-assign" onclick="openAssignModal(${s.id})">+ Assign</button>`;

  return `<tr data-sid="${s.id}">
    <td class="text-muted small">${esc(wo.release_label)}</td>
    <td><span class="fw-semibold">${esc(e.elevation_tag)}</span>${scopeTag}</td>
    <td class="hide-xs"><span class="badge" style="background:${esc(color)};font-size:.7rem">${esc(type)}</span></td>
    <td class="fw-semibold" style="font-size:.88rem">${esc(s.name)}</td>
    <td class="hide-xs">${dateCell}</td>
    <td>${assignCell}</td>
    <td>
      <button class="sf-btn ${s.status}" onclick="cycleStage(${s.id})"
              title="Click to ${STATUS_NEXT[s.status] || 'advance'}">
        <i class="ti ${STATUS_ICON[s.status] || 'ti-circle'}"></i>
        ${STATUS_LABEL[s.status] || s.status}
      </button>
    </td>
  </tr>`;
}

function isPast(d) { return d && new Date(d) < new Date(new Date().toDateString()); }
function initials(n) { return (n||'').split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2); }
function esc(v) {
  if (v == null) return '';
  const d = document.createElement('div'); d.textContent = String(v); return d.innerHTML;
}

// ── Stage cycling ──────────────────────────────────────────────────────────
async function cycleStage(sid) {
  const btn = document.querySelector(`[data-sid="${sid}"] .sf-btn`);
  if (btn) { btn.disabled = true; }
  try {
    await SHOP(`/shop/stages/${sid}`, { method: 'PATCH' });
    await reload();
  } catch (e) {
    console.error(e);
    if (btn) btn.disabled = false;
  }
}

async function reload() {
  const r = await SHOP('/shop/work-orders');
  sfWOs   = (await r.json()).work_orders || [];
  buildStagePills();
  render();
}

// ── Auto-refresh every 60 s ────────────────────────────────────────────────
setInterval(reload, 60000);

// ── Assign modal ───────────────────────────────────────────────────────────
function openAssignModal(sid) {
  assignPick = null;
  document.getElementById('assign-stage-id').value = sid;

  // Find current assignee for pre-selection
  let currentUid = null;
  sfWOs.forEach(wo => wo.elevations.forEach(e => e.stages.forEach(s => {
    if (s.id === sid) currentUid = s.assigned_to_id;
  })));

  document.getElementById('assign-user-list').innerHTML = sfUsers.map(u => `
    <div class="assign-user-opt ${u.id == currentUid ? 'selected' : ''}"
         onclick="selectUser(${u.id}, this)">
      <div class="assign-avatar">${esc(u.initials || initials(u.name))}</div>
      <div class="flex-grow-1">
        <div style="font-size:.88rem;font-weight:600">${esc(u.name)}</div>
        <div class="text-muted" style="font-size:.73rem">${esc(u.role)}</div>
      </div>
    </div>`).join('');

  if (currentUid) assignPick = currentUid;
  document.getElementById('assignModal').classList.add('open');
}

function selectUser(uid, el) {
  assignPick = uid;
  document.querySelectorAll('.assign-user-opt').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
}

function closeAssignModal() {
  document.getElementById('assignModal').classList.remove('open');
}

async function saveAssignment() {
  const sid = document.getElementById('assign-stage-id').value;
  if (!assignPick) { alert('Select a user.'); return; }
  try {
    await SHOP(`/shop/stages/${sid}/assign`, {
      method: 'PATCH',
      body: JSON.stringify({ user_id: assignPick }),
    });
    closeAssignModal();
    await reload();
  } catch (e) { console.error(e); alert('Failed to assign'); }
}

// Close assign modal on backdrop click
document.getElementById('assignModal').addEventListener('click', e => {
  if (e.target === document.getElementById('assignModal')) closeAssignModal();
});

init();
</script>
</body>
</html>
