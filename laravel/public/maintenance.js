// API_BASE, currentUser, apiCall, authenticatedFetch, showModal, hideModal, and showNotification
// are all provided by auth-scripts.blade.php

// Full lists (unpaginated) — used to populate dropdowns/filters and by tooling.js
let machines = [];
let assets = [];
let tasks = [];
let users = [];

// Paginated rows currently shown in each tab's table
let machinesTableRows = [];
let assetsTableRows = [];
let tasksTableRows = [];
let records = [];

// Pagination state
let machinesPage = 1, machinesLastPage = 1, machinesTotal = 0;
let assetsPage = 1, assetsLastPage = 1, assetsTotal = 0;
let tasksPage = 1, tasksLastPage = 1, tasksTotal = 0;
let recordsPage = 1, recordsLastPage = 1, recordsTotal = 0;

let machineSearchTimeout = null;
let assetSearchTimeout = null;

// Initialize App
async function initApp() {
  await loadDashboard();
  await loadAlerts();
  await loadUsers();
  await loadMachines();
  await loadAssets();
  await loadTasks();

  populateMachineTypeFilter();
  populateMachineFilterSelect('taskMachineFilter');
  populateMachineFilterSelect('recordMachineFilter');
  populatePerformedByFilter();

  await loadMachinesTable();
  await loadAssetsTable();
  await loadTasksTable();
  await loadRecords();
  renderCalendar();
}

// Maintenance Alerts (overdue / due soon)
async function loadAlerts() {
  try {
    const upcoming = await authenticatedFetch('/maintenance/upcoming-tasks');
    renderAlerts(upcoming);
  } catch (error) {
    console.error('Failed to load maintenance alerts:', error);
  }
}

function renderAlerts(items) {
  const card = document.getElementById('maintenanceAlertsCard');
  const list = document.getElementById('maintenanceAlertsList');

  if (!items || items.length === 0) {
    card.style.display = 'none';
    return;
  }

  card.style.display = '';
  list.innerHTML = items.map(t => {
    const badgeClass = t.is_overdue ? 'overdue' : 'due-soon';
    const label = t.is_overdue ? 'OVERDUE' : 'DUE SOON';
    const due = t.next_due_date ? new Date(t.next_due_date).toLocaleDateString() : '-';

    return `
      <div class="d-flex align-items-center justify-content-between border-bottom py-2">
        <div>
          <span class="badge ${badgeClass} me-2">${label}</span>
          <strong>${t.title}</strong> — ${t.machine}
          <span class="badge priority-${t.priority} ms-1">${t.priority}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="text-muted">Due ${due}</span>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="jumpToTask(${t.id})">View</button>
        </div>
      </div>
    `;
  }).join('');
}

function jumpToTask(id) {
  const tasksTabLink = document.querySelector('a[href="#tab-tasks"]');
  if (tasksTabLink && window.bootstrap) {
    window.bootstrap.Tab.getOrCreateInstance(tasksTabLink).show();
  }
  editTask(id);
}

// Normalize a response that may be a Laravel paginator object or a plain array
function extractPaginated(response) {
  if (Array.isArray(response)) {
    return { data: response, current_page: 1, last_page: 1, total: response.length };
  }
  return response;
}

function updatePaginationUI(prefix, meta) {
  const info = document.getElementById(`${prefix}PageInfo`);
  const prevBtn = document.getElementById(`${prefix}PrevPage`);
  const nextBtn = document.getElementById(`${prefix}NextPage`);
  if (!info || !prevBtn || !nextBtn) return;

  info.textContent = meta.last_page > 1
    ? `Page ${meta.current_page} of ${meta.last_page} (${meta.total} total)`
    : `${meta.total} total`;

  prevBtn.disabled = meta.current_page <= 1;
  nextBtn.disabled = meta.current_page >= meta.last_page;
}

