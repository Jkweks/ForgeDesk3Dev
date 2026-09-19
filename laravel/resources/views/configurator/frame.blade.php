@extends('layouts.app')

@section('title', 'Frame Builder')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Configurator</div>
          <h1 class="page-title">Frame Builder</h1>
          <p class="text-muted">Configure an opening and generate its frame extrusion / hardware BOM from the catalog</p>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <div class="btn-list">
            <a href="/admin#tab-configurator-catalog" class="btn btn-outline-secondary" data-permission="configurator.catalog.manage"><i class="ti ti-settings me-1"></i>Catalog Admin</a>
            <button class="btn btn-primary" onclick="fbOpenNewModal()" data-permission="configurator.create"><i class="ti ti-plus me-1"></i>New Configuration</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <main class="page-body">
    <div class="container-xl">
      <div class="row row-cards">
        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Configurations</h3></div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead><tr><th>Job</th><th>Scope</th><th>Status</th></tr></thead>
                <tbody id="fb-list-tbody"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-8" id="fb-detail-col" style="display:none">
          <div class="card mb-3">
            <div class="card-header">
              <div>
                <h3 class="card-title" id="fb-detail-title">—</h3>
                <div class="text-muted" id="fb-detail-subtitle"></div>
              </div>
              <div class="card-actions">
                <span class="badge" id="fb-status-badge"></span>
                <button class="btn btn-success ms-2" id="fb-release-btn" onclick="fbRelease()" data-permission="configurator.release"><i class="ti ti-lock me-1"></i>Release</button>
              </div>
            </div>
            <div class="card-body" id="fb-validation-errors"></div>
          </div>

          <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">1. Opening Specifications</h3></div>
            <div class="card-body">
              <form id="fb-opening-form" class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Opening Type</label>
                  <select class="form-select" id="fb-op-type" onchange="fbToggleHand()">
                    <option value="single">Single</option>
                    <option value="pair">Pair</option>
                  </select>
                </div>
                <div class="col-md-4" id="fb-op-hand-single-wrap">
                  <label class="form-label">Hand</label>
                  <select class="form-select" id="fb-op-hand-single">
                    <option value="lh_inswing">LH Inswing</option>
                    <option value="rh_inswing">RH Inswing</option>
                    <option value="lhr">LHR</option>
                    <option value="rhr">RHR</option>
                  </select>
                </div>
                <div class="col-md-4" id="fb-op-hand-pair-wrap" style="display:none">
                  <label class="form-label">Hand (Pair)</label>
                  <select class="form-select" id="fb-op-hand-pair">
                    <option value="rhr_active">RHR Active</option>
                    <option value="lhra_active">LHRA Active</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Hinging</label>
                  <select class="form-select" id="fb-op-hinging">
                    <option value="continuous">Continuous</option>
                    <option value="butt">Butt</option>
                    <option value="pivot_offset">Pivot Offset</option>
                    <option value="pivot_center">Pivot Center</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Opening Width (in)</label>
                  <input type="number" step="0.01" class="form-control" id="fb-op-width" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Opening Height (in)</label>
                  <input type="number" step="0.01" class="form-control" id="fb-op-height" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Finish</label>
                  <select class="form-select" id="fb-op-finish">
                    <option value="c2">C2 - Clear Anodized</option>
                    <option value="db">DB - Dark Bronze</option>
                    <option value="bl">BL - Black</option>
                  </select>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary" data-permission="configurator.edit">Save Opening Specs</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card mb-3" id="fb-frame-card" style="display:none">
            <div class="card-header"><h3 class="card-title">2. Frame Configuration</h3></div>
            <div class="card-body">
              <form id="fb-frame-form" class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Frame System</label>
                  <select class="form-select" id="fb-frame-system" onchange="fbFilterSeries()"></select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Frame Series</label>
                  <select class="form-select" id="fb-frame-series" required></select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Glazing</label>
                  <select class="form-select" id="fb-frame-glazing">
                    <option value="0.25">1/4"</option>
                    <option value="0.5">1/2"</option>
                    <option value="1.0">1"</option>
                  </select>
                </div>
                <div class="col-md-3 form-check form-switch pt-4">
                  <input class="form-check-input" type="checkbox" id="fb-frame-transom" onchange="fbToggleTransom()">
                  <label class="form-check-label">Has Transom</label>
                </div>
                <div class="col-md-3 form-check form-switch pt-4">
                  <input class="form-check-input" type="checkbox" id="fb-frame-threshold">
                  <label class="form-check-label">Has Threshold</label>
                </div>
                <div class="col-md-3" id="fb-frame-transom-glazing-wrap" style="display:none">
                  <label class="form-label">Transom Glazing</label>
                  <select class="form-select" id="fb-frame-transom-glazing">
                    <option value="0.25">1/4"</option>
                    <option value="0.5">1/2"</option>
                    <option value="1.0">1"</option>
                  </select>
                </div>
                <div class="col-md-3" id="fb-frame-height-wrap" style="display:none">
                  <label class="form-label">Total Frame Height (in)</label>
                  <input type="number" step="0.01" class="form-control" id="fb-frame-height">
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary" data-permission="configurator.edit">Save Frame Configuration</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card mb-3" id="fb-bom-card" style="display:none">
            <div class="card-header">
              <h3 class="card-title">Frame Parts BOM</h3>
              <div class="card-actions">
                <button class="btn btn-outline-primary btn-sm" onclick="fbAddManualPart('frame')" data-permission="configurator.edit"><i class="ti ti-plus me-1"></i>Add Manual Part</button>
                <button class="btn btn-primary btn-sm" onclick="fbGenerateParts()" data-permission="configurator.edit"><i class="ti ti-refresh me-1"></i>Generate / Recalculate</button>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead><tr><th>Part</th><th>Product</th><th>Length</th><th>Qty</th><th>Source</th><th class="w-1"></th></tr></thead>
                <tbody id="fb-parts-tbody"></tbody>
              </table>
              <div class="text-muted p-3" id="fb-parts-empty">No parts yet — save a frame series above, then click Generate.</div>
            </div>
          </div>

          <div class="card mb-3" id="fb-door-card" style="display:none">
            <div class="card-header"><h3 class="card-title">Door Configuration</h3></div>
            <div class="card-body">
              <form id="fb-door-form" class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Door Series</label>
                  <select class="form-select" id="fb-door-series" onchange="fbFilterDoorStiles()" required>
                    <option value="STANDARD">Standard</option>
                    <option value="THERMAL">Thermal</option>
                    <option value="MONUMENTAL">Monumental</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Stile Width</label>
                  <select class="form-select" id="fb-door-stile" required></select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Glazing</label>
                  <select class="form-select" id="fb-door-glazing"></select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Handing</label>
                  <select class="form-select" id="fb-door-handing" required>
                    <option value="LH (INSWING)">LH Inswing</option>
                    <option value="RH (INSWING)">RH Inswing</option>
                    <option value="LHR">LHR</option>
                    <option value="RHR">RHR</option>
                    <option value="CP SINGLE">Center Pivot Single</option>
                    <option value="PAIR-RHRA">Pair - RHRA Active</option>
                    <option value="PAIR-LHRA">Pair - LHRA Active</option>
                    <option value="CP PAIR">Center Pivot Pair</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Hinge Type</label>
                  <select class="form-select" id="fb-door-hinge" required>
                    <option value="BUTT HINGES">Butt Hinges</option>
                    <option value="OFFSET PIVOTS">Offset Pivots</option>
                    <option value="CONTINUOUS HINGE">Continuous Hinge</option>
                    <option value="CENTER PIVOTS">Center Pivots</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Bottom Gap (in)</label>
                  <input type="number" step="0.0001" class="form-control" id="fb-door-bottomgap" value="0.6875">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Top Rail</label>
                  <select class="form-select" id="fb-door-toprail" required></select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Bottom Rail</label>
                  <select class="form-select" id="fb-door-botrail" required></select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Mid Rail Qty</label>
                  <select class="form-select" id="fb-door-midqty" onchange="fbToggleMidRail()">
                    <option value="0">None</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                  </select>
                </div>
                <div class="col-md-4" id="fb-door-midrail-wrap" style="display:none">
                  <label class="form-label">Mid Rail</label>
                  <select class="form-select" id="fb-door-midrail"></select>
                </div>
                <div class="col-md-4" id="fb-door-midloc1-wrap" style="display:none">
                  <label class="form-label">Mid Rail Location #1 (in from bottom)</label>
                  <input type="number" step="0.0001" class="form-control" id="fb-door-midloc1">
                </div>
                <div class="col-md-4" id="fb-door-midloc2-wrap" style="display:none">
                  <label class="form-label">Mid Rail Location #2 (in from bottom)</label>
                  <input type="number" step="0.0001" class="form-control" id="fb-door-midloc2">
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary" data-permission="configurator.edit">Save Door Configuration</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card mb-3" id="fb-door-bom-card" style="display:none">
            <div class="card-header">
              <h3 class="card-title">Door Parts BOM</h3>
              <div class="card-actions">
                <button class="btn btn-outline-primary btn-sm" onclick="fbAddManualPart('door')" data-permission="configurator.edit"><i class="ti ti-plus me-1"></i>Add Manual Part</button>
                <button class="btn btn-primary btn-sm" onclick="fbGenerateDoorParts()" data-permission="configurator.edit"><i class="ti ti-refresh me-1"></i>Generate / Recalculate</button>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead><tr><th>Part</th><th>Product</th><th>Length</th><th>Qty</th><th>Source</th><th class="w-1"></th></tr></thead>
                <tbody id="fb-door-parts-tbody"></tbody>
              </table>
              <div class="text-muted p-3" id="fb-door-parts-empty">No parts yet — save a door configuration above, then click Generate.</div>
            </div>
          </div>

          <div class="card mb-3" id="fb-hardware-card" style="display:none">
            <div class="card-header"><h3 class="card-title">Hardware</h3></div>
            <div class="card-body">
              <form id="fb-hardware-add-form" class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                  <label class="form-label">Category</label>
                  <select class="form-select" id="fb-hw-category" onchange="fbFilterHwItems()" required></select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Item</label>
                  <select class="form-select" id="fb-hw-item" required></select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Series</label>
                  <select class="form-select" id="fb-hw-series">
                    <option value="Standard">Standard</option>
                    <option value="Thermal">Thermal</option>
                    <option value="Monumental">Monumental</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Leaf</label>
                  <select class="form-select" id="fb-hw-leaf">
                    <option value="both">Both</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
                <div class="col-md-1">
                  <label class="form-label">Qty</label>
                  <input type="number" class="form-control" id="fb-hw-qty" value="1" min="1">
                </div>
                <div class="col-md-1">
                  <button type="submit" class="btn btn-primary w-100" data-permission="configurator.edit"><i class="ti ti-plus"></i></button>
                </div>
              </form>

              <table class="table table-vcenter card-table">
                <thead><tr><th>Item</th><th>Category</th><th>Series</th><th>Leaf</th><th>Qty</th><th class="w-1"></th></tr></thead>
                <tbody id="fb-hw-links-tbody"></tbody>
              </table>
              <div class="text-muted p-3" id="fb-hw-links-empty">No hardware linked yet.</div>

              <div id="fb-hw-resolved-wrap" style="display:none">
                <hr>
                <h4>Resolved Prep Values</h4>
                <div id="fb-hw-resolved"></div>
              </div>
            </div>
          </div>

          <div class="card mb-3" id="fb-hardware-bom-card" style="display:none">
            <div class="card-header">
              <h3 class="card-title">Hardware BOM</h3>
              <div class="card-actions">
                <button class="btn btn-primary btn-sm" onclick="fbGenerateHardwareParts()" data-permission="configurator.edit"><i class="ti ti-refresh me-1"></i>Generate / Recalculate</button>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead><tr><th>Part</th><th>Product</th><th>Qty</th><th>Source</th><th class="w-1"></th></tr></thead>
                <tbody id="fb-hw-parts-tbody"></tbody>
              </table>
              <div class="text-muted p-3" id="fb-hw-parts-empty">No parts yet — link hardware above, then click Generate.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- New Configuration Modal -->
