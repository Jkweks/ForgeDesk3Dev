@extends('layouts.app')

@section('title', 'Jobs Management - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Production</div>
              <h1 class="page-title">Jobs & Projects</h1>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn btn-primary" onclick="showAddJobModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Add Job
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
              <div class="card stat-card" id="statCardAll" style="cursor:pointer;" onclick="filterByStatCard('')" title="Show all jobs">
                <div class="card-body">
                  <div class="subheader">Total Jobs</div>
                  <div class="h1 mb-3" id="statTotalJobs">-</div>
                  <div class="text-muted">All jobs in system</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card stat-card" id="statCardActive" style="cursor:pointer;" onclick="filterByStatCard('active')" title="Filter by Active">
                <div class="card-body">
                  <div class="subheader">Active Jobs</div>
                  <div class="h1 mb-3 text-success" id="statActiveJobs">-</div>
                  <div class="text-muted">In progress</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card stat-card" id="statCardOnHold" style="cursor:pointer;" onclick="filterByStatCard('on_hold')" title="Filter by On Hold">
                <div class="card-body">
                  <div class="subheader">On Hold</div>
                  <div class="h1 mb-3 text-warning" id="statOnHoldJobs">-</div>
                  <div class="text-muted">Pending action</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card stat-card" id="statCardCompleted" style="cursor:pointer;" onclick="filterByStatCard('completed')" title="Filter by Completed">
                <div class="card-body">
                  <div class="subheader">Completed</div>
                  <div class="h1 mb-3 text-info" id="statCompletedJobs">-</div>
                  <div class="text-muted">Finished</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Job Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Jobs</h3>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
                      <option value="">All Status</option>
                      <option value="active">Active</option>
                      <option value="on_hold">On Hold</option>
                      <option value="completed">Completed</option>
                      <option value="cancelled">Cancelled</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" placeholder="Search jobs..." id="searchInput" style="width: 250px;">
                  </div>
                </div>
                <div class="card-body">
                  <div class="loading" id="loadingIndicator">
                    <div class="text-muted">Loading jobs...</div>
                  </div>

                  <div id="jobsTable" style="display: none;">
                    <div class="table-responsive">
                      <table class="table table-vcenter card-table table-striped">
                        <thead>
                          <tr>
                            <th style="cursor:pointer;" onclick="sortJobs('job_number')">Job Number <span id="sort_job_number"></span></th>
                            <th>Job Name</th>
                            <th style="cursor:pointer;" onclick="sortJobs('customer_name')">Customer <span id="sort_customer_name"></span></th>
                            <th style="cursor:pointer;" onclick="sortJobs('status')">Status <span id="sort_status"></span></th>
                            <th>Reservations</th>
                            <th style="cursor:pointer;" onclick="sortJobs('start_date')">Start Date <span id="sort_start_date"></span></th>
                            <th style="cursor:pointer;" onclick="sortJobs('target_completion_date')">Target Completion <span id="sort_target_completion_date"></span></th>
                            <th style="cursor:pointer;" onclick="sortJobs('days_until_completion')">Days Remaining <span id="sort_days_until_completion"></span></th>
                            <th class="w-1">Actions</th>
                          </tr>
                        </thead>
                        <tbody id="jobsTableBody">
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div id="emptyState" style="display: none;">
                    <div class="empty">
                      <div class="empty-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /></svg>
                      </div>
                      <p class="empty-title">No jobs found</p>
                      <p class="empty-subtitle text-muted">
                        Get started by creating your first job
                      </p>
                      <div class="empty-action">
                        <button class="btn btn-primary" onclick="showAddJobModal()">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                          Add Job
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Add/Edit Job Modal -->
    <div class="modal fade" id="jobModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="jobModalTitle">Add Job</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="jobForm">
              <input type="hidden" id="jobId">

              <!-- Basic Information -->
              <div class="card mb-3">
                <div class="card-header">
                  <h3 class="card-title">Basic Information</h3>
                </div>
                <div class="card-body">
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label required">Job Number</label>
                      <input type="text" class="form-control" id="jobNumber" required>
                      <small class="form-hint">Unique identifier for this job</small>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label required">Job Name</label>
                      <input type="text" class="form-control" id="jobName" required>
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Customer Name</label>
                    <input type="text" class="form-control" id="customerName">
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Site Address</label>
                    <textarea class="form-control" id="siteAddress" rows="2"></textarea>
                  </div>
                </div>
              </div>

              <!-- Contact Information -->
              <div class="card mb-3">
                <div class="card-header">
                  <h3 class="card-title">Contact Information</h3>
                </div>
                <div class="card-body">
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label">Contact Name</label>
                      <input type="text" class="form-control" id="contactName">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Contact Phone</label>
                      <input type="tel" class="form-control" id="contactPhone">
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Contact Email</label>
                    <input type="email" class="form-control" id="contactEmail">
                  </div>
                </div>
              </div>

              <!-- Project Details -->
              <div class="card mb-3">
                <div class="card-header">
                  <h3 class="card-title">Project Details</h3>
                </div>
                <div class="card-body">
                  <div class="row mb-3">
                    <div class="col-md-4">
                      <label class="form-label">Status</label>
                      <select class="form-select" id="status">
                        <option value="active">Active</option>
                        <option value="on_hold">On Hold</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Start Date</label>
                      <input type="date" class="form-control" id="startDate">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Target Completion</label>
                      <input type="date" class="form-control" id="targetCompletionDate">
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" rows="3"></textarea>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div id="jobModalError" class="alert alert-danger mx-3 mb-0" style="display:none;"></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveJob()">Save Job</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Job Details Modal -->
    <div class="modal modal-blur fade" id="jobDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="jobDetailsModalTitle">Job Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Job Header Info -->
            <div class="card mb-3">
              <div class="card-body">
                <div class="row">
                  <div class="col-md-3">
                    <div class="subheader">Job Number</div>
                    <div class="h3 mb-0" id="detailJobNumber">-</div>
                  </div>
                  <div class="col-md-4">
                    <div class="subheader">Job Name</div>
                    <div class="h3 mb-0" id="detailJobName">-</div>
                  </div>
                  <div class="col-md-3">
                    <div class="subheader">Customer</div>
                    <div class="h4 mb-0 text-muted" id="detailCustomer">-</div>
                  </div>
                  <div class="col-md-2">
                    <div class="subheader">Status</div>
                    <div id="detailStatus"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-3" id="jobTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="reservations-tab" data-bs-toggle="tab" data-bs-target="#reservations" type="button" role="tab">
                  <i class="ti ti-clipboard-check me-1"></i>Reservations <span class="badge bg-info ms-1" id="reservationsCount">0</span>
                </button>
              </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="jobTabContent">
              <!-- Reservations Tab -->
              <div class="tab-pane fade show active" id="reservations" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h3 class="mb-0">Material Reservations</h3>
                    <p class="text-muted mb-0">Manage inventory reservations for this job</p>
                  </div>
                  <div class="btn-group">
                    <button class="btn btn-success" onclick="showMaterialCheckModal()">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11l3 3l8 -8" /><path d="M20 12v6a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h9" /></svg>
                      Material Check
                    </button>
                    <button class="btn btn-primary" onclick="showAddReservationModal()">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                      Manual Reservation
                    </button>
                  </div>
                </div>

                <div id="reservationsLoading" class="text-center text-muted py-5">
                  <div class="spinner-border" role="status"></div>
                  <p class="mt-2">Loading reservations...</p>
                </div>

                <div id="reservationsContent" style="display: none;">
                  <div class="table-responsive">
                    <table class="table table-vcenter card-table table-striped">
                      <thead>
                        <tr>
                          <th>Res #</th>
                          <th>Status</th>
                          <th>Requested By</th>
                          <th>Needed By</th>
                          <th>Items</th>
                          <th>Committed</th>
                          <th>Consumed</th>
                          <th>Created</th>
                          <th class="w-1">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="reservationsTableBody">
                      </tbody>
                    </table>
                  </div>
                </div>

                <div id="reservationsEmpty" style="display: none;" class="empty">
                  <div class="empty-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /></svg>
                  </div>
                  <p class="empty-title">No reservations yet</p>
                  <p class="empty-subtitle text-muted">
                    Create your first reservation for this job
                  </p>
                  <div class="empty-action">
                    <button class="btn btn-primary" onclick="showAddReservationModal()">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                      New Reservation
                    </button>
                  </div>
                </div>
              </div>

            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Reservation Modal -->
    <div class="modal fade" id="addReservationModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">New Manual Reservation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="addReservationForm">
              <input type="hidden" id="reservationJobId">

              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label required">Requested By</label>
                  <input type="text" class="form-control" id="reservationRequestedBy" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Needed By</label>
                  <input type="date" class="form-control" id="reservationNeededBy">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" id="reservationNotes" rows="2"></textarea>
              </div>

              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Items to Reserve</h3>
                  <div class="card-actions">
                    <button type="button" class="btn btn-sm btn-primary" onclick="showNewResAddItemForm()" id="newResAddItemBtn">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-sm"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                      Add Item
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <!-- Inline add item form -->
                  <div id="newResAddItemForm" class="mb-3" style="display: none;">
                    <div class="card bg-light">
                      <div class="card-body">
                        <h4 class="card-title">Add Item</h4>
                        <div class="row">
                          <div class="col-md-8">
                            <div class="mb-3">
                              <label class="form-label">Product (Search by SKU, Part #, or Description)</label>
                              <input type="text" class="form-control" id="newResItemSearch" placeholder="Type to search...">
                              <input type="hidden" id="newResItemProductId">
                            </div>
                            <div id="newResSearchResults" class="list-group mb-3" style="max-height: 200px; overflow-y: auto; display: none;"></div>
                            <div id="newResSelectedProduct" style="display: none;" class="alert alert-success mb-3">
                              <strong>Selected:</strong> <span id="newResProductInfo"></span>
                            </div>
                          </div>
                          <div class="col-md-2">
                            <div class="mb-3">
                              <label class="form-label">Requested Qty</label>
                              <input type="number" class="form-control" id="newResItemRequestedQty" min="1" value="1">
                            </div>
                          </div>
                          <div class="col-md-2">
                            <div class="mb-3">
                              <label class="form-label">Committed Qty</label>
                              <input type="number" class="form-control" id="newResItemCommittedQty" min="0" value="0">
                              <small class="form-hint">0 = auto</small>
                            </div>
                          </div>
                        </div>
                        <div class="d-flex gap-2">
                          <button type="button" class="btn btn-success" onclick="addItemToNewReservation()">
                            <i class="ti ti-check me-1"></i>Add Item
                          </button>
                          <button type="button" class="btn" onclick="hideNewResAddItemForm()">Cancel</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Items display -->
                  <div id="reservationItemsContainer">
                    <p class="text-muted">No items added yet. Click "Add Item" to begin.</p>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="createJobReservation()">Create Reservation</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Material Check Modal -->
    <div class="modal modal-blur fade" id="materialCheckModal" tabindex="-1">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Material Check - Upload EZ Estimate</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Upload Section -->
            <div class="card mb-3">
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label">Select EZ Estimate File (.xlsx or .xlsm)</label>
                  <input type="file" class="form-control" id="materialCheckFile" accept=".xlsx,.xlsm">
                  <small class="form-hint">Upload an EZ Estimate file to check material availability</small>
                </div>
                <button class="btn btn-primary" onclick="checkJobMaterials()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11l3 3l8 -8" /><path d="M20 12v6a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h9" /></svg>
                  Check Materials
                </button>
              </div>
            </div>

            <!-- Results Section -->
            <div id="materialCheckResults" style="display: none;">
              <!-- Summary Cards -->
              <div class="row row-deck row-cards mb-3">
                <div class="col-sm-6 col-lg-3">
                  <div class="card card-sm">
                    <div class="card-body">
                      <div class="subheader">Total Items</div>
                      <div class="h2 text-primary" id="mcStatTotal">0</div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                  <div class="card card-sm">
                    <div class="card-body">
                      <div class="subheader">Available</div>
                      <div class="h2 text-success" id="mcStatAvailable">0</div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                  <div class="card card-sm">
                    <div class="card-body">
                      <div class="subheader">Partial</div>
                      <div class="h2 text-warning" id="mcStatPartial">0</div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                  <div class="card card-sm">
                    <div class="card-body">
                      <div class="subheader">Unavailable</div>
                      <div class="h2 text-danger" id="mcStatUnavailable">0</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Results Table -->
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Material Check Results</h3>
                  <div class="ms-auto">
                    <button class="btn btn-success btn-sm" id="mcCommitButton" onclick="showCommitModal()" style="display: none;">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                      Create Reservation from Selected
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-vcenter table-sm">
                      <thead>
                        <tr>
                          <th width="40"><input type="checkbox" id="mcSelectAll" onchange="toggleMcSelectAll()"></th>
                          <th>Status</th>
                          <th>Part #</th>
                          <th>Finish</th>
                          <th>SKU</th>
                          <th>Description</th>
                          <th>Required</th>
                          <th>Available</th>
                          <th>Shortage</th>
                        </tr>
                      </thead>
                      <tbody id="materialCheckTableBody">
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Commit Materials Modal -->
    <div class="modal fade" id="commitMaterialsModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Create Reservation from Materials</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required">Requested By</label>
              <input type="text" class="form-control" id="mcRequestedBy" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Needed By</label>
              <input type="date" class="form-control" id="mcNeededBy">
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="mcNotes" rows="3"></textarea>
            </div>
            <div class="alert alert-info">
              <strong id="mcSelectedCount">0</strong> items selected for reservation
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" onclick="commitJobMaterials()">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
              Create Reservation
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reservation Detail Modal -->
    <div class="modal modal-blur fade" id="reservationDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="reservationDetailTitle">Reservation Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="reservationDetailBody">
            <!-- Loaded dynamically -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <div id="reservationDetailActions" class="d-flex gap-2"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Reservation Status Change Modal -->
    <div class="modal modal-blur fade" id="reservationStatusModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Change Reservation Status</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="resStatusChangeId">
            <div class="mb-3">
              <label class="form-label">New Status</label>
              <select class="form-select" id="resNewStatus">
                <option value="active">Active</option>
                <option value="in_progress">In Progress</option>
                <option value="fulfilled">Fulfilled</option>
                <option value="on_hold">On Hold</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div id="resStatusWarnings" class="alert alert-warning" style="display: none;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="confirmReservationStatusChange()">Change Status</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Complete Reservation Modal -->
    <div class="modal modal-blur fade" id="reservationCompleteModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Complete Reservation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="completeResId">
            <div class="alert alert-info">
              Enter the actual consumed quantities for each item. Items will be deducted from inventory.
            </div>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Part #</th>
                    <th>Finish</th>
                    <th>Committed</th>
                    <th>Already Consumed</th>
                    <th>Actual Consumed</th>
                    <th>To Release</th>
                  </tr>
                </thead>
                <tbody id="completeResItemsTable"></tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" onclick="confirmReservationComplete()">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
              Complete Reservation
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Reservation Modal -->
    <div class="modal modal-blur fade" id="editReservationModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Reservation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="editResId">

            <div class="card mb-3">
              <div class="card-header">
                <h3 class="card-title">Reservation Details</h3>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Requested By</label>
                      <input type="text" class="form-control" id="editResRequestedBy" placeholder="Requester name">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Needed By</label>
                      <input type="date" class="form-control" id="editResNeededBy">
                    </div>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Notes</label>
                  <textarea class="form-control" id="editResNotes" rows="2" placeholder="Add any notes..."></textarea>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Line Items</h3>
                <div class="card-actions">
                  <button type="button" class="btn btn-sm btn-primary" onclick="showEditResAddItemForm()" id="editResAddItemBtn">
                    <i class="ti ti-plus me-1"></i>Add Item
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div id="editResAddItemFormContainer" class="mb-3" style="display: none;">
                  <div class="card bg-light">
                    <div class="card-body">
                      <h4 class="card-title">Add New Item</h4>
                      <div class="row">
                        <div class="col-md-6">
                          <label class="form-label">Product (Search by SKU, Part #, or Description)</label>
                          <input type="text" class="form-control" id="editResNewItemSearch" placeholder="Type to search...">
                          <input type="hidden" id="editResNewItemProductId">
                          <div id="editResNewItemSearchResults" class="list-group mt-1" style="max-height: 200px; overflow-y: auto; display: none;"></div>
                          <div id="editResNewItemSelected" style="display: none;" class="alert alert-success mt-2 mb-0 py-2">
                            <strong>Selected:</strong> <span id="editResNewItemInfo"></span>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <label class="form-label">Requested Qty</label>
                          <input type="number" class="form-control" id="editResNewItemRequestedQty" min="1" value="1">
                        </div>
                        <div class="col-md-3">
                          <label class="form-label">Committed Qty</label>
                          <input type="number" class="form-control" id="editResNewItemCommittedQty" min="0" value="0">
                          <small class="form-hint">0 = auto</small>
                        </div>
                      </div>
                      <div class="d-flex gap-2 mt-2">
                        <button type="button" class="btn btn-success" onclick="addItemToEditReservation()">
                          <i class="ti ti-check me-1"></i>Add Item
                        </button>
                        <button type="button" class="btn" onclick="hideEditResAddItemForm()">Cancel</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm table-hover">
                    <thead>
                      <tr>
                        <th>SKU</th>
                        <th>Part #</th>
                        <th>Finish</th>
                        <th>Description</th>
                        <th class="text-end">Requested</th>
                        <th class="text-end">Committed</th>
                        <th class="text-end">Consumed</th>
                        <th class="text-end">On Hand</th>
                        <th class="text-center">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="editResItemsBody"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveEditedReservation()">
              <i class="ti ti-device-floppy me-1"></i>Save Changes
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Replace Item Modal -->
    <div class="modal modal-blur fade" id="editReplaceItemModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Replace Item</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="editReplaceItemIndex">
            <div class="alert alert-info">
              <strong>Replacing:</strong> <span id="editReplaceOldProduct"></span>
            </div>
            <div class="mb-3">
              <label class="form-label">Search for Replacement Product (by SKU, Part #, or Description)</label>
              <input type="text" class="form-control" id="editReplaceProductSearch" placeholder="Type to search...">
              <input type="hidden" id="editReplaceNewProductId">
            </div>
            <div id="editReplaceSearchResults" class="list-group mb-3" style="max-height: 300px; overflow-y: auto; display: none;"></div>
            <div id="editReplaceSelectedProduct" style="display: none;">
              <div class="card bg-light">
                <div class="card-body">
                  <h4 class="card-title">Selected Replacement</h4>
                  <div id="editReplaceProductDetails"></div>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Reason for Replacement (Optional)</label>
              <textarea class="form-control" id="editReplaceReason" rows="2" placeholder="e.g., Finish color change requested"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="confirmEditReplaceItem()">
              <i class="ti ti-replace me-1"></i>Replace Item
            </button>
          </div>
        </div>
      </div>
    </div>

    <script>
        let allJobs = [];
        let currentJob = null;

        document.addEventListener('DOMContentLoaded', function() {
            loadJobs();

            // Search and filter
            document.getElementById('searchInput').addEventListener('input', filterJobs);
            document.getElementById('filterStatus').addEventListener('change', filterJobs);
        });

        async function loadJobs() {
            try {
                document.getElementById('loadingIndicator').style.display = 'block';
                document.getElementById('jobsTable').style.display = 'none';
                document.getElementById('emptyState').style.display = 'none';

                const response = await fetch('/api/v1/business-jobs', {
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load jobs');
                }

                const data = await response.json();
                allJobs = data.jobs;

                updateStats();
                displayJobs(allJobs);

                document.getElementById('loadingIndicator').style.display = 'none';

                if (allJobs.length === 0) {
                    document.getElementById('emptyState').style.display = 'block';
                } else {
                    document.getElementById('jobsTable').style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading jobs:', error);
                showToast('Failed to load jobs', 'danger');
                document.getElementById('loadingIndicator').style.display = 'none';
            }
        }

        function updateStats() {
            const total = allJobs.length;
            const active = allJobs.filter(j => j.status === 'active').length;
            const onHold = allJobs.filter(j => j.status === 'on_hold').length;
            const completed = allJobs.filter(j => j.status === 'completed').length;

            document.getElementById('statTotalJobs').textContent = total;
            document.getElementById('statActiveJobs').textContent = active;
            document.getElementById('statOnHoldJobs').textContent = onHold;
            document.getElementById('statCompletedJobs').textContent = completed;
        }

        function displayJobs(jobs) {
            const tbody = document.getElementById('jobsTableBody');
            tbody.innerHTML = '';

            jobs.forEach(job => {
                const tr = document.createElement('tr');
                const reservationCount = job.reservations_count || 0;

                // Make entire row clickable to open job details
                tr.style.cursor = 'pointer';
                tr.onclick = function(e) {
                    // Don't open modal if clicking on action buttons
                    if (!e.target.closest('.action-buttons')) {
                        viewJobDetails(job.id);
                    }
                };

                tr.innerHTML = `
                    <td><strong>${escapeHtml(job.job_number)}</strong></td>
                    <td>${escapeHtml(job.job_name)}</td>
                    <td>${escapeHtml(job.customer_name || '-')}</td>
                    <td><span class="badge bg-${getStatusColor(job.status)}">${job.status_label}</span></td>
                    <td>
                        <span class="badge ${reservationCount > 0 ? 'bg-info' : 'bg-secondary'}">
                            ${reservationCount}
                        </span>
                    </td>
                    <td>${job.start_date || '-'}</td>
                    <td>${job.target_completion_date || '-'}</td>
                    <td>${getDaysRemaining(job.days_until_completion)}</td>
                    <td class="action-buttons">
                        <div class="btn-list flex-nowrap">
                            <button class="btn btn-sm btn-icon btn-primary" onclick="editJob(${job.id})" title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                            </button>
                            <button class="btn btn-sm btn-icon btn-danger" onclick="deleteJob(${job.id})" title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function filterJobs() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value;

            let filtered = allJobs;

            if (search) {
                filtered = filtered.filter(job =>
                    job.job_number.toLowerCase().includes(search) ||
                    job.job_name.toLowerCase().includes(search) ||
                    (job.customer_name && job.customer_name.toLowerCase().includes(search))
                );
            }

            if (statusFilter) {
                filtered = filtered.filter(job => job.status === statusFilter);
            }

            displayJobs(applySortToJobs(filtered));
        }

        let sortField = null;
        let sortDir = 'asc';

        function sortJobs(field) {
            if (sortField === field) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortField = field;
                sortDir = 'asc';
            }

            // Update sort indicators
            document.querySelectorAll('thead span[id^="sort_"]').forEach(el => el.textContent = '');
            const indicator = document.getElementById('sort_' + field);
            if (indicator) indicator.textContent = sortDir === 'asc' ? ' ↑' : ' ↓';

            filterJobs();
        }

        function applySortToJobs(jobs) {
            if (!sortField) return jobs;
            return [...jobs].sort((a, b) => {
                let va = a[sortField];
                let vb = b[sortField];

                // Nulls last
                if (va === null || va === undefined) return 1;
                if (vb === null || vb === undefined) return -1;

                if (typeof va === 'string') va = va.toLowerCase();
                if (typeof vb === 'string') vb = vb.toLowerCase();

                if (va < vb) return sortDir === 'asc' ? -1 : 1;
                if (va > vb) return sortDir === 'asc' ? 1 : -1;
                return 0;
            });
        }

        function filterByStatCard(status) {
            const filterSelect = document.getElementById('filterStatus');
            filterSelect.value = status;

            // Highlight the active card
            const cardMap = { '': 'statCardAll', 'active': 'statCardActive', 'on_hold': 'statCardOnHold', 'completed': 'statCardCompleted' };
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('border-primary', 'border-2'));
            const activeCard = document.getElementById(cardMap[status]);
            if (activeCard) activeCard.classList.add('border-primary', 'border-2');

            filterJobs();
        }

        function showAddJobModal() {
            currentJob = null;
            document.getElementById('jobModalTitle').textContent = 'Add Job';
            document.getElementById('jobForm').reset();
            document.getElementById('jobId').value = '';
            document.getElementById('status').value = 'active';

            showModal(document.getElementById('jobModal'));
        }

        async function editJob(id) {
            try {
                const response = await fetch(`/api/v1/business-jobs/${id}`, {
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load job');
                }

                const data = await response.json();
                currentJob = data.job;

                document.getElementById('jobModalTitle').textContent = 'Edit Job';
                document.getElementById('jobId').value = currentJob.id;
                document.getElementById('jobNumber').value = currentJob.job_number;
                document.getElementById('jobName').value = currentJob.job_name;
                document.getElementById('customerName').value = currentJob.customer_name || '';
                document.getElementById('siteAddress').value = currentJob.site_address || '';
                document.getElementById('contactName').value = currentJob.contact_name || '';
                document.getElementById('contactPhone').value = currentJob.contact_phone || '';
                document.getElementById('contactEmail').value = currentJob.contact_email || '';
                document.getElementById('status').value = currentJob.status;
                document.getElementById('startDate').value = currentJob.start_date || '';
                document.getElementById('targetCompletionDate').value = currentJob.target_completion_date || '';
                document.getElementById('notes').value = currentJob.notes || '';

                showModal(document.getElementById('jobModal'));
            } catch (error) {
                console.error('Error loading job:', error);
                showToast('Failed to load job details', 'danger');
            }
        }

        async function saveJob() {
            const jobModalError = document.getElementById('jobModalError');
            jobModalError.style.display = 'none';

            const jobId = document.getElementById('jobId').value;
            const isEdit = jobId !== '';

            const jobData = {
                job_number: document.getElementById('jobNumber').value,
                job_name: document.getElementById('jobName').value,
                customer_name: document.getElementById('customerName').value || null,
                site_address: document.getElementById('siteAddress').value || null,
                contact_name: document.getElementById('contactName').value || null,
                contact_phone: document.getElementById('contactPhone').value || null,
                contact_email: document.getElementById('contactEmail').value || null,
                status: document.getElementById('status').value,
                start_date: document.getElementById('startDate').value || null,
                target_completion_date: document.getElementById('targetCompletionDate').value || null,
                notes: document.getElementById('notes').value || null,
            };

            try {
                const url = isEdit ? `/api/v1/business-jobs/${jobId}` : '/api/v1/business-jobs';
                const method = isEdit ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                    body: JSON.stringify(jobData),
                });

                if (!response.ok) {
                    const error = await response.json();
                    const msg = error.message || 'Failed to save job';
                    jobModalError.textContent = msg;
                    jobModalError.style.display = 'block';
                    return;
                }

                hideModal(document.getElementById('jobModal'));
                await loadJobs();
                showToast(isEdit ? 'Job updated successfully' : 'Job created successfully', 'success');
            } catch (error) {
                console.error('Error saving job:', error);
                showToast(error.message, 'danger');
            }
        }

        async function deleteJob(id) {
            const confirmed = await showConfirm(
                'Are you sure you want to delete this job? This action cannot be undone.',
                'Delete Job', 'Delete', 'btn-danger'
            );
            if (!confirmed) return;

            try {
                const response = await fetch(`/api/v1/business-jobs/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to delete job');
                }

                await loadJobs();
                showToast('Job deleted successfully', 'success');
            } catch (error) {
                console.error('Error deleting job:', error);
                showToast(error.message, 'danger');
            }
        }

        function getStatusColor(status) {
            const colors = {
                'active': 'success',
                'on_hold': 'warning',
                'completed': 'info',
                'cancelled': 'danger',
            };
            return colors[status] || 'secondary';
        }

        function getDaysRemaining(days) {
            if (days === null || days === undefined) {
                return '-';
            }

            if (days < 0) {
                return `<span class="text-danger">${Math.abs(days)} days overdue</span>`;
            } else if (days === 0) {
                return '<span class="text-warning">Due today</span>';
            } else if (days <= 7) {
                return `<span class="text-warning">${days} days</span>`;
            } else {
                return `<span class="text-muted">${days} days</span>`;
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ===== JOB DETAILS MODAL FUNCTIONS =====
        let currentJobForReservations = null;
        let jobReservations = [];
        let reservationItems = [];

        async function viewJobDetails(jobId) {
            currentJobForReservations = allJobs.find(j => j.id === jobId);
            if (!currentJobForReservations) return;

            // Populate job header
            document.getElementById('jobDetailsModalTitle').textContent =
                `${currentJobForReservations.job_number} - ${currentJobForReservations.job_name}`;
            document.getElementById('detailJobNumber').textContent = currentJobForReservations.job_number;
            document.getElementById('detailJobName').textContent = currentJobForReservations.job_name;
            document.getElementById('detailCustomer').textContent = currentJobForReservations.customer_name || 'N/A';
            document.getElementById('detailStatus').innerHTML =
                `<span class="badge bg-${getStatusColor(currentJobForReservations.status)}">${currentJobForReservations.status_label}</span>`;

            // Show modal
            const modal = document.getElementById('jobDetailsModal');
            showModal(modal);

            // Reset to reservations tab (activate first tab)
            const reservationsTab = document.getElementById('reservations-tab');
            const reservationsPane = document.getElementById('reservations');

            // Remove active from all tabs
            document.querySelectorAll('#jobTabs .nav-link').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('#jobTabContent .tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });

            // Activate reservations tab
            reservationsTab.classList.add('active');
            reservationsPane.classList.add('show', 'active');

            // Load reservations
            await loadJobReservations(jobId);
        }

        async function loadJobReservations(jobId) {
            document.getElementById('reservationsLoading').style.display = 'block';
            document.getElementById('reservationsContent').style.display = 'none';
            document.getElementById('reservationsEmpty').style.display = 'none';

            try {
                const response = await fetch(`/api/v1/business-jobs/${jobId}/reservations`, {
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load reservations');
                }

                const data = await response.json();
                jobReservations = data.reservations;

                // Update count badge
                document.getElementById('reservationsCount').textContent = jobReservations.length;

                displayJobReservations();
            } catch (error) {
                console.error('Error loading reservations:', error);
                showToast('Failed to load reservations', 'danger');
                document.getElementById('reservationsLoading').style.display = 'none';
            }
        }

        function displayJobReservations() {
            document.getElementById('reservationsLoading').style.display = 'none';

            if (jobReservations.length === 0) {
                document.getElementById('reservationsEmpty').style.display = 'block';
                return;
            }

            document.getElementById('reservationsContent').style.display = 'block';

            const tbody = document.getElementById('reservationsTableBody');
            tbody.innerHTML = jobReservations.map(res => `
                <tr>
                    <td><strong>#${res.reservation_id}</strong></td>
                    <td><span class="badge bg-${getReservationStatusColor(res.status)}">${res.status_label}</span></td>
                    <td>${escapeHtml(res.requested_by)}</td>
                    <td>${formatNeededBy(res.needed_by, res.status)}</td>
                    <td>${res.items_count}</td>
                    <td>${res.total_committed}</td>
                    <td>${res.total_consumed}</td>
                    <td>${new Date(res.created_at).toLocaleDateString()}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-sm btn-info" onclick="viewReservationDetails(${res.id})" title="View Details">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg>
                            </button>
                            ${res.status !== 'fulfilled' && res.status !== 'cancelled' ? `
                                <button class="btn btn-sm btn-primary" onclick="openEditReservationModal(${res.id})" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                                </button>
                                <button class="btn btn-sm btn-secondary" onclick="showReservationStatusModal(${res.id}, '${res.status}')" title="Change Status">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /><path d="M9 12l2 2l4 -4" /></svg>
                                </button>
                                ${res.status === 'in_progress' ? `
                                    <button class="btn btn-sm btn-success" onclick="showReservationCompleteModal(${res.id})" title="Complete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                                    </button>
                                ` : ''}
                                <button class="btn btn-sm btn-danger" onclick="deleteReservation(${res.reservation_id})" title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function getReservationStatusColor(status) {
            const colors = {
                'active': 'primary',
                'in_progress': 'info',
                'fulfilled': 'success',
                'on_hold': 'warning',
                'cancelled': 'dark',
            };
            return colors[status] || 'secondary';
        }

        function formatNeededBy(neededBy, status) {
            if (!neededBy) return '-';
            if (['fulfilled', 'cancelled'].includes(status)) return neededBy;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const due = new Date(neededBy + 'T00:00:00');
            if (due < today) {
                return `<span class="text-danger fw-bold"><i class="ti ti-alert-triangle me-1"></i>${neededBy}</span>`;
            }
            return neededBy;
        }

        function showAddReservationModal() {
            if (!currentJobForReservations) return;

            document.getElementById('reservationJobId').value = currentJobForReservations.id;
            document.getElementById('reservationRequestedBy').value = '';
            document.getElementById('reservationNeededBy').value = '';
            document.getElementById('reservationNotes').value = '';
            reservationItems = [];
            updateReservationItemsDisplay();
            hideNewResAddItemForm();

            showModal(document.getElementById('addReservationModal'));
        }

        let newResItemSearchTimeout;

        function showNewResAddItemForm() {
            document.getElementById('newResAddItemForm').style.display = 'block';
            document.getElementById('newResAddItemBtn').style.display = 'none';
            document.getElementById('newResItemSearch').value = '';
            document.getElementById('newResItemProductId').value = '';
            document.getElementById('newResItemRequestedQty').value = 1;
            document.getElementById('newResItemCommittedQty').value = 0;
            document.getElementById('newResSearchResults').style.display = 'none';
            document.getElementById('newResSelectedProduct').style.display = 'none';

            const searchInput = document.getElementById('newResItemSearch');
            searchInput.oninput = function() {
                clearTimeout(newResItemSearchTimeout);
                newResItemSearchTimeout = setTimeout(() => searchNewResProduct(this.value), 300);
            };
        }

        function hideNewResAddItemForm() {
            document.getElementById('newResAddItemForm').style.display = 'none';
            document.getElementById('newResAddItemBtn').style.display = 'block';
        }

        async function searchNewResProduct(query) {
            if (query.length < 2) {
                document.getElementById('newResSearchResults').style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`/api/v1/job-reservations/search-products?q=${encodeURIComponent(query)}&per_page=10`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    const resultsDiv = document.getElementById('newResSearchResults');

                    if (data.data && data.data.length > 0) {
                        resultsDiv.innerHTML = data.data.map(product => `
                            <button type="button" class="list-group-item list-group-item-action"
                                    onclick="selectNewResProduct(${product.id}, '${escapeHtml(product.sku)}', '${escapeHtml(product.part_number || '')}', '${escapeHtml(product.finish || '')}', '${product.description.replace(/'/g, "\\'")}', ${product.quantity_available})">
                                <div class="d-flex w-100 justify-content-between">
                                    <strong>${escapeHtml(product.sku)}</strong>
                                    <span class="badge bg-${product.quantity_available > 0 ? 'success' : 'warning'}">${product.quantity_available} avail</span>
                                </div>
                                <small>${escapeHtml(product.part_number || '')} ${escapeHtml(product.finish || '')} - ${escapeHtml(product.description)}</small>
                            </button>
                        `).join('');
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.innerHTML = '<div class="list-group-item">No products found</div>';
                        resultsDiv.style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Error searching products:', error);
            }
        }

        function selectNewResProduct(productId, sku, partNumber, finish, description, available) {
            document.getElementById('newResItemProductId').value = productId;
            document.getElementById('newResSearchResults').style.display = 'none';
            document.getElementById('newResItemSearch').value = sku;
            document.getElementById('newResProductInfo').textContent = `${sku} - ${partNumber} ${finish} (${available} available)`;
            document.getElementById('newResSelectedProduct').style.display = 'block';
            window._tempNewResProduct = { productId, sku, partNumber, finish, description, available };
        }

        function addItemToNewReservation() {
            const productId = document.getElementById('newResItemProductId').value;
            const requestedQty = parseInt(document.getElementById('newResItemRequestedQty').value);
            const committedQty = parseInt(document.getElementById('newResItemCommittedQty').value);

            if (!productId) { showToast('Please select a product', 'warning'); return; }
            if (requestedQty < 1) { showToast('Requested quantity must be at least 1', 'warning'); return; }
            if (reservationItems.some(item => item.product_id == productId)) {
                showToast('This product is already in the reservation', 'warning');
                return;
            }

            const product = window._tempNewResProduct;
            reservationItems.push({
                product_id: productId,
                sku: product.sku,
                part_number: product.partNumber,
                finish: product.finish,
                description: product.description,
                requested_qty: requestedQty,
                committed_qty: committedQty,
                available: product.available,
            });

            updateReservationItemsDisplay();
            hideNewResAddItemForm();
        }

        function updateReservationItemsDisplay() {
            const container = document.getElementById('reservationItemsContainer');

            if (reservationItems.length === 0) {
                container.innerHTML = '<p class="text-muted">No items added yet. Click "Add Item" to begin.</p>';
                return;
            }

            container.innerHTML = `
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Part #</th>
                            <th>Finish</th>
                            <th>Description</th>
                            <th>Requested</th>
                            <th>Available</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${reservationItems.map((item, index) => `
                            <tr>
                                <td><code>${item.sku}</code></td>
                                <td>${item.part_number}</td>
                                <td>${item.finish || '-'}</td>
                                <td>${item.description}</td>
                                <td>${item.requested_qty}</td>
                                <td><span class="badge bg-${item.available > 0 ? 'success' : 'warning'}">${item.available}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-ghost-danger" onclick="removeReservationItem(${index})">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        function removeReservationItem(index) {
            reservationItems.splice(index, 1);
            updateReservationItemsDisplay();
        }

        async function createJobReservation() {
            const jobId = document.getElementById('reservationJobId').value;
            const requestedBy = document.getElementById('reservationRequestedBy').value;
            const neededBy = document.getElementById('reservationNeededBy').value;
            const notes = document.getElementById('reservationNotes').value;

            if (!requestedBy) {
                showToast('Please enter who requested this reservation', 'warning');
                return;
            }

            if (reservationItems.length === 0) {
                showToast('Please add at least one item to the reservation', 'warning');
                return;
            }

            try {
                const response = await fetch(`/api/v1/business-jobs/${jobId}/reservations`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                    body: JSON.stringify({
                        requested_by: requestedBy,
                        needed_by: neededBy || null,
                        notes: notes || null,
                        items: reservationItems.map(item => ({
                            product_id: item.product_id,
                            requested_qty: item.requested_qty,
                            committed_qty: item.committed_qty,
                        })),
                    }),
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to create reservation');
                }

                const data = await response.json();

                hideModal(document.getElementById('addReservationModal'));
                await loadJobReservations(jobId);
                await loadJobs();

                if (data.job_reopened) {
                    showToast('Job was reopened to Active because a new reservation was added.', 'info');
                }
                showToast(`Reservation #${data.reservation.reservation_id} created successfully`, 'success');
            } catch (error) {
                console.error('Error creating reservation:', error);
                showToast(error.message, 'danger');
            }
        }

        async function viewReservationDetails(reservationId) {
            try {
                const response = await fetch(`/api/v1/job-reservations/${reservationId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load reservation details');
                }

                const data = await response.json();
                showReservationDetailModal(data);
            } catch (error) {
                console.error('Error loading reservation details:', error);
                showToast('Failed to load reservation details', 'danger');
            }
        }

        function showReservationDetailModal(data) {
            const res = data.reservation;
            const items = data.items;

            document.getElementById('reservationDetailTitle').textContent =
                `Reservation #${res.reservation_id} — ${res.status_label}`;

            const itemsRows = items.map(item => `
                <tr>
                    <td><code>${escapeHtml(item.product.sku || '-')}</code></td>
                    <td><strong>${escapeHtml(item.product.part_number)}</strong></td>
                    <td>${escapeHtml(item.product.finish || '-')}</td>
                    <td>${escapeHtml(item.product.description || '-')}</td>
                    <td class="text-end">${item.requested_qty}</td>
                    <td class="text-end">${item.committed_qty}</td>
                    <td class="text-end">${item.consumed_qty}</td>
                    <td class="text-end">${item.released_qty}</td>
                    <td class="text-end">${item.product.quantity_on_hand}</td>
                    <td class="text-end">${item.product.quantity_available}</td>
                    <td>${escapeHtml(item.product.location || '-')}</td>
                </tr>
            `).join('');

            document.getElementById('reservationDetailBody').innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-5">Release #:</dt>
                            <dd class="col-7"><strong>${res.reservation_id}</strong></dd>
                            <dt class="col-5">Status:</dt>
                            <dd class="col-7"><span class="badge bg-${getReservationStatusColor(res.status)}">${res.status_label}</span></dd>
                            <dt class="col-5">Requested By:</dt>
                            <dd class="col-7">${escapeHtml(res.requested_by || '-')}</dd>
                            <dt class="col-5">Needed By:</dt>
                            <dd class="col-7">${formatNeededBy(res.needed_by, res.status)}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-5">Created:</dt>
                            <dd class="col-7">${new Date(res.created_at).toLocaleString()}</dd>
                            <dt class="col-5">Updated:</dt>
                            <dd class="col-7">${new Date(res.updated_at).toLocaleString()}</dd>
                        </dl>
                    </div>
                </div>
                ${res.notes ? `<div class="mb-3"><strong>Notes:</strong> ${escapeHtml(res.notes)}</div>` : ''}
                <h4>Line Items</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter">
                        <thead>
                            <tr>
                                <th>SKU</th><th>Part #</th><th>Finish</th><th>Description</th>
                                <th class="text-end">Requested</th><th class="text-end">Committed</th>
                                <th class="text-end">Consumed</th><th class="text-end">Released</th>
                                <th class="text-end">On Hand</th><th class="text-end">Available</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>${itemsRows}</tbody>
                    </table>
                </div>
            `;

            const actionsDiv = document.getElementById('reservationDetailActions');
            if (!['fulfilled', 'cancelled'].includes(res.status)) {
                actionsDiv.innerHTML = `
                    <button type="button" class="btn btn-primary" onclick="openEditReservationModal(${res.id})">
                        <i class="ti ti-edit me-1"></i>Edit
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="showReservationStatusModal(${res.id}, '${res.status}')">
                        Change Status
                    </button>
                    ${res.status === 'in_progress' ? `
                        <button type="button" class="btn btn-success" onclick="showReservationCompleteModal(${res.id})">
                            <i class="ti ti-check me-1"></i>Complete
                        </button>
                    ` : ''}
                `;
            } else {
                actionsDiv.innerHTML = '';
            }

            showModal(document.getElementById('reservationDetailModal'));
        }

        async function deleteReservation(reservationId) {
            if (!currentJobForReservations) return;

            const confirmed = await showConfirm(
                `Are you sure you want to delete Reservation #${reservationId}? This action cannot be undone.`,
                'Delete Reservation', 'Delete', 'btn-danger'
            );
            if (!confirmed) return;

            try {
                const response = await fetch(`/api/v1/business-jobs/${currentJobForReservations.id}/reservations/${reservationId}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to delete reservation');
                }

                await loadJobReservations(currentJobForReservations.id);
                await loadJobs();

                showToast('Reservation deleted successfully', 'success');
            } catch (error) {
                console.error('Error deleting reservation:', error);
                showToast(error.message, 'danger');
            }
        }

        // ===== RESERVATION STATUS CHANGE =====

        function showReservationStatusModal(id, currentStatus) {
            document.getElementById('resStatusChangeId').value = id;
            document.getElementById('resStatusWarnings').style.display = 'none';

            // Rebuild options excluding the current status
            const select = document.getElementById('resNewStatus');
            const allStatuses = [
                { value: 'active', label: 'Active' },
                { value: 'in_progress', label: 'In Progress' },
                { value: 'fulfilled', label: 'Fulfilled' },
                { value: 'on_hold', label: 'On Hold' },
                { value: 'cancelled', label: 'Cancelled' },
            ];
            select.innerHTML = allStatuses
                .filter(s => s.value !== currentStatus)
                .map(s => `<option value="${s.value}">${s.label}</option>`)
                .join('');

            showModal(document.getElementById('reservationStatusModal'));
        }

        async function confirmReservationStatusChange() {
            const id = document.getElementById('resStatusChangeId').value;
            const status = document.getElementById('resNewStatus').value;

            try {
                const response = await fetch(`/api/v1/job-reservations/${id}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status })
                });

                if (response.ok) {
                    const data = await response.json();

                    if (data.warnings && data.warnings.length > 0) {
                        const warningsDiv = document.getElementById('resStatusWarnings');
                        warningsDiv.innerHTML = data.warnings.join('<br>');
                        warningsDiv.style.display = 'block';

                        if (data.insufficient_items && data.insufficient_items.length > 0) {
                            const items = data.insufficient_items.map(item =>
                                `${item.part_number}-${item.finish}: need ${item.shortage} more`
                            ).join(', ');
                            warningsDiv.innerHTML += `<br><strong>Insufficient items:</strong> ${items}`;
                        }

                        const proceed = await showConfirm(
                            'There are stock warnings. Do you want to proceed anyway?',
                            'Stock Warning', 'Proceed', 'btn-warning'
                        );
                        if (!proceed) return;
                    }

                    hideModal(document.getElementById('reservationStatusModal'));
                    await loadJobReservations(currentJobForReservations.id);
                    await loadJobs();
                    showToast(`Status updated to: ${data.reservation.new_status}`, 'success');
                    await checkAllReservationsFulfilled();
                } else {
                    const error = await response.json();
                    showToast('Error updating status: ' + (error.message || error.error), 'danger');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                showToast('Error updating status: ' + error.message, 'danger');
            }
        }

        // ===== COMPLETE RESERVATION =====
        let completeResItems = [];

        async function showReservationCompleteModal(id) {
            try {
                const response = await fetch(`/api/v1/job-reservations/${id}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) { showToast('Error loading reservation details', 'danger'); return; }

                const data = await response.json();
                const items = data.items;

                document.getElementById('completeResId').value = id;
                completeResItems = items;

                const tbody = document.getElementById('completeResItemsTable');
                tbody.innerHTML = items.map(item => {
                    const toRelease = item.committed_qty - item.consumed_qty;
                    return `
                        <tr>
                            <td><strong>${escapeHtml(item.product.part_number)}</strong></td>
                            <td>${escapeHtml(item.product.finish || '-')}</td>
                            <td>${item.committed_qty}</td>
                            <td>${item.consumed_qty}</td>
                            <td>
                                <input type="number" class="form-control form-control-sm"
                                    id="resConsumed_${item.product_id}"
                                    data-product-id="${item.product_id}"
                                    data-committed="${item.committed_qty}"
                                    data-already-consumed="${item.consumed_qty}"
                                    value="${item.consumed_qty}"
                                    min="${item.consumed_qty}"
                                    onchange="updateResRelease(${item.product_id})"
                                    style="width: 100px;">
                            </td>
                            <td>
                                <span id="resRelease_${item.product_id}" class="badge bg-success">${toRelease}</span>
                            </td>
                        </tr>
                    `;
                }).join('');

                showModal(document.getElementById('reservationCompleteModal'));
            } catch (error) {
                console.error('Error loading reservation:', error);
                showToast('Error loading reservation details', 'danger');
            }
        }

        function updateResRelease(productId) {
            const input = document.getElementById(`resConsumed_${productId}`);
            const consumed = parseInt(input.value) || 0;
            const committed = parseInt(input.dataset.committed);
            const alreadyConsumed = parseInt(input.dataset.alreadyConsumed);

            if (consumed < alreadyConsumed) {
                showToast(`Cannot reduce consumed quantity below already consumed (${alreadyConsumed})`, 'warning');
                input.value = alreadyConsumed;
                return;
            }

            const badge = document.getElementById(`resRelease_${productId}`);
            if (consumed > committed) {
                badge.textContent = `-${consumed - committed}`;
                badge.className = 'badge bg-warning';
            } else {
                badge.textContent = committed - consumed;
                badge.className = 'badge bg-success';
            }
        }

        async function confirmReservationComplete() {
            const id = document.getElementById('completeResId').value;
            const consumedQuantities = {};

            completeResItems.forEach(item => {
                const input = document.getElementById(`resConsumed_${item.product_id}`);
                consumedQuantities[item.product_id] = parseInt(input.value) || 0;
            });

            try {
                const response = await fetch(`/api/v1/job-reservations/${id}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ consumed_quantities: consumedQuantities })
                });

                if (response.ok) {
                    const data = await response.json();
                    hideModal(document.getElementById('reservationCompleteModal'));
                    await loadJobReservations(currentJobForReservations.id);
                    await loadJobs();

                    showToast(`Reservation completed — Consumed: ${data.reservation.total_consumed}, Released: ${data.reservation.total_released}`, 'success');
                    await checkAllReservationsFulfilled();
                } else {
                    const error = await response.json();
                    showToast('Error completing reservation: ' + (error.message || error.error), 'danger');
                }
            } catch (error) {
                console.error('Error completing reservation:', error);
                showToast('Error completing reservation: ' + error.message, 'danger');
            }
        }

        // ===== EDIT RESERVATION =====
        let editingResReservation = null;
        let editingResItems = [];

        async function openEditReservationModal(id) {
            try {
                const response = await fetch(`/api/v1/job-reservations/${id}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) { showToast('Error loading reservation for editing', 'danger'); return; }

                const data = await response.json();
                editingResReservation = data.reservation;
                editingResItems = data.items;

                document.getElementById('editResId').value = editingResReservation.id;
                document.getElementById('editResRequestedBy').value = editingResReservation.requested_by || '';
                document.getElementById('editResNeededBy').value = editingResReservation.needed_by || '';
                document.getElementById('editResNotes').value = editingResReservation.notes || '';

                hideEditResAddItemForm();
                renderEditResItems();

                // Close detail modal if open, then show edit modal
                hideModal(document.getElementById('reservationDetailModal'));
                showModal(document.getElementById('editReservationModal'));
            } catch (error) {
                console.error('Error loading reservation:', error);
                showToast('Error loading reservation for editing', 'danger');
            }
        }

        function renderEditResItems() {
            const tbody = document.getElementById('editResItemsBody');

            if (editingResItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No items</td></tr>';
                return;
            }

            tbody.innerHTML = editingResItems.map((item, index) => `
                <tr>
                    <td><code>${escapeHtml(item.product.sku || '-')}</code></td>
                    <td>${escapeHtml(item.product.part_number || '-')}</td>
                    <td>${escapeHtml(item.product.finish || '-')}</td>
                    <td>${escapeHtml(item.product.description || '-')}</td>
                    <td class="text-end">
                        <input type="number" class="form-control form-control-sm"
                               value="${item.requested_qty}" min="1"
                               onchange="updateEditResItemQty(${index}, 'requested_qty', this.value)"
                               style="width: 80px;">
                    </td>
                    <td class="text-end">
                        <input type="number" class="form-control form-control-sm"
                               value="${item.committed_qty}" min="${item.consumed_qty || 0}"
                               onchange="updateEditResItemQty(${index}, 'committed_qty', this.value)"
                               style="width: 80px;">
                    </td>
                    <td class="text-end">${item.consumed_qty}</td>
                    <td class="text-end">${item.product.quantity_on_hand}</td>
                    <td class="text-center">
                        ${item.consumed_qty === 0 ? `
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-ghost-primary" onclick="showEditReplaceItemModal(${index})" title="Replace">
                                    <i class="ti ti-replace"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-ghost-danger" onclick="removeEditResItem(${index})" title="Remove">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                        ` : '<span class="text-muted" title="Cannot modify consumed item">-</span>'}
                    </td>
                </tr>
            `).join('');
        }

        function updateEditResItemQty(index, field, value) {
            const item = editingResItems[index];
            const numValue = parseInt(value);

            if (field === 'committed_qty' && numValue < item.consumed_qty) {
                showToast(`Cannot reduce committed quantity below already consumed (${item.consumed_qty})`, 'warning');
                renderEditResItems();
                return;
            }

            if (field === 'committed_qty' && numValue > item.committed_qty) {
                const increase = numValue - item.committed_qty;
                if (increase > item.product.quantity_available) {
                    showToast(`Only ${item.product.quantity_available} available. Cannot increase by ${increase}.`, 'warning');
                    renderEditResItems();
                    return;
                }
            }

            item[field] = numValue;
        }

        let editResNewItemSearchTimeout;

        function showEditResAddItemForm() {
            document.getElementById('editResAddItemFormContainer').style.display = 'block';
            document.getElementById('editResAddItemBtn').style.display = 'none';
            document.getElementById('editResNewItemSearch').value = '';
            document.getElementById('editResNewItemProductId').value = '';
            document.getElementById('editResNewItemRequestedQty').value = 1;
            document.getElementById('editResNewItemCommittedQty').value = 0;
            document.getElementById('editResNewItemSearchResults').style.display = 'none';
            document.getElementById('editResNewItemSelected').style.display = 'none';

            document.getElementById('editResNewItemSearch').oninput = function() {
                clearTimeout(editResNewItemSearchTimeout);
                editResNewItemSearchTimeout = setTimeout(() => searchEditResNewProduct(this.value), 300);
            };
        }

        async function searchEditResNewProduct(query) {
            if (query.length < 2) {
                document.getElementById('editResNewItemSearchResults').style.display = 'none';
                return;
            }
            try {
                const response = await fetch(`/api/v1/job-reservations/search-products?q=${encodeURIComponent(query)}&per_page=10`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (response.ok) {
                    const data = await response.json();
                    const resultsDiv = document.getElementById('editResNewItemSearchResults');
                    if (data.data && data.data.length > 0) {
                        resultsDiv.innerHTML = data.data.map(p => `
                            <button type="button" class="list-group-item list-group-item-action"
                                    onclick="selectEditResNewProduct(${p.id}, '${escapeHtml(p.sku)}', '${escapeHtml(p.part_number || '')}', '${escapeHtml(p.finish || '')}', '${p.description.replace(/'/g, "\\'")}', ${p.quantity_available})">
                                <div class="d-flex w-100 justify-content-between">
                                    <strong>${escapeHtml(p.sku)}</strong>
                                    <span class="badge bg-${p.quantity_available > 0 ? 'success' : 'warning'}">${p.quantity_available} avail</span>
                                </div>
                                <small>${escapeHtml(p.part_number || '')} ${escapeHtml(p.finish || '')} — ${escapeHtml(p.description)}</small>
                            </button>
                        `).join('');
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.innerHTML = '<div class="list-group-item">No products found</div>';
                        resultsDiv.style.display = 'block';
                    }
                }
            } catch (e) { console.error(e); }
        }

        function selectEditResNewProduct(productId, sku, partNumber, finish, description, available) {
            document.getElementById('editResNewItemProductId').value = productId;
            document.getElementById('editResNewItemSearch').value = sku;
            document.getElementById('editResNewItemSearchResults').style.display = 'none';
            document.getElementById('editResNewItemInfo').textContent = `${sku} — ${partNumber} ${finish} (${available} available)`;
            document.getElementById('editResNewItemSelected').style.display = 'block';
            window._tempEditResNewProduct = { productId, sku, partNumber, finish, description, available };
        }

        function hideEditResAddItemForm() {
            document.getElementById('editResAddItemFormContainer').style.display = 'none';
            document.getElementById('editResAddItemBtn').style.display = 'block';
        }

        function addItemToEditReservation() {
            const productId = parseInt(document.getElementById('editResNewItemProductId').value);
            const requestedQty = parseInt(document.getElementById('editResNewItemRequestedQty').value);
            const committedQty = parseInt(document.getElementById('editResNewItemCommittedQty').value);
            const product = window._tempEditResNewProduct;

            if (!productId || !product) { showToast('Please select a product', 'warning'); return; }
            if (requestedQty < 1) { showToast('Requested quantity must be at least 1', 'warning'); return; }
            if (editingResItems.some(item => item.product_id === productId)) {
                showToast('This product is already in the reservation', 'warning');
                return;
            }
            if (committedQty > product.available) {
                showToast(`Only ${product.available} available. Cannot commit ${committedQty}.`, 'warning');
                return;
            }

            editingResItems.push({
                id: null,
                product_id: productId,
                requested_qty: requestedQty,
                committed_qty: committedQty,
                consumed_qty: 0,
                product: {
                    id: productId,
                    sku: product.sku,
                    part_number: product.partNumber,
                    finish: product.finish,
                    description: product.description,
                    quantity_on_hand: product.available,
                    quantity_available: product.available
                }
            });

            renderEditResItems();
            hideEditResAddItemForm();
        }

        async function removeEditResItem(index) {
            const confirmed = await showConfirm('Remove this item from the reservation?', 'Remove Item', 'Remove', 'btn-danger');
            if (!confirmed) return;
            editingResItems.splice(index, 1);
            renderEditResItems();
        }

        async function saveEditedReservation() {
            const id = document.getElementById('editResId').value;
            const requestedBy = document.getElementById('editResRequestedBy').value;
            const neededBy = document.getElementById('editResNeededBy').value;
            const notes = document.getElementById('editResNotes').value;

            try {
                const headerResponse = await fetch(`/api/v1/job-reservations/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ requested_by: requestedBy, needed_by: neededBy || null, notes: notes || null })
                });

                if (!headerResponse.ok) {
                    const error = await headerResponse.json();
                    showToast('Error updating reservation: ' + (error.message || error.error), 'danger');
                    return;
                }

                for (const item of editingResItems) {
                    if (item.id === null) {
                        const addResponse = await fetch(`/api/v1/job-reservations/${id}/items`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                product_id: item.product_id,
                                requested_qty: item.requested_qty,
                                committed_qty: item.committed_qty
                            })
                        });
                        if (!addResponse.ok) {
                            const error = await addResponse.json();
                            showToast('Error adding item: ' + (error.message || error.error), 'danger');
                            return;
                        }
                    } else {
                        const updateResponse = await fetch(`/api/v1/job-reservations/${id}/items/${item.id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                requested_qty: item.requested_qty,
                                committed_qty: item.committed_qty
                            })
                        });
                        if (!updateResponse.ok) {
                            const error = await updateResponse.json();
                            showToast('Error updating item: ' + (error.message || error.error), 'danger');
                            return;
                        }
                    }
                }

                hideModal(document.getElementById('editReservationModal'));
                await loadJobReservations(currentJobForReservations.id);
                await loadJobs();
                showToast('Reservation updated successfully', 'success');
                viewReservationDetails(id);
            } catch (error) {
                console.error('Error saving reservation:', error);
                showToast('Error saving reservation: ' + error.message, 'danger');
            }
        }

        // ===== REPLACE ITEM (in edit modal) =====
        let editReplaceItemProductSearch;

        function showEditReplaceItemModal(itemIndex) {
            const item = editingResItems[itemIndex];
            document.getElementById('editReplaceItemIndex').value = itemIndex;
            document.getElementById('editReplaceOldProduct').textContent =
                `${item.product.sku} - ${item.product.part_number} ${item.product.finish || ''} (${item.product.description})`;

            document.getElementById('editReplaceProductSearch').value = '';
            document.getElementById('editReplaceNewProductId').value = '';
            document.getElementById('editReplaceReason').value = '';
            document.getElementById('editReplaceSearchResults').style.display = 'none';
            document.getElementById('editReplaceSelectedProduct').style.display = 'none';

            const searchInput = document.getElementById('editReplaceProductSearch');
            clearTimeout(editReplaceItemProductSearch);
            searchInput.oninput = function() {
                clearTimeout(editReplaceItemProductSearch);
                editReplaceItemProductSearch = setTimeout(() => searchEditReplaceProduct(this.value), 300);
            };

            showModal(document.getElementById('editReplaceItemModal'));
        }

        async function searchEditReplaceProduct(query) {
            if (query.length < 2) {
                document.getElementById('editReplaceSearchResults').style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`/api/v1/job-reservations/search-products?q=${encodeURIComponent(query)}&per_page=10`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    const resultsDiv = document.getElementById('editReplaceSearchResults');

                    if (data.data && data.data.length > 0) {
                        resultsDiv.innerHTML = data.data.map(product => `
                            <button type="button" class="list-group-item list-group-item-action"
                                    onclick="selectEditReplaceProduct(${product.id}, '${escapeHtml(product.sku)}', '${escapeHtml(product.part_number || '')}', '${escapeHtml(product.finish || '')}', '${product.description.replace(/'/g, "\\'")}', ${product.quantity_on_hand}, ${product.quantity_available})">
                                <div class="d-flex w-100 justify-content-between">
                                    <strong>${escapeHtml(product.sku)}</strong>
                                    <span class="badge bg-${product.quantity_available > 0 ? 'success' : 'danger'}">${product.quantity_available} avail</span>
                                </div>
                                <small>${escapeHtml(product.part_number || '')} ${escapeHtml(product.finish || '')} - ${escapeHtml(product.description)}</small>
                            </button>
                        `).join('');
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.innerHTML = '<div class="list-group-item">No products found</div>';
                        resultsDiv.style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Error searching products:', error);
            }
        }

        function selectEditReplaceProduct(productId, sku, partNumber, finish, description, onHand, available) {
            document.getElementById('editReplaceNewProductId').value = productId;
            document.getElementById('editReplaceSearchResults').style.display = 'none';
            document.getElementById('editReplaceProductSearch').value = sku;

            document.getElementById('editReplaceProductDetails').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>SKU:</strong> ${escapeHtml(sku)}<br>
                        <strong>Part#:</strong> ${escapeHtml(partNumber)}<br>
                        <strong>Finish:</strong> ${escapeHtml(finish) || 'N/A'}
                    </div>
                    <div class="col-md-6">
                        <strong>Description:</strong> ${escapeHtml(description)}<br>
                        <strong>On Hand:</strong> ${onHand}<br>
                        <strong>Available:</strong> <span class="badge bg-${available > 0 ? 'success' : 'danger'}">${available}</span>
                    </div>
                </div>
            `;
            document.getElementById('editReplaceSelectedProduct').style.display = 'block';
        }

        async function confirmEditReplaceItem() {
            const itemIndex = parseInt(document.getElementById('editReplaceItemIndex').value);
            const newProductId = document.getElementById('editReplaceNewProductId').value;
            const reason = document.getElementById('editReplaceReason').value;

            if (!newProductId) { showToast('Please select a replacement product', 'warning'); return; }

            const item = editingResItems[itemIndex];

            try {
                const response = await fetch(`/api/v1/job-reservations/${editingResReservation.id}/items/${item.id}/replace`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ new_product_id: newProductId, reason: reason })
                });

                if (response.ok) {
                    const data = await response.json();
                    editingResItems[itemIndex].product = data.new_product;
                    editingResItems[itemIndex].product_id = data.new_product.id || data.new_product.product_id;

                    renderEditResItems();
                    hideModal(document.getElementById('editReplaceItemModal'));
                    showToast('Item replaced successfully', 'success');
                } else {
                    const error = await response.json();
                    showToast('Error: ' + (error.message || error.error), 'danger');
                }
            } catch (error) {
                console.error('Error replacing item:', error);
                showToast('Error replacing item: ' + error.message, 'danger');
            }
        }

        // ===== ALL RESERVATIONS COMPLETE CHECK =====

        async function checkAllReservationsFulfilled() {
            if (!currentJobForReservations) return;
            const job = allJobs.find(j => j.id === currentJobForReservations.id);
            if (!job || job.status === 'completed' || job.status === 'cancelled') return;

            const allDone = jobReservations.length > 0 &&
                jobReservations.every(r => r.status === 'fulfilled' || r.status === 'cancelled');

            if (!allDone) return;

            const proceed = await showConfirm(
                'All reservations are fulfilled or cancelled. Mark this job as Completed?',
                'Complete Job?', 'Mark Completed', 'btn-success'
            );
            if (!proceed) return;

            try {
                const response = await fetch(`/api/v1/business-jobs/${currentJobForReservations.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                    body: JSON.stringify({ status: 'completed' }),
                });

                if (response.ok) {
                    await loadJobs();
                    showToast('Job marked as Completed', 'success');
                } else {
                    const err = await response.json();
                    showToast(err.message || 'Could not update job status', 'danger');
                }
            } catch (e) {
                console.error(e);
                showToast('Error updating job status', 'danger');
            }
        }

        // ===== MATERIAL CHECK FUNCTIONS =====
        let materialCheckResults = [];
        let selectedMaterialItems = new Set();

        function showMaterialCheckModal() {
            // Reset modal state
            document.getElementById('materialCheckFile').value = '';
            document.getElementById('materialCheckResults').style.display = 'none';
            materialCheckResults = [];
            selectedMaterialItems.clear();

            showModal(document.getElementById('materialCheckModal'));
        }

        async function checkJobMaterials() {
            const fileInput = document.getElementById('materialCheckFile');
            const file = fileInput.files[0];

            if (!file) {
                showToast('Please select a file', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('mode', 'ez_estimate');

            try {
                const response = await fetch('/api/v1/fulfillment/material-check', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || error.message || 'Failed to check materials');
                }

                const data = await response.json();
                materialCheckResults = data.results;
                displayMaterialCheckResults(data.results, data.summary);
            } catch (error) {
                console.error('Error checking materials:', error);
                showToast('Error checking materials: ' + error.message, 'danger');
            }
        }

        function displayMaterialCheckResults(results, summary) {
            // Update summary cards
            document.getElementById('mcStatTotal').textContent = summary.total;
            document.getElementById('mcStatAvailable').textContent = summary.available;
            document.getElementById('mcStatPartial').textContent = summary.partial;
            document.getElementById('mcStatUnavailable').textContent = summary.unavailable + summary.not_found;

            // Show results section
            document.getElementById('materialCheckResults').style.display = 'block';

            // Build table
            const tbody = document.getElementById('materialCheckTableBody');
            tbody.innerHTML = '';

            results.forEach((item, index) => {
                const row = document.createElement('tr');

                let statusBadge = '';
                let statusClass = '';
                let canCommit = false;

                if (item.status === 'available') {
                    statusBadge = '<span class="badge bg-success">Available</span>';
                    canCommit = true;
                } else if (item.status === 'partial') {
                    statusBadge = '<span class="badge bg-warning">Partial</span>';
                    statusClass = 'table-warning';
                    canCommit = true;
                } else if (item.status === 'unavailable') {
                    statusBadge = '<span class="badge bg-danger">Out of Stock</span>';
                    statusClass = 'table-danger';
                } else if (item.status === 'not_found') {
                    statusBadge = '<span class="badge bg-secondary">Not Found</span>';
                    statusClass = 'table-secondary';
                }

                // Only show checkbox for items found in inventory
                const checkboxHtml = item.product_id && canCommit
                    ? `<input type="checkbox" class="mc-item-checkbox" data-index="${index}" onchange="toggleMcItemSelection(${index})">`
                    : '';

                // Format quantities
                const packSize = item.pack_size || 1;
                const hasPackSize = packSize > 1;

                const reqPacks = item.required_qty_packs ?? item.required_quantity ?? 0;
                const reqEaches = item.required_qty_eaches ?? item.required_quantity ?? 0;
                const requiredDisplay = hasPackSize
                    ? `${reqPacks} pk<br><small class="text-muted">${reqEaches} ea</small>`
                    : reqEaches;

                const availPacks = item.available_qty_packs ?? 0;
                const availEaches = item.available_qty_eaches ?? item.available_quantity ?? 0;
                const availableDisplay = item.status === 'not_found'
                    ? '-'
                    : (hasPackSize
                        ? `${availPacks} pk<br><small class="text-muted">${availEaches} ea</small>`
                        : availEaches);

                const shortEaches = item.shortage_eaches ?? item.shortage ?? 0;
                const shortPacks = item.shortage_packs ?? item.shortage ?? 0;
                const shortageDisplay = shortEaches > 0
                    ? (hasPackSize
                        ? `<span class="text-danger">${shortPacks} pk<br><small>${shortEaches} ea</small></span>`
                        : `<span class="text-danger">${shortEaches}</span>`)
                    : '-';

                row.className = statusClass;
                row.innerHTML = `
                    <td>${checkboxHtml}</td>
                    <td>${statusBadge}</td>
                    <td><strong>${escapeHtml(item.part_number)}</strong></td>
                    <td>${escapeHtml(item.finish || '-')}</td>
                    <td><code>${escapeHtml(item.sku || '-')}</code></td>
                    <td>${escapeHtml(item.description || '-')}</td>
                    <td class="text-end">${requiredDisplay}</td>
                    <td class="text-end">${availableDisplay}</td>
                    <td class="text-end">${shortageDisplay}</td>
                `;

                tbody.appendChild(row);
            });

            updateMcCommitButton();
        }

        function toggleMcItemSelection(index) {
            if (selectedMaterialItems.has(index)) {
                selectedMaterialItems.delete(index);
            } else {
                selectedMaterialItems.add(index);
            }
            updateMcCommitButton();
        }

        function toggleMcSelectAll() {
            const selectAll = document.getElementById('mcSelectAll');
            const checkboxes = document.querySelectorAll('.mc-item-checkbox');

            selectedMaterialItems.clear();
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
                if (selectAll.checked) {
                    const index = parseInt(checkbox.dataset.index);
                    selectedMaterialItems.add(index);
                }
            });

            updateMcCommitButton();
        }

        function updateMcCommitButton() {
            const commitButton = document.getElementById('mcCommitButton');
            if (selectedMaterialItems.size > 0) {
                commitButton.style.display = 'inline-flex';
            } else {
                commitButton.style.display = 'none';
            }
        }

        function showCommitModal() {
            if (selectedMaterialItems.size === 0) {
                showToast('Please select items to commit', 'warning');
                return;
            }

            // Update selected count
            document.getElementById('mcSelectedCount').textContent = selectedMaterialItems.size;

            // Reset form
            document.getElementById('mcRequestedBy').value = '';
            document.getElementById('mcNeededBy').value = '';
            document.getElementById('mcNotes').value = '';

            showModal(document.getElementById('commitMaterialsModal'));
        }

        async function commitJobMaterials() {
            if (!currentJobForReservations) {
                showToast('No job selected', 'warning');
                return;
            }

            const requestedBy = document.getElementById('mcRequestedBy').value;
            const neededBy = document.getElementById('mcNeededBy').value;
            const notes = document.getElementById('mcNotes').value;

            if (!requestedBy) {
                showToast('Please enter who requested this reservation', 'warning');
                return;
            }

            // Prepare items array - commit in EACHES (not packs)
            const items = [];
            selectedMaterialItems.forEach(index => {
                const item = materialCheckResults[index];
                if (item.product_id) {
                    // Use eaches values for commitment
                    const requiredEaches = item.required_qty_eaches ?? item.required_quantity ?? 0;
                    const availableEaches = item.available_qty_eaches ?? item.available_quantity ?? 0;

                    items.push({
                        product_id: item.product_id,
                        requested_qty: requiredEaches,
                        committed_qty: Math.min(requiredEaches, availableEaches)
                    });
                }
            });

            if (items.length === 0) {
                showToast('No valid items to commit', 'warning');
                return;
            }

            try {
                const response = await fetch(`/api/v1/business-jobs/${currentJobForReservations.id}/reservations`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                    body: JSON.stringify({
                        requested_by: requestedBy,
                        needed_by: neededBy || null,
                        notes: notes || null,
                        items: items
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to create reservation');
                }

                const data = await response.json();

                hideModal(document.getElementById('commitMaterialsModal'));
                hideModal(document.getElementById('materialCheckModal'));

                await loadJobReservations(currentJobForReservations.id);
                await loadJobs();

                if (data.job_reopened) {
                    showToast('Job was reopened to Active because a new reservation was added.', 'info');
                }
                showToast(`Reservation #${data.reservation.reservation_id} created from material check (${items.length} items)`, 'success');
            } catch (error) {
                console.error('Error creating reservation:', error);
                showToast('Error creating reservation: ' + error.message, 'danger');
            }
        }
    </script>
@endsection
