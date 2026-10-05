@extends('layouts.app')

@section('title', 'Cut Lists – Fabrication')

@section('styles')
.cl-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1060; align-items:center; justify-content:center; }
.cl-overlay > .card { box-shadow:0 1rem 3rem rgba(0,0,0,.5); }
.cl-row-locked { background:var(--tblr-bg-surface-secondary); }
.cl-toast { position:fixed; bottom:1rem; right:1rem; z-index:2000; max-width:360px; }
@endsection

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Fabrication</div>
          <h2 class="page-title">Cut Lists</h2>
        </div>
        <div class="col-auto ms-auto">
          <button class="btn btn-outline-secondary" onclick="clShowList()" id="cl-back" style="display:none">
            <i class="ti ti-arrow-left me-1"></i>All cut lists
          </button>
        </div>
      </div>
      <div class="text-secondary small mt-1">
        Lines can be edited or removed until the first cut is made on them. Once cutting has started a line is
        read-only here — its cut history is shown instead.
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      {{-- List view --}}
      <div id="cl-list-view">
        <div class="card">
          <div class="card-header">
            <input type="search" id="cl-filter" class="form-control form-control-sm" style="max-width:260px" placeholder="Filter by name…" oninput="clRenderList()">
            <select id="cl-status" class="form-select form-select-sm ms-2" style="width:auto" onchange="clRenderList()">
              <option value="">All statuses</option>
              <option>Not cut</option>
              <option>In progress</option>
              <option>Done</option>
            </select>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>Cut list</th><th>Work order</th><th class="text-end">Lines</th><th class="text-end">Cut / Total</th><th>Status</th><th>Updated</th></tr></thead>
              <tbody id="cl-list-body"><tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Detail view --}}
      <div id="cl-detail-view" style="display:none">
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title mb-0"><span id="cl-d-name"></span> <span id="cl-d-status" class="badge ms-2"></span></h3>
            <div class="card-actions" data-permission="fabrication.work-orders.edit">
              <button class="btn btn-sm btn-outline-secondary" onclick="clRename()"><i class="ti ti-pencil me-1"></i>Rename</button>
              <button class="btn btn-sm btn-primary" onclick="clOpenPart()"><i class="ti ti-plus me-1"></i>Add line</button>
              <button class="btn btn-sm btn-outline-danger" id="cl-delete-btn" onclick="clDeleteList()"><i class="ti ti-trash me-1"></i>Delete list</button>
            </div>
          </div>
          <div id="cl-d-warning" class="alert alert-warning mb-0 rounded-0 border-0 border-bottom small" style="display:none">
            This list is linked to a work order. Releasing more openings from the configurator re-syncs it, which can
            overwrite or duplicate lines you've edited here.
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>Profile</th><th>Finish</th><th class="text-end">Length (in)</th><th>Use</th><th>Phase</th><th>Angles L / R</th><th class="text-end">Qty</th><th class="text-end">Remaining</th><th>Status</th><th></th></tr></thead>
              <tbody id="cl-parts-body"></tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3 class="card-title">Cut history</h3></div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>When</th><th>Profile</th><th>Finish</th><th>Use</th><th class="text-end">Length (in)</th><th>Stick</th><th>Operator</th><th></th></tr></thead>
              <tbody id="cl-log-body"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="cl-part-overlay" class="cl-overlay" style="display:none;">
  <div class="card" style="width:min(560px, 94vw); max-height:90vh; display:flex; flex-direction:column;">
    <div class="card-header">
      <h3 class="card-title" id="cl-part-title">Add line</h3>
      <button type="button" class="btn-close ms-auto" onclick="clClosePart()"></button>
    </div>
    <div class="card-body" style="overflow-y:auto;">
      <div class="row g-2">
        <div class="col-8"><label class="form-label">Profile / part #</label><input id="cl-f-name" class="form-control"></div>
        <div class="col-4"><label class="form-label">Finish</label><input id="cl-f-finish" class="form-control" placeholder="e.g. C2"></div>
        <div class="col-6"><label class="form-label">Length (in)</label><input id="cl-f-dimension" class="form-control" placeholder="96, 48.375 or 96 3/8"></div>
        <div class="col-6"><label class="form-label">Qty</label><input id="cl-f-qty" type="number" min="1" class="form-control"></div>
        <div class="col-6"><label class="form-label">Use</label><input id="cl-f-description" class="form-control" placeholder="Head / Sill / Jamb"></div>
        <div class="col-6"><label class="form-label">Phase</label><input id="cl-f-phase" class="form-control"></div>
        <div class="col-6"><label class="form-label">Left cut angle</label><input id="cl-f-left" type="number" step="0.01" class="form-control"></div>
        <div class="col-6"><label class="form-label">Right cut angle</label><input id="cl-f-right" type="number" step="0.01" class="form-control"></div>
      </div>
      <div id="cl-part-error" class="text-danger small mt-2"></div>
    </div>
    <div class="card-footer text-end">
      <button class="btn btn-link" onclick="clClosePart()">Cancel</button>
      <button class="btn btn-primary" id="cl-part-save" onclick="clSavePart()">Save</button>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