// Load Dashboard Stats
async function loadDashboard() {
  try {
    const data = await authenticatedFetch('/maintenance/dashboard');
    document.getElementById('dashMachineCount').textContent = data.machine_count;
    document.getElementById('dashActiveTaskCount').textContent = data.active_task_count;
    document.getElementById('dashOverdueCount').textContent = data.overdue_task_count;
    document.getElementById('dashTotalDowntime').textContent = data.total_downtime_hours + 'h';
  } catch (error) {
    console.error('Failed to load dashboard:', error);
  }
}

// Users (for assignee / performed-by dropdowns)
async function loadUsers() {
  try {
    users = await authenticatedFetch('/users?is_active=active');
  } catch (error) {
    console.error('Failed to load users:', error);
  }
}

function userOptionsHtml(selectedId, placeholder) {
  return `<option value="">${placeholder}</option>` +
    users.map(u => `<option value="${u.id}">${u.name}</option>`).join('');
}

// Machines
async function loadMachines() {
  try {
    machines = await authenticatedFetch('/machines?per_page=all');
  } catch (error) {
    console.error('Failed to load machines:', error);
  }
}

function populateMachineTypeFilter() {
  const select = document.getElementById('machineTypeFilter');
  if (!select) return;
  const current = select.value;
  const types = [...new Set(machines.map(m => m.equipment_type).filter(Boolean))].sort();
  select.innerHTML = '<option value="">All Types</option>' +
    types.map(t => `<option value="${t}">${t}</option>`).join('');
  select.value = current;
}

function populateMachineFilterSelect(selectId) {
  const select = document.getElementById(selectId);
  if (!select) return;
  const current = select.value;
  const placeholder = select.options[0] ? select.options[0].textContent : 'All Machines';
  select.innerHTML = `<option value="">${placeholder}</option>` +
    machines.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
  select.value = current;
}

function populatePerformedByFilter() {
  const select = document.getElementById('recordPerformedByFilter');
  if (!select) return;
  const current = select.value;
  select.innerHTML = '<option value="">Anyone</option>' +
    users.map(u => `<option value="${u.id}">${u.name}</option>`).join('');
  select.value = current;
}

function debounceMachineSearch() {
  clearTimeout(machineSearchTimeout);
  machineSearchTimeout = setTimeout(() => loadMachinesTable(1), 500);
}

async function loadMachinesTable(page = 1) {
  try {
    machinesPage = page;
    const params = new URLSearchParams({ per_page: 25, page: machinesPage });

    const search = document.getElementById('machineSearch')?.value;
    const equipmentType = document.getElementById('machineTypeFilter')?.value;
    if (search) params.append('search', search);
    if (equipmentType) params.append('equipment_type', equipmentType);

    const response = extractPaginated(await authenticatedFetch(`/machines?${params.toString()}`));
    machinesTableRows = response.data;
    machinesLastPage = response.last_page;
    machinesTotal = response.total;

    renderMachines();
    updatePaginationUI('machines', response);
  } catch (error) {
    console.error('Failed to load machines:', error);
  }
}

function changeMachinesPage(delta) {
  const next = machinesPage + delta;
  if (next < 1 || next > machinesLastPage) return;
  loadMachinesTable(next);
}

function renderMachines() {
  const tbody = document.getElementById('machinesTable');
  tbody.innerHTML = machinesTableRows.map(m => `
    <tr>
      <td>${m.name}</td>
      <td>${m.equipment_type}</td>
      <td>${m.manufacturer || '-'}</td>
      <td>${m.model || '-'}</td>
      <td>${m.location || '-'}</td>
      <td>${m.task_count || 0}</td>
      <td>${m.last_service_at ? new Date(m.last_service_at).toLocaleDateString() : 'Never'}</td>
      <td class="table-actions">
        <button class="btn btn-sm btn-primary" onclick="editMachine(${m.id})">Edit</button>
        <button class="btn btn-sm btn-danger" onclick="deleteMachine(${m.id})">Delete</button>
      </td>
    </tr>
  `).join('');
}

