@extends('layouts.app')

@section('title', 'Cut Lists – Fabrication')

@section('styles')
.cl-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1060; align-items:center; justify-content:center; }
.cl-overlay > .card { box-shadow:0 1rem 3rem rgba(0,0,0,.5); }
.cl-row-locked { background:var(--tblr-bg-surface-secondary); }
.cl-group { border:1px solid var(--tblr-border-color); border-radius:var(--tblr-border-radius); }
.cl-group input.form-control, .cl-group select.form-select { border-radius:0; }
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
        <div class="col-auto ms-auto d-flex gap-2">
          <button class="btn btn-primary" id="cl-new-btn" data-permission="fabrication.work-orders.edit" onclick="clOpenNew()">
            <i class="ti ti-plus me-1"></i>New cut list
          </button>
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
            <label class="form-check form-switch ms-auto mb-0" title="Cut lists of completed work orders">
              <input class="form-check-input" type="checkbox" id="cl-archived" onchange="clLoadList()">
              <span class="form-check-label">Archived <span class="badge bg-secondary-lt" id="cl-archived-count"></span></span>
            </label>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr id="cl-head">
                <th data-sort="job">Job</th><th data-sort="wo">Work order</th><th data-sort="lines" class="text-end">Lines</th><th data-sort="cut" class="text-end">Cut / Total</th><th data-sort="status">Status</th><th data-sort="updated">Updated</th>
              </tr></thead>
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
            <div class="card-actions">
              <a class="btn btn-sm btn-outline-primary" id="cl-sill-btn" style="display:none" target="_blank" rel="noopener"><i class="ti ti-file-type-pdf me-1"></i>Sill quarter points</a>
              <span data-permission="fabrication.work-orders.edit">
              <button class="btn btn-sm btn-outline-secondary" onclick="clRename()"><i class="ti ti-pencil me-1"></i>Rename</button>
              <button class="btn btn-sm btn-success" id="cl-markcut-btn" onclick="clOpenMarkCut()"><i class="ti ti-check me-1"></i>Mark cut (other saw)</button>
              <button class="btn btn-sm btn-primary" onclick="clOpenPart()"><i class="ti ti-plus me-1"></i>Add line</button>
              <button class="btn btn-sm btn-primary" onclick="clOpenBulk()"><i class="ti ti-table-plus me-1"></i>Bulk add</button>
              <button class="btn btn-sm btn-outline-danger" id="cl-delete-btn" onclick="clDeleteList()"><i class="ti ti-trash me-1"></i>Delete list</button>
              </span>
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

        <div class="card mb-3" id="cl-links-card">
          <div class="card-header">
            <h3 class="card-title">Elevation links</h3>
            <div class="card-subtitle text-muted ms-2 small">Ties this list's phases to work-order elevations so their Cut step starts and completes automatically.</div>
          </div>
          <div id="cl-links-body" class="card-body"><span class="text-muted small">Loading…</span></div>
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
<div id="cl-mark-overlay" class="cl-overlay" style="display:none;">
  <div class="card" style="width:min(680px, 96vw); max-height:92vh; display:flex; flex-direction:column;">
    <div class="card-header">
      <h3 class="card-title">Mark cut on another saw</h3>
      <button type="button" class="btn-close ms-auto" onclick="clCloseMarkCut()"></button>
    </div>
    <div class="card-body" style="overflow-y:auto;">
      <div class="row g-2 mb-3">
        <div class="col-7"><label class="form-label">Cut by</label><select id="cl-m-user" class="form-select" data-sort></select></div>
        <div class="col-5"><label class="form-label">Date cut</label><input id="cl-m-date" type="date" class="form-control"></div>
      </div>
      <div class="text-secondary small mb-2">
        Set how many pieces of each line were cut (defaults to everything remaining). They're logged as cuts, count in the
        cut totals, and draw stock from inventory exactly like a cut at the saw station.
      </div>
      <table class="table table-sm">
        <thead><tr><th>Profile</th><th>Finish</th><th class="text-end">Length</th><th>Use</th><th class="text-end">Remaining</th><th style="width:90px">Cut</th></tr></thead>
        <tbody id="cl-m-body"></tbody>
      </table>
      <div id="cl-mark-error" class="text-danger small mt-2"></div>
    </div>
    <div class="card-footer d-flex align-items-center">
      <span class="text-muted small" id="cl-m-summary"></span>
      <div class="ms-auto">
        <button class="btn btn-link" onclick="clCloseMarkCut()">Cancel</button>
        <button class="btn btn-success" id="cl-mark-save" onclick="clSaveMarkCut()">Mark as cut</button>
      </div>
    </div>
  </div>
