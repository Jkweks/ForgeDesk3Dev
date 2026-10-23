{{-- Admin section: System Settings (pane markup + modals + script) --}}
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
                                       style="min-height:120px; background:var(--tblr-bg-surface-secondary);">
                                    <span class="text-muted">No logo uploaded</span>
                                  </div>
                                </div>
                                <div class="col-md-8">
                                  <label class="form-label">Company Logo</label>
                                  <input type="file" class="form-control" id="companyLogoInput" accept="image/png,image/jpeg,image/gif,image/webp">
                                  <small class="form-text">Printed on purchase order PDFs. PNG or JPG, up to 4&nbsp;MB.</small>
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

                      <div class="row mt-3">
                        <div class="col-12">
                          <div class="card">
                            <div class="card-header">
                              <h4 class="card-title mb-0">CutFlow Bridge</h4>
                            </div>
                            <div class="card-body">
                              <p class="text-muted">
                                Connection details for the tiger-bridge service that talks to the TigerStop saw
                                and the cut-station's Zebra printer, and the IP allowlist for the shop-floor
                                tablet allowed to actually move the saw. These used to live in the server's
                                <code>.env</code> file; changes here take effect immediately, no restart needed.
                              </p>
                              <div class="row">
                                <div class="col-md-6 mb-3">
                                  <label class="form-label">Bridge URL</label>
                                  <input type="text" class="form-control" id="cfBridgeUrl" placeholder="http://192.168.1.50:9111">
                                </div>
                                <div class="col-md-6 mb-3">
                                  <label class="form-label">Bridge Token</label>
                                  <input type="password" class="form-control" id="cfBridgeToken" placeholder="Leave blank to keep the current token" autocomplete="new-password">
                                  <small class="form-text" id="cfBridgeTokenHint"></small>
                                </div>
                              </div>
                              <div class="row">
                                <div class="col-md-6 mb-3">
                                  <label class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="cfBridgeFake">
                                    <span class="form-check-label">Fake mode (simulate the bridge — no hardware calls)</span>
                                  </label>
                                </div>
                                <div class="col-md-6 mb-3">
                                  <label class="form-label">Tablet IP Allowlist</label>
                                  <input type="text" class="form-control" id="cfTabletAllowedIps" placeholder="192.168.1.60, 192.168.1.0/24">
                                  <small class="form-text">Comma-separated IPs / IPv4 CIDR ranges. Blank = unrestricted.</small>
                                </div>
                              </div>
                              <button class="btn btn-primary" onclick="saveCutFlowBridgeSettings()">
                                <i class="ti ti-device-floppy me-1"></i>Save CutFlow Bridge Settings
                              </button>
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


@push('scripts')

<script>
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

      // ============================================================
      // CutFlow Bridge settings
      // ============================================================
      async function loadCutFlowBridgeSettings() {
        try {
          const res = await authenticatedFetch('/cutflow-bridge-settings');
          document.getElementById('cfBridgeUrl').value = res.bridge_url || '';
          document.getElementById('cfBridgeFake').checked = !!res.bridge_fake;
          document.getElementById('cfTabletAllowedIps').value = (res.tablet_allowed_ips || []).join(', ');
          document.getElementById('cfBridgeTokenHint').textContent = res.bridge_token_set
            ? 'A token is currently set. Leave blank to keep it.'
            : 'No token is currently set.';
        } catch (e) {
          console.error(e);
        }
      }

      async function saveCutFlowBridgeSettings() {
        const payload = {
          bridge_url: document.getElementById('cfBridgeUrl').value.trim() || null,
          bridge_fake: document.getElementById('cfBridgeFake').checked,
          tablet_allowed_ips: document.getElementById('cfTabletAllowedIps').value.trim() || null,
        };
        const token = document.getElementById('cfBridgeToken').value;
        if (token) payload.bridge_token = token;

        try {
          await authenticatedFetch('/cutflow-bridge-settings', {
            method: 'PUT',
            body: JSON.stringify(payload),
          });
          document.getElementById('cfBridgeToken').value = '';
          showNotification('CutFlow bridge settings saved', 'success');
          loadCutFlowBridgeSettings();
        } catch (e) {
          showNotification(e.message || 'Failed to save CutFlow bridge settings', 'danger');
        }
      }

      // Sidebar: keep URL hash in sync, show section title, stats only for people sections
      document.addEventListener('DOMContentLoaded', () => {
        const tab = document.querySelector('a[href="#tab-settings"]');
        if (tab) tab.addEventListener('shown.bs.tab', () => {
          loadCompanyLocations();
          loadCompanySettings();
          loadCutFlowBridgeSettings();
        }, { once: false });
      });
</script>
@endpush
