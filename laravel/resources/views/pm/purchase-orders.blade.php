@extends('layouts.app')

@section('title', 'Job Purchase Orders - Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Project Management · Purchasing</div>
          <h1 class="page-title">Job Purchase Orders</h1>
        </div>
        <div class="col-auto ms-auto">
          <a class="btn btn-primary" data-permission="pm.jobs.manage" href="/pm/purchase-orders/new"><i class="ti ti-plus me-1"></i>New PO</a>
        </div>
      </div>
    </div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div class="card">
        <div class="card-header">
          <div class="row g-2 w-100">
            <div class="col-md-4"><select class="form-select" id="fJob"><option value="">All jobs</option></select></div>
            <div class="col-md-3"><select class="form-select" id="fStage"><option value="">All stages</option></select></div>
            <div class="col-md-3"><label class="form-check form-switch m-0 pt-2"><input class="form-check-input" type="checkbox" id="fVoided"><span class="form-check-label">Include voided</span></label></div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>PO #</th><th>Job</th><th>Vendor</th><th>Stage</th><th>Status</th><th class="text-end">Total</th><th>Rev</th></tr></thead>
            <tbody id="poBody"><tr><td colspan="7" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<script>
  const STAGES = { draft: 'Draft', 'submittal-request': 'Submittal request', 'material-release': 'Material release', 'subcontract-submittal-request': 'Subcontract submittal request', 'subcontract-material-release': 'Subcontract material release' };
  const money = (n) => '$' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const esc = (s) => escapeHtml(String(s ?? ''));

  async function load() {
    const q = new URLSearchParams();
    if (fJob.value) q.set('business_job_id', fJob.value);
    if (fStage.value) q.set('issue_stage', fStage.value);
    if (fVoided.checked) q.set('include_voided', '1');
    const page = await authenticatedFetch('/job-purchase-orders?' + q);
    const rows = page.data || [];
    document.getElementById('poBody').innerHTML = rows.length ? rows.map(p => `<tr>
      <td><a href="/pm/purchase-orders/${p.id}">${esc(p.po_number)}</a>${p.voided_at ? ' <span class="badge bg-red-lt">Voided</span>' : ''}${p.closed_at ? ' <span class="badge bg-secondary-lt">Closed</span>' : ''}</td>
      <td>${p.job ? esc(p.job.job_number) : ''}</td><td>${p.supplier ? esc(p.supplier.name) : ''}</td>
      <td>${esc(STAGES[p.issue_stage] || p.issue_stage)}</td><td>${esc(p.status)}</td>
      <td class="text-end">${money(p.total_amount)}</td><td>${p.revision_number}</td></tr>`).join('') : '<tr><td colspan="7" class="text-muted">No purchase orders.</td></tr>';
  }

  document.addEventListener('DOMContentLoaded', async () => {
    fStage.innerHTML += Object.entries(STAGES).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
    try {
      const jobs = await authenticatedFetch('/pm/jobs');
      fJob.innerHTML += jobs.map(j => `<option value="${j.id}">${esc(j.job_number)} — ${esc(j.job_name)}</option>`).join('');
      const pre = new URLSearchParams(location.search).get('job'); if (pre) fJob.value = pre;
      [fJob, fStage, fVoided].forEach(el => el.addEventListener('change', load));
      await load();
    } catch (e) { console.error(e); }
  });
</script>
@endsection
