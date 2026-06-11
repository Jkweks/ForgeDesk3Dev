@extends('layouts.app')

@section('content')
<div class="container-xl">

  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">
          <i class="ti ti-building-warehouse me-2"></i>Bulk Location Assignment
        </h2>
        <div class="text-muted mt-1">One-time tool — reassign items from a source location to proper primary &amp; secondary locations</div>
      </div>
    </div>
  </div>

  <div class="page-body">

    <!-- Step 1: pick source location -->
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">1. Select Source Location</h3>
      </div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-5">
            <label class="form-label">Source Storage Location</label>
            <select class="form-select" id="sourceLocation">
              <option value="">— choose a location to pull items from —</option>
            </select>
          </div>
          <div class="col-auto">
            <button class="btn btn-primary" onclick="loadItems()">
              <i class="ti ti-download me-1"></i>Load Items
            </button>
          </div>
          <div class="col-auto">
            <span id="loadCount" class="text-muted"></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Step 2: bulk assignment table -->
    <div class="card" id="assignmentCard" style="display:none">
      <div class="card-header">
        <h3 class="card-title">2. Assign Locations</h3>
        <div class="ms-auto d-flex gap-2 align-items-center">
          <span id="progressText" class="text-muted small"></span>
          <button class="btn btn-sm btn-outline-secondary" onclick="setAllPrimary()">
            <i class="ti ti-copy me-1"></i>Apply primary to all
          </button>
          <button class="btn btn-sm btn-success" id="saveAllBtn" onclick="saveAll()">
            <i class="ti ti-device-floppy me-1"></i>Save All
          </button>
        </div>
      </div>

      <!-- Quick-set bar -->
      <div class="card-body border-bottom py-2">
        <div class="row g-2 align-items-center">
          <div class="col-auto text-muted small">Quick-set all rows:</div>
          <div class="col-md-3">
            <select class="form-select form-select-sm" id="quickPrimary">
              <option value="">Primary location…</option>
            </select>
          </div>
          <div class="col-md-3">
            <select class="form-select form-select-sm" id="quickSecondary">
              <option value="">(no secondary)</option>
            </select>
          </div>
          <div class="col-auto">
            <button class="btn btn-sm btn-outline-primary" onclick="applyQuickSet()">Apply to unsaved rows</button>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-vcenter table-sm mb-0">
          <thead>
            <tr>
              <th style="width:10%">SKU</th>
              <th style="width:20%">Description</th>
              <th style="width:6%" class="text-end">Qty</th>
              <th style="width:5%" class="text-center">Primary?</th>
              <th style="width:22%">New Primary Location <span class="text-danger">*</span></th>
              <th style="width:22%">Secondary Location <span class="text-muted">(optional)</span></th>
              <th style="width:10%">Status</th>
              <th style="width:5%"></th>
            </tr>
          </thead>
          <tbody id="assignmentBody"></tbody>
        </table>
      </div>
    </div>

    <!-- Unassigned products section -->
    <div class="card mt-3" id="unassignedCard" style="display:none">
      <div class="card-header">
        <h3 class="card-title">
          <i class="ti ti-alert-triangle text-yellow me-2"></i>Parts With No Storage Location
        </h3>
        <div class="ms-auto d-flex gap-2 align-items-center">
          <span id="unassignedProgressText" class="text-muted small"></span>
          <button class="btn btn-sm btn-success" id="saveAllUnassignedBtn" onclick="saveAllUnassigned()">
            <i class="ti ti-device-floppy me-1"></i>Save All
          </button>
        </div>
      </div>

      <!-- Quick-set bar for unassigned -->
      <div class="card-body border-bottom py-2">
        <div class="row g-2 align-items-center">
          <div class="col-auto text-muted small">Quick-set all rows:</div>
          <div class="col-md-3">
            <select class="form-select form-select-sm" id="unassignedQuickPrimary">
              <option value="">Primary location…</option>
            </select>
          </div>
          <div class="col-md-3">
            <select class="form-select form-select-sm" id="unassignedQuickSecondary">
              <option value="">(no secondary)</option>
            </select>
          </div>
          <div class="col-auto">
            <button class="btn btn-sm btn-outline-primary" onclick="applyUnassignedQuickSet()">Apply to unsaved rows</button>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-vcenter table-sm mb-0">
          <thead>
            <tr>
              <th style="width:10%">SKU</th>
              <th style="width:22%">Description</th>
              <th style="width:6%" class="text-end">On Hand</th>
              <th style="width:6%" class="text-end">Committed</th>
              <th style="width:22%">Primary Location <span class="text-danger">*</span></th>
              <th style="width:22%">Secondary Location <span class="text-muted">(optional)</span></th>
              <th style="width:7%">Status</th>
              <th style="width:5%"></th>
            </tr>
          </thead>
          <tbody id="unassignedBody"></tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
