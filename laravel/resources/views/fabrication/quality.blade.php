@extends('layouts.app')

@section('title', 'Quality Reports')

@section('content')
    <style>
      .qr-sortable { cursor: pointer; user-select: none; white-space: nowrap; }
      .qr-sortable:hover { color: var(--tblr-primary); }
    </style>
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Fabrication</div>
              <h1 class="page-title">Quality Reports</h1>
              <p class="text-muted">Uploaded quality-issue PDFs matched to work order elevations, pending manager review</p>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn btn-primary" id="qr-upload-btn" data-permission="quality.create">
                  <i class="ti ti-upload me-1"></i>Upload PDF
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <div class="d-flex justify-content-end mb-2">
            <button class="btn btn-outline-secondary btn-sm" id="qr-analytics-pdf-btn" onclick="qrExportAnalyticsPdf()">
              <i class="ti ti-file-type-pdf me-1"></i>Save charts as PDF
            </button>
          </div>
          <div class="row row-deck row-cards mb-3">
            <div class="col-12 col-xl-4">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Incident rate by month</h3>
                  <button class="btn btn-icon btn-sm ms-auto" title="Export as image" onclick="qrDownloadChartImage('qr-chart-incident-rate', 'incident-rate-by-month')">
                    <i class="ti ti-download"></i>
                  </button>
                  <button class="btn btn-icon btn-sm" title="View full size" onclick="qrExpandChart('qr-chart-incident-rate', 'Incident rate by month')">
                    <i class="ti ti-arrows-maximize"></i>
                  </button>
                </div>
                <div class="card-body" style="height: 300px;">
                  <canvas id="qr-chart-incident-rate"></canvas>
                </div>
              </div>
            </div>
            <div class="col-12 col-xl-4">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Problem types (rolling 13 weeks)</h3>
                  <button class="btn btn-icon btn-sm ms-auto" title="Export as image" onclick="qrDownloadChartImage('qr-chart-problem-types', 'problem-types-13-week')">
                    <i class="ti ti-download"></i>
                  </button>
                  <button class="btn btn-icon btn-sm" title="View full size" onclick="qrExpandChart('qr-chart-problem-types', 'Problem types (rolling 13 weeks)')">
                    <i class="ti ti-arrows-maximize"></i>
                  </button>
                </div>
                <div class="card-body" style="height: 300px;">
                  <canvas id="qr-chart-problem-types"></canvas>
                </div>
              </div>
            </div>
            <div class="col-12 col-xl-4">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Weekly cases (13-week trend)</h3>
                  <button class="btn btn-icon btn-sm ms-auto" title="Export as image" onclick="qrDownloadChartImage('qr-chart-weekly-trend', 'weekly-cases-13-week-trend')">
                    <i class="ti ti-download"></i>
                  </button>
                  <button class="btn btn-icon btn-sm" title="View full size" onclick="qrExpandChart('qr-chart-weekly-trend', 'Weekly cases (13-week trend)')">
                    <i class="ti ti-arrows-maximize"></i>
                  </button>
                </div>
                <div class="card-body" style="height: 300px;">
                  <canvas id="qr-chart-weekly-trend"></canvas>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Reports</h3>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="qr-status-filter" style="width: auto;">
                      <option value="" selected>All statuses</option>
                      <option value="pending_review">Pending verification</option>
                      <option value="verified">Verified</option>
                      <option value="reviewed">Reviewed</option>
                      <option value="rejected">Rejected</option>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" onclick="qrExportReports('csv')">
                      <i class="ti ti-file-spreadsheet me-1"></i>CSV
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="qrExportReports('pdf')">
                      <i class="ti ti-file-type-pdf me-1"></i>PDF
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="loading" id="qr-loading">
                    <div class="spinner-border" role="status"></div>
                    <div>Loading quality reports...</div>
                  </div>

                  <div class="table-responsive" id="qr-table-wrap" style="display:none;">
                    <table class="table table-vcenter card-table table-striped">
                      <thead>
                        <tr>
                          <th class="qr-sortable" onclick="qrSortBy('created_at')">Uploaded <i class="ti qr-sort-icon" id="qr-sort-icon-created_at"></i></th>
                          <th class="qr-sortable" onclick="qrSortBy('work_order_label')">Work Order <i class="ti qr-sort-icon" id="qr-sort-icon-work_order_label"></i></th>
                          <th class="qr-sortable" onclick="qrSortBy('elevation_tag')">Elevation <i class="ti qr-sort-icon" id="qr-sort-icon-elevation_tag"></i></th>
                          <th class="qr-sortable" onclick="qrSortBy('report_date')">Issue Date <i class="ti qr-sort-icon" id="qr-sort-icon-report_date"></i></th>
                          <th class="qr-sortable" onclick="qrSortBy('inspector_name')">Completed By <i class="ti qr-sort-icon" id="qr-sort-icon-inspector_name"></i></th>
                          <th class="qr-sortable" onclick="qrSortBy('problem_type')">Problem Type <i class="ti qr-sort-icon" id="qr-sort-icon-problem_type"></i></th>
                          <th class="qr-sortable" onclick="qrSortBy('replacement_needed')">Replacement? <i class="ti qr-sort-icon" id="qr-sort-icon-replacement_needed"></i></th>
                          <th class="qr-sortable" onclick="qrSortBy('match_confidence')">Match <i class="ti qr-sort-icon" id="qr-sort-icon-match_confidence"></i></th>
                          <th class="qr-sortable" onclick="qrSortBy('status')">Status <i class="ti qr-sort-icon" id="qr-sort-icon-status"></i></th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody id="qr-table-body"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Upload modal -->
    <div class="modal" id="qr-upload-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Upload quality report</h5>
            <button type="button" class="btn-close" onclick="qrHideModal('qr-upload-modal')"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">PDF file</label>
              <input type="file" class="form-control" id="qr-upload-file" accept="application/pdf">
            </div>
            <div class="mb-3">
              <label class="form-label">Elevation (optional — leave blank to review later)</label>
              <select class="form-select" id="qr-upload-elevation">
                <option value="">Not sure yet</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn" onclick="qrHideModal('qr-upload-modal')">Cancel</button>
            <button class="btn btn-primary" id="qr-upload-submit" onclick="qrUploadFile()">
              <i class="ti ti-upload me-1"></i>Upload
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Full-size chart modal -->
    <div class="modal" id="qr-chart-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="qr-chart-modal-title">Chart</h5>
            <button type="button" class="btn-close" onclick="qrHideModal('qr-chart-modal')"></button>
          </div>
          <div class="modal-body" style="height: 70vh;">
            <canvas id="qr-chart-modal-canvas"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- In-app PDF viewer -->
    <div class="modal" id="qr-pdf-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="qr-pdf-modal-title">Report PDF</h5>
            <button type="button" class="btn-close" onclick="qrHideModal('qr-pdf-modal')"></button>
          </div>
          <div class="modal-body p-0" style="height: 80vh;">
            <iframe id="qr-pdf-modal-frame" src="" style="width:100%; height:100%; border:0;"></iframe>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail / edit modal -->
    <div class="modal" id="qr-detail-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Quality report</h5>
            <button type="button" class="btn-close" onclick="qrHideModal('qr-detail-modal')"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Job</label>
                <select class="form-select" id="qr-d-job" onchange="qrOnJobChange()"></select>
                <div class="form-hint mt-1" id="qr-d-job-source"></div>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Date issue discovered</label>
                <input type="date" class="form-control" id="qr-d-report-date">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Elevation</label>
                <select class="form-select" id="qr-d-elevation"></select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Completed by</label>
                <input type="text" class="form-control" id="qr-d-inspector">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Completed at</label>
                <input type="text" class="form-control" id="qr-d-completed-at" disabled>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Problem type</label>
                <input type="text" class="form-control" id="qr-d-problem-type">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Replacement needed?</label>
                <select class="form-select" id="qr-d-replacement">
                  <option value="">Unknown</option>
                  <option value="1">Yes</option>
                  <option value="0">No</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Match confidence</label>
                <input type="text" class="form-control" id="qr-d-confidence" disabled>
              </div>
              <div class="col-12 mb-3">
                <label class="form-label">Issue description</label>
                <textarea class="form-control" id="qr-d-description" rows="4"></textarea>
              </div>
              <div class="col-12">
                <div id="qr-d-files"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-danger me-auto" id="qr-d-delete-btn" data-permission="quality.delete" onclick="qrDeleteReport()">Delete</button>
            <button class="btn btn-outline-primary" id="qr-d-copy-btn" style="display:none;" onclick="qrCopyRow()">
              <i class="ti ti-copy me-1"></i>Copy row
            </button>
            <button class="btn btn-outline-danger" id="qr-d-reject-btn" data-permission="quality.verify" onclick="qrRejectReport()">Reject</button>
            <button class="btn btn-success" id="qr-d-verify-btn" data-permission="quality.verify" onclick="qrVerifyReport()">Verify</button>
            <button class="btn btn-primary" id="qr-d-review-btn" style="display:none;" data-permission="quality.verify" onclick="qrReviewReport()">Mark reviewed</button>
            <button class="btn btn-primary" id="qr-d-save-btn" data-permission="quality.edit" onclick="qrSaveReport()">Save</button>
          </div>
        </div>
      </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
