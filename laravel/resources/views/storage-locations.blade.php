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
              <p class="text-muted">Manage warehouse locations and inventory distribution</p>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn" onclick="exportLocations()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                  Export
                </button>
                <button class="btn btn-primary d-none d-sm-inline-block" onclick="showAddLocationModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Add Location
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <!-- Stats Cards -->
          <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Total Locations</div>
                  <div class="h1 mb-3" id="statTotalLocations">-</div>
                  <div>Active storage locations</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Locations in Use</div>
                  <div class="h1 mb-3 text-success" id="statInUse">-</div>
                  <div>With inventory assigned</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Total Capacity</div>
                  <div class="h1 mb-3" id="statTotalCapacity">-</div>
                  <div>Units across all locations</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Capacity Utilization</div>
                  <div class="h1 mb-3 text-info" id="statUtilization">-</div>
                  <div>Percentage in use</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Locations Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Storage Locations</h3>
                  <div class="ms-auto d-flex gap-2">
                    <div class="btn-group" role="group">
                      <input type="radio" class="btn-check" name="view-mode" id="view-tree" autocomplete="off" checked>
                      <label class="btn btn-sm" for="view-tree">Tree View</label>
                      <input type="radio" class="btn-check" name="view-mode" id="view-list" autocomplete="off">
                      <label class="btn btn-sm" for="view-list">List View</label>
                    </div>
                    <input type="text" class="form-control form-control-sm" placeholder="Search locations..." id="searchInput" style="min-width: 200px;">
                  </div>
                </div>
                <div class="card-body">
                  <div class="loading" id="loadingIndicator">
                    <div class="spinner-border" role="status"></div>
                    <div>Loading locations...</div>
                  </div>

                  <!-- Tree View -->
                  <div id="treeViewContainer" style="display: none;">
                    <div id="locationsTree"></div>
                  </div>

                  <!-- List View -->
                  <div class="table-responsive" id="locationsTableContainer" style="display: none;">
                    <table class="table table-vcenter card-table table-striped">
                      <thead>
                        <tr>
                          <th>Location Path</th>
                          <th>Type</th>
                          <th class="text-end">Products Stored</th>
                          <th class="text-end">Total Quantity</th>
                          <th class="text-end">Total Value</th>
                          <th>Status</th>
                          <th class="w-1"></th>
                        </tr>
                      </thead>
                      <tbody id="locationsTableBody"></tbody>
                    </table>
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
              <small class="form-hint">Enter a unique name for this storage location</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Parent Location</label>
              <select class="form-select" id="locationParent" name="parent_id">
                <option value="">None (Root Level)</option>
              </select>
              <small class="form-hint">Select a parent location to nest this location under</small>
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
              <small class="form-hint">Hierarchy: Aisle → Rack → Shelf → Bin</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="locationDescription" name="description" rows="2" placeholder="Optional description or notes about this location"></textarea>
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

  <!-- View Location Details Modal -->
  <div class="modal modal-blur fade" id="viewLocationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewLocationModalTitle">Location Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="locationDetailsView"></div>

          <hr class="my-4">

          <h5 class="mb-3">Products at this Location</h5>
          <div class="table-responsive">
            <table class="table table-vcenter">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Description</th>
                  <th class="text-end">Quantity</th>
                  <th class="text-end">Committed</th>
                  <th class="text-end">Available</th>
                  <th class="text-end">Value</th>
                  <th>Primary</th>
                </tr>
              </thead>
              <tbody id="locationProductsTableBody">
                <tr>
                  <td colspan="7" class="text-center text-muted">Loading...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

@endsection


