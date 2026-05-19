@extends('layouts.app')

@section('title', 'Work Orders – Fabrication')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <h2 class="page-title">Work Orders</h2>
          <div class="text-muted mt-1">Fabrication scheduling dashboard</div>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <!-- View toggle -->
          <div class="btn-group me-2" role="group" aria-label="View">
            <button type="button" class="btn btn-outline-secondary btn-sm active" id="btn-manager-view" onclick="switchView('manager')">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /><path d="M9 12h6" /><path d="M9 16h6" /></svg>
              Manager
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-shop-view" onclick="switchView('shop')">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M14 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M4 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M14 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /></svg>
              Shop Floor
            </button>
          </div>
          <button class="btn btn-primary btn-sm" onclick="openNewWO()" data-permission="fabrication.work-orders.create">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
            New Work Order
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      <!-- Filter bar -->
      <div class="card mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
              <input type="text" class="form-control form-control-sm" id="wo-search" placeholder="Search project, WO#, job#…" oninput="debounceFilter()">
            </div>
            <div class="col-auto">
              <select class="form-select form-select-sm" id="filter-type" onchange="applyFilter()">
                <option value="">All Types</option>
                <option value="SF">SF – Storefront</option>
                <option value="CW">CW – Curtain Wall</option>
              </select>
            </div>
            <div class="col-auto">
              <select class="form-select form-select-sm" id="filter-pm" onchange="applyFilter()">
                <option value="">All PMs</option>
              </select>
            </div>
            <div class="col-auto">
              <select class="form-select form-select-sm" id="filter-material" onchange="applyFilter()">
                <option value="">All Material</option>
                <option value="yes">Arrived</option>
                <option value="sof">SOF</option>
                <option value="no">Pending</option>
              </select>
            </div>
            <div class="col-auto ms-auto">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="show-archived" onchange="applyFilter()">
                <label class="form-check-label small" for="show-archived">Show Archived</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading state -->
      <div id="wo-loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2 text-muted">Loading work orders…</div>
      </div>

      <!-- Empty state -->
      <div id="wo-empty" class="card" style="display:none;">
        <div class="card-body text-center py-5">
          <div class="mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="icon text-muted"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /></svg>
          </div>
          <h3 class="text-muted">No work orders found</h3>
          <p class="text-muted">Try adjusting your filters or create a new work order.</p>
          <button class="btn btn-primary" onclick="openNewWO()" data-permission="fabrication.work-orders.create">New Work Order</button>
        </div>
      </div>

      <!-- Manager table view -->
      <div id="manager-view" style="display:none;">
        <div class="card">
          <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
              <thead>
                <tr>
                  <th>WO # / Project</th>
                  <th>Type</th>
                  <th>PM</th>
                  <th>Material</th>
                  <th>Stages</th>
                  <th>Hours</th>
                  <th>Due</th>
                  <th class="w-1"></th>
                </tr>
              </thead>
              <tbody id="wo-table-body"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Shop floor card grid -->
      <div id="shop-view" style="display:none;">
        <div class="row row-cards" id="wo-card-grid"></div>
      </div>

    </div>
  </div>
</div>

<!-- Detail Offcanvas (right side) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="detail-offcanvas" style="width:440px;">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title" id="detail-title">Work Order Detail</h5>
    <button type="button" class="btn-close" onclick="closeOffcanvas('detail-offcanvas')" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0" id="detail-body">
    <div class="text-center py-5 text-muted">Select a work order to view details</div>
  </div>
</div>

