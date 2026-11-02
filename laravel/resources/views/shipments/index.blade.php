@extends('layouts.app')

@section('title', 'Shipments - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Shipping</div>
          <h1 class="page-title">Shipments</h1>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <button class="btn btn-primary" data-permission="shipments.manage" onclick="newShipment()"><i class="ti ti-plus me-1"></i>New Shipment</button>
        </div>
      </div>
    </div>
  </div>

  <main class="page-body">
    <div class="container-xl">
      <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link active" href="#shipments" data-tab="shipments">Shipments</a></li>
        <li class="nav-item" data-permission="shipments.types.manage"><a class="nav-link" href="#types" data-tab="types">Types &amp; checklists</a></li>
      </ul>

      <div data-pane="shipments">
        <div class="card">
          <div class="card-header gap-2 flex-wrap">
            <div class="input-icon flex-grow-1" style="min-width:200px">
              <span class="input-icon-addon"><i class="ti ti-search"></i></span>
              <input type="text" class="form-control" id="shSearch" placeholder="Search shipment, job, destination…" autocomplete="off">
            </div>
            <select class="form-select w-auto" id="shStatus"></select>
          </div>
          <div class="table-responsive">
            <table class="table card-table table-vcenter table-hover">
              <thead><tr><th>#</th><th>Job</th><th>Loads</th><th>Status</th><th>Ship date</th><th>Destination</th></tr></thead>
              <tbody id="shRows"><tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      <div data-pane="types" class="d-none">
        <div class="d-flex mb-3">
          <p class="text-muted mb-0">Each shipment type has its own checklist before it ships (<b>pre</b>, done at the shop) and on arrival (<b>post</b>, done by the crew in the field app).</p>
          <button class="btn btn-primary ms-auto text-nowrap" onclick="addType()"><i class="ti ti-plus me-1"></i>Add type</button>
        </div>
        <div id="typeCards"></div>
      </div>
    </div>
  </main>
</div>
@endsection

@push('scripts')
@include('fleet._js')
@include('shipments._js')
<script>
  let shTypes = [];
  const TABS = ['shipments', 'types'];

  function showTab(name) {
    if (!TABS.includes(name)) name = 'shipments';
    document.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('d-none', p.dataset.pane !== name));
    document.querySelectorAll('.nav-tabs .nav-link').forEach(a => a.classList.toggle('active', a.dataset.tab === name));
    if (name === 'shipments') loadShipments(); else loadTypes();
  }

  // ---- shipments ----
  async function loadShipments() {
    const p = new URLSearchParams();
    const s = document.getElementById('shSearch').value, st = document.getElementById('shStatus').value;
    if (s) p.set('search', s);
    if (st) p.set('status', st);
    try {
      const rows = await authenticatedFetch('/shipments?' + p);
      document.getElementById('shRows').innerHTML = rows.length ? rows.map(r => `<tr style="cursor:pointer" onclick="location.href='/shipments/${r.id}'">
        <td class="text-nowrap fw-bold">${esc(r.shipment_number)}</td>
        <td>${r.job ? esc(r.job.job_name) + ' <span class="text-muted">' + esc(r.job.job_number) + '</span>' : '—'}</td>
        <td>${r.loads.map(l => flBadge(l.type.name, 'azure')).join(' ')}</td>
        <td>${shStatus(r.status)}</td><td>${flDate(r.ship_date)}</td><td class="text-muted">${esc(r.destination || '')}</td></tr>`).join('')
        : '<tr><td colspan="6" class="text-center text-muted py-4">No shipments.</td></tr>';
    } catch (e) { console.error(e); }
  }

  async function newShipment() {
    shTypes = (await flApi('/shipment-types')).filter(t => t.is_active);
    const jobs = await flApi('/shipments/job-options');
    const form = flModal('New Shipment',
      flSelect('business_job_id', 'Job', flOpts(jobs.map(j => ({ id: j.id, name: `${j.job_number} · ${j.job_name}` })), new URLSearchParams(location.search).get('job') || '', 'No job'), 'col-12') +
      flField('ship_date', 'Ship date', '', 'date') + flField('carrier', 'Carrier / driver', '') +
      flField('destination', 'Destination', '', 'text', '', 'col-12') +
      `<div class="col-12"><label class="form-label">What is on this shipment?</label>` + shTypes.map(t => `<div class="row g-2 align-items-center mb-1">
        <div class="col"><label class="form-check mb-0"><input type="checkbox" class="form-check-input load-cb" value="${t.id}"><span class="form-check-label">${esc(t.name)}</span></label></div>
        <div class="col-4"><input type="number" min="0" class="form-control form-control-sm" placeholder="Pieces" data-pieces="${t.id}"></div></div>`).join('') + `</div>` +
      flText('notes', 'Notes', '', 2),
      async f => {
        const d = flFormData(f);
        d.loads = [...f.querySelectorAll('.load-cb:checked')].map(c => ({ shipment_type_id: +c.value, piece_count: f.querySelector(`[data-pieces="${c.value}"]`).value || null }));
        if (!d.loads.length) { showNotification('Pick at least one load type.', 'warning'); throw new Error('no loads'); }
        delete d.undefined;
        const s = await flApi('/shipments', 'POST', d);
        location.href = '/shipments/' + s.id;
      }, 'Create');
  }

  // ---- types & checklists ----
  async function loadTypes() {
    shTypes = await flApi('/shipment-types');
    document.getElementById('typeCards').innerHTML = shTypes.map(t => `<div class="card mb-3">
      <div class="card-header"><h3 class="card-title">${esc(t.name)} ${t.is_active ? '' : flBadge('Inactive', 'secondary')}</h3>
        <div class="card-actions btn-list">
          <button class="btn btn-sm" onclick="renameType(${t.id})">Rename</button>
          <button class="btn btn-sm" onclick="toggleType(${t.id})">${t.is_active ? 'Deactivate' : 'Activate'}</button>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteType(${t.id})">Delete</button></div></div>
      <div class="card-body"><div class="row">${['pre', 'post'].map(ph => `<div class="col-lg-6 mb-3">
        <div class="d-flex align-items-center mb-2"><h4 class="mb-0">${ph === 'pre' ? 'Before shipping (shop)' : 'On delivery (field app)'}</h4>
          <button class="btn btn-sm ms-auto" onclick="itemModal(${t.id}, '${ph}')"><i class="ti ti-plus me-1"></i>Add item</button></div>
        <div class="list-group list-group-flush">${t.items.filter(i => i.phase === ph).map(i => `<div class="list-group-item px-0 d-flex align-items-center gap-2 ${i.is_active ? '' : 'opacity-50'}">
          <div class="flex-grow-1"><div>${esc(i.label)}</div><div class="small text-muted">${esc(SH.INPUTS[i.input_type])}${i.unit ? ' (' + esc(i.unit) + ')' : ''}
            ${i.required ? ' · required' : ''}${i.photo_required ? ' · photo' : ''}${i.critical ? ' · critical' : ''}</div></div>
          <button class="btn btn-sm btn-icon" title="Edit" onclick="itemModal(${t.id}, '${ph}', ${i.id})"><i class="ti ti-pencil"></i></button>
          <button class="btn btn-sm btn-icon text-danger" title="Delete" onclick="deleteItem(${i.id})"><i class="ti ti-trash"></i></button></div>`).join('') || '<div class="text-muted small">No items.</div>'}</div></div>`).join('')}
      </div></div></div>`).join('') || '<div class="text-muted">No types yet.</div>';
  }

  function addType() {
    flModal('Add shipment type', flField('name', 'Name (e.g. Hardware, Louvers)', '', 'text', 'required', 'col-12'), async f => {
      await flApi('/shipment-types', 'POST', { name: f.elements.name.value }); loadTypes();
    }, 'Add');
  }
  function renameType(id) {
    const t = shTypes.find(x => x.id === id);
    flModal('Rename type', flField('name', 'Name', t.name, 'text', 'required', 'col-12'), async f => {
      await flApi('/shipment-types/' + id, 'PUT', { name: f.elements.name.value }); loadTypes();
    });
  }
  async function toggleType(id) {
    const t = shTypes.find(x => x.id === id);
    await flApi('/shipment-types/' + id, 'PUT', { is_active: !t.is_active }); loadTypes();
  }
  function deleteType(id) { flConfirm('Delete this type and its checklist items?', async () => { await flApi('/shipment-types/' + id, 'DELETE'); loadTypes(); }); }
  function deleteItem(id) { flConfirm('Delete this checklist item? Past inspections keep their results.', async () => { await flApi('/shipment-check-items/' + id, 'DELETE'); loadTypes(); }); }

  function itemModal(typeId, phase, itemId = null) {
    const it = itemId ? shTypes.find(t => t.id === typeId).items.find(i => i.id === itemId) : { input_type: 'pass_fail', required: true, photo_required: false, critical: false, is_active: true, options: [] };
    const chk = (n, l, v) => `<div class="col-6 col-md-3"><label class="form-check"><input type="checkbox" class="form-check-input" name="${n}" ${v ? 'checked' : ''}><span class="form-check-label">${l}</span></label></div>`;
    const form = flModal(itemId ? 'Edit item' : 'Add item (' + phase + ')',
      flField('label', 'Data point', it.label || '', 'text', 'required', 'col-12') +
      flSelect('input_type', 'Answer type', flOpts(Object.entries(SH.INPUTS).map(([id, name]) => ({ id, name })), it.input_type)) +
      flField('unit', 'Unit (number type)', it.unit || '', 'text', 'maxlength="20"') +
      flText('options', 'Choices (one per line, for Choice list)', (it.options || []).join('\n'), 3) +
      chk('required', 'Required', it.required) + chk('photo_required', 'Photo needed', it.photo_required) +
      chk('critical', 'Critical', it.critical) + chk('is_active', 'Active', it.is_active) +
      `<div class="col-12 small text-muted">Photo needed: pass/fail items require a photo when they fail; other types always. Critical: a failure fails the whole inspection.</div>`,
      async f => {
        const d = flFormData(f);
        d.options = (d.options || '').split('\n').map(s => s.trim()).filter(Boolean);
        if (d.input_type !== 'choice') d.options = [];
        if (itemId) await flApi('/shipment-check-items/' + itemId, 'PUT', d);
        else await flApi(`/shipment-types/${typeId}/items`, 'POST', { ...d, phase });
        loadTypes();
      }, 'Save', 'modal-lg');
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('shStatus').innerHTML = '<option value="">All statuses</option>' + Object.entries(SH.STATUS).map(([k, v]) => `<option value="${k}">${v[0]}</option>`).join('');
    document.getElementById('shStatus').addEventListener('change', loadShipments);
    let t; document.getElementById('shSearch').addEventListener('input', () => { clearTimeout(t); t = setTimeout(loadShipments, 250); });
    document.querySelectorAll('.nav-tabs .nav-link').forEach(a => a.addEventListener('click', e => { e.preventDefault(); history.replaceState(null, '', a.getAttribute('href')); showTab(a.dataset.tab); }));
    showTab(location.hash.slice(1));
    if (new URLSearchParams(location.search).get('job') && typeof hasPermission === 'function' && hasPermission('shipments.manage')) newShipment();
  });
</script>
@endpush
