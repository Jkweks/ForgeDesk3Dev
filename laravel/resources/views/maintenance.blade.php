@extends('layouts.app')

@section('title', 'Maintenance Hub - ForgeDesk')

@section('styles')
.priority-critical { background-color: #d63939; color: white; }
.priority-high { background-color: #f76707; color: white; }
.priority-medium { background-color: #fab005; color: white; }
.priority-low { background-color: #74b816; color: white; }
.overdue { background-color: #d63939; color: white; }
.due-soon { background-color: #fab005; color: white; }
@endsection

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <h2 class="page-title">Maintenance Hub</h2>
            </div>
          </div>
        </div>
      </div>

      <!-- Dashboard Stats -->
      <div class="page-body">
        <div class="container-xl">
          <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Machines</div>
                  </div>
                  <div class="h1 mb-0" id="dashMachineCount">0</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Active Tasks</div>
                  </div>
                  <div class="h1 mb-0" id="dashActiveTaskCount">0</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Overdue Tasks</div>
                  </div>
                  <div class="h1 mb-0 text-danger" id="dashOverdueCount">0</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Total Downtime</div>
                  </div>
                  <div class="h1 mb-0" id="dashTotalDowntime">0h</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <div class="card">
            <div class="card-header">
              <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                  <a href="#tab-machines" class="nav-link active" data-bs-toggle="tab" role="tab">Machines</a>
                </li>
                <li class="nav-item" role="presentation">
                  <a href="#tab-assets" class="nav-link" data-bs-toggle="tab" role="tab">Assets</a>
                </li>
                <li class="nav-item" role="presentation">
                  <a href="#tab-tooling" class="nav-link" data-bs-toggle="tab" role="tab">Machine Tooling</a>
                </li>
                <li class="nav-item" role="presentation">
                  <a href="#tab-tasks" class="nav-link" data-bs-toggle="tab" role="tab">Tasks</a>
                </li>
                <li class="nav-item" role="presentation">
                  <a href="#tab-records" class="nav-link" data-bs-toggle="tab" role="tab">Service Log</a>
                </li>
              </ul>
            </div>
            <div class="card-body">
              <div class="tab-content">
                <!-- Machines Tab -->
                <div class="tab-pane active" id="tab-machines" role="tabpanel">
                  <div class="d-flex mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#machineModal" onclick="openMachineModal()">
                      <i class="ti ti-plus icon"></i> Add Machine
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter">
                      <thead>
                        <tr>
                          <th>Name</th>
                          <th>Type</th>
                          <th>Manufacturer</th>
                          <th>Model</th>
                          <th>Location</th>
                          <th>Tasks</th>
                          <th>Last Service</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody id="machinesTable"></tbody>
                    </table>
                  </div>
                </div>

                <!-- Assets Tab -->
                <div class="tab-pane" id="tab-assets" role="tabpanel">
                  <div class="d-flex mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assetModal" onclick="openAssetModal()">
                      <i class="ti ti-plus icon"></i> Add Asset
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter">
                      <thead>
                        <tr>
                          <th>Name</th>
                          <th>Description</th>
                          <th>Machines</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody id="assetsTable"></tbody>
                    </table>
                  </div>
                </div>

                <!-- Machine Tooling Tab -->
                <div class="tab-pane" id="tab-tooling" role="tabpanel">
                  <div class="d-flex mb-3 gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addToolingProductModal" onclick="openAddToolingProductModal()">
                      <i class="ti ti-plus icon"></i> Add Maintenance Product
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#installToolModal" onclick="openInstallToolModal()">
                      <i class="ti ti-tool icon"></i> Install Tool on Machine
                    </button>
                  </div>

                  <div class="row mb-3">
                    <div class="col-md-4">
                      <label class="form-label">Installation Status</label>
                      <select class="form-select" id="toolingInstallationFilter" onchange="loadMachineTooling()">
                        <option value="">All Tools</option>
                        <option value="available">Available (In Inventory)</option>
                        <option value="installed">Installed (On Machines)</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Tool Type</label>
                      <select class="form-select" id="toolingTypeFilter" onchange="loadMachineTooling()">
                        <option value="">All Types</option>
                        <option value="consumable_tool">Machine Tooling</option>
                        <option value="asset_tool">Machine Assets</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Search</label>
                      <input type="text" class="form-control" id="toolingSearch" placeholder="SKU or description" onkeyup="debounceSearch()">
                    </div>
                  </div>

                  <!-- Tooling Statistics Cards -->
                  <div class="row row-deck row-cards mb-3">
                    <div class="col-sm-6 col-lg-3">
                      <div class="card">
                        <div class="card-body">
                          <div class="subheader">Total Tools</div>
                          <div class="h2 mb-0" id="toolingTotalCount">0</div>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                      <div class="card">
                        <div class="card-body">
                          <div class="subheader">Available</div>
                          <div class="h2 mb-0 text-success" id="toolingAvailableCount">0</div>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                      <div class="card">
                        <div class="card-body">
                          <div class="subheader">Installed</div>
                          <div class="h2 mb-0 text-primary" id="toolingInstalledCount">0</div>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                      <div class="card">
                        <div class="card-body">
                          <div class="subheader">Needs Attention</div>
                          <div class="h2 mb-0 text-warning" id="toolingAttentionCount">0</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="table-responsive">
                    <table class="table table-vcenter">
                      <thead>
                        <tr>
                          <th>Tool (SKU)</th>
                          <th>Tool Type</th>
                          <th>Qty in Stock</th>
                          <th>Installation Status</th>
                          <th>Installed On</th>
                          <th>Tool Life</th>
                          <th>Manufacturer</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody id="toolingTable"></tbody>
                    </table>
                  </div>
                </div>

                <!-- Tasks Tab -->
                <div class="tab-pane" id="tab-tasks" role="tabpanel">
                  <div class="d-flex mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal" onclick="openTaskModal()">
                      <i class="ti ti-plus icon"></i> Add Task
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter">
                      <thead>
                        <tr>
                          <th>Machine</th>
                          <th>Title</th>
                          <th>Priority</th>
                          <th>Frequency</th>
                          <th>Next Due</th>
                          <th>Status</th>
                          <th>Assigned To</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody id="tasksTable"></tbody>
                    </table>
                  </div>
                </div>

                <!-- Service Log Tab -->
                <div class="tab-pane" id="tab-records" role="tabpanel">
                  <div class="d-flex mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordModal" onclick="openRecordModal()">
                      <i class="ti ti-plus icon"></i> Add Service Record
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>Machine</th>
                          <th>Task</th>
                          <th>Performed By</th>
                          <th>Downtime</th>
                          <th>Labor Hours</th>
                          <th>Notes</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody id="recordsTable"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Machine Modal -->
  <div class="modal fade" id="machineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="machineModalTitle">Add Machine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="machineForm">
          <div class="modal-body">
            <input type="hidden" id="machineId">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">Name</label>
                <input type="text" class="form-control" id="machineName" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label required">Equipment Type</label>
                <input type="text" class="form-control" id="machineEquipmentType" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Manufacturer</label>
                <input type="text" class="form-control" id="machineManufacturer">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Model</label>
                <input type="text" class="form-control" id="machineModel">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Serial Number</label>
                <input type="text" class="form-control" id="machineSerialNumber">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" id="machineLocation">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="machineNotes" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Asset Modal -->
  <div class="modal fade" id="assetModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="assetModalTitle">Add Asset</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="assetForm">
          <div class="modal-body">
            <input type="hidden" id="assetId">
            <div class="mb-3">
              <label class="form-label required">Name</label>
              <input type="text" class="form-control" id="assetName" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="assetDescription" rows="2"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Compatible Machines</label>
              <div id="assetMachinesList"></div>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="assetNotes" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Task Modal -->
  <div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="taskModalTitle">Add Maintenance Task</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="taskForm">
          <div class="modal-body">
            <input type="hidden" id="taskId">
            <div class="mb-3">
              <label class="form-label required">Machine</label>
              <select class="form-select" id="taskMachineId" required></select>
            </div>
            <div class="mb-3">
              <label class="form-label required">Title</label>
              <input type="text" class="form-control" id="taskTitle" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="taskDescription" rows="2"></textarea>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Frequency</label>
                <input type="text" class="form-control" id="taskFrequency" placeholder="e.g., Weekly, Monthly">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Assigned To</label>
                <input type="text" class="form-control" id="taskAssignedTo">
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Interval Count</label>
                <input type="number" class="form-control" id="taskIntervalCount" min="1">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Interval Unit</label>
                <select class="form-select" id="taskIntervalUnit">
                  <option value="">Select...</option>
                  <option value="day">Day(s)</option>
                  <option value="week">Week(s)</option>
                  <option value="month">Month(s)</option>
                  <option value="year">Year(s)</option>
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" id="taskStartDate">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Priority</label>
                <select class="form-select" id="taskPriority">
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium</option>
                  <option value="high">High</option>
                  <option value="critical">Critical</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Status</label>
                <select class="form-select" id="taskStatus">
                  <option value="active" selected>Active</option>
                  <option value="paused">Paused</option>
                  <option value="retired">Retired</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Service Record Modal -->
  <div class="modal fade" id="recordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="recordModalTitle">Add Service Record</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="recordForm">
          <div class="modal-body">
            <input type="hidden" id="recordId">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">Machine</label>
                <select class="form-select" id="recordMachineId" required></select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Related Task</label>
                <select class="form-select" id="recordTaskId">
                  <option value="">Unplanned Maintenance</option>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Asset Used</label>
                <select class="form-select" id="recordAssetId">
                  <option value="">None</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Performed By</label>
                <input type="text" class="form-control" id="recordPerformedBy">
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Date Performed</label>
                <input type="date" class="form-control" id="recordPerformedAt">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Downtime (minutes)</label>
                <input type="number" class="form-control" id="recordDowntimeMinutes" min="0">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Labor Hours</label>
                <input type="number" class="form-control" id="recordLaborHours" step="0.25" min="0">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Parts Used (one per line)</label>
              <textarea class="form-control" id="recordPartsUsed" rows="3" placeholder="Part A&#10;Part B&#10;Part C"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="recordNotes" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Add Tooling Product Modal -->
  <div class="modal fade" id="addToolingProductModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Maintenance Product</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="addToolingProductForm">
          <div class="modal-body">
            <!-- Tool Type Selection -->
            <div class="row mb-3">
              <div class="col-md-12">
                <label class="form-label required">Product Type</label>
                <select class="form-select" id="newToolType" required onchange="toggleToolLifeFields()">
                  <option value="">Select Product Type</option>
                  <option value="consumable_tool">Machine Tooling (Consumable cutting tools - tracks tool life)</option>
                  <option value="asset_tool" selected>Machine Assets (Reusable machine items - no life tracking)</option>
                </select>
                <div class="mt-2">
                  <small class="form-hint d-block"><strong>Machine Tooling:</strong> End mills, drill bits, inserts, taps</small>
                  <small class="form-hint d-block"><strong>Machine Assets:</strong> Collets, tool holders, fixtures, vises</small>
                  <small class="form-hint d-block"><strong>Maintenance Assets:</strong> Bearings, seals, gaskets, belts, filters</small>
                </div>
              </div>
            </div>

            <!-- Basic Information -->
            <h5 class="mb-3">Basic Information</h5>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label required">Part Number</label>
                <input type="text" class="form-control" id="newToolPartNumber" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">SKU</label>
                <input type="text" class="form-control" id="newToolSKU" placeholder="Auto-generated if empty">
                <small class="form-hint">Leave empty to auto-generate from part number</small>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Category</label>
                <select class="form-select" id="newToolCategory">
                  <option value="">Select Category</option>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12 mb-3">
                <label class="form-label required">Description</label>
                <input type="text" class="form-control" id="newToolDescription" required placeholder="e.g., 1/2 inch Carbide End Mill - 4 Flute">
              </div>
            </div>

            <!-- Inventory Information -->
            <h5 class="mb-3">Inventory Information</h5>
            <div class="row">
              <div class="col-md-3 mb-3">
                <label class="form-label">Quantity on Hand</label>
                <input type="number" class="form-control" id="newToolQuantity" value="0" min="0">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Unit Cost</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" class="form-control" id="newToolUnitCost" step="0.01" min="0" value="0">
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Net Cost</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" class="form-control" id="newToolNetCost" step="0.01" min="0" value="0">
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Minimum Quantity</label>
                <input type="number" class="form-control" id="newToolMinQuantity" value="0" min="0">
              </div>
            </div>

            <div class="row">
              <div class="col-md-3 mb-3">
                <label class="form-label">Reorder Point</label>
                <input type="number" class="form-control" id="newToolReorderPoint" value="0" min="0">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" id="newToolLocation" placeholder="Storage location">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Supplier</label>
                <select class="form-select" id="newToolSupplier">
                  <option value="">No Supplier</option>
                </select>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Supplier Part Number</label>
                <input type="text" class="form-control" id="newToolSupplierSKU" placeholder="Supplier's part #">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Manufacturer</label>
                <input type="text" class="form-control" id="newToolManufacturer" placeholder="e.g., Kennametal, OSG">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Manufacturer Part Number</label>
                <input type="text" class="form-control" id="newToolManufacturerPartNumber" placeholder="Manufacturer's part #">
              </div>
            </div>

            <!-- Tool Life Tracking (only for consumable_tool) -->
            <div id="toolLifeSection" style="display: none;">
              <h5 class="mb-3">Tool Life Tracking</h5>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label class="form-label required">Maximum Tool Life</label>
                  <input type="number" class="form-control" id="newToolLifeMax" step="0.01" min="0" placeholder="e.g., 3600">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label required">Life Unit</label>
                  <select class="form-select" id="newToolLifeUnit">
                    <option value="seconds">Seconds</option>
                    <option value="minutes">Minutes</option>
                    <option value="hours">Hours</option>
                    <option value="cycles">Cycles</option>
                    <option value="parts">Parts</option>
                    <option value="meters">Meters</option>
                  </select>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Warning Threshold (%)</label>
                  <input type="number" class="form-control" id="newToolWarningThreshold" value="80" min="0" max="100">
                  <small class="form-hint">Alert when tool reaches this % of life used</small>
                </div>
              </div>
            </div>

            <!-- Machine Compatibility -->
            <h5 class="mb-3">Machine Compatibility</h5>
            <div class="mb-3">
              <label class="form-label">Compatible Machine Types</label>
              <div id="newToolMachineTypes" class="d-flex flex-wrap gap-2">
                <!-- Populated dynamically with checkboxes -->
              </div>
              <small class="form-hint">Leave empty if compatible with all machines</small>
            </div>

            <!-- Tool Specifications -->
            <h5 class="mb-3">Tool Specifications</h5>
            <div class="row">
              <div class="col-md-3 mb-3">
                <label class="form-label">Diameter</label>
                <input type="text" class="form-control" id="newToolSpecDiameter" placeholder="e.g., 0.5 inch">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Length</label>
                <input type="text" class="form-control" id="newToolSpecLength" placeholder="e.g., 3 inch">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Material</label>
                <input type="text" class="form-control" id="newToolSpecMaterial" placeholder="e.g., Carbide, HSS">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Coating</label>
                <input type="text" class="form-control" id="newToolSpecCoating" placeholder="e.g., TiAlN">
              </div>
            </div>

            <div class="row">
              <div class="col-md-12 mb-3">
                <label class="form-label">Additional Specifications (JSON format)</label>
                <textarea class="form-control" id="newToolSpecOther" rows="2" placeholder='{"flutes": "4", "shank_diameter": "0.5 inch"}'></textarea>
                <small class="form-hint">Optional: Add other specs in JSON format</small>
              </div>
            </div>

            <!-- Notes -->
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="newToolNotes" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Product</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Install Tool Modal -->
  <div class="modal fade" id="installToolModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Install Tool on Machine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="installToolForm">
          <div class="modal-body">
            <input type="hidden" id="installToolMachineId">
            <div class="mb-3">
              <label class="form-label required">Machine</label>
              <select class="form-select" id="installToolMachine" required onchange="loadCompatibleTools()">
                <option value="">Select Machine</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label required">Tool</label>
              <select class="form-select" id="installToolProduct" required>
                <option value="">Select Tool</option>
              </select>
              <small class="form-hint">Only compatible tools for the selected machine are shown</small>
            </div>
            <div class="mb-3">
              <label class="form-label required">Location on Machine</label>
              <input type="text" class="form-control" id="installToolLocation" required placeholder="e.g., T12, Spindle 1">
            </div>
            <div class="mb-3">
              <label class="form-label">Installation Date</label>
              <input type="date" class="form-control" id="installToolDate">
            </div>
            <div class="mb-3">
              <label class="form-label">Installed By</label>
              <input type="text" class="form-control" id="installToolBy">
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="installToolNotes" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Install Tool</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Replace Tool Modal -->
  <div class="modal fade" id="replaceToolModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Replace Tool</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="replaceToolForm">
          <div class="modal-body">
            <input type="hidden" id="replaceToolId">

            <!-- Current Tool Info -->
            <div class="alert alert-info mb-3">
              <h4 class="alert-heading">Current Tool</h4>
              <div id="replaceToolCurrentInfo"></div>
            </div>

            <div class="mb-3">
              <label class="form-label required">Tool Life Used (from machine display)</label>
              <div class="input-group">
                <input type="number" class="form-control" id="replaceToolLifeUsed" required min="0" step="0.01">
                <span class="input-group-text" id="replaceToolLifeUnit">seconds</span>
              </div>
              <small class="form-hint">Enter the tool life value shown on the machine controller</small>
            </div>

            <div class="mb-3">
              <label class="form-label required">Replacement Tool</label>
              <select class="form-select" id="replaceToolNewProduct" required>
                <option value="">Select Replacement Tool</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Replacement Reason</label>
              <select class="form-select" id="replaceToolReason">
                <option value="reached_tool_life">Reached Tool Life</option>
                <option value="tool_breakage">Tool Breakage</option>
                <option value="poor_finish">Poor Finish Quality</option>
                <option value="scheduled_preventive">Scheduled Preventive Replacement</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="replaceToolNotes" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Replace Tool</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Tool Details Modal -->
  <div class="modal fade" id="toolDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Tool Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="toolDetailsContent">
          <!-- Content populated by JavaScript -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
  <script src="/maintenance.js?v={{ time() }}"></script>
  <script src="/tooling.js?v={{ time() }}"></script>
@endpush