function openMachineModal(id = null) {
  const modal = document.getElementById('machineModal');
  const form = document.getElementById('machineForm');
  form.reset();

  if (id) {
    const machine = machines.find(m => m.id === id);
    document.getElementById('machineModalTitle').textContent = 'Edit Machine';
    document.getElementById('machineId').value = machine.id;
    document.getElementById('machineName').value = machine.name;
    document.getElementById('machineEquipmentType').value = machine.equipment_type;
    document.getElementById('machineManufacturer').value = machine.manufacturer || '';
    document.getElementById('machineModel').value = machine.model || '';
    document.getElementById('machineSerialNumber').value = machine.serial_number || '';
    document.getElementById('machineLocation').value = machine.location || '';
    document.getElementById('machineNotes').value = machine.notes || '';
  } else {
    document.getElementById('machineModalTitle').textContent = 'Add Machine';
    document.getElementById('machineId').value = '';
  }
}

function editMachine(id) {
  openMachineModal(id);
  showModal(document.getElementById('machineModal'));
}

async function deleteMachine(id) {
  if (!confirm('Are you sure you want to delete this machine?')) return;

  try {
    await authenticatedFetch(`/machines/${id}`, { method: 'DELETE' });
    await loadMachines();
    populateMachineTypeFilter();
    populateMachineFilterSelect('taskMachineFilter');
    populateMachineFilterSelect('recordMachineFilter');
    await loadMachinesTable(machinesPage);
    await loadDashboard();
  } catch (error) {
    console.error('Failed to delete machine:', error);
    alert('Failed to delete machine');
  }
}

document.getElementById('machineForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const id = document.getElementById('machineId').value;
  const data = {
    name: document.getElementById('machineName').value,
    equipment_type: document.getElementById('machineEquipmentType').value,
    manufacturer: document.getElementById('machineManufacturer').value || null,
    model: document.getElementById('machineModel').value || null,
    serial_number: document.getElementById('machineSerialNumber').value || null,
    location: document.getElementById('machineLocation').value || null,
    notes: document.getElementById('machineNotes').value || null,
  };

  try {
    if (id) {
      await authenticatedFetch(`/machines/${id}`, { method: 'PUT', body: JSON.stringify(data) });
    } else {
      await authenticatedFetch('/machines', { method: 'POST', body: JSON.stringify(data) });
    }

    hideModal(document.getElementById('machineModal'));
    await loadMachines();
    populateMachineTypeFilter();
    populateMachineFilterSelect('taskMachineFilter');
    populateMachineFilterSelect('recordMachineFilter');
    await loadMachinesTable(machinesPage);
    await loadDashboard();
  } catch (error) {
    console.error('Failed to save machine:', error);
    alert('Failed to save machine');
  }
});

// Assets
async function loadAssets() {
  try {
    assets = await authenticatedFetch('/assets?per_page=all');
  } catch (error) {
    console.error('Failed to load assets:', error);
  }
}

function debounceAssetSearch() {
  clearTimeout(assetSearchTimeout);
  assetSearchTimeout = setTimeout(() => loadAssetsTable(1), 500);
}

async function loadAssetsTable(page = 1) {
  try {
    assetsPage = page;
    const params = new URLSearchParams({ per_page: 25, page: assetsPage });

    const search = document.getElementById('assetSearch')?.value;
    if (search) params.append('search', search);

    const response = extractPaginated(await authenticatedFetch(`/assets?${params.toString()}`));
    assetsTableRows = response.data;
    assetsLastPage = response.last_page;
    assetsTotal = response.total;

    renderAssets();
    updatePaginationUI('assets', response);
  } catch (error) {
    console.error('Failed to load assets:', error);
  }
}

function changeAssetsPage(delta) {
  const next = assetsPage + delta;
  if (next < 1 || next > assetsLastPage) return;
  loadAssetsTable(next);
}

