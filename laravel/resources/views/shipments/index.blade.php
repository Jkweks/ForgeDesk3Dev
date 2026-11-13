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
        <li class="nav-item" data-permission="shipments.types.manage"><a class="nav-link" href="#types" data-tab="types">Categories &amp; checklists</a></li>
      </ul>

      <div data-pane="shipments">
        <div id="unschedBox"></div>
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
              <thead><tr>
                <th class="sortable" data-sort="shipment_number">#</th><th class="sortable" data-sort="job">Job</th><th class="sortable" data-sort="work_orders">Work orders</th>
                <th class="sortable" data-sort="categories">Categories</th><th class="sortable" data-sort="elevations_count">Elev.</th><th class="sortable" data-sort="status">Status</th>
                <th class="sortable" data-sort="ship_date">Ship date</th><th class="sortable" data-sort="destination">Destination</th></tr></thead>
              <tbody id="shRows"><tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      <div data-pane="types" class="d-none">
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title">Standard criteria</h3>
            <div class="card-actions"><button class="btn btn-sm btn-primary" onclick="criterionModal()"><i class="ti ti-plus me-1"></i>Add criterion</button></div></div>
          <div class="card-body border-bottom py-2 small text-muted">Define each check once here, then add it to any category below. Editing a criterion&rsquo;s wording updates it in every category that uses it.</div>
          <div class="table-responsive"><table class="table card-table table-vcenter">
            <thead><tr><th>Criterion</th><th>Answer</th><th>Used in</th><th></th></tr></thead><tbody id="critRows"></tbody></table></div>
        </div>
        <div class="d-flex mb-3">
          <p class="text-muted mb-0"><b>Categories</b> (Metal, Glass, Doors…) are built from criteria: a checklist before shipping (<b>pre</b>, at the shop) and on arrival (<b>post</b>, in the field app). Add categories to a shipment when you create it.</p>
          <button class="btn btn-primary ms-auto text-nowrap" onclick="addType()"><i class="ti ti-plus me-1"></i>Add category</button>
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
  let shTypes = [], shCriteria = [];
  const TABS = ['shipments', 'types'];

  function showTab(name) {
    if (!TABS.includes(name)) name = 'shipments';
    document.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('d-none', p.dataset.pane !== name));
    document.querySelectorAll('.nav-tabs .nav-link').forEach(a => a.classList.toggle('active', a.dataset.tab === name));
    if (name === 'shipments') { loadShipments(); loadUnscheduled(); } else loadTypes();
  }

  // ---- shipments ----
  let shRowsData = [], shSort = { key: 'shipment_number', dir: -1 };
  const jobText = r => [...r.jobs.map(j => j.job_number || ''), ...(r.custom_jobs || [])].join(' ');
  const SORT_VAL = {
    job: jobText,
    work_orders: r => (r.work_orders || []).map(w => w.label).join(' '),
    categories: r => r.loads.map(l => l.type.name).join(' '),
    elevations_count: r => +r.elevations_count || 0,
    ship_date: r => r.ship_date || '',
  };
  function sortBy(key) {
    shSort = { key, dir: shSort.key === key ? -shSort.dir : 1 };
    renderShipments();
  }
  function renderShipments() {
    const get = SORT_VAL[shSort.key] || (r => r[shSort.key] ?? '');
    const rows = [...shRowsData].sort((a, b) => {
      const x = get(a), y = get(b);
      // Blanks always last, whichever direction.
      if ((x === '' || x == null) !== (y === '' || y == null)) return (x === '' || x == null) ? 1 : -1;
      const c = typeof x === 'number' ? x - y : String(x).localeCompare(String(y), undefined, { numeric: true, sensitivity: 'base' });
      return c * shSort.dir || (b.id - a.id);
    });
    document.querySelectorAll('th.sortable').forEach(th => {
      const on = th.dataset.sort === shSort.key;
      th.style.cursor = 'pointer'; th.classList.add('user-select-none');
      th.dataset.arrow = on ? (shSort.dir > 0 ? ' ▲' : ' ▼') : '';
      th.textContent = th.textContent.replace(/ [▲▼]$/, '') + th.dataset.arrow;
    });
    document.getElementById('shRows').innerHTML = rows.length ? rows.map(r => `<tr style="cursor:pointer" onclick="location.href='/shipments/${r.id}'">
        <td class="text-nowrap fw-bold">${esc(r.shipment_number)}${r.trip ? '<div class="small fw-normal text-muted">' + esc(r.trip) + '</div>' : ''}</td>
        <td>${(r.jobs.length || (r.custom_jobs||[]).length) ? r.jobs.map(j => esc(j.job_name) + ' <span class="text-muted">' + esc(j.job_number) + '</span>').concat((r.custom_jobs||[]).map(n => esc(n) + ' <span class="text-muted">(custom)</span>')).join('<br>') : '—'}</td>
        <td>${(r.work_orders || []).map(w => `<span class="badge bg-blue-lt me-1 mb-1" title="${esc(w.tags.join(', '))}${w.partial ? ' (partial)' : ''}">${esc(w.label)} · ${w.elevations} elev${w.partial ? ' (partial)' : ''}</span>`).join('') || '<span class="text-muted">—</span>'}</td>
        <td>${r.loads.map(l => flBadge(l.type.name, 'azure')).join(' ')}</td>
        <td>${r.elevations_count || '<span class="text-muted">0</span>'}</td>
        <td>${shStatus(r.status)}</td><td>${flDate(r.ship_date)}</td><td class="text-muted">${esc(r.destination || '')}</td></tr>`).join('')
      : '<tr><td colspan="8" class="text-center text-muted py-4">No shipments.</td></tr>';
  }
  async function loadShipments() {
    const p = new URLSearchParams();
    const s = document.getElementById('shSearch').value, st = document.getElementById('shStatus').value;
    if (s) p.set('search', s);
    if (st) p.set('status', st);
    try {
      shRowsData = await authenticatedFetch('/shipments?' + p);
      renderShipments();
    } catch (e) { console.error(e); }
  }

  // Elevations of open work orders that sit on no open shipment (not yet scheduled, or only partly delivered).
  async function loadUnscheduled() {
    const box = document.getElementById('unschedBox');
    try {
      const wos = await authenticatedFetch('/shipments/unscheduled');
      if (!wos.length) { box.innerHTML = ''; return; }
      const total = wos.reduce((n, w) => n + w.elevations.length, 0);
      box.innerHTML = `<div class="card mb-3 border-warning"><div class="card-header"><h3 class="card-title"><i class="ti ti-alert-triangle text-warning me-1"></i>${total} elevation${total === 1 ? '' : 's'} not on a shipment</h3>
        <div class="card-actions"><a href="#" class="btn btn-sm" onclick="document.getElementById('unschedList').classList.toggle('d-none'); return false;">Show / hide</a></div></div>
        <div class="list-group list-group-flush d-none" id="unschedList">${wos.map(w => `<div class="list-group-item"><div class="d-flex align-items-center">
          <div class="fw-bold">${esc(w.label)}${w.due_date ? ' <span class="text-muted fw-normal">due ' + flDate(w.due_date) + '</span>' : ''}</div>
          ${w.draft_shipment_id ? `<a class="btn btn-sm ms-auto" href="/shipments/${w.draft_shipment_id}#elevations">Open draft shipment</a>` : ''}</div>
          <div class="mt-1">${w.elevations.map(e => flBadge(esc(e.tag || '#' + e.id) + (e.state === 'partial' ? ' (partial)' : ''), e.state === 'partial' ? 'warning' : 'secondary')).join(' ')}</div></div>`).join('')}</div></div>`;
    } catch (e) { box.innerHTML = ''; }
  }

  const newShipment = () => shipmentForm(null, new URLSearchParams(location.search).get('job'));

  // ---- types & checklists ----
  async function loadTypes() {
    [shTypes, shCriteria] = await Promise.all([flApi('/shipment-types'), flApi('/shipment-criteria')]);
    renderCriteria();
    document.getElementById('typeCards').innerHTML = shTypes.map(t => `<div class="card mb-3">
      <div class="card-header"><h3 class="card-title">${esc(t.name)} ${t.is_active ? '' : flBadge('Inactive', 'secondary')}</h3>
        <div class="card-actions btn-list">
          <button class="btn btn-sm" onclick="renameType(${t.id})">Rename</button>
          <button class="btn btn-sm" onclick="toggleType(${t.id})">${t.is_active ? 'Deactivate' : 'Activate'}</button>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteType(${t.id})">Delete</button></div></div>
      <div class="card-body"><div class="row">${['pre', 'post'].map(ph => `<div class="col-lg-6 mb-3">
        <div class="d-flex align-items-center mb-2"><h4 class="mb-0">${ph === 'pre' ? 'Before shipping (shop)' : 'On delivery (field app)'}</h4>
          <button class="btn btn-sm ms-auto" onclick="attachModal(${t.id}, '${ph}')"><i class="ti ti-plus me-1"></i>Add criterion</button></div>
        <div class="list-group list-group-flush item-list" data-type="${t.id}" data-phase="${ph}" ondragover="itemDragOver(event)" ondrop="itemDrop(event)">${t.items.filter(i => i.phase === ph).map(i => `<div class="list-group-item px-0 d-flex align-items-center gap-2 ${i.is_active ? '' : 'opacity-50'}" data-id="${i.id}" draggable="true" ondragstart="itemDragStart(event)" ondragend="itemDragEnd(event)">
          <i class="ti ti-grip-vertical text-muted" style="cursor:grab" title="Drag to reorder"></i>
          <div class="flex-grow-1"><div>${esc(i.label)}</div><div class="small text-muted">${esc(SH.INPUTS[i.input_type])}${i.unit ? ' (' + esc(i.unit) + ')' : ''}
            ${i.required ? ' · required' : ''}${i.photo_required && i.input_type !== 'photo' ? ' · photo' : ''}${i.critical ? ' · critical' : ''}</div></div>
          <button class="btn btn-sm btn-icon" title="Edit" onclick="itemModal(${t.id}, '${ph}', ${i.id})"><i class="ti ti-pencil"></i></button>
          <button class="btn btn-sm btn-icon text-danger" title="Delete" onclick="deleteItem(${i.id})"><i class="ti ti-trash"></i></button></div>`).join('') || '<div class="text-muted small">No criteria yet.</div>'}</div></div>`).join('')}
      </div></div></div>`).join('') || '<div class="text-muted">No categories yet.</div>';
  }

  // ---- drag-and-drop ordering of checklist items (within one type + phase) ----
  let dragEl = null;
  function itemDragStart(e) { dragEl = e.currentTarget; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', dragEl.dataset.id); setTimeout(() => dragEl && dragEl.classList.add('opacity-50'), 0); }
  function itemDragEnd() { if (dragEl) dragEl.classList.remove('opacity-50'); dragEl = null; }
  function itemDragOver(e) {
    const list = e.currentTarget;
    if (!dragEl || dragEl.parentElement !== list) return;   // can't cross types/phases
    e.preventDefault();
    const after = [...list.querySelectorAll('[data-id]')].find(el => el !== dragEl && e.clientY < el.getBoundingClientRect().top + el.offsetHeight / 2);
    if (after) list.insertBefore(dragEl, after); else list.appendChild(dragEl);
  }
  async function itemDrop(e) {
    e.preventDefault();
    const list = e.currentTarget;
    if (!dragEl || dragEl.parentElement !== list) return;
    const ids = [...list.querySelectorAll('[data-id]')].map(el => +el.dataset.id);
    try { await flApi(`/shipment-types/${list.dataset.type}/items/reorder`, 'POST', { phase: list.dataset.phase, ids }); }
    catch (err) { /* flApi already notified */ }
    loadTypes();
  }

  function addType() {
    flModal('Add category', flField('name', 'Name (e.g. Hardware, Louvers)', '', 'text', 'required', 'col-12'), async f => {
      await flApi('/shipment-types', 'POST', { name: f.elements.name.value }); loadTypes();
    }, 'Add');
  }
  function renameType(id) {
    const t = shTypes.find(x => x.id === id);
    flModal('Rename category', flField('name', 'Name', t.name, 'text', 'required', 'col-12'), async f => {
      await flApi('/shipment-types/' + id, 'PUT', { name: f.elements.name.value }); loadTypes();
    });
  }
  async function toggleType(id) {
    const t = shTypes.find(x => x.id === id);
    await flApi('/shipment-types/' + id, 'PUT', { is_active: !t.is_active }); loadTypes();
  }
  function deleteType(id) { flConfirm('Delete this category and its checklist?', async () => { await flApi('/shipment-types/' + id, 'DELETE'); loadTypes(); }); }
  function deleteItem(id) { flConfirm('Delete this checklist item? Past inspections keep their results.', async () => { await flApi('/shipment-check-items/' + id, 'DELETE'); loadTypes(); }); }

  // ---- criteria library ----
  function renderCriteria() {
    document.getElementById('critRows').innerHTML = shCriteria.map(c => {
      const used = [...new Set(c.check_items.map(i => (shTypes.find(t => t.id === i.shipment_type_id) || {}).name).filter(Boolean))];
      return `<tr class="${c.is_active ? '' : 'opacity-50'}"><td>${esc(c.label)}</td>
        <td class="text-muted">${esc(SH.INPUTS[c.input_type])}${c.unit ? ' (' + esc(c.unit) + ')' : ''}</td>
        <td>${used.map(n => flBadge(n, 'azure')).join(' ') || '<span class="text-muted">—</span>'}</td>
        <td class="text-end text-nowrap"><button class="btn btn-sm btn-icon" title="Edit" onclick="criterionModal(${c.id})"><i class="ti ti-pencil"></i></button>
          <button class="btn btn-sm btn-icon text-danger" title="Delete" onclick="deleteCriterion(${c.id})"><i class="ti ti-trash"></i></button></td></tr>`;
    }).join('') || '<tr><td colspan="4" class="text-center text-muted py-3">No criteria yet.</td></tr>';
  }

  function criterionModal(id = null) {
    const c = id ? shCriteria.find(x => x.id === id) : { input_type: 'pass_fail', required: true, photo_required: false, critical: false, is_active: true, options: [] };
    const chk = (n, l, v) => `<div class="col-6 col-md-3"><label class="form-check"><input type="checkbox" class="form-check-input" name="${n}" ${v ? 'checked' : ''}><span class="form-check-label">${l}</span></label></div>`;
    flModal(id ? 'Edit criterion' : 'Add criterion',
      flField('label', 'Check (what the crew confirms)', c.label || '', 'text', 'required', 'col-12') +
      flSelect('input_type', 'Answer type', flOpts(Object.entries(SH.INPUTS).map(([k, name]) => ({ id: k, name })), c.input_type)) +
      flField('unit', 'Unit (number type)', c.unit || '', 'text', 'maxlength="20"') +
      flText('options', 'Choices (one per line, for Choice list)', (c.options || []).join('\n'), 3) +
      chk('required', 'Required', c.required) + chk('photo_required', 'Photo needed', c.photo_required) + chk('critical', 'Critical', c.critical) + chk('is_active', 'Active', c.is_active) +
      `<div class="col-12 small text-muted">Required / Photo needed / Critical are the defaults when the criterion is added to a category; each category can override them afterwards. Photos answer type: one or more pictures. Critical: a failure fails the whole inspection.${id ? ' Wording and answer type changes apply to every category using this criterion.' : ''}</div>`,
      async f => {
        const d = flFormData(f);
        d.options = (d.options || '').split('\n').map(s => s.trim()).filter(Boolean);
        if (d.input_type !== 'choice') d.options = [];
        if (id) await flApi('/shipment-criteria/' + id, 'PUT', d); else await flApi('/shipment-criteria', 'POST', d);
        loadTypes();
      }, 'Save', 'modal-lg');
  }
  function deleteCriterion(id) { flConfirm('Delete this criterion?', async () => { await flApi('/shipment-criteria/' + id, 'DELETE'); loadTypes(); }); }

  // Add a library criterion to a category's checklist (the normal path), or define a new one inline.
  function attachModal(typeId, phase) {
    const t = shTypes.find(x => x.id === typeId);
    const have = new Set(t.items.filter(i => i.phase === phase).map(i => i.criterion_id));
    const avail = shCriteria.filter(c => c.is_active && !have.has(c.id));
    flModal('Add criteria to ' + t.name + ' (' + (phase === 'pre' ? 'before shipping' : 'on delivery') + ')',
      (avail.map(c => `<div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" data-crit="${c.id}"><span class="form-check-label">${esc(c.label)} <span class="text-muted small">${esc(SH.INPUTS[c.input_type])}${c.critical ? ' · critical' : ''}</span></span></label></div>`).join('')
        || '<div class="col-12 text-muted">Every active criterion is already on this checklist.</div>') +
      `<div class="col-12"><a href="#" onclick="event.preventDefault(); flModalInst.hide(); itemModal(${typeId}, '${phase}'); return false;">Or create a brand-new criterion for this checklist…</a></div>`,
      async f => {
        for (const c of f.querySelectorAll('[data-crit]:checked')) await flApi(`/shipment-types/${typeId}/items`, 'POST', { criterion_id: +c.dataset.crit, phase });
        loadTypes();
      }, 'Add selected');
  }

  function itemModal(typeId, phase, itemId = null) {
    const it = itemId ? shTypes.find(t => t.id === typeId).items.find(i => i.id === itemId) : { input_type: 'pass_fail', required: true, photo_required: false, critical: false, is_active: true, options: [] };
    const chk = (n, l, v) => `<div class="col-6 col-md-3"><label class="form-check"><input type="checkbox" class="form-check-input" name="${n}" ${v ? 'checked' : ''}><span class="form-check-label">${l}</span></label></div>`;
    const form = flModal(itemId ? 'Edit item' : 'Add item (' + phase + ')',
      (it.criterion_id ? `<div class="col-12"><div class="fw-bold">${esc(it.label)}</div><div class="small text-muted">${esc(SH.INPUTS[it.input_type])}. Wording and answer type are edited under Standard criteria.</div></div>` :
      flField('label', 'Data point', it.label || '', 'text', 'required', 'col-12') +
      flSelect('input_type', 'Answer type', flOpts(Object.entries(SH.INPUTS).map(([id, name]) => ({ id, name })), it.input_type)) +
      flField('unit', 'Unit (number type)', it.unit || '', 'text', 'maxlength="20"') +
      flText('options', 'Choices (one per line, for Choice list)', (it.options || []).join('\n'), 3)) +
      chk('required', 'Required', it.required) + chk('photo_required', 'Photo needed', it.photo_required) +
      chk('critical', 'Critical', it.critical) + chk('is_active', 'Active', it.is_active) +
      `<div class="col-12 small text-muted">Photos answer type: one or more pictures (e.g. of a certain item or area); Required means at least one. Photo needed: pass/fail items require a photo when they fail; other types always. Critical: a failure fails the whole inspection.</div>`,
      async f => {
        const d = flFormData(f);
        if (!it.criterion_id) { d.options = (d.options || '').split('\n').map(s => s.trim()).filter(Boolean); if (d.input_type !== 'choice') d.options = []; }
        if (itemId) await flApi('/shipment-check-items/' + itemId, 'PUT', d);
        else await flApi(`/shipment-types/${typeId}/items`, 'POST', { ...d, phase });
        loadTypes();
      }, 'Save', 'modal-lg');
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('th.sortable').forEach(th => th.addEventListener('click', () => sortBy(th.dataset.sort)));
    document.getElementById('shStatus').innerHTML = '<option value="">All statuses</option>' + Object.entries(SH.STATUS).map(([k, v]) => `<option value="${k}">${v[0]}</option>`).join('');
    document.getElementById('shStatus').addEventListener('change', loadShipments);
    let t; document.getElementById('shSearch').addEventListener('input', () => { clearTimeout(t); t = setTimeout(loadShipments, 250); });
    document.querySelectorAll('.nav-tabs .nav-link').forEach(a => a.addEventListener('click', e => { e.preventDefault(); history.replaceState(null, '', a.getAttribute('href')); showTab(a.dataset.tab); }));
    showTab(location.hash.slice(1));
    if (new URLSearchParams(location.search).get('job') && typeof hasPermission === 'function' && hasPermission('shipments.manage')) newShipment();
  });
</script>
@endpush
