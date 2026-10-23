@once
  @include('reports.partials._cutStationUi')
@endonce

<!-- Cut Station Stock & Waste Report -->
<div class="col-12" id="cutStationWasteReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-ruler-measure-2 me-2"></i>Stock &amp; Waste</h3>
      <div class="ms-auto d-flex gap-2">
        <input type="date" class="form-control form-control-sm" id="csWasteStart" style="max-width: 150px;">
        <input type="date" class="form-control form-control-sm" id="csWasteEnd" style="max-width: 150px;">
        <button class="btn btn-sm btn-outline-secondary" onclick="loadCutStationWasteReport()">
          <i class="ti ti-refresh me-1"></i>Apply
        </button>
        <button class="btn btn-sm btn-primary" onclick="CutStationUi.csv('cut_station_waste', 'csWaste')" data-permission="reports.export">
          <i class="ti ti-download me-1"></i>CSV
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="text-muted small mb-3">
        Completed sticks. Leftover is what remained after the planned pieces; rack drops are leftovers kept and tagged, scrap is too short to keep.
      </div>
      <div class="row mb-3">
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Sticks Cut</div><div class="h2 mb-0" id="csWasteSticks">-</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Leftover</div><div class="h2 mb-0 text-info" id="csWasteLeftover">-</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Rack Drops Tagged</div><div class="h2 mb-0 text-success" id="csWasteRack">-</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Scrap</div><div class="h2 mb-0 text-danger" id="csWasteScrap">-</div>
        </div></div></div>
      </div>

      <div id="csWasteLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Loading report...</div>
      </div>

      <div id="csWasteContent" style="display: none;">
        <h4>By Profile</h4>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Profile</th><th>Finish</th>
                <th class="text-end">Sticks</th>
                <th class="text-end">Stock (in)</th>
                <th class="text-end">Leftover (in)</th>
                <th class="text-end">Leftover %</th>
                <th class="text-end">Rack Drops</th>
                <th class="text-end">Scrap Pieces</th>
                <th class="text-end">Scrap (in)</th>
              </tr>
            </thead>
            <tbody id="csWasteBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.ReportModules = window.ReportModules || {};

async function loadCutStationWasteReport() {
  const ui = window.CutStationUi;
  try {
    ui.show('csWaste', false);
    const r = await authenticatedFetch(`/reports/cut-station/waste${ui.query('csWaste')}`);
    ui.syncDates('csWaste', r.summary);

    document.getElementById('csWasteSticks').textContent = ui.num(r.summary.sticks);
    document.getElementById('csWasteLeftover').textContent = `${ui.num(r.summary.leftover_inches, 1)} in (${ui.pct(r.summary.leftover_percent)})`;
    document.getElementById('csWasteRack').textContent = ui.num(r.summary.rack_drops);
    document.getElementById('csWasteScrap').textContent = `${ui.num(r.summary.scrap_inches, 1)} in (${ui.pct(r.summary.scrap_percent)})`;

    ui.fill('csWasteBody', r.by_profile, 9, p => `
      <tr>
        <td>${escapeHtml(p.part_name)}</td>
        <td>${escapeHtml(p.finish || '')}</td>
        <td class="text-end">${ui.num(p.sticks)}</td>
        <td class="text-end">${ui.num(p.stock_inches, 1)}</td>
        <td class="text-end">${ui.num(p.leftover_inches, 1)}</td>
        <td class="text-end">${ui.pct(p.leftover_percent)}</td>
        <td class="text-end">${ui.num(p.rack_drops)}</td>
        <td class="text-end">${ui.num(p.scrap_pieces)}</td>
        <td class="text-end">${ui.num(p.scrap_inches, 1)}</td>
      </tr>`);

    ui.show('csWaste', true);
  } catch (error) {
    console.error('Error loading stock and waste report:', error);
    showNotification('Error loading stock and waste report', 'danger');
  }
}

window.ReportModules.cutStationWaste = { load: loadCutStationWasteReport };
</script>