function renderAssets() {
  const tbody = document.getElementById('assetsTable');
  tbody.innerHTML = assetsTableRows.map(a => `
    <tr>
      <td>${a.name}</td>
      <td>${a.description || '-'}</td>
      <td>${a.machines ? a.machines.map(m => m.name).join(', ') : '-'}</td>
      <td class="table-actions">
        <button class="btn btn-sm btn-primary" onclick="editAsset(${a.id})">Edit</button>
        <button class="btn btn-sm btn-danger" onclick="deleteAsset(${a.id})">Delete</button>
      </td>
    </tr>
  `).join('');
}

function openAssetModal(id = null) {
  const modal = document.getElementById('assetModal');
  const form = document.getElementById('assetForm');
  form.reset();

  // Populate machine checkboxes
  const machinesList = document.getElementById('assetMachinesList');
  machinesList.innerHTML = machines.map(m => `
    <div class="form-check">
      <input class="form-check-input" type="checkbox" value="${m.id}" id="machine_${m.id}">
      <label class="form-check-label" for="machine_${m.id}">${m.name}</label>
    </div>
  `).join('');

  if (id) {
    const asset = assetsTableRows.find(a => a.id === id);
    document.getElementById('assetModalTitle').textContent = 'Edit Asset';
    document.getElementById('assetId').value = asset.id;
    document.getElementById('assetName').value = asset.name;
    document.getElementById('assetDescription').value = asset.description || '';
    document.getElementById('assetNotes').value = asset.notes || '';

    if (asset.machines) {
      asset.machines.forEach(m => {
        const checkbox = document.getElementById(`machine_${m.id}`);
        if (checkbox) checkbox.checked = true;
      });
    }
  } else {
    document.getElementById('assetModalTitle').textContent = 'Add Asset';
    document.getElementById('assetId').value = '';
  }
}

function editAsset(id) {
  openAssetModal(id);
  showModal(document.getElementById('assetModal'));
}

async function deleteAsset(id) {
  if (!confirm('Are you sure you want to delete this asset?')) return;

  try {
    await authenticatedFetch(`/assets/${id}`, { method: 'DELETE' });
    await loadAssets();
    await loadAssetsTable(assetsPage);
  } catch (error) {
    console.error('Failed to delete asset:', error);
    alert('Failed to delete asset');
  }
}

document.getElementById('assetForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const id = document.getElementById('assetId').value;
  const selectedMachines = Array.from(document.querySelectorAll('#assetMachinesList input:checked'))
    .map(cb => parseInt(cb.value));

  const data = {
    name: document.getElementById('assetName').value,
    description: document.getElementById('assetDescription').value || null,
    notes: document.getElementById('assetNotes').value || null,
    machine_ids: selectedMachines,
  };

  try {
    if (id) {
      await authenticatedFetch(`/assets/${id}`, { method: 'PUT', body: JSON.stringify(data) });
    } else {
      await authenticatedFetch('/assets', { method: 'POST', body: JSON.stringify(data) });
    }

    hideModal(document.getElementById('assetModal'));
    await loadAssets();
    await loadAssetsTable(assetsPage);
  } catch (error) {
    console.error('Failed to save asset:', error);
    alert('Failed to save asset');
  }
});

// Tasks
async function loadTasks() {
  try {
    tasks = await authenticatedFetch('/maintenance-tasks?per_page=all');
    populateTaskDropdowns();
  } catch (error) {
    console.error('Failed to load tasks:', error);
  }
}

function populateTaskDropdowns() {
  const taskSelect = document.getElementById('recordTaskId');
  taskSelect.innerHTML = '<option value="">Unplanned Maintenance</option>' +
    tasks.map(t => `<option value="${t.id}">${t.title} (${t.machine?.name})</option>`).join('');
}

