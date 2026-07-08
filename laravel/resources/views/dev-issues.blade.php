@extends('layouts.app')
@section('title', 'Dev Issue Tracker - ForgeDesk')

@section('styles')
.priority-critical { color: var(--tblr-danger); font-weight: 600; }
.priority-high     { color: var(--tblr-orange); font-weight: 600; }
.priority-medium   { color: var(--tblr-warning); }
.priority-low      { color: var(--tblr-secondary); }
.console-pre {
  font-family: monospace;
  font-size: 0.78rem;
  background: var(--tblr-bg-surface-secondary);
  border: 1px solid var(--tblr-border-color);
  border-radius: 4px;
  padding: 0.5rem;
  max-height: 200px;
  overflow-y: auto;
  white-space: pre-wrap;
  word-break: break-all;
}
@endsection

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Development</div>
          <h2 class="page-title">Dev Issue Tracker</h2>
        </div>
        <div class="col-auto ms-auto">
          <div class="d-flex gap-2">
            <select id="filterStatus" class="form-select form-select-sm" style="width:auto">
              <option value="">All Statuses</option>
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
              <option value="wont_fix">Won't Fix</option>
            </select>
            <select id="filterPriority" class="form-select form-select-sm" style="width:auto">
              <option value="">All Priorities</option>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
            <input type="search" id="searchInput" class="form-control form-control-sm" placeholder="Search…" style="width:180px">
            <button class="btn btn-primary btn-sm" onclick="openCreateModal()">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M12 5l0 14" /><path d="M5 12l14 0" />
              </svg>
              Log Issue
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      <!-- Stats row -->
      <div class="row row-cards mb-3" id="statsRow">
        <div class="col-6 col-sm-3">
          <div class="card card-sm">
            <div class="card-body text-center">
              <div class="fs-2 fw-bold text-danger" id="statCritical">—</div>
              <div class="text-muted small">Critical</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-sm-3">
          <div class="card card-sm">
            <div class="card-body text-center">
              <div class="fs-2 fw-bold text-primary" id="statOpen">—</div>
              <div class="text-muted small">Open</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-sm-3">
          <div class="card card-sm">
            <div class="card-body text-center">
              <div class="fs-2 fw-bold text-warning" id="statInProgress">—</div>
              <div class="text-muted small">In Progress</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-sm-3">
          <div class="card card-sm">
            <div class="card-body text-center">
              <div class="fs-2 fw-bold text-success" id="statResolved">—</div>
              <div class="text-muted small">Resolved</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Issue table -->
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
              <thead>
                <tr>
                  <th style="width:60px">#</th>
                  <th>Title</th>
                  <th style="width:110px">Priority</th>
                  <th style="width:120px">Status</th>
                  <th style="width:130px">Reported By</th>
                  <th style="width:150px">Date</th>
                  <th style="width:100px" class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="issuesTableBody">
                <tr><td colspan="7" class="text-center py-4 text-muted">Loading…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Create / Edit Modal -->
<div class="modal fade" id="issueModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="issueModalTitle">Log New Issue</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editIssueId">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label required">Title</label>
            <input type="text" class="form-control" id="issueTitle" placeholder="Short description of the issue">
          </div>
          <div class="col-md-6">
            <label class="form-label">Priority</label>
            <select class="form-select" id="issuePriority">
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select class="form-select" id="issueStatus">
              <option value="open" selected>Open</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
              <option value="wont_fix">Won't Fix</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Reported By</label>
            <input type="text" class="form-control" id="issueReportedBy" placeholder="Your name">
          </div>
          <div class="col-md-6">
            <label class="form-label">Page URL</label>
            <input type="text" class="form-control" id="issuePageUrl" placeholder="e.g. /inventory">
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="issueDescription" rows="3" placeholder="What happened? Steps to reproduce…"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label d-flex justify-content-between align-items-center">
              Console Output
              <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="pasteConsole()">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1">
                  <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                  <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
                  <path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" />
                </svg>
                Paste from clipboard
              </button>
            </label>
            <textarea class="form-control font-monospace" id="issueConsoleOutput" rows="6"
              placeholder="Paste browser console errors / stack traces here…" style="font-size:0.8rem"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Notes / Resolution</label>
            <textarea class="form-control" id="issueNotes" rows="2" placeholder="Additional context or fix notes…"></textarea>
          </div>
        </div>
        <div id="issueModalError" class="alert alert-danger mt-3" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveIssueBtn" onclick="saveIssue()">Save Issue</button>
      </div>
    </div>
  </div>
</div>

<!-- View / Detail Modal -->
<div class="modal fade" id="viewIssueModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewIssueTitle">Issue Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewIssueBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="editFromViewBtn">Edit</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
window.sessionReady.then(() => {
  loadIssues();

  document.getElementById('filterStatus').addEventListener('change', loadIssues);
  document.getElementById('filterPriority').addEventListener('change', loadIssues);

  let searchTimeout;
  document.getElementById('searchInput').addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadIssues, 300);
  });
});

// ── Helpers ──────────────────────────────────────────────────────────────────

