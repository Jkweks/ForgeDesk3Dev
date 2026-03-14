// API_BASE, authToken, authenticatedFetch, showModal, hideModal, and showNotification
// are all provided by auth-scripts.blade.php

// State
let machines = [];
let assets = [];
let tasks = [];
let records = [];

// Initialize App
async function initApp() {
  await loadDashboard();
  await loadMachines();
  await loadAssets();
  await loadTasks();
  await loadRecords();
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

// Machines
async function loadMachines() {
  try {
    machines = await authenticatedFetch('/machines');
    renderMachines();
  } catch (error) {
    console.error('Failed to load machines:', error);
  }
}

function renderMachines() {
  const tbody = document.getElementById('machinesTable');
  tbody.innerHTML = machines.map(m => `
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
    await loadDashboard();
  } catch (error) {
    console.error('Failed to save machine:', error);
    alert('Failed to save machine');
  }
});

// Assets
async function loadAssets() {
  try {
    assets = await authenticatedFetch('/assets');
    renderAssets();
  } catch (error) {
    console.error('Failed to load assets:', error);
  }
}

function renderAssets() {
  const tbody = document.getElementById('assetsTable');
  tbody.innerHTML = assets.map(a => `
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
    const asset = assets.find(a => a.id === id);
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
  } catch (error) {
    console.error('Failed to save asset:', error);
    alert('Failed to save asset');
  }
});

// Tasks
async function loadTasks() {
  try {
    tasks = await authenticatedFetch('/maintenance-tasks');
    renderTasks();
    populateTaskDropdowns();
  } catch (error) {
    console.error('Failed to load tasks:', error);
  }
}

function renderTasks() {
  const tbody = document.getElementById('tasksTable');
  tbody.innerHTML = tasks.map(t => {
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
        <td>${t.assigned_to || '-'}</td>
        <td class="table-actions">
          <button class="btn btn-sm btn-primary" onclick="editTask(${t.id})">Edit</button>
          <button class="btn btn-sm btn-danger" onclick="deleteTask(${t.id})">Delete</button>
        </td>
      </tr>
    `;
  }).join('');
}

function populateTaskDropdowns() {
  const taskSelect = document.getElementById('recordTaskId');
  taskSelect.innerHTML = '<option value="">Unplanned Maintenance</option>' +
    tasks.map(t => `<option value="${t.id}">${t.title} (${t.machine?.name})</option>`).join('');
}

function openTaskModal(id = null) {
  const modal = document.getElementById('taskModal');
  const form = document.getElementById('taskForm');
  form.reset();

  // Populate machine dropdown
  const machineSelect = document.getElementById('taskMachineId');
  machineSelect.innerHTML = '<option value="">Select Machine</option>' +
    machines.map(m => `<option value="${m.id}">${m.name}</option>`).join('');

  if (id) {
    const task = tasks.find(t => t.id === id);
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
    await loadDashboard();
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
    assigned_to: document.getElementById('taskAssignedTo').value || null,
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
    await loadDashboard();
  } catch (error) {
    console.error('Failed to save task:', error);
    alert('Failed to save task');
  }
});

// Service Records
async function loadRecords() {
  try {
    records = await authenticatedFetch('/maintenance-records');
    renderRecords();
  } catch (error) {
    console.error('Failed to load records:', error);
  }
}

function renderRecords() {
  const tbody = document.getElementById('recordsTable');
  tbody.innerHTML = records.map(r => `
    <tr>
      <td>${r.performed_at ? new Date(r.performed_at).toLocaleDateString() : '-'}</td>
      <td>${r.machine ? r.machine.name : 'Unknown'}</td>
      <td>${r.task ? r.task.title : 'Unplanned'}</td>
      <td>${r.performed_by || '-'}</td>
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
    await loadRecords();
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
    performed_by: document.getElementById('recordPerformedBy').value || null,
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
    await loadRecords();
    await loadDashboard();
  } catch (error) {
    console.error('Failed to save record:', error);
    alert('Failed to save record');
  }
});

// Initialize on page load (auth is handled by auth-scripts.blade.php)
document.addEventListener('DOMContentLoaded', () => {
  // Only initialize if user is authenticated (auth-scripts.blade.php shows/hides login)
  if (authToken) {
    initApp();
  }
});
