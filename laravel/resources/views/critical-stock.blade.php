@extends('layouts.app')

@section('title', 'Critical Stock - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Inventory Management</div>
              <h1 class="page-title">Critical Stock Items</h1>
              <p class="text-muted">Items critically low or out of stock requiring immediate attention</p>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn" onclick="exportProducts()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                  Export
                </button>
                <button class="btn btn-danger d-none d-sm-inline-block" onclick="generateEmergencyPO()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Generate Emergency PO
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <!-- Alert Banner -->
          <div class="alert alert-danger mb-3" role="alert">
            <div class="d-flex">
              <div>
                <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v4" /><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" /><path d="M12 16h.01" /></svg>
              </div>
              <div>
                <h4 class="alert-title">Critical Inventory Alert!</h4>
                <div class="text-secondary">These items are critically low or out of stock. Immediate action recommended to prevent production delays.</div>
              </div>
            </div>
          </div>

          <!-- Stats Cards -->
          <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Critical Items</div>
                  <div class="h1 mb-3 text-danger" id="statCriticalCount">-</div>
                  <div>Urgent reorder needed</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Out of Stock</div>
                  <div class="h1 mb-3 text-dark" id="statOutOfStock">-</div>
                  <div>No inventory available</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Total Impact</div>
                  <div class="h1 mb-3" id="statTotalValue">-</div>
                  <div>Potential revenue at risk</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Emergency PO Value</div>
                  <div class="h1 mb-3 text-danger" id="statEmergencyPO">-</div>
                  <div>Immediate order cost</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Inventory Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Critical Stock Items</h3>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="statusFilter" style="width: auto;">
                      <option value="">All Critical</option>
                      <option value="critical">Critical</option>
                      <option value="out_of_stock">Out of Stock</option>
                    </select>
                    <select class="form-select form-select-sm" id="categoryFilter" style="width: auto;">
                      <option value="">All Categories</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" placeholder="Search..." id="searchInput" style="min-width: 200px;">
                  </div>
                </div>
                <div class="card-body">
                  <div class="loading" id="loadingIndicator">
                    <div class="spinner-border" role="status"></div>
                    <div>Loading critical stock items...</div>
                  </div>

                  <div class="table-responsive" id="inventoryTableContainer" style="display: none;">
                    <table class="table table-vcenter card-table table-striped">
                      <thead>
                        <tr>
                          <th>Priority</th>
                          <th>SKU</th>
                          <th>Description</th>
                          <th class="text-end">Available</th>
                          <th class="text-end">Committed</th>
                          <th class="text-end">Reorder Point</th>
                          <th class="text-end">Suggested Order</th>
                          <th>Status</th>
                          <th class="w-1"></th>
                        </tr>
                      </thead>
                      <tbody id="inventoryTableBody"></tbody>
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

@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      loadCriticalStockItems();
      loadCategories();

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', debounce(loadCriticalStockItems, 300));
      document.getElementById('categoryFilter').addEventListener('change', loadCriticalStockItems);
      document.getElementById('statusFilter').addEventListener('change', loadCriticalStockItems);
    });

    async function loadCriticalStockItems() {
      try {
        const search = document.getElementById('searchInput').value;
        const category = document.getElementById('categoryFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;

        const params = new URLSearchParams({
          per_page: 100
        });

        // If specific status selected, use it; otherwise get both critical and out_of_stock
        if (statusFilter) {
          params.append('status', statusFilter);
        } else {
          params.append('status', 'critical,out_of_stock');
        }

        if (search) params.append('search', search);
        if (category) params.append('category_id', category);

        const data = await authenticatedFetch(`/products?${params}`);

        // Calculate stats
        const criticalCount = data.data.filter(p => p.status === 'critical').length;
        const outOfStockCount = data.data.filter(p => p.status === 'out_of_stock').length;
        const totalValue = data.data.reduce((sum, p) => sum + (p.quantity_available * p.unit_price), 0);
        const emergencyPO = data.data.reduce((sum, p) => sum + ((p.suggested_order_qty || 0) * p.unit_cost), 0);

        document.getElementById('statCriticalCount').textContent = criticalCount.toLocaleString();
        document.getElementById('statOutOfStock').textContent = outOfStockCount.toLocaleString();
        document.getElementById('statTotalValue').textContent = `$${totalValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('statEmergencyPO').textContent = `$${emergencyPO.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

        renderInventoryTable(data.data);

        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('inventoryTableContainer').style.display = 'block';
      } catch (error) {
        console.error('Error loading critical stock items:', error);
        showNotification('Failed to load critical stock items', 'danger');
      }
    }

    function renderInventoryTable(products) {
      const tbody = document.getElementById('inventoryTableBody');
      tbody.innerHTML = '';

      if (products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-success py-5"><i class="ti ti-check" style="font-size: 2rem;"></i><br>No critical stock items - all inventory levels are healthy!</td></tr>';
        return;
      }

      // Sort by priority: out of stock first, then by available quantity
      products.sort((a, b) => {
        if (a.status === 'out_of_stock' && b.status !== 'out_of_stock') return -1;
        if (a.status !== 'out_of_stock' && b.status === 'out_of_stock') return 1;
        return a.quantity_available - b.quantity_available;
      });

      products.forEach((product, index) => {
        const statusBadge = getStatusBadge(product.status);
        const priorityBadge = product.status === 'out_of_stock'
          ? '<span class="badge bg-dark">URGENT</span>'
          : `<span class="badge bg-danger">${index + 1}</span>`;

        const row = `
          <tr class="${product.status === 'out_of_stock' ? 'table-danger' : ''}">
            <td>${priorityBadge}</td>
            <td><span class="text-muted">${product.sku}</span></td>
            <td>${product.description}</td>
            <td class="text-end text-danger fw-bold">${product.quantity_available.toLocaleString()}</td>
            <td class="text-end text-warning">${product.quantity_committed.toLocaleString()}</td>
            <td class="text-end">${product.reorder_point ? product.reorder_point.toLocaleString() : '-'}</td>
            <td class="text-end text-info fw-bold">${product.suggested_order_qty ? product.suggested_order_qty.toLocaleString() : '-'}</td>
            <td>${statusBadge}</td>
            <td class="table-actions">
              <a href="/?product=${product.id}" class="btn btn-sm btn-icon btn-ghost-primary" title="View">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg>
              </a>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });
    }

    function getStatusBadge(status) {
      const badges = {
        'in_stock': '<span class="badge text-bg-success">In Stock</span>',
        'low_stock': '<span class="badge text-bg-warning">Low Stock</span>',
        'critical': '<span class="badge text-bg-danger">Critical</span>',
        'out_of_stock': '<span class="badge text-bg-dark">Out of Stock</span>'
      };
      return badges[status] || '<span class="badge text-bg-secondary">Unknown</span>';
    }

    async function loadCategories() {
      try {
        const categories = await authenticatedFetch(`/categories`);

        const select = document.getElementById('categoryFilter');
        categories.forEach(category => {
          const option = document.createElement('option');
          option.value = category.id;
          option.textContent = category.name;
          select.appendChild(option);
        });
      } catch (error) {
        console.error('Error loading categories:', error);
      }
    }

    async function exportProducts() {
      try {
        const authToken = localStorage.getItem('authToken');
        const search = document.getElementById('searchInput').value;
        const category = document.getElementById('categoryFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;

        const params = new URLSearchParams({
          export: 'csv'
        });

        if (statusFilter) {
          params.append('status', statusFilter);
        } else {
          params.append('status', 'critical,out_of_stock');
        }

        if (search) params.append('search', search);
        if (category) params.append('category_id', category);

        const response = await fetch(`${API_BASE}/products?${params}`, {
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
        a.download = `critical_stock_export_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showNotification('Critical stock items exported successfully', 'success');
      } catch (error) {
        console.error('Export failed:', error);
        showNotification('Export failed: ' + error.message, 'danger');
      }
    }

    function generateEmergencyPO() {
      showNotification('Emergency PO generation feature coming soon', 'info');
      // TODO: Implement emergency PO generation
      // This would collect all critical items and create a draft PO
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
