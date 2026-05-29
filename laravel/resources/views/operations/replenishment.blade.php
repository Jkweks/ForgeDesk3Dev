@extends('layouts.app')

@section('title', 'Vendor Replenishment - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Operations</div>
          <h1 class="page-title">
            <i class="ti ti-refresh-alert me-2"></i>Vendor Replenishment
          </h1>
          <p class="text-muted mb-0">Review critical and low-stock items, set order quantities, and create POs by vendor.</p>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <div class="btn-list">
            <button class="btn btn-outline-secondary" onclick="loadReplenishmentItems()">
              <i class="ti ti-refresh me-1"></i>Refresh
            </button>
            <button class="btn btn-primary" id="createAllPOsBtn" onclick="createAllPendingPOs()" disabled>
              <i class="ti ti-shopping-cart-plus me-1"></i>Create All POs
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      <!-- Stats -->
      <div class="row row-deck row-cards mb-3">
        <div class="col-6 col-lg">
          <div class="card">
            <div class="card-body">
              <div class="subheader">Out of Stock</div>
              <div class="h1 mb-1 text-dark" id="statOutOfStock">-</div>
              <div class="text-muted small">Zero available, max qty set</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg">
          <div class="card">
            <div class="card-body">
              <div class="subheader">Critical</div>
              <div class="h1 mb-1 text-danger" id="statCritical">-</div>
              <div class="text-muted small">Need immediate order</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg">
          <div class="card">
            <div class="card-body">
              <div class="subheader">Very Low</div>
              <div class="h1 mb-1 text-warning" id="statVeryLow">-</div>
              <div class="text-muted small">Below safety stock</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg">
          <div class="card">
            <div class="card-body">
              <div class="subheader">Low</div>
              <div class="h1 mb-1 text-info" id="statLow">-</div>
              <div class="text-muted small">Below reorder point</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg">
          <div class="card">
            <div class="card-body">
              <div class="subheader">Estimated PO Total</div>
              <div class="h1 mb-1 text-success" id="statEstTotal">$0.00</div>
              <div class="text-muted small">Selected items only</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="card mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label mb-1">Status Filter</label>
              <select class="form-select form-select-sm" id="filterStatus" onchange="applyFilters()">
                <option value="">All Urgent Statuses</option>
                <option value="out_of_stock">Out of Stock Only</option>
                <option value="critical">Critical Only</option>
                <option value="very_low">Very Low Only</option>
                <option value="low">Low Only</option>
                <option disabled>──────────</option>
                <option value="in_stock">In Stock</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1">Vendor</label>
              <select class="form-select form-select-sm" id="filterVendor" onchange="applyFilters()">
                <option value="">All Vendors</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Search</label>
              <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="SKU, description..." oninput="debounceSearch()">
            </div>
            <div class="col-md-2">
              <button class="btn btn-sm btn-secondary w-100" onclick="clearFilters()">
                <i class="ti ti-x me-1"></i>Clear
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div id="loadingState" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="text-muted mt-2">Loading replenishment items...</div>
      </div>

      <!-- Empty State -->
      <div id="emptyState" class="card" style="display:none;">
        <div class="card-body text-center py-6">
          <div class="text-success mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
          </div>
          <h3 class="text-muted">All stocked up!</h3>
          <p class="text-muted">No items are currently out of stock, critical, very low, or low in stock.</p>
        </div>
      </div>

      <!-- Vendor Sections -->
      <div id="vendorSections"></div>

    </div>
  </div>
</div>

<!-- Confirm PO Modal -->
<div class="modal modal-blur fade" id="confirmPOModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="ti ti-shopping-cart me-2"></i>Confirm Purchase Order
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Vendor</label>
            <div class="fw-bold pt-1" id="confirmVendorName">-</div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold">PO Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" id="confirmPONumber"
                   placeholder="e.g. PO-2024-1234" maxlength="50" autocomplete="off">
            <div class="form-hint">Enter your external PO reference number</div>
          </div>
          <div class="col-md-2">
            <label class="form-label">Order Date</label>
            <input type="date" class="form-control form-control-sm" id="confirmOrderDate">
          </div>
          <div class="col-md-2">
            <label class="form-label">Expected Date</label>
            <input type="date" class="form-control form-control-sm" id="confirmExpectedDate">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea class="form-control form-control-sm" id="confirmNotes" rows="2" placeholder="Optional PO notes..."></textarea>
        </div>
        <hr>
        <h5 class="mb-2">Line Items</h5>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Description</th>
                <th class="text-end">Qty</th>
                <th class="text-end">List Price</th>
                <th class="text-end">Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="confirmLineItems"></tbody>
            <tfoot>
              <tr class="fw-bold">
                <td colspan="4" class="text-end">Total:</td>
                <td class="text-end" id="confirmTotal">$0.00</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmCreateBtn" onclick="submitPO()">
          <i class="ti ti-check me-1"></i>Create Purchase Order
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
let allItems = [];         // Urgent replenishment products (critical/very_low/low/out_of_stock)
let allInStockItems = [];  // In-stock products — loaded lazily on demand
let filteredItems = [];    // After filters applied
let pendingPOSupplier = null;  // Supplier being confirmed
let searchTimeout = null;
let currentSortBy  = 'status';  // Default sort: urgency
let currentSortDir = 'asc';

const URGENCY_ORDER = { out_of_stock: 0, critical: 1, very_low: 2, low: 3 };

// ─── Init ───────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('confirmOrderDate').value = todayDate();
  loadReplenishmentItems();
});

