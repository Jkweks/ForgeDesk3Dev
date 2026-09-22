@extends('layouts.app')

@section('title', 'Frame Builder')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Configurator</div>
          <h1 class="page-title">Frame Builder</h1>
          <p class="text-muted">Configure an opening and generate its frame extrusion / hardware BOM from the catalog</p>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <div class="btn-list">
            <a href="/configurator/admin#tab-configurator-catalog" class="btn btn-outline-secondary" data-permission="configurator.catalog.manage"><i class="ti ti-settings me-1"></i>Catalog Admin</a>
            <button class="btn btn-primary" onclick="fbOpenNewModal()" data-permission="configurator.create"><i class="ti ti-plus me-1"></i>New Configuration</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <main class="page-body">
    <div class="container-xl">
      <div class="row row-cards">
        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Configurations</h3>
              <div class="card-actions">
                <select class="form-select form-select-sm" id="fb-list-job-filter" style="min-width:180px" onchange="fbLoadList()">
                  <option value="">All Jobs</option>
                </select>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead><tr><th>Job</th><th>Scope</th><th>Status</th><th>WO</th></tr></thead>
                <tbody id="fb-list-tbody"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-8" id="fb-detail-col" style="display:none">
          <div class="card mb-3">
            <div class="card-header flex-wrap row-gap-2">
              <div>
                <h3 class="card-title" id="fb-detail-title">—</h3>
                <div class="text-muted" id="fb-detail-subtitle"></div>
              </div>
              <div class="card-actions d-flex flex-wrap align-items-center gap-2">
                <span class="badge" id="fb-status-badge"></span>
                <div class="btn-group" role="group">
                  <button class="btn btn-outline-secondary" id="fb-reserve-toggle-btn" onclick="fbToggleReserve()" style="display:none" data-permission="configurator.release"><i class="ti ti-bookmark me-1"></i>Reserve</button>
                  <button class="btn btn-outline-secondary" id="fb-reserve-btn" onclick="fbCreateReservation()" style="display:none" data-permission="configurator.release"><i class="ti ti-package me-1"></i>Create Reservation</button>
                  <button class="btn btn-success" id="fb-release-btn" onclick="fbRelease()" data-permission="configurator.release"><i class="ti ti-lock me-1"></i>Release</button>
                </div>
                <div class="btn-group" role="group">
                  <button class="btn btn-outline-secondary" onclick="fbOpenDuplicateModal()" data-permission="configurator.create"><i class="ti ti-copy me-1"></i>Duplicate</button>
                  <button class="btn btn-outline-primary" onclick="fbExportPdf()" data-permission="configurator.view"><i class="ti ti-file-download me-1"></i>Export PDF</button>
                  <button class="btn btn-outline-primary" onclick="fbExportCsv()" data-permission="configurator.view"><i class="ti ti-file-spreadsheet me-1"></i>Export CSV</button>
                </div>
              </div>
            </div>
            <div class="card-body py-2 d-none" id="fb-linked-banner">
              <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0 py-2">
                <div><i class="ti ti-link me-1"></i><span id="fb-linked-text"></span></div>
                <div class="btn-list">
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fbUnlink('single')" data-permission="configurator.edit">Unlink This One</button>
                  <button type="button" class="btn btn-sm btn-outline-danger" onclick="fbUnlink('all')" data-permission="configurator.edit">Unlink All</button>
                </div>
              </div>
            </div>
            <div class="card-body" id="fb-validation-errors"></div>
          </div>

          <div class="card mb-3">
            <div class="card-header p-0">
              <ul class="nav nav-tabs" data-bs-toggle="tabs" role="tablist">
                <li class="nav-item" role="presentation">
                  <a href="#fb-tab-opening" class="nav-link active" data-bs-toggle="tab" role="tab">Opening</a>
                </li>
                <li class="nav-item" role="presentation" id="fb-tab-frame-nav" style="display:none">
                  <a href="#fb-tab-frame" class="nav-link" data-bs-toggle="tab" role="tab">Frame</a>
                </li>
                <li class="nav-item" role="presentation" id="fb-tab-door-nav" style="display:none">
                  <a href="#fb-tab-door" class="nav-link" data-bs-toggle="tab" role="tab">Door</a>
                </li>
                <li class="nav-item" role="presentation">
                  <a href="#fb-tab-hardware" class="nav-link" data-bs-toggle="tab" role="tab">Hardware</a>
                </li>
              </ul>
            </div>
            <div class="card-body">
              <div class="tab-content">

                <div class="tab-pane active show" id="fb-tab-opening" role="tabpanel">
                  <div class="d-flex justify-content-end mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fbOpenGlobalSettings()" data-permission="configurator.catalog.manage" title="Global gap settings">
                      <i class="ti ti-settings me-1"></i>Global Settings
                    </button>
                  </div>
                  <form id="fb-opening-form" class="row g-3">
                    <div class="col-md-4">
                      <label class="form-label">Scope</label>
                      <select class="form-select" id="fb-op-scope">
                        <option value="door_and_frame">Door and Frame</option>
                        <option value="frame_only">Frame Only</option>
                        <option value="door_only">Door Only</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Opening Type</label>
                      <select class="form-select" id="fb-op-type" onchange="fbToggleHand()">
                        <option value="single">Single</option>
                        <option value="pair">Pair</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Finish</label>
                      <select class="form-select" id="fb-op-finish">
                        <option value="c2">C2 - Clear Anodized</option>
                        <option value="db">DB - Dark Bronze</option>
                        <option value="bl">BL - Black</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Glazing</label>
                      <select class="form-select" id="fb-op-glazing"></select>
                    </div>

                    <div class="col-12"><hr class="my-2"></div>

                    <div class="col-md-4" id="fb-op-hand-single-wrap">
                      <label class="form-label">Hand</label>
                      <select class="form-select" id="fb-op-hand-single">
                        <option value="lh_inswing">LH Inswing</option>
                        <option value="rh_inswing">RH Inswing</option>
                        <option value="lhr">LHR</option>
                        <option value="rhr">RHR</option>
                      </select>
                    </div>
                    <div class="col-md-4" id="fb-op-hand-pair-wrap" style="display:none">
                      <label class="form-label">Hand (Pair)</label>
                      <select class="form-select" id="fb-op-hand-pair" onchange="fbCheckNonStandardPairHand(this)">
                        <option value="rhr_active">RHR Active</option>
                        <option value="lhra_active">LHR Active</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Hinging</label>
                      <select class="form-select" id="fb-op-hinging" onchange="fbToggleButtHingeFields()">
                        <option value="continuous">Continuous</option>
                        <option value="butt">Butt</option>
                        <option value="pivot_offset">Pivot Offset</option>
                        <option value="pivot_center">Pivot Center</option>
                      </select>
                    </div>
                    <div class="col-md-4" id="fb-op-butt-hinge-count-wrap" style="display:none">
                      <label class="form-label">Number of Hinges</label>
                      <input type="number" step="1" min="2" max="20" class="form-control" id="fb-op-butt-hinge-count">
                    </div>
                    <div class="col-md-4" id="fb-op-hinge-standard-wrap" style="display:none">
                      <label class="form-label">Hinge Spacing Standard</label>
                      <select class="form-select" id="fb-op-hinge-standard"></select>
                    </div>
                    <div class="col-12" id="fb-op-hinge-locations-wrap" style="display:none">
                      <div class="text-muted small" id="fb-op-hinge-locations"></div>
                    </div>

                    <div class="col-12"><hr class="my-2"></div>

                    <div class="col-md-4">
                      <label class="form-label">Door Opening Width (in)</label>
                      <input type="number" step="0.01" class="form-control" id="fb-op-width" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Door Opening Height (in)</label>
                      <input type="number" step="0.01" class="form-control" id="fb-op-height" required>
                    </div>

                    <div class="col-12">
                      <button type="submit" class="btn btn-primary" data-permission="configurator.edit">Save Opening Specs</button>
                    </div>
                  </form>
                </div>

                <div class="tab-pane" id="fb-tab-frame" role="tabpanel">
                  <form id="fb-frame-form" class="mb-4">
                    <div class="row g-3">
                      <div class="col-md-4">
                        <label class="form-label">Frame System</label>
                        <select class="form-select" id="fb-frame-system" onchange="fbFilterSeries()"></select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Frame Series</label>
                        <select class="form-select" id="fb-frame-series" required></select>
                      </div>
                      <div class="col-md-3 form-check form-switch pt-4">
                        <input class="form-check-input" type="checkbox" id="fb-frame-threshold">
                        <label class="form-check-label">Has Threshold</label>
                      </div>
                    </div>

                    <hr>

                    <div class="border rounded p-3">
                      <div class="row g-3">
                        <div class="col-md-3 form-check form-switch pt-4">
                          <input class="form-check-input" type="checkbox" id="fb-frame-transom" onchange="fbToggleTransom()">
                          <label class="form-check-label">Has Transom</label>
                        </div>
                        <div class="col-md-4" id="fb-frame-transom-glazing-wrap" style="display:none">
                          <label class="form-label">Transom Glazing</label>
                          <select class="form-select" id="fb-frame-transom-glazing">
                            <option value="0.25">1/4"</option>
                            <option value="0.5">1/2"</option>
                            <option value="1.0">1"</option>
                          </select>
                        </div>
                        <div class="col-md-4" id="fb-frame-height-wrap" style="display:none">
                          <label class="form-label">Total Frame Height (in)</label>
                          <input type="number" step="0.01" class="form-control" id="fb-frame-height">
                        </div>
                      </div>
                    </div>

                    <hr>

                    <button type="submit" class="btn btn-primary" data-permission="configurator.edit">Save Frame Configuration</button>
                  </form>

                  <hr>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h4 class="mb-0">Frame Parts BOM</h4>
                    <div class="btn-list">
                      <button class="btn btn-outline-primary btn-sm" onclick="fbAddManualPart('frame')" data-permission="configurator.edit"><i class="ti ti-plus me-1"></i>Add Manual Part</button>
                      <button class="btn btn-primary btn-sm" onclick="fbGenerateParts()" data-permission="configurator.edit"><i class="ti ti-refresh me-1"></i>Generate / Recalculate</button>
                    </div>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                      <thead><tr><th>Part</th><th>Product</th><th>Length</th><th>Qty</th><th>Source</th><th class="w-1"></th></tr></thead>
                      <tbody id="fb-parts-tbody"></tbody>
                    </table>
                    <div class="text-muted p-3" id="fb-parts-empty">No parts yet — save a frame series above, then click Generate.</div>
                  </div>
                </div>

                <div class="tab-pane" id="fb-tab-door" role="tabpanel">
                  <form id="fb-door-form" class="row g-3 mb-4">
                    <div class="col-md-4">
                      <label class="form-label">Door Series</label>
                      <select class="form-select" id="fb-door-series" onchange="fbFilterDoorStiles()" required>
                        <option value="STANDARD">Standard</option>
                        <option value="THERMAL">Thermal</option>
                        <option value="MONUMENTAL">Monumental</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Stile Width</label>
                      <select class="form-select" id="fb-door-stile" required></select>
                    </div>
                    <div class="col-md-4 text-muted small d-flex align-items-end pb-2">
                      Handing, hinging, glazing and bottom gap are set on the Opening tab.
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Top Rail</label>
                      <select class="form-select" id="fb-door-toprail" required></select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Bottom Rail</label>
                      <select class="form-select" id="fb-door-botrail" required></select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Mid Rail Qty</label>
                      <select class="form-select" id="fb-door-midqty" onchange="fbToggleMidRail()">
                        <option value="0">None</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                      </select>
                    </div>
                    <div class="col-md-4" id="fb-door-midrail-wrap" style="display:none">
                      <label class="form-label">Mid Rail</label>
                      <select class="form-select" id="fb-door-midrail"></select>
                    </div>
                    <div class="col-md-4" id="fb-door-midloc1-wrap" style="display:none">
                      <label class="form-label">Mid Rail Location #1 (in from bottom)</label>
                      <input type="number" step="0.0001" class="form-control" id="fb-door-midloc1">
                    </div>
                    <div class="col-md-4" id="fb-door-midloc2-wrap" style="display:none">
                      <label class="form-label">Mid Rail Location #2 (in from bottom)</label>
                      <input type="number" step="0.0001" class="form-control" id="fb-door-midloc2">
                    </div>
                    <div class="col-12">
                      <button type="submit" class="btn btn-primary" data-permission="configurator.edit">Save Door Configuration</button>
                    </div>
                  </form>

                  <hr>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h4 class="mb-0">Door Parts BOM</h4>
                    <div class="btn-list">
                      <button class="btn btn-outline-primary btn-sm" onclick="fbAddManualPart('door')" data-permission="configurator.edit"><i class="ti ti-plus me-1"></i>Add Manual Part</button>
                      <button class="btn btn-primary btn-sm" onclick="fbGenerateDoorParts()" data-permission="configurator.edit"><i class="ti ti-refresh me-1"></i>Generate / Recalculate</button>
                    </div>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                      <thead><tr><th>Part</th><th>Product</th><th>Length</th><th>Qty</th><th>Source</th><th class="w-1"></th></tr></thead>
                      <tbody id="fb-door-parts-tbody"></tbody>
                    </table>
                    <div class="text-muted p-3" id="fb-door-parts-empty">No parts yet — save a door configuration above, then click Generate.</div>
                  </div>
                </div>

                <div class="tab-pane" id="fb-tab-hardware" role="tabpanel">
                  <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-3">
                      <label class="form-label">Hardware Set</label>
                      <select class="form-select" id="fb-hw-scope" onchange="fbRenderHwCategorySelect()">
                        <option value="standard">Standard (VOS)</option>
                        <option value="custom">Custom (all)</option>
                      </select>
                    </div>
                  </div>
                  <form id="fb-hardware-add-form" class="row g-2 align-items-end mb-3">
                    <div class="col-md-3">
                      <label class="form-label">Category</label>
                      <select class="form-select" id="fb-hw-category" onchange="fbFilterHwItems()" required></select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Item</label>
                      <select class="form-select" id="fb-hw-item" required></select>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label">Series</label>
                      <select class="form-select" id="fb-hw-series">
                        <option value="Standard">Standard</option>
                        <option value="Thermal">Thermal</option>
                        <option value="Monumental">Monumental</option>
                      </select>
                    </div>
                    <div class="col-md-2" id="fb-hw-leaf-wrap" style="display:none">
                      <label class="form-label">Leaf</label>
                      <select class="form-select" id="fb-hw-leaf">
                        <option value="both">Both</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                      </select>
                    </div>
                    <div class="col-md-1">
                      <label class="form-label">Qty</label>
                      <input type="number" class="form-control" id="fb-hw-qty" value="1" min="1">
                    </div>
                    <div class="col-md-1">
                      <button type="submit" class="btn btn-primary w-100" data-permission="configurator.edit"><i class="ti ti-plus"></i></button>
                    </div>
                  </form>

                  <table class="table table-vcenter card-table">
                    <thead><tr><th>Item</th><th>Category</th><th>Series</th><th>Leaf</th><th>Qty</th><th class="w-1"></th></tr></thead>
                    <tbody id="fb-hw-links-tbody"></tbody>
                  </table>
                  <div class="text-muted p-3" id="fb-hw-links-empty">No hardware linked yet.</div>

                  <div id="fb-hw-resolved-wrap" style="display:none">
                    <hr>
                    <h4>Resolved Prep Values</h4>
                    <div id="fb-hw-resolved"></div>
                  </div>

                  <hr>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h4 class="mb-0">Hardware BOM</h4>
                    <button class="btn btn-primary btn-sm" onclick="fbGenerateHardwareParts()" data-permission="configurator.edit"><i class="ti ti-refresh me-1"></i>Generate / Recalculate</button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                      <thead><tr><th>Part</th><th>Product</th><th>Qty</th><th>Source</th><th class="w-1"></th></tr></thead>
                      <tbody id="fb-hw-parts-tbody"></tbody>
                    </table>
                    <div class="text-muted p-3" id="fb-hw-parts-empty">No parts yet — link hardware above, then click Generate.</div>
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