const API = (p, o = {}) => fetch('/api/v1' + p, {
  ...o,
  credentials: 'include',
  headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', ...(o.headers || {}) },
});
const canEditCL = () => typeof hasPermission === 'function' ? hasPermission('fabrication.work-orders.edit') : true;

let clLists = [];
let clCurrent = null;
let clEditingPartId = null;

function esc(s) { if (s == null) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
function clToast(msg, ok = true) {
  const el = document.createElement('div');
  el.className = 'cl-toast alert alert-' + (ok ? 'success' : 'danger') + ' shadow';
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}
const statusClass = s => s === 'Done' ? 'bg-green-lt' : s === 'In progress' ? 'bg-orange-lt' : 'bg-secondary-lt';

async function clJson(r) {
  const body = await r.json().catch(() => ({}));
  if (!r.ok) {
    const first = body.errors ? Object.values(body.errors)[0][0] : null;
    throw new Error(first || body.error || body.message || ('Request failed (' + r.status + ')'));
  }
  return body;
}

// ── List ────────────────────────────────────────────────────────────────
async function clLoadList() {
  try {
    clLists = (await clJson(await API('/cut-lists'))).cut_lists || [];
    clRenderList();
  } catch (e) { clToast(e.message, false); }
}

function clRenderList() {
  const q = document.getElementById('cl-filter').value.trim().toLowerCase();
  const st = document.getElementById('cl-status').value;
  const rows = clLists.filter(l => (!q || l.name.toLowerCase().includes(q)) && (!st || l.status === st));
  document.getElementById('cl-list-body').innerHTML = rows.length ? rows.map(l => `
    <tr style="cursor:pointer" onclick="clOpen(${l.id})">
      <td class="fw-medium">${esc(l.name)}</td>
      <td>${esc(l.work_order_label) || '<span class="text-muted">—</span>'}</td>
      <td class="text-end">${l.parts_count}</td>
      <td class="text-end">${l.qty_cut} / ${l.qty_original}</td>
      <td><span class="badge ${statusClass(l.status)}">${l.status}</span></td>
      <td class="text-muted">${l.updated_at ? new Date(l.updated_at).toLocaleDateString() : ''}</td>
    </tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No cut lists found.</td></tr>';
}

function clShowList() {
  clCurrent = null;
  document.getElementById('cl-detail-view').style.display = 'none';
  document.getElementById('cl-list-view').style.display = '';
  document.getElementById('cl-back').style.display = 'none';
  history.replaceState(null, '', location.pathname);
  clLoadList();
}

// ── Detail ──────────────────────────────────────────────────────────────
async function clOpen(id) {
  try {
    const [d, l] = await Promise.all([API('/cut-lists/' + id).then(clJson), API('/cut-lists/' + id + '/log').then(clJson)]);
    clCurrent = d.cut_list;
    document.getElementById('cl-list-view').style.display = 'none';
    document.getElementById('cl-detail-view').style.display = '';
    document.getElementById('cl-back').style.display = '';
    history.replaceState(null, '', '#' + id);
    clRenderDetail(l.entries || []);
  } catch (e) { clToast(e.message, false); }
}

function clRenderDetail(log) {
  const c = clCurrent, edit = canEditCL();
  document.getElementById('cl-d-name').textContent = c.name;
  const sb = document.getElementById('cl-d-status');
  sb.className = 'badge ms-2 ' + statusClass(c.status);
  sb.textContent = c.status;
  document.getElementById('cl-d-warning').style.display = c.work_order_id ? '' : 'none';
  document.getElementById('cl-delete-btn').style.display = c.can_delete ? '' : 'none';

  document.getElementById('cl-parts-body').innerHTML = c.parts.length ? c.parts.map(p => `
    <tr class="${p.editable ? '' : 'cl-row-locked'}">
      <td class="fw-medium">${esc(p.name)}</td>
      <td>${esc(p.finish) || '—'}</td>
      <td class="text-end">${esc(p.dimension_inches.toFixed(3))}</td>
      <td>${esc(p.description) || '—'}</td>
      <td>${esc(p.phase) || '—'}</td>
      <td>${p.left_cut_angle ?? '—'} / ${p.right_cut_angle ?? '—'}</td>
      <td class="text-end">${p.qty_original}</td>
      <td class="text-end">${p.qty_remaining}</td>
      <td><span class="badge ${statusClass(p.status === 'Pending' ? 'Not cut' : p.status)}">${p.status}</span></td>
      <td class="text-end text-nowrap">${p.editable && edit ? `
        <button class="btn btn-sm btn-ghost-secondary" title="Edit" onclick="clOpenPart(${p.id})"><i class="ti ti-pencil"></i></button>
        <button class="btn btn-sm btn-ghost-danger" title="Delete" onclick="clDeletePart(${p.id})"><i class="ti ti-trash"></i></button>`
        : (p.editable ? '' : '<i class="ti ti-lock text-muted" title="Cutting has started — read-only"></i>')}</td>
    </tr>`).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">No lines.</td></tr>';

  document.getElementById('cl-log-body').innerHTML = log.length ? log.map(e => `
    <tr>
      <td class="text-nowrap">${esc(e.cut_at)}</td>
      <td>${esc(e.part_name)}</td>
      <td>${esc(e.finish) || '—'}</td>
      <td>${esc(e.description) || '—'}</td>
      <td class="text-end">${esc(e.dimension_inches.toFixed(3))}</td>
      <td>${esc(e.stick_length_label) || '—'}</td>
      <td>${esc(e.operator_name) || '—'}</td>
      <td>${e.is_recut ? '<span class="badge bg-orange-lt">recut</span>' : ''}</td>
    </tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">Nothing cut yet.</td></tr>';
}

async function clReload() {
  const id = clCurrent.id;
  await clOpen(id);
}

// ── Mutations ───────────────────────────────────────────────────────────
function clOpenPart(partId) {
  clEditingPartId = partId || null;
  const p = partId ? clCurrent.parts.find(x => x.id === partId) : null;
  document.getElementById('cl-part-title').textContent = p ? 'Edit line' : 'Add line';
  const set = (id, v) => document.getElementById('cl-f-' + id).value = v ?? '';
  set('name', p?.name); set('finish', p?.finish); set('dimension', p ? p.dimension_inches : '');
  set('qty', p?.qty_original); set('description', p?.description); set('phase', p?.phase);
  set('left', p?.left_cut_angle); set('right', p?.right_cut_angle);
  document.getElementById('cl-part-error').textContent = '';
  document.getElementById('cl-part-overlay').style.display = 'flex';
}
function clClosePart() { document.getElementById('cl-part-overlay').style.display = 'none'; }

async function clSavePart() {
  const v = id => document.getElementById('cl-f-' + id).value;
  const payload = {
    name: v('name'), finish: v('finish'), dimension: v('dimension'), qty: v('qty'),
    description: v('description'), phase: v('phase'),
    left_cut_angle: v('left') === '' ? null : v('left'),
    right_cut_angle: v('right') === '' ? null : v('right'),
  };
  const btn = document.getElementById('cl-part-save');
  btn.disabled = true;
  try {
    const base = '/cut-lists/' + clCurrent.id + '/parts';
    const r = await API(clEditingPartId ? base + '/' + clEditingPartId : base, {
      method: clEditingPartId ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const body = await clJson(r);
    clClosePart();
    clToast(body.message);
    await clReload();
  } catch (e) {
    document.getElementById('cl-part-error').textContent = e.message;
  } finally { btn.disabled = false; }
}

async function clDeletePart(partId) {
  if (!confirm('Delete this line from the cut list?')) return;
  try {
    clToast((await clJson(await API('/cut-lists/' + clCurrent.id + '/parts/' + partId, { method: 'DELETE' }))).message);
  } catch (e) { clToast(e.message, false); }
  await clReload();
}

async function clRename() {
  const name = prompt('Cut list name:', clCurrent.name);
  if (!name || name === clCurrent.name) return;
  try {
    const r = await API('/cut-lists/' + clCurrent.id, {
      method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name }),
    });
    clToast((await clJson(r)).message);
    await clReload();
  } catch (e) { clToast(e.message, false); }
}

async function clDeleteList() {
  if (!confirm('Delete the entire cut list "' + clCurrent.name + '"? Nothing on it has been cut.')) return;
  try {
    clToast((await clJson(await API('/cut-lists/' + clCurrent.id, { method: 'DELETE' }))).message);
    clShowList();
  } catch (e) { clToast(e.message, false); }
}

document.addEventListener('DOMContentLoaded', () => {
  const id = parseInt(location.hash.slice(1), 10);
  id ? clOpen(id) : clLoadList();
});
</script>
@endpush