function todayDate() {
  return new Date().toISOString().split('T')[0];
}

// ─── Data Loading ────────────────────────────────────────────────────────────

async function loadReplenishmentItems() {
  showLoading(true);
  document.getElementById('vendorSections').innerHTML = '';
  document.getElementById('emptyState').style.display = 'none';

  try {
    const data = await authenticatedFetch('/products?status=critical,very_low,low,out_of_stock&per_page=500&with_supplier=1');
    allItems = (data.data || data).filter(p => {
      if (!p.supplier_id) return false;
      // Only include out_of_stock items that have a maximum_quantity set
      if (p.status === 'out_of_stock' && !(p.maximum_quantity > 0)) return false;
      return true;
    });

    allInStockItems = []; // Reset so in-stock list is re-fetched on next request
    buildVendorFilter(allItems);
    applyFilters();
  } catch (err) {
    console.error('Error loading replenishment items:', err);
    showNotification('Failed to load replenishment items', 'danger');
  } finally {
    showLoading(false);
  }
}

function buildVendorFilter(items) {
  const vendors = {};
  items.forEach(p => {
    if (p.supplier_id && p.supplier) vendors[p.supplier_id] = p.supplier.name;
  });

  const select = document.getElementById('filterVendor');
  const current = select.value;
  select.innerHTML = '<option value="">All Vendors</option>';
  Object.entries(vendors).sort((a, b) => a[1].localeCompare(b[1])).forEach(([id, name]) => {
    const opt = document.createElement('option');
    opt.value = id;
    opt.textContent = name;
    select.appendChild(opt);
  });
  if (current) select.value = current;
}

// ─── Filtering & Rendering ───────────────────────────────────────────────────

async function loadInStockItems() {
  showLoading(true);
  try {
    const data = await authenticatedFetch('/products?status=in_stock&per_page=500&with_supplier=1');
    allInStockItems = (data.data || data).filter(p => p.supplier_id);
  } catch (err) {
    console.error('Error loading in-stock items:', err);
    showNotification('Failed to load in-stock items', 'danger');
  } finally {
    showLoading(false);
  }
}