</div>
<div id="cl-new-overlay" class="cl-overlay" style="display:none;">
  <div class="card" style="width:min(520px, 94vw);">
    <div class="card-header">
      <h3 class="card-title">New cut list</h3>
      <button type="button" class="btn-close ms-auto" onclick="clCloseNew()"></button>
    </div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Job / work order</label>
        <select id="cl-n-wo" class="form-select" onchange="clNewWoChanged()" data-sort></select>
        <div class="form-hint">Only work orders without a cut list are shown. Choose "Standalone" for a list not tied to a work order.</div>
      </div>
      <div class="mb-0">
        <label class="form-label">Name</label>
        <input id="cl-n-name" class="form-control" placeholder="Defaults to job name + release">
      </div>
      <div id="cl-new-error" class="text-danger small mt-2"></div>
    </div>
    <div class="card-footer text-end">
      <button class="btn btn-link" onclick="clCloseNew()">Cancel</button>
      <button class="btn btn-primary" id="cl-new-save" onclick="clSaveNew()">Create &amp; open</button>
    </div>
  </div>
</div>
<div id="cl-bulk-overlay" class="cl-overlay" style="display:none;">
  <div class="card" style="width:min(900px, 96vw); max-height:92vh; display:flex; flex-direction:column;">
    <div class="card-header">
      <h3 class="card-title">Bulk add lines</h3>
      <button type="button" class="btn-close ms-auto" onclick="clCloseBulk()"></button>
    </div>
    <div class="card-body" style="overflow-y:auto;">
      <div class="text-secondary small mb-3">
        Pick a part number and finish, then type quantity and length underneath — a new row appears as you go.
        Lengths can be decimal (48.375) or fractions (96 3/8). You can paste two columns (qty, length) straight
        from a spreadsheet into any row. Tab / Enter moves to the next cell.
      </div>
      <datalist id="cl-pn-list" data-sort></datalist>
      <div id="cl-bulk-groups" class="row g-3"></div>
      <button class="btn btn-outline-primary btn-sm mt-3" onclick="clAddGroup(true)"><i class="ti ti-plus me-1"></i>Add another part</button>
      <div id="cl-bulk-error" class="text-danger small mt-2"></div>
    </div>
    <div class="card-footer d-flex align-items-center">
      <span class="text-muted small" id="cl-bulk-summary"></span>
      <div class="ms-auto">
        <button class="btn btn-link" onclick="clCloseBulk()">Cancel</button>
        <button class="btn btn-primary" id="cl-bulk-save" onclick="clSaveBulk()">Add to cut list</button>
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
    const arch = document.getElementById('cl-archived')?.checked;
    const res = await clJson(await API('/cut-lists' + (arch ? '?archived=1' : '')));
    clLists = res.cut_lists || [];
    document.getElementById('cl-archived-count').textContent = res.archived_count || '';
    clRenderList();
  } catch (e) { clToast(e.message, false); }
}

// Sortable columns. Default: status (In progress → Not cut → Done), then job name.
const CL_STATUS_RANK = { 'In progress': 0, 'Not cut': 1, 'Done': 2 };
const CL_SORTS = {
  job:     l => (l.job_name || l.name || '').toLowerCase(),
  wo:      l => (l.work_order_label || '').toLowerCase(),
  lines:   l => l.parts_count || 0,
  cut:     l => (l.qty_original ? l.qty_cut / l.qty_original : 0),
  status:  l => CL_STATUS_RANK[l.status] ?? 9,
  updated: l => l.updated_at ? Date.parse(l.updated_at) : 0,
};
let clSort = { key: 'status', dir: 1 };
function clSortBy(key) {
  clSort = clSort.key === key ? { key, dir: -clSort.dir } : { key, dir: 1 };
  clRenderList();
}
function clCompare(a, b) {
  const f = CL_SORTS[clSort.key];
  const x = f(a), y = f(b);
  let c = typeof x === 'string' ? x.localeCompare(y, undefined, { numeric: true }) : x - y;
  c *= clSort.dir;
  if (c) return c;
  // stable tie-breakers: status rank, then job name
  return (CL_SORTS.status(a) - CL_SORTS.status(b)) || CL_SORTS.job(a).localeCompare(CL_SORTS.job(b), undefined, { numeric: true });
}
function clRenderHead() {
  document.querySelectorAll('#cl-head th[data-sort]').forEach(th => {
    th.style.cursor = 'pointer'; th.style.userSelect = 'none';
    th.onclick = () => clSortBy(th.dataset.sort);
    const on = th.dataset.sort === clSort.key;
    th.setAttribute('aria-sort', on ? (clSort.dir === 1 ? 'ascending' : 'descending') : 'none');
    th.querySelector('.cl-arrow')?.remove();
    if (on) th.insertAdjacentHTML('beforeend', `<span class="cl-arrow ms-1">${clSort.dir === 1 ? '▲' : '▼'}</span>`);
  });
}