<!-- Create / Edit WO Modal -->
<div class="modal modal-blur fade" id="wo-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="wo-modal-title">New Work Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="wo-edit-id">
        <div class="row g-3">
          <div class="col-12 col-md-8">
            <label class="form-label required">Project Name</label>
            <input type="text" class="form-control" id="f-project-name" placeholder="e.g. Main St Office Lobby">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label required">Type</label>
            <select class="form-select" id="f-job-type">
              <option value="SF">SF – Storefront</option>
              <option value="CW">CW – Curtain Wall</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label required">WO Number</label>
            <input type="text" class="form-control" id="f-wo-number" placeholder="e.g. WO-2026-001">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Job Number</label>
            <input type="text" class="form-control" id="f-job-number" placeholder="e.g. J-4512">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">System</label>
            <input type="text" class="form-control" id="f-system" placeholder="e.g. Series 400">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">Joints</label>
            <input type="number" class="form-control" id="f-joints" value="0" min="0" oninput="updateCalcHours()">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">Dr/Fr Units</label>
            <input type="number" class="form-control" id="f-dr-fr" value="0" min="0" oninput="updateCalcHours()">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">Door Units</label>
            <input type="number" class="form-control" id="f-doors" value="0" min="0" oninput="updateCalcHours()">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">Est. Hours</label>
            <div class="input-group">
              <span class="input-group-text bg-muted-lt" id="calc-hours-display" title="Calculated">0.0</span>
              <input type="number" class="form-control" id="f-hours-override" placeholder="Override" step="0.25" min="0">
            </div>
            <div class="form-hint">Calc'd / override</div>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Date Received</label>
            <input type="date" class="form-control" id="f-date-received">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Planned Start</label>
            <input type="date" class="form-control" id="f-planned-start">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Planned Complete</label>
            <input type="date" class="form-control" id="f-planned-complete">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Requested Finish</label>
            <input type="date" class="form-control" id="f-requested-finish">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Material Status</label>
            <select class="form-select" id="f-material">
              <option value="">Pending</option>
              <option value="SOF">SOF</option>
              <option value="Yes">Arrived</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Project Manager</label>
            <input type="text" class="form-control" id="f-pm" placeholder="e.g. Dan S">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="f-cut-list">
              <label class="form-check-label" for="f-cut-list">Cut List / Glazer Released</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" id="f-notes" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn me-auto btn-danger d-none" id="wo-archive-btn" onclick="archiveWO()">Archive</button>
        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveWO()">Save Work Order</button>
      </div>
    </div>
  </div>
</div>

<style>
  /* Stage pip squares */
  .stage-pip {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
    margin: 1px;
    background: var(--tblr-border-color);
  }
  .stage-pip.complete   { background: var(--tblr-success); }
  .stage-pip.in_progress{ background: var(--tblr-warning); }
  .stage-pip.blocked    { background: var(--tblr-danger); }

  /* Material bar on shop floor cards */
  .wo-card { position: relative; overflow: hidden; }
  .mat-bar {
    position: absolute; top: 0; left: 0; right: 0;
    height: 3px;
  }
  .mat-bar.mat-yes { background: var(--tblr-success); }
  .mat-bar.mat-sof { background: var(--tblr-warning); }
  .mat-bar.mat-no  { background: var(--tblr-danger); }

  /* Due date urgency colors */
  .date-ok   { color: var(--tblr-success); }
  .date-warn { color: var(--tblr-warning); }
  .date-late { color: var(--tblr-danger); font-weight: 600; }

  /* Hours override star indicator */
  .hours-override { color: var(--tblr-warning); font-size: 0.7em; }

  /* Offcanvas stage rows */
  .stage-row {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--tblr-border-color);
  }
  .stage-row:last-child { border-bottom: none; }
  .stage-status-dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
    background: var(--tblr-border-color);
  }
  .stage-status-dot.complete    { background: var(--tblr-success); }
  .stage-status-dot.in_progress { background: var(--tblr-warning); }
  .stage-status-dot.blocked     { background: var(--tblr-danger); }

  /* Shop floor card clickable */
  .wo-card { cursor: pointer; transition: box-shadow 0.15s; }
  .wo-card:hover { box-shadow: 0 0 0 2px var(--tblr-primary); }

  /* Table row clickable */
  #wo-table-body tr { cursor: pointer; }
</style>
@endsection

@push('scripts')
<script>
const API = '/api/v1';
const RATES = { cw_prep: 0.5, fab_joints: 0.25, fab_doors: 2.25, fab_frames: 1.5 };

let allWOs = [];
let filtered = [];
let activeView = 'manager';
let selectedWOId = null;
let fabUsers = [];
let filterDebounce = null;