async function applyFilters() {
  const status = document.getElementById('filterStatus').value;
  const vendor = document.getElementById('filterVendor').value;
  const search = document.getElementById('filterSearch').value.toLowerCase().trim();

  // Determine the source array; lazy-load in-stock items on first request
  let sourceItems;
  if (status === 'in_stock') {
    if (allInStockItems.length === 0) {
      await loadInStockItems();
    }
    sourceItems = allInStockItems;
  } else {
    sourceItems = allItems;
  }

  // Rebuild vendor dropdown for the active source so vendors match what's visible
  buildVendorFilter(sourceItems);

  filteredItems = sourceItems.filter(p => {
    if (status && p.status !== status) return false;
    if (vendor && String(p.supplier_id) !== vendor) return false;
    if (search) {
      const haystack = `${p.sku} ${p.description} ${p.part_number || ''}`.toLowerCase();
      if (!haystack.includes(search)) return false;
    }
    return true;
  });

  updateStats(filteredItems);
  renderVendorSections(filteredItems);
  updateGlobalEstimate();
}

function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(applyFilters, 250);
}

function clearFilters() {
  document.getElementById('filterStatus').value = '';
  document.getElementById('filterVendor').value = '';
  document.getElementById('filterSearch').value = '';
  applyFilters();
}

function updateStats(items) {
  document.getElementById('statOutOfStock').textContent = items.filter(p => p.status === 'out_of_stock').length;
  document.getElementById('statCritical').textContent   = items.filter(p => p.status === 'critical').length;
  document.getElementById('statVeryLow').textContent    = items.filter(p => p.status === 'very_low').length;
  document.getElementById('statLow').textContent        = items.filter(p => p.status === 'low').length;
}

// ─── Vendor Section Rendering ─────────────────────────────────────────────────

function renderVendorSections(items) {
  const container = document.getElementById('vendorSections');
  container.innerHTML = '';

  if (items.length === 0) {
    document.getElementById('emptyState').style.display = 'block';
    return;
  }
  document.getElementById('emptyState').style.display = 'none';

  // Group by supplier
  const groups = {};
  items.forEach(p => {
    const sid = p.supplier_id;
    if (!groups[sid]) groups[sid] = { supplier: p.supplier, items: [] };
    groups[sid].items.push(p);
  });

  // Sort groups: vendor name alphabetically
  const sorted = Object.values(groups).sort((a, b) =>
    (a.supplier?.name || '').localeCompare(b.supplier?.name || '')
  );

  sorted.forEach(group => renderVendorCard(container, group));
}

function sortHeaderHtml(label, col, extraClass = '') {
  const isActive = currentSortBy === col;
  let icon = '';
  if (isActive) {
    icon = currentSortDir === 'asc'
      ? '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="ms-1"><path d="M12 5l0 14"/><path d="M18 11l-6 -6"/><path d="M6 11l6 -6"/></svg>'
      : '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="ms-1"><path d="M12 5l0 14"/><path d="M18 13l-6 6"/><path d="M6 13l6 6"/></svg>';
  } else {
    icon = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ms-1 text-muted opacity-50"><path d="M8 9l4 -4 4 4"/><path d="M16 15l-4 4 -4 -4"/></svg>';
  }
  const activeStyle = isActive ? 'color:inherit;' : 'color:inherit;';
  return `<th class="${extraClass}" style="cursor:pointer;user-select:none;" onclick="sortColumn('${col}')">${label}${icon}</th>`;
}