<!-- New Configuration Modal -->
<div class="modal modal-blur fade" id="fb-new-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="fb-new-form">
        <div class="modal-header"><h5 class="modal-title">New Door/Frame Configuration</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Job</label>
            <select class="form-select" id="fb-new-job" required></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Scope</label>
            <select class="form-select" id="fb-new-scope">
              <option value="door_and_frame">Door and Frame</option>
              <option value="frame_only">Frame Only</option>
              <option value="door_only">Door Only</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Door Tags (comma separated)</label>
            <input type="text" class="form-control" id="fb-new-tags" placeholder="D1, D2" required>
            <div class="form-hint">One tag per physical opening — this also sets the quantity (2 tags = qty 2) and becomes this configuration's name.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Global Settings Modal -->
<div class="modal modal-blur fade" id="fb-settings-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="fb-settings-form">
        <div class="modal-header">
          <h5 class="modal-title">Global Configurator Settings</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">
            Default gaps applied across every opening. Bottom gap is the only one
            a generated BOM currently uses (door stile length); the rest are
            recorded ahead of the calculations that will use them.
          </p>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Top Gap (in)</label>
              <input type="number" step="0.0001" class="form-control" id="fb-settings-top-gap" required>
            </div>
            <div class="col-6">
              <label class="form-label">Bottom Gap (in)</label>
              <input type="number" step="0.0001" class="form-control" id="fb-settings-bottom-gap" required>
            </div>
            <div class="col-6">
              <label class="form-label">Hinge Gap (in)</label>
              <input type="number" step="0.0001" class="form-control" id="fb-settings-hinge-gap" required>
            </div>
            <div class="col-6">
              <label class="form-label">Lock Gap (in)</label>
              <input type="number" step="0.0001" class="form-control" id="fb-settings-lock-gap" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bulk Duplicate Modal -->