<div class="modal modal-blur fade" id="fb-new-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="fb-new-form">
        <div class="modal-header"><h5 class="modal-title">New Door/Frame Configuration</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Job</label>
            <select class="form-select" id="fb-new-job" required></select>
          </div>
          <div class="mb-3"><label class="form-label">Configuration Name</label><input type="text" class="form-control" id="fb-new-name"></div>
          <div class="mb-3">
            <label class="form-label">Scope</label>
            <select class="form-select" id="fb-new-scope">
              <option value="door_and_frame">Door and Frame</option>
              <option value="frame_only">Frame Only</option>
              <option value="door_only">Door Only</option>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Quantity</label><input type="number" class="form-control" id="fb-new-qty" value="1" min="1" required></div>
          <div class="mb-3">
            <label class="form-label">Door Tags (comma separated)</label>
            <input type="text" class="form-control" id="fb-new-tags" placeholder="D1, D2" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Part Edit Modal -->
<div class="modal modal-blur fade" id="fb-part-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="fb-part-form">
        <div class="modal-header"><h5 class="modal-title">Edit Part</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="fb-part-id">
          <input type="hidden" id="fb-part-kind" value="frame">
          <div class="mb-3"><label class="form-label">Label</label><input type="text" class="form-control" id="fb-part-label" required></div>
          <div class="mb-3"><label class="form-label">Product</label><select class="form-select" id="fb-part-product" required></select></div>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Unit Type</label>
              <select class="form-select" id="fb-part-unittype">
                <option value="length">Length</option>
                <option value="qty">Quantity</option>
              </select>
            </div>
            <div class="col-md-6 mb-3"><label class="form-label">Length / Qty</label><input type="number" step="0.001" class="form-control" id="fb-part-amount" required></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let fbConfigs = [];
