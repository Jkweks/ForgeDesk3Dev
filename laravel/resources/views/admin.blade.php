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
                              <div>P, S parts</div>
                            </div>
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
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
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

        users.forEach(user => {
          const statusBadge = user.is_active
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>';

          const roleBadge = {
            admin: '<span class="badge bg-red">Admin</span>',
            manager: '<span class="badge bg-blue">Manager</span>',
            fabricator: '<span class="badge bg-green">Fabricator</span>',
            viewer: '<span class="badge bg-gray">Viewer</span>'
          }[user.role] || '<span class="badge bg-gray">' + user.role + '</span>';

          const lastLogin = user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never';
          const createdAt = user.created_at ? new Date(user.created_at).toLocaleDateString() : '-';

          const row = `
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
          tbody.innerHTML += row;
        });

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

        roles.forEach(role => {
          const systemBadge = role.is_system ? '<span class="badge bg-info ms-2">System</span>' : '';
          const deleteOption = role.is_system
            ? ''
            : `<a class="dropdown-item text-danger" href="#" onclick="deleteRole(${role.id}); return false;" data-permission="roles.delete">
                 <i class="ti ti-trash me-2"></i>Delete
               </a>`;

          const card = `
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
          container.innerHTML += card;
        });

        // Update role dropdowns after loading roles
        populateRoleDropdowns();

        // Apply action permissions to dynamically created buttons
        if (typeof applyActionPermissions === 'function') {
          applyActionPermissions();
        }
      }

      // Populate all role dropdowns with loaded roles
      function populateRoleDropdowns() {
        // Populate filter dropdown
        const filterRole = document.getElementById('filterRole');
        if (filterRole) {
          const currentFilter = filterRole.value;
          filterRole.innerHTML = '<option value="">All Roles</option>';
          roles.forEach(role => {
            filterRole.innerHTML += `<option value="${role.name}">${role.display_name}</option>`;
          });
          filterRole.value = currentFilter; // Restore previous selection
        }

        // Populate add user role dropdown
        const addUserRole = document.getElementById('addUserRole');
        if (addUserRole) {
          addUserRole.innerHTML = '<option value="">Select role...</option>';
          roles.forEach(role => {
            addUserRole.innerHTML += `<option value="${role.name}">${role.display_name}</option>`;
          });
        }

        // Populate edit user role dropdown
        const editUserRole = document.getElementById('editUserRole');
        if (editUserRole) {
          const currentRole = editUserRole.value;
          editUserRole.innerHTML = '';
          roles.forEach(role => {
            editUserRole.innerHTML += `<option value="${role.name}">${role.display_name}</option>`;
          });
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
