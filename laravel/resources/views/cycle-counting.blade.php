@extends('layouts.app')

@section('content')
<style>
/* Tablet/iPad Modal Optimizations */
@media (min-width: 768px) and (max-width: 1366px) {
  /* Modal sizing for tablets in landscape */
  .modal-xl .modal-dialog {
    max-width: 95vw !important;
    margin: 0.5rem auto;
  }

  .modal-lg .modal-dialog {
    max-width: 85vw !important;
    margin: 0.5rem auto;
  }

  /* Optimize modal body for scrolling on smaller screens */
  .modal-body {
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
  }

  /* Touch-friendly buttons - minimum 44px touch target */
  .modal-footer .btn,
  .modal-header .btn,
  .btn {
    min-height: 44px;
    padding: 0.625rem 1rem;
  }

  /* Larger close button for touch */
  .btn-close {
    min-width: 44px;
    min-height: 44px;
    padding: 1rem;
  }

  /* Form inputs optimized for touch */
  .form-control,
  .form-select {
    min-height: 44px;
    font-size: 16px; /* Prevents zoom on iOS */
    padding: 0.625rem 0.75rem;
  }

  /* Checkboxes and radio buttons - larger touch targets */
  .form-check-input {
    width: 24px;
    height: 24px;
    margin-top: 0;
  }

  /* Table optimizations for tablets */
  .table-responsive {
    -webkit-overflow-scrolling: touch;
  }

  .table td,
  .table th {
    padding: 0.75rem;
    white-space: nowrap;
  }

  /* Select multiple - better sizing for tablets */
  select[multiple] {
    min-height: 200px;
    font-size: 16px;
  }

  /* Better spacing for form groups on tablets */
  .mb-3,
  .my-3 {
    margin-bottom: 1.25rem !important;
  }

  /* Modal footer buttons - stack on smaller tablets */
  @media (max-width: 900px) {
    .modal-footer {
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    .modal-footer .btn {
      flex: 1 1 auto;
      min-width: calc(50% - 0.25rem);
    }
  }
}

/* Portrait tablet adjustments */
@media (min-width: 768px) and (max-width: 1024px) and (orientation: portrait) {
  .modal-xl .modal-dialog,
  .modal-lg .modal-dialog {
    max-width: 90vw !important;
  }

  .modal-body {
    max-height: calc(100vh - 240px);
  }

  /* Stack form columns in portrait */
  .modal-body .row .col-md-6,
  .modal-body .row .col-md-4,
  .modal-body .row .col-md-3 {
    width: 100%;
    margin-bottom: 1rem;
  }
}

/* Landscape tablet optimizations (iPad landscape, Android tablets) */
@media (min-width: 1024px) and (max-width: 1366px) and (orientation: landscape) {
  /* Full width utilization in landscape */
  .modal-xl .modal-dialog {
    max-width: 96vw !important;
  }

  /* Optimize vertical space */
  .modal-header,
  .modal-footer {
    padding: 0.75rem 1rem;
  }

  .modal-title {
    font-size: 1.125rem;
  }

  /* Compact table for better fit */
  .table-sm td,
  .table-sm th {
    padding: 0.5rem;
  }
}
</style>
<div class="container-xl">
  <!-- Page header -->
  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">
          <i class="ti ti-clipboard-check me-2"></i>Cycle Counting
        </h2>
        <div class="text-muted mt-1">Physical inventory counting and variance management</div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
          <button class="btn btn-primary" onclick="showCreateSessionModal()">
            <i class="ti ti-plus me-1"></i>Create Count Session
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="page-body">
    <div class="row row-deck row-cards mb-3">
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Total Sessions</div>
            </div>
            <div class="h1 mb-0" id="statTotalSessions">-</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Active Sessions</div>
            </div>
            <div class="h1 mb-0 text-primary" id="statActiveSessions">-</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Items Counted (Month)</div>
            </div>
            <div class="h1 mb-0 text-success" id="statItemsCounted">-</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Accuracy (Month)</div>
            </div>
            <div class="h1 mb-0 text-info" id="statAccuracy">-</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
      <div class="col-md-12">
        <div class="card">
          <div class="card-body">
            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" id="filterStatus" onchange="loadCycleCounts()">
                  <option value="">All Statuses</option>
                  <option value="planned">Planned</option>
                  <option value="in_progress">In Progress</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" id="filterLocation" placeholder="Location..." onkeyup="debounceSearch()">
              </div>
              <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" id="filterSearch" placeholder="Session number..." onkeyup="debounceSearch()">
              </div>
              <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-secondary w-100" onclick="clearFilters()">
                  <i class="ti ti-x me-1"></i>Clear Filters
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Cycle Count Sessions Table -->
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Cycle Count Sessions</h3>
          </div>
          <div id="sessionLoading" class="card-body text-center py-5">
            <div class="spinner-border" role="status"></div>
            <div class="text-muted mt-2">Loading cycle count sessions...</div>
          </div>
          <div id="sessionContent" style="display: none;">
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>Session #</th>
                    <th>Location</th>
                    <th>Scheduled Date</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Accuracy</th>
                    <th class="w-1">Actions</th>
                  </tr>
                </thead>
                <tbody id="sessionTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Create Session Modal -->
<div class="modal modal-blur fade" id="createSessionModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create Cycle Count Session</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="sessionForm">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Storage Locations (Optional)</label>
              <select class="form-select" id="sessionStorageLocations" multiple size="8" style="font-family: monospace; font-size: 0.875rem;">
                <option value="">Loading...</option>
              </select>
              <small class="form-hint">Hold Ctrl/Cmd to select multiple. Leave empty to count all locations</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Category (Optional)</label>
              <select class="form-select" id="sessionCategory">
                <option value="">All Categories</option>
              </select>
              <small class="form-hint">Filter products by category</small>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label required">Scheduled Date</label>
              <input type="date" class="form-control" id="sessionDate" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Assigned To</label>
              <select class="form-select" id="sessionAssignedTo">
                <option value="">Select user...</option>
              </select>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="sessionNotes" rows="2" placeholder="Optional notes about this count session..."></textarea>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label">Product Selection (Optional)</label>
              <select class="form-select" id="sessionProducts" multiple size="10">
              </select>
              <small class="form-hint">Hold Ctrl/Cmd to select multiple products. Leave empty to count all products matching filters.</small>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="createCycleCountSession()">Create Session</button>
      </div>
    </div>
  </div>
</div>

<!-- Count Entry Modal -->
<div class="modal modal-blur fade" id="countEntryModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cycle Count - <span id="countSessionNumber"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="countSessionId">

        <!-- Session Info -->
        <div class="row mb-3">
          <div class="col-md-12">
            <div class="alert alert-info">
              <strong>Location:</strong> <span id="countLocation"></span> |
              <strong>Progress:</strong> <span id="countProgress"></span> |
              <strong>Variances:</strong> <span id="countVariances"></span>
            </div>
          </div>
        </div>

        <!-- Count Entry Table -->
        <div class="table-responsive">
          <table class="table table-vcenter">
            <thead>
              <tr>
                <th style="width: 80px;">Skip</th>
                <th class="sortable-count" data-sort="sku" style="cursor: pointer;">SKU <span class="sort-icon"></span></th>
                <th class="sortable-count" data-sort="description" style="cursor: pointer;">Description <span class="sort-icon"></span></th>
                <th class="sortable-count" data-sort="location" style="cursor: pointer;">Location <span class="sort-icon"></span></th>
                <th class="text-end sortable-count" data-sort="system_quantity" style="cursor: pointer;">System Qty <span class="sort-icon"></span></th>
                <th class="text-end">Counted Qty</th>
                <th class="text-end sortable-count" data-sort="variance" style="cursor: pointer;">Variance <span class="sort-icon"></span></th>
                <th class="sortable-count" data-sort="status" style="cursor: pointer;">Status <span class="sort-icon"></span></th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody id="countEntryTable"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-info" onclick="printCycleCountReport()">
          <i class="ti ti-printer me-1"></i>Print Report
        </button>
        <button type="button" class="btn btn-danger" id="cancelSessionBtn" onclick="cancelCurrentSession()" style="display: none;">
          <i class="ti ti-x me-1"></i>Cancel Session
        </button>
        <button type="button" class="btn btn-warning" onclick="showVarianceReview()">Review Variances</button>
        <button type="button" class="btn btn-success" onclick="completeSession()">Complete Session</button>
      </div>
    </div>
  </div>
</div>

<!-- Variance Review Modal -->
<div class="modal modal-blur fade" id="varianceModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Variance Review - <span id="varianceSessionNumber"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="varianceSessionId">

        <!-- Summary -->
        <div class="row mb-3">
          <div class="col-md-3">
            <div class="card card-sm">
              <div class="card-body">
                <div class="text-muted">Items with Variance</div>
                <div class="h2 mb-0" id="varianceSummaryItems">-</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card card-sm">
              <div class="card-body">
                <div class="text-muted">Total Variance</div>
                <div class="h2 mb-0" id="varianceSummaryTotal">-</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card card-sm">
              <div class="card-body">
                <div class="text-muted">Positive Variance</div>
                <div class="h2 mb-0 text-success" id="varianceSummaryPositive">-</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card card-sm">
              <div class="card-body">
                <div class="text-muted">Negative Variance</div>
                <div class="h2 mb-0 text-danger" id="varianceSummaryNegative">-</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Variance Items -->
        <div class="mb-3">
          <button class="btn btn-sm btn-success" onclick="approveAllVariances()">
            <i class="ti ti-check me-1"></i>Approve All Variances
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-vcenter">
            <thead>
              <tr>
                <th style="width: 5%">
                  <input type="checkbox" class="form-check-input" onchange="toggleAllVariances(this)">
                </th>
                <th>SKU</th>
                <th>Description</th>
                <th class="text-end">System</th>
                <th class="text-end">Counted</th>
                <th class="text-end">Variance</th>
                <th class="text-end">%</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="varianceTable"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-info" onclick="downloadVariancePdfReport()">
          <i class="ti ti-file-download me-1"></i>Download PDF Report
        </button>
        <button type="button" class="btn btn-success" onclick="approveSelectedVariances()">
          <i class="ti ti-check me-1"></i>Approve Selected
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Session Details Modal -->
<div class="modal modal-blur fade" id="sessionDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Session Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <table class="table table-sm">
              <tr>
                <th>Session #:</th>
                <td id="detailSessionNumber"></td>
              </tr>
              <tr>
                <th>Location:</th>
                <td id="detailLocation"></td>
              </tr>
              <tr>
                <th>Status:</th>
                <td id="detailStatus"></td>
              </tr>
              <tr>
                <th>Scheduled:</th>
                <td id="detailScheduled"></td>
              </tr>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm">
              <tr>
                <th>Total Items:</th>
                <td id="detailTotalItems"></td>
              </tr>
              <tr>
                <th>Items Counted:</th>
                <td id="detailCountedItems"></td>
              </tr>
              <tr>
                <th>Variances:</th>
                <td id="detailVariances"></td>
              </tr>
              <tr>
                <th>Accuracy:</th>
                <td id="detailAccuracy"></td>
              </tr>
            </table>
          </div>
        </div>

        <div id="detailNotesSection" style="display: none;">
          <strong>Notes:</strong>
          <p id="detailNotes" class="text-muted"></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
let currentSession = null;
let countSortBy = 'location';
let countSortDir = 'asc';
let allCategories = [];
let allProducts = [];
let allUsers = [];
let searchTimeout = null;

// Safe modal helpers
function safeShowModal(modalId) {
  const modalElement = document.getElementById(modalId);
  if (!modalElement) return;

  // Try using Bootstrap if available
  if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
    const modal = new window.bootstrap.Modal(modalElement);
    modal.show();
    return modal;
  }

  // Fallback: manual modal display
  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop fade show';
  backdrop.id = 'backdrop-' + modalId;
  document.body.appendChild(backdrop);

  modalElement.style.display = 'block';
  modalElement.classList.add('show');
  modalElement.setAttribute('aria-modal', 'true');
  modalElement.removeAttribute('aria-hidden');
  document.body.classList.add('modal-open');

  // Close on backdrop click
  backdrop.addEventListener('click', () => safeHideModal(modalId));

  // Add close button listeners
  const closeButtons = modalElement.querySelectorAll('[data-bs-dismiss="modal"]');
  closeButtons.forEach(btn => {
    btn.onclick = () => safeHideModal(modalId);
  });

  return null;
}

