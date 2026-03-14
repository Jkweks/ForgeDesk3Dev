@extends('layouts.app')

@section('content')
<div class="container-xl">
  <!-- Page header -->
  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">
          <i class="ti ti-shopping-cart me-2"></i>Purchase Orders
        </h2>
        <div class="text-muted mt-1">Material receiving and purchase order management</div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
          <button class="btn btn-primary" onclick="showCreatePOModal()">
            <i class="ti ti-plus me-1"></i>Create Purchase Order
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
              <div class="subheader">Total Orders</div>
            </div>
            <div class="h1 mb-0" id="statTotalOrders">-</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Open Orders</div>
            </div>
            <div class="h1 mb-0 text-primary" id="statOpenOrders">-</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Pending Value</div>
            </div>
            <div class="h1 mb-0 text-warning" id="statPendingValue">-</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Total Value</div>
            </div>
            <div class="h1 mb-0 text-success" id="statTotalValue">-</div>
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
                <select class="form-select" id="filterStatus" onchange="loadPurchaseOrders()">
                  <option value="">All Statuses</option>
                  <option value="draft">Draft</option>
                  <option value="submitted">Submitted</option>
                  <option value="approved">Approved</option>
                  <option value="partially_received">Partially Received</option>
                  <option value="received">Received</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Supplier</label>
                <select class="form-select" id="filterSupplier" onchange="loadPurchaseOrders()">
                  <option value="">All Suppliers</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" id="filterSearch" placeholder="PO number, notes..." onkeyup="debounceSearch()">
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

    <!-- Purchase Orders Table -->
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Purchase Orders</h3>
          </div>
          <div id="poLoading" class="card-body text-center py-5">
            <div class="spinner-border" role="status"></div>
            <div class="text-muted mt-2">Loading purchase orders...</div>
          </div>
          <div id="poContent" style="display: none;">
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Expected Date</th>
                    <th class="text-end">Total Amount</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th class="w-1">Actions</th>
                  </tr>
                </thead>
                <tbody id="poTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Create/Edit PO Modal -->
<div class="modal modal-blur fade" id="createPOModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create Purchase Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="poForm">
          <input type="hidden" id="poId">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label required">Supplier</label>
              <select class="form-select" id="poSupplier" required>
                <option value="">Select supplier...</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label required">Order Date</label>
              <input type="date" class="form-control" id="poOrderDate" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Expected Date</label>
              <input type="date" class="form-control" id="poExpectedDate">
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Ship To</label>
              <input type="text" class="form-control" id="poShipTo" placeholder="Warehouse location">
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="poNotes" rows="2"></textarea>
            </div>
          </div>

          <hr>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Line Items</h4>
            <button type="button" class="btn btn-sm btn-primary" onclick="addPOLineItem()">
              <i class="ti ti-plus me-1"></i>Add Item
            </button>
          </div>

          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th style="width: 35%">Product</th>
                  <th style="width: 15%">Quantity</th>
                  <th style="width: 15%">Unit Cost</th>
                  <th style="width: 15%">Total</th>
                  <th style="width: 15%">Location</th>
                  <th style="width: 5%"></th>
                </tr>
              </thead>
              <tbody id="poLineItems">
                <tr>
                  <td colspan="6" class="text-center text-muted">No items added. Click "Add Item" to add products.</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-end"><strong>Total:</strong></td>
                  <td><strong id="poTotalAmount">$0.00</strong></td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="savePurchaseOrder()">Create Purchase Order</button>
      </div>
    </div>
  </div>
</div>