<div class="modal modal-blur fade" id="fb-duplicate-modal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="fb-duplicate-form">
        <div class="modal-header"><h5 class="modal-title">Duplicate Configuration</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <p class="text-muted small mb-3">
            Each row creates a full copy of this configuration's opening, frame, door and hardware
            data under new door tag(s). Check "Flip Hand" on a row to swap LH/RH throughout the copy
            instead of duplicating it exactly.
          </p>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th style="width:55%">New Door Tag(s)</th><th style="width:25%">Flip Hand</th><th></th></tr></thead>
              <tbody id="fb-duplicate-rows"></tbody>
            </table>
          </div>
          <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="fbAddDuplicateRow()">
            <i class="ti ti-plus me-1"></i>Add Row
          </button>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="fb-duplicate-link" checked>
            <label class="form-check-label" for="fb-duplicate-link">
              Keep these linked — warn before an edit to any one of them diverges it from the others
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="fb-duplicate-submit">Duplicate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Part Edit Modal -->
<div class="modal modal-blur fade" id="fb-part-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="fb-part-form">
        <div class="modal-header"><h5 class="modal-title">Edit Part</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="fb-part-id">
          <input type="hidden" id="fb-part-kind" value="frame">
          <div class="mb-3"><label class="form-label">Label</label><input type="text" class="form-control" id="fb-part-label" required></div>
          <div class="mb-3"><label class="form-label">Product</label><select class="form-select" id="fb-part-product" required></select></div>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Unit Type</label>
              <select class="form-select" id="fb-part-unittype">
                <option value="length">Length</option>
                <option value="qty">Quantity</option>
              </select>
            </div>
            <div class="col-md-6 mb-3"><label class="form-label">Length / Qty</label><input type="number" step="0.001" class="form-control" id="fb-part-amount" required></div>
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
let fbConfigs = [];
let fbSelectedId = null;
let fbSelectedDetail = null;
let fbCatalogTree = [];
let fbProducts = [];
let fbDoorCatalog = null;

function esc(s) {
  const d = document.createElement('div');
  d.textContent = s ?? '';
  return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

async function fbLoadProducts() {
  if (fbProducts.length) return fbProducts;
  try {
    const data = await authenticatedFetch('/products?per_page=500');
    fbProducts = data.data || [];
  } catch (e) { fbProducts = []; }
  return fbProducts;
}
function fbProductOptions(selectedId) {
  return '<option value="">— select —</option>' + fbProducts.map(p =>
    `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${esc(p.part_number || p.sku)} — ${esc(p.description || '')}</option>`
  ).join('');
}

async function fbLoadJobsInto(select) {
  const data = await authenticatedFetch('/business-jobs');
  select.innerHTML = (data.jobs || []).map(j => `<option value="${j.id}">${esc(j.job_number)} — ${esc(j.job_name)}</option>`).join('');
}

async function fbLoadCatalogTree() {
  if (fbCatalogTree.length) return fbCatalogTree;
  const data = await authenticatedFetch('/configurator/catalog/tree');
  fbCatalogTree = data.frame_systems || [];
  const systemSelect = document.getElementById('fb-frame-system');
  systemSelect.innerHTML = '<option value="">All Systems</option>' + fbCatalogTree.map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
  return fbCatalogTree;
}

async function fbLoadDoorCatalog() {
  if (fbDoorCatalog) return fbDoorCatalog;
  fbDoorCatalog = await authenticatedFetch('/configurator/door-catalog');
  return fbDoorCatalog;
}

// Display-only — underlying values (used for lookups/validation) stay as
// the catalog's stored casing; only the label text shown to the user changes.
function fbTitleCase(str) {
  return String(str ?? '').toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
}

function fbFilterDoorStiles() {
  const series = document.getElementById('fb-door-series').value;
  const stileSelect = document.getElementById('fb-door-stile');
  const stiles = (fbDoorCatalog?.door_types || []).filter(t => t.series === series);
  const current = fbSelectedDetail?.door_config?.stile_width;
  stileSelect.innerHTML = stiles.map(t => `<option value="${esc(t.stile_name)}" ${t.stile_name === current ? 'selected' : ''}>${esc(fbTitleCase(t.stile_name))}</option>`).join('');
}

function fbPopulateRailSelects() {
  const rails = fbDoorCatalog?.rails || [];
  const dc = fbSelectedDetail?.door_config;
  const byType = (type) => rails.filter(r => r.rail_type === type);
  const opts = (list, current) => list.map(r => `<option value="${esc(r.label)}" ${r.label === current ? 'selected' : ''}>${esc(r.label)}</option>`).join('');
  document.getElementById('fb-door-toprail').innerHTML = opts(byType('top'), dc?.top_rail_label);
  document.getElementById('fb-door-botrail').innerHTML = opts(byType('bot'), dc?.bot_rail_label || '10"');
  document.getElementById('fb-door-midrail').innerHTML = '<option value="">— none —</option>' + opts(byType('mid'), dc?.mid_rail_label);
}

function fbPopulateGlazingSelect() {
  const glassSpecs = fbDoorCatalog?.glass_specs || [];
  const current = fbSelectedDetail?.opening_specs?.glazing;
  document.getElementById('fb-op-glazing').innerHTML =
    '<option value="">— select —</option>' + glassSpecs.map(g => `<option value="${esc(g.thickness)}" ${g.thickness === current ? 'selected' : ''}>${esc(g.thickness)}</option>`).join('');
}

function fbPopulateHingeStandardSelect() {
  const standards = fbDoorCatalog?.hinge_spacing_standards || [];
  const current = fbSelectedDetail?.opening_specs?.hinge_spacing_standard_id;
  document.getElementById('fb-op-hinge-standard').innerHTML =
    '<option value="">— select —</option>' + standards.map(s => `<option value="${s.id}" ${s.id == current ? 'selected' : ''}>${esc(s.name)}</option>`).join('');
}

function fbToggleButtHingeFields() {
  const isButt = document.getElementById('fb-op-hinging').value === 'butt';
  document.getElementById('fb-op-butt-hinge-count-wrap').style.display = isButt ? '' : 'none';
  document.getElementById('fb-op-hinge-standard-wrap').style.display = isButt ? '' : 'none';
  document.getElementById('fb-op-hinge-locations-wrap').style.display = isButt ? '' : 'none';
}

function fbRenderHingeLocations() {
  const locations = fbSelectedDetail?.opening_specs?.hinge_locations || [];
  const wrap = document.getElementById('fb-op-hinge-locations');
  if (!locations.length) { wrap.innerHTML = ''; return; }
  wrap.innerHTML = '<strong>Hinge prep locations (from door top):</strong><br>' +
    locations.map(l => `${esc(l.label)}: ${l.distance_from_top.toFixed(3)}"`).join(' &nbsp;|&nbsp; ');
}

function fbToggleMidRail() {
  const midQty = parseInt(document.getElementById('fb-door-midqty').value || 0, 10);
  document.getElementById('fb-door-midrail-wrap').style.display = midQty > 0 ? '' : 'none';
  document.getElementById('fb-door-midloc1-wrap').style.display = midQty >= 1 ? '' : 'none';
  document.getElementById('fb-door-midloc2-wrap').style.display = midQty >= 2 ? '' : 'none';
}

// ---- Hardware ----
let fbHwCategories = [];

async function fbLoadHwCatalog() {
  if (fbHwCategories.length) { fbRenderHwCategorySelect(); return fbHwCategories; }
  const data = await authenticatedFetch('/configurator/hwlib-catalog');
  fbHwCategories = (data.categories || []).slice().sort((a, b) => a.name.localeCompare(b.name));
  fbRenderHwCategorySelect();
  return fbHwCategories;
}

// A "Butt Hinge"/"Continuous Hinge" category only shows when the door's
// configured hinge_type matches it — other categories are never filtered by
// hinging. If the door config hasn't been saved yet, nothing is hidden.
function fbHwCategoryAllowedForHinging(categoryName) {
  const isButt = /butt hinge/i.test(categoryName);
  const isContinuous = /continuous hinge/i.test(categoryName);
  if (!isButt && !isContinuous) return true;
  const hingeType = fbSelectedDetail?.door_config?.hinge_type;
  if (!hingeType) return true;
  if (isButt) return hingeType === 'BUTT HINGES';
  if (isContinuous) return hingeType === 'CONTINUOUS HINGE';
  return true;
}

// Categories with subcategories (e.g. Cylinders -> Rim/Mortise/Cores/Rings)
// flatten into one option per subcategory, labeled "Category - Subcategory";
// a category with no subcategories (or with items that aren't assigned one)
// keeps/gets a plain "Category" option. Option values are "c<id>" (category,
// no subcategory filter) or "s<id>" (a specific subcategory).
function fbBuildHwCategoryOptions() {
  const scope = document.getElementById('fb-hw-scope').value;
  const matchesScope = i => scope !== 'standard' || i.vos_standard;
  const options = [];
  fbHwCategories.forEach(c => {
    if (!fbHwCategoryAllowedForHinging(c.name)) return;
    const items = c.items || [];
    const subs = c.subcategories || [];
    subs.forEach(s => {
      const subItems = items.filter(i => i.subcategory_id == s.id);
      if (subItems.some(matchesScope)) options.push({ value: `s${s.id}`, label: `${c.name} - ${s.name}`, categoryId: c.id, subcategoryId: s.id });
    });
    const uncategorized = items.filter(i => !i.subcategory_id);
    if (uncategorized.some(matchesScope)) options.push({ value: `c${c.id}`, label: c.name, categoryId: c.id, subcategoryId: null });
  });
  return options;
}

function fbRenderHwCategorySelect() {
  const catSelect = document.getElementById('fb-hw-category');
  const current = catSelect.value;
  const options = fbBuildHwCategoryOptions();
  catSelect.innerHTML = options.map(o => `<option value="${o.value}">${esc(o.label)}</option>`).join('');
  if (options.some(o => o.value === current)) catSelect.value = current;
  fbFilterHwItems();
}

function fbFilterHwItems() {
  const value = document.getElementById('fb-hw-category').value;
  const selected = fbBuildHwCategoryOptions().find(o => o.value === value);
  const cat = selected ? fbHwCategories.find(c => c.id == selected.categoryId) : null;
  const scope = document.getElementById('fb-hw-scope').value;
  let items = (cat?.items || []).filter(i => scope !== 'standard' || i.vos_standard);
  if (selected) {
    items = selected.subcategoryId
      ? items.filter(i => i.subcategory_id == selected.subcategoryId)
      : items.filter(i => !i.subcategory_id);
  }
  const itemSelect = document.getElementById('fb-hw-item');
  itemSelect.innerHTML = items.map(i =>
    `<option value="${i.id}">${esc(i.name)}${i.pn ? ' — ' + esc(i.pn) : ''}</option>`
  ).join('');

  // Butt-hinge hardware quantity always matches the hinge count set on the
  // Opening tab — it's read-only here; change it on Opening instead.
  const qtyInput = document.getElementById('fb-hw-qty');
  const isButtHinge = /butt hinge/i.test(cat?.name || '');
  qtyInput.readOnly = isButtHinge;
  if (isButtHinge) {
    const buttHingeCount = fbSelectedDetail?.opening_specs?.butt_hinge_count;
    if (buttHingeCount) qtyInput.value = buttHingeCount;
  }
}

function fbSelectButtHingeCategory() {
  const match = fbHwCategories.find(c => /butt hinge/i.test(c.name));
  if (!match) return;
  const opt = fbBuildHwCategoryOptions().find(o => o.categoryId == match.id);
  if (!opt) return;
  document.getElementById('fb-hw-category').value = opt.value;
  fbFilterHwItems();
}

function fbRenderHwLinks(links) {
  const tbody = document.getElementById('fb-hw-links-tbody');
  document.getElementById('fb-hw-links-empty').style.display = links.length ? 'none' : 'block';
  tbody.innerHTML = links.map(l => `
    <tr>
      <td>${esc(l.item.name)}${l.item.pn ? '<div class="text-muted small">' + esc(l.item.pn) + '</div>' : ''}</td>
      <td>${esc(l.item.category.name)}${l.item.subcategory ? ' - ' + esc(l.item.subcategory.name) : ''}</td>
      <td>${esc(l.series)}</td>
      <td>${esc(l.leaf)}</td>
      <td>${l.quantity}</td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-icon text-danger" onclick="fbDeleteHwLink(${l.id})" data-permission="configurator.edit"><i class="ti ti-trash"></i></button>
      </td>
    </tr>`).join('');
  applyActionPermissions();
}

document.getElementById('fb-hardware-add-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = {
    item_id: document.getElementById('fb-hw-item').value,
    series: document.getElementById('fb-hw-series').value,
    leaf: document.getElementById('fb-hw-leaf').value,
    quantity: parseInt(document.getElementById('fb-hw-qty').value || 1, 10),
  };
  if (!payload.item_id) { showNotification('Select an item first', 'warning'); return; }
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/hardware-links`, { method: 'POST', body: JSON.stringify(payload) });
    await fbLoadDetail();
    const genRes = await fbGenerateWithDiffPrompt('hardware');
    if (genRes) showNotification('Hardware parts generated', 'success');
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
});

async function fbDeleteHwLink(linkId) {
  if (!confirm('Remove this hardware item?')) return;
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/hardware-links/${linkId}`, { method: 'DELETE' });
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