async function loadTasksTable(page = 1) {
  try {
    tasksPage = page;
    const params = new URLSearchParams({ per_page: 25, page: tasksPage });

    const machineId = document.getElementById('taskMachineFilter')?.value;
    const status = document.getElementById('taskStatusFilter')?.value;
    const priority = document.getElementById('taskPriorityFilter')?.value;
    const due = document.getElementById('taskDueFilter')?.value;
    if (machineId) params.append('machine_id', machineId);
    if (status) params.append('status', status);
    if (priority) params.append('priority', priority);
    if (due) params.append(due, '1');

    const response = extractPaginated(await authenticatedFetch(`/maintenance-tasks?${params.toString()}`));
    tasksTableRows = response.data;
    tasksLastPage = response.last_page;
    tasksTotal = response.total;

    renderTasks();
    updatePaginationUI('tasks', response);
  } catch (error) {
    console.error('Failed to load tasks:', error);
  }
}

function changeTasksPage(delta) {
  const next = tasksPage + delta;
  if (next < 1 || next > tasksLastPage) return;
  loadTasksTable(next);
}

function renderTasks() {
  const tbody = document.getElementById('tasksTable');
  tbody.innerHTML = tasksTableRows.map(t => {
    const priorityClass = `priority-${t.priority}`;
    const dueClass = t.is_overdue ? 'overdue' : (t.is_due_soon ? 'due-soon' : '');
    const nextDue = t.next_due_date ? new Date(t.next_due_date).toLocaleDateString() : 'Not scheduled';

    return `
      <tr>
        <td>${t.machine ? t.machine.name : 'Unknown'}</td>
        <td>${t.title}</td>
        <td><span class="badge ${priorityClass}">${t.priority}</span></td>
        <td>${t.frequency || '-'}</td>
        <td><span class="badge ${dueClass}">${nextDue}</span></td>
        <td><span class="badge">${t.status}</span></td>
        <td>${t.assigned_user ? t.assigned_user.name : '-'}</td>
        <td class="table-actions">
          <button class="btn btn-sm btn-primary" onclick="editTask(${t.id})">Edit</button>
          <button class="btn btn-sm btn-danger" onclick="deleteTask(${t.id})">Delete</button>
        </td>
      </tr>
    `;
  }).join('');
}

function openTaskModal(id = null) {
  const modal = document.getElementById('taskModal');
  const form = document.getElementById('taskForm');
  form.reset();

  // Populate machine dropdown
  const machineSelect = document.getElementById('taskMachineId');
  machineSelect.innerHTML = '<option value="">Select Machine</option>' +
    machines.map(m => `<option value="${m.id}">${m.name}</option>`).join('');

  // Populate assignee dropdown
  document.getElementById('taskAssignedTo').innerHTML = userOptionsHtml(null, 'Unassigned');

  if (id) {
    const task = tasksTableRows.find(t => t.id === id) || tasks.find(t => t.id === id);
    document.getElementById('taskModalTitle').textContent = 'Edit Task';
    document.getElementById('taskId').value = task.id;
    document.getElementById('taskMachineId').value = task.machine_id;
    document.getElementById('taskTitle').value = task.title;
    document.getElementById('taskDescription').value = task.description || '';
    document.getElementById('taskFrequency').value = task.frequency || '';
    document.getElementById('taskAssignedTo').value = task.assigned_to || '';
    document.getElementById('taskIntervalCount').value = task.interval_count || '';
    document.getElementById('taskIntervalUnit').value = task.interval_unit || '';
    document.getElementById('taskStartDate').value = task.start_date || '';
    document.getElementById('taskPriority').value = task.priority;
    document.getElementById('taskStatus').value = task.status;
  } else {
    document.getElementById('taskModalTitle').textContent = 'Add Maintenance Task';
    document.getElementById('taskId').value = '';
  }
}

function editTask(id) {
  openTaskModal(id);
  showModal(document.getElementById('taskModal'));
}

async function deleteTask(id) {
  if (!confirm('Are you sure you want to delete this task?')) return;

  try {
    await authenticatedFetch(`/maintenance-tasks/${id}`, { method: 'DELETE' });
    await loadTasks();
    await loadTasksTable(tasksPage);
    await loadDashboard();
    await loadAlerts();
    renderCalendar();
  } catch (error) {
    console.error('Failed to delete task:', error);
    alert('Failed to delete task');
  }
}