function clRenderList() {
  clRenderHead();
  const q = document.getElementById('cl-filter').value.trim();
  const st = document.getElementById('cl-status').value;
  const rows = clLists.filter(l => fdSearch.match(q, l.name, l.job_name, l.work_order_label) && (!st || l.status === st)).sort(clCompare);
  document.getElementById('cl-list-body').innerHTML = rows.length ? rows.map(l => `
    <tr style="cursor:pointer" onclick="clOpen(${l.id})">
      <td class="fw-medium">${esc(l.job_name || l.name)}${l.sequence > 1 ? ' <span class="badge bg-azure-lt">Add-on</span>' : ''}${l.job_name ? `<div class="text-muted small fw-normal">${esc(l.name)}</div>` : ''}</td>
      <td>${esc(l.work_order_label) || '<span class="text-muted">—</span>'}</td>
      <td class="text-end">${l.parts_count}</td>
      <td class="text-end">${l.qty_cut} / ${l.qty_original}</td>
      <td><span class="badge ${statusClass(l.status)}">${l.status}</span></td>
      <td class="text-muted">${l.updated_at ? new Date(l.updated_at).toLocaleDateString() : ''}</td>
    </tr>`).join('') : `<tr><td colspan="6" class="text-center text-muted py-4">${document.getElementById('cl-archived')?.checked ? 'No archived cut lists — they appear here once their work order is complete.' : 'No cut lists found.'}</td></tr>`;
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
    clLoadLinks();
  } catch (e) { clToast(e.message, false); }
}

