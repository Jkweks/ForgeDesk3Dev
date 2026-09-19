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
                    <li class="nav-item" role="presentation" data-permission="configurator.catalog.manage">
                      <a href="#tab-configurator-catalog" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1">
                        <i class="ti ti-door me-2"></i>Frame Catalog
                      </a>
                    </li>
                    <li class="nav-item" role="presentation" data-permission="configurator.catalog.manage">
                      <a href="#tab-door-catalog" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1"
                         onclick="dcLoadAll()">
                        <i class="ti ti-door-enter me-2"></i>Door Catalog
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
                        <div class="btn-list">
                          <button class="btn btn-outline-primary" id="sendPendingInvitesBtn" onclick="sendPendingInvites()"
                                  data-permission="users.create" style="display:none">
                            <i class="ti ti-mail-fast me-1"></i><span id="sendPendingInvitesLabel">Send held invitations</span>
                          </button>
                          <button class="btn btn-primary" onclick="showAddUserModal()" data-permission="users.create">
                            <i class="ti ti-plus me-1"></i>Add User
                          </button>
                        </div>
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
                        <div class="col-12">
                          <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                              <h4 class="card-title mb-0">Company Locations</h4>
                              <button class="btn btn-sm btn-primary" onclick="openCompanyLocationModal()">
                                <i class="ti ti-plus me-1"></i>Add Location
                              </button>
                            </div>
                            <div class="card-body">
                              <p class="text-muted">
                                The <strong>primary</strong> location prints as the order-from / bill-to address on
                                purchase order PDFs. Any location can be picked as a PO's ship-to address.
                              </p>
                              <div class="table-responsive">
                                <table class="table table-vcenter">
                                  <thead>
                                    <tr>
                                      <th>Name</th>
                                      <th>Address</th>
                                      <th>Phone / Fax</th>
                                      <th class="text-center">Primary</th>
                                      <th class="w-1"></th>
                                    </tr>
                                  </thead>
                                  <tbody id="companyLocationsBody">
                                    <tr><td colspan="5" class="text-muted text-center py-3">Loading…</td></tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="row mt-3">
                        <div class="col-12">
                          <div class="card">
                            <div class="card-header">
                              <h4 class="card-title mb-0">Company Branding</h4>
                            </div>
                            <div class="card-body">
                              <div class="row align-items-center">
                                <div class="col-md-4">
                                  <div id="companyLogoPreview" class="border rounded d-flex align-items-center justify-content-center p-3"
                                       style="min-height:120px; background:#f8fafc;">
                                    <span class="text-muted">No logo uploaded</span>
                                  </div>
                                </div>
                                <div class="col-md-8">
                                  <label class="form-label">Company Logo</label>
                                  <input type="file" class="form-control" id="companyLogoInput" accept="image/png,image/jpeg,image/gif,image/webp">
                                  <small class="form-hint">Printed on purchase order PDFs. PNG or JPG, up to 4&nbsp;MB.</small>
                                  <div class="mt-2">
                                    <button class="btn btn-primary btn-sm" onclick="uploadCompanyLogo()">
                                      <i class="ti ti-upload me-1"></i>Upload
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" id="removeCompanyLogoBtn" onclick="removeCompanyLogo()" style="display:none;">
                                      <i class="ti ti-trash me-1"></i>Remove
                                    </button>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Company Location Modal -->
                    <div class="modal modal-blur fade" id="companyLocationModal" tabindex="-1">
                      <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="companyLocationModalTitle">Add Company Location</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <input type="hidden" id="clId">
                            <div class="row">
                              <div class="col-md-8 mb-3">
                                <label class="form-label required">Location Name</label>
                                <input type="text" class="form-control" id="clName" placeholder="e.g. Main Plant">
                              </div>
                              <div class="col-md-4 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <label class="form-check form-switch mt-2">
                                  <input class="form-check-input" type="checkbox" id="clIsPrimary">
                                  <span class="form-check-label">Primary (order-from) location</span>
                                </label>
                              </div>
                            </div>
                            <div class="row">
                              <div class="col-md-6 mb-3">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" id="clAddr1">
                              </div>
                              <div class="col-md-6 mb-3">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" id="clAddr2">
                              </div>
                            </div>
                            <div class="row">
                              <div class="col-md-5 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" id="clCity">
                              </div>
                              <div class="col-md-3 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" id="clState">
                              </div>
                              <div class="col-md-4 mb-3">
                                <label class="form-label">ZIP</label>
                                <input type="text" class="form-control" id="clZip">
                              </div>
                            </div>
                            <div class="row">
                              <div class="col-md-4 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" id="clCountry" value="USA">
                              </div>
                              <div class="col-md-4 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" id="clPhone">
                              </div>
                              <div class="col-md-4 mb-3">
                                <label class="form-label">Fax</label>
                                <input type="text" class="form-control" id="clFax">
                              </div>
                            </div>
                            <div class="row">
                              <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="clEmail">
                              </div>
                              <div class="col-md-6 mb-3">
                                <label class="form-label">Notes</label>
                                <input type="text" class="form-control" id="clNotes">
                              </div>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="saveCompanyLocation()">Save Location</button>
                          </div>
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
                            Recalculates <code>quantity_on_hand</code> and <code>quantity_committed</code> from inventory locations, and <code>on_order_qty</code> from open purchase orders, for every product, then re-evaluates each product's status (In Stock / Low / Critical / Out of Stock / On Order). Run this if statuses appear out of sync or after editing a PO directly.
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
                              <th>Standard Joints</th>
                              <th>Sort</th>
                              <th>Status</th>
                              <th class="w-1">Actions</th>
                            </tr>
                          </thead>
                          <tbody id="elev-types-tbody">
                            <tr><td colspan="6" class="text-muted text-center py-3">Click "Elevation Types" tab to load.</td></tr>
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

                    <div class="tab-pane" id="tab-configurator-catalog" role="tabpanel">
                      <div class="mb-3">
                        <h3 class="mb-1">Frame Catalog</h3>
                        <p class="text-muted mb-0">Frame systems, series, extrusion profiles, and components used to auto-generate a frame BOM in the <a href="/configurator">Configurator</a>.</p>
                      </div>

                      <div class="row row-cards">
                        <!-- Frame Systems -->
                        <div class="col-12 col-lg-6">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Frame Systems</h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" onclick="cfgOpenSystemModal()" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add System</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Name</th><th>Code</th><th class="w-1"></th></tr></thead>
                                <tbody id="cfg-systems-tbody"></tbody>
                              </table>
                            </div>
                          </div>
                        </div>

                        <!-- Frame Series -->
                        <div class="col-12 col-lg-6">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Series <span id="cfg-series-scope" class="text-muted ms-1"></span></h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" id="cfg-add-series-btn" onclick="cfgOpenSeriesModal()" disabled data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Series</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Name</th><th>Code</th><th class="w-1"></th></tr></thead>
                                <tbody id="cfg-series-tbody"></tbody>
                              </table>
                              <div class="text-muted p-3" id="cfg-series-empty">Select a frame system to see its series.</div>
                            </div>
                          </div>
                        </div>

                        <!-- Profiles -->
                        <div class="col-12">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Extrusion Profiles <span id="cfg-profiles-scope" class="text-muted ms-1"></span></h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" id="cfg-add-profile-btn" onclick="cfgOpenProfileModal()" disabled data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Profile</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Role Label</th><th>Product</th><th>Formula</th><th>Conditions</th><th class="w-1"></th></tr></thead>
                                <tbody id="cfg-profiles-tbody"></tbody>
                              </table>
                              <div class="text-muted p-3" id="cfg-profiles-empty">Select a series to see its profiles.</div>
                            </div>
                          </div>
                        </div>

                        <!-- Components -->
                        <div class="col-12 col-lg-7">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Components <span id="cfg-components-scope" class="text-muted ms-1"></span></h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" id="cfg-add-component-btn" onclick="cfgOpenComponentModal()" disabled data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Component</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Label</th><th>Product</th><th>Qty</th><th class="w-1"></th></tr></thead>
                                <tbody id="cfg-components-tbody"></tbody>
                              </table>
                              <div class="text-muted p-3" id="cfg-components-empty">Select a profile to see its components.</div>
                            </div>
                          </div>
                        </div>

                        <!-- Fasteners -->
                        <div class="col-12 col-lg-5">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Fasteners <span id="cfg-fasteners-scope" class="text-muted ms-1"></span></h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" id="cfg-add-fastener-btn" onclick="cfgOpenFastenerModal()" disabled data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Fastener</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Label</th><th>Product</th><th>Qty/ea</th><th class="w-1"></th></tr></thead>
                                <tbody id="cfg-fasteners-tbody"></tbody>
                              </table>
                              <div class="text-muted p-3" id="cfg-fasteners-empty">Select a component to see its fasteners.</div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div><!-- /tab-configurator-catalog -->

                    <div class="tab-pane" id="tab-door-catalog" role="tabpanel">
                      <div class="mb-3">
                        <h3 class="mb-1">Door Catalog</h3>
                        <p class="text-muted mb-0">Door types, rails, lugs, glass specs, setting block kits, and tie rods used to auto-generate a door BOM in the <a href="/configurator">Configurator</a>.</p>
                      </div>

                      <div class="row row-cards">
                        <!-- Door Types -->
                        <div class="col-12">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Door Types <span class="text-muted ms-1">(stile height + hinge-type PN variants)</span></h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" onclick="dcOpenModal('doorType')" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Series</th><th>Stile</th><th>Height</th><th>Bevel PN</th><th>Rabbet PN</th><th>Center Pivot PN</th><th>Astragal PN</th><th>Inactive PN</th><th class="w-1"></th></tr></thead>
                                <tbody id="dc-doorType-tbody"></tbody>
                              </table>
                            </div>
                          </div>
                        </div>

                        <!-- Rails -->
                        <div class="col-12">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Rails <span class="text-muted ms-1">(top / bottom / mid, per series)</span></h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" onclick="dcOpenModal('rail')" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Type</th><th>Label</th><th>Value</th><th>Std PN</th><th>Thermal PN</th><th>Mon PN</th><th>Stacked PNs</th><th class="w-1"></th></tr></thead>
                                <tbody id="dc-rail-tbody"></tbody>
                              </table>
                            </div>
                          </div>
                        </div>

                        <!-- Rail Lugs -->
                        <div class="col-12 col-lg-6">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Rail Lugs</h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" onclick="dcOpenModal('railLug')" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Rail PN</th><th>Lug PN</th><th class="w-1"></th></tr></thead>
                                <tbody id="dc-railLug-tbody"></tbody>
                              </table>
                            </div>
                          </div>
                        </div>

                        <!-- Mid Lugs -->
                        <div class="col-12 col-lg-6">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Mid / Stacked Rail Lugs</h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" onclick="dcOpenModal('midLug')" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Rail PN</th><th>Lug PN</th><th>Fastener #1</th><th>Fastener #2</th><th class="w-1"></th></tr></thead>
                                <tbody id="dc-midLug-tbody"></tbody>
                              </table>
                            </div>
                          </div>
                        </div>

                        <!-- Glass Specs -->
                        <div class="col-12 col-lg-7">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Glass Specs</h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" onclick="dcOpenModal('glassSpec')" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Thickness</th><th>Stop PN</th><th>Gasket PN</th><th>Gasket #2 PN</th><th>Qty Factor</th><th>Stop Height</th><th class="w-1"></th></tr></thead>
                                <tbody id="dc-glassSpec-tbody"></tbody>
                              </table>
                            </div>
                          </div>
                        </div>

                        <!-- Setting Block Kits -->
                        <div class="col-12 col-lg-5">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Setting Block Kits</h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" onclick="dcOpenModal('sbk')" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Series</th><th>Glass</th><th>Kit 1</th><th>Kit 2</th><th class="w-1"></th></tr></thead>
                                <tbody id="dc-sbk-tbody"></tbody>
                              </table>
                            </div>
                          </div>
                        </div>

                        <!-- Tie Rods -->
                        <div class="col-12">
                          <div class="card">
                            <div class="card-header">
                              <h3 class="card-title">Tie Rods <span class="text-muted ms-1">(by door-width range)</span></h3>
                              <div class="card-actions">
                                <button class="btn btn-sm btn-primary" onclick="dcOpenModal('tieRod')" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add</button>
                              </div>
                            </div>
                            <div class="table-responsive">
                              <table class="table table-vcenter card-table">
                                <thead><tr><th>Series</th><th>PN</th><th>Min Len</th><th>Max Len</th><th class="w-1"></th></tr></thead>
                                <tbody id="dc-tieRod-tbody"></tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div><!-- /tab-door-catalog -->

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
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Sort Order</label>
                <input type="number" class="form-control" id="elevTypeSortOrder" value="99" min="1">
              </div>
              <div class="col-md-6">
                <label class="form-label">Standard Joints / Unit</label>
                <input type="number" class="form-control" id="elevTypeJointCount" min="0" placeholder="e.g. 6 for a door">
                <div class="form-text">Auto-fills a new elevation's joint count as quantity × this value. Leave blank if not applicable; always editable per elevation.</div>
              </div>
            </div>
            <div class="mb-1">
              <label class="form-label">Linked names</label>
              <textarea class="form-control" id="elevTypeAliases" rows="3"
                placeholder="One per line or comma-separated — e.g. Curtainwall, Curtain Wall, CWall"></textarea>
              <div class="form-text">
                Work-order imports match a row to this type when its Type cell equals or contains
                any of these, or the type name itself. Case doesn’t matter.
              </div>
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
      <div class="modal-dialog modal-xl modal-fullscreen-lg-down modal-dialog-centered modal-dialog-scrollable">
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
              <small class="form-hint">The welcome email carries a temporary password the user must change within 7 days. A held invitation gets a fresh temporary password when you finally send it.</small>
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
            <div class="mb-1">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" id="addUserSendWelcome" checked>
                <span class="form-check-label">Send welcome email now</span>
              </label>
              <small class="form-hint">Uncheck to hold it — set up the profile, roles and permissions first, then send held invitations one by one or all at once from the Users list.</small>
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

    <!-- Configurator: Frame System Modal -->
    <div class="modal modal-blur fade" id="cfg-system-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="cfg-system-form">
            <div class="modal-header"><h5 class="modal-title">Frame System</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="cfg-system-id">
              <div class="mb-3"><label class="form-label">Name</label><input type="text" class="form-control" id="cfg-system-name" required></div>
              <div class="mb-3"><label class="form-label">Code</label><input type="text" class="form-control" id="cfg-system-code" required></div>
              <div class="mb-3"><label class="form-label">Sort Order</label><input type="number" class="form-control" id="cfg-system-sort" value="0"></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Configurator: Frame Series Modal -->
    <div class="modal modal-blur fade" id="cfg-series-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="cfg-series-form">
            <div class="modal-header"><h5 class="modal-title">Frame Series</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="cfg-series-id">
              <div class="mb-3"><label class="form-label">Name</label><input type="text" class="form-control" id="cfg-series-name" required></div>
              <div class="mb-3"><label class="form-label">Code</label><input type="text" class="form-control" id="cfg-series-code" required></div>
              <div class="mb-3"><label class="form-label">Sort Order</label><input type="number" class="form-control" id="cfg-series-sort" value="0"></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Configurator: Extrusion Profile Modal -->
    <div class="modal modal-blur fade" id="cfg-profile-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <form id="cfg-profile-form">
            <div class="modal-header"><h5 class="modal-title">Extrusion Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="cfg-profile-id">
              <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Role Label</label><input type="text" class="form-control" id="cfg-profile-label" placeholder="e.g. LH Jamb" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Product</label><select class="form-select" id="cfg-profile-product" required></select></div>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label class="form-label">Condition</label>
                  <select class="form-select" id="cfg-profile-condition">
                    <option value="">Always</option>
                    <option value="single">Single only</option>
                    <option value="pair">Pair only</option>
                    <option value="transom">Transom only</option>
                    <option value="threshold">Threshold only</option>
                    <option value="transom_pair">Transom + Pair only</option>
                  </select>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Transom glazing (in, comma-separated)</label>
                  <input type="text" class="form-control" id="cfg-profile-glassthicknesses" placeholder="e.g. 0.25, 0.375, 0.5">
                </div>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label class="form-label">Section height (in)</label>
                  <input type="number" step="0.0001" class="form-control" id="cfg-profile-sectionheight" value="0">
                  <div class="form-hint">Fixed cross-section dimension (e.g. jamb depth) — referenced by other profiles' "Section" / "If Threshold" formula terms and by TH when there's no transom.</div>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Qty per opening</label>
                  <input type="number" step="1" min="1" class="form-control" id="cfg-profile-qtyperopening" value="1">
                </div>
              </div>
              <div class="mb-2 d-flex justify-content-between align-items-center">
                <label class="form-label mb-0">Cut Length Formula</label>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cfgAddFormulaTerm()"><i class="ti ti-plus"></i> Term</button>
              </div>
              <div id="cfg-formula-terms"></div>
              <div class="form-hint">Length = sum of signed terms. "Section" references another profile's section height by role label (e.g. Door Head). "If Threshold" adds the series' Threshold profile's section height only when the opening has a threshold.</div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Configurator: Component Modal -->
    <div class="modal modal-blur fade" id="cfg-component-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="cfg-component-form">
            <div class="modal-header"><h5 class="modal-title">Component</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="cfg-component-id">
              <div class="mb-3"><label class="form-label">Label</label><input type="text" class="form-control" id="cfg-component-label" required></div>
              <div class="mb-3"><label class="form-label">Product</label><select class="form-select" id="cfg-component-product" required></select></div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Qty Type</label>
                  <select class="form-select" id="cfg-component-qtytype">
                    <option value="per_opening">Per Opening</option>
                    <option value="per_door">Per Door</option>
                    <option value="per_length">Per Length (ft of cut length)</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3"><label class="form-label">Qty per</label><input type="number" step="0.001" class="form-control" id="cfg-component-qtyper" value="1" required></div>
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

    <!-- Configurator: Fastener Modal -->
    <div class="modal modal-blur fade" id="cfg-fastener-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="cfg-fastener-form">
            <div class="modal-header"><h5 class="modal-title">Fastener</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="cfg-fastener-id">
              <div class="mb-3"><label class="form-label">Label</label><input type="text" class="form-control" id="cfg-fastener-label" required></div>
              <div class="mb-3"><label class="form-label">Product</label><select class="form-select" id="cfg-fastener-product" required></select></div>
              <div class="mb-3"><label class="form-label">Qty per component</label><input type="number" step="0.001" class="form-control" id="cfg-fastener-qtyper" value="1" required></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Door Catalog: Door Type Modal -->
    <div class="modal modal-blur fade" id="dc-doorType-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <form id="dc-doorType-form">
            <div class="modal-header"><h5 class="modal-title">Door Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="dc-doorType-id">
              <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Series</label>
                  <select class="form-select" id="dc-doorType-series" required>
                    <option value="STANDARD">Standard</option><option value="THERMAL">Thermal</option><option value="MONUMENTAL">Monumental</option>
                  </select>
                </div>
                <div class="col-md-4 mb-3"><label class="form-label">Stile Name</label><input type="text" class="form-control" id="dc-doorType-stileName" placeholder="e.g. NARROW STILE" required></div>
                <div class="col-md-4 mb-3"><label class="form-label">Stile Height (in)</label><input type="number" step="0.0001" class="form-control" id="dc-doorType-stileHeight" required></div>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Bevel PN</label><input type="text" class="form-control" id="dc-doorType-bevPn"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Rabbet PN <span class="text-muted">(continuous hinge)</span></label><input type="text" class="form-control" id="dc-doorType-rabPn"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Center Pivot PN</label><input type="text" class="form-control" id="dc-doorType-cpPn"></div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Astragal Stile PN</label><input type="text" class="form-control" id="dc-doorType-astPn"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Inactive Meeting Stile PN</label><input type="text" class="form-control" id="dc-doorType-inactPn"></div>
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

    <!-- Door Catalog: Rail Modal -->
    <div class="modal modal-blur fade" id="dc-rail-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <form id="dc-rail-form">
            <div class="modal-header"><h5 class="modal-title">Rail</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="dc-rail-id">
              <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Rail Type</label>
                  <select class="form-select" id="dc-rail-railType" required>
                    <option value="top">Top</option><option value="bot">Bottom</option><option value="mid">Mid</option>
                  </select>
                </div>
                <div class="col-md-4 mb-3"><label class="form-label">Label</label><input type="text" class="form-control" id="dc-rail-label" placeholder='e.g. 2 1/8"' required></div>
                <div class="col-md-4 mb-3"><label class="form-label">Value (in)</label><input type="number" step="0.00001" class="form-control" id="dc-rail-valueIn" required></div>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Standard PN</label><input type="text" class="form-control" id="dc-rail-stdPn"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Thermal PN</label><input type="text" class="form-control" id="dc-rail-thermalPn"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Monumental PN</label><input type="text" class="form-control" id="dc-rail-monPn"></div>
              </div>
              <div class="form-hint mb-2">Stacked variants (bottom rails only — label should include "stacked", e.g. 12" (stacked))</div>
              <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Stacked Standard PN</label><input type="text" class="form-control" id="dc-rail-stackedStdPn"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Stacked Thermal PN</label><input type="text" class="form-control" id="dc-rail-stackedThermalPn"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Stacked Monumental PN</label><input type="text" class="form-control" id="dc-rail-stackedMonPn"></div>
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

    <!-- Door Catalog: Rail Lug Modal -->
    <div class="modal modal-blur fade" id="dc-railLug-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="dc-railLug-form">
            <div class="modal-header"><h5 class="modal-title">Rail Lug</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="dc-railLug-id">
              <div class="mb-3"><label class="form-label">Rail PN</label><input type="text" class="form-control" id="dc-railLug-railPn" required></div>
              <div class="mb-3"><label class="form-label">Lug PN</label><input type="text" class="form-control" id="dc-railLug-lugPn" required></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Door Catalog: Mid Lug Modal -->
    <div class="modal modal-blur fade" id="dc-midLug-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <form id="dc-midLug-form">
            <div class="modal-header"><h5 class="modal-title">Mid / Stacked Rail Lug</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="dc-midLug-id">
              <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Rail PN</label><input type="text" class="form-control" id="dc-midLug-railPn" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Lug PN</label><input type="text" class="form-control" id="dc-midLug-lugPn" required></div>
              </div>
              <div class="row">
                <div class="col-md-3 mb-3"><label class="form-label">Fastener #1 PN</label><input type="text" class="form-control" id="dc-midLug-f1Pn"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Fastener #1 Qty</label><input type="number" step="0.01" class="form-control" id="dc-midLug-f1Qty" value="0"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Fastener #2 PN</label><input type="text" class="form-control" id="dc-midLug-f2Pn"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Fastener #2 Qty</label><input type="number" step="0.01" class="form-control" id="dc-midLug-f2Qty" value="0"></div>
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

    <!-- Door Catalog: Glass Spec Modal -->
    <div class="modal modal-blur fade" id="dc-glassSpec-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <form id="dc-glassSpec-form">
            <div class="modal-header"><h5 class="modal-title">Glass Spec</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="dc-glassSpec-id">
              <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Thickness</label><input type="text" class="form-control" id="dc-glassSpec-thickness" placeholder='e.g. 1/4"' required></div>
                <div class="col-md-4 mb-3"><label class="form-label">Qty Factor</label><input type="number" step="0.01" class="form-control" id="dc-glassSpec-qtyFactor"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Stop Height (in)</label><input type="number" step="0.01" class="form-control" id="dc-glassSpec-stopHeight"></div>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Stop PN</label><input type="text" class="form-control" id="dc-glassSpec-stopPn"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Gasket PN</label><input type="text" class="form-control" id="dc-glassSpec-gasketPn"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Gasket #2 PN</label><input type="text" class="form-control" id="dc-glassSpec-gasket2Pn"></div>
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

    <!-- Door Catalog: Setting Block Kit Modal -->
    <div class="modal modal-blur fade" id="dc-sbk-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="dc-sbk-form">
            <div class="modal-header"><h5 class="modal-title">Setting Block Kit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="dc-sbk-id">
              <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Series</label>
                  <select class="form-select" id="dc-sbk-series" required>
                    <option value="STANDARD">Standard</option><option value="THERMAL">Thermal</option><option value="MONUMENTAL">Monumental</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3"><label class="form-label">Glass Thickness</label><input type="text" class="form-control" id="dc-sbk-glassThickness" placeholder='e.g. 1/4"' required></div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Kit 1 PN</label><input type="text" class="form-control" id="dc-sbk-kit1Pn"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Kit 2 PN <span class="text-muted">(used when there's a midrail)</span></label><input type="text" class="form-control" id="dc-sbk-kit2Pn"></div>
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

    <!-- Door Catalog: Tie Rod Modal -->
    <div class="modal modal-blur fade" id="dc-tieRod-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="dc-tieRod-form">
            <div class="modal-header"><h5 class="modal-title">Tie Rod</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <input type="hidden" id="dc-tieRod-id">
              <div class="mb-3"><label class="form-label">Series <span class="text-muted">(stile-width label, e.g. "NARROW STILE" or "THERMAL NARROW STILE")</span></label><input type="text" class="form-control" id="dc-tieRod-series" required></div>
              <div class="mb-3"><label class="form-label">PN</label><input type="text" class="form-control" id="dc-tieRod-pn" required></div>
              <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Min Length (exclusive)</label><input type="number" step="0.0001" class="form-control" id="dc-tieRod-minLen"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Max Length (inclusive)</label><input type="number" step="0.0001" class="form-control" id="dc-tieRod-maxLen"></div>
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
        document.getElementById('addUserRole').value = '';
        document.getElementById('addUserActive').checked = true;
        document.getElementById('addUserSendWelcome').checked = true;

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
        const role = document.getElementById('addUserRole').value;
        const active = document.getElementById('addUserActive').checked;
        const sendWelcome = document.getElementById('addUserSendWelcome').checked;

        if (!firstName || !lastName || !email || !role) {
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
              role: role,
              is_active: active,
              send_welcome_email: sendWelcome
            })
          });

          const tone = response.welcome_held ? 'info' : (response.email_sent === false ? 'warning' : 'success');
          showNotification(response.message || 'User created.', tone);
          hideModal(document.getElementById('addUserModal'));
          loadUsers();
          loadStatistics();
        } catch (error) {
          console.error('Error creating user:', error);
          showNotification(error.message || 'Failed to create user', 'danger');
        }
      }

      async function resendInvitation(userId) {
        if (!confirm('Resend the welcome email with a new temporary password? The current temporary password will stop working.')) return;

        try {
          const response = await authenticatedFetch(`/users/${userId}/resend-invitation`, { method: 'POST' });
          showNotification(response.message || 'Invitation resent.', response.email_sent === false ? 'warning' : 'success');
          loadUsers();
        } catch (error) {
          console.error('Error resending invitation:', error);
          showNotification(error.message || 'Failed to resend invitation', 'danger');
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
          let statusBadge = user.is_active
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge text-bg-secondary">Inactive</span>';

          if (user.invitation_pending) {
            statusBadge += ' <span class="badge bg-azure" title="Account created — welcome email not sent yet">Not invited</span>';
          } else if (user.must_change_password) {
            statusBadge += user.temp_password_expired
              ? ' <span class="badge bg-red" title="Temporary password expired">Invite expired</span>'
              : ' <span class="badge bg-yellow" title="Waiting for the user to set a new password">Pending invite</span>';
          }

          const roleBadge = roleBadges[user.role] || '<span class="badge bg-gray">' + user.role + '</span>';

          const lastLogin = user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never';
          const createdAt = user.created_at ? new Date(user.created_at).toLocaleDateString() : '-';

          const resendBtn = user.invitation_pending
            ? `<button class="btn btn-sm btn-icon btn-ghost-primary" onclick="sendInvitation(${user.id})" title="Send welcome email now" data-permission="users.create">
                  <i class="ti ti-send"></i>
                </button>`
            : (user.must_change_password
              ? `<button class="btn btn-sm btn-icon btn-ghost-primary" onclick="resendInvitation(${user.id})" title="Resend invitation" data-permission="users.edit">
                  <i class="ti ti-mail-forward"></i>
                </button>`
              : '');

          return `
            <tr>
              <td>${user.name}</td>
              <td>${user.email}</td>
              <td>${roleBadge}</td>
              <td>${statusBadge}</td>
              <td>${lastLogin}</td>
              <td>${createdAt}</td>
              <td>
                ${resendBtn}
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

        updatePendingInvitesButton();

        // Apply action permissions to dynamically created buttons
        if (typeof applyActionPermissions === 'function') {
          applyActionPermissions();
        }
      }

      // Show/label the bulk "Send held invitations" button from the loaded users.
      function updatePendingInvitesButton() {
        const btn = document.getElementById('sendPendingInvitesBtn');
        if (!btn) return;
        const n = (users || []).filter(u => u.invitation_pending && u.is_active).length;
        document.getElementById('sendPendingInvitesLabel').textContent =
          n ? `Send ${n} held invitation${n === 1 ? '' : 's'}` : 'Send held invitations';
        btn.style.display = n ? '' : 'none';
      }

      async function sendInvitation(userId) {
        const user = (users || []).find(u => u.id === userId);
        const who = user ? `${user.name} <${user.email}>` : 'this user';
        if (!confirm(`Send the welcome email to ${who} now? A fresh temporary password will be issued.`)) return;
        try {
          const res = await authenticatedFetch(`/users/${userId}/resend-invitation`, { method: 'POST' });
          showNotification(res.message || 'Invitation sent.', res.email_sent === false ? 'warning' : 'success');
          loadUsers();
          loadStatistics();
        } catch (error) {
          console.error('Error sending invitation:', error);
          showNotification(error.message || 'Failed to send invitation', 'danger');
        }
      }

      async function sendPendingInvites() {
        const n = (users || []).filter(u => u.invitation_pending && u.is_active).length;
        if (!n) { showNotification('No held invitations to send.', 'info'); return; }
        if (!confirm(`Send ${n} held welcome email${n === 1 ? '' : 's'} now? Each user gets a fresh temporary password valid for 7 days.`)) return;

        const btn = document.getElementById('sendPendingInvitesBtn');
        btn.disabled = true;
        try {
          const res = await authenticatedFetch('/users/send-pending-invitations', { method: 'POST', body: JSON.stringify({}) });
          const tone = res.failed && res.failed.length ? 'warning' : 'success';
          showNotification(res.message || `Sent ${res.sent} invitation(s).`, tone);
          if (res.failed && res.failed.length) {
            console.warn('Invitations that failed to send:', res.failed);
          }
          loadUsers();
          loadStatistics();
        } catch (error) {
          console.error('Error sending held invitations:', error);
          showNotification(error.message || 'Failed to send held invitations', 'danger');
        } finally {
          btn.disabled = false;
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
            credentials: 'include',
            headers: { 'X-CSRF-TOKEN': adminCsrfToken() },
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
        return { pending: '<span class="badge text-bg-secondary">Pending</span>',
                 saving:  '<span class="badge bg-azure">Saving…</span>',
                 saved:   '<span class="badge bg-success">Saved</span>',
                 error:   '<span class="badge bg-danger">Error</span>' }[status] || status;
      }

      function adminCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
      }

      async function locApiFetch(path, method = 'GET', body = null) {
        const opts = {
          method,
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': adminCsrfToken(),
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
            credentials: 'include',
            headers: { 'X-CSRF-TOKEN': adminCsrfToken() },
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
          tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">No elevation types defined.</td></tr>';
          return;
        }
        tbody.innerHTML = elevationTypes.map(t => `
          <tr>
            <td><span style="display:inline-block;width:28px;height:28px;border-radius:6px;background:${locEscHtml(t.color)}"></span></td>
            <td>
              <strong>${locEscHtml(t.name)}</strong>
              ${(t.aliases && t.aliases.length)
                ? `<div class="text-muted small">${t.aliases.map(locEscHtml).join(', ')}</div>`
                : ''}
            </td>
            <td>${t.standard_joint_count != null ? `${t.standard_joint_count} / unit` : '<span class="text-muted">—</span>'}</td>
            <td>${t.sort_order}</td>
            <td>${t.active ? '<span class="badge bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>'}</td>
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
        document.getElementById('elevTypeJointCount').value = '';
        document.getElementById('elevTypeAliases').value = '';
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
        document.getElementById('elevTypeJointCount').value = t.standard_joint_count ?? '';
        document.getElementById('elevTypeAliases').value = (t.aliases || []).join('\n');
        const modal = document.getElementById('elevTypeModal');
        if (window.bootstrap?.Modal) new window.bootstrap.Modal(modal).show();
        else modal.classList.add('show'), modal.style.display = 'block', document.body.classList.add('modal-open');
      }

      async function saveElevType() {
        const id = document.getElementById('elevTypeId').value;
        const color = document.getElementById('elevTypeColorHex').value || document.getElementById('elevTypeColor').value;
        const aliases = document.getElementById('elevTypeAliases').value
          .split(/[\n,]+/).map(s => s.trim()).filter(Boolean);
        const jointCountVal = document.getElementById('elevTypeJointCount').value;
        const body = {
          name: document.getElementById('elevTypeName').value,
          color: color,
          sort_order: parseInt(document.getElementById('elevTypeSortOrder').value) || 99,
          standard_joint_count: jointCountVal === '' ? null : Math.max(0, parseInt(jointCountVal) || 0),
          aliases: aliases,
        };
        if (!body.name) { fabToast('Name is required.', 'info'); return; }
        try {
          const url = id ? `/api/v1/elevation-types/${id}` : '/api/v1/elevation-types';
          const method = id ? 'PUT' : 'POST';
          const r = await fetch(url, {
            method,
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': adminCsrfToken(),
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
          fabToast('Failed to save elevation type.', 'error');
        }
      }

      async function deactivateElevType(id) {
        const ok = await fabConfirm({
          title: 'Deactivate Elevation Type',
          message: 'Deactivate this elevation type? It will no longer appear in work order forms.',
          confirmLabel: 'Deactivate',
          confirmClass: 'btn-warning',
        });
        if (!ok) return;
        try {
          await fetch(`/api/v1/elevation-types/${id}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: { 'X-CSRF-TOKEN': adminCsrfToken() },
          });
          await loadElevationTypes();
        } catch (e) {
          console.error(e);
          fabToast('Failed to deactivate.', 'error');
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
        credentials: 'include',
        headers: {
          'X-CSRF-TOKEN': adminCsrfToken(),
          ...(opts.headers || {}),
        },
      });

      async function loadFabUsersAdmin() {
        document.getElementById('fab-users-loading').style.display = 'block';
        try {
          const r = await fabUserAPI('/fab-users?all=1');
          const data = await r.json();
          renderFabUsers(data.users || []);
        } catch (e) {
          console.error(e);
          fabToast('Failed to load fab users.', 'error');
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

      async function clearFabPin() {
        const ok = await fabConfirm({
          title: 'Remove PIN',
          message: 'Remove this user\'s shop floor PIN?',
          confirmLabel: 'Remove',
          confirmClass: 'btn-warning',
        });
        if (!ok) return;
        _fabUserClearPin = true;
        document.getElementById('fabUserPin').value = '';
        document.getElementById('fabUserClearPinBtn').style.display = 'none';
        document.getElementById('fabUserPinHint').textContent = 'PIN will be cleared on save.';
      }

      async function saveFabUser() {
        const name = document.getElementById('fabUserName').value.trim();
        if (!name) { fabToast('Name is required.', 'info'); return; }
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
            if (pin.length < 4) { fabToast('PIN must be at least 4 digits.', 'info'); return; }
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
          fabToast('Failed to save fab user.', 'error');
        }
      }

      async function deactivateFabUser(id) {
        const ok = await fabConfirm({
          title: 'Deactivate Fab User',
          message: 'Deactivate this fab user? They will no longer appear in assignment dropdowns.',
          confirmLabel: 'Deactivate',
          confirmClass: 'btn-warning',
        });
        if (!ok) return;
        try {
          await fabUserAPI(`/fab-users/${id}`, { method: 'DELETE' });
          await loadFabUsersAdmin();
        } catch (e) { console.error(e); fabToast('Failed to deactivate.', 'error'); }
      }

      async function reactivateFabUser(id) {
        try {
          await fabUserAPI(`/fab-users/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ active: true }),
          });
          await loadFabUsersAdmin();
        } catch (e) { console.error(e); fabToast('Failed to reactivate.', 'error'); }
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
      let tplSets = [];        // [{id,name,is_default,sort_order,stage_templates:[...]}]
      let tplActiveSetId = null;

      async function openTypeTemplates(typeId, typeName) {
        tplCurrentTypeId = typeId;
        tplCurrentTypeName = typeName;
        tplActiveSetId = null;
        document.getElementById('tplModalTypeName').textContent = typeName + ' — Stage Templates';
        document.getElementById('tplModalBody').innerHTML = '<p class="text-muted">Loading…</p>';
        showTplModal();
        await reloadTplModal();
      }

      async function reloadTplModal() {
        try {
          const authHeaders = { 'X-CSRF-TOKEN': adminCsrfToken() };
          const [tplRes, uRes] = await Promise.all([
            fetch('/api/v1/elevation-types?with_templates=1', { credentials: 'include', headers: authHeaders }),
            fetch('/api/v1/fab-users?all=1', { credentials: 'include', headers: authHeaders }),
          ]);
          const typeData  = ((await tplRes.json()).elevation_types || []).find(t => t.id === tplCurrentTypeId);
          tplUsers        = (await uRes.json()).users || [];
          tplSets         = (typeData?.stage_template_sets || []).slice()
                              .sort((a, b) => a.sort_order - b.sort_order);

          if (!tplSets.some(s => s.id === tplActiveSetId)) {
            tplActiveSetId = (tplSets.find(s => s.is_default) || tplSets[0])?.id || null;
          }
          renderTplModal();
        } catch (e) {
          console.error(e);
          document.getElementById('tplModalBody').innerHTML = '<p class="text-danger">Failed to load templates.</p>';
        }
      }

      function tplActiveSet() {
        return tplSets.find(s => s.id === tplActiveSetId) || null;
      }

      function renderTplModal() {
        const set = tplActiveSet();
        const templates = set?.stage_templates || [];
        const userOpts = '<option value="">— No default —</option>' +
          tplUsers.map(u => `<option value="${u.id}">${escT(u.name)}</option>`).join('');

        const tierTabs = tplSets.map(s => `
          <button class="btn btn-sm ${s.id === tplActiveSetId ? 'btn-primary' : 'btn-ghost-secondary'}"
              onclick="tplSelectSet(${s.id})">
            ${escT(s.name)}${s.is_default ? ' <span class="badge bg-blue-lt ms-1">default</span>' : ''}
          </button>`).join('');

        const tierControls = set ? `
          <button class="btn btn-sm btn-ghost-secondary" onclick="renameTplSet(${set.id})" title="Rename tier">
            <i class="ti ti-pencil"></i>
          </button>
          ${set.is_default ? '' : `<button class="btn btn-sm btn-ghost-secondary" onclick="setDefaultTplSet(${set.id})" title="Make default tier"><i class="ti ti-star"></i></button>`}
          ${tplSets.length > 1 ? `<button class="btn btn-sm btn-ghost-danger" onclick="deleteTplSet(${set.id})" title="Delete tier"><i class="ti ti-trash"></i></button>` : ''}
        ` : '';

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
            <td style="width:74px">
              <input type="number" min="1" step="1" class="form-control form-control-sm" style="width:64px"
                value="${t.phase ?? ''}" placeholder="—"
                title="Steps sharing a phase run in parallel; a later phase waits for every 'Blocks next' step in earlier phases. Blank = run in list order."
                onblur="saveTplField(${t.id}, 'phase', this.value)"
                onkeydown="if(event.key==='Enter')this.blur()">
            </td>
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
            <td class="text-center" style="width:70px">
              <input type="checkbox" class="form-check-input" ${t.blocks_next ? 'checked' : ''}
                title="Blocks the next stage until this one is done"
                onchange="saveTplField(${t.id}, 'blocks_next', this.checked)">
            </td>
            <td style="width:92px">
              <input type="number" min="0" step="0.25" class="form-control form-control-sm" style="width:82px"
                value="${t.minutes_per_joint ?? ''}" placeholder="—"
                title="Minutes of labour per joint for this step (optional)"
                onblur="saveTplField(${t.id}, 'minutes_per_joint', this.value)"
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

        const tierRate = set ? `
          <span class="d-inline-flex align-items-center gap-1 ms-2">
            <span class="text-muted small">Tier min / joint:</span>
            <input type="number" min="0" step="0.25" class="form-control form-control-sm" style="width:82px"
              value="${set.minutes_per_joint ?? ''}" placeholder="—"
              title="Fallback labour rate for the whole tier — used only when no step sets its own"
              onblur="saveTierField(${set.id}, 'minutes_per_joint', this.value)"
              onkeydown="if(event.key==='Enter')this.blur()">
          </span>` : '';

        document.getElementById('tplModalBody').innerHTML = `
          <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
            <span class="text-muted small me-1">Tier:</span>
            ${tierTabs}
            <button class="btn btn-sm btn-ghost-primary" onclick="addTplSet()" title="Add complexity tier">
              <i class="ti ti-plus"></i> Tier
            </button>
            ${tierRate}
            <span class="ms-auto d-flex gap-1">${tierControls}</span>
          </div>
          <p class="text-muted small mb-2">
            Each tier is an independent step list — pick it when creating an elevation, or bump an
            elevation up later. “Blocks next” gates the following stage until this one is done.
            Give steps the same <strong>Phase</strong> to let them run in parallel (any order, or at
            once); a later phase waits for every “Blocks next” step in earlier phases. Blank phase =
            run in list order. Set <strong>Min / joint</strong> per step, or leave the steps blank and
            set one <strong>Tier min / joint</strong> for the whole list. Saves on blur.
          </p>
          <div class="table-responsive">
            <table class="table table-sm table-vcenter align-middle mb-2">
              <thead>
                <tr>
                  <th style="width:52px"></th>
                  <th style="width:36px">#</th>
                  <th style="width:74px" title="Steps sharing a phase run in parallel">Phase</th>
                  <th>Stage Name</th>
                  <th>Description</th>
                  <th style="width:70px" class="text-center">Blocks next</th>
                  <th style="width:92px" title="Minutes of labour per joint for this step">Min / joint</th>
                  <th>Default Assignee</th>
                  <th style="width:48px"></th>
                </tr>
              </thead>
              <tbody>${rows || '<tr><td colspan="9" class="text-muted text-center py-3">No stages in this tier yet. Add one below.</td></tr>'}</tbody>
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
              <div style="width:104px">
                <label class="form-label mb-1 small">Min / joint</label>
                <input type="number" min="0" step="0.25" class="form-control form-control-sm" id="tpl-new-mpj" placeholder="—">
              </div>
              <button class="btn btn-primary btn-sm" onclick="addTpl()" ${set ? '' : 'disabled'}>
                <i class="ti ti-plus me-1"></i>Add Stage
              </button>
            </div>
          </div>`;
      }

      function tplSelectSet(setId) {
        tplActiveSetId = setId;
        renderTplModal();
      }

      async function saveTplField(id, field, value) {
        const payload = { [field]: (typeof value === 'boolean') ? value : (value || null) };
        try {
          await fetch(`/api/v1/stage-templates/${id}`, {
            method: 'PATCH',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': adminCsrfToken(),
            },
            body: JSON.stringify(payload),
          });
        } catch (e) { console.error(e); fabToast('Failed to save.', 'error'); }
      }

      // Tier-level field (currently just the fallback minutes/joint rate).
      async function saveTierField(setId, field, value) {
        const set = tplSets.find(s => s.id === setId);
        if (set) set[field] = value === '' ? null : parseFloat(value);
        try {
          await fetch(`/api/v1/stage-template-sets/${setId}`, {
            method: 'PATCH',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': adminCsrfToken(),
            },
            body: JSON.stringify({ [field]: value === '' ? null : Math.max(0, parseFloat(value) || 0) }),
          });
        } catch (e) { console.error(e); fabToast('Failed to save.', 'error'); }
      }

      async function moveTpl(id, direction) {
        try {
          const templates = (tplActiveSet()?.stage_templates || []);
          const idx = templates.findIndex(t => t.id === id);
          if (idx < 0) return;

          const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
          if (swapIdx < 0 || swapIdx >= templates.length) return;

          const a = templates[idx];
          const b = templates[swapIdx];
          const tplHeaders = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': adminCsrfToken() };
          await Promise.all([
            fetch(`/api/v1/stage-templates/${a.id}`, { method: 'PATCH', credentials: 'include', headers: tplHeaders, body: JSON.stringify({ sort_order: b.sort_order }) }),
            fetch(`/api/v1/stage-templates/${b.id}`, { method: 'PATCH', credentials: 'include', headers: tplHeaders, body: JSON.stringify({ sort_order: a.sort_order }) }),
          ]);
          await reloadTplModal();
        } catch (e) { console.error(e); fabToast('Failed to reorder.', 'error'); }
      }

      async function addTpl() {
        const name = document.getElementById('tpl-new-name').value.trim();
        const desc = document.getElementById('tpl-new-desc').value.trim();
        const mpj  = document.getElementById('tpl-new-mpj').value;
        if (!name) { fabToast('Stage name is required.', 'info'); return; }
        if (!tplActiveSetId) { fabToast('Add a tier first.', 'info'); return; }
        try {
          await fetch('/api/v1/stage-templates', {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': adminCsrfToken(),
            },
            body: JSON.stringify({
              elevation_type_id: tplCurrentTypeId,
              template_set_id: tplActiveSetId,
              name,
              description: desc || null,
              minutes_per_joint: mpj === '' ? null : Math.max(0, parseFloat(mpj) || 0),
            }),
          });
          await reloadTplModal();
        } catch (e) { console.error(e); fabToast('Failed to add stage.', 'error'); }
      }

      async function deleteTpl(id) {
        const ok = await fabConfirm({
          title: 'Delete Stage Template',
          message: 'Delete this stage template? Existing work order stages are not affected.',
          confirmLabel: 'Delete',
          confirmClass: 'btn-danger',
        });
        if (!ok) return;
        try {
          await fetch(`/api/v1/stage-templates/${id}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: { 'X-CSRF-TOKEN': adminCsrfToken() },
          });
          await reloadTplModal();
        } catch (e) { console.error(e); fabToast('Failed to delete stage.', 'error'); }
      }

      // ── Complexity tiers ─────────────────────────────────────────────────
      const tplSetHeaders = () => ({ 'Content-Type': 'application/json', 'X-CSRF-TOKEN': adminCsrfToken() });

      async function addTplSet() {
        const name = (prompt('New tier name (e.g. "Advanced"):') || '').trim();
        if (!name) return;
        try {
          const r = await fetch('/api/v1/stage-template-sets', {
            method: 'POST', credentials: 'include', headers: tplSetHeaders(),
            body: JSON.stringify({ elevation_type_id: tplCurrentTypeId, name }),
          });
          const data = await r.json().catch(() => ({}));
          if (!r.ok) { fabToast(data.message || 'Failed to add tier.', 'error'); return; }
          tplActiveSetId = data.id;
          await reloadTplModal();
        } catch (e) { console.error(e); fabToast('Failed to add tier.', 'error'); }
      }

      async function renameTplSet(setId) {
        const current = tplSets.find(s => s.id === setId);
        const name = (prompt('Rename tier:', current?.name || '') || '').trim();
        if (!name || name === current?.name) return;
        try {
          const r = await fetch(`/api/v1/stage-template-sets/${setId}`, {
            method: 'PATCH', credentials: 'include', headers: tplSetHeaders(),
            body: JSON.stringify({ name }),
          });
          if (!r.ok) { const d = await r.json().catch(() => ({})); fabToast(d.message || 'Failed to rename.', 'error'); return; }
          await reloadTplModal();
        } catch (e) { console.error(e); fabToast('Failed to rename tier.', 'error'); }
      }

      async function setDefaultTplSet(setId) {
        try {
          await fetch(`/api/v1/stage-template-sets/${setId}`, {
            method: 'PATCH', credentials: 'include', headers: tplSetHeaders(),
            body: JSON.stringify({ is_default: true }),
          });
          await reloadTplModal();
        } catch (e) { console.error(e); fabToast('Failed to set default.', 'error'); }
      }

      async function deleteTplSet(setId) {
        const set = tplSets.find(s => s.id === setId);
        const hasSteps = (set?.stage_templates || []).length > 0;
        const ok = await fabConfirm({
          title: 'Delete Tier',
          message: hasSteps
            ? `“${set.name}” has ${set.stage_templates.length} step(s). They will be moved to another tier. Existing work order stages are not affected. Continue?`
            : `Delete the “${set?.name}” tier?`,
          confirmLabel: 'Delete',
          confirmClass: 'btn-danger',
        });
        if (!ok) return;
        try {
          const r = await fetch(`/api/v1/stage-template-sets/${setId}?force=1`, {
            method: 'DELETE', credentials: 'include', headers: tplSetHeaders(),
          });
          if (!r.ok) { const d = await r.json().catch(() => ({})); fabToast(d.message || 'Failed to delete tier.', 'error'); return; }
          if (tplActiveSetId === setId) tplActiveSetId = null;
          await reloadTplModal();
        } catch (e) { console.error(e); fabToast('Failed to delete tier.', 'error'); }
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

      // ============================================================
      // Company Locations (System Settings tab)
      // ============================================================
      let companyLocations = [];
      const clEsc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
      ));

      async function loadCompanyLocations() {
        const body = document.getElementById('companyLocationsBody');
        if (!body) return;
        try {
          const res = await authenticatedFetch('/company-locations');
          companyLocations = res.data || res || [];
          if (!companyLocations.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-3">No locations yet.</td></tr>';
            return;
          }
          body.innerHTML = companyLocations.map(loc => {
            const cityLine = [loc.city, [loc.state, loc.zip].filter(Boolean).join(' ')].filter(Boolean).join(', ');
            const addr = [loc.address_line1, loc.address_line2, cityLine].filter(Boolean).join(', ') || '—';
            const phone = [loc.phone ? 'P: ' + clEsc(loc.phone) : '', loc.fax ? 'F: ' + clEsc(loc.fax) : '']
              .filter(Boolean).join('<br>') || '—';
            return `
              <tr>
                <td class="fw-bold">${clEsc(loc.name)}</td>
                <td class="text-muted">${clEsc(addr)}</td>
                <td class="text-muted">${phone}</td>
                <td class="text-center">${loc.is_primary
                  ? '<span class="badge bg-green-lt">Primary</span>'
                  : `<button class="btn btn-sm btn-ghost-secondary" onclick="makeCompanyLocationPrimary(${loc.id})">Make primary</button>`}</td>
                <td>
                  <div class="btn-list flex-nowrap">
                    <button class="btn btn-sm btn-ghost-primary" onclick="openCompanyLocationModal(${loc.id})"><i class="ti ti-edit"></i></button>
                    <button class="btn btn-sm btn-ghost-danger" onclick="deleteCompanyLocation(${loc.id})"><i class="ti ti-trash"></i></button>
                  </div>
                </td>
              </tr>`;
          }).join('');
        } catch (e) {
          console.error(e);
          body.innerHTML = '<tr><td colspan="5" class="text-danger text-center py-3">Failed to load locations.</td></tr>';
        }
      }

      function openCompanyLocationModal(id = null) {
        const loc = id ? companyLocations.find(l => l.id === id) : null;
        document.getElementById('companyLocationModalTitle').textContent = loc ? 'Edit Company Location' : 'Add Company Location';
        document.getElementById('clId').value = loc?.id || '';
        document.getElementById('clName').value = loc?.name || '';
        document.getElementById('clIsPrimary').checked = !!loc?.is_primary;
        document.getElementById('clAddr1').value = loc?.address_line1 || '';
        document.getElementById('clAddr2').value = loc?.address_line2 || '';
        document.getElementById('clCity').value = loc?.city || '';
        document.getElementById('clState').value = loc?.state || '';
        document.getElementById('clZip').value = loc?.zip || '';
        document.getElementById('clCountry').value = loc?.country || 'USA';
        document.getElementById('clPhone').value = loc?.phone || '';
        document.getElementById('clFax').value = loc?.fax || '';
        document.getElementById('clEmail').value = loc?.email || '';
        document.getElementById('clNotes').value = loc?.notes || '';
        showModal(document.getElementById('companyLocationModal'));
      }

      async function saveCompanyLocation() {
        const id = document.getElementById('clId').value;
        const payload = {
          name: document.getElementById('clName').value.trim(),
          is_primary: document.getElementById('clIsPrimary').checked,
          address_line1: document.getElementById('clAddr1').value.trim() || null,
          address_line2: document.getElementById('clAddr2').value.trim() || null,
          city: document.getElementById('clCity').value.trim() || null,
          state: document.getElementById('clState').value.trim() || null,
          zip: document.getElementById('clZip').value.trim() || null,
          country: document.getElementById('clCountry').value.trim() || null,
          phone: document.getElementById('clPhone').value.trim() || null,
          fax: document.getElementById('clFax').value.trim() || null,
          email: document.getElementById('clEmail').value.trim() || null,
          notes: document.getElementById('clNotes').value.trim() || null,
        };
        if (!payload.name) { showNotification('Location name is required', 'danger'); return; }
        try {
          await authenticatedFetch(id ? `/company-locations/${id}` : '/company-locations', {
            method: id ? 'PATCH' : 'POST',
            body: JSON.stringify(payload),
          });
          hideModal(document.getElementById('companyLocationModal'));
          showNotification('Location saved', 'success');
          loadCompanyLocations();
        } catch (e) {
          showNotification(e.message || 'Failed to save location', 'danger');
        }
      }

      async function makeCompanyLocationPrimary(id) {
        try {
          await authenticatedFetch(`/company-locations/${id}`, {
            method: 'PATCH',
            body: JSON.stringify({ name: companyLocations.find(l => l.id === id)?.name, is_primary: true }),
          });
          showNotification('Primary location updated', 'success');
          loadCompanyLocations();
        } catch (e) {
          showNotification(e.message || 'Failed to update', 'danger');
        }
      }

      async function deleteCompanyLocation(id) {
        if (!confirm('Delete this company location?')) return;
        try {
          await authenticatedFetch(`/company-locations/${id}`, { method: 'DELETE' });
          showNotification('Location deleted', 'success');
          loadCompanyLocations();
        } catch (e) {
          showNotification(e.message || 'Failed to delete location', 'danger');
        }
      }

      // ============================================================
      // Company Branding (logo)
      // ============================================================
      async function loadCompanySettings() {
        try {
          const res = await authenticatedFetch('/company-settings');
          renderCompanyLogo(res.logo_url || null);
        } catch (e) {
          console.error(e);
        }
      }

      function renderCompanyLogo(url) {
        const preview = document.getElementById('companyLogoPreview');
        const removeBtn = document.getElementById('removeCompanyLogoBtn');
        if (!preview) return;
        if (url) {
          preview.innerHTML = `<img src="${url}?t=${Date.now()}" alt="Company logo" style="max-height:96px; max-width:100%;">`;
          removeBtn.style.display = '';
        } else {
          preview.innerHTML = '<span class="text-muted">No logo uploaded</span>';
          removeBtn.style.display = 'none';
        }
      }

      async function uploadCompanyLogo() {
        const input = document.getElementById('companyLogoInput');
        const file = input.files[0];
        if (!file) { showNotification('Choose an image first', 'warning'); return; }

        const fd = new FormData();
        fd.append('logo', file);
        try {
          const res = await authenticatedUpload('/company-settings/logo', fd);
          const json = await res.json();
          if (!res.ok) throw new Error(json.message || `HTTP ${res.status}`);
          input.value = '';
          renderCompanyLogo(json.company_setting?.logo_url || null);
          showNotification('Logo updated', 'success');
        } catch (e) {
          showNotification(e.message || 'Failed to upload logo', 'danger');
        }
      }

      async function removeCompanyLogo() {
        if (!confirm('Remove the company logo?')) return;
        try {
          await authenticatedFetch('/company-settings/logo', { method: 'DELETE' });
          renderCompanyLogo(null);
          showNotification('Logo removed', 'success');
        } catch (e) {
          showNotification(e.message || 'Failed to remove logo', 'danger');
        }
      }

      document.addEventListener('DOMContentLoaded', () => {
        const tab = document.querySelector('a[href="#tab-settings"]');
        if (tab) tab.addEventListener('shown.bs.tab', () => {
          loadCompanyLocations();
          loadCompanySettings();
        }, { once: false });

        // Use shown.bs.tab (not onclick) so this also fires when the tab is
        // shown programmatically via the deep-link support below — a real
        // click fires onclick, but bootstrap.Tab(...).show() does not.
        const cfgTab = document.querySelector('a[href="#tab-configurator-catalog"]');
        if (cfgTab) cfgTab.addEventListener('shown.bs.tab', () => {
          cfgLoadTree();
        }, { once: false });

        // Deep link support, e.g. /admin#tab-configurator-catalog
        if (location.hash && document.querySelector(`a[href="${location.hash}"]`)) {
          try {
            new bootstrap.Tab(document.querySelector(`a[href="${location.hash}"]`)).show();
          } catch (e) { /* bootstrap not ready yet — tab still reachable by click */ }
        }
      });

      // ==================== Configurator: Frame Catalog ====================
      let cfgTree = [];
      let cfgProducts = [];
      let cfgSelectedSystemId = null;
      let cfgSelectedSeriesId = null;
      let cfgSelectedProfileId = null;
      let cfgSelectedComponentId = null;

      function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

      async function cfgLoadProducts() {
        if (cfgProducts.length) return cfgProducts;
        try {
          const data = await authenticatedFetch('/products?per_page=1000');
          cfgProducts = data.data || data.products || data || [];
        } catch (e) { cfgProducts = []; }
        return cfgProducts;
      }

      function cfgProductOptions(selectedId) {
        return cfgProducts.map(p =>
          `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${esc(p.part_number || p.sku)} — ${esc(p.description || '')}</option>`
        ).join('');
      }

      async function cfgLoadTree() {
        const data = await authenticatedFetch('/configurator/catalog/tree');
        cfgTree = data.frame_systems || [];
        cfgRenderSystems();
        cfgRenderSeries();
        cfgRenderProfiles();
        cfgRenderComponents();
        cfgRenderFasteners();
      }

      function cfgFindSystem(id) { return cfgTree.find(s => s.id == id); }
      function cfgFindSeries(id) {
        for (const sys of cfgTree) { const s = (sys.series || []).find(x => x.id == id); if (s) return s; }
        return null;
      }
      function cfgFindProfile(id) {
        for (const sys of cfgTree) for (const ser of (sys.series || [])) {
          const p = (ser.profiles || []).find(x => x.id == id); if (p) return p;
        }
        return null;
      }
      function cfgFindComponent(id) {
        for (const sys of cfgTree) for (const ser of (sys.series || [])) for (const p of (ser.profiles || [])) {
          const c = (p.components || []).find(x => x.id == id); if (c) return c;
        }
        return null;
      }

      function cfgRenderSystems() {
        const tbody = document.getElementById('cfg-systems-tbody');
        tbody.innerHTML = cfgTree.map(s => `
          <tr class="${s.id == cfgSelectedSystemId ? 'table-active' : ''}" style="cursor:pointer" onclick="cfgSelectSystem(${s.id})">
            <td>${esc(s.name)}</td><td><span class="badge bg-blue-lt">${esc(s.code)}</span></td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-icon" onclick="event.stopPropagation(); cfgOpenSystemModal(${s.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
              <button type="button" class="btn btn-sm btn-icon text-danger" onclick="event.stopPropagation(); cfgDeleteSystem(${s.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
            </td>
          </tr>`).join('') || '<tr><td colspan="3" class="text-muted">No frame systems yet.</td></tr>';
        applyActionPermissions();
      }

      function cfgSelectSystem(id) {
        cfgSelectedSystemId = id; cfgSelectedSeriesId = null; cfgSelectedProfileId = null; cfgSelectedComponentId = null;
        cfgRenderSystems(); cfgRenderSeries(); cfgRenderProfiles(); cfgRenderComponents(); cfgRenderFasteners();
        document.getElementById('cfg-add-series-btn').disabled = false;
      }

      function cfgRenderSeries() {
        const tbody = document.getElementById('cfg-series-tbody');
        const empty = document.getElementById('cfg-series-empty');
        const scope = document.getElementById('cfg-series-scope');
        const sys = cfgFindSystem(cfgSelectedSystemId);
        scope.textContent = sys ? `— ${sys.name}` : '';
        const series = sys ? (sys.series || []) : [];
        empty.style.display = series.length ? 'none' : (sys ? 'block' : 'block');
        empty.textContent = sys ? 'No series yet for this system.' : 'Select a frame system to see its series.';
        tbody.innerHTML = series.map(s => `
          <tr class="${s.id == cfgSelectedSeriesId ? 'table-active' : ''}" style="cursor:pointer" onclick="cfgSelectSeries(${s.id})">
            <td>${esc(s.name)}</td><td><span class="badge bg-azure-lt">${esc(s.code)}</span></td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-icon" onclick="event.stopPropagation(); cfgOpenSeriesModal(${s.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
              <button type="button" class="btn btn-sm btn-icon text-danger" onclick="event.stopPropagation(); cfgDeleteSeries(${s.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
            </td>
          </tr>`).join('');
        applyActionPermissions();
      }

      function cfgSelectSeries(id) {
        cfgSelectedSeriesId = id; cfgSelectedProfileId = null; cfgSelectedComponentId = null;
        cfgRenderSeries(); cfgRenderProfiles(); cfgRenderComponents(); cfgRenderFasteners();
        document.getElementById('cfg-add-profile-btn').disabled = false;
      }

      function cfgFormulaSummary(formula) {
        if (!Array.isArray(formula) || !formula.length) return '<span class="text-muted">—</span>';
        return formula.map(t => {
          const sign = t.sign < 0 ? '−' : '+';
          const label = (t.var || '').startsWith('section:') ? `[${esc(t.var.slice(8))}]` : t.var === 'fixed' ? (t.value ?? 0) : esc(t.var || '');
          return `${sign}${label}`;
        }).join(' ');
      }

      function cfgConditionBadges(p) {
        const colors = { single: 'bg-green-lt', pair: 'bg-green-lt', transom: 'bg-purple-lt', threshold: 'bg-orange-lt', transom_pair: 'bg-purple-lt' };
        if (!p.condition) return '<span class="badge bg-secondary-lt">Always</span>';
        return `<span class="badge ${colors[p.condition] || 'bg-secondary-lt'}">${esc(p.condition.replace('_', ' + '))}</span>`;
      }

      function cfgRenderProfiles() {
        const tbody = document.getElementById('cfg-profiles-tbody');
        const empty = document.getElementById('cfg-profiles-empty');
        const scope = document.getElementById('cfg-profiles-scope');
        const series = cfgFindSeries(cfgSelectedSeriesId);
        scope.textContent = series ? `— ${series.name}` : '';
        const profiles = series ? (series.profiles || []) : [];
        empty.style.display = profiles.length ? 'none' : 'block';
        empty.textContent = series ? 'No profiles yet for this series.' : 'Select a series to see its profiles.';
        tbody.innerHTML = profiles.map(p => `
          <tr class="${p.id == cfgSelectedProfileId ? 'table-active' : ''}" style="cursor:pointer" onclick="cfgSelectProfile(${p.id})">
            <td>${esc(p.role_label)}</td>
            <td>${esc(p.product?.part_number || '')}</td>
            <td class="text-muted small">${cfgFormulaSummary(p.formula)}</td>
            <td>${cfgConditionBadges(p)}</td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-icon" onclick="event.stopPropagation(); cfgOpenProfileModal(${p.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
              <button type="button" class="btn btn-sm btn-icon text-danger" onclick="event.stopPropagation(); cfgDeleteProfile(${p.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
            </td>
          </tr>`).join('');
        applyActionPermissions();
      }

      function cfgSelectProfile(id) {
        cfgSelectedProfileId = id; cfgSelectedComponentId = null;
        cfgRenderProfiles(); cfgRenderComponents(); cfgRenderFasteners();
        document.getElementById('cfg-add-component-btn').disabled = false;
      }

      function cfgRenderComponents() {
        const tbody = document.getElementById('cfg-components-tbody');
        const empty = document.getElementById('cfg-components-empty');
        const scope = document.getElementById('cfg-components-scope');
        const profile = cfgFindProfile(cfgSelectedProfileId);
        scope.textContent = profile ? `— ${profile.role_label}` : '';
        const components = profile ? (profile.components || []) : [];
        empty.style.display = components.length ? 'none' : 'block';
        empty.textContent = profile ? 'No components yet for this profile.' : 'Select a profile to see its components.';
        tbody.innerHTML = components.map(c => `
          <tr class="${c.id == cfgSelectedComponentId ? 'table-active' : ''}" style="cursor:pointer" onclick="cfgSelectComponent(${c.id})">
            <td>${esc(c.label)}</td>
            <td>${esc(c.product?.part_number || '')}</td>
            <td>${c.qty_per} <span class="text-muted small">${esc((c.qty_type || '').replace('_',' '))}</span></td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-icon" onclick="event.stopPropagation(); cfgOpenComponentModal(${c.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
              <button type="button" class="btn btn-sm btn-icon text-danger" onclick="event.stopPropagation(); cfgDeleteComponent(${c.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
            </td>
          </tr>`).join('');
        applyActionPermissions();
      }

      function cfgSelectComponent(id) {
        cfgSelectedComponentId = id;
        cfgRenderComponents(); cfgRenderFasteners();
        document.getElementById('cfg-add-fastener-btn').disabled = false;
      }

      function cfgRenderFasteners() {
        const tbody = document.getElementById('cfg-fasteners-tbody');
        const empty = document.getElementById('cfg-fasteners-empty');
        const scope = document.getElementById('cfg-fasteners-scope');
        const component = cfgFindComponent(cfgSelectedComponentId);
        scope.textContent = component ? `— ${component.label}` : '';
        const fasteners = component ? (component.fasteners || []) : [];
        empty.style.display = fasteners.length ? 'none' : 'block';
        empty.textContent = component ? 'No fasteners yet for this component.' : 'Select a component to see its fasteners.';
        tbody.innerHTML = fasteners.map(f => `
          <tr>
            <td>${esc(f.label)}</td>
            <td>${esc(f.product?.part_number || '')}</td>
            <td>${f.qty_per}</td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-icon" onclick="cfgOpenFastenerModal(${f.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
              <button type="button" class="btn btn-sm btn-icon text-danger" onclick="cfgDeleteFastener(${f.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
            </td>
          </tr>`).join('');
        applyActionPermissions();
      }

      // ---- Frame System CRUD ----
      function cfgOpenSystemModal(id) {
        const s = id ? cfgFindSystem(id) : null;
        document.getElementById('cfg-system-id').value = id || '';
        document.getElementById('cfg-system-name').value = s?.name || '';
        document.getElementById('cfg-system-code').value = s?.code || '';
        document.getElementById('cfg-system-sort').value = s?.sort_order ?? 0;
        showModal(document.getElementById('cfg-system-modal'));
      }
      document.getElementById('cfg-system-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('cfg-system-id').value;
        const payload = {
          name: document.getElementById('cfg-system-name').value,
          code: document.getElementById('cfg-system-code').value,
          sort_order: parseInt(document.getElementById('cfg-system-sort').value || 0, 10),
        };
        try {
          await authenticatedFetch(id ? `/configurator/frame-systems/${id}` : '/configurator/frame-systems', {
            method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
          });
          hideModal(document.getElementById('cfg-system-modal'));
          await cfgLoadTree();
        } catch (err) { showNotification(err.message, 'danger'); }
      });
      async function cfgDeleteSystem(id) {
        if (!confirm('Delete this frame system and everything under it?')) return;
        try { await authenticatedFetch(`/configurator/frame-systems/${id}`, { method: 'DELETE' }); await cfgLoadTree(); }
        catch (err) { showNotification(err.message, 'danger'); }
      }

      // ---- Frame Series CRUD ----
      function cfgOpenSeriesModal(id) {
        const s = id ? cfgFindSeries(id) : null;
        document.getElementById('cfg-series-id').value = id || '';
        document.getElementById('cfg-series-name').value = s?.name || '';
        document.getElementById('cfg-series-code').value = s?.code || '';
        document.getElementById('cfg-series-sort').value = s?.sort_order ?? 0;
        showModal(document.getElementById('cfg-series-modal'));
      }
      document.getElementById('cfg-series-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('cfg-series-id').value;
        const payload = {
          frame_system_id: cfgSelectedSystemId,
          name: document.getElementById('cfg-series-name').value,
          code: document.getElementById('cfg-series-code').value,
          sort_order: parseInt(document.getElementById('cfg-series-sort').value || 0, 10),
        };
        try {
          await authenticatedFetch(id ? `/configurator/frame-series/${id}` : '/configurator/frame-series', {
            method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
          });
          hideModal(document.getElementById('cfg-series-modal'));
          await cfgLoadTree();
        } catch (err) { showNotification(err.message, 'danger'); }
      });
      async function cfgDeleteSeries(id) {
        if (!confirm('Delete this frame series and everything under it?')) return;
        try { await authenticatedFetch(`/configurator/frame-series/${id}`, { method: 'DELETE' }); await cfgLoadTree(); }
        catch (err) { showNotification(err.message, 'danger'); }
      }

      // ---- Frame Profile CRUD ----
      function cfgAddFormulaTerm(term) {
        const wrap = document.getElementById('cfg-formula-terms');
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center mb-2 cfg-formula-row';
        const isSection = (term?.var || '').startsWith('section:');
        const sectionRef = isSection ? term.var.slice(8) : '';
        const otherProfiles = (cfgFindSeries(cfgSelectedSeriesId)?.profiles || [])
          .filter(p => p.id != cfgSelectedProfileId)
          .map(p => `<option value="${esc(p.role_label)}" ${sectionRef === p.role_label ? 'selected' : ''}>${esc(p.role_label)}</option>`).join('');
        const currentVar = term && !isSection ? term.var : (term ? 'section' : 'fixed');
        row.innerHTML = `
          <div class="col-2">
            <select class="form-select form-select-sm cfg-term-sign">
              <option value="1" ${!term || term.sign >= 0 ? 'selected' : ''}>+</option>
              <option value="-1" ${term && term.sign < 0 ? 'selected' : ''}>−</option>
            </select>
          </div>
          <div class="col-3">
            <select class="form-select form-select-sm cfg-term-var" onchange="cfgToggleTermInputs(this)">
              <option value="W" ${currentVar === 'W' ? 'selected' : ''}>Width</option>
              <option value="H" ${currentVar === 'H' ? 'selected' : ''}>Height</option>
              <option value="TH" ${currentVar === 'TH' ? 'selected' : ''}>Total Frame Height</option>
              <option value="fixed" ${currentVar === 'fixed' ? 'selected' : ''}>Fixed</option>
              <option value="if_threshold" ${currentVar === 'if_threshold' ? 'selected' : ''}>If Threshold</option>
              <option value="section" ${currentVar === 'section' ? 'selected' : ''}>Section</option>
            </select>
          </div>
          <div class="col-4">
            <input type="number" step="0.0001" class="form-control form-control-sm cfg-term-value" placeholder="value" value="${term?.value ?? ''}" style="${currentVar === 'fixed' ? '' : 'display:none'}">
            <select class="form-select form-select-sm cfg-term-ref" style="${currentVar === 'section' ? '' : 'display:none'}"><option value="">— role —</option>${otherProfiles}</select>
          </div>
          <div class="col-2">
            <button type="button" class="btn btn-sm btn-icon text-danger" onclick="this.closest('.cfg-formula-row').remove()"><i class="ti ti-x"></i></button>
          </div>`;
        wrap.appendChild(row);
      }
      function cfgToggleTermInputs(sel) {
        const row = sel.closest('.cfg-formula-row');
        row.querySelector('.cfg-term-value').style.display = sel.value === 'fixed' ? '' : 'none';
        row.querySelector('.cfg-term-ref').style.display = sel.value === 'section' ? '' : 'none';
      }
      function cfgCollectFormula() {
        return Array.from(document.querySelectorAll('.cfg-formula-row')).map(row => {
          const varType = row.querySelector('.cfg-term-var').value;
          const term = { sign: parseInt(row.querySelector('.cfg-term-sign').value, 10) };
          if (varType === 'section') term.var = 'section:' + row.querySelector('.cfg-term-ref').value;
          else term.var = varType;
          if (varType === 'fixed') term.value = parseFloat(row.querySelector('.cfg-term-value').value || 0);
          return term;
        });
      }

      async function cfgOpenProfileModal(id) {
        await cfgLoadProducts();
        const p = id ? cfgFindProfile(id) : null;
        document.getElementById('cfg-profile-id').value = id || '';
        document.getElementById('cfg-profile-label').value = p?.role_label || '';
        document.getElementById('cfg-profile-product').innerHTML = cfgProductOptions(p?.product_id);
        document.getElementById('cfg-profile-condition').value = p?.condition || '';
        document.getElementById('cfg-profile-glassthicknesses').value = (p?.glass_thicknesses || []).join(', ');
        document.getElementById('cfg-profile-sectionheight').value = p?.section_height ?? 0;
        document.getElementById('cfg-profile-qtyperopening').value = p?.qty_per_opening ?? 1;
        document.getElementById('cfg-formula-terms').innerHTML = '';
        (p?.formula || []).forEach(t => cfgAddFormulaTerm(t));
        showModal(document.getElementById('cfg-profile-modal'));
      }
      document.getElementById('cfg-profile-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('cfg-profile-id').value;
        const glassRaw = document.getElementById('cfg-profile-glassthicknesses').value.trim();
        const payload = {
          frame_series_id: cfgSelectedSeriesId,
          role_label: document.getElementById('cfg-profile-label').value,
          product_id: document.getElementById('cfg-profile-product').value,
          formula: cfgCollectFormula(),
          condition: document.getElementById('cfg-profile-condition').value || null,
          glass_thicknesses: glassRaw ? glassRaw.split(',').map(s => parseFloat(s.trim())).filter(n => !isNaN(n)) : null,
          section_height: parseFloat(document.getElementById('cfg-profile-sectionheight').value || 0),
          qty_per_opening: parseInt(document.getElementById('cfg-profile-qtyperopening').value || 1, 10),
        };
        try {
          await authenticatedFetch(id ? `/configurator/frame-profiles/${id}` : '/configurator/frame-profiles', {
            method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
          });
          hideModal(document.getElementById('cfg-profile-modal'));
          await cfgLoadTree();
        } catch (err) { showNotification(err.message, 'danger'); }
      });
      async function cfgDeleteProfile(id) {
        if (!confirm('Delete this profile and its components/fasteners?')) return;
        try { await authenticatedFetch(`/configurator/frame-profiles/${id}`, { method: 'DELETE' }); await cfgLoadTree(); }
        catch (err) { showNotification(err.message, 'danger'); }
      }

      // ---- Component CRUD ----
      async function cfgOpenComponentModal(id) {
        await cfgLoadProducts();
        const c = id ? cfgFindComponent(id) : null;
        document.getElementById('cfg-component-id').value = id || '';
        document.getElementById('cfg-component-label').value = c?.label || '';
        document.getElementById('cfg-component-product').innerHTML = cfgProductOptions(c?.product_id);
        document.getElementById('cfg-component-qtytype').value = c?.qty_type || 'per_opening';
        document.getElementById('cfg-component-qtyper').value = c?.qty_per ?? 1;
        showModal(document.getElementById('cfg-component-modal'));
      }
      document.getElementById('cfg-component-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('cfg-component-id').value;
        const payload = {
          frame_profile_id: cfgSelectedProfileId,
          label: document.getElementById('cfg-component-label').value,
          product_id: document.getElementById('cfg-component-product').value,
          qty_type: document.getElementById('cfg-component-qtytype').value,
          qty_per: parseFloat(document.getElementById('cfg-component-qtyper').value || 1),
        };
        try {
          await authenticatedFetch(id ? `/configurator/frame-components/${id}` : '/configurator/frame-components', {
            method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
          });
          hideModal(document.getElementById('cfg-component-modal'));
          await cfgLoadTree();
        } catch (err) { showNotification(err.message, 'danger'); }
      });
      async function cfgDeleteComponent(id) {
        if (!confirm('Delete this component and its fasteners?')) return;
        try { await authenticatedFetch(`/configurator/frame-components/${id}`, { method: 'DELETE' }); await cfgLoadTree(); }
        catch (err) { showNotification(err.message, 'danger'); }
      }

      // ---- Fastener CRUD ----
      async function cfgOpenFastenerModal(id) {
        await cfgLoadProducts();
        let f = null;
        const component = cfgFindComponent(cfgSelectedComponentId);
        if (id) f = (component?.fasteners || []).find(x => x.id == id);
        document.getElementById('cfg-fastener-id').value = id || '';
        document.getElementById('cfg-fastener-label').value = f?.label || '';
        document.getElementById('cfg-fastener-product').innerHTML = cfgProductOptions(f?.product_id);
        document.getElementById('cfg-fastener-qtyper').value = f?.qty_per ?? 1;
        showModal(document.getElementById('cfg-fastener-modal'));
      }
      document.getElementById('cfg-fastener-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('cfg-fastener-id').value;
        const payload = {
          frame_component_id: cfgSelectedComponentId,
          label: document.getElementById('cfg-fastener-label').value,
          product_id: document.getElementById('cfg-fastener-product').value,
          qty_per: parseFloat(document.getElementById('cfg-fastener-qtyper').value || 1),
        };
        try {
          await authenticatedFetch(id ? `/configurator/frame-fasteners/${id}` : '/configurator/frame-fasteners', {
            method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
          });
          hideModal(document.getElementById('cfg-fastener-modal'));
          await cfgLoadTree();
        } catch (err) { showNotification(err.message, 'danger'); }
      });
      async function cfgDeleteFastener(id) {
        if (!confirm('Delete this fastener?')) return;
        try { await authenticatedFetch(`/configurator/frame-fasteners/${id}`, { method: 'DELETE' }); await cfgLoadTree(); }
        catch (err) { showNotification(err.message, 'danger'); }
      }

      // ==================== Configurator: Door Catalog ====================
      // Flat lookup tables (not a hierarchy) — one generic CRUD driver per entity type.
      let dcData = { door_types: [], rails: [], rail_lugs: [], mid_lugs: [], glass_specs: [], setting_block_kits: [], tie_rods: [] };

      // field: [domSuffix, jsonKey, 'text'|'number']
      const DC_ENTITIES = {
        doorType: {
          api: 'door-types', dataKey: 'door_type', listKey: 'door_types',
          fields: [['series', 'series', 'text'], ['stileName', 'stile_name', 'text'], ['stileHeight', 'stile_height', 'number'],
                   ['bevPn', 'bev_pn', 'text'], ['rabPn', 'rab_pn', 'text'], ['cpPn', 'cp_pn', 'text'], ['astPn', 'ast_pn', 'text'], ['inactPn', 'inact_pn', 'text']],
        },
        rail: {
          api: 'rails', dataKey: 'rail', listKey: 'rails',
          fields: [['railType', 'rail_type', 'text'], ['label', 'label', 'text'], ['valueIn', 'value_in', 'number'],
                   ['stdPn', 'std_pn', 'text'], ['thermalPn', 'thermal_pn', 'text'], ['monPn', 'mon_pn', 'text'],
                   ['stackedStdPn', 'stacked_std_pn', 'text'], ['stackedThermalPn', 'stacked_thermal_pn', 'text'], ['stackedMonPn', 'stacked_mon_pn', 'text']],
        },
        railLug: {
          api: 'rail-lugs', dataKey: 'rail_lug', listKey: 'rail_lugs',
          fields: [['railPn', 'rail_pn', 'text'], ['lugPn', 'lug_pn', 'text']],
        },
        midLug: {
          api: 'mid-lugs', dataKey: 'mid_lug', listKey: 'mid_lugs',
          fields: [['railPn', 'rail_pn', 'text'], ['lugPn', 'lug_pn', 'text'], ['f1Pn', 'f1_pn', 'text'], ['f1Qty', 'f1_qty', 'number'], ['f2Pn', 'f2_pn', 'text'], ['f2Qty', 'f2_qty', 'number']],
        },
        glassSpec: {
          api: 'glass-specs', dataKey: 'glass_spec', listKey: 'glass_specs',
          fields: [['thickness', 'thickness', 'text'], ['stopPn', 'stop_pn', 'text'], ['gasketPn', 'gasket_pn', 'text'], ['gasket2Pn', 'gasket2_pn', 'text'], ['qtyFactor', 'gasket_qty_factor', 'number'], ['stopHeight', 'stop_height', 'number']],
        },
        sbk: {
          api: 'setting-block-kits', dataKey: 'setting_block_kit', listKey: 'setting_block_kits',
          fields: [['series', 'series', 'text'], ['glassThickness', 'glass_thickness', 'text'], ['kit1Pn', 'kit1_pn', 'text'], ['kit2Pn', 'kit2_pn', 'text']],
        },
        tieRod: {
          api: 'tie-rods', dataKey: 'tie_rod', listKey: 'tie_rods',
          fields: [['series', 'series', 'text'], ['pn', 'pn', 'text'], ['minLen', 'min_len', 'number'], ['maxLen', 'max_len', 'number']],
        },
      };

      async function dcLoadAll() {
        const data = await authenticatedFetch('/configurator/door-catalog');
        dcData = data;
        Object.keys(DC_ENTITIES).forEach(dcRenderTable);
      }

      function dcFind(type, id) {
        const list = dcData[DC_ENTITIES[type].listKey] || [];
        return list.find(r => r.id == id) || null;
      }

      function dcRenderTable(type) {
        const list = dcData[DC_ENTITIES[type].listKey] || [];
        const tbody = document.getElementById(`dc-${type}-tbody`);
        if (!tbody) return;
        const renderers = {
          doorType: r => `<td>${esc(r.series)}</td><td>${esc(r.stile_name)}</td><td>${r.stile_height}</td><td>${esc(r.bev_pn||'')}</td><td>${esc(r.rab_pn||'')}</td><td>${esc(r.cp_pn||'')}</td><td>${esc(r.ast_pn||'')}</td><td>${esc(r.inact_pn||'')}</td>`,
          rail: r => `<td><span class="badge bg-blue-lt">${esc(r.rail_type)}</span></td><td>${esc(r.label)}</td><td>${r.value_in}"</td><td>${esc(r.std_pn||'')}</td><td>${esc(r.thermal_pn||'')}</td><td>${esc(r.mon_pn||'')}</td><td class="small text-muted">${[r.stacked_std_pn,r.stacked_thermal_pn,r.stacked_mon_pn].filter(Boolean).join(', ')}</td>`,
          railLug: r => `<td>${esc(r.rail_pn)}</td><td>${esc(r.lug_pn)}</td>`,
          midLug: r => `<td>${esc(r.rail_pn)}</td><td>${esc(r.lug_pn)}</td><td>${esc(r.f1_pn||'')} ${r.f1_pn?`(${r.f1_qty})`:''}</td><td>${esc(r.f2_pn||'')} ${r.f2_pn?`(${r.f2_qty})`:''}</td>`,
          glassSpec: r => `<td>${esc(r.thickness)}</td><td>${esc(r.stop_pn||'')}</td><td>${esc(r.gasket_pn||'')}</td><td>${esc(r.gasket2_pn||'')}</td><td>${r.gasket_qty_factor ?? ''}</td><td>${r.stop_height ?? ''}</td>`,
          sbk: r => `<td>${esc(r.series)}</td><td>${esc(r.glass_thickness)}</td><td>${esc(r.kit1_pn||'')}</td><td>${esc(r.kit2_pn||'')}</td>`,
          tieRod: r => `<td>${esc(r.series||'')}</td><td>${esc(r.pn)}</td><td>${r.min_len ?? ''}</td><td>${r.max_len ?? ''}</td>`,
        };
        tbody.innerHTML = list.map(r => `
          <tr>
            ${renderers[type](r)}
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-icon" onclick="dcOpenModal('${type}', ${r.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
              <button type="button" class="btn btn-sm btn-icon text-danger" onclick="dcDelete('${type}', ${r.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
            </td>
          </tr>`).join('') || `<tr><td colspan="9" class="text-muted">None yet.</td></tr>`;
        applyActionPermissions();
      }

      function dcOpenModal(type, id) {
        const entity = DC_ENTITIES[type];
        const row = id ? dcFind(type, id) : null;
        document.getElementById(`dc-${type}-id`).value = id || '';
        entity.fields.forEach(([dom, key, kind]) => {
          const el = document.getElementById(`dc-${type}-${dom}`);
          if (!el) return;
          el.value = row ? (row[key] ?? '') : (kind === 'number' ? (dom.endsWith('Qty') ? 0 : '') : (el.tagName === 'SELECT' ? el.value : ''));
        });
        showModal(document.getElementById(`dc-${type}-modal`));
      }

      async function dcDelete(type, id) {
        if (!confirm('Delete this entry?')) return;
        try {
          await authenticatedFetch(`/configurator/${DC_ENTITIES[type].api}/${id}`, { method: 'DELETE' });
          await dcLoadAll();
        } catch (err) { showNotification(err.message, 'danger'); }
      }

      Object.keys(DC_ENTITIES).forEach(type => {
        const form = document.getElementById(`dc-${type}-form`);
        if (!form) return;
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const entity = DC_ENTITIES[type];
          const id = document.getElementById(`dc-${type}-id`).value;
          const payload = {};
          entity.fields.forEach(([dom, key, kind]) => {
            const el = document.getElementById(`dc-${type}-${dom}`);
            if (!el) return;
            const raw = el.value;
            payload[key] = kind === 'number' ? (raw === '' ? null : parseFloat(raw)) : (raw || null);
          });
          try {
            await authenticatedFetch(id ? `/configurator/${entity.api}/${id}` : `/configurator/${entity.api}`, {
              method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
            });
            hideModal(document.getElementById(`dc-${type}-modal`));
            await dcLoadAll();
          } catch (err) { showNotification(err.message, 'danger'); }
        });
      });

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
