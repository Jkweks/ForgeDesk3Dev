@extends('layouts.app')

@section('title', 'Work Order Requests - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col"><div class="page-pretitle">Fabrication</div><h1 class="page-title">Work Order Requests</h1></div>
        <div class="col-auto ms-auto"><select class="form-select" id="fStatus"><option value="pending">Pending</option><option value="approved,rejected,withdrawn">Decided</option></select></div>
      </div>
    </div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div id="rqNotice" class="mb-3"></div>
      <div id="rqBody" class="row row-cards"></div>
    </div>
  </main>
</div>
<script>
  const esc = (s) => escapeHtml(String(s ?? ''));
  const notice = (m, t = 'info') => { rqNotice.innerHTML = m ? `<div class="alert alert-${t} mb-0">${esc(m)}</div>` : ''; };
  const post = (url, body) => authenticatedFetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) });

  async function load() {
    const rows = await authenticatedFetch('/work-order-requests?status=' + encodeURIComponent(fStatus.value));
    rqBody.innerHTML = rows.length ? rows.map(r => `<div class="col-md-6"><div class="card"><div class="card-body">
      <div class="d-flex align-items-center mb-2"><div><strong>${esc(r.job.job_number)} — ${esc(r.job.job_name)}</strong>
        <div class="text-muted small">Release ${esc(r.release_code || '—')} · requested by ${esc(r.requester ? (r.requester.name || r.requester.email) : '')}${r.requested_due_date ? ' · needed ' + esc(r.requested_due_date) : ''}</div></div>
        <span class="badge ms-auto bg-${r.status === 'pending' ? 'orange' : r.status === 'approved' ? 'green' : 'secondary'}-lt">${esc(r.status)}</span></div>
      ${r.scope ? `<p class="mb-2">${esc(r.scope)}</p>` : ''}${r.notes ? `<p class="text-muted small">${esc(r.notes)}</p>` : ''}
      <div class="mb-2">${r.checks.map(c => `<div class="small"><span class="${c.ok ? 'text-green' : (c.info ? 'text-muted' : 'text-red')}">${c.ok ? '✓' : (c.info ? '–' : '✗')}</span> ${esc(c.text)}</div>`).join('')}</div>
      <div class="mb-2">${r.files.map(f => `<div><a href="${location.origin}/api/v1/work-order-requests/${r.id}/files/${f.id}" target="_blank">${esc(f.label || f.file.original_name)}</a></div>`).join('')}</div>
      ${r.status === 'pending' ? `<div class="d-flex gap-2" data-permission="fabrication.work-orders.create"><button class="btn btn-primary btn-sm" onclick="approve(${r.id})">Approve and create work order</button><button class="btn btn-sm" onclick="reject(${r.id})">Reject</button></div>` : (r.decision_note ? `<div class="small"><em>${esc(r.decision_note)}</em></div>` : '')}
    </div></div></div>`).join('') : '<div class="col-12 text-muted">Nothing here.</div>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }
  async function approve(id) { try { const d = await post(`/work-order-requests/${id}/approve`, {}); notice(`Work order created (release ${d.release_number}).`, 'success'); load(); } catch (e) { notice(e.message || 'Could not approve', 'danger'); } }
  async function reject(id) { const n = prompt('Reason for rejecting (the requester will see this):'); if (!n) return; try { await post(`/work-order-requests/${id}/reject`, { decision_note: n }); load(); } catch (e) { notice(e.message, 'danger'); } }

  document.addEventListener('DOMContentLoaded', () => { fStatus.addEventListener('change', load); load().catch(console.error); });
</script>
@endsection