function clRenderDetail(log) {
  const c = clCurrent, edit = canEditCL();
  document.getElementById('cl-d-name').textContent = c.name;
  const sb = document.getElementById('cl-d-status');
  sb.className = 'badge ms-2 ' + statusClass(c.status);
  sb.textContent = c.status;
  document.getElementById('cl-d-warning').style.display = c.work_order_id ? '' : 'none';
  document.getElementById('cl-markcut-btn').style.display = c.parts.some(p => p.qty_remaining > 0) ? '' : 'none';
  document.getElementById('cl-delete-btn').style.display = c.can_delete ? '' : 'none';
  const sillBtn = document.getElementById('cl-sill-btn');
  sillBtn.style.display = c.has_sill_report ? '' : 'none';
  sillBtn.href = `/api/v1/cut-lists/${c.id}/sill-quarter-points`;

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
      <td>${e.is_recut ? '<span class="badge bg-orange-lt">recut</span>' : ''}${e.is_external ? '<span class="badge bg-blue-lt">other saw</span>' : ''}</td>
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

// ── Mark cut on another saw ─────────────────────────────────────────────
let clCutters = null;

async function clOpenMarkCut() {
  if (!clCutters) {
    try { clCutters = (await clJson(await API('/cut-lists-cutters'))).users || []; }
    catch (e) { clToast(e.message, false); return; }
  }
  document.getElementById('cl-m-user').innerHTML = '<option value="">Choose…</option>' +
    clCutters.map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('');
  const d = new Date();
  document.getElementById('cl-m-date').value = [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
  document.getElementById('cl-m-date').max = document.getElementById('cl-m-date').value;
  document.getElementById('cl-m-body').innerHTML = clCurrent.parts.filter(p => p.qty_remaining > 0).map(p => `
    <tr data-part="${p.id}" data-max="${p.qty_remaining}">
      <td class="fw-medium">${esc(p.name)}</td><td>${esc(p.finish) || '—'}</td>
      <td class="text-end">${esc(p.dimension_inches.toFixed(3))}</td><td>${esc(p.description) || '—'}</td>
      <td class="text-end">${p.qty_remaining}</td>
      <td><input type="number" min="0" max="${p.qty_remaining}" value="${p.qty_remaining}" class="form-control form-control-sm cl-m-qty" oninput="clMarkSummary()"></td>
    </tr>`).join('');
  document.getElementById('cl-mark-error').textContent = '';
  clMarkSummary();
  document.getElementById('cl-mark-overlay').style.display = 'flex';
}
function clCloseMarkCut() { document.getElementById('cl-mark-overlay').style.display = 'none'; }

function clMarkRows() {
  return [...document.querySelectorAll('#cl-m-body tr')]
    .map(tr => ({ part_id: parseInt(tr.dataset.part, 10), qty: parseInt(tr.querySelector('.cl-m-qty').value, 10) || 0, max: parseInt(tr.dataset.max, 10) }))
    .filter(r => r.qty > 0);
}
function clMarkSummary() {
  const n = clMarkRows().reduce((a, r) => a + r.qty, 0);
  document.getElementById('cl-m-summary').textContent = n ? n + ' piece(s)' : '';
}

async function clSaveMarkCut() {
  const err = document.getElementById('cl-mark-error');
  err.textContent = '';
  const rows = clMarkRows();
  const user = document.getElementById('cl-m-user').value, date = document.getElementById('cl-m-date').value;
  if (!user) { err.textContent = 'Choose who cut these.'; return; }
  if (!date) { err.textContent = 'Enter the date they were cut.'; return; }
  if (!rows.length) { err.textContent = 'Enter a quantity for at least one line.'; return; }
  if (rows.some(r => r.qty > r.max)) { err.textContent = 'A quantity is more than what remains on that line.'; return; }
  const btn = document.getElementById('cl-mark-save');
  btn.disabled = true;
  try {
    const body = await clJson(await API('/cut-lists/' + clCurrent.id + '/mark-cut', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ operator_id: parseInt(user, 10), cut_date: date, parts: rows.map(r => ({ part_id: r.part_id, qty: r.qty })) }),
    }));
    clCloseMarkCut();
    clToast(body.message);
    await clReload();
  } catch (e) { err.textContent = e.message; }
  finally { btn.disabled = false; }
}

// ── New cut list ────────────────────────────────────────────────────────
let clWoOptions = [];

async function clOpenNew() {
  try {
    clWoOptions = (await clJson(await API('/cut-lists/work-order-options'))).work_orders || [];
  } catch (e) { clToast(e.message, false); return; }
  document.getElementById('cl-n-wo').innerHTML = '<option value="">Standalone (no work order)</option>' +
    clWoOptions.map(w => `<option value="${w.id}">${esc(w.label)} – ${esc(w.job_name || '')}</option>`).join('');
  document.getElementById('cl-n-name').value = '';
  document.getElementById('cl-new-error').textContent = '';
  document.getElementById('cl-new-overlay').style.display = 'flex';
}
function clCloseNew() { document.getElementById('cl-new-overlay').style.display = 'none'; }

function clNewWoChanged() {
  const w = clWoOptions.find(x => String(x.id) === document.getElementById('cl-n-wo').value);
  document.getElementById('cl-n-name').value = w ? w.name : '';
}

async function clSaveNew() {
  const err = document.getElementById('cl-new-error');
  err.textContent = '';
  const woId = document.getElementById('cl-n-wo').value;
  const btn = document.getElementById('cl-new-save');
  btn.disabled = true;
  try {
    const body = await clJson(await API('/cut-lists', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ work_order_id: woId ? parseInt(woId, 10) : null, name: document.getElementById('cl-n-name').value.trim() }),
    }));
    clCloseNew();
    clToast(body.message);
    await clOpen(body.id);
  } catch (e) { err.textContent = e.message; }
  finally { btn.disabled = false; }
}

// ── Bulk add ────────────────────────────────────────────────────────────
let clFinishes = null;
let clPnLoaded = false;

