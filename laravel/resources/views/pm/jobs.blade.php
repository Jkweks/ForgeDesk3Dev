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
            <tbody id="pmJobsBody"><tr><td colspan="6" class="text-muted">Loading...</td></tr></tbody>
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
  let pmJobs = [];

  async function loadJobs() {
    const params = new URLSearchParams(location.search);
    const status = document.getElementById('pmStatus').value || params.get('status') || '';
    if (status) document.getElementById('pmStatus').value = status;
    const q = document.getElementById('pmSearch').value;
    pmJobs = await authenticatedFetch('/pm/jobs?' + new URLSearchParams({ ...(status && { status }), ...(q && { search: q }) }));
    render();
  }

  function render() {
    const body = document.getElementById('pmJobsBody');
    if (!pmJobs.length) { body.innerHTML = '<tr><td colspan="6" class="text-muted">No jobs.</td></tr>'; return; }
    body.innerHTML = pmJobs.map(j => `<tr>
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
    document.getElementById('pmStatus').addEventListener('change', loadJobs);
    let t; document.getElementById('pmSearch').addEventListener('input', () => { clearTimeout(t); t = setTimeout(loadJobs, 250); });
    loadJobs().catch(console.error);
  });
</script>
@endsection
