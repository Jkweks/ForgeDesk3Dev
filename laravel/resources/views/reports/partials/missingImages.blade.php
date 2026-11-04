<!-- Parts Without an Image (ForgeDesk system report) -->
<div class="col-12" id="missingImagesReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-photo-off me-2"></i>Parts Without an Image</h3>
      <div class="ms-auto d-flex gap-2">
        <input type="text" class="form-control form-control-sm" id="missingImagesSearch" placeholder="Search SKU, part # or description..." style="max-width: 260px;">
        <button class="btn btn-sm btn-primary" onclick="missingImagesDownload()">
          <i class="ti ti-download me-1"></i>CSV
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-4"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Without an Image</div><div class="h2 mb-0 text-warning" id="missingImagesCount">-</div>
        </div></div></div>
        <div class="col-md-4"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Active Parts</div><div class="h2 mb-0" id="missingImagesTotal">-</div>
        </div></div></div>
        <div class="col-md-4"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Share Missing</div><div class="h2 mb-0" id="missingImagesPct">-</div>
        </div></div></div>
      </div>

      <div id="missingImagesLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Loading report...</div>
      </div>

      <div id="missingImagesContent" style="display: none;">
        <div class="table-responsive">
          <table class="table table-vcenter">
            <thead>
              <tr>
                <th class="sortable-report" data-sort="sku" data-report="missingImages" style="cursor: pointer;">SKU <span class="sort-icon"></span></th>
                <th class="sortable-report" data-sort="part_number" data-report="missingImages" style="cursor: pointer;">Part # <span class="sort-icon"></span></th>
                <th>Finish</th>
                <th class="sortable-report" data-sort="description" data-report="missingImages" style="cursor: pointer;">Description <span class="sort-icon"></span></th>
                <th class="sortable-report" data-sort="category" data-report="missingImages" style="cursor: pointer;">Category <span class="sort-icon"></span></th>
                <th class="text-end sortable-report" data-sort="on_hand" data-report="missingImages" style="cursor: pointer;">On Hand <span class="sort-icon"></span></th>
                <th></th>
              </tr>
            </thead>
            <tbody id="missingImagesBody"></tbody>
          </table>
        </div>
        <div class="card-footer d-flex align-items-center" id="missingImagesPagination" style="display: none;"></div>
      </div>
    </div>
  </div>
</div>

<script>
window.ReportModules = window.ReportModules || {};

let missingImagesSearchTerm = '';

async function loadMissingImagesReport() {
  try {
    document.getElementById('missingImagesLoading').style.display = 'block';
    document.getElementById('missingImagesContent').style.display = 'none';
    const r = await authenticatedFetch('/reports/system/missing-images');
    document.getElementById('missingImagesCount').textContent = r.summary.missing;
    document.getElementById('missingImagesTotal').textContent = r.summary.total_active;
    document.getElementById('missingImagesPct').textContent = r.summary.percent_missing + '%';
    reportPaginationState.missingImages = r.products;
    renderMissingImagesTable(1);
    document.getElementById('missingImagesLoading').style.display = 'none';
    document.getElementById('missingImagesContent').style.display = 'block';
  } catch (e) {
    console.error('Error loading missing images report:', e);
    showNotification('Error loading the report', 'danger');
  }
}

function renderMissingImagesTable(page = 1) {
  const term = missingImagesSearchTerm.toLowerCase();
  let data = getProcessedReportData('missingImages');
  if (term) data = data.filter(p => [p.sku, p.part_number, p.description, p.category].some(v => (v || '').toLowerCase().includes(term)));
  const pagination = paginateData(data, page);
  const tbody = document.getElementById('missingImagesBody');
  updateReportSortIcons('missingImages');
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Every active part has an image.</td></tr>';
    document.getElementById('missingImagesPagination').style.display = 'none';
    return;
  }
  tbody.innerHTML = pagination.data.map(p => `
    <tr>
      <td><strong>${escapeHtml(p.sku)}</strong></td>
      <td>${escapeHtml(p.part_number || '-')}</td>
      <td>${escapeHtml(p.finish || '-')}</td>
      <td>${escapeHtml(p.description || '')}</td>
      <td>${escapeHtml(p.category || '-')}</td>
      <td class="text-end">${p.on_hand}</td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/inventory/products?q=${encodeURIComponent(p.sku)}">Open</a></td>
    </tr>`).join('');
  renderReportPagination('missingImagesPagination', pagination, renderMissingImagesTable);
}

async function missingImagesDownload() {
  try {
    const res = await fetch('/api/v1/reports/export?type=missing_images', { credentials: 'include', headers: { 'Accept': 'text/csv' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const url = URL.createObjectURL(await res.blob());
    const a = document.createElement('a'); a.href = url; a.download = 'parts_without_image.csv'; a.click();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  } catch (e) { showNotification('Could not export the report', 'danger'); }
}

document.getElementById('missingImagesSearch').addEventListener('input', e => {
  missingImagesSearchTerm = e.target.value.trim();
  renderMissingImagesTable(1);
});

window.ReportModules.missingImages = { load: () => loadMissingImagesReport() };
</script>