let allStorageLocations = [];
let rows = [];             // array of item objects from API
let rowStates = {};        // rowStates[inv_location_id] = { primaryId, secondaryId, status }

// ─── Bootstrap ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  await loadStorageLocations();
  loadUnassignedProducts();
});

async function loadStorageLocations() {
  try {
    const data = await apiFetch('/storage-locations?per_page=500');
    allStorageLocations = (data.data || data).filter(l => l.is_active);
    populateSourceDropdown();
    populateTargetDropdowns();
  } catch(e) {
    alert('Error loading storage locations: ' + e.message);
  }
}

function populateSourceDropdown() {
  const sel = document.getElementById('sourceLocation');
  allStorageLocations.forEach(loc => {
    const opt = document.createElement('option');
    opt.value = loc.id;
    opt.textContent = loc.name + (loc.code ? ` [${loc.code}]` : '');
    sel.appendChild(opt);
  });
}

function populateTargetDropdowns() {
  ['quickPrimary', 'quickSecondary', 'unassignedQuickPrimary', 'unassignedQuickSecondary'].forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;
    // keep first placeholder option
    while (sel.options.length > 1) sel.remove(1);
    allStorageLocations.forEach(loc => {
      const opt = document.createElement('option');
      opt.value = loc.id;
      opt.textContent = loc.name + (loc.code ? ` [${loc.code}]` : '');
      sel.appendChild(opt);
    });
  });
}

function makeLocationOptions(selectedId = '') {
  let html = '<option value="">— select —</option>';
  allStorageLocations.forEach(loc => {
    const sel = String(loc.id) === String(selectedId) ? ' selected' : '';
    const label = escHtml(loc.name + (loc.code ? ` [${loc.code}]` : ''));
    html += `<option value="${loc.id}"${sel}>${label}</option>`;
  });
  return html;
}

// ─── Load items ──────────────────────────────────────────────────────────────
async function loadItems() {
  const srcId = document.getElementById('sourceLocation').value;
  if (!srcId) { alert('Please select a source location.'); return; }

  document.getElementById('loadCount').textContent = 'Loading…';
  try {
    const data = await apiFetch(`/locations/by-storage/${srcId}`);
    rows = data.items;
    rowStates = {};
    rows.forEach(r => {
      rowStates[r.inv_location_id] = { primaryId: '', secondaryId: '', status: 'pending' };
    });

    document.getElementById('loadCount').textContent =
      `${rows.length} item${rows.length !== 1 ? 's' : ''} at this location`;
    document.getElementById('assignmentCard').style.display = '';
    renderTable();
    updateProgress();
  } catch(e) {
    document.getElementById('loadCount').textContent = '';
    alert('Error loading items: ' + e.message);
  }
}

