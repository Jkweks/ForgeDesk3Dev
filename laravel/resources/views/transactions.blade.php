@extends('layouts.app')

@section('title', 'Transaction History - ForgeDesk')

@section('styles')
/* Manual-transaction product picker — tablet friendly */
#addTransactionModal .product-search-results {
  position: static;           /* flow inline so it can't be clipped/hidden */
  width: 100%;
  max-height: 320px;
  overflow-y: auto;
  border: 1px solid var(--tblr-border-color, #dadce0);
  border-radius: .375rem;
  margin-top: .25rem;
}
#addTransactionModal .product-search-results .list-group-item {
  padding: .7rem .9rem;       /* larger tap targets */
  line-height: 1.3;
}
#addTransactionModal .product-search-results .list-group-item strong { font-size: 1rem; }
#addTransactionModal .tx-line .form-control,
#addTransactionModal .tx-line .form-select { min-height: 2.6rem; }
@endsection

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Inventory Management</div>
              <h1 class="page-title">Transaction History</h1>
              <p class="text-muted">View and audit all inventory transactions</p>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn" onclick="exportTransactions()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                  Export
                </button>
                <button class="btn btn-primary" data-permission="inventory.create" onclick="showAddTransactionModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Add Transaction
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
                  <div class="subheader">Total Transactions</div>
                  <div class="h1 mb-3" id="statTotal">-</div>
                  <div>All time transactions</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">This Month</div>
                  <div class="h1 mb-3 text-primary" id="statThisMonth">-</div>
                  <div>Transactions this month</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Today</div>
                  <div class="h1 mb-3 text-success" id="statToday">-</div>
                  <div>Transactions today</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Most Active Type</div>
                  <div class="h1 mb-3 text-info" id="statMostActiveType">-</div>
                  <div>Most common transaction</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Filters & Transactions Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Transactions</h3>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="typeFilter" style="width: 150px;">
                      <option value="">All Types</option>
                    </select>
                    <input type="date" class="form-control form-control-sm" id="startDate" style="width: 150px;">
                    <input type="date" class="form-control form-control-sm" id="endDate" style="width: 150px;">
                    <input type="text" class="form-control form-control-sm" placeholder="Search..." id="searchInput" style="min-width: 200px;">
                  </div>
                </div>
                <div class="card-body">
                  <div class="loading" id="loadingIndicator">
                    <div class="spinner-border" role="status"></div>
                    <div>Loading transactions...</div>
                  </div>

                  <div class="table-responsive" id="transactionsTableContainer" style="display: none;">
                    <table class="table table-vcenter card-table table-striped">
                      <thead>
                        <tr>
                          <th>Date/Time</th>
                          <th>Type</th>
                          <th>Product</th>
                          <th class="text-end">Quantity</th>
                          <th class="text-end">Before</th>
                          <th class="text-end">After</th>
                          <th>Reference</th>
                          <th>User</th>
                          <th class="w-1"></th>
                        </tr>
                      </thead>
                      <tbody id="transactionsTableBody"></tbody>
                    </table>
                  </div>

                  <!-- Pagination -->
                  <div class="mt-3 d-flex justify-content-between align-items-center" id="paginationContainer" style="display: none !important;">
                    <div class="text-muted">
                      Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalRecords">0</span> transactions
                    </div>
                    <nav>
                      <ul class="pagination mb-0" id="paginationLinks"></ul>
                    </nav>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Transaction Details Modal -->
  <div class="modal modal-blur fade" id="transactionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Transaction Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="transactionDetails"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Manual Transaction Modal -->
  <div class="modal modal-blur fade" id="addTransactionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down modal-dialog-centered modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Manual Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="addTransactionForm">
          <div class="modal-body">
            <!-- Transaction Details -->
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label required">Transaction Type</label>
                <select class="form-select" name="type" id="manualTransactionType" required>
                  <option value="issue">Issue (Remove from inventory)</option>
                  <option value="receipt">Receipt (Add to inventory)</option>
                  <option value="return">Return (Add to inventory)</option>
                  <option value="adjustment">Adjustment (Add to inventory)</option>
                  <option value="job_issue">Job Issue (Remove for job)</option>
                  <option value="job_material_transfer">Job Material Transfer (Add from completed job)</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label required">Date & Time</label>
                <input type="datetime-local" class="form-control" name="transaction_date" id="manualTransactionDate" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label required">Reference/Job Name</label>
              <input type="text" class="form-control" name="reference_number" id="manualTransactionReference" placeholder="e.g., Service Job #123" required>
              <small class="form-hint">Enter the job name, service ticket, or other reference</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" name="notes" id="manualTransactionNotes" rows="2" placeholder="Additional details..."></textarea>
            </div>

            <hr class="my-3">

            <!-- Parts/Products Section -->
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="mb-0">Parts/Products</h6>
              <button type="button" class="btn btn-sm btn-primary" onclick="addProductLine()">
                <i class="ti ti-plus me-1"></i>
                Add Part
              </button>
            </div>

            <div id="productLinesContainer">
              <!-- Product lines will be added here -->
            </div>

            <div id="noProductsMessage" class="alert alert-info">
              Click "Add Part" to add products to this transaction
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveManualTransactionBtn">
              <i class="ti ti-check me-1"></i>
              Save Transaction
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Transaction Modal (admin only) -->
  <div class="modal modal-blur fade" id="editTransactionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="editTransactionForm">
          <div class="modal-body">
            <input type="hidden" id="editTransactionId">
            <div class="alert alert-warning">
              Changing the quantity or type will adjust this product's on-hand inventory by the difference.
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Product</label>
              <p class="mb-0" id="editTransactionProduct">-</p>
            </div>
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label required">Transaction Type</label>
                <select class="form-select" name="type" id="editTransactionType" required>
                  <option value="issue">Issue (Remove from inventory)</option>
                  <option value="receipt">Receipt (Add to inventory)</option>
                  <option value="return">Return (Add to inventory)</option>
                  <option value="adjustment">Adjustment (Add to inventory)</option>
                  <option value="transfer">Transfer</option>
                  <option value="cycle_count">Cycle Count</option>
                  <option value="shipment">Shipment (Remove from inventory)</option>
                  <option value="job_issue">Job Issue (Remove for job)</option>
                  <option value="job_material_transfer">Job Material Transfer (Add from completed job)</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label required">Quantity</label>
                <input type="number" min="1" step="1" class="form-control" name="quantity" id="editTransactionQuantity" required>
                <small class="form-hint">Magnitude only; the sign is derived from the type.</small>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label required">Date &amp; Time</label>
              <input type="datetime-local" class="form-control" name="transaction_date" id="editTransactionDate" required>
            </div>
            <div class="mb-3">
              <label class="form-label required">Reference/Job Name</label>
              <input type="text" class="form-control" name="reference_number" id="editTransactionReference" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" name="notes" id="editTransactionNotes" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveEditTransactionBtn">
              <i class="ti ti-check me-1"></i>
              Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

