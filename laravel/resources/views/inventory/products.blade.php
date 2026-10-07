@extends('layouts.app')

@section('title', 'All Products - ForgeDesk')

@section('content')
    <style>
      #inventoryTableContainer { max-height: 60vh; overflow-y: auto; }
      #inventoryTableContainer thead th { position: sticky; top: 0; z-index: 2; background: var(--tblr-bg-surface, #fff); }
      #inventoryTableContainer table > :not(caption) > * > * { padding-top: 0.5rem; padding-bottom: 0.5rem; }
    </style>
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Overview</div>
              <h1 class="page-title">All Products</h1>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <span class="d-none d-sm-inline">
                  <button class="btn" onclick="exportProducts()" data-permission="reports.export">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                    Export
                  </button>
                </span>
                <button class="btn btn-primary d-none d-sm-inline-block" onclick="showAddProductModal()" data-permission="inventory.create">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Add Product
                </button>
                <button class="btn btn-primary d-sm-none btn-icon" onclick="showAddProductModal()" aria-label="Add product" data-permission="inventory.create">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
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
                  <div class="subheader">SKUs Tracked</div>
                  <div class="h1 mb-3" id="statSkus">-</div>
                  <div>Active inventory items</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Units on Hand</div>
                  <div class="h1 mb-3" id="statOnHand">-</div>
                  <div>Total inventory count</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Available Units</div>
                  <div class="h1 mb-3" id="statAvailable">-</div>
                  <div>Uncommitted inventory</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Low Stock Alerts</div>
                  <div class="h1 mb-3 text-warning" id="statLowStock">-</div>
                  <div>Items below threshold</div>
                </div>
              </div>
            </div>
          </div>
          <!-- Inventory Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Inventory Snapshot</h3>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="categoryFilter" style="width: auto;">
                      <option value="">All Categories</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" placeholder="Search..." id="searchInput" style="min-width: 200px;">
                  </div>
                </div>
                <div class="card-body">
                  <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                      <a href="#" class="nav-link active" data-tab="all">All Inventory</a>
                    </li>
                    <li class="nav-item">
                      <a href="#" class="nav-link" data-tab="low_stock">Low Stock <span class="badge text-bg-warning ms-2" id="badgeLowStock">0</span></a>
                    </li>
                    <li class="nav-item">
                      <a href="#" class="nav-link" data-tab="critical">Critical <span class="badge text-bg-danger ms-2" id="badgeCritical">0</span></a>
                    </li>
                    <li class="nav-item">
                      <a href="#" class="nav-link" data-tab="boneyard">Boneyard (No Cost)</a>
                    </li>
                    <li class="nav-item">
                      <a href="#" class="nav-link" data-tab="door_shims">Door Shims</a>
                    </li>
                    <li class="nav-item">
                      <a href="#" class="nav-link" data-tab="special_order">Special Order</a>
                    </li>
                  </ul>

                  <div class="loading" id="loadingIndicator">
                    <div class="spinner-border" role="status"></div>
                    <div>Loading inventory...</div>
                  </div>

                  <div class="table-responsive" id="inventoryTableContainer" style="display: none;">
                    <table class="table table-vcenter card-table table-striped">
                      <thead>
                        <tr>
                          <th class="sortable" data-sort="sku" style="cursor: pointer;">
                            SKU <span class="sort-icon"></span>
                          </th>
                          <th class="sortable" data-sort="description" style="cursor: pointer;">
                            Description <span class="sort-icon"></span>
                          </th>
                          <th>Locations</th>
                          <th class="text-end sortable" data-sort="quantity_on_hand" style="cursor: pointer;">
                            On Hand <span class="sort-icon"></span>
                          </th>
                          <th class="text-end sortable" data-sort="quantity_committed" style="cursor: pointer;">
                            Committed <span class="sort-icon"></span>
                          </th>
                          <th class="text-end sortable" data-sort="quantity_available" style="cursor: pointer;">
                            Available <span class="sort-icon"></span>
                          </th>
                          <th class="sortable" data-sort="status" style="cursor: pointer;">
                            Status <span class="sort-icon"></span>
                          </th>
                        </tr>
                      </thead>
                      <tbody id="inventoryTableBody"></tbody>
                    </table>
                  </div>
                  <!-- Pagination -->
                  <div class="card-footer d-flex align-items-center" id="paginationContainer" style="display: none;">
                    <p class="m-0 text-muted">Showing <span id="paginationFrom">1</span> to <span id="paginationTo">50</span> of <span id="paginationTotal">0</span> items</p>
                    <ul class="pagination m-0 ms-auto" id="paginationNav">
                      <!-- Pagination will be rendered by JavaScript -->
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- View/Edit Product Modal -->

  @include('partials.product-modal')


  <!-- Add Product Modal -->
  <div class="modal modal-blur fade" id="addProductModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add New Product</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="addProductForm">
          <div class="modal-body">

            <!-- Identity — mirrors edit form header -->
            <div class="mb-3">
              <div class="row g-2 mb-2">
                <div class="col-md-3">
                  <label class="form-label small text-muted mb-1">Part Number</label>
                  <input type="text" class="form-control form-control-sm" name="part_number" id="productPartNumber" placeholder="e.g., ABC-123">
                  <small class="form-text text-primary" id="skuPreview"></small>
                  <small class="form-text text-success" id="partLookupHint" style="display:none"></small>
                </div>
                <div class="col-md-3">
                  <label class="form-label small text-muted mb-1">Finish</label>
                  <select class="form-select form-select-sm" name="finish" id="productFinish">
                    <option value="">None</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-muted mb-1">SKU</label>
                  <input type="text" class="form-control form-control-sm" name="sku" id="productSku" placeholder="Auto-generated">
                </div>
                <div class="col-md-2">
                  <label class="form-label small text-muted mb-1">Active</label>
                  <select class="form-select form-select-sm" name="is_active" id="productIsActive">
                    <option value="1" selected>Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
              </div>

              <!-- Finish variants: the same product in other finishes, created together -->
              <div class="mb-2 d-none" id="finishVariantsRow">
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <span class="small text-muted">Also create in:</span>
                  <div class="form-selectgroup" id="finishVariantOptions"></div>
                  <label class="form-check form-check-inline small mb-0 ms-auto d-none" id="copyStockWrap">
                    <input class="form-check-input" type="checkbox" id="copyStockToVariants">
                    <span class="form-check-label" title="By default variants start with no stock, since each finish is separate stock">Also copy On Hand and On Order</span>
                  </label>
                </div>
                <small class="form-text text-primary" id="variantPreview"></small>
              </div>

              <div class="mb-2">
                <label class="form-label small text-muted mb-1">Description <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="description" id="productDescription" placeholder="Product description" required>
              </div>
              <div class="row g-2">
                <div class="col-md-8">
                  <label class="form-label small text-muted mb-1">Long Description</label>
                  <textarea class="form-control form-control-sm" name="long_description" id="productLongDescription" rows="3"></textarea>
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-muted mb-1">Categories</label>
                  <select class="form-select form-select-sm" name="category_ids" id="productCategoryIds" multiple size="3">
                    <!-- Options loaded dynamically -->
                  </select>
                  <small class="form-text">Ctrl/Cmd for several. Indented = subcategory.</small>
                </div>
              </div>
            </div>

            <!-- Row 1: Pricing + Supplier + Initial Stock -->
            <div class="row g-3 mb-3">
              <div class="col-lg-3">
                <div class="card card-sm h-100">
                  <div class="card-header py-2"><strong>Pricing</strong></div>
                  <div class="card-body py-2">
                    <div class="row g-2">
                      <div class="col-6 col-lg-12">
                        <label class="form-label small text-muted mb-1">List Price <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                          <span class="input-group-text">$</span>
                          <input type="number" class="form-control" name="unit_cost" id="productUnitCost" placeholder="0.00" step="0.01" min="0" required>
                        </div>
                      </div>
                      <div class="col-6 col-lg-12">
                        <label class="form-label small text-muted mb-1">Net Price</label>
                        <div class="input-group input-group-sm">
                          <span class="input-group-text">$</span>
                          <input type="number" class="form-control" name="net_cost" id="productNetCost" placeholder="0.00" step="0.01" min="0">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-5">
                <div class="card card-sm h-100">
                  <div class="card-header py-2"><strong>Supplier</strong></div>
                  <div class="card-body py-2">
                    <div class="row g-2">
                      <div class="col-12">
                        <label class="form-label small text-muted mb-1 required">Supplier</label>
                        <select class="form-select form-select-sm" name="supplier_id" id="productSupplierId" required>
                          <option value="">Select supplier…</option>
                        </select>
                      </div>
                      <div class="col-7">
                        <label class="form-label small text-muted mb-1">Supplier SKU</label>
                        <input type="text" class="form-control form-control-sm" name="supplier_sku" id="productSupplierSku" placeholder="">
                      </div>
                      <div class="col-5">
                        <label class="form-label small text-muted mb-1">Lead Time (d)</label>
                        <input type="number" class="form-control form-control-sm" name="lead_time_days" id="productLeadTime" placeholder="0" min="0">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="card card-sm h-100">
                  <div class="card-header py-2"><strong>Initial Stock</strong></div>
                  <div class="card-body py-2">
                    <div class="row g-2">
                      <div class="col-6">
                        <label class="form-label small text-muted mb-1">On Hand <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-sm" name="quantity_on_hand" id="productQuantityOnHand" placeholder="0" min="0" required>
                      </div>
                      <div class="col-6">
                        <label class="form-label small text-muted mb-1">On Order</label>
                        <input type="number" class="form-control form-control-sm" name="on_order_qty" id="productOnOrderQty" placeholder="0" min="0" value="0">
                      </div>
                      <div class="col-12">
                        <label class="form-label small text-muted mb-1">Storage Location</label>
                        <input type="text" class="form-control form-control-sm" name="location" id="productLocation" placeholder="Choose from list" list="productLocationList">
                        <datalist id="productLocationList"></datalist>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Row 2: Stock Management + Unit of Measure -->
            <div class="row g-3 mb-3">
              <div class="col-lg-8">
                <div class="card card-sm h-100">
                  <div class="card-header py-2"><strong>Stock Management</strong></div>
                  <div class="card-body py-2">
                    <div class="row g-2">
                      <div class="col-3">
                        <label class="form-label small text-muted mb-1">Min</label>
                        <input type="number" class="form-control form-control-sm" name="minimum_quantity" id="productMinQuantity" placeholder="0" min="0" step="0.01">
                      </div>
                      <div class="col-3">
                        <label class="form-label small text-muted mb-1">Max</label>
                        <input type="number" class="form-control form-control-sm" name="maximum_quantity" id="productMaxQuantity" placeholder="—" min="0" step="0.01">
                      </div>
                      <div class="col-3">
                        <label class="form-label small text-muted mb-1">Reorder Pt.</label>
                        <input type="number" class="form-control form-control-sm" name="reorder_point" id="productReorderPoint" placeholder="Auto" min="0">
                        <small class="form-text text-success" id="reorderPreview"></small>
                      </div>
                      <div class="col-3">
                        <label class="form-label small text-muted mb-1">Safety Stock</label>
                        <input type="number" class="form-control form-control-sm" name="safety_stock" id="productSafetyStock" placeholder="0" min="0" step="0.01">
                      </div>
                      <div class="col-4">
                        <label class="form-label small text-muted mb-1">Avg Daily Use</label>
                        <input type="number" class="form-control form-control-sm" name="average_daily_use" id="productAvgDailyUse" placeholder="0.00" step="0.01" min="0">
                      </div>
                      <div class="col-2">
                        <label class="form-label small text-muted mb-1">Type</label>
                        <div class="form-check form-switch mt-1">
                          <input class="form-check-input" type="checkbox" name="nonsof" id="productNonsof">
                          <label class="form-check-label small" for="productNonsof" id="productNonsofLabel">Stock</label>
                        </div>
                      </div>
                      <div class="col-2">
                        <label class="form-label small text-muted mb-1">Door Shim</label>
                        <div class="form-check form-switch mt-1">
                          <input class="form-check-input" type="checkbox" name="cp_part" id="productCpPart">
                          <label class="form-check-label small" for="productCpPart">No</label>
                        </div>
                      </div>
                      <div class="col-2">
                        <label class="form-label small text-muted mb-1">Shared</label>
                        <div class="form-check form-switch mt-1">
                          <input class="form-check-input" type="checkbox" name="is_shared" id="productIsShared">
                          <label class="form-check-label small" for="productIsShared">No</label>
                        </div>
                      </div>
                      <div class="col-2">
                        <label class="form-label small text-muted mb-1">Special Order</label>
                        <div class="form-check form-switch mt-1">
                          <input class="form-check-input" type="checkbox" name="is_special_order" id="productIsSpecialOrder">
                          <label class="form-check-label small" for="productIsSpecialOrder">No</label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="card card-sm h-100">
                  <div class="card-header py-2"><strong>Unit of Measure</strong></div>
                  <div class="card-body py-2">
                    <div class="row g-2">
                      <div class="col-6">
                        <label class="form-label small text-muted mb-1">Stock UOM <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" name="unit_of_measure" id="productUOM" required>
                          <option value="">Select…</option>
                        </select>
                      </div>
                      <div class="col-6">
                        <label class="form-label small text-muted mb-1">Pack Size</label>
                        <input type="number" class="form-control form-control-sm" name="pack_size" id="productPackSize" placeholder="1" min="1" value="1">
                      </div>
                      <div class="col-6">
                        <label class="form-label small text-muted mb-1">Min Order Qty</label>
                        <input type="number" class="form-control form-control-sm" name="min_order_qty" id="productMinOrderQty" placeholder="1" min="1">
                      </div>
                      <div class="col-6">
                        <label class="form-label small text-muted mb-1">Order Multiple</label>
                        <input type="number" class="form-control form-control-sm" name="order_multiple" id="productOrderMultiple" placeholder="1" min="1">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div id="formError" class="alert alert-danger" style="display: none;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary ms-auto" id="saveProductBtn">
              <i class="ti ti-device-floppy icon"></i>
              Save Product
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
  <script>
    // Dashboard-specific state
    let currentTab = 'all';
    let currentCategoryFilter = '';
    let currentPage = 1;
    let paginationData = null;
    let currentSortBy = 'sku';
    let currentSortDir = 'asc';
    let currentSearch = '';
    let searchDebounceTimer = null;

    async function loadDashboard(page = 1) {
      try {
        currentPage = page;
        document.getElementById('loadingIndicator').style.display = 'block';
        document.getElementById('inventoryTableContainer').style.display = 'none';
        document.getElementById('paginationContainer').style.display = 'none';

        // Build URL with all filters
        let url = `/dashboard?page=${page}&sort_by=${currentSortBy}&sort_dir=${currentSortDir}`;
        if (currentCategoryFilter) {
          url += `&category_id=${currentCategoryFilter}`;
        }
        if (currentSearch) {
          url += `&search=${encodeURIComponent(currentSearch)}`;
        }
        const response = await apiCall(url);
        if (!response.ok) {
          if (response.status === 401) { showLogin(); return; }
          throw new Error(`Server error: ${response.status}`);
        }
        const data = await response.json();

        document.getElementById('statSkus').textContent = data.stats.skus_tracked.toLocaleString();
        document.getElementById('statOnHand').textContent = data.stats.units_on_hand.toLocaleString();
        document.getElementById('statAvailable').textContent = data.stats.units_available.toLocaleString();
        document.getElementById('statLowStock').textContent = data.stats.low_stock_alerts.toLocaleString();
        document.getElementById('badgeLowStock').textContent = data.stats.low_stock_alerts;
        document.getElementById('badgeCritical').textContent = data.stats.critical_count;

        renderInventoryTable(data.inventory.data);
        renderPagination(data.inventory);
        updateSortIcons();

        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('inventoryTableContainer').style.display = 'block';
      } catch (error) {
        if (!currentUser) return; // session expired — login page already shown
        console.error('Error loading dashboard:', error);
        document.getElementById('loadingIndicator').style.display = 'none';
      }
    }

    // Sort by column
    function sortByColumn(column) {
      if (currentSortBy === column) {
        // Toggle direction if same column
        currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
      } else {
        currentSortBy = column;
        currentSortDir = 'asc';
      }
      currentPage = 1; // Reset to first page
      if (currentTab === 'all') {
        loadDashboard(1);
      } else {
        loadByStatus(currentTab, 1);
      }
    }

    // Update sort icons in table headers
    function updateSortIcons() {
      document.querySelectorAll('.sortable').forEach(th => {
        const icon = th.querySelector('.sort-icon');
        const column = th.dataset.sort;
        if (column === currentSortBy) {
          icon.innerHTML = currentSortDir === 'asc'
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1"><path d="M12 5l0 14"/><path d="M18 11l-6 -6"/><path d="M6 11l6 -6"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1"><path d="M12 5l0 14"/><path d="M18 13l-6 6"/><path d="M6 13l6 6"/></svg>';
        } else {
          icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1 text-muted"><path d="M8 9l4 -4l4 4"/><path d="M16 15l-4 4l-4 -4"/></svg>';
        }
      });
    }

    // Search handler with debounce
    function handleSearch(e) {
      const searchTerm = e.target.value.trim();
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => {
        currentSearch = searchTerm;
        currentPage = 1; // Reset to first page
        if (currentTab === 'all') {
          loadDashboard(1);
        } else {
          loadByStatus(currentTab, 1);
        }
      }, 300); // 300ms debounce
    }

    function renderInventoryTable(products) {
      const tbody = document.getElementById('inventoryTableBody');
      tbody.innerHTML = '';

      if (products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No inventory items found</td></tr>';
        return;
      }

      tbody.innerHTML = products.map(product => {
        const statusBadge = getStatusBadge(product.status, product.on_order_qty);
        const locationCount = product.inventory_locations?.length || 0;
        const locationsDisplay = locationCount > 0
          ? `<span class="badge text-bg-azure">${locationCount} <i class="ti ti-map-pin"></i></span>`
          : '<span class="text-muted">-</span>';

        // Use pack-aware display functions
        const onHandDisplay = formatOnHandDisplay(product);
        const committedDisplay = formatCommittedDisplay(product, true);
        const availableDisplay = formatAvailableDisplay(product);

        return `
          <tr onclick="viewProduct(${product.id})" style="cursor: pointer;">
            <td><span class="text-muted">${product.sku}</span></td>
            <td>${product.description}</td>
            <td>${locationsDisplay}</td>
            <td class="text-end">${onHandDisplay}</td>
            <td class="text-end">${committedDisplay}</td>
            <td class="text-end">${availableDisplay}</td>
            <td>${statusBadge}</td>
          </tr>
        `;
      }).join('');
    }

    function renderPagination(pagination) {
      paginationData = pagination;
      const container = document.getElementById('paginationContainer');
      const nav = document.getElementById('paginationNav');

      if (!pagination || pagination.total === 0) {
        container.style.display = 'none';
        return;
      }

      // Update showing text
      document.getElementById('paginationFrom').textContent = pagination.from || 0;
      document.getElementById('paginationTo').textContent = pagination.to || 0;
      document.getElementById('paginationTotal').textContent = pagination.total.toLocaleString();

      // Build pagination nav
      const currentPage = pagination.current_page;
      const lastPage = pagination.last_page;

      let html = '';

      // Previous button
      html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
          <a class="page-link" href="#" onclick="goToPage(${currentPage - 1}); return false;" tabindex="-1" ${currentPage === 1 ? 'aria-disabled="true"' : ''}>
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
              <path d="M15 6l-6 6l6 6"></path>
            </svg>
          </a>
        </li>
      `;

      // Page numbers - show max 7 pages with ellipsis
      const pageNumbers = getPageNumbers(currentPage, lastPage, 7);
      pageNumbers.forEach(pageNum => {
        if (pageNum === '...') {
          html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        } else {
          html += `
            <li class="page-item ${pageNum === currentPage ? 'active' : ''}">
              <a class="page-link" href="#" onclick="goToPage(${pageNum}); return false;">${pageNum}</a>
            </li>
          `;
        }
      });

      // Next button
      html += `
        <li class="page-item ${currentPage === lastPage ? 'disabled' : ''}">
          <a class="page-link" href="#" onclick="goToPage(${currentPage + 1}); return false;" ${currentPage === lastPage ? 'aria-disabled="true"' : ''}>
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
              <path d="M9 6l6 6l-6 6"></path>
            </svg>
          </a>
        </li>
      `;

      nav.innerHTML = html;
      container.style.display = pagination.last_page > 1 ? 'flex' : 'none';
    }

    function getPageNumbers(current, last, maxVisible) {
      if (last <= maxVisible) {
        return Array.from({length: last}, (_, i) => i + 1);
      }

      const pages = [];
      const half = Math.floor(maxVisible / 2);

      if (current <= half + 1) {
        // Near start
        for (let i = 1; i <= maxVisible - 2; i++) pages.push(i);
        pages.push('...');
        pages.push(last);
      } else if (current >= last - half) {
        // Near end
        pages.push(1);
        pages.push('...');
        for (let i = last - maxVisible + 3; i <= last; i++) pages.push(i);
      } else {
        // Middle
        pages.push(1);
        pages.push('...');
        for (let i = current - 1; i <= current + 1; i++) pages.push(i);
        pages.push('...');
        pages.push(last);
      }

      return pages;
    }

    function goToPage(page) {
      if (!paginationData || page < 1 || page > paginationData.last_page) return;

      if (currentTab === 'all') {
        loadDashboard(page);
      } else {
        loadByStatus(currentTab, page);
      }
    }

    /**
     * Format committed quantity display
     * Shows packs needed if product has pack_size > 1, with eaches in tooltip
     * Example: "2 packs" with title="137 eaches total"
     */
    document.querySelectorAll('.nav-link[data-tab]').forEach(link => {
      link.addEventListener('click', async (e) => {
        e.preventDefault();
        document.querySelectorAll('.nav-link[data-tab]').forEach(l => l.classList.remove('active'));
        e.target.classList.add('active');

        currentTab = e.target.dataset.tab;
        currentPage = 1; // Reset to first page on tab change
        if (currentTab === 'all') {
          loadDashboard(1);
        } else {
          await loadByStatus(currentTab, 1);
        }
      });
    });

    async function loadByStatus(status, page = 1) {
      try {
        currentPage = page;
        document.getElementById('loadingIndicator').style.display = 'block';
        document.getElementById('inventoryTableContainer').style.display = 'none';
        document.getElementById('paginationContainer').style.display = 'none';

        let url = `/dashboard/inventory/${status}?page=${page}&sort_by=${currentSortBy}&sort_dir=${currentSortDir}`;
        if (currentCategoryFilter) {
          url += `&category_id=${currentCategoryFilter}`;
        }
        if (currentSearch) {
          url += `&search=${encodeURIComponent(currentSearch)}`;
        }
        const response = await apiCall(url);
        if (!response.ok) {
          throw new Error(`Server error: ${response.status}`);
        }
        const data = await response.json();
        renderInventoryTable(data.data);
        renderPagination(data);
        updateSortIcons();

        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('inventoryTableContainer').style.display = 'block';
      } catch (error) {
        console.error('Error loading filtered inventory:', error);
        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('inventoryTableContainer').style.display = 'block';
        const tbody = document.getElementById('inventoryTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load inventory. Please try again.</td></tr>';
      }
    }

    async function exportProducts() {
      try {
        const response = await fetch(`${API_BASE}/export/products`, {
          method: 'GET',
          credentials: 'include',
          headers: {
            'Accept': 'text/csv',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          }
        });

        if (!response.ok) {
          throw new Error('Export failed');
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `products_export_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showNotification('Products exported successfully', 'success');
      } catch (error) {
        console.error('Export failed:', error);
        showNotification('Export failed: ' + error.message, 'danger');
      }
    }

    // Refresh the page's inventory table after modal operations
    function refreshTable() {
      if (currentTab === 'all') {
        loadDashboard(currentPage);
      } else {
        loadByStatus(currentTab, currentPage);
      }
    }

    async function loadConfigurations() {
      try {
        // Load finish codes
        const finishResponse = await apiCall('/finish-codes');
        const finishData = await finishResponse.json();
        finishCodes = Array.isArray(finishData) ? finishData : [];

        // Load UOMs
        const uomResponse = await apiCall('/unit-of-measures');
        const uomData = await uomResponse.json();
        unitOfMeasures = Array.isArray(uomData) ? uomData : [];

        // Load categories as tree (preserves hierarchy for the edit form)
        const categoriesResponse = await apiCall('/categories-tree');
        const categoriesData = await categoriesResponse.json();
        categories = Array.isArray(categoriesData) ? categoriesData : [];

        // Load suppliers
        const suppliersResponse = await apiCall('/suppliers?per_page=all');
        const suppliersData = await suppliersResponse.json();
        suppliers = Array.isArray(suppliersData) ? suppliersData : [];

        // Load storage locations
        const locationsResponse = await apiCall('/storage-locations-names');
        const locationsData = await locationsResponse.json();
        const storageLocationNames = Array.isArray(locationsData) ? locationsData : [];

        // Populate storage locations datalist for add product form
        const locationDatalist = document.getElementById('productLocationList');
        if (locationDatalist) {
          locationDatalist.innerHTML = '';
          storageLocationNames.forEach(locationName => {
            const option = document.createElement('option');
            option.value = locationName;
            locationDatalist.appendChild(option);
          });
        }

        // Populate finish dropdown
        const finishSelect = document.getElementById('productFinish');
        finishSelect.innerHTML = '<option value="">None</option>';
        finishCodes.forEach(finish => {
          const option = document.createElement('option');
          option.value = finish.code;
          option.textContent = `${finish.code} - ${finish.name}`;
          finishSelect.appendChild(option);
        });
        renderFinishVariantOptions();

        // Populate UOM dropdowns
        ['productUOM'].forEach(selectId => {
          const select = document.getElementById(selectId);
          if (!select) return;
          const firstOption = select.querySelector('option').outerHTML;
          select.innerHTML = firstOption;
          unitOfMeasures.forEach(uom => {
            const option = document.createElement('option');
            option.value = uom.code;
            option.textContent = `${uom.code} - ${uom.name}`;
            select.appendChild(option);
          });
        });

        // Populate category dropdown
        populateCategoryDropdown();

        // Populate supplier dropdown
        populateSupplierDropdown();

      } catch (error) {
        console.error('Error loading configurations:', error);
      }
    }

    function populateCategoryDropdown() {
      const categorySelect = document.getElementById('productCategoryIds');
      if (!categorySelect) return;
      categorySelect.innerHTML = '';

      // Sort categories by name
      const sortedCategories = [...categories].sort((a, b) => a.name.localeCompare(b.name));

      sortedCategories.forEach(category => {
        const option = document.createElement('option');
        option.value = category.id;

        // Show parent category if exists
        if (category.parent) {
          option.textContent = `${category.parent.name} > ${category.name}`;
        } else {
          option.textContent = category.name;
        }

        categorySelect.appendChild(option);
      });

      // Also populate the category filter dropdown
      populateCategoryFilterDropdown();
    }

    function populateCategoryFilterDropdown() {
      const categoryFilter = document.getElementById('categoryFilter');
      if (!categoryFilter) return;

      // Keep the "All Categories" option
      categoryFilter.innerHTML = '<option value="">All Categories</option>';

      // Sort categories by name
      const sortedCategories = [...categories].sort((a, b) => a.name.localeCompare(b.name));

      sortedCategories.forEach(category => {
        const option = document.createElement('option');
        option.value = category.id;

        // Show parent category if exists
        if (category.parent) {
          option.textContent = `${category.parent.name} > ${category.name}`;
        } else {
          option.textContent = category.name;
        }

        categoryFilter.appendChild(option);
      });
    }

    function populateSupplierDropdown() {
      const supplierSelect = document.getElementById('productSupplierId');
      supplierSelect.innerHTML = '<option value="">Select supplier...</option>';

      // Sort suppliers by name
      const sortedSuppliers = [...suppliers].sort((a, b) => a.name.localeCompare(b.name));

      sortedSuppliers.forEach(supplier => {
        const option = document.createElement('option');
        option.value = supplier.id;
        option.textContent = supplier.name;
        if (supplier.code) {
          option.textContent += ` (${supplier.code})`;
        }
        supplierSelect.appendChild(option);
      });
    }

    // Auto-generate SKU preview
    let lastGeneratedSku = '';

    function updateSkuPreview() {
      const partNumber = document.getElementById('productPartNumber').value.trim().toUpperCase();
      const finish = document.getElementById('productFinish').value;
      const skuField = document.getElementById('productSku');
      const skuPreview = document.getElementById('skuPreview');

      if (partNumber) {
        const generatedSku = finish ? `${partNumber}-${finish}` : partNumber;
        skuPreview.textContent = `Will generate: ${generatedSku}`;
        skuPreview.classList.add('text-primary');

        // Auto-fill if empty or if field still matches the last auto-generated value
        if (!skuField.value || skuField.value === lastGeneratedSku) {
          skuField.value = generatedSku;
          lastGeneratedSku = generatedSku;
        }
      } else {
        skuPreview.textContent = '';
        skuPreview.classList.remove('text-primary');
        if (skuField.value === lastGeneratedSku) {
          skuField.value = '';
          lastGeneratedSku = '';
        }
      }
    }

    // ---- Finish variants: create the same product in other finishes at the same time ----
    // Mirrors ProductController::finishVariantData(): an auto-generated SKU is rebuilt for the new
    // finish; a custom SKU has a trailing "-<finish>" swapped, or "-<finish>" appended.
    function variantSkuFor(finish) {
      const sku = document.getElementById('productSku').value.trim().toUpperCase();
      const partNumber = document.getElementById('productPartNumber').value.trim().toUpperCase();
      const primary = document.getElementById('productFinish').value;
      if (!sku) return '';
      if (partNumber && sku === (primary ? `${partNumber}-${primary}` : partNumber)) return `${partNumber}-${finish}`;
      if (primary && sku.endsWith(`-${primary.toUpperCase()}`)) return sku.slice(0, sku.length - primary.length) + finish;
      return `${sku}-${finish}`;
    }

    function selectedFinishVariants() {
      return Array.from(document.querySelectorAll('#finishVariantOptions input:checked')).map(i => i.value);
    }

    function updateVariantPreview() {
      const chosen = selectedFinishVariants();
      const preview = document.getElementById('variantPreview');
      const skus = chosen.map(variantSkuFor).filter(Boolean);
      preview.textContent = chosen.length
        ? (skus.length ? `Will also create: ${skus.join(', ')} (same details; no stock unless you tick the box)` : 'Enter a part number or SKU to create variants.')
        : '';
      document.getElementById('copyStockWrap').classList.toggle('d-none', !chosen.length);
    }

    function renderFinishVariantOptions() {
      const primary = document.getElementById('productFinish').value;
      const wrap = document.getElementById('finishVariantOptions');
      const kept = new Set(selectedFinishVariants());
      const options = finishCodes.filter(f => f.code !== primary);
      wrap.innerHTML = options.map(f => `
        <label class="form-selectgroup-item">
          <input type="checkbox" value="${htmlEscape(f.code)}" class="form-selectgroup-input" ${kept.has(f.code) ? 'checked' : ''}>
          <span class="form-selectgroup-label py-1 px-2" title="${htmlEscape(f.name)}">${htmlEscape(f.code)}</span>
        </label>`).join('');
      document.getElementById('finishVariantsRow').classList.toggle('d-none', !options.length);
      updateVariantPreview();
    }

    // Calculate reorder point preview
    function updateReorderPointPreview() {
      const avgDailyUse = parseFloat(document.getElementById('productAvgDailyUse').value) || 0;
      const leadTime = parseInt(document.getElementById('productLeadTime').value) || 0;
      const safetyStock = parseInt(document.getElementById('productSafetyStock').value) || 0;
      const reorderField = document.getElementById('productReorderPoint');
      const reorderPreview = document.getElementById('reorderPreview');

      if (avgDailyUse && leadTime) {
        const calculatedReorder = Math.round((avgDailyUse * leadTime) + safetyStock);
        reorderPreview.textContent = `Calculated: ${calculatedReorder}`;
        reorderPreview.classList.add('text-success');

        // Auto-fill if empty
        if (!reorderField.value) {
          reorderField.value = calculatedReorder;
        }
      } else {
        reorderPreview.textContent = '';
        reorderPreview.classList.remove('text-success');
      }
    }

    // Autofill description/list price/net price from the EZ Estimate template
    // (SL Formulas / P Formulas sheets) when the part number matches, so there's
    // one source of pricing truth instead of a separate catalog going stale.
    async function tryAutofillFromEzEstimate() {
      const partNumber = document.getElementById('productPartNumber').value.trim();
      const hint = document.getElementById('partLookupHint');
      if (!partNumber) { hint.style.display = 'none'; return; }

      try {
        const res = await apiCall(`/ez-estimate/part-lookup?part_number=${encodeURIComponent(partNumber)}`);
        if (!res.ok) return;
        const data = await res.json();
        if (!data.found) { hint.style.display = 'none'; return; }

        const descField = document.getElementById('productDescription');
        const listField = document.getElementById('productUnitCost');
        const netField  = document.getElementById('productNetCost');

        const filled = [];
        if (data.description && !descField.value) { descField.value = data.description; filled.push('description'); }
        if (data.list_price != null && !listField.value) { listField.value = data.list_price; filled.push('list price'); }
        if (data.net_price != null && !netField.value) { netField.value = data.net_price; filled.push('net price'); }

        hint.style.display = '';
        hint.textContent = filled.length
          ? `Autofilled ${filled.join(', ')} from EZ Estimate catalog.`
          : 'Matched EZ Estimate catalog (fields already filled).';
      } catch (e) {
        console.error('EZ Estimate lookup failed', e);
      }
    }

    // Add event listeners for auto-calculations
    document.getElementById('productPartNumber').addEventListener('input', updateSkuPreview);
    document.getElementById('productPartNumber').addEventListener('blur', tryAutofillFromEzEstimate);
    document.getElementById('productFinish').addEventListener('change', updateSkuPreview);
    document.getElementById('productFinish').addEventListener('change', renderFinishVariantOptions);
    document.getElementById('finishVariantOptions').addEventListener('change', updateVariantPreview);
    ['productPartNumber', 'productSku'].forEach(id => document.getElementById(id).addEventListener('input', updateVariantPreview));
    document.getElementById('productAvgDailyUse').addEventListener('input', updateReorderPointPreview);
    document.getElementById('productLeadTime').addEventListener('input', updateReorderPointPreview);
    document.getElementById('productSafetyStock').addEventListener('input', updateReorderPointPreview);

    function showAddProductModal() {
      document.getElementById('addProductForm').reset();
      document.getElementById('formError').style.display = 'none';
      document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      document.getElementById('skuPreview').textContent = '';
      document.getElementById('reorderPreview').textContent = '';
      const partLookupHint = document.getElementById('partLookupHint');
      partLookupHint.textContent = '';
      partLookupHint.style.display = 'none';
      lastGeneratedSku = '';
      document.getElementById('finishVariantOptions').innerHTML = ''; // start with no variants ticked
      document.getElementById('copyStockToVariants').checked = false;
      renderFinishVariantOptions();

      // Wire toggle live-labels
      const nonsofCb = document.getElementById('productNonsof');
      const nonsofLabel = document.getElementById('productNonsofLabel');
      if (nonsofCb && nonsofLabel) {
        nonsofCb.onchange = () => { nonsofLabel.textContent = nonsofCb.checked ? 'Boneyard' : 'Stock'; };
      }
      const cpPartCb   = document.getElementById('productCpPart');
      if (cpPartCb)   cpPartCb.onchange   = () => { cpPartCb.nextElementSibling.textContent   = cpPartCb.checked   ? 'Yes' : 'No'; };
      const isSharedCb = document.getElementById('productIsShared');
      if (isSharedCb) isSharedCb.onchange = () => { isSharedCb.nextElementSibling.textContent = isSharedCb.checked ? 'Yes' : 'No'; };
      const isSpecialOrderCb = document.getElementById('productIsSpecialOrder');
      if (isSpecialOrderCb) isSpecialOrderCb.onchange = () => { isSpecialOrderCb.nextElementSibling.textContent = isSpecialOrderCb.checked ? 'Yes' : 'No'; };

      showModal(document.getElementById('addProductModal'));
    }

    // Add Product Form Submission
    document.getElementById('addProductForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      const formData = new FormData(e.target);
      const data = {};

      formData.forEach((value, key) => {
        if (key === 'is_active') {
          data[key] = value === '1';
        } else if (value !== '') {
          data[key] = value;
        }
      });

      // Checkboxes — unchecked values don't appear in FormData
      data.nonsof    = !!document.getElementById('productNonsof')?.checked;
      data.cp_part   = !!document.getElementById('productCpPart')?.checked;
      data.is_shared = !!document.getElementById('productIsShared')?.checked;
      data.is_special_order = !!document.getElementById('productIsSpecialOrder')?.checked;

      // Finish variants (checkboxes have no name, so they are not in FormData)
      const variantFinishes = selectedFinishVariants();
      if (variantFinishes.length) {
        data.finish_variants = variantFinishes;
        data.copy_stock_to_variants = !!document.getElementById('copyStockToVariants').checked;
      }

      // Handle multiple category selection
      const categorySelect = document.getElementById('productCategoryIds');
      const selectedCategories = Array.from(categorySelect.selectedOptions).map(option => parseInt(option.value));
      if (selectedCategories.length > 0) {
        data.category_ids = selectedCategories;
        data.primary_category_id = selectedCategories[0];
      }
      // Remove old single category_id if present
      delete data.category_id;

      // Clear previous errors
      document.getElementById('formError').style.display = 'none';
      document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

      try {
        const saveBtn = document.getElementById('saveProductBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        const response = await apiCall('/products', {
          method: 'POST',
          body: JSON.stringify(data)
        });

        if (response.ok) {
          const created = await response.json().catch(() => ({}));
          hideModal(document.getElementById('addProductModal'));
          showNotification(
            created.variants && created.variants.length
              ? `Created ${[created.sku, ...created.variants.map(v => v.sku)].join(', ')}`
              : 'Product created successfully!',
            'success'
          );
          loadDashboard();
        } else {
          const error = await response.json();
          if (error.errors) {
            // Display field-specific errors; ones with no matching input (e.g. variants) go in the banner
            const unmatched = [];
            Object.keys(error.errors).forEach(field => {
              const input = document.querySelector(`#addProductForm [name="${field}"]`);
              if (!input) unmatched.push(error.errors[field][0]);
              if (input) {
                input.classList.add('is-invalid');
                const feedback = input.parentElement.querySelector('.invalid-feedback') ||
                                input.closest('.mb-3').querySelector('.invalid-feedback');
                if (feedback) {
                  feedback.textContent = error.errors[field][0];
                  feedback.style.display = 'block';
                }
              }
            });
            if (unmatched.length) {
              document.getElementById('formError').textContent = unmatched.join(' ');
              document.getElementById('formError').style.display = 'block';
            }
          } else {
            document.getElementById('formError').textContent = error.message || 'Failed to create product';
            document.getElementById('formError').style.display = 'block';
          }
        }
      } catch (error) {
        document.getElementById('formError').textContent = 'Error: ' + error.message;
        document.getElementById('formError').style.display = 'block';
      } finally {
        const saveBtn = document.getElementById('saveProductBtn');
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="ti ti-device-floppy icon"></i> Save Product';
      }
    });


    // Category filter event listener
    document.getElementById('categoryFilter').addEventListener('change', function(e) {
      currentCategoryFilter = e.target.value;
      currentPage = 1; // Reset to first page
      if (currentTab === 'all') {
        loadDashboard(1);
      } else {
        loadByStatus(currentTab, 1);
      }
    });

    // Search input event listener
    document.getElementById('searchInput').addEventListener('input', handleSearch);

    // Sort column click handlers (main inventory table)
    document.querySelectorAll('.sortable').forEach(th => {
      th.addEventListener('click', function() {
        const column = this.dataset.sort;
        if (column) {
          sortByColumn(column);
        }
      });
    });

    // Note: .sortable-activity handlers are registered by the product-modal partial
    function htmlEscape(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
    // Initialize dashboard after session is validated
    window.sessionReady.then(() => {
      if (!currentUser) return;
      loadDashboard();
      loadConfigurations(); // Load finish codes and UOMs

      // Check for product ID in URL and auto-open modal
      const urlParams = new URLSearchParams(window.location.search);
      const productId = urlParams.get('product');
      if (productId) {
        // Wait for dashboard to load, then open product
        setTimeout(() => viewProduct(parseInt(productId)), 500);
        // Clear the URL parameter
        window.history.replaceState({}, document.title, '/inventory/products');
      }
    });
  </script>
@endpush