document.getElementById('taskForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const id = document.getElementById('taskId').value;
  const data = {
    machine_id: parseInt(document.getElementById('taskMachineId').value),
    title: document.getElementById('taskTitle').value,
    description: document.getElementById('taskDescription').value || null,
    frequency: document.getElementById('taskFrequency').value || null,
    assigned_to: document.getElementById('taskAssignedTo').value ? parseInt(document.getElementById('taskAssignedTo').value) : null,
    interval_count: document.getElementById('taskIntervalCount').value ? parseInt(document.getElementById('taskIntervalCount').value) : null,
    interval_unit: document.getElementById('taskIntervalUnit').value || null,
    start_date: document.getElementById('taskStartDate').value || null,
    priority: document.getElementById('taskPriority').value,
    status: document.getElementById('taskStatus').value,
  };

  try {
    if (id) {
      await authenticatedFetch(`/maintenance-tasks/${id}`, { method: 'PUT', body: JSON.stringify(data) });
    } else {
      await authenticatedFetch('/maintenance-tasks', { method: 'POST', body: JSON.stringify(data) });
    }

    hideModal(document.getElementById('taskModal'));
    await loadTasks();
    await loadTasksTable(tasksPage);
    await loadDashboard();
    await loadAlerts();
    renderCalendar();
  } catch (error) {
    console.error('Failed to save task:', error);
    alert('Failed to save task');
  }
});

// Service Records
async function loadRecords(page = 1) {
  try {
    recordsPage = page;
    const params = new URLSearchParams({ per_page: 25, page: recordsPage });

    const machineId = document.getElementById('recordMachineFilter')?.value;
    const performedBy = document.getElementById('recordPerformedByFilter')?.value;
    const dateFrom = document.getElementById('recordDateFrom')?.value;
    const dateTo = document.getElementById('recordDateTo')?.value;
    if (machineId) params.append('machine_id', machineId);
    if (performedBy) params.append('performed_by', performedBy);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    const response = extractPaginated(await authenticatedFetch(`/maintenance-records?${params.toString()}`));
    records = response.data;
    recordsLastPage = response.last_page;
    recordsTotal = response.total;

    renderRecords();
    updatePaginationUI('records', response);
  } catch (error) {
    console.error('Failed to load records:', error);
  }
}

function changeRecordsPage(delta) {
  const next = recordsPage + delta;
  if (next < 1 || next > recordsLastPage) return;
  loadRecords(next);
}

function renderRecords() {
  const tbody = document.getElementById('recordsTable');
  tbody.innerHTML = records.map(r => `
    <tr>
      <td>${r.performed_at ? new Date(r.performed_at).toLocaleDateString() : '-'}</td>
      <td>${r.machine ? r.machine.name : 'Unknown'}</td>
      <td>${r.task ? r.task.title : 'Unplanned'}</td>
      <td>${r.performer ? r.performer.name : '-'}</td>
      <td>${r.downtime_minutes ? r.downtime_minutes + ' min' : '-'}</td>
      <td>${r.labor_hours ? r.labor_hours + ' hrs' : '-'}</td>
      <td>${r.notes ? (r.notes.length > 50 ? r.notes.substring(0, 50) + '...' : r.notes) : '-'}</td>
      <td class="table-actions">
        <button class="btn btn-sm btn-primary" onclick="editRecord(${r.id})">Edit</button>
        <button class="btn btn-sm btn-danger" onclick="deleteRecord(${r.id})">Delete</button>
      </td>
    </tr>
  `).join('');
}

