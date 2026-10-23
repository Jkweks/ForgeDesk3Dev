{{-- Admin section: Elevation types, job steps and stage templates (pane markup + modals + script) --}}
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

                      <!-- Job Steps -->
                      <div class="mt-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                          <div>
                            <h3 class="mb-1">Job Steps</h3>
                            <p class="text-muted mb-0">Work-order-level steps, in the order they are done. New work orders start <strong>Pending</strong> with a copy of these and become <strong>Active</strong> once every step is complete. Changes apply to new work orders only.</p>
                          </div>
                          <button class="btn btn-primary" onclick="addJobStepTemplate()">
                            <i class="ti ti-plus me-1"></i>Add Step
                          </button>
                        </div>
                        <div class="table-responsive">
                          <table class="table table-vcenter card-table">
                            <thead><tr><th style="width:90px">Order</th><th>Name</th><th style="width:90px">Active</th><th class="w-1">Actions</th></tr></thead>
                            <tbody id="job-step-tpl-tbody"><tr><td colspan="4" class="text-muted text-center py-3">Loading…</td></tr></tbody>
                          </table>
                        </div>
                      </div>
                    </div><!-- /tab-elevation-types -->


@push('scripts')
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


<script>
      // ============================================================
      // Job Steps admin (shares the Elevation Types tab)
      // ============================================================
      let jobStepTemplates = [];

      function jobStepHeaders() {
        return { 'X-CSRF-TOKEN': adminCsrfToken(), 'Content-Type': 'application/json', 'Accept': 'application/json' };
      }

      async function loadJobStepTemplates() {
        try {
          const r = await fetch('/api/v1/job-step-templates', { credentials: 'include', headers: jobStepHeaders() });
          jobStepTemplates = (await r.json()).job_step_templates || [];
          renderJobStepTemplates();
        } catch (e) { console.error(e); }
      }

      function renderJobStepTemplates() {
        const tbody = document.getElementById('job-step-tpl-tbody');
        if (!jobStepTemplates.length) {
          tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-3">No job steps defined — new work orders start Active.</td></tr>';
          return;
        }
        const last = jobStepTemplates.length - 1;
        tbody.innerHTML = jobStepTemplates.map((t, i) => `
          <tr class="${t.active ? '' : 'text-muted'}">
            <td style="white-space:nowrap">
              <button class="btn btn-sm btn-ghost-secondary p-0 px-1" onclick="moveJobStepTemplate(${t.id}, -1)" ${i === 0 ? 'disabled' : ''}><i class="ti ti-chevron-up"></i></button>
              <button class="btn btn-sm btn-ghost-secondary p-0 px-1" onclick="moveJobStepTemplate(${t.id}, 1)" ${i === last ? 'disabled' : ''}><i class="ti ti-chevron-down"></i></button>
              <span class="text-muted small ms-1">${i + 1}</span>
            </td>
            <td>${escAdmin(t.name)}</td>
            <td><label class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" ${t.active ? 'checked' : ''} onchange="toggleJobStepTemplate(${t.id}, this.checked)"></label></td>
            <td style="white-space:nowrap">
              <button class="btn btn-sm btn-ghost-secondary" onclick="renameJobStepTemplate(${t.id})"><i class="ti ti-pencil"></i></button>
              <button class="btn btn-sm btn-ghost-danger" onclick="deleteJobStepTemplate(${t.id})"><i class="ti ti-trash"></i></button>
            </td>
          </tr>`).join('');
      }

      async function jobStepRequest(url, method, body) {
        const r = await fetch(url, { method, credentials: 'include', headers: jobStepHeaders(), body: body ? JSON.stringify(body) : undefined });
        if (!r.ok) throw new Error((await r.json().catch(() => ({}))).message || 'Request failed');
        return r.json();
      }

      async function addJobStepTemplate() {
        const name = (prompt('Name of the new job step:') || '').trim();
        if (!name) return;
        try { await jobStepRequest('/api/v1/job-step-templates', 'POST', { name }); await loadJobStepTemplates(); }
        catch (e) { fabToast(e.message, 'error'); }
      }

      async function renameJobStepTemplate(id) {
        const t = jobStepTemplates.find(x => x.id === id);
        const name = (prompt('Rename job step:', t.name) || '').trim();
        if (!name || name === t.name) return;
        try { await jobStepRequest(`/api/v1/job-step-templates/${id}`, 'PATCH', { name }); await loadJobStepTemplates(); }
        catch (e) { fabToast(e.message, 'error'); }
      }

      async function toggleJobStepTemplate(id, active) {
        try { await jobStepRequest(`/api/v1/job-step-templates/${id}`, 'PATCH', { active }); await loadJobStepTemplates(); }
        catch (e) { fabToast(e.message, 'error'); await loadJobStepTemplates(); }
      }

      async function moveJobStepTemplate(id, dir) {
        const ids = jobStepTemplates.map(t => t.id);
        const i = ids.indexOf(id), j = i + dir;
        if (j < 0 || j >= ids.length) return;
        [ids[i], ids[j]] = [ids[j], ids[i]];
        try {
          jobStepTemplates = (await jobStepRequest('/api/v1/job-step-templates/order', 'PUT', { order: ids })).job_step_templates || [];
          renderJobStepTemplates();
        } catch (e) { fabToast(e.message, 'error'); }
      }

      async function deleteJobStepTemplate(id) {
        const ok = await fabConfirm({
          title: 'Delete Job Step',
          message: 'Delete this job step? Existing work orders keep the steps they already have.',
          confirmLabel: 'Delete',
          confirmClass: 'btn-danger',
        });
        if (!ok) return;
        try { await jobStepRequest(`/api/v1/job-step-templates/${id}`, 'DELETE'); await loadJobStepTemplates(); }
        catch (e) { fabToast(e.message, 'error'); }
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
            <td>${t.active ? '<span class="badge bg-success-lt">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>'}</td>
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
          <button class="btn btn-sm btn-ghost-secondary" onclick="duplicateTplSet(${set.id})" title="Duplicate tier">
            <i class="ti ti-copy"></i>
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

      async function duplicateTplSet(setId) {
        const current = tplSets.find(s => s.id === setId);
        const name = (prompt('Name for the duplicated tier:', `${current?.name || ''} (copy)`) || '').trim();
        if (!name) return;
        try {
          const r = await fetch(`/api/v1/stage-template-sets/${setId}/duplicate`, {
            method: 'POST', credentials: 'include', headers: tplSetHeaders(),
            body: JSON.stringify({ name }),
          });
          const data = await r.json().catch(() => ({}));
          if (!r.ok) { fabToast(data.message || 'Failed to duplicate tier.', 'error'); return; }
          tplActiveSetId = data.id;
          await reloadTplModal();
        } catch (e) { console.error(e); fabToast('Failed to duplicate tier.', 'error'); }
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

</script>
@endpush