let qrReports = [];
let qrElevations = [];
let qrJobs = [];
let qrEditingId = null;
let qrDetailSelectedElevationId = null;
let qrCharts = {};
let qrSortKey = 'created_at';
let qrSortDir = 'desc';

document.addEventListener('DOMContentLoaded', () => {
  qrLoadReports();
  qrLoadElevations();
  qrLoadAnalytics();
  document.getElementById('qr-status-filter').addEventListener('change', qrLoadReports);
  document.getElementById('qr-upload-btn').addEventListener('click', () => qrShowModal('qr-upload-modal'));
});

async function qrLoadAnalytics() {
  try {
    const [incident, problemTypes, weeklyTrend] = await Promise.all([
      qrApiJson('/quality-reports/analytics/incident-rate'),
      qrApiJson('/quality-reports/analytics/problem-types'),
      qrApiJson('/quality-reports/analytics/weekly-trend'),
    ]);
    qrRenderIncidentRateChart(incident?.data ?? []);
    qrRenderProblemTypesChart(problemTypes?.data ?? []);
    qrRenderWeeklyTrendChart(weeklyTrend?.data ?? []);
  } catch (e) {
    console.error('Failed to load quality analytics', e);
  }
}

function qrChart(canvasId, config) {
  if (qrCharts[canvasId]) qrCharts[canvasId].destroy();
  qrCharts[canvasId] = new Chart(document.getElementById(canvasId), config);
}