@push('scripts')
  <style>
    .tree-node {
      padding: 0;
    }
    .tree-node-content {
      display: flex;
      align-items: center;
      padding: 4px 8px;
      border-radius: 4px;
      transition: background 0.2s;
      min-height: 36px;
    }
    .tree-node-content:hover {
      background: #f8f9fa;
    }
    .tree-node-toggle {
      width: 16px;
      height: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-right: 6px;
      cursor: pointer;
      user-select: none;
      font-weight: bold;
      font-size: 12px;
    }
    .tree-node-toggle.empty {
      visibility: hidden;
    }
    .tree-node-icon {
      margin-right: 6px;
      color: #6c757d;
      font-size: 16px;
    }
    .tree-children {
      margin-left: 20px;
      border-left: 1px dashed #dee2e6;
      padding-left: 6px;
    }
    .tree-children.collapsed {
      display: none;
    }
    .tree-node-actions {
      margin-left: auto;
      display: flex;
      gap: 2px;
      opacity: 0;
      transition: opacity 0.2s;
    }
    .tree-node-content:hover .tree-node-actions {
      opacity: 1;
    }
    .tree-node-actions .btn {
      padding: 2px 4px;
      font-size: 14px;
    }
    .type-badge {
      font-size: 0.7rem;
      padding: 1px 4px;
      border-radius: 3px;
      margin-left: 6px;
    }
    .tree-node-info {
      display: flex;
      flex-direction: column;
      flex: 1;
      min-width: 0;
    }
    .tree-node-info strong {
      font-size: 0.9rem;
      line-height: 1.3;
    }
    .tree-node-info .small {
      font-size: 0.75rem;
      line-height: 1.2;
      margin-top: 1px;
    }
  </style>

  <script>
    let currentLocations = [];
    let locationTree = [];
    let editingLocationId = null;
    let currentViewMode = 'tree';

    // Utility function to escape HTML
    function escapeHtml(text) {
      if (!text) return '';
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    document.addEventListener('DOMContentLoaded', () => {
      loadLocations();

      // View mode toggle
      document.querySelectorAll('input[name="view-mode"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
          currentViewMode = e.target.id === 'view-tree' ? 'tree' : 'list';
          renderCurrentView();
        });
      });

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', debounce(filterLocations, 300));

      // Form submission
      document.getElementById('locationForm').addEventListener('submit', handleLocationFormSubmit);
    });

    async function loadLocations() {
      try {
        currentLocations = await authenticatedFetch(`/storage-locations-stats`);
        locationTree = await authenticatedFetch(`/storage-locations-tree`);

        // Calculate aggregated stats
        const totalLocations = currentLocations.length;
        const inUse = currentLocations.filter(l => l.stats && l.stats.products_count > 0).length;
        const totalCapacity = currentLocations.reduce((sum, l) => sum + ((l.stats && l.stats.total_quantity_eaches) || 0), 0);
        const utilization = totalLocations > 0 ? ((inUse / totalLocations) * 100).toFixed(1) : 0;

        document.getElementById('statTotalLocations').textContent = totalLocations.toLocaleString();
        document.getElementById('statInUse').textContent = inUse.toLocaleString();
        document.getElementById('statTotalCapacity').textContent = totalCapacity.toLocaleString();
        document.getElementById('statUtilization').textContent = `${utilization}%`;

        renderCurrentView();
        populateParentDropdown();

        document.getElementById('loadingIndicator').style.display = 'none';
      } catch (error) {
        console.error('Error loading locations:', error);
        showNotification('Failed to load locations', 'danger');
      }
    }

    function renderCurrentView() {
      if (currentViewMode === 'tree') {
        document.getElementById('treeViewContainer').style.display = 'block';
        document.getElementById('locationsTableContainer').style.display = 'none';
        renderTreeView(locationTree);
      } else {
        document.getElementById('treeViewContainer').style.display = 'none';
        document.getElementById('locationsTableContainer').style.display = 'block';
        renderLocationsTable(currentLocations);
      }
    }

    function renderTreeView(nodes) {
      const container = document.getElementById('locationsTree');

      if (!nodes || nodes.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-5">No locations found. Click "Add Location" to create one.</div>';
        return;
      }

      container.innerHTML = nodes.map(node => renderTreeNode(node)).join('');
    }

    function renderTreeNode(node) {
      const hasChildren = node.children && node.children.length > 0;
      const location = currentLocations.find(l => l.id === node.id);
      const stats = location?.stats || { products_count: 0, total_quantity_eaches: 0, total_value: 0 };

      const toggleIcon = hasChildren ? '▼' : '';
      const typeIcons = {
        aisle: 'ti-road',
        rack: 'ti-building-warehouse',
        shelf: 'ti-layout-rows',
        bin: 'ti-box',
        warehouse: 'ti-building',
        zone: 'ti-map-pin',
        other: 'ti-dots'
      };
      const icon = typeIcons[node.type] || 'ti-map-pin';

      const statusBadge = stats.products_count > 0
        ? '<span class="badge badge-sm bg-success-lt">In Use</span>'
        : '<span class="badge badge-sm bg-secondary-lt">Empty</span>';

      return `
        <div class="tree-node" data-id="${node.id}">
          <div class="tree-node-content">
            <span class="tree-node-toggle ${!hasChildren ? 'empty' : ''}" onclick="toggleTreeNode(event, ${node.id})">
              ${toggleIcon}
            </span>
            <i class="ti ${icon} tree-node-icon"></i>
            <div class="tree-node-info">
              <div>
                <strong>${escapeHtml(node.name)}</strong>
                <span class="type-badge badge bg-azure-lt">${node.type}</span>
                ${node.code ? `<span class="text-muted ms-1 small">${escapeHtml(node.code)}</span>` : ''}
              </div>
              <div class="small text-muted">
                ${stats.products_count} products | ${stats.total_quantity_eaches} units | $${stats.total_value.toLocaleString(undefined, {minimumFractionDigits: 2})}
              </div>
            </div>
            ${statusBadge}
            <div class="tree-node-actions">
              <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="addChildLocation(${node.id}, '${escapeHtml(node.name).replace(/'/g, "\\'")}')" title="Add Child">
                <i class="ti ti-plus"></i>
              </button>
              <button class="btn btn-sm btn-icon btn-ghost-secondary" onclick="editLocation(${node.id})" title="Edit">
                <i class="ti ti-edit"></i>
              </button>
              <button class="btn btn-sm btn-icon btn-ghost-info" onclick="viewLocationDetails(${node.id})" title="View">
                <i class="ti ti-eye"></i>
              </button>
              <button class="btn btn-sm btn-icon btn-ghost-danger" onclick="deleteLocation(${node.id}, '${escapeHtml(node.name).replace(/'/g, "\\'")}')" title="Delete">
                <i class="ti ti-trash"></i>
              </button>
            </div>
          </div>
          ${hasChildren ? `
            <div class="tree-children" id="children-${node.id}">
              ${node.children.map(child => renderTreeNode(child)).join('')}
            </div>
          ` : ''}
        </div>
      `;
    }

    function toggleTreeNode(event, nodeId) {
      event.stopPropagation();
      const childrenEl = document.getElementById(`children-${nodeId}`);
      const toggle = event.target;

      if (childrenEl) {
        childrenEl.classList.toggle('collapsed');
        toggle.textContent = childrenEl.classList.contains('collapsed') ? '▶' : '▼';
      }
    }

    function addChildLocation(parentId, parentName) {
      editingLocationId = null;
      document.getElementById('locationForm').reset();
      document.getElementById('locationModalTitle').textContent = `Add Child Location under "${parentName}"`;
      document.getElementById('locationName').value = '';
      document.getElementById('locationParent').value = parentId;
      document.getElementById('locationActive').checked = true;

      // Suggest type based on parent
      const parent = currentLocations.find(l => l.id === parentId);
      if (parent) {
        const typeHierarchy = { aisle: 'rack', rack: 'shelf', shelf: 'bin' };
        const suggestedType = typeHierarchy[parent.type] || 'bin';
        document.getElementById('locationType').value = suggestedType;
      }

      showModal(document.getElementById('addLocationModal'));
    }

    function populateParentDropdown() {
      const select = document.getElementById('locationParent');
      const currentValue = select.value;

      select.innerHTML = '<option value="">None (Root Level)</option>';

      // Flatten tree for dropdown
      function addOptions(nodes, indent = '') {
        nodes.forEach(node => {
          const option = document.createElement('option');
          option.value = node.id;
          option.textContent = `${indent}${node.name} (${node.type})`;
          select.appendChild(option);

          if (node.children && node.children.length > 0) {
            addOptions(node.children, indent + '  ');
          }
        });
      }

      addOptions(locationTree);

      if (currentValue) {
        select.value = currentValue;
      }
    }

    function renderLocationsTable(locations) {
      const tbody = document.getElementById('locationsTableBody');
      tbody.innerHTML = '';

      if (locations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-5">No locations found</td></tr>';
        return;
      }

      // Sort by sort_order then name
      locations.sort((a, b) => {
        if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
        return a.name.localeCompare(b.name);
      });

      locations.forEach(location => {
        const statusBadge = location.stats.products_count > 0
          ? '<span class="badge text-bg-success">In Use</span>'
          : '<span class="badge text-bg-secondary">Empty</span>';

        const row = `
          <tr>
            <td>
              <div class="d-flex align-items-center">
                <span class="avatar avatar-sm me-2"><i class="ti ti-map-pin"></i></span>
                <div>
                  <strong>${location.full_path || location.name}</strong>
                  ${location.code ? `<br><small class="text-muted">${location.code}</small>` : ''}
                  ${location.full_address ? `<br><small class="text-muted">${location.full_address}</small>` : ''}
                </div>
              </div>
            </td>
            <td><span class="badge bg-azure-lt">${location.type}</span></td>
            <td class="text-end">${location.stats.products_count.toLocaleString()}</td>
            <td class="text-end">${location.stats.total_quantity_eaches.toLocaleString()}</td>
            <td class="text-end">$${location.stats.total_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td>${statusBadge}</td>
            <td class="table-actions">
              <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="viewLocationDetails(${location.id})" title="View">
                <i class="ti ti-eye"></i>
              </button>
              <button class="btn btn-sm btn-icon btn-ghost-secondary" onclick="editLocation(${location.id})" title="Edit">
                <i class="ti ti-edit"></i>
              </button>
              <button class="btn btn-sm btn-icon btn-ghost-danger" onclick="deleteLocation(${location.id}, '${location.name.replace(/'/g, "\\'")}')" title="Delete">
                <i class="ti ti-trash"></i>
              </button>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });
    }

    function filterLocations() {
      const search = document.getElementById('searchInput').value.toLowerCase();

      let filteredLocations = currentLocations;

      if (search) {
        filteredLocations = currentLocations.filter(loc =>
          loc.name.toLowerCase().includes(search) ||
          (loc.code && loc.code.toLowerCase().includes(search)) ||
          (loc.description && loc.description.toLowerCase().includes(search))
        );
      }

      renderLocationsTable(filteredLocations);
    }

    async function viewLocationDetails(locationId) {
      try {
        const location = currentLocations.find(l => l.id === locationId);
        if (!location) return;

        document.getElementById('viewLocationModalTitle').textContent = `Location: ${location.name}`;

        document.getElementById('locationDetailsView').innerHTML = `
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label fw-bold">Location Name</label>
              <p><i class="ti ti-map-pin me-2"></i>${location.name}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Code</label>
              <p>${location.code || '-'}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Type</label>
              <p><span class="badge text-bg-azure">${location.type}</span></p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Status</label>
              <p>${location.is_active ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>'}</p>
            </div>
          </div>
          ${location.full_address ? `
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label fw-bold">Full Address</label>
              <p>${location.full_address}</p>
            </div>
          </div>
          ` : ''}
          ${location.description ? `
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label fw-bold">Description</label>
              <p>${location.description}</p>
            </div>
          </div>
          ` : ''}
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label fw-bold">Products Stored</label>
              <p>${location.stats.products_count.toLocaleString()}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Total Quantity (Eaches)</label>
              <p>${location.stats.total_quantity_eaches.toLocaleString()}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Available (Eaches)</label>
              <p class="text-success">${location.stats.total_available_eaches.toLocaleString()}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Total Value</label>
              <p>$${location.stats.total_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
            </div>
          </div>
        `;

        // Render products table - fetch full location details with inventory
        const tbody = document.getElementById('locationProductsTableBody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading products...</td></tr>';

        // Fetch detailed inventory for this location
        const locationDetails = await authenticatedFetch(`/storage-locations/${locationId}`);
        const inventoryLocs = locationDetails?.inventory_locations || [];

        if (inventoryLocs.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No products at this location</td></tr>';
        } else {
          tbody.innerHTML = '';
          inventoryLocs.forEach(item => {
            const product = item.product;
            const qtyEaches = parseFloat(item.quantity || 0);
            const committedEaches = parseFloat(item.quantity_committed || 0);
            const available = qtyEaches - committedEaches;

            // Pack-aware value calculation
            let value;
            if (product?.pack_size && product.pack_size > 1) {
              const qtyPacks = qtyEaches / product.pack_size;
              value = qtyPacks * parseFloat(product?.unit_cost || 0);
            } else {
              value = qtyEaches * parseFloat(product?.unit_cost || 0);
            }

            const isPrimary = item.is_primary ? '<i class="ti ti-check text-success"></i>' : '';

            const row = `
              <tr>
                <td><span class="text-muted">${product?.sku || '-'}</span></td>
                <td>${product?.description || '-'}</td>
                <td class="text-end">${qtyEaches.toLocaleString()}</td>
                <td class="text-end">${committedEaches.toLocaleString()}</td>
                <td class="text-end text-success">${available.toLocaleString()}</td>
                <td class="text-end">$${value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td class="text-center">${isPrimary}</td>
              </tr>
            `;
            tbody.innerHTML += row;
          });
        }

        showModal(document.getElementById('viewLocationModal'));
      } catch (error) {
        console.error('Error loading location details:', error);
        showNotification('Failed to load location details', 'danger');
      }
    }

    function showAddLocationModal() {
      editingLocationId = null;
      document.getElementById('locationForm').reset();
      document.getElementById('locationModalTitle').textContent = 'Add Storage Location';
      document.getElementById('locationName').value = '';
      document.getElementById('locationActive').checked = true;
      showModal(document.getElementById('addLocationModal'));
    }

    async function editLocation(locationId) {
      try {
        const location = currentLocations.find(l => l.id === locationId);
        if (!location) return;

        editingLocationId = locationId;
        document.getElementById('locationModalTitle').textContent = 'Edit Storage Location';

        // Populate form
        document.getElementById('locationName').value = location.name || '';
        document.getElementById('locationParent').value = location.parent_id || '';
        document.getElementById('locationDescription').value = location.description || '';
        document.getElementById('locationType').value = location.type || 'bin';
        document.getElementById('locationAisle').value = location.aisle || '';
        document.getElementById('locationBay').value = location.bay || '';
        document.getElementById('locationLevel').value = location.level || '';
        document.getElementById('locationPosition').value = location.position || '';
        document.getElementById('locationActive').checked = location.is_active;

        showModal(document.getElementById('addLocationModal'));
      } catch (error) {
        console.error('Error loading location for edit:', error);
        showNotification('Failed to load location', 'danger');
      }
    }

    async function handleLocationFormSubmit(e) {
      e.preventDefault();

      const formData = new FormData(e.target);
      const data = {
        name: formData.get('name'),
        parent_id: formData.get('parent_id') || null,
        type: formData.get('type'),
        description: formData.get('description'),
        aisle: formData.get('aisle'),
        bay: formData.get('bay'),
        level: formData.get('level'),
        position: formData.get('position'),
        is_active: formData.get('is_active') === 'on',
      };

      try {
        let response;
        if (editingLocationId) {
          // Update existing location
          response = await apiCall(`/storage-locations/${editingLocationId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
          });
        } else {
          // Create new location
          response = await apiCall(`/storage-locations`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
          });
        }

        if (response.ok) {
          showNotification(editingLocationId ? 'Location updated successfully' : 'Location created successfully', 'success');
          hideModal(document.getElementById('addLocationModal'));
          loadLocations(); // Reload the list
        } else {
          const error = await response.json();
          throw new Error(error.message || 'Failed to save location');
        }
      } catch (error) {
        console.error('Error saving location:', error);
        showNotification('Failed to save location: ' + error.message, 'danger');
      }
    }

    async function deleteLocation(locationId, locationName) {
      if (!confirm(`Are you sure you want to delete location "${locationName}"? This action cannot be undone.`)) {
        return;
      }

      try {
        const response = await apiCall(`/storage-locations/${locationId}`, {
          method: 'DELETE'
        });

        if (response.ok) {
          showNotification('Location deleted successfully', 'success');
          loadLocations(); // Reload the list
        } else {
          const error = await response.json();
          throw new Error(error.message || 'Failed to delete location');
        }
      } catch (error) {
        console.error('Error deleting location:', error);
        showNotification('Failed to delete location: ' + error.message, 'danger');
      }
    }

    async function exportLocations() {
      try {
        // Create CSV
        const headers = ['Name', 'Code', 'Type', 'Aisle', 'Bay', 'Level', 'Position', 'Products Stored', 'Total Quantity', 'Total Value', 'Status'];
        const rows = currentLocations.map(loc => [
          loc.name,
          loc.code || '',
          loc.type,
          loc.aisle || '',
          loc.bay || '',
          loc.level || '',
          loc.position || '',
          loc.stats.products_count,
          loc.stats.total_quantity,
          loc.stats.total_value.toFixed(2),
          loc.is_active ? 'Active' : 'Inactive'
        ]);

        const csv = [headers, ...rows].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `storage_locations_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showNotification('Locations exported successfully', 'success');
      } catch (error) {
        console.error('Export failed:', error);
        showNotification('Export failed: ' + error.message, 'danger');
      }
    }

    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }
  </script>
@endpush