async function fbLoadHwResolvedValues() {
  try {
    const data = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/hardware-values`);
    const wrap = document.getElementById('fb-hw-resolved-wrap');
    const links = (data.links || []).filter(l => l.values.length);
    wrap.style.display = links.length ? '' : 'none';
    document.getElementById('fb-hw-resolved').innerHTML = links.map(l => `
      <div class="mb-2">
        <div class="fw-bold">${esc(l.item_name)}</div>
        <div class="d-flex flex-wrap gap-3 small text-muted">
          ${l.values.map(v => `<span>${esc(v.label)}: <strong class="text-body">${v.value ?? '—'}${v.unit ? esc(v.unit) : ''}</strong>${v.overridden ? ' <span class="badge bg-yellow-lt">override</span>' : ''}</span>`).join('')}
        </div>
      </div>`).join('');
  } catch (err) { /* non-fatal — resolved values are a convenience display */ }
}

function fbRenderHwParts(parts) {
  const tbody = document.getElementById('fb-hw-parts-tbody');
  document.getElementById('fb-hw-parts-empty').style.display = parts.length ? 'none' : 'block';
  const sourceBadge = { item: 'bg-blue-lt', backer: 'bg-azure-lt', fastener: 'bg-purple-lt', manual: 'bg-secondary-lt' };
  tbody.innerHTML = parts.map(p => `
    <tr>
      <td>${esc(p.formatted_label)}</td>
      <td>${esc(p.product.part_number)}<div class="text-muted small">${esc(p.product.description || '')}</div></td>
      <td>${p.quantity}</td>
      <td><span class="badge ${sourceBadge[p.source_type] || 'bg-secondary-lt'}">${esc(p.source_type)}</span></td>
      <td class="text-end">
        ${!p.is_auto_generated ? `<button type="button" class="btn btn-sm btn-icon text-danger" onclick="fbDeleteHwPart(${p.id})" data-permission="configurator.edit"><i class="ti ti-trash"></i></button>` : ''}
      </td>
    </tr>`).join('');
  applyActionPermissions();
}

async function fbGenerateHardwareParts() {
  try {
    const res = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/hardware-parts/generate`, { method: 'POST' });
    showNotification('Hardware parts generated', 'success');
    if (res.warnings && res.warnings.length) {
      showNotification(`${res.warnings.length} PN(s) could not be matched to a product — see console.`, 'warning');
      console.warn('Hardware BOM warnings:', res.warnings);
    }
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

async function fbDeleteHwPart(partId) {
  if (!confirm('Remove this part?')) return;
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/hardware-parts/${partId}`, { method: 'DELETE' });
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

function fbFilterSeries() {
  const systemId = document.getElementById('fb-frame-system').value;
  const seriesSelect = document.getElementById('fb-frame-series');
  const all = [];
  fbCatalogTree.forEach(sys => {
    if (systemId && sys.id != systemId) return;
    (sys.series || []).forEach(ser => all.push({ id: ser.id, label: `${sys.name} — ${ser.name}` }));
  });
  const current = fbSelectedDetail?.frame_config?.frame_series?.id;
  seriesSelect.innerHTML = all.map(s => `<option value="${s.id}" ${s.id == current ? 'selected' : ''}>${esc(s.label)}</option>`).join('');
}

async function fbLoadJobFilterOptions() {
  const select = document.getElementById('fb-list-job-filter');
  const current = select.value;
  const data = await authenticatedFetch('/business-jobs');
  select.innerHTML = '<option value="">All Jobs</option>' +
    (data.jobs || []).map(j => `<option value="${j.id}">${esc(j.job_number)} — ${esc(j.job_name)}</option>`).join('');
  select.value = current;
}

async function fbLoadList() {
  const jobId = document.getElementById('fb-list-job-filter').value;
  const qs = jobId ? `?business_job_id=${jobId}` : '';
  const data = await authenticatedFetch(`/door-frame-configurations${qs}`);
  fbConfigs = data.configurations || [];
  fbRenderList();
}

function fbStatusBadgeClass(status) {
  if (status === 'reserved') return 'bg-blue-lt';
  if (status === 'released' || status === 'in_progress' || status === 'completed') return 'bg-green-lt';
  return 'bg-yellow-lt';
}

function fbRenderList() {
  const tbody = document.getElementById('fb-list-tbody');
  tbody.innerHTML = fbConfigs.map(c => `
    <tr class="${c.id == fbSelectedId ? 'table-active' : ''}" style="cursor:pointer" onclick="fbSelect(${c.id})">
      <td>${esc(c.job_number)}<div class="text-muted small">${esc(c.door_tags)}</div></td>
      <td>${esc(c.scope_label)}</td>
      <td><span class="badge ${fbStatusBadgeClass(c.status)}">${esc(c.status_label)}</span></td>
      <td>${c.work_order_release_token ? `<span class="badge bg-blue-lt">${esc(c.work_order_release_token)}</span>` : '<span class="text-muted">—</span>'}</td>
    </tr>`).join('') || '<tr><td colspan="4" class="text-muted">No configurations yet.</td></tr>';
}

async function fbSelect(id) {
  fbSelectedId = id;
  fbRenderList();
  document.getElementById('fb-detail-col').style.display = '';
  await fbLoadCatalogTree();
  await fbLoadDoorCatalog();
  await fbLoadHwCatalog();
  await fbLoadDetail();
}

async function fbLoadDetail() {
  const data = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}`);
  fbSelectedDetail = data.configuration;
  fbRenderDetail();
}