let fbSelectedId = null;
let fbSelectedDetail = null;
let fbCatalogTree = [];
let fbProducts = [];
let fbDoorCatalog = null;

function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

async function fbLoadProducts() {
  if (fbProducts.length) return fbProducts;
  try {
    const data = await authenticatedFetch('/products?per_page=500');
    fbProducts = data.data || [];
  } catch (e) { fbProducts = []; }
  return fbProducts;
}
function fbProductOptions(selectedId) {
  return '<option value="">— select —</option>' + fbProducts.map(p =>
    `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${esc(p.part_number || p.sku)} — ${esc(p.description || '')}</option>`
  ).join('');
}

async function fbLoadJobsInto(select) {
  const data = await authenticatedFetch('/business-jobs');
  select.innerHTML = (data.jobs || []).map(j => `<option value="${j.id}">${esc(j.job_number)} — ${esc(j.job_name)}</option>`).join('');
}

async function fbLoadCatalogTree() {
  const data = await authenticatedFetch('/configurator/catalog/tree');
  fbCatalogTree = data.frame_systems || [];
  const systemSelect = document.getElementById('fb-frame-system');
  systemSelect.innerHTML = '<option value="">All Systems</option>' + fbCatalogTree.map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
}

async function fbLoadDoorCatalog() {
  if (fbDoorCatalog) return fbDoorCatalog;
  fbDoorCatalog = await authenticatedFetch('/configurator/door-catalog');
  return fbDoorCatalog;
}

