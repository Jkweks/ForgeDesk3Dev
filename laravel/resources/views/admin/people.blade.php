{{-- Admin section: Users and Roles (pane markup + modals + script) --}}
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
                            <option value="inactive">Inactive (no login)</option>
                            <option value="disabled">Disabled</option>
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


@push('scripts')
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
              <small class="form-text">The welcome email carries a temporary password the user must change within 7 days. A held invitation gets a fresh temporary password when you finally send it.</small>
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
              <small class="form-text">Uncheck to hold it — set up the profile, roles and permissions first, then send held invitations one by one or all at once from the Users list.</small>
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
                <span class="form-check-label">Active (can sign in)</span>
              </label>
              <div class="form-hint">Unchecked users can't log in, but can still be picked as project manager / superintendent and still get work-order emails.</div>
            </div>
            <div class="mb-3">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" id="editUserDisabled" onchange="if (this.checked) document.getElementById('editUserActive').checked = false">
                <span class="form-check-label">Disabled (no longer with the company)</span>
              </label>
              <div class="form-hint">Removes them from the pickers and stops all emails. Also turns off sign-in.</div>
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
      let users = [];
      let roles = [];
      let permissions = [];

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
          document.getElementById('editUserDisabled').checked = !!user.is_disabled;

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
        const disabled = document.getElementById('editUserDisabled').checked;
        const active = disabled ? false : document.getElementById('editUserActive').checked;

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
            is_active: active,
            is_disabled: disabled
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
              <input class="form-check-input" type="checkbox" value="${escapeHtml(permission.name)}" ${isChecked ? 'checked' : ''}>
              <span class="form-check-label">
                ${escapeHtml(permission.display_name)}
                ${permission.description ? `<br><small class="text-muted">${escapeHtml(permission.description)}</small>` : ''}
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
          admin: '<span class="badge bg-red-lt">Admin</span>',
          manager: '<span class="badge bg-blue-lt">Manager</span>',
          fabricator: '<span class="badge bg-green-lt">Fabricator</span>',
          viewer: '<span class="badge bg-secondary-lt">Viewer</span>'
        };

        tbody.innerHTML = users.map(user => {
          let statusBadge = user.is_disabled
            ? '<span class="badge bg-red-lt" title="No longer with the company — not selectable, no emails">Disabled</span>'
            : (user.is_active
              ? '<span class="badge bg-success-lt">Active</span>'
              : '<span class="badge text-bg-secondary" title="No login; still selectable and emailed">Inactive</span>');

          if (user.invitation_pending) {
            statusBadge += ' <span class="badge bg-azure-lt" title="Account created — welcome email not sent yet">Not invited</span>';
          } else if (user.must_change_password) {
            statusBadge += user.temp_password_expired
              ? ' <span class="badge bg-red-lt" title="Temporary password expired">Invite expired</span>'
              : ' <span class="badge bg-yellow-lt" title="Waiting for the user to set a new password">Pending invite</span>';
          }

          const roleBadge = roleBadges[user.role] || '<span class="badge bg-secondary-lt">' + user.role + '</span>';

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
              <td>${escapeHtml(user.name)}</td>
              <td>${escapeHtml(user.email)}</td>
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
          const systemBadge = role.is_system ? '<span class="badge bg-info-lt ms-2">System</span>' : '';
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
                      <h3 class="card-title mb-1">${escapeHtml(role.display_name)}${systemBadge}</h3>
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
        const roleOptions = roles.map(role => `<option value="${escapeHtml(role.name)}">${escapeHtml(role.display_name)}</option>`).join('');

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
</script>
@endpush