async function clOpenBulk() {
  if (!clPnLoaded) {
    try {
      const o = await clJson(await API('/cut-lists/part-options'));
      clFinishes = o.finishes || {};
      document.getElementById('cl-pn-list').innerHTML = (o.part_numbers || []).map(n => `<option value="${esc(n)}">`).join('');
      clPnLoaded = true;
    } catch (e) { clToast(e.message, false); return; }
  }
  document.getElementById('cl-bulk-groups').innerHTML = '';
  document.getElementById('cl-bulk-error').textContent = '';
  clAddGroup(false);
  document.getElementById('cl-bulk-overlay').style.display = 'flex';
  clUpdateBulkSummary();
}
function clCloseBulk() { document.getElementById('cl-bulk-overlay').style.display = 'none'; }

function clAddGroup(focus, prev) {
  const finishOpts = Object.entries(clFinishes || {}).map(([c, l]) => `<option value="${esc(c)}">${esc(c)} – ${esc(l)}</option>`).join('');
  const g = document.createElement('div');
  g.className = 'col-md-6 cl-group-wrap';
  g.innerHTML = `
    <div class="cl-group">
      <div class="d-flex border-bottom bg-surface-secondary">
        <input class="form-control cl-g-name" list="cl-pn-list" placeholder="Part number" style="flex:2" oninput="clUpdateBulkSummary()">
        <select class="form-select cl-g-finish" style="flex:2"><option value="">No finish</option>${finishOpts}</select>
        <button class="btn btn-ghost-danger" title="Remove part" onclick="this.closest('.cl-group-wrap').remove(); clUpdateBulkSummary()"><i class="ti ti-x"></i></button>
      </div>
      <table class="table table-sm mb-0">
        <thead><tr><th style="width:90px">Qty</th><th>Length (in)</th></tr></thead>
        <tbody class="cl-g-rows"></tbody>
      </table>
    </div>`;
  document.getElementById('cl-bulk-groups').appendChild(g);
  if (prev) g.querySelector('.cl-g-finish').value = prev.finish;
  clAddRow(g.querySelector('.cl-g-rows'));
  if (focus) g.querySelector('.cl-g-name').focus();
}

function clAddRow(tbody, qty = '', len = '') {
  const tr = document.createElement('tr');
  tr.innerHTML = `<td class="p-0"><input type="text" inputmode="numeric" class="form-control form-control-sm border-0 cl-r-qty" value="${esc(qty)}"></td>
    <td class="p-0"><input type="text" class="form-control form-control-sm border-0 cl-r-len" value="${esc(len)}" placeholder="96, 48.375 or 96 3/8"></td>`;
  tbody.appendChild(tr);
  const [q, l] = tr.querySelectorAll('input');
  const ensureSpare = () => {
    const last = tbody.lastElementChild;
    if (last && [...last.querySelectorAll('input')].some(i => i.value.trim() !== '')) clAddRow(tbody);
    clUpdateBulkSummary();
  };
  [q, l].forEach(inp => {
    inp.addEventListener('input', ensureSpare);
    inp.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const rows = [...tbody.children], i = rows.indexOf(tr);
        const next = rows[i + 1] || (clAddRow(tbody), tbody.lastElementChild);
        next.querySelector(inp === q ? '.cl-r-qty' : '.cl-r-len').focus();
      }
    });
    inp.addEventListener('paste', e => {
      const text = (e.clipboardData || window.clipboardData).getData('text');
      if (!/[\n\t]/.test(text.trim())) return;
      e.preventDefault();
      const rows = [...tbody.children];
      let at = rows.indexOf(tr);
      text.replace(/\r/g, '').split('\n').filter(r => r.trim() !== '').forEach(line => {
        const cells = line.split('\t');
        if (!tbody.children[at]) clAddRow(tbody);
        const row = tbody.children[at++];
        if (cells.length >= 2) {
          row.querySelector('.cl-r-qty').value = cells[0].trim();
          row.querySelector('.cl-r-len').value = cells[1].trim();
        } else {
          row.querySelector(inp === q ? '.cl-r-qty' : '.cl-r-len').value = cells[0].trim();
        }
      });
      ensureSpare();
    });
  });
}