function fbFilterDoorStiles() {
  const series = document.getElementById('fb-door-series').value;
  const stileSelect = document.getElementById('fb-door-stile');
  const stiles = (fbDoorCatalog?.door_types || []).filter(t => t.series === series);
  const current = fbSelectedDetail?.door_config?.stile_width;
  stileSelect.innerHTML = stiles.map(t => `<option value="${esc(t.stile_name)}" ${t.stile_name === current ? 'selected' : ''}>${esc(t.stile_name)}</option>`).join('');
}

function fbPopulateRailSelects() {
  const rails = fbDoorCatalog?.rails || [];
  const dc = fbSelectedDetail?.door_config;
  const byType = (type) => rails.filter(r => r.rail_type === type);
  const opts = (list, current) => list.map(r => `<option value="${esc(r.label)}" ${r.label === current ? 'selected' : ''}>${esc(r.label)}</option>`).join('');
  document.getElementById('fb-door-toprail').innerHTML = opts(byType('top'), dc?.top_rail_label);
  document.getElementById('fb-door-botrail').innerHTML = opts(byType('bot'), dc?.bot_rail_label);
  document.getElementById('fb-door-midrail').innerHTML = '<option value="">— none —</option>' + opts(byType('mid'), dc?.mid_rail_label);
}

function fbPopulateGlazingSelect() {
  const glassSpecs = fbDoorCatalog?.glass_specs || [];
  const current = fbSelectedDetail?.door_config?.glazing;
  document.getElementById('fb-door-glazing').innerHTML =
    '<option value="">— select —</option>' + glassSpecs.map(g => `<option value="${esc(g.thickness)}" ${g.thickness === current ? 'selected' : ''}>${esc(g.thickness)}</option>`).join('');
}

function fbToggleMidRail() {
  const midQty = parseInt(document.getElementById('fb-door-midqty').value || 0, 10);
  document.getElementById('fb-door-midrail-wrap').style.display = midQty > 0 ? '' : 'none';
  document.getElementById('fb-door-midloc1-wrap').style.display = midQty >= 1 ? '' : 'none';
  document.getElementById('fb-door-midloc2-wrap').style.display = midQty >= 2 ? '' : 'none';
}

// ---- Hardware ----
let fbHwCategories = [];

async function fbLoadHwCatalog() {
  const data = await authenticatedFetch('/configurator/hwlib-catalog');
  fbHwCategories = data.categories || [];
  const catSelect = document.getElementById('fb-hw-category');
  catSelect.innerHTML = fbHwCategories.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
  fbFilterHwItems();
}

function fbFilterHwItems() {
  const catId = document.getElementById('fb-hw-category').value;
  const cat = fbHwCategories.find(c => c.id == catId);
  const itemSelect = document.getElementById('fb-hw-item');
  itemSelect.innerHTML = (cat?.items || []).map(i =>
    `<option value="${i.id}">${esc(i.name)}${i.pn ? ' — ' + esc(i.pn) : ''}</option>`
  ).join('');
}

function fbRenderHwLinks(links) {
  const tbody = document.getElementById('fb-hw-links-tbody');
  document.getElementById('fb-hw-links-empty').style.display = links.length ? 'none' : 'block';
  tbody.innerHTML = links.map(l => `
    <tr>
      <td>${esc(l.item.name)}${l.item.pn ? '<div class="text-muted small">' + esc(l.item.pn) + '</div>' : ''}</td>
      <td>${esc(l.item.category.name)}</td>
      <td>${esc(l.series)}</td>
      <td>${esc(l.leaf)}</td>
      <td>${l.quantity}</td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-icon text-danger" onclick="fbDeleteHwLink(${l.id})" data-permission="configurator.edit"><i class="ti ti-trash"></i></button>
      </td>
    </tr>`).join('');
  applyActionPermissions();
}

document.getElementById('fb-hardware-add-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = {
    item_id: document.getElementById('fb-hw-item').value,
    series: document.getElementById('fb-hw-series').value,
    leaf: document.getElementById('fb-hw-leaf').value,
    quantity: parseInt(document.getElementById('fb-hw-qty').value || 1, 10),
  };
  if (!payload.item_id) { showNotification('Select an item first', 'warning'); return; }
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/hardware-links`, { method: 'POST', body: JSON.stringify(payload) });
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
});

async function fbDeleteHwLink(linkId) {
  if (!confirm('Remove this hardware item?')) return;
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/hardware-links/${linkId}`, { method: 'DELETE' });
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