function fbRenderDetail() {
  const c = fbSelectedDetail;
  document.getElementById('fb-detail-title').textContent = `${c.business_job.job_number} — ${(c.door_tags || []).join(', ') || c.scope_label}`;
  const woLabel = c.work_order ? ` · WO ${c.work_order.release_token}` : ' · No work order yet';
  const resLabel = c.job_reservation ? ` · Reservation ${c.job_reservation.reservation_id}` : '';
  document.getElementById('fb-detail-subtitle').textContent = `${c.business_job.job_name} · Qty ${c.quantity} · ${c.door_tags.join(', ')}${woLabel}${resLabel}`;
  const badge = document.getElementById('fb-status-badge');
  badge.textContent = c.status_label;
  badge.className = 'badge ' + fbStatusBadgeClass(c.status);
  const isDraftOrReserved = c.status === 'draft' || c.status === 'reserved';
  const reserveToggleBtn = document.getElementById('fb-reserve-toggle-btn');
  reserveToggleBtn.style.display = isDraftOrReserved ? '' : 'none';
  if (c.status === 'reserved') {
    reserveToggleBtn.classList.remove('btn-outline-secondary');
    reserveToggleBtn.classList.add('btn-primary');
    reserveToggleBtn.innerHTML = '<i class="ti ti-bookmark-off me-1"></i>Reserved';
  } else {
    reserveToggleBtn.classList.remove('btn-primary');
    reserveToggleBtn.classList.add('btn-outline-secondary');
    reserveToggleBtn.innerHTML = '<i class="ti ti-bookmark me-1"></i>Reserve';
  }
  document.getElementById('fb-release-btn').style.display = (isDraftOrReserved && c.is_complete) ? '' : 'none';
  document.getElementById('fb-reserve-btn').style.display = (c.status === 'released' && !c.job_reservation) ? '' : 'none';

  const errBox = document.getElementById('fb-validation-errors');
  errBox.innerHTML = (c.validation_errors && c.validation_errors.length)
    ? `<div class="alert alert-warning mb-0"><strong>Incomplete:</strong> ${c.validation_errors.map(esc).join(', ')}</div>` : '';

  const linkedBanner = document.getElementById('fb-linked-banner');
  if (c.duplicate_group_id && (c.linked_siblings || []).length) {
    linkedBanner.classList.remove('d-none');
    const tags = c.linked_siblings.map(s => s.door_tags.join('/') || ('#' + s.id)).join(', ');
    document.getElementById('fb-linked-text').textContent =
      `Linked to ${c.linked_siblings.length} other configuration(s): ${tags}. Edits here won't warn siblings — unlink before diverging this one.`;
  } else {
    linkedBanner.classList.add('d-none');
  }

  const includesFrame = ['door_and_frame', 'frame_only'].includes(c.job_scope);
  const includesDoor = ['door_and_frame', 'door_only'].includes(c.job_scope);
  document.getElementById('fb-tab-frame-nav').style.display = includesFrame ? '' : 'none';
  document.getElementById('fb-tab-door-nav').style.display = includesDoor ? '' : 'none';
  // If the currently-active tab just got hidden (e.g. scope changed), fall back to Opening.
  const activeTabLink = document.querySelector('#fb-detail-col .nav-link.active');
  if (activeTabLink && activeTabLink.closest('.nav-item').style.display === 'none') {
    const openingTab = document.querySelector('a[href="#fb-tab-opening"]');
    if (window.bootstrap?.Tab) new bootstrap.Tab(openingTab).show();
  }

  const editable = c.can_edit;
  document.querySelectorAll('#fb-opening-form input, #fb-opening-form select, #fb-frame-form input, #fb-frame-form select, #fb-door-form input, #fb-door-form select').forEach(el => el.disabled = !editable);

  // Opening specs
  const os = c.opening_specs;
  document.getElementById('fb-op-scope').value = c.job_scope || 'door_and_frame';
  document.getElementById('fb-op-type').value = os?.opening_type || 'single';
  fbToggleHand();
  document.getElementById('fb-op-hand-single').value = os?.hand_single || 'lh_inswing';
  document.getElementById('fb-op-hand-pair').value = os?.hand_pair || 'rhr_active';
  document.getElementById('fb-op-hinging').value = os?.hinging || 'continuous';
  document.getElementById('fb-op-butt-hinge-count').value = os?.butt_hinge_count ?? 2;
  fbPopulateHingeStandardSelect();
  fbToggleButtHingeFields();
  fbRenderHingeLocations();
  document.getElementById('fb-op-width').value = os
    ? (os.door_opening_width ?? '')
    : ((os?.opening_type || 'single') === 'pair' ? 72 : 36);
  fdRefresh('fb-op-width');
  document.getElementById('fb-op-height').value = os ? (os.door_opening_height ?? '') : 84;
  fdRefresh('fb-op-height');
  document.getElementById('fb-op-finish').value = os?.finish || 'c2';
  fbPopulateGlazingSelect();

  // Frame config
  fbFilterSeries();
  const fc = c.frame_config;
  document.getElementById('fb-frame-system').value = fc?.frame_series?.frame_system?.id || '';
  fbFilterSeries();
  document.getElementById('fb-frame-transom').checked = !!fc?.has_transom;
  document.getElementById('fb-frame-threshold').checked = !!fc?.has_threshold;
  document.getElementById('fb-frame-transom-glazing').value = fc?.transom_glazing || '0.25';
  document.getElementById('fb-frame-height').value = fc?.total_frame_height ?? '';
  fdRefresh('fb-frame-height');
  fbToggleTransom();

  fbRenderParts('fb-parts-tbody', 'fb-parts-empty', fc?.parts || [], 'frame');

  // Door config
  if (includesDoor) {
    const dc = c.door_config;
    document.getElementById('fb-door-series').value = dc?.door_series || 'STANDARD';
    fbFilterDoorStiles();
    document.getElementById('fb-door-stile').value = dc?.stile_width || '';
    fbPopulateRailSelects();
    document.getElementById('fb-door-midqty').value = dc?.mid_qty ?? 0;
    document.getElementById('fb-door-midloc1').value = dc?.mid_loc1 ?? '';
    fdRefresh('fb-door-midloc1');
    document.getElementById('fb-door-midloc2').value = dc?.mid_loc2 ?? '';
    fdRefresh('fb-door-midloc2');
    fbToggleMidRail();

    fbRenderParts('fb-door-parts-tbody', 'fb-door-parts-empty', dc?.parts || [], 'door');
  }

  // Hardware
  document.getElementById('fb-hw-leaf-wrap').style.display = c.opening_specs?.opening_type === 'pair' ? '' : 'none';
  fbRenderHwCategorySelect();
  fbRenderHwLinks(c.hardware_links || []);
  fbRenderHwParts(c.hardware_parts || []);
  if ((c.hardware_links || []).length) {
    fbLoadHwResolvedValues();
  } else {
    document.getElementById('fb-hw-resolved-wrap').style.display = 'none';
  }
}

function fbCheckNonStandardPairHand(sel) {
  if (sel.value === 'lhra_active') {
    if (!confirm('"LHR Active" is a non-standard configuration. Please verify this is correct before continuing.')) {
      sel.value = 'rhr_active';
    }
  }
}

function fbToggleHand() {
  const isPair = document.getElementById('fb-op-type').value === 'pair';
  document.getElementById('fb-op-hand-single-wrap').style.display = isPair ? 'none' : '';
  document.getElementById('fb-op-hand-pair-wrap').style.display = isPair ? '' : 'none';
  document.getElementById('fb-hw-leaf-wrap').style.display = isPair ? '' : 'none';

  // If the width is still sitting at the untouched default for the other
  // opening type, flip it to this type's default too — never overwrites a
  // value the user actually typed.
  const widthInput = document.getElementById('fb-op-width');
  const widthVal = parseFloat(widthInput.value);
  if (widthVal === 36 || widthVal === 72) {
    widthInput.value = isPair ? 72 : 36;
    fdRefresh('fb-op-width');
  }
}
function fbToggleTransom() {
  const on = document.getElementById('fb-frame-transom').checked;
  document.getElementById('fb-frame-transom-glazing-wrap').style.display = on ? '' : 'none';
  document.getElementById('fb-frame-height-wrap').style.display = on ? '' : 'none';
}