let qrModalChart = null;

/** Re-renders the same chart's data/options into a larger modal canvas — Chart.js doesn't support resizing a chart across canvases, so this is a fresh instance, not the original moved. */
function qrExpandChart(canvasId, title) {
  const source = qrCharts[canvasId];
  if (!source) return;

  document.getElementById('qr-chart-modal-title').textContent = title;

  const config = {
    data: JSON.parse(JSON.stringify(source.config.data)),
    options: JSON.parse(JSON.stringify(source.config.options || {})),
  };
  if (source.config.type) config.type = source.config.type;
  config.options.maintainAspectRatio = false;

  if (qrModalChart) qrModalChart.destroy();
  qrModalChart = new Chart(document.getElementById('qr-chart-modal-canvas'), config);

  qrShowModal('qr-chart-modal');
}

function qrRenderIncidentRateChart(rows) {
  qrChart('qr-chart-incident-rate', {
    data: {
      labels: rows.map(r => r.month_label),
      datasets: [
        {
          type: 'bar',
          label: 'Joints completed',
          data: rows.map(r => r.joint_count),
          backgroundColor: 'rgba(32, 107, 196, 0.5)',
          yAxisID: 'y',
        },
        {
          type: 'line',
          label: 'Incident rate (%)',
          data: rows.map(r => r.incident_rate),
          borderColor: '#d63939',
          backgroundColor: '#d63939',
          yAxisID: 'y1',
          tension: 0.3,
        },
        {
          type: 'line',
          label: 'Goal (1.5%)',
          data: rows.map(() => 1.5),
          borderColor: '#2fb344',
          backgroundColor: '#2fb344',
          borderDash: [6, 4],
          pointRadius: 0,
          yAxisID: 'y1',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Joints completed' } },
        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Incident rate (%)' } },
      },
    },
  });
}

function qrRenderProblemTypesChart(rows) {
  qrChart('qr-chart-problem-types', {
    type: 'bar',
    data: {
      labels: rows.map(r => r.problem_type),
      datasets: [{
        label: 'Reports',
        data: rows.map(r => r.count),
        backgroundColor: 'rgba(32, 107, 196, 0.5)',
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: 'y',
      scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
      plugins: { legend: { display: false } },
    },
  });
}

function qrRenderWeeklyTrendChart(rows) {
  qrChart('qr-chart-weekly-trend', {
    data: {
      labels: rows.map(r => r.week),
      datasets: [
        {
          type: 'bar',
          label: 'Cases',
          data: rows.map(r => r.case_count),
          backgroundColor: 'rgba(32, 107, 196, 0.5)',
        },
        {
          type: 'line',
          label: '13-week trend',
          data: rows.map(r => r.trend_value),
          borderColor: '#d63939',
          backgroundColor: '#d63939',
          tension: 0,
          pointRadius: 0,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });
}

async function qrApiJson(endpoint, options = {}) {
  const response = await apiCall(endpoint, options);
  if (!response.ok) {
    if (response.status === 401) return;
    const err = await response.json().catch(() => ({ message: 'Request failed' }));
    throw new Error(err.error || err.message || `HTTP ${response.status}`);
  }
  return response.json();
}

async function qrApiMultipart(endpoint, formData, method = 'POST') {
  const headers = {
    'Accept': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
  };
  const response = await fetch(`${API_BASE}${endpoint}`, { method, headers, body: formData, credentials: 'include' });
  if (!response.ok) {
    if (response.status === 401) { showLogin(); return null; }
    const err = await response.json().catch(() => ({ message: 'Request failed' }));
    throw new Error(err.error || err.message || `HTTP ${response.status}`);
  }
  return response.json();
}

function qrShowModal(id) { new bootstrap.Modal(document.getElementById(id)).show(); }
function qrHideModal(id) {
  const el = document.getElementById(id);
  const modal = bootstrap.Modal.getInstance(el);
  if (modal) modal.hide();
}

async function qrLoadReports() {
  try {
    const status = document.getElementById('qr-status-filter').value;
    const params = new URLSearchParams();
    if (status) params.append('status', status);

    const data = await qrApiJson(`/quality-reports?${params}`);
    qrReports = data?.data ?? [];
    qrRenderTable();
  } catch (e) {
    console.error(e);
    showNotification('Failed to load quality reports', 'danger');
  } finally {
    document.getElementById('qr-loading').style.display = 'none';
    document.getElementById('qr-table-wrap').style.display = 'block';
  }
}

/** Work order labels are "{jobNumber}-{releaseToken}" (e.g. "4250403-WO6"); just the release token (everything after the last "-") is what distinguishes elevations within the same job. */
function qrWorkOrderSuffix(workOrderLabel, workOrderId) {
  if (!workOrderLabel) return 'WO #' + workOrderId;
  const parts = workOrderLabel.split('-');
  return parts.length > 1 ? parts[parts.length - 1] : workOrderLabel;
}

/** "Pre-Forge" picker option for issues on jobs that predate ForgeDesk tracking entirely — self-disables a year after the historical-data changeover so it doesn't linger indefinitely. */
const QR_PRE_FORGE_CUTOFF = new Date('2027-09-16T00:00:00');
function qrPreForgeAvailable() { return new Date() < QR_PRE_FORGE_CUTOFF; }
const QR_PRE_FORGE_OPTION = '<option value="pre-forge">Pre-Forge (pre-tracking)</option>';

async function qrLoadElevations() {
  try {
    const data = await qrApiJson('/quality-reports/elevation-options');
    qrElevations = (data?.data ?? []).map(el => ({
      id: el.id,
      jobId: el.business_job_id ?? null,
      jobName: el.business_job_name || '',
      elevationTag: el.elevation_tag,
      label: `${el.elevation_tag} (${qrWorkOrderSuffix(el.work_order_label, el.work_order_id)})`,
    })).sort((a, b) => a.elevationTag.localeCompare(b.elevationTag, undefined, { numeric: true, sensitivity: 'base' }));

    const jobMap = new Map();
    qrElevations.forEach(el => {
      if (el.jobId != null && !jobMap.has(el.jobId)) jobMap.set(el.jobId, el.jobName);
    });
    qrJobs = [...jobMap.entries()].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name));

    const options = [];
    if (qrPreForgeAvailable()) options.push(QR_PRE_FORGE_OPTION);
    options.push('<option value="">Not sure yet</option>');
    options.push(...qrElevations.map(el => `<option value="${el.id}">${el.label}</option>`));
    document.getElementById('qr-upload-elevation').innerHTML = options.join('');
  } catch (e) {
    console.error('Failed to load elevations for matching', e);
  }
}

/** Rebuilds the Job dropdown; called once per detail-modal open with the recommended job pre-selected. */
function qrRenderJobOptions(recommendedJobId) {
  const options = ['<option value="">All jobs (unfiltered)</option>']
    .concat(qrJobs.map(j => `<option value="${j.id}" ${j.id === recommendedJobId ? 'selected' : ''}>${j.name}</option>`));
  document.getElementById('qr-d-job').innerHTML = options.join('');
}

function qrOnJobChange() {
  qrRenderElevationOptions();
}

/** Narrows the detail modal's elevation dropdown to elevations on the selected job, since the full list can span many unrelated jobs. */
function qrRenderElevationOptions() {
  const jobId = document.getElementById('qr-d-job').value;

  let pool = qrElevations;
  if (jobId !== '') {
    pool = qrElevations.filter(el => String(el.jobId) === jobId);
  }

  const options = [];
  if (qrPreForgeAvailable()) {
    options.push(`<option value="pre-forge" ${qrDetailSelectedElevationId === 'pre-forge' ? 'selected' : ''}>Pre-Forge (pre-tracking)</option>`);
  }
  options.push('<option value="">Unassigned</option>');
  options.push(...pool.map(el => `<option value="${el.id}" ${el.id === qrDetailSelectedElevationId ? 'selected' : ''}>${el.label}</option>`));
  document.getElementById('qr-d-elevation').innerHTML = options.join('');
}

/** Toggles asc/desc on repeat clicks of the same column; switching columns starts asc. */
function qrSortBy(key) {
  if (qrSortKey === key) {
    qrSortDir = qrSortDir === 'asc' ? 'desc' : 'asc';
  } else {
    qrSortKey = key;
    qrSortDir = 'asc';
  }
  qrRenderTable();
}

function qrUpdateSortIcons() {
  document.querySelectorAll('.qr-sort-icon').forEach(el => el.className = 'ti qr-sort-icon');
  const icon = document.getElementById(`qr-sort-icon-${qrSortKey}`);
  if (icon) icon.classList.add(qrSortDir === 'asc' ? 'ti-arrow-up' : 'ti-arrow-down');
}

/** Nulls/blanks always sort last regardless of direction — a missing value isn't meaningfully "low". */
function qrSortedReports() {
  const dir = qrSortDir === 'asc' ? 1 : -1;
  return [...qrReports].sort((a, b) => {
    let av = a[qrSortKey];
    let bv = b[qrSortKey];
    const aEmpty = av === null || av === undefined || av === '';
    const bEmpty = bv === null || bv === undefined || bv === '';
    if (aEmpty && bEmpty) return 0;
    if (aEmpty) return 1;
    if (bEmpty) return -1;

    if (qrSortKey === 'replacement_needed') { av = av ? 1 : 0; bv = bv ? 1 : 0; }
    if (typeof av === 'string' && typeof bv === 'string') {
      return av.localeCompare(bv, undefined, { numeric: true, sensitivity: 'base' }) * dir;
    }
    return (av > bv ? 1 : av < bv ? -1 : 0) * dir;
  });
}

function qrRenderTable() {
  qrUpdateSortIcons();
  const tbody = document.getElementById('qr-table-body');
  if (qrReports.length === 0) {
    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-5">No quality reports found</td></tr>';
    return;
  }

  const statusBadge = {
    pending_review: '<span class="badge bg-warning-lt text-warning">Pending verification</span>',
    verified: '<span class="badge bg-success-lt text-success">Verified</span>',
    reviewed: '<span class="badge bg-blue-lt text-blue">Reviewed</span>',
    rejected: '<span class="badge bg-danger-lt text-danger">Rejected</span>',
  };

  tbody.innerHTML = qrSortedReports().map(r => {
    const confidence = r.match_confidence != null
      ? `<span class="badge ${r.match_confidence < 40 ? 'bg-danger-lt text-danger' : 'bg-blue-lt text-blue'}">${Math.round(r.match_confidence)}%</span>`
      : '<span class="text-muted">-</span>';
    const replacement = r.replacement_needed === true ? 'Yes' : (r.replacement_needed === false ? 'No' : '<span class="text-muted">-</span>');

    return `
    <tr onclick="qrOpenDetail(${r.id})" style="cursor:pointer;">
      <td>${new Date(r.created_at).toLocaleDateString()}</td>
      <td>${r.work_order_label || '<span class="text-muted">-</span>'}</td>
      <td>${r.elevation_tag || '<span class="text-muted">Unassigned</span>'}</td>
      <td>${r.report_date || '<span class="text-muted">-</span>'}</td>
      <td>${r.inspector_name || '<span class="text-muted">-</span>'}</td>
      <td>${r.problem_type || '<span class="text-muted">-</span>'}</td>
      <td>${replacement}</td>
      <td>${confidence}</td>
      <td>${statusBadge[r.status] || r.status}</td>
    </tr>
  `;
  }).join('');
}

async function qrUploadFile() {
  const fileInput = document.getElementById('qr-upload-file');
  if (!fileInput.files.length) { showNotification('Please choose a PDF file', 'warning'); return; }

  const btn = document.getElementById('qr-upload-submit');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Uploading…';

  try {
    const fd = new FormData();
    fd.append('file', fileInput.files[0]);
    const elevationId = document.getElementById('qr-upload-elevation').value;
    if (elevationId === 'pre-forge') {
      fd.append('elevation_tag_guess', 'Pre-Forge');
    } else if (elevationId) {
      fd.append('elevation_id', elevationId);
    }

    const result = await qrApiMultipart('/quality-reports', fd);
    if (!result) return;

    qrHideModal('qr-upload-modal');
    fileInput.value = '';
    showNotification('Quality report uploaded', 'success');
    qrLoadReports();
  } catch (e) {
    showNotification(e.message || 'Upload failed', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="ti ti-upload me-1"></i>Upload';
  }
}

async function qrOpenDetail(id) {
  try {
    const r = await qrApiJson(`/quality-reports/${id}`);
    qrEditingId = id;

    qrDetailSelectedElevationId = (r.elevation_id == null && r.elevation_tag_guess === 'Pre-Forge') ? 'pre-forge' : r.elevation_id;
    qrRenderJobOptions(r.business_job_id ?? null);
    qrRenderElevationOptions();

    const pdfJobText = r.extracted_fields?.job_text || '';
    document.getElementById('qr-d-job-source').textContent = pdfJobText
      ? `Reference: "${pdfJobText}"`
      : '';

    document.getElementById('qr-d-report-date').value = r.report_date || '';
    document.getElementById('qr-d-inspector').value = r.inspector_name || '';
    document.getElementById('qr-d-completed-at').value = r.completed_at ? new Date(r.completed_at).toLocaleString() : '';
    document.getElementById('qr-d-problem-type').value = r.problem_type || '';
    document.getElementById('qr-d-replacement').value = r.replacement_needed === true ? '1' : (r.replacement_needed === false ? '0' : '');
    document.getElementById('qr-d-confidence').value = r.match_confidence != null
      ? `${Math.round(r.match_confidence)}%${r.auto_matched ? ' (auto-matched)' : ' (manually assigned)'}`
      : '';
    document.getElementById('qr-d-description').value = r.issue_description || '';

    const files = (r.files || []).map(f => `
      <div class="d-flex align-items-center gap-2 mb-1">
        <i class="ti ti-file-text"></i>
        <span class="flex-fill">${f.original_name}</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="qrViewPdf(${r.id}, ${f.id}, '${(f.original_name || 'Report PDF').replace(/'/g, "\\'")}')">
          <i class="ti ti-eye me-1"></i>View
        </button>
        <a href="${f.download_url}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-download"></i></a>
      </div>
    `).join('') || '<span class="text-muted">No files</span>';
    document.getElementById('qr-d-files').innerHTML = files;

    const isPending = r.status === 'pending_review';
    document.getElementById('qr-d-verify-btn').style.display = isPending ? '' : 'none';
    document.getElementById('qr-d-reject-btn').style.display = isPending ? '' : 'none';
    document.getElementById('qr-d-review-btn').style.display = r.status === 'verified' ? '' : 'none';
    document.getElementById('qr-d-copy-btn').style.display = (r.status === 'verified' || r.status === 'reviewed') ? '' : 'none';

    qrShowModal('qr-detail-modal');
  } catch (e) {
    showNotification(e.message || 'Failed to load report', 'danger');
  }
}

async function qrSaveReport() {
  try {
    const replacementVal = document.getElementById('qr-d-replacement').value;
    const elevationVal = document.getElementById('qr-d-elevation').value;
    const payload = {
      elevation_id: elevationVal === 'pre-forge' ? null : (elevationVal || null),
      report_date: document.getElementById('qr-d-report-date').value || null,
      inspector_name: document.getElementById('qr-d-inspector').value.trim() || null,
      problem_type: document.getElementById('qr-d-problem-type').value.trim() || null,
      replacement_needed: replacementVal === '' ? null : replacementVal === '1',
      issue_description: document.getElementById('qr-d-description').value.trim() || null,
    };
    if (elevationVal === 'pre-forge') payload.elevation_tag_guess = 'Pre-Forge';
    await qrApiJson(`/quality-reports/${qrEditingId}`, { method: 'PUT', body: JSON.stringify(payload) });
    qrHideModal('qr-detail-modal');
    showNotification('Report updated', 'success');
    qrLoadReports();
  } catch (e) {
    showNotification(e.message || 'Save failed', 'danger');
  }
}

async function qrVerifyReport() {
  try {
    await qrApiJson(`/quality-reports/${qrEditingId}/verify`, { method: 'POST' });
    document.getElementById('qr-d-verify-btn').style.display = 'none';
    document.getElementById('qr-d-reject-btn').style.display = 'none';
    document.getElementById('qr-d-review-btn').style.display = '';
    document.getElementById('qr-d-copy-btn').style.display = '';
    showNotification('Report verified — you can now copy the summary row', 'success');
    qrLoadReports();
  } catch (e) {
    showNotification(e.message || 'Verify failed', 'danger');
  }
}

async function qrReviewReport() {
  try {
    await qrApiJson(`/quality-reports/${qrEditingId}/review`, { method: 'POST' });
    document.getElementById('qr-d-review-btn').style.display = 'none';
    showNotification('Report marked reviewed', 'success');
    qrLoadReports();
  } catch (e) {
    showNotification(e.message || 'Review failed', 'danger');
  }
}

function qrViewPdf(reportId, fileId, title) {
  document.getElementById('qr-pdf-modal-title').textContent = title;
  document.getElementById('qr-pdf-modal-frame').src = `${API_BASE}/quality-reports/${reportId}/files/${fileId}/view`;
  qrShowModal('qr-pdf-modal');
}

document.getElementById('qr-pdf-modal').addEventListener('hidden.bs.modal', () => {
  document.getElementById('qr-pdf-modal-frame').src = '';
});

/** Downloads the chart's canvas as a PNG at its own native aspect ratio — no PDF/DOM roundtrip needed since Chart.js draws straight to a <canvas>. */
function qrDownloadChartImage(canvasId, filename) {
  const chart = qrCharts[canvasId];
  if (!chart) { showNotification('Chart not ready yet', 'warning'); return; }

  const a = document.createElement('a');
  a.href = chart.toBase64Image('image/png', 1);
  a.download = `${filename}-${new Date().toISOString().split('T')[0]}.png`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

/** Tab-separated so it pastes as individual spreadsheet cells: date reported, job, elevation, replacement needed, problem type, description, reported by. Reads live from the modal's fields, not the cached report, so an edited-but-unsaved value is reflected. */
function qrCopyRow() {
  const jobSelect = document.getElementById('qr-d-job');
  const jobName = jobSelect.selectedIndex >= 0 ? jobSelect.options[jobSelect.selectedIndex].text : '';
  const jobLabel = jobSelect.value === '' ? '' : jobName;

  const elevationSelect = document.getElementById('qr-d-elevation');
  const elevationId = elevationSelect.value ? parseInt(elevationSelect.value, 10) : null;
  const elevation = qrElevations.find(el => el.id === elevationId);
  const elevationTag = elevation ? elevation.elevationTag : '';

  const replacementVal = document.getElementById('qr-d-replacement').value;
  const replacement = replacementVal === '1' ? 'Yes' : (replacementVal === '0' ? 'No' : '');

  const cells = [
    document.getElementById('qr-d-report-date').value || '',
    jobLabel,
    elevationTag,
    replacement,
    document.getElementById('qr-d-problem-type').value.trim(),
    document.getElementById('qr-d-description').value.trim(),
    document.getElementById('qr-d-inspector').value.trim(),
  ];
  const row = cells.join('\t');

  navigator.clipboard.writeText(row).then(() => {
    showNotification('Row copied to clipboard', 'success');
  }).catch(() => {
    showNotification('Could not copy — your browser blocked clipboard access', 'danger');
  });
}

async function qrRejectReport() {
  const reason = prompt('Reason for rejecting this report (optional):') || '';
  try {
    await qrApiJson(`/quality-reports/${qrEditingId}/reject`, { method: 'POST', body: JSON.stringify({ reason }) });
    qrHideModal('qr-detail-modal');
    showNotification('Report rejected', 'success');
    qrLoadReports();
  } catch (e) {
    showNotification(e.message || 'Reject failed', 'danger');
  }
}

async function qrDeleteReport() {
  if (!confirm('Delete this quality report? This cannot be undone.')) return;
  try {
    await qrApiJson(`/quality-reports/${qrEditingId}`, { method: 'DELETE' });
    qrHideModal('qr-detail-modal');
    showNotification('Report deleted', 'success');
    qrLoadReports();
  } catch (e) {
    showNotification(e.message || 'Delete failed', 'danger');
  }
}

async function qrDownloadBlob(response, filename) {
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
}

async function qrExportReports(type) {
  try {
    const status = document.getElementById('qr-status-filter').value;
    const params = new URLSearchParams();
    if (status) params.append('status', status);

    const response = await fetch(`${API_BASE}/quality-reports/export/${type}?${params}`, {
      credentials: 'include',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });
    if (!response.ok) throw new Error('Export failed');

    await qrDownloadBlob(response, `quality-reports-${new Date().toISOString().split('T')[0]}.${type}`);
  } catch (e) {
    showNotification(e.message || 'Export failed', 'danger');
  }
}

async function qrExportAnalyticsPdf() {
  const btn = document.getElementById('qr-analytics-pdf-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Building PDF…';

  try {
    const payload = {
      incident_chart: qrCharts['qr-chart-incident-rate']?.toBase64Image() || null,
      problem_types_chart: qrCharts['qr-chart-problem-types']?.toBase64Image() || null,
      weekly_chart: qrCharts['qr-chart-weekly-trend']?.toBase64Image() || null,
    };

    const response = await fetch(`${API_BASE}/quality-reports/analytics/export-pdf`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify(payload),
    });
    if (!response.ok) throw new Error('Export failed');

    await qrDownloadBlob(response, `quality-analytics-${new Date().toISOString().split('T')[0]}.pdf`);
  } catch (e) {
    showNotification(e.message || 'Export failed', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="ti ti-file-type-pdf me-1"></i>Save charts as PDF';
  }
}
</script>
@endpush
