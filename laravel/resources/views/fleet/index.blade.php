@extends('layouts.app')

@section('title', 'Field & Fleet - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Field &amp; Fleet</div>
          <h1 class="page-title">Field &amp; Fleet Assets</h1>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <button class="btn btn-primary" data-permission="fleet.manage" onclick="addAsset()"><i class="ti ti-plus me-1"></i>Add Asset</button>
        </div>
      </div>
    </div>
  </div>

  <main class="page-body">
    <div class="container-xl">
      <div id="syncBanner" class="alert alert-warning d-none"></div>

      <ul class="nav nav-tabs mb-3" id="fleetTabs">
        <li class="nav-item"><a class="nav-link active" href="#overview" data-tab="overview">Overview</a></li>
        <li class="nav-item"><a class="nav-link" href="#assets" data-tab="assets">Assets</a></li>
        <li class="nav-item"><a class="nav-link" href="#work-orders" data-tab="work-orders">Work Orders</a></li>
        <li class="nav-item" data-permission="fleet.manage"><a class="nav-link" href="#inbox" data-tab="inbox">Field Inbox <span class="badge bg-red text-white ms-1 d-none" id="inboxBadge"></span></a></li>
        <li class="nav-item" data-permission="fleet.manage"><a class="nav-link" href="#types" data-tab="types">Asset Types</a></li>
      </ul>

      {{-- Overview --}}
      <div data-pane="overview">
        <div class="row row-cards mb-3" id="statTiles"></div>
        <div class="row row-cards">
          <div class="col-lg-6"><div class="card"><div class="card-header"><h3 class="card-title">Maintenance due</h3></div>
            <div class="table-responsive"><table class="table card-table table-vcenter"><tbody id="duePlans"></tbody></table></div></div></div>
          <div class="col-lg-6"><div class="card"><div class="card-header"><h3 class="card-title">Expiring compliance</h3></div>
            <div class="table-responsive"><table class="table card-table table-vcenter"><tbody id="dueCompliance"></tbody></table></div></div></div>
        </div>
      </div>

      {{-- Assets --}}
      <div data-pane="assets" class="d-none">
        <div class="card">
          <div class="card-header gap-2 flex-wrap">
            <div class="input-icon flex-grow-1" style="min-width:200px">
              <span class="input-icon-addon"><i class="ti ti-search"></i></span>
              <input type="text" class="form-control" id="assetSearch" placeholder="Search number, name, VIN, plate, serial…" autocomplete="off">
            </div>
            <select class="form-select w-auto" id="assetType"></select>
            <select class="form-select w-auto" id="assetStatus"></select>
            <label class="form-check mb-0"><input type="checkbox" class="form-check-input" id="assetOverdue"><span class="form-check-label">Overdue only</span></label>
          </div>
          <div class="table-responsive">
            <table class="table card-table table-vcenter table-hover">
              <thead><tr><th>#</th><th>Name</th><th>Type</th><th>Status</th><th class="text-end">Meter</th><th>Assigned</th><th>Location / Job</th><th class="text-end">Open WOs</th></tr></thead>
              <tbody id="assetRows"><tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Work orders --}}
      <div data-pane="work-orders" class="d-none">
        <div class="card">
          <div class="card-header gap-2">
            <select class="form-select w-auto" id="woFilter">
              <option value="open">Open</option><option value="all">All</option><option value="completed">Completed</option>
            </select>
            <div class="ms-auto"><button class="btn btn-primary" data-permission="fleet.manage" onclick="addWorkOrder()"><i class="ti ti-plus me-1"></i>New Work Order</button></div>
          </div>
          <div class="table-responsive">
            <table class="table card-table table-vcenter">
              <thead><tr><th>WO #</th><th>Asset</th><th>Title</th><th>Type</th><th>Priority</th><th>Status</th><th>Scheduled</th><th>Source</th></tr></thead>
              <tbody id="woRows"></tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Field inbox --}}
      <div data-pane="inbox" class="d-none">
        <div class="alert alert-info">Submissions from the field app that were rejected or held for review. Valid submissions are applied automatically and do not appear here.</div>
        <div class="card"><div class="table-responsive">
          <table class="table card-table table-vcenter">
            <thead><tr><th>Received</th><th>Kind</th><th>Asset</th><th>Status</th><th>Reason</th><th>Payload</th><th></th></tr></thead>
            <tbody id="inboxRows"></tbody>
          </table></div></div>
      </div>

      {{-- Types --}}
      <div data-pane="types" class="d-none">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Asset types &amp; inspection checklists</h3>
            <div class="ms-auto"><button class="btn btn-primary" onclick="editType()"><i class="ti ti-plus me-1"></i>Add Type</button></div></div>
          <div class="table-responsive"><table class="table card-table table-vcenter">
            <thead><tr><th>Name</th><th>Category</th><th>Meter</th><th>Checklist items</th><th>Auto out-of-service</th><th>Assets</th><th></th></tr></thead>
            <tbody id="typeRows"></tbody></table></div>
        </div>
      </div>
    </div>
  </main>