const PRIORITY_BADGE = {
  critical: 'bg-danger',
  high:     'bg-orange',
  medium:   'bg-warning',
  low:      'bg-secondary',
};

const STATUS_BADGE = {
  open:        'bg-primary',
  in_progress: 'bg-azure',
  resolved:    'bg-success',
  wont_fix:    'bg-secondary',
};

const STATUS_LABEL = {
  open:        'Open',
  in_progress: 'In Progress',
  resolved:    'Resolved',
  wont_fix:    "Won't Fix",
};

function fmtDate(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function escHtml(s) {
  if (s == null) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Load & render ─────────────────────────────────────────────────────────────

async function loadIssues() {
  const params = new URLSearchParams();
  const status   = document.getElementById('filterStatus').value;
  const priority = document.getElementById('filterPriority').value;
  const search   = document.getElementById('searchInput').value;
  if (status)   params.append('status', status);
  if (priority) params.append('priority', priority);
  if (search)   params.append('search', search);

  const tbody = document.getElementById('issuesTableBody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Loading…</td></tr>';

  try {
    const issues = await apiCall(`/api/v1/dev-issues?${params}`);
    renderTable(issues);
    renderStats(issues);
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">Failed to load issues: ${escHtml(e.message)}</td></tr>`;
  }
}

function renderTable(issues) {
  const tbody = document.getElementById('issuesTableBody');
  if (!issues.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No issues found.</td></tr>';
    return;
  }

  tbody.innerHTML = issues.map(issue => `
    <tr style="cursor:pointer" onclick="viewIssue(${issue.id})">
      <td class="text-muted">${issue.id}</td>
      <td>
        <div class="fw-medium">${escHtml(issue.title)}</div>
        ${issue.page_url ? `<small class="text-muted">${escHtml(issue.page_url)}</small>` : ''}
      </td>
      <td>
        <span class="badge ${PRIORITY_BADGE[issue.priority] || 'bg-secondary'} text-capitalize">
          ${escHtml(issue.priority)}
        </span>
      </td>
      <td>
        <span class="badge ${STATUS_BADGE[issue.status] || 'bg-secondary'}">
          ${STATUS_LABEL[issue.status] || escHtml(issue.status)}
        </span>
      </td>
      <td class="text-muted">${escHtml(issue.reported_by) || '—'}</td>
      <td class="text-muted">${fmtDate(issue.created_at)}</td>
      <td class="text-end table-actions" onclick="event.stopPropagation()">
        <button class="btn btn-sm btn-ghost-secondary" title="Edit" onclick="openEditModal(${issue.id})">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
          </svg>
        </button>
        <button class="btn btn-sm btn-ghost-danger" title="Delete" onclick="deleteIssue(${issue.id})">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" />
            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
          </svg>
        </button>
      </td>
    </tr>
  `).join('');
}

function renderStats(issues) {
  document.getElementById('statCritical').textContent =
    issues.filter(i => i.priority === 'critical' && i.status !== 'resolved' && i.status !== 'wont_fix').length;
  document.getElementById('statOpen').textContent =
    issues.filter(i => i.status === 'open').length;
  document.getElementById('statInProgress').textContent =
    issues.filter(i => i.status === 'in_progress').length;
  document.getElementById('statResolved').textContent =
    issues.filter(i => i.status === 'resolved').length;
}

// ── Create / Edit modal ───────────────────────────────────────────────────────

function openCreateModal(prefill = {}) {
  document.getElementById('issueModalTitle').textContent = 'Log New Issue';
  document.getElementById('editIssueId').value = '';
  document.getElementById('issueTitle').value       = prefill.title || '';
  document.getElementById('issuePriority').value    = prefill.priority || 'medium';
  document.getElementById('issueStatus').value      = 'open';
  document.getElementById('issueReportedBy').value  = prefill.reported_by || '';
  document.getElementById('issuePageUrl').value     = prefill.page_url || window.location.pathname;
  document.getElementById('issueDescription').value = prefill.description || '';
  document.getElementById('issueConsoleOutput').value = prefill.console_output || '';
  document.getElementById('issueNotes').value       = '';
  document.getElementById('issueModalError').style.display = 'none';
  new bootstrap.Modal(document.getElementById('issueModal')).show();
}

async function openEditModal(id) {
  try {
    const issue = await apiCall(`/api/v1/dev-issues/${id}`);
    document.getElementById('issueModalTitle').textContent = `Edit Issue #${id}`;
    document.getElementById('editIssueId').value          = id;
    document.getElementById('issueTitle').value           = issue.title || '';
    document.getElementById('issuePriority').value        = issue.priority || 'medium';
    document.getElementById('issueStatus').value          = issue.status || 'open';
    document.getElementById('issueReportedBy').value      = issue.reported_by || '';
    document.getElementById('issuePageUrl').value         = issue.page_url || '';
    document.getElementById('issueDescription').value     = issue.description || '';
    document.getElementById('issueConsoleOutput').value   = issue.console_output || '';
    document.getElementById('issueNotes').value           = issue.notes || '';
    document.getElementById('issueModalError').style.display = 'none';

    // close view modal if open
    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewIssueModal'));
    if (viewModal) viewModal.hide();

    new bootstrap.Modal(document.getElementById('issueModal')).show();
  } catch (e) {
    alert('Failed to load issue: ' + e.message);
  }
}

async function saveIssue() {
  const id = document.getElementById('editIssueId').value;
  const payload = {
    title:          document.getElementById('issueTitle').value.trim(),
    priority:       document.getElementById('issuePriority').value,
    status:         document.getElementById('issueStatus').value,
    reported_by:    document.getElementById('issueReportedBy').value.trim(),
    page_url:       document.getElementById('issuePageUrl').value.trim(),
    description:    document.getElementById('issueDescription').value.trim(),
    console_output: document.getElementById('issueConsoleOutput').value.trim(),
    notes:          document.getElementById('issueNotes').value.trim(),
  };

  const errEl = document.getElementById('issueModalError');
  errEl.style.display = 'none';

  if (!payload.title) {
    errEl.textContent = 'Title is required.';
    errEl.style.display = 'block';
    return;
  }

  const btn = document.getElementById('saveIssueBtn');
  btn.disabled = true;
  btn.textContent = 'Saving…';

  try {
    if (id) {
      await apiCall(`/api/v1/dev-issues/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
    } else {
      await apiCall('/api/v1/dev-issues', { method: 'POST', body: JSON.stringify(payload) });
    }

    bootstrap.Modal.getInstance(document.getElementById('issueModal')).hide();
    loadIssues();
  } catch (e) {
    errEl.textContent = e.message || 'Failed to save issue.';
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Issue';
  }
}

// ── View modal ────────────────────────────────────────────────────────────────

async function viewIssue(id) {
  try {
    const issue = await apiCall(`/api/v1/dev-issues/${id}`);
    document.getElementById('viewIssueTitle').textContent = `Issue #${id}: ${issue.title}`;
    document.getElementById('viewIssueBody').innerHTML = `
      <div class="row g-3">
        <div class="col-md-6">
          <div class="mb-1 text-muted small">Priority</div>
          <span class="badge ${PRIORITY_BADGE[issue.priority] || 'bg-secondary'} text-capitalize">${escHtml(issue.priority)}</span>
        </div>
        <div class="col-md-6">
          <div class="mb-1 text-muted small">Status</div>
          <span class="badge ${STATUS_BADGE[issue.status] || 'bg-secondary'}">${STATUS_LABEL[issue.status] || escHtml(issue.status)}</span>
        </div>
        <div class="col-md-6">
          <div class="mb-1 text-muted small">Reported By</div>
          <div>${escHtml(issue.reported_by) || '—'}</div>
        </div>
        <div class="col-md-6">
          <div class="mb-1 text-muted small">Page URL</div>
          <div>${escHtml(issue.page_url) || '—'}</div>
        </div>
        <div class="col-12">
          <div class="mb-1 text-muted small">Description</div>
          <div>${escHtml(issue.description) || '<em class="text-muted">None</em>'}</div>
        </div>
        ${issue.console_output ? `
        <div class="col-12">
          <div class="mb-1 d-flex justify-content-between align-items-center">
            <span class="text-muted small">Console Output</span>
            <button class="btn btn-xs btn-ghost-secondary" onclick="copyConsole(${id})">Copy</button>
          </div>
          <pre class="console-pre">${escHtml(issue.console_output)}</pre>
        </div>` : ''}
        ${issue.notes ? `
        <div class="col-12">
          <div class="mb-1 text-muted small">Notes</div>
          <div>${escHtml(issue.notes)}</div>
        </div>` : ''}
        <div class="col-12">
          <div class="text-muted small">Logged ${fmtDate(issue.created_at)}</div>
        </div>
      </div>
    `;
    document.getElementById('editFromViewBtn').onclick = () => openEditModal(id);
    new bootstrap.Modal(document.getElementById('viewIssueModal')).show();
  } catch (e) {
    alert('Failed to load issue: ' + e.message);
  }
}

// ── Actions ───────────────────────────────────────────────────────────────────

async function deleteIssue(id) {
  if (!confirm('Delete this issue? This cannot be undone.')) return;
  try {
    await apiCall(`/api/v1/dev-issues/${id}`, { method: 'DELETE' });
    loadIssues();
  } catch (e) {
    alert('Failed to delete issue: ' + e.message);
  }
}

async function pasteConsole() {
  try {
    const text = await navigator.clipboard.readText();
    document.getElementById('issueConsoleOutput').value = text;
  } catch {
    alert('Clipboard access denied. Please paste manually (Ctrl+V / Cmd+V).');
  }
}

async function copyConsole(id) {
  const pre = document.querySelector('.console-pre');
  if (!pre) return;
  try {
    await navigator.clipboard.writeText(pre.textContent);
    showToast('Console output copied!', 'success');
  } catch {
    alert('Clipboard write failed.');
  }
}

function showToast(message, type = 'info') {
  const el = document.createElement('div');
  el.className = `alert alert-${type} position-fixed`;
  el.style.cssText = 'top:1rem;right:1rem;z-index:9999;min-width:220px';
  el.textContent = message;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}
</script>
@endpush