// ── Auth helper ──────────────────────────────────────────────────────────────
function authHeaders() {
  return { 'Content-Type': 'application/json', 'Authorization': `Bearer ${authToken}` };
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadWOs();
  loadFabUsers();
});

async function loadWOs() {
  showLoading(true);
  try {
    const archived = document.getElementById('show-archived').checked ? 1 : 0;
    const params = new URLSearchParams({ archived });

    const type = document.getElementById('filter-type').value;
    const pm   = document.getElementById('filter-pm').value;
    const mat  = document.getElementById('filter-material').value;
    const q    = document.getElementById('wo-search').value.trim();

    if (type) params.set('type', type);
    if (pm)   params.set('pm', pm);
    if (mat)  params.set('material', mat);
    if (q)    params.set('q', q);

    const res = await fetch(`${API}/work-orders?${params}`, { headers: authHeaders() });
    if (!res.ok) throw new Error('API error');
    const data = await res.json();
    allWOs = data.work_orders || [];
    filtered = allWOs;
    populatePmFilter();
    render();
  } catch (e) {
    showToast('Failed to load work orders', 'danger');
  } finally {
    showLoading(false);
  }
}

async function loadFabUsers() {
  try {
    const res = await fetch(`${API}/fab-users`, { headers: authHeaders() });
    if (!res.ok) return;
    const data = await res.json();
    fabUsers = data.users || [];
  } catch {}
}

// ── Filtering ─────────────────────────────────────────────────────────────────
function debounceFilter() {
  clearTimeout(filterDebounce);
  filterDebounce = setTimeout(applyFilter, 220);
}

function applyFilter() {
  loadWOs();
}

function populatePmFilter() {
  const sel = document.getElementById('filter-pm');
  const current = sel.value;
  const pms = [...new Set(allWOs.map(w => w.project_manager).filter(Boolean))].sort();
  sel.innerHTML = '<option value="">All PMs</option>' +
    pms.map(pm => `<option value="${esc(pm)}" ${pm === current ? 'selected' : ''}>${esc(pm)}</option>`).join('');
}

// ── View switching ────────────────────────────────────────────────────────────
function switchView(v) {
  activeView = v;
  document.getElementById('btn-manager-view').classList.toggle('active', v === 'manager');
  document.getElementById('btn-shop-view').classList.toggle('active', v === 'shop');
  render();
}

function render() {
  const hasItems = filtered.length > 0;
  document.getElementById('wo-empty').style.display    = hasItems ? 'none' : 'block';
  document.getElementById('manager-view').style.display = hasItems && activeView === 'manager' ? 'block' : 'none';
  document.getElementById('shop-view').style.display    = hasItems && activeView === 'shop'    ? 'block' : 'none';

  if (activeView === 'manager') renderTable();
  else renderShopGrid();
}

// ── Manager table ─────────────────────────────────────────────────────────────
function renderTable() {
  const tbody = document.getElementById('wo-table-body');
  if (!filtered.length) { tbody.innerHTML = ''; return; }

  tbody.innerHTML = filtered.map(wo => `
    <tr onclick="openDetail(${wo.id})">
      <td>
        <div class="fw-semibold">${esc(wo.project_name)}</div>
        <div class="text-muted small">${esc(wo.wo_number)}${wo.job_number ? ' &middot; ' + esc(wo.job_number) : ''}</div>
      </td>
      <td>${jobTypeBadge(wo.job_type)}</td>
      <td><span class="text-muted small">${esc(wo.project_manager || '—')}</span></td>
      <td>${matBadge(wo.material_arrived)}</td>
      <td><div class="d-flex flex-wrap">${stagePips(wo)}</div></td>
      <td><span class="text-body-secondary small">${hoursDisplay(wo)}</span></td>
      <td><span class="${dueCss(wo.planned_complete_date)} small">${fmtDate(wo.planned_complete_date)}</span></td>
      <td>
        <button class="btn btn-sm btn-ghost-secondary" onclick="event.stopPropagation(); editWO(${wo.id})" data-permission="fabrication.work-orders.edit" title="Edit">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /></svg>
        </button>
      </td>
    </tr>
  `).join('');
}

