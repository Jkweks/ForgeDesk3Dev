<!-- Storage Location Contents Report -->
<div class="col-12" id="storageLocationReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-building-warehouse me-2"></i>Storage Location Contents</h3>
      <div class="ms-auto d-flex gap-2">
        <input type="text" class="form-control form-control-sm" id="storageLocationSearch" placeholder="Search location or SKU..." style="max-width: 240px;">
        <button class="btn btn-sm btn-outline-primary" onclick="exportStorageLocationPdf()">
          <i class="ti ti-file-type-pdf me-1"></i>PDF
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Total Locations</div>
              <div class="h2 mb-0" id="storageLocationCount">-</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Total Line Items</div>
              <div class="h2 mb-0 text-info" id="storageLocationLineItems">-</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Total Quantity</div>
              <div class="h2 mb-0 text-success" id="storageLocationTotalQty">-</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="text-muted">Unassigned Parts</div>
              <div class="h2 mb-0" id="storageLocationUnassignedCount">-</div>
            </div>
          </div>
        </div>
      </div>

      <div id="storageLocationLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Loading report...</div>
      </div>

      <div id="storageLocationContent" style="display: none;">
        <div class="accordion" id="storageLocationAccordion"></div>

        <div id="storageLocationUnassigned" style="display:none; margin-top:20px;">
          <div class="card border-warning">
            <div class="card-header bg-warning-lt">
              <h4 class="card-title text-warning mb-0">
                <i class="ti ti-alert-triangle me-2"></i>Parts With No Storage Location
              </h4>
              <span class="ms-auto badge bg-warning text-white" id="storageLocationUnassignedBadge"></span>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-vcenter mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Part #</th>
                    <th>Description</th>
                    <th>SKU</th>
                    <th class="text-end">On Hand</th>
                    <th class="text-end">Committed</th>
                    <th class="text-center">UOM</th>
                  </tr>
                </thead>
                <tbody id="storageLocationUnassignedBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.ReportModules = window.ReportModules || {};

// Storage Location Report
let storageLocationData = [];

async function loadStorageLocationReport() {
  try {
    document.getElementById('storageLocationLoading').style.display = 'block';
    document.getElementById('storageLocationContent').style.display = 'none';

    const response = await authenticatedFetch('/reports/storage-locations');

    document.getElementById('storageLocationCount').textContent = response.summary.total_locations.toLocaleString();
    document.getElementById('storageLocationLineItems').textContent = response.summary.total_line_items.toLocaleString();
    document.getElementById('storageLocationTotalQty').textContent = response.summary.total_qty.toLocaleString();

    const unassignedCount = response.summary.unassigned_count || 0;
    const unassignedEl = document.getElementById('storageLocationUnassignedCount');
    unassignedEl.textContent = unassignedCount.toLocaleString();
    unassignedEl.className = 'h2 mb-0 ' + (unassignedCount > 0 ? 'text-danger' : 'text-success');

    storageLocationData = response.locations;
    renderStorageLocationAccordion(storageLocationData);
    renderStorageLocationUnassigned(response.unassigned_products || []);

    document.getElementById('storageLocationLoading').style.display = 'none';
    document.getElementById('storageLocationContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading storage location report:', error);
    showNotification('Error loading storage location report', 'danger');
  }
}