function compareItems(a, b) {
  let aVal, bVal, cmp;
  switch (currentSortBy) {
    case 'sku':
      aVal = (a.sku || '').toLowerCase(); bVal = (b.sku || '').toLowerCase();
      cmp = aVal.localeCompare(bVal);
      return currentSortDir === 'asc' ? cmp : -cmp;
    case 'description':
      aVal = (a.description || '').toLowerCase(); bVal = (b.description || '').toLowerCase();
      cmp = aVal.localeCompare(bVal);
      return currentSortDir === 'asc' ? cmp : -cmp;
    case 'quantity_available':
      aVal = a.quantity_available ?? 0; bVal = b.quantity_available ?? 0;
      return currentSortDir === 'asc' ? aVal - bVal : bVal - aVal;
    case 'reorder_point':
      aVal = a.reorder_point ?? 0; bVal = b.reorder_point ?? 0;
      return currentSortDir === 'asc' ? aVal - bVal : bVal - aVal;
    case 'days_until_stockout':
      aVal = a.days_until_stockout ?? 9999; bVal = b.days_until_stockout ?? 9999;
      return currentSortDir === 'asc' ? aVal - bVal : bVal - aVal;
    case 'unit_cost':
      aVal = a.unit_cost ?? 0; bVal = b.unit_cost ?? 0;
      return currentSortDir === 'asc' ? aVal - bVal : bVal - aVal;
    case 'line_total':
      aVal = Math.ceil((a.suggested_order_qty || 0) / Math.max((a.pack_size || 1), 1)) * (a.unit_cost || 0);
      bVal = Math.ceil((b.suggested_order_qty || 0) / Math.max((b.pack_size || 1), 1)) * (b.unit_cost || 0);
      return currentSortDir === 'asc' ? aVal - bVal : bVal - aVal;
    default: // 'status' — urgency order, then days
      const urgencyDiff = (URGENCY_ORDER[a.status] ?? 99) - (URGENCY_ORDER[b.status] ?? 99);
      if (urgencyDiff !== 0) return urgencyDiff;
      return (a.days_until_stockout ?? 9999) - (b.days_until_stockout ?? 9999);
  }
}

function sortColumn(col) {
  if (currentSortBy === col) {
    currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
  } else {
    currentSortBy = col;
    currentSortDir = 'asc';
  }
  renderVendorSections(filteredItems);
}

function renderVendorCard(container, group) {
  const supplier = group.supplier;
  const sid = supplier.id;

  const items = [...group.items].sort(compareItems);

  const card = document.createElement('div');
  card.className = 'card mb-3';
  card.id = `vendor-card-${sid}`;

  card.innerHTML = `
    <div class="card-header">
      <div class="d-flex align-items-center gap-2">
        <input type="checkbox" class="form-check-input mt-0" id="selectAll-${sid}"
          onchange="toggleVendorAll(${sid}, this.checked)" title="Select all items for this vendor">
        <h3 class="card-title mb-0">
          <i class="ti ti-truck me-2 text-primary"></i>${escapeHtml(supplier.name)}
        </h3>
        <span class="badge bg-secondary ms-1" id="vendorCount-${sid}">${items.length} item${items.length !== 1 ? 's' : ''}</span>
      </div>
      <div class="card-options">
        <span class="text-muted small me-3">Est: <strong id="vendorTotal-${sid}">$0.00</strong></span>
        <button class="btn btn-sm btn-primary" onclick="showConfirmPO(${sid})" id="createPOBtn-${sid}" disabled>
          <i class="ti ti-shopping-cart-plus me-1"></i>Create PO
        </button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter table-sm card-table mb-0">
        <thead>
          <tr>
            <th style="width:36px"></th>
            ${sortHeaderHtml('SKU', 'sku')}
            ${sortHeaderHtml('Description', 'description')}
            ${sortHeaderHtml('Available', 'quantity_available', 'text-end')}
            ${sortHeaderHtml('Reorder Pt', 'reorder_point', 'text-end')}
            <th class="text-end">On Order</th>
            ${sortHeaderHtml('Days Left', 'days_until_stockout', 'text-end')}
            <th class="text-end" style="width:140px">Order Qty</th>
            ${sortHeaderHtml('Pack Cost', 'unit_cost', 'text-end')}
            ${sortHeaderHtml('Line Total', 'line_total', 'text-end')}
            ${sortHeaderHtml('Status', 'status', '')}
          </tr>
        </thead>
        <tbody id="vendorBody-${sid}">
          ${items.map(p => renderItemRow(p, sid)).join('')}
        </tbody>
      </table>
    </div>
  `;

  container.appendChild(card);

  // Bind quantity change listeners
  items.forEach(p => {
    const qtyInput = document.getElementById(`qty-${p.id}`);
    if (qtyInput) {
      qtyInput.addEventListener('input', () => onQtyChange(p.id, sid));
    }
    const checkbox = document.getElementById(`check-${p.id}`);
    if (checkbox) {
      checkbox.addEventListener('change', () => onCheckChange(p.id, sid));
    }
  });

  // Auto-select urgent items that have a suggested qty > 0; never pre-check in_stock items
  items.forEach(p => {
    if (p.status === 'in_stock') return;
    const packSize = (p.pack_size && p.pack_size > 1) ? p.pack_size : 1;
    const displayQty = packSize > 1
      ? Math.ceil((p.suggested_order_qty || 0) / packSize)  // ceiling: always order whole packs
      : (p.suggested_order_qty || 0);
    if (displayQty > 0) {
      const cb = document.getElementById(`check-${p.id}`);
      if (cb) { cb.checked = true; onCheckChange(p.id, sid); }
    }
  });
}

