@extends('layouts.app')

@section('title', 'Inventory Dashboard - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Overview</div>
              <h1 class="page-title">Inventory Dashboard</h1>
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
            <!-- Basic Info -->
            <h5 class="mb-3"><i class="ti ti-info-circle me-2"></i>Basic Information</h5>
            <div class="row mb-3">
              <div class="col-lg-4">
                <label class="form-label">Part Number</label>
                <input type="text" class="form-control" name="part_number" id="productPartNumber" placeholder="e.g., ABC-123">
                <small class="form-hint">Optional - used for auto-SKU</small>
              </div>
              <div class="col-lg-4">
                <label class="form-label">Finish</label>
                <select class="form-select" name="finish" id="productFinish">
                  <option value="">None</option>
                </select>
              </div>
              <div class="col-lg-4">
                <label class="form-label">SKU</label>
                <input type="text" class="form-control" name="sku" id="productSku" placeholder="Auto-generated">
                <small class="form-hint text-primary" id="skuPreview"></small>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-lg-6">
                <label class="form-label required">Description</label>
                <input type="text" class="form-control" name="description" id="productDescription" placeholder="Product description" required>
              </div>
              <div class="col-lg-3">
                <label class="form-label">Categories/Systems</label>
                <select class="form-select" name="category_ids" id="productCategoryIds" multiple size="4">
                  <!-- Options loaded dynamically -->
                </select>
                <small class="form-hint">Hold Ctrl/Cmd to select multiple</small>
              </div>
              <div class="col-lg-3">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" name="location" id="productLocation" placeholder="Choose from list" list="productLocationList">
                <datalist id="productLocationList"></datalist>
                <small class="form-hint">Choose from existing storage locations</small>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Long Description</label>
              <textarea class="form-control" name="long_description" id="productLongDescription" rows="2"></textarea>
            </div>

            <hr>

            <!-- Pricing -->
            <h5 class="mb-3"><i class="ti ti-currency-dollar me-2"></i>Pricing</h5>
            <div class="row mb-3">
              <div class="col-lg-6">
                <label class="form-label required">List Price</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" class="form-control" name="unit_cost" id="productUnitCost" placeholder="0.00" step="0.01" min="0" required>
                </div>
              </div>
              <div class="col-lg-6">
                <label class="form-label">Net Price</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" class="form-control" name="net_cost" id="productNetCost" placeholder="0.00" step="0.01" min="0">
                </div>
                <small class="form-hint">Calculated from EZ Estimate or manually entered</small>
              </div>
            </div>

            <hr>

            <!-- Quantities -->
            <h5 class="mb-3"><i class="ti ti-packages me-2"></i>Inventory Quantities</h5>
            <div class="row mb-3">
              <div class="col-lg-3">
                <label class="form-label required">On Hand</label>
                <input type="number" class="form-control" name="quantity_on_hand" id="productQuantityOnHand" placeholder="0" min="0" required>
              </div>
              <div class="col-lg-3">
                <label class="form-label required">Minimum</label>
                <input type="number" class="form-control" name="minimum_quantity" id="productMinQuantity" placeholder="0" min="0" required>
              </div>
              <div class="col-lg-3">
                <label class="form-label">Maximum</label>
                <input type="number" class="form-control" name="maximum_quantity" id="productMaxQuantity" placeholder="Optional" min="0">
              </div>
              <div class="col-lg-3">
                <label class="form-label">On Order</label>
                <input type="number" class="form-control" name="on_order_qty" id="productOnOrderQty" placeholder="0" min="0" value="0">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-lg-3">
                <label class="form-label">Reorder Point</label>
                <input type="number" class="form-control" name="reorder_point" id="productReorderPoint" placeholder="Auto" min="0">
                <small class="form-hint text-success" id="reorderPreview"></small>
              </div>
              <div class="col-lg-3">
                <label class="form-label">Safety Stock</label>
                <input type="number" class="form-control" name="safety_stock" id="productSafetyStock" placeholder="0" min="0" value="0">
              </div>
              <div class="col-lg-3">
                <label class="form-label">Avg Daily Use</label>
                <input type="number" class="form-control" name="average_daily_use" id="productAvgDailyUse" placeholder="0.00" step="0.01" min="0">
              </div>
              <div class="col-lg-3">
                <label class="form-label">Lead Time (Days)</label>
                <input type="number" class="form-control" name="lead_time_days" id="productLeadTime" placeholder="0" min="0">
              </div>
            </div>

            <hr>

            <!-- UOM & Pack -->
            <h5 class="mb-3"><i class="ti ti-ruler-measure me-2"></i>Unit of Measure & Pack</h5>
            <div class="row mb-3">
              <div class="col-lg-3">
                <label class="form-label required">Stock UOM</label>
                <select class="form-select" name="unit_of_measure" id="productUOM" required>
                  <option value="">Select...</option>
                </select>
              </div>
              <div class="col-lg-3">
                <label class="form-label">Pack Size</label>
                <input type="number" class="form-control" name="pack_size" id="productPackSize" placeholder="1" min="1" value="1">
                <small class="form-hint">Units per pack</small>
              </div>
              <div class="col-lg-3">
                <label class="form-label">Purchase UOM</label>
                <select class="form-select" name="purchase_uom" id="productPurchaseUOM">
                  <option value="">Same as stock</option>
                </select>
              </div>
              <div class="col-lg-3">
                <label class="form-label">Alternate UOM</label>
                <select class="form-select" name="stock_uom" id="productStockUOM">
                  <option value="">Same as main</option>
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-lg-6">
                <label class="form-label">Min Order Qty</label>
                <input type="number" class="form-control" name="min_order_qty" id="productMinOrderQty" placeholder="1" min="1">
                <small class="form-hint">Minimum order quantity</small>
              </div>
              <div class="col-lg-6">
                <label class="form-label">Order Multiple</label>
                <input type="number" class="form-control" name="order_multiple" id="productOrderMultiple" placeholder="1" min="1">
                <small class="form-hint">Must order in multiples</small>
              </div>
            </div>

            <hr>

            <!-- Supplier -->
            <h5 class="mb-3"><i class="ti ti-truck-delivery me-2"></i>Supplier</h5>
            <div class="row mb-3">
              <div class="col-lg-6">
                <label class="form-label">Supplier</label>
                <select class="form-select" name="supplier_id" id="productSupplierId">
                  <option value="">Select supplier...</option>
                </select>
                <small class="form-hint">Product supplier</small>
              </div>
              <div class="col-lg-6">
                <label class="form-label">Supplier SKU</label>
                <input type="text" class="form-control" name="supplier_sku" id="productSupplierSku" placeholder="Supplier's code">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="productIsActive" checked>
                <span class="form-check-label">Active Product</span>
              </label>
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


  <!-- Theme Settings Offcanvas -->
  <form class="offcanvas offcanvas-start offcanvas-narrow" tabindex="-1" id="offcanvasTheme" role="dialog" aria-modal="true" aria-labelledby="offcanvasThemeLabel">
    <div class="offcanvas-header">
      <h2 class="offcanvas-title" id="offcanvasThemeLabel">Theme Settings</h2>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
      <div>
        <div class="mb-4">
          <label class="form-label">Color mode</label>
          <p class="form-hint">Choose the color mode for your app.</p>
          <label class="form-check">
            <div class="form-selectgroup-item">
              <input type="radio" name="theme" value="light" class="form-check-input" checked />
              <div class="form-check-label">Light</div>
            </div>
          </label>
          <label class="form-check">
            <div class="form-selectgroup-item">
              <input type="radio" name="theme" value="dark" class="form-check-input" />
              <div class="form-check-label">Dark</div>
            </div>
          </label>
        </div>
        <div class="mb-4">
          <label class="form-label">Color scheme</label>
          <p class="form-hint">The perfect color mode for your app.</p>
          <div class="row g-2">
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="blue" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-blue"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="azure" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-azure"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="indigo" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-indigo"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="purple" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-purple"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="pink" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-pink"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="red" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-red"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="orange" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-orange"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="yellow" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-yellow"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="lime" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-lime"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="green" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-green"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="teal" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-teal"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="cyan" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-cyan"></span>
              </label>
            </div>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label">Font family</label>
          <p class="form-hint">Choose the font family that fits your app.</p>
          <div>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-font" value="sans-serif" class="form-check-input" checked />
                <div class="form-check-label">Sans-serif</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-font" value="serif" class="form-check-input" />
                <div class="form-check-label">Serif</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-font" value="monospace" class="form-check-input" />
                <div class="form-check-label">Monospace</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-font" value="comic" class="form-check-input" />
                <div class="form-check-label">Comic</div>
              </div>
            </label>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label">Theme base</label>
          <p class="form-hint">Choose the gray shade for your app.</p>
          <div>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-base" value="slate" class="form-check-input" />
                <div class="form-check-label">Slate</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-base" value="gray" class="form-check-input" checked />
                <div class="form-check-label">Gray</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-base" value="zinc" class="form-check-input" />
                <div class="form-check-label">Zinc</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-base" value="neutral" class="form-check-input" />
                <div class="form-check-label">Neutral</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-base" value="stone" class="form-check-input" />
                <div class="form-check-label">Stone</div>
              </div>
            </label>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label">Corner Radius</label>
          <p class="form-hint">Choose the border radius factor for your app.</p>
          <div>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-radius" value="0" class="form-check-input" />
                <div class="form-check-label">0</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-radius" value="0.5" class="form-check-input" />
                <div class="form-check-label">0.5</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-radius" value="1" class="form-check-input" checked />
                <div class="form-check-label">1</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-radius" value="1.5" class="form-check-input" />
                <div class="form-check-label">1.5</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-radius" value="2" class="form-check-input" />
                <div class="form-check-label">2</div>
              </div>
            </label>
          </div>
        </div>
      </div>
      <div class="mt-auto space-y">
        <button type="button" class="btn w-100" id="resetThemeBtn">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19.95 11a8 8 0 1 0 -.5 4m.5 5v-5h-5" /></svg>
          Reset changes
        </button>
        <a href="#" class="btn btn-primary w-100" data-bs-dismiss="offcanvas">Save</a>
      </div>
    </div>
  </form>
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
        console.error('Error loading dashboard:', error);
        alert('Failed to load dashboard data');
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
          headers: {
            'Authorization': `Bearer ${authToken}`,
            'Accept': 'text/csv'
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

        // Populate UOM dropdowns
        const uomSelects = ['productUOM', 'productPurchaseUOM', 'productStockUOM'];
        uomSelects.forEach(selectId => {
          const select = document.getElementById(selectId);
          const firstOption = select.querySelector('option').outerHTML; // Keep first option
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

    // Add event listeners for auto-calculations
    document.getElementById('productPartNumber').addEventListener('input', updateSkuPreview);
    document.getElementById('productFinish').addEventListener('change', updateSkuPreview);
    document.getElementById('productAvgDailyUse').addEventListener('input', updateReorderPointPreview);
    document.getElementById('productLeadTime').addEventListener('input', updateReorderPointPreview);
    document.getElementById('productSafetyStock').addEventListener('input', updateReorderPointPreview);

    function showAddProductModal() {
      document.getElementById('addProductForm').reset();
      document.getElementById('formError').style.display = 'none';
      document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      document.getElementById('skuPreview').textContent = '';
      document.getElementById('reorderPreview').textContent = '';
      lastGeneratedSku = '';
      showModal(document.getElementById('addProductModal'));
    }

    // Add Product Form Submission
    document.getElementById('addProductForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      const formData = new FormData(e.target);
      const data = {};

      formData.forEach((value, key) => {
        if (key === 'is_active') {
          data[key] = document.getElementById('productIsActive').checked;
        } else if (value !== '') {
          data[key] = value;
        }
      });

      // Handle multiple category selection
      const categorySelect = document.getElementById('productCategoryIds');
      const selectedCategories = Array.from(categorySelect.selectedOptions).map(option => parseInt(option.value));
      if (selectedCategories.length > 0) {
        data.category_ids = selectedCategories;
        data.primary_category_id = selectedCategories[0]; // First selected is primary
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
          hideModal(document.getElementById('addProductModal'));
          showNotification('Product created successfully!', 'success');
          loadDashboard();
        } else {
          const error = await response.json();
          if (error.errors) {
            // Display field-specific errors
            Object.keys(error.errors).forEach(field => {
              const input = document.querySelector(`[name="${field}"]`);
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

    // Theme settings from Tabler demo
    var themeConfig = {
      'theme': 'light',
      'theme-base': 'gray',
      'theme-font': 'sans-serif',
      'theme-primary': 'blue',
      'theme-radius': '1'
    };
    var form = document.getElementById('offcanvasTheme');
    var resetButton = document.getElementById('resetThemeBtn');

    var checkItems = function() {
      for (var key in themeConfig) {
        var value = window.localStorage['tabler-' + key] || themeConfig[key];
        if (!!value) {
          var radios = form.querySelectorAll(`[name="${key}"]`);
          if (!!radios) {
            radios.forEach((radio) => {
              radio.checked = radio.value === value;
            });
          }
        }
      }
    };

    form.addEventListener('change', function(event) {
      var target = event.target;
      var name = target.name;
      var value = target.value;
      for (var key in themeConfig) {
        if (name === key) {
          document.documentElement.setAttribute('data-bs-' + key, value);
          window.localStorage.setItem('tabler-' + key, value);
        }
      }
    });

    resetButton.addEventListener('click', function() {
      for (var key in themeConfig) {
        var value = themeConfig[key];
        document.documentElement.removeAttribute('data-bs-' + key);
        window.localStorage.removeItem('tabler-' + key);
      }
      checkItems();
      showNotification('Theme reset to defaults', 'info');
    });

    checkItems();


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
    // Initialize dashboard if authenticated
    if (authToken) {
      loadDashboard();
      loadConfigurations(); // Load finish codes and UOMs

      // Check for product ID in URL and auto-open modal
      const urlParams = new URLSearchParams(window.location.search);
      const productId = urlParams.get('product');
      if (productId) {
        // Wait for dashboard to load, then open product
        setTimeout(() => viewProduct(parseInt(productId)), 500);
        // Clear the URL parameter
        window.history.replaceState({}, document.title, '/');
      }
    }
  </script>
@endpush