// ── Shop floor grid ───────────────────────────────────────────────────────────
function renderShopGrid() {
  const grid = document.getElementById('wo-card-grid');
  if (!filtered.length) { grid.innerHTML = ''; return; }

  grid.innerHTML = filtered.map(wo => `
    <div class="col-12 col-sm-6 col-lg-4">
      <div class="card wo-card" onclick="openDetail(${wo.id})">
        <div class="mat-bar ${matBarClass(wo.material_arrived)}"></div>
        <div class="card-body pt-3">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <div>${jobTypeBadge(wo.job_type)}</div>
            <span class="${dueCss(wo.planned_complete_date)} small fw-semibold">${fmtDate(wo.planned_complete_date)}</span>
          </div>
          <div class="fw-semibold">${esc(wo.project_name)}</div>
          <div class="text-muted small mb-2">${esc(wo.wo_number)}${wo.assigned_name ? ' · ' + esc(wo.assigned_name) : ''}</div>
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex flex-wrap">${stagePips(wo)}</div>
            <span class="text-muted small">${hoursDisplay(wo)} hrs</span>
          </div>
          ${wo.notes ? `<div class="text-muted small mt-2 text-truncate">${esc(wo.notes)}</div>` : ''}
        </div>
      </div>
    </div>
  `).join('');
}

// ── Offcanvas helpers (Bootstrap-independent fallback) ────────────────────────
function openOffcanvas(id) {
  const el = document.getElementById(id);
  if (!el) return;
  if (window.bootstrap?.Offcanvas) {
    new window.bootstrap.Offcanvas(el).show();
    return;
  }
  // Manual fallback
  let bd = document.getElementById('offcanvas-backdrop');
  if (!bd) {
    bd = document.createElement('div');
    bd.id = 'offcanvas-backdrop';
    bd.className = 'offcanvas-backdrop fade show';
    bd.onclick = () => closeOffcanvas(id);
    document.body.appendChild(bd);
  }
  el.classList.add('show');
  document.body.classList.add('offcanvas-open');
}

function closeOffcanvas(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('show');
  document.body.classList.remove('offcanvas-open');
  document.getElementById('offcanvas-backdrop')?.remove();
}

// ── Detail offcanvas ──────────────────────────────────────────────────────────
async function openDetail(id) {
  selectedWOId = id;
  const panel = document.getElementById('detail-body');
  panel.innerHTML = '<div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

  openOffcanvas('detail-offcanvas');

  try {
    const res = await fetch(`${API}/work-orders/${id}`, { headers: authHeaders() });
    if (!res.ok) throw new Error();
    const wo = await res.json();
    document.getElementById('detail-title').textContent = wo.wo_number;
    renderDetailPanel(wo);
  } catch {
    panel.innerHTML = '<div class="text-center py-5 text-danger">Failed to load details</div>';
  }
}

