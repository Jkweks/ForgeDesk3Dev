@extends('layouts.app')

@section('title', 'Configurator Admin - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Configurator</div>
          <h1 class="page-title">Configurator Admin</h1>
          <p class="text-muted">Catalog data used to auto-generate frame, door, and hardware BOMs in the <a href="/config">Frame Builder</a>.</p>
        </div>
      </div>
    </div>
  </div>

  <main class="page-body">
    <div class="container-xl">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" role="tablist">
                <li class="nav-item" role="presentation">
                  <a href="#tab-configurator-catalog" class="nav-link active" data-bs-toggle="tab" aria-selected="true" role="tab">
                    <i class="ti ti-door me-2"></i>Frame Catalog
                  </a>
                </li>
                <li class="nav-item" role="presentation">
                  <a href="#tab-door-catalog" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1">
                    <i class="ti ti-door-enter me-2"></i>Door Catalog
                  </a>
                </li>
                <li class="nav-item" role="presentation">
                  <a href="#tab-hardware-library" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1">
                    <i class="ti ti-tools me-2"></i>Hardware Library
                  </a>
                </li>
              </ul>
            </div>

            <div class="card-body">
              <div class="tab-content">

                <div class="tab-pane active show" id="tab-configurator-catalog" role="tabpanel">
                  <div class="mb-3">
                    <h3 class="mb-1">Frame Catalog</h3>
                    <p class="text-muted mb-0">Frame systems, series, extrusion profiles, and components used to auto-generate a frame BOM in the <a href="/config">Configurator</a>.</p>
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
                        <div class="d-flex align-items-center gap-2 flex-wrap px-3 py-2 border-bottom">
                          <span class="text-muted small text-uppercase">Scenario</span>
                          <div class="btn-group btn-group-sm" id="cfg-cond-filter-bar" role="group"></div>
                          <button type="button" class="btn btn-sm btn-link p-0 ms-1" id="cfg-cond-filter-clear" onclick="cfgClearCondFilter()" style="display:none">Clear</button>
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
                    <p class="text-muted mb-0">Door types, rails, lugs, glass specs, setting block kits, and tie rods used to auto-generate a door BOM in the <a href="/config">Configurator</a>.</p>
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
                            <thead><tr><th>Series</th><th>Stile</th><th>Height</th><th>Std Top Rail</th><th>Std Bot Rail</th><th>Bevel PN</th><th>Rabbet PN</th><th>Center Pivot PN</th><th>Astragal PN</th><th>Inactive PN</th><th class="w-1"></th></tr></thead>
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

                    <!-- Hinge Spacing Standards -->
                    <div class="col-12">
                      <div class="card">
                        <div class="card-header">
                          <h3 class="card-title">Hinge Spacing Standards <span class="text-muted ms-1">(butt hinges)</span></h3>
                          <div class="card-actions">
                            <button class="btn btn-sm btn-primary" onclick="dcOpenModal('hingeSpacingStandard')" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add</button>
                          </div>
                        </div>
                        <div class="table-responsive">
                          <table class="table table-vcenter card-table">
                            <thead><tr><th>Name</th><th>From Door Top</th><th>From Door Bottom / Floor</th><th class="w-1"></th></tr></thead>
                            <tbody id="dc-hingeSpacingStandard-tbody"></tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div><!-- /tab-door-catalog -->

                <div class="tab-pane" id="tab-hardware-library" role="tabpanel">
                  @include('configurator.partials.hwlib-admin')
                </div><!-- /tab-hardware-library -->

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
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
        <div class="modal-header"><h5 class="modal-title" id="cfg-series-modal-title">Frame Series</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="cfg-series-id">
          <input type="hidden" id="cfg-series-dup-from">
          <div class="alert alert-info py-2 d-none" id="cfg-series-dup-hint"></div>
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
              <div class="form-text">Fixed cross-section dimension (e.g. jamb depth) — referenced by other profiles' "Section" / "If Threshold" formula terms and by TH when there's no transom.</div>
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
          <div class="form-text">Length = sum of signed terms. "Section" references another profile's section height by role label (e.g. Door Head). "If Threshold" adds the series' Threshold profile's section height only when the opening has a threshold.</div>
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
            <div class="col-md-6 mb-3"><label class="form-label">Standard Top Rail</label><select class="form-select" id="dc-doorType-stdTopRail"></select></div>
            <div class="col-md-6 mb-3"><label class="form-label">Standard Bottom Rail</label><select class="form-select" id="dc-doorType-stdBotRail"></select></div>
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
          <div class="form-text mb-2">Stacked variants (bottom rails only — label should include "stacked", e.g. 12" (stacked))</div>
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

<!-- Door Catalog: Hinge Spacing Standard Modal -->
<div class="modal modal-blur fade" id="dc-hingeSpacingStandard-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="dc-hingeSpacingStandard-form">
        <div class="modal-header"><h5 class="modal-title">Hinge Spacing Standard</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="dc-hingeSpacingStandard-id">
          <div class="mb-3"><label class="form-label">Name</label><input type="text" class="form-control" id="dc-hingeSpacingStandard-name" required></div>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Distance from Door Top</label><input type="number" step="0.0001" class="form-control" id="dc-hingeSpacingStandard-topDistance" required></div>
            <div class="col-md-6 mb-3"><label class="form-label">Top Reference Point</label><input type="text" class="form-control" id="dc-hingeSpacingStandard-topLabel" placeholder="e.g. top of prep, center of prep" required></div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Distance from Bottom Reference</label><input type="number" step="0.0001" class="form-control" id="dc-hingeSpacingStandard-bottomDistance" required></div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Bottom Reference</label>
              <select class="form-select" id="dc-hingeSpacingStandard-bottomReference" required>
                <option value="door_bottom">Bottom of Door</option>
                <option value="floor">Finished Floor</option>
              </select>
            </div>
            <div class="col-md-4 mb-3"><label class="form-label">Bottom Reference Point</label><input type="text" class="form-control" id="dc-hingeSpacingStandard-bottomLabel" placeholder="e.g. bottom of prep, center of prep" required></div>
          </div>
          <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" id="dc-hingeSpacingStandard-notes" rows="2"></textarea></div>
          <div class="text-muted small">Additional hinges beyond the top/bottom pair are spaced evenly between them.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('configurator.partials.hwlib-admin-modals')

<script>
  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // ==================== Configurator: Frame Catalog ====================
  let cfgTree = [];
  let cfgSelectedSystemId = null;
  let cfgSelectedSeriesId = null;
  let cfgSelectedProfileId = null;
  let cfgSelectedComponentId = null;

  // Product picker: type-ahead against /products?search= (server-side) so
  // we never preload the whole catalog. Replaces a <select> with a hidden
  // input carrying the same id (so `.value` reads keep working) plus a text
  // box and result list.
  function cfgProductLabel(p) {
    return `${p.part_number || p.sku || ''}${p.description ? ' — ' + p.description : ''}`;
  }

  function cfgInitProductPicker(selectId) {
    const el = document.getElementById(selectId);
    if (!el || el.dataset.picker) return;
    const wrap = document.createElement('div');
    wrap.className = 'position-relative';
    wrap.innerHTML = `
      <input type="hidden" id="${selectId}">
      <input type="text" class="form-control" autocomplete="off" placeholder="Search part number, SKU or description…" required>
      <div class="list-group position-absolute w-100 shadow" style="z-index:2000;max-height:260px;overflow-y:auto;display:none"></div>`;
    el.replaceWith(wrap);
    const hidden = wrap.children[0], text = wrap.children[1], list = wrap.children[2];
    hidden.dataset.picker = '1';

    let timer = null, seq = 0;
    const validity = () => text.setCustomValidity(hidden.value ? '' : 'Pick a product from the list');
    const close = () => { list.style.display = 'none'; };

    async function run() {
      const q = text.value.trim();
      const mine = ++seq;
      if (q.length < 2) { list.innerHTML = '<div class="list-group-item text-muted small">Type at least 2 characters</div>'; list.style.display = ''; return; }
      let rows = [];
      try {
        const data = await authenticatedFetch(`/products?search=${encodeURIComponent(q)}&per_page=20`);
        rows = data.data || [];
      } catch (e) { /* leave empty */ }
      if (mine !== seq) return;
      list.innerHTML = rows.length
        ? rows.map(p => `<button type="button" class="list-group-item list-group-item-action" data-id="${p.id}" data-label="${esc(cfgProductLabel(p))}">${esc(cfgProductLabel(p))}</button>`).join('')
        : '<div class="list-group-item text-muted small">No matches</div>';
      list.style.display = '';
    }

    text.addEventListener('input', () => {
      hidden.value = ''; validity();
      clearTimeout(timer); timer = setTimeout(run, 250);
    });
    text.addEventListener('focus', () => { if (!hidden.value && text.value.trim().length >= 2) run(); });
    list.addEventListener('mousedown', (e) => {
      const btn = e.target.closest('button[data-id]');
      if (!btn) return;
      e.preventDefault();
      hidden.value = btn.dataset.id; text.value = btn.dataset.label; validity(); close();
    });
    text.addEventListener('blur', close);
    validity();
  }

  // Set the picker to an existing product (edit) or clear it (new). The
  // row's eager-loaded `product` supplies the label; no extra fetch.
  function cfgSetProductPicker(selectId, productId, product) {
    cfgInitProductPicker(selectId);
    const hidden = document.getElementById(selectId);
    const text = hidden.nextElementSibling;
    hidden.value = productId || '';
    text.value = productId && product ? cfgProductLabel(product) : '';
    text.setCustomValidity(hidden.value ? '' : 'Pick a product from the list');
  }

  async function cfgLoadTree() {
    const data = await authenticatedFetch('/config/catalog/tree');
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
          <button type="button" class="btn btn-sm btn-icon" title="Duplicate this series (with its profiles, components and fasteners)" onclick="event.stopPropagation(); cfgOpenDuplicateSeriesModal(${s.id})" data-permission="configurator.catalog.manage"><i class="ti ti-copy"></i></button>
          <button type="button" class="btn btn-sm btn-icon" title="Edit" onclick="event.stopPropagation(); cfgOpenSeriesModal(${s.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
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

  // ---- Scenario (condition) filter — like fab_utils' condition-filter-bar.
  // Profiles with no condition ("Always") show regardless of the filter;
  // profiles with a condition show only when that condition is toggled on.
  // Empty filter set = show everything.
  const CFG_CONDITIONS = [
    ['single', 'Single'], ['pair', 'Pair'], ['transom', 'Transom'],
    ['threshold', 'Threshold'], ['transom_pair', 'Transom + Pair'],
  ];
  let cfgCondFilter = new Set();

  function cfgRenderCondFilterBar() {
    const bar = document.getElementById('cfg-cond-filter-bar');
    if (!bar || bar.dataset.rendered) return;
    bar.dataset.rendered = '1';
    bar.innerHTML = CFG_CONDITIONS.map(([value, label]) =>
      `<button type="button" class="btn btn-outline-secondary" data-cond="${value}" onclick="cfgToggleCondFilter('${value}')">${esc(label)}</button>`
    ).join('');
  }

  function cfgToggleCondFilter(cond) {
    if (cfgCondFilter.has(cond)) cfgCondFilter.delete(cond);
    else cfgCondFilter.add(cond);
    document.querySelectorAll('#cfg-cond-filter-bar [data-cond]').forEach(btn => {
      btn.classList.toggle('btn-primary', cfgCondFilter.has(btn.dataset.cond));
      btn.classList.toggle('btn-outline-secondary', !cfgCondFilter.has(btn.dataset.cond));
    });
    document.getElementById('cfg-cond-filter-clear').style.display = cfgCondFilter.size ? '' : 'none';
    cfgRenderProfiles();
  }

  function cfgClearCondFilter() {
    cfgCondFilter.clear();
    document.querySelectorAll('#cfg-cond-filter-bar [data-cond]').forEach(btn => {
      btn.classList.add('btn-outline-secondary');
      btn.classList.remove('btn-primary');
    });
    document.getElementById('cfg-cond-filter-clear').style.display = 'none';
    cfgRenderProfiles();
  }

  function cfgProfileMatchesFilter(p) {
    if (! cfgCondFilter.size) return true;
    return !p.condition || cfgCondFilter.has(p.condition);
  }

  function cfgRenderProfiles() {
    cfgRenderCondFilterBar();
    const tbody = document.getElementById('cfg-profiles-tbody');
    const empty = document.getElementById('cfg-profiles-empty');
    const scope = document.getElementById('cfg-profiles-scope');
    const series = cfgFindSeries(cfgSelectedSeriesId);
    scope.textContent = series ? `— ${series.name}` : '';
    const allProfiles = series ? (series.profiles || []) : [];
    const profiles = allProfiles.filter(cfgProfileMatchesFilter);
    empty.style.display = profiles.length ? 'none' : 'block';
    empty.textContent = !series ? 'Select a series to see its profiles.'
      : !allProfiles.length ? 'No profiles yet for this series.'
      : 'No profiles match the selected scenario filter.';
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
      await authenticatedFetch(id ? `/config/frame-systems/${id}` : '/config/frame-systems', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      hideModal(document.getElementById('cfg-system-modal'));
      await cfgLoadTree();
    } catch (err) { showNotification(err.message, 'danger'); }
  });
  async function cfgDeleteSystem(id) {
    if (!confirm('Delete this frame system and everything under it?')) return;
    try { await authenticatedFetch(`/config/frame-systems/${id}`, { method: 'DELETE' }); await cfgLoadTree(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ---- Frame Series CRUD ----
  // Duplicate: the same modal, prefilled from the source series; saving copies it (and its whole
  // profile / component / fastener tree) into this frame system under the new name and code.
  function cfgOpenDuplicateSeriesModal(id) {
    const s = cfgFindSeries(id);
    if (!s) return;
    cfgOpenSeriesModal(null);
    document.getElementById('cfg-series-dup-from').value = id;
    document.getElementById('cfg-series-modal-title').textContent = 'Duplicate Frame Series';
    document.getElementById('cfg-series-name').value = `${s.name} (copy)`.slice(0, 100);
    document.getElementById('cfg-series-code').value = `${s.code}-COPY`.slice(0, 30);
    document.getElementById('cfg-series-sort').value = (s.sort_order ?? 0) + 1;
    const hint = document.getElementById('cfg-series-dup-hint');
    const profiles = s.profiles || [];
    const components = profiles.reduce((n, p) => n + (p.components || []).length, 0);
    hint.textContent = `Copies "${s.name}" with its ${profiles.length} profile(s) and ${components} component(s) (and their fasteners) into this frame system. Existing configurations stay on the original series.`;
    hint.classList.remove('d-none');
  }

  function cfgOpenSeriesModal(id) {
    const s = id ? cfgFindSeries(id) : null;
    document.getElementById('cfg-series-dup-from').value = '';
    document.getElementById('cfg-series-dup-hint').classList.add('d-none');
    document.getElementById('cfg-series-modal-title').textContent = 'Frame Series';
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
    const dupFrom = document.getElementById('cfg-series-dup-from').value;
    try {
      if (dupFrom) {
        const res = await authenticatedFetch(`/config/frame-series/${dupFrom}/duplicate`, {
          method: 'POST', body: JSON.stringify({ name: payload.name, code: payload.code, sort_order: payload.sort_order }),
        });
        hideModal(document.getElementById('cfg-series-modal'));
        await cfgLoadTree();
        cfgSelectSeries(res.frame_series.id);
        showNotification(res.message, 'success');
        return;
      }
      await authenticatedFetch(id ? `/config/frame-series/${id}` : '/config/frame-series', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      hideModal(document.getElementById('cfg-series-modal'));
      await cfgLoadTree();
    } catch (err) { showNotification(err.message, 'danger'); }
  });
  async function cfgDeleteSeries(id) {
    if (!confirm('Delete this frame series and everything under it?')) return;
    try { await authenticatedFetch(`/config/frame-series/${id}`, { method: 'DELETE' }); await cfgLoadTree(); }
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
    const p = id ? cfgFindProfile(id) : null;
    document.getElementById('cfg-profile-id').value = id || '';
    document.getElementById('cfg-profile-label').value = p?.role_label || '';
    cfgSetProductPicker('cfg-profile-product', p?.product_id, p?.product);
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
      await authenticatedFetch(id ? `/config/frame-profiles/${id}` : '/config/frame-profiles', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      hideModal(document.getElementById('cfg-profile-modal'));
      await cfgLoadTree();
    } catch (err) { showNotification(err.message, 'danger'); }
  });
  async function cfgDeleteProfile(id) {
    if (!confirm('Delete this profile and its components/fasteners?')) return;
    try { await authenticatedFetch(`/config/frame-profiles/${id}`, { method: 'DELETE' }); await cfgLoadTree(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ---- Component CRUD ----
  async function cfgOpenComponentModal(id) {
    const c = id ? cfgFindComponent(id) : null;
    document.getElementById('cfg-component-id').value = id || '';
    document.getElementById('cfg-component-label').value = c?.label || '';
    cfgSetProductPicker('cfg-component-product', c?.product_id, c?.product);
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
      await authenticatedFetch(id ? `/config/frame-components/${id}` : '/config/frame-components', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      hideModal(document.getElementById('cfg-component-modal'));
      await cfgLoadTree();
    } catch (err) { showNotification(err.message, 'danger'); }
  });
  async function cfgDeleteComponent(id) {
    if (!confirm('Delete this component and its fasteners?')) return;
    try { await authenticatedFetch(`/config/frame-components/${id}`, { method: 'DELETE' }); await cfgLoadTree(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ---- Fastener CRUD ----
  async function cfgOpenFastenerModal(id) {
    let f = null;
    const component = cfgFindComponent(cfgSelectedComponentId);
    if (id) f = (component?.fasteners || []).find(x => x.id == id);
    document.getElementById('cfg-fastener-id').value = id || '';
    document.getElementById('cfg-fastener-label').value = f?.label || '';
    cfgSetProductPicker('cfg-fastener-product', f?.product_id, f?.product);
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
      await authenticatedFetch(id ? `/config/frame-fasteners/${id}` : '/config/frame-fasteners', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      hideModal(document.getElementById('cfg-fastener-modal'));
      await cfgLoadTree();
    } catch (err) { showNotification(err.message, 'danger'); }
  });
  async function cfgDeleteFastener(id) {
    if (!confirm('Delete this fastener?')) return;
    try { await authenticatedFetch(`/config/frame-fasteners/${id}`, { method: 'DELETE' }); await cfgLoadTree(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ==================== Configurator: Door Catalog ====================
  // Flat lookup tables (not a hierarchy) — one generic CRUD driver per entity type.
  let dcData = { door_types: [], rails: [], rail_lugs: [], mid_lugs: [], glass_specs: [], setting_block_kits: [], tie_rods: [], hinge_spacing_standards: [] };

  // field: [domSuffix, jsonKey, 'text'|'number']
  const DC_ENTITIES = {
    doorType: {
      api: 'door-types', dataKey: 'door_type', listKey: 'door_types',
      fields: [['series', 'series', 'text'], ['stileName', 'stile_name', 'text'], ['stileHeight', 'stile_height', 'number'], ['stdTopRail', 'std_top_rail_label', 'text'], ['stdBotRail', 'std_bot_rail_label', 'text'],
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
    hingeSpacingStandard: {
      api: 'hinge-spacing-standards', dataKey: 'hinge_spacing_standard', listKey: 'hinge_spacing_standards',
      fields: [['name', 'name', 'text'], ['topDistance', 'top_distance', 'number'], ['topLabel', 'top_label', 'text'],
               ['bottomDistance', 'bottom_distance', 'number'], ['bottomReference', 'bottom_reference', 'text'], ['bottomLabel', 'bottom_label', 'text'],
               ['notes', 'notes', 'text']],
    },
  };

  async function dcLoadAll() {
    const data = await authenticatedFetch('/config/door-catalog');
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
      doorType: r => `<td>${esc(r.series)}</td><td>${esc(r.stile_name)}</td><td>${r.stile_height}</td><td>${esc(r.std_top_rail_label||'')}</td><td>${esc(r.std_bot_rail_label||'')}</td><td>${esc(r.bev_pn||'')}</td><td>${esc(r.rab_pn||'')}</td><td>${esc(r.cp_pn||'')}</td><td>${esc(r.ast_pn||'')}</td><td>${esc(r.inact_pn||'')}</td>`,
      rail: r => `<td><span class="badge bg-blue-lt">${esc(r.rail_type)}</span></td><td>${esc(r.label)}</td><td>${r.value_in}"</td><td>${esc(r.std_pn||'')}</td><td>${esc(r.thermal_pn||'')}</td><td>${esc(r.mon_pn||'')}</td><td class="small text-muted">${[r.stacked_std_pn,r.stacked_thermal_pn,r.stacked_mon_pn].filter(Boolean).join(', ')}</td>`,
      railLug: r => `<td>${esc(r.rail_pn)}</td><td>${esc(r.lug_pn)}</td>`,
      midLug: r => `<td>${esc(r.rail_pn)}</td><td>${esc(r.lug_pn)}</td><td>${esc(r.f1_pn||'')} ${r.f1_pn?`(${r.f1_qty})`:''}</td><td>${esc(r.f2_pn||'')} ${r.f2_pn?`(${r.f2_qty})`:''}</td>`,
      glassSpec: r => `<td>${esc(r.thickness)}</td><td>${esc(r.stop_pn||'')}</td><td>${esc(r.gasket_pn||'')}</td><td>${esc(r.gasket2_pn||'')}</td><td>${r.gasket_qty_factor ?? ''}</td><td>${r.stop_height ?? ''}</td>`,
      sbk: r => `<td>${esc(r.series)}</td><td>${esc(r.glass_thickness)}</td><td>${esc(r.kit1_pn||'')}</td><td>${esc(r.kit2_pn||'')}</td>`,
      tieRod: r => `<td>${esc(r.series||'')}</td><td>${esc(r.pn)}</td><td>${r.min_len ?? ''}</td><td>${r.max_len ?? ''}</td>`,
      hingeSpacingStandard: r => `<td>${esc(r.name)}</td><td>${r.top_distance}" to ${esc(r.top_label)}</td><td>${r.bottom_distance}" to ${esc(r.bottom_label)} from ${r.bottom_reference === 'floor' ? 'finished floor' : 'bottom of door'}</td>`,
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
    if (type === 'doorType') {
      const railOpts = (t) => '<option value="">— none —</option>' + dcData.rails.filter(r => r.rail_type === t)
        .map(r => `<option value="${esc(r.label)}">${esc(r.label)}</option>`).join('');
      document.getElementById('dc-doorType-stdTopRail').innerHTML = railOpts('top');
      document.getElementById('dc-doorType-stdBotRail').innerHTML = railOpts('bot');
    }
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
      await authenticatedFetch(`/config/${DC_ENTITIES[type].api}/${id}`, { method: 'DELETE' });
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
        await authenticatedFetch(id ? `/config/${entity.api}/${id}` : `/config/${entity.api}`, {
          method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
        });
        hideModal(document.getElementById(`dc-${type}-modal`));
        await dcLoadAll();
      } catch (err) { showNotification(err.message, 'danger'); }
    });
  });

  // ==================== Shared admin table helper (search / filters / sort / count) ====================
  // Each table registers columns (th order; `sort` is the value accessor), a `text` accessor for search and
  // optional dropdown `filters`. hwtApply() filters + sorts the data inside the table's existing render
  // function, and hwtMount() builds the toolbar. Sort and filters are remembered per table in localStorage.
  const HWT = {};
  const hwtStore = {
    get(k) { try { return JSON.parse(localStorage.getItem('hwt:' + k) || 'null'); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem('hwt:' + k, JSON.stringify(v)); } catch (e) { /* storage unavailable */ } },
  };

  function hwtDefine(key, cfg) {
    const saved = hwtStore.get(key) || {};
    HWT[key] = Object.assign({ filters: [] }, cfg, { state: { q: '', sort: saved.sort ?? null, dir: saved.dir ?? 1, f: saved.f || {} } });
  }

  function hwtSave(key) { const s = HWT[key].state; hwtStore.set(key, { sort: s.sort, dir: s.dir, f: s.f }); }

  function hwtMount(key) {
    const t = HWT[key];
    const tbody = document.getElementById(t.tbody);
    const wrap = tbody.closest('.table-responsive');
    const table = tbody.closest('table');
    t.wrap = wrap;
    wrap.classList.add('hwt-scroll');
    table.classList.add('table-sm');

    const bar = document.createElement('div');
    bar.className = 'd-flex flex-wrap gap-2 align-items-center px-3 py-2 border-bottom hwt-bar';
    bar.innerHTML = `
      <div class="input-icon" style="width:230px"><span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="search" class="form-control form-control-sm hwt-q" placeholder="${esc(t.placeholder || 'Search…')}" autocomplete="off"></div>
      ${t.filters.map(f => `<select class="form-select form-select-sm w-auto hwt-f" data-f="${f.id}" title="${esc(f.label)}"></select>`).join('')}
      <button type="button" class="btn btn-sm btn-link hwt-clear" style="display:none">Clear filters</button>
      <span class="ms-auto text-muted small hwt-count"></span>`;
    wrap.parentNode.insertBefore(bar, wrap);
    t.bar = bar;

    let timer;
    bar.querySelector('.hwt-q').addEventListener('input', e => {
      clearTimeout(timer);
      timer = setTimeout(() => { t.state.q = e.target.value; t.render(); }, 150);
    });
    bar.querySelectorAll('.hwt-f').forEach(sel => sel.addEventListener('change', () => {
      t.state.f[sel.dataset.f] = sel.value; hwtSave(key); t.render();
    }));
    bar.querySelector('.hwt-clear').addEventListener('click', () => {
      t.state.q = ''; t.state.f = {}; bar.querySelector('.hwt-q').value = ''; hwtSave(key); t.render();
    });

    table.querySelectorAll('thead th').forEach((th, idx) => {
      const col = t.cols[idx];
      if (!col || !col.sort) return;
      th.classList.add('hwt-sortable');
      th.dataset.col = idx;
      th.addEventListener('click', () => {
        if (t.state.sort === idx) t.state.dir = -t.state.dir; else { t.state.sort = idx; t.state.dir = 1; }
        hwtSave(key); t.render();
      });
    });
  }

  function hwtApply(key, data) {
    const t = HWT[key];
    if (!t || !t.bar) return data;
    const st = t.state, q = st.q.trim().toLowerCase();

    // Dropdown options follow the data; only rewrite when they change so an open select isn't disturbed.
    t.filters.forEach(f => {
      const sel = t.bar.querySelector(`[data-f="${f.id}"]`);
      const opts = f.options(data);
      const sig = JSON.stringify(opts);
      if (sel.dataset.sig !== sig) {
        sel.dataset.sig = sig;
        sel.innerHTML = `<option value="">${esc(f.label)}</option>` + opts.map(([v, l]) => `<option value="${esc(v)}">${esc(l)}</option>`).join('');
      }
      if (st.f[f.id] && !opts.some(o => o[0] === st.f[f.id])) st.f[f.id] = '';
      sel.value = st.f[f.id] || '';
    });

    let list = data.filter(r => (!q || t.text(r).toLowerCase().includes(q))
      && t.filters.every(f => !st.f[f.id] || f.test(r, st.f[f.id])));

    if (st.sort !== null && t.cols[st.sort]?.sort) {
      const get = t.cols[st.sort].sort;
      list = list.slice().sort((a, b) => {
        const x = get(a), y = get(b);
        const r = (typeof x === 'number' && typeof y === 'number') ? x - y : String(x ?? '').localeCompare(String(y ?? ''), undefined, { numeric: true, sensitivity: 'base' });
        return r * st.dir;
      });
    }

    const filtered = q || t.filters.some(f => st.f[f.id]);
    t.bar.querySelector('.hwt-clear').style.display = filtered ? '' : 'none';
    t.bar.querySelector('.hwt-count').textContent = filtered ? `${list.length} of ${data.length}` : `${data.length} total`;
    t.wrap.querySelectorAll('thead th.hwt-sortable').forEach(th => {
      const on = +th.dataset.col === st.sort;
      th.querySelector('.hwt-arrow')?.remove();
      if (on) th.insertAdjacentHTML('beforeend', `<i class="ti ti-arrow-${st.dir > 0 ? 'up' : 'down'} ms-1 hwt-arrow"></i>`);
      th.setAttribute('aria-sort', on ? (st.dir > 0 ? 'ascending' : 'descending') : 'none');
    });

    return list;
  }

  const hwtActiveFilter = { id: 'status', label: 'Any status', options: () => [['active', 'Active'], ['inactive', 'Inactive']], test: (r, v) => (v === 'active') === !!r.active };
  const hwtDistinct = fn => data => [...new Set(data.map(fn).filter(Boolean))].sort((a, b) => a.localeCompare(b)).map(x => [x, x]);

  function hwtDefineAll() {
    const catName = i => (hwFindCategory(i.category_id)?.name || '') + (i.subcategory_id ? ' - ' + (hwFindSubcategory(i.subcategory_id)?.name || '') : '');
    hwtDefine('cat', { tbody: 'hwlib-cat-tbody', placeholder: 'Search categories…', render: hwCatRender,
      text: c => [c.name, c.description, ...(c.subcategories || []).map(s => s.name)].join(' '),
      cols: [{ sort: c => c.name }, { sort: c => c.description || '' }, { sort: c => (c.variables || []).length }, { sort: c => (c.items || []).length }] });
    hwtDefine('var', { tbody: 'hwlib-var-tbody', placeholder: 'Search code, label or group…', render: hwVarRender,
      text: v => [v.code, v.label, v.group_name, v.var_type].join(' '),
      cols: [{ sort: v => v.code }, { sort: v => v.label }, { sort: v => v.group_name }, { sort: v => v.var_type }, null],
      filters: [
        { id: 'group', label: 'Any group', options: hwtDistinct(v => v.group_name), test: (v, x) => v.group_name === x },
        { id: 'type', label: 'Any type', options: hwtDistinct(v => v.var_type), test: (v, x) => v.var_type === x },
        { id: 'calc', label: 'Entered or calculated', options: () => [['calc', 'Calculated'], ['manual', 'Entered']], test: (v, x) => (x === 'calc') === !!v.is_calculated },
      ] });
    hwtDefine('func', { tbody: 'hwlib-func-tbody', placeholder: 'Search code, label or group…', render: hwFuncRender,
      text: f => [f.code, f.label, f.group_name].join(' '),
      cols: [{ sort: f => f.code }, { sort: f => f.label }, { sort: f => f.group_name || '' }, { sort: f => (f.active ? 0 : 1) }],
      filters: [{ id: 'group', label: 'Any group', options: hwtDistinct(f => f.group_name), test: (f, x) => f.group_name === x }, hwtActiveFilter] });
    hwtDefine('item', { tbody: 'hwlib-item-tbody', placeholder: 'Search name, PN or manufacturer…', render: hwItemRender,
      text: i => [i.name, i.pn, i.manufacturer, catName(i)].join(' '),
      cols: [{ sort: i => i.name }, { sort: catName }, { sort: i => i.manufacturer || '' }, { sort: i => i.pn || '' }, { sort: i => (i.active ? 0 : 1) }, { sort: i => (i.needs_review ? 0 : 1) }],
      filters: [{ id: 'mfr', label: 'Any manufacturer', options: hwtDistinct(i => i.manufacturer), test: (i, x) => i.manufacturer === x }, hwtActiveFilter] });
    hwtDefine('backer', { tbody: 'hwlib-backer-tbody', placeholder: 'Search PN, description or fastener…', render: hwBackerRender,
      text: b => [b.pn, b.description, ...(b.fasteners || []).map(f => hwFindFastener(f.fastener_id)?.pn)].join(' '),
      cols: [{ sort: b => b.pn }, { sort: b => b.description || '' }, { sort: b => (b.fasteners || []).length }, { sort: b => (b.active ? 0 : 1) }],
      filters: [hwtActiveFilter] });
    hwtDefine('fastener', { tbody: 'hwlib-fastener-tbody', placeholder: 'Search PN or description…', render: hwFastenerRender,
      text: f => [f.pn, f.description].join(' '),
      cols: [{ sort: f => f.pn }, { sort: f => f.description || '' }, { sort: f => (f.active ? 0 : 1) }],
      filters: [hwtActiveFilter] });
    hwtDefine('set', { tbody: 'hwlib-set-tbody', placeholder: 'Search set or job…', render: hwSetRender,
      text: x => [x.name, x.business_job?.job_number, x.business_job?.job_name].join(' '),
      cols: [{ sort: x => x.name }, { sort: x => x.business_job?.job_name || x.business_job?.job_number || '' }, { sort: x => (x.set_items || []).length }, { sort: x => (x.is_pair ? 0 : 1) }],
      filters: [
        { id: 'job', label: 'Any job', test: (x, v) => String(x.business_job?.id) === v,
          options: data => {
            const jobs = new Map();
            data.forEach(x => { if (x.business_job) jobs.set(String(x.business_job.id), x.business_job.job_name || x.business_job.job_number); });
            return [...jobs].sort((a, b) => a[1].localeCompare(b[1])).map(([id, name]) => [id, name]);
          } },
        { id: 'pair', label: 'Pair or single', options: () => [['pair', 'Pairs'], ['single', 'Single']], test: (x, v) => (v === 'pair') === !!x.is_pair },
      ] });
    Object.keys(HWT).forEach(hwtMount);
  }

  // ==================== Hardware Library Admin ====================
  let hwCategories = [];
  let hwVariables = [];
  let hwFunctions = [];
  let hwItems = [];
  let hwBackers = [];
  let hwFasteners = [];
  let hwSets = [];
  let hwJobs = [];

  async function hwlibAdminInit() {
    const [catalog, admin, jobsData] = await Promise.all([
      authenticatedFetch('/config/hwlib-catalog'),
      authenticatedFetch('/config/hwlib-admin'),
      authenticatedFetch('/business-jobs'),
    ]);
    hwCategories = catalog.categories || [];
    hwItems = admin.items || [];
    hwBackers = admin.backers || [];
    hwFasteners = admin.fasteners || [];
    hwSets = admin.sets || [];
    hwJobs = jobsData.jobs || [];

    const varsData = await authenticatedFetch('/config/hwlib-variables');
    hwVariables = varsData.variables || [];

    const funcsData = await authenticatedFetch('/config/hwlib-functions');
    hwFunctions = funcsData.functions || [];

    if (!Object.keys(HWT).length) hwtDefineAll();
    hwCatRender();
    hwVarRender();
    hwFuncRender();
    hwItemPopulateCategoryFilter();
    hwItemRender();
    hwBackerRender();
    hwFastenerRender();
    hwSetRender();
  }

  function hwFindCategory(id) { return hwCategories.find(c => c.id == id); }
  function hwFindSubcategory(id) {
    for (const c of hwCategories) {
      const s = (c.subcategories || []).find(s => s.id == id);
      if (s) return s;
    }
    return null;
  }
  function hwFindVariable(id) { return hwVariables.find(v => v.id == id); }
  function hwFindFunction(id) { return hwFunctions.find(f => f.id == id); }
  function hwFindItem(id) { return hwItems.find(i => i.id == id); }
  function hwFindBacker(id) { return hwBackers.find(b => b.id == id); }
  function hwFindFastener(id) { return hwFasteners.find(f => f.id == id); }
  function hwFindSet(id) { return hwSets.find(s => s.id == id); }

  // ---- Categories ----
  function hwCatRender() {
    const tbody = document.getElementById('hwlib-cat-tbody');
    tbody.innerHTML = hwtApply('cat', hwCategories).map(c => `
      <tr>
        <td>${esc(c.name)}${(c.subcategories || []).length ? `<div class="text-muted small">${(c.subcategories || []).map(s => esc(s.name)).join(', ')}</div>` : ''}</td>
        <td class="text-muted small">${esc(c.description || '')}</td>
        <td>${(c.variables || []).length}</td>
        <td>${(c.items || []).length}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-icon" onclick="hwCatOpenModal(${c.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-icon text-danger" onclick="hwCatDelete(${c.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
        </td>
      </tr>`).join('') || '<tr><td colspan="5" class="text-muted">No categories match.</td></tr>';
    applyActionPermissions();
  }

  function hwCatOpenModal(id) {
    const c = id ? hwFindCategory(id) : null;
    document.getElementById('hwlib-cat-id').value = id || '';
    document.getElementById('hwlib-cat-name').value = c?.name || '';
    document.getElementById('hwlib-cat-description').value = c?.description || '';
    document.getElementById('hwlib-cat-sort').value = c?.sort_order ?? 0;

    const assignedIds = new Set((c?.variables || []).map(v => v.id));
    const byGroup = {};
    hwVariables.forEach(v => { (byGroup[v.group_name] ||= []).push(v); });
    const wrap = document.getElementById('hwlib-cat-variables');
    wrap.innerHTML = Object.keys(byGroup).sort().map(group => `
      <div class="mb-2">
        <div class="fw-bold small text-muted mb-1">${esc(group)}</div>
        ${byGroup[group].map(v => `
          <label class="form-check">
            <input class="form-check-input hwlib-cat-var-cb" type="checkbox" value="${v.id}" ${assignedIds.has(v.id) ? 'checked' : ''}>
            <span class="form-check-label">${esc(v.label)} <span class="text-muted small">(${esc(v.code)})</span></span>
          </label>`).join('')}
      </div>`).join('');

    hwSubcatRenderList(c);
    document.getElementById('hwlib-cat-subcat-add-wrap').style.display = id ? '' : 'none';
    document.getElementById('hwlib-cat-subcat-hint').style.display = id ? 'none' : '';
    document.getElementById('hwlib-cat-subcat-new').value = '';

    showModal(document.getElementById('hwlib-cat-modal'));
  }

  function hwSubcatRenderList(category) {
    const wrap = document.getElementById('hwlib-cat-subcats');
    const subs = (category?.subcategories || []).slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.name.localeCompare(b.name));
    wrap.innerHTML = subs.map(s => `
      <div class="d-flex align-items-center gap-2 mb-1">
        <input type="text" class="form-control form-control-sm" style="max-width:240px" value="${esc(s.name)}" onchange="hwSubcatRename(${s.id}, this.value)">
        <button type="button" class="btn btn-sm btn-icon text-danger" onclick="hwSubcatDelete(${s.id})"><i class="ti ti-trash"></i></button>
      </div>`).join('') || '<div class="text-muted small mb-2">None yet.</div>';
  }

  async function hwSubcatRefreshAndRender() {
    const categoryId = document.getElementById('hwlib-cat-id').value;
    const data = await authenticatedFetch('/config/hwlib-catalog');
    hwCategories = data.categories || [];
    hwSubcatRenderList(hwFindCategory(categoryId));
    hwCatRender();
  }

  async function hwSubcatAdd() {
    const categoryId = document.getElementById('hwlib-cat-id').value;
    const nameInput = document.getElementById('hwlib-cat-subcat-new');
    const name = nameInput.value.trim();
    if (!categoryId || !name) return;
    try {
      await authenticatedFetch('/config/hwlib-subcategories', {
        method: 'POST', body: JSON.stringify({ category_id: categoryId, name }),
      });
      nameInput.value = '';
      await hwSubcatRefreshAndRender();
    } catch (err) { showNotification(err.message, 'danger'); }
  }

  async function hwSubcatRename(id, name) {
    name = (name || '').trim();
    if (!name) return;
    try {
      await authenticatedFetch(`/config/hwlib-subcategories/${id}`, { method: 'PUT', body: JSON.stringify({ name }) });
      await hwSubcatRefreshAndRender();
    } catch (err) { showNotification(err.message, 'danger'); }
  }

  async function hwSubcatDelete(id) {
    if (!confirm('Delete this subcategory? Items using it stay in the parent category, just uncategorized within it.')) return;
    try {
      await authenticatedFetch(`/config/hwlib-subcategories/${id}`, { method: 'DELETE' });
      await hwSubcatRefreshAndRender();
    } catch (err) { showNotification(err.message, 'danger'); }
  }

  document.getElementById('hwlib-cat-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('hwlib-cat-id').value;
    const payload = {
      name: document.getElementById('hwlib-cat-name').value,
      description: document.getElementById('hwlib-cat-description').value || null,
      sort_order: parseInt(document.getElementById('hwlib-cat-sort').value || 0, 10),
    };
    const variableIds = Array.from(document.querySelectorAll('.hwlib-cat-var-cb:checked')).map(cb => parseInt(cb.value, 10));
    try {
      const res = await authenticatedFetch(id ? `/config/hwlib-categories/${id}` : '/config/hwlib-categories', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      const categoryId = id || res.category.id;
      await authenticatedFetch(`/config/hwlib-categories/${categoryId}/variables`, {
        method: 'PUT', body: JSON.stringify({ variable_ids: variableIds }),
      });
      hideModal(document.getElementById('hwlib-cat-modal'));
      await hwlibAdminInit();
    } catch (err) { showNotification(err.message, 'danger'); }
  });

  async function hwCatDelete(id) {
    if (!confirm('Delete this category? Items assigned to it will need to be recategorized.')) return;
    try { await authenticatedFetch(`/config/hwlib-categories/${id}`, { method: 'DELETE' }); await hwlibAdminInit(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ---- Variables ----
  function hwVarOverrideBadges(v) {
    const badges = [];
    if (v.is_calculated) badges.push('<span class="badge bg-purple-lt">calc</span>');
    if (v.is_inspection) badges.push('<span class="badge bg-orange-lt">inspection</span>');
    if (v.show_in_report) badges.push('<span class="badge bg-blue-lt">report</span>');
    if (v.side) badges.push(`<span class="badge bg-secondary-lt">${esc(v.side)}</span>`);
    if (v.overrides_variable_id) {
      const target = hwFindVariable(v.overrides_variable_id);
      badges.push(`<span class="badge bg-yellow-lt">→ ${esc(target?.code || v.overrides_variable_id)}</span>`);
    }
    const overriddenBy = hwVariables.filter(o => o.overrides_variable_id == v.id);
    if (overriddenBy.length) badges.push(`<span class="badge bg-yellow-lt">← ${overriddenBy.map(o => esc(o.code)).join(', ')}</span>`);
    return badges.join(' ');
  }

  function hwVarRender() {
    const tbody = document.getElementById('hwlib-var-tbody');
    const list = hwtApply('var', hwVariables);
    tbody.innerHTML = list.map(v => `
      <tr>
        <td><code>${esc(v.code)}</code></td>
        <td>${esc(v.label)}</td>
        <td>${esc(v.group_name)}</td>
        <td><span class="badge bg-secondary-lt">${esc(v.var_type)}</span></td>
        <td>${hwVarOverrideBadges(v)}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-icon" onclick="hwVarOpenModal(${v.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-icon text-danger" onclick="hwVarDelete(${v.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
        </td>
      </tr>`).join('') || '<tr><td colspan="6" class="text-muted">No variables match.</td></tr>';
    applyActionPermissions();
  }

  function hwVarToggleOptions() {
    document.getElementById('hwlib-var-options-wrap').style.display = document.getElementById('hwlib-var-type').value === 'select' ? '' : 'none';
  }

  // ---- Variable formula builder (same signed-term shape as the frame cut-length formula) ----
  function hwVarToggleFormula() {
    const on = document.getElementById('hwlib-var-calculated').checked;
    document.getElementById('hwlib-var-formula-wrap').style.display = on ? '' : 'none';
    if (on && !document.querySelector('#hwlib-var-branches .hw-branch')) hwVarAddBranch({ when: null, terms: [] });
  }
  function hwVarCodeOptions(selected) {
    const cur = document.getElementById('hwlib-var-id').value;
    return '<option value="">— variable —</option>' + hwVariables
      .filter(x => x.id != cur).sort((a, b) => a.code.localeCompare(b.code))
      .map(x => `<option value="${esc(x.code)}" ${x.code === selected ? 'selected' : ''}>${esc(x.code)} — ${esc(x.label)}</option>`).join('');
  }
  function hwVarAddBranch(branch) {
    const el = document.createElement('div');
    el.className = 'border rounded p-2 mb-2 hw-branch';
    const w = branch?.when || null;
    el.innerHTML = `
      <div class="row g-2 align-items-center mb-2">
        <div class="col-3">
          <select class="form-select form-select-sm hw-when-type" onchange="hwVarToggleWhen(this)">
            <option value="" ${!w ? 'selected' : ''}>Always / otherwise</option>
            <option value="var_true" ${w?.type === 'var_true' ? 'selected' : ''}>If variable is true</option>
            <option value="category" ${w?.type === 'category' ? 'selected' : ''}>If opening has category</option>
          </select>
        </div>
        <div class="col-7">
          <select class="form-select form-select-sm hw-when-var" style="${w?.type === 'var_true' ? '' : 'display:none'}">${hwVarCodeOptions(w?.var)}</select>
          <input type="text" class="form-control form-control-sm hw-when-match" placeholder="category name contains… (e.g. continuous hinge)" value="${esc(w?.match || '')}" style="${w?.type === 'category' ? '' : 'display:none'}">
        </div>
        <div class="col-2 text-end">
          <button type="button" class="btn btn-sm btn-icon text-danger" title="Remove condition" onclick="this.closest('.hw-branch').remove()"><i class="ti ti-x"></i></button>
        </div>
      </div>
      <div class="hw-terms"></div>
      <button type="button" class="btn btn-sm btn-link p-0" onclick="hwVarAddTerm(this.closest('.hw-branch').querySelector('.hw-terms'))"><i class="ti ti-plus"></i> Term</button>`;
    document.getElementById('hwlib-var-branches').appendChild(el);
    const terms = branch?.terms?.length ? branch.terms : [{ sign: 1, kind: 'var' }];
    terms.forEach(t => hwVarAddTerm(el.querySelector('.hw-terms'), t));
  }
  function hwVarToggleWhen(sel) {
    const b = sel.closest('.hw-branch');
    b.querySelector('.hw-when-var').style.display = sel.value === 'var_true' ? '' : 'none';
    b.querySelector('.hw-when-match').style.display = sel.value === 'category' ? '' : 'none';
  }
  function hwVarAddTerm(wrap, term) {
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center mb-2 hw-term';
    const kind = term?.kind || 'var';
    row.innerHTML = `
      <div class="col-2"><select class="form-select form-select-sm hw-term-sign">
        <option value="1" ${!term || term.sign >= 0 ? 'selected' : ''}>+</option>
        <option value="-1" ${term && term.sign < 0 ? 'selected' : ''}>−</option></select></div>
      <div class="col-2"><select class="form-select form-select-sm hw-term-kind" onchange="hwVarToggleTerm(this)">
        <option value="var" ${kind === 'var' ? 'selected' : ''}>Variable</option>
        <option value="fixed" ${kind === 'fixed' ? 'selected' : ''}>Fixed</option></select></div>
      <div class="col-5">
        <select class="form-select form-select-sm hw-term-var" style="${kind === 'var' ? '' : 'display:none'}">${hwVarCodeOptions(term?.var)}</select>
        <input type="number" step="0.0001" class="form-control form-control-sm hw-term-value" placeholder="value" value="${kind === 'fixed' ? (term?.value ?? '') : ''}" style="${kind === 'fixed' ? '' : 'display:none'}">
      </div>
      <div class="col-2"><input type="number" step="any" class="form-control form-control-sm hw-term-mult" title="Multiplier" placeholder="×1" value="${kind === 'var' && term?.mult != null && term.mult != 1 ? term.mult : ''}" style="${kind === 'var' ? '' : 'display:none'}"></div>
      <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-icon text-danger" onclick="this.closest('.hw-term').remove()"><i class="ti ti-x"></i></button></div>`;
    wrap.appendChild(row);
  }
  function hwVarToggleTerm(sel) {
    const r = sel.closest('.hw-term'), isVar = sel.value === 'var';
    r.querySelector('.hw-term-var').style.display = isVar ? '' : 'none';
    r.querySelector('.hw-term-mult').style.display = isVar ? '' : 'none';
    r.querySelector('.hw-term-value').style.display = isVar ? 'none' : '';
  }
  function hwVarCollectFormula() {
    const branches = Array.from(document.querySelectorAll('#hwlib-var-branches .hw-branch')).map(b => {
      const type = b.querySelector('.hw-when-type').value;
      const when = !type ? null : type === 'var_true'
        ? { type, var: b.querySelector('.hw-when-var').value }
        : { type, match: b.querySelector('.hw-when-match').value.trim() };
      const terms = Array.from(b.querySelectorAll('.hw-term')).map(r => {
        const kind = r.querySelector('.hw-term-kind').value;
        const t = { sign: parseInt(r.querySelector('.hw-term-sign').value, 10), kind };
        if (kind === 'fixed') t.value = parseFloat(r.querySelector('.hw-term-value').value || 0);
        else { t.var = r.querySelector('.hw-term-var').value; t.mult = parseFloat(r.querySelector('.hw-term-mult').value || 1); }
        return t;
      });
      return { when, terms };
    });
    return { branches };
  }

  function hwVarOpenModal(id) {
    const v = id ? hwFindVariable(id) : null;
    document.getElementById('hwlib-var-id').value = id || '';
    document.getElementById('hwlib-var-code').value = v?.code || '';
    document.getElementById('hwlib-var-label').value = v?.label || '';
    document.getElementById('hwlib-var-group').value = v?.group_name || '';
    document.getElementById('hwlib-var-type').value = v?.var_type || 'number';
    document.getElementById('hwlib-var-unit').value = v?.unit || '';
    document.getElementById('hwlib-var-side').value = v?.side || '';
    document.getElementById('hwlib-var-sort').value = v?.sort_order ?? 0;
    document.getElementById('hwlib-var-options').value = (v?.options || []).join(', ');
    document.getElementById('hwlib-var-default').value = v?.default_value || '';
    document.getElementById('hwlib-var-notes').value = v?.notes || '';
    document.getElementById('hwlib-var-calculated').checked = !!v?.is_calculated;
    document.getElementById('hwlib-var-showreport').checked = !!v?.show_in_report;
    document.getElementById('hwlib-var-inspection').checked = !!v?.is_inspection;

    document.getElementById('hwlib-var-group-list').innerHTML =
      [...new Set(hwVariables.map(x => x.group_name))].sort().map(g => `<option value="${esc(g)}">`).join('');
    document.getElementById('hwlib-var-overrides').innerHTML = '<option value="">— none —</option>' +
      hwVariables.filter(x => x.id != id).map(x => `<option value="${x.id}" ${v?.overrides_variable_id == x.id ? 'selected' : ''}>${esc(x.code)} — ${esc(x.label)}</option>`).join('');

    hwVarToggleOptions();
    document.getElementById('hwlib-var-branches').innerHTML = '';
    const def = v?.formula_def;
    if (def?.branches?.length) def.branches.forEach(b => hwVarAddBranch(b));
    hwVarToggleFormula();
    showModal(document.getElementById('hwlib-var-modal'));
  }

  document.getElementById('hwlib-var-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('hwlib-var-id').value;
    const optionsRaw = document.getElementById('hwlib-var-options').value.trim();
    const payload = {
      code: document.getElementById('hwlib-var-code').value.trim().toUpperCase(),
      label: document.getElementById('hwlib-var-label').value,
      group_name: document.getElementById('hwlib-var-group').value,
      var_type: document.getElementById('hwlib-var-type').value,
      unit: document.getElementById('hwlib-var-unit').value || null,
      side: document.getElementById('hwlib-var-side').value || null,
      sort_order: parseInt(document.getElementById('hwlib-var-sort').value || 0, 10),
      options: optionsRaw ? optionsRaw.split(',').map(s => s.trim()).filter(Boolean) : [],
      default_value: document.getElementById('hwlib-var-default').value || null,
      notes: document.getElementById('hwlib-var-notes').value || null,
      overrides_variable_id: document.getElementById('hwlib-var-overrides').value || null,
      is_calculated: document.getElementById('hwlib-var-calculated').checked,
      formula_def: document.getElementById('hwlib-var-calculated').checked ? hwVarCollectFormula() : null,
      show_in_report: document.getElementById('hwlib-var-showreport').checked,
      is_inspection: document.getElementById('hwlib-var-inspection').checked,
    };
    try {
      await authenticatedFetch(id ? `/config/hwlib-variables/${id}` : '/config/hwlib-variables', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      hideModal(document.getElementById('hwlib-var-modal'));
      await hwlibAdminInit();
    } catch (err) { showNotification(err.message, 'danger'); }
  });

  async function hwVarDelete(id) {
    if (!confirm('Delete this variable?')) return;
    try { await authenticatedFetch(`/config/hwlib-variables/${id}`, { method: 'DELETE' }); await hwlibAdminInit(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ---- Functions ----
  function hwFuncRender() {
    const tbody = document.getElementById('hwlib-func-tbody');
    tbody.innerHTML = hwtApply('func', hwFunctions).map(f => `
      <tr>
        <td><code>${esc(f.code)}</code></td>
        <td>${esc(f.label)}</td>
        <td>${f.group_name ? esc(f.group_name) : '<span class="text-muted">—</span>'}</td>
        <td>${f.active ? '<span class="badge bg-green-lt">active</span>' : '<span class="badge bg-secondary-lt">inactive</span>'}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-icon" onclick="hwFuncOpenModal(${f.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-icon text-danger" onclick="hwFuncDelete(${f.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
        </td>
      </tr>`).join('') || '<tr><td colspan="5" class="text-muted">No functions match.</td></tr>';
    applyActionPermissions();
  }

  function hwFuncOpenModal(id) {
    const f = id ? hwFindFunction(id) : null;
    document.getElementById('hwlib-func-id').value = id || '';
    document.getElementById('hwlib-func-code').value = f?.code || '';
    document.getElementById('hwlib-func-label').value = f?.label || '';
    document.getElementById('hwlib-func-group').value = f?.group_name || '';
    document.getElementById('hwlib-func-notes').value = f?.notes || '';
    document.getElementById('hwlib-func-sort').value = f?.sort_order ?? 0;
    document.getElementById('hwlib-func-active').checked = f ? !!f.active : true;

    document.getElementById('hwlib-func-group-list').innerHTML =
      [...new Set(hwFunctions.map(x => x.group_name).filter(Boolean))].sort().map(g => `<option value="${esc(g)}">`).join('');

    showModal(document.getElementById('hwlib-func-modal'));
  }

  document.getElementById('hwlib-func-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('hwlib-func-id').value;
    const payload = {
      code: document.getElementById('hwlib-func-code').value.trim().toUpperCase(),
      label: document.getElementById('hwlib-func-label').value,
      group_name: document.getElementById('hwlib-func-group').value || null,
      notes: document.getElementById('hwlib-func-notes').value || null,
      sort_order: parseInt(document.getElementById('hwlib-func-sort').value || 0, 10),
      active: document.getElementById('hwlib-func-active').checked,
    };
    try {
      await authenticatedFetch(id ? `/config/hwlib-functions/${id}` : '/config/hwlib-functions', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      hideModal(document.getElementById('hwlib-func-modal'));
      await hwlibAdminInit();
    } catch (err) { showNotification(err.message, 'danger'); }
  });

  async function hwFuncDelete(id) {
    if (!confirm('Delete this function? Items/links currently using it must stop referencing it first.')) return;
    try { await authenticatedFetch(`/config/hwlib-functions/${id}`, { method: 'DELETE' }); await hwlibAdminInit(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ---- Items ----
  // One option per subcategory ("Category - Subcategory") for a category
  // that has any, plus a bare "Category" option for its unassigned items;
  // a category with no subcategories keeps a single plain option.
  function hwCategoryFlatOptions() {
    const options = [];
    hwCategories.forEach(c => {
      const subs = c.subcategories || [];
      if (!subs.length) { options.push({ value: `c${c.id}`, label: c.name }); return; }
      subs.forEach(s => options.push({ value: `s${s.id}`, label: `${c.name} - ${s.name}` }));
      options.push({ value: `c${c.id}`, label: `${c.name} (uncategorized)` });
    });
    return options;
  }

  function hwItemPopulateCategoryFilter() {
    const sel = document.getElementById('hwlib-item-catfilter');
    const current = sel.value;
    sel.innerHTML = '<option value="">All Categories</option>' + hwCategoryFlatOptions().map(o => `<option value="${o.value}">${esc(o.label)}</option>`).join('');
    sel.value = current;
  }

  function hwItemRender() {
    const catFilter = document.getElementById('hwlib-item-catfilter').value;
    const reviewOnly = document.getElementById('hwlib-item-reviewfilter').checked;
    const tbody = document.getElementById('hwlib-item-tbody');
    let list = hwtApply('item', hwItems);
    if (catFilter.startsWith('s')) list = list.filter(i => i.subcategory_id == catFilter.slice(1));
    else if (catFilter.startsWith('c')) list = list.filter(i => i.category_id == catFilter.slice(1) && !i.subcategory_id);
    if (reviewOnly) list = list.filter(i => i.needs_review);
    tbody.innerHTML = list.map(i => `
      <tr>
        <td>${esc(i.name)}</td>
        <td>${esc(hwFindCategory(i.category_id)?.name || '')}${i.subcategory_id ? ' - ' + esc(hwFindSubcategory(i.subcategory_id)?.name || '') : ''}</td>
        <td>${esc(i.manufacturer || '')}</td>
        <td>${esc(i.pn || '')}</td>
        <td>${i.active ? '<span class="badge bg-green-lt">active</span>' : '<span class="badge bg-secondary-lt">inactive</span>'}</td>
        <td>${i.needs_review ? '<span class="badge bg-yellow-lt">needs review</span>' : ''}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-icon" onclick="hwItemOpenModal(${i.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-icon text-danger" onclick="hwItemDelete(${i.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
        </td>
      </tr>`).join('') || '<tr><td colspan="7" class="text-muted">No items match.</td></tr>';
    applyActionPermissions();
  }

  function hwItemValueInputHtml(variable, currentValue) {
    const name = `hwlib-item-val-${variable.id}`;
    if (variable.is_calculated) {
      return `<input type="text" class="form-control form-control-sm" value="${esc(currentValue ?? '')}" disabled placeholder="calculated">`;
    }
    if (variable.var_type === 'boolean') {
      return `<select class="form-select form-select-sm" id="${name}"><option value="">—</option><option value="true" ${currentValue === 'true' ? 'selected' : ''}>True</option><option value="false" ${currentValue === 'false' ? 'selected' : ''}>False</option></select>`;
    }
    if (variable.var_type === 'select') {
      const opts = (variable.options || []).map(o => `<option value="${esc(o)}" ${currentValue === o ? 'selected' : ''}>${esc(o)}</option>`).join('');
      return `<select class="form-select form-select-sm" id="${name}"><option value="">—</option>${opts}</select>`;
    }
    if (variable.var_type === 'degree_matrix') {
      let opts = '<option value="">—</option>';
      for (let d = 85; d <= 110; d++) opts += `<option value="${d}" ${currentValue == d ? 'selected' : ''}>${d}°</option>`;
      return `<select class="form-select form-select-sm" id="${name}">${opts}</select>`;
    }
    if (variable.var_type === 'number') {
      return `<input type="number" step="any" class="form-control form-control-sm" id="${name}" value="${esc(currentValue ?? '')}">`;
    }
    return `<input type="text" class="form-control form-control-sm" id="${name}" value="${esc(currentValue ?? '')}">`;
  }

  function hwItemPopulateSubcategorySelect(categoryId, selectedId) {
    const category = hwFindCategory(categoryId);
    const subs = (category?.subcategories || []).slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.name.localeCompare(b.name));
    const sel = document.getElementById('hwlib-item-subcategory');
    sel.innerHTML = '<option value="">— none —</option>' + subs.map(s => `<option value="${s.id}" ${s.id == selectedId ? 'selected' : ''}>${esc(s.name)}</option>`).join('');
    document.getElementById('hwlib-item-subcategory-wrap').style.display = subs.length ? '' : 'none';
  }

  function hwItemOnCategoryChange() {
    hwItemPopulateSubcategorySelect(document.getElementById('hwlib-item-category').value, null);
    hwItemRenderValueInputs();
  }

  function hwItemRenderValueInputs() {
    const categoryId = document.getElementById('hwlib-item-category').value;
    const category = hwFindCategory(categoryId);
    const variables = (category?.variables || []).slice().sort((a, b) => (a.pivot?.sort_order ?? 0) - (b.pivot?.sort_order ?? 0));
    const itemId = document.getElementById('hwlib-item-id').value;
    const item = itemId ? hwFindItem(itemId) : null;
    const valueFor = (variableId) => item?.values?.find(v => v.variable_id == variableId)?.value_text;

    const byGroup = {};
    variables.forEach(v => { (byGroup[v.group_name] ||= []).push(v); });

    const wrap = document.getElementById('hwlib-item-values');
    wrap.innerHTML = Object.keys(byGroup).sort().map(group => `
      <div class="col-12"><div class="fw-bold small text-muted mt-2">${esc(group)}</div></div>
      ${byGroup[group].map(v => `
        <div class="col-md-4">
          <label class="form-label small mb-1">${esc(v.label)}${v.unit ? ` <span class="text-muted">(${esc(v.unit)})</span>` : ''}</label>
          ${hwItemValueInputHtml(v, valueFor(v.id))}
        </div>`).join('')}
    `).join('') || '<div class="col-12 text-muted small">Select a category to see its prep fields.</div>';
  }

  function hwItemRenderFunctionInputs(item) {
    const assignedIds = new Set((item?.functions || []).map(f => f.id));
    const byGroup = {};
    hwFunctions.forEach(f => { (byGroup[f.group_name || ''] ||= []).push(f); });
    const wrap = document.getElementById('hwlib-item-functions');
    wrap.innerHTML = Object.keys(byGroup).sort().map(group => `
      <div class="mb-2">
        <div class="fw-bold small text-muted mb-1">${group ? esc(group) : 'Ungrouped'}</div>
        ${byGroup[group].map(f => `
          <label class="form-check">
            <input class="form-check-input hwlib-item-func-cb" type="checkbox" value="${f.id}" ${assignedIds.has(f.id) ? 'checked' : ''}>
            <span class="form-check-label">${esc(f.label)} <span class="text-muted small">(${esc(f.code)})</span></span>
          </label>`).join('')}
      </div>`).join('') || '<div class="text-muted small">No functions defined yet — add some on the Functions tab.</div>';
  }

  function hwItemCollectFunctions() {
    return Array.from(document.querySelectorAll('.hwlib-item-func-cb:checked')).map(cb => parseInt(cb.value, 10));
  }

  function hwItemCollectValues() {
    const categoryId = document.getElementById('hwlib-item-category').value;
    const category = hwFindCategory(categoryId);
    return (category?.variables || [])
      .filter(v => !v.is_calculated)
      .map(v => {
        const el = document.getElementById(`hwlib-item-val-${v.id}`);
        return { variable_id: v.id, value_text: el ? el.value : '' };
      });
  }

  function hwItemBackerRowHtml(row) {
    const backerOptions = hwBackers.map(b => `<option value="${b.id}" ${row?.backer_id == b.id ? 'selected' : ''}>${esc(b.pn)} — ${esc(b.description || '')}</option>`).join('');
    return `
      <div class="row g-2 align-items-center mb-2 hwlib-item-backer-row">
        <div class="col-md-2"><select class="form-select form-select-sm hwlib-ib-side"><option value="door" ${!row || row?.side === 'door' ? 'selected' : ''}>Door</option><option value="frame" ${row?.side === 'frame' ? 'selected' : ''}>Frame</option></select></div>
        <div class="col-md-2"><select class="form-select form-select-sm hwlib-ib-series"><option value="Standard" ${!row || row?.series === 'Standard' ? 'selected' : ''}>Standard</option><option value="Thermal" ${row?.series === 'Thermal' ? 'selected' : ''}>Thermal</option><option value="Monumental" ${row?.series === 'Monumental' ? 'selected' : ''}>Monumental</option></select></div>
        <div class="col-md-4"><select class="form-select form-select-sm hwlib-ib-backer"><option value="">— none —</option>${backerOptions}</select></div>
        <div class="col-md-2"><input type="number" step="0.001" class="form-control form-control-sm hwlib-ib-qty" placeholder="qty" value="${row?.qty ?? 1}"></div>
        <div class="col-md-1"><input type="text" class="form-control form-control-sm hwlib-ib-notes" placeholder="notes" value="${esc(row?.notes || '')}"></div>
        <div class="col-md-1"><button type="button" class="btn btn-sm btn-icon text-danger" onclick="this.closest('.hwlib-item-backer-row').remove()"><i class="ti ti-x"></i></button></div>
      </div>`;
  }

  function hwItemAddBackerRow(row) {
    document.getElementById('hwlib-item-backers').insertAdjacentHTML('beforeend', hwItemBackerRowHtml(row));
  }

  function hwItemCollectBackers() {
    return Array.from(document.querySelectorAll('.hwlib-item-backer-row')).map(row => ({
      side: row.querySelector('.hwlib-ib-side').value,
      series: row.querySelector('.hwlib-ib-series').value,
      backer_id: row.querySelector('.hwlib-ib-backer').value || null,
      qty: parseFloat(row.querySelector('.hwlib-ib-qty').value || 0),
      notes: row.querySelector('.hwlib-ib-notes').value || null,
    }));
  }

  // ---- Item documents ----
  const HW_DOC_TYPES = { template: 'Template', instructions: 'Instructions', parts_manual: 'Parts manual', spec_sheet: 'Spec sheet', other: 'Other' };

  function hwItemRenderDocs(docs) {
    const fmt = n => n == null ? '' : n < 1048576 ? Math.max(1, Math.round(n / 1024)) + ' KB' : (n / 1048576).toFixed(1) + ' MB';
    document.getElementById('hwlib-item-docs').innerHTML = (docs || []).map(d => `
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge bg-blue-lt">${esc(HW_DOC_TYPES[d.doc_type] || d.doc_type)}</span>
        <a href="${esc(d.url)}" target="_blank" rel="noopener">${esc(d.title)}</a>
        <span class="text-muted small">${esc(d.file_name)} ${fmt(d.file_size)}</span>
        <button type="button" class="btn btn-sm btn-icon text-danger ms-auto" title="Delete" onclick="hwItemDeleteDoc(${d.id})"><i class="ti ti-trash"></i></button>
      </div>`).join('') || '<div class="text-muted small">No documents yet.</div>';
  }
  function hwItemSetDocs(itemId, docs) {
    const it = hwFindItem(itemId);
    if (it) it.documents = docs;
    hwItemRenderDocs(docs);
  }
  async function hwItemUploadDoc() {
    const itemId = document.getElementById('hwlib-item-id').value;
    const file = document.getElementById('hwlib-doc-file').files[0];
    if (!itemId || !file) { showNotification('Choose a file to upload.', 'warning'); return; }
    const fd = new FormData();
    fd.append('file', file);
    fd.append('doc_type', document.getElementById('hwlib-doc-type').value);
    const title = document.getElementById('hwlib-doc-title').value.trim();
    if (title) fd.append('title', title);
    const btn = document.getElementById('hwlib-doc-upload');
    btn.disabled = true;
    try {
      const r = await authenticatedUpload(`/config/hwlib-items/${itemId}/documents`, fd);
      const data = await r.json().catch(() => ({}));
      if (!r.ok) { showNotification(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Upload failed', 'danger'); return; }
      hwItemSetDocs(itemId, data.documents);
      document.getElementById('hwlib-doc-file').value = '';
      document.getElementById('hwlib-doc-title').value = '';
    } catch (err) { showNotification(err.message, 'danger'); }
    finally { btn.disabled = false; }
  }
  async function hwItemDeleteDoc(docId) {
    if (!confirm('Delete this document?')) return;
    const itemId = document.getElementById('hwlib-item-id').value;
    try {
      const res = await authenticatedFetch(`/config/hwlib-item-documents/${docId}`, { method: 'DELETE' });
      hwItemSetDocs(itemId, res.documents);
    } catch (err) { showNotification(err.message, 'danger'); }
  }

  function hwItemOpenModal(id) {
    const i = id ? hwFindItem(id) : null;
    document.getElementById('hwlib-item-id').value = id || '';
    document.getElementById('hwlib-item-category').innerHTML = hwCategories.map(c => `<option value="${c.id}" ${i?.category_id == c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('');
    hwItemPopulateSubcategorySelect(i?.category_id, i?.subcategory_id);
    document.getElementById('hwlib-item-name').value = i?.name || '';
    document.getElementById('hwlib-item-pn').value = i?.pn || '';
    document.getElementById('hwlib-item-manufacturer').value = i?.manufacturer || '';
    document.getElementById('hwlib-item-model').value = i?.model_number || '';
    document.getElementById('hwlib-item-notes').value = i?.notes || '';
    document.getElementById('hwlib-item-minwidth').value = i?.min_width ?? '';
    document.getElementById('hwlib-item-maxwidth').value = i?.max_width ?? '';
    document.getElementById('hwlib-item-minheight').value = i?.min_height ?? '';
    document.getElementById('hwlib-item-maxheight').value = i?.max_height ?? '';
    document.getElementById('hwlib-item-active').checked = i ? !!i.active : true;
    document.getElementById('hwlib-item-needsreview').checked = !!i?.needs_review;
    document.getElementById('hwlib-item-vosstandard').checked = !!i?.vos_standard;
    document.getElementById('hwlib-item-fieldinstall').checked = !!i?.field_install;
    document.getElementById('hwlib-item-handed').checked = !!i?.handed;

    const itemOptions = (excludeId) => hwItems.filter(x => x.id != excludeId).map(x => `<option value="${x.id}">${esc(x.name)}</option>`).join('');
    document.getElementById('hwlib-item-strike').innerHTML = '<option value="">— none —</option>' + itemOptions(id);
    document.getElementById('hwlib-item-strike').value = i?.default_strike_item_id || '';
    document.getElementById('hwlib-item-cover').innerHTML = '<option value="">— none —</option>' + itemOptions(id);
    document.getElementById('hwlib-item-cover').value = i?.default_cover_item_id || '';

    hwItemRenderValueInputs();
    hwItemRenderFunctionInputs(i);

    document.getElementById('hwlib-item-backers').innerHTML = '';
    (i?.backers || []).forEach(row => hwItemAddBackerRow(row));

    document.getElementById('hwlib-item-docs-new').style.display = id ? 'none' : '';
    document.getElementById('hwlib-item-docs-wrap').style.display = id ? '' : 'none';
    document.getElementById('hwlib-doc-file').value = '';
    document.getElementById('hwlib-doc-title').value = '';
    hwItemRenderDocs(i?.documents);

    showModal(document.getElementById('hwlib-item-modal'));
  }

  document.getElementById('hwlib-item-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('hwlib-item-id').value;
    const payload = {
      category_id: document.getElementById('hwlib-item-category').value,
      subcategory_id: document.getElementById('hwlib-item-subcategory').value || null,
      name: document.getElementById('hwlib-item-name').value,
      pn: document.getElementById('hwlib-item-pn').value || null,
      manufacturer: document.getElementById('hwlib-item-manufacturer').value || null,
      model_number: document.getElementById('hwlib-item-model').value || null,
      notes: document.getElementById('hwlib-item-notes').value || null,
      min_width: document.getElementById('hwlib-item-minwidth').value || null,
      max_width: document.getElementById('hwlib-item-maxwidth').value || null,
      min_height: document.getElementById('hwlib-item-minheight').value || null,
      max_height: document.getElementById('hwlib-item-maxheight').value || null,
      active: document.getElementById('hwlib-item-active').checked,
      needs_review: document.getElementById('hwlib-item-needsreview').checked,
      vos_standard: document.getElementById('hwlib-item-vosstandard').checked,
      field_install: document.getElementById('hwlib-item-fieldinstall').checked,
      handed: document.getElementById('hwlib-item-handed').checked,
      default_strike_item_id: document.getElementById('hwlib-item-strike').value || null,
      default_cover_item_id: document.getElementById('hwlib-item-cover').value || null,
      finishes: [],
    };
    try {
      const res = await authenticatedFetch(id ? `/config/hwlib-items/${id}` : '/config/hwlib-items', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      const itemId = id || res.item.id;
      await authenticatedFetch(`/config/hwlib-items/${itemId}/values`, {
        method: 'PUT', body: JSON.stringify({ values: hwItemCollectValues() }),
      });
      await authenticatedFetch(`/config/hwlib-items/${itemId}/functions`, {
        method: 'PUT', body: JSON.stringify({ function_ids: hwItemCollectFunctions() }),
      });
      await authenticatedFetch(`/config/hwlib-items/${itemId}/backers`, {
        method: 'PUT', body: JSON.stringify({ backers: hwItemCollectBackers() }),
      });
      hideModal(document.getElementById('hwlib-item-modal'));
      await hwlibAdminInit();
    } catch (err) { showNotification(err.message, 'danger'); }
  });

  async function hwItemDelete(id) {
    if (!confirm('Delete this item?')) return;
    try { await authenticatedFetch(`/config/hwlib-items/${id}`, { method: 'DELETE' }); await hwlibAdminInit(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ---- Backers ----
  function hwBackerRender() {
    const tbody = document.getElementById('hwlib-backer-tbody');
    tbody.innerHTML = hwtApply('backer', hwBackers).map(b => `
      <tr>
        <td>${esc(b.pn)}</td>
        <td class="text-muted small">${esc(b.description || '')}</td>
        <td>${(b.fasteners || []).length}</td>
        <td>${b.active ? '<span class="badge bg-green-lt">active</span>' : '<span class="badge bg-secondary-lt">inactive</span>'}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-icon" onclick="hwBackerOpenModal(${b.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-icon text-danger" onclick="hwBackerDelete(${b.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
        </td>
      </tr>`).join('') || '<tr><td colspan="5" class="text-muted">No backers match.</td></tr>';
    applyActionPermissions();
  }

  function hwBackerFastenerRowHtml(row) {
    const fastenerOptions = hwFasteners.map(f => `<option value="${f.id}" ${row?.fastener_id == f.id ? 'selected' : ''}>${esc(f.pn)} — ${esc(f.description || '')}</option>`).join('');
    return `
      <div class="row g-2 align-items-center mb-2 hwlib-backer-fastener-row">
        <div class="col-md-6"><select class="form-select form-select-sm hwlib-bf-fastener"><option value="">— select —</option>${fastenerOptions}</select></div>
        <div class="col-md-3"><input type="number" step="0.001" class="form-control form-control-sm hwlib-bf-qty" placeholder="qty" value="${row?.qty ?? 1}"></div>
        <div class="col-md-2"><input type="text" class="form-control form-control-sm hwlib-bf-notes" placeholder="notes" value="${esc(row?.notes || '')}"></div>
        <div class="col-md-1"><button type="button" class="btn btn-sm btn-icon text-danger" onclick="this.closest('.hwlib-backer-fastener-row').remove()"><i class="ti ti-x"></i></button></div>
      </div>`;
  }

  function hwBackerAddFastenerRow(row) {
    document.getElementById('hwlib-backer-fasteners').insertAdjacentHTML('beforeend', hwBackerFastenerRowHtml(row));
  }

  function hwBackerCollectFasteners() {
    return Array.from(document.querySelectorAll('.hwlib-backer-fastener-row'))
      .map(row => ({
        fastener_id: row.querySelector('.hwlib-bf-fastener').value,
        qty: parseFloat(row.querySelector('.hwlib-bf-qty').value || 0),
        notes: row.querySelector('.hwlib-bf-notes').value || null,
      }))
      .filter(r => r.fastener_id);
  }

  function hwBackerOpenModal(id) {
    const b = id ? hwFindBacker(id) : null;
    document.getElementById('hwlib-backer-id').value = id || '';
    document.getElementById('hwlib-backer-pn').value = b?.pn || '';
    document.getElementById('hwlib-backer-description').value = b?.description || '';
    document.getElementById('hwlib-backer-notes').value = b?.notes || '';
    document.getElementById('hwlib-backer-active').checked = b ? !!b.active : true;

    document.getElementById('hwlib-backer-fasteners').innerHTML = '';
    (b?.fasteners || []).forEach(row => hwBackerAddFastenerRow({ fastener_id: row.fastener_id, qty: row.qty, notes: row.notes }));

    showModal(document.getElementById('hwlib-backer-modal'));
  }

  document.getElementById('hwlib-backer-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('hwlib-backer-id').value;
    const payload = {
      pn: document.getElementById('hwlib-backer-pn').value,
      description: document.getElementById('hwlib-backer-description').value || null,
      notes: document.getElementById('hwlib-backer-notes').value || null,
      active: document.getElementById('hwlib-backer-active').checked,
    };
    try {
      const res = await authenticatedFetch(id ? `/config/hwlib-backers/${id}` : '/config/hwlib-backers', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      const backerId = id || res.backer.id;
      await authenticatedFetch(`/config/hwlib-backers/${backerId}/fasteners`, {
        method: 'PUT', body: JSON.stringify({ fasteners: hwBackerCollectFasteners() }),
      });
      hideModal(document.getElementById('hwlib-backer-modal'));
      await hwlibAdminInit();
    } catch (err) { showNotification(err.message, 'danger'); }
  });

  async function hwBackerDelete(id) {
    if (!confirm('Delete this backer?')) return;
    try { await authenticatedFetch(`/config/hwlib-backers/${id}`, { method: 'DELETE' }); await hwlibAdminInit(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ---- Fasteners ----
  function hwFastenerRender() {
    const tbody = document.getElementById('hwlib-fastener-tbody');
    tbody.innerHTML = hwtApply('fastener', hwFasteners).map(f => `
      <tr>
        <td>${esc(f.pn)}</td>
        <td class="text-muted small">${esc(f.description || '')}</td>
        <td>${f.active ? '<span class="badge bg-green-lt">active</span>' : '<span class="badge bg-secondary-lt">inactive</span>'}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-icon" onclick="hwFastenerOpenModal(${f.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-icon text-danger" onclick="hwFastenerDelete(${f.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
        </td>
      </tr>`).join('') || '<tr><td colspan="4" class="text-muted">No fasteners match.</td></tr>';
    applyActionPermissions();
  }

  function hwFastenerOpenModal(id) {
    const f = id ? hwFindFastener(id) : null;
    document.getElementById('hwlib-fastener-id').value = id || '';
    document.getElementById('hwlib-fastener-pn').value = f?.pn || '';
    document.getElementById('hwlib-fastener-description').value = f?.description || '';
    document.getElementById('hwlib-fastener-notes').value = f?.notes || '';
    document.getElementById('hwlib-fastener-active').checked = f ? !!f.active : true;
    showModal(document.getElementById('hwlib-fastener-modal'));
  }

  document.getElementById('hwlib-fastener-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('hwlib-fastener-id').value;
    const payload = {
      pn: document.getElementById('hwlib-fastener-pn').value,
      description: document.getElementById('hwlib-fastener-description').value || null,
      notes: document.getElementById('hwlib-fastener-notes').value || null,
      active: document.getElementById('hwlib-fastener-active').checked,
    };
    try {
      await authenticatedFetch(id ? `/config/hwlib-fasteners/${id}` : '/config/hwlib-fasteners', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      hideModal(document.getElementById('hwlib-fastener-modal'));
      await hwlibAdminInit();
    } catch (err) { showNotification(err.message, 'danger'); }
  });

  async function hwFastenerDelete(id) {
    if (!confirm('Delete this fastener?')) return;
    try { await authenticatedFetch(`/config/hwlib-fasteners/${id}`, { method: 'DELETE' }); await hwlibAdminInit(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ---- Sets ----
  function hwSetRender() {
    const tbody = document.getElementById('hwlib-set-tbody');
    tbody.innerHTML = hwtApply('set', hwSets).map(s => `
      <tr>
        <td>${esc(s.name)}</td>
        <td>${esc(s.business_job?.job_name || s.business_job?.job_number || '')}${s.business_job?.job_name && s.business_job?.job_number ? ` <span class="text-muted small">${esc(s.business_job.job_number)}</span>` : ''}</td>
        <td>${(s.set_items || []).length}</td>
        <td>${s.is_pair ? 'Yes' : 'No'}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-icon" onclick="hwSetOpenModal(${s.id})" data-permission="configurator.catalog.manage"><i class="ti ti-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-icon text-danger" onclick="hwSetDelete(${s.id})" data-permission="configurator.catalog.manage"><i class="ti ti-trash"></i></button>
        </td>
      </tr>`).join('') || '<tr><td colspan="5" class="text-muted">No sets match.</td></tr>';
    applyActionPermissions();
  }

  // Set item rows are entered as Category -> Manufacturer -> Part, mirroring
  // the same cascade the Configurator's per-opening hardware picker uses
  // (see fbFilterHwItems in frame.blade.php), plus a per-row function picker
  // once a part is chosen, and a "+ Add new part" quick-add for parts that
  // aren't in the library yet (created with needs_review so it can be found
  // and cleaned up later on the Items tab).
  let hwSiRowSeq = 0;

  function hwSiCategoryOptionsHtml(selectedId) {
    return hwCategories.slice().sort((a, b) => a.name.localeCompare(b.name))
      .map(c => `<option value="${c.id}" ${selectedId == c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('');
  }

  function hwSiManufacturersForCategory(categoryId) {
    const mfrs = new Set(hwItems.filter(i => i.category_id == categoryId).map(i => i.manufacturer || ''));
    return Array.from(mfrs).sort((a, b) => a.localeCompare(b));
  }

  function hwSiManufacturerOptionsHtml(categoryId, selected) {
    return hwSiManufacturersForCategory(categoryId)
      .map(m => `<option value="${esc(m)}" ${m === (selected || '') ? 'selected' : ''}>${m ? esc(m) : '(none)'}</option>`).join('');
  }

  function hwSiPartsFor(categoryId, manufacturer) {
    return hwItems.filter(i => i.category_id == categoryId && (i.manufacturer || '') === (manufacturer || ''))
      .slice().sort((a, b) => a.name.localeCompare(b.name));
  }

  function hwSiPartOptionsHtml(categoryId, manufacturer, selectedId) {
    const parts = hwSiPartsFor(categoryId, manufacturer);

    return '<option value="">— select part —</option>' + parts.map(i =>
      `<option value="${i.id}" ${selectedId == i.id ? 'selected' : ''}>${esc(i.name)}${i.pn ? ' — ' + esc(i.pn) : ''}${i.needs_review ? ' (needs review)' : ''}</option>`
    ).join('');
  }

  function hwSiRenderFunctions(rowEl, itemId, selectedFunctionIds) {
    const wrap = rowEl.querySelector('.hwlib-si-functions');
    const item = itemId ? hwFindItem(itemId) : null;
    const functions = item?.functions || [];
    if (!functions.length) { wrap.innerHTML = ''; return; }

    const rowUid = rowEl.dataset.rowUid;
    const selected = new Set((selectedFunctionIds || []).map(id => String(id)));
    const byGroup = {};
    functions.forEach(f => { (byGroup[f.group_name || ''] ||= []).push(f); });

    wrap.innerHTML = Object.keys(byGroup).sort().map(group => {
      const groupFns = byGroup[group];
      if (group) {
        const name = `hwsi-func-${rowUid}-${group.replace(/[^a-z0-9]/gi, '_')}`;
        const noneChecked = !groupFns.some(f => selected.has(String(f.id)));

        return `<div class="d-flex flex-wrap gap-2 align-items-center mb-1">
          <span class="text-muted small">${esc(group)}:</span>
          <label class="form-check form-check-inline mb-0"><input class="form-check-input hwlib-si-func-radio" type="radio" name="${name}" value="" ${noneChecked ? 'checked' : ''}><span class="form-check-label small text-muted">None</span></label>
          ${groupFns.map(f => `<label class="form-check form-check-inline mb-0"><input class="form-check-input hwlib-si-func-radio" type="radio" name="${name}" value="${f.id}" ${selected.has(String(f.id)) ? 'checked' : ''}><span class="form-check-label small">${esc(f.code)}</span></label>`).join('')}
        </div>`;
      }

      return `<div class="d-flex flex-wrap gap-2 align-items-center mb-1">
        <span class="text-muted small">Options:</span>
        ${groupFns.map(f => `<label class="form-check form-check-inline mb-0"><input class="form-check-input hwlib-si-func-cb" type="checkbox" value="${f.id}" ${selected.has(String(f.id)) ? 'checked' : ''}><span class="form-check-label small">${esc(f.code)}</span></label>`).join('')}
      </div>`;
    }).join('');
  }

  // Prep-value defaults for one set row: what openings using the set start with. Blank = the item's own
  // catalog default. Calculated variables are formulas (nothing to set); per-angle (degree matrix) defaults
  // are edited from an opening in the configurator, so they're shown read-only and carried through untouched.
  function hwSiRenderValues(rowEl, itemId, rowValues) {
    const wrap = rowEl.querySelector('.hwlib-si-values');
    const item = itemId ? hwFindItem(itemId) : null;
    const catVars = (item ? (hwFindCategory(item.category_id)?.variables || []) : [])
      .map(cv => hwFindVariable(cv.id) || cv).filter(v => v && !v.is_calculated);
    if (!catVars.length) { wrap.innerHTML = ''; return; }

    const mine = new Map((rowValues || []).map(v => [Number(v.variable_id), v.value_text]));
    const itemDefaults = new Map((item.values || []).map(v => [Number(v.variable_id), v.value_text]));
    const setCount = catVars.filter(v => (mine.get(v.id) ?? '') !== '').length;

    const field = v => {
      const cur = mine.get(v.id) ?? '';
      const def = itemDefaults.get(v.id);
      const ph = def !== undefined && v.var_type !== 'degree_matrix' ? `item: ${def}` : 'item default';
      let input;
      if (v.var_type === 'degree_matrix') {
        input = `<input type="text" class="form-control form-control-sm hwlib-si-val" data-var-id="${v.id}" data-keep="${esc(cur)}" value="${cur ? 'per-angle values set' : ''}" placeholder="per opening angle" disabled title="Per-angle defaults are edited from an opening in the Configurator">`;
      } else if (v.var_type === 'boolean') {
        input = `<select class="form-select form-select-sm hwlib-si-val" data-var-id="${v.id}"><option value="">${esc(ph)}</option><option value="true" ${cur === 'true' ? 'selected' : ''}>Yes</option><option value="false" ${cur === 'false' ? 'selected' : ''}>No</option></select>`;
      } else if (v.var_type === 'select') {
        input = `<select class="form-select form-select-sm hwlib-si-val" data-var-id="${v.id}"><option value="">${esc(ph)}</option>${(v.options || []).map(o => `<option value="${esc(o)}" ${cur === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
      } else {
        input = `<input type="${v.var_type === 'number' ? 'number' : 'text'}" ${v.var_type === 'number' ? 'step="any"' : ''} class="form-control form-control-sm hwlib-si-val" data-var-id="${v.id}" value="${esc(cur)}" placeholder="${esc(ph)}">`;
      }
      return `<div class="col-md-4"><label class="form-label small mb-0 text-muted">${esc(v.label)}${v.unit ? ' (' + esc(v.unit) + ')' : ''}</label>${input}</div>`;
    };

    wrap.innerHTML = `<details ${setCount ? 'open' : ''}><summary class="small text-muted" style="cursor:pointer">Prep values <span class="badge bg-purple-lt">${setCount} set</span></summary>
      <div class="row g-2 mt-1">${catVars.map(field).join('')}</div></details>`;
  }

  function hwSiOnCategoryChange(selectEl) {
    const rowEl = selectEl.closest('.hwlib-set-item-row');
    const mfrSelect = rowEl.querySelector('.hwlib-si-manufacturer');
    mfrSelect.innerHTML = hwSiManufacturerOptionsHtml(selectEl.value, null);
    hwSiOnManufacturerChange(mfrSelect);
  }

  function hwSiOnManufacturerChange(selectEl) {
    const rowEl = selectEl.closest('.hwlib-set-item-row');
    const categoryId = rowEl.querySelector('.hwlib-si-category').value;
    const partSelect = rowEl.querySelector('.hwlib-si-part');
    partSelect.innerHTML = hwSiPartOptionsHtml(categoryId, selectEl.value, null);
    hwSiOnPartChange(partSelect);
  }

  function hwSiOnPartChange(selectEl) {
    const rowEl = selectEl.closest('.hwlib-set-item-row');
    hwSiRenderFunctions(rowEl, selectEl.value, []);
    hwSiRenderValues(rowEl, selectEl.value, []);
  }

  function hwSiToggleQuickAdd(link) {
    const rowEl = link.closest('.hwlib-set-item-row');
    const form = rowEl.querySelector('.hwlib-si-quickadd-form');
    const opening = form.classList.contains('d-none');
    form.classList.toggle('d-none');
    if (opening) {
      rowEl.querySelector('.hwlib-si-qa-mfr').value = rowEl.querySelector('.hwlib-si-manufacturer').value || '';
      rowEl.querySelector('.hwlib-si-qa-name').focus();
    }
  }

  async function hwSiQuickAddSave(btn) {
    const rowEl = btn.closest('.hwlib-set-item-row');
    const categoryId = rowEl.querySelector('.hwlib-si-category').value;
    const manufacturer = rowEl.querySelector('.hwlib-si-qa-mfr').value.trim();
    const name = rowEl.querySelector('.hwlib-si-qa-name').value.trim();
    const pn = rowEl.querySelector('.hwlib-si-qa-pn').value.trim();
    if (!categoryId) { showNotification('Pick a category first', 'warning'); return; }
    if (!name) { showNotification('Part name is required', 'warning'); return; }
    try {
      const res = await authenticatedFetch('/config/hwlib-items', {
        method: 'POST',
        body: JSON.stringify({ category_id: categoryId, manufacturer: manufacturer || null, name, pn: pn || null, needs_review: true }),
      });
      hwItems.push(res.item);

      const mfrSelect = rowEl.querySelector('.hwlib-si-manufacturer');
      mfrSelect.innerHTML = hwSiManufacturerOptionsHtml(categoryId, manufacturer);
      const partSelect = rowEl.querySelector('.hwlib-si-part');
      partSelect.innerHTML = hwSiPartOptionsHtml(categoryId, manufacturer, res.item.id);
      hwSiRenderFunctions(rowEl, res.item.id, []);
      hwSiRenderValues(rowEl, res.item.id, []);

      rowEl.querySelector('.hwlib-si-quickadd-form').classList.add('d-none');
      rowEl.querySelector('.hwlib-si-qa-mfr').value = '';
      rowEl.querySelector('.hwlib-si-qa-name').value = '';
      rowEl.querySelector('.hwlib-si-qa-pn').value = '';
      showNotification('Part added — flagged for review', 'success');
    } catch (err) { showNotification(err.message, 'danger'); }
  }

  function hwSetItemRowHtml(row) {
    const rowUid = ++hwSiRowSeq;
    const item = row?.item_id ? hwFindItem(row.item_id) : null;
    const categoryId = item?.category_id ?? '';
    const manufacturer = item?.manufacturer ?? '';

    return `
      <div class="border rounded p-2 mb-2 hwlib-set-item-row" data-row-uid="${rowUid}" data-set-item-id="${row?.id ?? ''}">
        <div class="row g-2 align-items-center mb-2">
          <div class="col-md-3">
            <select class="form-select form-select-sm hwlib-si-category" onchange="hwSiOnCategoryChange(this)">
              <option value="">— category —</option>${hwSiCategoryOptionsHtml(categoryId)}
            </select>
          </div>
          <div class="col-md-3">
            <select class="form-select form-select-sm hwlib-si-manufacturer" onchange="hwSiOnManufacturerChange(this)">
              ${categoryId !== '' ? hwSiManufacturerOptionsHtml(categoryId, manufacturer) : ''}
            </select>
          </div>
          <div class="col-md-3">
            <select class="form-select form-select-sm hwlib-si-part" onchange="hwSiOnPartChange(this)">
              ${categoryId !== '' ? hwSiPartOptionsHtml(categoryId, manufacturer, row?.item_id) : '<option value="">— select part —</option>'}
            </select>
          </div>
          <div class="col-md-2"><input type="number" min="1" class="form-control form-control-sm hwlib-si-qty" placeholder="qty" value="${row?.quantity ?? 1}"></div>
          <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-icon text-danger" onclick="this.closest('.hwlib-set-item-row').remove()"><i class="ti ti-x"></i></button></div>
        </div>
        <div class="row g-2 align-items-center mb-2">
          <div class="col-md-3"><select class="form-select form-select-sm hwlib-si-series"><option value="Standard" ${!row || row?.series === 'Standard' ? 'selected' : ''}>Standard</option><option value="Thermal" ${row?.series === 'Thermal' ? 'selected' : ''}>Thermal</option><option value="Monumental" ${row?.series === 'Monumental' ? 'selected' : ''}>Monumental</option></select></div>
          <div class="col-md-3"><select class="form-select form-select-sm hwlib-si-leaf"><option value="both" ${!row || row?.leaf === 'both' ? 'selected' : ''}>Both</option><option value="active" ${row?.leaf === 'active' ? 'selected' : ''}>Active</option><option value="inactive" ${row?.leaf === 'inactive' ? 'selected' : ''}>Inactive</option></select></div>
          <div class="col-md-6"><input type="text" class="form-control form-control-sm hwlib-si-notes" placeholder="notes" value="${esc(row?.notes || '')}"></div>
        </div>
        <div class="hwlib-si-functions mb-1"></div>
        <div class="hwlib-si-values mb-1"></div>
        <div class="small">
          <a href="#" onclick="event.preventDefault(); hwSiToggleQuickAdd(this)">+ Add new part</a>
          <div class="hwlib-si-quickadd-form d-none d-flex gap-2 mt-1 align-items-center">
            <input type="text" class="form-control form-control-sm hwlib-si-qa-mfr" placeholder="Manufacturer" style="max-width:160px">
            <input type="text" class="form-control form-control-sm hwlib-si-qa-name" placeholder="Part name" style="max-width:200px">
            <input type="text" class="form-control form-control-sm hwlib-si-qa-pn" placeholder="PN" style="max-width:120px">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="hwSiQuickAddSave(this)">Add</button>
          </div>
        </div>
      </div>`;
  }

  function hwSetAddItemRow(row) {
    document.getElementById('hwlib-set-items').insertAdjacentHTML('beforeend', hwSetItemRowHtml(row));
    if (row?.item_id) {
      const rows = document.querySelectorAll('.hwlib-set-item-row');
      hwSiRenderFunctions(rows[rows.length - 1], row.item_id, (row.functions || []).map(f => f.id));
      hwSiRenderValues(rows[rows.length - 1], row.item_id, row.values || []);
    }
  }

  function hwSetCollectItems() {
    return Array.from(document.querySelectorAll('.hwlib-set-item-row'))
      .map(row => {
        const radios = Array.from(row.querySelectorAll('.hwlib-si-func-radio:checked')).map(r => r.value).filter(Boolean);
        const checks = Array.from(row.querySelectorAll('.hwlib-si-func-cb:checked')).map(c => c.value);

        const values = Array.from(row.querySelectorAll('.hwlib-si-val')).map(el => ({
          variable_id: parseInt(el.dataset.varId, 10),
          value_text: el.dataset.keep !== undefined ? el.dataset.keep : el.value,
        })).filter(v => v.value_text !== '' && v.value_text != null);

        return {
          id: row.dataset.setItemId ? parseInt(row.dataset.setItemId, 10) : undefined,
          values,
          item_id: row.querySelector('.hwlib-si-part').value,
          quantity: parseInt(row.querySelector('.hwlib-si-qty').value || 1, 10),
          series: row.querySelector('.hwlib-si-series').value,
          leaf: row.querySelector('.hwlib-si-leaf').value,
          notes: row.querySelector('.hwlib-si-notes').value || null,
          function_ids: [...radios, ...checks].map(v => parseInt(v, 10)),
        };
      })
      .filter(r => r.item_id);
  }

  function hwSetOpenModal(id) {
    const s = id ? hwFindSet(id) : null;
    document.getElementById('hwlib-set-id').value = id || '';
    document.getElementById('hwlib-set-job').innerHTML = '<option value="">— select —</option>' +
      hwJobs.map(j => `<option value="${j.id}" ${s?.business_job_id == j.id ? 'selected' : ''}>${esc(j.job_name)} — ${esc(j.job_number)}</option>`).join('');
    document.getElementById('hwlib-set-name').value = s?.name || '';
    document.getElementById('hwlib-set-notes').value = s?.notes || '';
    document.getElementById('hwlib-set-ispair').checked = !!s?.is_pair;

    document.getElementById('hwlib-set-items').innerHTML = '';
    (s?.set_items || []).forEach(row => hwSetAddItemRow(row));

    showModal(document.getElementById('hwlib-set-modal'));
  }

  document.getElementById('hwlib-set-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('hwlib-set-id').value;
    const payload = {
      business_job_id: document.getElementById('hwlib-set-job').value,
      name: document.getElementById('hwlib-set-name').value,
      notes: document.getElementById('hwlib-set-notes').value || null,
      is_pair: document.getElementById('hwlib-set-ispair').checked,
    };
    try {
      const res = await authenticatedFetch(id ? `/config/hwlib-sets/${id}` : '/config/hwlib-sets', {
        method: id ? 'PUT' : 'POST', body: JSON.stringify(payload),
      });
      const setId = id || res.set.id;
      await authenticatedFetch(`/config/hwlib-sets/${setId}/items`, {
        method: 'PUT', body: JSON.stringify({ items: hwSetCollectItems() }),
      });
      hideModal(document.getElementById('hwlib-set-modal'));
      await hwlibAdminInit();
    } catch (err) { showNotification(err.message, 'danger'); }
  });

  async function hwSetDelete(id) {
    if (!confirm('Delete this set?')) return;
    try { await authenticatedFetch(`/config/hwlib-sets/${id}`, { method: 'DELETE' }); await hwlibAdminInit(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // Door Catalog PN fields (dc-*-...Pn) autocomplete/validate against
  // Product.part_number — never sku, since a PN here (e.g. "E4544")
  // identifies a part, not one finish's SKU row. Non-blocking: catalog rows
  // are allowed to reference a PN that isn't in inventory yet, same as
  // DoorBomGenerator's own warn-don't-fail resolution at BOM time.
  const PN_MODAL_IDS = [
    'dc-doorType-modal', 'dc-rail-modal', 'dc-railLug-modal', 'dc-midLug-modal',
    'dc-glassSpec-modal', 'dc-sbk-modal', 'dc-tieRod-modal',
  ];

  function initPartNumberHints() {
    PN_MODAL_IDS.forEach(modalId => {
      const modal = document.getElementById(modalId);
      if (!modal) return;

      modal.querySelectorAll('input[id^="dc-"][id$="Pn"]').forEach(input => {
        if (input.dataset.pnHintWired) return;
        input.dataset.pnHintWired = '1';

        const hint = document.createElement('div');
        hint.className = 'form-text pn-match-hint';
        input.insertAdjacentElement('afterend', hint);

        let timer = null;
        input.addEventListener('input', () => {
          clearTimeout(timer);
          timer = setTimeout(() => checkPartNumberHint(input, hint), 350);
        });
      });

      modal.addEventListener('shown.bs.modal', () => {
        modal.querySelectorAll('input[id^="dc-"][id$="Pn"]').forEach(input => {
          const hint = input.nextElementSibling;
          if (hint && hint.classList.contains('pn-match-hint')) checkPartNumberHint(input, hint);
        });
      });
    });
  }

  async function checkPartNumberHint(input, hint) {
    const value = input.value.trim();
    if (!value) { hint.textContent = ''; hint.className = 'form-text pn-match-hint'; return; }
    try {
      const data = await authenticatedFetch(`/config/products/search-by-part-number?q=${encodeURIComponent(value)}`);
      const matches = data.data || [];
      const exact = matches.find(p => (p.part_number || '').toLowerCase() === value.toLowerCase());
      if (exact) {
        hint.textContent = `Matches inventory part ${exact.part_number}${exact.description ? ' — '+exact.description : ''}`;
        hint.className = 'form-text pn-match-hint text-success';
      } else if (matches.length) {
        hint.textContent = `No exact match. Close matches: ${matches.slice(0, 3).map(p => p.part_number).join(', ')}`;
        hint.className = 'form-text pn-match-hint text-warning';
      } else {
        hint.textContent = 'No matching part number in inventory yet.';
        hint.className = 'form-text pn-match-hint text-warning';
      }
    } catch (err) { /* non-blocking — leave hint as-is */ }
  }

  document.addEventListener('DOMContentLoaded', () => {
    window.sessionReady.then(() => {
      cfgLoadTree();
      dcLoadAll();
      hwlibAdminInit();
      initPartNumberHints();
    });
  });
</script>
@endsection