async function fbLoadHwResolvedValues() {
  try {
    const data = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/hardware-values`);
    const wrap = document.getElementById('fb-hw-resolved-wrap');
    const links = (data.links || []).filter(l => l.values.length);
    wrap.style.display = links.length ? '' : 'none';
    document.getElementById('fb-hw-resolved').innerHTML = links.map(l => `
      <div class="mb-2">
        <div class="fw-bold">${esc(l.item_name)}</div>
        <div class="d-flex flex-wrap gap-3 small text-muted">
          ${l.values.map(v => `<span>${esc(v.label)}: <strong class="text-body">${v.value ?? '—'}${v.unit ? esc(v.unit) : ''}</strong>${v.overridden ? ' <span class="badge bg-yellow-lt">override</span>' : ''}</span>`).join('')}
        </div>
      </div>`).join('');
  } catch (err) { /* non-fatal — resolved values are a convenience display */ }
}

function fbRenderHwParts(parts) {
  const tbody = document.getElementById('fb-hw-parts-tbody');
  document.getElementById('fb-hw-parts-empty').style.display = parts.length ? 'none' : 'block';
  const sourceBadge = { item: 'bg-blue-lt', backer: 'bg-azure-lt', fastener: 'bg-purple-lt', manual: 'bg-secondary-lt' };
  tbody.innerHTML = parts.map(p => `
    <tr>
      <td>${esc(p.formatted_label)}</td>
      <td>${esc(p.product.part_number)}<div class="text-muted small">${esc(p.product.description || '')}</div></td>
      <td>${p.quantity}</td>
      <td><span class="badge ${sourceBadge[p.source_type] || 'bg-secondary-lt'}">${esc(p.source_type)}</span></td>
      <td class="text-end">
        ${!p.is_auto_generated ? `<button type="button" class="btn btn-sm btn-icon text-danger" onclick="fbDeleteHwPart(${p.id})" data-permission="configurator.edit"><i class="ti ti-trash"></i></button>` : ''}
      </td>
    </tr>`).join('');
  applyActionPermissions();
}

async function fbGenerateHardwareParts() {
  try {
    const res = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/hardware-parts/generate`, { method: 'POST' });
    showNotification('Hardware parts generated', 'success');
    if (res.warnings && res.warnings.length) {
      showNotification(`${res.warnings.length} PN(s) could not be matched to a product — see console.`, 'warning');
      console.warn('Hardware BOM warnings:', res.warnings);
    }
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

async function fbDeleteHwPart(partId) {
  if (!confirm('Remove this part?')) return;
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/hardware-parts/${partId}`, { method: 'DELETE' });
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

function fbFilterSeries() {
  const systemId = document.getElementById('fb-frame-system').value;
  const seriesSelect = document.getElementById('fb-frame-series');
  const all = [];
  fbCatalogTree.forEach(sys => {
    if (systemId && sys.id != systemId) return;
    (sys.series || []).forEach(ser => all.push({ id: ser.id, label: `${sys.name} — ${ser.name}` }));
  });
  const current = fbSelectedDetail?.frame_config?.frame_series?.id;
  seriesSelect.innerHTML = all.map(s => `<option value="${s.id}" ${s.id == current ? 'selected' : ''}>${esc(s.label)}</option>`).join('');
}

async function fbLoadList() {
  const data = await authenticatedFetch('/door-frame-configurations');
  fbConfigs = data.configurations || [];
  fbRenderList();
}

function fbRenderList() {
  const tbody = document.getElementById('fb-list-tbody');
  tbody.innerHTML = fbConfigs.map(c => `
    <tr class="${c.id == fbSelectedId ? 'table-active' : ''}" style="cursor:pointer" onclick="fbSelect(${c.id})">
      <td>${esc(c.job_number)}<div class="text-muted small">${esc(c.door_tags)}</div></td>
      <td>${esc(c.scope_label)}</td>
      <td><span class="badge ${c.status === 'released' ? 'bg-green-lt' : 'bg-yellow-lt'}">${esc(c.status_label)}</span></td>
    </tr>`).join('') || '<tr><td colspan="3" class="text-muted">No configurations yet.</td></tr>';
}

async function fbSelect(id) {
  fbSelectedId = id;
  fbRenderList();
  document.getElementById('fb-detail-col').style.display = '';
  await fbLoadCatalogTree();
  await fbLoadDoorCatalog();
  await fbLoadHwCatalog();
  await fbLoadDetail();
}

async function fbLoadDetail() {
  const data = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}`);
  fbSelectedDetail = data.configuration;
  fbRenderDetail();
}