function safeHideModal(modalId) {
  const modalElement = document.getElementById(modalId);
  if (!modalElement) return;

  // Try using Bootstrap if available
  if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
    const modal = window.bootstrap.Modal.getInstance(modalElement);
    if (modal) {
      modal.hide();
      return;
    }
  }

  // Fallback: manual modal hide
  modalElement.style.display = 'none';
  modalElement.classList.remove('show');
  modalElement.setAttribute('aria-hidden', 'true');
  modalElement.removeAttribute('aria-modal');
  document.body.classList.remove('modal-open');

  const backdrop = document.getElementById('backdrop-' + modalId);
  if (backdrop) backdrop.remove();
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  loadCategories();
  loadProducts();
  loadUsers();
  loadCycleCounts();
  loadStatistics();

  // Set today's date as default
  document.getElementById('sessionDate').value = new Date().toISOString().split('T')[0];

  // Add event listeners for sortable count columns
  document.querySelectorAll('.sortable-count').forEach(th => {
    th.addEventListener('click', function() {
      const column = this.dataset.sort;
      if (column) {
        sortCountItems(column);
      }
    });
  });
});

// Load cycle count sessions
async function loadCycleCounts() {
  try {
    document.getElementById('sessionLoading').style.display = 'block';
    document.getElementById('sessionContent').style.display = 'none';

    const params = new URLSearchParams();
    const status = document.getElementById('filterStatus').value;
    const location = document.getElementById('filterLocation').value;
    const search = document.getElementById('filterSearch').value;

    if (status) params.append('status', status);
    if (location) params.append('location', location);
    if (search) params.append('search', search);

    const response = await authenticatedFetch(`/cycle-counts?${params.toString()}`);
    const sessions = response.data || response;

    renderCycleCounts(sessions);

    document.getElementById('sessionLoading').style.display = 'none';
    document.getElementById('sessionContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading cycle counts:', error);
    showNotification('Error loading cycle counts', 'danger');
  }
}

