@extends('layouts.app')

@section('title', 'Admin Panel - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">System Administration</div>
              <h1 class="page-title">Admin Panel</h1>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <!-- Stats Cards -->
          <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Total Users</div>
                  <div class="h1 mb-3" id="statTotalUsers">-</div>
                  <div>All user accounts</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Active Users</div>
                  <div class="h1 mb-3" id="statActiveUsers">-</div>
                  <div>Currently active</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Roles</div>
                  <div class="h1 mb-3" id="statTotalRoles">-</div>
                  <div>Permission groups</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Admin Users</div>
                  <div class="h1 mb-3" id="statAdminUsers">-</div>
                  <div>Users with admin access</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Navigation Tabs -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                      <a href="#tab-users" class="nav-link active" data-bs-toggle="tab" aria-selected="true" role="tab">
                        <i class="ti ti-users me-2"></i>User Management
                      </a>
                    </li>
                    <li class="nav-item" role="presentation">
                      <a href="#tab-permissions" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1">
                        <i class="ti ti-shield-lock me-2"></i>Permissions & Roles
                      </a>
                    </li>
                    <li class="nav-item" role="presentation">
                      <a href="#tab-settings" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1">
                        <i class="ti ti-settings me-2"></i>System Settings
                      </a>
                    </li>
                    <li class="nav-item" role="presentation">
                      <a href="#tab-inventory" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1">
                        <i class="ti ti-package me-2"></i>Inventory Management
                      </a>
                    </li>
                    <li class="nav-item" role="presentation">
                      <a href="#tab-location-assignment" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1">
                        <i class="ti ti-building-warehouse me-2"></i>Location Assignment
                      </a>
                    </li>
                    <li class="nav-item" role="presentation">
                      <a href="#tab-elevation-types" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1"
                         onclick="loadElevationTypes()">
                        <i class="ti ti-ruler-2 me-2"></i>Elevation Types
                      </a>
                    </li>
                    <li class="nav-item" role="presentation">
                      <a href="#tab-fab-users" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1"
                         onclick="loadFabUsersAdmin()">
                        <i class="ti ti-hard-hat me-2"></i>Fab Users
                      </a>
                    </li>
                  </ul>
                </div>

                <div class="card-body">
                  <div class="tab-content">
                    <!-- User Management Tab -->
                    <div class="tab-pane active show" id="tab-users" role="tabpanel">
                      <div class="mb-3 d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Users</h3>
                        <button class="btn btn-primary" onclick="showAddUserModal()" data-permission="users.create">
                          <i class="ti ti-plus me-1"></i>Add User
                        </button>
                      </div>

                      <div class="row mb-3">
                        <div class="col-md-3">
                          <select class="form-select" id="filterRole">
                            <option value="">All Roles</option>
                            <!-- Populated dynamically from roles -->
                          </select>
                        </div>
                        <div class="col-md-3">
                          <select class="form-select" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <input type="text" class="form-control" placeholder="Search users..." id="searchUsers">
                        </div>
                      </div>

                      <div class="loading" id="loadingUsers">
                        <div class="text-muted">Loading users...</div>
                      </div>

                      <div class="table-responsive" id="usersTableContainer" style="display: none;">
                        <table class="table table-vcenter card-table table-striped">
                          <thead>
                            <tr>
                              <th>Name</th>
                              <th>Email</th>
                              <th>Role</th>
                              <th>Status</th>
                              <th>Last Login</th>
                              <th>Created</th>
                              <th class="w-1">Actions</th>
                            </tr>
                          </thead>
                          <tbody id="usersTableBody">
                            <!-- Populated by JavaScript -->
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <!-- Permissions & Roles Tab -->
                    <div class="tab-pane" id="tab-permissions" role="tabpanel">
                      <div class="mb-3 d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Roles & Permissions</h3>
                        <button class="btn btn-primary" onclick="showAddRoleModal()" data-permission="roles.create">
                          <i class="ti ti-plus me-1"></i>Add Role
                        </button>
                      </div>

                      <div class="loading" id="loadingRoles">
                        <div class="text-muted">Loading roles...</div>
                      </div>

                      <div class="row row-cards" id="rolesContainer" style="display: none;">
                        <!-- Populated by JavaScript -->
                      </div>
                    </div>

                    <!-- System Settings Tab -->
                    <div class="tab-pane" id="tab-settings" role="tabpanel">
                      <h3 class="mb-4">System Settings</h3>

                      <div class="row">
                        <div class="col-md-6">
                          <div class="card">
                            <div class="card-header">
                              <h4 class="card-title">Application Settings</h4>
                            </div>
                            <div class="card-body">
                              <div class="mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" value="ForgeDesk" placeholder="Company name">
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Default Currency</label>
                                <select class="form-select">
                                  <option value="USD" selected>USD ($)</option>
                                  <option value="EUR">EUR (€)</option>
                                  <option value="GBP">GBP (£)</option>
                                </select>
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Timezone</label>
                                <select class="form-select">
                                  <option value="America/New_York" selected>Eastern Time (ET)</option>
                                  <option value="America/Chicago">Central Time (CT)</option>
                                  <option value="America/Denver">Mountain Time (MT)</option>
                                  <option value="America/Los_Angeles">Pacific Time (PT)</option>
                                </select>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-6">
                          <div class="card">
                            <div class="card-header">
                              <h4 class="card-title">Security Settings</h4>
                            </div>
                            <div class="card-body">
                              <div class="mb-3">
                                <label class="form-label">Session Timeout (minutes)</label>
                                <input type="number" class="form-control" value="120" placeholder="Minutes">
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Password Requirements</label>
                                <div class="form-selectgroup">
                                  <label class="form-selectgroup-item">
                                    <input type="checkbox" class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label">Minimum 8 characters</span>
                                  </label>
                                  <label class="form-selectgroup-item">
                                    <input type="checkbox" class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label">Require uppercase</span>
                                  </label>
                                  <label class="form-selectgroup-item">
                                    <input type="checkbox" class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label">Require numbers</span>
                                  </label>
                                  <label class="form-selectgroup-item">
                                    <input type="checkbox" class="form-selectgroup-input">
                                    <span class="form-selectgroup-label">Require special characters</span>
                                  </label>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="row mt-3">
                        <div class="col-12">
                          <button class="btn btn-primary">Save Settings</button>
                        </div>
                      </div>
                    </div>

                    <!-- Inventory Management Tab -->
                    <div class="tab-pane" id="tab-inventory" role="tabpanel">
                      <h3 class="mb-4">Inventory Pricing Management</h3>

                      <!-- Pricing Stats -->
                      <div class="row row-deck row-cards mb-4">
                        <div class="col-sm-6 col-lg-3">
                          <div class="card">
                            <div class="card-body">
                              <div class="subheader">Total Products</div>
                              <div class="h1 mb-3" id="statTotalProducts">-</div>
                              <div>Tubelite products</div>
                            </div>
                          </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                          <div class="card">
                            <div class="card-body">
                              <div class="subheader">With Net Cost</div>
                              <div class="h1 mb-3" id="statWithNetCost">-</div>
                              <div>Products priced</div>
                            </div>
                          </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                          <div class="card">
                            <div class="card-body">
                              <div class="subheader">Stock Length</div>
                              <div class="h1 mb-3" id="statStockLength">-</div>
                              <div>A, E, M, T parts</div>
                            </div>
                          </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                          <div class="card">
                            <div class="card-body">
                              <div class="subheader">Accessories</div>
                              <div class="h1 mb-3" id="statAccessories">-</div>
                              <div>P, S, CP parts</div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Inventory Status Refresh -->
                      <div class="card mb-4">
                        <div class="card-header">
                          <h4 class="card-title">Inventory Status</h4>
                        </div>
                        <div class="card-body">
                          <p class="text-muted mb-3">
                            Recalculates <code>quantity_on_hand</code> and <code>quantity_committed</code> from inventory locations for every product, then re-evaluates each product's status (In Stock / Low / Critical / Out of Stock). Run this if statuses appear out of sync.
                          </p>
                          <div class="d-flex align-items-center gap-3">
                            <button class="btn btn-warning" id="btn-refresh-statuses" onclick="refreshInventoryStatuses()">
                              <i class="ti ti-refresh me-1"></i>Refresh All Statuses
                            </button>
                            <span id="refresh-status-result" class="text-muted small"></span>
                          </div>
                        </div>
                      </div>

                      <!-- EZ Estimate Upload Section -->
                      <div class="row">
                        <div class="col-md-6">
                          <div class="card">
                            <div class="card-header">
                              <h4 class="card-title">EZ Estimate Management</h4>
                            </div>
                            <div class="card-body">
                              <p class="text-muted mb-3">
                                Upload an EZ Estimate Excel file to automatically update product pricing.
                                The system will parse pricing data from worksheets and calculate net costs.
                              </p>

                              <div class="mb-3" id="currentFileInfo" style="display: none;">
                                <div class="alert alert-info">
                                  <div class="d-flex align-items-center">
                                    <div class="me-2">
                                      <i class="ti ti-file-spreadsheet fs-2"></i>
                                    </div>
                                    <div class="flex-fill">
                                      <strong id="currentFileName">-</strong>
                                      <div class="text-muted small">
                                        Uploaded: <span id="currentFileDate">-</span>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              </div>

                              <div class="mb-3">
                                <label class="form-label">Upload EZ Estimate File</label>
                                <input type="file" class="form-control" id="ezEstimateFile" accept=".xlsx,.xls">
                                <div class="form-hint">Accepted formats: .xlsx, .xls (Max 10MB)</div>
                              </div>

                              <button type="button" class="btn btn-primary" onclick="uploadEzEstimate()">
                                <i class="ti ti-upload me-1"></i>Upload & Process
                              </button>

                              <div id="uploadProgress" style="display: none;" class="mt-3">
                                <div class="progress">
                                  <div class="progress-bar progress-bar-indeterminate"></div>
                                </div>
                                <div class="text-muted text-center mt-2">Processing EZ Estimate...</div>
                              </div>

                              <div id="uploadResult" style="display: none;" class="mt-3"></div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-6">
                          <div class="card">
                            <div class="card-header">
                              <h4 class="card-title">Pricing Calculation Details</h4>
                            </div>
                            <div class="card-body">
                              <h5>Stock Length Parts (A, E, M, T)</h5>
                              <p class="text-muted">
                                <strong>Formula:</strong> Price per Length × Finish Multiplier × Category Multiplier = Net Cost
                              </p>
                              <ul class="text-muted small">
                                <li><strong>SL Formulas worksheet:</strong> Part Number (Column C), Price per Length (Column G), Pricing Category (Column A)</li>
                                <li><strong>Finish Codes worksheet:</strong> Finish Code (Column F), Finish Multiplier (Column H)</li>
                                <li><strong>Multipliers worksheet:</strong> Pricing Category (B4-B12), Category Multiplier (D4-D12)</li>
                              </ul>

                              <hr>

                              <h5>Accessory Parts (P, S)</h5>
                              <p class="text-muted">
                                <strong>Formula:</strong> Price per Package × Category Multiplier = Net Cost
                              </p>
                              <ul class="text-muted small">
                                <li><strong>P Formulas worksheet:</strong> Part Number (Column C), Pricing Category (Column A), Price per Package (Column H)</li>
                                <li><strong>Multipliers worksheet:</strong> Same as above</li>
                              </ul>

                              <div class="alert alert-warning mt-3">
                                <i class="ti ti-alert-triangle me-2"></i>
                                <strong>Note:</strong> Only products from manufacturer "Tubelite" will be updated.
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Location Assignment Tab -->
                    <div class="tab-pane" id="tab-location-assignment" role="tabpanel">
                      <h3 class="mb-1">Bulk Location Assignment</h3>
                      <p class="text-muted mb-4">Reassign items from a source location to proper primary &amp; secondary locations. Useful when reorganising shelves.</p>

                      <!-- Step 1: pick source location -->
                      <div class="card mb-3">
                        <div class="card-header">
                          <h4 class="card-title">1. Select Source Location</h4>
                        </div>
                        <div class="card-body">
                          <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                              <label class="form-label">Source Storage Location</label>
                              <select class="form-select" id="locSourceLocation">
                                <option value="">— choose a location to pull items from —</option>
                              </select>
                            </div>
                            <div class="col-auto">
                              <button class="btn btn-primary" onclick="locLoadItems()">
                                <i class="ti ti-download me-1"></i>Load Items
                              </button>
                            </div>
                            <div class="col-auto">
                              <span id="locLoadCount" class="text-muted"></span>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Step 2: assignment table -->
                      <div class="card mb-3" id="locAssignmentCard" style="display:none">
                        <div class="card-header">
                          <h4 class="card-title">2. Assign Locations</h4>
                          <div class="ms-auto d-flex gap-2 align-items-center">
                            <span id="locProgressText" class="text-muted small"></span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="locSetAllPrimary()">
                              <i class="ti ti-copy me-1"></i>Apply primary to all
                            </button>
                            <button class="btn btn-sm btn-success" id="locSaveAllBtn" onclick="locSaveAll()">
                              <i class="ti ti-device-floppy me-1"></i>Save All
                            </button>
                          </div>
                        </div>
                        <div class="card-body border-bottom py-2">
                          <div class="row g-2 align-items-center">
                            <div class="col-auto text-muted small">Quick-set all rows:</div>
                            <div class="col-md-3">
                              <select class="form-select form-select-sm" id="locQuickPrimary">
                                <option value="">Primary location…</option>
                              </select>
                            </div>
                            <div class="col-md-3">
                              <select class="form-select form-select-sm" id="locQuickSecondary">
                                <option value="">(no secondary)</option>
                              </select>
                            </div>
                            <div class="col-auto">
                              <button class="btn btn-sm btn-outline-primary" onclick="locApplyQuickSet()">Apply to unsaved rows</button>
                            </div>
                          </div>
                        </div>
                        <div class="table-responsive">
                          <table class="table table-vcenter table-sm mb-0">
                            <thead>
                              <tr>
                                <th style="width:10%">SKU</th>
                                <th style="width:20%">Description</th>
                                <th style="width:6%" class="text-end">Qty</th>
                                <th style="width:5%" class="text-center">Primary?</th>
                                <th style="width:22%">New Primary Location <span class="text-danger">*</span></th>
                                <th style="width:22%">Secondary Location <span class="text-muted">(optional)</span></th>
                                <th style="width:10%">Status</th>
                                <th style="width:5%"></th>
                              </tr>
                            </thead>
                            <tbody id="locAssignmentBody"></tbody>
                          </table>
                        </div>
                      </div>

                      <!-- Unassigned products section -->
                      <div class="card" id="locUnassignedCard" style="display:none">
                        <div class="card-header">
                          <h4 class="card-title">
                            <i class="ti ti-alert-triangle text-yellow me-2"></i>Parts With No Storage Location
                          </h4>
                          <div class="ms-auto d-flex gap-2 align-items-center">
                            <span id="locUnassignedProgressText" class="text-muted small"></span>
                            <button class="btn btn-sm btn-success" id="locSaveAllUnassignedBtn" onclick="locSaveAllUnassigned()">
                              <i class="ti ti-device-floppy me-1"></i>Save All
                            </button>
                          </div>
                        </div>
                        <div class="card-body border-bottom py-2">
                          <div class="row g-2 align-items-center">
                            <div class="col-auto text-muted small">Quick-set all rows:</div>
                            <div class="col-md-3">
                              <select class="form-select form-select-sm" id="locUnassignedQuickPrimary">
                                <option value="">Primary location…</option>
                              </select>
                            </div>
                            <div class="col-md-3">
                              <select class="form-select form-select-sm" id="locUnassignedQuickSecondary">
                                <option value="">(no secondary)</option>
                              </select>
                            </div>
                            <div class="col-auto">
                              <button class="btn btn-sm btn-outline-primary" onclick="locApplyUnassignedQuickSet()">Apply to unsaved rows</button>
                            </div>
                          </div>
                        </div>
                        <div class="table-responsive">
                          <table class="table table-vcenter table-sm mb-0">
                            <thead>
                              <tr>
                                <th style="width:10%">SKU</th>
                                <th style="width:22%">Description</th>
                                <th style="width:6%" class="text-end">On Hand</th>
                                <th style="width:6%" class="text-end">Committed</th>
                                <th style="width:22%">Primary Location <span class="text-danger">*</span></th>
                                <th style="width:22%">Secondary Location <span class="text-muted">(optional)</span></th>
                                <th style="width:7%">Status</th>
                                <th style="width:5%"></th>
                              </tr>
                            </thead>
                            <tbody id="locUnassignedBody"></tbody>
                          </table>
                        </div>
                      </div>

                    </div><!-- /tab-location-assignment -->

                    <!-- Elevation Types Tab -->
                    <div class="tab-pane" id="tab-elevation-types" role="tabpanel">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                          <h3 class="mb-1">Elevation Types</h3>
                          <p class="text-muted mb-0">Manage types used on fabrication work order elevations. Each type can have its own stage checklist.</p>
                        </div>
                        <button class="btn btn-primary" onclick="openAddElevType()">
                          <i class="ti ti-plus me-1"></i>Add Type
                        </button>
                      </div>

                      <div id="elev-types-loading" class="text-center text-muted py-4" style="display:none;">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <p class="mt-2 mb-0">Loading…</p>
                      </div>

                      <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                          <thead>
                            <tr>
                              <th style="width:50px">Color</th>
                              <th>Name</th>
                              <th>Sort</th>
                              <th>Status</th>
                              <th class="w-1">Actions</th>
                            </tr>
                          </thead>
                          <tbody id="elev-types-tbody">
                            <tr><td colspan="5" class="text-muted text-center py-3">Click "Elevation Types" tab to load.</td></tr>
                          </tbody>
                        </table>
                      </div>
                    </div><!-- /tab-elevation-types -->

                    <!-- Fab Users Tab -->
                    <div class="tab-pane" id="tab-fab-users" role="tabpanel">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                          <h3 class="mb-1">Fab Users</h3>
                          <p class="text-muted mb-0">Shop floor workers who can be assigned to stages and listed as elevation completers.</p>
                        </div>
                        <button class="btn btn-primary" onclick="openAddFabUser()">
                          <i class="ti ti-plus me-1"></i>Add Fab User
                        </button>
                      </div>

                      <div id="fab-users-loading" class="text-center text-muted py-4" style="display:none;">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <p class="mt-2 mb-0">Loading…</p>
                      </div>

                      <div class="table-responsive">
                        <table class="table table-vcenter card-table table-striped">
                          <thead>
                            <tr>
                              <th>Name</th>
                              <th>Initials</th>
                              <th>Role</th>
                              <th>Email</th>
                              <th>Status</th>
                              <th class="w-1">Actions</th>
                            </tr>
                          </thead>
                          <tbody id="fab-users-tbody">
                            <tr><td colspan="6" class="text-muted text-center py-3">Click "Fab Users" tab to load.</td></tr>
                          </tbody>
                        </table>
                      </div>
                    </div><!-- /tab-fab-users -->

                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Elevation Type Modal -->
    <div class="modal fade" id="elevTypeModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="elevTypeModalTitle">Add Elevation Type</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="elevTypeId">
            <div class="row mb-3">
              <div class="col-md-8">
                <label class="form-label required">Name</label>
                <input type="text" class="form-control" id="elevTypeName" placeholder="e.g. Storefront">
              </div>
              <div class="col-md-4">
                <label class="form-label">Color</label>
                <div class="input-group">
                  <input type="color" class="form-control form-control-color" id="elevTypeColor" value="#3b82f6">
                  <input type="text" class="form-control" id="elevTypeColorHex" value="#3b82f6"
                    oninput="document.getElementById('elevTypeColor').value=this.value"
                    style="max-width:90px;font-family:monospace;">
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Sort Order</label>
              <input type="number" class="form-control" id="elevTypeSortOrder" value="99" min="1">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveElevType()">Save</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Stage Templates Default-User Modal -->
    <div class="modal modal-blur fade" id="tplModal" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="tplModalTypeName">Stage Templates</h5>
            <button type="button" class="btn-close" onclick="closeTplModal()"></button>
          </div>
          <div class="modal-body" id="tplModalBody">
            <p class="text-muted">Loading…</p>
          </div>
          <div class="modal-footer">
            <span class="text-muted small me-auto">Changes save automatically.</span>
            <button type="button" class="btn btn-ghost-secondary" onclick="closeTplModal()">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Fab User Modal -->
    <div class="modal modal-blur fade" id="fabUserModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="fabUserModalTitle">Add Fab User</h5>
            <button type="button" class="btn-close" onclick="closeFabUserModal()"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="fabUserId">
            <div class="row g-3 mb-3">
              <div class="col-md-8">
                <label class="form-label required">Name</label>
                <input type="text" class="form-control" id="fabUserName" placeholder="Full name">
              </div>
              <div class="col-md-4">
                <label class="form-label">Initials</label>
                <input type="text" class="form-control" id="fabUserInitials" placeholder="e.g. JD" maxlength="4">
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label required">Role</label>
                <select class="form-select" id="fabUserRole">
                  <option value="worker">Worker</option>
                  <option value="manager">Manager</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" id="fabUserEmail" placeholder="optional">
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Shop Floor PIN <span class="text-muted small">(4–8 digits)</span></label>
                <input type="password" class="form-control" id="fabUserPin" placeholder="Leave blank to keep / clear"
                  inputmode="numeric" maxlength="8" autocomplete="new-password">
                <div class="form-text" id="fabUserPinHint">Set a PIN to allow this user to log in on the shop floor tablet.</div>
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <button type="button" class="btn btn-ghost-danger btn-sm" id="fabUserClearPinBtn" style="display:none"
                  onclick="clearFabPin()">Clear existing PIN</button>
              </div>
            </div>
            <div class="mb-0">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" id="fabUserActive" checked>
                <span class="form-check-label">Active</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-ghost-secondary" onclick="closeFabUserModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveFabUser()">Save</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal modal-blur fade" id="addUserModal" tabindex="-1" style="display: none;" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add New User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">First Name</label>
                <input type="text" class="form-control" id="addUserFirstName" placeholder="First name">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label required">Last Name</label>
                <input type="text" class="form-control" id="addUserLastName" placeholder="Last name">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label required">Email</label>
              <input type="email" class="form-control" id="addUserEmail" placeholder="user@example.com">
            </div>
            <div class="mb-3">
              <label class="form-label required">Password</label>
              <input type="password" class="form-control" id="addUserPassword" placeholder="Password" autocomplete="new-password">
            </div>
            <div class="mb-3">
              <label class="form-label required">Role</label>
              <select class="form-select" id="addUserRole">
                <option value="">Select role...</option>
                <!-- Populated dynamically from roles -->
              </select>
            </div>
            <div class="mb-3">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" id="addUserActive" checked>
                <span class="form-check-label">Active</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveNewUser()">Create User</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal modal-blur fade" id="editUserModal" tabindex="-1" style="display: none;" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="editUserId">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">First Name</label>
                <input type="text" class="form-control" id="editUserFirstName">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label required">Last Name</label>
                <input type="text" class="form-control" id="editUserLastName">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label required">Email</label>
              <input type="email" class="form-control" id="editUserEmail">
            </div>
            <div class="mb-3">
              <label class="form-label">New Password (leave blank to keep current)</label>
              <input type="password" class="form-control" id="editUserPassword" placeholder="New password" autocomplete="new-password">
            </div>
            <div class="mb-3">
              <label class="form-label required">Role</label>
              <select class="form-select" id="editUserRole">
                <!-- Populated dynamically from roles -->
              </select>
            </div>
            <div class="mb-3">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" id="editUserActive">
                <span class="form-check-label">Active</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveEditUser()">Save Changes</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Role Modal -->
    <div class="modal modal-blur fade" id="addRoleModal" tabindex="-1" style="display: none;" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add New Role</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required">Role Name</label>
              <input type="text" class="form-control" id="addRoleName" placeholder="e.g., custom_role (lowercase, underscores only)">
              <small class="form-text text-muted">Internal role name (lowercase, underscores, no spaces)</small>
            </div>
            <div class="mb-3">
              <label class="form-label required">Display Name</label>
              <input type="text" class="form-control" id="addRoleDisplayName" placeholder="e.g., Custom Role">
              <small class="form-text text-muted">User-friendly display name</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="addRoleDescription" rows="2" placeholder="Role description"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Permissions</label>
              <div id="addRolePermissions" class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                <div class="text-center text-muted py-3">
                  <div class="spinner-border spinner-border-sm" role="status"></div>
                  <p class="mt-2 mb-0">Loading permissions...</p>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveNewRole()">Create Role</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal modal-blur fade" id="editRoleModal" tabindex="-1" style="display: none;" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Role</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="editRoleId">
            <input type="hidden" id="editRoleIsSystem">

            <div class="alert alert-warning" id="systemRoleWarning" style="display: none;">
              <i class="ti ti-alert-triangle me-2"></i>
              This is a system role. The role name cannot be changed.
            </div>

            <div class="mb-3">
              <label class="form-label required">Role Name</label>
              <input type="text" class="form-control" id="editRoleName" placeholder="e.g., custom_role">
              <small class="form-text text-muted">Internal role name (lowercase, underscores, no spaces)</small>
            </div>
            <div class="mb-3">
              <label class="form-label required">Display Name</label>
              <input type="text" class="form-control" id="editRoleDisplayName" placeholder="e.g., Custom Role">
              <small class="form-text text-muted">User-friendly display name</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="editRoleDescription" rows="2" placeholder="Role description"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Permissions</label>
              <div id="editRolePermissions" class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                <div class="text-center text-muted py-3">
                  <div class="spinner-border spinner-border-sm" role="status"></div>
                  <p class="mt-2 mb-0">Loading permissions...</p>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveEditRole()">Save Changes</button>
          </div>
        </div>
      </div>
    </div>

    <script>
      // Placeholder data for demonstration
      let users = [];
      let roles = [];
      let permissions = [];

      // Bootstrap Modal helpers
      function showModal(element) {
        const modal = new bootstrap.Modal(element);
        modal.show();
      }

      function hideModal(element) {
        const modal = bootstrap.Modal.getInstance(element);
        if (modal) modal.hide();
      }

      // Notification helper
      function showNotification(message, type = 'success') {
        const bgColors = {
          success: 'bg-success',
          danger: 'bg-danger',
          warning: 'bg-warning',
          info: 'bg-info'
        };

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white ${bgColors[type] || 'bg-success'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
          <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
        `;

        let container = document.getElementById('toastContainer');
        if (!container) {
          container = document.createElement('div');
          container.id = 'toastContainer';
          container.className = 'toast-container position-fixed top-0 end-0 p-3';
          container.style.zIndex = '9999';
          document.body.appendChild(container);
        }

        container.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 3000 });
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => {
          toast.remove();
        });
      }

      // User Management Functions
      function showAddUserModal() {
        document.getElementById('addUserFirstName').value = '';
        document.getElementById('addUserLastName').value = '';
        document.getElementById('addUserEmail').value = '';
        document.getElementById('addUserPassword').value = '';
        document.getElementById('addUserRole').value = '';
        document.getElementById('addUserActive').checked = true;

        // Ensure role dropdown is populated
        if (roles.length > 0) {
          populateRoleDropdowns();
        }

        showModal(document.getElementById('addUserModal'));
      }

      async function saveNewUser() {
        const firstName = document.getElementById('addUserFirstName').value;
        const lastName = document.getElementById('addUserLastName').value;
        const email = document.getElementById('addUserEmail').value;
        const password = document.getElementById('addUserPassword').value;
        const role = document.getElementById('addUserRole').value;
        const active = document.getElementById('addUserActive').checked;

        if (!firstName || !lastName || !email || !password || !role) {
          showNotification('Please fill in all required fields', 'danger');
          return;
        }

        try {
          const response = await authenticatedFetch('/users', {
            method: 'POST',
            body: JSON.stringify({
              first_name: firstName,
              last_name: lastName,
              email: email,
              password: password,
              role: role,
              is_active: active
            })
          });

          showNotification('User created successfully', 'success');
          hideModal(document.getElementById('addUserModal'));
          loadUsers();
          loadStatistics();
        } catch (error) {
          console.error('Error creating user:', error);
          showNotification(error.message || 'Failed to create user', 'danger');
        }
      }

      async function editUser(userId) {
        try {
          const user = await authenticatedFetch(`/users/${userId}`);

          document.getElementById('editUserId').value = user.id;
          document.getElementById('editUserFirstName').value = user.first_name;
          document.getElementById('editUserLastName').value = user.last_name;
          document.getElementById('editUserEmail').value = user.email;
          document.getElementById('editUserPassword').value = '';
          document.getElementById('editUserRole').value = user.role;
          document.getElementById('editUserActive').checked = user.is_active;

          showModal(document.getElementById('editUserModal'));
        } catch (error) {
          console.error('Error loading user:', error);
          showNotification('Failed to load user details', 'danger');
        }
      }

      async function deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;

        try {
          await authenticatedFetch(`/users/${userId}`, {
            method: 'DELETE'
          });

          showNotification('User deleted successfully', 'success');
          loadUsers();
          loadStatistics();
        } catch (error) {
          console.error('Error deleting user:', error);
          showNotification(error.message || 'Failed to delete user', 'danger');
        }
      }

      async function saveEditUser() {
        const userId = document.getElementById('editUserId').value;
        const firstName = document.getElementById('editUserFirstName').value;
        const lastName = document.getElementById('editUserLastName').value;
        const email = document.getElementById('editUserEmail').value;
        const password = document.getElementById('editUserPassword').value;
        const role = document.getElementById('editUserRole').value;
        const active = document.getElementById('editUserActive').checked;

        if (!firstName || !lastName || !email || !role) {
          showNotification('Please fill in all required fields', 'danger');
          return;
        }

        try {
          const payload = {
            first_name: firstName,
            last_name: lastName,
            email: email,
            role: role,
            is_active: active
          };

          // Only include password if it was changed
          if (password) {
            payload.password = password;
          }

          await authenticatedFetch(`/users/${userId}`, {
            method: 'PUT',
            body: JSON.stringify(payload)
          });

          showNotification('User updated successfully', 'success');
          hideModal(document.getElementById('editUserModal'));
          loadUsers();
        } catch (error) {
          console.error('Error updating user:', error);
          showNotification(error.message || 'Failed to update user', 'danger');
        }
      }

      // Role Management Functions
      async function showAddRoleModal() {
        document.getElementById('addRoleName').value = '';
        document.getElementById('addRoleDisplayName').value = '';
        document.getElementById('addRoleDescription').value = '';

        // Load permissions and render checkboxes
        await loadPermissions();
        renderPermissionsCheckboxes('addRolePermissions', []);

        showModal(document.getElementById('addRoleModal'));
      }

      async function saveNewRole() {
        const name = document.getElementById('addRoleName').value.trim();
        const displayName = document.getElementById('addRoleDisplayName').value.trim();
        const description = document.getElementById('addRoleDescription').value.trim();

        if (!name) {
          showNotification('Please enter a role name', 'danger');
          return;
        }

        if (!displayName) {
          showNotification('Please enter a display name', 'danger');
          return;
        }

        // Get selected permissions
        const checkboxes = document.querySelectorAll('#addRolePermissions input[type="checkbox"]:checked');
        const selectedPermissions = Array.from(checkboxes).map(cb => cb.value);

        try {
          await authenticatedFetch('/roles', {
            method: 'POST',
            body: JSON.stringify({
              name: name,
              display_name: displayName,
              description: description,
              permissions: selectedPermissions
            })
          });

          showNotification('Role created successfully', 'success');
          hideModal(document.getElementById('addRoleModal'));
          loadRoles();
        } catch (error) {
          console.error('Error creating role:', error);
          showNotification(error.message || 'Failed to create role', 'danger');
        }
      }

      async function editRole(roleId) {
        try {
          // Load role data
          const role = await authenticatedFetch(`/roles/${roleId}`);

          // Populate edit modal
          document.getElementById('editRoleId').value = role.id;
          document.getElementById('editRoleName').value = role.name;
          document.getElementById('editRoleDisplayName').value = role.display_name;
          document.getElementById('editRoleDescription').value = role.description || '';
          document.getElementById('editRoleIsSystem').value = role.is_system;

          // Disable name editing for system roles
          if (role.is_system) {
            document.getElementById('editRoleName').disabled = true;
            document.getElementById('systemRoleWarning').style.display = 'block';
          } else {
            document.getElementById('editRoleName').disabled = false;
            document.getElementById('systemRoleWarning').style.display = 'none';
          }

          // Load permissions and render with current role's permissions checked
          await loadPermissions();
          const rolePermissions = role.permissions.map(p => p.name);
          renderPermissionsCheckboxes('editRolePermissions', rolePermissions);

          showModal(document.getElementById('editRoleModal'));
        } catch (error) {
          console.error('Error loading role:', error);
          showNotification('Failed to load role data', 'danger');
        }
      }

      async function saveEditRole() {
        const roleId = document.getElementById('editRoleId').value;
        const name = document.getElementById('editRoleName').value.trim();
        const displayName = document.getElementById('editRoleDisplayName').value.trim();
        const description = document.getElementById('editRoleDescription').value.trim();
        const isSystem = document.getElementById('editRoleIsSystem').value === 'true';

        if (!displayName) {
          showNotification('Please enter a display name', 'danger');
          return;
        }

        // Get selected permissions
        const checkboxes = document.querySelectorAll('#editRolePermissions input[type="checkbox"]:checked');
        const selectedPermissions = Array.from(checkboxes).map(cb => cb.value);

        try {
          const payload = {
            display_name: displayName,
            description: description,
            permissions: selectedPermissions
          };

          // Only send name if it's not a system role
          if (!isSystem) {
            payload.name = name;
          }

          await authenticatedFetch(`/roles/${roleId}`, {
            method: 'PUT',
            body: JSON.stringify(payload)
          });

          showNotification('Role updated successfully', 'success');
          hideModal(document.getElementById('editRoleModal'));
          loadRoles();
        } catch (error) {
          console.error('Error updating role:', error);
          showNotification(error.message || 'Failed to update role', 'danger');
        }
      }

      async function deleteRole(roleId) {
        const role = roles.find(r => r.id === roleId);

        if (!role) return;

        if (role.is_system) {
          showNotification('Cannot delete system roles', 'danger');
          return;
        }

        if (!confirm(`Are you sure you want to delete the role "${role.display_name}"? This action cannot be undone.`)) {
          return;
        }

        try {
          await authenticatedFetch(`/roles/${roleId}`, {
            method: 'DELETE'
          });

          showNotification('Role deleted successfully', 'success');
          loadRoles();
        } catch (error) {
          console.error('Error deleting role:', error);
          showNotification(error.message || 'Failed to delete role', 'danger');
        }
      }

      // Load permissions from API
      async function loadPermissions() {
        if (permissions.length > 0) return; // Already loaded

        try {
          permissions = await authenticatedFetch('/permissions');
        } catch (error) {
          console.error('Error loading permissions:', error);
          showNotification('Failed to load permissions', 'danger');
        }
      }

      // Render permissions checkboxes grouped by category
      function renderPermissionsCheckboxes(containerId, checkedPermissions = []) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';

        if (permissions.length === 0) {
          container.innerHTML = '<p class="text-muted">No permissions available</p>';
          return;
        }

        permissions.forEach(group => {
          const categoryDiv = document.createElement('div');
          categoryDiv.className = 'mb-3';

          const categoryTitle = document.createElement('h4');
          categoryTitle.className = 'text-muted mb-2';
          categoryTitle.style.fontSize = '0.875rem';
          categoryTitle.style.textTransform = 'capitalize';
          categoryTitle.textContent = group.category;
          categoryDiv.appendChild(categoryTitle);

          const row = document.createElement('div');
          row.className = 'row';

          group.permissions.forEach((permission, index) => {
            const col = document.createElement('div');
            col.className = 'col-md-6';

            const isChecked = checkedPermissions.includes(permission.name);

            const label = document.createElement('label');
            label.className = 'form-check';
            label.innerHTML = `
              <input class="form-check-input" type="checkbox" value="${permission.name}" ${isChecked ? 'checked' : ''}>
              <span class="form-check-label">
                ${permission.display_name}
                ${permission.description ? `<br><small class="text-muted">${permission.description}</small>` : ''}
              </span>
            `;

            col.appendChild(label);
            row.appendChild(col);
          });

          categoryDiv.appendChild(row);
          container.appendChild(categoryDiv);
        });
      }

      // Data Loading Functions
      async function loadUsers() {
        document.getElementById('loadingUsers').style.display = 'block';
        document.getElementById('usersTableContainer').style.display = 'none';

        try {
          const roleFilter = document.getElementById('filterRole')?.value;
          const statusFilter = document.getElementById('filterStatus')?.value;
          const searchQuery = document.getElementById('searchUsers')?.value;

          let url = '/users?';
          if (roleFilter) url += `role=${roleFilter}&`;
          if (statusFilter) url += `is_active=${statusFilter}&`;
          if (searchQuery) url += `search=${encodeURIComponent(searchQuery)}&`;

          users = await authenticatedFetch(url);

          renderUsers();
          document.getElementById('loadingUsers').style.display = 'none';
          document.getElementById('usersTableContainer').style.display = 'block';
        } catch (error) {
          console.error('Error loading users:', error);
          document.getElementById('loadingUsers').style.display = 'none';
          showNotification('Failed to load users', 'danger');
        }
      }

      async function loadStatistics() {
        try {
          const stats = await authenticatedFetch('/users/statistics');

          document.getElementById('statTotalUsers').textContent = stats.total_users;
          document.getElementById('statActiveUsers').textContent = stats.active_users;
          document.getElementById('statAdminUsers').textContent = stats.admin_users;
          document.getElementById('statTotalRoles').textContent = Object.keys(stats.by_role || {}).length;
        } catch (error) {
          console.error('Error loading statistics:', error);
        }
      }

      function renderUsers() {
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = '';

        const roleBadges = {
          admin: '<span class="badge bg-red">Admin</span>',
          manager: '<span class="badge bg-blue">Manager</span>',
          fabricator: '<span class="badge bg-green">Fabricator</span>',
          viewer: '<span class="badge bg-gray">Viewer</span>'
        };

        tbody.innerHTML = users.map(user => {
          const statusBadge = user.is_active
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>';

          const roleBadge = roleBadges[user.role] || '<span class="badge bg-gray">' + user.role + '</span>';

          const lastLogin = user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never';
          const createdAt = user.created_at ? new Date(user.created_at).toLocaleDateString() : '-';

          return `
            <tr>
              <td>${user.name}</td>
              <td>${user.email}</td>
              <td>${roleBadge}</td>
              <td>${statusBadge}</td>
              <td>${lastLogin}</td>
              <td>${createdAt}</td>
              <td>
                <button class="btn btn-sm btn-icon btn-ghost-secondary" onclick="editUser(${user.id})" title="Edit" data-permission="users.edit">
                  <i class="ti ti-edit"></i>
                </button>
                <button class="btn btn-sm btn-icon btn-ghost-danger" onclick="deleteUser(${user.id})" title="Delete" data-permission="users.delete">
                  <i class="ti ti-trash"></i>
                </button>
              </td>
            </tr>
          `;
        }).join('');

        // Apply action permissions to dynamically created buttons
        if (typeof applyActionPermissions === 'function') {
          applyActionPermissions();
        }
      }

      async function loadRoles() {
        document.getElementById('loadingRoles').style.display = 'block';
        document.getElementById('rolesContainer').style.display = 'none';

        try {
          roles = await authenticatedFetch('/roles');

          renderRoles();
          document.getElementById('loadingRoles').style.display = 'none';
          document.getElementById('rolesContainer').style.display = 'flex';
        } catch (error) {
          console.error('Error loading roles:', error);
          document.getElementById('loadingRoles').style.display = 'none';
          showNotification('Failed to load roles', 'danger');
        }
      }

      function renderRoles() {
        const container = document.getElementById('rolesContainer');
        container.innerHTML = '';

        container.innerHTML = roles.map(role => {
          const systemBadge = role.is_system ? '<span class="badge bg-info ms-2">System</span>' : '';
          const deleteOption = role.is_system
            ? ''
            : `<a class="dropdown-item text-danger" href="#" onclick="deleteRole(${role.id}); return false;" data-permission="roles.delete">
                 <i class="ti ti-trash me-2"></i>Delete
               </a>`;

          return `
            <div class="col-md-6 col-lg-4">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                      <h3 class="card-title mb-1">${role.display_name}${systemBadge}</h3>
                      <div class="text-muted">${role.description || 'No description'}</div>
                    </div>
                    <div class="dropdown">
                      <button class="btn btn-icon btn-sm" type="button" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="#" onclick="editRole(${role.id}); return false;" data-permission="roles.edit">
                          <i class="ti ti-edit me-2"></i>Edit
                        </a>
                        ${deleteOption}
                      </div>
                    </div>
                  </div>
                  <div class="mt-3">
                    <div class="d-flex align-items-center justify-content-between">
                      <div>
                        <i class="ti ti-users me-2 text-muted"></i>
                        <span class="text-muted">${role.user_count} user${role.user_count !== 1 ? 's' : ''}</span>
                      </div>
                      <div>
                        <i class="ti ti-shield-lock me-2 text-muted"></i>
                        <span class="text-muted">${role.permission_count} permissions</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `;
        }).join('');

        // Update role dropdowns after loading roles
        populateRoleDropdowns();

        // Apply action permissions to dynamically created buttons
        if (typeof applyActionPermissions === 'function') {
          applyActionPermissions();
        }
      }

      // Populate all role dropdowns with loaded roles
      function populateRoleDropdowns() {
        const roleOptions = roles.map(role => `<option value="${role.name}">${role.display_name}</option>`).join('');

        // Populate filter dropdown
        const filterRole = document.getElementById('filterRole');
        if (filterRole) {
          const currentFilter = filterRole.value;
          filterRole.innerHTML = '<option value="">All Roles</option>' + roleOptions;
          filterRole.value = currentFilter; // Restore previous selection
        }

        // Populate add user role dropdown
        const addUserRole = document.getElementById('addUserRole');
        if (addUserRole) {
          addUserRole.innerHTML = '<option value="">Select role...</option>' + roleOptions;
        }

        // Populate edit user role dropdown
        const editUserRole = document.getElementById('editUserRole');
        if (editUserRole) {
          const currentRole = editUserRole.value;
          editUserRole.innerHTML = roleOptions;
          editUserRole.value = currentRole; // Restore previous selection
        }
      }

      // Initialize on page load
      document.addEventListener('DOMContentLoaded', function() {
        loadUsers();
        loadRoles();
        loadStatistics();
        loadPricingStats();
        loadCurrentEzEstimate();
      });

      // Filter handlers
      document.getElementById('filterRole')?.addEventListener('change', loadUsers);
      document.getElementById('filterStatus')?.addEventListener('change', loadUsers);
      document.getElementById('searchUsers')?.addEventListener('input', loadUsers);

      // Inventory Management Functions
      async function refreshInventoryStatuses() {
        const btn = document.getElementById('btn-refresh-statuses');
        const result = document.getElementById('refresh-status-result');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Refreshing…';
        result.textContent = '';
        try {
          const r = await fetch('/api/v1/products/refresh-statuses', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + authToken },
          });
          const data = await r.json();
          result.className = 'text-success small';
          result.textContent = data.message || `Done — ${data.refreshed} products updated.`;
        } catch (e) {
          result.className = 'text-danger small';
          result.textContent = 'Refresh failed. Check the console.';
          console.error(e);
        } finally {
          btn.disabled = false;
          btn.innerHTML = '<i class="ti ti-refresh me-1"></i>Refresh All Statuses';
        }
      }

      function loadPricingStats() {
        fetch('/api/v1/ez-estimate/stats')
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              document.getElementById('statTotalProducts').textContent = data.stats.total_products;
              document.getElementById('statWithNetCost').textContent = data.stats.with_net_cost;
              document.getElementById('statStockLength').textContent = data.stats.stock_length;
              document.getElementById('statAccessories').textContent = data.stats.accessories;
            }
          })
          .catch(error => {
            console.error('Failed to load pricing stats:', error);
          });
      }

      function loadCurrentEzEstimate() {
        fetch('/api/v1/ez-estimate/current-file')
          .then(response => response.json())
          .then(data => {
            if (data.success && data.file) {
              document.getElementById('currentFileName').textContent = data.file.name;
              document.getElementById('currentFileDate').textContent = data.file.uploaded_at;
              document.getElementById('currentFileInfo').style.display = 'block';
            }
          })
          .catch(error => {
            console.error('Failed to load current EZ Estimate:', error);
          });
      }

      function uploadEzEstimate() {
        const fileInput = document.getElementById('ezEstimateFile');
        const file = fileInput.files[0];

        if (!file) {
          alert('Please select a file to upload');
          return;
        }

        // Show progress
        document.getElementById('uploadProgress').style.display = 'block';
        document.getElementById('uploadResult').style.display = 'none';

        const formData = new FormData();
        formData.append('file', file);

        fetch('/api/v1/ez-estimate/upload', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
          },
          body: formData
        })
        .then(async response => {
          // Try to parse as JSON, but if it fails, show the text response
          const text = await response.text();
          try {
            return JSON.parse(text);
          } catch (e) {
            throw new Error('Server returned invalid response: ' + text.substring(0, 500));
          }
        })
        .then(data => {
          document.getElementById('uploadProgress').style.display = 'none';
          document.getElementById('uploadResult').style.display = 'block';

          if (data.success) {
            let resultHtml = `
              <div class="alert alert-success">
                <h4 class="alert-title">Success!</h4>
                <div class="text-muted">
                  EZ Estimate processed successfully.<br>
                  <strong>Stock Length Parts Updated:</strong> ${data.stats.stock_length_updated}<br>
                  <strong>Accessory Parts Updated:</strong> ${data.stats.accessory_updated}
                </div>
            `;

            if (data.stats.errors && data.stats.errors.length > 0) {
              resultHtml += `
                <hr>
                <div class="mt-2">
                  <strong>Errors:</strong>
                  <ul class="mb-0">
                    ${data.stats.errors.map(err => `<li class="small">${err}</li>`).join('')}
                  </ul>
                </div>
              `;
            }

            resultHtml += '</div>';
            document.getElementById('uploadResult').innerHTML = resultHtml;

            // Reload stats and file info
            loadPricingStats();
            loadCurrentEzEstimate();

            // Clear file input
            fileInput.value = '';
          } else {
            document.getElementById('uploadResult').innerHTML = `
              <div class="alert alert-danger">
                <h4 class="alert-title">Error</h4>
                <div class="text-muted">${data.message}</div>
              </div>
            `;
          }
        })
        .catch(error => {
          document.getElementById('uploadProgress').style.display = 'none';
          document.getElementById('uploadResult').style.display = 'block';
          document.getElementById('uploadResult').innerHTML = `
            <div class="alert alert-danger">
              <h4 class="alert-title">Error</h4>
              <div class="text-muted">Failed to upload file: ${error.message}</div>
            </div>
          `;
        });
      }

      // ─── Location Assignment Tab ─────────────────────────────────────────────
      let locAllStorageLocations = [];
      let locRows       = [];
      let locRowStates  = {};
      let locUnassignedRows   = [];
      let locUnassignedStates = {};
      let locInitialized = false;

      // Lazy-load when tab becomes active
      document.querySelector('a[href="#tab-location-assignment"]')
        .addEventListener('shown.bs.tab', function() {
          if (!locInitialized) {
            locInitialized = true;
            locLoadStorageLocations().then(() => locLoadUnassignedProducts());
          }
        });

      async function locLoadStorageLocations() {
        try {
          const data = await locApiFetch('/storage-locations?per_page=500');
          locAllStorageLocations = (data.data || data).filter(l => l.is_active);
          locPopulateSourceDropdown();
          locPopulateTargetDropdowns();
        } catch(e) {
          alert('Error loading storage locations: ' + e.message);
        }
      }

      function locPopulateSourceDropdown() {
        const sel = document.getElementById('locSourceLocation');
        while (sel.options.length > 1) sel.remove(1);
        locAllStorageLocations.forEach(loc => {
          const opt = document.createElement('option');
          opt.value = loc.id;
          opt.textContent = loc.name + (loc.code ? ` [${loc.code}]` : '');
          sel.appendChild(opt);
        });
      }

      function locPopulateTargetDropdowns() {
        ['locQuickPrimary','locQuickSecondary','locUnassignedQuickPrimary','locUnassignedQuickSecondary'].forEach(id => {
          const sel = document.getElementById(id);
          if (!sel) return;
          while (sel.options.length > 1) sel.remove(1);
          locAllStorageLocations.forEach(loc => {
            const opt = document.createElement('option');
            opt.value = loc.id;
            opt.textContent = loc.name + (loc.code ? ` [${loc.code}]` : '');
            sel.appendChild(opt);
          });
        });
      }

      function locMakeLocationOptions(selectedId = '') {
        let html = '<option value="">— select —</option>';
        locAllStorageLocations.forEach(loc => {
          const sel = String(loc.id) === String(selectedId) ? ' selected' : '';
          const label = locEscHtml(loc.name + (loc.code ? ` [${loc.code}]` : ''));
          html += `<option value="${loc.id}"${sel}>${label}</option>`;
        });
        return html;
      }

      async function locLoadItems() {
        const srcId = document.getElementById('locSourceLocation').value;
        if (!srcId) { alert('Please select a source location.'); return; }
        document.getElementById('locLoadCount').textContent = 'Loading…';
        try {
          const data = await locApiFetch(`/locations/by-storage/${srcId}`);
          locRows = data.items;
          locRowStates = {};
          locRows.forEach(r => {
            locRowStates[r.inv_location_id] = { primaryId: '', secondaryId: '', status: 'pending' };
          });
          document.getElementById('locLoadCount').textContent =
            `${locRows.length} item${locRows.length !== 1 ? 's' : ''} at this location`;
          document.getElementById('locAssignmentCard').style.display = '';
          locRenderTable();
          locUpdateProgress();
        } catch(e) {
          document.getElementById('locLoadCount').textContent = '';
          alert('Error loading items: ' + e.message);
        }
      }

      function locRenderTable() {
        const tbody = document.getElementById('locAssignmentBody');
        if (locRows.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No items at this location.</td></tr>';
          return;
        }
        tbody.innerHTML = locRows.map(row => {
          const state    = locRowStates[row.inv_location_id];
          const disabled = state.status === 'saved' ? 'disabled' : '';
          return `
            <tr id="loc-row-${row.inv_location_id}" class="${state.status === 'saved' ? 'table-success' : state.status === 'error' ? 'table-danger' : ''}">
              <td><code>${locEscHtml(row.sku)}</code></td>
              <td class="small">${locEscHtml(row.description || '')}</td>
              <td class="text-end">${row.quantity.toLocaleString()}</td>
              <td class="text-center">${row.is_primary ? '<span class="badge bg-blue-lt">Yes</span>' : ''}</td>
              <td>
                <select class="form-select form-select-sm" id="loc-primary-${row.inv_location_id}" ${disabled}
                        onchange="locRowStates[${row.inv_location_id}].primaryId = this.value">
                  ${locMakeLocationOptions(state.primaryId)}
                </select>
              </td>
              <td>
                <select class="form-select form-select-sm" id="loc-secondary-${row.inv_location_id}" ${disabled}
                        onchange="locRowStates[${row.inv_location_id}].secondaryId = this.value">
                  <option value="">(none)</option>
                  ${locMakeLocationOptions(state.secondaryId)}
                </select>
              </td>
              <td>${locStatusBadge(state.status)}</td>
              <td>${state.status !== 'saved'
                ? `<button class="btn btn-sm btn-primary" onclick="locSaveRow(${row.inv_location_id})"><i class="ti ti-check"></i></button>`
                : ''}</td>
            </tr>`;
        }).join('');
      }

      function locApplyQuickSet() {
        const primId = document.getElementById('locQuickPrimary').value;
        const secId  = document.getElementById('locQuickSecondary').value;
        locRows.forEach(row => {
          const state = locRowStates[row.inv_location_id];
          if (state.status === 'saved') return;
          if (primId) state.primaryId = primId;
          state.secondaryId = secId;
        });
        locRenderTable();
      }

      function locSetAllPrimary() {
        const primId = document.getElementById('locQuickPrimary').value;
        if (!primId) { alert('Set a primary location in the quick-set bar first.'); return; }
        locRows.forEach(row => {
          if (locRowStates[row.inv_location_id].status !== 'saved')
            locRowStates[row.inv_location_id].primaryId = primId;
        });
        locRenderTable();
      }

      async function locSaveRow(invLocId) {
        const state = locRowStates[invLocId];
        if (!state.primaryId) { alert('Please select a primary location for this row.'); return; }
        const row = locRows.find(r => r.inv_location_id === invLocId);
        locSetRowStatus(invLocId, 'saving');
        try {
          await locApiFetch(`/products/${row.product_id}/locations/${invLocId}`, 'PUT', {
            storage_location_id: parseInt(state.primaryId),
            quantity:            row.quantity,
            quantity_committed:  row.quantity_committed,
            is_primary:          true,
          });
          if (state.secondaryId) {
            try {
              await locApiFetch(`/products/${row.product_id}/locations`, 'POST', {
                storage_location_id: parseInt(state.secondaryId),
                quantity: 0, quantity_committed: 0, is_primary: false,
              });
            } catch(e) { console.warn('Secondary location note:', e.message); }
          }
          locSetRowStatus(invLocId, 'saved');
        } catch(e) {
          locSetRowStatus(invLocId, 'error');
          alert(`Error saving ${row.sku}: ${e.message}`);
        }
        locUpdateProgress();
      }

      async function locSaveAll() {
        const pending = locRows.filter(r => locRowStates[r.inv_location_id].status !== 'saved');
        const missing = pending.filter(r => !locRowStates[r.inv_location_id].primaryId);
        if (missing.length) { alert(`${missing.length} row(s) have no primary location selected.`); return; }
        document.getElementById('locSaveAllBtn').disabled = true;
        for (const row of pending) await locSaveRow(row.inv_location_id);
        document.getElementById('locSaveAllBtn').disabled = false;
      }

      function locSetRowStatus(invLocId, status) {
        locRowStates[invLocId].status = status;
        const tr = document.getElementById(`loc-row-${invLocId}`);
        if (!tr) return;
        tr.className = status === 'saved' ? 'table-success' : status === 'error' ? 'table-danger' : '';
        tr.cells[6].innerHTML = locStatusBadge(status);
        tr.cells[7].innerHTML = status !== 'saved'
          ? `<button class="btn btn-sm btn-primary" onclick="locSaveRow(${invLocId})"><i class="ti ti-check"></i></button>` : '';
        if (status === 'saved') {
          tr.querySelector(`#loc-primary-${invLocId}`)?.setAttribute('disabled', '');
          tr.querySelector(`#loc-secondary-${invLocId}`)?.setAttribute('disabled', '');
        }
      }

      function locUpdateProgress() {
        const total  = locRows.length;
        const saved  = locRows.filter(r => locRowStates[r.inv_location_id].status === 'saved').length;
        const errors = locRows.filter(r => locRowStates[r.inv_location_id].status === 'error').length;
        document.getElementById('locProgressText').textContent =
          total > 0 ? `${saved}/${total} saved${errors ? `, ${errors} error(s)` : ''}` : '';
      }

      // ── Unassigned products ──────────────────────────────────────────────────
      async function locLoadUnassignedProducts() {
        try {
          const data = await locApiFetch('/locations/products-without-storage');
          locUnassignedRows = data.items;
          locUnassignedStates = {};
          locUnassignedRows.forEach(r => {
            locUnassignedStates[r.product_id] = { primaryId: '', secondaryId: '', status: 'pending' };
          });
          if (locUnassignedRows.length === 0) return;
          document.getElementById('locUnassignedCard').style.display = '';
          locRenderUnassignedTable();
          locUpdateUnassignedProgress();
        } catch(e) { console.error('Error loading unassigned products:', e.message); }
      }

      function locRenderUnassignedTable() {
        const tbody = document.getElementById('locUnassignedBody');
        if (locUnassignedRows.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No unassigned parts found.</td></tr>';
          return;
        }
        tbody.innerHTML = locUnassignedRows.map(row => {
          const state    = locUnassignedStates[row.product_id];
          const disabled = state.status === 'saved' ? 'disabled' : '';
          return `
            <tr id="loc-urow-${row.product_id}" class="${state.status === 'saved' ? 'table-success' : state.status === 'error' ? 'table-danger' : ''}">
              <td><code>${locEscHtml(row.sku)}</code></td>
              <td class="small">${locEscHtml(row.description || '')}</td>
              <td class="text-end">${(row.quantity_on_hand || 0).toLocaleString()}</td>
              <td class="text-end">${(row.quantity_committed || 0).toLocaleString()}</td>
              <td>
                <select class="form-select form-select-sm" id="loc-uprimary-${row.product_id}" ${disabled}
                        onchange="locUnassignedStates[${row.product_id}].primaryId = this.value">
                  ${locMakeLocationOptions(state.primaryId)}
                </select>
              </td>
              <td>
                <select class="form-select form-select-sm" id="loc-usecondary-${row.product_id}" ${disabled}
                        onchange="locUnassignedStates[${row.product_id}].secondaryId = this.value">
                  <option value="">(none)</option>
                  ${locMakeLocationOptions(state.secondaryId)}
                </select>
              </td>
              <td>${locStatusBadge(state.status)}</td>
              <td>${state.status !== 'saved'
                ? `<button class="btn btn-sm btn-primary" onclick="locSaveUnassignedRow(${row.product_id})"><i class="ti ti-check"></i></button>`
                : ''}</td>
            </tr>`;
        }).join('');
      }

      function locApplyUnassignedQuickSet() {
        const primId = document.getElementById('locUnassignedQuickPrimary').value;
        const secId  = document.getElementById('locUnassignedQuickSecondary').value;
        locUnassignedRows.forEach(row => {
          const state = locUnassignedStates[row.product_id];
          if (state.status === 'saved') return;
          if (primId) state.primaryId = primId;
          state.secondaryId = secId;
        });
        locRenderUnassignedTable();
      }

      async function locSaveUnassignedRow(productId) {
        const state = locUnassignedStates[productId];
        if (!state.primaryId) { alert('Please select a primary location for this row.'); return; }
        const row = locUnassignedRows.find(r => r.product_id === productId);
        locSetUnassignedRowStatus(productId, 'saving');
        try {
          await locApiFetch(`/products/${productId}/locations`, 'POST', {
            storage_location_id: parseInt(state.primaryId),
            quantity:            row.quantity_on_hand || 0,
            quantity_committed:  row.quantity_committed || 0,
            is_primary:          true,
          });
          if (state.secondaryId) {
            try {
              await locApiFetch(`/products/${productId}/locations`, 'POST', {
                storage_location_id: parseInt(state.secondaryId),
                quantity: 0, quantity_committed: 0, is_primary: false,
              });
            } catch(e) { console.warn('Secondary location note:', e.message); }
          }
          locSetUnassignedRowStatus(productId, 'saved');
        } catch(e) {
          locSetUnassignedRowStatus(productId, 'error');
          alert(`Error saving ${row.sku}: ${e.message}`);
        }
        locUpdateUnassignedProgress();
      }

      async function locSaveAllUnassigned() {
        const pending = locUnassignedRows.filter(r => locUnassignedStates[r.product_id].status !== 'saved');
        const missing = pending.filter(r => !locUnassignedStates[r.product_id].primaryId);
        if (missing.length) { alert(`${missing.length} row(s) have no primary location selected.`); return; }
        document.getElementById('locSaveAllUnassignedBtn').disabled = true;
        for (const row of pending) await locSaveUnassignedRow(row.product_id);
        document.getElementById('locSaveAllUnassignedBtn').disabled = false;
      }

      function locSetUnassignedRowStatus(productId, status) {
        locUnassignedStates[productId].status = status;
        const tr = document.getElementById(`loc-urow-${productId}`);
        if (!tr) return;
        tr.className = status === 'saved' ? 'table-success' : status === 'error' ? 'table-danger' : '';
        tr.cells[6].innerHTML = locStatusBadge(status);
        tr.cells[7].innerHTML = status !== 'saved'
          ? `<button class="btn btn-sm btn-primary" onclick="locSaveUnassignedRow(${productId})"><i class="ti ti-check"></i></button>` : '';
        if (status === 'saved') {
          tr.querySelector(`#loc-uprimary-${productId}`)?.setAttribute('disabled', '');
          tr.querySelector(`#loc-usecondary-${productId}`)?.setAttribute('disabled', '');
        }
      }

      function locUpdateUnassignedProgress() {
        const total  = locUnassignedRows.length;
        const saved  = locUnassignedRows.filter(r => locUnassignedStates[r.product_id].status === 'saved').length;
        const errors = locUnassignedRows.filter(r => locUnassignedStates[r.product_id].status === 'error').length;
        document.getElementById('locUnassignedProgressText').textContent =
          total > 0 ? `${saved}/${total} saved${errors ? `, ${errors} error(s)` : ''}` : '';
      }

      // ── Shared helpers ───────────────────────────────────────────────────────
      function locStatusBadge(status) {
        return { pending: '<span class="badge bg-secondary">Pending</span>',
                 saving:  '<span class="badge bg-azure">Saving…</span>',
                 saved:   '<span class="badge bg-success">Saved</span>',
                 error:   '<span class="badge bg-danger">Error</span>' }[status] || status;
      }

      async function locApiFetch(path, method = 'GET', body = null) {
        const opts = {
          method,
          headers: {
            'Authorization': `Bearer ${authToken}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        };
        if (body) opts.body = JSON.stringify(body);
        const res  = await fetch(`${API_BASE}${path}`, opts);
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || json.error || `HTTP ${res.status}`);
        return json;
      }

      function locEscHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
      }

      // ============================================================
      // Elevation Types admin
      // ============================================================
      let elevationTypes = [];

      async function loadElevationTypes() {
        document.getElementById('elev-types-loading').style.display = 'block';
        try {
          const r = await fetch('/api/v1/elevation-types', {
            headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') },
          });
          const data = await r.json();
          elevationTypes = data.elevation_types || [];
          renderElevTypes();
        } catch (e) {
          console.error(e);
        } finally {
          document.getElementById('elev-types-loading').style.display = 'none';
        }
      }

      function renderElevTypes() {
        const tbody = document.getElementById('elev-types-tbody');
        if (!elevationTypes.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-3">No elevation types defined.</td></tr>';
          return;
        }
        tbody.innerHTML = elevationTypes.map(t => `
          <tr>
            <td><span style="display:inline-block;width:28px;height:28px;border-radius:6px;background:${locEscHtml(t.color)}"></span></td>
            <td><strong>${locEscHtml(t.name)}</strong></td>
            <td>${t.sort_order}</td>
            <td>${t.active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-ghost-secondary" onclick="openTypeTemplates(${t.id}, '${t.name.replace(/'/g, "\\'")}')" title="Stage defaults">
                  <i class="ti ti-users"></i>
                </button>
                <button class="btn btn-ghost-secondary" onclick="openEditElevType(${t.id})" title="Edit">
                  <i class="ti ti-pencil"></i>
                </button>
                <button class="btn btn-ghost-danger" onclick="deactivateElevType(${t.id})" title="Deactivate" ${!t.active ? 'disabled' : ''}>
                  <i class="ti ti-trash"></i>
                </button>
              </div>
            </td>
          </tr>`).join('');
      }

      function openAddElevType() {
        document.getElementById('elevTypeModalTitle').textContent = 'Add Elevation Type';
        document.getElementById('elevTypeId').value = '';
        document.getElementById('elevTypeName').value = '';
        document.getElementById('elevTypeColor').value = '#3b82f6';
        document.getElementById('elevTypeColorHex').value = '#3b82f6';
        document.getElementById('elevTypeSortOrder').value = (elevationTypes.length + 1);
        // Use data-bs-dismiss or manual show
        const modal = document.getElementById('elevTypeModal');
        if (window.bootstrap?.Modal) new window.bootstrap.Modal(modal).show();
        else modal.classList.add('show'), modal.style.display = 'block', document.body.classList.add('modal-open');
      }

      function openEditElevType(id) {
        const t = elevationTypes.find(x => x.id === id);
        if (!t) return;
        document.getElementById('elevTypeModalTitle').textContent = 'Edit Elevation Type';
        document.getElementById('elevTypeId').value = t.id;
        document.getElementById('elevTypeName').value = t.name;
        document.getElementById('elevTypeColor').value = t.color || '#3b82f6';
        document.getElementById('elevTypeColorHex').value = t.color || '#3b82f6';
        document.getElementById('elevTypeSortOrder').value = t.sort_order;
        const modal = document.getElementById('elevTypeModal');
        if (window.bootstrap?.Modal) new window.bootstrap.Modal(modal).show();
        else modal.classList.add('show'), modal.style.display = 'block', document.body.classList.add('modal-open');
      }

      async function saveElevType() {
        const id = document.getElementById('elevTypeId').value;
        const color = document.getElementById('elevTypeColorHex').value || document.getElementById('elevTypeColor').value;
        const body = {
          name: document.getElementById('elevTypeName').value,
          color: color,
          sort_order: parseInt(document.getElementById('elevTypeSortOrder').value) || 99,
        };
        if (!body.name) { alert('Name is required.'); return; }
        try {
          const url = id ? `/api/v1/elevation-types/${id}` : '/api/v1/elevation-types';
          const method = id ? 'PUT' : 'POST';
          const r = await fetch(url, {
            method,
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
            },
            body: JSON.stringify(body),
          });
          if (!r.ok) throw new Error('Save failed');
          const modal = document.getElementById('elevTypeModal');
          modal.classList.remove('show'); modal.style.display = '';
          document.body.classList.remove('modal-open');
          await loadElevationTypes();
        } catch (e) {
          console.error(e);
          alert('Failed to save elevation type');
        }
      }

      async function deactivateElevType(id) {
        if (!confirm('Deactivate this elevation type? It will no longer appear in work order forms.')) return;
        try {
          await fetch(`/api/v1/elevation-types/${id}`, {
            method: 'DELETE',
            headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') },
          });
          await loadElevationTypes();
        } catch (e) {
          console.error(e);
          alert('Failed to deactivate');
        }
      }

      // Sync color picker → hex input
      document.addEventListener('DOMContentLoaded', () => {
        const picker = document.getElementById('elevTypeColor');
        const hex = document.getElementById('elevTypeColorHex');
        if (picker && hex) picker.addEventListener('input', () => hex.value = picker.value);
      });

      // ============================================================
      // Fab Users admin
      // ============================================================
      const fabUserAPI = (path, opts = {}) => fetch('/api/v1' + path, {
        ...opts,
        headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken'), ...(opts.headers || {}) },
      });

      async function loadFabUsersAdmin() {
        document.getElementById('fab-users-loading').style.display = 'block';
        try {
          const r = await fabUserAPI('/fab-users?all=1');
          const data = await r.json();
          renderFabUsers(data.users || []);
        } catch (e) {
          console.error(e);
          alert('Failed to load fab users');
        } finally {
          document.getElementById('fab-users-loading').style.display = 'none';
        }
      }

      function renderFabUsers(users) {
        const tbody = document.getElementById('fab-users-tbody');
        if (!users.length) {
          tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">No fab users yet.</td></tr>';
          return;
        }
        const roleLabel = { worker: 'Worker', manager: 'Manager', admin: 'Admin' };
        tbody.innerHTML = users.map(u => `
          <tr>
            <td><strong>${escAdmin(u.name)}</strong></td>
            <td><span class="badge bg-secondary-lt text-secondary">${escAdmin(u.initials || '—')}</span></td>
            <td>${escAdmin(roleLabel[u.role] || u.role)}</td>
            <td class="text-muted small">${escAdmin(u.email || '—')}</td>
            <td>${u.active
              ? '<span class="badge bg-success-lt text-success">Active</span>'
              : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>'}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-ghost-secondary" onclick="openEditFabUser(${u.id})" title="Edit"><i class="ti ti-pencil"></i></button>
                ${u.active
                  ? `<button class="btn btn-ghost-danger" onclick="deactivateFabUser(${u.id})" title="Deactivate"><i class="ti ti-user-off"></i></button>`
                  : `<button class="btn btn-ghost-success" onclick="reactivateFabUser(${u.id})" title="Reactivate"><i class="ti ti-user-check"></i></button>`}
              </div>
            </td>
          </tr>`).join('');
      }

      function escAdmin(str) {
        if (str == null) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
      }

      let _fabUserHasPin = false; // tracks if edit target already has a pin set
      let _fabUserClearPin = false; // user clicked "Clear existing PIN"

      function openAddFabUser() {
        document.getElementById('fabUserModalTitle').textContent = 'Add Fab User';
        document.getElementById('fabUserId').value = '';
        document.getElementById('fabUserName').value = '';
        document.getElementById('fabUserInitials').value = '';
        document.getElementById('fabUserRole').value = 'worker';
        document.getElementById('fabUserEmail').value = '';
        document.getElementById('fabUserActive').checked = true;
        document.getElementById('fabUserPin').value = '';
        document.getElementById('fabUserClearPinBtn').style.display = 'none';
        _fabUserHasPin = false; _fabUserClearPin = false;
        showFabUserModal();
      }

      async function openEditFabUser(id) {
        try {
          const r = await fabUserAPI('/fab-users?all=1');
          const data = await r.json();
          const u = (data.users || []).find(x => x.id === id);
          if (!u) return;
          document.getElementById('fabUserModalTitle').textContent = 'Edit Fab User';
          document.getElementById('fabUserId').value = u.id;
          document.getElementById('fabUserName').value = u.name;
          document.getElementById('fabUserInitials').value = u.initials || '';
          document.getElementById('fabUserRole').value = u.role;
          document.getElementById('fabUserEmail').value = u.email || '';
          document.getElementById('fabUserActive').checked = u.active;
          document.getElementById('fabUserPin').value = '';
          _fabUserHasPin = !!u.has_pin;
          _fabUserClearPin = false;
          document.getElementById('fabUserClearPinBtn').style.display = _fabUserHasPin ? '' : 'none';
          document.getElementById('fabUserPinHint').textContent = _fabUserHasPin
            ? 'PIN is set. Enter a new value to replace it, or click "Clear existing PIN".'
            : 'Set a PIN to allow this user to log in on the shop floor tablet.';
          showFabUserModal();
        } catch (e) { console.error(e); }
      }

      function clearFabPin() {
        if (!confirm('Remove this user\'s shop floor PIN?')) return;
        _fabUserClearPin = true;
        document.getElementById('fabUserPin').value = '';
        document.getElementById('fabUserClearPinBtn').style.display = 'none';
        document.getElementById('fabUserPinHint').textContent = 'PIN will be cleared on save.';
      }

      async function saveFabUser() {
        const name = document.getElementById('fabUserName').value.trim();
        if (!name) { alert('Name is required.'); return; }
        const id  = document.getElementById('fabUserId').value;
        const pin = document.getElementById('fabUserPin').value.trim();
        const body = {
          name,
          initials:  document.getElementById('fabUserInitials').value.trim() || null,
          role:      document.getElementById('fabUserRole').value,
          email:     document.getElementById('fabUserEmail').value.trim() || null,
          active:    document.getElementById('fabUserActive').checked,
        };
        try {
          const url    = id ? `/fab-users/${id}` : '/fab-users';
          const method = id ? 'PUT' : 'POST';
          const r = await fabUserAPI(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
          });
          if (!r.ok) throw new Error('Save failed');
          const saved = await r.json();
          const userId = id || saved.id;
          // Handle PIN separately
          if (pin) {
            if (pin.length < 4) { alert('PIN must be at least 4 digits.'); return; }
            await fabUserAPI(`/fab-users/${userId}/set-pin`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ pin }),
            });
          } else if (_fabUserClearPin) {
            await fabUserAPI(`/fab-users/${userId}/set-pin`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ pin: null }),
            });
          }
          closeFabUserModal();
          await loadFabUsersAdmin();
        } catch (e) {
          console.error(e);
          alert('Failed to save fab user');
        }
      }

      async function deactivateFabUser(id) {
        if (!confirm('Deactivate this fab user? They will no longer appear in assignment dropdowns.')) return;
        try {
          await fabUserAPI(`/fab-users/${id}`, { method: 'DELETE' });
          await loadFabUsersAdmin();
        } catch (e) { console.error(e); alert('Failed to deactivate'); }
      }

      async function reactivateFabUser(id) {
        try {
          await fabUserAPI(`/fab-users/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ active: true }),
          });
          await loadFabUsersAdmin();
        } catch (e) { console.error(e); alert('Failed to reactivate'); }
      }

      function showFabUserModal() {
        const m = document.getElementById('fabUserModal');
        m.classList.add('show'); m.style.display = 'block';
        document.body.classList.add('modal-open');
      }
      function closeFabUserModal() {
        const m = document.getElementById('fabUserModal');
        m.classList.remove('show'); m.style.display = '';
        document.body.classList.remove('modal-open');
      }

      // ============================================================
      // Stage template CRUD management
      // ============================================================
      function escT(str) {
        if (!str) return '';
        const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML;
      }

      let tplCurrentTypeId = null;
      let tplCurrentTypeName = '';
      let tplUsers = [];

      async function openTypeTemplates(typeId, typeName) {
        tplCurrentTypeId = typeId;
        tplCurrentTypeName = typeName;
        document.getElementById('tplModalTypeName').textContent = typeName + ' — Stage Templates';
        document.getElementById('tplModalBody').innerHTML = '<p class="text-muted">Loading…</p>';
        showTplModal();
        await reloadTplModal();
      }

      async function reloadTplModal() {
        try {
          const [tplRes, uRes] = await Promise.all([
            fetch('/api/v1/elevation-types?with_templates=1', {
              headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') },
            }),
            fetch('/api/v1/fab-users?all=1', {
              headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') },
            }),
          ]);
          const typeData  = ((await tplRes.json()).elevation_types || []).find(t => t.id === tplCurrentTypeId);
          tplUsers        = (await uRes.json()).users || [];
          const templates = typeData?.stage_templates || [];
          renderTplModal(templates);
        } catch (e) {
          console.error(e);
          document.getElementById('tplModalBody').innerHTML = '<p class="text-danger">Failed to load templates.</p>';
        }
      }

      function renderTplModal(templates) {
        const userOpts = '<option value="">— No default —</option>' +
          tplUsers.map(u => `<option value="${u.id}">${escT(u.name)}</option>`).join('');

        const rows = templates.map((t, idx) => `
          <tr id="tpl-row-${t.id}">
            <td style="width:52px">
              <div class="d-flex flex-column gap-1">
                <button class="btn btn-xs btn-ghost-secondary p-0 px-1" title="Move up"
                    onclick="moveTpl(${t.id}, 'up')" ${idx === 0 ? 'disabled' : ''}>
                  <i class="ti ti-chevron-up"></i>
                </button>
                <button class="btn btn-xs btn-ghost-secondary p-0 px-1" title="Move down"
                    onclick="moveTpl(${t.id}, 'down')" ${idx === templates.length - 1 ? 'disabled' : ''}>
                  <i class="ti ti-chevron-down"></i>
                </button>
              </div>
            </td>
            <td style="width:36px" class="text-muted small">${t.sort_order}</td>
            <td>
              <input type="text" class="form-control form-control-sm" value="${escT(t.name)}"
                style="min-width:140px"
                onblur="saveTplField(${t.id}, 'name', this.value)"
                onkeydown="if(event.key==='Enter')this.blur()">
            </td>
            <td>
              <input type="text" class="form-control form-control-sm text-muted" value="${escT(t.description || '')}"
                placeholder="Description…" style="min-width:180px"
                onblur="saveTplField(${t.id}, 'description', this.value)"
                onkeydown="if(event.key==='Enter')this.blur()">
            </td>
            <td>
              <select class="form-select form-select-sm" style="min-width:160px"
                  onchange="saveTplField(${t.id}, 'default_user_id', this.value || null)">
                ${userOpts.replace(`value="${t.default_user_id || ''}"`, `value="${t.default_user_id || ''}" selected`)}
              </select>
            </td>
            <td>
              <button class="btn btn-sm btn-ghost-danger" onclick="deleteTpl(${t.id})" title="Delete stage">
                <i class="ti ti-trash"></i>
              </button>
            </td>
          </tr>`).join('');

        document.getElementById('tplModalBody').innerHTML = `
          <p class="text-muted small mb-2">
            Edit stage names, descriptions, and default assignees. Use arrows to reorder.
            Changes to names and descriptions save on blur (click away or press Enter).
          </p>
          <div class="table-responsive">
            <table class="table table-sm table-vcenter align-middle mb-2">
              <thead>
                <tr>
                  <th style="width:52px"></th>
                  <th style="width:36px">#</th>
                  <th>Stage Name</th>
                  <th>Description</th>
                  <th>Default Assignee</th>
                  <th style="width:48px"></th>
                </tr>
              </thead>
              <tbody>${rows || '<tr><td colspan="6" class="text-muted text-center py-3">No stages yet. Add one below.</td></tr>'}</tbody>
            </table>
          </div>
          <div class="border-top pt-3">
            <div class="d-flex gap-2 align-items-end">
              <div class="flex-grow-1">
                <label class="form-label mb-1 small">New Stage Name</label>
                <input type="text" class="form-control form-control-sm" id="tpl-new-name" placeholder="e.g. Frame Fab">
              </div>
              <div style="min-width:200px">
                <label class="form-label mb-1 small">Description (optional)</label>
                <input type="text" class="form-control form-control-sm" id="tpl-new-desc" placeholder="Brief description">
              </div>
              <button class="btn btn-primary btn-sm" onclick="addTpl()">
                <i class="ti ti-plus me-1"></i>Add Stage
              </button>
            </div>
          </div>`;
      }

      async function saveTplField(id, field, value) {
        try {
          await fetch(`/api/v1/stage-templates/${id}`, {
            method: 'PATCH',
            headers: {
              'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ [field]: value || null }),
          });
        } catch (e) { console.error(e); alert('Failed to save'); }
      }

      async function moveTpl(id, direction) {
        try {
          const r = await fetch('/api/v1/elevation-types?with_templates=1', {
            headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') },
          });
          const typeData  = ((await r.json()).elevation_types || []).find(t => t.id === tplCurrentTypeId);
          const templates = typeData?.stage_templates || [];
          const idx = templates.findIndex(t => t.id === id);
          if (idx < 0) return;

          const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
          if (swapIdx < 0 || swapIdx >= templates.length) return;

          // Swap sort_orders
          const a = templates[idx];
          const b = templates[swapIdx];
          await Promise.all([
            fetch(`/api/v1/stage-templates/${a.id}`, {
              method: 'PATCH',
              headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken'), 'Content-Type': 'application/json' },
              body: JSON.stringify({ sort_order: b.sort_order }),
            }),
            fetch(`/api/v1/stage-templates/${b.id}`, {
              method: 'PATCH',
              headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken'), 'Content-Type': 'application/json' },
              body: JSON.stringify({ sort_order: a.sort_order }),
            }),
          ]);
          await reloadTplModal();
        } catch (e) { console.error(e); alert('Failed to reorder'); }
      }

      async function addTpl() {
        const name = document.getElementById('tpl-new-name').value.trim();
        const desc = document.getElementById('tpl-new-desc').value.trim();
        if (!name) { alert('Stage name is required'); return; }
        try {
          await fetch('/api/v1/stage-templates', {
            method: 'POST',
            headers: {
              'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ elevation_type_id: tplCurrentTypeId, name, description: desc || null }),
          });
          await reloadTplModal();
        } catch (e) { console.error(e); alert('Failed to add stage'); }
      }

      async function deleteTpl(id) {
        if (!confirm('Delete this stage template? Existing work order stages are not affected.')) return;
        try {
          await fetch(`/api/v1/stage-templates/${id}`, {
            method: 'DELETE',
            headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') },
          });
          await reloadTplModal();
        } catch (e) { console.error(e); alert('Failed to delete stage'); }
      }

      function showTplModal() {
        const m = document.getElementById('tplModal');
        m.classList.add('show'); m.style.display = 'block';
        document.body.classList.add('modal-open');
      }
      function closeTplModal() {
        const m = document.getElementById('tplModal');
        m.classList.remove('show'); m.style.display = '';
        document.body.classList.remove('modal-open');
      }

    </script>

    <style>
      .form-check {
        margin-bottom: 0.5rem;
      }

      .loading {
        padding: 2rem;
        text-align: center;
      }
    </style>
@endsection
