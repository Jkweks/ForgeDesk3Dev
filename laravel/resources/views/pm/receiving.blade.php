@extends('layouts.app')

@section('title', 'Receiving - Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl"><div class="page-pretitle">Project Management · Purchasing</div><h1 class="page-title">Receiving</h1></div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div id="rNotice" class="mb-3"></div>
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Still expected</h3>
          <div class="card-actions"><select class="form-select form-select-sm" id="fJob"><option value="">All jobs</option></select></div></div>
        <div id="outBody" class="list-group list-group-flush"><div class="list-group-item text-muted">Loading...</div></div>
        <div class="card-footer text-muted small">Records that material arrived. It does not change stock.</div>
      </div>
      <div class="card">
        <div class="card-header"><h3 class="card-title">Flagged on arrival</h3></div>
        <div class="table-responsive"><table class="table table-vcenter card-table">
          <thead><tr><th>PO</th><th>Vendor</th><th>Line</th><th class="text-end">Qty</th><th>Date</th><th>Notes</th></tr></thead>
          <tbody id="flagBody"></tbody></table></div>
      </div>
    </div>
  </main>
</div>
<script>
  const esc = (s) => escapeHtml(String(s ?? ''));
  const notice = (m, t = 'info') => { rNotice.innerHTML = m ? `<div class="alert alert-${t} mb-0">${esc(m)}</div>` : ''; };

  async function load() {
    const q = fJob.value ? '?business_job_id=' + fJob.value : '';
    const pos = await authenticatedFetch('/pm/receiving/outstanding' + q);
    outBody.innerHTML = pos.length ? pos.map(p => `<div class="list-group-item">
      <div class="d-flex align-items-center mb-2"><a href="/pm/purchase-orders/${p.id}" class="fw-bold me-2">${esc(p.po_number)}</a>
        <span class="text-muted me-2">${esc(p.supplier)} · ${p.job ? esc(p.job.job_number) : ''}</span>
        ${p.expected_date ? `<span class="badge ${p.overdue ? 'bg-red-lt' : 'bg-blue-lt'}">Expected ${esc(p.expected_date)}</span>` : ''}
        <button class="btn btn-sm btn-primary ms-auto" data-permission="orders.receive" onclick="receivePo(${p.id})">Receive entered</button></div>
      ${p.lines.map(l => `<div class="row g-2 align-items-center mb-1"><div class="col">${esc(l.description)}</div>
        <div class="col-auto text-muted">${l.quantity_received} / ${l.quantity_ordered}</div>
        <div class="col-auto" style="width:110px"><input type="number" min="0" step="1" class="form-control form-control-sm" data-po="${p.id}" data-line="${l.id}" placeholder="Qty"></div></div>`).join('')}
    </div>`).join('') : '<div class="list-group-item text-muted">Nothing outstanding.</div>';
    const flags = await authenticatedFetch('/pm/receiving/flagged');
    flagBody.innerHTML = flags.length ? flags.map(f => `<tr><td><a href="/pm/purchase-orders/${f.po_id}">${esc(f.po_number)}</a></td><td>${esc(f.supplier)}</td><td>${esc(f.line)}</td><td class="text-end">${f.quantity}</td><td>${esc(f.received_date)}</td><td>${esc(f.notes)}</td></tr>`).join('')
      : '<tr><td colspan="6" class="text-muted">None.</td></tr>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  async function receivePo(id) {
    const receipts = [...document.querySelectorAll(`[data-po="${id}"]`)].map(el => ({ item_id: +el.dataset.line, quantity: parseInt(el.value) || 0 })).filter(r => r.quantity > 0);
    if (!receipts.length) { notice('Enter a quantity on at least one line.', 'warning'); return; }
    try { await authenticatedFetch(`/job-purchase-orders/${id}/receive`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ receipts }) }); notice('Receipt recorded.', 'success'); load(); }
    catch (e) { notice(e.message || 'Could not record the receipt', 'danger'); }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    try {
      const jobs = await authenticatedFetch('/pm/jobs');
      fJob.innerHTML += jobs.map(j => `<option value="${j.id}">${esc(j.job_number)} — ${esc(j.job_name)}</option>`).join('');
      fJob.addEventListener('change', load);
      await load();
    } catch (e) { console.error(e); }
  });
</script>
@endsection