function renderDetailPanel(wo) {
  const stages = wo.stages || [];
  const canEdit = typeof hasPermission === 'function' ? hasPermission('fabrication.work-orders.edit') : true;

  const stageRows = stages.map(s => `
    <div class="stage-row">
      <div class="stage-status-dot ${s.status}"></div>
      <div class="flex-fill">
        <div class="fw-semibold small">${esc(s.name)}</div>
        ${s.assigned_name ? `<div class="text-muted" style="font-size:0.75rem">${esc(s.assigned_name)}</div>` : ''}
      </div>
      ${canEdit ? `
        <div class="btn-group btn-group-sm">
          ${s.status !== 'complete' ? `<button class="btn btn-sm btn-ghost-success py-0 px-1" onclick="cycleStage(${s.id}, '${nextStatus(s.status)}')" title="Advance">&rsaquo;</button>` : ''}
          ${s.status === 'pending' ? `<button class="btn btn-sm btn-ghost-danger py-0 px-1" onclick="cycleStage(${s.id}, 'blocked')" title="Block">!</button>` : ''}
        </div>
      ` : ''}
    </div>
  `).join('');

  document.getElementById('detail-body').innerHTML = `
    <div class="p-3 border-bottom">
      <div class="row g-2 small">
        <div class="col-6">
          <div class="text-muted">Project</div>
          <div class="fw-semibold">${esc(wo.project_name)}</div>
        </div>
        <div class="col-6">
          <div class="text-muted">Type</div>
          <div>${jobTypeBadge(wo.job_type)}</div>
        </div>
        <div class="col-6">
          <div class="text-muted">PM</div>
          <div>${esc(wo.project_manager || '—')}</div>
        </div>
        <div class="col-6">
          <div class="text-muted">Material</div>
          <div>${matBadge(wo.material_arrived)}</div>
        </div>
        <div class="col-4">
          <div class="text-muted">Joints</div>
          <div>${wo.joints}</div>
        </div>
        <div class="col-4">
          <div class="text-muted">Dr/Fr</div>
          <div>${wo.dr_fr_units}</div>
        </div>
        <div class="col-4">
          <div class="text-muted">Doors</div>
          <div>${wo.doors_units}</div>
        </div>
        <div class="col-6">
          <div class="text-muted">Est. Hours</div>
          <div>${hoursDisplay(wo)}</div>
        </div>
        <div class="col-6">
          <div class="text-muted">Due</div>
          <div class="${dueCss(wo.planned_complete_date)}">${fmtDate(wo.planned_complete_date)}</div>
        </div>
        ${wo.notes ? `<div class="col-12"><div class="text-muted">Notes</div><div>${esc(wo.notes)}</div></div>` : ''}
      </div>
      ${canEdit ? `
        <div class="mt-2 d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" onclick="editWO(${wo.id})">Edit</button>
          <button class="btn btn-sm btn-outline-danger ms-auto" onclick="archiveWODirect(${wo.id})" data-permission="fabrication.work-orders.delete">Archive</button>
        </div>
      ` : ''}
    </div>
    <div class="p-2">
      <div class="d-flex justify-content-between align-items-center px-1 mb-1">
        <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Stages (${stages.length})</span>
        ${canEdit ? `<button class="btn btn-sm btn-ghost-secondary py-0" onclick="promptAddStage(${wo.id})">+ Add</button>` : ''}
      </div>
      <div>${stageRows || '<div class="text-muted text-center small py-2">No stages</div>'}</div>
    </div>
  `;
}

// ── Stage actions ─────────────────────────────────────────────────────────────
function nextStatus(current) {
  const map = { pending: 'in_progress', in_progress: 'complete', blocked: 'in_progress' };
  return map[current] || 'pending';
}

async function cycleStage(stageId, status) {
  try {
    const res = await fetch(`${API}/work-order-stages/${stageId}`, {
      method: 'PATCH',
      headers: authHeaders(),
      body: JSON.stringify({ status }),
    });
    if (!res.ok) throw new Error();
    if (selectedWOId) await openDetail(selectedWOId);
  } catch {
    showToast('Failed to update stage', 'danger');
  }
}

async function promptAddStage(woId) {
  const name = prompt('Stage name:');
  if (!name || !name.trim()) return;
  try {
    const res = await fetch(`${API}/work-order-stages`, {
      method: 'POST',
      headers: authHeaders(),
      body: JSON.stringify({ work_order_id: woId, name: name.trim() }),
    });
    if (!res.ok) throw new Error();
    await openDetail(woId);
  } catch {
    showToast('Failed to add stage', 'danger');
  }
}

// ── WO Modal ──────────────────────────────────────────────────────────────────
function openNewWO() {
  document.getElementById('wo-modal-title').textContent = 'New Work Order';
  document.getElementById('wo-edit-id').value = '';
  document.getElementById('wo-archive-btn').classList.add('d-none');
  clearModalForm();
  updateCalcHours();
  showModal(document.getElementById('wo-modal'));
}

function editWO(id) {
  const wo = allWOs.find(w => w.id === id);
  if (!wo) return;
  document.getElementById('wo-modal-title').textContent = 'Edit Work Order';
  document.getElementById('wo-edit-id').value = id;
  document.getElementById('wo-archive-btn').classList.remove('d-none');
  fillModalForm(wo);
  updateCalcHours();
  showModal(document.getElementById('wo-modal'));
}