</div>
@endsection

@push('scripts')
@include('fleet._js')
<script>
  let flOptions = { types: [], statuses: [], users: [] };
  let flTypes = [];
  const loaded = {};

  const TABS = ['overview', 'assets', 'work-orders', 'inbox', 'types'];

  function showTab(name) {
    if (!TABS.includes(name)) name = 'overview';
    document.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('d-none', p.dataset.pane !== name));
    document.querySelectorAll('#fleetTabs .nav-link').forEach(a => a.classList.toggle('active', a.dataset.tab === name));
    if (name === 'overview') loadOverview();
    if (name === 'assets') loadAssets();
    if (name === 'work-orders') loadWorkOrders();
    if (name === 'inbox') loadInbox();
    if (name === 'types') loadTypes();
  }

  async function loadOverview() {
    try {
      const d = await authenticatedFetch('/fleet/dashboard');
      const tile = (label, value, cls = '', href = '') => `<div class="col-6 col-lg"><div class="card card-sm"><div class="card-body">
        <div class="subheader">${label}</div><div class="h1 mb-0 ${cls}">${value}</div></div></div></div>`;
      document.getElementById('statTiles').innerHTML =
        tile('Assets in service', d.total_assets) +
        tile('Maintenance overdue', d.overdue_plans, d.overdue_plans ? 'text-danger' : '') +
        tile('Due soon', d.due_soon_plans, d.due_soon_plans ? 'text-warning' : '') +
        tile('Compliance expired', d.expired_compliance, d.expired_compliance ? 'text-danger' : '') +
        tile('Expiring (90d)', d.expiring_compliance, d.expiring_compliance ? 'text-warning' : '') +
        tile('Open work orders', d.open_work_orders) +
        (d.cost_12_months !== undefined ? tile('Maint. cost (12 mo)', flMoney(d.cost_12_months)) : '');

      const badge = document.getElementById('inboxBadge');
      badge.textContent = d.inbox_pending; badge.classList.toggle('d-none', !d.inbox_pending);

      const sync = document.getElementById('syncBanner');
      sync.classList.toggle('d-none', !(d.sync.enabled && d.sync.stale));
      sync.textContent = 'Field app sync looks stale — last pull ' + (d.sync.last_in ? new Date(d.sync.last_in).toLocaleString() : 'never') + '. Field submissions may be waiting.';

      document.getElementById('duePlans').innerHTML = d.plans.length ? d.plans.map(p => `<tr>
        <td>${flDue(p.state)}</td><td><a href="/fleet/assets/${p.asset.id}">${esc(p.asset.asset_number)}</a> ${esc(p.asset.name)}</td>
        <td>${esc(p.title)}</td><td class="text-muted small">${p.next_due_at ? flDate(p.next_due_at) : ''} ${p.next_due_meter ? '@ ' + flNum(p.next_due_meter) : ''}</td></tr>`).join('')
        : '<tr><td class="text-muted text-center py-3">Nothing due. 🎉</td></tr>';
      document.getElementById('dueCompliance').innerHTML = d.compliance.length ? d.compliance.map(c => `<tr>
        <td>${flDue(c.state)}</td><td><a href="/fleet/assets/${c.asset.id}">${esc(c.asset.asset_number)}</a> ${esc(c.asset.name)}</td>
        <td>${flLabel(c.kind)}</td><td class="text-muted small">${flDate(c.expires_on)} (${c.days < 0 ? Math.abs(c.days) + 'd ago' : c.days + 'd'})</td></tr>`).join('')
        : '<tr><td class="text-muted text-center py-3">Nothing expiring.</td></tr>';
    } catch (e) { console.error(e); }
  }

  // ---- assets ----
  async function loadOptions() {
    flOptions = await authenticatedFetch('/field-assets/filter-options');
    document.getElementById('assetType').innerHTML = flOpts(flOptions.types, '', 'All types');
    document.getElementById('assetStatus').innerHTML = '<option value="">In service</option>' + flOpts(flOptions.statuses, '');
  }

  async function loadAssets() {
    const p = new URLSearchParams();
    const v = id => document.getElementById(id).value;
    if (v('assetSearch')) p.set('search', v('assetSearch'));
    if (v('assetType')) p.set('type_id', v('assetType'));
    if (v('assetStatus')) p.set('status', v('assetStatus'));
    if (document.getElementById('assetOverdue').checked) p.set('overdue_only', '1');
    try {
      const res = await authenticatedFetch('/field-assets?' + p);
      const rows = res.data;
      document.getElementById('assetRows').innerHTML = rows.length ? rows.map(a => `<tr style="cursor:pointer" onclick="location.href='/fleet/assets/${a.id}'">
        <td class="text-nowrap"><strong>${esc(a.asset_number)}</strong></td><td>${esc(a.name)}<div class="text-muted small">${esc([a.year, a.make, a.model].filter(Boolean).join(' '))}</div></td>
        <td>${esc(a.type?.name)}</td><td>${flStatus(a.status)}</td>
        <td class="text-end">${a.current_meter != null ? flNum(a.current_meter) : '—'}</td>
        <td>${esc(a.assigned_user?.name || '—')}</td>
        <td>${esc(a.job ? (a.job.job_name || a.job.job_number) : (a.current_location_text || '—'))}</td>
        <td class="text-end">${a.open_work_orders_count || ''}</td></tr>`).join('')
        : '<tr><td colspan="8" class="text-center text-muted py-4">No assets match.</td></tr>';
    } catch (e) { console.error(e); }
  }

  function assetForm(a = {}) {
    return flSelect('field_asset_type_id', 'Type', flOpts(flOptions.types, a.field_asset_type_id)) +
      flField('name', 'Name', a.name, 'text', 'required') +
      flField('asset_number', 'Asset # (blank = auto)', a.asset_number || '', 'text', `placeholder="${esc(flOptions.next_asset_number || '')}"`) +
      flField('make', 'Make', a.make) + flField('model', 'Model', a.model) + flField('year', 'Year', a.year, 'number') +
      flField('serial_number', 'Serial #', a.serial_number) + flField('vin', 'VIN', a.vin) +
      flField('license_plate', 'Plate', a.license_plate) + flField('plate_state', 'Plate state', a.plate_state) +
      (a.id ? '' : flField('current_meter', 'Current meter (miles / hours)', '', 'number', 'step="0.1" min="0"')) +
      flSelect('assigned_user_id', 'Assigned to', flOpts(flOptions.users, a.assigned_user_id, '— none —')) +
      flField('current_location_text', 'Current location / job text', a.current_location_text) +
      flField('purchase_date', 'Purchase date', flDate(a.purchase_date) === '—' ? '' : flDate(a.purchase_date), 'date') +
      (FL.canCosts() ? flField('purchase_cost', 'Purchase cost', a.purchase_cost, 'number', 'step="0.01" min="0"') : '') +
      flField('vendor', 'Vendor', a.vendor) + flField('warranty_expires_on', 'Warranty expires', flDate(a.warranty_expires_on) === '—' ? '' : flDate(a.warranty_expires_on), 'date') +
      flText('notes', 'Notes', a.notes);
  }

  function addAsset() {
    flModal('Add Asset', assetForm(), async f => {
      const a = await flApi('/field-assets', 'POST', flFormData(f));
      location.href = '/fleet/assets/' + a.id;
    }, 'Create', 'modal-lg');
  }

  // ---- work orders ----
  async function loadWorkOrders() {
    const f = document.getElementById('woFilter').value;
    const q = f === 'open' ? '?open_only=1' : (f === 'completed' ? '?status=completed' : '');
    try {
      const res = await authenticatedFetch('/field-asset-work-orders' + q);
      document.getElementById('woRows').innerHTML = res.data.length ? res.data.map(w => `<tr style="cursor:pointer" onclick="location.href='/fleet/assets/${w.field_asset_id}#maintenance'">
        <td class="text-nowrap">${esc(w.wo_number)}</td><td>${esc(w.asset?.asset_number)} ${esc(w.asset?.name)}</td><td>${esc(w.title)}</td>
        <td>${flLabel(w.type)}</td><td>${flBadge(w.priority, FL.PRIORITY[w.priority] || 'secondary')}</td><td>${flWoStatus(w.status)}</td>
        <td>${flDate(w.scheduled_for)}</td><td>${w.source === 'field_app' ? flBadge('Field app', 'purple') : 'Office'}</td></tr>`).join('')
        : '<tr><td colspan="8" class="text-center text-muted py-4">No work orders.</td></tr>';
    } catch (e) { console.error(e); }
  }

  async function addWorkOrder() {
    const res = await authenticatedFetch('/field-assets?per_page=200');
    flModal('New Work Order', flSelect('field_asset_id', 'Asset', flOpts(res.data.map(a => ({ id: a.id, name: `${a.asset_number} · ${a.name}` })), '', 'Select asset…'), 'col-12') +
      flField('title', 'Title', '', 'text', 'required', 'col-12') +
      flSelect('type', 'Type', flOpts(['repair', 'preventive', 'recall', 'other'], 'repair')) +
      flSelect('priority', 'Priority', flOpts(['low', 'medium', 'high', 'critical'], 'medium')) +
      flField('scheduled_for', 'Scheduled for', '', 'date') + flField('vendor', 'Vendor', '') +
      flText('description', 'Description'), async f => {
      const d = flFormData(f);
      const wo = await flApi('/field-asset-work-orders', 'POST', d);
      showNotification('Work order ' + wo.wo_number + ' created', 'success');
      loadWorkOrders();
    }, 'Create');
  }

  // ---- inbox ----
  async function loadInbox() {
    try {
      const res = await authenticatedFetch('/fleet/inbox');
      document.getElementById('inboxRows').innerHTML = res.data.length ? res.data.map(r => `<tr>
        <td class="text-nowrap small">${esc(new Date(r.created_at).toLocaleString())}</td><td>${flLabel(r.kind)}</td><td>${esc(r.asset_number)}</td>
        <td>${flBadge(r.status.replace('_', ' '), r.status === 'rejected' ? 'danger' : 'warning')}</td><td>${esc(r.error)}</td>
        <td><code class="small">${esc(JSON.stringify(r.payload || {}).substring(0, 120))}</code></td>
        <td class="text-nowrap text-end">${r.kind === 'meter' && r.status === 'needs_review' ? `<button class="btn btn-sm btn-primary" onclick="resolveInbox(${r.id}, 'apply_meter')">Apply as correction</button> ` : ''}
          <button class="btn btn-sm" onclick="resolveInbox(${r.id}, 'dismiss')">Dismiss</button></td></tr>`).join('')
        : '<tr><td colspan="7" class="text-center text-muted py-4">Nothing waiting for review.</td></tr>';
    } catch (e) { console.error(e); }
  }

  async function resolveInbox(id, action) {
    await flApi(`/fleet/inbox/${id}/resolve`, 'POST', { action });
    loadInbox(); loadOverview();
  }

  // ---- types ----
  async function loadTypes() {
    flTypes = await authenticatedFetch('/field-asset-types');
    document.getElementById('typeRows').innerHTML = flTypes.map(t => `<tr>
      <td>${esc(t.name)} ${t.is_active ? '' : flBadge('inactive', 'secondary')}</td><td>${flLabel(t.category)}</td><td>${flLabel(t.meter_type)}</td>
      <td>${(t.default_checklist || []).length}</td><td>${t.auto_out_of_service_on_fail ? 'Yes' : 'No'}</td><td>${t.assets_count}</td>
      <td class="text-end"><button class="btn btn-sm" onclick="editType(${t.id})">Edit</button></td></tr>`).join('');
  }

  function editType(id) {
    const t = flTypes.find(x => x.id === id) || { name: '', category: 'other', meter_type: 'none', default_checklist: [], is_active: true };
    const lines = (t.default_checklist || []).map(i => (i.critical ? '!' : '') + i.label).join('\n');
    flModal(id ? 'Edit Type' : 'Add Type',
      flField('name', 'Name', t.name, 'text', 'required', 'col-12') +
      flSelect('category', 'Category', flOpts(['vehicle', 'trailer', 'heavy_equipment', 'access_equipment', 'storage', 'power_tools', 'other'], t.category)) +
      flSelect('meter_type', 'Meter', flOpts(['odometer_miles', 'engine_hours', 'none'], t.meter_type)) +
      flText('checklist', 'Inspection checklist — one item per line; start a line with ! for safety-critical items', lines, 8) +
      `<div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="auto_out_of_service_on_fail" ${t.auto_out_of_service_on_fail ? 'checked' : ''}>
        <span class="form-check-label">Automatically mark the asset out of service when a field inspection fails a critical item</span></label>
       <label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" ${t.is_active ? 'checked' : ''}><span class="form-check-label">Active</span></label></div>`,
      async f => {
        const d = flFormData(f);
        d.default_checklist = (d.checklist || '').split('\n').map(s => s.trim()).filter(Boolean)
          .map(s => s.startsWith('!') ? { label: s.slice(1).trim(), critical: true } : { label: s, critical: false });
        delete d.checklist;
        await flApi(id ? `/field-asset-types/${id}` : '/field-asset-types', id ? 'PUT' : 'POST', d);
        loadTypes(); loadOptions();
      }, 'Save', 'modal-lg');
  }

  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  document.addEventListener('DOMContentLoaded', async () => {
    document.querySelectorAll('#fleetTabs .nav-link').forEach(a => a.addEventListener('click', e => { e.preventDefault(); history.replaceState(null, '', '#' + a.dataset.tab); showTab(a.dataset.tab); }));
    document.getElementById('assetSearch').addEventListener('input', debounce(loadAssets, 250));
    ['assetType', 'assetStatus', 'assetOverdue'].forEach(id => document.getElementById(id).addEventListener('change', loadAssets));
    document.getElementById('woFilter').addEventListener('change', loadWorkOrders);
    window.addEventListener('hashchange', () => showTab(location.hash.slice(1)));
    try { await loadOptions(); } catch (e) { console.error(e); }
    showTab(location.hash.slice(1));
  });
</script>
@endpush
