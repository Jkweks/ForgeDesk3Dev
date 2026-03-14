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
                          <th class="w-1"></th>
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
  <div class="modal modal-blur fade" id="viewProductModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewProductModalTitle">Product Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Tabs -->
          <ul class="nav nav-tabs mb-3" id="productTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                <i class="ti ti-info-circle me-1"></i>Details
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="locations-tab" data-bs-toggle="tab" data-bs-target="#locations" type="button" role="tab">
                <i class="ti ti-map-pin me-1"></i>Locations <span class="badge text-bg-primary ms-1" id="locationsCount">0</span>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="reservations-tab" data-bs-toggle="tab" data-bs-target="#reservations" type="button" role="tab">
                <i class="ti ti-clipboard-check me-1"></i>Reservations <span class="badge text-bg-warning ms-1" id="reservationsCount">0</span>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                <i class="ti ti-history me-1"></i>Activity
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="configurator-tab" data-bs-toggle="tab" data-bs-target="#configurator" type="button" role="tab">
                <i class="ti ti-box-model me-1"></i>Configurator <span class="badge text-bg-info ms-1" id="bomCount">0</span>
              </button>
            </li>
          </ul>

          <!-- Tab Content -->
          <div class="tab-content" id="productTabContent">
            <!-- Details Tab -->
            <div class="tab-pane fade show active" id="details" role="tabpanel">
              <div class="mb-3">
                <div class="d-flex justify-content-end">
                  <button class="btn btn-primary" id="editProductBtn" onclick="toggleEditMode()" data-permission="inventory.edit">
                    <i class="ti ti-edit me-1"></i>Edit Product
                  </button>
                  <div id="editProductActions" style="display: none;">
                    <button class="btn btn-primary me-2" onclick="saveProductChanges()" data-permission="inventory.edit">
                      <i class="ti ti-check me-1"></i>Save Changes
                    </button>
                    <button class="btn btn-link" onclick="cancelEditMode()">Cancel</button>
                  </div>
                </div>
              </div>
              <div id="productDetailsView"></div>
              <div id="productEditForm" style="display: none;"></div>
            </div>

            <!-- Locations Tab -->
            <div class="tab-pane fade" id="locations" role="tabpanel">
              <div class="mb-3">
                <div class="row g-2 align-items-center mb-3">
                  <div class="col">
                    <h3 class="mb-0">Inventory Locations</h3>
                    <p class="text-muted mb-0">Manage stock distribution across multiple locations</p>
                  </div>
                  <div class="col-auto">
                    <button class="btn btn-warning ms-2" onclick="showIssueToJobForm()" data-permission="inventory.adjust">
                      <i class="ti ti-package-export me-1"></i>Issue to Job
                    </button>
                    <button class="btn btn-primary" onclick="showAddLocationForm()" data-permission="inventory.edit">
                      <i class="ti ti-plus me-1"></i>Add Location
                    </button>
                    <button class="btn btn-outline-primary ms-2" onclick="showTransferForm()" data-permission="inventory.adjust">
                      <i class="ti ti-arrows-transfer-down me-1"></i>Transfer
                    </button>
                  </div>
                </div>

                <!-- Location Statistics -->
                <div class="row row-cards mb-3" id="locationStatsCards">
                  <div class="col-md-3">
                    <div class="card card-sm">
                      <div class="card-body">
                        <div class="subheader">Total Locations</div>
                        <div class="h2 mb-0" id="statTotalLocations">-</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card card-sm">
                      <div class="card-body">
                        <div class="subheader">Total Quantity</div>
                        <div class="h2 mb-0" id="statTotalQuantity">-</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card card-sm">
                      <div class="card-body">
                        <div class="subheader">Committed</div>
                        <div class="h2 mb-0" id="statTotalCommitted">-</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card card-sm">
                      <div class="card-body">
                        <div class="subheader">Available</div>
                        <div class="h2 mb-0 text-success" id="statTotalAvailable">-</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Add/Edit Location Form (Hidden by default) -->
                <div class="card mb-3" id="locationFormCard" style="display: none;">
                  <div class="card-header">
                    <h4 class="card-title mb-0" id="locationFormTitle">Add Location</h4>
                  </div>
                  <div class="card-body">
                    <form id="locationForm">
                      <input type="hidden" id="locationId" name="location_id">
                      <input type="hidden" id="storageLocationId" name="storage_location_id">
                      <div class="row mb-3">
                        <div class="col-md-6">
                          <label class="form-label required">Storage Location</label>
                          <select class="form-select" id="storageLocationSelect" onchange="handleStorageLocationSelect()">
                            <option value="">Select a storage location...</option>
                            <option value="custom">Custom Location (enter manually)</option>
                          </select>
                          <input type="text" class="form-control mt-2" id="locationName" name="location" placeholder="e.g., Warehouse A, Bin 23" style="display: none;">
                          <small class="form-hint">Select hierarchical location or enter custom</small>
                        </div>
                        <div class="col-md-3">
                          <label class="form-label required">Quantity</label>
                          <input type="number" class="form-control" id="locationQuantity" name="quantity" min="0" required>
                        </div>
                        <div class="col-md-3">
                          <label class="form-label">Committed</label>
                          <input type="number" class="form-control" id="locationCommitted" name="quantity_committed" min="0" value="0">
                        </div>
                      </div>
                      <div class="row mb-3">
                        <div class="col-md-6">
                          <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="locationPrimary" name="is_primary">
                            <span class="form-check-label">Set as Primary Location</span>
                          </label>
                        </div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="locationNotes" name="notes" rows="2" placeholder="Optional notes about this location"></textarea>
                      </div>
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="saveLocationBtn">
                          <i class="ti ti-check me-1"></i>Save Location
                        </button>
                        <button type="button" class="btn btn-link" onclick="hideLocationForm()">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>

                <!-- Locations List -->
                <div class="card">
                  <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                      <thead>
                        <tr>
                          <th>Location</th>
                          <th class="text-end">Quantity</th>
                          <th class="text-end">Committed</th>
                          <th class="text-end">Available</th>
                          <th>Distribution</th>
                          <th>Status</th>
                          <th class="w-1"></th>
                        </tr>
                      </thead>
                      <tbody id="locationsTableBody">
                        <tr>
                          <td colspan="7" class="text-center text-muted py-5">
                            <i class="ti ti-map-pin" style="font-size: 2rem;"></i>
                            <p class="mb-0">No locations added yet</p>
                            <button class="btn btn-sm btn-primary mt-2" onclick="showAddLocationForm()">Add First Location</button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Transfer Modal -->
                <div class="card mt-3" id="transferCard" style="display: none;">
                  <div class="card-header">
                    <h4 class="card-title mb-0">Transfer Inventory</h4>
                  </div>
                  <div class="card-body">
                    <form id="transferForm">
                      <div class="row mb-3">
                        <div class="col-md-5">
                          <label class="form-label required">From Location</label>
                          <select class="form-select" id="transferFrom" name="from_location_id" required>
                            <option value="">Select source...</option>
                          </select>
                          <small class="text-muted" id="transferFromAvailable"></small>
                        </div>
                        <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                          <i class="ti ti-arrow-right" style="font-size: 2rem;"></i>
                        </div>
                        <div class="col-md-5">
                          <label class="form-label required">To Location</label>
                          <select class="form-select" id="transferTo" name="to_location_id" required>
                            <option value="">Select destination...</option>
                          </select>
                        </div>
                      </div>
                      <div class="row mb-3">
                        <div class="col-md-4">
                          <label class="form-label required">Quantity to Transfer</label>
                          <input type="number" class="form-control" id="transferQuantity" name="quantity" min="1" required>
                        </div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" id="transferNotes" name="notes" placeholder="Optional transfer notes">
                      </div>
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                          <i class="ti ti-arrows-transfer-down me-1"></i>Transfer
                        </button>
                        <button type="button" class="btn btn-link" onclick="hideTransferForm()">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <!-- Reservations Tab -->
            <div class="tab-pane fade" id="reservations" role="tabpanel">
              <div class="mb-3">
                <div class="row g-2 align-items-center mb-3">
                  <div class="col">
                    <h3 class="mb-0">Job Reservations</h3>
                    <p class="text-muted mb-0">Reserve inventory for jobs and track commitments</p>
                  </div>
                  <div class="col-auto">
                    <button class="btn btn-primary" onclick="showAddReservationForm()" data-permission="orders.create">
                      <i class="ti ti-plus me-1"></i>Reserve Inventory
                    </button>
                  </div>
                </div>

                <!-- Reservation Statistics -->
                <div class="row row-cards mb-3">
                  <div class="col-md-2">
                    <div class="card card-sm">
                      <div class="card-body">
                        <div class="subheader">Active</div>
                        <div class="h2 mb-0" id="statActiveReservations">-</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="card card-sm">
                      <div class="card-body">
                        <div class="subheader">Committed</div>
                        <div class="h2 mb-0" id="statQuantityCommitted">-</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card card-sm">
                      <div class="card-body">
                        <div class="subheader">ATP (Available)</div>
                        <div class="h2 mb-0 text-success" id="statATP">-</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="card card-sm">
                      <div class="card-body">
                        <div class="subheader">Overdue</div>
                        <div class="h2 mb-0 text-danger" id="statOverdueReservations">-</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card card-sm">
                      <div class="card-body">
                        <div class="subheader">Due This Week</div>
                        <div class="h2 mb-0 text-warning" id="statUpcomingReservations">-</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Add/Edit Reservation Form (Hidden by default) -->
                <div class="card mb-3" id="reservationFormCard" style="display: none;">
                  <div class="card-header">
                    <h4 class="card-title mb-0" id="reservationFormTitle">Reserve Inventory</h4>
                  </div>
                  <div class="card-body">
                    <form id="reservationForm">
                      <input type="hidden" id="reservationId" name="reservation_id">
                      <div class="row mb-3">
                        <div class="col-md-6">
                          <label class="form-label required">Job Number</label>
                          <input type="text" class="form-control" id="reservationJobNumber" name="job_number" placeholder="e.g., JOB-2024-001" required list="existingJobs">
                          <datalist id="existingJobs"></datalist>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Job Name</label>
                          <input type="text" class="form-control" id="reservationJobName" name="job_name" placeholder="Optional job description">
                        </div>
                      </div>
                      <div class="row mb-3">
                        <div class="col-md-4">
                          <label class="form-label required">Quantity to Reserve</label>
                          <input type="number" class="form-control" id="reservationQuantity" name="quantity_reserved" min="1" required>
                          <small class="text-muted" id="availableForReservation"></small>
                        </div>
                        <div class="col-md-4">
                          <label class="form-label required">Reserved Date</label>
                          <input type="date" class="form-control" id="reservationDate" name="reserved_date" required>
                        </div>
                        <div class="col-md-4">
                          <label class="form-label">Required Date</label>
                          <input type="date" class="form-control" id="reservationRequiredDate" name="required_date">
                        </div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="reservationNotes" name="notes" rows="2" placeholder="Optional notes about this reservation"></textarea>
                      </div>
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="saveReservationBtn">
                          <i class="ti ti-check me-1"></i>Reserve
                        </button>
                        <button type="button" class="btn btn-link" onclick="hideReservationForm()">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>

                <!-- Reservations List -->
                <div class="card">
                  <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="reservationFilterTabs">
                      <li class="nav-item">
                        <a class="nav-link active" href="#" data-filter="all" onclick="filterReservations('all'); return false;">All</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" href="#" data-filter="active" onclick="filterReservations('active'); return false;">Active</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" href="#" data-filter="fulfilled" onclick="filterReservations('fulfilled'); return false;">Fulfilled</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" href="#" data-filter="cancelled" onclick="filterReservations('cancelled'); return false;">Cancelled</a>
                      </li>
                    </ul>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                      <thead>
                        <tr>
                          <th>Job #</th>
                          <th>Reserved</th>
                          <th>Required</th>
                          <th class="text-end">Qty</th>
                          <th class="text-end">Fulfilled</th>
                          <th class="text-end">Remaining</th>
                          <th>Status</th>
                          <th class="w-1"></th>
                        </tr>
                      </thead>
                      <tbody id="reservationsTableBody">
                        <tr>
                          <td colspan="8" class="text-center text-muted py-5">
                            <i class="ti ti-clipboard-check" style="font-size: 2rem;"></i>
                            <p class="mb-0">No reservations yet</p>
                            <button class="btn btn-sm btn-primary mt-2" onclick="showAddReservationForm()">Reserve First Item</button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Activity Tab -->
            <div class="tab-pane fade" id="activity" role="tabpanel">
              <div class="mb-3">
                <div class="row g-2 align-items-center">
                  <div class="col">
                    <h3 class="mb-0">Activity History</h3>
                    <p class="text-muted mb-0">All inventory transactions for this product</p>
                  </div>
                  <div class="col-auto">
                    <select class="form-select form-select-sm" id="activityTypeFilter" onchange="loadProductActivity(currentProductId)">
                      <option value="">All Types</option>
                      <option value="receipt">Receipts</option>
                      <option value="shipment">Shipments</option>
                      <option value="adjustment">Adjustments</option>
                      <option value="transfer">Transfers</option>
                      <option value="return">Returns</option>
                      <option value="cycle_count">Cycle Counts</option>
                    </select>
                  </div>
                  <div class="col-auto">
                    <button class="btn btn-sm btn-primary" onclick="exportProductTransactions(currentProductId)">
                      <i class="ti ti-download me-1"></i>Export
                    </button>
                  </div>
                </div>
              </div>

              <div class="loading" id="activityLoading" style="display: none;">
                <div class="text-muted">Loading activity...</div>
              </div>

              <div id="activityContent">
                <div class="table-responsive">
                  <table class="table table-sm table-vcenter">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Before</th>
                        <th class="text-end">After</th>
                        <th>Reference</th>
                        <th>User</th>
                        <th>Notes</th>
                      </tr>
                    </thead>
                    <tbody id="activityTableBody">
                      <tr>
                        <td colspan="8" class="text-center text-muted">No activity records</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

            <!-- Configurator Tab -->
            <div class="tab-pane fade" id="configurator" role="tabpanel">
              <!-- Configurator Settings -->
              <div class="mb-4">
                <h4 class="mb-3"><i class="ti ti-settings me-2"></i>Configurator Settings</h4>
                <div class="row">
                  <div class="col-md-3">
                    <div class="form-check form-switch mb-2">
                      <input class="form-check-input" type="checkbox" id="configuratorAvailable" disabled>
                      <label class="form-check-label" for="configuratorAvailable">
                        <strong>Configurator Available</strong>
                      </label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label"><strong>Type</strong></label>
                    <div id="configuratorType" class="text-muted">-</div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label"><strong>Use Path</strong></label>
                    <div id="configuratorUsePath" class="text-muted">-</div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label"><strong>Dimensions</strong></label>
                    <div id="configuratorDimensions" class="text-muted">-</div>
                  </div>
                </div>
              </div>

              <hr>

              <!-- BOM (Required Parts) -->
              <div class="mb-3">
                <div class="row g-2 align-items-center">
                  <div class="col">
                    <h4 class="mb-0"><i class="ti ti-list-details me-2"></i>Bill of Materials (BOM)</h4>
                    <p class="text-muted mb-0">Parts required to build this product</p>
                  </div>
                  <div class="col-auto">
                    <button class="btn btn-sm btn-primary" onclick="showAddRequiredPartForm()" data-permission="inventory.edit">
                      <i class="ti ti-plus me-1"></i>Add Part
                    </button>
                    <button class="btn btn-sm btn-info" onclick="checkBOMAvailability(currentProductId)" data-permission="inventory.view">
                      <i class="ti ti-check me-1"></i>Check Availability
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="explodeBOM(currentProductId)" data-permission="inventory.view">
                      <i class="ti ti-sitemap me-1"></i>Explode BOM
                    </button>
                  </div>
                </div>
              </div>

              <!-- Add Required Part Form (Hidden by default) -->
              <div class="card mb-3" id="addRequiredPartForm" style="display: none;">
                <div class="card-header">
                  <h5 class="card-title mb-0">Add Required Part</h5>
                </div>
                <div class="card-body">
                  <form id="requiredPartForm">
                    <input type="hidden" id="requiredPartId">
                    <div class="row mb-3">
                      <div class="col-md-6">
                        <label class="form-label required">Part/Product</label>
                        <select class="form-select" id="requiredProductId" required>
                          <option value="">Search and select...</option>
                        </select>
                        <small class="form-hint">Select the part needed</small>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label required">Quantity</label>
                        <input type="number" class="form-control" id="requiredQuantity" step="0.01" min="0" required>
                        <small class="form-hint">Quantity per unit</small>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="requiredSortOrder" value="0">
                      </div>
                    </div>
                    <div class="row mb-3">
                      <div class="col-md-4">
                        <label class="form-label required">Finish Policy</label>
                        <select class="form-select" id="requiredFinishPolicy" required>
                          <option value="same_as_parent">Same as Parent</option>
                          <option value="specific">Specific Finish</option>
                          <option value="any">Any Finish</option>
                        </select>
                      </div>
                      <div class="col-md-4" id="specificFinishGroup" style="display: none;">
                        <label class="form-label">Specific Finish</label>
                        <select class="form-select" id="requiredSpecificFinish">
                          <option value="">Select...</option>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Optional Part</label>
                        <div class="form-check form-switch mt-2">
                          <input class="form-check-input" type="checkbox" id="requiredIsOptional">
                          <label class="form-check-label" for="requiredIsOptional">Is Optional</label>
                        </div>
                      </div>
                    </div>
                    <div class="row mb-3">
                      <div class="col-md-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="requiredNotes" rows="2"></textarea>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-md-12">
                        <button type="button" class="btn" onclick="hideRequiredPartForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Part</button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>

              <!-- BOM List -->
              <div class="loading" id="bomLoading" style="display: none;">
                <div class="text-muted">Loading BOM...</div>
              </div>

              <div id="bomContent">
                <div class="table-responsive">
                  <table class="table table-vcenter">
                    <thead>
                      <tr>
                        <th>Part SKU</th>
                        <th>Description</th>
                        <th class="text-end">Qty/Unit</th>
                        <th>Finish Policy</th>
                        <th>Optional</th>
                        <th>Notes</th>
                        <th class="w-1">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="bomTableBody">
                      <tr>
                        <td colspan="7" class="text-center text-muted">No parts in BOM</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Where Used -->
              <div class="mt-4">
                <h5 class="mb-3"><i class="ti ti-arrow-up me-2"></i>Where Used</h5>
                <div id="whereUsedContent">
                  <p class="text-muted">Loading where-used information...</p>
                </div>
              </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

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
                <label class="form-label required">Unit Cost</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" class="form-control" name="unit_cost" id="productUnitCost" placeholder="0.00" step="0.01" min="0" required>
                </div>
              </div>
              <div class="col-lg-6">
                <label class="form-label">Net Cost</label>
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

  <!-- Issue Material to Job Modal -->
  <div class="modal modal-blur fade" id="issueToJobModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Issue Material to Job</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="issueToJobForm">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label fw-bold">Product</label>
              <p id="issueJobProductInfo" class="mb-0">-</p>
            </div>
            <div class="mb-3">
              <label class="form-label">Available Quantity</label>
              <p id="issueJobAvailable" class="text-success h3 mb-0">0</p>
            </div>
            <div class="mb-3">
              <label class="form-label required">Job Name</label>
              <input type="text" class="form-control" name="job_name" id="issueJobName" placeholder="Enter job name or number" required>
              <small class="form-hint">Enter the job name or number this material is being issued to</small>
            </div>
            <div class="mb-3">
              <label class="form-label required">Quantity to Issue</label>
              <input type="number" class="form-control" name="quantity" id="issueJobQuantity" placeholder="0" min="1" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes (Optional)</label>
              <textarea class="form-control" name="notes" id="issueJobNotes" rows="2" placeholder="Additional notes..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning" id="issueJobBtn">
              <i class="ti ti-package-export me-1"></i>
              Issue Material
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

      products.forEach(product => {
        const statusBadge = getStatusBadge(product.status, product.on_order_qty);
        const locationCount = product.inventory_locations?.length || 0;
        const locationsDisplay = locationCount > 0
          ? `<span class="badge text-bg-azure">${locationCount} <i class="ti ti-map-pin"></i></span>`
          : '<span class="text-muted">-</span>';

        // Use pack-aware display functions
        const onHandDisplay = formatOnHandDisplay(product);
        const committedDisplay = formatCommittedDisplay(product, true);
        const availableDisplay = formatAvailableDisplay(product);

        const row = `
          <tr>
            <td><span class="text-muted">${product.sku}</span></td>
            <td>${product.description}</td>
            <td>${locationsDisplay}</td>
            <td class="text-end">${onHandDisplay}</td>
            <td class="text-end">${committedDisplay}</td>
            <td class="text-end">${availableDisplay}</td>
            <td>${statusBadge}</td>
            <td class="table-actions">
              <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="viewProduct(${product.id})" title="View">
                <i class="ti ti-eye"></i>
              </button>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });
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
    function formatCommittedDisplay(product, linkable = true) {
      const committedEaches = product.quantity_committed || 0;
      const committedPacks = product.quantity_committed_packs || committedEaches;
      const packSize = product.pack_size || 1;
      const hasPackSize = packSize > 1;
      const countingUnit = product.counting_unit || 'EA';

      if (committedEaches === 0) {
        return hasPackSize ? '0 packs' : '0';
      }

      let display;
      let title;

      if (hasPackSize) {
        // Show packs with eaches in tooltip
        const packLabel = committedPacks === 1 ? 'pack' : 'packs';
        display = `${committedPacks.toLocaleString()} ${packLabel}`;
        title = `${committedEaches.toLocaleString()} eaches total (${packSize} per pack)`;
      } else {
        display = committedEaches.toLocaleString();
        title = `${committedEaches.toLocaleString()} ${countingUnit}`;
      }

      if (linkable && committedEaches > 0) {
        return `<a href="#" class="text-decoration-none" onclick="viewProductReservations(${product.id}); return false;" title="${title}">${display}</a>`;
      }

      return `<span title="${title}">${display}</span>`;
    }

    /**
     * Format on-hand quantity display
     * Shows full packs if product has pack_size > 1, with eaches in tooltip
     */
    function formatOnHandDisplay(product) {
      const onHandEaches = product.quantity_on_hand || 0;
      const onHandPacks = product.quantity_on_hand_packs || onHandEaches;
      const packSize = product.pack_size || 1;
      const hasPackSize = packSize > 1;

      if (hasPackSize) {
        const packLabel = onHandPacks === 1 ? 'pack' : 'packs';
        const title = `${onHandEaches.toLocaleString()} eaches total (${packSize} per pack)`;
        return `<span title="${title}">${onHandPacks.toLocaleString()} ${packLabel}</span>`;
      }

      return onHandEaches.toLocaleString();
    }

    /**
     * Format available quantity display
     * Shows available packs if product has pack_size > 1
     * Available packs = on-hand packs - committed packs (can be 0 or negative)
     */
    function formatAvailableDisplay(product) {
      const availableEaches = product.quantity_available || 0;
      const packSize = product.pack_size || 1;
      const hasPackSize = packSize > 1;

      if (hasPackSize) {
        // Calculate available packs: on-hand full packs minus committed packs needed
        // Use nullish coalescing (??) to properly handle 0 values
        const onHandPacks = product.quantity_on_hand_packs ?? Math.floor((product.quantity_on_hand || 0) / packSize);
        const committedPacks = product.quantity_committed_packs ?? Math.ceil((product.quantity_committed || 0) / packSize);
        const availablePacks = product.quantity_available_packs ?? Math.max(0, onHandPacks - committedPacks);
        const packLabel = availablePacks === 1 ? 'pack' : 'packs';
        const title = `${availableEaches.toLocaleString()} eaches available (${onHandPacks} on hand - ${committedPacks} committed)`;
        return `<span title="${title}">${availablePacks.toLocaleString()} ${packLabel}</span>`;
      }

      return availableEaches.toLocaleString();
    }

    function getStatusBadge(status, onOrderQty = 0) {
      const badges = {
        'in_stock': '<span class="badge text-bg-success status-badge">In Stock</span>',
        'low_stock': '<span class="badge text-bg-warning status-badge">Low Stock</span>',
        'critical': '<span class="badge text-bg-danger status-badge">Critical</span>',
        'out_of_stock': '<span class="badge text-bg-dark status-badge">Out of Stock</span>'
      };
      let badge = badges[status] || badges['in_stock'];

      // Add "On Order" indicator if there's pending order quantity
      if (onOrderQty && onOrderQty > 0) {
        badge += ` <span class="badge text-bg-info status-badge" title="${onOrderQty} on order">On Order</span>`;
      }

      return badge;
    }

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
        const data = await response.json();
        renderInventoryTable(data.data);
        renderPagination(data);
        updateSortIcons();

        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('inventoryTableContainer').style.display = 'block';
      } catch (error) {
        console.error('Error loading filtered inventory:', error);
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

    let currentProductId = null;
    let currentProductLocations = [];

    // View product and open reservations tab directly
    async function viewProductReservations(id) {
      await viewProduct(id);
      // Switch to reservations tab after modal opens
      setTimeout(() => {
        const reservationsTab = document.getElementById('reservations-tab');
        if (reservationsTab) {
          reservationsTab.click();
        }
      }, 100);
    }

    async function viewProduct(id) {
      try {
        currentProductId = id;
        const response = await apiCall(`/products/${id}`);
        const product = await response.json();

        // Set modal title
        document.getElementById('viewProductModalTitle').textContent = `${product.sku} - ${product.description}`;

        // Populate details tab
        const needsReorder = product.reorder_point && product.quantity_available <= product.reorder_point;
        const daysUntilStockout = product.days_until_stockout;
        const suggestedOrderQty = product.suggested_order_qty || 0;

        document.getElementById('productDetailsView').innerHTML = `
          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">SKU</label>
              <p>${product.sku}</p>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Part Number</label>
              <p>${product.part_number || '-'}</p>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Finish</label>
              <p>${product.finish ? `${product.finish} - ${product.finish_name || product.finish}` : '-'}</p>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label fw-bold">Description</label>
              <p>${product.description}</p>
              ${product.long_description ? `<p class="text-muted">${product.long_description}</p>` : ''}
            </div>
          </div>

          <hr>
          <h5 class="mb-3">Pricing</h5>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Unit Cost</label>
              <p><span class="${canViewPricing() ? 'price-visible' : 'price-masked'}" ${!canViewPricing() && product.unit_cost ? `data-actual-value="${product.unit_cost}"` : ''} aria-label="${canViewPricing() ? '' : 'Price hidden'}">${formatPrice(product.unit_cost)}</span></p>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Net Cost</label>
              <p><span class="${canViewPricing() ? 'price-visible' : 'price-masked'}" ${!canViewPricing() && product.net_cost ? `data-actual-value="${product.net_cost}"` : ''} aria-label="${canViewPricing() ? '' : 'Price hidden'}">${product.net_cost ? formatPrice(product.net_cost) : (canViewPricing() ? 'Not set' : formatPrice(null))}</span></p>
            </div>
          </div>

          <hr>
          <h5 class="mb-3">Inventory Status ${product.pack_size > 1 ? '<small class="text-muted">(showing packs)</small>' : ''}</h5>
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label fw-bold">On Hand</label>
              <p>${formatOnHandDisplay(product)}</p>
              ${product.pack_size > 1 ? `<small class="text-muted">${(product.quantity_on_hand ?? 0).toLocaleString()} eaches</small>` : ''}
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Committed</label>
              <p>${formatCommittedDisplay(product, false)}</p>
              ${product.pack_size > 1 ? `<small class="text-muted">${(product.quantity_committed ?? 0).toLocaleString()} eaches</small>` : ''}
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Available</label>
              <p class="text-success fw-bold">${formatAvailableDisplay(product)}</p>
              ${product.pack_size > 1 ? `<small class="text-muted">${(product.quantity_available ?? 0).toLocaleString()} eaches</small>` : ''}
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">On Order</label>
              <p>${(product.on_order_qty ?? 0).toLocaleString()}</p>
            </div>
          </div>
          ${needsReorder ? `
            <div class="alert alert-warning mb-3">
              <h4 class="alert-title"><i class="ti ti-alert-triangle me-2"></i>Reorder Alert</h4>
              <p class="mb-2">Available quantity (${product.quantity_available}) is at or below reorder point (${product.reorder_point}).</p>
              ${suggestedOrderQty > 0 ? `<p class="mb-0"><strong>Suggested Order:</strong> ${suggestedOrderQty} units</p>` : ''}
            </div>
          ` : ''}
          ${daysUntilStockout && daysUntilStockout <= 30 ? `
            <div class="alert alert-${daysUntilStockout <= 7 ? 'danger' : 'info'} mb-3">
              <h4 class="alert-title"><i class="ti ti-clock-exclamation me-2"></i>Stockout Warning</h4>
              <p class="mb-0">At current usage rate, inventory will last approximately <strong>${daysUntilStockout} days</strong>.</p>
            </div>
          ` : ''}

          <hr>
          <h5 class="mb-3">Stock Management</h5>
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label fw-bold">Reorder Point</label>
              <p>${product.reorder_point || '-'}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Safety Stock</label>
              <p>${product.safety_stock || '-'}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Avg Daily Use</label>
              <p>${product.average_daily_use || '-'}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Min / Max</label>
              <p>${product.minimum_quantity} / ${product.maximum_quantity || '-'}</p>
            </div>
          </div>

          <hr>
          <h5 class="mb-3">Unit of Measure</h5>
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label fw-bold">Stock UOM</label>
              <p>${product.unit_of_measure} ${product.uom_name ? '- ' + product.uom_name : ''}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Pack Size</label>
              <p>${product.pack_size || 1}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Min Order Qty</label>
              <p>${product.min_order_qty || '-'}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Order Multiple</label>
              <p>${product.order_multiple || '-'}</p>
            </div>
          </div>

          <hr>
          <h5 class="mb-3">Supplier</h5>
          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">Supplier</label>
              <p>${product.supplier ? product.supplier.name : '-'}</p>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Supplier SKU</label>
              <p>${product.supplier_sku || '-'}</p>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Lead Time</label>
              <p>${product.lead_time_days ? product.lead_time_days + ' days' : '-'}</p>
            </div>
          </div>

          <hr>
          <div class="row">
            <div class="col-md-6">
              <label class="form-label fw-bold">Categories</label>
              <p>${product.categories && product.categories.length > 0
                ? product.categories.map(c => `<span class="badge text-bg-info me-1">${c.name}</span>`).join('')
                : '-'}</p>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Status</label>
              <p>${getStatusBadge(product.status, product.on_order_qty)}</p>
            </div>
          </div>
        `;

        // Load locations and reservations
        await loadProductLocations(id);
        await loadProductReservations(id);
        await loadProductActivity(id);
        await loadProductBOM(id);

        // Populate configurator settings
        document.getElementById('configuratorAvailable').checked = product.configurator_available || false;
        document.getElementById('configuratorType').textContent = product.configurator_type || '-';
        document.getElementById('configuratorUsePath').textContent = product.configurator_use_path || '-';
        const dimensions = [];
        if (product.dimension_height) dimensions.push(`H: ${product.dimension_height}`);
        if (product.dimension_depth) dimensions.push(`D: ${product.dimension_depth}`);
        document.getElementById('configuratorDimensions').textContent = dimensions.length > 0 ? dimensions.join(', ') : '-';

        // Show modal
        showModal(document.getElementById('viewProductModal'));
      } catch (error) {
        console.error('Error loading product:', error);
        showNotification('Failed to load product details', 'danger');
      }
    }

    let currentProductData = null;
    let isEditMode = false;

    // Reset edit mode when the product modal is closed
    document.getElementById('viewProductModal').addEventListener('hidden.bs.modal', function () {
      if (isEditMode) {
        cancelEditMode();
      }
    });

    async function toggleEditMode() {
      isEditMode = true;
      document.getElementById('editProductBtn').style.display = 'none';
      document.getElementById('editProductActions').style.display = 'block';
      document.getElementById('productDetailsView').style.display = 'none';
      document.getElementById('productEditForm').style.display = 'block';

      // Load fresh product data
      try {
        const response = await apiCall(`/products/${currentProductId}`);
        currentProductData = await response.json();
        renderEditForm(currentProductData);
      } catch (error) {
        console.error('Error loading product for edit:', error);
        showNotification('Failed to load product for editing', 'danger');
        cancelEditMode();
      }
    }

    function cancelEditMode() {
      isEditMode = false;
      document.getElementById('editProductBtn').style.display = 'block';
      document.getElementById('editProductActions').style.display = 'none';
      document.getElementById('productDetailsView').style.display = 'block';
      document.getElementById('productEditForm').style.display = 'none';
    }

    async function renderEditForm(product) {
      // Store references to global config data before destructuring to avoid temporal dead zone
      const globalFinishes = finishCodes;
      const globalCategories = categories;
      const globalSuppliers = suppliers;
      const globalUoms = unitOfMeasures;

      // Load options for dropdowns - use cached data if available
      const [finishes, cats, sups, uoms] = await Promise.all([
        globalFinishes.length > 0 ? Promise.resolve(globalFinishes) :
          apiCall('/finish-codes').then(r => r.json()).then(data => Array.isArray(data) ? data : []).catch(() => []),
        globalCategories.length > 0 ? Promise.resolve(globalCategories) :
          apiCall('/categories?per_page=all').then(r => r.json()).then(data => Array.isArray(data) ? data : (data.data || [])).catch(() => []),
        globalSuppliers.length > 0 ? Promise.resolve(globalSuppliers) :
          apiCall('/suppliers?per_page=all').then(r => r.json()).then(data => Array.isArray(data) ? data : (data.data || [])).catch(() => []),
        globalUoms.length > 0 ? Promise.resolve(globalUoms) :
          apiCall('/unit-of-measures').then(r => r.json()).then(data => Array.isArray(data) ? data : []).catch(() => [])
      ]);

      document.getElementById('productEditForm').innerHTML = `
        <form id="editProductFormElement">
          <!-- Basic Info -->
          <h5 class="mb-3"><i class="ti ti-info-circle me-2"></i>Basic Information</h5>
          <div class="row mb-3">
            <div class="col-lg-4">
              <label class="form-label">Part Number</label>
              <input type="text" class="form-control" name="part_number" value="${product.part_number || ''}" placeholder="e.g., ABC-123">
            </div>
            <div class="col-lg-4">
              <label class="form-label">Finish</label>
              <select class="form-select" name="finish" id="editProductFinish">
                <option value="">None</option>
                ${finishes.map(f => `<option value="${f.code}" ${product.finish === f.code ? 'selected' : ''}>${f.code} - ${f.name}</option>`).join('')}
              </select>
            </div>
            <div class="col-lg-4">
              <label class="form-label required">SKU</label>
              <input type="text" class="form-control" name="sku" value="${product.sku}" required readonly>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-lg-6">
              <label class="form-label required">Description</label>
              <input type="text" class="form-control" name="description" value="${product.description}" required>
            </div>
            <div class="col-lg-3">
              <label class="form-label">Categories/Systems</label>
              <select class="form-select" name="category_ids" multiple size="4">
                ${cats.map(c => {
                  const isSelected = product.categories && product.categories.some(cat => cat.id === c.id);
                  return `<option value="${c.id}" ${isSelected ? 'selected' : ''}>${c.name}</option>`;
                }).join('')}
              </select>
              <small class="form-hint">Hold Ctrl/Cmd to select multiple</small>
            </div>
            <div class="col-lg-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="is_active">
                <option value="1" ${product.is_active ? 'selected' : ''}>Active</option>
                <option value="0" ${!product.is_active ? 'selected' : ''}>Inactive</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Long Description</label>
            <textarea class="form-control" name="long_description" rows="2">${product.long_description || ''}</textarea>
          </div>

          <hr>
          <h5 class="mb-3"><i class="ti ti-currency-dollar me-2"></i>Pricing</h5>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Unit Cost</label>
              <input type="number" step="0.01" class="form-control" name="unit_cost" value="${product.unit_cost || '0.00'}">
            </div>
            <div class="col-md-6">
              <label class="form-label">Net Cost</label>
              <input type="number" step="0.01" class="form-control" name="net_cost" value="${product.net_cost || ''}">
              <small class="form-hint">Calculated from EZ Estimate or manually entered</small>
            </div>
          </div>

          <hr>
          <h5 class="mb-3"><i class="ti ti-package me-2"></i>Stock Management</h5>
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label">Reorder Point</label>
              <input type="number" step="0.01" class="form-control" name="reorder_point" value="${product.reorder_point || ''}">
            </div>
            <div class="col-md-3">
              <label class="form-label">Safety Stock</label>
              <input type="number" step="0.01" class="form-control" name="safety_stock" value="${product.safety_stock || ''}">
            </div>
            <div class="col-md-3">
              <label class="form-label">Avg Daily Use</label>
              <input type="number" step="0.01" class="form-control" name="average_daily_use" value="${product.average_daily_use || ''}">
            </div>
            <div class="col-md-3">
              <label class="form-label">Lead Time (days)</label>
              <input type="number" class="form-control" name="lead_time_days" value="${product.lead_time_days || ''}">
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Minimum Quantity</label>
              <input type="number" step="0.01" class="form-control" name="minimum_quantity" value="${product.minimum_quantity || '0'}">
            </div>
            <div class="col-md-6">
              <label class="form-label">Maximum Quantity</label>
              <input type="number" step="0.01" class="form-control" name="maximum_quantity" value="${product.maximum_quantity || ''}">
            </div>
          </div>

          <hr>
          <h5 class="mb-3"><i class="ti ti-ruler me-2"></i>Unit of Measure</h5>
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label">Stock UOM</label>
              <select class="form-select" name="unit_of_measure">
                ${uoms.map(u => `<option value="${u.code}" ${product.unit_of_measure === u.code ? 'selected' : ''}>${u.code} - ${u.name}</option>`).join('')}
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Pack Size</label>
              <input type="number" step="0.01" class="form-control" name="pack_size" value="${product.pack_size || '1'}">
            </div>
            <div class="col-md-3">
              <label class="form-label">Min Order Qty</label>
              <input type="number" step="0.01" class="form-control" name="min_order_qty" value="${product.min_order_qty || ''}">
            </div>
            <div class="col-md-3">
              <label class="form-label">Order Multiple</label>
              <input type="number" step="0.01" class="form-control" name="order_multiple" value="${product.order_multiple || ''}">
            </div>
          </div>

          <hr>
          <h5 class="mb-3"><i class="ti ti-truck me-2"></i>Supplier</h5>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Supplier</label>
              <select class="form-select" name="supplier_id">
                <option value="">Select supplier...</option>
                ${sups.map(s => `<option value="${s.id}" ${product.supplier_id === s.id ? 'selected' : ''}>${s.name}</option>`).join('')}
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Supplier SKU</label>
              <input type="text" class="form-control" name="supplier_sku" value="${product.supplier_sku || ''}">
            </div>
          </div>
        </form>
      `;
    }

    async function saveProductChanges() {
      try {
        const form = document.getElementById('editProductFormElement');
        const formData = new FormData(form);
        const data = {};

        formData.forEach((value, key) => {
          if (key === 'is_active') {
            data[key] = value === '1';
          } else if (key !== 'category_ids' && value !== '') {
            data[key] = value;
          }
        });

        // Handle multiple category selection
        const categorySelect = form.querySelector('[name="category_ids"]');
        if (categorySelect) {
          const selectedCategories = Array.from(categorySelect.selectedOptions).map(option => parseInt(option.value));
          if (selectedCategories.length > 0) {
            data.category_ids = selectedCategories;
            // Keep first selected as primary, or use the first category from current product
            data.primary_category_id = selectedCategories[0];
          } else {
            data.category_ids = [];
          }
        }
        // Remove old single category_id if present
        delete data.category_id;

        const response = await apiCall(`/products/${currentProductId}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });

        if (response.ok) {
          const updatedProduct = await response.json();
          showNotification('Product updated successfully', 'success');
          cancelEditMode();

          // Reload the product view with updated data
          await viewProduct(currentProductId);

          // Refresh the dashboard table
          loadDashboard();
        } else {
          const error = await response.json();
          throw new Error(error.message || 'Failed to update product');
        }
      } catch (error) {
        console.error('Error saving product:', error);
        showNotification('Failed to save changes: ' + error.message, 'danger');
      }
    }

    async function loadProductLocations(productId) {
      try {
        const response = await apiCall(`/products/${productId}/locations`);

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        // Ensure we have an array
        currentProductLocations = Array.isArray(data) ? data : [];

        // Update locations count badge
        document.getElementById('locationsCount').textContent = currentProductLocations.length;

        // Load location statistics
        await loadLocationStatistics(productId);

        // Render locations table
        renderLocationsTable();

        // Load all existing locations for autocomplete
        await loadAllLocations();
      } catch (error) {
        console.error('Error loading locations:', error);
        currentProductLocations = [];
        document.getElementById('locationsCount').textContent = '0';
        renderLocationsTable();
        showNotification('Failed to load locations', 'danger');
      }
    }

    async function loadLocationStatistics(productId) {
      try {
        const response = await apiCall(`/products/${productId}/locations/statistics`);
        const stats = await response.json();

        document.getElementById('statTotalLocations').textContent = stats.total_locations;
        document.getElementById('statTotalQuantity').textContent = stats.total_quantity.toLocaleString();
        document.getElementById('statTotalCommitted').textContent = stats.total_committed.toLocaleString();
        document.getElementById('statTotalAvailable').textContent = stats.total_available.toLocaleString();
      } catch (error) {
        console.error('Error loading statistics:', error);
      }
    }

    async function loadAllLocations() {
      try {
        // Load hierarchical storage locations
        const response = await apiCall('/storage-locations-tree');
        const locations = await response.json();

        const select = document.getElementById('storageLocationSelect');
        // Keep the first two options (Select... and Custom)
        while (select.options.length > 2) {
          select.remove(2);
        }

        // Recursive function to add options with indentation
        function addLocationOptions(locations, level = 0) {
          locations.forEach(location => {
            const option = document.createElement('option');
            option.value = location.id;
            const indent = '\u00A0'.repeat(level * 4); // Non-breaking spaces for indentation
            option.textContent = indent + location.name;
            select.appendChild(option);

            if (location.children && location.children.length > 0) {
              addLocationOptions(location.children, level + 1);
            }
          });
        }

        addLocationOptions(locations);
      } catch (error) {
        console.error('Error loading all locations:', error);
      }
    }

    function renderLocationsTable() {
      const tbody = document.getElementById('locationsTableBody');

      if (currentProductLocations.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" class="text-center text-muted py-5">
              <i class="ti ti-map-pin" style="font-size: 2rem;"></i>
              <p class="mb-0">No locations added yet</p>
              <button class="btn btn-sm btn-primary mt-2" onclick="showAddLocationForm()">Add First Location</button>
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = '';
      currentProductLocations.forEach(location => {
        const primaryBadge = location.is_primary
          ? '<span class="badge text-bg-primary ms-1">Primary</span>'
          : '';

        const availableClass = location.quantity_available <= 0 ? 'text-danger' : 'text-success';

        // Display storage location name if available, otherwise fall back to location string
        const locationDisplay = location.storage_location ? location.storage_location.name : location.location;

        const row = `
          <tr>
            <td>
              <strong>${locationDisplay}</strong>${primaryBadge}
              ${location.notes ? `<br><small class="text-muted">${location.notes}</small>` : ''}
            </td>
            <td class="text-end">${location.quantity.toLocaleString()}</td>
            <td class="text-end">${location.quantity_committed.toLocaleString()}</td>
            <td class="text-end ${availableClass}"><strong>${location.quantity_available.toLocaleString()}</strong></td>
            <td>
              <div class="progress" style="height: 20px;">
                <div class="progress-bar" role="progressbar" style="width: ${location.percentage || 0}%;"
                     aria-valuenow="${location.percentage || 0}" aria-valuemin="0" aria-valuemax="100">
                  ${location.percentage || 0}%
                </div>
              </div>
            </td>
            <td>
              ${location.quantity > 0 ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Empty</span>'}
            </td>
            <td class="table-actions">
              <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="editLocation(${location.id})" title="Edit" data-permission="inventory.edit">
                <i class="ti ti-edit"></i>
              </button>
              <button class="btn btn-sm btn-icon btn-ghost-danger" onclick="deleteLocation(${location.id})" title="Delete" data-permission="inventory.delete">
                <i class="ti ti-trash"></i>
              </button>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });

      // Update transfer dropdowns
      updateTransferDropdowns();

      // Apply action permissions to dynamically created buttons
      if (typeof applyActionPermissions === 'function') {
        applyActionPermissions();
      }
    }

    function handleStorageLocationSelect() {
      const select = document.getElementById('storageLocationSelect');
      const locationName = document.getElementById('locationName');
      const storageLocationId = document.getElementById('storageLocationId');

      if (select.value === 'custom') {
        // Show custom location input
        locationName.style.display = 'block';
        locationName.required = true;
        locationName.value = '';
        storageLocationId.value = '';
        locationName.focus();
      } else if (select.value) {
        // Hide custom input and use selected storage location
        locationName.style.display = 'none';
        locationName.required = false;
        locationName.value = select.options[select.selectedIndex].text.trim();
        storageLocationId.value = select.value;
      } else {
        // Nothing selected
        locationName.style.display = 'none';
        locationName.required = false;
        locationName.value = '';
        storageLocationId.value = '';
      }
    }

    function showAddLocationForm() {
      document.getElementById('locationFormTitle').textContent = 'Add Location';
      document.getElementById('locationForm').reset();
      document.getElementById('locationId').value = '';
      document.getElementById('storageLocationId').value = '';
      document.getElementById('storageLocationSelect').value = '';
      document.getElementById('locationName').style.display = 'none';
      document.getElementById('locationFormCard').style.display = 'block';
      document.getElementById('storageLocationSelect').focus();
    }

    function hideLocationForm() {
      document.getElementById('locationFormCard').style.display = 'none';
      document.getElementById('locationForm').reset();
    }

    function editLocation(locationId) {
      const location = currentProductLocations.find(l => l.id === locationId);
      if (!location) return;

      document.getElementById('locationFormTitle').textContent = 'Edit Location';
      document.getElementById('locationId').value = location.id;
      document.getElementById('storageLocationId').value = location.storage_location_id || '';

      // If location has a storage_location_id, select it in dropdown
      if (location.storage_location_id) {
        document.getElementById('storageLocationSelect').value = location.storage_location_id;
        document.getElementById('locationName').style.display = 'none';
        document.getElementById('locationName').value = location.location;
      } else {
        // Custom location
        document.getElementById('storageLocationSelect').value = 'custom';
        document.getElementById('locationName').value = location.location;
        document.getElementById('locationName').style.display = 'block';
        document.getElementById('locationName').required = true;
      }

      document.getElementById('locationQuantity').value = location.quantity;
      document.getElementById('locationCommitted').value = location.quantity_committed;
      document.getElementById('locationPrimary').checked = location.is_primary;
      document.getElementById('locationNotes').value = location.notes || '';
      document.getElementById('locationFormCard').style.display = 'block';
      document.getElementById('storageLocationSelect').focus();
    }

    async function deleteLocation(locationId) {
      const location = currentProductLocations.find(l => l.id === locationId);
      if (!location) return;

      if (location.quantity > 0) {
        showNotification('Cannot delete location with inventory. Please transfer or adjust quantity to zero first.', 'warning');
        return;
      }

      if (!confirm(`Are you sure you want to delete the location "${location.location}"?`)) {
        return;
      }

      try {
        const response = await apiCall(`/products/${currentProductId}/locations/${locationId}`, {
          method: 'DELETE'
        });

        if (response.ok) {
          showNotification('Location deleted successfully', 'success');
          await loadProductLocations(currentProductId);
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to delete location', 'danger');
        }
      } catch (error) {
        console.error('Error deleting location:', error);
        showNotification('Failed to delete location', 'danger');
      }
    }

    // Location Form Submit
    document.getElementById('locationForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      const locationId = document.getElementById('locationId').value;
      const storageLocationId = document.getElementById('storageLocationId').value;
      const formData = {
        storage_location_id: storageLocationId || null,
        location: document.getElementById('locationName').value,
        quantity: parseInt(document.getElementById('locationQuantity').value),
        quantity_committed: parseInt(document.getElementById('locationCommitted').value),
        is_primary: document.getElementById('locationPrimary').checked,
        notes: document.getElementById('locationNotes').value
      };

      try {
        const saveBtn = document.getElementById('saveLocationBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        let response;
        if (locationId) {
          // Update existing location
          response = await apiCall(`/products/${currentProductId}/locations/${locationId}`, {
            method: 'PUT',
            body: JSON.stringify(formData)
          });
        } else {
          // Create new location
          response = await apiCall(`/products/${currentProductId}/locations`, {
            method: 'POST',
            body: JSON.stringify(formData)
          });
        }

        if (response.ok) {
          showNotification(locationId ? 'Location updated successfully' : 'Location added successfully', 'success');
          hideLocationForm();
          await loadProductLocations(currentProductId);
          await loadDashboard(); // Refresh main dashboard
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to save location', 'danger');
        }
      } catch (error) {
        console.error('Error saving location:', error);
        showNotification('Failed to save location', 'danger');
      } finally {
        const saveBtn = document.getElementById('saveLocationBtn');
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="ti ti-check me-1"></i>Save Location';
      }
    });

    function updateTransferDropdowns() {
      const fromSelect = document.getElementById('transferFrom');
      const toSelect = document.getElementById('transferTo');

      fromSelect.innerHTML = '<option value="">Select source...</option>';
      toSelect.innerHTML = '<option value="">Select destination...</option>';

      currentProductLocations.forEach(location => {
        if (location.quantity_available > 0) {
          const option = document.createElement('option');
          option.value = location.id;
          option.textContent = `${location.location} (Available: ${location.quantity_available})`;
          fromSelect.appendChild(option);
        }

        const toOption = document.createElement('option');
        toOption.value = location.id;
        toOption.textContent = location.location;
        toSelect.appendChild(toOption);
      });
    }

    function showTransferForm() {
      updateTransferDropdowns();
      document.getElementById('transferCard').style.display = 'block';
    }

    function hideTransferForm() {
      document.getElementById('transferCard').style.display = 'none';
      document.getElementById('transferForm').reset();
    }

    // Update available quantity when source location changes
    document.getElementById('transferFrom').addEventListener('change', function() {
      const locationId = parseInt(this.value);
      if (!locationId) {
        document.getElementById('transferFromAvailable').textContent = '';
        return;
      }

      const location = currentProductLocations.find(l => l.id === locationId);
      if (location) {
        document.getElementById('transferFromAvailable').textContent =
          `Available: ${location.quantity_available}`;
        document.getElementById('transferQuantity').max = location.quantity_available;
      }
    });

    // Transfer Form Submit
    document.getElementById('transferForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      const formData = {
        from_location_id: parseInt(document.getElementById('transferFrom').value),
        to_location_id: parseInt(document.getElementById('transferTo').value),
        quantity: parseInt(document.getElementById('transferQuantity').value),
        notes: document.getElementById('transferNotes').value
      };

      if (formData.from_location_id === formData.to_location_id) {
        showNotification('Source and destination must be different', 'warning');
        return;
      }

      try {
        const response = await apiCall(`/products/${currentProductId}/locations/transfer`, {
          method: 'POST',
          body: JSON.stringify(formData)
        });

        if (response.ok) {
          showNotification('Inventory transferred successfully', 'success');
          hideTransferForm();
          await loadProductLocations(currentProductId);
          await loadDashboard(); // Refresh main dashboard
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to transfer inventory', 'danger');
        }
      } catch (error) {
        console.error('Error transferring inventory:', error);
        showNotification('Failed to transfer inventory', 'danger');
      }
    });

    // ========== ISSUE TO JOB ==========
    function showIssueToJobForm() {
      if (!currentProductId) {
        showNotification('No product selected', 'danger');
        return;
      }

      const product = currentProductData;
      if (!product) {
        showNotification('Product data not loaded', 'danger');
        return;
      }

      // Populate modal with product info
      document.getElementById('issueJobProductInfo').innerHTML =
        `<strong>${product.sku}</strong> - ${product.description}`;
      document.getElementById('issueJobAvailable').textContent = product.quantity_available || 0;

      // Reset form
      document.getElementById('issueToJobForm').reset();

      // Show modal
      showModal(document.getElementById('issueToJobModal'));
    }

    document.getElementById('issueToJobForm').addEventListener('submit', async function(e) {
      e.preventDefault();

      const formData = new FormData(e.target);
      const data = {
        job_name: formData.get('job_name'),
        quantity: parseInt(formData.get('quantity')),
        notes: formData.get('notes') || null,
      };

      // Validate quantity doesn't exceed available
      const product = currentProductData;
      if (data.quantity > product.quantity_available) {
        showNotification(`Cannot issue ${data.quantity}. Only ${product.quantity_available} available.`, 'danger');
        return;
      }

      const btn = document.getElementById('issueJobBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Issuing...';

      try {
        const response = await apiCall(`/products/${currentProductId}/issue-to-job`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(data)
        });

        if (response.ok) {
          showNotification('Material issued to job successfully', 'success');
          hideModal(document.getElementById('issueToJobModal'));

          // Reload product data
          await viewProduct(currentProductId);
          await loadDashboard();
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to issue material', 'danger');
        }
      } catch (error) {
        console.error('Error issuing material:', error);
        showNotification('Failed to issue material', 'danger');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-package-export me-1"></i>Issue Material';
      }
    });

    // ========== RESERVATION MANAGEMENT ==========
    let currentProductReservations = [];
    let currentReservationFilter = 'all';

    async function loadProductReservations(productId) {
      try {
        const response = await apiCall(`/products/${productId}/reservations`);
        currentProductReservations = await response.json();

        // Update reservations count badge
        const activeCount = currentProductReservations.filter(r => r.status === 'active' || r.status === 'partially_fulfilled').length;
        document.getElementById('reservationsCount').textContent = activeCount;

        // Load reservation statistics
        await loadReservationStatistics(productId);

        // Render reservations table
        renderReservationsTable();

        // Load all existing jobs for autocomplete
        await loadAllJobs();

        // Set today's date as default
        document.getElementById('reservationDate').valueAsDate = new Date();
      } catch (error) {
        console.error('Error loading reservations:', error);
        showNotification('Failed to load reservations', 'danger');
      }
    }

    // ========== ACTIVITY/TRANSACTIONS ==========
    let currentProductTransactions = [];

    async function loadProductActivity(productId) {
      try {
        document.getElementById('activityLoading').style.display = 'block';
        document.getElementById('activityContent').style.display = 'none';

        const typeFilter = document.getElementById('activityTypeFilter').value;
        let url = `/products/${productId}/transactions?per_page=all`;

        if (typeFilter) {
          url += `&type=${typeFilter}`;
        }

        const response = await apiCall(url);
        const data = await response.json();
        currentProductTransactions = Array.isArray(data) ? data : (data.data || []);
        renderProductActivity();

        document.getElementById('activityLoading').style.display = 'none';
        document.getElementById('activityContent').style.display = 'block';
      } catch (error) {
        console.error('Error loading product activity:', error);
        document.getElementById('activityLoading').style.display = 'none';
        document.getElementById('activityContent').innerHTML = '<div class="alert alert-danger">Error loading activity</div>';
      }
    }

    function renderProductActivity() {
      const tbody = document.getElementById('activityTableBody');

      if (currentProductTransactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No activity records</td></tr>';
        return;
      }

      tbody.innerHTML = currentProductTransactions.map(transaction => {
        const date = new Date(transaction.transaction_date);
        const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});

        const typeBadge = getTransactionTypeBadge(transaction.type);
        const quantityClass = transaction.quantity >= 0 ? 'text-success' : 'text-danger';
        const quantitySign = transaction.quantity >= 0 ? '+' : '';

        return `
          <tr>
            <td><small>${formattedDate}</small></td>
            <td>${typeBadge}</td>
            <td class="text-end ${quantityClass}"><strong>${quantitySign}${transaction.quantity}</strong></td>
            <td class="text-end">${transaction.quantity_before}</td>
            <td class="text-end">${transaction.quantity_after}</td>
            <td>${transaction.reference_number ? escapeHtml(transaction.reference_number) : '-'}</td>
            <td><small>${transaction.user ? escapeHtml(transaction.user.name) : '-'}</small></td>
            <td><small>${transaction.notes ? escapeHtml(transaction.notes) : '-'}</small></td>
          </tr>
        `;
      }).join('');
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function getTransactionTypeBadge(type) {
      const badges = {
        'receipt': '<span class="badge text-bg-success">Receipt</span>',
        'shipment': '<span class="badge text-bg-info">Shipment</span>',
        'adjustment': '<span class="badge text-bg-warning">Adjustment</span>',
        'transfer': '<span class="badge text-bg-primary">Transfer</span>',
        'return': '<span class="badge text-bg-secondary">Return</span>',
        'cycle_count': '<span class="badge text-bg-purple">Cycle Count</span>',
        'job_issue': '<span class="badge text-bg-orange">Job Issue</span>',
      };
      return badges[type] || '<span class="badge">' + type + '</span>';
    }

    async function exportProductTransactions(productId) {
      try {
        const typeFilter = document.getElementById('activityTypeFilter').value;
        let url = `/transactions-export?product_id=${productId}`;

        if (typeFilter) {
          url += `&type=${typeFilter}`;
        }

        const response = await fetch(`${API_BASE}${url}`, {
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
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = `transactions_export_${productId}_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(downloadUrl);

        showNotification('Transactions exported successfully', 'success');
      } catch (error) {
        console.error('Error exporting transactions:', error);
        showNotification('Error exporting transactions: ' + error.message, 'danger');
      }
    }

    // ========== CONFIGURATOR & BOM ==========
    let currentBOM = [];
    let allProducts = []; // For part selector

    async function loadProductBOM(productId) {
      try {
        document.getElementById('bomLoading').style.display = 'block';
        document.getElementById('bomContent').style.display = 'none';

        const response = await apiCall(`/products/${productId}/required-parts`);
        const data = await response.json();
        currentBOM = Array.isArray(data) ? data : (data.data || []);
        renderBOM();

        document.getElementById('bomCount').textContent = currentBOM.length;
        document.getElementById('bomLoading').style.display = 'none';
        document.getElementById('bomContent').style.display = 'block';

        // Load where-used
        loadWhereUsed(productId);
      } catch (error) {
        console.error('Error loading BOM:', error);
        document.getElementById('bomLoading').style.display = 'none';
        document.getElementById('bomContent').innerHTML = '<div class="alert alert-danger">Error loading BOM</div>';
      }
    }

    function renderBOM() {
      const tbody = document.getElementById('bomTableBody');

      if (currentBOM.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No parts in BOM</td></tr>';
        return;
      }

      tbody.innerHTML = currentBOM.map(part => {
        const finishPolicyBadge = getFinishPolicyBadge(part.finish_policy, part.specific_finish);
        const optionalBadge = part.is_optional
          ? '<span class="badge text-bg-secondary">Optional</span>'
          : '<span class="badge text-bg-success">Required</span>';

        return `
          <tr>
            <td><strong>${escapeHtml(part.required_product.sku)}</strong></td>
            <td>${escapeHtml(part.required_product.description)}</td>
            <td class="text-end">${part.quantity}</td>
            <td>${finishPolicyBadge}</td>
            <td>${optionalBadge}</td>
            <td><small>${part.notes ? escapeHtml(part.notes) : '-'}</small></td>
            <td>
              <div class="btn-group">
                <button class="btn btn-sm btn-ghost-primary" onclick="editRequiredPart(${part.id})" data-permission="inventory.edit">
                  <i class="ti ti-edit"></i>
                </button>
                <button class="btn btn-sm btn-ghost-danger" onclick="deleteRequiredPart(${part.id})" data-permission="inventory.delete">
                  <i class="ti ti-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join('');

      // Apply action permissions to dynamically created buttons
      if (typeof applyActionPermissions === 'function') {
        applyActionPermissions();
      }
    }

    function getFinishPolicyBadge(policy, specificFinish) {
      const badges = {
        'same_as_parent': '<span class="badge text-bg-primary">Same as Parent</span>',
        'specific': `<span class="badge text-bg-info">Specific: ${specificFinish || '?'}</span>`,
        'any': '<span class="badge text-bg-secondary">Any</span>',
      };
      return badges[policy] || policy;
    }

    async function loadWhereUsed(productId) {
      try {
        const response = await apiCall(`/products/${productId}/where-used`);
        const data = await response.json();
        const whereUsed = Array.isArray(data) ? data : (data.data || []);

        const container = document.getElementById('whereUsedContent');

        if (whereUsed.length === 0) {
          container.innerHTML = '<p class="text-muted">This part is not used in any other products</p>';
          return;
        }

        container.innerHTML = `
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Product SKU</th>
                  <th>Description</th>
                  <th class="text-end">Quantity</th>
                  <th>Finish Policy</th>
                </tr>
              </thead>
              <tbody>
                ${whereUsed.map(item => `
                  <tr>
                    <td>${escapeHtml(item.sku)}</td>
                    <td>${escapeHtml(item.description)}</td>
                    <td class="text-end">${item.quantity}</td>
                    <td>${getFinishPolicyBadge(item.finish_policy)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
      } catch (error) {
        console.error('Error loading where-used:', error);
        document.getElementById('whereUsedContent').innerHTML = '<p class="text-danger">Error loading where-used information</p>';
      }
    }

    function showAddRequiredPartForm() {
      document.getElementById('requiredPartId').value = '';
      document.getElementById('requiredPartForm').reset();
      document.getElementById('addRequiredPartForm').style.display = 'block';
      document.getElementById('specificFinishGroup').style.display = 'none';

      // Load product list for selector
      loadProductsForSelector();
    }

    function hideRequiredPartForm() {
      document.getElementById('addRequiredPartForm').style.display = 'none';
    }

    async function loadProductsForSelector() {
      try {
        const response = await apiCall('/products?per_page=all&is_active=1');
        allProducts = response.data || response;

        const select = document.getElementById('requiredProductId');
        select.innerHTML = '<option value="">Search and select...</option>';

        allProducts.forEach(product => {
          const option = document.createElement('option');
          option.value = product.id;
          option.textContent = `${product.sku} - ${product.description}`;
          select.appendChild(option);
        });
      } catch (error) {
        console.error('Error loading products:', error);
      }
    }

    // Handle finish policy change
    document.addEventListener('DOMContentLoaded', () => {
      const finishPolicySelect = document.getElementById('requiredFinishPolicy');
      if (finishPolicySelect) {
        finishPolicySelect.addEventListener('change', function() {
          const specificGroup = document.getElementById('specificFinishGroup');
          if (this.value === 'specific') {
            specificGroup.style.display = 'block';
            // Populate finish codes
            const finishSelect = document.getElementById('requiredSpecificFinish');
            finishSelect.innerHTML = '<option value="">Select...</option>';
            Object.entries(finishCodes).forEach(([code, name]) => {
              const option = document.createElement('option');
              option.value = code;
              option.textContent = `${code} - ${name}`;
              finishSelect.appendChild(option);
            });
          } else {
            specificGroup.style.display = 'none';
          }
        });
      }

      // Handle form submission
      const requiredPartForm = document.getElementById('requiredPartForm');
      if (requiredPartForm) {
        requiredPartForm.addEventListener('submit', handleRequiredPartSubmit);
      }
    });

    async function handleRequiredPartSubmit(e) {
      e.preventDefault();

      const formData = {
        required_product_id: parseInt(document.getElementById('requiredProductId').value),
        quantity: parseFloat(document.getElementById('requiredQuantity').value),
        finish_policy: document.getElementById('requiredFinishPolicy').value,
        specific_finish: document.getElementById('requiredSpecificFinish').value || null,
        is_optional: document.getElementById('requiredIsOptional').checked,
        notes: document.getElementById('requiredNotes').value || null,
        sort_order: parseInt(document.getElementById('requiredSortOrder').value) || 0,
      };

      try {
        const partId = document.getElementById('requiredPartId').value;

        if (partId) {
          // Update existing part
          await apiCall(`/products/${currentProductId}/required-parts/${partId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData),
          });
          showNotification('Required part updated successfully', 'success');
        } else {
          // Add new part
          await apiCall(`/products/${currentProductId}/required-parts`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData),
          });
          showNotification('Required part added successfully', 'success');
        }

        hideRequiredPartForm();
        loadProductBOM(currentProductId);
      } catch (error) {
        console.error('Error saving required part:', error);
        showNotification(error.message || 'Error saving required part', 'danger');
      }
    }

    async function editRequiredPart(partId) {
      const part = currentBOM.find(p => p.id === partId);
      if (!part) return;

      document.getElementById('requiredPartId').value = part.id;
      document.getElementById('requiredProductId').value = part.required_product_id;
      document.getElementById('requiredQuantity').value = part.quantity;
      document.getElementById('requiredFinishPolicy').value = part.finish_policy;
      document.getElementById('requiredSpecificFinish').value = part.specific_finish || '';
      document.getElementById('requiredIsOptional').checked = part.is_optional;
      document.getElementById('requiredNotes').value = part.notes || '';
      document.getElementById('requiredSortOrder').value = part.sort_order || 0;

      if (part.finish_policy === 'specific') {
        document.getElementById('specificFinishGroup').style.display = 'block';
      }

      document.getElementById('addRequiredPartForm').style.display = 'block';
      await loadProductsForSelector();
    }

    async function deleteRequiredPart(partId) {
      if (!confirm('Are you sure you want to remove this part from the BOM?')) {
        return;
      }

      try {
        await apiCall(`/products/${currentProductId}/required-parts/${partId}`, {
          method: 'DELETE',
        });
        showNotification('Required part removed successfully', 'success');
        loadProductBOM(currentProductId);
      } catch (error) {
        console.error('Error deleting required part:', error);
        showNotification(error.message || 'Error deleting required part', 'danger');
      }
    }

    async function explodeBOM(productId) {
      try {
        const response = await apiCall(`/products/${productId}/bom-explosion?quantity=1`);

        // Display explosion results in a modal or alert
        let message = `BOM Explosion for ${response.product.sku}\n\n`;
        message += `Total unique parts: ${response.total_parts}\n\n`;
        message += 'Summary:\n';
        response.summary.forEach(part => {
          message += `- ${part.sku}: ${part.total_quantity} ${part.finish ? '(' + part.finish + ')' : ''}\n`;
        });

        alert(message);
      } catch (error) {
        console.error('Error exploding BOM:', error);
        showNotification('Error exploding BOM', 'danger');
      }
    }

    async function checkBOMAvailability(productId) {
      try {
        const response = await apiCall(`/products/${productId}/bom-availability?quantity=1`);

        let message = `BOM Availability Check\n\n`;
        message += response.all_available ? '✓ All parts available!\n\n' : '⚠ Some parts not available\n\n';

        response.parts.forEach(part => {
          const icon = part.is_available ? '✓' : '✗';
          message += `${icon} ${part.sku}: Need ${part.required}, Have ${part.available}`;
          if (part.shortage > 0) {
            message += ` (Short: ${part.shortage})`;
          }
          message += '\n';
        });

        alert(message);
      } catch (error) {
        console.error('Error checking availability:', error);
        showNotification('Error checking BOM availability', 'danger');
      }
    }

    async function loadReservationStatistics(productId) {
      try {
        const response = await apiCall(`/products/${productId}/reservations/statistics`);
        const stats = await response.json();

        document.getElementById('statActiveReservations').textContent = stats.active_reservations_count;
        document.getElementById('statQuantityCommitted').textContent = stats.quantity_committed.toLocaleString();
        document.getElementById('statATP').textContent = stats.atp.toLocaleString();
        document.getElementById('statOverdueReservations').textContent = stats.overdue_reservations;
        document.getElementById('statUpcomingReservations').textContent = stats.upcoming_reservations;

        // Update available for reservation message
        document.getElementById('availableForReservation').textContent =
          `${stats.atp} units available to reserve`;
      } catch (error) {
        console.error('Error loading reservation statistics:', error);
      }
    }

    async function loadAllJobs() {
      try {
        const response = await apiCall('/jobs');
        const jobs = await response.json();

        const datalist = document.getElementById('existingJobs');
        datalist.innerHTML = '';
        jobs.forEach(job => {
          const option = document.createElement('option');
          option.value = job.job_number;
          option.setAttribute('data-name', job.job_name || '');
          datalist.appendChild(option);
        });
      } catch (error) {
        console.error('Error loading jobs:', error);
      }
    }

    function renderReservationsTable() {
      const tbody = document.getElementById('reservationsTableBody');

      // Filter reservations based on current filter
      let filteredReservations = currentProductReservations;
      if (currentReservationFilter !== 'all') {
        filteredReservations = currentProductReservations.filter(r => r.status === currentReservationFilter);
      }

      if (filteredReservations.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="8" class="text-center text-muted py-5">
              <i class="ti ti-clipboard-check" style="font-size: 2rem;"></i>
              <p class="mb-0">No ${currentReservationFilter !== 'all' ? currentReservationFilter : ''} reservations</p>
              ${currentReservationFilter === 'all' ? '<button class="btn btn-sm btn-primary mt-2" onclick="showAddReservationForm()">Reserve First Item</button>' : ''}
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = '';
      filteredReservations.forEach(reservation => {
        const statusBadge = getReservationStatusBadge(reservation.status);
        const remaining = reservation.quantity_reserved - reservation.quantity_fulfilled;
        const reservedBy = reservation.reserved_by ? `by ${reservation.reserved_by.name}` : '';
        const requiredDate = reservation.required_date ? new Date(reservation.required_date).toLocaleDateString() : '-';
        const isOverdue = reservation.required_date && new Date(reservation.required_date) < new Date() && (reservation.status === 'active' || reservation.status === 'partially_fulfilled');

        const row = `
          <tr ${isOverdue ? 'class="table-danger"' : ''}>
            <td>
              <strong>${reservation.job_number}</strong>
              ${reservation.job_name ? `<br><small class="text-muted">${reservation.job_name}</small>` : ''}
              ${reservedBy ? `<br><small class="text-muted">${reservedBy}</small>` : ''}
            </td>
            <td>${new Date(reservation.reserved_date).toLocaleDateString()}</td>
            <td>${requiredDate}${isOverdue ? ' <span class="badge text-bg-danger">OVERDUE</span>' : ''}</td>
            <td class="text-end">${reservation.quantity_reserved}</td>
            <td class="text-end">${reservation.quantity_fulfilled}</td>
            <td class="text-end"><strong>${remaining}</strong></td>
            <td>${statusBadge}</td>
            <td class="table-actions">
              <div class="btn-group">
                ${(reservation.status === 'active' || reservation.status === 'partially_fulfilled') ? `
                  <button class="btn btn-sm btn-icon btn-ghost-success" onclick="showFulfillModal(${reservation.id})" title="Fulfill" data-permission="orders.edit">
                    <i class="ti ti-check"></i>
                  </button>
                  <button class="btn btn-sm btn-icon btn-ghost-warning" onclick="releaseReservation(${reservation.id})" title="Release/Cancel" data-permission="orders.edit">
                    <i class="ti ti-x"></i>
                  </button>
                  <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="editReservation(${reservation.id})" title="Edit" data-permission="orders.edit">
                    <i class="ti ti-edit"></i>
                  </button>
                ` : ''}
                ${(reservation.status === 'fulfilled' || reservation.status === 'cancelled') ? `
                  <button class="btn btn-sm btn-icon btn-ghost-danger" onclick="deleteReservation(${reservation.id})" title="Delete" data-permission="orders.delete">
                    <i class="ti ti-trash"></i>
                  </button>
                ` : ''}
              </div>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });

      // Apply action permissions to dynamically created buttons
      if (typeof applyActionPermissions === 'function') {
        applyActionPermissions();
      }
    }

    function getReservationStatusBadge(status) {
      const badges = {
        'active': '<span class="badge text-bg-warning">Active</span>',
        'partially_fulfilled': '<span class="badge text-bg-info">Partially Fulfilled</span>',
        'fulfilled': '<span class="badge text-bg-success">Fulfilled</span>',
        'cancelled': '<span class="badge text-bg-secondary">Cancelled</span>'
      };
      return badges[status] || badges['active'];
    }

    function filterReservations(filter) {
      currentReservationFilter = filter;

      // Update active tab
      document.querySelectorAll('#reservationFilterTabs .nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('data-filter') === filter) {
          link.classList.add('active');
        }
      });

      renderReservationsTable();
    }

    function showAddReservationForm() {
      document.getElementById('reservationFormTitle').textContent = 'Reserve Inventory';
      document.getElementById('reservationForm').reset();
      document.getElementById('reservationId').value = '';
      document.getElementById('reservationDate').valueAsDate = new Date();
      document.getElementById('reservationFormCard').style.display = 'block';
      document.getElementById('reservationJobNumber').focus();
    }

    function hideReservationForm() {
      document.getElementById('reservationFormCard').style.display = 'none';
      document.getElementById('reservationForm').reset();
    }

    function editReservation(reservationId) {
      const reservation = currentProductReservations.find(r => r.id === reservationId);
      if (!reservation) return;

      document.getElementById('reservationFormTitle').textContent = 'Edit Reservation';
      document.getElementById('reservationId').value = reservation.id;
      document.getElementById('reservationJobNumber').value = reservation.job_number;
      document.getElementById('reservationJobName').value = reservation.job_name || '';
      document.getElementById('reservationQuantity').value = reservation.quantity_reserved;
      document.getElementById('reservationDate').value = reservation.reserved_date;
      document.getElementById('reservationRequiredDate').value = reservation.required_date || '';
      document.getElementById('reservationNotes').value = reservation.notes || '';
      document.getElementById('reservationFormCard').style.display = 'block';
      document.getElementById('reservationJobNumber').focus();
    }

    async function releaseReservation(reservationId) {
      const reservation = currentProductReservations.find(r => r.id === reservationId);
      if (!reservation) return;

      const notes = prompt(`Release reservation for ${reservation.job_number}?\n\nEnter notes (optional):`);
      if (notes === null) return; // User cancelled

      try {
        const response = await apiCall(`/products/${currentProductId}/reservations/${reservationId}/release`, {
          method: 'POST',
          body: JSON.stringify({ notes: notes || '' })
        });

        if (response.ok) {
          showNotification('Reservation released successfully', 'success');
          await loadProductReservations(currentProductId);
          await loadDashboard(); // Refresh main dashboard
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to release reservation', 'danger');
        }
      } catch (error) {
        console.error('Error releasing reservation:', error);
        showNotification('Failed to release reservation', 'danger');
      }
    }

    function showFulfillModal(reservationId) {
      const reservation = currentProductReservations.find(r => r.id === reservationId);
      if (!reservation) return;

      const remaining = reservation.quantity_reserved - reservation.quantity_fulfilled;
      const quantity = prompt(`Fulfill reservation for ${reservation.job_number}\n\nRemaining: ${remaining} units\nEnter quantity to fulfill:`);

      if (quantity === null) return; // User cancelled

      const qtyNum = parseInt(quantity);
      if (isNaN(qtyNum) || qtyNum <= 0) {
        showNotification('Please enter a valid quantity', 'warning');
        return;
      }

      fulfillReservation(reservationId, qtyNum);
    }

    async function fulfillReservation(reservationId, quantity) {
      try {
        const response = await apiCall(`/products/${currentProductId}/reservations/${reservationId}/fulfill`, {
          method: 'POST',
          body: JSON.stringify({
            quantity_fulfilled: quantity,
            notes: `Fulfilled ${quantity} units`
          })
        });

        if (response.ok) {
          showNotification('Reservation fulfilled successfully', 'success');
          await loadProductReservations(currentProductId);
          await loadDashboard(); // Refresh main dashboard
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to fulfill reservation', 'danger');
        }
      } catch (error) {
        console.error('Error fulfilling reservation:', error);
        showNotification('Failed to fulfill reservation', 'danger');
      }
    }

    async function deleteReservation(reservationId) {
      const reservation = currentProductReservations.find(r => r.id === reservationId);
      if (!reservation) return;

      if (!confirm(`Delete reservation for ${reservation.job_number}?\n\nThis action cannot be undone.`)) {
        return;
      }

      try {
        const response = await apiCall(`/products/${currentProductId}/reservations/${reservationId}`, {
          method: 'DELETE'
        });

        if (response.ok) {
          showNotification('Reservation deleted successfully', 'success');
          await loadProductReservations(currentProductId);
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to delete reservation', 'danger');
        }
      } catch (error) {
        console.error('Error deleting reservation:', error);
        showNotification('Failed to delete reservation', 'danger');
      }
    }

    // Reservation Form Submit
    document.getElementById('reservationForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      const reservationId = document.getElementById('reservationId').value;
      const formData = {
        job_number: document.getElementById('reservationJobNumber').value,
        job_name: document.getElementById('reservationJobName').value,
        quantity_reserved: parseInt(document.getElementById('reservationQuantity').value),
        reserved_date: document.getElementById('reservationDate').value,
        required_date: document.getElementById('reservationRequiredDate').value || null,
        notes: document.getElementById('reservationNotes').value
      };

      try {
        const saveBtn = document.getElementById('saveReservationBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        let response;
        if (reservationId) {
          // Update existing reservation
          response = await apiCall(`/products/${currentProductId}/reservations/${reservationId}`, {
            method: 'PUT',
            body: JSON.stringify(formData)
          });
        } else {
          // Create new reservation
          response = await apiCall(`/products/${currentProductId}/reservations`, {
            method: 'POST',
            body: JSON.stringify(formData)
          });
        }

        if (response.ok) {
          showNotification(reservationId ? 'Reservation updated successfully' : 'Inventory reserved successfully', 'success');
          hideReservationForm();
          await loadProductReservations(currentProductId);
          await loadDashboard(); // Refresh main dashboard
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to save reservation', 'danger');
        }
      } catch (error) {
        console.error('Error saving reservation:', error);
        showNotification('Failed to save reservation', 'danger');
      } finally {
        const saveBtn = document.getElementById('saveReservationBtn');
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="ti ti-check me-1"></i>Reserve';
      }
    });

    // ========== CONFIGURATION DATA ==========
    let finishCodes = [];
    let unitOfMeasures = [];
    let categories = [];
    let suppliers = [];

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

        // Load categories
        const categoriesResponse = await apiCall('/categories?per_page=all&with_parent=true');
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
    function updateSkuPreview() {
      const partNumber = document.getElementById('productPartNumber').value.trim().toUpperCase();
      const finish = document.getElementById('productFinish').value;
      const skuField = document.getElementById('productSku');
      const skuPreview = document.getElementById('skuPreview');

      if (partNumber) {
        const generatedSku = finish ? `${partNumber}-${finish}` : partNumber;
        skuPreview.textContent = `Will generate: ${generatedSku}`;
        skuPreview.classList.add('text-primary');

        // Auto-fill SKU if empty
        if (!skuField.value) {
          skuField.value = generatedSku;
        }
      } else {
        skuPreview.textContent = '';
        skuPreview.classList.remove('text-primary');
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

    // Sort column click handlers
    document.querySelectorAll('.sortable').forEach(th => {
      th.addEventListener('click', function() {
        const column = this.dataset.sort;
        if (column) {
          sortByColumn(column);
        }
      });
    });
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
