@extends('layouts.app')

@section('title', 'Development - ForgeDesk')

@section('content')
<div class="page-wrapper">
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title"><i class="ti ti-bug me-2"></i>Development</h2>
        <div class="text-muted mt-1">Bugs, feature requests and notes. Admin only.</div>
      </div>
      <div class="col-auto ms-auto">
        <div class="btn-list">
          <button class="btn" id="devCopyMd" title="Copy the filtered list as Markdown to paste to Claude"><i class="ti ti-clipboard me-1"></i>Copy as Markdown</button>
          <button class="btn btn-primary" onclick="openDevFeedback()"><i class="ti ti-plus me-1"></i>New item</button>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div id="devDenied" class="alert alert-danger" style="display:none;">This page is only available to administrators.</div>
    <ul class="nav nav-tabs mb-3" id="devTabs" style="display:none;">
      <li class="nav-item"><a class="nav-link active" href="#" data-tab="tracker">Tracker</a></li>
      <li class="nav-item"><a class="nav-link" href="#" data-tab="rules"><i class="ti ti-ruler-measure me-1"></i>Cut-list rules</a></li>
    </ul>
    @include('development.cutlist-rules')
    <div id="devMain" style="display:none;">
      <div class="row row-cards mb-3">
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Open</div><div class="h2 mb-0" id="cntOpen">-</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body"><div class="subheader">In progress</div><div class="h2 mb-0" id="cntProg">-</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Active bugs</div><div class="h2 mb-0 text-danger" id="cntBugs">-</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Active features</div><div class="h2 mb-0 text-azure" id="cntFeat">-</div></div></div></div>
      </div>

      <div class="card">
        <div class="card-body border-bottom py-3">
          <div class="row g-2">
            <div class="col-md-4"><input type="search" class="form-control" id="fSearch" placeholder="Search title or description"></div>
            <div class="col-6 col-md-2">
              <select class="form-select" id="fType"><option value="">All types</option><option value="bug">Bugs</option><option value="feature">Features</option><option value="note">Notes</option></select>
            </div>
            <div class="col-6 col-md-3">
              <select class="form-select" id="fStatus">
                <option value="active">Open + in progress</option>
                <option value="">All statuses</option>
                <option value="open">Open</option><option value="in_progress">In progress</option>
                <option value="resolved">Resolved</option><option value="wont_fix">Won't fix</option>
              </select>
            </div>
            <div class="col-md-3">
              <select class="form-select" id="fPriority"><option value="">All priorities</option><option value="critical">Critical</option><option value="high">High</option><option value="normal">Normal</option><option value="low">Low</option><option value="future">Future</option></select>
            </div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table table-hover">
            <thead><tr><th>#</th><th>Type</th><th>Title</th><th>Priority</th><th>Status</th><th>Page</th><th>Reported</th></tr></thead>
            <tbody id="devRows"><tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<div class="modal modal-blur fade" id="devDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="dTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-3" id="dMeta"></div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Status</label>
            <select class="form-select" id="dStatus"><option value="open">Open</option><option value="in_progress">In progress</option><option value="resolved">Resolved</option><option value="wont_fix">Won't fix</option></select></div>
          <div class="col-md-4"><label class="form-label">Priority</label>
            <select class="form-select" id="dPriority"><option value="future">Future</option><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="critical">Critical</option></select></div>
          <div class="col-md-4"><label class="form-label">Type</label>
            <select class="form-select" id="dType"><option value="bug">Bug</option><option value="feature">Feature</option><option value="note">Note</option></select></div>
          <div class="col-12"><label class="form-label">Title</label><input type="text" class="form-control" id="dTitleInput" maxlength="255"></div>
          <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" id="dDescription" rows="5"></textarea></div>
          <div class="col-12"><label class="form-label">Console output</label><textarea class="form-control font-monospace small" id="dConsole" rows="6"></textarea></div>
          <div class="col-12"><label class="form-label">Resolution notes</label><textarea class="form-control" id="dResolution" rows="3" placeholder="What was changed / why it was closed"></textarea></div>
        </div>
        <div id="dError" class="alert alert-danger mt-3 mb-0" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger me-auto" id="dDelete"><i class="ti ti-trash me-1"></i>Delete</button>
        <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="dSave">Save</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const $ = (id) => document.getElementById(id);
  const TYPE = { bug: ['bg-red-lt', 'Bug'], feature: ['bg-azure-lt', 'Feature'], note: ['bg-secondary-lt', 'Note'] };
  const PRIO = { critical: 'bg-red', high: 'bg-orange', normal: 'bg-secondary-lt', low: 'bg-secondary-lt', future: 'bg-purple-lt' };
  const STAT = { open: ['bg-yellow-lt', 'Open'], in_progress: ['bg-blue-lt', 'In progress'], resolved: ['bg-green-lt', 'Resolved'], wont_fix: ['bg-secondary-lt', "Won't fix"] };
  let items = [], current = null, detailModal = null, timer = null;

  const params = () => {
    const p = new URLSearchParams();
    [['search', 'fSearch'], ['type', 'fType'], ['status', 'fStatus'], ['priority', 'fPriority']].forEach(([k, id]) => { if ($(id).value) p.set(k, $(id).value); });
    // An empty status filter means "all"; send it explicitly so the export doesn't default to active.
    if (!$('fStatus').value) p.set('status', '');
    return p.toString();
  };

  async function load() {
    const res = await apiCall('/dev-items?' + params());
    if (!res.ok) { $('devRows').innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Failed to load (HTTP ' + res.status + ')</td></tr>'; return; }
    const body = await res.json();
    items = body.data;
    $('cntOpen').textContent = body.counts.open;
    $('cntProg').textContent = body.counts.in_progress;
    $('cntBugs').textContent = body.counts.bugs_active;
    $('cntFeat').textContent = body.counts.features_active;
    render();
  }

  function render() {
    if (!items.length) { $('devRows').innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Nothing here.</td></tr>'; return; }
    $('devRows').innerHTML = items.map((i) => {
      const t = TYPE[i.type] || ['bg-secondary-lt', i.type], s = STAT[i.status] || ['bg-secondary-lt', i.status];
      return '<tr role="button" data-id="' + i.id + '">'
        + '<td class="text-muted">' + i.id + '</td>'
        + '<td><span class="badge ' + t[0] + '">' + t[1] + '</span>' + (i.source === 'field_app' ? ' <i class="ti ti-device-mobile text-muted" title="Reported from the field app"></i>' : '') + '</td>'
        + '<td>' + escapeHtml(i.title) + (i.console_output ? ' <i class="ti ti-terminal-2 text-muted" title="Has console output"></i>' : '') + '</td>'
        + '<td><span class="badge ' + (PRIO[i.priority] || '') + '">' + escapeHtml(i.priority) + '</span></td>'
        + '<td><span class="badge ' + s[0] + '">' + s[1] + '</span></td>'
        + '<td class="text-muted small">' + escapeHtml(i.page_url || '') + '</td>'
        + '<td class="text-muted small">' + new Date(i.created_at).toLocaleDateString() + (i.creator ? ' · ' + escapeHtml(i.creator.name) : '') + '</td>'
        + '</tr>';
    }).join('');
  }

  $('devRows').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-id]');
    if (!tr) return;
    current = items.find((i) => i.id == tr.dataset.id);
    if (!current) return;
    $('dTitle').textContent = '#' + current.id + ' ' + current.title;
    $('dMeta').textContent = [current.page_url, current.user_agent, 'Reported ' + new Date(current.created_at).toLocaleString(), current.resolved_at ? 'Closed ' + new Date(current.resolved_at).toLocaleString() : ''].filter(Boolean).join(' · ');
    $('dStatus').value = current.status; $('dPriority').value = current.priority; $('dType').value = current.type;
    $('dTitleInput').value = current.title; $('dDescription').value = current.description || '';
    $('dConsole').value = current.console_output || ''; $('dResolution').value = current.resolution_notes || '';
    $('dError').style.display = 'none';
    detailModal = detailModal || new bootstrap.Modal($('devDetailModal'));
    detailModal.show();
  });

  $('dSave').addEventListener('click', async () => {
    try {
      const res = await apiCall('/dev-items/' + current.id, { method: 'PUT', body: JSON.stringify({
        status: $('dStatus').value, priority: $('dPriority').value, type: $('dType').value,
        title: $('dTitleInput').value.trim(), description: $('dDescription').value || null,
        console_output: $('dConsole').value || null, resolution_notes: $('dResolution').value || null,
      }) });
      if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'HTTP ' + res.status);
      detailModal.hide(); load();
    } catch (ex) { $('dError').textContent = ex.message; $('dError').style.display = 'block'; }
  });

  $('dDelete').addEventListener('click', async () => {
    if (!confirm('Delete item #' + current.id + '? This cannot be undone.')) return;
    const res = await apiCall('/dev-items/' + current.id, { method: 'DELETE' });
    if (res.ok) { detailModal.hide(); load(); } else { $('dError').textContent = 'Delete failed (HTTP ' + res.status + ')'; $('dError').style.display = 'block'; }
  });

  $('devCopyMd').addEventListener('click', async () => {
    const res = await apiCall('/dev-items/export?' + params(), { headers: { Accept: 'text/markdown' } });
    if (!res.ok) { showNotification('Export failed', 'danger'); return; }
    try { await navigator.clipboard.writeText(await res.text()); showNotification('Copied to clipboard', 'success'); }
    catch (e) { showNotification('Clipboard blocked by the browser', 'danger'); }
  });

  ['fType', 'fStatus', 'fPriority'].forEach((id) => $(id).addEventListener('change', load));
  $('fSearch').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 300); });
  window.onDevItemSaved = load;

  (window.sessionReady || Promise.resolve()).then(() => {
    if (typeof isAdmin === 'function' && !isAdmin()) { $('devDenied').style.display = 'block'; return; }
    $('devMain').style.display = 'block';
    $('devTabs').style.display = 'flex';
    load();
    const show = (tab) => {
      document.querySelectorAll('#devTabs [data-tab]').forEach((a) => a.classList.toggle('active', a.dataset.tab === tab));
      $('devMain').style.display = tab === 'tracker' ? 'block' : 'none';
      $('crPane').style.display = tab === 'rules' ? 'block' : 'none';
      if (tab === 'rules') window.cutlistRulesShow();
      history.replaceState(null, '', tab === 'rules' ? '#rules' : location.pathname);
    };
    $('devTabs').addEventListener('click', (e) => { const a = e.target.closest('[data-tab]'); if (a) { e.preventDefault(); show(a.dataset.tab); } });
    if (location.hash === '#rules') show('rules');
  });
})();
</script>
@endpush
