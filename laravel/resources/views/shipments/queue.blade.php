@extends('layouts.app')

@section('title', 'Shipping Queue - ForgeDesk')

@section('styles')
.sq-grid { display:grid; grid-template-columns:repeat(7, minmax(150px, 1fr)); gap:6px; min-width:1100px; }
.sq-head { text-align:center; font-weight:600; padding:.25rem; background:var(--tblr-bg-surface-secondary); border-radius:var(--tblr-border-radius-sm); }
.sq-head.today { background:var(--tblr-primary-lt); }
.sq-day { min-height:9rem; padding:4px; border:1px solid var(--tblr-border-color); border-radius:var(--tblr-border-radius-sm); background:var(--tblr-bg-surface); }
.sq-day.weekend { background:var(--tblr-bg-surface-secondary); }
.sq-day.today { background:var(--tblr-primary-lt); }
.sq-day.past { background:var(--tblr-bg-surface-secondary); }
.sq-day.drop-hover, .sq-panel.drop-hover { outline:2px dashed var(--tblr-primary); outline-offset:-2px; background:var(--tblr-primary-lt); }
.sq-card { display:block; padding:.3rem .45rem; margin-bottom:4px; border:1px solid var(--tblr-border-color); border-left:4px solid var(--tblr-secondary); border-radius:var(--tblr-border-radius-sm); background:var(--tblr-bg-surface); color:inherit; text-decoration:none; font-size:.8rem; line-height:1.25; }
.sq-card[draggable=true] { cursor:grab; }
.sq-card.dragging { opacity:.35; }
.sq-card.st-draft { border-left-color:var(--tblr-secondary); }
.sq-card.st-pre_check_done { border-left-color:var(--tblr-blue); }
.sq-card.st-shipped { border-left-color:var(--tblr-cyan); }
.sq-card.st-awaiting_verification { border-left-color:var(--tblr-orange); }
.sq-card.st-delivered { border-left-color:var(--tblr-success); }
.sq-card.st-delivered_issues { border-left-color:var(--tblr-danger); }
.sq-card.late { background:var(--tblr-danger-lt); }
.sq-card strong, .sq-card small { display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.sq-card small { color:var(--tblr-secondary-color); }
.sq-card.req { border-style:dashed; border-left-style:dashed; border-left-color:var(--tblr-yellow); background:var(--tblr-yellow-lt); cursor:context-menu; }
.sq-ctx { position:fixed; z-index:2000; min-width:12rem; padding:.25rem 0; background:var(--tblr-bg-surface); border:1px solid var(--tblr-border-color); border-radius:var(--tblr-border-radius); box-shadow:var(--tblr-box-shadow-dropdown, 0 4px 16px rgba(0,0,0,.2)); }
.sq-ctx a { display:block; padding:.35rem .9rem; color:inherit; text-decoration:none; font-size:.875rem; }
.sq-ctx a:hover { background:var(--tblr-bg-surface-secondary); }
.sq-panel { padding:.5rem; border:1px dashed var(--tblr-border-color); border-radius:var(--tblr-border-radius-sm); margin-bottom:.75rem; }
.sq-panel .cards { display:flex; flex-wrap:wrap; gap:6px; }
.sq-panel .cards .sq-card { width:14rem; margin:0; }
@endsection

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Shipping</div>
          <h2 class="page-title">Shipping Queue</h2>
        </div>
        <div class="col-auto ms-auto d-flex gap-2 align-items-center">
          <span class="text-muted small" id="sq-updated"></span>
          <a class="btn btn-outline-secondary" href="/shipments"><i class="ti ti-list me-1"></i>All shipments</a>
          <button class="btn btn-primary" data-permission="shipments.manage" onclick="shipmentForm(null)"><i class="ti ti-plus me-1"></i>New shipment</button>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="sqShift(-7)"><i class="ti ti-chevron-left"></i></button>
        <input type="date" id="sq-start" class="form-control form-control-sm" style="width:auto" onchange="sqLoad()">
        <button class="btn btn-sm btn-outline-secondary" onclick="sqShift(7)"><i class="ti ti-chevron-right"></i></button>
        <button class="btn btn-sm btn-outline-secondary" onclick="sqToday()">Today</button>
        <input type="text" id="sq-search" class="form-control form-control-sm" style="width:14rem" placeholder="Search shipment, job, destination…" oninput="sqRender()" autocomplete="off">
        <span class="text-secondary small ms-2">Drag a shipment onto a day to set its ship date, or onto "Not scheduled" to clear it. Shipments that have left can't be moved. Right-click a day to request a shipment; dashed yellow cards are requests awaiting approval.</span>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div id="sq-late"></div>
      <div id="sq-unsched"></div>
      <div class="table-responsive"><div id="sq-body"></div></div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@include('fleet._js')
@include('shipments._js')
<script>
let sqRows = [], sqReqs = [];
let sqDragId = null;

const ymd = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const parseYmd = s => new Date(s + 'T00:00:00');
function mondayOf(d) { const x = new Date(d); x.setHours(0, 0, 0, 0); x.setDate(x.getDate() - ((x.getDay() + 6) % 7)); return x; }
function sqShift(n) { const el = document.getElementById('sq-start'); const d = parseYmd(el.value); d.setDate(d.getDate() + n); el.value = ymd(d); sqLoad(); }
function sqToday() { document.getElementById('sq-start').value = ymd(mondayOf(new Date())); sqLoad(); }
function sqDays() { const m = parseYmd(document.getElementById('sq-start').value); return Array.from({ length: 14 }, (_, i) => { const d = new Date(m); d.setDate(d.getDate() + i); return ymd(d); }); }

async function sqLoad(fromPoll) {
  if (fromPoll === true && sqDragId) return;       // never redraw mid-drag
  const el = document.getElementById('sq-start');
  if (!el.value) el.value = ymd(mondayOf(new Date()));
  const days = sqDays();
  try {
    const d = await authenticatedFetch(`/shipments/queue?from=${days[0]}&to=${days[13]}`);
    sqRows = d.shipments;
    try { sqReqs = await authenticatedFetch('/shipment-requests?status=pending'); } catch (e) { sqReqs = []; }
    document.getElementById('sq-updated').textContent = 'Updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    sqRender();
  } catch (e) { console.error(e); }
}

function sqVisible(r) {
  const q = document.getElementById('sq-search').value.trim().toLowerCase();
  if (!q) return true;
  const hay = `${r.shipment_number} ${r.jobs.map(j => j.job_name + ' ' + j.job_number).concat(r.custom_jobs || []).join(' ')} ${r.destination || ''} ${r.loads.map(l => l.type.name).join(' ')}`.toLowerCase();
  return q.split(/\s+/).every(t => hay.includes(t));
}

function sqCard(r, late) {
  const checks = [r.pre_result ? `<i class="ti ti-clipboard-check ${r.pre_result === 'fail' ? 'text-danger' : 'text-success'}" title="Pre-check ${r.pre_result}"></i>` : '',
    r.post_result ? `<i class="ti ti-truck-delivery ${r.post_result === 'pass' ? 'text-success' : 'text-danger'}" title="Delivery check ${r.post_result}"></i>` : ''].join(' ');
  return `<a class="sq-card st-${r.status}${late ? ' late' : ''}" href="/shipments/${r.id}" ${r.movable && sqCanMove() ? `draggable="true" ondragstart="sqDragStart(event, ${r.id})" ondragend="sqDragEnd(event)"` : ''}
      title="${esc(r.jobs.map(j => j.job_name).concat(r.custom_jobs || []).join(', '))} — ${esc(r.destination || '')}">
    <strong>${esc(r.shipment_number)}${r.trip ? ' · ' + esc(r.trip) : ''} <span class="float-end">${checks}</span></strong>
    <small>${esc((r.jobs.length || (r.custom_jobs||[]).length) ? r.jobs.map(j => j.job_name).concat(r.custom_jobs || []).join(', ') : 'No job')}</small>
    <small>${r.loads.map(l => esc(l.type.name)).join(', ')}${r.destination ? ' · ' + esc(r.destination) : ''}</small>
    <small>${shStatusText(r.status)}</small></a>`;
}
const shStatusText = s => (SH.STATUS[s] || [s])[0];
const sqCanMove = () => typeof hasPermission !== 'function' || hasPermission('shipments.manage');

function sqRender() {
  const todayStr = ymd(new Date());
  const days = sqDays();
  const rows = sqRows.filter(sqVisible);
  const late = rows.filter(r => r.ship_date && r.ship_date < days[0]);
  const unsched = rows.filter(r => !r.ship_date);
  const byDay = {};
  rows.filter(r => r.ship_date && r.ship_date >= days[0]).forEach(r => (byDay[r.ship_date] ||= []).push(r));

  document.getElementById('sq-late').innerHTML = late.length ? `<div class="sq-panel border-danger"><div class="subheader text-danger mb-1">Past due — not shipped (${late.length}) · drag onto a day to reschedule</div><div class="cards">${late.map(r => sqCard(r, true)).join('')}</div></div>` : '';
  document.getElementById('sq-unsched').innerHTML = `<div class="sq-panel" ondragover="sqOver(event)" ondragleave="sqLeave(event)" ondrop="sqDrop(event, null)">
    <div class="subheader mb-1">Not scheduled (${unsched.length}) <span class="text-secondary fw-normal">· drop here to clear the ship date</span></div>
    <div class="cards">${unsched.map(r => sqCard(r)).join('') || '<span class="text-muted small">Nothing waiting for a date.</span>'}</div></div>`;

  const dayLabel = d => { const dt = parseYmd(d); return `${dt.toLocaleDateString([], { weekday: 'short' })} <span class="fw-normal">${dt.toLocaleDateString([], { month: 'short', day: 'numeric' })}</span>`; };
  const week = (ds, n) => `<div class="sq-grid mb-3">${ds.map(d => `<div class="sq-head${d === todayStr ? ' today' : ''}">${n === 0 && d === ds[0] ? 'This ' : ''}${dayLabel(d)}</div>`).join('')}${ds.map(d => {
    const wk = [0, 6].includes(parseYmd(d).getDay());
    return `<div class="sq-day${wk ? ' weekend' : ''}${d === todayStr ? ' today' : d < todayStr ? ' past' : ''}" ondragover="sqOver(event)" ondragleave="sqLeave(event)" ondrop="sqDrop(event, '${d}')" oncontextmenu="sqDayMenu(event, '${d}')">${(byDay[d] || []).map(r => sqCard(r)).join('')}${reqsOn(d).map(sqReqCard).join('')}</div>`;
  }).join('')}</div>`;
  document.getElementById('sq-body').innerHTML = week(days.slice(0, 7), 0) + week(days.slice(7), 1);
  if (typeof applyActionPermissions === 'function') applyActionPermissions();
}

// ---- shipping requests ----
const sqCan = p => typeof hasPermission !== 'function' || hasPermission(p);
const reqsOn = d => sqReqs.filter(r => r.requested_date === d && sqReqVisible(r));
function sqReqVisible(r) {
  const q = document.getElementById('sq-search').value.trim().toLowerCase();
  if (!q) return true;
  const hay = `${r.job ? r.job.job_name + ' ' + r.job.job_number : ''} ${r.custom_job || ''} ${r.destination || ''} ${(r.type_names || []).join(' ')}`.toLowerCase();
  return q.split(/\s+/).every(t => hay.includes(t));
}
const reqJob = r => r.job ? `${r.job.job_name} (${r.job.job_number})` : (r.custom_job || 'No job');
function sqReqCard(r) {
  return `<div class="sq-card req" oncontextmenu="sqReqMenu(event, ${r.id})" onclick="sqReqDetail(${r.id})" title="Shipping request, awaiting approval">
    <strong><i class="ti ti-hourglass-high me-1"></i>Request</strong><small>${esc(reqJob(r))}</small>
    <small>${esc((r.type_names || []).join(', '))}${r.destination ? ' · ' + esc(r.destination) : ''}</small></div>`;
}

function sqMenu(e, items) {
  e.preventDefault(); e.stopPropagation(); sqCloseMenu();
  const m = document.createElement('div'); m.className = 'sq-ctx'; m.id = 'sq-ctx';
  m.innerHTML = items.map((it, i) => `<a href="#" data-i="${i}">${it.html}</a>`).join('');
  m.addEventListener('click', ev => { const a = ev.target.closest('a'); if (!a) return; ev.preventDefault(); sqCloseMenu(); items[+a.dataset.i].fn(); });
  document.body.appendChild(m);
  m.style.left = Math.min(e.clientX, innerWidth - m.offsetWidth - 8) + 'px';
  m.style.top = Math.min(e.clientY, innerHeight - m.offsetHeight - 8) + 'px';
}
function sqCloseMenu() { document.getElementById('sq-ctx')?.remove(); }
document.addEventListener('click', sqCloseMenu);
document.addEventListener('scroll', sqCloseMenu, true);
document.addEventListener('keydown', e => { if (e.key === 'Escape') sqCloseMenu(); });

function sqDayMenu(e, day) {
  if (e.target.closest('.sq-card')) return;               // cards have their own menu / native behaviour
  if (!sqCan('shipments.request')) return;                // no permission: leave the browser menu alone
  const nice = parseYmd(day).toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
  sqMenu(e, [{ html: `<i class="ti ti-truck-loading me-2"></i>Request shipment for ${esc(nice)}`, fn: () => sqRequestForm(day) }]);
}
function sqReqMenu(e, id) {
  const items = [{ html: '<i class="ti ti-eye me-2"></i>View request', fn: () => sqReqDetail(id) }];
  if (sqCan('shipments.approve')) items.push(
    { html: '<i class="ti ti-check me-2 text-success"></i>Approve', fn: () => sqDecide(id, 'approve') },
    { html: '<i class="ti ti-x me-2 text-danger"></i>Reject…', fn: () => sqDecide(id, 'reject') });
  sqMenu(e, items);
}

async function sqRequestForm(day) {
  const types = (await flApi('/shipment-types')).filter(t => t.is_active);
  let picked = null, t;
  const form = flModal('Request a shipment', `
    <div class="col-md-5"><label class="form-label">Requested ship date</label><input type="date" class="form-control" name="requested_date" value="${day}" required></div>
    <div class="col-12"><label class="form-label">Job</label>
      <input type="text" class="form-control" id="rq-job-search" placeholder="Search job number or name…" autocomplete="off">
      <div id="rq-job-hits" class="list-group mt-1" style="max-height:9rem;overflow:auto"></div>
      <div id="rq-job-picked" class="small mt-1 text-muted">No job selected.</div></div>
    ${flField('custom_job', 'Or a job not in the system', '', 'text', 'maxlength="120"', 'col-12')}
    ${flField('destination', 'Destination', '', 'text', 'maxlength="255"', 'col-12')}
    <div class="col-12"><label class="form-label">Material</label><div class="d-flex flex-wrap gap-3">${types.map(x => `<label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-type="${x.id}"><span class="form-check-label">${esc(x.name)}</span></label>`).join('') || '<span class="text-muted small">No categories defined.</span>'}</div></div>
    ${flText('notes', 'What needs to ship / notes for the approver', '', 3)}
    <div class="col-12 small text-muted">A manager or admin reviews the request. Once approved it becomes a draft shipment on the requested date.</div>`,
    async f => {
      const d = flFormData(f);
      await flApi('/shipment-requests', 'POST', {
        requested_date: d.requested_date, job_id: picked ? picked.id : null, custom_job: d.custom_job, destination: d.destination, notes: d.notes,
        shipment_type_ids: [...f.querySelectorAll('[data-type]:checked')].map(c => +c.dataset.type),
      });
      showNotification('Request submitted for approval.', 'success');
      sqLoad();
    }, 'Submit request', 'modal-lg');
  window.rqPick = (id, name, num) => { picked = { id }; document.getElementById('rq-job-picked').innerHTML = `<i class="ti ti-check text-success"></i> ${esc(name)} <span class="text-muted">${esc(num)}</span>`; document.getElementById('rq-job-hits').innerHTML = ''; };
  document.getElementById('rq-job-search').addEventListener('input', ev => {
    clearTimeout(t); const v = ev.target.value.trim();
    if (v.length < 2) { document.getElementById('rq-job-hits').innerHTML = ''; return; }
    t = setTimeout(async () => {
      const jobs = await authenticatedFetch('/shipments/job-options?search=' + encodeURIComponent(v));
      document.getElementById('rq-job-hits').innerHTML = jobs.map(j => `<a href="#" class="list-group-item list-group-item-action py-1 small" onclick="event.preventDefault(); rqPick(${j.id}, ${esc(JSON.stringify(j.job_name))}, ${esc(JSON.stringify(j.job_number))})">${esc(j.job_name)} <span class="text-muted">${esc(j.job_number)}</span></a>`).join('') || '<div class="list-group-item small text-muted">No matches.</div>';
    }, 300);
  });
}

function sqReqDetail(id) {
  const r = sqReqs.find(x => x.id === id); if (!r) return;
  const approver = sqCan('shipments.approve');
  const row = (k, v) => v ? `<div class="col-12"><div class="subheader">${k}</div><div>${v}</div></div>` : '';
  flModal('Shipping request', row('Job', esc(reqJob(r))) + row('Requested date', flDate(r.requested_date)) + row('Destination', esc(r.destination || '')) +
    row('Material', esc((r.type_names || []).join(', '))) + row('Notes', esc(r.notes || '').replace(/\n/g, '<br>')) +
    row('Requested by', esc(r.requester ? r.requester.name : '—')) +
    `<div class="col-12 d-flex gap-2">${approver ? `<button type="button" class="btn btn-success" onclick="flModalInst.hide(); sqDecide(${id}, 'approve')">Approve</button>
      <button type="button" class="btn btn-outline-danger" onclick="flModalInst.hide(); sqDecide(${id}, 'reject')">Reject…</button>` : ''}
      ${sqCan('shipments.request') ? `<button type="button" class="btn ms-auto" onclick="flModalInst.hide(); sqWithdraw(${id})">Withdraw request</button>` : ''}</div>`, null);
}

async function sqDecide(id, action) {
  if (action === 'approve') {
    try {
      const res = await flApi(`/shipment-requests/${id}/approve`, 'POST', {});
      showNotification(`Approved: ${res.shipment_number} created.`, 'success');
    } catch (e) { /* notified */ }
    return sqLoad();
  }
  flModal('Reject request', flText('decision_note', 'Reason (shown to the requester)', '', 3), async f => {
    await flApi(`/shipment-requests/${id}/reject`, 'POST', { decision_note: f.elements.decision_note.value || null });
    sqLoad();
  }, 'Reject');
}
function sqWithdraw(id) { flConfirm('Withdraw this request?', async () => { await flApi(`/shipment-requests/${id}/withdraw`, 'POST', {}); sqLoad(); }); }

function sqDragStart(e, id) {
  sqDragId = id; e.currentTarget.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', String(id));          // Firefox won't start a drag without data
}
function sqDragEnd(e) { sqDragId = null; e.currentTarget.classList.remove('dragging'); }
function sqOver(e) { if (sqDragId == null) return; e.preventDefault(); e.dataTransfer.dropEffect = 'move'; e.currentTarget.classList.add('drop-hover'); }
function sqLeave(e) { e.currentTarget.classList.remove('drop-hover'); }

async function sqDrop(e, day) {
  e.preventDefault();
  e.currentTarget.classList.remove('drop-hover');
  const id = sqDragId; sqDragId = null;
  const row = sqRows.find(r => r.id === id);
  if (!row || row.ship_date === day) return;
  const before = row.ship_date;
  row.ship_date = day; sqRender();                           // optimistic; reverted if the server refuses
  try {
    await authenticatedFetch(`/shipments/${id}/schedule`, { method: 'PATCH', body: JSON.stringify({ ship_date: day }) });
  } catch (err) {
    row.ship_date = before;
    showNotification(err.message || 'Could not reschedule.', 'danger');
  }
  sqLoad();
}

document.addEventListener('DOMContentLoaded', () => { sqLoad(); setInterval(() => sqLoad(true), 30000); });
</script>
@endpush