@endsection


@push('scripts')
  <script>
    let allTransactions = [];
    let filteredTransactions = [];
    let currentPage = 1;
    let perPage = 50;

    // Utility function to escape HTML and prevent XSS
    function escapeHtml(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', () => {
      loadTransactionTypes();
      loadTransactions();
      loadStatistics();

      // Filter listeners
      document.getElementById('typeFilter').addEventListener('change', applyFilters);
      document.getElementById('startDate').addEventListener('change', applyFilters);
      document.getElementById('endDate').addEventListener('change', applyFilters);
      document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 300));
    });

    async function loadTransactionTypes() {
      try {
        const types = await authenticatedFetch('/transactions-types');
        const select = document.getElementById('typeFilter');

        Object.entries(types).forEach(([key, label]) => {
          const option = document.createElement('option');
          option.value = key;
          option.textContent = label;
          select.appendChild(option);
        });
      } catch (error) {
        console.error('Error loading transaction types:', error);
      }
    }

    async function loadStatistics() {
      try {
        const stats = await authenticatedFetch('/transactions-statistics');

        document.getElementById('statTotal').textContent = (stats.total || 0).toLocaleString();
        document.getElementById('statThisMonth').textContent = (stats.this_month || 0).toLocaleString();
        document.getElementById('statToday').textContent = (stats.today || 0).toLocaleString();

        if (stats.most_active_type) {
          document.getElementById('statMostActiveType').textContent = stats.most_active_type.label || '-';
        }
      } catch (error) {
        console.error('Error loading statistics:', error);
      }
    }

    async function loadTransactions() {
      try {
        const response = await authenticatedFetch('/transactions?per_page=500&sort=-transaction_date');

        // Handle both paginated and plain array responses
        allTransactions = Array.isArray(response) ? response : (response.data || []);
        filteredTransactions = allTransactions;
        renderTransactionsTable();

        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('transactionsTableContainer').style.display = 'block';
        document.getElementById('paginationContainer').style.display = 'flex';
      } catch (error) {
        console.error('Error loading transactions:', error);
        if (error.message !== 'Session expired') {
          showNotification('Failed to load transactions', 'danger');
        }
      }
    }

    function applyFilters() {
      const typeFilter = document.getElementById('typeFilter').value;
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;
      const search = document.getElementById('searchInput').value.toLowerCase();

      filteredTransactions = allTransactions.filter(trans => {
        // Type filter
        if (typeFilter && trans.type !== typeFilter) return false;

        // Date filters
        if (startDate) {
          const transDate = new Date(trans.transaction_date);
          if (transDate < new Date(startDate)) return false;
        }
        if (endDate) {
          const transDate = new Date(trans.transaction_date);
          if (transDate > new Date(endDate + 'T23:59:59')) return false;
        }

        // Search filter
        if (search) {
          const searchableText = [
            trans.product?.sku,
            trans.product?.description,
            trans.reference_number,
            trans.notes,
            trans.user?.name
          ].filter(Boolean).join(' ').toLowerCase();

          if (!searchableText.includes(search)) return false;
        }

        return true;
      });

      currentPage = 1;
      renderTransactionsTable();
    }

    function renderTransactionsTable() {
      const tbody = document.getElementById('transactionsTableBody');
      tbody.innerHTML = '';

      if (filteredTransactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5">No transactions found</td></tr>';
        document.getElementById('paginationContainer').style.display = 'none';
        return;
      }

      // Pagination
      const start = (currentPage - 1) * perPage;
      const end = start + perPage;
      const pageTransactions = filteredTransactions.slice(start, end);

      const typeColors = {
        'receipt': 'success',
        'shipment': 'warning',
        'adjustment': 'info',
        'transfer': 'purple',
        'return': 'danger',
        'cycle_count': 'azure',
        'job_issue': 'orange',
        'issue': 'red',
        'job_material_transfer': 'teal'
      };

      tbody.innerHTML = pageTransactions.map(trans => {
        const typeColor = typeColors[trans.type] || 'secondary';
        const quantityClass = trans.quantity >= 0 ? 'text-success' : 'text-danger';
        const quantitySign = trans.quantity >= 0 ? '+' : '';

        return `
          <tr>
            <td>
              <div class="small">${formatDateTime(trans.transaction_date)}</div>
            </td>
            <td>
              <span class="badge text-bg-${typeColor}">${formatType(trans.type)}</span>
            </td>
            <td>
              <div>
                <strong>${trans.product?.sku || '-'}</strong>
                <div class="small text-muted">${trans.product?.description || '-'}</div>
              </div>
            </td>
            <td class="text-end ${quantityClass} fw-bold">${quantitySign}${trans.quantity}</td>
            <td class="text-end">${trans.quantity_before}</td>
            <td class="text-end">${trans.quantity_after}</td>
            <td>
              <div class="small">${trans.reference_number || '-'}</div>
            </td>
            <td>
              <div class="small">${trans.user?.name || '-'}</div>
            </td>
            <td class="table-actions">
              <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="viewTransaction(${trans.id})" title="View Details">
                <i class="ti ti-eye"></i>
              </button>
              ${isAdmin() ? `
              <button class="btn btn-sm btn-icon btn-ghost-secondary" onclick="editTransaction(${trans.id})" title="Edit">
                <i class="ti ti-pencil"></i>
              </button>
              <button class="btn btn-sm btn-icon btn-ghost-danger" onclick="deleteTransaction(${trans.id})" title="Delete">
                <i class="ti ti-trash"></i>
              </button>
              ` : ''}
            </td>
          </tr>
        `;
      }).join('');

      updatePagination();
    }

    function updatePagination() {
      const total = filteredTransactions.length;
      const start = (currentPage - 1) * perPage + 1;
      const end = Math.min(currentPage * perPage, total);
      const totalPages = Math.ceil(total / perPage);

      document.getElementById('showingStart').textContent = start;
      document.getElementById('showingEnd').textContent = end;
      document.getElementById('totalRecords').textContent = total;

      const paginationLinks = document.getElementById('paginationLinks');
      paginationLinks.innerHTML = '';

      if (totalPages <= 1) {
        document.getElementById('paginationContainer').style.display = 'none';
        return;
      }

      document.getElementById('paginationContainer').style.display = 'flex';

      const pageItems = [];

      // Previous button
      pageItems.push(`
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
          <a class="page-link" href="#" onclick="goToPage(${currentPage - 1}); return false;">Prev</a>
        </li>
      `);

      // Page numbers
      for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
          pageItems.push(`
            <li class="page-item ${i === currentPage ? 'active' : ''}">
              <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
            </li>
          `);
        } else if (i === currentPage - 3 || i === currentPage + 3) {
          pageItems.push('<li class="page-item disabled"><span class="page-link">...</span></li>');
        }
      }

      // Next button
      pageItems.push(`
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
          <a class="page-link" href="#" onclick="goToPage(${currentPage + 1}); return false;">Next</a>
        </li>
      `);

      paginationLinks.innerHTML = pageItems.join('');
    }

    function goToPage(page) {
      currentPage = page;
      renderTransactionsTable();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function viewTransaction(transactionId) {
      try {
        const trans = await authenticatedFetch(`/transactions/${transactionId}`);

        const typeColors = {
          'receipt': 'success',
          'shipment': 'warning',
          'adjustment': 'info',
          'transfer': 'purple',
          'return': 'danger',
          'cycle_count': 'azure',
          'job_issue': 'orange',
          'issue': 'red',
          'job_material_transfer': 'teal'
        };
        const typeColor = typeColors[trans.type] || 'secondary';
        const quantityClass = trans.quantity >= 0 ? 'text-success' : 'text-danger';
        const quantitySign = trans.quantity >= 0 ? '+' : '';

        document.getElementById('transactionDetails').innerHTML = `
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Transaction Date</label>
              <p>${formatDateTime(trans.transaction_date)}</p>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Transaction Type</label>
              <p><span class="badge text-bg-${typeColor}">${formatType(trans.type)}</span></p>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label fw-bold">Product</label>
              <p><strong>${trans.product?.sku || '-'}</strong> - ${trans.product?.description || '-'}</p>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">Quantity Change</label>
              <p class="${quantityClass} h3">${quantitySign}${trans.quantity}</p>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Quantity Before</label>
              <p class="h3">${trans.quantity_before}</p>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Quantity After</label>
              <p class="h3">${trans.quantity_after}</p>
            </div>
          </div>
          ${trans.reference_number ? `
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Reference Number</label>
              <p>${trans.reference_number}</p>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Reference Type</label>
              <p>${trans.reference_type || '-'}</p>
            </div>
          </div>
          ` : ''}
          ${trans.notes ? `
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label fw-bold">Notes</label>
              <p style="white-space: pre-wrap;">${trans.notes}</p>
            </div>
          </div>
          ` : ''}
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Performed By</label>
              <p>${trans.user?.name || '-'}</p>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Created At</label>
              <p>${formatDateTime(trans.created_at)}</p>
            </div>
          </div>
        `;

        showModal(document.getElementById('transactionModal'));
      } catch (error) {
        console.error('Error loading transaction details:', error);
        showNotification('Failed to load transaction details', 'danger');
      }
    }

    function toDateTimeLocal(dateString) {
      if (!dateString) return '';
      const d = new Date(dateString);
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    async function editTransaction(transactionId) {
      if (!isAdmin()) return;
      try {
        const trans = await authenticatedFetch(`/transactions/${transactionId}`);

        document.getElementById('editTransactionId').value = trans.id;
        document.getElementById('editTransactionProduct').textContent =
          `${trans.product?.sku || '-'} - ${trans.product?.description || '-'}`;
        document.getElementById('editTransactionType').value = trans.type;
        document.getElementById('editTransactionQuantity').value = Math.abs(trans.quantity);
        document.getElementById('editTransactionDate').value = toDateTimeLocal(trans.transaction_date);
        document.getElementById('editTransactionReference').value = trans.reference_number || '';
        document.getElementById('editTransactionNotes').value = trans.notes || '';

        showModal(document.getElementById('editTransactionModal'));
      } catch (error) {
        console.error('Error loading transaction for edit:', error);
        showNotification('Failed to load transaction', 'danger');
      }
    }

    async function deleteTransaction(transactionId) {
      if (!isAdmin()) return;
      if (!confirm('Delete this transaction? Its effect on inventory will be reversed. This action cannot be undone.')) return;

      try {
        const response = await apiCall(`/transactions/${transactionId}`, { method: 'DELETE' });

        if (response.status === 401) {
          localStorage.removeItem('userData');
          currentUser = null;
          showLogin();
          showNotification('Session expired. Please login again.', 'warning');
          return;
        }

        if (response.ok) {
          showNotification('Transaction deleted successfully', 'success');
          await Promise.all([loadTransactions(), loadStatistics()]);
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to delete transaction', 'danger');
        }
      } catch (error) {
        console.error('Error deleting transaction:', error);
        showNotification('Failed to delete transaction', 'danger');
      }
    }

    function formatType(type) {
      const types = {
        'receipt': 'Receipt',
        'shipment': 'Shipment',
        'adjustment': 'Adjustment',
        'transfer': 'Transfer',
        'return': 'Return',
        'cycle_count': 'Cycle Count',
        'job_issue': 'Job Issue',
        'issue': 'Issue',
        'job_material_transfer': 'Job Material Transfer'
      };
      return types[type] || type;
    }

    function formatDateTime(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }

    async function exportTransactions() {
      try {
        window.location.href = `/api/v1/transactions-export`;
        showNotification('Export started successfully', 'success');
      } catch (error) {
        console.error('Export failed:', error);
        showNotification('Export failed', 'danger');
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

    // ========== MANUAL TRANSACTION ==========
    let productLines = [];
    let productLineCounter = 0;

    function showAddTransactionModal() {
      // Reset form
      document.getElementById('addTransactionForm').reset();
      productLines = [];
      productLineCounter = 0;
      document.getElementById('productLinesContainer').innerHTML = '';
      document.getElementById('noProductsMessage').style.display = 'block';

      // Set default date to now
      const now = new Date();
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
      document.getElementById('manualTransactionDate').value = now.toISOString().slice(0, 16);

      showModal(document.getElementById('addTransactionModal'));
    }

    function addProductLine() {
      const lineId = productLineCounter++;
      document.getElementById('noProductsMessage').style.display = 'none';

      const lineHtml = `
        <div class="card mb-2 tx-line" id="productLine${lineId}">
          <div class="card-body">
            <div class="mb-2">
              <label class="form-label required">Product</label>
              <input type="text" class="form-control product-search-input"
                     id="productSearch${lineId}"
                     placeholder="Search — SKU, part #, description, finish (any order, any case)"
                     autocomplete="off">
              <div class="product-search-results list-group"
                   id="productSearchResults${lineId}"
                   style="display: none;"></div>
              <div class="selected-product-info mt-2"
                   id="selectedProductInfo${lineId}"
                   style="display: none;">
                <div class="alert alert-info mb-0 py-1 px-2 small">
                  <strong class="selected-product-display"></strong>
                  <div class="small selected-product-available"></div>
                </div>
              </div>
            </div>
            <div class="row g-2 align-items-end">
              <div class="col-5 col-sm-4">
                <label class="form-label required">Quantity</label>
                <input type="number" class="form-control"
                       id="productQuantity${lineId}"
                       inputmode="numeric" placeholder="Qty" min="1" step="1" required
                       oninput="updateLineUnitHint(${lineId})">
              </div>
              <div class="col-4 col-sm-3">
                <label class="form-label">Unit</label>
                <select class="form-select" id="productUnit${lineId}" disabled
                        onchange="updateLineUnitHint(${lineId})">
                  <option value="each">Each</option>
                  <option value="pack">Pack</option>
                </select>
              </div>
              <div class="col-3 col-sm-2 d-flex">
                <button type="button" class="btn btn-danger w-100" onclick="removeProductLine(${lineId})" title="Remove part">
                  <i class="ti ti-trash"></i>
                </button>
              </div>
              <div class="col-12">
                <div class="form-hint mt-1" id="productUnitHint${lineId}"></div>
              </div>
            </div>
          </div>
        </div>
      `;

      document.getElementById('productLinesContainer').insertAdjacentHTML('beforeend', lineHtml);

      // Initialize product search for this line
      initializeProductSearch(lineId);
    }

    function removeProductLine(lineId) {
      const lineElement = document.getElementById(`productLine${lineId}`);
      if (lineElement) {
        lineElement.remove();
      }

      // Remove from productLines array
      productLines = productLines.filter(line => line.lineId !== lineId);

      // Show "no products" message if no lines left
      if (document.getElementById('productLinesContainer').children.length === 0) {
        document.getElementById('noProductsMessage').style.display = 'block';
      }
    }

    function initializeProductSearch(lineId) {
      const searchInput = document.getElementById(`productSearch${lineId}`);
      let searchTimeout = null;

      // Close the results when focus leaves the field (delay so a tap on a
      // result still registers).
      searchInput.addEventListener('blur', function() {
        setTimeout(() => {
          const rc = document.getElementById(`productSearchResults${lineId}`);
          if (rc) rc.style.display = 'none';
        }, 200);
      });

      searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.trim();

        if (searchTimeout) {
          clearTimeout(searchTimeout);
        }

        const resultsContainer = document.getElementById(`productSearchResults${lineId}`);

        if (searchTerm.length < 2) {
          resultsContainer.style.display = 'none';
          return;
        }

        searchTimeout = setTimeout(async () => {
          try {
            const response = await authenticatedFetch(`/products?search=${encodeURIComponent(searchTerm)}&per_page=10`);
            const products = Array.isArray(response) ? response : (response.data || []);

            if (products.length === 0) {
              resultsContainer.innerHTML = '<div class="list-group-item text-muted">No products found</div>';
              resultsContainer.style.display = 'block';
              return;
            }

            resultsContainer.innerHTML = products.map(product => {
              const packSize = parseInt(product.pack_size) || 1;
              const availEa = Math.round(product.quantity_available || 0);
              const availStr = packSize > 1
                ? `${Math.round(product.quantity_available_packs ?? (availEa / packSize))} pk (${availEa} ea) · ${packSize}/pack`
                : `${availEa} ea`;
              const meta = [product.part_number, product.finish].filter(Boolean).map(escapeHtml).join(' · ');
              return `
              <a href="#" class="list-group-item list-group-item-action"
                 data-line-id="${lineId}"
                 data-product-id="${product.id}"
                 data-product-sku="${escapeHtml(product.sku)}"
                 data-product-description="${escapeHtml(product.description)}"
                 data-product-available="${availEa}"
                 data-product-available-packs="${product.quantity_available_packs ?? ''}"
                 data-product-pack-size="${packSize}">
                <strong>${escapeHtml(product.sku)}</strong>${meta ? ` <span class="text-muted">${meta}</span>` : ''}
                <div>${escapeHtml(product.description)}</div>
                <div class="small text-muted">Available: ${availStr}</div>
              </a>`;
            }).join('');
            resultsContainer.style.display = 'block';

            // Add click handlers
            resultsContainer.querySelectorAll('a').forEach(item => {
              item.addEventListener('click', function(e) {
                e.preventDefault();
                selectProductForLine(
                  parseInt(this.dataset.lineId),
                  parseInt(this.dataset.productId),
                  this.dataset.productSku,
                  this.dataset.productDescription,
                  parseInt(this.dataset.productAvailable),
                  parseInt(this.dataset.productPackSize) || 1,
                  this.dataset.productAvailablePacks === '' ? null : parseFloat(this.dataset.productAvailablePacks)
                );
              });
            });
          } catch (error) {
            console.error('Error searching products:', error);
          }
        }, 300);
      });
    }

    function selectProductForLine(lineId, productId, sku, description, available, packSize = 1, availablePacks = null) {
      packSize = parseInt(packSize) || 1;
      const existingIndex = productLines.findIndex(line => line.lineId === lineId);
      const lineData = { lineId, productId, sku, description, available, packSize, availablePacks };

      if (existingIndex >= 0) {
        productLines[existingIndex] = lineData;
      } else {
        productLines.push(lineData);
      }

      // Update UI
      const searchInput = document.getElementById(`productSearch${lineId}`);
      const selectedInfo = document.getElementById(`selectedProductInfo${lineId}`);
      const resultsContainer = document.getElementById(`productSearchResults${lineId}`);
      const unitSelect = document.getElementById(`productUnit${lineId}`);

      searchInput.value = `${sku} - ${description}`;
      const availStr = packSize > 1
        ? `${availablePacks != null ? Math.round(availablePacks) : Math.round(available / packSize)} pk (${Math.round(available)} ea) · ${packSize} per pack`
        : `${Math.round(available)} ea`;
      selectedInfo.querySelector('.selected-product-display').textContent = `${sku} - ${description}`;
      selectedInfo.querySelector('.selected-product-available').textContent = `Available: ${availStr}`;
      selectedInfo.style.display = 'block';
      resultsContainer.style.display = 'none';

      // Enable the Each/Pack toggle only for pack-tracked products.
      if (packSize > 1) {
        unitSelect.disabled = false;
        unitSelect.value = 'each';
      } else {
        unitSelect.disabled = true;
        unitSelect.value = 'each';
      }
      updateLineUnitHint(lineId);
    }

    // "= N ea" helper shown under the quantity field for pack-tracked products.
    function updateLineUnitHint(lineId) {
      const line = productLines.find(l => l.lineId === lineId);
      const hint = document.getElementById(`productUnitHint${lineId}`);
      if (!hint) return;
      const qty = parseInt(document.getElementById(`productQuantity${lineId}`).value) || 0;
      const unit = document.getElementById(`productUnit${lineId}`).value;
      const packSize = line?.packSize || 1;

      if (!line || packSize <= 1) { hint.textContent = ''; return; }
      if (unit === 'pack') {
        hint.textContent = qty > 0 ? `= ${qty * packSize} ea  (${packSize} per pack)` : `${packSize} per pack`;
      } else {
        hint.textContent = qty >= packSize && qty % packSize === 0
          ? `= ${qty / packSize} pk`
          : `${packSize} per pack`;
      }
    }

    // Submit manual transaction
    document.getElementById('addTransactionForm').addEventListener('submit', async function(e) {
      e.preventDefault();

      // Validate at least one product
      if (productLines.length === 0) {
        showNotification('Please add at least one product', 'danger');
        return;
      }

      // Build products array with quantities
      const products = [];
      let hasError = false;

      for (const line of productLines) {
        const quantity = parseInt(document.getElementById(`productQuantity${line.lineId}`).value);
        const unit = document.getElementById(`productUnit${line.lineId}`)?.value || 'each';
        const packSize = line.packSize || 1;

        if (!quantity || quantity < 1) {
          showNotification(`Please enter a valid quantity for ${line.sku}`, 'danger');
          hasError = true;
          break;
        }

        // Transactions are recorded in eaches; a "pack" quantity is expanded.
        const eaches = unit === 'pack' ? quantity * packSize : quantity;

        products.push({
          product_id: line.productId,
          quantity: eaches
        });
      }

      if (hasError) return;

      const formData = new FormData(e.target);
      const data = {
        type: formData.get('type'),
        transaction_date: formData.get('transaction_date'),
        reference_number: formData.get('reference_number'),
        notes: formData.get('notes') || null,
        products: products
      };

      const btn = document.getElementById('saveManualTransactionBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

      try {
        const response = await apiCall('/transactions/manual', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(data)
        });

        // Handle 401 Unauthorized
        if (response.status === 401) {
          localStorage.removeItem('userData');
          currentUser = null;
          showLogin();
          showNotification('Session expired. Please login again.', 'warning');
          return;
        }

        if (response.ok) {
          showNotification('Transaction added successfully', 'success');
          hideModal(document.getElementById('addTransactionModal'));

          // Reload transactions and stats
          currentPage = 1;
          await Promise.all([
            loadTransactions(),
            loadStatistics()
          ]);
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to add transaction', 'danger');
        }
      } catch (error) {
        console.error('Error adding transaction:', error);
        showNotification('Failed to add transaction', 'danger');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-check me-1"></i>Save Transaction';
      }
    });

    document.getElementById('editTransactionForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!isAdmin()) return;

      const id = document.getElementById('editTransactionId').value;
      const formData = new FormData(e.target);
      const quantity = parseInt(formData.get('quantity'));

      if (!quantity || quantity < 1) {
        showNotification('Please enter a valid quantity', 'danger');
        return;
      }

      const data = {
        type: formData.get('type'),
        transaction_date: formData.get('transaction_date'),
        reference_number: formData.get('reference_number'),
        notes: formData.get('notes') || null,
        quantity: quantity
      };

      const btn = document.getElementById('saveEditTransactionBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

      try {
        const response = await apiCall(`/transactions/${id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });

        if (response.status === 401) {
          localStorage.removeItem('userData');
          currentUser = null;
          showLogin();
          showNotification('Session expired. Please login again.', 'warning');
          return;
        }

        if (response.ok) {
          showNotification('Transaction updated successfully', 'success');
          hideModal(document.getElementById('editTransactionModal'));
          await Promise.all([loadTransactions(), loadStatistics()]);
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to update transaction', 'danger');
        }
      } catch (error) {
        console.error('Error updating transaction:', error);
        showNotification('Failed to update transaction', 'danger');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-check me-1"></i>Save Changes';
      }
    });
  </script>
@endpush
