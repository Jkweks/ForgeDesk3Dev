@extends('layouts.app')

@section('title', 'Fleet Asset - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle"><a href="/fleet#assets">Field &amp; Fleet</a> / <span id="hdrNumber"></span></div>
          <h1 class="page-title"><span id="hdrName">Loading…</span> <span id="hdrStatus" class="ms-2"></span></h1>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <div class="btn-list">
            <button class="btn" data-permission="fleet.manage" onclick="updateMeter()" id="btnMeter"><i class="ti ti-gauge me-1"></i>Update meter</button>
            <button class="btn" data-permission="fleet.manage" onclick="changeStatus()"><i class="ti ti-switch-horizontal me-1"></i>Status</button>
            <button class="btn" data-permission="fleet.manage" onclick="editAsset()"><i class="ti ti-edit me-1"></i>Edit</button>
            <button class="btn btn-outline-danger" data-permission="fleet.delete" onclick="deleteAsset()"><i class="ti ti-trash me-1"></i>Delete</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <main class="page-body">
    <div class="container-xl">
      <ul class="nav nav-tabs mb-3" id="assetTabs">
        @foreach (['overview' => 'Overview', 'maintenance' => 'Maintenance', 'inspections' => 'Inspections', 'compliance' => 'Compliance', 'documents' => 'Documents', 'timeline' => 'Timeline'] as $k => $label)
          <li class="nav-item"><a class="nav-link {{ $loop->first ? 'active' : '' }}" href="#{{ $k }}" data-tab="{{ $k }}">{{ $label }}</a></li>
        @endforeach
      </ul>

      <div data-pane="overview"><div class="row row-cards">
        <div class="col-lg-8"><div class="card"><div class="card-body"><dl class="row mb-0" id="detailList"></dl></div></div></div>
        <div class="col-lg-4">
          <div class="card mb-3"><div class="card-header"><h3 class="card-title">Meter</h3></div><div class="card-body">
            <div class="h1 mb-0" id="meterValue">—</div><div class="text-muted small" id="meterSub"></div>
            <div class="mt-3 small" id="meterHistory"></div></div></div>
          <div class="card"><div class="card-header"><h3 class="card-title">Attached equipment</h3></div><div class="card-body" id="childList"></div></div>
        </div></div></div>

      <div data-pane="maintenance" class="d-none">
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Maintenance plans</h3>
            <div class="ms-auto"><button class="btn btn-sm btn-primary" data-permission="fleet.manage" onclick="editPlan()"><i class="ti ti-plus me-1"></i>Add plan</button></div></div>
          <div class="table-responsive"><table class="table card-table table-vcenter">
            <thead><tr><th>Status</th><th>Plan</th><th>Every</th><th>Last done</th><th>Next due</th><th></th></tr></thead><tbody id="planRows"></tbody></table></div>
        </div>
        <div class="card">
          <div class="card-header"><h3 class="card-title">Work orders</h3>
            <div class="ms-auto"><button class="btn btn-sm btn-primary" data-permission="fleet.manage" onclick="editWorkOrder()"><i class="ti ti-plus me-1"></i>New work order</button></div></div>
          <div class="table-responsive"><table class="table card-table table-vcenter">
            <thead><tr><th>WO #</th><th>Title</th><th>Type</th><th>Status</th><th>Completed</th><th class="text-end cost-col">Cost</th><th></th></tr></thead><tbody id="woRows"></tbody></table></div>
        </div>
      </div>

      <div data-pane="inspections" class="d-none"><div class="card">
        <div class="card-header"><h3 class="card-title">Inspections</h3>
          <div class="ms-auto"><button class="btn btn-sm btn-primary" data-permission="fleet.manage" onclick="newInspection()"><i class="ti ti-clipboard-check me-1"></i>Record inspection</button></div></div>
        <div class="table-responsive"><table class="table card-table table-vcenter">
          <thead><tr><th>Date</th><th>Checklist</th><th>By</th><th>Result</th><th>Failed items</th><th></th></tr></thead><tbody id="inspRows"></tbody></table></div>
      </div></div>

      <div data-pane="compliance" class="d-none"><div class="card">
        <div class="card-header"><h3 class="card-title">Registration, insurance &amp; certifications</h3>
          <div class="ms-auto"><button class="btn btn-sm btn-primary" data-permission="fleet.manage" onclick="editCompliance()"><i class="ti ti-plus me-1"></i>Add record</button></div></div>
        <div class="table-responsive"><table class="table card-table table-vcenter">
          <thead><tr><th>Status</th><th>Kind</th><th>Reference</th><th>Issued</th><th>Expires</th><th>Issuer</th><th></th></tr></thead><tbody id="compRows"></tbody></table></div>
      </div></div>

      <div data-pane="documents" class="d-none"><div class="card">
        <div class="card-header"><h3 class="card-title">Documents</h3>
          <div class="ms-auto"><button class="btn btn-sm btn-primary" data-permission="fleet.documents.manage" onclick="uploadDocs()"><i class="ti ti-upload me-1"></i>Upload</button></div></div>
        <div class="table-responsive"><table class="table card-table table-vcenter">
          <thead><tr><th>Type</th><th>File</th><th>Label</th><th>Expires</th><th>Size</th><th>Added</th><th></th></tr></thead><tbody id="docRows"></tbody></table></div>
      </div></div>

      <div data-pane="timeline" class="d-none"><div class="card"><div class="card-body"><div class="list-group list-group-flush" id="timelineList"></div></div></div></div>
    </div>
  </main>
</div>
@endsection

@push('scripts')
@include('fleet._js')
<script>
  const ASSET_ID = {{ (int) $assetId }};
  const TAB_KEYS = ['overview', 'maintenance', 'inspections', 'compliance', 'documents', 'timeline'];
  let asset = null;
  let flOptions = { types: [], statuses: [], users: [] };
  let plans = [], workOrders = [], inspections = [], compliance = [], documents = [];
  const A = path => `/field-assets/${ASSET_ID}${path}`;
  const loadedTabs = {};

  function showTab(name) {
    if (!TAB_KEYS.includes(name)) name = 'overview';
    document.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('d-none', p.dataset.pane !== name));
    document.querySelectorAll('#assetTabs .nav-link').forEach(a => a.classList.toggle('active', a.dataset.tab === name));
    ({ overview: renderOverview, maintenance: loadMaintenance, inspections: loadInspections, compliance: loadCompliance, documents: loadDocuments, timeline: loadTimeline })[name]();
  }

  async function loadAsset() {
    try { asset = await authenticatedFetch(A('')); } catch (e) { document.getElementById('hdrName').textContent = 'Asset not found'; throw e; }
    document.getElementById('hdrNumber').textContent = asset.asset_number;
    document.getElementById('hdrName').textContent = asset.name;
    document.getElementById('hdrStatus').innerHTML = flStatus(asset.status);
    document.title = `${asset.asset_number} ${asset.name} - ForgeDesk`;
    document.getElementById('btnMeter').classList.toggle('d-none', asset.type.meter_type === 'none');
  }

  const meterUnit = () => ({ odometer_miles: 'miles', engine_hours: 'hours' }[asset.type.meter_type] || '');

  async function renderOverview() {
    const row = (k, v) => `<dt class="col-sm-4">${k}</dt><dd class="col-sm-8">${v == null || v === '' ? '—' : v}</dd>`;
    document.getElementById('detailList').innerHTML =
      row('Type', esc(asset.type.name)) + row('Make / model / year', esc([asset.make, asset.model, asset.year].filter(Boolean).join(' '))) +
      row('Serial #', esc(asset.serial_number)) + row('VIN', esc(asset.vin)) +
      row('Plate', esc([asset.license_plate, asset.plate_state].filter(Boolean).join(' '))) +
      row('Assigned to', esc(asset.assigned_user?.name)) + row('Home location', esc(asset.home_location?.name)) +
      row('Current location', esc(asset.current_location_text)) +
      row('Current job', asset.job ? esc((asset.job.job_name || '') + ' ' + (asset.job.job_number || '')) : '') +
      row('Parent asset', asset.parent ? `<a href="/fleet/assets/${asset.parent.id}">${esc(asset.parent.asset_number)} ${esc(asset.parent.name)}</a>` : '') +
      row('Purchased', flDate(asset.purchase_date) === '—' ? '' : flDate(asset.purchase_date)) +
      (asset.purchase_cost !== undefined ? row('Purchase cost', flMoney(asset.purchase_cost)) : '') +
      row('Vendor', esc(asset.vendor)) + row('Warranty expires', flDate(asset.warranty_expires_on) === '—' ? '' : flDate(asset.warranty_expires_on)) +
      row('Notes', esc(asset.notes).replace(/\n/g, '<br>'));
    document.getElementById('meterValue').textContent = asset.current_meter != null ? flNum(asset.current_meter) + ' ' + meterUnit() : (asset.type.meter_type === 'none' ? 'Not tracked' : 'No readings');
    document.getElementById('meterSub').textContent = asset.meter_updated_at ? 'Updated ' + new Date(asset.meter_updated_at).toLocaleString() : '';
    document.getElementById('childList').innerHTML = asset.children.length
      ? asset.children.map(c => `<div><a href="/fleet/assets/${c.id}">${esc(c.asset_number)} ${esc(c.name)}</a> ${flStatus(c.status)}</div>`).join('')
      : '<span class="text-muted">None</span>';
    if (asset.type.meter_type !== 'none') {
      try {
        const h = await authenticatedFetch(A('/meter-readings'));
        document.getElementById('meterHistory').innerHTML = h.slice(0, 6).map(r => `<div class="d-flex justify-content-between"><span>${flNum(r.reading)}</span><span class="text-muted">${flDate(r.read_at)} · ${esc(r.source)}</span></div>`).join('');
      } catch (e) {}
    }
  }

  // ---- header actions ----
  async function ensureOptions() {
    if (!flOptions.types.length) flOptions = await authenticatedFetch('/field-assets/filter-options');
  }

  function updateMeter() {
    flModal('Update meter', flField('reading', `Reading (${meterUnit()})`, '', 'number', 'step="0.1" min="0" required', 'col-12') +
      `<div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="is_correction"><span class="form-check-label">This is a correction (reading is lower than the current meter)</span></label></div>`,
      async f => { await flApi(A('/meter'), 'POST', flFormData(f)); await loadAsset(); showTab(currentTab()); }, 'Save');
  }

  function changeStatus() {
    flModal('Change status', flSelect('status', 'Status', flOpts(Object.keys(FL.STATUS), asset.status), 'col-12'),
      async f => { await flApi(A('/status'), 'POST', flFormData(f)); await loadAsset(); showTab(currentTab()); });
  }

  async function editAsset() {
    await ensureOptions();
    flModal('Edit asset', `
      ${flSelect('field_asset_type_id', 'Type', flOpts(flOptions.types, asset.field_asset_type_id))}
      ${flField('name', 'Name', asset.name, 'text', 'required')}
      ${flField('asset_number', 'Asset #', asset.asset_number)}
      ${flField('make', 'Make', asset.make)}${flField('model', 'Model', asset.model)}${flField('year', 'Year', asset.year, 'number')}
      ${flField('serial_number', 'Serial #', asset.serial_number)}${flField('vin', 'VIN', asset.vin)}
      ${flField('license_plate', 'Plate', asset.license_plate)}${flField('plate_state', 'Plate state', asset.plate_state)}
      ${flSelect('assigned_user_id', 'Assigned to', flOpts(flOptions.users, asset.assigned_user_id, '— none —'))}
      ${flField('current_location_text', 'Current location / job text', asset.current_location_text)}
      ${flField('purchase_date', 'Purchase date', flDate(asset.purchase_date) === '—' ? '' : flDate(asset.purchase_date), 'date')}
      ${asset.purchase_cost !== undefined ? flField('purchase_cost', 'Purchase cost', asset.purchase_cost, 'number', 'step="0.01" min="0"') : ''}
      ${flField('vendor', 'Vendor', asset.vendor)}${flField('warranty_expires_on', 'Warranty expires', flDate(asset.warranty_expires_on) === '—' ? '' : flDate(asset.warranty_expires_on), 'date')}
      ${flField('parent_asset_id', 'Parent asset ID (e.g. the trailer this box rides on)', asset.parent_asset_id, 'number')}
      ${flText('notes', 'Notes', asset.notes)}`,
      async f => { await flApi(A(''), 'PUT', flFormData(f)); await loadAsset(); showTab(currentTab()); }, 'Save', 'modal-lg');
  }

  function deleteAsset() {
    flConfirm(`Delete ${asset.asset_number}? Its history and files are kept but it will no longer appear in lists. (To keep it visible as history, set the status to Retired or Sold instead.)`,
      async () => { await flApi(A(''), 'DELETE'); location.href = '/fleet#assets'; });
  }

  // ---- maintenance ----
  async function loadMaintenance() {
    try {
      [plans, workOrders] = await Promise.all([authenticatedFetch(A('/plans')), authenticatedFetch(`/field-asset-work-orders?asset_id=${ASSET_ID}&per_page=100`).then(r => r.data)]);
    } catch (e) { return; }
    const costs = FL.canCosts();
    document.querySelectorAll('.cost-col').forEach(c => c.classList.toggle('d-none', !costs));
    document.getElementById('planRows').innerHTML = plans.length ? plans.map(p => `<tr>
      <td>${flDue(p.due_state)}</td><td>${esc(p.title)}${p.is_active ? '' : ' ' + flBadge('paused', 'secondary')}</td>
      <td class="small">${[p.interval_days ? p.interval_days + ' days' : '', p.interval_meter ? flNum(p.interval_meter) + ' ' + meterUnit() : ''].filter(Boolean).join(' / ')}</td>
      <td class="small">${flDate(p.last_done_at)} ${p.last_done_meter ? '@ ' + flNum(p.last_done_meter) : ''}</td>
      <td class="small">${flDate(p.next_due_at)} ${p.next_due_meter ? '@ ' + flNum(p.next_due_meter) : ''}</td>
      <td class="text-end text-nowrap"><span data-permission="fleet.manage">
        <button class="btn btn-sm" onclick="editPlan(${p.id})">Edit</button>
        <button class="btn btn-sm btn-primary" onclick="editWorkOrder(null, ${p.id})">Log service</button></span></td></tr>`).join('')
      : '<tr><td colspan="6" class="text-center text-muted py-3">No maintenance plans yet.</td></tr>';
    document.getElementById('woRows').innerHTML = workOrders.length ? workOrders.map(w => `<tr>
      <td class="text-nowrap">${esc(w.wo_number)}</td><td>${esc(w.title)}</td><td>${flLabel(w.type)}</td><td>${flWoStatus(w.status)}</td>
      <td>${flDate(w.completed_on)}</td><td class="text-end ${costs ? '' : 'd-none'}">${w.total_cost !== undefined ? flMoney(w.total_cost) : ''}</td>
      <td class="text-end text-nowrap"><button class="btn btn-sm" onclick="viewWorkOrder(${w.id})">Open</button></td></tr>`).join('')
      : '<tr><td colspan="7" class="text-center text-muted py-3">No work orders.</td></tr>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  function editPlan(id) {
    const p = plans.find(x => x.id === id) || { warn_days: 14, priority: 'medium', is_active: true };
    flModal(id ? 'Edit plan' : 'Add maintenance plan',
      flField('title', 'Title', p.title, 'text', 'required', 'col-12') +
      flField('interval_days', 'Every (days)', p.interval_days, 'number', 'min="1"') +
      flField('interval_meter', `Every (${meterUnit() || 'meter units'})`, p.interval_meter, 'number', 'min="1" step="1"' + (asset.type.meter_type === 'none' ? ' disabled' : '')) +
      flField('warn_days', 'Warn (days before)', p.warn_days, 'number', 'min="0"') +
      flField('warn_meter', 'Warn (meter units before)', p.warn_meter, 'number', 'min="0"' + (asset.type.meter_type === 'none' ? ' disabled' : '')) +
      (id ? '' : flField('starts_on', 'Counting from (date)', new Date().toISOString().slice(0, 10), 'date') +
        flField('starts_meter', 'Counting from (meter)', asset.current_meter, 'number', 'step="0.1"' + (asset.type.meter_type === 'none' ? ' disabled' : ''))) +
      flSelect('priority', 'Priority', flOpts(['low', 'medium', 'high', 'critical'], p.priority)) +
      flField('default_vendor', 'Usual vendor', p.default_vendor) +
      flText('description', 'Notes', p.description, 2) +
      `<div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" ${p.is_active ? 'checked' : ''}><span class="form-check-label">Active</span></label></div>`,
      async f => { await flApi(id ? `/field-asset-plans/${id}` : A('/plans'), id ? 'PUT' : 'POST', flFormData(f)); loadMaintenance(); }, 'Save', 'modal-lg');
  }

  function editWorkOrder(id, planId) {
    const plan = plans.find(x => x.id === planId);
    flModal('New work order',
      flField('title', 'Title', plan ? plan.title : '', 'text', 'required', 'col-12') +
      flSelect('type', 'Type', flOpts(['repair', 'preventive', 'recall', 'other'], plan ? 'preventive' : 'repair')) +
      flSelect('priority', 'Priority', flOpts(['low', 'medium', 'high', 'critical'], plan ? plan.priority : 'medium')) +
      flField('scheduled_for', 'Scheduled for', '', 'date') + flField('vendor', 'Vendor', plan ? plan.default_vendor : '') +
      `<input type="hidden" name="plan_id" value="${plan ? plan.id : ''}">` + flText('description', 'Description', '', 3),
      async f => {
        const d = flFormData(f); d.field_asset_id = ASSET_ID;
        const wo = await flApi('/field-asset-work-orders', 'POST', d);
        await loadMaintenance();
        if (plan) viewWorkOrder(wo.id);
      }, 'Create');
  }

  async function viewWorkOrder(id) {
    const w = await flApi(`/field-asset-work-orders/${id}`);
    const costs = FL.canCosts(), done = ['completed', 'cancelled'].includes(w.status);
    const items = (w.items || []).map(i => `<tr><td>${esc(i.description)}</td><td class="text-end">${flNum(i.qty)}</td>${costs ? `<td class="text-end">${flMoney(i.unit_cost)}</td>` : ''}</tr>`).join('');
    const docs = (w.documents || []).map(d => `<div><a href="${flDocUrl(ASSET_ID, d.id)}" target="_blank">${esc(d.original_name)}</a> <span class="text-muted small">${flLabel(d.doc_type)}</span></div>`).join('') || '<span class="text-muted">None</span>';
    const form = flModal(`${w.wo_number} · ${w.title}`, `
      <div class="col-12">${flWoStatus(w.status)} ${flBadge(w.priority, FL.PRIORITY[w.priority])} <span class="text-muted ms-2">${flLabel(w.type)} · reported ${flDate(w.reported_on)}${w.source === 'field_app' ? ' · from field app' + (w.reported_by_external ? ' (' + esc(w.reported_by_external) + ')' : '') : ''}</span></div>
      <div class="col-12" style="white-space:pre-wrap">${esc(w.description) || '<span class="text-muted">No description</span>'}</div>
      ${items ? `<div class="col-12"><table class="table table-sm"><thead><tr><th>Item</th><th class="text-end">Qty</th>${costs ? '<th class="text-end">Unit</th>' : ''}</tr></thead><tbody>${items}</tbody></table></div>` : ''}
      ${w.completed_on ? `<div class="col-12 small">Completed ${flDate(w.completed_on)}${w.meter_at_service ? ' @ ' + flNum(w.meter_at_service) : ''}${w.vendor ? ' · ' + esc(w.vendor) : ''}${w.performed_by ? ' · ' + esc(w.performed_by) : ''}${costs ? ' · total ' + flMoney(w.total_cost) : ''}</div>` : ''}
      <div class="col-12"><div class="subheader">Documents</div>${docs}</div>
      ${done ? '' : `<div class="col-12 d-flex gap-2" data-permission="fleet.manage">
        <select class="form-select w-auto" id="woStatusSel">${flOpts(['open', 'scheduled', 'in_progress', 'waiting_parts'], w.status)}</select>
        <button type="button" class="btn" id="woStatusBtn">Set status</button>
        <button type="button" class="btn btn-success ms-auto" id="woCompleteBtn"><i class="ti ti-check me-1"></i>Complete…</button>
        <button type="button" class="btn btn-outline-danger" id="woCancelBtn">Cancel WO</button></div>`}`, null, '', 'modal-lg');
    form.querySelector('#woStatusBtn')?.addEventListener('click', async () => { await flApi(`/field-asset-work-orders/${id}`, 'PUT', { status: form.querySelector('#woStatusSel').value }); flModalInst.hide(); await loadAsset(); loadMaintenance(); });
    form.querySelector('#woCancelBtn')?.addEventListener('click', async () => { await flApi(`/field-asset-work-orders/${id}`, 'PUT', { status: 'cancelled' }); flModalInst.hide(); loadMaintenance(); });
    form.querySelector('#woCompleteBtn')?.addEventListener('click', () => { flModalInst.hide(); setTimeout(() => completeWorkOrder(w), 350); });
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  function completeWorkOrder(w) {
    const costs = FL.canCosts();
    flModal(`Complete ${w.wo_number}`,
      flField('completed_on', 'Completed on', new Date().toISOString().slice(0, 10), 'date', 'max="' + new Date().toISOString().slice(0, 10) + '"') +
      (asset.type.meter_type !== 'none' ? flField('meter_at_service', `Meter at service (${meterUnit()})`, asset.current_meter, 'number', 'step="0.1" min="0"') : '<div class="col-md-6"></div>') +
      flField('performed_by', 'Performed by', '') + flField('vendor', 'Vendor', w.vendor) +
      flField('labor_hours', 'Labor hours', '', 'number', 'step="0.25" min="0"') + flField('downtime_days', 'Downtime (days)', '', 'number', 'min="0"') +
      (costs ? flField('labor_cost', 'Labor cost', '', 'number', 'step="0.01" min="0"') + flField('parts_cost', 'Parts cost', '', 'number', 'step="0.01" min="0"') +
        flField('outside_cost', 'Outside / vendor cost', '', 'number', 'step="0.01" min="0"') : '') +
      flField('invoice_number', 'Invoice #', '') + flText('notes', 'Work performed / notes', w.notes, 3),
      async f => { await flApi(`/field-asset-work-orders/${w.id}/complete`, 'POST', flFormData(f)); await loadAsset(); loadMaintenance();
        showNotification('Work order completed. Attach the invoice on the Documents tab.', 'success'); }, 'Complete', 'modal-lg');
  }

  // ---- inspections ----
  async function loadInspections() {
    try { inspections = await authenticatedFetch(A('/inspections')); } catch (e) { return; }
    const color = { pass: 'success', pass_with_issues: 'warning', fail: 'danger' };
    document.getElementById('inspRows').innerHTML = inspections.length ? inspections.map(i => {
      const failed = i.items.filter(x => x.result === 'fail');
      return `<tr><td class="text-nowrap">${flDate(i.performed_at)}</td><td>${esc(i.template_name || 'General')}</td>
        <td>${esc(i.performed_by?.name || i.submitted_by_external || '—')} ${i.source === 'field_app' ? flBadge('Field app', 'purple') : ''}</td>
        <td>${flBadge(i.result.replace(/_/g, ' '), color[i.result])}</td>
        <td class="small">${failed.map(x => esc(x.label) + (x.critical ? ' ⚠' : '') + (x.comment ? ' — ' + esc(x.comment) : '')).join('<br>') || '—'}</td>
        <td class="text-end">${failed.length ? `<button class="btn btn-sm" data-permission="fleet.manage" onclick="inspectionWo(${i.id})">Create work order</button>` : ''}</td></tr>`;
    }).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">No inspections recorded.</td></tr>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  async function inspectionWo(id) {
    const wo = await flApi(`/field-asset-inspections/${id}/create-work-order`, 'POST');
    showNotification('Created ' + wo.wo_number, 'success');
  }

  async function newInspection() {
    const cl = await authenticatedFetch(A('/inspection-checklist'));
    const rows = cl.items.map((it, n) => `<div class="col-12 border-bottom pb-2 insp-row" data-label="${esc(it.label)}" data-critical="${it.critical ? 1 : 0}">
      <div class="d-flex align-items-center gap-2"><div class="flex-grow-1">${esc(it.label)} ${it.critical ? '<span class="text-danger" title="Safety critical">⚠</span>' : ''}</div>
      <select class="form-select w-auto insp-result"><option value="pass">Pass</option><option value="fail">Fail</option><option value="na">N/A</option></select></div>
      <input class="form-control form-control-sm mt-1 insp-comment" placeholder="Comment (required detail for failures)"></div>`).join('');
    flModal('Record inspection', flField('template_name', 'Checklist', cl.template_name, 'text', '', 'col-md-8') +
      (asset.type.meter_type !== 'none' ? flField('meter', `Meter (${meterUnit()})`, asset.current_meter, 'number', 'step="0.1" min="0"', 'col-md-4') : '') +
      rows + flText('notes', 'Notes', '', 2) +
      `<div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="mark_out_of_service"><span class="form-check-label">If a critical item fails, mark this asset Out of Service</span></label></div>`,
      async f => {
        const d = flFormData(f);
        d.items = [...f.querySelectorAll('.insp-row')].map(r => ({ label: r.dataset.label, critical: r.dataset.critical === '1',
          result: r.querySelector('.insp-result').value, comment: r.querySelector('.insp-comment').value || null }));
        const res = await flApi(A('/inspections'), 'POST', d);
        showNotification('Inspection recorded: ' + res.inspection.result.replace(/_/g, ' ') + (res.work_order ? ` — ${res.work_order.wo_number} opened` : ''), res.inspection.result === 'pass' ? 'success' : 'warning');
        await loadAsset(); loadInspections();
      }, 'Save inspection', 'modal-lg');
  }

  // ---- compliance ----
  async function loadCompliance() {
    try { compliance = await authenticatedFetch(A('/compliance')); } catch (e) { return; }
    document.getElementById('compRows').innerHTML = compliance.length ? compliance.map(c => {
      const d = c.days_until_expiry;
      const st = d == null ? '<span class="text-muted">No expiry</span>' : d < 0 ? flDue('expired') : d <= 30 ? flDue('expiring') : flDue('ok');
      return `<tr><td>${st}</td><td>${flLabel(c.kind)}</td><td>${esc(c.reference_number)}</td><td>${flDate(c.issued_on)}</td>
        <td>${flDate(c.expires_on)} ${d != null ? `<span class="text-muted small">(${d < 0 ? Math.abs(d) + 'd ago' : d + 'd'})</span>` : ''}</td><td>${esc(c.issuer)}</td>
        <td class="text-end text-nowrap" data-permission="fleet.manage"><button class="btn btn-sm" onclick="editCompliance(${c.id})">Edit</button>
        <button class="btn btn-sm btn-outline-danger" onclick="deleteCompliance(${c.id})"><i class="ti ti-trash"></i></button></td></tr>`;
    }).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">No compliance records.</td></tr>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  function editCompliance(id) {
    const c = compliance.find(x => x.id === id) || {};
    flModal(id ? 'Edit record' : 'Add compliance record',
      flSelect('kind', 'Kind', flOpts(FL.KINDS, c.kind || 'registration')) + flField('reference_number', 'Reference / policy #', c.reference_number) +
      flField('issued_on', 'Issued', flDate(c.issued_on) === '—' ? '' : flDate(c.issued_on), 'date') +
      flField('expires_on', 'Expires', flDate(c.expires_on) === '—' ? '' : flDate(c.expires_on), 'date') +
      flField('issuer', 'Issuer / carrier', c.issuer) + (FL.canCosts() ? flField('cost', 'Cost', c.cost, 'number', 'step="0.01" min="0"') : '') +
      flText('notes', 'Notes', c.notes, 2),
      async f => { await flApi(id ? `/field-asset-compliance/${id}` : A('/compliance'), id ? 'PUT' : 'POST', flFormData(f)); loadCompliance(); }, 'Save');
  }

  function deleteCompliance(id) {
    flConfirm('Delete this compliance record?', async () => { await flApi(`/field-asset-compliance/${id}`, 'DELETE'); loadCompliance(); });
  }

  // ---- documents ----
  async function loadDocuments() {
    try { documents = await authenticatedFetch(A('/documents')); } catch (e) { return; }
    const size = b => b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.ceil((b || 0) / 1024) + ' KB';
    document.getElementById('docRows').innerHTML = documents.length ? documents.map(d => `<tr>
      <td>${flLabel(d.doc_type)}</td><td><a href="${flDocUrl(ASSET_ID, d.id)}" target="_blank" rel="noopener">${esc(d.original_name)}</a></td><td>${esc(d.label)}</td>
      <td>${flDate(d.expires_on)}</td><td>${size(d.file_size)}</td><td class="small">${flDate(d.created_at)}${d.work_order_id ? ' · WO' : ''}${d.inspection_id ? ' · inspection' : ''}</td>
      <td class="text-end" data-permission="fleet.documents.manage"><button class="btn btn-sm btn-outline-danger" onclick="deleteDoc(${d.id})"><i class="ti ti-trash"></i></button></td></tr>`).join('')
      : '<tr><td colspan="7" class="text-center text-muted py-3">No documents yet.</td></tr>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  async function uploadDocs() {
    let wos = [];
    try { wos = (await authenticatedFetch(`/field-asset-work-orders?asset_id=${ASSET_ID}&per_page=100`)).data; } catch (e) {}
    flModal('Upload documents', flSelect('doc_type', 'Type', flOpts(FL.DOC_TYPES, 'other')) + flField('label', 'Label (optional)', '') +
      flField('expires_on', 'Expires (insurance, certs…)', '', 'date') +
      flSelect('work_order_id', 'Attach to work order', flOpts(wos.map(w => ({ id: w.id, name: `${w.wo_number} · ${w.title}` })), '', '— asset only —')) +
      `<div class="col-12"><label class="form-label">Files (PDF, images, Office, CSV — max 25 MB each, up to 10)</label>
       <input type="file" class="form-control" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.xls,.xlsx,.csv,.txt,image/*"></div>`,
      async f => {
        const fd = new FormData(f);
        for (const [k, v] of [...fd.entries()]) if (typeof v === 'string' && v === '') fd.delete(k);
        const r = await authenticatedUpload(A('/documents'), fd);
        const data = await r.json().catch(() => ({}));
        if (!r.ok) { showNotification(data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Upload failed'), 'danger'); throw new Error('upload'); }
        showNotification('Uploaded ' + data.length + ' file(s)', 'success'); loadDocuments();
      }, 'Upload');
  }

  function deleteDoc(id) {
    flConfirm('Delete this document? The file is retained on the server for recovery.', async () => { await flApi(A(`/documents/${id}`), 'DELETE'); loadDocuments(); });
  }

  // ---- timeline ----
  async function loadTimeline() {
    try {
      const ev = await authenticatedFetch(A('/timeline'));
      const icon = { work_order: 'ti-tool', inspection: 'ti-clipboard-check', compliance: 'ti-certificate', meter: 'ti-gauge' };
      document.getElementById('timelineList').innerHTML = ev.length ? ev.map(e => `<div class="list-group-item d-flex gap-3 align-items-center">
        <i class="ti ${icon[e.kind] || 'ti-point'} fs-2 text-muted"></i><div class="flex-grow-1"><div>${esc(e.title)}</div><div class="text-muted small">${esc(e.detail)}</div></div>
        <div class="text-muted small text-nowrap">${esc(e.at)}</div></div>`).join('') : '<div class="text-muted text-center py-3">No history yet.</div>';
    } catch (e) {}
  }

  const currentTab = () => (TAB_KEYS.includes(location.hash.slice(1)) ? location.hash.slice(1) : 'overview');

  document.addEventListener('DOMContentLoaded', async () => {
    document.querySelectorAll('#assetTabs .nav-link').forEach(a => a.addEventListener('click', e => { e.preventDefault(); history.replaceState(null, '', '#' + a.dataset.tab); showTab(a.dataset.tab); }));
    window.addEventListener('hashchange', () => showTab(currentTab()));
    try { await loadAsset(); } catch (e) { return; }
    showTab(currentTab());
  });
</script>
@endpush