function fbRenderParts(tbodyId, emptyId, parts, kind) {
  const tbody = document.getElementById(tbodyId);
  document.getElementById(emptyId).style.display = parts.length ? 'none' : 'block';
  const sourceBadge = { profile: 'bg-blue-lt', component: 'bg-azure-lt', fastener: 'bg-purple-lt', extrusion: 'bg-blue-lt', manual: 'bg-secondary-lt' };
  tbody.innerHTML = parts.map(p => `
    <tr>
      <td>${esc(p.formatted_label)}</td>
      <td>${esc(p.product.part_number)}<div class="text-muted small">${esc(p.product.description || '')}</div></td>
      <td>${p.unit_type === 'length' ? (p.calculated_length ?? '—') + '"' : '—'}</td>
      <td>${p.unit_type === 'qty' ? p.quantity : '—'}</td>
      <td><span class="badge ${sourceBadge[p.source_type] || 'bg-secondary-lt'}">${esc(p.source_type)}</span></td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-icon" onclick="fbOpenPartModal('${kind}', ${p.id})" data-permission="configurator.edit"><i class="ti ti-pencil"></i></button>
        ${!p.is_auto_generated ? `<button type="button" class="btn btn-sm btn-icon text-danger" onclick="fbDeletePart('${kind}', ${p.id})" data-permission="configurator.edit"><i class="ti ti-trash"></i></button>` : ''}
      </td>
    </tr>`).join('');
  applyActionPermissions();
}

// ---- Save-and-continue: auto-advance + auto-generate-on-first-save ----

// Jumps to the next relevant tab after a section save, skipping Frame/Door
// when the current scope doesn't include them. Hardware is always last —
// there's nowhere further to advance to from there.
function fbAdvanceTab(fromTab) {
  const c = fbSelectedDetail;
  const includesFrame = ['door_and_frame', 'frame_only'].includes(c.job_scope);
  const includesDoor = ['door_and_frame', 'door_only'].includes(c.job_scope);
  const order = ['opening', ...(includesFrame ? ['frame'] : []), ...(includesDoor ? ['door'] : []), 'hardware'];
  const next = order[order.indexOf(fromTab) + 1];
  if (!next) return;
  const link = document.querySelector(`a[href="#fb-tab-${next}"]`);
  if (link && window.bootstrap?.Tab) new bootstrap.Tab(link).show();
}

// Builds a human-readable +/-/~ diff between two BOM row lists, matched by
// part label + part number. Empty string means no meaningful change.
function fbDiffParts(oldParts, newParts) {
  const key = p => `${p.part_label}|${p.product?.part_number}`;
  const oldMap = new Map(oldParts.map(p => [key(p), p]));
  const newMap = new Map(newParts.map(p => [key(p), p]));
  const describe = p => `${p.product?.part_number || '?'} qty ${p.quantity}${p.calculated_length != null ? ', ' + p.calculated_length + '"' : ''}`;
  const lines = [];
  new Set([...oldMap.keys(), ...newMap.keys()]).forEach(k => {
    const o = oldMap.get(k), n = newMap.get(k);
    if (!o) { lines.push(`+ ${n.part_label}: ${describe(n)}`); return; }
    if (!n) { lines.push(`− ${o.part_label}: ${describe(o)}`); return; }
    if (String(o.quantity) !== String(n.quantity) || String(o.calculated_length ?? '') !== String(n.calculated_length ?? '') || o.product?.part_number !== n.product?.part_number) {
      lines.push(`~ ${o.part_label}: ${describe(o)}  →  ${describe(n)}`);
    }
  });
  return lines.join('\n');
}

const FB_GENERATE_ENDPOINTS = {
  frame: 'frame-parts/generate',
  door: 'door-parts/generate',
  hardware: 'hardware-parts/generate',
};

function fbExistingAutoParts(kind) {
  const c = fbSelectedDetail;
  if (kind === 'frame') return (c.frame_config?.parts || []).filter(p => p.is_auto_generated);
  if (kind === 'door') return (c.door_config?.parts || []).filter(p => p.is_auto_generated);
  return (c.hardware_parts || []).filter(p => p.is_auto_generated);
}

// First run for this section: always generate immediately, no prompt. A
// later run (BOM already exists): preview what would change and only
// overwrite the saved BOM if the user confirms — a spec edit made through
// another tab shouldn't silently rewrite parts someone hand-tweaked.
async function fbGenerateWithDiffPrompt(kind) {
  const endpoint = FB_GENERATE_ENDPOINTS[kind];
  const existing = fbExistingAutoParts(kind);

  if (existing.length === 0) {
    return authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/${endpoint}`, { method: 'POST' });
  }

  const preview = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/${endpoint}?preview=1`, { method: 'POST' });
  const diff = fbDiffParts(existing, preview.parts || []);
  if (!diff) {
    return null; // nothing changed — leave the saved BOM alone
  }
  if (!confirm(`The generated ${kind} BOM has changed based on your updated inputs:\n\n${diff}\n\nApply the updated BOM? Cancel keeps the current parts list.`)) {
    return null;
  }
  return authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/${endpoint}`, { method: 'POST' });
}

async function fbUnlink(scope) {
  const msg = scope === 'all'
    ? 'Unlink every configuration in this group? Each one will edit independently from now on.'
    : 'Unlink this configuration from the others? It will edit independently from now on.';
  if (!confirm(msg)) return;
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/unlink`, { method: 'POST', body: JSON.stringify({ scope }) });
    showNotification('Unlinked successfully', 'success');
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

// ---- Bulk duplication ----
let fbDuplicateRowId = 0;

function fbOpenDuplicateModal() {
  document.getElementById('fb-duplicate-rows').innerHTML = '';
  fbDuplicateRowId = 0;
  document.getElementById('fb-duplicate-link').checked = true;
  fbAddDuplicateRow();
  showModal(document.getElementById('fb-duplicate-modal'));
}

function fbAddDuplicateRow() {
  const id = ++fbDuplicateRowId;
  const tr = document.createElement('tr');
  tr.id = `fb-dup-row-${id}`;
  tr.innerHTML = `
    <td><input type="text" class="form-control form-control-sm fb-dup-tags" placeholder="e.g. 105A, 106A"></td>
    <td class="text-center"><input type="checkbox" class="form-check-input fb-dup-flip"></td>
    <td class="text-end">
      <button type="button" class="btn btn-sm btn-ghost-danger" onclick="document.getElementById('fb-dup-row-${id}').remove()">
        <i class="ti ti-x"></i>
      </button>
    </td>`;
  document.getElementById('fb-duplicate-rows').appendChild(tr);
}

// LH/RH counterparts for opening hand, pair hand, and door handing — used to
// flip an entire duplicated opening's handedness in one click instead of
// re-picking every hand-related field by hand.
const FB_HAND_SINGLE_FLIP = { lh_inswing: 'rh_inswing', rh_inswing: 'lh_inswing', lhr: 'rhr', rhr: 'lhr' };
const FB_HAND_PAIR_FLIP = { rhr_active: 'lhra_active', lhra_active: 'rhr_active' };
const FB_DOOR_HANDING_FLIP = {
  'LH (INSWING)': 'RH (INSWING)', 'RH (INSWING)': 'LH (INSWING)',
  LHR: 'RHR', RHR: 'LHR',
  'PAIR-RHRA': 'PAIR-LHRA', 'PAIR-LHRA': 'PAIR-RHRA',
};

function fbFlipHandOverrides() {
  const c = fbSelectedDetail;
  const overrides = {};
  const os = c.opening_specs;
  if (os?.opening_type === 'pair') {
    if (FB_HAND_PAIR_FLIP[os.hand_pair]) overrides.opening_specs = { hand_pair: FB_HAND_PAIR_FLIP[os.hand_pair] };
  } else if (FB_HAND_SINGLE_FLIP[os?.hand_single]) {
    overrides.opening_specs = { hand_single: FB_HAND_SINGLE_FLIP[os.hand_single] };
  }
  const handing = c.door_config?.handing;
  if (FB_DOOR_HANDING_FLIP[handing]) {
    overrides.door_config = { handing: FB_DOOR_HANDING_FLIP[handing] };
  }
  return overrides;
}

