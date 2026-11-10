@once
  @include('reports.partials._cutStationUi')
@endonce

<!-- Cut List Progress Report -->
<div class="col-12" id="cutListProgressReport" style="display: none;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-chart-bar me-2"></i>Cut List Progress</h3>
      <div class="ms-auto d-flex gap-2">
        <select multiple class="form-select form-select-sm" id="clpJobs" size="1" style="min-width: 280px;" data-sort></select>
        <button class="btn btn-sm btn-outline-secondary" onclick="loadCutListProgressReport()">
          <i class="ti ti-refresh me-1"></i>Apply
        </button>
        <button class="btn btn-sm btn-primary" onclick="clpCsv()" data-permission="reports.export">
          <i class="ti ti-download me-1"></i>CSV
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="text-muted small mb-3">
        Pieces cut per part number for the selected cut lists (hold Ctrl/Cmd to pick several). Time left is remaining cuts &times; the median time between cuts, ignoring gaps over 5 minutes.
      </div>
      <div class="row mb-3">
        <div class="col-6 col-md-4"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Cut</div><div class="h2 mb-0" id="clpDone">-</div>
        </div></div></div>
        <div class="col-6 col-md-4"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Remaining</div><div class="h2 mb-0" id="clpRemaining">-</div>
        </div></div></div>
        <div class="col-12 col-md-4"><div class="card card-sm"><div class="card-body">
          <div class="text-muted">Est. Time Left</div><div class="h2 mb-0 text-info" id="clpTime">-</div>
          <div class="text-muted small" id="clpPace"></div>
        </div></div></div>
      </div>

      <div id="clpLoading" class="text-center py-4">
        <div class="spinner-border" role="status"></div>
        <div class="text-muted mt-2">Loading report...</div>
      </div>

      <div id="clpContent" style="display: none;">
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Part Number</th><th>Finish</th>
                <th class="text-end">Cut</th>
                <th style="min-width: 240px;">Progress</th>
                <th class="text-end">Est. Time Left</th>
              </tr>
            </thead>
            <tbody id="clpBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.ReportModules = window.ReportModules || {};

function clpSelectedIds() {
  return [...document.getElementById('clpJobs').selectedOptions].map(o => o.value);
}

function clpQuery() {
  const params = new URLSearchParams();
  clpSelectedIds().forEach(id => params.append('job_ids[]', id));
  return params.toString() ? `?${params}` : '';
}

function clpFormatDuration(seconds) {
  if (seconds === null || seconds === undefined) return '—';
  const minutes = Math.round(seconds / 60);
  if (minutes < 1) return seconds > 0 ? '<1 min' : '0 min';
  return minutes >= 60 ? `${Math.floor(minutes / 60)} h ${minutes % 60} min` : `${minutes} min`;
}

async function loadCutListProgressReport() {
  const ui = window.CutStationUi;
  try {
    ui.show('clp', false);
    const r = await authenticatedFetch(`/reports/cut-station/cutlist-progress${clpQuery()}`);

    // Populate the picker once, keeping the user's selection across reloads.
    const select = document.getElementById('clpJobs');
    const chosen = new Set(clpSelectedIds());
    select.innerHTML = r.jobs.map(j =>
      `<option value="${j.id}" ${chosen.has(String(j.id)) ? 'selected' : ''}>${escapeHtml(j.label)}${j.completed ? ' (done)' : ''}</option>`).join('');
    select.size = Math.min(6, Math.max(1, r.jobs.length));

    const s = r.summary;
    document.getElementById('clpDone').textContent = `${ui.num(s.done_cuts)} / ${ui.num(s.total_cuts)} (${ui.pct(s.percent)})`;
    document.getElementById('clpRemaining').textContent = ui.num(s.remaining_cuts);
    document.getElementById('clpTime').textContent = clpFormatDuration(s.remaining_seconds);
    document.getElementById('clpPace').textContent = s.seconds_per_cut
      ? `${Math.round(s.seconds_per_cut)} s/cut from ${s.pace_source}` : (s.total_cuts ? 'No cutting history yet' : '');

    const body = document.getElementById('clpBody');
    body.innerHTML = r.by_part.length ? r.by_part.map(p => `
      <tr>
        <td>${escapeHtml(p.part_number)}</td>
        <td>${escapeHtml(p.finish || '')}</td>
        <td class="text-end">${ui.num(p.done_cuts)} / ${ui.num(p.total_cuts)}</td>
        <td><div class="d-flex align-items-center gap-2">
          <div class="flex-fill">${ui.bar(p.done_cuts, p.total_cuts, p.remaining_cuts === 0 ? 'success' : 'primary')}</div>
          <span class="text-muted small" style="min-width: 48px; text-align: right;">${ui.pct(p.percent)}</span>
        </div></td>
        <td class="text-end">${p.remaining_cuts ? clpFormatDuration(p.remaining_seconds) : 'Done'}</td>
      </tr>`).join('')
      : '<tr><td colspan="5" class="text-center text-muted py-3">Select one or more cut lists above and press Apply.</td></tr>';

    ui.show('clp', true);
  } catch (error) {
    console.error('Error loading cut list progress report:', error);
    showNotification('Error loading cut list progress report', 'danger');
  }
}

async function clpCsv() {
  const ui = window.CutStationUi;
  try {
    const response = await apiCall(`/reports/export?type=cut_list_progress${clpQuery().replace('?', '&')}`, { headers: { Accept: 'text/csv' } });
    if (!response.ok) throw new Error('Export failed');
    const link = document.createElement('a');
    link.href = URL.createObjectURL(await response.blob());
    link.download = `cut_list_progress_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(link.href);
  } catch (error) {
    console.error('Cut list progress export failed:', error);
    showNotification('Could not export the report', 'danger');
  }
}

window.ReportModules.cutListProgress = { load: loadCutListProgressReport };
</script>