function clearModalForm() {
  ['f-project-name','f-wo-number','f-job-number','f-system','f-pm','f-notes'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('f-job-type').value = 'SF';
  document.getElementById('f-joints').value = 0;
  document.getElementById('f-dr-fr').value = 0;
  document.getElementById('f-doors').value = 0;
  document.getElementById('f-hours-override').value = '';
  document.getElementById('f-material').value = '';
  document.getElementById('f-cut-list').checked = false;
  ['f-date-received','f-planned-start','f-planned-complete','f-requested-finish'].forEach(id => {
    document.getElementById(id).value = '';
  });
}

function fillModalForm(wo) {
  document.getElementById('f-project-name').value = wo.project_name || '';
  document.getElementById('f-wo-number').value    = wo.wo_number || '';
  document.getElementById('f-job-number').value   = wo.job_number || '';
  document.getElementById('f-job-type').value     = wo.job_type || 'SF';
  document.getElementById('f-system').value       = wo.system || '';
  document.getElementById('f-joints').value       = wo.joints || 0;
  document.getElementById('f-dr-fr').value        = wo.dr_fr_units || 0;
  document.getElementById('f-doors').value        = wo.doors_units || 0;
  document.getElementById('f-hours-override').value = wo.estimated_hours_override || '';
  document.getElementById('f-material').value     = wo.material_arrived || '';
  document.getElementById('f-pm').value           = wo.project_manager || '';
  document.getElementById('f-cut-list').checked   = !!wo.cut_list_glazer;
  document.getElementById('f-notes').value        = wo.notes || '';
  document.getElementById('f-date-received').value     = wo.date_received || '';
  document.getElementById('f-planned-start').value     = wo.planned_start_date || '';
  document.getElementById('f-planned-complete').value  = wo.planned_complete_date || '';
  document.getElementById('f-requested-finish').value  = wo.requested_finish_date || '';
}

function updateCalcHours() {
  const type  = document.getElementById('f-job-type').value;
  const j = parseFloat(document.getElementById('f-joints').value) || 0;
  const f = parseFloat(document.getElementById('f-dr-fr').value)  || 0;
  const d = parseFloat(document.getElementById('f-doors').value)  || 0;
  let hrs;
  if (type === 'CW') {
    hrs = (j * RATES.cw_prep) + (f * RATES.fab_frames) + (d * RATES.fab_doors);
  } else {
    hrs = (j * RATES.fab_joints) + (f * RATES.fab_frames) + (d * RATES.fab_doors);
  }
  document.getElementById('calc-hours-display').textContent = hrs.toFixed(2);
}

// Also update hours when job type changes
document.getElementById('f-job-type')?.addEventListener('change', updateCalcHours);

async function saveWO() {
  const name = document.getElementById('f-project-name').value.trim();
  const wo   = document.getElementById('f-wo-number').value.trim();
  const type = document.getElementById('f-job-type').value;
  if (!name || !wo) { showToast('Project Name and WO Number are required', 'warning'); return; }

  const payload = {
    project_name:             name,
    wo_number:                wo,
    job_number:               document.getElementById('f-job-number').value.trim() || null,
    job_type:                 type,
    system:                   document.getElementById('f-system').value.trim() || null,
    joints:                   parseInt(document.getElementById('f-joints').value) || 0,
    dr_fr_units:              parseInt(document.getElementById('f-dr-fr').value) || 0,
    doors_units:              parseInt(document.getElementById('f-doors').value) || 0,
    estimated_hours_override: parseFloat(document.getElementById('f-hours-override').value) || null,
    material_arrived:         document.getElementById('f-material').value || null,
    project_manager:          document.getElementById('f-pm').value.trim() || null,
    cut_list_glazer:          document.getElementById('f-cut-list').checked,
    notes:                    document.getElementById('f-notes').value.trim() || null,
    date_received:            document.getElementById('f-date-received').value || null,
    planned_start_date:       document.getElementById('f-planned-start').value || null,
    planned_complete_date:    document.getElementById('f-planned-complete').value || null,
    requested_finish_date:    document.getElementById('f-requested-finish').value || null,
  };

  const editId = document.getElementById('wo-edit-id').value;
  const method = editId ? 'PUT' : 'POST';
  const url    = editId ? `${API}/work-orders/${editId}` : `${API}/work-orders`;

  try {
    const res = await fetch(url, { method, headers: authHeaders(), body: JSON.stringify(payload) });
    if (!res.ok) { const e = await res.json(); throw new Error(e.error || 'Save failed'); }
    hideModal(document.getElementById('wo-modal'));
    showToast(editId ? 'Work order updated' : 'Work order created', 'success');
    loadWOs();
  } catch (e) {
    showToast(e.message || 'Failed to save', 'danger');
  }
}

function archiveWO() {
  const id = document.getElementById('wo-edit-id').value;
  if (!id) return;
  archiveWODirect(parseInt(id));
}

async function archiveWODirect(id) {
  if (!confirm('Archive this work order?')) return;
  try {
    const res = await fetch(`${API}/work-orders/${id}`, { method: 'DELETE', headers: authHeaders() });
    if (!res.ok) throw new Error();
    hideModal(document.getElementById('wo-modal'));
    closeOffcanvas('detail-offcanvas');
    showToast('Work order archived', 'success');
    loadWOs();
  } catch {
    showToast('Failed to archive', 'danger');
  }
}

// ── UI helpers ────────────────────────────────────────────────────────────────
function jobTypeBadge(type) {
  if (type === 'SF') return '<span class="badge bg-blue-lt text-blue">SF</span>';
  if (type === 'CW') return '<span class="badge bg-orange-lt text-orange">CW</span>';
  return `<span class="badge bg-secondary-lt">${esc(type)}</span>`;
}

function matBadge(mat) {
  if (mat === 'Yes') return '<span class="badge bg-success-lt text-success">Arrived</span>';
  if (mat === 'SOF') return '<span class="badge bg-warning-lt text-warning">SOF</span>';
  return '<span class="badge bg-danger-lt text-danger">Pending</span>';
}

function matBarClass(mat) {
  if (mat === 'Yes') return 'mat-yes';
  if (mat === 'SOF') return 'mat-sof';
  return 'mat-no';
}

function stagePips(wo) {
  const total  = wo.stage_count || 0;
  const done   = wo.stages_done || 0;
  const active = wo.stages_active || 0;
  const blocked= wo.stages_blocked || 0;
  const pending= total - done - active - blocked;

  return [
    ...Array(done).fill('<span class="stage-pip complete"></span>'),
    ...Array(active).fill('<span class="stage-pip in_progress"></span>'),
    ...Array(blocked).fill('<span class="stage-pip blocked"></span>'),
    ...Array(Math.max(0, pending)).fill('<span class="stage-pip"></span>'),
  ].join('');
}

function hoursDisplay(wo) {
  if (wo.estimated_hours_override != null) {
    return `${parseFloat(wo.estimated_hours_override).toFixed(1)}<span class="hours-override" title="Override">★</span>`;
  }
  return parseFloat(wo.estimated_hours_calc || 0).toFixed(1);
}

function fmtDate(d) {
  if (!d) return '—';
  const dt = new Date(d + 'T00:00:00');
  return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function dueCss(d) {
  if (!d) return 'text-muted';
  const diff = (new Date(d + 'T00:00:00') - new Date()) / 86400000;
  if (diff < 0) return 'date-late';
  if (diff <= 3) return 'date-warn';
  return 'date-ok';
}

function showLoading(v) {
  document.getElementById('wo-loading').style.display = v ? 'block' : 'none';
  if (v) {
    document.getElementById('wo-empty').style.display   = 'none';
    document.getElementById('manager-view').style.display = 'none';
    document.getElementById('shop-view').style.display    = 'none';
  }
}

function esc(s) {
  if (s == null) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(message, type = 'info') {
  const colors = { success: 'bg-success', danger: 'bg-danger', warning: 'bg-warning', info: 'bg-info' };
  const id = 'toast-' + Date.now();
  const el = document.createElement('div');
  el.id = id;
  el.className = `alert alert-${type} alert-dismissible position-fixed bottom-0 end-0 m-3`;
  el.style.zIndex = 9999;
  el.innerHTML = `${esc(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}
</script>
@endpush
