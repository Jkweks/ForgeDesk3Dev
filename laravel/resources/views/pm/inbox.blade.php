@extends('layouts.app')

@section('title', 'File Inbox - Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl"><div class="page-pretitle">Project Management</div><h1 class="page-title">File Inbox</h1></div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div id="ibNotice" class="mb-3"></div>
      <div class="card">
        <div class="card-header"><h3 class="card-title">Files that need a decision</h3></div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>File</th><th>Why it is here</th><th style="width:200px">Job</th><th style="width:170px">What it is</th><th class="w-1"></th></tr></thead>
            <tbody id="ibBody"><tr><td colspan="5" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
        <div class="card-footer text-muted small">Files found on the F: drive that could not be placed automatically. Nothing is imported until you say what it is and which job it belongs to.</div>
      </div>
    </div>
  </main>
</div>
<script>
  const esc = (s) => escapeHtml(String(s ?? ''));
  const TYPES = { bid_estimate: 'BidEstimate', drawing_set: 'Shop drawing set', submittal_document: 'Submittal document', other: 'Other' };
  let jobs = [];
  const notice = (m, t = 'info') => { ibNotice.innerHTML = m ? `<div class="alert alert-${t} mb-0">${esc(m)}</div>` : ''; };

  async function load() {
    const rows = await authenticatedFetch('/pm/inbox');
    const jobOpts = '<option value="">Job…</option>' + jobs.map(j => `<option value="${j.id}">${esc(j.job_number)} — ${esc(j.job_name)}</option>`).join('');
    ibBody.innerHTML = rows.length ? rows.map(f => `<tr><td>${esc(f.original_name)}<div class="text-muted small">${esc(f.fdrive_path || '')}</div></td>
      <td>${f.status === 'failed' ? '<span class="badge bg-red-lt me-1">Failed</span>' : ''}${esc(f.error || '')}</td>
      <td><select class="form-select form-select-sm" id="job-${f.id}">${jobOpts}</select></td>
      <td><select class="form-select form-select-sm" id="type-${f.id}">${Object.entries(TYPES).map(([k, v]) => `<option value="${k}" ${k === f.doc_type ? 'selected' : ''}>${v}</option>`).join('')}</select></td>
      <td><button class="btn btn-sm btn-primary" onclick="assign(${f.id})">File it</button></td></tr>`).join('')
      : '<tr><td colspan="5" class="text-muted">The inbox is empty.</td></tr>';
    rows.forEach(f => { if (f.business_job_id) document.getElementById('job-' + f.id).value = f.business_job_id; });
  }
  async function assign(id) {
    const job = document.getElementById('job-' + id).value;
    if (!job) { notice('Choose the job first.', 'warning'); return; }
    try {
      const d = await authenticatedFetch(`/pm/inbox/${id}/assign`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ business_job_id: +job, doc_type: document.getElementById('type-' + id).value }) });
      notice({ imported: 'Imported.', queued: 'Queued for reading.', filed: 'Filed against the job.', failed: 'Filed, but the import failed. It stays here with the error.' }[d.action] || 'Done.', d.action === 'failed' ? 'danger' : 'success');
      load();
    } catch (e) { notice(e.message || 'Could not file it', 'danger'); }
  }
  document.addEventListener('DOMContentLoaded', async () => { try { jobs = await authenticatedFetch('/pm/jobs'); await load(); } catch (e) { console.error(e); } });
</script>
@endsection