function openRecordModal(id = null) {
  const modal = document.getElementById('recordModal');
  const form = document.getElementById('recordForm');
  form.reset();

  // Populate dropdowns
  const machineSelect = document.getElementById('recordMachineId');
  machineSelect.innerHTML = '<option value="">Select Machine</option>' +
    machines.map(m => `<option value="${m.id}">${m.name}</option>`).join('');

  const assetSelect = document.getElementById('recordAssetId');
  assetSelect.innerHTML = '<option value="">None</option>' +
    assets.map(a => `<option value="${a.id}">${a.name}</option>`).join('');

  document.getElementById('recordPerformedBy').innerHTML = userOptionsHtml(null, 'Unspecified');

  populateTaskDropdowns();

  if (id) {
    const record = records.find(r => r.id === id);
    document.getElementById('recordModalTitle').textContent = 'Edit Service Record';
    document.getElementById('recordId').value = record.id;
    document.getElementById('recordMachineId').value = record.machine_id;
    document.getElementById('recordTaskId').value = record.task_id || '';
    document.getElementById('recordAssetId').value = record.asset_id || '';
    document.getElementById('recordPerformedBy').value = record.performed_by || '';
    document.getElementById('recordPerformedAt').value = record.performed_at || '';
    document.getElementById('recordDowntimeMinutes').value = record.downtime_minutes || '';
    document.getElementById('recordLaborHours').value = record.labor_hours || '';
    document.getElementById('recordPartsUsed').value = record.parts_used ? record.parts_used.join('\n') : '';
    document.getElementById('recordNotes').value = record.notes || '';
  } else {
    document.getElementById('recordModalTitle').textContent = 'Add Service Record';
    document.getElementById('recordId').value = '';
    document.getElementById('recordPerformedAt').value = new Date().toISOString().split('T')[0];
  }
}

function editRecord(id) {
  openRecordModal(id);
  showModal(document.getElementById('recordModal'));
}

async function deleteRecord(id) {
  if (!confirm('Are you sure you want to delete this record?')) return;

  try {
    await authenticatedFetch(`/maintenance-records/${id}`, { method: 'DELETE' });
    await loadRecords(recordsPage);
    await loadDashboard();
  } catch (error) {
    console.error('Failed to delete record:', error);
    alert('Failed to delete record');
  }
}

document.getElementById('recordForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const id = document.getElementById('recordId').value;
  const partsText = document.getElementById('recordPartsUsed').value;
  const parts = partsText ? partsText.split('\n').filter(p => p.trim()) : [];

  const data = {
    machine_id: parseInt(document.getElementById('recordMachineId').value),
    task_id: document.getElementById('recordTaskId').value ? parseInt(document.getElementById('recordTaskId').value) : null,
    asset_id: document.getElementById('recordAssetId').value ? parseInt(document.getElementById('recordAssetId').value) : null,
    performed_by: document.getElementById('recordPerformedBy').value ? parseInt(document.getElementById('recordPerformedBy').value) : null,
    performed_at: document.getElementById('recordPerformedAt').value || null,
    downtime_minutes: document.getElementById('recordDowntimeMinutes').value ? parseInt(document.getElementById('recordDowntimeMinutes').value) : null,
    labor_hours: document.getElementById('recordLaborHours').value ? parseFloat(document.getElementById('recordLaborHours').value) : null,
    parts_used: parts.length > 0 ? parts : null,
    notes: document.getElementById('recordNotes').value || null,
  };

  try {
    if (id) {
      await authenticatedFetch(`/maintenance-records/${id}`, { method: 'PUT', body: JSON.stringify(data) });
    } else {
      await authenticatedFetch('/maintenance-records', { method: 'POST', body: JSON.stringify(data) });
    }

    hideModal(document.getElementById('recordModal'));
    await loadRecords(recordsPage);
    await loadDashboard();
  } catch (error) {
    console.error('Failed to save record:', error);
    alert('Failed to save record');
  }
});

// Calendar
let calendarYear;
let calendarMonth; // 0-indexed

function changeCalendarMonth(delta) {
  if (calendarYear === undefined) initCalendarState();
  calendarMonth += delta;
  if (calendarMonth < 0) {
    calendarMonth = 11;
    calendarYear--;
  } else if (calendarMonth > 11) {
    calendarMonth = 0;
    calendarYear++;
  }
  renderCalendar();
}