function renderItemRow(p, sid) {
  const packSize     = (p.pack_size && p.pack_size > 1) ? p.pack_size : 1;
  const isPack       = packSize > 1;
  const suggestedEaches = p.suggested_order_qty || 0;
  // suggested_order_qty is already rounded to pack boundaries server-side;
  // Math.ceil here guards against any floating-point edge cases
  const suggestedQty = isPack ? Math.ceil(suggestedEaches / packSize) : suggestedEaches;
  const available    = p.quantity_available ?? 0;
  const onOrder      = p.on_order_qty ?? 0;
  const reorderPt    = p.reorder_point ?? '-';
  const unitCost     = p.unit_cost ?? 0;  // unit_cost = pack cost for pack products
  const packLabel    = isPack ? (p.purchase_uom || 'packs') : (p.stock_uom || 'EA');

  const daysDisplay = p.days_until_stockout != null && p.days_until_stockout > 0
    ? `<span class="badge ${p.days_until_stockout <= 3 ? 'bg-danger-lt text-danger' : p.days_until_stockout <= 7 ? 'bg-warning-lt text-warning' : 'bg-secondary-lt'}">${p.days_until_stockout}d</span>`
    : '<span class="text-muted">—</span>';

  const statusBadge = statusBadgeHtml(p.status);

  const availColor = (p.status === 'critical' || p.status === 'out_of_stock') ? 'text-danger fw-bold' :
                     p.status === 'very_low' ? 'text-warning fw-bold' : 'text-info';

  const rowClass = (p.status === 'critical' || p.status === 'out_of_stock') ? 'table-danger-lt' :
                   p.status === 'very_low' ? 'table-warning-lt' : '';

  const packCostLabel = isPack
    ? `<br><small class="text-muted">/ ${packLabel} (${packSize} EA)</small>`
    : '';

  return `
    <tr id="row-${p.id}" class="${rowClass}">
      <td class="text-center">
        <input type="checkbox" class="form-check-input" id="check-${p.id}" data-product-id="${p.id}" data-supplier-id="${sid}">
      </td>
      <td>
        <span class="text-muted">${escapeHtml(p.sku || '')}</span>
      </td>
      <td>
        <span title="${escapeHtml(p.description || '')}">${escapeHtml((p.description || '').substring(0, 55))}${(p.description || '').length > 55 ? '…' : ''}</span>
      </td>
      <td class="text-end ${availColor}">${available.toLocaleString()}</td>
      <td class="text-end text-muted">${typeof reorderPt === 'number' ? reorderPt.toLocaleString() : reorderPt}</td>
      <td class="text-end text-muted">${onOrder > 0 ? `<span class="text-primary">${onOrder.toLocaleString()}</span>` : '—'}</td>
      <td class="text-end">${daysDisplay}</td>
      <td class="text-end">
        <div class="d-flex align-items-center justify-content-end gap-1">
          <input type="number" class="form-control form-control-sm text-end" id="qty-${p.id}"
            min="1" step="1" value="${suggestedQty || ''}"
            placeholder="Qty"
            data-product-id="${p.id}" data-supplier-id="${sid}" data-unit-cost="${unitCost}" data-pack-size="${packSize}"
            style="width:80px;display:inline-block;">
          <small class="text-muted text-nowrap">${escapeHtml(packLabel)}</small>
        </div>
      </td>
      <td class="text-end text-muted">${formatCurrency(unitCost)}${packCostLabel}</td>
      <td class="text-end fw-bold" id="lineTotal-${p.id}">—</td>
      <td>${statusBadge}</td>
    </tr>
  `;
}

