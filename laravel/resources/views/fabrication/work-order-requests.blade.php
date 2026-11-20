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
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><form class="modal-content" onsubmit="saveEdit(event)">
    <div class="modal-header"><h5 class="modal-title" id="editTitle">Edit request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2 mb-3"><div class="col-6"><label class="form-label">Release code</label><input class="form-control" id="edCode"></div>
        <div class="col-6"><label class="form-label">Needed by</label><input type="date" class="form-control" id="edDue"></div></div>
      <div class="mb-3" id="edElevBox" style="display:none"><label class="form-label">Elevations released from <span id="edDrawing"></span></label>
        <div class="table-responsive border rounded"><table class="table table-sm table-vcenter mb-0">
          <thead><tr><th class="w-1"></th><th>Elevation</th><th>System / finish</th><th>R.O. W × H</th><th>Quantity</th></tr></thead><tbody id="edElevs"></tbody></table></div>
        <div class="small text-muted mt-1">Untick an elevation to take it out of the request. A quantity cannot exceed what the drawing calls for, less what other requests already cover.</div></div>
      <div class="mb-3"><label class="form-label">Scope</label><textarea class="form-control" id="edScope" rows="3"></textarea></div>
      <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" id="edNotes" rows="2"></textarea></div>
      <div class="mb-1"><label class="form-label">Why you changed it <span class="text-muted">(sent to the project manager with the changes)</span></label><textarea class="form-control" id="edWhy" rows="2" placeholder="e.g. Only 2 of the 4 can be built this release, the glass for the rest is not in"></textarea></div>
      <div id="edError" class="text-danger small mt-2"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save changes and notify the PM</button></div>
  </form></div>
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
      ${r.drawing_set ? `<div class="mb-2"><div class="small text-muted">Drawing: ${esc(r.drawing_set.file ? r.drawing_set.file.original_name : 'drawing set')}</div>
        ${(r.elevations || []).map(l => `<span class="badge bg-azure-lt me-1" title="${esc([l.system, l.finish].filter(Boolean).join(' · '))}">${esc(l.label)} × ${l.quantity}${l.total_reqd ? ' of ' + l.total_reqd : ''}</span>`).join('')}</div>` : ''}
      ${r.scope ? `<p class="mb-2">${esc(r.scope)}</p>` : ''}${r.notes ? `<p class="text-muted small">${esc(r.notes)}</p>` : ''}
      <div class="mb-2">${r.checks.map(c => `<div class="small"><span class="${c.ok ? 'text-green' : (c.info ? 'text-muted' : 'text-red')}">${c.ok ? '✓' : (c.info ? '–' : '✗')}</span> ${esc(c.text)}</div>`).join('')}</div>
      <div class="mb-2">${r.files.map(f => `<div><a href="${location.origin}/api/v1/work-order-requests/${r.id}/files/${f.id}" target="_blank">${esc(f.label || f.file.original_name)}</a></div>`).join('')}</div>
      ${(r.changes || []).length ? `<div class="small text-muted mb-2"><i class="ti ti-pencil"></i> Edited ${r.changes.length} time${r.changes.length === 1 ? '' : 's'} — last by ${esc(r.changes[0].user ? r.changes[0].user.name : 'Fabrication')}: ${esc(r.changes[0].summary.split('\n').join('; '))}</div>` : ''}
      ${r.status === 'pending' ? `<div class="d-flex gap-2" data-permission="fabrication.work-orders.create"><button class="btn btn-primary btn-sm" onclick="approve(${r.id})">Approve and create work order</button><button class="btn btn-sm" onclick="showEdit(${r.id})">Edit</button><button class="btn btn-sm" onclick="reject(${r.id})">Reject</button></div>` : (r.decision_note ? `<div class="small"><em>${esc(r.decision_note)}</em></div>` : '')}
    </div></div></div>`).join('') : '<div class="col-12 text-muted">Nothing here.</div>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }
  // ---- edit a pending request before accepting it ----
  let editing = null, editRows = [];
  const frac = (v) => { if (v == null) return '—'; v = Number(v); const w = Math.floor(v), q = Math.round((v - w) * 16), W = q === 16 ? w + 1 : w, Q = q === 16 ? 0 : q;
    if (!Q) return W + '"'; let n = Q, d = 16; while (n % 2 === 0) { n /= 2; d /= 2; } return (W ? W + ' ' : '') + n + '/' + d + '"'; };
  async function showEdit(id) {
    const rows = await authenticatedFetch('/work-order-requests?status=pending');
    editing = rows.find(r => r.id === id); if (!editing) return;
    editTitle.textContent = `Edit request — ${editing.job.job_number} ${editing.job.job_name}`;
    edCode.value = editing.release_code || ''; edDue.value = (editing.requested_due_date || '').slice(0, 10);
    edScope.value = editing.scope || ''; edNotes.value = editing.notes || ''; edWhy.value = ''; edError.textContent = '';
    edElevBox.style.display = editing.drawing_set ? '' : 'none';
    edElevs.innerHTML = '';
    if (editing.drawing_set) {
      edDrawing.textContent = editing.drawing_set.file ? editing.drawing_set.file.original_name : 'the drawing';
      editRows = await authenticatedFetch(`/work-order-requests/${id}/available-elevations`);
      edElevs.innerHTML = editRows.map(e => { const on = e.in_request != null, max = e.free;
        return `<tr><td><input type="checkbox" class="form-check-input" id="edE${e.id}" ${on ? 'checked' : ''} ${(max === 0 && !on) ? 'disabled' : ''} onchange="edToggle(${e.id})"></td>
          <td><label for="edE${e.id}" class="mb-0"><strong>${esc(e.label)}</strong><div class="small text-muted">${esc(e.arch_ref || '')}</div></label></td>
          <td>${esc([e.system, e.finish].filter(Boolean).join(' · ') || '—')}</td><td>${frac(e.ro_w)} × ${frac(e.ro_h)}</td>
          <td style="width:170px"><div class="input-group input-group-sm"><input type="number" class="form-control" id="edQ${e.id}" min="1" ${max != null ? `max="${max}"` : ''} value="${e.in_request ?? (max ?? 1)}" ${on ? '' : 'disabled'}>${max != null ? `<span class="input-group-text">of ${max}</span>` : ''}</div></td></tr>`; }).join('')
        || '<tr><td colspan="5" class="text-muted">This drawing has no approved elevations.</td></tr>';
    }
    bootstrap.Modal.getOrCreateInstance(editModal).show();
  }
  function edToggle(id) { document.getElementById('edQ' + id).disabled = !document.getElementById('edE' + id).checked; }
  async function saveEdit(ev) {
    ev.preventDefault(); edError.textContent = '';
    const body = { release_code: edCode.value || null, requested_due_date: edDue.value || null, scope: edScope.value || null, notes: edNotes.value || null, change_note: edWhy.value || null };
    if (editing.drawing_set) body.elevations = editRows.filter(e => document.getElementById('edE' + e.id).checked).map(e => ({ drawing_elevation_id: e.id, quantity: +document.getElementById('edQ' + e.id).value }));
    try {
      await authenticatedFetch(`/work-order-requests/${editing.id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      bootstrap.Modal.getOrCreateInstance(editModal).hide(); notice('Changes saved and the project manager has been told.', 'success'); load();
    } catch (e) { edError.textContent = e.message || 'Could not save the changes'; }
  }
  async function approve(id) { try { const d = await post(`/work-order-requests/${id}/approve`, {}); notice(`Work order created (release ${d.release_number}).`, 'success'); load(); } catch (e) { notice(e.message || 'Could not approve', 'danger'); } }
  async function reject(id) { const n = prompt('Reason for rejecting (the requester will see this):'); if (!n) return; try { await post(`/work-order-requests/${id}/reject`, { decision_note: n }); load(); } catch (e) { notice(e.message, 'danger'); } }

  document.addEventListener('DOMContentLoaded', () => { fStatus.addEventListener('change', load); load().catch(console.error); });
</script>
@endsection
