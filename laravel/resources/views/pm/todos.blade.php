@extends('layouts.app')

@section('title', 'To-do - Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col"><div class="page-pretitle">Project Management</div><h1 class="page-title">To-do</h1></div>
        <div class="col-auto ms-auto"><button class="btn btn-primary" onclick="showNew()"><i class="ti ti-plus me-1"></i>New to-do</button></div>
      </div>
    </div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div class="card">
        <div class="card-header">
          <div class="row g-2 w-100">
            <div class="col-md-4"><select class="form-select" id="fJob"><option value="">All jobs</option></select></div>
            <div class="col-md-3"><label class="form-check form-switch m-0 pt-2"><input class="form-check-input" type="checkbox" id="fMine"><span class="form-check-label">Assigned to me</span></label></div>
            <div class="col-md-3"><label class="form-check form-switch m-0 pt-2"><input class="form-check-input" type="checkbox" id="fDone"><span class="form-check-label">Show completed</span></label></div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th class="w-1"></th><th>To-do</th><th>Job</th><th>Assigned</th><th>Due</th><th class="w-1"></th></tr></thead>
            <tbody id="tBody"><tr><td colspan="6" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="tModal" tabindex="-1">
  <div class="modal-dialog"><form class="modal-content" onsubmit="saveTodo(event)">
    <div class="modal-header"><h5 class="modal-title">New to-do</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label required">To-do</label><input class="form-control" id="tTitle" required></div>
      <div class="row g-2 mb-3"><div class="col-7"><label class="form-label">Job</label><select class="form-select" id="tJob"><option value="">No job</option></select></div>
        <div class="col-5"><label class="form-label">Due</label><input type="date" class="form-control" id="tDue"></div></div>
      <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" id="tNotes" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add</button></div>
  </form></div>
</div>

@include('partials.pm-modal-helpers')

<script>
  const esc = (s) => escapeHtml(String(s ?? ''));
  const todayStr = new Date().toISOString().slice(0, 10);

  async function load() {
    const q = new URLSearchParams();
    if (fJob.value) q.set('business_job_id', fJob.value);
    if (fMine.checked) q.set('mine', '1');
    if (fDone.checked) q.set('done', '1');
    const rows = await authenticatedFetch('/pm/todos?' + q);
    tBody.innerHTML = rows.length ? rows.map(t => {
      const due = (t.due_date || '').slice(0, 10), late = !t.completed_at && due && due < todayStr;
      return `<tr><td><input type="checkbox" class="form-check-input" ${t.completed_at ? 'checked' : ''} onchange="toggle(${t.id}, this.checked)"></td>
        <td class="${t.completed_at ? 'text-decoration-line-through text-muted' : ''}">${esc(t.title)}${t.notes ? `<div class="text-muted small">${esc(t.notes)}</div>` : ''}</td>
        <td>${t.job ? `<a href="/pm/jobs/${t.job.id}">${esc(t.job.job_number)}</a>` : ''}</td>
        <td>${esc(t.assignee ? (t.assignee.name || t.assignee.email) : '')}</td>
        <td class="${late ? 'text-danger fw-bold' : ''}">${esc(due)}</td>
        <td><button class="btn btn-sm btn-ghost-danger" onclick="del(${t.id})">&times;</button></td></tr>`;
    }).join('') : '<tr><td colspan="6" class="text-muted">Nothing here.</td></tr>';
  }

  async function toggle(id, done) { await authenticatedFetch(`/pm/todos/${id}/${done ? 'complete' : 'reopen'}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' }); load(); }
  async function del(id) { if (confirm('Delete this to-do?')) { await authenticatedFetch('/pm/todos/' + id, { method: 'DELETE' }); load(); } }
  function showNew() { document.querySelector('#tModal form').reset(); if (fJob.value) tJob.value = fJob.value; safeShowModal('tModal'); }
  async function saveTodo(e) {
    e.preventDefault();
    try {
      await authenticatedFetch('/pm/todos', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: tTitle.value, business_job_id: tJob.value ? +tJob.value : null, due_date: tDue.value || null, notes: tNotes.value || null }) });
      safeHideModal('tModal'); load();
    } catch (err) { alert(err.message || 'Could not add'); }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    try {
      const jobs = await authenticatedFetch('/pm/jobs');
      const opts = jobs.map(j => `<option value="${j.id}">${esc(j.job_number)} — ${esc(j.job_name)}</option>`).join('');
      fJob.innerHTML += opts; tJob.innerHTML += opts;
      const pre = new URLSearchParams(location.search).get('job'); if (pre) fJob.value = pre;
      [fJob, fMine, fDone].forEach(el => el.addEventListener('change', load));
      await load();
    } catch (e) { console.error(e); }
  });
</script>
@endsection