// ─── Checkbox & Quantity Handlers ─────────────────────────────────────────────

function onCheckChange(productId, supplierId) {
  updateLineTotal(productId);
  updateVendorTotal(supplierId);
  updateVendorButtonState(supplierId);
  updateGlobalEstimate();
  syncSelectAllCheckbox(supplierId);
}

function onQtyChange(productId, supplierId) {
  const cb = document.getElementById(`check-${productId}`);
  const qty = parseFloat(document.getElementById(`qty-${productId}`).value) || 0;

  // Auto-check if user typed a qty, auto-uncheck if cleared
  if (qty > 0 && cb) cb.checked = true;
  if (qty <= 0 && cb) cb.checked = false;

  updateLineTotal(productId);
  updateVendorTotal(supplierId);
  updateVendorButtonState(supplierId);
  updateGlobalEstimate();
  syncSelectAllCheckbox(supplierId);
}

function updateLineTotal(productId) {
  const cb    = document.getElementById(`check-${productId}`);
  const qtyEl = document.getElementById(`qty-${productId}`);
  const totEl = document.getElementById(`lineTotal-${productId}`);
  if (!cb || !qtyEl || !totEl) return;

  const unitCost = parseFloat(qtyEl.dataset.unitCost) || 0;
  const qty = parseFloat(qtyEl.value) || 0;

  if (cb.checked && qty > 0) {
    totEl.textContent = formatCurrency(qty * unitCost);
    totEl.classList.remove('text-muted');
  } else {
    totEl.textContent = '—';
    totEl.classList.add('text-muted');
  }
}

function updateVendorTotal(supplierId) {
  let total = 0;
  document.querySelectorAll(`[data-supplier-id="${supplierId}"][data-product-id]`).forEach(el => {
    if (el.tagName === 'INPUT' && el.type === 'number') {
      const cb = document.getElementById(`check-${el.dataset.productId}`);
      if (cb && cb.checked) {
        const qty = parseFloat(el.value) || 0;
        const cost = parseFloat(el.dataset.unitCost) || 0;
        total += qty * cost;
      }
    }
  });
  const el = document.getElementById(`vendorTotal-${supplierId}`);
  if (el) el.textContent = formatCurrency(total);
}

function updateVendorButtonState(supplierId) {
  const hasChecked = Array.from(
    document.querySelectorAll(`[data-supplier-id="${supplierId}"][data-product-id][type="checkbox"]`)
  ).some(cb => cb.checked && parseFloat(document.getElementById(`qty-${cb.dataset.productId}`)?.value || 0) > 0);

  const btn = document.getElementById(`createPOBtn-${supplierId}`);
  if (btn) btn.disabled = !hasChecked;
}

function toggleVendorAll(supplierId, checked) {
  document.querySelectorAll(`[data-supplier-id="${supplierId}"][type="checkbox"]`).forEach(cb => {
    if (cb.id !== `selectAll-${supplierId}`) {
      cb.checked = checked;
      onCheckChange(cb.dataset.productId, supplierId);
    }
  });
}

function syncSelectAllCheckbox(supplierId) {
  const allBoxes = Array.from(
    document.querySelectorAll(`[data-supplier-id="${supplierId}"][type="checkbox"]:not([id^="selectAll"])`)
  );
  const allChecked = allBoxes.length > 0 && allBoxes.every(cb => cb.checked);
  const selectAll = document.getElementById(`selectAll-${supplierId}`);
  if (selectAll) selectAll.checked = allChecked;
}

