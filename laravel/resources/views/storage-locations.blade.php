@extends('layouts.app')

@section('title', 'Storage Locations - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Operations Management</div>
              <h1 class="page-title">Storage Locations</h1>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn" onclick="exportLocations()"><i class="ti ti-download me-1"></i>Export CSV</button>
                <button class="btn" id="bulkShelfPrintBtn" onclick="printAllShelfLabels()"><i class="ti ti-printer me-1"></i>Print All Shelf Labels</button>
                <button class="btn btn-primary" onclick="showAddLocationModal()"><i class="ti ti-plus me-1"></i>Add Location</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <div class="row row-cards mb-3">
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body">
              <div class="subheader">Locations</div><div class="h1 mb-0" id="statTotalLocations">-</div>
            </div></div></div>
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body">
              <div class="subheader">In Use</div><div class="h1 mb-0 text-success" id="statInUse">-</div>
              <div class="progress progress-sm mt-2"><div class="progress-bar bg-success" id="statUtilBar" style="width:0%"></div></div>
            </div></div></div>
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body">
              <div class="subheader">Units Stored</div><div class="h1 mb-0" id="statUnits">-</div>
            </div></div></div>
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body">
              <div class="subheader">Inventory Value</div><div class="h1 mb-0" id="statValue">-</div>
            </div></div></div>
          </div>

          <div class="row row-cards">
            <!-- Browser -->
            <div class="col-lg-5">
              <div class="card sl-pane">
                <div class="card-header flex-column align-items-stretch gap-2">
                  <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" class="form-control" placeholder="Search name, code or path..." id="searchInput" autocomplete="off">
                  </div>
                  <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="typeFilter">
                      <option value="">All types</option>
                      <option value="warehouse">Warehouse</option><option value="zone">Zone</option>
                      <option value="aisle">Aisle</option><option value="rack">Rack</option>
                      <option value="shelf">Shelf</option><option value="bin">Bin</option><option value="other">Other</option>
                    </select>
                    <select class="form-select form-select-sm" id="usageFilter">
                      <option value="">In use + empty</option>
                      <option value="used">In use only</option>
                      <option value="empty">Empty only</option>
                    </select>
                    <div class="btn-group">
                      <button class="btn btn-sm btn-icon" title="Expand all" onclick="setAllExpanded(true)"><i class="ti ti-arrows-diagonal"></i></button>
                      <button class="btn btn-sm btn-icon" title="Collapse all" onclick="setAllExpanded(false)"><i class="ti ti-arrows-diagonal-minimize-2"></i></button>
                    </div>
                  </div>
                </div>
                <div class="card-body p-2 sl-scroll">
                  <div class="text-center text-muted py-5" id="loadingIndicator">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading locations...
                  </div>
                  <div id="locationsTree"></div>
                </div>
                <div class="card-footer text-muted small" id="resultCount"></div>
              </div>
            </div>

            <!-- Detail -->
            <div class="col-lg-7">
              <div class="card sl-pane" id="detailCard">
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-muted" id="detailEmpty">
                  <i class="ti ti-map-pin" style="font-size:3rem"></i>
                  <p class="mt-2 mb-0">Select a location to see its contents</p>
                </div>
                <div id="detailBody" class="d-none flex-column h-100 sl-detail">
                  <div class="card-header flex-column align-items-stretch">
                    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1" id="detailCrumbs"></ol></nav>
                    <div class="d-flex align-items-start gap-2">
                      <div class="flex-fill min-w-0">
                        <h3 class="mb-1 text-truncate" id="detailName"></h3>
                        <div id="detailMeta" class="d-flex flex-wrap gap-1 align-items-center"></div>
                      </div>
                      <div class="btn-list">
                        <button class="btn btn-sm" id="detailAddChild"><i class="ti ti-plus me-1"></i>Child</button>
                        <button class="btn btn-sm" id="detailEdit"><i class="ti ti-edit me-1"></i>Edit</button>
                        <button class="btn btn-sm" id="printShelfTagBtn" onclick="printShelfLabel()"><i class="ti ti-file-type-pdf me-1"></i>Label</button>
                        <button class="btn btn-sm btn-outline-danger" id="detailDelete"><i class="ti ti-trash"></i></button>
                      </div>
                    </div>
                  </div>
                  <div class="card-body sl-scroll">
                    <div class="row g-2 mb-3" id="detailStats"></div>
                    <div id="detailAddress" class="mb-3"></div>
                    <div id="detailChildrenWrap" class="mb-3" style="display:none">
                      <div class="subheader mb-2">Child locations</div>
                      <div class="d-flex flex-wrap gap-2" id="detailChildren"></div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                      <div class="subheader mb-0" id="storedHereLabel">Stored here</div>
                      <label class="form-check form-switch ms-auto mb-0" id="includeChildrenWrap">
                        <input class="form-check-input" type="checkbox" id="includeChildren">
                        <span class="form-check-label">Include child locations</span>
                      </label>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-vcenter table-sm sl-items">
                        <thead><tr>
                          <th>SKU</th><th>Description</th><th id="locColHeader" style="display:none">Location</th>
                          <th class="text-end">Qty</th><th class="text-end">Committed</th>
                          <th class="text-end">Available</th><th class="text-end">Value</th><th></th><th></th>
                        </tr></thead>
                        <tbody id="locationProductsTableBody"></tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Add/Edit Location Modal -->
  <div class="modal modal-blur fade" id="addLocationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="locationModalTitle">Add Storage Location</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="locationForm">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required">Location Name</label>
              <input type="text" class="form-control" id="locationName" name="name" placeholder="e.g., Aisle A, Rack 1, Shelf 2, Bin 5" required>
              <small class="form-text">Enter a unique name for this storage location</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Parent Location</label>
              <select class="form-select" id="locationParent" name="parent_id">
                <option value="">None (Root Level)</option>
              </select>
              <small class="form-text">Select a parent location to nest this location under</small>
            </div>
            <div class="mb-3">
              <label class="form-label required">Location Type</label>
              <select class="form-select" id="locationType" name="type" required>
                <option value="aisle">Aisle</option>
                <option value="rack">Rack</option>
                <option value="shelf">Shelf</option>
                <option value="bin" selected>Bin</option>
                <option value="warehouse">Warehouse</option>
                <option value="zone">Zone</option>
                <option value="other">Other</option>
              </select>
              <small class="form-text">Hierarchy: Aisle → Rack → Shelf → Bin</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="locationDescription" name="description" rows="2" placeholder="Optional description or notes about this location"></textarea>
            </div>
            <div class="mb-3 border rounded p-3 bg-surface-secondary" id="bulkSection" style="display:none">
              <label class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="bulkEnabled">
                <span class="form-check-label">Create multiple sequentially numbered locations</span>
              </label>
              <div id="bulkFields" style="display:none">
                <div class="row g-2">
                  <div class="col-4"><label class="form-label">How many</label>
                    <input type="number" class="form-control" id="bulkCount" min="1" max="100" value="5"></div>
                  <div class="col-4"><label class="form-label">Start at</label>
                    <input type="number" class="form-control" id="bulkStart" min="0" value="1"></div>
                  <div class="col-4"><label class="form-label">Prefix</label>
                    <input type="text" class="form-control" id="bulkPrefix"></div>
                </div>
                <div class="form-text mt-2" id="bulkPreview"></div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <label class="form-label">Aisle</label>
                <input type="text" class="form-control" id="locationAisle" name="aisle" placeholder="e.g., A1">
              </div>
              <div class="col-md-6">
                <label class="form-label">Bay</label>
                <input type="text" class="form-control" id="locationBay" name="bay" placeholder="e.g., 05">
              </div>
            </div>
            <div class="row mt-3">
              <div class="col-md-6">
                <label class="form-label">Level</label>
                <input type="text" class="form-control" id="locationLevel" name="level" placeholder="e.g., 2">
              </div>
              <div class="col-md-6">
                <label class="form-label">Position</label>
                <input type="text" class="form-control" id="locationPosition" name="position" placeholder="e.g., 03">
              </div>
            </div>
            <div class="mt-3">
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="locationActive" name="is_active" checked>
                <span class="form-check-label">Active Location</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Location</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Transfer Modal -->
  <div class="modal modal-blur fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Transfer Inventory</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="transferForm">
          <div class="modal-body">
            <div class="mb-3">
              <div class="subheader">Product</div>
              <div id="transferProduct" class="fw-bold"></div>
              <div class="text-muted small" id="transferFromInfo"></div>
            </div>
            <div class="mb-3 position-relative">
              <label class="form-label required">Move to</label>
              <input type="text" class="form-control" id="transferDest" autocomplete="off" placeholder="Type to search locations..." required>
              <div class="list-group position-absolute w-100 shadow" id="transferDestResults" style="display:none; z-index:1060; max-height:220px; overflow-y:auto;"></div>
              <input type="hidden" id="transferDestId">
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6"><label class="form-label required">Quantity</label>
                <input type="number" class="form-control" id="transferQty" min="0.1" step="0.1" required></div>
              <div class="col-6 d-flex align-items-end">
                <button type="button" class="btn btn-link px-0" id="transferAllBtn">Move all available</button></div>
            </div>
            <div>
              <label class="form-label">Notes</label>
              <input type="text" class="form-control" id="transferNotes" placeholder="Optional">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="transferSubmit"><i class="ti ti-arrows-transfer-down me-1"></i>Transfer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

@endsection


@push('scripts')
  <style>
    .sl-pane { height: calc(100vh - 330px); min-height: 480px; display: flex; flex-direction: column; }
    .sl-pane { overflow: hidden; min-width: 0; }
    .sl-scroll { overflow-y: auto; overflow-x: hidden; flex: 1 1 auto; min-height: 0; min-width: 0; }
    .sl-detail { min-height: 0; min-width: 0; flex: 1 1 auto; }
    .sl-items td, .sl-items th { white-space: nowrap; }
    .sl-items td.sl-desc { white-space: normal; min-width: 120px; max-width: 260px; overflow: hidden; text-overflow: ellipsis; }
    .sl-items td.sl-loc { max-width: 150px; overflow: hidden; text-overflow: ellipsis; }
    .min-w-0 { min-width: 0; }
    .tree-row { display: flex; align-items: center; gap: 6px; padding: 5px 8px; border-radius: var(--tblr-border-radius); cursor: pointer; }
    .tree-row:hover { background: var(--tblr-bg-surface-secondary); }
    .tree-row.active { background: var(--tblr-primary-lt); }
    .tree-toggle { width: 18px; text-align: center; color: var(--tblr-secondary-color); flex: none; }
    .tree-toggle.leaf { visibility: hidden; }
    .tree-toggle i { transition: transform .15s; }
    .tree-node.collapsed > .tree-row .tree-toggle i { transform: rotate(-90deg); }
    .tree-node.collapsed > .tree-children { display: none; }
    .tree-children { margin-left: 17px; padding-left: 4px; border-left: 1px solid var(--tblr-border-color); }
    .tree-label { flex: 1; min-width: 0; }
    .tree-label .name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
    .tree-label .path { font-size: .75rem; color: var(--tblr-secondary-color); }
    .tree-count { font-size: .75rem; color: var(--tblr-secondary-color); flex: none; }
    .tree-dot { width: 8px; height: 8px; border-radius: 50%; flex: none; background: var(--tblr-border-color); }
    .tree-dot.used { background: var(--tblr-success); }
    .tree-dot.inactive { background: var(--tblr-danger); }
    mark { padding: 0; background: var(--tblr-yellow-lt); color: inherit; }
  </style>

  <script>
    const TYPE_ICONS = { warehouse: 'ti-building-warehouse', zone: 'ti-map-2', aisle: 'ti-road', rack: 'ti-columns', shelf: 'ti-layout-rows', bin: 'ti-box', other: 'ti-dots' };
    let currentLocations = [];
    let locationTree = [];
    let statsById = new Map();
    let childrenById = new Map();
    let expanded = new Set();
    let selectedId = null;
    let editingLocationId = null;
    let currentViewLocationId = null;
    let currentItems = [];
    let includeChildren = false;
    try { includeChildren = localStorage.getItem('sl_include_children') === '1'; } catch (e) {}
    let transferSource = null;

    const money = (n, d = 2) => '$' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: d, maximumFractionDigits: d });
    const num = n => Number(n || 0).toLocaleString();

    function escapeHtml(text) {
      if (text === null || text === undefined) return '';
      return text.toString().replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    function debounce(func, wait) {
      let timeout;
      return (...args) => { clearTimeout(timeout); timeout = setTimeout(() => func(...args), wait); };
    }

    document.addEventListener('DOMContentLoaded', () => {
      loadLocations();
      document.getElementById('searchInput').addEventListener('input', debounce(renderTree, 200));
      document.getElementById('typeFilter').addEventListener('change', renderTree);
      document.getElementById('usageFilter').addEventListener('change', renderTree);
      document.getElementById('locationForm').addEventListener('submit', handleLocationFormSubmit);
      const incl = document.getElementById('includeChildren');
      incl.checked = includeChildren;
      incl.addEventListener('change', () => {
        includeChildren = incl.checked;
        try { localStorage.setItem('sl_include_children', includeChildren ? '1' : '0'); } catch (e) {}
        if (selectedId) selectLocation(selectedId);
      });

      // One delegated handler for the tree instead of inline onclick strings
      document.getElementById('locationsTree').addEventListener('click', e => {
        const row = e.target.closest('.tree-row');
        if (!row) return;
        const id = Number(row.dataset.id);
        if (e.target.closest('.tree-toggle')) { toggleNode(id); return; }
        selectLocation(id);
      });
      document.getElementById('detailChildren').addEventListener('click', e => {
        const chip = e.target.closest('[data-id]');
        if (chip) selectLocation(Number(chip.dataset.id), true);
      });
      document.getElementById('detailCrumbs').addEventListener('click', e => {
        const a = e.target.closest('[data-id]');
        if (a) { e.preventDefault(); selectLocation(Number(a.dataset.id), true); }
      });
    });

    async function loadLocations() {
      try {
        const firstLoad = currentLocations.length === 0;
        [currentLocations, locationTree] = await Promise.all([
          authenticatedFetch('/storage-locations-stats'),
          authenticatedFetch('/storage-locations-tree'),
        ]);
        statsById = new Map(currentLocations.map(l => [l.id, l.stats || {}]));
        childrenById = new Map();
        (function walk(nodes) { nodes.forEach(n => { childrenById.set(n.id, n.children || []); walk(n.children || []); }); })(locationTree);

        if (firstLoad) locationTree.forEach(n => expanded.add(n.id));

        const total = currentLocations.length;
        const inUse = currentLocations.filter(l => l.stats && l.stats.products_count > 0).length;
        // Root totals already include descendants, so summing roots avoids double counting
        const rootIds = new Set(locationTree.map(n => n.id));
        const roots = currentLocations.filter(l => rootIds.has(l.id));
        document.getElementById('statTotalLocations').textContent = num(total);
        document.getElementById('statInUse').textContent = `${num(inUse)} / ${num(total)}`;
        document.getElementById('statUtilBar').style.width = (total ? inUse / total * 100 : 0) + '%';
        document.getElementById('statUnits').textContent = num(roots.reduce((s, l) => s + (l.stats?.total_quantity_eaches || 0), 0));
        document.getElementById('statValue').textContent = money(roots.reduce((s, l) => s + (l.stats?.total_value || 0), 0), 0);

        document.getElementById('loadingIndicator').style.display = 'none';
        populateParentDropdown();
        renderTree();
        if (selectedId && statsById.has(selectedId)) selectLocation(selectedId); else if (selectedId) clearSelection();
      } catch (error) {
        console.error('Error loading locations:', error);
        document.getElementById('loadingIndicator').style.display = 'none';
        showNotification('Failed to load locations', 'danger');
      }
    }

    // ---------- Tree ----------
    function nodeMatches(node, f) {
      const loc = currentLocations.find(l => l.id === node.id) || node;
      const st = statsById.get(node.id) || {};
      if (f.type && node.type !== f.type) return false;
      if (f.usage === 'used' && !(st.products_count > 0)) return false;
      if (f.usage === 'empty' && st.products_count > 0) return false;
      if (f.q) {
        const hay = `${node.name} ${node.code || ''} ${loc.full_path || ''} ${node.description || ''}`.toLowerCase();
        if (!hay.includes(f.q)) return false;
      }
      return true;
    }

    function renderTree() {
      const f = {
        q: document.getElementById('searchInput').value.trim().toLowerCase(),
        type: document.getElementById('typeFilter').value,
        usage: document.getElementById('usageFilter').value,
      };
      const filtering = !!(f.q || f.type || f.usage);
      let shown = 0;

      // Returns html for node (or '' if neither it nor a descendant matches)
      function build(node) {
        const kids = (node.children || []).map(build).filter(Boolean);
        const self = nodeMatches(node, f);
        if (filtering && !self && kids.length === 0) return '';
        if (self) shown++;
        return rowHtml(node, kids, filtering, f.q);
      }

      const html = locationTree.map(build).filter(Boolean).join('');
      document.getElementById('locationsTree').innerHTML = html ||
        `<div class="text-center text-muted py-5">${currentLocations.length ? 'No locations match your filters.' : 'No locations yet. Click "Add Location" to create one.'}</div>`;
      document.getElementById('resultCount').textContent = filtering
        ? `${shown} of ${currentLocations.length} locations match` : `${currentLocations.length} locations`;
    }

    function highlight(text, q) {
      const safe = escapeHtml(text);
      if (!q) return safe;
      const i = text.toLowerCase().indexOf(q);
      if (i < 0) return safe;
      return escapeHtml(text.slice(0, i)) + '<mark>' + escapeHtml(text.slice(i, i + q.length)) + '</mark>' + escapeHtml(text.slice(i + q.length));
    }

    function rowHtml(node, kidsHtml, filtering, q) {
      const st = statsById.get(node.id) || {};
      const used = st.products_count > 0;
      const hasKids = kidsHtml.length > 0;
      const open = filtering || expanded.has(node.id);
      const dot = !node.is_active ? 'inactive' : (used ? 'used' : '');
      return `
        <div class="tree-node ${hasKids && !open ? 'collapsed' : ''}" data-node="${node.id}">
          <div class="tree-row ${node.id === selectedId ? 'active' : ''}" data-id="${node.id}">
            <span class="tree-toggle ${hasKids ? '' : 'leaf'}"><i class="ti ti-chevron-down"></i></span>
            <i class="ti ${TYPE_ICONS[node.type] || 'ti-map-pin'} text-secondary"></i>
            <span class="tree-label">
              <span class="name">${highlight(node.name, q)}${node.code ? ` <span class="text-muted small">${highlight(node.code, q)}</span>` : ''}</span>
            </span>
            ${used ? `<span class="tree-count" title="Products stored (including children)">${num(st.products_count)} items</span>` : ''}
            <span class="tree-dot ${dot}" title="${!node.is_active ? 'Inactive' : used ? 'In use' : 'Empty'}"></span>
          </div>
          ${hasKids ? `<div class="tree-children">${kidsHtml.join('')}</div>` : ''}
        </div>`;
    }

    function toggleNode(id) {
      expanded.has(id) ? expanded.delete(id) : expanded.add(id);
      renderTree();
    }

    function setAllExpanded(open) {
      expanded = new Set();
      if (open) childrenById.forEach((kids, id) => { if (kids.length) expanded.add(id); });
      renderTree();
    }

    function expandAncestors(id) {
      let loc = currentLocations.find(l => l.id === id);
      while (loc && loc.parent_id) { expanded.add(loc.parent_id); loc = currentLocations.find(l => l.id === loc.parent_id); }
    }

    // ---------- Detail panel ----------
    function clearSelection() {
      selectedId = null; currentViewLocationId = null;
      document.getElementById('detailBody').classList.replace('d-flex', 'd-none');
      document.getElementById('detailEmpty').classList.remove('d-none');
      renderTree();
    }

    async function selectLocation(id, reveal = false) {
      const location = currentLocations.find(l => l.id === id);
      if (!location) return;
      selectedId = id;
      currentViewLocationId = id;
      if (reveal) expandAncestors(id);
      renderTree();
      if (reveal) document.querySelector(`.tree-row[data-id="${id}"]`)?.scrollIntoView({ block: 'nearest' });

      const st = location.stats || {};
      document.getElementById('detailEmpty').classList.add('d-none');
      const body = document.getElementById('detailBody');
      body.classList.remove('d-none'); body.classList.add('d-flex');

      // Breadcrumb from ancestor chain
      const chain = [];
      for (let l = location; l; l = currentLocations.find(x => x.id === l.parent_id)) chain.unshift(l);
      document.getElementById('detailCrumbs').innerHTML = chain.map((l, i) => i === chain.length - 1
        ? `<li class="breadcrumb-item active">${escapeHtml(l.name)}</li>`
        : `<li class="breadcrumb-item"><a href="#" data-id="${l.id}">${escapeHtml(l.name)}</a></li>`).join('');

      document.getElementById('detailName').innerHTML = `<i class="ti ${TYPE_ICONS[location.type] || 'ti-map-pin'} me-2 text-secondary"></i>${escapeHtml(location.name)}`;
      document.getElementById('detailMeta').innerHTML = `
        <span class="badge bg-azure-lt">${escapeHtml(location.type)}</span>
        ${location.code ? `<span class="badge bg-secondary-lt">${escapeHtml(location.code)}</span>` : ''}
        ${location.is_active ? '<span class="badge bg-success-lt">Active</span>' : '<span class="badge bg-danger-lt">Inactive</span>'}
        ${location.description ? `<span class="text-muted small ms-1">${escapeHtml(location.description)}</span>` : ''}`;

      document.getElementById('detailAddChild').onclick = () => addChildLocation(id, location.name);
      document.getElementById('detailEdit').onclick = () => editLocation(id);
      document.getElementById('detailDelete').onclick = () => deleteLocation(id, location.name);

      const tile = (label, value, cls = '') => `<div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="subheader">${label}</div><div class="h3 mb-0 ${cls}">${value}</div></div></div>`;
      document.getElementById('detailStats').innerHTML =
        tile('Products', num(st.products_count)) +
        tile('Units', num(st.total_quantity_eaches)) +
        tile('Available', num(st.total_available_eaches), 'text-success') +
        tile('Value', money(st.total_value, 0));

      const addr = [['Aisle', location.aisle], ['Bay', location.bay], ['Level', location.level], ['Position', location.position]].filter(a => a[1]);
      document.getElementById('detailAddress').innerHTML = addr.length
        ? `<div class="subheader mb-1">Address</div>` + addr.map(a => `<span class="badge bg-secondary-lt me-1">${a[0]}: ${escapeHtml(a[1])}</span>`).join('')
        : '';

      const kids = childrenById.get(id) || [];
      document.getElementById('detailChildrenWrap').style.display = kids.length ? '' : 'none';
      document.getElementById('detailChildren').innerHTML = kids.map(k => {
        const ks = statsById.get(k.id) || {};
        return `<button type="button" class="btn btn-sm" data-id="${k.id}"><i class="ti ${TYPE_ICONS[k.type] || 'ti-map-pin'} me-1"></i>${escapeHtml(k.name)}${ks.products_count ? ` <span class="badge bg-green-lt ms-1">${num(ks.products_count)}</span>` : ''}</button>`;
      }).join('');

      // Only worth offering when there is something below this location
      const showSub = includeChildren && kids.length > 0;
      document.getElementById('includeChildrenWrap').style.display = kids.length ? '' : 'none';
      document.getElementById('storedHereLabel').textContent = showSub ? 'Stored here and in child locations' : 'Stored here';
      document.getElementById('locColHeader').style.display = showSub ? '' : 'none';
      const cols = showSub ? 9 : 8;
      const tbody = document.getElementById('locationProductsTableBody');
      tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted">Loading...</td></tr>`;
      try {
        const details = await authenticatedFetch(`/storage-locations/${id}${showSub ? '?include_children=1' : ''}`);
        if (selectedId !== id) return; // user clicked elsewhere meanwhile
        const items = details?.inventory_locations || [];
        if (!items.length) {
          tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted py-4">${showSub ? 'Nothing stored here or in child locations' : 'Nothing stored directly at this location'}</td></tr>`;
          return;
        }
        currentItems = items;
        tbody.innerHTML = items.map(item => {
          const p = item.product;
          const qty = parseFloat(item.quantity || 0);
          const committed = parseFloat(item.quantity_committed || 0);
          const cost = parseFloat(p?.unit_cost || 0);
          const value = (p?.pack_size > 1 ? qty / p.pack_size : qty) * cost;
          return `<tr>
            <td class="text-nowrap">${escapeHtml(p?.sku || '-')}</td>
            <td class="sl-desc" title="${escapeHtml(p?.description || '')}">${escapeHtml(p?.description || '-')}</td>
            ${showSub ? `<td class="text-nowrap small sl-loc">${item.storage_location_id === id ? '<span class="text-muted">(this location)</span>' : escapeHtml(item.storage_location?.name || '')}</td>` : ''}
            <td class="text-end">${num(qty)}</td>
            <td class="text-end">${num(committed)}</td>
            <td class="text-end text-success">${num(qty - committed)}</td>
            <td class="text-end">${money(value)}</td>
            <td>${item.is_primary ? '<span class="badge bg-primary-lt">Primary</span>' : ''}</td>
            <td class="text-end"><button type="button" class="btn btn-sm" data-permission="inventory.adjust" ${qty - committed > 0 ? '' : 'disabled'}
                onclick="openTransfer(${item.id})"><i class="ti ti-arrows-transfer-down me-1"></i>Move</button></td>
          </tr>`;
        }).join('');
        if (typeof applyActionPermissions === 'function') applyActionPermissions();
      } catch (error) {
        console.error('Error loading location details:', error);
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-danger">Failed to load products</td></tr>`;
      }
    }

    // ---------- PDFs ----------
    async function downloadPdf(url, filename, btn, errorMsg) {
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating…';
      try {
        const response = await fetch(url, { headers: { 'Accept': 'application/pdf' } });
        if (!response.ok) { showNotification(errorMsg, 'danger'); return; }
        const blobUrl = URL.createObjectURL(await response.blob());
        const a = document.createElement('a');
        a.href = blobUrl; a.download = filename;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(blobUrl);
      } catch (e) {
        showNotification(errorMsg, 'danger');
      } finally {
        btn.disabled = false; btn.innerHTML = original;
      }
    }

    function printShelfLabel() {
      if (!currentViewLocationId) return;
      const loc = currentLocations.find(l => l.id === currentViewLocationId);
      const name = String(loc?.code || loc?.name || currentViewLocationId).replace(/[^A-Za-z0-9\-_.]/g, '_');
      downloadPdf(`${API_BASE}/storage-locations/${currentViewLocationId}/shelf-label-pdf`, `shelf-label-${name}.pdf`,
        document.getElementById('printShelfTagBtn'), 'Failed to generate shelf label PDF');
    }

    function printAllShelfLabels() {
      downloadPdf(`${API_BASE}/storage-locations/bulk-shelf-labels-pdf`, 'shelf-labels-all.pdf',
        document.getElementById('bulkShelfPrintBtn'), 'Failed to generate shelf labels PDF');
    }


    // ---------- Bulk child creation ----------
    function bulkPrefixDefault() {
      const parent = currentLocations.find(l => l.id === Number(document.getElementById('locationParent').value));
      return parent ? parent.name + '.' : '';
    }

    function updateBulkPreview() {
      const count = Math.max(0, parseInt(document.getElementById('bulkCount').value) || 0);
      const start = parseInt(document.getElementById('bulkStart').value) || 0;
      const prefix = document.getElementById('bulkPrefix').value;
      const names = Array.from({ length: Math.min(count, 3) }, (_, i) => prefix + (start + i));
      const last = prefix + (start + count - 1);
      document.getElementById('bulkPreview').textContent = count
        ? `Will create ${count}: ${names.join(', ')}${count > 3 ? ', … ' + last : ''}` : '';
    }

    function setBulkMode(on, resetPrefix = false) {
      document.getElementById('bulkFields').style.display = on ? '' : 'none';
      document.getElementById('bulkEnabled').checked = on;
      // single-location name is replaced by prefix + number
      const name = document.getElementById('locationName');
      name.required = !on;
      name.disabled = on;
      if (on && resetPrefix) document.getElementById('bulkPrefix').value = bulkPrefixDefault();
      if (on) updateBulkPreview();
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('bulkEnabled').addEventListener('change', e => setBulkMode(e.target.checked, true));
      ['bulkCount', 'bulkStart', 'bulkPrefix'].forEach(id => document.getElementById(id).addEventListener('input', updateBulkPreview));
      document.getElementById('locationParent').addEventListener('change', () => {
        if (document.getElementById('bulkEnabled').checked) {
          document.getElementById('bulkPrefix').value = bulkPrefixDefault();
          updateBulkPreview();
        }
      });
    });

    // ---------- Transfer ----------
    function openTransfer(inventoryLocationId) {
      const item = currentItems.find(i => i.id === inventoryLocationId);
      if (!item) return;
      const available = parseFloat(item.quantity || 0) - parseFloat(item.quantity_committed || 0);
      transferSource = { ...item, available };
      document.getElementById('transferForm').reset();
      document.getElementById('transferDestId').value = '';
      document.getElementById('transferProduct').textContent = `${item.product?.sku || ''} — ${item.product?.description || ''}`;
      const from = currentLocations.find(l => l.id === selectedId);
      document.getElementById('transferFromInfo').textContent = `From ${from?.full_path || from?.name || ''} · ${num(available)} available`;
      const qty = document.getElementById('transferQty');
      qty.max = available;
      showModal(document.getElementById('transferModal'));
      setTimeout(() => document.getElementById('transferDest').focus(), 300);
    }

    function searchTransferDest() {
      const input = document.getElementById('transferDest');
      const box = document.getElementById('transferDestResults');
      document.getElementById('transferDestId').value = '';
      const terms = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
      const matches = currentLocations
        .filter(l => l.is_active && l.id !== selectedId && terms.every(t => `${l.full_path || l.name} ${l.code || ''}`.toLowerCase().includes(t)))
        .slice(0, 10);
      box.innerHTML = matches.length ? matches.map(l => `
        <button type="button" class="list-group-item list-group-item-action" data-id="${l.id}" data-path="${escapeHtml(l.full_path || l.name)}">
          <strong>${escapeHtml(l.name)}</strong> <span class="badge bg-azure-lt ms-1">${escapeHtml(l.type)}</span><br>
          <small class="text-muted">${escapeHtml(l.full_path || l.name)}</small></button>`).join('')
        : '<div class="list-group-item text-muted">No matching locations</div>';
      box.style.display = 'block';
    }

    document.addEventListener('DOMContentLoaded', () => {
      const input = document.getElementById('transferDest');
      const box = document.getElementById('transferDestResults');
      input.addEventListener('input', searchTransferDest);
      input.addEventListener('focus', searchTransferDest);
      box.addEventListener('mousedown', e => {
        const el = e.target.closest('button[data-id]');
        if (!el) return;
        e.preventDefault();
        input.value = el.dataset.path;
        document.getElementById('transferDestId').value = el.dataset.id;
        box.style.display = 'none';
      });
      input.addEventListener('blur', () => setTimeout(() => { box.style.display = 'none'; }, 150));
      document.getElementById('transferAllBtn').addEventListener('click', () => {
        if (transferSource) document.getElementById('transferQty').value = transferSource.available;
      });
      document.getElementById('transferForm').addEventListener('submit', handleTransferSubmit);
    });

    async function handleTransferSubmit(e) {
      e.preventDefault();
      const destId = document.getElementById('transferDestId').value;
      const qty = parseFloat(document.getElementById('transferQty').value);
      if (!destId) { showNotification('Pick a destination from the suggestions', 'warning'); return; }
      if (!(qty > 0) || qty > transferSource.available) { showNotification(`Quantity must be between 0.1 and ${transferSource.available}`, 'warning'); return; }

      const btn = document.getElementById('transferSubmit');
      btn.disabled = true;
      try {
        const response = await apiCall('/inventory-locations/transfer', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            from_inventory_location_id: transferSource.id,
            to_storage_location_id: Number(destId),
            quantity: qty,
            notes: document.getElementById('transferNotes').value || null,
          }),
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || Object.values(body.errors || {}).flat().join(' ') || 'Transfer failed');
        showNotification('Inventory transferred', 'success');
        hideModal(document.getElementById('transferModal'));
        expandAncestors(Number(destId));
        await loadLocations();
      } catch (error) {
        showNotification(error.message, 'danger');
      } finally {
        btn.disabled = false;
      }
    }

    // ---------- Add / edit ----------
    function populateParentDropdown() {
      const select = document.getElementById('locationParent');
      const current = select.value;
      select.innerHTML = '<option value="">None (Root Level)</option>';
      (function add(nodes, indent) {
        nodes.forEach(node => {
          const o = document.createElement('option');
          o.value = node.id;
          o.textContent = `${indent}${node.name} (${node.type})`;
          select.appendChild(o);
          add(node.children || [], indent + '\u00A0\u00A0\u00A0');
        });
      })(locationTree, '');
      if (current) select.value = current;
    }

    function showAddLocationModal() {
      editingLocationId = null;
      document.getElementById('locationForm').reset();
      document.getElementById('locationModalTitle').textContent = 'Add Storage Location';
      document.getElementById('locationActive').checked = true;
      document.getElementById('bulkSection').style.display = 'none';
      setBulkMode(false);
      showModal(document.getElementById('addLocationModal'));
    }

    function addChildLocation(parentId, parentName) {
      showAddLocationModal();
      document.getElementById('locationModalTitle').textContent = `Add Child under "${parentName}"`;
      document.getElementById('locationParent').value = parentId;
      const parent = currentLocations.find(l => l.id === parentId);
      const next = { warehouse: 'zone', zone: 'aisle', aisle: 'rack', rack: 'shelf', shelf: 'bin' };
      document.getElementById('locationType').value = next[parent?.type] || 'bin';
      document.getElementById('bulkSection').style.display = '';
    }

    function editLocation(locationId) {
      const location = currentLocations.find(l => l.id === locationId);
      if (!location) return;
      editingLocationId = locationId;
      document.getElementById('bulkSection').style.display = 'none';
      setBulkMode(false);
      document.getElementById('locationModalTitle').textContent = 'Edit Storage Location';
      document.getElementById('locationName').value = location.name || '';
      document.getElementById('locationParent').value = location.parent_id || '';
      document.getElementById('locationDescription').value = location.description || '';
      document.getElementById('locationType').value = location.type || 'bin';
      document.getElementById('locationAisle').value = location.aisle || '';
      document.getElementById('locationBay').value = location.bay || '';
      document.getElementById('locationLevel').value = location.level || '';
      document.getElementById('locationPosition').value = location.position || '';
      document.getElementById('locationActive').checked = !!location.is_active;
      showModal(document.getElementById('addLocationModal'));
    }

    async function handleLocationFormSubmit(e) {
      e.preventDefault();
      const fd = new FormData(e.target);
      const data = {
        name: fd.get('name'),
        parent_id: fd.get('parent_id') || null,
        type: fd.get('type'),
        description: fd.get('description'),
        aisle: fd.get('aisle'),
        bay: fd.get('bay'),
        level: fd.get('level'),
        position: fd.get('position'),
        is_active: fd.get('is_active') === 'on',
      };
      if (!editingLocationId && document.getElementById('bulkEnabled').checked) {
        return submitBulk(data);
      }
      try {
        const response = await apiCall(editingLocationId ? `/storage-locations/${editingLocationId}` : `/storage-locations`, {
          method: editingLocationId ? 'PUT' : 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        if (!response.ok) {
          const error = await response.json();
          throw new Error(error.message || Object.values(error.errors || {}).flat().join(' ') || 'Failed to save location');
        }
        const saved = await response.json().catch(() => null);
        showNotification(editingLocationId ? 'Location updated' : 'Location created', 'success');
        hideModal(document.getElementById('addLocationModal'));
        if (data.parent_id) expanded.add(Number(data.parent_id));
        const newId = saved?.id ?? saved?.data?.id ?? saved?.location?.id;
        if (!editingLocationId && newId) selectedId = newId;
        await loadLocations();
      } catch (error) {
        console.error('Error saving location:', error);
        showNotification('Failed to save location: ' + error.message, 'danger');
      }
    }

    async function submitBulk(data) {
      if (!data.parent_id) { showNotification('Choose a parent location for bulk creation', 'warning'); return; }
      try {
        const response = await apiCall('/storage-locations/bulk-children', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            parent_id: Number(data.parent_id),
            count: parseInt(document.getElementById('bulkCount').value),
            start: parseInt(document.getElementById('bulkStart').value) || 0,
            prefix: document.getElementById('bulkPrefix').value,
            type: data.type,
            description: data.description || null,
            is_active: data.is_active,
          }),
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || Object.values(body.errors || {}).flat().join(' ') || 'Failed to create locations');
        showNotification(`Created ${body.length} locations`, 'success');
        hideModal(document.getElementById('addLocationModal'));
        expanded.add(Number(data.parent_id));
        await loadLocations();
      } catch (error) {
        showNotification(error.message, 'danger');
      }
    }

    async function deleteLocation(locationId, locationName) {
      if (!confirm(`Delete location "${locationName}"? This cannot be undone.`)) return;
      try {
        const response = await apiCall(`/storage-locations/${locationId}`, { method: 'DELETE' });
        if (!response.ok) {
          const error = await response.json();
          throw new Error(error.message || 'Failed to delete location');
        }
        showNotification('Location deleted', 'success');
        if (selectedId === locationId) selectedId = null;
        await loadLocations();
      } catch (error) {
        console.error('Error deleting location:', error);
        showNotification('Failed to delete location: ' + error.message, 'danger');
      }
    }

    function exportLocations() {
      const headers = ['Path', 'Name', 'Code', 'Type', 'Aisle', 'Bay', 'Level', 'Position', 'Products Stored', 'Total Quantity', 'Total Value', 'Status'];
      const rows = currentLocations.map(l => [
        l.full_path || l.name, l.name, l.code || '', l.type, l.aisle || '', l.bay || '', l.level || '', l.position || '',
        l.stats.products_count, l.stats.total_quantity_eaches, Number(l.stats.total_value).toFixed(2), l.is_active ? 'Active' : 'Inactive'
      ]);
      const csv = [headers, ...rows].map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
      const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = `storage_locations_${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
      URL.revokeObjectURL(url);
      showNotification('Locations exported', 'success');
    }
  </script>
@endpush