document.getElementById('fb-duplicate-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const rows = document.querySelectorAll('#fb-duplicate-rows tr');
  const duplicates = [];
  rows.forEach(row => {
    const tags = row.querySelector('.fb-dup-tags').value.split(',').map(s => s.trim()).filter(Boolean);
    if (!tags.length) return;
    const flip = row.querySelector('.fb-dup-flip').checked;
    duplicates.push({ door_tags: tags, overrides: flip ? fbFlipHandOverrides() : {} });
  });
  if (!duplicates.length) { showNotification('Enter at least one door tag', 'warning'); return; }

  const btn = document.getElementById('fb-duplicate-submit');
  btn.disabled = true;
  btn.textContent = 'Duplicating…';
  try {
    const res = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/duplicate`, {
      method: 'POST',
      body: JSON.stringify({ duplicates, link: document.getElementById('fb-duplicate-link').checked }),
    });
    hideModal(document.getElementById('fb-duplicate-modal'));
    showNotification(res.message, 'success');
    await fbLoadList();
  } catch (err) {
    showNotification(err.message, 'danger');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Duplicate';
  }
});

// ---- Global settings ----
async function fbOpenGlobalSettings() {
  try {
    const data = await authenticatedFetch('/configurator/settings');
    const s = data.settings || {};
    document.getElementById('fb-settings-top-gap').value = s.top_gap ?? 0.125;
    document.getElementById('fb-settings-bottom-gap').value = s.bottom_gap ?? 0.6875;
    document.getElementById('fb-settings-hinge-gap').value = s.hinge_gap ?? 0.0625;
    document.getElementById('fb-settings-lock-gap').value = s.lock_gap ?? 0.0625;
    showModal(document.getElementById('fb-settings-modal'));
  } catch (err) { showNotification(err.message, 'danger'); }
}

document.getElementById('fb-settings-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = {
    top_gap: parseFloat(document.getElementById('fb-settings-top-gap').value),
    bottom_gap: parseFloat(document.getElementById('fb-settings-bottom-gap').value),
    hinge_gap: parseFloat(document.getElementById('fb-settings-hinge-gap').value),
    lock_gap: parseFloat(document.getElementById('fb-settings-lock-gap').value),
  };
  try {
    await authenticatedFetch('/configurator/settings', { method: 'PUT', body: JSON.stringify(payload) });
    hideModal(document.getElementById('fb-settings-modal'));
    showNotification('Global settings saved', 'success');
  } catch (err) { showNotification(err.message, 'danger'); }
});

// ---- New configuration ----
async function fbOpenNewModal() {
  await fbLoadJobsInto(document.getElementById('fb-new-job'));
  document.getElementById('fb-new-form').reset();
  showModal(document.getElementById('fb-new-modal'));
}
document.getElementById('fb-new-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = {
    business_job_id: document.getElementById('fb-new-job').value,
    job_scope: document.getElementById('fb-new-scope').value,
    door_tags: document.getElementById('fb-new-tags').value.split(',').map(s => s.trim()).filter(Boolean),
  };
  try {
    const res = await authenticatedFetch('/door-frame-configurations', { method: 'POST', body: JSON.stringify(payload) });
    hideModal(document.getElementById('fb-new-modal'));
    await fbLoadList();
    await fbSelect(res.configuration.id);
  } catch (err) { showNotification(err.message, 'danger'); }
});

// ---- Opening specs ----
document.getElementById('fb-opening-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const isPair = document.getElementById('fb-op-type').value === 'pair';
  const isButt = document.getElementById('fb-op-hinging').value === 'butt';
  const payload = {
    job_scope: document.getElementById('fb-op-scope').value,
    opening_type: document.getElementById('fb-op-type').value,
    hand_single: isPair ? null : document.getElementById('fb-op-hand-single').value,
    hand_pair: isPair ? document.getElementById('fb-op-hand-pair').value : null,
    door_opening_width: parseFloat(document.getElementById('fb-op-width').value),
    door_opening_height: parseFloat(document.getElementById('fb-op-height').value),
    hinging: document.getElementById('fb-op-hinging').value,
    butt_hinge_count: isButt ? parseInt(document.getElementById('fb-op-butt-hinge-count').value || 2, 10) : null,
    hinge_spacing_standard_id: isButt ? (document.getElementById('fb-op-hinge-standard').value || null) : null,
    finish: document.getElementById('fb-op-finish').value,
    glazing: document.getElementById('fb-op-glazing').value || null,
  };
  if (isButt && !payload.hinge_spacing_standard_id) { showNotification('Select a hinge spacing standard', 'warning'); return; }
  if (payload.door_opening_width < 30 && !confirm(`${payload.door_opening_width}" is narrower than the usual 30" minimum. Please verify this is correct before continuing.`)) return;
  if (payload.door_opening_height < 70 && !confirm(`${payload.door_opening_height}" is shorter than the usual 70" minimum. Please verify this is correct before continuing.`)) return;
  try {
    const res = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/opening-specs`, { method: 'PUT', body: JSON.stringify(payload) });
    showNotification('Opening specs saved', 'success');
    if (res.warnings && res.warnings.length) {
      res.warnings.forEach(w => showNotification(w, 'warning'));
    }
    await fbLoadDetail();

    const hasButtHingeHw = (fbSelectedDetail?.hardware_links || []).some(l => /butt hinge/i.test(l.item?.category?.name || ''));
    if (payload.butt_hinge_count && !hasButtHingeHw && confirm(`Number of hinges set to ${payload.butt_hinge_count}. Select butt hinge hardware now?`)) {
      const hwTabLink = document.querySelector('a[href="#fb-tab-hardware"]');
      if (hwTabLink && window.bootstrap?.Tab) new bootstrap.Tab(hwTabLink).show();
      fbSelectButtHingeCategory();
    } else {
      fbAdvanceTab('opening');
    }
  } catch (err) { showNotification(err.message, 'danger'); }
});

// ---- Frame config ----
document.getElementById('fb-frame-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const hasTransom = document.getElementById('fb-frame-transom').checked;
  const payload = {
    frame_series_id: document.getElementById('fb-frame-series').value,
    has_transom: hasTransom,
    has_threshold: document.getElementById('fb-frame-threshold').checked,
    transom_glazing: hasTransom ? document.getElementById('fb-frame-transom-glazing').value : null,
    total_frame_height: hasTransom ? parseFloat(document.getElementById('fb-frame-height').value || 0) : null,
  };
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/frame-config`, { method: 'PUT', body: JSON.stringify(payload) });
    showNotification('Frame configuration saved', 'success');
    const genRes = await fbGenerateWithDiffPrompt('frame');
    if (genRes) showNotification('Frame parts generated', 'success');
    await fbLoadDetail();
    fbAdvanceTab('frame');
  } catch (err) { showNotification(err.message, 'danger'); }
});

// ---- Door config ----
document.getElementById('fb-door-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const midQty = parseInt(document.getElementById('fb-door-midqty').value || 0, 10);
  const payload = {
    door_series: document.getElementById('fb-door-series').value,
    stile_width: document.getElementById('fb-door-stile').value,
    top_rail_label: document.getElementById('fb-door-toprail').value,
    bot_rail_label: document.getElementById('fb-door-botrail').value,
    mid_rail_label: midQty > 0 ? (document.getElementById('fb-door-midrail').value || null) : null,
    mid_qty: midQty,
    mid_loc1: midQty >= 1 ? parseFloat(document.getElementById('fb-door-midloc1').value || 0) : null,
    mid_loc2: midQty >= 2 ? parseFloat(document.getElementById('fb-door-midloc2').value || 0) : null,
  };
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/door-config`, { method: 'PUT', body: JSON.stringify(payload) });
    showNotification('Door configuration saved', 'success');
    const genRes = await fbGenerateWithDiffPrompt('door');
    if (genRes) {
      showNotification('Door parts generated', 'success');
      if (genRes.warnings && genRes.warnings.length) {
        showNotification(`${genRes.warnings.length} PN(s) could not be matched to a product — see console.`, 'warning');
        console.warn('Door BOM warnings:', genRes.warnings);
      }
    }
    await fbLoadDetail();
    fbAdvanceTab('door');
  } catch (err) { showNotification(err.message, 'danger'); }
});

// ---- BOM ----
async function fbGenerateParts() {
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/frame-parts/generate`, { method: 'POST' });
    showNotification('Parts generated', 'success');
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

async function fbGenerateDoorParts() {
  try {
    const res = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/door-parts/generate`, { method: 'POST' });
    showNotification('Door parts generated', 'success');
    if (res.warnings && res.warnings.length) {
      showNotification(`${res.warnings.length} PN(s) could not be matched to a product — see console.`, 'warning');
      console.warn('Door BOM warnings:', res.warnings);
    }
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

function fbPartsSource(kind) {
  return kind === 'door' ? (fbSelectedDetail.door_config?.parts || []) : (fbSelectedDetail.frame_config?.parts || []);
}
function fbPartsEndpoint(kind) {
  return kind === 'door' ? 'door-parts' : 'frame-parts';
}