// ─── Table rendering ─────────────────────────────────────────────────────────
function renderTable() {
  const tbody = document.getElementById('assignmentBody');
  if (rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No items at this location.</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map(row => {
    const state   = rowStates[row.inv_location_id];
    const statusHtml = statusBadge(state.status);
    const disabled   = state.status === 'saved' ? 'disabled' : '';

    return `
      <tr id="row-${row.inv_location_id}" class="${state.status === 'saved' ? 'table-success' : state.status === 'error' ? 'table-danger' : ''}">
        <td><code>${escHtml(row.sku)}</code></td>
        <td class="small">${escHtml(row.description || '')}</td>
        <td class="text-end">${row.quantity.toLocaleString()}</td>
        <td class="text-center">${row.is_primary ? '<span class="badge bg-blue-lt">Yes</span>' : ''}</td>
        <td>
          <select class="form-select form-select-sm" id="primary-${row.inv_location_id}" ${disabled}
                  onchange="rowStates[${row.inv_location_id}].primaryId = this.value">
            ${makeLocationOptions(state.primaryId)}
          </select>
        </td>
        <td>
          <select class="form-select form-select-sm" id="secondary-${row.inv_location_id}" ${disabled}
                  onchange="rowStates[${row.inv_location_id}].secondaryId = this.value">
            <option value="">(none)</option>
            ${makeLocationOptions(state.secondaryId)}
          </select>
        </td>
        <td>${statusHtml}</td>
        <td>
          ${state.status !== 'saved'
            ? `<button class="btn btn-sm btn-primary" onclick="saveRow(${row.inv_location_id})">
                 <i class="ti ti-check"></i>
               </button>`
            : ''}
        </td>
      </tr>`;
  }).join('');
}

function statusBadge(status) {
  const map = {
    pending: '<span class="badge text-bg-secondary">Pending</span>',
    saving:  '<span class="badge bg-azure">Saving…</span>',
    saved:   '<span class="badge bg-success">Saved</span>',
    error:   '<span class="badge bg-danger">Error</span>',
  };
  return map[status] || status;
}

// ─── Quick-set helpers ────────────────────────────────────────────────────────
function applyQuickSet() {
  const primId = document.getElementById('quickPrimary').value;
  const secId  = document.getElementById('quickSecondary').value;
  rows.forEach(row => {
    const state = rowStates[row.inv_location_id];
    if (state.status === 'saved') return;
    if (primId) state.primaryId  = primId;
    state.secondaryId = secId;
  });
  renderTable();
}

function setAllPrimary() {
  const primId = document.getElementById('quickPrimary').value;
  if (!primId) { alert('Set a primary location in the quick-set bar first.'); return; }
  rows.forEach(row => {
    if (rowStates[row.inv_location_id].status !== 'saved') {
      rowStates[row.inv_location_id].primaryId = primId;
    }
  });
  renderTable();
}

// ─── Save logic ───────────────────────────────────────────────────────────────
async function saveRow(invLocId) {
  const state = rowStates[invLocId];
  if (!state.primaryId) { alert('Please select a primary location for this row.'); return; }

  const row = rows.find(r => r.inv_location_id === invLocId);
  setRowStatus(invLocId, 'saving');

  try {
    // 1. Update existing inventory_location → point to new primary storage location
    await apiFetch(`/products/${row.product_id}/locations/${invLocId}`, 'PUT', {
      storage_location_id: parseInt(state.primaryId),
      quantity:            row.quantity,
      quantity_committed:  row.quantity_committed,
      is_primary:          true,
    });

    // 2. If a secondary location was chosen, create a new inventory_location row (qty 0)
    if (state.secondaryId) {
      try {
        await apiFetch(`/products/${row.product_id}/locations`, 'POST', {
          storage_location_id: parseInt(state.secondaryId),
          quantity:            0,
          quantity_committed:  0,
          is_primary:          false,
        });
      } catch(secErr) {
        // Secondary might already exist — not fatal, just note it
        console.warn('Secondary location note:', secErr.message);
      }
    }

    setRowStatus(invLocId, 'saved');
  } catch(e) {
    setRowStatus(invLocId, 'error');
    alert(`Error saving ${row.sku}: ${e.message}`);
  }

  updateProgress();
}

async function saveAll() {
  const pending = rows.filter(r => rowStates[r.inv_location_id].status !== 'saved');
  const missing = pending.filter(r => !rowStates[r.inv_location_id].primaryId);
  if (missing.length > 0) {
    alert(`${missing.length} row(s) have no primary location selected. Please set all primary locations before saving.`);
    return;
  }

  document.getElementById('saveAllBtn').disabled = true;
  for (const row of pending) {
    await saveRow(row.inv_location_id);
  }
  document.getElementById('saveAllBtn').disabled = false;
}

function setRowStatus(invLocId, status) {
  rowStates[invLocId].status = status;
  // Re-render just this row to avoid full table flash
  const row = rows.find(r => r.inv_location_id === invLocId);
  const state = rowStates[invLocId];
  const tr = document.getElementById(`row-${invLocId}`);
  if (!tr) return;

  tr.className = status === 'saved' ? 'table-success' : status === 'error' ? 'table-danger' : '';

  const statusCell = tr.cells[6];
  statusCell.innerHTML = statusBadge(status);

  const actionCell = tr.cells[7];
  actionCell.innerHTML = status !== 'saved'
    ? `<button class="btn btn-sm btn-primary" onclick="saveRow(${invLocId})"><i class="ti ti-check"></i></button>`
    : '';

  // Disable dropdowns on saved
  if (status === 'saved') {
    tr.querySelector(`#primary-${invLocId}`)?.setAttribute('disabled', '');
    tr.querySelector(`#secondary-${invLocId}`)?.setAttribute('disabled', '');
  }
}

function updateProgress() {
  const total  = rows.length;
  const saved  = rows.filter(r => rowStates[r.inv_location_id].status === 'saved').length;
  const errors = rows.filter(r => rowStates[r.inv_location_id].status === 'error').length;
  document.getElementById('progressText').textContent =
    total > 0 ? `${saved}/${total} saved${errors ? `, ${errors} error(s)` : ''}` : '';
}

// ─── Unassigned products ─────────────────────────────────────────────────────
let unassignedRows  = [];
let unassignedStates = {};  // keyed by product_id

async function loadUnassignedProducts() {
  try {
    const data = await apiFetch('/locations/products-without-storage');
    unassignedRows = data.items;
    unassignedStates = {};
    unassignedRows.forEach(r => {
      unassignedStates[r.product_id] = { primaryId: '', secondaryId: '', status: 'pending' };
    });

    if (unassignedRows.length === 0) return; // hide section if nothing to show

    document.getElementById('unassignedCard').style.display = '';
    renderUnassignedTable();
    updateUnassignedProgress();
  } catch(e) {
    console.error('Error loading unassigned products:', e.message);
  }
}

function renderUnassignedTable() {
  const tbody = document.getElementById('unassignedBody');
  if (unassignedRows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No unassigned parts found.</td></tr>';
    return;
  }

  tbody.innerHTML = unassignedRows.map(row => {
    const state    = unassignedStates[row.product_id];
    const disabled = state.status === 'saved' ? 'disabled' : '';

    return `
      <tr id="urow-${row.product_id}" class="${state.status === 'saved' ? 'table-success' : state.status === 'error' ? 'table-danger' : ''}">
        <td><code>${escHtml(row.sku)}</code></td>
        <td class="small">${escHtml(row.description || '')}</td>
        <td class="text-end">${(row.quantity_on_hand || 0).toLocaleString()}</td>
        <td class="text-end">${(row.quantity_committed || 0).toLocaleString()}</td>
        <td>
          <select class="form-select form-select-sm" id="uprimary-${row.product_id}" ${disabled}
                  onchange="unassignedStates[${row.product_id}].primaryId = this.value">
            ${makeLocationOptions(state.primaryId)}
          </select>
        </td>
        <td>
          <select class="form-select form-select-sm" id="usecondary-${row.product_id}" ${disabled}
                  onchange="unassignedStates[${row.product_id}].secondaryId = this.value">
            <option value="">(none)</option>
            ${makeLocationOptions(state.secondaryId)}
          </select>
        </td>
        <td>${statusBadge(state.status)}</td>
        <td>
          ${state.status !== 'saved'
            ? `<button class="btn btn-sm btn-primary" onclick="saveUnassignedRow(${row.product_id})">
                 <i class="ti ti-check"></i>
               </button>`
            : ''}
        </td>
      </tr>`;
  }).join('');
}

function applyUnassignedQuickSet() {
  const primId = document.getElementById('unassignedQuickPrimary').value;
  const secId  = document.getElementById('unassignedQuickSecondary').value;
  unassignedRows.forEach(row => {
    const state = unassignedStates[row.product_id];
    if (state.status === 'saved') return;
    if (primId) state.primaryId  = primId;
    state.secondaryId = secId;
  });
  renderUnassignedTable();
}

async function saveUnassignedRow(productId) {
  const state = unassignedStates[productId];
  if (!state.primaryId) { alert('Please select a primary location for this row.'); return; }

  const row = unassignedRows.find(r => r.product_id === productId);
  setUnassignedRowStatus(productId, 'saving');

  try {
    // Create a new primary inventory_location record for this product
    await apiFetch(`/products/${productId}/locations`, 'POST', {
      storage_location_id: parseInt(state.primaryId),
      quantity:            row.quantity_on_hand || 0,
      quantity_committed:  row.quantity_committed || 0,
      is_primary:          true,
    });

    // Optional secondary location (qty 0)
    if (state.secondaryId) {
      try {
        await apiFetch(`/products/${productId}/locations`, 'POST', {
          storage_location_id: parseInt(state.secondaryId),
          quantity:            0,
          quantity_committed:  0,
          is_primary:          false,
        });
      } catch(secErr) {
        console.warn('Secondary location note:', secErr.message);
      }
    }

    setUnassignedRowStatus(productId, 'saved');
  } catch(e) {
    setUnassignedRowStatus(productId, 'error');
    alert(`Error saving ${row.sku}: ${e.message}`);
  }

  updateUnassignedProgress();
}

async function saveAllUnassigned() {
  const pending = unassignedRows.filter(r => unassignedStates[r.product_id].status !== 'saved');
  const missing = pending.filter(r => !unassignedStates[r.product_id].primaryId);
  if (missing.length > 0) {
    alert(`${missing.length} row(s) have no primary location selected.`);
    return;
  }

  document.getElementById('saveAllUnassignedBtn').disabled = true;
  for (const row of pending) {
    await saveUnassignedRow(row.product_id);
  }
  document.getElementById('saveAllUnassignedBtn').disabled = false;
}

function setUnassignedRowStatus(productId, status) {
  unassignedStates[productId].status = status;
  const tr = document.getElementById(`urow-${productId}`);
  if (!tr) return;

  tr.className = status === 'saved' ? 'table-success' : status === 'error' ? 'table-danger' : '';
  tr.cells[6].innerHTML = statusBadge(status);
  tr.cells[7].innerHTML = status !== 'saved'
    ? `<button class="btn btn-sm btn-primary" onclick="saveUnassignedRow(${productId})"><i class="ti ti-check"></i></button>`
    : '';

  if (status === 'saved') {
    tr.querySelector(`#uprimary-${productId}`)?.setAttribute('disabled', '');
    tr.querySelector(`#usecondary-${productId}`)?.setAttribute('disabled', '');
  }
}

function updateUnassignedProgress() {
  const total  = unassignedRows.length;
  const saved  = unassignedRows.filter(r => unassignedStates[r.product_id].status === 'saved').length;
  const errors = unassignedRows.filter(r => unassignedStates[r.product_id].status === 'error').length;
  document.getElementById('unassignedProgressText').textContent =
    total > 0 ? `${saved}/${total} saved${errors ? `, ${errors} error(s)` : ''}` : '';
}

// ─── API helper ──────────────────────────────────────────────────────────────
async function apiFetch(path, method = 'GET', body = null) {
  const opts = {
    method,
    headers: {
      'Authorization': `Bearer ${authToken}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(`${API_BASE}${path}`, opts);
  const json = await res.json();
  if (!res.ok) throw new Error(json.message || json.error || `HTTP ${res.status}`);
  return json;
}

function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str ?? '';
  return d.innerHTML;
}
</script>
@endsection
