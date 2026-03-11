@extends('layouts.app')

@section('title', 'Supplier Management - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Organization</div>
              <h1 class="page-title">Supplier Directory</h1>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn btn-primary" onclick="showAddSupplierModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Add Supplier
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
                  <div class="subheader">Total Suppliers</div>
                  <div class="h1 mb-3" id="statTotalSuppliers">-</div>
                  <div>All suppliers</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Active Suppliers</div>
                  <div class="h1 mb-3" id="statActiveSuppliers">-</div>
                  <div>Currently active</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">With Products</div>
                  <div class="h1 mb-3" id="statSuppliersWithProducts">-</div>
                  <div>Suppliers in use</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Countries</div>
                  <div class="h1 mb-3" id="statCountries">-</div>
                  <div>Unique countries</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Supplier Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Suppliers</h3>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="filterCountry" style="width: auto;">
                      <option value="">All Countries</option>
                    </select>
                    <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
                      <option value="">All Status</option>
                      <option value="1">Active</option>
                      <option value="0">Inactive</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" placeholder="Search..." id="searchInput" style="width: 200px;">
                  </div>
                </div>
                <div class="card-body">
                  <div class="loading" id="loadingIndicator">
                    <div class="text-muted">Loading suppliers...</div>
                  </div>

                  <div id="suppliersTable" style="display: none;">
                    <div class="table-responsive">
                      <table class="table table-vcenter card-table table-striped">
                        <thead>
                          <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Lead Time</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th class="w-1">Actions</th>
                          </tr>
                        </thead>
                        <tbody id="supplierTableBody">
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Top Suppliers by Product Count -->
          <div class="row mt-3">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Top Suppliers by Product Count</h3>
                </div>
                <div class="card-body">
                  <div id="topSuppliers" class="row">
                    <div class="col-12 text-muted">Loading...</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Add/Edit Supplier Modal -->
    <div class="modal modal-blur fade" id="supplierModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="supplierModalTitle">Add Supplier</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="supplierForm">
            <div class="modal-body">
              <!-- Basic Information -->
              <h5 class="mb-3"><i class="ti ti-building me-2"></i>Basic Information</h5>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label required">Supplier Name</label>
                  <input type="text" class="form-control" id="supplierName" name="name" required>
                  <small class="form-hint">Legal or trading name</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Code</label>
                  <input type="text" class="form-control" id="supplierCode" name="code">
                  <small class="form-hint">Unique identifier (optional)</small>
                </div>
              </div>

              <hr>

              <!-- Contact Information -->
              <h5 class="mb-3"><i class="ti ti-user me-2"></i>Contact Information</h5>
              <div class="row mb-3">
                <div class="col-md-4">
                  <label class="form-label">Contact Name</label>
                  <input type="text" class="form-control" id="supplierContactName" name="contact_name">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" id="supplierContactEmail" name="contact_email">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Phone</label>
                  <input type="tel" class="form-control" id="supplierContactPhone" name="contact_phone">
                </div>
              </div>

              <hr>

              <!-- Address -->
              <h5 class="mb-3"><i class="ti ti-map-pin me-2"></i>Address</h5>
              <div class="row mb-3">
                <div class="col-md-12">
                  <label class="form-label">Street Address</label>
                  <textarea class="form-control" id="supplierAddress" name="address" rows="2"></textarea>
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-md-4">
                  <label class="form-label">City</label>
                  <input type="text" class="form-control" id="supplierCity" name="city">
                </div>
                <div class="col-md-4">
                  <label class="form-label">State/Province</label>
                  <input type="text" class="form-control" id="supplierState" name="state">
                </div>
                <div class="col-md-4">
                  <label class="form-label">ZIP/Postal Code</label>
                  <input type="text" class="form-control" id="supplierZip" name="zip">
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Country</label>
                  <input type="text" class="form-control" id="supplierCountry" name="country" value="USA" list="countriesList">
                  <datalist id="countriesList"></datalist>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Website</label>
                  <input type="url" class="form-control" id="supplierWebsite" name="website" placeholder="https://">
                </div>
              </div>

              <hr>

              <!-- Business Terms -->
              <h5 class="mb-3"><i class="ti ti-file-text me-2"></i>Business Terms</h5>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Default Lead Time (Days)</label>
                  <input type="number" class="form-control" id="supplierLeadTime" name="default_lead_time_days" min="0" placeholder="e.g., 7">
                  <small class="form-hint">Typical delivery time in days</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Minimum Order Amount</label>
                  <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="supplierMinOrderAmount" name="minimum_order_amount" min="0" step="0.01" placeholder="0.00">
                  </div>
                  <small class="form-hint">Minimum order value</small>
                </div>
              </div>

              <div class="row mb-3">
                <div class="col-md-12">
                  <label class="form-label">Notes</label>
                  <textarea class="form-control" id="supplierNotes" name="notes" rows="3"></textarea>
                  <small class="form-hint">Additional information about this supplier</small>
                </div>
              </div>

              <div class="row">
                <div class="col-md-12">
                  <label class="form-label">Status</label>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="supplierIsActive" name="is_active" checked>
                    <label class="form-check-label" for="supplierIsActive">Active</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Supplier</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- View Supplier Details Modal -->
    <div class="modal modal-blur fade" id="viewSupplierModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="viewSupplierModalTitle">Supplier Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="supplierDetailsView"></div>

            <!-- Supplier Products -->
            <div class="mt-4">
              <h5 class="mb-3">Products from this Supplier</h5>
              <div id="supplierProducts">
                <div class="text-muted">Loading products...</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" onclick="editSupplierFromView()">Edit Supplier</button>
          </div>
        </div>
      </div>
    </div>

    <script>
    let suppliers = [];
    let countries = [];
    let statistics = {};
    let editingSupplierId = null;
    let viewingSupplierId = null;

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
      loadSuppliers();
      loadCountries();
      loadStatistics();
      setupEventListeners();
    });

    function setupEventListeners() {
      // Search
      document.getElementById('searchInput').addEventListener('input', debounce(loadSuppliers, 300));

      // Filters
      document.getElementById('filterCountry').addEventListener('change', loadSuppliers);
      document.getElementById('filterStatus').addEventListener('change', loadSuppliers);

      // Form submission
      document.getElementById('supplierForm').addEventListener('submit', handleSupplierSubmit);
    }

    async function loadSuppliers() {
      const search = document.getElementById('searchInput').value;
      const country = document.getElementById('filterCountry').value;
      const status = document.getElementById('filterStatus').value;

      let url = '/suppliers?per_page=all';

      if (search) {
        url += `&search=${encodeURIComponent(search)}`;
      }

      if (country) {
        url += `&country=${encodeURIComponent(country)}`;
      }

      if (status !== '') {
        url += `&is_active=${status}`;
      }

      try {
        const response = await authenticatedFetch(url);
        suppliers = response;
        renderSuppliers();
      } catch (error) {
        console.error('Error loading suppliers:', error);
        showNotification('Error loading suppliers', 'danger');
      }
    }

    async function loadCountries() {
      try {
        const response = await authenticatedFetch('/supplier-countries');
        countries = response;
        populateCountryFilter();
        populateCountryDatalist();
      } catch (error) {
        console.error('Error loading countries:', error);
      }
    }

    async function loadStatistics() {
      try {
        const response = await authenticatedFetch('/supplier-statistics');
        statistics = response;
        updateStats();
        renderTopSuppliers();
      } catch (error) {
        console.error('Error loading statistics:', error);
      }
    }

    function populateCountryFilter() {
      const select = document.getElementById('filterCountry');
      select.innerHTML = '<option value="">All Countries</option>';

      countries.forEach(country => {
        const option = document.createElement('option');
        option.value = country;
        option.textContent = country;
        select.appendChild(option);
      });
    }

    function populateCountryDatalist() {
      const datalist = document.getElementById('countriesList');
      datalist.innerHTML = '';

      countries.forEach(country => {
        const option = document.createElement('option');
        option.value = country;
        datalist.appendChild(option);
      });
    }

    function renderSuppliers() {
      document.getElementById('loadingIndicator').style.display = 'none';
      document.getElementById('suppliersTable').style.display = 'block';

      const tbody = document.getElementById('supplierTableBody');

      if (suppliers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No suppliers found</td></tr>';
        return;
      }

      tbody.innerHTML = suppliers.map(supplier => {
        const statusBadge = supplier.is_active
          ? '<span class="badge text-bg-success">Active</span>'
          : '<span class="badge text-bg-secondary">Inactive</span>';

        const location = [supplier.city, supplier.state, supplier.country]
          .filter(Boolean)
          .join(', ') || '-';

        return `
          <tr>
            <td>
              <strong>${escapeHtml(supplier.name)}</strong>
              ${supplier.code ? `<br><small class="text-muted">${escapeHtml(supplier.code)}</small>` : ''}
            </td>
            <td>${supplier.code ? escapeHtml(supplier.code) : '-'}</td>
            <td>
              ${supplier.contact_name ? escapeHtml(supplier.contact_name) : '-'}
              ${supplier.contact_email ? `<br><small class="text-muted">${escapeHtml(supplier.contact_email)}</small>` : ''}
            </td>
            <td>${escapeHtml(location)}</td>
            <td>${supplier.default_lead_time_days ? supplier.default_lead_time_days + ' days' : '-'}</td>
            <td>
              <span class="badge text-bg-azure">${supplier.products_count || 0}</span>
            </td>
            <td>${statusBadge}</td>
            <td>
              <div class="btn-group">
                <button class="btn btn-sm btn-ghost-primary" onclick="viewSupplier(${supplier.id})">
                  <i class="ti ti-eye"></i>
                </button>
                <button class="btn btn-sm btn-ghost-primary" onclick="editSupplier(${supplier.id})">
                  <i class="ti ti-edit"></i>
                </button>
                <button class="btn btn-sm btn-ghost-danger" onclick="deleteSupplier(${supplier.id}, '${escapeHtml(supplier.name)}')">
                  <i class="ti ti-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    function updateStats() {
      document.getElementById('statTotalSuppliers').textContent = statistics.stats?.total_suppliers || 0;
      document.getElementById('statActiveSuppliers').textContent = statistics.stats?.active_suppliers || 0;
      document.getElementById('statSuppliersWithProducts').textContent = statistics.stats?.suppliers_with_products || 0;
      document.getElementById('statCountries').textContent = statistics.stats?.countries_count || 0;
    }

    function renderTopSuppliers() {
      const container = document.getElementById('topSuppliers');

      if (!statistics.top_suppliers || statistics.top_suppliers.length === 0) {
        container.innerHTML = '<div class="col-12 text-muted">No data available</div>';
        return;
      }

      container.innerHTML = statistics.top_suppliers.map(supplier => {
        return `
          <div class="col-md-6 col-lg-4 mb-3">
            <div class="card card-sm">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-auto">
                    <span class="avatar" style="background-image: url(/static/avatars/company.svg)"></span>
                  </div>
                  <div class="col">
                    <div class="font-weight-medium">${escapeHtml(supplier.name)}</div>
                    <div class="text-muted">${supplier.products_count} product${supplier.products_count !== 1 ? 's' : ''}</div>
                  </div>
                  <div class="col-auto">
                    <button class="btn btn-sm" onclick="viewSupplier(${supplier.id})">View</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    function showAddSupplierModal() {
      editingSupplierId = null;
      document.getElementById('supplierModalTitle').textContent = 'Add Supplier';
      document.getElementById('supplierForm').reset();
      document.getElementById('supplierIsActive').checked = true;
      document.getElementById('supplierCountry').value = 'USA';

      safeShowModal('supplierModal');
    }

    async function editSupplier(id) {
      editingSupplierId = id;
      document.getElementById('supplierModalTitle').textContent = 'Edit Supplier';

      try {
        const supplier = await authenticatedFetch(`/suppliers/${id}`);

        document.getElementById('supplierName').value = supplier.supplier.name || '';
        document.getElementById('supplierCode').value = supplier.supplier.code || '';
        document.getElementById('supplierContactName').value = supplier.supplier.contact_name || '';
        document.getElementById('supplierContactEmail').value = supplier.supplier.contact_email || '';
        document.getElementById('supplierContactPhone').value = supplier.supplier.contact_phone || '';
        document.getElementById('supplierAddress').value = supplier.supplier.address || '';
        document.getElementById('supplierCity').value = supplier.supplier.city || '';
        document.getElementById('supplierState').value = supplier.supplier.state || '';
        document.getElementById('supplierZip').value = supplier.supplier.zip || '';
        document.getElementById('supplierCountry').value = supplier.supplier.country || 'USA';
        document.getElementById('supplierWebsite').value = supplier.supplier.website || '';
        document.getElementById('supplierLeadTime').value = supplier.supplier.default_lead_time_days || '';
        document.getElementById('supplierMinOrderAmount').value = supplier.supplier.minimum_order_amount || '';
        document.getElementById('supplierNotes').value = supplier.supplier.notes || '';
        document.getElementById('supplierIsActive').checked = supplier.supplier.is_active;

        safeShowModal('supplierModal');
      } catch (error) {
        console.error('Error loading supplier:', error);
        showNotification('Error loading supplier', 'danger');
      }
    }

    function editSupplierFromView() {
      safeHideModal('viewSupplierModal');
      editSupplier(viewingSupplierId);
    }

    async function viewSupplier(id) {
      viewingSupplierId = id;

      try {
        const data = await authenticatedFetch(`/suppliers/${id}`);
        const supplier = data.supplier;
        const stats = data.stats;

        // Build supplier details HTML
        const detailsHTML = `
          <div class="row mb-3">
            <div class="col-md-6">
              <h6 class="text-muted mb-2">Basic Information</h6>
              <table class="table table-sm">
                <tr><th>Name:</th><td>${escapeHtml(supplier.name)}</td></tr>
                <tr><th>Code:</th><td>${supplier.code ? escapeHtml(supplier.code) : '-'}</td></tr>
                <tr><th>Status:</th><td>${supplier.is_active ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>'}</td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-2">Contact Information</h6>
              <table class="table table-sm">
                <tr><th>Contact:</th><td>${supplier.contact_name ? escapeHtml(supplier.contact_name) : '-'}</td></tr>
                <tr><th>Email:</th><td>${supplier.contact_email ? `<a href="mailto:${escapeHtml(supplier.contact_email)}">${escapeHtml(supplier.contact_email)}</a>` : '-'}</td></tr>
                <tr><th>Phone:</th><td>${supplier.contact_phone ? escapeHtml(supplier.contact_phone) : '-'}</td></tr>
              </table>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <h6 class="text-muted mb-2">Address</h6>
              <table class="table table-sm">
                <tr><th>Address:</th><td>${supplier.address ? escapeHtml(supplier.address) : '-'}</td></tr>
                <tr><th>City:</th><td>${supplier.city ? escapeHtml(supplier.city) : '-'}</td></tr>
                <tr><th>State:</th><td>${supplier.state ? escapeHtml(supplier.state) : '-'}</td></tr>
                <tr><th>ZIP:</th><td>${supplier.zip ? escapeHtml(supplier.zip) : '-'}</td></tr>
                <tr><th>Country:</th><td>${supplier.country ? escapeHtml(supplier.country) : '-'}</td></tr>
                <tr><th>Website:</th><td>${supplier.website ? `<a href="${escapeHtml(supplier.website)}" target="_blank">${escapeHtml(supplier.website)}</a>` : '-'}</td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-2">Business Terms & Statistics</h6>
              <table class="table table-sm">
                <tr><th>Lead Time:</th><td>${supplier.default_lead_time_days ? supplier.default_lead_time_days + ' days' : '-'}</td></tr>
                <tr><th>Min Order:</th><td>${supplier.minimum_order_amount ? '$' + parseFloat(supplier.minimum_order_amount).toFixed(2) : '-'}</td></tr>
                <tr><th>Total Products:</th><td>${stats.total_products}</td></tr>
                <tr><th>Active Products:</th><td>${stats.active_products}</td></tr>
                <tr><th>Inventory Value:</th><td>$${parseFloat(stats.total_inventory_value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td></tr>
                <tr><th>Low Stock Items:</th><td>${stats.low_stock_items}</td></tr>
              </table>
            </div>
          </div>
          ${supplier.notes ? `<div class="row"><div class="col-12"><h6 class="text-muted mb-2">Notes</h6><p>${escapeHtml(supplier.notes)}</p></div></div>` : ''}
        `;

        document.getElementById('supplierDetailsView').innerHTML = detailsHTML;
        document.getElementById('viewSupplierModalTitle').textContent = supplier.name;

        // Load supplier products
        loadSupplierProducts(id);

        safeShowModal('viewSupplierModal');
      } catch (error) {
        console.error('Error loading supplier details:', error);
        showNotification('Error loading supplier details', 'danger');
      }
    }

    async function loadSupplierProducts(supplierId) {
      try {
        const response = await authenticatedFetch(`/suppliers/${supplierId}/products`);
        const products = response.data;

        const container = document.getElementById('supplierProducts');

        if (products.length === 0) {
          container.innerHTML = '<div class="text-muted">No products from this supplier</div>';
          return;
        }

        const productsHTML = `
          <div class="table-responsive">
            <table class="table table-sm table-vcenter">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Description</th>
                  <th>Category</th>
                  <th class="text-end">On Hand</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                ${products.map(product => `
                  <tr>
                    <td>${escapeHtml(product.sku)}</td>
                    <td>${escapeHtml(product.description)}</td>
                    <td>${product.category ? escapeHtml(product.category.name) : '-'}</td>
                    <td class="text-end">${product.quantity_on_hand}</td>
                    <td>${getStatusBadge(product.status)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;

        container.innerHTML = productsHTML;
      } catch (error) {
        console.error('Error loading supplier products:', error);
        document.getElementById('supplierProducts').innerHTML = '<div class="text-danger">Error loading products</div>';
      }
    }

    function getStatusBadge(status) {
      const badges = {
        'in_stock': '<span class="badge text-bg-success">In Stock</span>',
        'low_stock': '<span class="badge text-bg-warning">Low Stock</span>',
        'critical': '<span class="badge text-bg-danger">Critical</span>',
        'out_of_stock': '<span class="badge text-bg-dark">Out of Stock</span>'
      };
      return badges[status] || badges['in_stock'];
    }

    async function handleSupplierSubmit(e) {
      e.preventDefault();

      const formData = {
        name: document.getElementById('supplierName').value,
        code: document.getElementById('supplierCode').value || null,
        contact_name: document.getElementById('supplierContactName').value || null,
        contact_email: document.getElementById('supplierContactEmail').value || null,
        contact_phone: document.getElementById('supplierContactPhone').value || null,
        address: document.getElementById('supplierAddress').value || null,
        city: document.getElementById('supplierCity').value || null,
        state: document.getElementById('supplierState').value || null,
        zip: document.getElementById('supplierZip').value || null,
        country: document.getElementById('supplierCountry').value || null,
        website: document.getElementById('supplierWebsite').value || null,
        default_lead_time_days: document.getElementById('supplierLeadTime').value ? parseInt(document.getElementById('supplierLeadTime').value) : null,
        minimum_order_amount: document.getElementById('supplierMinOrderAmount').value ? parseFloat(document.getElementById('supplierMinOrderAmount').value) : null,
        notes: document.getElementById('supplierNotes').value || null,
        is_active: document.getElementById('supplierIsActive').checked,
      };

      try {
        if (editingSupplierId) {
          await authenticatedFetch(`/suppliers/${editingSupplierId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData),
          });
          showNotification('Supplier updated successfully', 'success');
        } else {
          await authenticatedFetch('/suppliers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData),
          });
          showNotification('Supplier created successfully', 'success');
        }

        safeHideModal('supplierModal');
        loadSuppliers();
        loadStatistics(); // Refresh statistics
      } catch (error) {
        console.error('Error saving supplier:', error);
        showNotification(error.message || 'Error saving supplier', 'danger');
      }
    }

    async function deleteSupplier(id, name) {
      if (!confirm(`Are you sure you want to delete "${name}"?\n\nThis action cannot be undone.`)) {
        return;
      }

      try {
        await authenticatedFetch(`/suppliers/${id}`, {
          method: 'DELETE',
        });
        showNotification('Supplier deleted successfully', 'success');
        loadSuppliers();
        loadStatistics();
      } catch (error) {
        console.error('Error deleting supplier:', error);
        showNotification(error.message || 'Error deleting supplier', 'danger');
      }
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
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
@endsection
