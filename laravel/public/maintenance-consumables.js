// Maintenance Consumables — shop consumables (pneumatic fittings, clamp pads,
// dust collector bags/filter bags, etc) tagged into the "Maintenance
// Consumables" Category and surfaced only on this page. These are ordinary
// inventory Products; quantity, storage location and cycle counting all work
// exactly as they do everywhere else — see MaintenanceController::consumables().
// Requires: authenticatedFetch, showNotification, escapeHtml, viewProduct
// (from auth-scripts.blade.php, tooling.js and partials/product-modal.blade.php).

let maintenanceConsumables = [];
let consumablesCategoryId = null;
let consumablesSuppliers = [];
let consumablesSearchTimeout = null;

async function loadConsumables() {
  try {
    const search = document.getElementById('consumablesSearch')?.value;
    const params = new URLSearchParams();
    if (search) params.append('search', search);

    const url = '/maintenance/consumables' + (params.toString() ? '?' + params.toString() : '');
    const data = await authenticatedFetch(url);
    maintenanceConsumables = data.consumables || [];
    consumablesCategoryId = data.category_id;

    renderConsumables();
  } catch (error) {
    console.error('Failed to load maintenance consumables:', error);
  }
}

function debounceConsumablesSearch() {
  clearTimeout(consumablesSearchTimeout);
  consumablesSearchTimeout = setTimeout(loadConsumables, 500);
}

function renderConsumables() {
  const tbody = document.getElementById('consumablesTable');
  if (!tbody) return;

  if (!maintenanceConsumables || maintenanceConsumables.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No maintenance consumables yet</td></tr>';
    return;
  }

  tbody.innerHTML = maintenanceConsumables.map(p => {
    const locations = p.inventory_locations || [];
    const locationDisplay = locations.length
      ? locations.map(l => escapeHtml(l.storage_location?.name || l.storage_location?.code || 'Bin')).join(', ')
      : '<span class="text-warning" title="Open this item and add a location under its Locations tab so cycle counts include it"><i class="ti ti-alert-triangle me-1"></i>Not assigned</span>';

    const qty = p.quantity_on_hand ?? 0;
    const low = p.reorder_point != null && qty <= p.reorder_point;

    return `<tr style="cursor:pointer" onclick="viewProduct(${p.id})">
      <td>
        <div class="fw-bold">${escapeHtml(p.description || '')}</div>
        <div class="text-muted small">${escapeHtml(p.sku || p.part_number || '')}</div>
      </td>
      <td class="${low ? 'text-danger fw-bold' : ''}">${qty}</td>
      <td>${p.reorder_point ?? '-'}</td>
      <td>${locationDisplay}</td>
      <td>${escapeHtml(p.supplier?.name || '-')}</td>
      <td>${escapeHtml(p.manufacturer || '-')}</td>
    </tr>`;
  }).join('');
}

async function loadSuppliersForConsumables() {
  try {
    const response = await authenticatedFetch('/suppliers?per_page=all');
    consumablesSuppliers = Array.isArray(response) ? response
      : Array.isArray(response?.data) ? response.data
      : Array.isArray(response?.data?.data) ? response.data.data
      : [];

    const select = document.getElementById('newConsumableSupplier');
    select.innerHTML = '<option value="">No Supplier</option>' +
      consumablesSuppliers.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
  } catch (error) {
    console.error('Failed to load suppliers:', error);
  }
}

async function openAddConsumableModal() {
  const form = document.getElementById('addConsumableForm');
  form.reset();

  if (consumablesSuppliers.length === 0) {
    await loadSuppliersForConsumables();
  }
  if (consumablesCategoryId === null) {
    await loadConsumables();
  }
}

document.getElementById('addConsumableForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();

  const data = {
    part_number: document.getElementById('newConsumablePartNumber').value,
    sku: document.getElementById('newConsumableSKU').value || null,
    description: document.getElementById('newConsumableDescription').value,
    category_ids: consumablesCategoryId ? [consumablesCategoryId] : [],
    primary_category_id: consumablesCategoryId || null,

    quantity_on_hand: parseInt(document.getElementById('newConsumableQuantity').value) || 0,
    unit_cost: parseFloat(document.getElementById('newConsumableUnitCost').value) || 0,
    minimum_quantity: parseInt(document.getElementById('newConsumableMinQuantity').value) || 0,
    reorder_point: parseInt(document.getElementById('newConsumableReorderPoint').value) || 0,
    supplier_id: document.getElementById('newConsumableSupplier').value || null,
    supplier_sku: document.getElementById('newConsumableSupplierSKU').value || null,
    manufacturer: document.getElementById('newConsumableManufacturer').value || null,
    long_description: document.getElementById('newConsumableNotes').value || null,

    is_active: true,
    unit_of_measure: 'EA',
  };

  const submitBtn = document.querySelector('#addConsumableForm button[type="submit"]');
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  try {
    const response = await authenticatedFetch('/products', {
      method: 'POST',
      body: JSON.stringify(data),
    });

    hideModal(document.getElementById('addConsumableModal'));
    showNotification(`Consumable created successfully! SKU: ${response.sku}`, 'success');
    await loadConsumables();
  } catch (error) {
    console.error('Failed to create consumable:', error);
    showNotification('Failed to create consumable.', 'danger');
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = 'Create Consumable';
  }
});

document.addEventListener('DOMContentLoaded', function () {
  const consumablesTab = document.querySelector('a[href="#tab-consumables"]');
  if (consumablesTab) {
    consumablesTab.addEventListener('shown.bs.tab', async function () {
      await loadConsumables();
    });
  }
});
