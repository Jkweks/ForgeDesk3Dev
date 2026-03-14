@extends('layouts.app')

@section('content')
<div class="container-xl">
  <!-- Page header -->
  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">
          <i class="ti ti-heartbeat me-2"></i>System Status
        </h2>
        <div class="text-muted mt-1">Service health, database statistics, and system information</div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
          <span class="badge bg-green text-green-fg d-none" id="statusOverallBadge">All Systems Operational</span>
          <button class="btn btn-icon" onclick="loadStatus()" title="Refresh">
            <i class="ti ti-refresh"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <!-- Loading state -->
    <div id="statusLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <div class="text-muted mt-2">Loading system status...</div>
    </div>

    <!-- Status content -->
    <div id="statusContent" style="display:none;">

      <!-- Application & Server Info -->
      <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
          <div class="card">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="subheader">Server Uptime</div>
              </div>
              <div class="h1 mb-0" id="statUptime">-</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="subheader">PHP Version</div>
              </div>
              <div class="h1 mb-0" id="statPhp">-</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="subheader">Laravel Version</div>
              </div>
              <div class="h1 mb-0" id="statLaravel">-</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="subheader">Environment</div>
              </div>
              <div class="h1 mb-0" id="statEnv">-</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Services Status -->
      <div class="row row-deck row-cards mb-3">
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="ti ti-server me-2"></i>Services</h3>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>Service</th>
                    <th>Driver</th>
                    <th>Status</th>
                    <th>Details</th>
                  </tr>
                </thead>
                <tbody id="servicesTable">
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="ti ti-disc me-2"></i>Disk Usage</h3>
            </div>
            <div class="card-body">
              <div id="diskInfo">
                <div class="mb-2">
                  <div class="d-flex justify-content-between mb-1">
                    <span>Storage</span>
                    <span id="diskPercent">-</span>
                  </div>
                  <div class="progress">
                    <div class="progress-bar" id="diskBar" role="progressbar" style="width: 0%"></div>
                  </div>
                </div>
                <div class="row mt-3">
                  <div class="col-6">
                    <div class="text-muted">Free</div>
                    <div class="h3" id="diskFree">-</div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted">Total</div>
                    <div class="h3" id="diskTotal">-</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Database Info -->
      <div class="row row-deck row-cards mb-3">
        <div class="col-lg-4">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="ti ti-database me-2"></i>Database</h3>
            </div>
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-5">Connection</dt>
                <dd class="col-7" id="dbConnection">-</dd>
                <dt class="col-5">Status</dt>
                <dd class="col-7" id="dbStatus">-</dd>
                <dt class="col-5">Size</dt>
                <dd class="col-7" id="dbSize">-</dd>
                <dt class="col-5">Tables</dt>
                <dd class="col-7" id="dbTableCount">-</dd>
                <dt class="col-5">Total Rows</dt>
                <dd class="col-7" id="dbTotalRows">-</dd>
              </dl>
            </div>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="ti ti-table me-2"></i>Table Sizes</h3>
            </div>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
              <table class="table table-vcenter card-table table-striped">
                <thead>
                  <tr>
                    <th>Table</th>
                    <th class="text-end">Rows</th>
                    <th class="text-end">Size</th>
                  </tr>
                </thead>
                <tbody id="dbTablesTable">
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Inventory & Operations Stats -->
      <div class="row row-deck row-cards mb-3">
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="ti ti-package me-2"></i>Inventory Overview</h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-6">
                  <div class="text-muted">Total Products</div>
                  <div class="h3 mb-0" id="invTotal">-</div>
                </div>
                <div class="col-6">
                  <div class="text-muted">Active Products</div>
                  <div class="h3 mb-0" id="invActive">-</div>
                </div>
                <div class="col-6">
                  <div class="text-muted text-success">In Stock</div>
                  <div class="h3 mb-0 text-success" id="invInStock">-</div>
                </div>
                <div class="col-6">
                  <div class="text-muted text-warning">Low Stock</div>
                  <div class="h3 mb-0 text-warning" id="invLowStock">-</div>
                </div>
                <div class="col-6">
                  <div class="text-muted text-danger">Critical</div>
                  <div class="h3 mb-0 text-danger" id="invCritical">-</div>
                </div>
                <div class="col-6">
                  <div class="text-muted text-secondary">Out of Stock</div>
                  <div class="h3 mb-0 text-secondary" id="invOutOfStock">-</div>
                </div>
              </div>
              <hr>
              <div class="row g-3">
                <div class="col-4">
                  <div class="text-muted">Categories</div>
                  <div class="h3 mb-0" id="invCategories">-</div>
                </div>
                <div class="col-4">
                  <div class="text-muted">Suppliers</div>
                  <div class="h3 mb-0" id="invSuppliers">-</div>
                </div>
                <div class="col-4">
                  <div class="text-muted">Locations</div>
                  <div class="h3 mb-0" id="invLocations">-</div>
                </div>
              </div>
              <hr>
              <div class="row g-3">
                <div class="col-6">
                  <div class="text-muted">Transactions Today</div>
                  <div class="h3 mb-0" id="invTxToday">-</div>
                </div>
                <div class="col-6">
                  <div class="text-muted">Transactions This Week</div>
                  <div class="h3 mb-0" id="invTxWeek">-</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="ti ti-settings me-2"></i>Operations</h3>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <h4 class="subheader">Purchase Orders</h4>
                <div class="row g-3">
                  <div class="col-6">
                    <div class="text-muted">Total</div>
                    <div class="h3 mb-0" id="opsPoTotal">-</div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted">Open</div>
                    <div class="h3 mb-0" id="opsPoOpen">-</div>
                  </div>
                </div>
              </div>
              <hr>
              <div class="mb-3">
                <h4 class="subheader">Job Reservations</h4>
                <div class="row g-3">
                  <div class="col-6">
                    <div class="text-muted">Total</div>
                    <div class="h3 mb-0" id="opsJrTotal">-</div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted">Active</div>
                    <div class="h3 mb-0" id="opsJrActive">-</div>
                  </div>
                </div>
              </div>
              <hr>
              <div class="mb-3">
                <h4 class="subheader">Cycle Counts</h4>
                <div class="row g-3">
                  <div class="col-6">
                    <div class="text-muted">Total</div>
                    <div class="h3 mb-0" id="opsCcTotal">-</div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted">Active</div>
                    <div class="h3 mb-0" id="opsCcActive">-</div>
                  </div>
                </div>
              </div>
              <hr>
              <div>
                <h4 class="subheader">Maintenance</h4>
                <div class="row g-3">
                  <div class="col-4">
                    <div class="text-muted">Machines</div>
                    <div class="h3 mb-0" id="opsMtMachines">-</div>
                  </div>
                  <div class="col-4">
                    <div class="text-muted">Assets</div>
                    <div class="h3 mb-0" id="opsMtAssets">-</div>
                  </div>
                  <div class="col-4">
                    <div class="text-muted">Active Tasks</div>
                    <div class="h3 mb-0" id="opsMtTasks">-</div>
                  </div>
                </div>
                <div class="row g-3 mt-1">
                  <div class="col-6">
                    <div class="text-muted">Service Records</div>
                    <div class="h3 mb-0" id="opsMtRecords">-</div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted">Last Service</div>
                    <div class="h4 mb-0" id="opsMtLastService">-</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Users -->
      <div class="row row-deck row-cards mb-3">
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="ti ti-users me-2"></i>Users</h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-3">
                  <div class="text-muted">Total</div>
                  <div class="h3 mb-0" id="usrTotal">-</div>
                </div>
                <div class="col-3">
                  <div class="text-muted text-success">Active</div>
                  <div class="h3 mb-0 text-success" id="usrActive">-</div>
                </div>
                <div class="col-3">
                  <div class="text-muted text-secondary">Inactive</div>
                  <div class="h3 mb-0 text-secondary" id="usrInactive">-</div>
                </div>
                <div class="col-3">
                  <div class="text-muted text-info">Recent (7d)</div>
                  <div class="h3 mb-0 text-info" id="usrRecent">-</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="ti ti-info-circle me-2"></i>Application</h3>
            </div>
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-5">App Name</dt>
                <dd class="col-7" id="appName">-</dd>
                <dt class="col-5">Timezone</dt>
                <dd class="col-7" id="appTimezone">-</dd>
                <dt class="col-5">Server Time</dt>
                <dd class="col-7" id="appServerTime">-</dd>
                <dt class="col-5">Debug Mode</dt>
                <dd class="col-7" id="appDebug">-</dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  function statusBadge(status) {
    const map = {
      'operational': '<span class="badge bg-success">Operational</span>',
      'connected': '<span class="badge bg-success">Connected</span>',
      'degraded': '<span class="badge bg-warning">Degraded</span>',
      'error': '<span class="badge bg-danger">Error</span>',
      'unknown': '<span class="badge bg-secondary">Unknown</span>',
    };
    return map[status] || '<span class="badge bg-secondary">' + status + '</span>';
  }

  function num(v) {
    if (v === null || v === undefined) return '-';
    return Number(v).toLocaleString();
  }

  async function loadStatus() {
    document.getElementById('statusLoading').style.display = '';
    document.getElementById('statusContent').style.display = 'none';

    try {
      const data = await authenticatedFetch('/status');

      // Application info
      const app = data.application;
      document.getElementById('statUptime').textContent = app.uptime || 'N/A';
      document.getElementById('statPhp').textContent = app.php_version;
      document.getElementById('statLaravel').textContent = app.laravel_version;
      document.getElementById('statEnv').textContent = app.environment;
      document.getElementById('appName').textContent = app.name;
      document.getElementById('appTimezone').textContent = app.timezone;
      document.getElementById('appServerTime').textContent = new Date(app.server_time).toLocaleString();
      document.getElementById('appDebug').innerHTML = app.debug
        ? '<span class="badge bg-warning">Enabled</span>'
        : '<span class="badge bg-success">Disabled</span>';

      // Services
      const svc = data.services;
      let svcHtml = '';

      svcHtml += '<tr><td>Database</td><td>' + data.database.connection + '</td><td>'
        + statusBadge(data.database.status) + '</td><td>' + (data.database.size_human || '-') + '</td></tr>';

      svcHtml += '<tr><td>Cache</td><td>' + svc.cache.driver + '</td><td>'
        + statusBadge(svc.cache.status) + '</td><td>' + (svc.cache.error || '') + '</td></tr>';

      svcHtml += '<tr><td>Session</td><td>' + svc.session.driver + '</td><td>'
        + statusBadge(svc.session.status) + '</td><td></td></tr>';

      let queueDetails = '';
      if (svc.queue.pending_jobs !== undefined) {
        queueDetails = num(svc.queue.pending_jobs) + ' pending';
        if (svc.queue.failed_jobs > 0) queueDetails += ', ' + num(svc.queue.failed_jobs) + ' failed';
      }
      svcHtml += '<tr><td>Queue</td><td>' + svc.queue.driver + '</td><td>'
        + statusBadge(svc.queue.status) + '</td><td>' + queueDetails + '</td></tr>';

      svcHtml += '<tr><td>Storage</td><td>Local</td><td>'
        + statusBadge(svc.storage.status) + '</td><td>' + svc.storage.disk_used_percent + '% used</td></tr>';

      document.getElementById('servicesTable').innerHTML = svcHtml;

      // Check overall status
      const allOperational = data.database.status === 'connected'
        && svc.cache.status === 'operational'
        && svc.storage.status === 'operational';
      const badge = document.getElementById('statusOverallBadge');
      if (allOperational) {
        badge.className = 'badge bg-green text-green-fg';
        badge.textContent = 'All Systems Operational';
      } else {
        badge.className = 'badge bg-yellow text-yellow-fg';
        badge.textContent = 'Some Issues Detected';
      }
      badge.classList.remove('d-none');

      // Disk
      document.getElementById('diskPercent').textContent = svc.storage.disk_used_percent + '%';
      const bar = document.getElementById('diskBar');
      bar.style.width = svc.storage.disk_used_percent + '%';
      bar.className = 'progress-bar' + (svc.storage.disk_used_percent > 90 ? ' bg-danger' : svc.storage.disk_used_percent > 75 ? ' bg-warning' : ' bg-primary');
      document.getElementById('diskFree').textContent = svc.storage.disk_free_human;
      document.getElementById('diskTotal').textContent = svc.storage.disk_total_human;

      // Database
      const db = data.database;
      document.getElementById('dbConnection').textContent = db.connection;
      document.getElementById('dbStatus').innerHTML = statusBadge(db.status);
      document.getElementById('dbSize').textContent = db.size_human || '-';
      document.getElementById('dbTableCount').textContent = num(db.tables.length);
      document.getElementById('dbTotalRows').textContent = num(db.tables.reduce((sum, t) => sum + t.rows, 0));

      let tblHtml = '';
      db.tables.forEach(t => {
        tblHtml += '<tr><td><code>' + t.name + '</code></td><td class="text-end">'
          + num(t.rows) + '</td><td class="text-end">' + (t.size_human || '-') + '</td></tr>';
      });
      document.getElementById('dbTablesTable').innerHTML = tblHtml;

      // Inventory
      const inv = data.inventory;
      document.getElementById('invTotal').textContent = num(inv.total_products);
      document.getElementById('invActive').textContent = num(inv.active_products);
      document.getElementById('invInStock').textContent = num(inv.in_stock);
      document.getElementById('invLowStock').textContent = num(inv.low_stock);
      document.getElementById('invCritical').textContent = num(inv.critical);
      document.getElementById('invOutOfStock').textContent = num(inv.out_of_stock);
      document.getElementById('invCategories').textContent = num(inv.categories);
      document.getElementById('invSuppliers').textContent = num(inv.suppliers);
      document.getElementById('invLocations').textContent = num(inv.storage_locations);
      document.getElementById('invTxToday').textContent = num(inv.transactions_today);
      document.getElementById('invTxWeek').textContent = num(inv.transactions_this_week);

      // Operations
      const ops = data.operations;
      document.getElementById('opsPoTotal').textContent = num(ops.purchase_orders.total);
      document.getElementById('opsPoOpen').textContent = num(ops.purchase_orders.open);
      document.getElementById('opsJrTotal').textContent = num(ops.job_reservations.total);
      document.getElementById('opsJrActive').textContent = num(ops.job_reservations.active);
      document.getElementById('opsCcTotal').textContent = num(ops.cycle_counts.total);
      document.getElementById('opsCcActive').textContent = num(ops.cycle_counts.active);
      document.getElementById('opsMtMachines').textContent = num(ops.maintenance.machines);
      document.getElementById('opsMtAssets').textContent = num(ops.maintenance.assets);
      document.getElementById('opsMtTasks').textContent = num(ops.maintenance.active_tasks);
      document.getElementById('opsMtRecords').textContent = num(ops.maintenance.total_records);
      document.getElementById('opsMtLastService').textContent = ops.maintenance.last_service
        ? new Date(ops.maintenance.last_service).toLocaleDateString()
        : 'None';

      // Users
      const usr = data.users;
      document.getElementById('usrTotal').textContent = num(usr.total);
      document.getElementById('usrActive').textContent = num(usr.active);
      document.getElementById('usrInactive').textContent = num(usr.inactive);
      document.getElementById('usrRecent').textContent = num(usr.logged_in_recently);

      document.getElementById('statusLoading').style.display = 'none';
      document.getElementById('statusContent').style.display = '';

    } catch (error) {
      console.error('Failed to load status:', error);
      document.getElementById('statusLoading').innerHTML =
        '<div class="alert alert-danger">Failed to load system status: ' + error.message + '</div>';
    }
  }

  // Load on page init
  if (authToken) {
    loadStatus();
  }
</script>
@endpush