<!-- View PO Details Modal -->
<div class="modal modal-blur fade" id="viewPOModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Purchase Order Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <table class="table table-sm">
              <tr>
                <th>PO Number:</th>
                <td id="viewPONumber"></td>
              </tr>
              <tr>
                <th>Supplier:</th>
                <td id="viewPOSupplier"></td>
              </tr>
              <tr>
                <th>Order Date:</th>
                <td id="viewPOOrderDate"></td>
              </tr>
              <tr>
                <th>Expected Date:</th>
                <td id="viewPOExpectedDate"></td>
              </tr>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm">
              <tr>
                <th>Status:</th>
                <td id="viewPOStatus"></td>
              </tr>
              <tr>
                <th>Total Amount:</th>
                <td id="viewPOTotal"></td>
              </tr>
              <tr>
                <th>Created By:</th>
                <td id="viewPOCreator"></td>
              </tr>
              <tr>
                <th>Approved By:</th>
                <td id="viewPOApprover"></td>
              </tr>
            </table>
          </div>
        </div>

        <div class="mb-3" id="viewPONotesSection" style="display: none;">
          <strong>Notes:</strong>
          <p id="viewPONotes" class="text-muted"></p>
        </div>

        <h4>Line Items</h4>
        <div class="table-responsive">
          <table class="table table-vcenter">
            <thead>
              <tr>
                <th>Product</th>
                <th class="text-end">Ordered</th>
                <th class="text-end">Received</th>
                <th class="text-end">Remaining</th>
                <th class="text-end">Unit Cost</th>
                <th class="text-end">Total</th>
                <th>Progress</th>
              </tr>
            </thead>
            <tbody id="viewPOItems"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
        <div id="poActionButtons"></div>
      </div>
    </div>
  </div>
</div>

<!-- Receive Materials Modal -->
<div class="modal modal-blur fade" id="receiveModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Receive Materials</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="receivePOId">
        <div class="mb-3">
          <label class="form-label">Received Date</label>
          <input type="date" class="form-control" id="receiveDate">
        </div>

        <h5>Select items to receive:</h5>
        <div class="table-responsive">
          <table class="table table-vcenter">
            <thead>
              <tr>
                <th style="width: 5%">
                  <input type="checkbox" class="form-check-input" onchange="toggleAllReceiveItems(this)">
                </th>
                <th>Product</th>
                <th class="text-end">Remaining</th>
                <th class="text-end">Receive Qty</th>
                <th>Location</th>
              </tr>
            </thead>
            <tbody id="receiveItemsTable"></tbody>
          </table>
        </div>

        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="receiveNotes" rows="2" placeholder="Optional receiving notes..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="submitReceive()">
          <i class="ti ti-check me-1"></i>Receive Materials
        </button>
      </div>
    </div>
  </div>
</div>

<script>
let currentPO = null;
let allProducts = [];
let allSuppliers = [];
let lineItemCounter = 0;
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
  loadSuppliers();
  loadProducts();
  loadPurchaseOrders();
  loadStatistics();

  // Set today's date as default
  document.getElementById('poOrderDate').value = new Date().toISOString().split('T')[0];
  document.getElementById('receiveDate').value = new Date().toISOString().split('T')[0];
});

// Load purchase orders
async function loadPurchaseOrders() {
  try {
    document.getElementById('poLoading').style.display = 'block';
    document.getElementById('poContent').style.display = 'none';

    const params = new URLSearchParams();
    const status = document.getElementById('filterStatus').value;
    const supplier = document.getElementById('filterSupplier').value;
    const search = document.getElementById('filterSearch').value;

    if (status) params.append('status', status);
    if (supplier) params.append('supplier_id', supplier);
    if (search) params.append('search', search);

    const response = await authenticatedFetch(`/purchase-orders?${params.toString()}`);
    const orders = response.data || response;

    renderPurchaseOrders(orders);

    document.getElementById('poLoading').style.display = 'none';
    document.getElementById('poContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading purchase orders:', error);
    showNotification('Error loading purchase orders', 'danger');
  }
}

