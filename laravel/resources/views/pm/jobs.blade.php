@extends('layouts.app')

@section('title', 'Jobs - Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Project Management</div>
          <h1 class="page-title">Jobs</h1>
        </div>
        <div class="col-auto ms-auto">
          <button class="btn btn-primary" data-permission="pm.jobs.manage" onclick="showNewBid()"><i class="ti ti-plus me-1"></i>New Bid</button>
        </div>
      </div>
    </div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div class="row row-cards mb-3" id="pmStageCards">
        <div class="col-6 col-lg-3"><a class="card card-sm card-link" data-stage="bidding" onclick="pickStage('bidding')"><div class="card-body">
          <div class="subheader">Bidding</div><div class="h1 mb-0 text-azure" id="cntBidding">-</div></div></a></div>
        <div class="col-6 col-lg-3"><a class="card card-sm card-link" data-stage="won" onclick="pickStage('won')"><div class="card-body">
          <div class="subheader">Won, not yet active</div><div class="h1 mb-0 text-success" id="cntWon">-</div></div></a></div>
        <div class="col-6 col-lg-3"><a class="card card-sm card-link" data-stage="active" onclick="pickStage('active')"><div class="card-body">
          <div class="subheader">Active</div><div class="h1 mb-0 text-primary" id="cntActive">-</div></div></a></div>
        <div class="col-6 col-lg-3"><a class="card card-sm card-link" data-stage="" onclick="pickStage('')"><div class="card-body">
          <div class="subheader">All jobs</div><div class="h1 mb-0" id="cntAll">-</div></div></a></div>
      </div>
      <style>
        .pm-row { cursor: pointer; }
        .pm-row:hover > td { background: var(--tblr-bg-surface-secondary); }
        .pm-row:focus-visible { outline: 2px solid var(--tblr-primary); outline-offset: -2px; }
        #pmStageCards .card-link.active { border-color: var(--tblr-primary); box-shadow: 0 0 0 1px var(--tblr-primary); }
      </style>
      <div class="card">
        <div class="card-header">
          <div class="row g-2 w-100">
            <div class="col-md-4"><input type="search" class="form-control" id="pmSearch" placeholder="Search job number, name or customer"></div>
            <div class="col-md-3">
              <select class="form-select" id="pmStatus">
                <option value="">All stages</option>
                <option value="bidding">Bidding</option><option value="won">Won</option><option value="lost">Lost</option>
                <option value="active">Active</option><option value="on_hold">On hold</option><option value="completed">Completed</option>
              </select>
            </div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Job #</th><th>Name</th><th>Customer</th><th>Stage</th><th>Source</th><th class="w-1"></th></tr></thead>
            <tbody id="pmJobsBody" onclick="rowClick(event)" onkeydown="rowKey(event)"><tr><td colspan="6" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="newBidModal" tabindex="-1">
  <div class="modal-dialog"><form class="modal-content" onsubmit="saveBid(event)">
    <div class="modal-header"><h5 class="modal-title">New Bid</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label">Job Number</label><input class="form-control" id="bidNumber" required></div>
      <div class="mb-3"><label class="form-label">Job Name</label><input class="form-control" id="bidName" required></div>
      <div class="mb-3"><label class="form-label">Customer</label><input class="form-control" id="bidCustomer"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create bid</button></div>
  </form></div>
</div>

@include('partials.pm-modal-helpers')

<script>
  const NEXT = { bidding: [['won','Mark won'],['lost','Mark lost']], won: [['active','Activate (hand to Fabrication)'],['lost','Mark lost']], lost: [['bidding','Reopen bid']] };
  const BADGE = { bidding: 'bg-azure-lt', won: 'bg-green-lt', lost: 'bg-red-lt', active: 'bg-blue-lt' };
  let pmJobs = [], pmAll = [];

  // the whole list is fetched once (the API caps it at 500) and filtered here, so the stage cards always show true counts
  async function loadJobs() {
    pmAll = await authenticatedFetch('/pm/jobs');
    applyFilters();
  }

  function applyFilters() {
    const status = document.getElementById('pmStatus').value, q = document.getElementById('pmSearch').value.trim().toLowerCase();
    pmJobs = pmAll.filter(j => (!status || j.status === status)
      && (!q || [j.job_number, j.job_name, j.customer_name].some(v => String(v || '').toLowerCase().includes(q))));
    const n = (st) => pmAll.filter(j => j.status === st).length;
    cntBidding.textContent = n('bidding'); cntWon.textContent = n('won'); cntActive.textContent = n('active'); cntAll.textContent = pmAll.length;
    document.querySelectorAll('#pmStageCards [data-stage]').forEach(c => c.classList.toggle('active', c.dataset.stage === status));
    render();
  }

  function pickStage(st) { document.getElementById('pmStatus').value = st; applyFilters(); }

  // anywhere on a row opens the job (Ctrl/Cmd-click: a new tab); the stage buttons and the job link do their own thing
  function rowClick(ev) {
    if (ev.target.closest('button, a, input, select, [data-noclick]')) return;
    const tr = ev.target.closest('tr[data-href]'); if (!tr) return;
    if (ev.ctrlKey || ev.metaKey) window.open(tr.dataset.href, '_blank'); else location.href = tr.dataset.href;
  }
  function rowKey(ev) {
    if (ev.key !== 'Enter' || ev.target.closest('button, a, input, select')) return;
    const tr = ev.target.closest('tr[data-href]'); if (tr) location.href = tr.dataset.href;
  }

  function render() {
    const body = document.getElementById('pmJobsBody');
    if (!pmJobs.length) { body.innerHTML = '<tr><td colspan="6" class="text-muted">No jobs.</td></tr>'; return; }
    body.innerHTML = pmJobs.map(j => `<tr class="pm-row" tabindex="0" data-href="/pm/jobs/${j.id}" title="Open ${escapeHtml(j.job_number)}">
      <td><a href="/pm/jobs/${j.id}">${escapeHtml(j.job_number)}</a></td><td>${escapeHtml(j.job_name)}</td><td>${escapeHtml(j.customer_name || '')}</td>
      <td><span class="badge ${BADGE[j.status] || 'bg-secondary-lt'}">${escapeHtml(j.status_label || j.status)}</span></td>
      <td class="text-muted">${j.origin === 'pm' ? 'PM bid' : 'Fabrication'}</td>
      <td class="text-nowrap" data-permission="pm.jobs.manage">${(NEXT[j.status] || []).map(([s, l]) =>
        `<button class="btn btn-sm me-1" onclick="moveJob(${j.id}, '${s}')">${l}</button>`).join('')}</td></tr>`).join('');
  }

  async function moveJob(id, stage) {
    if (stage === 'active' && !confirm('Activate this job? Fabrication will be able to see it.')) return;
    try {
      await authenticatedFetch(`/pm/jobs/${id}/stage`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ stage }) });
      loadJobs();
    } catch (e) { showNotification(e.message || 'Could not change stage', 'danger'); }
  }

  function showNewBid() { document.querySelector('#newBidModal form').reset(); safeShowModal('newBidModal'); }

  async function saveBid(e) {
    e.preventDefault();
    try {
      await authenticatedFetch('/pm/jobs', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ job_number: bidNumber.value, job_name: bidName.value, customer_name: bidCustomer.value || null }) });
      safeHideModal('newBidModal'); loadJobs();
    } catch (err) { showNotification(err.message || 'Could not create bid', 'danger'); }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const fromUrl = new URLSearchParams(location.search).get('status');
    if (fromUrl) document.getElementById('pmStatus').value = fromUrl;
    document.getElementById('pmStatus').addEventListener('change', applyFilters);
    document.getElementById('pmSearch').addEventListener('input', applyFilters);
    loadJobs().catch(console.error);
  });
</script>
@endsection