function fbRenderDetail() {
  const c = fbSelectedDetail;
  document.getElementById('fb-detail-title').textContent = `${c.business_job.job_number} — ${c.configuration_name || c.scope_label}`;
  document.getElementById('fb-detail-subtitle').textContent = `${c.business_job.job_name} · Qty ${c.quantity} · ${c.door_tags.join(', ')}`;
  const badge = document.getElementById('fb-status-badge');
  badge.textContent = c.status_label;
  badge.className = 'badge ' + (c.status === 'released' ? 'bg-green-lt' : 'bg-yellow-lt');
  document.getElementById('fb-release-btn').style.display = (c.status === 'draft' && c.is_complete) ? '' : 'none';

  const errBox = document.getElementById('fb-validation-errors');
  errBox.innerHTML = (c.validation_errors && c.validation_errors.length)
    ? `<div class="alert alert-warning mb-0"><strong>Incomplete:</strong> ${c.validation_errors.map(esc).join(', ')}</div>` : '';

  const includesFrame = ['door_and_frame', 'frame_only'].includes(c.job_scope);
  const includesDoor = ['door_and_frame', 'door_only'].includes(c.job_scope);
  document.getElementById('fb-frame-card').style.display = includesFrame ? '' : 'none';
  document.getElementById('fb-bom-card').style.display = includesFrame ? '' : 'none';
  document.getElementById('fb-door-card').style.display = includesDoor ? '' : 'none';
  document.getElementById('fb-door-bom-card').style.display = includesDoor ? '' : 'none';
  document.getElementById('fb-hardware-card').style.display = '';
  document.getElementById('fb-hardware-bom-card').style.display = '';

  const editable = c.can_edit;
  document.querySelectorAll('#fb-opening-form input, #fb-opening-form select, #fb-frame-form input, #fb-frame-form select, #fb-door-form input, #fb-door-form select').forEach(el => el.disabled = !editable);

  // Opening specs
  const os = c.opening_specs;
  document.getElementById('fb-op-type').value = os?.opening_type || 'single';
  fbToggleHand();
  document.getElementById('fb-op-hand-single').value = os?.hand_single || 'lh_inswing';
  document.getElementById('fb-op-hand-pair').value = os?.hand_pair || 'rhr_active';
  document.getElementById('fb-op-hinging').value = os?.hinging || 'continuous';
  document.getElementById('fb-op-width').value = os?.door_opening_width ?? '';
  document.getElementById('fb-op-height').value = os?.door_opening_height ?? '';
  document.getElementById('fb-op-finish').value = os?.finish || 'c2';

  // Frame config
  fbFilterSeries();
  const fc = c.frame_config;
  document.getElementById('fb-frame-system').value = fc?.frame_series?.frame_system?.id || '';
  fbFilterSeries();
  document.getElementById('fb-frame-glazing').value = fc?.glazing || '0.25';
  document.getElementById('fb-frame-transom').checked = !!fc?.has_transom;
  document.getElementById('fb-frame-threshold').checked = !!fc?.has_threshold;
  document.getElementById('fb-frame-transom-glazing').value = fc?.transom_glazing || '0.25';
  document.getElementById('fb-frame-height').value = fc?.total_frame_height ?? '';
  fbToggleTransom();

  fbRenderParts('fb-parts-tbody', 'fb-parts-empty', fc?.parts || [], 'frame');

  // Door config
  if (includesDoor) {
    const dc = c.door_config;
    document.getElementById('fb-door-series').value = dc?.door_series || 'STANDARD';
    fbFilterDoorStiles();
    document.getElementById('fb-door-stile').value = dc?.stile_width || '';
    fbPopulateGlazingSelect();
    document.getElementById('fb-door-glazing').value = dc?.glazing || '';
    document.getElementById('fb-door-handing').value = dc?.handing || 'LH (INSWING)';
    document.getElementById('fb-door-hinge').value = dc?.hinge_type || 'BUTT HINGES';
    document.getElementById('fb-door-bottomgap').value = dc?.bottom_gap ?? 0.6875;
    fbPopulateRailSelects();
    document.getElementById('fb-door-midqty').value = dc?.mid_qty ?? 0;
    document.getElementById('fb-door-midloc1').value = dc?.mid_loc1 ?? '';
    document.getElementById('fb-door-midloc2').value = dc?.mid_loc2 ?? '';
    fbToggleMidRail();

    fbRenderParts('fb-door-parts-tbody', 'fb-door-parts-empty', dc?.parts || [], 'door');
  }

  // Hardware
  fbRenderHwLinks(c.hardware_links || []);
  fbRenderHwParts(c.hardware_parts || []);
  if ((c.hardware_links || []).length) {
    fbLoadHwResolvedValues();
  } else {
    document.getElementById('fb-hw-resolved-wrap').style.display = 'none';
  }
}

function fbToggleHand() {
  const isPair = document.getElementById('fb-op-type').value === 'pair';
  document.getElementById('fb-op-hand-single-wrap').style.display = isPair ? 'none' : '';
  document.getElementById('fb-op-hand-pair-wrap').style.display = isPair ? '' : 'none';
}
function fbToggleTransom() {
  const on = document.getElementById('fb-frame-transom').checked;
  document.getElementById('fb-frame-transom-glazing-wrap').style.display = on ? '' : 'none';
  document.getElementById('fb-frame-height-wrap').style.display = on ? '' : 'none';
}