function clCollectBulk() {
  const groups = [];
  document.querySelectorAll('#cl-bulk-groups .cl-group').forEach(g => {
    const name = g.querySelector('.cl-g-name').value.trim();
    const finish = g.querySelector('.cl-g-finish').value;
    const rows = [...g.querySelectorAll('.cl-g-rows tr')]
      .map(tr => ({ qty: tr.querySelector('.cl-r-qty').value.trim(), length: tr.querySelector('.cl-r-len').value.trim() }))
      .filter(r => r.qty !== '' || r.length !== '');
    if (name || rows.length) groups.push({ name, finish, rows });
  });
  return groups;
}

function clUpdateBulkSummary() {
  const g = clCollectBulk();
  const lines = g.reduce((n, x) => n + x.rows.length, 0);
  const pcs = g.reduce((n, x) => n + x.rows.reduce((m, r) => m + (parseInt(r.qty, 10) || 0), 0), 0);
  document.getElementById('cl-bulk-summary').textContent = lines ? `${lines} line(s), ${pcs} piece(s)` : '';
}

async function clSaveBulk() {
  const err = document.getElementById('cl-bulk-error');
  err.textContent = '';
  const groups = clCollectBulk();
  for (const g of groups) {
    if (!g.name) { err.textContent = 'Every group with rows needs a part number.'; return; }
    for (const r of g.rows) {
      if (!/^\d+$/.test(r.qty) || parseInt(r.qty, 10) < 1) { err.textContent = `${g.name}: quantity "${r.qty}" must be a whole number of 1 or more.`; return; }
      if (r.length === '') { err.textContent = `${g.name}: a row has a quantity but no length.`; return; }
    }
    if (!g.rows.length) { err.textContent = `${g.name}: add at least one qty / length row.`; return; }
  }
  if (!groups.length) { err.textContent = 'Nothing to add yet.'; return; }
  const btn = document.getElementById('cl-bulk-save');
  btn.disabled = true;
  try {
    const body = await clJson(await API('/cut-lists/' + clCurrent.id + '/parts/bulk', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ groups }),
    }));
    clCloseBulk();
    clToast(body.message);
    await clReload();
  } catch (e) { err.textContent = e.message; }
  finally { btn.disabled = false; }
}

// ── Elevation links (phase → work-order elevations, for lists whose tags don't match on their own) ──
let clLinks = null;

async function clLoadLinks() {
  const box = document.getElementById('cl-links-body');
  if (!box || !clCurrent) return;
  try {
    clLinks = await clJson(await API(`/cut-lists/${clCurrent.id}/elevation-links`));
    clRenderLinks();
  } catch (e) { box.innerHTML = '<span class="text-danger small">Could not load links.</span>'; }
}

function clRenderLinks() {
  const box = document.getElementById('cl-links-body');
  const edit = canEditCL(), d = clLinks;
  const wo = d.work_order;
  const head = wo
    ? `<div class="mb-2">Work order: <strong>${esc(wo.label)}</strong>${wo.job_name ? ' – ' + esc(wo.job_name) : ''}${wo.by_name ? ' <span class="text-muted small">(matched from the list name)</span>' : ''}
        ${edit ? `<a href="#" class="ms-2 small" onclick="clPickWorkOrder(); return false;">Change</a>` : ''}</div>`
    : `<div class="alert alert-warning py-2 d-flex align-items-center">This list isn't tied to a work order, so its cutting can't update any elevation.
        ${edit ? `<button class="btn btn-sm btn-primary ms-auto" onclick="clPickWorkOrder()">Link to a work order…</button>` : ''}</div>`;
  const rows = d.phases.length ? `<div class="table-responsive"><table class="table table-sm table-vcenter mb-0">
      <thead><tr><th>Phase</th><th class="text-end">Lines</th><th class="text-end">Remaining</th><th>Elevations</th><th></th></tr></thead><tbody>
      ${d.phases.map((p, i) => `<tr><td class="fw-medium">${esc(p.phase)}</td><td class="text-end">${p.lines}</td><td class="text-end">${p.remaining} / ${p.qty}</td>
        <td>${p.auto.map(e => `<span class="badge bg-green-lt me-1" title="Matched automatically">${esc(e.tag)}</span>`).join('')}${p.manual.map(e => `<span class="badge bg-azure-lt me-1" title="Linked by hand">${esc(e.tag)}</span>`).join('')}
          ${p.linked ? '' : '<span class="badge bg-orange-lt">Not linked</span>'}</td>
        <td class="text-end">${edit && wo ? `<button class="btn btn-sm" onclick="clOpenLink(${i})">${p.manual.length ? 'Edit link' : 'Link…'}</button>` : ''}</td></tr>`).join('')}
      </tbody></table></div>` : '<div class="text-muted small">No phases on this list (lines have no elevation tag), so there is nothing to link.</div>';
  box.innerHTML = head + rows;
}

