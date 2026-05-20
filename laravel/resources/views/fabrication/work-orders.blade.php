@extends('layouts.app')

@section('title', 'Work Orders – Fabrication')

@section('content')
<style>
.pip { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin: 1px; }
.pip-pending    { background: #adb5bd; }
.pip-in_progress{ background: #f59f00; }
.pip-complete   { background: #2fb344; }
.pip-blocked    { background: #d63939; }
.wo-offcanvas   { width: 700px !important; }
.elev-row td    { vertical-align: middle; }
</style>
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <h2 class="page-title">Work Orders</h2>
          <div class="text-muted mt-1">Fabrication production releases</div>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <div class="form-check form-switch me-3 d-inline-flex align-items-center">
            <input class="form-check-input" type="checkbox" id="toggle-archived" onchange="applyFilter()">
            <label class="form-check-label ms-2" for="toggle-archived">Show Archived</label>
          </div>
          <button class="btn btn-primary" onclick="openCreateWO()" data-permission="fabrication.work-orders.create">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
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
              <input type="text" class="form-control form-control-sm" id="wo-search"
                placeholder="Search job #, name…" oninput="debounceFilter()">
            </div>
            <div class="col-auto">
              <select class="form-select form-select-sm" id="filter-material" onchange="applyFilter()">
                <option value="">All Material</option>
                <option value="in_shop">In Shop</option>
                <option value="sof">SOF</option>
                <option value="pending">Pending</option>
              </select>
            </div>
            <div class="col-auto ms-auto">
              <span class="text-muted small" id="wo-count-label"></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading -->
      <div id="wo-loading" class="text-center text-muted py-5">
        <div class="spinner-border" role="status"></div>
        <p class="mt-2">Loading work orders…</p>
      </div>

      <!-- Empty -->
      <div id="wo-empty" style="display:none;" class="empty">
        <div class="empty-icon">
          <i class="ti ti-tool" style="font-size: 3rem; opacity: 0.4;"></i>
        </div>
        <p class="empty-title">No work orders found</p>
        <p class="empty-subtitle text-muted">Try adjusting your filters or create a new work order.</p>
        <div class="empty-action">
          <button class="btn btn-primary" onclick="openCreateWO()">New Work Order</button>
        </div>
      </div>

      <!-- WO table -->
      <div id="wo-table-wrap" style="display:none;">
        <div class="card">
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr>
                  <th>Release</th>
                  <th>Job Name</th>
                  <th>PM</th>
                  <th>Date Issued</th>
                  <th>Material</th>
                  <th>Elevations</th>
                  <th class="w-1"></th>
                </tr>
              </thead>
              <tbody id="wo-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ============================================================
     Detail Offcanvas
     ============================================================ -->
<div class="offcanvas offcanvas-end wo-offcanvas" tabindex="-1" id="wo-detail">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title" id="wo-detail-title">Work Order</h5>
    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-sm btn-outline-warning" onclick="archiveCurrentWO()" id="btn-archive-wo" title="Archive">
        <i class="ti ti-archive"></i>
      </button>
      <button class="btn btn-sm btn-outline-danger" onclick="closeOffcanvas('wo-detail')">
        <i class="ti ti-x"></i>
      </button>
    </div>
  </div>
  <div class="offcanvas-body p-0">

    <!-- WO Header -->
    <div class="p-3 border-bottom bg-light">
      <div class="row g-3">
        <div class="col-6 col-md-3">
          <div class="subheader">Job</div>
          <div class="fw-bold" id="d-job-number">—</div>
        </div>
        <div class="col-6 col-md-5">
          <div class="subheader">Job Name</div>
          <div id="d-job-name">—</div>
        </div>
        <div class="col-6 col-md-2">
          <div class="subheader">PM</div>
          <div id="d-pm">—</div>
        </div>
        <div class="col-6 col-md-2">
          <div class="subheader">Division</div>
          <div id="d-division">—</div>
        </div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-auto">
          <div class="subheader">Date Issued</div>
          <input type="date" class="form-control form-control-sm" id="d-date-issued" style="width:150px"
            onchange="patchWO('date_issued', this.value)">
        </div>
        <div class="col-auto">
          <div class="subheader">Material Delivery</div>
          <div class="input-group input-group-sm" style="width:260px">
            <input type="text" class="form-control" id="d-material" placeholder="Date, In Shop, SOF"
              onchange="patchWO('material_delivery', this.value || null)">
            <button class="btn btn-outline-success" type="button"
              onclick="setMaterial('In Shop')">In Shop</button>
            <button class="btn btn-outline-warning" type="button"
              onclick="setMaterial('SOF')">SOF</button>
          </div>
        </div>
        <div class="col">
          <div class="subheader">Notes</div>
          <input type="text" class="form-control form-control-sm" id="d-notes" placeholder="Notes"
            onchange="patchWO('notes', this.value || null)">
        </div>
      </div>
    </div>

    <!-- Shop Drawings -->
    <div class="p-3 border-bottom">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Shop Drawings</h5>
        <label class="btn btn-sm btn-outline-primary mb-0" for="drawing-upload">
          <i class="ti ti-upload me-1"></i>Upload
          <input type="file" id="drawing-upload" class="d-none" multiple
            accept=".pdf,.dwg,.dxf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx"
            onchange="uploadDrawings(this.files)">
        </label>
      </div>
      <div id="drawings-loading" class="text-muted small" style="display:none;">Uploading…</div>
      <div id="drawings-list">
        <div class="text-muted small">No drawings attached.</div>
      </div>
    </div>

    <!-- Elevations -->
    <div class="p-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Elevations</h5>
        <button class="btn btn-sm btn-outline-primary" onclick="openAddElev()">
          <i class="ti ti-plus me-1"></i>Add Elevation
        </button>
      </div>
      <div id="elevations-loading" class="text-muted small" style="display:none;">Loading…</div>
      <div id="elevations-list">
        <div class="text-muted small">No elevations yet.</div>
      </div>
    </div>

  </div>
</div>
<div id="wo-backdrop" onclick="closeOffcanvas('wo-detail')"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:1040;"></div>

<!-- ============================================================
     Create WO Modal
     ============================================================ -->
<div class="modal fade" id="createWoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Work Order</h5>
        <button type="button" class="btn-close" onclick="hideModal(document.getElementById('createWoModal'))"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label required">Job</label>
          <select class="form-select" id="new-wo-job" required>
            <option value="">— Select a job —</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Date Issued</label>
          <input type="date" class="form-control" id="new-wo-date">
        </div>
        <div class="mb-3">
          <label class="form-label">Material Delivery</label>
          <div class="input-group">
            <input type="text" class="form-control" id="new-wo-material" placeholder="Date, In Shop, or SOF">
            <button type="button" class="btn btn-outline-success" onclick="document.getElementById('new-wo-material').value='In Shop'">In Shop</button>
            <button type="button" class="btn btn-outline-warning" onclick="document.getElementById('new-wo-material').value='SOF'">SOF</button>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="new-wo-notes" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="hideModal(document.getElementById('createWoModal'))">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveNewWO()">Create</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     Add / Edit Elevation Modal
     ============================================================ -->
<div class="modal fade" id="addElevModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="elev-modal-title">Add Elevation</h5>
        <button type="button" class="btn-close" onclick="hideModal(document.getElementById('addElevModal'))"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="elev-id">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label required">Elevation Tag</label>
            <input type="text" class="form-control" id="elev-tag" placeholder="e.g. A1, B2">
          </div>
          <div class="col-md-6">
            <label class="form-label">Type</label>
            <select class="form-select" id="elev-type">
              <option value="">— None —</option>
            </select>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Quantity</label>
            <input type="number" class="form-control" id="elev-qty" value="1" min="1">
          </div>
          <div class="col-md-4">
            <label class="form-label">Date Requested</label>
            <input type="date" class="form-control" id="elev-date-req">
          </div>
          <div class="col-md-4">
            <label class="form-label">Date Completed</label>
            <input type="date" class="form-control" id="elev-date-done">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Completed By</label>
          <select class="form-select" id="elev-completed-by">
            <option value="">— None —</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="elev-notes" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="hideModal(document.getElementById('addElevModal'))">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveElev()">Save</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
// ============================================================
// Globals
// ============================================================
let allWOs = [];
let currentWO = null;
let elevTypes = [];
let fabUsers = [];
let filterTimer = null;

const STAGE_CYCLE = { pending: 'in_progress', in_progress: 'complete', complete: 'pending', blocked: 'pending' };

const API = (path, opts = {}) => fetch('/api/v1' + path, {
    ...opts,
    headers: {
        'Authorization': 'Bearer ' + authToken,
        ...(opts.headers || {}),
    },
});

// ============================================================
// Offcanvas helpers (Tabler doesn't expose window.bootstrap)
// ============================================================
function openOffcanvas(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('show');
    el.style.visibility = 'visible';
    document.getElementById('wo-backdrop').style.display = 'block';
    document.body.classList.add('offcanvas-open');
}
function closeOffcanvas(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('show');
    el.style.visibility = '';
    document.getElementById('wo-backdrop').style.display = 'none';
    document.body.classList.remove('offcanvas-open');
}

// ============================================================
// Init
// ============================================================
document.addEventListener('DOMContentLoaded', async () => {
    await Promise.all([loadWorkOrders(), loadElevTypes(), loadFabUsers()]);

    // Open WO from query param (e.g. linked from Jobs page)
    const params = new URLSearchParams(location.search);
    if (params.has('wo')) openWODetail(parseInt(params.get('wo')));
});

// ============================================================
// Load & render WO list
// ============================================================
async function loadWorkOrders() {
    const archived = document.getElementById('toggle-archived').checked;
    const q = document.getElementById('wo-search').value.trim();
    const mat = document.getElementById('filter-material').value;

    const qs = new URLSearchParams();
    if (archived) qs.set('archived', '1');
    if (q) qs.set('q', q);
    if (mat) qs.set('material', mat);

    document.getElementById('wo-loading').style.display = 'block';
    document.getElementById('wo-table-wrap').style.display = 'none';
    document.getElementById('wo-empty').style.display = 'none';

    try {
        const r = await API('/work-orders?' + qs.toString());
        const data = await r.json();
        allWOs = data.work_orders || [];
        renderWOList(allWOs);
    } catch (e) {
        console.error(e);
        document.getElementById('wo-loading').style.display = 'none';
    }
}

function renderWOList(wos) {
    document.getElementById('wo-loading').style.display = 'none';
    document.getElementById('wo-count-label').textContent = `${wos.length} work order${wos.length !== 1 ? 's' : ''}`;

    if (wos.length === 0) {
        document.getElementById('wo-empty').style.display = 'block';
        return;
    }

    document.getElementById('wo-table-wrap').style.display = 'block';
    const tbody = document.getElementById('wo-tbody');
    tbody.innerHTML = wos.map(wo => `
        <tr style="cursor:pointer" onclick="openWODetail(${wo.id})">
            <td><strong>${esc(wo.release_label)}</strong></td>
            <td>${esc(wo.job?.job_name || '—')}</td>
            <td class="text-muted">${esc(wo.job?.project_manager || '—')}</td>
            <td>${wo.date_issued || '<span class="text-muted">—</span>'}</td>
            <td>${matBadge(wo.material_delivery)}</td>
            <td>
                ${wo.elevation_count > 0
                    ? `<span class="text-muted small">${wo.elevations_complete}/${wo.elevation_count} done</span>`
                    : '<span class="text-muted">—</span>'}
            </td>
            <td>
                <button class="btn btn-sm btn-ghost-secondary" onclick="event.stopPropagation();openWODetail(${wo.id})">
                    <i class="ti ti-chevron-right"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// ============================================================
// Filters
// ============================================================
function debounceFilter() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyFilter, 300);
}

async function applyFilter() {
    await loadWorkOrders();
}

// ============================================================
// WO Detail offcanvas
// ============================================================
async function openWODetail(id) {
    currentWO = allWOs.find(w => w.id === id) || { id };
    document.getElementById('wo-detail-title').textContent = 'Loading…';
    openOffcanvas('wo-detail');

    try {
        const r = await API(`/work-orders/${id}`);
        const wo = await r.json();
        currentWO = wo;
        populateDetail(wo);
    } catch (e) {
        console.error(e);
    }
}

function populateDetail(wo) {
    const job = wo.job || {};
    document.getElementById('wo-detail-title').textContent = wo.release_label || `WO #${wo.id}`;
    document.getElementById('d-job-number').textContent = job.job_number || '—';
    document.getElementById('d-job-name').textContent = job.job_name || '—';
    document.getElementById('d-pm').textContent = job.project_manager || '—';
    document.getElementById('d-division').textContent = job.division || '—';
    document.getElementById('d-date-issued').value = wo.date_issued || '';
    document.getElementById('d-material').value = wo.material_delivery || '';
    document.getElementById('d-notes').value = wo.notes || '';

    renderDrawings(wo.drawings || []);
    renderElevations(wo.elevations || []);
}

// ============================================================
// Inline WO patch
// ============================================================
async function patchWO(field, value) {
    if (!currentWO) return;
    try {
        await API(`/work-orders/${currentWO.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ [field]: value }),
        });
        // Refresh list silently
        loadWorkOrders();
    } catch (e) {
        console.error(e);
    }
}

function setMaterial(val) {
    document.getElementById('d-material').value = val;
    patchWO('material_delivery', val);
}

// ============================================================
// Archive
// ============================================================
async function archiveCurrentWO() {
    if (!currentWO || !confirm('Archive this work order?')) return;
    try {
        await API(`/work-orders/${currentWO.id}`, { method: 'DELETE' });
        closeOffcanvas('wo-detail');
        await loadWorkOrders();
    } catch (e) {
        console.error(e);
        alert('Failed to archive work order');
    }
}

// ============================================================
// Drawings
// ============================================================
function renderDrawings(drawings) {
    const el = document.getElementById('drawings-list');
    if (!drawings.length) {
        el.innerHTML = '<div class="text-muted small">No drawings attached.</div>';
        return;
    }
    el.innerHTML = `<div class="list-group list-group-flush">
        ${drawings.map(d => `
            <div class="list-group-item d-flex align-items-center gap-2 px-0">
                <i class="ti ti-file text-muted"></i>
                <a href="${esc(d.download_url)}" target="_blank" class="flex-grow-1 text-truncate small">${esc(d.original_name)}</a>
                <span class="text-muted small">${formatBytes(d.file_size)}</span>
                <button class="btn btn-sm btn-ghost-danger" onclick="deleteDrawing(${d.id})">
                    <i class="ti ti-trash"></i>
                </button>
            </div>`).join('')}
    </div>`;
}

async function uploadDrawings(files) {
    if (!currentWO || !files.length) return;
    document.getElementById('drawings-loading').style.display = 'block';
    for (const file of files) {
        const fd = new FormData();
        fd.append('file', file);
        try {
            await fetch(`/api/v1/work-orders/${currentWO.id}/drawings`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + authToken },
                body: fd,
            });
        } catch (e) {
            console.error('Upload failed:', e);
        }
    }
    document.getElementById('drawings-loading').style.display = 'none';
    document.getElementById('drawing-upload').value = '';
    // Reload detail to get fresh drawings list
    const r = await API(`/work-orders/${currentWO.id}`);
    const wo = await r.json();
    renderDrawings(wo.drawings || []);
}

async function deleteDrawing(drawingId) {
    if (!currentWO || !confirm('Delete this drawing?')) return;
    try {
        await API(`/work-orders/${currentWO.id}/drawings/${drawingId}`, { method: 'DELETE' });
        const r = await API(`/work-orders/${currentWO.id}`);
        const wo = await r.json();
        renderDrawings(wo.drawings || []);
    } catch (e) {
        console.error(e);
        alert('Failed to delete drawing');
    }
}

// ============================================================
// Elevations
// ============================================================
function renderElevations(elevations) {
    const el = document.getElementById('elevations-list');
    if (!elevations.length) {
        el.innerHTML = '<div class="text-muted small">No elevations yet.</div>';
        return;
    }
    el.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-vcenter">
            <thead><tr>
                <th>Tag</th><th>Type</th><th>Qty</th>
                <th>Requested</th><th>Completed</th>
                <th>Stages</th><th class="w-1"></th>
            </tr></thead>
            <tbody>
                ${elevations.map(e => elevRow(e)).join('')}
            </tbody>
        </table>
    </div>`;
}

function elevRow(e) {
    const typeBadge = e.elevation_type
        ? `<span class="badge" style="background:${esc(e.elevation_type.color || '#666')}">${esc(e.elevation_type.name)}</span>`
        : '<span class="text-muted">—</span>';

    const nextLabels = { pending: 'Start', in_progress: 'Complete', complete: 'Reset', blocked: 'Reset' };
    const pips = (e.stages || []).map(s =>
        `<span class="pip pip-${s.status}" style="cursor:pointer"
            title="${s.name}: ${s.status} → click to ${nextLabels[s.status] || 'advance'}"
            onclick="cycleStage(${s.id},'${s.status}',event)"></span>`
    ).join('');

    const completedInfo = e.date_completed
        ? `<span class="badge bg-success">${e.date_completed}</span>${e.completed_by_name ? `<br><small class="text-muted">${esc(e.completed_by_name)}</small>` : ''}`
        : `<span class="text-muted small">—</span>`;

    return `<tr class="elev-row">
        <td><strong>${esc(e.elevation_tag)}</strong></td>
        <td>${typeBadge}</td>
        <td>${e.quantity}</td>
        <td class="text-muted small">${e.date_requested || '—'}</td>
        <td>${completedInfo}</td>
        <td><div class="d-flex flex-wrap gap-0">${pips || '<span class="text-muted small">—</span>'}</div></td>
        <td>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-ghost-secondary" onclick="openEditElev(${e.id})" title="Edit">
                    <i class="ti ti-pencil"></i>
                </button>
                <button class="btn btn-ghost-danger" onclick="deleteElev(${e.id})" title="Delete">
                    <i class="ti ti-trash"></i>
                </button>
            </div>
        </td>
    </tr>`;
}

// ============================================================
// Add / Edit Elevation
// ============================================================
async function loadElevTypes() {
    try {
        const r = await API('/elevation-types');
        const data = await r.json();
        elevTypes = data.elevation_types || [];
        const sel = document.getElementById('elev-type');
        elevTypes.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            sel.appendChild(opt);
        });
    } catch (e) {
        console.error(e);
    }
}

async function loadFabUsers() {
    try {
        const r = await API('/fab-users');
        const data = await r.json();
        fabUsers = data.users || [];
        const sel = document.getElementById('elev-completed-by');
        fabUsers.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.name;
            sel.appendChild(opt);
        });
    } catch (e) {
        console.error(e);
    }
}

// ============================================================
// Stage cycling
// ============================================================
async function cycleStage(stageId, currentStatus, event) {
    event.stopPropagation();
    const nextStatus = STAGE_CYCLE[currentStatus] || 'pending';
    try {
        await API(`/work-order-stages/${stageId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: nextStatus }),
        });
        // Refresh elevations in offcanvas
        const r = await API(`/work-orders/${currentWO.id}`);
        const wo = await r.json();
        currentWO = wo;
        renderElevations(wo.elevations || []);
        loadWorkOrders();
    } catch (e) {
        console.error(e);
    }
}

function openAddElev() {
    document.getElementById('elev-modal-title').textContent = 'Add Elevation';
    document.getElementById('elev-id').value = '';
    document.getElementById('elev-tag').value = '';
    document.getElementById('elev-type').value = '';
    document.getElementById('elev-qty').value = 1;
    document.getElementById('elev-date-req').value = '';
    document.getElementById('elev-date-done').value = '';
    document.getElementById('elev-completed-by').value = '';
    document.getElementById('elev-notes').value = '';
    showModal(document.getElementById('addElevModal'));
}

function openEditElev(elevId) {
    if (!currentWO) return;
    const e = (currentWO.elevations || []).find(x => x.id === elevId);
    if (!e) return;
    document.getElementById('elev-modal-title').textContent = 'Edit Elevation';
    document.getElementById('elev-id').value = e.id;
    document.getElementById('elev-tag').value = e.elevation_tag;
    document.getElementById('elev-type').value = e.elevation_type_id || '';
    document.getElementById('elev-qty').value = e.quantity;
    document.getElementById('elev-date-req').value = e.date_requested || '';
    document.getElementById('elev-date-done').value = e.date_completed || '';
    document.getElementById('elev-completed-by').value = e.completed_by_id || '';
    document.getElementById('elev-notes').value = e.notes || '';
    showModal(document.getElementById('addElevModal'));
}

async function saveElev() {
    if (!currentWO) return;
    const elevId = document.getElementById('elev-id').value;
    const isEdit = !!elevId;

    const body = {
        elevation_tag: document.getElementById('elev-tag').value,
        elevation_type_id: document.getElementById('elev-type').value || null,
        quantity: parseInt(document.getElementById('elev-qty').value) || 1,
        date_requested: document.getElementById('elev-date-req').value || null,
        date_completed: document.getElementById('elev-date-done').value || null,
        completed_by_id: document.getElementById('elev-completed-by').value || null,
        notes: document.getElementById('elev-notes').value || null,
    };

    if (!body.elevation_tag) { alert('Elevation Tag is required.'); return; }

    try {
        if (isEdit) {
            await API(`/elevations/${elevId}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
        } else {
            await API(`/work-orders/${currentWO.id}/elevations`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
        }
        hideModal(document.getElementById('addElevModal'));
        // Reload detail
        const r = await API(`/work-orders/${currentWO.id}`);
        const wo = await r.json();
        currentWO = wo;
        renderElevations(wo.elevations || []);
        loadWorkOrders();
    } catch (e) {
        console.error(e);
        alert('Failed to save elevation');
    }
}

async function deleteElev(elevId) {
    if (!confirm('Delete this elevation and all its stages?')) return;
    try {
        await API(`/elevations/${elevId}`, { method: 'DELETE' });
        const r = await API(`/work-orders/${currentWO.id}`);
        const wo = await r.json();
        currentWO = wo;
        renderElevations(wo.elevations || []);
        loadWorkOrders();
    } catch (e) {
        console.error(e);
        alert('Failed to delete elevation');
    }
}

// ============================================================
// Create New WO Modal
// ============================================================
async function openCreateWO() {
    // Populate job selector
    const sel = document.getElementById('new-wo-job');
    sel.innerHTML = '<option value="">— Select a job —</option>';
    try {
        const r = await API('/business-jobs?per_page=500&status=active');
        const data = await r.json();
        (data.jobs || []).forEach(j => {
            const opt = document.createElement('option');
            opt.value = j.id;
            opt.textContent = `${j.job_number} – ${j.job_name}`;
            sel.appendChild(opt);
        });
    } catch (e) {
        console.error(e);
    }
    document.getElementById('new-wo-date').value = '';
    document.getElementById('new-wo-material').value = '';
    document.getElementById('new-wo-notes').value = '';
    showModal(document.getElementById('createWoModal'));
}

async function saveNewWO() {
    const jobId = document.getElementById('new-wo-job').value;
    if (!jobId) { alert('Please select a job.'); return; }

    const body = {
        business_job_id: parseInt(jobId),
        date_issued: document.getElementById('new-wo-date').value || null,
        material_delivery: document.getElementById('new-wo-material').value || null,
        notes: document.getElementById('new-wo-notes').value || null,
    };

    try {
        const r = await API('/work-orders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        if (!r.ok) throw new Error('Failed to create');
        const data = await r.json();
        hideModal(document.getElementById('createWoModal'));
        await loadWorkOrders();
        openWODetail(data.id);
    } catch (e) {
        console.error(e);
        alert('Failed to create work order');
    }
}

// ============================================================
// Helpers
// ============================================================
function esc(str) {
    if (str == null) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

function matBadge(mat) {
    if (!mat) return '<span class="badge bg-secondary">Pending</span>';
    if (mat === 'In Shop') return '<span class="badge bg-success">In Shop</span>';
    if (mat === 'SOF') return '<span class="badge bg-warning text-dark">SOF</span>';
    return `<span class="badge bg-info">${esc(mat)}</span>`;
}

function formatBytes(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}
</script>
@endpush