function initCalendarState() {
  const now = new Date();
  calendarYear = now.getFullYear();
  calendarMonth = now.getMonth();
}

function dateKey(y, m, d) {
  return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

function renderCalendar() {
  const grid = document.getElementById('calendarGrid');
  const label = document.getElementById('calendarMonthLabel');
  if (!grid || !label) return;

  if (calendarYear === undefined) initCalendarState();

  const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'];
  label.textContent = `${monthNames[calendarMonth]} ${calendarYear}`;

  const byDate = {};
  tasks.forEach(t => {
    if (!t.next_due_date || t.status !== 'active') return;
    const d = new Date(t.next_due_date);
    const key = dateKey(d.getFullYear(), d.getMonth(), d.getDate());
    if (!byDate[key]) byDate[key] = [];
    byDate[key].push(t);
  });

  const startDay = new Date(calendarYear, calendarMonth, 1).getDay();
  const daysInMonth = new Date(calendarYear, calendarMonth + 1, 0).getDate();
  const daysInPrevMonth = new Date(calendarYear, calendarMonth, 0).getDate();
  const now = new Date();
  const todayKey = dateKey(now.getFullYear(), now.getMonth(), now.getDate());

  const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
    .map(d => `<div class="calendar-day-header">${d}</div>`).join('');

  const cells = [];
  for (let i = startDay - 1; i >= 0; i--) {
    cells.push({ day: daysInPrevMonth - i, otherMonth: true, key: null });
  }
  for (let day = 1; day <= daysInMonth; day++) {
    cells.push({ day, otherMonth: false, key: dateKey(calendarYear, calendarMonth, day) });
  }
  let nextDay = 1;
  while (cells.length % 7 !== 0) {
    cells.push({ day: nextDay++, otherMonth: true, key: null });
  }

  const cellsHtml = cells.map(c => {
    const classes = ['calendar-day'];
    if (c.otherMonth) classes.push('other-month');
    if (c.key === todayKey) classes.push('today');

    const dayTasks = c.key ? (byDate[c.key] || []) : [];
    const tasksHtml = dayTasks.map(t => {
      const cls = t.is_overdue ? 'overdue' : (t.is_due_soon ? 'due-soon' : `priority-${t.priority}`);
      const machineName = t.machine ? t.machine.name : '';
      return `<span class="badge calendar-task ${cls}" title="${escapeHtml(t.title)} — ${escapeHtml(machineName)}" onclick="editTask(${t.id}); showModal(document.getElementById('taskModal'));">${escapeHtml(t.title)}</span>`;
    }).join('');

    return `<div class="${classes.join(' ')}"><div class="calendar-day-number">${c.day}</div>${tasksHtml}</div>`;
  }).join('');

  grid.innerHTML = dayHeaders + cellsHtml;
}

// Export service history PDF using the current Service Log filters
async function exportServiceHistoryPdf() {
  try {
    const params = new URLSearchParams();
    const machineId = document.getElementById('recordMachineFilter')?.value;
    const dateFrom = document.getElementById('recordDateFrom')?.value;
    const dateTo = document.getElementById('recordDateTo')?.value;
    if (machineId) params.append('machine_id', machineId);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    showNotification('Generating PDF report...', 'info');

    const response = await apiCall(`/maintenance/service-history/pdf?${params.toString()}`, {
      headers: { Accept: 'application/pdf' },
    });

    if (!response.ok) {
      throw new Error('Failed to generate PDF');
    }

    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = downloadUrl;
    a.download = `maintenance-service-history-${new Date().toISOString().split('T')[0]}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(downloadUrl);
  } catch (error) {
    console.error('Failed to export service history PDF:', error);
    alert('Failed to export service history PDF');
  }
}

// Initialize on page load (auth is handled by auth-scripts.blade.php)
document.addEventListener('DOMContentLoaded', () => {
  // Only initialize if user is authenticated (auth-scripts.blade.php shows/hides login)
  if (currentUser) {
    initApp();
  }
});
