{{-- Admin section: Fab users (pane markup + modals + script) --}}
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

@push('scripts')
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


<script>
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

</script>
@endpush