function renderStorageLocationAccordion(locations) {
  const container = document.getElementById('storageLocationAccordion');

  if (locations.length === 0) {
    container.innerHTML = '<div class="text-center text-muted py-5">No storage locations with inventory found.</div>';
    return;
  }

  container.innerHTML = locations.map((loc, idx) => {
    const collapseId = `slCollapse${loc.id}`;
    const headingId  = `slHeading${loc.id}`;
    const meta = [loc.code, loc.full_path !== loc.name ? loc.full_path : null]
      .filter(Boolean).join(' · ');
    const address = [
      loc.aisle    ? 'Aisle ' + loc.aisle    : null,
      loc.bay      ? 'Bay '   + loc.bay      : null,
      loc.level    ? 'Level ' + loc.level    : null,
      loc.position ? 'Pos '   + loc.position : null,
    ].filter(Boolean).join(' ');

    const rows = loc.items.length > 0
      ? loc.items.map(item => `
          <tr>
            <td class="text-muted" style="width:12%">${escapeHtml(item.part_number || '—')}</td>
            <td>${escapeHtml(item.description)}</td>
            <td style="width:12%">${escapeHtml(item.sku)}</td>
            <td class="text-end" style="width:8%">${item.quantity.toLocaleString()}</td>
            <td class="text-center" style="width:6%">${escapeHtml(item.uom)}</td>
            <td class="text-center" style="width:8%">${item.is_primary ? '<span class="badge bg-blue-lt">Primary</span>' : ''}</td>
          </tr>`).join('')
      : `<tr><td colspan="6" class="text-center text-muted py-3">No inventory at this location</td></tr>`;

    const totalRow = loc.items.length > 0
      ? `<tfoot class="table-secondary">
           <tr>
             <td colspan="3" class="text-end fw-bold">Location Total</td>
             <td class="text-end fw-bold">${loc.total_qty.toLocaleString()}</td>
             <td colspan="2"></td>
           </tr>
         </tfoot>`
      : '';

    return `
      <div class="accordion-item">
        <h2 class="accordion-header" id="${headingId}">
          <button class="accordion-button ${idx === 0 ? '' : 'collapsed'}" type="button"
                  data-bs-toggle="collapse" data-bs-target="#${collapseId}"
                  aria-expanded="${idx === 0}" aria-controls="${collapseId}">
            <span class="fw-bold me-2">${escapeHtml(loc.name)}</span>
            ${meta ? `<span class="text-muted small me-2">${escapeHtml(meta)}</span>` : ''}
            ${address ? `<span class="text-muted small me-2">— ${escapeHtml(address)}</span>` : ''}
            <span class="ms-auto me-3 badge bg-secondary-lt text-secondary">${loc.item_count} item${loc.item_count !== 1 ? 's' : ''}</span>
            <span class="badge bg-teal-lt text-teal me-2">Qty: ${loc.total_qty.toLocaleString()}</span>
            <span class="badge bg-azure-lt text-azure">ID: ${loc.id}</span>
          </button>
        </h2>
        <div id="${collapseId}" class="accordion-collapse collapse ${idx === 0 ? 'show' : ''}"
             aria-labelledby="${headingId}" data-bs-parent="">
          <div class="accordion-body p-0">
            <table class="table table-sm table-vcenter mb-0">
              <thead class="table-light">
                <tr>
                  <th>Part #</th><th>Description</th><th>SKU</th>
                  <th class="text-end">Quantity</th><th class="text-center">UOM</th><th class="text-center">Primary</th>
                </tr>
              </thead>
              <tbody>${rows}</tbody>
              ${totalRow}
            </table>
          </div>
        </div>
      </div>`;
  }).join('');
}

function filterStorageLocationReport(searchTerm) {
  if (!searchTerm) {
    renderStorageLocationAccordion(storageLocationData);
    return;
  }
  const term = searchTerm.toLowerCase();
  const filtered = storageLocationData
    .map(loc => {
      const nameMatch = loc.name.toLowerCase().includes(term) ||
                        (loc.code || '').toLowerCase().includes(term);
      const matchedItems = loc.items.filter(item =>
        item.sku.toLowerCase().includes(term) ||
        item.description.toLowerCase().includes(term) ||
        (item.part_number || '').toLowerCase().includes(term)
      );
      if (!nameMatch && matchedItems.length === 0) return null;
      return { ...loc, items: nameMatch ? loc.items : matchedItems,
               item_count: nameMatch ? loc.item_count : matchedItems.length,
               total_qty: nameMatch ? loc.total_qty : matchedItems.reduce((s, i) => s + i.quantity, 0) };
    })
    .filter(Boolean);
  renderStorageLocationAccordion(filtered);
}

function renderStorageLocationUnassigned(products) {
  const section = document.getElementById('storageLocationUnassigned');
  const badge   = document.getElementById('storageLocationUnassignedBadge');
  const tbody   = document.getElementById('storageLocationUnassignedBody');

  if (!products || products.length === 0) {
    section.style.display = 'none';
    return;
  }

  badge.textContent = `${products.length} part${products.length !== 1 ? 's' : ''}`;
  tbody.innerHTML = products.map(p => `
    <tr>
      <td class="text-muted">${escapeHtml(p.part_number || '—')}</td>
      <td>${escapeHtml(p.description || '')}</td>
      <td><code>${escapeHtml(p.sku)}</code></td>
      <td class="text-end">${Math.floor(Number(p.quantity_on_hand) || 0).toLocaleString()}</td>
      <td class="text-end">${(p.quantity_committed || 0).toLocaleString()}</td>
      <td class="text-center">${escapeHtml(p.uom || '')}</td>
    </tr>`).join('');

  section.style.display = '';
}

async function exportStorageLocationPdf() {
  try {
    showNotification('Generating PDF report...', 'info');
    const response = await fetch(`${API_BASE}/reports/storage-locations/pdf`, {
      headers: { 'Accept': 'application/pdf' }
    });
    if (!response.ok) throw new Error('Failed to generate PDF');
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `storage-location-report-${new Date().toISOString().split('T')[0]}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    showNotification('PDF downloaded successfully', 'success');
  } catch (error) {
    console.error('Error exporting storage location PDF:', error);
    showNotification('Error generating PDF report', 'danger');
  }
}

window.ReportModules.storageLocation = { load: () => loadStorageLocationReport() };
</script>