function updateGlobalEstimate() {
  let total = 0;
  document.querySelectorAll('[data-product-id][type="checkbox"]').forEach(cb => {
    if (cb.id.startsWith('selectAll')) return;
    if (cb.checked) {
      const qtyEl = document.getElementById(`qty-${cb.dataset.productId}`);
      if (qtyEl) {
        const qty = parseFloat(qtyEl.value) || 0;
        const cost = parseFloat(qtyEl.dataset.unitCost) || 0;
        total += qty * cost;
      }
    }
  });
  document.getElementById('statEstTotal').textContent = formatCurrency(total);

  // Enable/disable "Create All POs" button
  const anyChecked = Array.from(
    document.querySelectorAll('[data-product-id][type="checkbox"]:not([id^="selectAll"])')
  ).some(cb => cb.checked);
  document.getElementById('createAllPOsBtn').disabled = !anyChecked;
}

// ─── PO Creation ──────────────────────────────────────────────────────────────

function getVendorLineItems(supplierId) {
  const items = [];
  document.querySelectorAll(`[data-supplier-id="${supplierId}"][type="checkbox"]:not([id^="selectAll"])`).forEach(cb => {
    if (!cb.checked) return;
    const productId = cb.dataset.productId;
    const qtyEl = document.getElementById(`qty-${productId}`);
    const qty = parseFloat(qtyEl?.value || 0);
    if (qty <= 0) return;

    // Find product data
    const product = filteredItems.find(p => String(p.id) === String(productId))
                  || allItems.find(p => String(p.id) === String(productId));
    if (!product) return;

    items.push({
      product_id: parseInt(productId),
      quantity: qty,
      unit_cost: parseFloat(qtyEl.dataset.unitCost) || 0,
      product: product,
    });
  });
  return items;
}

function showConfirmPO(supplierId) {
  pendingPOSupplier = supplierId;
  const supplier = (filteredItems.find(p => p.supplier_id === supplierId) || allItems.find(p => p.supplier_id === supplierId))?.supplier;
  const items = getVendorLineItems(supplierId);

  if (items.length === 0) {
    showNotification('No items selected for this vendor', 'warning');
    return;
  }

  document.getElementById('confirmVendorName').textContent = supplier?.name || `Vendor #${supplierId}`;
  document.getElementById('confirmPONumber').value = '';
  document.getElementById('confirmOrderDate').value = todayDate();
  document.getElementById('confirmExpectedDate').value = '';
  document.getElementById('confirmNotes').value = '';

  let total = 0;
  document.getElementById('confirmLineItems').innerHTML = items.map(item => {
    const lineTotal = item.quantity * item.unit_cost;
    total += lineTotal;
    return `
      <tr>
        <td><span class="text-muted">${escapeHtml(item.product.sku || '')}</span></td>
        <td>${escapeHtml(item.product.description || '')}</td>
        <td class="text-end">${item.quantity.toLocaleString()}</td>
        <td class="text-end">${formatCurrency(item.unit_cost)}</td>
        <td class="text-end fw-bold">${formatCurrency(lineTotal)}</td>
        <td>${statusBadgeHtml(item.product.status)}</td>
      </tr>
    `;
  }).join('');

  document.getElementById('confirmTotal').textContent = formatCurrency(total);

  safeShowModal('confirmPOModal');
}

