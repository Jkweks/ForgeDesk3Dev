@extends('layouts.app')

@section('title', 'Job Reservations - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Fulfillment</div>
              <h1 class="page-title">Job Reservations</h1>
            </div>
            <div class="col-auto ms-auto">
              <div class="btn-list">
                <button type="button" class="btn btn-secondary" onclick="openManualReservationModal()">
                  <i class="ti ti-edit me-1"></i>
                  Manual Reservation
                </button>
                <a href="/fulfillment/material-check" class="btn btn-primary">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  New Material Check
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <!-- Status Filter Cards -->
          <div class="row row-deck row-cards mb-3">
            <div class="col-md-6 col-lg-3">
              <div class="card card-link" onclick="filterByStatus('')">
                <div class="card-body">
                  <div class="subheader">Total Reservations</div>
                  <div class="h2 mb-0" id="statTotal">0</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="card card-link" onclick="filterByStatus('active')">
                <div class="card-body">
                  <div class="subheader">Active</div>
                  <div class="h2 mb-0 text-primary" id="statActive">0</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="card card-link" onclick="filterByStatus('in_progress')">
                <div class="card-body">
                  <div class="subheader">In Progress</div>
                  <div class="h2 mb-0 text-info" id="statInProgress">0</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="card card-link" onclick="filterByStatus('fulfilled')">
                <div class="card-body">
                  <div class="subheader">Fulfilled</div>
                  <div class="h2 mb-0 text-success" id="statFulfilled">0</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Reservations List -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Job Reservations</h3>
                  <div class="col-auto ms-auto">
                    <div class="d-flex gap-2">
                      <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search..." onkeyup="filterReservations()">
                      <select class="form-select form-select-sm" id="statusFilter" onchange="filterReservations()">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="in_progress">In Progress</option>
                        <option value="fulfilled">Fulfilled</option>
                        <option value="on_hold">On Hold</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-vcenter card-table">
                    <thead>
                      <tr>
                        <th>Job #</th>
                        <th>Release</th>
                        <th>Job Name</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Needed By</th>
                        <th>Items</th>
                        <th>Committed</th>
                        <th>Consumed</th>
                        <th>Created</th>
                        <th class="w-1"></th>
                      </tr>
                    </thead>
                    <tbody id="reservationsTableBody">
                      <tr>
                        <td colspan="11" class="text-center text-muted">Loading...</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Reservation Detail Modal -->
    <div class="modal modal-blur fade" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="detailModalTitle">Reservation Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="detailModalBody">
            <!-- Content loaded dynamically -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
            <div id="detailModalActions"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal modal-blur fade" id="statusModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Change Status</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="statusChangeReservationId">
            <div class="mb-3">
              <label class="form-label">New Status</label>
              <select class="form-select" id="newStatus">
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="in_progress">In Progress</option>
                <option value="fulfilled">Fulfilled</option>
                <option value="on_hold">On Hold</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div id="statusWarnings" class="alert alert-warning" style="display: none;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="confirmStatusChange()">Change Status</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Complete Job Modal -->
    <div class="modal modal-blur fade" id="completeModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Complete Job</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="completeReservationId">
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
                <tbody id="completeItemsTable">
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" onclick="confirmComplete()">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
              Complete Job
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Reservation Modal -->
    <div class="modal modal-blur fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editModalTitle">Edit Reservation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="editReservationId">

            <!-- Reservation Header -->
            <div class="card mb-3">
              <div class="card-header">
                <h3 class="card-title">Reservation Information</h3>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Job Number <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="editJobNumber" readonly>
                      <small class="form-hint">Job number cannot be changed</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Release Number <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="editReleaseNumber" readonly>
                      <small class="form-hint">Release number cannot be changed</small>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Job Name</label>
                      <input type="text" class="form-control" id="editJobName" placeholder="Enter job name">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Requested By</label>
                      <input type="text" class="form-control" id="editRequestedBy" placeholder="Enter requester name">
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Needed By</label>
                      <input type="date" class="form-control" id="editNeededBy">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Status</label>
                      <input type="text" class="form-control" id="editStatus" readonly>
                      <small class="form-hint">Use "Change Status" button to modify status</small>
                    </div>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Notes</label>
                  <textarea class="form-control" id="editNotes" rows="3" placeholder="Add any notes..."></textarea>
                </div>
              </div>
            </div>

            <!-- Line Items -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Line Items</h3>
                <div class="card-actions">
                  <button type="button" class="btn btn-sm btn-primary" onclick="showAddItemForm()" id="addItemBtn">
                    <i class="ti ti-plus me-1"></i>Add Item
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div id="addItemForm" class="mb-3" style="display: none;">
                  <div class="card bg-light">
                    <div class="card-body">
                      <h4 class="card-title">Add New Item</h4>
                      <div class="row">
                        <div class="col-md-6">
                          <div class="mb-3">
                            <label class="form-label">Product SKU</label>
                            <input type="text" class="form-control" id="newItemSKU" placeholder="Search by SKU...">
                            <input type="hidden" id="newItemProductId">
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="mb-3">
                            <label class="form-label">Requested Qty</label>
                            <input type="number" class="form-control" id="newItemRequestedQty" min="1" value="1">
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="mb-3">
                            <label class="form-label">Committed Qty</label>
                            <input type="number" class="form-control" id="newItemCommittedQty" min="0" value="0">
                          </div>
                        </div>
                      </div>
                      <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success" onclick="addNewItem()">
                          <i class="ti ti-check me-1"></i>Add Item
                        </button>
                        <button type="button" class="btn" onclick="hideAddItemForm()">Cancel</button>
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
                    <tbody id="editItemsBody">
                      <!-- Items loaded dynamically -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveReservation()">
              <i class="ti ti-device-floppy me-1"></i>Save Changes
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Replace Item Modal -->
    <div class="modal modal-blur fade" id="replaceItemModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Replace Item</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="replaceItemIndex">
            <div class="alert alert-info">
              <strong>Replacing:</strong> <span id="replaceOldProduct"></span>
            </div>
            <div class="mb-3">
              <label class="form-label">Search for Replacement Product (by SKU, Part #, or Description)</label>
              <input type="text" class="form-control" id="replaceProductSearch" placeholder="Type to search...">
              <input type="hidden" id="replaceNewProductId">
            </div>
            <div id="replaceSearchResults" class="list-group mb-3" style="max-height: 300px; overflow-y: auto; display: none;">
              <!-- Search results populated here -->
            </div>
            <div id="replaceSelectedProduct" style="display: none;">
              <div class="card bg-light">
                <div class="card-body">
                  <h4 class="card-title">Selected Replacement</h4>
                  <div id="replaceProductDetails"></div>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Reason for Replacement (Optional)</label>
              <textarea class="form-control" id="replaceReason" rows="2" placeholder="e.g., Finish color change requested"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="confirmReplaceItem()">
              <i class="ti ti-replace me-1"></i>Replace Item
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Manual Reservation Modal -->
    <div class="modal modal-blur fade" id="manualReservationModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Create Manual Reservation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Job Information -->
            <div class="card mb-3">
              <div class="card-header">
                <h3 class="card-title">Job Information</h3>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-4">
                    <div class="mb-3">
                      <label class="form-label">Job Number <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="manualJobNumber" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="mb-3">
                      <label class="form-label">Release Number <span class="text-danger">*</span></label>
                      <input type="number" class="form-control" id="manualReleaseNumber" min="1" value="1" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="mb-3">
                      <label class="form-label">Needed By</label>
                      <input type="date" class="form-control" id="manualNeededBy">
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Job Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="manualJobName" required>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Requested By <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="manualRequestedBy" required>
                    </div>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Notes</label>
                  <textarea class="form-control" id="manualNotes" rows="2"></textarea>
                </div>
              </div>
            </div>

            <!-- Line Items -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Line Items</h3>
                <div class="card-actions">
                  <button type="button" class="btn btn-sm btn-primary" onclick="showManualAddItemForm()" id="manualAddItemBtn">
                    <i class="ti ti-plus me-1"></i>Add Item
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div id="manualAddItemForm" class="mb-3" style="display: none;">
                  <div class="card bg-light">
                    <div class="card-body">
                      <h4 class="card-title">Add Item</h4>
                      <div class="row">
                        <div class="col-md-8">
                          <div class="mb-3">
                            <label class="form-label">Product (Search by SKU, Part #, or Description)</label>
                            <input type="text" class="form-control" id="manualItemSearch" placeholder="Type to search...">
                            <input type="hidden" id="manualItemProductId">
                          </div>
                          <div id="manualSearchResults" class="list-group mb-3" style="max-height: 200px; overflow-y: auto; display: none;">
                            <!-- Search results -->
                          </div>
                          <div id="manualSelectedProduct" style="display: none;" class="alert alert-success mb-3">
                            <strong>Selected:</strong> <span id="manualProductInfo"></span>
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="mb-3">
                            <label class="form-label">Requested Qty</label>
                            <input type="number" class="form-control" id="manualItemRequestedQty" min="1" value="1">
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="mb-3">
                            <label class="form-label">Committed Qty</label>
                            <input type="number" class="form-control" id="manualItemCommittedQty" min="0" value="0">
                            <small class="form-hint">Leave 0 for auto</small>
                          </div>
                        </div>
                      </div>
                      <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success" onclick="addManualItem()">
                          <i class="ti ti-check me-1"></i>Add Item
                        </button>
                        <button type="button" class="btn" onclick="hideManualAddItemForm()">Cancel</button>
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
                        <th class="text-end">Available</th>
                        <th class="text-center">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="manualItemsBody">
                      <tr>
                        <td colspan="8" class="text-center text-muted">No items added yet. Click "Add Item" to begin.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="createManualReservation()">
              <i class="ti ti-device-floppy me-1"></i>Create Reservation
            </button>
          </div>
        </div>
      </div>
    </div>

    <script>
        let reservations = [];
        let filteredReservations = [];
        let completeItems = [];
        let editingReservation = null;
        let editingItems = [];

        // Helper function to close modal
        function closeModal(modalId) {
            const modalElement = document.getElementById(modalId);
            modalElement.classList.remove('show');
            modalElement.style.display = 'none';
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.removeAttribute('aria-modal');
            document.body.classList.remove('modal-open');

            // Remove backdrop
            const backdrop = document.querySelector(`.modal-backdrop[data-modal-id="${modalId}"]`);
            if (backdrop) {
                backdrop.remove();
            }
        }

        // Load reservations on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadReservations();

            // Add close button handlers for all modals
            document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(button => {
                button.addEventListener('click', function(e) {
                    const modal = this.closest('.modal');
                    if (modal) {
                        closeModal(modal.id);
                    }
                });
            });
        });

        async function loadReservations() {
            try {
                const response = await fetch('/api/v1/job-reservations', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    reservations = data.reservations;
                    filteredReservations = [...reservations];
                    displayReservations();
                    updateStats();
                } else {
                    const error = await response.json();
                    console.error('Error loading reservations:', error);
                    document.getElementById('reservationsTableBody').innerHTML = '<tr><td colspan="11" class="text-center text-danger">Error loading reservations</td></tr>';
                }
            } catch (error) {
                console.error('Error loading reservations:', error);
                document.getElementById('reservationsTableBody').innerHTML = '<tr><td colspan="11" class="text-center text-danger">Error loading reservations</td></tr>';
            }
        }

        function displayReservations() {
            const tbody = document.getElementById('reservationsTableBody');

            if (filteredReservations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted">No reservations found</td></tr>';
                return;
            }

            tbody.innerHTML = filteredReservations.map(res => {
                const statusBadge = getStatusBadge(res.status);
                const rowClass = res.status === 'fulfilled' ? 'table-success' : res.status === 'cancelled' ? 'table-secondary' : '';

                return `
                    <tr class="${rowClass}">
                        <td><strong>${res.job_number}</strong></td>
                        <td>${res.release_number}</td>
                        <td>${res.job_name}</td>
                        <td>${statusBadge}</td>
                        <td>${res.requested_by}</td>
                        <td>${res.needed_by || '-'}</td>
                        <td>${res.items_count}</td>
                        <td>${res.total_committed}</td>
                        <td>${res.total_consumed}</td>
                        <td><small class="text-muted">${new Date(res.created_at).toLocaleDateString()}</small></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-sm btn-primary" onclick="viewDetails(${res.id})" title="View Details">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-sm"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg>
                                </button>
                                ${res.status === 'in_progress' ? `
                                    <button class="btn btn-sm btn-success" onclick="showCompleteModal(${res.id})" title="Complete Job">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-sm"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                                    </button>
                                ` : ''}
                                ${res.status !== 'fulfilled' && res.status !== 'cancelled' ? `
                                    <button class="btn btn-sm btn-secondary" onclick="showStatusModal(${res.id}, '${res.status}')" title="Change Status">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-sm"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /><path d="M9 12l2 2l4 -4" /></svg>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function getStatusBadge(status) {
            const badges = {
                'draft': '<span class="badge bg-secondary">Draft</span>',
                'active': '<span class="badge bg-primary">Active</span>',
                'in_progress': '<span class="badge bg-info">In Progress</span>',
                'fulfilled': '<span class="badge bg-success">Fulfilled</span>',
                'on_hold': '<span class="badge bg-warning">On Hold</span>',
                'cancelled': '<span class="badge bg-dark">Cancelled</span>',
            };
            return badges[status] || `<span class="badge">${status}</span>`;
        }

        function updateStats() {
            const stats = {
                total: reservations.length,
                active: reservations.filter(r => r.status === 'active').length,
                in_progress: reservations.filter(r => r.status === 'in_progress').length,
                fulfilled: reservations.filter(r => r.status === 'fulfilled').length,
            };

            document.getElementById('statTotal').textContent = stats.total;
            document.getElementById('statActive').textContent = stats.active;
            document.getElementById('statInProgress').textContent = stats.in_progress;
            document.getElementById('statFulfilled').textContent = stats.fulfilled;
        }

        function filterReservations() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;

            filteredReservations = reservations.filter(res => {
                const matchesSearch = !searchTerm ||
                    res.job_number.toLowerCase().includes(searchTerm) ||
                    res.job_name.toLowerCase().includes(searchTerm) ||
                    res.requested_by.toLowerCase().includes(searchTerm);

                const matchesStatus = !statusFilter || res.status === statusFilter;

                return matchesSearch && matchesStatus;
            });

            displayReservations();
        }

        function filterByStatus(status) {
            document.getElementById('statusFilter').value = status;
            filterReservations();
        }

        async function viewDetails(id) {
            try {
                const response = await fetch(`/api/v1/job-reservations/${id}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    showDetailsModal(data);
                } else {
                    alert('Error loading reservation details');
                }
            } catch (error) {
                console.error('Error loading details:', error);
                alert('Error loading reservation details');
            }
        }

        function showDetailsModal(data) {
            const res = data.reservation;
            const items = data.items;

            document.getElementById('detailModalTitle').textContent = `Reservation #${res.id} - ${res.job_number} Release ${res.release_number}`;

            const itemsTable = items.map(item => `
                <tr>
                    <td><code>${item.product.sku || '-'}</code></td>
                    <td><strong>${item.product.part_number}</strong></td>
                    <td>${item.product.finish || '-'}</td>
                    <td>${item.product.description || '-'}</td>
                    <td>${item.requested_qty}</td>
                    <td>${item.committed_qty}</td>
                    <td>${item.consumed_qty}</td>
                    <td>${item.released_qty}</td>
                    <td>${item.product.quantity_on_hand}</td>
                    <td>${item.product.quantity_available}</td>
                    <td>${item.product.location || '-'}</td>
                </tr>
            `).join('');

            document.getElementById('detailModalBody').innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-5">Job Number:</dt>
                            <dd class="col-7"><strong>${res.job_number}</strong></dd>
                            <dt class="col-5">Release Number:</dt>
                            <dd class="col-7">${res.release_number}</dd>
                            <dt class="col-5">Job Name:</dt>
                            <dd class="col-7">${res.job_name}</dd>
                            <dt class="col-5">Status:</dt>
                            <dd class="col-7">${getStatusBadge(res.status)}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-5">Requested By:</dt>
                            <dd class="col-7">${res.requested_by}</dd>
                            <dt class="col-5">Needed By:</dt>
                            <dd class="col-7">${res.needed_by || '-'}</dd>
                            <dt class="col-5">Created:</dt>
                            <dd class="col-7">${new Date(res.created_at).toLocaleString()}</dd>
                            <dt class="col-5">Updated:</dt>
                            <dd class="col-7">${new Date(res.updated_at).toLocaleString()}</dd>
                        </dl>
                    </div>
                </div>
                ${res.notes ? `<div class="mb-3"><strong>Notes:</strong> ${res.notes}</div>` : ''}
                <h4>Line Items</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Part #</th>
                                <th>Finish</th>
                                <th>Description</th>
                                <th>Requested</th>
                                <th>Committed</th>
                                <th>Consumed</th>
                                <th>Released</th>
                                <th>On Hand</th>
                                <th>Available</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsTable}
                        </tbody>
                    </table>
                </div>
            `;

            // Add Edit button if not in terminal state
            const detailModalActions = document.getElementById('detailModalActions');
            if (!['fulfilled', 'cancelled'].includes(res.status)) {
                detailModalActions.innerHTML = `
                    <button type="button" class="btn btn-primary" onclick="openEditModal(${res.id})">
                        <i class="ti ti-edit me-1"></i>Edit Reservation
                    </button>
                `;
            } else {
                detailModalActions.innerHTML = '';
            }

            // Show modal using DOM manipulation
            const modalElement = document.getElementById('detailModal');
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            modalElement.setAttribute('aria-modal', 'true');
            modalElement.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');

            // Add backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.setAttribute('data-modal-id', 'detailModal');
            document.body.appendChild(backdrop);
        }

        function showStatusModal(id, currentStatus) {
            document.getElementById('statusChangeReservationId').value = id;
            document.getElementById('newStatus').value = currentStatus;
            document.getElementById('statusWarnings').style.display = 'none';

            // Show modal using DOM manipulation
            const modalElement = document.getElementById('statusModal');
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            modalElement.setAttribute('aria-modal', 'true');
            modalElement.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');

            // Add backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.setAttribute('data-modal-id', 'statusModal');
            document.body.appendChild(backdrop);
        }

        async function confirmStatusChange() {
            const id = document.getElementById('statusChangeReservationId').value;
            const status = document.getElementById('newStatus').value;

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

                    // Show warnings if any
                    if (data.warnings && data.warnings.length > 0) {
                        const warningsDiv = document.getElementById('statusWarnings');
                        warningsDiv.innerHTML = data.warnings.join('<br>');
                        warningsDiv.style.display = 'block';

                        if (data.insufficient_items && data.insufficient_items.length > 0) {
                            const items = data.insufficient_items.map(item =>
                                `${item.part_number}-${item.finish}: need ${item.shortage} more`
                            ).join(', ');
                            warningsDiv.innerHTML += `<br><strong>Insufficient items:</strong> ${items}`;
                        }

                        // Ask for confirmation
                        if (!confirm('There are warnings. Do you want to proceed anyway?')) {
                            return;
                        }
                    }

                    // Close modal
                    const modalElement = document.getElementById('statusModal');
                    modalElement.classList.remove('show');
                    modalElement.style.display = 'none';
                    modalElement.setAttribute('aria-hidden', 'true');
                    modalElement.removeAttribute('aria-modal');
                    document.body.classList.remove('modal-open');

                    // Remove backdrop
                    const backdrop = document.querySelector('.modal-backdrop[data-modal-id="statusModal"]');
                    if (backdrop) {
                        backdrop.remove();
                    }

                    // Reload reservations
                    await loadReservations();

                    alert(`✅ Status updated to: ${data.reservation.new_status}`);
                } else {
                    const error = await response.json();
                    alert('Error updating status: ' + (error.message || error.error));
                }
            } catch (error) {
                console.error('Error updating status:', error);
                alert('Error updating status: ' + error.message);
            }
        }

        async function showCompleteModal(id) {
            // Show loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'completeModalLoading';
            loadingDiv.className = 'position-fixed top-50 start-50 translate-middle text-center';
            loadingDiv.style.zIndex = '9999';
            loadingDiv.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading reservation...</p>';
            document.body.appendChild(loadingDiv);

            try {
                const response = await fetch(`/api/v1/job-reservations/${id}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                // Remove loading indicator
                loadingDiv.remove();

                if (response.ok) {
                    const data = await response.json();
                    const items = data.items;

                    document.getElementById('completeReservationId').value = id;
                    completeItems = items;

                    const tbody = document.getElementById('completeItemsTable');
                    tbody.innerHTML = items.map((item, index) => {
                        const toRelease = item.committed_qty - item.consumed_qty;
                        return `
                            <tr>
                                <td><strong>${item.product.part_number}</strong></td>
                                <td>${item.product.finish || '-'}</td>
                                <td>${item.committed_qty}</td>
                                <td>${item.consumed_qty}</td>
                                <td>
                                    <input type="number"
                                        class="form-control form-control-sm"
                                        id="consumed_${item.product_id}"
                                        data-product-id="${item.product_id}"
                                        data-committed="${item.committed_qty}"
                                        data-already-consumed="${item.consumed_qty}"
                                        value="${item.consumed_qty}"
                                        min="${item.consumed_qty}"
                                        onchange="updateToRelease(${item.product_id})"
                                        style="width: 100px;"
                                        title="Can consume more than committed if needed">
                                </td>
                                <td>
                                    <span id="release_${item.product_id}" class="badge bg-success" title="">${toRelease}</span>
                                </td>
                            </tr>
                        `;
                    }).join('');

                    // Show modal using DOM manipulation
                    const modalElement = document.getElementById('completeModal');
                    modalElement.classList.add('show');
                    modalElement.style.display = 'block';
                    modalElement.setAttribute('aria-modal', 'true');
                    modalElement.removeAttribute('aria-hidden');
                    document.body.classList.add('modal-open');

                    // Add backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.setAttribute('data-modal-id', 'completeModal');
                    document.body.appendChild(backdrop);
                } else {
                    alert('Error loading reservation details');
                }
            } catch (error) {
                // Remove loading indicator
                const loading = document.getElementById('completeModalLoading');
                if (loading) loading.remove();

                console.error('Error loading reservation:', error);
                alert('Error loading reservation details');
            }
        }

        function updateToRelease(productId) {
            const input = document.getElementById(`consumed_${productId}`);
            const consumed = parseInt(input.value) || 0;
            const committed = parseInt(input.dataset.committed);
            const alreadyConsumed = parseInt(input.dataset.alreadyConsumed);

            // Validate constraints
            if (consumed < alreadyConsumed) {
                alert(`Cannot reduce consumed quantity below already consumed (${alreadyConsumed})`);
                input.value = alreadyConsumed;
                return;
            }

            // Allow over-consumption but warn
            if (consumed > committed) {
                const overConsumption = consumed - committed;
                const badge = document.getElementById(`release_${productId}`);
                badge.textContent = `-${overConsumption}`;
                badge.className = 'badge bg-warning';
                badge.title = `Over-consuming by ${overConsumption} units`;
            } else {
                // Update to release badge
                const toRelease = committed - consumed;
                const badge = document.getElementById(`release_${productId}`);
                badge.textContent = toRelease;
                badge.className = 'badge bg-success';
                badge.title = '';
            }
        }

        async function confirmComplete() {
            const id = document.getElementById('completeReservationId').value;
            const consumedQuantities = {};

            // Gather all consumed quantities
            completeItems.forEach(item => {
                const input = document.getElementById(`consumed_${item.product_id}`);
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

                    // Close modal
                    const modalElement = document.getElementById('completeModal');
                    modalElement.classList.remove('show');
                    modalElement.style.display = 'none';
                    modalElement.setAttribute('aria-hidden', 'true');
                    modalElement.removeAttribute('aria-modal');
                    document.body.classList.remove('modal-open');

                    // Remove backdrop
                    const backdrop = document.querySelector('.modal-backdrop[data-modal-id="completeModal"]');
                    if (backdrop) {
                        backdrop.remove();
                    }

                    // Reload reservations
                    await loadReservations();

                    // Show success message
                    const summary = data.items.map(item =>
                        `${item.part_number}-${item.finish}: Consumed ${item.consumed}, Released ${item.released}`
                    ).join('\n');

                    alert(`✅ Job completed successfully!\n\nJob: ${data.reservation.job_number} Release ${data.reservation.release_number}\nTotal Consumed: ${data.reservation.total_consumed}\nTotal Released: ${data.reservation.total_released}\n\n${summary}`);
                } else {
                    const error = await response.json();
                    alert('Error completing job: ' + (error.message || error.error));
                }
            } catch (error) {
                console.error('Error completing job:', error);
                alert('Error completing job: ' + error.message);
            }
        }

        // ===== EDIT RESERVATION FUNCTIONS =====

        async function openEditModal(id) {
            try {
                const response = await fetch(`/api/v1/job-reservations/${id}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    editingReservation = data.reservation;
                    editingItems = data.items;
                    showEditModal();
                    closeModal('detailModal'); // Close detail modal
                } else {
                    alert('Error loading reservation for editing');
                }
            } catch (error) {
                console.error('Error loading reservation:', error);
                alert('Error loading reservation for editing');
            }
        }

        function showEditModal() {
            const res = editingReservation;

            // Populate form fields
            document.getElementById('editReservationId').value = res.id;
            document.getElementById('editJobNumber').value = res.job_number;
            document.getElementById('editReleaseNumber').value = res.release_number;
            document.getElementById('editJobName').value = res.job_name || '';
            document.getElementById('editRequestedBy').value = res.requested_by || '';
            document.getElementById('editNeededBy').value = res.needed_by || '';
            document.getElementById('editStatus').value = res.status_label || res.status;
            document.getElementById('editNotes').value = res.notes || '';

            // Reset add item form
            hideAddItemForm();

            // Populate line items
            renderEditItems();

            // Show modal
            const modalElement = document.getElementById('editModal');
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            modalElement.setAttribute('aria-modal', 'true');
            modalElement.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');

            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.setAttribute('data-modal-id', 'editModal');
            document.body.appendChild(backdrop);
        }

        function renderEditItems() {
            const tbody = document.getElementById('editItemsBody');

            if (editingItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No items</td></tr>';
                return;
            }

            tbody.innerHTML = editingItems.map((item, index) => `
                <tr>
                    <td><code>${item.product.sku || '-'}</code></td>
                    <td>${item.product.part_number || '-'}</td>
                    <td>${item.product.finish || '-'}</td>
                    <td>${item.product.description || '-'}</td>
                    <td class="text-end">
                        <input type="number" class="form-control form-control-sm"
                               value="${item.requested_qty}"
                               min="1"
                               onchange="updateItemQuantity(${index}, 'requested_qty', this.value)"
                               style="width: 80px;">
                    </td>
                    <td class="text-end">
                        <input type="number" class="form-control form-control-sm"
                               value="${item.committed_qty}"
                               min="0"
                               onchange="updateItemQuantity(${index}, 'committed_qty', this.value)"
                               style="width: 80px;"
                               ${item.consumed_qty > 0 ? `min="${item.consumed_qty}"` : ''}>
                    </td>
                    <td class="text-end">${item.consumed_qty}</td>
                    <td class="text-end">${item.product.quantity_on_hand}</td>
                    <td class="text-center">
                        ${item.consumed_qty === 0 ? `
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-ghost-primary"
                                        onclick="showReplaceItemModal(${index})"
                                        title="Replace with different part/finish">
                                    <i class="ti ti-replace"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-ghost-danger"
                                        onclick="removeEditItem(${index})"
                                        title="Remove item">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                        ` : '<span class="text-muted" title="Cannot modify consumed item">-</span>'}
                    </td>
                </tr>
            `).join('');
        }

        function updateItemQuantity(index, field, value) {
            const item = editingItems[index];
            const numValue = parseInt(value);

            if (field === 'committed_qty' && numValue < item.consumed_qty) {
                alert(`Cannot reduce committed quantity below already consumed (${item.consumed_qty})`);
                renderEditItems();
                return;
            }

            if (field === 'committed_qty' && numValue > item.committed_qty) {
                const increase = numValue - item.committed_qty;
                if (increase > item.product.quantity_available) {
                    alert(`Only ${item.product.quantity_available} available. Cannot increase by ${increase}.`);
                    renderEditItems();
                    return;
                }
            }

            item[field] = numValue;
        }

        function showAddItemForm() {
            document.getElementById('addItemForm').style.display = 'block';
            document.getElementById('addItemBtn').style.display = 'none';
            document.getElementById('newItemSKU').value = '';
            document.getElementById('newItemProductId').value = '';
            document.getElementById('newItemRequestedQty').value = 1;
            document.getElementById('newItemCommittedQty').value = 0;
        }

        function hideAddItemForm() {
            document.getElementById('addItemForm').style.display = 'none';
            document.getElementById('addItemBtn').style.display = 'block';
        }

        async function addNewItem() {
            const sku = document.getElementById('newItemSKU').value.trim();
            const requestedQty = parseInt(document.getElementById('newItemRequestedQty').value);
            const committedQty = parseInt(document.getElementById('newItemCommittedQty').value);

            if (!sku) {
                alert('Please enter a product SKU');
                return;
            }

            // Find product by SKU using dedicated search endpoint
            try {
                const response = await fetch(`/api/v1/job-reservations/search-product?sku=${encodeURIComponent(sku)}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) {
                    const error = await response.json();
                    alert(error.message || 'Product not found');
                    return;
                }

                const data = await response.json();
                const product = data.product;

                if (!product) {
                    alert('Product not found with SKU: ' + sku);
                    return;
                }

                // Check if already in list
                if (editingItems.some(item => item.product_id === product.id)) {
                    alert('This product is already in the reservation');
                    return;
                }

                // Check available inventory
                if (committedQty > product.quantity_available) {
                    alert(`Only ${product.quantity_available} available. Cannot commit ${committedQty}.`);
                    return;
                }

                // Add to items array
                editingItems.push({
                    id: null, // New item, no ID yet
                    product_id: product.id,
                    requested_qty: requestedQty,
                    committed_qty: committedQty,
                    consumed_qty: 0,
                    product: {
                        id: product.id,
                        sku: product.sku,
                        part_number: product.part_number,
                        finish: product.finish,
                        description: product.description,
                        quantity_on_hand: product.quantity_on_hand,
                        quantity_available: product.quantity_available
                    }
                });

                renderEditItems();
                hideAddItemForm();

            } catch (error) {
                console.error('Error finding product:', error);
                alert('Error finding product');
            }
        }

        function removeEditItem(index) {
            if (confirm('Are you sure you want to remove this item?')) {
                editingItems.splice(index, 1);
                renderEditItems();
            }
        }

        async function saveReservation() {
            const id = document.getElementById('editReservationId').value;
            const jobName = document.getElementById('editJobName').value;
            const requestedBy = document.getElementById('editRequestedBy').value;
            const neededBy = document.getElementById('editNeededBy').value;
            const notes = document.getElementById('editNotes').value;

            try {
                // Update reservation header
                const headerResponse = await fetch(`/api/v1/job-reservations/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        job_name: jobName,
                        requested_by: requestedBy,
                        needed_by: neededBy,
                        notes: notes
                    })
                });

                if (!headerResponse.ok) {
                    const error = await headerResponse.json();
                    alert('Error updating reservation: ' + (error.message || error.error));
                    return;
                }

                // Update/add/remove items
                for (const item of editingItems) {
                    if (item.id === null) {
                        // New item - add it
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
                            alert('Error adding item: ' + (error.message || error.error));
                            return;
                        }
                    } else {
                        // Existing item - update it
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
                            alert('Error updating item: ' + (error.message || error.error));
                            return;
                        }
                    }
                }

                // Success
                alert('Reservation updated successfully');
                closeModal('editModal');
                loadReservations(); // Reload the list

            } catch (error) {
                console.error('Error saving reservation:', error);
                alert('Error saving reservation: ' + error.message);
            }
        }

        // ===== REPLACE ITEM FUNCTIONS =====
        let replaceItemProductSearch;

        function showReplaceItemModal(itemIndex) {
            const item = editingItems[itemIndex];
            document.getElementById('replaceItemIndex').value = itemIndex;
            document.getElementById('replaceOldProduct').textContent =
                `${item.product.sku} - ${item.product.part_number} ${item.product.finish || ''} (${item.product.description})`;

            // Reset search
            document.getElementById('replaceProductSearch').value = '';
            document.getElementById('replaceNewProductId').value = '';
            document.getElementById('replaceReason').value = '';
            document.getElementById('replaceSearchResults').style.display = 'none';
            document.getElementById('replaceSelectedProduct').style.display = 'none';

            // Setup search handler
            const searchInput = document.getElementById('replaceProductSearch');
            clearTimeout(replaceItemProductSearch);
            searchInput.oninput = function() {
                clearTimeout(replaceItemProductSearch);
                replaceItemProductSearch = setTimeout(() => searchReplaceProduct(this.value), 300);
            };

            // Show modal using DOM manipulation
            const modalElement = document.getElementById('replaceItemModal');
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            modalElement.setAttribute('aria-modal', 'true');
            modalElement.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');

            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.setAttribute('data-modal-id', 'replaceItemModal');
            document.body.appendChild(backdrop);
        }

        async function searchReplaceProduct(query) {
            if (query.length < 2) {
                document.getElementById('replaceSearchResults').style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`/api/v1/job-reservations/search-products?q=${encodeURIComponent(query)}&per_page=10`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    const resultsDiv = document.getElementById('replaceSearchResults');

                    if (data.data && data.data.length > 0) {
                        resultsDiv.innerHTML = data.data.map(product => `
                            <button type="button" class="list-group-item list-group-item-action" onclick="selectReplaceProduct(${product.id}, '${product.sku}', '${product.part_number || ''}', '${product.finish || ''}', '${product.description.replace(/'/g, "\\'")}', ${product.quantity_on_hand}, ${product.quantity_available})">
                                <div class="d-flex w-100 justify-content-between">
                                    <strong>${product.sku}</strong>
                                    <span class="badge bg-${product.quantity_available > 0 ? 'success' : 'danger'}">${product.quantity_available} avail</span>
                                </div>
                                <small>${product.part_number || ''} ${product.finish || ''} - ${product.description}</small>
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

        function selectReplaceProduct(productId, sku, partNumber, finish, description, onHand, available) {
            document.getElementById('replaceNewProductId').value = productId;
            document.getElementById('replaceSearchResults').style.display = 'none';
            document.getElementById('replaceProductSearch').value = sku;

            const detailsDiv = document.getElementById('replaceProductDetails');
            detailsDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>SKU:</strong> ${sku}<br>
                        <strong>Part#:</strong> ${partNumber}<br>
                        <strong>Finish:</strong> ${finish || 'N/A'}
                    </div>
                    <div class="col-md-6">
                        <strong>Description:</strong> ${description}<br>
                        <strong>On Hand:</strong> ${onHand}<br>
                        <strong>Available:</strong> <span class="badge bg-${available > 0 ? 'success' : 'danger'}">${available}</span>
                    </div>
                </div>
            `;
            document.getElementById('replaceSelectedProduct').style.display = 'block';
        }

        async function confirmReplaceItem() {
            const itemIndex = parseInt(document.getElementById('replaceItemIndex').value);
            const newProductId = document.getElementById('replaceNewProductId').value;
            const reason = document.getElementById('replaceReason').value;

            if (!newProductId) {
                alert('Please select a replacement product');
                return;
            }

            const item = editingItems[itemIndex];

            try {
                const response = await fetch(`/api/v1/job-reservations/${editingReservation.id}/items/${item.id}/replace`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        new_product_id: newProductId,
                        reason: reason
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    alert(`✅ ${data.message}\n\nOld: ${data.old_product.sku}\nNew: ${data.new_product.sku}`);

                    // Update the item in editing items array
                    editingItems[itemIndex].product = data.new_product;
                    editingItems[itemIndex].product_id = data.new_product.product_id;

                    // Re-render the items table
                    renderEditItems();

                    closeModal('replaceItemModal');
                } else {
                    const error = await response.json();
                    alert('Error: ' + (error.message || error.error));
                    if (error.details) {
                        console.error('Replace error details:', error.details);
                    }
                }
            } catch (error) {
                console.error('Error replacing item:', error);
                alert('Error replacing item: ' + error.message);
            }
        }

        // ===== MANUAL RESERVATION FUNCTIONS =====
        let manualItems = [];
        let manualItemSearchTimeout;

        function openManualReservationModal() {
            // Reset form
            document.getElementById('manualJobNumber').value = '';
            document.getElementById('manualReleaseNumber').value = '1';
            document.getElementById('manualJobName').value = '';
            document.getElementById('manualRequestedBy').value = '';
            document.getElementById('manualNeededBy').value = '';
            document.getElementById('manualNotes').value = '';

            manualItems = [];
            renderManualItems();
            hideManualAddItemForm();

            // Show modal using DOM manipulation
            const modalElement = document.getElementById('manualReservationModal');
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            modalElement.setAttribute('aria-modal', 'true');
            modalElement.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');

            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.setAttribute('data-modal-id', 'manualReservationModal');
            document.body.appendChild(backdrop);
        }

        function showManualAddItemForm() {
            document.getElementById('manualAddItemForm').style.display = 'block';
            document.getElementById('manualAddItemBtn').style.display = 'none';

            // Reset form
            document.getElementById('manualItemSearch').value = '';
            document.getElementById('manualItemProductId').value = '';
            document.getElementById('manualItemRequestedQty').value = '1';
            document.getElementById('manualItemCommittedQty').value = '0';
            document.getElementById('manualSearchResults').style.display = 'none';
            document.getElementById('manualSelectedProduct').style.display = 'none';

            // Setup search handler
            const searchInput = document.getElementById('manualItemSearch');
            searchInput.oninput = function() {
                clearTimeout(manualItemSearchTimeout);
                manualItemSearchTimeout = setTimeout(() => searchManualProduct(this.value), 300);
            };
        }

        function hideManualAddItemForm() {
            document.getElementById('manualAddItemForm').style.display = 'none';
            document.getElementById('manualAddItemBtn').style.display = 'block';
        }

        async function searchManualProduct(query) {
            if (query.length < 2) {
                document.getElementById('manualSearchResults').style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`/api/v1/job-reservations/search-products?q=${encodeURIComponent(query)}&per_page=10`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    const resultsDiv = document.getElementById('manualSearchResults');

                    if (data.data && data.data.length > 0) {
                        resultsDiv.innerHTML = data.data.map(product => `
                            <button type="button" class="list-group-item list-group-item-action" onclick="selectManualProduct(${product.id}, '${product.sku}', '${product.part_number || ''}', '${product.finish || ''}', '${product.description.replace(/'/g, "\\'")}', ${product.quantity_available})">
                                <div class="d-flex w-100 justify-content-between">
                                    <strong>${product.sku}</strong>
                                    <span class="badge bg-${product.quantity_available > 0 ? 'success' : 'warning'}">${product.quantity_available} avail</span>
                                </div>
                                <small>${product.part_number || ''} ${product.finish || ''} - ${product.description}</small>
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

        function selectManualProduct(productId, sku, partNumber, finish, description, available) {
            document.getElementById('manualItemProductId').value = productId;
            document.getElementById('manualSearchResults').style.display = 'none';
            document.getElementById('manualItemSearch').value = sku;
            document.getElementById('manualProductInfo').textContent = `${sku} - ${partNumber} ${finish} (${available} available)`;
            document.getElementById('manualSelectedProduct').style.display = 'block';

            // Store product details for later
            window.tempManualProduct = { productId, sku, partNumber, finish, description, available };
        }

        function addManualItem() {
            const productId = document.getElementById('manualItemProductId').value;
            const requestedQty = parseInt(document.getElementById('manualItemRequestedQty').value);
            const committedQty = parseInt(document.getElementById('manualItemCommittedQty').value);

            if (!productId) {
                alert('Please select a product');
                return;
            }

            if (requestedQty < 1) {
                alert('Requested quantity must be at least 1');
                return;
            }

            // Check if product already in list
            if (manualItems.some(item => item.product_id == productId)) {
                alert('This product is already in the reservation');
                return;
            }

            const product = window.tempManualProduct;
            manualItems.push({
                product_id: productId,
                sku: product.sku,
                part_number: product.partNumber,
                finish: product.finish,
                description: product.description,
                requested_qty: requestedQty,
                committed_qty: committedQty,
                available: product.available
            });

            renderManualItems();
            hideManualAddItemForm();
        }

        function renderManualItems() {
            const tbody = document.getElementById('manualItemsBody');

            if (manualItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No items added yet. Click "Add Item" to begin.</td></tr>';
                return;
            }

            tbody.innerHTML = manualItems.map((item, index) => `
                <tr>
                    <td><code>${item.sku}</code></td>
                    <td>${item.part_number}</td>
                    <td>${item.finish || '-'}</td>
                    <td>${item.description}</td>
                    <td class="text-end">${item.requested_qty}</td>
                    <td class="text-end">${item.committed_qty || 'Auto'}</td>
                    <td class="text-end"><span class="badge bg-${item.available > 0 ? 'success' : 'warning'}">${item.available}</span></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-ghost-danger" onclick="removeManualItem(${index})" title="Remove">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function removeManualItem(index) {
            manualItems.splice(index, 1);
            renderManualItems();
        }

        async function createManualReservation() {
            // Validate form
            const jobNumber = document.getElementById('manualJobNumber').value.trim();
            const releaseNumber = parseInt(document.getElementById('manualReleaseNumber').value);
            const jobName = document.getElementById('manualJobName').value.trim();
            const requestedBy = document.getElementById('manualRequestedBy').value.trim();
            const neededBy = document.getElementById('manualNeededBy').value;
            const notes = document.getElementById('manualNotes').value.trim();

            if (!jobNumber) {
                alert('Job Number is required');
                return;
            }

            if (!jobName) {
                alert('Job Name is required');
                return;
            }

            if (!requestedBy) {
                alert('Requested By is required');
                return;
            }

            if (manualItems.length === 0) {
                alert('Please add at least one item to the reservation');
                return;
            }

            try {
                const response = await fetch('/api/v1/job-reservations/create-manual', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        job_number: jobNumber,
                        release_number: releaseNumber,
                        job_name: jobName,
                        requested_by: requestedBy,
                        needed_by: neededBy || null,
                        notes: notes || null,
                        items: manualItems
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    let message = `✅ Manual reservation created successfully!\n\nJob: ${data.reservation.job_number}-${data.reservation.release_number}\nStatus: ${data.reservation.status_label}\nItems: ${data.items.length}`;

                    if (data.warnings && data.warnings.length > 0) {
                        message += '\n\n⚠️ Warnings:\n';
                        data.warnings.forEach(w => {
                            message += `- ${w.message}\n`;
                        });
                    }

                    alert(message);
                    closeModal('manualReservationModal');
                    loadReservations();
                } else {
                    const error = await response.json();
                    alert('Error: ' + (error.message || error.error));
                    if (error.details) {
                        console.error('Error details:', error.details);
                    }
                }
            } catch (error) {
                console.error('Error creating manual reservation:', error);
                alert('Error creating manual reservation: ' + error.message);
            }
        }
    </script>
@endsection