// Render cycle count sessions
function renderCycleCounts(sessions) {
  const tbody = document.getElementById('sessionTableBody');

  if (sessions.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No cycle count sessions found</td></tr>';
    return;
  }

  tbody.innerHTML = sessions.map(session => {
    const statusBadge = getSessionStatusBadge(session.status);
    const progress = session.total_items > 0
      ? Math.round((session.counted_items / session.total_items) * 100)
      : 0;

    return `
      <tr>
        <td><strong>${escapeHtml(session.session_number)}</strong></td>
        <td>${session.location || 'All Locations'}</td>
        <td>${formatDate(session.scheduled_date)}</td>
        <td>${session.assigned_user ? escapeHtml(session.assigned_user.name) : '-'}</td>
        <td>${statusBadge}</td>
        <td>
          <div class="progress" style="height: 20px;">
            <div class="progress-bar ${progress === 100 ? 'bg-success' : 'bg-primary'}" style="width: ${progress}%">
              ${progress}%
            </div>
          </div>
        </td>
        <td>
          ${session.status === 'completed' ? `
            <span class="badge ${session.accuracy_percentage >= 95 ? 'text-bg-success' : session.accuracy_percentage >= 90 ? 'text-bg-warning' : 'text-bg-danger'}">
              ${session.accuracy_percentage}%
            </span>
          ` : '-'}
        </td>
        <td>
          <div class="btn-group">
            <button class="btn btn-sm btn-ghost-primary" onclick="viewSessionDetails(${session.id})" title="View">
              <i class="ti ti-eye"></i>
            </button>
            ${session.status === 'planned' || session.status === 'in_progress' ? `
              <button class="btn btn-sm btn-ghost-success" onclick="enterCounts(${session.id})" title="Count">
                <i class="ti ti-clipboard-check"></i>
              </button>
            ` : ''}
            ${session.status === 'completed' ? `
              <button class="btn btn-sm btn-ghost-info" onclick="viewVarianceReport(${session.id})" title="Variance Report">
                <i class="ti ti-chart-bar"></i>
              </button>
              <button class="btn btn-sm btn-ghost-success" onclick="downloadPdfReport(${session.id})" title="Download PDF Report">
                <i class="ti ti-file-download"></i>
              </button>
            ` : ''}
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

// Load categories
async function loadCategories() {
  try {
    const response = await authenticatedFetch('/categories?is_active=1&per_page=all');
    allCategories = response.data || response;

    const select = document.getElementById('sessionCategory');
    select.innerHTML = '<option value="">All Categories</option>';

    allCategories.forEach(category => {
      const option = document.createElement('option');
      option.value = category.id;
      option.textContent = category.name;
      select.appendChild(option);
    });
  } catch (error) {
    console.error('Error loading categories:', error);
  }
}

// Load products
async function loadProducts() {
  try {
    const response = await authenticatedFetch('/products?is_active=1&per_page=all');
    allProducts = response.data || response;

    const select = document.getElementById('sessionProducts');
    select.innerHTML = '';

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

// Load users
async function loadUsers() {
  try {
    const response = await authenticatedFetch('/user');
    const currentUser = response;

    const select = document.getElementById('sessionAssignedTo');
    select.innerHTML = '<option value="">Select user...</option>';

    const option = document.createElement('option');
    option.value = currentUser.id;
    option.textContent = currentUser.name;
    option.selected = true;
    select.appendChild(option);
  } catch (error) {
    console.error('Error loading users:', error);
  }
}

// Load statistics
async function loadStatistics() {
  try {
    const stats = await authenticatedFetch('/cycle-counts-statistics');

    document.getElementById('statTotalSessions').textContent = stats.total_sessions;
    document.getElementById('statActiveSessions').textContent = stats.active_sessions;
    document.getElementById('statItemsCounted').textContent = stats.items_counted_this_month;
    document.getElementById('statAccuracy').textContent = stats.accuracy_this_month + '%';
  } catch (error) {
    console.error('Error loading statistics:', error);
  }
}

// Show create session modal
async function showCreateSessionModal() {
  document.getElementById('sessionForm').reset();
  document.getElementById('sessionDate').value = new Date().toISOString().split('T')[0];

  // Load storage locations
  await loadStorageLocationsForSession();

  safeShowModal('createSessionModal');
}

// Load storage locations for the session modal
async function loadStorageLocationsForSession() {
  try {
    const locations = await authenticatedFetch('/storage-locations-tree');
    const select = document.getElementById('sessionStorageLocations');
    select.innerHTML = '';

    // Recursive function to render tree
    function renderLocationOptions(locations, level = 0) {
      locations.forEach(location => {
        const indent = '\u00A0'.repeat(level * 4); // Non-breaking spaces for indentation
        const option = document.createElement('option');
        option.value = location.id;
        option.textContent = indent + location.name;
        select.appendChild(option);

        if (location.children && location.children.length > 0) {
          renderLocationOptions(location.children, level + 1);
        }
      });
    }

    renderLocationOptions(locations);
  } catch (error) {
    console.error('Error loading storage locations:', error);
    const select = document.getElementById('sessionStorageLocations');
    select.innerHTML = '<option value="">Error loading locations</option>';
  }
}

// Create cycle count session
async function createCycleCountSession() {
  try {
    const storageLocationSelect = document.getElementById('sessionStorageLocations');
    const storageLocationIds = Array.from(storageLocationSelect.selectedOptions).map(opt => parseInt(opt.value));
    const categoryId = document.getElementById('sessionCategory').value;
    const scheduledDate = document.getElementById('sessionDate').value;
    const assignedTo = document.getElementById('sessionAssignedTo').value;
    const notes = document.getElementById('sessionNotes').value;
    const productSelect = document.getElementById('sessionProducts');
    const productIds = Array.from(productSelect.selectedOptions).map(opt => parseInt(opt.value));

    const data = {
      storage_location_ids: storageLocationIds.length > 0 ? storageLocationIds : null,
      category_id: categoryId ? parseInt(categoryId) : null,
      scheduled_date: scheduledDate,
      assigned_to: assignedTo ? parseInt(assignedTo) : null,
      notes: notes || null,
      product_ids: productIds.length > 0 ? productIds : null,
    };

    console.log('Creating cycle count session with data:', data);

    await authenticatedFetch('/cycle-counts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });

    showNotification('Cycle count session created successfully', 'success');
    safeHideModal('createSessionModal');
    loadCycleCounts();
    loadStatistics();
  } catch (error) {
    console.error('Error creating cycle count session:', error);
    showNotification(error.message || 'Error creating cycle count session', 'danger');
  }
}

// Enter counts
async function enterCounts(sessionId) {
  try {
    // Validate session ID parameter
    if (!sessionId || sessionId === 'undefined') {
      showNotification('Error: Invalid session ID', 'danger');
      return;
    }

    const session = await authenticatedFetch(`/cycle-counts/${sessionId}`);
    currentSession = session;

    // Validate session has an ID
    if (!session.id) {
      showNotification('Error: Session data is invalid. The session may have been deleted. Please refresh the page.', 'danger');
      setTimeout(() => {
        loadCycleCounts();
      }, 2000);
      return;
    }

    document.getElementById('countSessionId').value = session.id;
    document.getElementById('countSessionNumber').textContent = session.session_number;
    document.getElementById('countLocation').textContent = session.location || 'All Locations';
    document.getElementById('countProgress').textContent = `${session.counted_items} / ${session.total_items} items`;
    document.getElementById('countVariances').textContent = `${session.variance_items} items`;

    // Render count items
    const tbody = document.getElementById('countEntryTable');

    if (!session.items || session.items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No items to count. This session may have been created with filters that matched no products.</td></tr>';

      // Disable Complete button when there are no items
      const completeButton = document.querySelector('#countEntryModal .btn-success[onclick="completeSession()"]');
      if (completeButton) {
        completeButton.disabled = true;
        completeButton.title = 'Cannot complete session without items';
      }

      // Show Cancel button for empty sessions
      const cancelButton = document.getElementById('cancelSessionBtn');
      if (cancelButton) {
        cancelButton.style.display = 'inline-block';
      }

      safeShowModal('countEntryModal');
      showNotification('Warning: This session has no items to count', 'warning');
      return;
    }

    // Enable Complete button when there are items
    const completeButton = document.querySelector('#countEntryModal .btn-success[onclick="completeSession()"]');
    if (completeButton) {
      completeButton.disabled = false;
      completeButton.title = '';
    }

    // Hide Cancel button when there are items
    const cancelButton = document.getElementById('cancelSessionBtn');
    if (cancelButton) {
      cancelButton.style.display = 'none';
    }

    // Apply initial sort by location
    sortCountItems('location');

    safeShowModal('countEntryModal');
  } catch (error) {
    console.error('Error loading count session:', error);
    showNotification('Error loading count session', 'danger');
  }
}

// Toggle skip checkbox
function toggleSkip(itemId) {
  const skipCheckbox = document.getElementById(`skip${itemId}`);
  const countedInput = document.getElementById(`counted${itemId}`);
  const notesInput = document.getElementById(`notes${itemId}`);
  const row = document.getElementById(`row${itemId}`);

  if (skipCheckbox.checked) {
    // Disable inputs when skipped
    countedInput.disabled = true;
    notesInput.disabled = true;
    countedInput.style.backgroundColor = '#f8f9fa';
    notesInput.style.backgroundColor = '#f8f9fa';
    row.classList.add('bg-light');
  } else {
    // Enable inputs when not skipped
    countedInput.disabled = false;
    notesInput.disabled = false;
    countedInput.style.backgroundColor = '';
    notesInput.style.backgroundColor = '';
    row.classList.remove('bg-light');
  }
}

// Sort count items by column
function sortCountItems(column) {
  if (!currentSession || !currentSession.items) return;

  if (countSortBy === column) {
    // Toggle direction if same column
    countSortDir = countSortDir === 'asc' ? 'desc' : 'asc';
  } else {
    countSortBy = column;
    countSortDir = 'asc';
  }

  // Sort the items
  const sortedItems = [...currentSession.items].sort((a, b) => {
    let aVal, bVal;

    switch (column) {
      case 'sku':
        aVal = a.product?.sku || '';
        bVal = b.product?.sku || '';
        break;
      case 'description':
        aVal = a.product?.description || '';
        bVal = b.product?.description || '';
        break;
      case 'location':
        aVal = a.location?.storage_location?.name || a.location?.location || '';
        bVal = b.location?.storage_location?.name || b.location?.location || '';
        break;
      case 'system_quantity':
        aVal = a.system_quantity || 0;
        bVal = b.system_quantity || 0;
        break;
      case 'variance':
        aVal = a.counted_quantity !== null ? (a.counted_quantity - a.system_quantity) : -999999;
        bVal = b.counted_quantity !== null ? (b.counted_quantity - b.system_quantity) : -999999;
        break;
      case 'status':
        aVal = a.variance_status || '';
        bVal = b.variance_status || '';
        break;
      default:
        return 0;
    }

    // Compare values
    let comparison = 0;
    if (typeof aVal === 'string') {
      comparison = aVal.localeCompare(bVal);
    } else {
      comparison = aVal - bVal;
    }

    return countSortDir === 'asc' ? comparison : -comparison;
  });

  // Update session with sorted items
  currentSession.items = sortedItems;

  // Re-render the table
  renderCountItems(currentSession);
  updateCountSortIcons();
}

// Update sort icons in count table headers
function updateCountSortIcons() {
  document.querySelectorAll('.sortable-count').forEach(th => {
    const icon = th.querySelector('.sort-icon');
    const column = th.dataset.sort;
    if (column === countSortBy) {
      icon.innerHTML = countSortDir === 'asc'
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1"><path d="M12 5l0 14"/><path d="M18 11l-6 -6"/><path d="M6 11l6 -6"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1"><path d="M12 5l0 14"/><path d="M18 13l-6 6"/><path d="M6 13l6 6"/></svg>';
    } else {
      icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1 text-muted"><path d="M8 9l4 -4l4 4"/><path d="M16 15l-4 4l-4 -4"/></svg>';
    }
  });
}

// Render count items into table
function renderCountItems(session) {
  const tbody = document.getElementById('countEntryTable');

  if (!session.items || session.items.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No items to count.</td></tr>';
    return;
  }

  tbody.innerHTML = session.items.map(item => {
    const variance = item.counted_quantity !== null
      ? item.counted_quantity - item.system_quantity
      : 0;

    let varianceClass = '';
    if (variance > 0) varianceClass = 'text-success';
    else if (variance < 0) varianceClass = 'text-danger';

    // Determine counting unit (packs or eaches)
    const packSize = item.pack_size || item.product?.pack_size || 1;
    const hasPackSize = packSize > 1;
    const countingUnit = item.counting_unit || (hasPackSize ? 'packs' : 'EA');
    const unitLabel = hasPackSize ? 'packs' : '';
    const packInfo = hasPackSize ? ` <small class="text-muted">(${packSize}/pack)</small>` : '';

    return `
      <tr id="row${item.id}">
        <td>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="skip${item.id}" onchange="toggleSkip(${item.id})">
            <label class="form-check-label" for="skip${item.id}">
              <small class="text-muted">Skip</small>
            </label>
          </div>
        </td>
        <td><strong>${escapeHtml(item.product.sku)}</strong></td>
        <td>${escapeHtml(item.product.description)}${packInfo}</td>
        <td>${item.location ? (item.location.storage_location ? escapeHtml(item.location.storage_location.name) : escapeHtml(item.location.location)) : '-'}</td>
        <td class="text-end">
          ${item.system_quantity}${unitLabel ? ` <small class="text-muted">${unitLabel}</small>` : ''}
        </td>
        <td>
          <div class="input-group input-group-sm" style="width: 140px;">
            <input type="number" class="form-control form-control-sm" id="counted${item.id}"
                   value="${item.counted_quantity !== null ? item.counted_quantity : ''}"
                   min="0" inputmode="numeric" pattern="[0-9]*" onchange="recordItemCount(${item.id})"
                   data-unit="${unitLabel}">
            ${unitLabel ? `<span class="input-group-text">${unitLabel}</span>` : ''}
          </div>
        </td>
        <td class="text-end ${varianceClass}">
          <strong id="variance${item.id}">${item.counted_quantity !== null ? (variance > 0 ? '+' : '') + variance + (unitLabel ? ' ' + unitLabel : '') : '-'}</strong>
        </td>
        <td>
          <span class="badge ${getVarianceStatusBadge(item.variance_status)}" id="status${item.id}">
            ${formatVarianceStatus(item.variance_status)}
          </span>
        </td>
        <td>
          <input type="text" class="form-control form-control-sm" id="notes${item.id}"
                 value="${item.count_notes || ''}" placeholder="Notes..." style="width: 150px;">
        </td>
      </tr>
    `;
  }).join('');
}

// Record item count
async function recordItemCount(itemId) {
  try {
    const countedQty = document.getElementById(`counted${itemId}`).value;
    const notes = document.getElementById(`notes${itemId}`).value;

    if (!countedQty || countedQty === '') return;

    const sessionId = document.getElementById('countSessionId').value;

    const response = await authenticatedFetch(`/cycle-counts/${sessionId}/record-count`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        item_id: itemId,
        counted_quantity: parseInt(countedQty),
        notes: notes || null,
      }),
    });

    // Update only this row's variance and status without refreshing the whole table
    const item = response.item;
    const variance = item.counted_quantity - item.system_quantity;
    const unitLabel = document.getElementById(`counted${itemId}`).getAttribute('data-unit');

    // Update variance display
    let varianceClass = '';
    if (variance > 0) varianceClass = 'text-success';
    else if (variance < 0) varianceClass = 'text-danger';

    const varianceCell = document.getElementById(`variance${itemId}`);
    varianceCell.className = varianceClass;
    varianceCell.innerHTML = `<strong>${variance > 0 ? '+' : ''}${variance}${unitLabel ? ' ' + unitLabel : ''}</strong>`;

    // Update status badge
    const statusCell = document.getElementById(`status${itemId}`);
    statusCell.className = `badge ${getVarianceStatusBadge(item.variance_status)}`;
    statusCell.textContent = formatVarianceStatus(item.variance_status);

    // Update progress in header
    const sessionIdValue = document.getElementById('countSessionId').value;
    const sessionData = await authenticatedFetch(`/cycle-counts/${sessionIdValue}`);
    document.getElementById('countProgress').textContent = `${sessionData.counted_items} / ${sessionData.total_items} items`;
    document.getElementById('countVariances').textContent = `${sessionData.variance_items} items`;

    showNotification('Count recorded successfully', 'success');
  } catch (error) {
    console.error('Error recording count:', error);
    showNotification(error.message || 'Error recording count', 'danger');
  }
}

// Show variance review
async function showVarianceReview() {
  const sessionId = document.getElementById('countSessionId').value;
  await viewVarianceReport(sessionId);
}

// View variance report
async function viewVarianceReport(sessionId) {
  try {
    const report = await authenticatedFetch(`/cycle-counts/${sessionId}/variance-report`);

    document.getElementById('varianceSessionId').value = sessionId;
    document.getElementById('varianceSessionNumber').textContent = report.session.session_number;

    // Summary
    document.getElementById('varianceSummaryItems').textContent = report.summary.items_with_variance;
    document.getElementById('varianceSummaryTotal').textContent = report.summary.total_variance;
    document.getElementById('varianceSummaryPositive').textContent = '+' + report.summary.positive_variance;
    document.getElementById('varianceSummaryNegative').textContent = report.summary.negative_variance;

    // Variance items
    const tbody = document.getElementById('varianceTable');
    tbody.innerHTML = report.variances.map(item => {
      const variancePercent = item.system_quantity > 0
        ? Math.round((item.variance / item.system_quantity) * 100)
        : 0;

      // Determine counting unit (packs or eaches)
      const packSize = item.pack_size || item.product?.pack_size || 1;
      const hasPackSize = packSize > 1;
      const unitLabel = hasPackSize ? 'packs' : '';
      const packInfo = hasPackSize ? ` <small class="text-muted">(${packSize}/pk)</small>` : '';

      // Calculate eaches for display in tooltip
      const systemEaches = hasPackSize ? item.system_quantity * packSize : item.system_quantity;
      const countedEaches = hasPackSize ? item.counted_quantity * packSize : item.counted_quantity;
      const varianceEaches = hasPackSize ? item.variance * packSize : item.variance;
      const eachesInfo = hasPackSize ? ` title="System: ${systemEaches} ea, Counted: ${countedEaches} ea, Variance: ${varianceEaches > 0 ? '+' : ''}${varianceEaches} ea"` : '';

      return `
        <tr${eachesInfo}>
          <td>
            ${item.variance_status !== 'approved' ? `
              <input type="checkbox" class="form-check-input variance-check" data-item-id="${item.id}" checked>
            ` : ''}
          </td>
          <td><strong>${escapeHtml(item.product.sku)}</strong>${packInfo}</td>
          <td>${escapeHtml(item.product.description)}</td>
          <td class="text-end">${item.system_quantity}${unitLabel ? ` <small>${unitLabel}</small>` : ''}</td>
          <td class="text-end">${item.counted_quantity}${unitLabel ? ` <small>${unitLabel}</small>` : ''}</td>
          <td class="text-end ${item.variance > 0 ? 'text-success' : 'text-danger'}">
            <strong>${item.variance > 0 ? '+' : ''}${item.variance}</strong>${unitLabel ? ` <small>${unitLabel}</small>` : ''}
          </td>
          <td class="text-end ${Math.abs(variancePercent) > 10 ? 'text-danger' : 'text-warning'}">
            ${variancePercent}%
          </td>
          <td>
            <span class="badge ${getVarianceStatusBadge(item.variance_status)}">
              ${formatVarianceStatus(item.variance_status)}
            </span>
          </td>
        </tr>
      `;
    }).join('');

    // Hide count entry modal if open
    safeHideModal('countEntryModal');

    safeShowModal('varianceModal');
  } catch (error) {
    console.error('Error loading variance report:', error);
    showNotification('Error loading variance report', 'danger');
  }
}

// Approve selected variances
async function approveSelectedVariances() {
  try {
    const sessionId = document.getElementById('varianceSessionId').value;
    const itemIds = [];

    document.querySelectorAll('.variance-check:checked').forEach(checkbox => {
      itemIds.push(parseInt(checkbox.getAttribute('data-item-id')));
    });

    if (itemIds.length === 0) {
      showNotification('Please select at least one variance to approve', 'warning');
      return;
    }

    if (!confirm(`Approve ${itemIds.length} variance(s)? This will create inventory adjustments.`)) return;

    await authenticatedFetch(`/cycle-counts/${sessionId}/approve-variances`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_ids: itemIds }),
    });

    showNotification('Variances approved and adjustments created', 'success');
    viewVarianceReport(sessionId); // Refresh
  } catch (error) {
    console.error('Error approving variances:', error);
    showNotification(error.message || 'Error approving variances', 'danger');
  }
}

// Approve all variances
async function approveAllVariances() {
  const itemIds = [];
  document.querySelectorAll('.variance-check').forEach(checkbox => {
    itemIds.push(parseInt(checkbox.getAttribute('data-item-id')));
    checkbox.checked = true;
  });

  await approveSelectedVariances();
}

// Toggle all variances
function toggleAllVariances(checkbox) {
  document.querySelectorAll('.variance-check').forEach(cb => {
    cb.checked = checkbox.checked;
  });
}

// Complete session
async function completeSession() {
  const sessionId = document.getElementById('countSessionId').value;

  // Validate session ID
  if (!sessionId || sessionId === 'undefined' || sessionId === '') {
    showNotification('Error: Session ID is missing. Please close and reopen the count modal.', 'danger');
    return;
  }

  // Check if current session has items
  if (!currentSession || !currentSession.items || currentSession.items.length === 0) {
    showNotification('Cannot complete session: No items to count', 'danger');
    return;
  }

  if (!confirm('Complete this cycle count session? All variances must be reviewed first.')) return;

  try {
    await authenticatedFetch(`/cycle-counts/${sessionId}/complete`, { method: 'POST' });

    showNotification('Cycle count session completed successfully', 'success');
    safeHideModal('countEntryModal');
    loadCycleCounts();
    loadStatistics();
  } catch (error) {
    showNotification(error.message || 'Error completing session', 'danger');
  }
}

// Print cycle count report
function printCycleCountReport() {
  const sessionId = document.getElementById('countSessionId').value;

  // Validate session ID
  if (!sessionId || sessionId === 'undefined' || sessionId === '') {
    showNotification('Error: Session ID is missing. Please close and reopen the count modal.', 'danger');
    return;
  }

  // Open PDF in new window/tab
  const apiUrl = window.location.origin + '/api/v1/cycle-counts/' + sessionId + '/pdf';
  const authToken = localStorage.getItem('authToken');

  // Create a temporary link to download the PDF
  fetch(apiUrl, {
    headers: {
      'Authorization': 'Bearer ' + authToken,
      'Accept': 'application/pdf',
    }
  })
  .then(response => {
    if (!response.ok) {
      throw new Error('Failed to generate PDF report');
    }
    return response.blob();
  })
  .then(blob => {
    // Create a blob URL and download
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'cycle-count-' + (currentSession?.session_number || sessionId) + '.pdf';
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
    showNotification('Generating PDF report...', 'info');
  })
  .catch(error => {
    console.error('Error generating PDF:', error);
    showNotification('Error generating PDF report', 'danger');
  });
}

// Download PDF report for a completed session (from main table)
function downloadPdfReport(sessionId) {
  // Validate session ID
  if (!sessionId || sessionId === 'undefined' || sessionId === '') {
    showNotification('Error: Invalid session ID', 'danger');
    return;
  }

  // Open PDF in new window/tab
  const apiUrl = window.location.origin + '/api/v1/cycle-counts/' + sessionId + '/pdf';
  const authToken = localStorage.getItem('authToken');

  // Create a temporary link to download the PDF
  fetch(apiUrl, {
    headers: {
      'Authorization': 'Bearer ' + authToken,
      'Accept': 'application/pdf',
    }
  })
  .then(response => {
    if (!response.ok) {
      throw new Error('Failed to generate PDF report');
    }
    return response.blob();
  })
  .then(blob => {
    // Create a blob URL and download
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'cycle-count-report-' + sessionId + '.pdf';
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
    showNotification('PDF report downloaded successfully', 'success');
  })
  .catch(error => {
    console.error('Error generating PDF:', error);
    showNotification('Error generating PDF report', 'danger');
  });
}

// Download PDF report from variance modal
function downloadVariancePdfReport() {
  const sessionId = document.getElementById('varianceSessionId').value;

  // Validate session ID
  if (!sessionId || sessionId === 'undefined' || sessionId === '') {
    showNotification('Error: Session ID is missing', 'danger');
    return;
  }

  downloadPdfReport(sessionId);
}

// Cancel current session
async function cancelCurrentSession() {
  const sessionId = document.getElementById('countSessionId').value;

  // Validate session ID
  if (!sessionId || sessionId === 'undefined' || sessionId === '') {
    showNotification('Error: Session ID is missing', 'danger');
    return;
  }

  if (!confirm('Cancel this cycle count session? This action cannot be undone.')) return;

  try {
    await authenticatedFetch(`/cycle-counts/${sessionId}/cancel`, { method: 'POST' });

    showNotification('Cycle count session cancelled successfully', 'success');
    safeHideModal('countEntryModal');
    loadCycleCounts();
    loadStatistics();
  } catch (error) {
    showNotification(error.message || 'Error cancelling session', 'danger');
  }
}

// View session details
async function viewSessionDetails(sessionId) {
  try {
    const session = await authenticatedFetch(`/cycle-counts/${sessionId}`);

    document.getElementById('detailSessionNumber').textContent = session.session_number;
    document.getElementById('detailLocation').textContent = session.location || 'All Locations';
    document.getElementById('detailStatus').innerHTML = getSessionStatusBadge(session.status);
    document.getElementById('detailScheduled').textContent = formatDate(session.scheduled_date);
    document.getElementById('detailTotalItems').textContent = session.total_items;
    document.getElementById('detailCountedItems').textContent = session.counted_items;
    document.getElementById('detailVariances').textContent = session.variance_items;
    document.getElementById('detailAccuracy').textContent = session.accuracy_percentage + '%';

    if (session.notes) {
      document.getElementById('detailNotes').textContent = session.notes;
      document.getElementById('detailNotesSection').style.display = 'block';
    } else {
      document.getElementById('detailNotesSection').style.display = 'none';
    }

    safeShowModal('sessionDetailsModal');
  } catch (error) {
    console.error('Error loading session details:', error);
    showNotification('Error loading session details', 'danger');
  }
}

// Clear filters
function clearFilters() {
  document.getElementById('filterStatus').value = '';
  document.getElementById('filterLocation').value = '';
  document.getElementById('filterSearch').value = '';
  loadCycleCounts();
}

// Debounce search
function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadCycleCounts();
  }, 500);
}

// Helper functions
function getSessionStatusBadge(status) {
  const badges = {
    'planned': '<span class="badge text-bg-secondary">Planned</span>',
    'in_progress': '<span class="badge text-bg-primary">In Progress</span>',
    'completed': '<span class="badge text-bg-success">Completed</span>',
    'cancelled': '<span class="badge text-bg-danger">Cancelled</span>',
  };
  return badges[status] || status;
}

function getVarianceStatusBadge(status) {
  const colors = {
    'pending': 'text-bg-secondary',
    'within_tolerance': 'text-bg-success',
    'requires_review': 'text-bg-warning',
    'approved': 'text-bg-primary',
    'rejected': 'text-bg-danger',
  };
  return colors[status] || 'text-bg-secondary';
}

function formatVarianceStatus(status) {
  const labels = {
    'pending': 'Pending',
    'within_tolerance': 'Within Tolerance',
    'requires_review': 'Requires Review',
    'approved': 'Approved',
    'rejected': 'Rejected',
  };
  return labels[status] || status;
}

function formatDate(dateString) {
  if (!dateString) return '-';
  return new Date(dateString).toLocaleDateString();
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
</script>
@endsection
