@extends('layouts.app')

@section('title', 'Low Stock - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Inventory Management</div>
              <h1 class="page-title">Low Stock Items</h1>
              <p class="text-muted">Items below their reorder point requiring attention</p>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn" onclick="exportProducts()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                  Export
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
                  <div class="subheader">Low Stock Items</div>
                  <div class="h1 mb-3 text-warning" id="statLowStockCount">-</div>
                  <div>Items needing reorder</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Total Value at Risk</div>
                  <div class="h1 mb-3" id="statTotalValue">-</div>
                  <div>Inventory value affected</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Avg Days to Stockout</div>
                  <div class="h1 mb-3 text-danger" id="statAvgDays">-</div>
                  <div>Based on current usage</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Suggested PO Value</div>
                  <div class="h1 mb-3 text-info" id="statSuggestedPO">-</div>
                  <div>Recommended order total</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Inventory Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Low Stock Items</h3>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="categoryFilter" style="width: auto;">
                      <option value="">All Categories</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" placeholder="Search..." id="searchInput" style="min-width: 200px;">
                  </div>
                </div>
                <div class="card-body">
                  <div class="loading" id="loadingIndicator">
                    <div class="spinner-border" role="status"></div>
                    <div>Loading low stock items...</div>
                  </div>

                  <div class="table-responsive" id="inventoryTableContainer" style="display: none;">
                    <table class="table table-vcenter card-table table-striped">
                      <thead>
                        <tr>
                          <th>SKU</th>
                          <th>Description</th>
                          <th class="text-end">Available</th>
                          <th class="text-end">Reorder Point</th>
                          <th class="text-end">Suggested Order</th>
                          <th>Days to Stockout</th>
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
      loadLowStockItems();
      loadCategories();

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', debounce(loadLowStockItems, 300));
      document.getElementById('categoryFilter').addEventListener('change', loadLowStockItems);
    });

    async function loadLowStockItems() {
      try {
        const search = document.getElementById('searchInput').value;
        const category = document.getElementById('categoryFilter').value;

        const params = new URLSearchParams({
          status: 'low_stock',
          per_page: 100
        });

        if (search) params.append('search', search);
        if (category) params.append('category_id', category);

        const data = await authenticatedFetch(`/products?${params}`);

        // Calculate stats
        const lowStockCount = data.data.length;
        const totalValue = data.data.reduce((sum, p) => sum + (p.quantity_available * p.unit_cost), 0);
        const avgDays = data.data.reduce((sum, p) => sum + (p.days_until_stockout || 0), 0) / lowStockCount;
        const suggestedPO = data.data.reduce((sum, p) => sum + ((p.suggested_order_qty || 0) * p.unit_cost), 0);

        document.getElementById('statLowStockCount').textContent = lowStockCount.toLocaleString();
        document.getElementById('statTotalValue').textContent = `$${totalValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('statAvgDays').textContent = avgDays > 0 ? Math.round(avgDays) : '-';
        document.getElementById('statSuggestedPO').textContent = `$${suggestedPO.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

        renderInventoryTable(data.data);

        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('inventoryTableContainer').style.display = 'block';
      } catch (error) {
        console.error('Error loading low stock items:', error);
        showNotification('Failed to load low stock items', 'danger');
      }
    }

    function renderInventoryTable(products) {
      const tbody = document.getElementById('inventoryTableBody');
      tbody.innerHTML = '';

      if (products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">No low stock items found</td></tr>';
        return;
      }

      products.forEach(product => {
        const statusBadge = getStatusBadge(product.status);
        const daysDisplay = product.days_until_stockout
          ? `<span class="badge ${product.days_until_stockout <= 7 ? 'bg-danger' : 'bg-warning'}">${product.days_until_stockout} days</span>`
          : '<span class="text-muted">-</span>';

        const row = `
          <tr>
            <td><span class="text-muted">${product.sku}</span></td>
            <td>${product.description}</td>
            <td class="text-end text-warning fw-bold">${product.quantity_available.toLocaleString()}</td>
            <td class="text-end">${product.reorder_point ? product.reorder_point.toLocaleString() : '-'}</td>
            <td class="text-end text-info">${product.suggested_order_qty ? product.suggested_order_qty.toLocaleString() : '-'}</td>
            <td>${daysDisplay}</td>
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

        const params = new URLSearchParams({
          status: 'low_stock',
          export: 'csv'
        });

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
        a.download = `low_stock_export_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showNotification('Low stock items exported successfully', 'success');
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