async function fbOpenPartModal(kind, id) {
  await fbLoadProducts();
  const part = fbPartsSource(kind).find(p => p.id == id);
  document.getElementById('fb-part-id').value = id;
  document.getElementById('fb-part-kind').value = kind;
  document.getElementById('fb-part-label').value = part.part_label;
  document.getElementById('fb-part-label').disabled = part.is_auto_generated;
  document.getElementById('fb-part-product').innerHTML = fbProductOptions(part.product.id);
  document.getElementById('fb-part-unittype').value = part.unit_type;
  document.getElementById('fb-part-unittype').disabled = part.is_auto_generated;
  document.getElementById('fb-part-amount').value = part.unit_type === 'length' ? part.calculated_length : part.quantity;
  showModal(document.getElementById('fb-part-modal'));
}
function fbAddManualPart(kind) {
  fbLoadProducts().then(() => {
    document.getElementById('fb-part-id').value = '';
    document.getElementById('fb-part-kind').value = kind;
    document.getElementById('fb-part-label').value = '';
    document.getElementById('fb-part-label').disabled = false;
    document.getElementById('fb-part-product').innerHTML = fbProductOptions(null);
    document.getElementById('fb-part-unittype').value = 'length';
    document.getElementById('fb-part-unittype').disabled = false;
    document.getElementById('fb-part-amount').value = '';
    showModal(document.getElementById('fb-part-modal'));
  });
}
document.getElementById('fb-part-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('fb-part-id').value;
  const kind = document.getElementById('fb-part-kind').value;
  const endpoint = fbPartsEndpoint(kind);
  const unitType = document.getElementById('fb-part-unittype').value;
  const amount = parseFloat(document.getElementById('fb-part-amount').value || 0);
  try {
    if (id) {
      await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/${endpoint}/${id}`, {
        method: 'PUT',
        body: JSON.stringify({
          product_id: document.getElementById('fb-part-product').value,
          calculated_length: unitType === 'length' ? amount : null,
          quantity: unitType === 'qty' ? amount : 1,
        }),
      });
    } else if (kind === 'door') {
      // Door parts have no bulk-manual endpoint — generate first, then add via the single-part PUT isn't
      // available pre-creation, so manual door parts are created through frame-parts-style bulk isn't offered;
      // fall back to requiring at least one generate pass first.
      showNotification('Generate door parts at least once before adding a manual part.', 'warning');
      return;
    } else {
      // Frame manual add: merge with existing manual parts and bulk-save.
      const existingManual = fbPartsSource('frame').filter(p => !p.is_auto_generated).map(p => ({
        part_label: p.part_label, product_id: p.product.id, calculated_length: p.calculated_length,
        quantity: p.quantity, unit_type: p.unit_type,
      }));
      existingManual.push({
        part_label: document.getElementById('fb-part-label').value,
        product_id: document.getElementById('fb-part-product').value,
        calculated_length: unitType === 'length' ? amount : null,
        quantity: unitType === 'qty' ? amount : 1,
        unit_type: unitType,
      });
      await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/frame-parts`, {
        method: 'PUT', body: JSON.stringify({ parts: existingManual }),
      });
    }
    hideModal(document.getElementById('fb-part-modal'));
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
});
async function fbDeletePart(kind, id) {
  if (!confirm('Remove this part?')) return;
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/${fbPartsEndpoint(kind)}/${id}`, { method: 'DELETE' });
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

// ---- Reserve ----
function fbToggleReserve() {
  return fbSelectedDetail.status === 'reserved' ? fbUnreserveConfiguration() : fbReserveConfiguration();
}

async function fbReserveConfiguration() {
  try {
    const data = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/reserve`, { method: 'POST' });
    showNotification('Configuration reserved', 'success');
    (data.warnings || []).forEach(w => showNotification(w, 'warning'));
    await fbLoadList();
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

async function fbUnreserveConfiguration() {
  if (!confirm('Move this configuration back to draft? Its reservation will be cancelled and the committed inventory released.')) return;
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/unreserve`, { method: 'POST' });
    showNotification('Configuration moved back to draft', 'success');
    await fbLoadList();
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

// ---- Release ----
async function fbRelease() {
  if (!confirm('Release this configuration to production? Catalog-driven edits will be locked.')) return;
  try {
    await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/release`, { method: 'POST' });
    showNotification('Configuration released', 'success');
    await fbLoadList();
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

async function fbCreateReservation() {
  try {
    const data = await authenticatedFetch(`/door-frame-configurations/${fbSelectedId}/create-reservation`, { method: 'POST' });
    showNotification(`Reservation ${data.job_reservation_number || data.job_reservation_id} created`, 'success');
    await fbLoadDetail();
  } catch (err) { showNotification(err.message, 'danger'); }
}

async function fbExportPdf() {
  try {
    showNotification('Generating cut sheet PDF...', 'info');
    const response = await apiCall(`/door-frame-configurations/${fbSelectedId}/export-pdf`);
    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      showNotification(err.message || 'PDF export failed', 'danger');
      return;
    }
    const blob = await response.blob();
    const disposition = response.headers.get('Content-Disposition') || '';
    const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
    const filename = match ? match[1].replace(/['"]/g, '') : `CutSheet_${fbSelectedId}.pdf`;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  } catch (err) {
    console.error('Cut sheet PDF export error:', err);
    showNotification('Failed to export cut sheet PDF', 'danger');
  }
}

async function fbExportCsv() {
  try {
    showNotification('Generating cut list CSV...', 'info');
    const response = await apiCall(`/door-frame-configurations/${fbSelectedId}/export-csv`);
    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      showNotification(err.message || 'CSV export failed', 'danger');
      return;
    }
    const blob = await response.blob();
    const disposition = response.headers.get('Content-Disposition') || '';
    const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
    const filename = match ? match[1].replace(/['"]/g, '') : `CutList_${fbSelectedId}.csv`;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  } catch (err) {
    console.error('Cut list CSV export error:', err);
    showNotification('Failed to export cut list CSV', 'danger');
  }
}

// ==================== Fractional dimension entry ====================
// Each of these number inputs holds a plain decimal inch value that every
// other bit of JS on this page reads/writes via .value — fdAttach() hides
// the original input and drops a free-text input next to it that accepts
// either a decimal ("3.3125") or a fraction ("3 5/16", "5/16") and keeps
// the hidden input's decimal value in sync, so nothing else on the page
// needs to change. fdRefresh() re-syncs the text whenever code elsewhere
// sets the hidden input's .value directly (e.g. loading a saved configuration).
const FD_DIMENSION_IDS = ['fb-op-width', 'fb-op-height', 'fb-frame-height', 'fb-door-midloc1', 'fb-door-midloc2'];

function fdGcd(a, b) { return b === 0 ? a : fdGcd(b, a % b); }

// Accepts "3.3125", "3 5/16", "3-5/16", "5/16", "36" — anything else is NaN.
function fdParseInches(str) {
  str = String(str ?? '').trim();
  if (str === '') return NaN;

  let m = str.match(/^(-?\d+(?:\.\d+)?)[\s-]+(\d+)\/(\d+)$/);
  if (m) {
    const whole = parseFloat(m[1]);
    const frac = parseFloat(m[2]) / parseFloat(m[3]);
    return whole < 0 ? whole - frac : whole + frac;
  }

  m = str.match(/^(-?\d+)\/(\d+)$/);
  if (m) return parseFloat(m[1]) / parseFloat(m[2]);

  if (/^-?\d+(\.\d+)?$/.test(str)) return parseFloat(str);

  return NaN;
}

// Renders a decimal back out as "whole num/den" (nearest 1/32nd), e.g.
// 3.3125 -> "3 5/16", 0.5 -> "1/2", 36 -> "36".
function fdFormatInches(value) {
  if (value == null || isNaN(value)) return '';
  const negative = value < 0;
  const abs = Math.abs(value);
  const whole = Math.floor(abs);
  let n = Math.round((abs - whole) * 32);
  let wholeOut = whole;
  if (n === 32) { wholeOut += 1; n = 0; }
  const sign = negative ? '-' : '';
  if (n === 0) return `${sign}${wholeOut}`;
  const g = fdGcd(n, 32);
  const fracStr = `${n / g}/${32 / g}`;
  return wholeOut === 0 ? `${sign}${fracStr}` : `${sign}${wholeOut} ${fracStr}`;
}

function fdAttach(id) {
  const original = document.getElementById(id);
  if (!original || original.dataset.fdAttached) return;
  original.dataset.fdAttached = '1';
  original.style.display = 'none';

  const input = document.createElement('input');
  input.type = 'text';
  input.className = 'form-control';
  input.placeholder = 'e.g. 3 5/16 or 3.3125';
  input.autocomplete = 'off';
  original.parentNode.insertBefore(input, original.nextSibling);

  const sync = () => {
    const parsed = fdParseInches(input.value);
    original.value = isNaN(parsed) ? '' : parsed.toString();
    input.classList.toggle('is-invalid', input.value.trim() !== '' && isNaN(parsed));
  };
  input.addEventListener('input', sync);
  input.addEventListener('blur', () => {
    sync();
    if (original.value !== '') input.value = fdFormatInches(parseFloat(original.value));
  });

  fdRefresh(id);
}

function fdRefresh(id) {
  const original = document.getElementById(id);
  const input = original?.nextElementSibling;
  if (!original || !input || input.type !== 'text') return;
  const value = original.value === '' ? NaN : parseFloat(original.value);
  input.value = isNaN(value) ? '' : fdFormatInches(value);
  input.classList.remove('is-invalid');
}

document.addEventListener('DOMContentLoaded', () => {
  // window.sessionReady is defined by partials.auth-scripts, which is
  // included after this page's content in layouts/app — by the time
  // DOMContentLoaded fires the whole document (including that script)
  // has run, so it's safe to reference here unguarded.
  window.sessionReady.then(async () => {
    FD_DIMENSION_IDS.forEach(fdAttach);

    const params = new URLSearchParams(window.location.search);
    const deepLinkJobId = params.get('job');
    const deepLinkConfigId = params.get('config');

    await fbLoadJobFilterOptions();
    if (deepLinkJobId) document.getElementById('fb-list-job-filter').value = deepLinkJobId;
    await fbLoadList();

    if (deepLinkConfigId) await fbSelect(parseInt(deepLinkConfigId, 10));
  });
});
</script>
@endsection
