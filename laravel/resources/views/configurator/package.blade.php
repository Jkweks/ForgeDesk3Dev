@extends('layouts.app')

@section('title', 'Fabrication Package')

@section('styles')
<style>
  .pk-pages { max-width: 900px; margin: 0 auto; padding-bottom: 48px; }
  .xpage { background: #fff; color: #111; margin-bottom: 20px; padding: 28px 36px; box-shadow: 0 1px 6px rgba(0,0,0,.14); font-family: 'Calibri','Carlito',Arial,Helvetica,sans-serif; font-size: 13px; line-height: 1.5; }
  .xpage-hdr { border-bottom: 2.5px solid #1a1a2e; padding-bottom: 8px; margin-bottom: 14px; }
  .xpage-title { font-size: 1rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #1a1a2e; }
  /* block grid: 12 columns, native wrap — ported from fab_utils' ReportBlocks */
  .rb-grid { display: grid; grid-template-columns: repeat(12, 1fr); grid-auto-flow: row dense; gap: 8px 14px; margin-bottom: 6px; }
  .rb-cell, .rb-block { min-width: 0; }
  .rb-title { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #444; border-bottom: 1px solid #ccc; padding-bottom: 2px; margin-bottom: 3px; }
  .rb-table { width: 100%; border-collapse: collapse; font-size: 10.5px; line-height: 1.15; table-layout: auto; }
  .rb-table th { background: #1a1a2e; color: #e8ecf4; text-align: left; padding: 3px 6px; font-size: 8.5px; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
  .rb-table td { padding: 1.5px 6px; border-bottom: 1px solid #eef0f4; vertical-align: middle; word-break: break-word; }
  .rb-table tr:last-child td { border-bottom: none; }
  .rb-table tr:nth-child(even) td { background: #fafbff; }
  .rb-bold { font-weight: 700; }
  .rb-highlight { display: inline-block; background: #000; color: #fff; padding: 1px 6px; border-radius: 2px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .rb-empty { font-size: 10.5px; color: #999; font-style: italic; }
  .rb-pills { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 7px 10px; background: #f7f8fa; border: 1px solid #dde0e8; padding: 8px 12px; }
  .rb-pill-lbl { font-size: 8px; text-transform: uppercase; letter-spacing: .06em; color: #999; }
  .rb-pill-val { font-size: 11.5px; font-weight: 700; color: #111; font-family: monospace; }
  .rb-initial-box { display: inline-block; width: 44px; height: 15px; border: 1px solid #999; border-radius: 2px; vertical-align: middle; }

  @media print {
    body * { visibility: hidden; }
    .pk-pages, .pk-pages * { visibility: visible; }
    .pk-pages { position: absolute; left: 0; top: 0; max-width: 100%; width: 100%; padding: 0; }
    .xpage { box-shadow: none; margin: 0; padding: 18px 24px; font-size: 10.5px; break-before: page; page-break-before: always; }
    .xpage:first-child { break-before: auto; page-break-before: auto; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .rb-table th, .rb-pills { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
@endsection

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Configurator</div>
          <h1 class="page-title">Fabrication Package</h1>
          <p class="text-muted mb-0">Door and frame sheets with hardware, backers, prep values and inspection sign-off, plus cut list, stock lengths, BOM and field-install pick list.</p>
        </div>
        <div class="col-auto ms-auto btn-list">
          <button class="btn btn-outline-secondary" onclick="pkOpenEditor()" data-permission="configurator.catalog.manage"><i class="ti ti-layout me-1"></i>Edit layout</button>
          <button class="btn btn-outline-primary" onclick="pkExportCutCsv()" data-permission="configurator.view"><i class="ti ti-file-spreadsheet me-1"></i>Cut list CSV</button>
          <button class="btn btn-primary" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print / Save PDF</button>
        </div>
      </div>
    </div>
  </div>

  <main class="page-body">
    <div class="container-xl">
      <div class="card mb-3 d-print-none">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Job</label>
              <select class="form-select" id="pk-job" onchange="pkJobChanged()"><option value="">Select a job…</option></select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Work order</label>
              <select class="form-select" id="pk-wo" onchange="pkLoad()"><option value="">All work orders</option></select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Sections</label>
              <div class="d-flex flex-wrap gap-3">
                <label class="form-check"><input class="form-check-input pk-sec" type="checkbox" value="doors" checked onchange="pkLoad()"><span class="form-check-label">Doors</span></label>
                <label class="form-check"><input class="form-check-input pk-sec" type="checkbox" value="frames" checked onchange="pkLoad()"><span class="form-check-label">Frames</span></label>
                <label class="form-check"><input class="form-check-input pk-sec" type="checkbox" value="cut_list" checked onchange="pkLoad()"><span class="form-check-label">Cut list</span></label>
                <label class="form-check"><input class="form-check-input pk-sec" type="checkbox" value="stock" checked onchange="pkLoad()"><span class="form-check-label">Stock</span></label>
                <label class="form-check"><input class="form-check-input pk-sec" type="checkbox" value="bom" checked onchange="pkLoad()"><span class="form-check-label">BOM</span></label>
                <label class="form-check"><input class="form-check-input pk-sec" type="checkbox" value="field_install" checked onchange="pkLoad()"><span class="form-check-label">Field install</span></label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="pk-pages" id="pk-pages"><div class="text-muted">Select a job to build the package.</div></div>
    </div>
  </main>
</div>


<div class="modal modal-blur fade" id="pk-editor-modal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Sheet layout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted">Order, width (1&ndash;12 columns; 6 = half a page) and visibility of the blocks on each door / frame sheet. Narrow blocks sit side by side. Only the format changes &mdash; the data is always live.</p>
        <ul class="nav nav-tabs mb-3" id="pk-ed-tabs">
          <li class="nav-item"><a href="#" class="nav-link active" onclick="pkEdSwitch('door'); return false;">Door sheet</a></li>
          <li class="nav-item"><a href="#" class="nav-link" onclick="pkEdSwitch('frame'); return false;">Frame sheet</a></li>
        </ul>
        <div id="pk-ed-body"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="pkEdSave()">Save layout</button>
      </div>
    </div>
  </div>
</div>

<script>
let pkJobs = [];

const PK_TITLES = {
  hinge_prep: 'HINGE PREP LOCATIONS', extrusion: 'EXTRUSION OUTPUT', component: 'COMPONENT OUTPUT', weatherstrip: 'WEATHERSTRIPPING & GASKETS', hardware: 'HARDWARE & COMPONENTS',
  hwlib_hardware: 'HWLIB CUSTOM / STANDARD HARDWARE', hwlib_backers: 'HWLIB BACKERS & FASTENERS', hwlib_variables: 'HARDWARE VARIABLES', hwlib_inspection: 'INSPECTION SIGN-OFF',
};

function pkEsc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function pkCell(c) {
  if (c && typeof c === 'object' && 'content' in c) {
    const cls = c.bold ? 'rb-bold' : '';
    const bg = c.fill ? ' style="background:#f4f5f8;"' : '';
    const span = c.colSpan ? ` colspan="${c.colSpan}"` : '';
    return `<td class="${cls}"${span}${bg}>${c.highlight ? `<span class="rb-highlight">${pkEsc(c.content)}</span>` : pkEsc(c.content)}</td>`;
  }
  return `<td>${pkEsc(c)}</td>`;
}

// cols: optional booleans aligned with head — a column is shown unless cols[i] === false.
function pkTable(title, head, rows, empty, cols) {
  let h = head, r = rows;
  if (cols && cols.length) {
    const idx = head.map((_, i) => i).filter(i => cols[i] !== false);
    h = idx.map(i => head[i]);
    r = (rows || []).map(row => idx.map(i => row[i]));
  }
  if (!r || !r.length) return `<div class="rb-block"><div class="rb-title">${pkEsc(title)}</div><p class="rb-empty">${pkEsc(empty || 'None')}</p></div>`;
  return `<div class="rb-block"><div class="rb-title">${pkEsc(title)}</div><table class="rb-table">
    <thead><tr>${h.map(x => `<th>${pkEsc(x)}</th>`).join('')}</tr></thead>
    <tbody>${r.map(row => `<tr>${row.map(pkCell).join('')}</tr>`).join('')}</tbody></table></div>`;
}

function pkPills(pills) {
  if (!pills || !pills.length) return '';
  return `<div class="rb-block"><div class="rb-pills">${pills.map(([l, v]) => `<div><div class="rb-pill-lbl">${pkEsc(l)}</div><div class="rb-pill-val">${pkEsc(v)}</div></div>`).join('')}</div></div>`;
}

function pkInspection(rows) {
  return `<div class="rb-block"><div class="rb-title">INSPECTION SIGN-OFF</div><table class="rb-table">
    <thead><tr><th>Variable</th><th>Value</th><th>Initials</th></tr></thead>
    <tbody>${rows.map(r => `<tr><td>${pkEsc(r[0])}</td><td>${pkEsc(r[1])}</td><td><span class="rb-initial-box"></span></td></tr>`).join('')}</tbody></table></div>`;
}

function pkBlock(kind, key, record, blocks, cols) {
  if (key === 'summary') return pkPills(record.pills);
  if (key === 'hwlib_inspection') return pkInspection(record.data[key]);
  const columns = blocks[kind][key][2];
  const head = key === 'hwlib_variables' ? ['Hardware Item / Variable (CNC Code)', 'Value'] : columns;
  return pkTable(PK_TITLES[key], head, record.data[key], undefined, key === 'hwlib_variables' ? undefined : cols);
}

function pkHasData(kind, key, record, blocks) {
  if (!blocks[kind][key]) return false;
  if (blocks[kind][key][1]) return true; // alwaysShow
  if (key === 'summary') return !!(record.pills && record.pills.length);
  return !!(record.data[key] && record.data[key].length);
}

function pkRecordPage(record, layouts, blocks) {
  const layout = layouts[record.kind] || [];
  const cells = layout.filter(it => blocks[record.kind][it.key] && it.enabled !== false && pkHasData(record.kind, it.key, record, blocks))
    .map(it => `<div class="rb-cell" style="grid-column: span ${Math.min(12, Math.max(1, it.span || 12))};">${pkBlock(record.kind, it.key, record, blocks, it.cols)}</div>`).join('');
  return `<div class="xpage"><div class="xpage-hdr"><div class="xpage-title">${pkEsc(record.title)}</div></div><div class="rb-grid">${cells}</div></div>`;
}

function pkSimplePage(title, inner) {
  return `<div class="xpage"><div class="xpage-hdr"><div class="xpage-title">${pkEsc(title)}</div></div>${inner}</div>`;
}

function pkRender(data) {
  const m = data.meta;
  let html = pkSimplePage('Fabrication Package — Export Summary',
    pkPills([['JOB', m.job || '—'], ['WORK ORDER', m.work_order || '—'], ['DATE', m.date], ['OPENINGS', String(m.records)]])
    + (data.cut_list.length ? pkTable('CUT LIST — ALL EXTRUSIONS', ['Part Number', 'Cut Length', 'Qty', 'Source'], data.cut_list) : '')
    + (data.stock.length ? pkTable('STOCK LENGTH REQUIREMENTS', ['Part Number', 'Stock Length', 'Total Lineal', 'Sticks Required'], data.stock) : ''));

  // Doors and frames in opening order; an opening's door sheet prints right above its frame.
  html += data.records.map(r => pkRecordPage(r, data.layouts, data.blocks)).join('');

  if (data.bom.length) html += pkSimplePage('Hardware Bill of Materials — Whole Set', pkTable('BILL OF MATERIALS', ['Type', 'Category', 'Item', 'Ref', 'Model / PN', 'Total Qty'], data.bom));
  if (data.field_install.length) html += pkSimplePage('Field Install Pick List', pkTable('FIELD INSTALL PICK LIST', ['Item', 'Model / PN', 'Qty', 'Door / Opening'], data.field_install));
  document.getElementById('pk-pages').innerHTML = html;
}

async function pkLoadSources() {
  const data = await authenticatedFetch('/config/package/sources');
  pkJobs = data.jobs || [];
  const sel = document.getElementById('pk-job');
  sel.innerHTML = '<option value="">Select a job…</option>' + pkJobs.map((j, i) => `<option value="${i}">${pkEsc(j.name)}</option>`).join('');
  const deep = new URLSearchParams(location.search).get('job');
  if (deep) {
    const idx = pkJobs.findIndex(j => String(j.id) === deep);
    if (idx >= 0) { sel.value = idx; pkJobChanged(); }
  }
}

function pkJobChanged() {
  const job = pkJobs[document.getElementById('pk-job').value];
  document.getElementById('pk-wo').innerHTML = '<option value="">All work orders</option>'
    + (job ? job.work_orders.map((w, i) => `<option value="${i}">${pkEsc(w.name)} (${w.count})</option>`).join('') : '');
  pkLoad();
}

async function pkExportCutCsv() {
  const job = pkJobs[document.getElementById('pk-job').value];
  if (!job) { showNotification('Select a job first.', 'warning'); return; }
  const woIdx = document.getElementById('pk-wo').value;
  const ids = woIdx === '' ? job.work_orders.flatMap(w => w.configuration_ids) : job.work_orders[woIdx].configuration_ids;
  if (!ids.length) { showNotification('No configurations.', 'warning'); return; }
  const qs = new URLSearchParams();
  ids.forEach(i => qs.append('configuration_ids[]', i));
  // Door / frame checkboxes decide which cuts are included; the other sections don't apply to the CSV.
  document.querySelectorAll('.pk-sec').forEach(b => { if (b.value === 'doors' || b.value === 'frames') qs.set(`sections[${b.value}]`, b.checked ? '1' : '0'); });
  try {
    const response = await apiCall(`/config/package/cut-list-csv?${qs.toString()}`);
    if (!response.ok) { const err = await response.json().catch(() => ({})); showNotification(err.message || 'CSV export failed', 'danger'); return; }
    const blob = await response.blob();
    const match = (response.headers.get('Content-Disposition') || '').match(/filename="?([^";]+)"?/);
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = match ? match[1] : 'CutList.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(a.href);
  } catch (err) { showNotification('Failed to export cut list CSV', 'danger'); }
}

async function pkLoad() {
  const job = pkJobs[document.getElementById('pk-job').value];
  const out = document.getElementById('pk-pages');
  if (!job) { out.innerHTML = '<div class="text-muted">Select a job to build the package.</div>'; return; }
  const woIdx = document.getElementById('pk-wo').value;
  const ids = woIdx === '' ? job.work_orders.flatMap(w => w.configuration_ids) : job.work_orders[woIdx].configuration_ids;
  if (!ids.length) { out.innerHTML = '<div class="text-muted">No configurations.</div>'; return; }

  out.innerHTML = '<div class="text-muted">Building package…</div>';
  const qs = new URLSearchParams();
  ids.forEach(i => qs.append('configuration_ids[]', i));
  document.querySelectorAll('.pk-sec').forEach(b => qs.set(`sections[${b.value}]`, b.checked ? '1' : '0'));
  pkRender(await authenticatedFetch(`/config/package?${qs.toString()}`));
}


// ── Layout editor ────────────────────────────────────────────────────────
let pkEd = { type: 'door', layouts: {}, blocks: {} };

async function pkOpenEditor() {
  const data = await authenticatedFetch('/config/pdf-templates');
  pkEd.blocks = data.blocks;
  pkEd.layouts = JSON.parse(JSON.stringify(data.layouts));
  // blocks missing from a saved layout are appended (disabled) so nothing is unreachable
  for (const t of ['door', 'frame']) {
    const have = new Set(pkEd.layouts[t].map(i => i.key));
    Object.keys(pkEd.blocks[t]).forEach(k => { if (!have.has(k)) pkEd.layouts[t].push({ key: k, span: 12, enabled: false }); });
  }
  pkEdSwitch('door');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('pk-editor-modal')).show();
}

function pkEdSwitch(type) {
  pkEd.type = type;
  document.querySelectorAll('#pk-ed-tabs .nav-link').forEach((a, i) => a.classList.toggle('active', (i === 0) === (type === 'door')));
  pkEdRender();
}

function pkEdRender() {
  const layout = pkEd.layouts[pkEd.type];
  document.getElementById('pk-ed-body').innerHTML = layout.map((it, i) => {
    const def = pkEd.blocks[pkEd.type][it.key];
    const cols = def[2];
    return `<div class="border rounded p-2 mb-2">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" ${it.enabled !== false ? 'checked' : ''} onchange="pkEdSet(${i}, 'enabled', this.checked)"><span class="form-check-label fw-bold">${pkEsc(def[0])}</span></label>
        <span class="ms-auto d-flex align-items-center gap-2">
          <span class="text-muted small">Width</span>
          <select class="form-select form-select-sm" style="width:auto" onchange="pkEdSet(${i}, 'span', parseInt(this.value, 10))">
            ${[3,4,6,8,9,12].map(n => `<option value="${n}" ${it.span === n ? 'selected' : ''}>${n}/12${n === 12 ? ' (full)' : n === 6 ? ' (half)' : ''}</option>`).join('')}
            ${[1,2,5,7,10,11].includes(it.span) ? `<option value="${it.span}" selected>${it.span}/12</option>` : ''}
          </select>
          <button class="btn btn-sm btn-icon" ${i === 0 ? 'disabled' : ''} onclick="pkEdMove(${i}, -1)"><i class="ti ti-arrow-up"></i></button>
          <button class="btn btn-sm btn-icon" ${i === layout.length - 1 ? 'disabled' : ''} onclick="pkEdMove(${i}, 1)"><i class="ti ti-arrow-down"></i></button>
        </span>
      </div>
      ${cols ? `<div class="mt-2 d-flex flex-wrap gap-3">${cols.map((c, ci) => `<label class="form-check mb-0"><input class="form-check-input" type="checkbox" ${(it.cols || [])[ci] !== false ? 'checked' : ''} onchange="pkEdCol(${i}, ${ci}, this.checked)"><span class="form-check-label small">${pkEsc(c)}</span></label>`).join('')}</div>` : ''}
    </div>`;
  }).join('');
}

function pkEdSet(i, field, value) { pkEd.layouts[pkEd.type][i][field] = value; }
function pkEdCol(i, ci, on) {
  const it = pkEd.layouts[pkEd.type][i];
  const n = pkEd.blocks[pkEd.type][it.key][2].length;
  it.cols = it.cols || Array(n).fill(true);
  it.cols[ci] = on;
  if (it.cols.every(Boolean)) delete it.cols;
}
function pkEdMove(i, d) {
  const l = pkEd.layouts[pkEd.type];
  [l[i], l[i + d]] = [l[i + d], l[i]];
  pkEdRender();
}
async function pkEdSave() {
  for (const t of ['door', 'frame']) {
    const layout = pkEd.layouts[t].map(it => ({ key: it.key, span: it.span || 12, enabled: it.enabled !== false, ...(it.cols ? { cols: it.cols } : {}) }));
    await authenticatedFetch(`/config/pdf-templates/${t}`, { method: 'PUT', body: JSON.stringify({ layout }) });
  }
  bootstrap.Modal.getInstance(document.getElementById('pk-editor-modal')).hide();
  pkLoad();
}

document.addEventListener('DOMContentLoaded', () => { window.sessionReady.then(pkLoadSources); });
</script>
@endsection