function fbRenderParts(tbodyId, emptyId, parts, kind) {
  const tbody = document.getElementById(tbodyId);
  document.getElementById(emptyId).style.display = parts.length ? 'none' : 'block';
  const sourceBadge = { profile: 'bg-blue-lt', component: 'bg-azure-lt', fastener: 'bg-purple-lt', extrusion: 'bg-blue-lt', manual: 'bg-secondary-lt' };
  tbody.innerHTML = parts.map(p => `
    <tr>
      <td>${esc(p.formatted_label)}</td>
      <td>${esc(p.product.part_number)}<div class="text-muted small">${esc(p.product.description || '')}</div></td>
      <td>${p.unit_type === 'length' ? (p.calculated_length ?? '—') + '"' : '—'}</td>
      <td>${p.unit_type === 'qty' ? p.quantity : '—'}</td>
      <td><span class="badge ${sourceBadge[p.source_type] || 'bg-secondary-lt'}">${esc(p.source_type)}</span></td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-icon" onclick="fbOpenPartModal('${kind}', ${p.id})" data-permission="configurator.edit"><i class="ti ti-pencil"></i></button>
        ${!p.is_auto_generated ? `<button type="button" class="btn btn-sm btn-icon text-danger" onclick="fbDeletePart('${kind}', ${p.id})" data-permission="configurator.edit"><i class="ti ti-trash"></i></button>` : ''}
      </td>
    </tr>`).join('');
  applyActionPermissions();
}

// ---- New configuration ----
async function fbOpenNewModal() {
  await fbLoadJobsInto(document.getElementById('fb-new-job'));
  document.getElementById('fb-new-form').reset();
  showModal(document.getElementById('fb-new-modal'));
}
document.getElementById('fb-new-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = {
    business_job_id: document.getElementById('fb-new-job').value,
    configuration_name: document.getElementById('fb-new-name').value || null,
    job_scope: document.getElementById('fb-new-scope').value,
    quantity: parseInt(document.getElementById('fb-new-qty').value || 1, 10),
    door_tags: document.getElementById('fb-new-tags').value.split(',').map(s => s.trim()).filter(Boolean),
  };
  try {
    const res = await authenticatedFetch('/door-frame-configurations', { method: 'POST', body: JSON.stringify(payload) });
    hideModal(document.getElementById('fb-new-modal'));
    await fbLoadList();
    await fbSelect(res.configuration.id);
  } catch (err) { showNotification(err.message, 'danger'); }
});

// ---- Opening specs ----
document.getElementById('fb-opening-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const isPair = document.getElementById('fb-op-type').value === 'pair';
  const payload = {
    opening_type: document.getElementById('fb-op-type').value,
    hand_single: isPair ? null : document.getElementById('fb-op-hand-single').value,
    hand_pair: isPair ? document.getElementById('fb-op-hand-pair').value : null,
    door_opening_width: parseFloat(document.getElementById('fb-op-width').value),
    door_opening_height: parseFloat(document.getElementById('fb-op-height').value),
    hinging: document.getElementById('fb-op-hinging').value,
    finish: document.getElementById('fb-op-finish').value,
  };
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/opening-specs`, { method: 'PUT', body: JSON.stringify(payload) });
    showNotification('Opening specs saved', 'success');
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
});

// ---- Frame config ----
document.getElementById('fb-frame-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const hasTransom = document.getElementById('fb-frame-transom').checked;
  const payload = {
    frame_series_id: document.getElementById('fb-frame-series').value,
    glazing: document.getElementById('fb-frame-glazing').value,
    has_transom: hasTransom,
    has_threshold: document.getElementById('fb-frame-threshold').checked,
    transom_glazing: hasTransom ? document.getElementById('fb-frame-transom-glazing').value : null,
    total_frame_height: hasTransom ? parseFloat(document.getElementById('fb-frame-height').value || 0) : null,
  };
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/frame-config`, { method: 'PUT', body: JSON.stringify(payload) });
    showNotification('Frame configuration saved', 'success');
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
});

// ---- Door config ----
document.getElementById('fb-door-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const midQty = parseInt(document.getElementById('fb-door-midqty').value || 0, 10);
  const payload = {
    door_series: document.getElementById('fb-door-series').value,
    stile_width: document.getElementById('fb-door-stile').value,
    handing: document.getElementById('fb-door-handing').value,
    hinge_type: document.getElementById('fb-door-hinge').value,
    bottom_gap: parseFloat(document.getElementById('fb-door-bottomgap').value || 0.6875),
    top_rail_label: document.getElementById('fb-door-toprail').value,
    bot_rail_label: document.getElementById('fb-door-botrail').value,
    mid_rail_label: midQty > 0 ? (document.getElementById('fb-door-midrail').value || null) : null,
    mid_qty: midQty,
    mid_loc1: midQty >= 1 ? parseFloat(document.getElementById('fb-door-midloc1').value || 0) : null,
    mid_loc2: midQty >= 2 ? parseFloat(document.getElementById('fb-door-midloc2').value || 0) : null,
    glazing: document.getElementById('fb-door-glazing').value || null,
  };
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/door-config`, { method: 'PUT', body: JSON.stringify(payload) });
    showNotification('Door configuration saved', 'success');
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
});

// ---- BOM ----
async function fbGenerateParts() {
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/frame-parts/generate`, { method: 'POST' });
    showNotification('Parts generated', 'success');
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

