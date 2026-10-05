<!-- Work Order Material Usage Report -->
<div class="col-12" id="materialUsageReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-ruler-measure me-2"></i>Material Usage by Work Order</h3>
      <div class="ms-auto d-flex gap-2">
        <input type="text" class="form-control form-control-sm" id="materialUsageSearch" placeholder="Job number or name" style="max-width: 220px;" onkeydown="if (event.key === 'Enter') loadMaterialUsageReport()">
        <button class="btn btn-sm btn-outline-secondary" onclick="loadMaterialUsageReport()">
          <i class="ti ti-refresh me-1"></i>Apply
        </button>
      </div>
    </div>
    <div class="card-body">
      <p class="text-muted small">Stock lengths cut at the cut station, from the cut log. Work orders tagged SOF also drew inventory; all others (In Shop, dated, untagged) are job-specific material and did not.</p>
      <div id="materialUsageLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Loading report...</div>
      </div>
      <div id="materialUsageContent" style="display: none;"></div>
    </div>
  </div>
</div>

<script>
window.ReportModules = window.ReportModules || {};

// Work Order Material Usage Report
async function loadMaterialUsageReport() {
  try {
    document.getElementById('materialUsageLoading').style.display = 'block';
    document.getElementById('materialUsageContent').style.display = 'none';

    const q = document.getElementById('materialUsageSearch').value.trim();
    const response = await authenticatedFetch(`/reports/work-order-material-usage${q ? `?q=${encodeURIComponent(q)}` : ''}`);

    const lengths = n => Number(n).toLocaleString(undefined, { maximumFractionDigits: 2 });
    const productRows = (rows, cols) => rows.map(p => `
      <tr>
        <td>${escapeHtml(p.sku || '')}</td>
        <td>${escapeHtml(p.name || '')}</td>
        <td>${escapeHtml(p.finish || '')}</td>
        <td class="text-end">${lengths(p.stock_lengths)}</td>
        <td class="text-end">${p.cuts.toLocaleString()}</td>
      </tr>`).join('');
    const table = rows => `
      <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table">
          <thead><tr><th>SKU</th><th>Description</th><th>Finish</th><th class="text-end">Stock Lengths</th><th class="text-end">Cuts</th></tr></thead>
          <tbody>${productRows(rows)}</tbody>
        </table>
      </div>`;

    document.getElementById('materialUsageContent').innerHTML = response.jobs.length === 0
      ? '<div class="text-center text-muted py-4">No cuts recorded for work orders' + (q ? ' matching that job.' : ' yet.') + '</div>'
      : response.jobs.map(job => `
        <div class="mb-5">
          <h3>${escapeHtml(job.job_number || '')} ${escapeHtml(job.job_name || '')}
            <span class="badge bg-azure-lt ms-2">${lengths(job.total_stock_lengths)} stock lengths</span></h3>
          <h4 class="mt-3">Job total</h4>
          ${table(job.summary)}
          ${job.work_orders.map(wo => `
            <h4 class="mt-4">Release ${escapeHtml(String(wo.release_number ?? '-'))}
              <span class="badge ${wo.drew_inventory ? 'bg-green-lt' : 'bg-secondary-lt'} ms-2">${escapeHtml(wo.material_delivery || 'No material tag')}</span>
              <small class="text-muted ms-2">${wo.drew_inventory ? 'drew inventory' : 'job-specific material, inventory not touched'} · ${lengths(wo.total_stock_lengths)} stock lengths</small>
            </h4>
            ${table(wo.products)}`).join('')}
        </div>`).join('');

    document.getElementById('materialUsageLoading').style.display = 'none';
    document.getElementById('materialUsageContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading material usage report:', error);
    showNotification('Error loading material usage report', 'danger');
  }
}

window.ReportModules.materialUsage = { load: loadMaterialUsageReport };
</script>