async function clPickWorkOrder() {
  let opts;
  try { opts = (await clJson(await API('/cut-lists/work-order-options?all=1'))).work_orders || []; } catch (e) { clToast(e.message, false); return; }
  const cur = clLinks?.work_order?.id;
  const html = `<div class="col-12"><label class="form-label">Work order</label><select id="cl-lw-sel" class="form-select">
      <option value="">— none (unlink) —</option>${opts.map(w => `<option value="${w.id}" ${w.id === cur ? 'selected' : ''}>${esc(w.label)} – ${esc(w.job_name || '')}</option>`).join('')}</select></div>`;
  clLinkModal('Link to a work order', html, async () => {
    const v = document.getElementById('cl-lw-sel').value;
    clLinks = await clJson(await API(`/cut-lists/${clCurrent.id}/work-order`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ work_order_id: v ? +v : null }) }));
    clRenderLinks();
    clToast(v ? 'Linked to the work order.' : 'Unlinked.');
  });
}

function clOpenLink(i) {
  const p = clLinks.phases[i];
  const on = new Set([...p.auto, ...p.manual].map(e => e.id));
  const html = `<div class="col-12 small text-muted mb-1">Tick the elevations that phase <strong>${esc(p.phase)}</strong> belongs to. Cutting already done on its lines counts straight away.</div>` +
    (clLinks.elevations.map(e => `<div class="col-12"><label class="form-check mb-0"><input type="checkbox" class="form-check-input cl-lk" value="${e.id}" ${on.has(e.id) ? 'checked' : ''} ${p.auto.some(a => a.id === e.id) ? 'disabled' : ''}>
      <span class="form-check-label"><strong>${esc(e.tag || '#' + e.id)}</strong> <span class="text-muted">${esc(e.type || '')}${e.quantity ? ' ×' + e.quantity : ''}</span>${p.auto.some(a => a.id === e.id) ? ' <span class="badge bg-green-lt">auto</span>' : ''}</span></label></div>`).join('')
      || '<div class="col-12 text-muted">This work order has no elevations yet.</div>');
  clLinkModal('Link ' + p.phase, html, async () => {
    const ids = [...document.querySelectorAll('.cl-lk:checked:not(:disabled)')].map(c => +c.value);
    clLinks = await clJson(await API(`/cut-lists/${clCurrent.id}/elevation-links`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ phase: p.phase, elevation_ids: ids }) }));
    clRenderLinks();
    clToast('Links saved.');
  });
}

// Small self-contained modal (the page's other overlays are fixed forms).
function clLinkModal(title, bodyHtml, onSave) {
  document.getElementById('cl-link-overlay')?.remove();
  const ov = document.createElement('div');
  ov.id = 'cl-link-overlay'; ov.className = 'cl-overlay'; ov.style.display = 'flex';
  ov.innerHTML = `<div class="card" style="width:min(560px,94vw);max-height:90vh;display:flex;flex-direction:column;">
    <div class="card-header"><h3 class="card-title">${esc(title)}</h3><button type="button" class="btn-close ms-auto"></button></div>
    <div class="card-body" style="overflow-y:auto;"><div class="row g-2">${bodyHtml}</div><div class="text-danger small mt-2" id="cl-link-err"></div></div>
    <div class="card-footer text-end"><button class="btn btn-link" data-x>Cancel</button><button class="btn btn-primary" data-save>Save</button></div></div>`;
  document.body.appendChild(ov);
  const close = () => ov.remove();
  ov.querySelector('.btn-close').onclick = close; ov.querySelector('[data-x]').onclick = close;
  ov.querySelector('[data-save]').onclick = async () => {
    try { await onSave(); close(); } catch (e) { document.getElementById('cl-link-err').textContent = e.message; }
  };
}

document.addEventListener('DOMContentLoaded', () => {
  const id = parseInt(location.hash.slice(1), 10);
  id ? clOpen(id) : clLoadList();
});
</script>
@endpush
