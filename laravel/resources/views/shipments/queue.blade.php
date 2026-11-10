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
        <span class="text-secondary small ms-2">Drag a shipment onto a day to set its ship date, or onto "Not scheduled" to clear it. Shipments that have left can't be moved.</span>
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
let sqRows = [];
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
    return `<div class="sq-day${wk ? ' weekend' : ''}${d === todayStr ? ' today' : d < todayStr ? ' past' : ''}" ondragover="sqOver(event)" ondragleave="sqLeave(event)" ondrop="sqDrop(event, '${d}')">${(byDay[d] || []).map(r => sqCard(r)).join('')}</div>`;
  }).join('')}</div>`;
  document.getElementById('sq-body').innerHTML = week(days.slice(0, 7), 0) + week(days.slice(7), 1);
  if (typeof applyActionPermissions === 'function') applyActionPermissions();
}

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