// Render purchase orders table
function renderPurchaseOrders(orders) {
  const tbody = document.getElementById('poTableBody');

  if (orders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No purchase orders found</td></tr>';
    return;
  }

  tbody.innerHTML = orders.map(po => {
    const statusBadge = getStatusBadge(po.status);
    const progress = calculatePOProgress(po);

    return `
      <tr>
        <td><strong>${escapeHtml(po.po_number)}</strong></td>
        <td>${po.supplier ? escapeHtml(po.supplier.name) : '-'}</td>
        <td>${formatDate(po.order_date)}</td>
        <td>${po.expected_date ? formatDate(po.expected_date) : '-'}</td>
        <td class="text-end">${formatCurrency(po.total_amount)}</td>
        <td>${statusBadge}</td>
        <td>
          <div class="progress" style="height: 20px;">
            <div class="progress-bar ${progress.color}" style="width: ${progress.percentage}%">
              ${progress.percentage}%
            </div>
          </div>
        </td>
        <td>
          <div class="btn-group">
            <button class="btn btn-sm btn-ghost-primary" onclick="viewPODetails(${po.id})" title="View Details">
              <i class="ti ti-eye"></i>
            </button>
            ${po.status === 'approved' || po.status === 'partially_received' ? `
              <button class="btn btn-sm btn-ghost-success" onclick="showReceiveModal(${po.id})" title="Receive">
                <i class="ti ti-package"></i>
              </button>
            ` : ''}
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

// Load suppliers
async function loadSuppliers() {
  try {
    const response = await authenticatedFetch('/suppliers?is_active=1&per_page=all');
    allSuppliers = response.data || response;

    const selects = ['poSupplier', 'filterSupplier'];
    selects.forEach(selectId => {
      const select = document.getElementById(selectId);
      const currentValue = select.value;

      if (selectId === 'poSupplier') {
        select.innerHTML = '<option value="">Select supplier...</option>';
      } else {
        select.innerHTML = '<option value="">All Suppliers</option>';
      }

      allSuppliers.forEach(supplier => {
        const option = document.createElement('option');
        option.value = supplier.id;
        option.textContent = supplier.name;
        select.appendChild(option);
      });

      if (currentValue) select.value = currentValue;
    });
  } catch (error) {
    console.error('Error loading suppliers:', error);
  }
}

// Load products
async function loadProducts() {
  try {
    const response = await authenticatedFetch('/products?is_active=1&per_page=all');
    allProducts = response.data || response;
  } catch (error) {
    console.error('Error loading products:', error);
  }
}

// Load statistics
async function loadStatistics() {
  try {
    const stats = await authenticatedFetch('/purchase-orders-statistics');

    document.getElementById('statTotalOrders').textContent = stats.total_orders;
    document.getElementById('statOpenOrders').textContent =
      stats.approved + stats.partially_received;
    document.getElementById('statPendingValue').textContent = formatCurrency(stats.pending_value);
    document.getElementById('statTotalValue').textContent = formatCurrency(stats.total_value);
  } catch (error) {
    console.error('Error loading statistics:', error);
  }
}

// Show create PO modal
function showCreatePOModal() {
  document.getElementById('poForm').reset();
  document.getElementById('poId').value = '';
  document.getElementById('poOrderDate').value = new Date().toISOString().split('T')[0];
  document.getElementById('poLineItems').innerHTML = '<tr><td colspan="6" class="text-center text-muted">No items added. Click "Add Item" to add products.</td></tr>';
  document.getElementById('poTotalAmount').textContent = '$0.00';
  lineItemCounter = 0;

  safeShowModal('createPOModal');
}

// Add PO line item
function addPOLineItem() {
  lineItemCounter++;
  const tbody = document.getElementById('poLineItems');

  // Remove "no items" message if it exists
  if (tbody.querySelector('.text-center')) {
    tbody.innerHTML = '';
  }

  const row = document.createElement('tr');
  row.id = `lineItem${lineItemCounter}`;
  row.innerHTML = `
    <td>
      <select class="form-select form-select-sm" id="product${lineItemCounter}" onchange="updateLineItemCost(${lineItemCounter})" required>
        <option value="">Select product...</option>
        ${allProducts.map(p => `<option value="${p.id}" data-cost="${p.unit_cost}">${escapeHtml(p.sku)} - ${escapeHtml(p.description)}</option>`).join('')}
      </select>
    </td>
    <td>
      <input type="number" class="form-control form-control-sm" id="quantity${lineItemCounter}" min="1" value="1" onchange="updateLineItemCost(${lineItemCounter})" required>
    </td>
    <td>
      <input type="number" class="form-control form-control-sm" id="unitCost${lineItemCounter}" min="0" step="0.01" onchange="updateLineItemCost(${lineItemCounter})" required>
    </td>
    <td>
      <input type="text" class="form-control form-control-sm" id="lineTotal${lineItemCounter}" readonly>
    </td>
    <td>
      <input type="text" class="form-control form-control-sm" id="location${lineItemCounter}" placeholder="Optional">
    </td>
    <td>
      <button type="button" class="btn btn-sm btn-ghost-danger" onclick="removePOLineItem(${lineItemCounter})">
        <i class="ti ti-trash"></i>
      </button>
    </td>
  `;

  tbody.appendChild(row);
}

// Remove PO line item
function removePOLineItem(itemId) {
  document.getElementById(`lineItem${itemId}`).remove();
  updatePOTotal();

  // Show "no items" message if no items left
  const tbody = document.getElementById('poLineItems');
  if (tbody.children.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No items added. Click "Add Item" to add products.</td></tr>';
  }
}

// Update line item cost
function updateLineItemCost(itemId) {
  const productSelect = document.getElementById(`product${itemId}`);
  const quantityInput = document.getElementById(`quantity${itemId}`);
  const unitCostInput = document.getElementById(`unitCost${itemId}`);
  const lineTotalInput = document.getElementById(`lineTotal${itemId}`);

  // Auto-fill unit cost from product
  if (productSelect.value && !unitCostInput.value) {
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    const cost = selectedOption.getAttribute('data-cost');
    unitCostInput.value = cost;
  }

  // Calculate line total
  const quantity = parseFloat(quantityInput.value) || 0;
  const unitCost = parseFloat(unitCostInput.value) || 0;
  const lineTotal = quantity * unitCost;

  lineTotalInput.value = formatCurrency(lineTotal);

  updatePOTotal();
}

// Update PO total
function updatePOTotal() {
  let total = 0;
  const tbody = document.getElementById('poLineItems');

  Array.from(tbody.children).forEach(row => {
    if (row.querySelector('.form-select')) {
      const itemId = row.id.replace('lineItem', '');
      const quantity = parseFloat(document.getElementById(`quantity${itemId}`).value) || 0;
      const unitCost = parseFloat(document.getElementById(`unitCost${itemId}`).value) || 0;
      total += quantity * unitCost;
    }
  });

  document.getElementById('poTotalAmount').textContent = formatCurrency(total);
}

// Save purchase order
async function savePurchaseOrder() {
  try {
    const tbody = document.getElementById('poLineItems');
    const items = [];

    // Collect line items
    Array.from(tbody.children).forEach(row => {
      if (row.querySelector('.form-select')) {
        const itemId = row.id.replace('lineItem', '');
        const productId = document.getElementById(`product${itemId}`).value;
        const quantity = document.getElementById(`quantity${itemId}`).value;
        const unitCost = document.getElementById(`unitCost${itemId}`).value;
        const location = document.getElementById(`location${itemId}`).value;

        if (productId && quantity && unitCost) {
          items.push({
            product_id: parseInt(productId),
            quantity: parseInt(quantity),
            unit_cost: parseFloat(unitCost),
            destination_location: location || null,
          });
        }
      }
    });

    if (items.length === 0) {
      showNotification('Please add at least one item', 'warning');
      return;
    }

    const data = {
      supplier_id: parseInt(document.getElementById('poSupplier').value),
      order_date: document.getElementById('poOrderDate').value,
      expected_date: document.getElementById('poExpectedDate').value || null,
      ship_to: document.getElementById('poShipTo').value || null,
      notes: document.getElementById('poNotes').value || null,
      items: items,
    };

    await authenticatedFetch('/purchase-orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });

    showNotification('Purchase order created successfully', 'success');
    safeHideModal('createPOModal');
    loadPurchaseOrders();
    loadStatistics();
  } catch (error) {
    console.error('Error creating purchase order:', error);
    showNotification(error.message || 'Error creating purchase order', 'danger');
  }
}

// View PO details
async function viewPODetails(poId) {
  try {
    const po = await authenticatedFetch(`/purchase-orders/${poId}`);
    currentPO = po;

    // Header info
    document.getElementById('viewPONumber').textContent = po.po_number;
    document.getElementById('viewPOSupplier').textContent = po.supplier ? po.supplier.name : '-';
    document.getElementById('viewPOOrderDate').textContent = formatDate(po.order_date);
    document.getElementById('viewPOExpectedDate').textContent = po.expected_date ? formatDate(po.expected_date) : '-';
    document.getElementById('viewPOStatus').innerHTML = getStatusBadge(po.status);
    document.getElementById('viewPOTotal').textContent = formatCurrency(po.total_amount);
    document.getElementById('viewPOCreator').textContent = po.creator ? po.creator.name : '-';
    document.getElementById('viewPOApprover').textContent = po.approver ? po.approver.name : '-';

    // Notes
    if (po.notes) {
      document.getElementById('viewPONotes').textContent = po.notes;
      document.getElementById('viewPONotesSection').style.display = 'block';
    } else {
      document.getElementById('viewPONotesSection').style.display = 'none';
    }

    // Items
    const itemsBody = document.getElementById('viewPOItems');
    itemsBody.innerHTML = po.items.map(item => {
      const remaining = item.quantity_ordered - item.quantity_received;
      const progress = item.quantity_ordered > 0
        ? Math.round((item.quantity_received / item.quantity_ordered) * 100)
        : 0;

      return `
        <tr>
          <td>
            <strong>${escapeHtml(item.product.sku)}</strong><br>
            <small class="text-muted">${escapeHtml(item.product.description)}</small>
          </td>
          <td class="text-end">${item.quantity_ordered}</td>
          <td class="text-end text-success">${item.quantity_received}</td>
          <td class="text-end ${remaining > 0 ? 'text-warning' : ''}">${remaining}</td>
          <td class="text-end">${formatCurrency(item.unit_cost)}</td>
          <td class="text-end">${formatCurrency(item.total_cost)}</td>
          <td>
            <div class="progress" style="height: 20px;">
              <div class="progress-bar ${progress === 100 ? 'bg-success' : 'bg-primary'}" style="width: ${progress}%">
                ${progress}%
              </div>
            </div>
          </td>
        </tr>
      `;
    }).join('');

    // Action buttons
    const actionsDiv = document.getElementById('poActionButtons');
    actionsDiv.innerHTML = '';

    if (po.status === 'draft') {
      actionsDiv.innerHTML = `
        <button class="btn btn-primary" onclick="submitPO(${po.id})">
          <i class="ti ti-send me-1"></i>Submit for Approval
        </button>
        <button class="btn btn-danger" onclick="deletePO(${po.id})">
          <i class="ti ti-trash me-1"></i>Delete
        </button>
      `;
    } else if (po.status === 'submitted') {
      actionsDiv.innerHTML = `
        <button class="btn btn-success" onclick="approvePO(${po.id})">
          <i class="ti ti-check me-1"></i>Approve
        </button>
        <button class="btn btn-danger" onclick="cancelPO(${po.id})">
          <i class="ti ti-x me-1"></i>Cancel
        </button>
      `;
    } else if (po.status === 'approved' || po.status === 'partially_received') {
      actionsDiv.innerHTML = `
        <button class="btn btn-success" onclick="showReceiveModal(${po.id})">
          <i class="ti ti-package me-1"></i>Receive Materials
        </button>
        <button class="btn btn-danger" onclick="cancelPO(${po.id})">
          <i class="ti ti-x me-1"></i>Cancel
        </button>
      `;
    }

    safeShowModal('viewPOModal');
  } catch (error) {
    console.error('Error loading PO details:', error);
    showNotification('Error loading PO details', 'danger');
  }
}

// Submit PO
async function submitPO(poId) {
  if (!confirm('Submit this purchase order for approval?')) return;

  try {
    await authenticatedFetch(`/purchase-orders/${poId}/submit`, { method: 'POST' });
    showNotification('Purchase order submitted successfully', 'success');
    safeHideModal('viewPOModal');
    loadPurchaseOrders();
    loadStatistics();
  } catch (error) {
    console.error('Error submitting PO:', error);
    showNotification(error.message || 'Error submitting PO', 'danger');
  }
}

// Approve PO
async function approvePO(poId) {
  if (!confirm('Approve this purchase order?')) return;

  try {
    await authenticatedFetch(`/purchase-orders/${poId}/approve`, { method: 'POST' });
    showNotification('Purchase order approved successfully', 'success');
    safeHideModal('viewPOModal');
    loadPurchaseOrders();
    loadStatistics();
  } catch (error) {
    console.error('Error approving PO:', error);
    showNotification(error.message || 'Error approving PO', 'danger');
  }
}

// Cancel PO
async function cancelPO(poId) {
  if (!confirm('Cancel this purchase order? This will release any on-order quantities.')) return;

  try {
    await authenticatedFetch(`/purchase-orders/${poId}/cancel`, { method: 'POST' });
    showNotification('Purchase order cancelled successfully', 'success');
    safeHideModal('viewPOModal');
    loadPurchaseOrders();
    loadStatistics();
  } catch (error) {
    console.error('Error cancelling PO:', error);
    showNotification(error.message || 'Error cancelling PO', 'danger');
  }
}

// Delete PO
async function deletePO(poId) {
  if (!confirm('Delete this draft purchase order? This action cannot be undone.')) return;

  try {
    await authenticatedFetch(`/purchase-orders/${poId}`, { method: 'DELETE' });
    showNotification('Purchase order deleted successfully', 'success');
    safeHideModal('viewPOModal');
    loadPurchaseOrders();
    loadStatistics();
  } catch (error) {
    console.error('Error deleting PO:', error);
    showNotification(error.message || 'Error deleting PO', 'danger');
  }
}

// Show receive modal
async function showReceiveModal(poId) {
  // Show loading notification
  const loadingToast = showNotification('Loading purchase order...', 'info', false);

  try {
    const po = await authenticatedFetch(`/purchase-orders/${poId}`);

    // Dismiss loading notification
    if (loadingToast && loadingToast.hide) loadingToast.hide();

    document.getElementById('receivePOId').value = po.id;
    document.getElementById('receiveDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('receiveNotes').value = '';

    const tbody = document.getElementById('receiveItemsTable');
    tbody.innerHTML = po.items
      .filter(item => item.quantity_received < item.quantity_ordered)
      .map(item => {
        const remaining = item.quantity_ordered - item.quantity_received;
        return `
          <tr>
            <td>
              <input type="checkbox" class="form-check-input receive-item-check" data-item-id="${item.id}" checked>
            </td>
            <td>
              <strong>${escapeHtml(item.product.sku)}</strong><br>
              <small class="text-muted">${escapeHtml(item.product.description)}</small>
            </td>
            <td class="text-end">${remaining}</td>
            <td>
              <input type="number" class="form-control form-control-sm" id="receiveQty${item.id}"
                     min="1" max="${remaining}" value="${remaining}" style="width: 100px;">
            </td>
            <td>
              <input type="text" class="form-control form-control-sm" id="receiveLocation${item.id}"
                     value="${item.destination_location || ''}" placeholder="Location" style="width: 150px;">
            </td>
          </tr>
        `;
      }).join('');

    // Hide view modal if open
    safeHideModal('viewPOModal');

    safeShowModal('receiveModal');
  } catch (error) {
    // Dismiss loading notification
    if (loadingToast && loadingToast.hide) loadingToast.hide();

    console.error('Error loading receive modal:', error);
    showNotification('Error loading receive modal', 'danger');
  }
}

// Toggle all receive items
function toggleAllReceiveItems(checkbox) {
  document.querySelectorAll('.receive-item-check').forEach(cb => {
    cb.checked = checkbox.checked;
  });
}

// Submit receive
async function submitReceive() {
  try {
    const poId = document.getElementById('receivePOId').value;
    const receiveDate = document.getElementById('receiveDate').value;
    const notes = document.getElementById('receiveNotes').value;

    const items = [];
    document.querySelectorAll('.receive-item-check:checked').forEach(checkbox => {
      const itemId = checkbox.getAttribute('data-item-id');
      const quantity = document.getElementById(`receiveQty${itemId}`).value;
      const location = document.getElementById(`receiveLocation${itemId}`).value;

      if (quantity && parseInt(quantity) > 0) {
        items.push({
          item_id: parseInt(itemId),
          quantity: parseInt(quantity),
          location: location || null,
          notes: notes,
        });
      }
    });

    if (items.length === 0) {
      showNotification('Please select at least one item to receive', 'warning');
      return;
    }

    await authenticatedFetch(`/purchase-orders/${poId}/receive`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        received_date: receiveDate,
        items: items,
      }),
    });

    showNotification('Materials received successfully', 'success');
    safeHideModal('receiveModal');
    loadPurchaseOrders();
    loadStatistics();
  } catch (error) {
    console.error('Error receiving materials:', error);
    showNotification(error.message || 'Error receiving materials', 'danger');
  }
}

// Clear filters
function clearFilters() {
  document.getElementById('filterStatus').value = '';
  document.getElementById('filterSupplier').value = '';
  document.getElementById('filterSearch').value = '';
  loadPurchaseOrders();
}

// Debounce search
function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadPurchaseOrders();
  }, 500);
}

// Helper functions
function getStatusBadge(status) {
  const badges = {
    'draft': '<span class="badge text-bg-secondary">Draft</span>',
    'submitted': '<span class="badge text-bg-info">Submitted</span>',
    'approved': '<span class="badge text-bg-primary">Approved</span>',
    'partially_received': '<span class="badge text-bg-warning">Partially Received</span>',
    'received': '<span class="badge text-bg-success">Received</span>',
    'cancelled': '<span class="badge text-bg-danger">Cancelled</span>',
  };
  return badges[status] || status;
}

function calculatePOProgress(po) {
  const totalOrdered = po.items ? po.items.reduce((sum, item) => sum + item.quantity_ordered, 0) : 0;
  const totalReceived = po.items ? po.items.reduce((sum, item) => sum + item.quantity_received, 0) : 0;

  const percentage = totalOrdered > 0 ? Math.round((totalReceived / totalOrdered) * 100) : 0;
  let color = 'bg-secondary';

  if (percentage > 0 && percentage < 100) color = 'bg-warning';
  else if (percentage === 100) color = 'bg-success';

  return { percentage, color };
}

function formatCurrency(value) {
  // Use the global formatPrice function which handles permission-based masking
  if (typeof formatPrice === 'function') {
    return formatPrice(value);
  }
  // Fallback if formatPrice not available
  return '$' + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
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