async function submitPO() {
  const btn = document.getElementById('confirmCreateBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

  try {
    const items = getVendorLineItems(pendingPOSupplier);
    if (items.length === 0) {
      showNotification('No items to order', 'warning');
      return;
    }

    const poNumber = document.getElementById('confirmPONumber').value.trim();
    if (!poNumber) {
      showNotification('Please enter a PO number before creating the order', 'warning');
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-check me-1"></i>Create Purchase Order';
      document.getElementById('confirmPONumber').focus();
      return;
    }

    const payload = {
      po_number: poNumber,
      supplier_id: pendingPOSupplier,
      order_date: document.getElementById('confirmOrderDate').value,
      expected_date: document.getElementById('confirmExpectedDate').value || null,
      notes: document.getElementById('confirmNotes').value || null,
      items: items.map(i => ({
        product_id: i.product_id,
        quantity: i.quantity,
        unit_cost: i.unit_cost,
      })),
    };

    const result = await authenticatedFetch('/purchase-orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    safeHideModal('confirmPOModal');

    const createdPONumber = result?.po_number || result?.data?.po_number || '';
    showNotification(`Purchase order${createdPONumber ? ' ' + createdPONumber : ''} created successfully`, 'success');

    // Reload to reflect updated stock status
    await loadReplenishmentItems();
  } catch (err) {
    console.error('Error creating PO:', err);
    showNotification(err.message || 'Failed to create purchase order', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="ti ti-check me-1"></i>Create Purchase Order';
  }
}

async function createAllPendingPOs() {
  // Collect all unique supplier IDs that have checked items
  const supplierIds = new Set();
  document.querySelectorAll('[data-product-id][type="checkbox"]:not([id^="selectAll"])').forEach(cb => {
    if (cb.checked) supplierIds.add(parseInt(cb.dataset.supplierId));
  });

  if (supplierIds.size === 0) {
    showNotification('No items selected', 'warning');
    return;
  }

  if (!confirm(`Create ${supplierIds.size} purchase order${supplierIds.size > 1 ? 's' : ''} (one per vendor)?`)) return;

  const btn = document.getElementById('createAllPOsBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

  let created = 0;
  let failed = 0;

  for (const sid of supplierIds) {
    try {
      const items = getVendorLineItems(sid);
      if (items.length === 0) continue;

      await authenticatedFetch('/purchase-orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          supplier_id: sid,
          order_date: todayDate(),
          items: items.map(i => ({
            product_id: i.product_id,
            quantity: i.quantity,
            unit_cost: i.unit_cost,
          })),
        }),
      });
      created++;
    } catch (err) {
      console.error(`Failed to create PO for supplier ${sid}:`, err);
      failed++;
    }
  }

  if (failed === 0) {
    showNotification(`${created} purchase order${created !== 1 ? 's' : ''} created successfully`, 'success');
  } else {
    showNotification(`${created} created, ${failed} failed. Check console for details.`, 'warning');
  }

  btn.innerHTML = '<i class="ti ti-shopping-cart-plus me-1"></i>Create All POs';

  await loadReplenishmentItems();
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text ?? '';
  return div.innerHTML;
}

function formatCurrency(value) {
  if (typeof formatPrice === 'function') return formatPrice(value);
  return '$' + parseFloat(value || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function statusBadgeHtml(status) {
  const map = {
    critical:  ['bg-danger',  'Critical'],
    very_low:  ['bg-warning', 'Very Low'],
    low:       ['bg-info',    'Low'],
    out_of_stock: ['bg-dark', 'Out of Stock'],
    in_stock:  ['bg-success', 'In Stock'],
  };
  const [cls, label] = map[status] || ['bg-secondary', status];
  return `<span class="badge ${cls}">${label}</span>`;
}

function showLoading(show) {
  document.getElementById('loadingState').style.display = show ? 'block' : 'none';
}

function safeShowModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
    new window.bootstrap.Modal(el).show();
  } else {
    const bd = document.createElement('div');
    bd.className = 'modal-backdrop fade show';
    bd.id = 'bd-' + id;
    document.body.appendChild(bd);
    el.style.display = 'block';
    el.classList.add('show');
    document.body.classList.add('modal-open');
    el.querySelectorAll('[data-bs-dismiss="modal"]').forEach(b => { b.onclick = () => safeHideModal(id); });
    bd.onclick = () => safeHideModal(id);
  }
}

function safeHideModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
    const m = window.bootstrap.Modal.getInstance(el);
    if (m) { m.hide(); return; }
  }
  el.style.display = 'none';
  el.classList.remove('show');
  document.body.classList.remove('modal-open');
  const bd = document.getElementById('bd-' + id);
  if (bd) bd.remove();
}
</script>
@endpush