async function fbGenerateDoorParts() {
  try {
    const res = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/door-parts/generate`, { method: 'POST' });
    showNotification('Door parts generated', 'success');
    if (res.warnings && res.warnings.length) {
      showNotification(`${res.warnings.length} PN(s) could not be matched to a product — see console.`, 'warning');
      console.warn('Door BOM warnings:', res.warnings);
    }
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

function fbPartsSource(kind) {
  return kind === 'door' ? (fbSelectedDetail.door_config?.parts || []) : (fbSelectedDetail.frame_config?.parts || []);
}
function fbPartsEndpoint(kind) {
  return kind === 'door' ? 'door-parts' : 'frame-parts';
}

async function fbOpenPartModal(kind, id) {
  await fbLoadProducts();
  const part = fbPartsSource(kind).find(p => p.id == id);
  document.getElementById('fb-part-id').value = id;
  document.getElementById('fb-part-kind').value = kind;
  document.getElementById('fb-part-label').value = part.part_label;
  document.getElementById('fb-part-label').disabled = part.is_auto_generated;
  document.getElementById('fb-part-product').innerHTML = fbProductOptions(part.product.id);
  document.getElementById('fb-part-unittype').value = part.unit_type;
  document.getElementById('fb-part-unittype').disabled = part.is_auto_generated;
  document.getElementById('fb-part-amount').value = part.unit_type === 'length' ? part.calculated_length : part.quantity;
  showModal(document.getElementById('fb-part-modal'));
}
function fbAddManualPart(kind) {
  fbLoadProducts().then(() => {
    document.getElementById('fb-part-id').value = '';
    document.getElementById('fb-part-kind').value = kind;
    document.getElementById('fb-part-label').value = '';
    document.getElementById('fb-part-label').disabled = false;
    document.getElementById('fb-part-product').innerHTML = fbProductOptions(null);
    document.getElementById('fb-part-unittype').value = 'length';
    document.getElementById('fb-part-unittype').disabled = false;
    document.getElementById('fb-part-amount').value = '';
    showModal(document.getElementById('fb-part-modal'));
  });
}
document.getElementById('fb-part-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('fb-part-id').value;
  const kind = document.getElementById('fb-part-kind').value;
  const endpoint = fbPartsEndpoint(kind);
  const unitType = document.getElementById('fb-part-unittype').value;
  const amount = parseFloat(document.getElementById('fb-part-amount').value || 0);
  try {
    if (id) {
      await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/${endpoint}/${id}`, {
        method: 'PUT',
        body: JSON.stringify({
          product_id: document.getElementById('fb-part-product').value,
          calculated_length: unitType === 'length' ? amount : null,
          quantity: unitType === 'qty' ? amount : 1,
        }),
      });
    } else if (kind === 'door') {
      // Door parts have no bulk-manual endpoint — generate first, then add via the single-part PUT isn't
      // available pre-creation, so manual door parts are created through frame-parts-style bulk isn't offered;
      // fall back to requiring at least one generate pass first.
      showNotification('Generate door parts at least once before adding a manual part.', 'warning');
      return;
    } else {
      // Frame manual add: merge with existing manual parts and bulk-save.
      const existingManual = fbPartsSource('frame').filter(p => !p.is_auto_generated).map(p => ({
        part_label: p.part_label, product_id: p.product.id, calculated_length: p.calculated_length,
        quantity: p.quantity, unit_type: p.unit_type,
      }));
      existingManual.push({
        part_label: document.getElementById('fb-part-label').value,
        product_id: document.getElementById('fb-part-product').value,
        calculated_length: unitType === 'length' ? amount : null,
        quantity: unitType === 'qty' ? amount : 1,
        unit_type: unitType,
      });
      await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/frame-parts`, {
        method: 'PUT', body: JSON.stringify({ parts: existingManual }),
      });
    }
    hideModal(document.getElementById('fb-part-modal'));
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
});
async function fbDeletePart(kind, id) {
  if (!confirm('Remove this part?')) return;
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/${fbPartsEndpoint(kind)}/${id}`, { method: 'DELETE' });
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

// ---- Release ----
async function fbRelease() {
  if (!confirm('Release this configuration to production? Catalog-driven edits will be locked.')) return;
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/release`, { method: 'POST' });
    showNotification('Configuration released', 'success');
    await fbLoadList();
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

window.sessionReady?.then(() => fbLoadList());
</script>
@endsection
