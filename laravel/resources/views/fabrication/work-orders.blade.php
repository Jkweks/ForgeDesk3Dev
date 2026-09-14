@extends('layouts.app')

@section('title', 'Work Orders – Fabrication')

@section('styles')
.pip { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin: 1px; cursor: pointer; }
.pip-pending     { background: var(--tblr-secondary, #adb5bd); }
.pip-in_progress { background: var(--tblr-warning, #f59f00); }
.pip-complete    { background: var(--tblr-success, #2fb344); }
.pip-blocked      { background: var(--tblr-danger, #d63939); }
.pip-not_required { background: var(--tblr-blue-lt, #e9f0fb); border: 1px solid var(--tblr-blue, #206bc4); }
.pip-on_hold      { background: var(--tblr-orange-lt, #fff4e6); border: 1px solid var(--tblr-orange, #f76707); }
.pip-locked       { box-shadow: 0 0 0 2px var(--tblr-danger, #d63939); opacity: .55; }
.wo-offcanvas    { width: min(920px, 96vw) !important; }
.elev-row td     { vertical-align: middle; }
.wo-detail-header { background: var(--tblr-bg-surface-secondary, var(--tblr-light)); }
.card.bg-light, table.bg-light { background: var(--tblr-bg-surface-secondary) !important; }

/* WO detail panel — consistent section rhythm + tabbed body */
.wo-block        { padding: 1rem 1.25rem; border-bottom: 1px solid var(--tblr-border-color, #e6e7e9); }
.wo-block:last-child { border-bottom: 0; }
.wo-block-head   { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; min-height: 1.75rem; }
.wo-block-head > h6 { margin: 0; font-size: .7rem; letter-spacing: .05em; text-transform: uppercase; font-weight: 600; color: var(--tblr-secondary, #667382); }
#wo-detail .nav-tabs { padding: 0 1rem; background: var(--tblr-bg-surface-secondary, var(--tblr-light)); }
#wo-detail .tab-pane { padding-top: .25rem; }
#wo-detail .subheader { margin-bottom: .15rem; }
@endsection

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Fabrication</div>
          <h2 class="page-title">Work Orders</h2>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <div class="btn-list">
            <div class="form-check form-switch d-inline-flex align-items-center me-1">
              <input class="form-check-input" type="checkbox" id="toggle-archived" onchange="applyFilter()">
              <label class="form-check-label ms-2" for="toggle-archived">Archived</label>
            </div>
            <button class="btn btn-outline-secondary" onclick="openReorderQueue()" data-permission="fabrication.work-orders.edit">
              <i class="ti ti-arrows-sort"></i>
              Reorder Queue
            </button>
            <button class="btn btn-primary" onclick="openCreateWO()" data-permission="fabrication.work-orders.create">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
              New Work Order
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <main id="content" class="page-body">
    <div class="container-xl">

      <!-- Filter bar -->
      <div class="card mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
              <input type="text" class="form-control form-control-sm" id="wo-search"
                placeholder="Search job #, name…" oninput="debounceFilter()">
            </div>
            <div class="col-auto">
              <select class="form-select form-select-sm" id="filter-material" onchange="applyFilter()">
                <option value="">All Material</option>
                <option value="in_shop">In Shop</option>
                <option value="sof">SOF</option>
                <option value="pending">Pending</option>
              </select>
            </div>
            <div class="col-auto ms-auto d-flex align-items-center gap-2">
              <span class="text-muted small" id="wo-count-label"></span>
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="wo-columns-btn"
                  data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                  <i class="ti ti-columns me-1"></i>Columns
                </button>
                <div class="dropdown-menu dropdown-menu-end p-2" id="wo-columns-menu" style="min-width:14rem;max-height:20rem;overflow:auto"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading -->
      <div id="wo-loading" class="text-center text-muted py-5">
        <div class="spinner-border" role="status"></div>
        <p class="mt-2">Loading work orders…</p>
      </div>

      <!-- Empty -->
      <div id="wo-empty" style="display:none;" class="empty">
        <div class="empty-icon">
          <i class="ti ti-tool" style="font-size: 3rem; opacity: 0.4;"></i>
        </div>
        <p class="empty-title">No work orders found</p>
        <p class="empty-subtitle text-muted">Try adjusting your filters or create a new work order.</p>
        <div class="empty-action">
          <button class="btn btn-primary" onclick="openCreateWO()">New Work Order</button>
        </div>
      </div>

      <!-- WO table -->
      <div id="wo-table-wrap" style="display:none;">
        <div class="card">
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover table-striped">
              <thead id="wo-thead"></thead>
              <tbody id="wo-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- ============================================================
     Detail Offcanvas
     ============================================================ -->
<div class="offcanvas offcanvas-end wo-offcanvas" tabindex="-1" id="wo-detail">
  <div class="offcanvas-header border-bottom align-items-start">
    <div>
      <h5 class="offcanvas-title mb-0" id="wo-detail-title">Work Order</h5>
      <div class="text-muted small" id="wo-detail-subtitle"></div>
    </div>
    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-sm btn-ghost-warning" onclick="archiveCurrentWO()" id="btn-archive-wo" title="Archive">
        <i class="ti ti-archive"></i>
      </button>
      <button class="btn btn-sm btn-ghost-secondary" onclick="closeOffcanvas('wo-detail')" title="Close">
        <i class="ti ti-x"></i>
      </button>
    </div>
  </div>
  <div class="offcanvas-body p-0 d-flex flex-column">

    <!-- Status strip — always visible -->
    <div class="wo-detail-header px-3 py-2 border-bottom">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="subheader mb-0">Status</span>
        <select class="form-select form-select-sm" id="d-wo-status" style="width:140px" onchange="onWoStatusSelect(this.value)">
          <option value="active">Active</option>
          <option value="on_hold">On Hold</option>
          <option value="complete">Complete</option>
        </select>
        <span id="d-wo-status-extra" class="text-muted small"></span>
        <div class="ms-auto d-flex gap-2">
          <button class="btn btn-sm btn-ghost-primary" id="d-wo-send-email-btn" style="display:none"
            onclick="openWoCompletionEmail()"><i class="ti ti-mail me-1"></i>Send Completion Email</button>
          <button class="btn btn-sm btn-ghost-secondary" id="d-wo-status-log-btn" style="display:none"
            onclick="toggleWoStatusLog()" title="Status history"><i class="ti ti-history"></i></button>
        </div>
      </div>
      <div id="d-wo-facts" class="small text-muted mt-1"></div>
      <div id="d-wo-status-log" class="mt-2 small border rounded p-2" style="display:none;max-height:170px;overflow:auto"></div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#wo-tab-elevations" type="button" role="tab">Elevations</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#wo-tab-details" type="button" role="tab">Details</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#wo-tab-checklist" type="button" role="tab">Checklist</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#wo-tab-drawings" type="button" role="tab">Drawings</button></li>
    </ul>

    <div class="tab-content flex-grow-1 overflow-auto">

      <!-- Elevations (default) -->
      <div class="tab-pane fade show active" id="wo-tab-elevations" role="tabpanel">
        <div class="wo-block">
          <div class="wo-block-head">
            <h6>Elevations</h6>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-ghost-success" onclick="openBulkCompleteStage()" title="Mark one stage complete on every elevation">
                <i class="ti ti-checks me-1"></i>Bulk Complete Stage
              </button>
              <button class="btn btn-ghost-success" onclick="openBulkCompleteWO()" title="Complete every open stage on every elevation of this work order">
                <i class="ti ti-clipboard-check me-1"></i>Bulk Complete Work Order
              </button>
              <button class="btn btn-ghost-secondary" onclick="openDoorSchedule()" title="Batch add doors &amp; frames">
                <i class="ti ti-door me-1"></i>Door Schedule
              </button>
              <button class="btn btn-ghost-primary" onclick="openBulkElev()">
                <i class="ti ti-plus me-1"></i>Add Elevation
              </button>
            </div>
          </div>
          <div id="elevations-loading" class="text-muted small" style="display:none;">Loading…</div>
          <div id="elevations-list">
            <div class="text-muted small">No elevations yet.</div>
          </div>
        </div>
      </div>

      <!-- Details -->
      <div class="tab-pane fade" id="wo-tab-details" role="tabpanel">

        <!-- Job (reference) -->
        <div class="wo-block">
          <div class="wo-block-head">
            <h6>Job</h6>
            <button class="btn btn-sm btn-ghost-primary" onclick="openEditJobFromWO()" data-permission="jobs.edit-core">
              <i class="ti ti-pencil me-1"></i>Edit Job
            </button>
          </div>
          <div class="row g-3">
            <div class="col-6 col-md-3">
              <div class="subheader">Job #</div>
              <div class="fw-bold" id="d-job-number">—</div>
            </div>
            <div class="col-6 col-md-6">
              <div class="subheader">Job Name</div>
              <div id="d-job-name">—</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="subheader">Division</div>
              <div id="d-division">—</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="subheader">Project Manager</div>
              <div id="d-pm">—</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="subheader">Superintendent</div>
              <div id="d-super">—</div>
            </div>
          </div>
        </div>

        <!-- Work Order fields (editable) -->
        <div class="wo-block">
          <div class="wo-block-head"><h6>Work Order</h6></div>
          <div class="row g-3">
            <div class="col-6 col-md-3">
              <label class="form-label form-label-sm mb-1">Release #</label>
              <input type="text" class="form-control form-control-sm" id="d-release-code"
                maxlength="50" onchange="saveReleaseCode(this.value)">
              <div class="form-hint mt-1" id="d-release-code-hint"></div>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label form-label-sm mb-1">Date Issued</label>
              <input type="date" class="form-control form-control-sm" id="d-date-issued"
                onchange="patchWO('date_issued', this.value)">
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label form-label-sm mb-1">Due Date</label>
              <div id="d-due-date">—</div>
              <div class="form-hint mt-1">Auto-set from the earliest elevation date</div>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label form-label-sm mb-1">Est. Start</label>
              <div id="d-planned-start-wrap"></div>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label form-label-sm mb-1">Est. Complete</label>
              <div id="d-planned-completion-wrap"></div>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label form-label-sm mb-1">Priority</label>
              <input type="number" class="form-control form-control-sm" id="d-priority"
                min="1" placeholder="—" onchange="patchWO('priority', this.value ? parseInt(this.value) : null)">
              <div class="form-hint mt-1" id="d-priority-hint"></div>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label form-label-sm mb-1">Est. Time</label>
              <div class="input-group input-group-sm">
                <input type="number" class="form-control" id="d-est-minutes" min="0" placeholder="—"
                  title="Total estimated minutes — overrides the elevation roll-up when set"
                  onchange="saveWOEstimate(this.value)">
                <span class="input-group-text" id="d-est-hours">– h</span>
              </div>
              <div class="form-hint mt-1" id="d-est-hint"></div>
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label form-label-sm mb-1">Material Delivery</label>
              <div class="input-group input-group-sm">
                <input type="text" class="form-control" id="d-material" placeholder="Date, In Shop, SOF"
                  onchange="patchWO('material_delivery', this.value || null)">
                <button class="btn btn-ghost-success" type="button" onclick="setMaterial('In Shop')">In Shop</button>
                <button class="btn btn-ghost-warning" type="button" onclick="setMaterial('SOF')">SOF</button>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label form-label-sm mb-1">Notes</label>
              <input type="text" class="form-control form-control-sm" id="d-notes" placeholder="Notes"
                onchange="patchWO('notes', this.value || null)">
            </div>
          </div>
        </div>

        <!-- Assigned Workers -->
        <div class="wo-block">
          <div class="wo-block-head">
            <h6>Assigned Workers</h6>
            <button class="btn btn-sm btn-ghost-primary" onclick="toggleAssignPanel()">
              <i class="ti ti-pencil me-1"></i>Edit
            </button>
          </div>
          <div id="d-assigned-display" class="d-flex flex-wrap gap-1"></div>
          <div id="d-assign-panel" style="display:none;" class="mt-2 p-2 border rounded">
            <div id="d-assign-checkboxes" class="d-flex flex-wrap gap-2 mb-2"></div>
            <button class="btn btn-sm btn-primary" onclick="saveWOAssignments()">Save</button>
            <button class="btn btn-sm btn-ghost-secondary ms-1" onclick="toggleAssignPanel()">Cancel</button>
          </div>
        </div>
      </div>

      <!-- Checklist (WO steps) -->
      <div class="tab-pane fade" id="wo-tab-checklist" role="tabpanel">
        <div class="wo-block">
          <div class="wo-block-head"><h6>Checklist</h6></div>
          <div class="d-flex align-items-center gap-1 flex-wrap" id="wo-steps-container">
            <span class="text-muted small">Loading steps…</span>
          </div>
        </div>
      </div>

      <!-- Shop Drawings -->
      <div class="tab-pane fade" id="wo-tab-drawings" role="tabpanel">
        <div class="wo-block">
          <div class="wo-block-head">
            <h6>Shop Drawings</h6>
            <label class="btn btn-sm btn-ghost-primary mb-0" for="drawing-upload">
              <i class="ti ti-upload me-1"></i>Upload
              <input type="file" id="drawing-upload" class="d-none" multiple
                accept=".pdf,.dwg,.dxf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx"
                onchange="uploadDrawings(this.files)">
            </label>
          </div>
          <div id="drawings-loading" class="text-muted small" style="display:none;">Uploading…</div>
          <div id="drawings-list">
            <div class="text-muted small">No drawings attached.</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<div id="wo-backdrop" onclick="closeOffcanvas('wo-detail')"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:1040;"></div>

<!-- ============================================================
     Create WO Wizard Modal (3-step)
     ============================================================ -->
<div class="modal modal-blur fade" id="createWoModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header border-bottom-0 pb-0">
        <div>
          <h5 class="modal-title mb-0" id="wo-wizard-title">New Work Order</h5>
          <div class="text-muted small" id="wo-wizard-subtitle">Step 1 of 3 — Work order details</div>
        </div>
        <button type="button" class="btn-close" onclick="closeWoWizard()"></button>
      </div>

      <!-- Step indicators + progress bar -->
      <div class="px-3 pt-2">
        <ul class="steps steps-green mb-2">
          <li class="step-item active" id="wiz-ind-1">Work Order</li>
          <li class="step-item" id="wiz-ind-2">Elevations</li>
          <li class="step-item" id="wiz-ind-3">Doors &amp; Frames</li>
        </ul>
        <div class="progress" style="height:4px">
          <div class="progress-bar bg-green" id="wiz-progress-bar" role="progressbar"
            style="width:33%;transition:width .25s ease"></div>
        </div>
      </div>

      <div class="modal-body">

        <!-- ── Step 1: WO Details ── -->
        <div id="wiz-step-1">
          <!-- Excel import -->
          <div class="mb-3 p-3 border rounded" style="background:var(--tblr-bg-surface-secondary)">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="ti ti-file-spreadsheet text-success"></i>
              <span class="fw-medium">Import from Excel</span>
              <span class="text-secondary small">Optional — upload a WO sheet to autofill elevations</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <label class="btn btn-sm btn-outline-success mb-0" for="wo-excel-upload">
                <i class="ti ti-upload me-1"></i>Upload Excel
                <input type="file" id="wo-excel-upload" class="d-none" accept=".xlsx,.xls"
                  onchange="importWOExcel(this)">
              </label>
              <span id="wo-excel-status" class="text-secondary small"></span>
            </div>
            <div id="wo-excel-hint" style="display:none" class="mt-2 small">
              <span class="badge bg-blue-lt text-blue me-1">Division: <span id="wo-excel-division">—</span></span>
              <span id="wo-excel-elev-count" class="text-muted"></span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label required">Job</label>
            <div class="dropdown" id="wiz-job-combo">
              <input type="text" class="form-control" id="new-wo-job-search" autocomplete="off"
                placeholder="Search job number, name, or customer…"
                oninput="onWizardJobSearch(this.value)"
                onkeydown="onWizardJobKeydown(event)"
                onfocus="onWizardJobSearch(this.value)"
                onblur="setTimeout(hideWizardJobResults, 200)">
              <input type="hidden" id="new-wo-job">
              <div class="dropdown-menu w-100 p-0" id="wiz-job-results"
                style="max-height:280px;overflow-y:auto;top:100%;left:0"></div>
            </div>
            <div class="form-hint mt-1" id="wiz-job-hint"></div>
            <div class="mt-1">
              <button type="button" class="btn btn-sm btn-ghost-primary" onclick="openQuickJobCreate()">
                <i class="ti ti-plus me-1"></i>Create New Job
              </button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Custom Release #</label>
            <input type="text" class="form-control" id="new-wo-release-code" maxlength="50" placeholder="Leave blank for R1, R2, …">
            <div class="form-hint">Optional — replaces the auto “R#” in the release label.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Material Delivery</label>
            <div class="input-group">
              <input type="text" class="form-control" id="new-wo-material" placeholder="Date, In Shop, or SOF">
              <button type="button" class="btn btn-ghost-success" onclick="document.getElementById('new-wo-material').value='In Shop'">In Shop</button>
              <button type="button" class="btn btn-ghost-warning" onclick="document.getElementById('new-wo-material').value='SOF'">SOF</button>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label">Notes</label>
            <textarea class="form-control" id="new-wo-notes" rows="2"></textarea>
          </div>
        </div>

        <!-- ── Step 2: Bulk Elevations ── -->
        <div id="wiz-step-2" style="display:none">
          <p class="text-muted small mb-2">Add any elevations for this work order. Leave the table empty and click Skip to proceed without elevations.</p>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="width:16%">Tag</th>
                  <th style="width:16%">Type</th>
                  <th style="width:20%" title="Fabrication system / complexity tier">System</th>
                  <th style="width:9%">Qty</th>
                  <th style="width:17%">Date Requested</th>
                  <th style="width:12%" title="Checked = Assemble, Unchecked = Kit">Assemble</th>
                  <th style="width:10%"></th>
                </tr>
              </thead>
              <tbody id="wiz-bulk-body"></tbody>
            </table>
          </div>
          <button class="btn btn-sm btn-ghost-secondary" onclick="addWizardBulkRow()">
            <i class="ti ti-plus me-1"></i>Add Row
          </button>
        </div>

        <!-- ── Step 3: Door / Frame Schedule ── -->
        <div id="wiz-step-3" style="display:none">
          <p class="text-muted small mb-2">
            Each row creates Door and/or Frame elevations with <strong>Programmed → CNC → Assembled</strong> stages.
            Leave the table empty and click Skip to finish without doors or frames.
          </p>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="width:25%">Tag</th>
                  <th style="width:25%">Leaves</th>
                  <th style="width:20%">Include Frame</th>
                  <th style="width:20%">Date Requested</th>
                  <th style="width:10%"></th>
                </tr>
              </thead>
              <tbody id="wiz-door-body"></tbody>
            </table>
          </div>
          <button class="btn btn-sm btn-ghost-secondary" onclick="addWizardDoorRow()">
            <i class="ti ti-plus me-1"></i>Add Row
          </button>
        </div>

      </div><!-- /.modal-body -->

      <div class="modal-footer">
        <span class="text-muted small me-auto" id="wo-wizard-status"></span>
        <button type="button" class="btn btn-ghost-secondary" id="wo-wizard-skip"
                style="display:none" onclick="wizardSkip()">Skip this step</button>
        <button type="button" class="btn btn-primary" id="wo-wizard-next"
                onclick="wizardNext()">Create Work Order →</button>
      </div>

    </div>
  </div>
</div>

<!-- ============================================================
     Add / Edit Elevation Modal
     ============================================================ -->
<div class="modal modal-blur fade" id="addElevModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="elev-modal-title">Add Elevation</h5>
        <button type="button" class="btn-close" onclick="hideModal(document.getElementById('addElevModal'))"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="elev-id">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label required">Elevation Tag</label>
            <input type="text" class="form-control" id="elev-tag" placeholder="e.g. A1, B2">
          </div>
          <div class="col-md-3">
            <label class="form-label">Type</label>
            <select class="form-select" id="elev-type" onchange="onElevTypeChange()">
              <option value="">— None —</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label" id="elev-tier-label">Complexity Tier</label>
            <select class="form-select" id="elev-tier"></select>
            <small class="form-hint" id="elev-tier-hint"></small>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Quantity</label>
            <input type="number" class="form-control" id="elev-qty" value="1" min="1">
          </div>
          <div class="col-md-4">
            <label class="form-label">Date Requested</label>
            <input type="date" class="form-control" id="elev-date-req">
          </div>
          <div class="col-md-4">
            <label class="form-label">Date Completed</label>
            <input type="date" class="form-control" id="elev-date-done">
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Completed By</label>
            <select class="form-select" id="elev-completed-by">
              <option value="">— None —</option>
            </select>
          </div>
          <div class="col-md-6 d-flex align-items-end pb-1">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="elev-scope" checked>
              <label class="form-check-label" for="elev-scope">
                <strong>Assemble</strong>
                <span class="text-muted small d-block">Uncheck for Kit</span>
              </label>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="elev-notes" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="hideModal(document.getElementById('addElevModal'))">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveElev()">Save</button>
      </div>
    </div>
  </div>
</div>
<!-- ============================================================
     Bulk Add Elevations Modal
     ============================================================ -->
<div class="modal modal-blur fade" id="bulkElevModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Elevations</h5>
        <button type="button" class="btn-close" onclick="hideModal(document.getElementById('bulkElevModal'))"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th style="width:16%">Tag</th>
                <th style="width:16%">Type</th>
                <th style="width:20%" title="Fabrication system / complexity tier">System</th>
                <th style="width:9%">Qty</th>
                <th style="width:17%">Date Requested</th>
                <th style="width:12%" title="Checked = Assemble, Unchecked = Kit">Assemble</th>
                <th style="width:10%"></th>
              </tr>
            </thead>
            <tbody id="bulk-elev-body"></tbody>
          </table>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="addBulkElevRow()">
          <i class="ti ti-plus me-1"></i>Add Row
        </button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="hideModal(document.getElementById('bulkElevModal'))">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveBulkElev()" id="bulk-elev-save">
          Create Elevations
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     Door / Frame Schedule Modal
     ============================================================ -->
<div class="modal modal-blur fade" id="doorScheduleModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Door / Frame Schedule</h5>
        <button type="button" class="btn-close" onclick="hideModal(document.getElementById('doorScheduleModal'))"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">
          Each row creates Door and/or Frame elevations with
          <strong>Programmed → CNC → Assembled</strong> stages.
          Matching a door to a frame with the same tag links them visually.
        </p>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th style="width:25%">Tag</th>
                <th style="width:25%">Leaves</th>
                <th style="width:20%" id="door-frame-col-header">Include Frame</th>
                <th style="width:20%">Date Requested</th>
                <th style="width:10%"></th>
              </tr>
            </thead>
            <tbody id="door-schedule-body"></tbody>
          </table>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="addDoorRow()">
          <i class="ti ti-plus me-1"></i>Add Row
        </button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="hideModal(document.getElementById('doorScheduleModal'))">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveDoorSchedule()" id="door-schedule-save">
          Create Elevations
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     Confirm Action Modal
     ============================================================ -->
<div class="modal modal-blur fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirm-modal-title">Confirm</h5>
      </div>
      <div class="modal-body">
        <p class="mb-0" id="confirm-modal-message"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost-secondary" onclick="hideModal(document.getElementById('confirmModal'))">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirm-modal-btn" onclick="runConfirmModalAction()">Confirm</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     Add Step Modal
     ============================================================ -->
<div class="modal modal-blur fade" id="addStepModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Step</h5>
        <button type="button" class="btn-close" onclick="hideModal(document.getElementById('addStepModal'))"></button>
      </div>
      <div class="modal-body">
        <label class="form-label required">Step name</label>
        <input type="text" class="form-control" id="add-step-name" placeholder="e.g. Cut List Prepared"
          onkeydown="if(event.key==='Enter')saveWoStep()">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost-secondary" onclick="hideModal(document.getElementById('addStepModal'))">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveWoStep()">Add Step</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     Edit Job Modal (parent job of this work order)
     ============================================================ -->
<div class="modal modal-blur fade" id="editJobModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Job</h5>
        <button type="button" class="btn-close" onclick="hideModal(document.getElementById('editJobModal'))"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Job Number</label>
            <input type="text" class="form-control" id="ej-number">
            <div class="form-hint" id="ej-number-hint"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Job Name</label>
            <input type="text" class="form-control" id="ej-name">
          </div>
          <div class="col-md-6">
            <label class="form-label">Customer</label>
            <input type="text" class="form-control" id="ej-customer">
          </div>
          <div class="col-md-6">
            <label class="form-label">Project Manager</label>
            <select class="form-select" id="ej-pm"></select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Superintendent</label>
            <select class="form-select" id="ej-super"></select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" id="ej-status">
              <option value="active">Active</option>
              <option value="on_hold">On Hold</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Start Date</label>
            <input type="date" class="form-control" id="ej-start">
          </div>
          <div class="col-md-4">
            <label class="form-label">Target Completion</label>
            <input type="date" class="form-control" id="ej-target">
          </div>
        </div>
        <div class="text-muted small mt-2">Changes apply to the job and every work order and reservation under it.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost-secondary" onclick="hideModal(document.getElementById('editJobModal'))">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveEditJobFromWO()">Save Job</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     Reorder Queue Modal
     ============================================================ -->
<div class="modal modal-blur fade" id="reorderQueueModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reorder Shop Queue</h5>
        <button type="button" class="btn-close" onclick="hideModal(document.getElementById('reorderQueueModal'))"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">
          Priority is normally derived from each work order's due date. Drag to override —
          moved work orders get <strong>pinned</strong> and the nightly recalc leaves them alone.
        </p>
        <ul class="list-group" id="reorder-queue-list" style="max-height:60vh;overflow-y:auto;"></ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost-danger me-auto" onclick="recalcPriorityFromDueDates()">
          <i class="ti ti-refresh me-1"></i>Recalc from due dates
        </button>
        <button type="button" class="btn btn-ghost-secondary" onclick="hideModal(document.getElementById('reorderQueueModal'))">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveReorderQueue()" id="reorder-queue-save-btn">Save Order</button>
      </div>
    </div>
  </div>
</div>

<!-- Date prompt modal (wizard step 2 — all dates blank) -->
<div class="modal modal-blur fade" id="wizDatePromptModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Date Requested</h5>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">No date requested was set for any elevation. Enter a date to apply to all elevations, or leave blank to skip.</p>
        <label class="form-label required">Date Requested</label>
        <input type="date" class="form-control" id="wiz-date-prompt-value">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost-secondary" onclick="wizDatePromptResolve(null)">Skip</button>
        <button type="button" class="btn btn-primary" onclick="wizDatePromptSubmit()">Apply to All</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
// ============================================================
// Globals
// ============================================================
let allWOs = [];
let currentWO = null;
let elevTypes = [];
let fabUsers = [];
let filterTimer = null;

const STAGE_CYCLE = { pending: 'in_progress', in_progress: 'complete', complete: 'pending', blocked: 'pending', not_required: 'pending', on_hold: 'pending' };

const API = (path, opts = {}) => fetch('/api/v1' + path, {
    ...opts,
    credentials: 'include',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        ...(opts.headers || {}),
    },
});

// ============================================================
// Offcanvas helpers (Tabler doesn't expose window.bootstrap)
// ============================================================
function openOffcanvas(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('show');
    el.style.visibility = 'visible';
    document.getElementById('wo-backdrop').style.display = 'block';
    document.body.classList.add('offcanvas-open');
}
function closeOffcanvas(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('show');
    el.style.visibility = '';
    document.getElementById('wo-backdrop').style.display = 'none';
    document.body.classList.remove('offcanvas-open');
}

// ============================================================
// Init
// ============================================================
document.addEventListener('DOMContentLoaded', async () => {
    renderWOTableHead();
    initWoColumnPrefs();
    await Promise.all([loadWorkOrders(), loadElevTypes(), loadFabUsers()]);

    // Open WO from query param (e.g. linked from Jobs page)
    const params = new URLSearchParams(location.search);
    if (params.has('wo')) openWODetail(parseInt(params.get('wo')));
});

// ============================================================
// Column visibility — persisted to the signed-in user's account
// ============================================================
const WO_COLUMNS = [
    { key: 'priority',        label: '#' },
    { key: 'job_name',        label: 'Job Name' },
    { key: 'pm',               label: 'PM' },
    { key: 'due',              label: 'Due' },
    { key: 'est_start',        label: 'Est. Start' },
    { key: 'est_completion',   label: 'Est. Complete' },
    { key: 'work_content',     label: 'Work Content' },
    { key: 'est_remaining',    label: 'Est. Remaining' },
    { key: 'work_combined',    label: 'Work Content (Combined)' },
    { key: 'assigned',         label: 'Assigned' },
    { key: 'material',         label: 'Material' },
    { key: 'elevations',       label: 'Elevations' },
];
let woHiddenColumns = new Set();

// Uses locally-cached prefs immediately (avoids a flash of every column),
// then reconciles against the server's copy once the session refresh
// resolves (`window.sessionReady`, set up by partials.auth-scripts) — this
// is what makes the choice follow the user to a new device/browser.
function initWoColumnPrefs() {
    applyStoredWoColumnPrefs(currentUser?.wo_column_prefs);
    renderWoColumnsMenu();

    if (typeof window.sessionReady !== 'undefined') {
        window.sessionReady.then(() => {
            applyStoredWoColumnPrefs(currentUser?.wo_column_prefs);
            renderWoColumnsMenu();
            applyWoColumnVisibility();
        });
    }
}

function applyStoredWoColumnPrefs(hidden) {
    woHiddenColumns = new Set(Array.isArray(hidden) ? hidden : []);
}

function renderWoColumnsMenu() {
    const menu = document.getElementById('wo-columns-menu');
    if (!menu) return;
    menu.innerHTML = WO_COLUMNS.map(c => `
        <label class="form-check">
            <input class="form-check-input" type="checkbox" ${woHiddenColumns.has(c.key) ? '' : 'checked'}
                onchange="toggleWoColumn('${c.key}', this.checked)">
            <span class="form-check-label">${esc(c.label)}</span>
        </label>
    `).join('');
}

function toggleWoColumn(key, visible) {
    if (visible) woHiddenColumns.delete(key);
    else woHiddenColumns.add(key);
    applyWoColumnVisibility();
    saveWoColumnPrefs();
}

function applyWoColumnVisibility() {
    WO_COLUMNS.forEach(c => {
        const hidden = woHiddenColumns.has(c.key);
        document.querySelectorAll(`[data-col="${c.key}"]`).forEach(el => {
            el.style.display = hidden ? 'none' : '';
        });
    });
}

let _woColumnSaveTimeout = null;
function saveWoColumnPrefs() {
    // currentUser stays in sync locally so a re-render (e.g. reopening the
    // dropdown) reflects the latest choice even before the save resolves.
    if (currentUser) {
        currentUser.wo_column_prefs = [...woHiddenColumns];
        try { localStorage.setItem('userData', JSON.stringify(currentUser)); } catch (e) { /* best-effort */ }
    }
    clearTimeout(_woColumnSaveTimeout);
    _woColumnSaveTimeout = setTimeout(async () => {
        try {
            await API('/user/wo-column-prefs', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ hidden: [...woHiddenColumns] }),
            });
        } catch (e) { console.error('Failed to save column preferences:', e); }
    }, 400);
}

// ============================================================
// Load & render WO list
// ============================================================
async function loadWorkOrders() {
    const archived = document.getElementById('toggle-archived').checked;
    const q = document.getElementById('wo-search').value.trim();
    const mat = document.getElementById('filter-material').value;

    const qs = new URLSearchParams();
    if (archived) qs.set('archived', '1');
    if (q) qs.set('q', q);
    if (mat) qs.set('material', mat);

    document.getElementById('wo-loading').style.display = 'block';
    document.getElementById('wo-table-wrap').style.display = 'none';
    document.getElementById('wo-empty').style.display = 'none';

    try {
        const r = await API('/work-orders?' + qs.toString());
        const data = await r.json();
        allWOs = data.work_orders || [];
        renderWOList(allWOs);
    } catch (e) {
        console.error(e);
        document.getElementById('wo-loading').style.display = 'none';
    }
}

const WO_STATUS_SORT = { active: 0, on_hold: 1, complete: 2 };

// ── Sortable table headers ──
// Default: priority ascending (the normal shop-floor work order). Any other
// column can be clicked to re-sort; clicking the active column flips
// direction. Persisted only for the session (not saved to the DB — unlike
// column visibility, which columns to see is what should follow the user).
let currentWOSortBy = 'priority';
let currentWOSortDir = 'asc';

function woSortIcon(col) {
    if (currentWOSortBy !== col) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ms-1 text-muted opacity-50"><path d="M8 9l4 -4 4 4"/><path d="M16 15l-4 4 -4 -4"/></svg>';
    }
    return currentWOSortDir === 'asc'
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="ms-1"><path d="M12 5l0 14"/><path d="M18 11l-6 -6"/><path d="M6 11l6 -6"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="ms-1"><path d="M12 5l0 14"/><path d="M18 13l-6 6"/><path d="M6 13l6 6"/></svg>';
}

function woSortTh(label, col, dataCol, extraStyle) {
    const dataAttr = dataCol ? ` data-col="${dataCol}"` : '';
    const style = extraStyle ? `cursor:pointer;user-select:none;${extraStyle}` : 'cursor:pointer;user-select:none;';
    return `<th${dataAttr} style="${style}" onclick="sortWOColumn('${col}')">${esc(label)}${woSortIcon(col)}</th>`;
}

function renderWOTableHead() {
    const thead = document.getElementById('wo-thead');
    if (!thead) return;
    thead.innerHTML = `<tr>
        ${woSortTh('#', 'priority', 'priority', 'width:4.5rem')}
        ${woSortTh('Release', 'release')}
        ${woSortTh('Job Name', 'job_name', 'job_name')}
        ${woSortTh('PM', 'pm', 'pm')}
        ${woSortTh('Due', 'due', 'due')}
        ${woSortTh('Est. Start', 'est_start', 'est_start')}
        ${woSortTh('Est. Complete', 'est_completion', 'est_completion')}
        ${woSortTh('Work Content', 'work_content', 'work_content')}
        ${woSortTh('Est. Remaining', 'est_remaining', 'est_remaining')}
        ${woSortTh('Work Content', 'work_combined', 'work_combined')}
        ${woSortTh('Assigned', 'assigned', 'assigned')}
        ${woSortTh('Material', 'material', 'material')}
        ${woSortTh('Elevations', 'elevations', 'elevations')}
        <th class="w-1"></th>
    </tr>`;
    applyWoColumnVisibility();
}

function sortWOColumn(col) {
    if (currentWOSortBy === col) {
        currentWOSortDir = currentWOSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        currentWOSortBy = col;
        currentWOSortDir = 'asc';
    }
    renderWOTableHead();
    renderWOList(allWOs);
}

function compareWO(a, b) {
    let aVal, bVal, cmp;
    const dir = currentWOSortDir === 'asc' ? 1 : -1;
    switch (currentWOSortBy) {
        case 'release':
            cmp = (a.release_label || '').localeCompare(b.release_label || '', undefined, { numeric: true, sensitivity: 'base' });
            break;
        case 'job_name':
            cmp = (a.job?.job_name || '').localeCompare(b.job?.job_name || '');
            break;
        case 'pm':
            cmp = (a.job?.project_manager || '').localeCompare(b.job?.project_manager || '');
            break;
        case 'due':
            aVal = a.due_date_first || a.due_date || '9999-99-99';
            bVal = b.due_date_first || b.due_date || '9999-99-99';
            cmp = String(aVal).localeCompare(String(bVal));
            break;
        case 'est_start':
            cmp = String(a.planned_start_date || '9999-99-99').localeCompare(String(b.planned_start_date || '9999-99-99'));
            break;
        case 'est_completion':
            cmp = String(a.planned_completion_date || '9999-99-99').localeCompare(String(b.planned_completion_date || '9999-99-99'));
            break;
        case 'work_content':
            aVal = a.estimated_minutes ?? -1; bVal = b.estimated_minutes ?? -1;
            cmp = aVal - bVal;
            break;
        case 'est_remaining':
            aVal = a.estimated_minutes_remaining ?? -1; bVal = b.estimated_minutes_remaining ?? -1;
            cmp = aVal - bVal;
            break;
        case 'work_combined':
            // Same ordering as est_remaining — remaining work is what
            // matters most here, total is secondary context.
            aVal = a.estimated_minutes_remaining ?? -1; bVal = b.estimated_minutes_remaining ?? -1;
            cmp = aVal - bVal;
            break;
        case 'assigned':
            cmp = (a.assigned_users || []).length - (b.assigned_users || []).length;
            break;
        case 'material':
            cmp = (a.material_delivery || '').localeCompare(b.material_delivery || '');
            break;
        case 'elevations':
            aVal = a.elevation_count ? a.elevations_complete / a.elevation_count : -1;
            bVal = b.elevation_count ? b.elevations_complete / b.elevation_count : -1;
            cmp = aVal - bVal;
            break;
        default: { // 'priority'
            // Status grouping (active, then on hold, then complete) takes
            // precedence over priority — priority only orders within a
            // status group, same as before sortable headers existed. This
            // grouping is fixed regardless of the asc/desc toggle; only the
            // priority ordering within each group flips.
            const statusDiff = (WO_STATUS_SORT[a.status] ?? 99) - (WO_STATUS_SORT[b.status] ?? 99);
            if (statusDiff !== 0) return statusDiff;
            aVal = a.priority ?? Infinity; bVal = b.priority ?? Infinity;
            cmp = aVal - bVal;
        }
    }
    if (cmp !== 0) return cmp * dir;
    // Stable, sensible tiebreak regardless of active sort column.
    const statusDiff = (WO_STATUS_SORT[a.status] ?? 99) - (WO_STATUS_SORT[b.status] ?? 99);
    if (statusDiff !== 0) return statusDiff;
    return (a.job?.job_number || '').localeCompare(b.job?.job_number || '', undefined, { numeric: true, sensitivity: 'base' });
}

function renderWOList(wos) {
    document.getElementById('wo-loading').style.display = 'none';
    document.getElementById('wo-count-label').textContent = `${wos.length} work order${wos.length !== 1 ? 's' : ''}`;

    if (wos.length === 0) {
        document.getElementById('wo-empty').style.display = 'block';
        return;
    }

    document.getElementById('wo-table-wrap').style.display = 'block';
    const tbody = document.getElementById('wo-tbody');
    const sorted = wos.slice().sort(compareWO);
    tbody.innerHTML = sorted.map((wo, idx) => {
        const assignedPills = (wo.assigned_users || []).map(u =>
            `<span class="badge bg-blue-lt text-blue" title="${esc(u.name)}">${esc(u.initials || u.name.slice(0,2))}</span>`
        ).join(' ') || '<span class="text-muted">—</span>';
        // Show the stored priority when the WO has one (the normal case —
        // every non-archived WO gets resequenced with one); fall back to its
        // position in the current sort so the column never sits blank for an
        // archived or not-yet-resequenced row.
        const priorityValue = wo.priority ?? (idx + 1);
        const priorityCell = `<span class="badge bg-secondary-lt text-secondary" title="${wo.priority != null ? 'Priority' : 'Unprioritized — showing list position'}">${priorityValue}</span>`;
        const pinBtn = `<button class="btn btn-sm btn-ghost-${wo.priority_locked ? 'yellow' : 'secondary'} p-0 px-1"
            title="${wo.priority_locked ? 'Pinned — auto-ranking skips this WO' : 'Pin at this position'}"
            onclick="toggleWOPin(${wo.id}, event)"><i class="ti ti-pin${wo.priority_locked ? '-filled' : ''}"></i></button>`;
        const dueCell = dueDateHtml(wo);
        const statusBadge = wo.status === 'complete'
            ? '<span class="badge bg-green-lt text-green ms-1">Complete</span>'
            : wo.status === 'on_hold'
                ? '<span class="badge bg-orange-lt text-orange ms-1">On Hold</span>'
                : (wo.is_ready_to_complete ? '<span class="badge bg-blue-lt text-blue ms-1">Ready</span>' : '');
        return `<tr style="cursor:pointer" onclick="openWODetail(${wo.id})">
            <td data-col="priority"><span class="d-flex align-items-center gap-1">${priorityCell}${pinBtn}</span></td>
            <td><strong>${esc(wo.release_label)}</strong>${statusBadge}</td>
            <td data-col="job_name">${esc(wo.job?.job_name || '—')}</td>
            <td data-col="pm">${pmPill(wo.job?.project_manager)}</td>
            <td data-col="due">${dueCell}</td>
            <td data-col="est_start">${plannedDateCell(wo.id, 'planned_start_date', wo.planned_start_date)}</td>
            <td data-col="est_completion">${plannedDateCell(wo.id, 'planned_completion_date', wo.planned_completion_date)}</td>
            <td data-col="work_content" class="text-muted">${wo.estimated_minutes != null ? (wo.estimated_minutes / 60).toFixed(1) + ' h' : '—'}</td>
            <td data-col="est_remaining" class="${wo.estimated_minutes_remaining ? 'fw-bold' : 'text-muted'}">${remainingHoursHtml(wo.estimated_minutes_remaining)}</td>
            <td data-col="work_combined" class="${wo.estimated_minutes_remaining ? 'fw-bold' : 'text-muted'}">${workCombinedHtml(wo)}</td>
            <td data-col="assigned">${assignedPills}</td>
            <td data-col="material">${matBadge(wo.material_delivery)}</td>
            <td data-col="elevations">
                ${wo.elevation_count > 0
                    ? `<span class="text-muted small">${wo.elevations_complete}/${wo.elevation_count} done</span>`
                    : '<span class="text-muted">—</span>'}
            </td>
            <td>
                <button class="btn btn-sm btn-ghost-secondary" onclick="event.stopPropagation();openWODetail(${wo.id})">
                    <i class="ti ti-chevron-right"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
    applyWoColumnVisibility();
}

// ============================================================
// Reorder Queue
// ============================================================
let reorderQueueWOs = [];
let reorderDragId = null;

function openReorderQueue() {
    // Active (non-archived, unpicked-up-yet) WOs, prioritized ones first in their
    // current order, then unprioritized ones appended in list order.
    reorderQueueWOs = allWOs
        .filter(wo => !wo.archived)
        .slice()
        .sort((a, b) => {
            if (a.priority == null && b.priority == null) return 0;
            if (a.priority == null) return 1;
            if (b.priority == null) return -1;
            return a.priority - b.priority;
        });
    renderReorderQueue();
    showModal(document.getElementById('reorderQueueModal'));
}

function renderReorderQueue() {
    const list = document.getElementById('reorder-queue-list');
    if (!reorderQueueWOs.length) {
        list.innerHTML = '<li class="list-group-item text-muted">No active work orders to order.</li>';
        return;
    }
    list.innerHTML = reorderQueueWOs.map((wo, idx) => `
        <li class="list-group-item d-flex align-items-center gap-2" draggable="true"
            data-wo-id="${wo.id}"
            ondragstart="reorderDragStart(event, ${wo.id})"
            ondragover="reorderDragOver(event)"
            ondrop="reorderDrop(event, ${wo.id})"
            style="cursor:grab">
            <i class="ti ti-grip-vertical text-muted"></i>
            <span class="badge bg-secondary-lt text-secondary" style="min-width:2rem;text-align:center">${idx + 1}</span>
            <strong>${esc(wo.release_label)}</strong>
            <span class="text-muted small">${esc(wo.job?.job_name || '')}</span>
        </li>
    `).join('');
}

function reorderDragStart(event, woId) {
    reorderDragId = woId;
    event.dataTransfer.effectAllowed = 'move';
}

function reorderDragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
}

function reorderDrop(event, targetWoId) {
    event.preventDefault();
    if (reorderDragId == null || reorderDragId === targetWoId) return;
    const fromIdx = reorderQueueWOs.findIndex(w => w.id === reorderDragId);
    const toIdx   = reorderQueueWOs.findIndex(w => w.id === targetWoId);
    if (fromIdx === -1 || toIdx === -1) return;
    const [moved] = reorderQueueWOs.splice(fromIdx, 1);
    reorderQueueWOs.splice(toIdx, 0, moved);
    reorderDragId = null;
    renderReorderQueue();
}

async function saveReorderQueue() {
    const btn = document.getElementById('reorder-queue-save-btn');
    btn.disabled = true;
    try {
        // One call: pins every listed WO at its dragged position (priority_locked).
        const r = await API('/work-orders/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ordered_ids: reorderQueueWOs.map(w => w.id) }),
        });
        if (!r.ok) throw new Error('reorder failed');
        hideModal(document.getElementById('reorderQueueModal'));
        await loadWorkOrders();
    } catch (e) {
        console.error(e);
        fabToast('Failed to save queue order. Please try again.', 'error');
    } finally {
        btn.disabled = false;
    }
}

// "Recalc from due dates" — clears every hand-pin and re-derives priority
// purely from due_date. Confirm because it discards the manual order.
async function recalcPriorityFromDueDates() {
    const ok = await fabConfirm({
        title: 'Recalculate priority',
        message: 'Re-rank every work order by its due date? This clears all manual pins.',
        confirmLabel: 'Recalculate',
        confirmClass: 'btn-primary',
    });
    if (!ok) return;
    try {
        const r = await API('/work-orders/resequence-priority', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ clear_locks: true }),
        });
        if (!r.ok) throw new Error('resequence failed');
        hideModal(document.getElementById('reorderQueueModal'));
        await loadWorkOrders();
        fabToast('Priority recalculated from due dates.', 'success');
    } catch (e) {
        console.error(e);
        fabToast('Failed to recalculate priority.', 'error');
    }
}

// Pin / unpin a single WO at its current rank (list row pin button).
async function toggleWOPin(woId, event) {
    event.stopPropagation();
    const wo = allWOs.find(w => w.id === woId);
    if (!wo) return;
    try {
        const r = await API(`/work-orders/${woId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ priority_locked: !wo.priority_locked }),
        });
        if (!r.ok) throw new Error('pin toggle failed');
        await loadWorkOrders();
    } catch (e) {
        console.error(e);
        fabToast('Failed to update pin.', 'error');
    }
}

// ============================================================
// Filters
// ============================================================
function debounceFilter() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyFilter, 300);
}

async function applyFilter() {
    await loadWorkOrders();
}

// ============================================================
// WO Detail offcanvas
// ============================================================
async function openWODetail(id) {
    currentWO = allWOs.find(w => w.id === id) || { id };
    document.getElementById('wo-detail-title').textContent = 'Loading…';
    openOffcanvas('wo-detail');

    try {
        const r = await API(`/work-orders/${id}`);
        const wo = await r.json();
        currentWO = wo;
        populateDetail(wo);
    } catch (e) {
        console.error(e);
    }
}

// Snap the detail panel back to the Elevations tab on (re)open.
function resetWoDetailTab() {
    document.querySelectorAll('#wo-detail .nav-link').forEach(el => {
        el.classList.toggle('active', el.dataset.bsTarget === '#wo-tab-elevations');
    });
    document.querySelectorAll('#wo-detail .tab-pane').forEach(el => {
        const on = el.id === 'wo-tab-elevations';
        el.classList.toggle('show', on);
        el.classList.toggle('active', on);
    });
}

function populateDetail(wo) {
    const job = wo.job || {};
    const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
    resetWoDetailTab();
    document.getElementById('wo-detail-title').textContent = wo.release_label || `WO #${wo.id}`;
    set('wo-detail-subtitle', job.job_name || '');

    // Glanceable facts under the status strip (so the Details tab isn't needed
    // just to see due date / priority / material / progress).
    const facts = [];
    const due = wo.due_date_first || wo.due_date;
    if (due) facts.push('Due ' + fmtDate(due));
    if (wo.priority != null) facts.push('Priority ' + wo.priority);
    if (wo.material_delivery) facts.push(wo.material_delivery);
    if (wo.elevation_count > 0) facts.push(`${wo.elevations_complete || 0}/${wo.elevation_count} elevations done`);
    set('d-wo-facts', facts.join('  ·  '));

    document.getElementById('d-job-number').textContent = job.job_number || '—';
    document.getElementById('d-job-name').textContent = job.job_name || '—';
    document.getElementById('d-pm').textContent = job.project_manager || '—';
    document.getElementById('d-super').textContent = job.superintendent || '—';
    document.getElementById('d-division').textContent = job.division || '—';
    document.getElementById('d-release-code').value = wo.release_code || '';
    document.getElementById('d-release-code').placeholder = 'R' + wo.release_number;
    document.getElementById('d-release-code-hint').textContent = wo.release_code
        ? `Default: R${wo.release_number}`
        : 'Blank = R' + wo.release_number;
    document.getElementById('d-date-issued').value = wo.date_issued || '';
    document.getElementById('d-due-date').innerHTML = dueDateHtml(wo);
    document.getElementById('d-planned-start-wrap').innerHTML =
        compactDateHtml('d-planned-start', wo.planned_start_date, `patchWO('planned_start_date', this.value || null)`);
    document.getElementById('d-planned-completion-wrap').innerHTML =
        compactDateHtml('d-planned-completion', wo.planned_completion_date, `patchWO('planned_completion_date', this.value || null)`);
    document.getElementById('d-priority').value = wo.priority != null ? wo.priority : '';
    document.getElementById('d-priority-hint').textContent = wo.priority_locked
        ? 'Pinned — auto-ranking skips this WO'
        : 'Auto-ranked by due date';
    document.getElementById('d-material').value = wo.material_delivery || '';
    document.getElementById('d-notes').value = wo.notes || '';
    renderWoStatusBar(wo);
    renderWOEstimate(wo);

    renderAssignedUsers(wo.assigned_users || []);
    renderWoSteps(wo.id, wo.steps || []);
    renderDrawings(wo.drawings || []);
    renderElevations(wo.elevations || []);
}

// ============================================================
// Edit parent Job — needs jobs.edit-core. Shared by the WO detail panel and
// the create-wizard's "job info doesn't match the sheet" prompt.
// _editJobCtx null => WO-detail behaviour; else { jobId, source:'wizard' }.
// ============================================================
let _editJobCtx = null;

async function fillEditJobModal(jobId, numberHint) {
    const r = await API(`/business-jobs/${jobId}`);
    const job = (await r.json()).job;
    document.getElementById('ej-number').value = job.job_number || '';
    document.getElementById('ej-name').value = job.job_name || '';
    document.getElementById('ej-customer').value = job.customer_name || '';
    document.getElementById('ej-status').value = job.status || 'active';
    document.getElementById('ej-start').value = job.start_date || '';
    document.getElementById('ej-target').value = job.target_completion_date || '';
    await populatePeopleSelect(document.getElementById('ej-pm'), job.project_manager_id,
        { placeholder: '— Select PM —', legacyLabel: job.project_manager });
    await populatePeopleSelect(document.getElementById('ej-super'), job.superintendent_id,
        { placeholder: '— Select superintendent —', legacyLabel: job.superintendent });
    document.getElementById('ej-number-hint').textContent = numberHint
        || 'Renaming carries across every reservation on this job.';
    showModal(document.getElementById('editJobModal'));
}

async function openEditJobFromWO() {
    _editJobCtx = null;
    const jobId = currentWO?.business_job_id;
    if (!jobId) { fabToast('This work order has no linked job.', 'info'); return; }
    try {
        await fillEditJobModal(jobId);
    } catch (e) {
        console.error(e);
        fabToast('Failed to load the job.', 'error');
    }
}

// Opened from the wizard when the uploaded sheet's job number doesn't match the
// selected job. On save, refreshes the picker instead of the WO detail panel.
async function openEditJobForWizard(jobId, sheet) {
    _editJobCtx = { jobId, source: 'wizard' };
    const bits = [];
    if (sheet?.number) bits.push(`sheet job #: ${sheet.number}`);
    if (sheet?.name) bits.push(`sheet job name: "${sheet.name}"`);
    const hint = (bits.length ? bits.join(' · ') + '. ' : '')
        + 'Renaming carries across every reservation on this job.';
    try {
        await fillEditJobModal(jobId, hint);
    } catch (e) {
        console.error(e);
        fabToast('Failed to load the job.', 'error');
    }
}

async function saveEditJobFromWO() {
    const jobId = _editJobCtx?.jobId ?? currentWO?.business_job_id;
    if (!jobId) return;
    const body = {
        job_number: document.getElementById('ej-number').value.trim(),
        job_name: document.getElementById('ej-name').value.trim(),
        customer_name: document.getElementById('ej-customer').value.trim() || null,
        project_manager_id: document.getElementById('ej-pm').value || null,
        superintendent_id: document.getElementById('ej-super').value || null,
        status: document.getElementById('ej-status').value,
        start_date: document.getElementById('ej-start').value || null,
        target_completion_date: document.getElementById('ej-target').value || null,
    };
    if (!body.job_number || !body.job_name) {
        fabToast('Job number and name are required.', 'info');
        return;
    }
    try {
        const r = await API(`/business-jobs/${jobId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) { fabToast(data.message || 'Failed to save the job.', 'error'); return; }
        hideModal(document.getElementById('editJobModal'));
        fabToast(data.message || 'Job updated.', 'success');

        if (_editJobCtx?.source === 'wizard') {
            // Reflect the edit in the wizard's job picker.
            const j = wizardJobs.find(x => String(x.id) === String(jobId));
            if (j) { j.job_number = body.job_number; j.job_name = body.job_name; j.customer_name = body.customer_name; }
            selectWizardJob(jobId);
        } else {
            await openWODetail(currentWO.id);   // refresh the panel
            loadWorkOrders();
        }
        _editJobCtx = null;
    } catch (e) {
        console.error(e);
        fabToast('Failed to save the job.', 'error');
    }
}

// ============================================================
// Inline WO patch
// ============================================================
async function patchWO(field, value) {
    if (!currentWO) return;
    try {
        await API(`/work-orders/${currentWO.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ [field]: value }),
        });
        // Refresh list silently
        loadWorkOrders();

        // A due-date / priority change reshuffles the ranking — re-sync the panel.
        if (['due_date', 'priority', 'priority_locked'].includes(field)) {
            const rr = await API(`/work-orders/${currentWO.id}`);
            currentWO = await rr.json();
            document.getElementById('d-priority').value = currentWO.priority != null ? currentWO.priority : '';
            document.getElementById('d-priority-hint').textContent = currentWO.priority_locked
                ? 'Pinned — auto-ranking skips this WO'
                : 'Auto-ranked by due date';
        }
    } catch (e) {
        console.error(e);
    }
}

// Like patchWO, but callable straight from the dashboard table row without the
// detail panel open. Refreshes currentWO too, in case that same WO is open.
async function patchWOField(id, field, value) {
    try {
        await API(`/work-orders/${id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ [field]: value }),
        });
        if (currentWO && currentWO.id === id) {
            const r = await API(`/work-orders/${id}`);
            currentWO = await r.json();
            populateDetail(currentWO);
        }
        loadWorkOrders();
    } catch (e) {
        console.error(e);
        fabToast('Failed to save.', 'error');
    }
}

function setMaterial(val) {
    document.getElementById('d-material').value = val;
    patchWO('material_delivery', val);
}

// Custom release code — replaces the "R{n}" token in the release label. Blank reverts.
async function saveReleaseCode(val) {
    if (!currentWO) return;
    const code = (val || '').trim();
    try {
        const r = await API(`/work-orders/${currentWO.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ release_code: code || null }),
        });
        if (!r.ok) { fabToast('Failed to save the release number.', 'error'); return; }
        const wr = await API(`/work-orders/${currentWO.id}`);
        currentWO = await wr.json();
        document.getElementById('wo-detail-title').textContent = currentWO.release_label || `WO #${currentWO.id}`;
        document.getElementById('d-release-code-hint').textContent = currentWO.release_code
            ? `Default: R${currentWO.release_number}`
            : 'Blank = R' + currentWO.release_number;
        loadWorkOrders();
    } catch (e) { console.error(e); fabToast('Failed to save the release number.', 'error'); }
}

// ============================================================
// WO time estimate (roll-up of elevation estimates, with an override)
// ============================================================
function renderWOEstimate(wo) {
    const inp = document.getElementById('d-est-minutes');
    const hrs = document.getElementById('d-est-hours');
    const hint = document.getElementById('d-est-hint');
    if (!inp) return;

    const effective = wo.estimated_minutes;
    const computed = wo.estimated_minutes_computed;
    const override = wo.estimated_minutes_override;

    inp.value = override != null ? override : '';
    inp.placeholder = computed != null ? String(computed) : '—';
    hrs.textContent = effective != null ? (effective / 60).toFixed(1) + ' h' : '– h';

    const joints = wo.joint_qty_total;
    const rollup = computed != null
        ? `Rolled up from ${joints != null ? joints + ' joint' + (joints === 1 ? '' : 's') : 'elevations'}`
        : 'Set joint counts + per-joint rates to estimate';

    if (override != null) {
        hint.innerHTML = 'Manual total. <a href="#" onclick="event.preventDefault();saveWOEstimate(\'\')">Revert</a>'
            + (computed != null ? ` to ${computed} min` : '');
    } else {
        hint.textContent = rollup;
    }
}

async function saveWOEstimate(val) {
    if (!currentWO) return;
    const clean = val === '' ? null : Math.max(0, parseInt(val) || 0);
    try {
        await API(`/work-orders/${currentWO.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ estimated_minutes_override: clean }),
        });
        const rr = await API(`/work-orders/${currentWO.id}`);
        currentWO = await rr.json();
        renderWOEstimate(currentWO);
        loadWorkOrders();
    } catch (e) { console.error(e); fabToast('Failed to save estimate.', 'error'); }
}

// ============================================================
// WO Assigned Workers
// ============================================================
function renderAssignedUsers(users) {
    const display = document.getElementById('d-assigned-display');
    if (!users.length) {
        display.innerHTML = '<span class="text-muted small">No workers assigned</span>';
    } else {
        display.innerHTML = users.map(u =>
            `<span class="badge bg-blue-lt text-blue">${esc(u.initials || u.name.slice(0,2))} ${esc(u.name)}</span>`
        ).join('');
    }
    // Close panel if open
    document.getElementById('d-assign-panel').style.display = 'none';
}

function toggleAssignPanel() {
    const panel = document.getElementById('d-assign-panel');
    if (panel.style.display !== 'none') {
        panel.style.display = 'none';
        return;
    }
    // Build checkboxes
    const currentIds = new Set((currentWO?.assigned_users || []).map(u => u.id));
    const box = document.getElementById('d-assign-checkboxes');
    box.innerHTML = fabUsers.map(u => `
        <label class="form-check">
            <input class="form-check-input wo-assign-chk" type="checkbox" value="${u.id}" ${currentIds.has(u.id) ? 'checked' : ''}>
            <span class="form-check-label">${esc(u.name)}</span>
        </label>`
    ).join('');
    panel.style.display = '';
}

async function saveWOAssignments() {
    if (!currentWO) return;
    const ids = [...document.querySelectorAll('.wo-assign-chk:checked')].map(el => parseInt(el.value));
    try {
        await API(`/work-orders/${currentWO.id}/assignments`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_ids: ids }),
        });
        // Update local state
        currentWO.assigned_users = fabUsers.filter(u => ids.includes(u.id));
        renderAssignedUsers(currentWO.assigned_users);
        loadWorkOrders();
    } catch (e) {
        console.error(e);
        fabToast('Failed to save assignments.', 'error');
    }
}

// ============================================================
// Confirmation modal helper
// ============================================================
let _confirmModalAction = null;

function showConfirmModal(title, message, btnLabel, btnClass, action) {
    document.getElementById('confirm-modal-title').textContent = title;
    document.getElementById('confirm-modal-message').textContent = message;
    const btn = document.getElementById('confirm-modal-btn');
    btn.textContent = btnLabel;
    btn.className = `btn ${btnClass}`;
    _confirmModalAction = action;
    showModal(document.getElementById('confirmModal'));
}

function runConfirmModalAction() {
    hideModal(document.getElementById('confirmModal'));
    if (_confirmModalAction) { _confirmModalAction(); _confirmModalAction = null; }
}

// ============================================================
// Archive
// ============================================================
function archiveCurrentWO() {
    if (!currentWO) return;
    showConfirmModal(
        'Archive Work Order',
        `Archive work order ${currentWO.release_label || '#' + currentWO.id}? It will no longer appear in the active list.`,
        'Archive',
        'btn-warning',
        async () => {
            try {
                await API(`/work-orders/${currentWO.id}`, { method: 'DELETE' });
                closeOffcanvas('wo-detail');
                await loadWorkOrders();
            } catch (e) {
                console.error(e);
                fabToast('Failed to archive work order.', 'error');
            }
        }
    );
}

// ============================================================
// WO lifecycle status (Active / On Hold / Complete)
// ============================================================
const WO_STATUS_BADGE = { active: 'bg-blue-lt', on_hold: 'bg-orange-lt', complete: 'bg-green-lt' };
let _woPromptBusy = false;

function renderWoStatusBar(wo) {
    const sel = document.getElementById('d-wo-status');
    const extra = document.getElementById('d-wo-status-extra');
    const emailBtn = document.getElementById('d-wo-send-email-btn');
    const logBtn = document.getElementById('d-wo-status-log-btn');
    if (!sel) return;

    sel.value = wo.status || 'active';

    let msg = '';
    if (wo.status === 'complete') {
        const when = wo.completed_at ? new Date(wo.completed_at).toLocaleDateString() : '';
        msg = `Completed${wo.completed_by_name ? ' by ' + wo.completed_by_name : ''}${when ? ' · ' + when : ''}`;
        if (wo.completion_email_sent_at) {
            msg += ` · email sent ${new Date(wo.completion_email_sent_at).toLocaleDateString()}`;
        }
    } else if (wo.status === 'on_hold') {
        const hold = (wo.status_log || []).find(l => l.to_status === 'on_hold');
        msg = hold && hold.note ? `On hold: ${hold.note}` : 'On hold';
    } else if (wo.is_ready_to_complete) {
        msg = 'All elevations & steps done — ready to complete';
    }
    extra.textContent = msg;

    // Manager/admin escape hatch: offer the completion email while the WO is
    // complete and the notice hasn't gone out yet.
    emailBtn.style.display = (wo.status === 'complete' && !wo.completion_email_sent_at && isManagerOrAbove())
        ? '' : 'none';

    logBtn.style.display = (wo.status_log && wo.status_log.length) ? '' : 'none';
    renderWoStatusLog(wo);
}

function renderWoStatusLog(wo) {
    const box = document.getElementById('d-wo-status-log');
    if (!box) return;
    const rows = wo.status_log || [];
    box.innerHTML = rows.length
        ? rows.map(l => {
            const t = l.created_at ? new Date(l.created_at).toLocaleString() : '';
            const who = l.user_name ? ` · ${esc(l.user_name)}` : '';
            const move = l.from_status && l.from_status !== l.to_status
                ? `${esc(l.from_status)} → ${esc(l.to_status)}` : esc(l.to_status);
            return `<div class="mb-1"><span class="text-muted">${t}${who}</span> — <strong>${move}</strong>${l.note ? '<br>' + esc(l.note) : ''}</div>`;
        }).join('')
        : '<span class="text-muted">No status changes yet.</span>';
}

function toggleWoStatusLog() {
    const box = document.getElementById('d-wo-status-log');
    box.style.display = box.style.display === 'none' ? 'block' : 'none';
}

// Dropdown pick → route to the right flow. Reverts the <select> on cancel/failure.
async function onWoStatusSelect(next) {
    if (!currentWO) return;
    const cur = currentWO.status || 'active';
    if (next === cur) return;

    if (next === 'on_hold') return openWoHoldPrompt();
    if (next === 'complete') {
        if (!currentWO.is_ready_to_complete) {
            const blk = (currentWO.completion_blockers || []).join(' ');
            fabToast(blk || 'This work order is not ready to be completed.', 'info');
            document.getElementById('d-wo-status').value = cur;
            return;
        }
        return showWoCompletePrompt();
    }
    // → active (release a hold / re-open a completed WO)
    const ok = await applyWoStatus('active', null);
    if (!ok) document.getElementById('d-wo-status').value = cur;
}

// PATCH /work-orders/{id}/status, refresh the panel + list. Returns success bool.
async function applyWoStatus(status, note) {
    if (!currentWO) return false;
    try {
        const r = await API(`/work-orders/${currentWO.id}/status`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status, note: note || null }),
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
            fabToast(data.error || 'Failed to update status.', 'error');
            return false;
        }
        // Re-fetch the full detail payload so the status log / on-hold note refresh.
        try {
            currentWO = await (await API(`/work-orders/${currentWO.id}`)).json();
        } catch (_) {
            currentWO = data.work_order || currentWO;
        }
        renderWoStatusBar(currentWO);
        loadWorkOrders();
        return true;
    } catch (e) {
        console.error(e);
        fabToast('Failed to update status.', 'error');
        return false;
    }
}

// ── On-hold prompt (note required) ──
function openWoHoldPrompt() {
    document.getElementById('wo-hold-note').value = '';
    document.getElementById('wo-hold-prompt').style.display = 'flex';
    setTimeout(() => document.getElementById('wo-hold-note').focus(), 50);
}
function dismissWoHoldPrompt() {
    document.getElementById('wo-hold-prompt').style.display = 'none';
    document.getElementById('d-wo-status').value = currentWO?.status || 'active';
}
async function confirmWoHold() {
    const note = document.getElementById('wo-hold-note').value.trim();
    if (!note) { fabToast('A note is required to place a work order on hold.', 'info'); return; }
    if (await applyWoStatus('on_hold', note)) {
        document.getElementById('wo-hold-prompt').style.display = 'none';
    }
}

// ── Completion prompt: step 1 (mark complete) → step 2 (send email) ──
function showWoCompletePrompt() {
    document.getElementById('wo-complete-note').value = '';
    document.getElementById('wo-complete-prompt').style.display = 'flex';
}
function dismissWoCompletePrompt() {
    document.getElementById('wo-complete-prompt').style.display = 'none';
    document.getElementById('d-wo-status').value = currentWO?.status || 'active';
}
async function confirmWoComplete() {
    const note = document.getElementById('wo-complete-note').value.trim();
    const ok = await applyWoStatus('complete', note);
    document.getElementById('wo-complete-prompt').style.display = 'none';
    if (!ok) { document.getElementById('d-wo-status').value = currentWO?.status || 'active'; return; }

    if (isManagerOrAbove()) {
        openWoCompletionEmail(note);
    } else {
        fabToast('Work order marked complete. A manager or admin can send the completion email.', 'success');
    }
}

// ── Completion email prompt (manager/admin) ──
function openWoCompletionEmail(prefillNote) {
    document.getElementById('wo-email-note').value = prefillNote || '';
    document.getElementById('wo-completion-email-prompt').style.display = 'flex';
}
function dismissWoCompletionEmail() {
    document.getElementById('wo-completion-email-prompt').style.display = 'none';
}
async function sendWoCompletionEmail() {
    if (!currentWO) return;
    const note = document.getElementById('wo-email-note').value.trim();
    try {
        const r = await API(`/work-orders/${currentWO.id}/completion-email`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ note: note || null }),
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) { fabToast(data.error || 'Failed to send completion email.', 'error'); return; }
        document.getElementById('wo-completion-email-prompt').style.display = 'none';
        fabToast(`Completion email sent to ${(data.recipients || []).length} recipient(s).`, 'success');
        const wo = await (await API(`/work-orders/${currentWO.id}`)).json();
        currentWO = wo;
        renderWoStatusBar(wo);
    } catch (e) {
        console.error(e);
        fabToast('Failed to send completion email.', 'error');
    }
}

// Called after any stage / step / elevation change that could finish the WO.
// Refreshes readiness and, the first time the WO becomes ready, prompts.
async function maybePromptWoComplete() {
    if (!currentWO || _woPromptBusy) return;
    // Don't stack on top of the elevation prompt.
    if (document.getElementById('elev-complete-prompt')?.style.display === 'flex') return;
    _woPromptBusy = true;
    try {
        const wo = await (await API(`/work-orders/${currentWO.id}`)).json();
        currentWO = wo;
        renderWoStatusBar(wo);
        if (wo.is_ready_to_complete && wo.status !== 'complete'
            && document.getElementById('wo-complete-prompt').style.display !== 'flex') {
            showWoCompletePrompt();
        }
    } catch (e) {
        console.error(e);
    } finally {
        _woPromptBusy = false;
    }
}

// ============================================================
// WO Steps
// ============================================================
function renderWoSteps(woId, steps) {
    const container = document.getElementById('wo-steps-container');
    if (!container) return;
    const html = steps.map(s => FabStep.stepBadgeHtml(s, {
        esc,
        onClick: `cycleWoStep(${s.id}, '${s.status}')`,
        onContextMenu: `woStepContextMenu(${s.id}, '${s.status}', event)`,
    })).join('');
    const pendingCount = steps.filter(s => s.status === 'pending').length;
    const bulkBtn = pendingCount > 0
        ? `<button class="btn btn-ghost-success btn-sm ms-1 px-2 py-0" style="font-size:.72rem"
               onclick="bulkCompleteWoSteps(${woId})" title="Mark all pending steps complete">
               <i class="ti ti-checks"></i> Complete All (${pendingCount})
           </button>`
        : '';
    container.innerHTML = `${html || '<span class="text-muted small me-1">No steps.</span>'}
        <button class="btn btn-ghost-secondary btn-sm ms-1 px-1 py-0" style="font-size:.72rem"
            onclick="addWoStep()" title="Add step"><i class="ti ti-plus"></i></button>
        ${bulkBtn}`;
}

async function bulkCompleteWoSteps(woId) {
    const ok = await fabConfirm({
        title: 'Complete All Steps',
        message: 'Mark all pending steps on this work order complete? Steps on hold are left untouched.',
        confirmLabel: 'Mark All Complete',
        confirmClass: 'btn-success',
    });
    if (!ok) return;
    try {
        const send = (override) => API(`/work-orders/${woId}/steps/complete-all`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(override ? { override: true } : {}),
        });
        let r = await send(false);
        if (!r.ok) {
            const err = await r.json().catch(() => ({}));
            if (err.code === 'stage_gated'
                && confirm(`"${err.blocking_stage?.name || 'An earlier step'}" isn't complete. Override and complete all?`)) {
                r = await send(true);
            }
            if (!r.ok) { fabToast('Failed to complete steps.', 'error'); return; }
        }
        await reloadWoSteps();
        fabToast('Steps marked complete.', 'success');
    } catch (e) {
        console.error(e);
        fabToast('Failed to complete steps.', 'error');
    }
}

// PATCH a job step, offering a gate override on a 422 stage_gated response.
async function patchJobStepStatus(stepId, status) {
    const send = (override) => API(`/job-steps/${stepId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(override
            ? { status, override: true }
            : { status }),
    });
    let r = await send(false);
    if (!r.ok) {
        const err = await r.json().catch(() => ({}));
        if (err.code === 'stage_gated'
            && confirm(`"${err.blocking_stage?.name || 'An earlier step'}" isn't complete. Override the gate and continue?`)) {
            r = await send(true);
        }
        if (!r.ok) {
            const e2 = await r.json().catch(() => ({}));
            fabToast(e2.message || 'Failed to update step status.', 'error');
            return false;
        }
    }
    return true;
}

async function reloadWoSteps() {
    if (!currentWO) return;
    try {
        const r = await API(`/work-orders/${currentWO.id}/steps`);
        const data = await r.json();
        renderWoSteps(currentWO.id, data.steps || []);
        maybePromptWoComplete();
    } catch (e) { console.error(e); }
}

async function cycleWoStep(stepId, currentStatus) {
    const nextStatus = await FabStep.cycleStepStatus(currentStatus);
    if (!nextStatus) return;
    try {
        if (await patchJobStepStatus(stepId, nextStatus)) reloadWoSteps();
    } catch (e) {
        console.error(e);
        fabToast('Failed to update step status.', 'error');
    }
}

function woStepContextMenu(stepId, currentStatus, event) {
    event.preventDefault();
    document.getElementById('wo-step-ctx')?.remove();
    const menu = document.createElement('div');
    menu.id = 'wo-step-ctx';
    menu.className = 'dropdown-menu show';
    menu.style.cssText = `position:fixed;z-index:9999;left:${event.clientX}px;top:${event.clientY}px`;
    const isSpecial = currentStatus === 'not_required' || currentStatus === 'on_hold';
    menu.innerHTML = isSpecial
        ? `<button class="dropdown-item" onclick="setWoStepStatus(${stepId},'pending');this.closest('#wo-step-ctx').remove()">Reset to Pending</button>
           <button class="dropdown-item text-danger" onclick="deleteWoStep(${stepId});this.closest('#wo-step-ctx').remove()">Delete Step</button>`
        : `<button class="dropdown-item text-muted" onclick="setWoStepStatus(${stepId},'not_required');this.closest('#wo-step-ctx').remove()">Mark Not Required</button>
           <button class="dropdown-item text-warning" onclick="setWoStepStatus(${stepId},'on_hold');this.closest('#wo-step-ctx').remove()">Mark On Hold</button>
           <button class="dropdown-item text-danger" onclick="deleteWoStep(${stepId});this.closest('#wo-step-ctx').remove()">Delete Step</button>`;
    document.body.appendChild(menu);
    const close = (e) => { if (!menu.contains(e.target)) { menu.remove(); document.removeEventListener('click', close); } };
    setTimeout(() => document.addEventListener('click', close), 0);
}

async function setWoStepStatus(stepId, status) {
    try {
        await API(`/job-steps/${stepId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status }),
        });
        reloadWoSteps();
    } catch (e) { console.error(e); }
}

function deleteWoStep(stepId) {
    showConfirmModal('Delete Step', 'Delete this step?', 'Delete', 'btn-danger', async () => {
        try {
            await API(`/job-steps/${stepId}`, { method: 'DELETE' });
            reloadWoSteps();
        } catch (e) { console.error(e); }
    });
}

function addWoStep() {
    if (!currentWO) return;
    document.getElementById('add-step-name').value = '';
    showModal(document.getElementById('addStepModal'));
}

async function saveWoStep() {
    if (!currentWO) return;
    const name = document.getElementById('add-step-name').value.trim();
    if (!name) { fabToast('Step name is required.', 'info'); return; }
    try {
        await API('/job-steps', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ work_order_id: currentWO.id, name }),
        });
        hideModal(document.getElementById('addStepModal'));
        reloadWoSteps();
    } catch (e) { console.error(e); }
}

// ============================================================
// Drawings
// ============================================================
function renderDrawings(drawings) {
    const el = document.getElementById('drawings-list');
    if (!drawings.length) {
        el.innerHTML = '<div class="text-muted small">No drawings attached.</div>';
        return;
    }
    el.innerHTML = `<div class="list-group list-group-flush">
        ${drawings.map(d => `
            <div class="list-group-item d-flex align-items-center gap-2 px-0">
                <i class="ti ti-file text-muted"></i>
                <a href="${esc(d.download_url)}" target="_blank" class="flex-grow-1 text-truncate small">${esc(d.original_name)}</a>
                <span class="text-muted small">${formatBytes(d.file_size)}</span>
                <button class="btn btn-sm btn-ghost-danger" onclick="deleteDrawing(${d.id})">
                    <i class="ti ti-trash"></i>
                </button>
            </div>`).join('')}
    </div>`;
}

async function uploadDrawings(files) {
    if (!currentWO || !files.length) return;
    document.getElementById('drawings-loading').style.display = 'block';
    let failed = 0;
    for (const file of files) {
        const fd = new FormData();
        fd.append('file', file);
        try {
            const r = await authenticatedUpload(`/work-orders/${currentWO.id}/drawings`, fd);
            if (!r.ok) {
                failed++;
                console.error('Drawing upload failed:', r.status, await r.text().catch(() => ''));
            }
        } catch (e) {
            failed++;
            console.error('Upload failed:', e);
        }
    }
    document.getElementById('drawings-loading').style.display = 'none';
    document.getElementById('drawing-upload').value = '';
    if (failed) fabToast(`${failed} drawing${failed !== 1 ? 's' : ''} failed to upload.`, 'error');
    // Reload detail to get fresh drawings list
    const r = await API(`/work-orders/${currentWO.id}`);
    const wo = await r.json();
    renderDrawings(wo.drawings || []);
}

function deleteDrawing(drawingId) {
    if (!currentWO) return;
    showConfirmModal('Delete Drawing', 'Delete this drawing?', 'Delete', 'btn-danger', async () => {
        try {
            await API(`/work-orders/${currentWO.id}/drawings/${drawingId}`, { method: 'DELETE' });
            const r = await API(`/work-orders/${currentWO.id}`);
            const wo = await r.json();
            renderDrawings(wo.drawings || []);
        } catch (e) {
            console.error(e);
            fabToast('Failed to delete drawing.', 'error');
        }
    });
}

// ============================================================
// Elevations
// ============================================================
function renderElevations(elevations) {
    const el = document.getElementById('elevations-list');
    if (!elevations.length) {
        el.innerHTML = '<div class="text-muted small">No elevations yet.</div>';
        return;
    }
    el.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-vcenter">
            <thead><tr>
                <th>Tag</th><th>Type</th><th>Qty</th>
                <th title="Number of joints in this elevation">Joints</th>
                <th title="Estimated labour — joints × the summed per-joint rate of its stages">Est.</th>
                <th>Requested</th><th>Completed</th>
                <th>Stages</th><th class="w-1"></th>
            </tr></thead>
            <tbody>
                ${elevations.map(e => elevRow(e)).join('')}
            </tbody>
        </table>
    </div>`;
}

function elevRow(e) {
    let typeBadge = '<span class="text-secondary">—</span>';
    if (e.elevation_type) {
        const tc = e.elevation_type.color || '#666';
        typeBadge = `<span class="badge" style="background:${esc(tc)};color:${pickTextColor(tc)};border:1px solid var(--tblr-border-color)">${esc(e.elevation_type.name)}</span>`;
    }

    const nextLabels = { pending: 'Start', in_progress: 'Complete', complete: 'Reset', blocked: 'Reset', not_required: 'Reset', on_hold: 'Reset' };
    const stages = e.stages || [];
    const pips = stages.map(s => {
        const blk = stageBlocker(s, stages);
        const tip = blk
            ? `${s.name}: blocked by "${blk.name}" — click to override`
            : `${s.name}: ${s.status} → left-click to ${nextLabels[s.status] || 'advance'}, right-click for options`;
        return `<span class="pip pip-${s.status}${blk ? ' pip-locked' : ''}" style="cursor:pointer"
            title="${esc(tip)}"
            onclick="cycleStage(${s.id},'${s.status}',event)"
            oncontextmenu="stageContextMenu(${s.id},'${s.status}',event)"></span>`;
    }).join('');

    const completedInfo = e.date_completed
        ? `<span class="badge bg-success">${e.date_completed}</span>${e.completed_by_name ? `<br><small class="text-secondary">${esc(e.completed_by_name)}</small>` : ''}`
        : `<span class="text-secondary small">—</span>`;

    const hasStages = stages.length > 0;
    const expandBtn = hasStages
        ? `<button class="btn btn-ghost-secondary btn-sm px-1" onclick="toggleStages('elev-stages-${e.id}', this)" title="Expand stages">
               <i class="ti ti-chevron-right" style="transition:transform .15s"></i>
           </button>`
        : '';

    const stageDetailRows = stages.map(s => `
        <tr>
            <td style="padding-left:2rem" class="small">${esc(s.name)}</td>
            <td>
                <span class="${FabStage.className(s.status)}" style="cursor:pointer"
                    onclick="cycleStage(${s.id},'${s.status}',event)"
                    oncontextmenu="stageContextMenu(${s.id},'${s.status}',event)">
                    ${FabStage.LABEL[s.status] || s.status}
                </span>
            </td>
            <td class="text-secondary small">${s.assigned_name ? esc(s.assigned_name) : '—'}</td>
            <td class="text-secondary small">${s.minutes_per_joint != null ? s.minutes_per_joint + ' min/jt' : '—'}</td>
            <td class="text-secondary small">${s.started_at ? new Date(s.started_at).toLocaleDateString() : '—'}</td>
            <td class="text-secondary small">${s.completed_at ? new Date(s.completed_at).toLocaleDateString() : '—'}${s.completed_by_name ? `<br><span class="text-secondary" style="font-size:.7rem">${esc(s.completed_by_name)}</span>` : ''}</td>
        </tr>`).join('');

    const stageDetailBlock = hasStages ? `
        <tr id="elev-stages-${e.id}" style="display:none">
            <td colspan="9" class="p-0">
                <table class="table table-sm mb-0" style="background:var(--tblr-bg-surface-secondary)">
                    <thead>
                        <tr class="text-secondary" style="font-size:.7rem;text-transform:uppercase">
                            <th style="padding-left:2rem">Stage</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Min / joint</th>
                            <th>Started</th>
                            <th>Completed</th>
                        </tr>
                    </thead>
                    <tbody>${stageDetailRows}</tbody>
                </table>
            </td>
        </tr>` : '';

    const scopeBadge = e.scope === 'kit'
        ? '<span class="badge bg-orange-lt ms-1">Kit</span>'
        : '';

    const rate = e.minutes_per_joint || 0;
    const estMin = e.estimated_minutes;
    const jointTitle = rate > 0
        ? `${rate} min/joint for this elevation (step rates, or its tier fallback) — set the joint count to estimate labour.`
        : 'No per-joint rate on this elevation’s steps or its tier yet (Admin → Elevation Types).';
    const estCell = estMin != null
        ? `<span title="${estMin} min">${(estMin / 60).toFixed(1)} h</span>`
        : '<span class="text-muted">—</span>';

    return `<tr class="elev-row">
        <td><strong>${esc(e.elevation_tag)}</strong>${scopeBadge}</td>
        <td>${typeBadge}</td>
        <td>${e.quantity}</td>
        <td>
            <input type="number" min="0" step="1" class="form-control form-control-sm"
                style="width:76px" value="${e.joint_qty ?? ''}" placeholder="—"
                title="${esc(jointTitle)}"
                onchange="setElevJoints(${e.id}, this.value)">
        </td>
        <td class="small">${estCell}</td>
        <td class="text-muted small">${e.date_requested || '—'}</td>
        <td>${completedInfo}</td>
        <td>
            <div class="d-flex align-items-center gap-1">
                <div class="d-flex flex-wrap gap-0">${pips || '<span class="text-muted small">—</span>'}</div>
                ${expandBtn}
            </div>
        </td>
        <td>
            <div class="btn-group btn-group-sm">
                ${stages.some(s => s.status === 'pending' || s.status === 'in_progress')
                    ? `<button class="btn btn-ghost-success" onclick="bulkCompleteElevation(${e.id})" title="Complete all stages & close this elevation">
                    <i class="ti ti-checks"></i>
                </button>` : ''}
                <button class="btn btn-ghost-secondary" onclick="openEditElev(${e.id})" title="Edit">
                    <i class="ti ti-pencil"></i>
                </button>
                <button class="btn btn-ghost-danger" onclick="deleteElev(${e.id})" title="Delete">
                    <i class="ti ti-trash"></i>
                </button>
            </div>
        </td>
    </tr>${stageDetailBlock}`;
}

function toggleStages(rowId, btn) {
    const row = document.getElementById(rowId);
    if (!row) return;
    const icon = btn.querySelector('i');
    const isOpen = row.style.display !== 'none';
    row.style.display = isOpen ? 'none' : '';
    icon.style.transform = isOpen ? '' : 'rotate(90deg)';
}

// Per-line joint count. Empty string clears it (line then contributes nothing).
async function setElevJoints(elevId, val) {
    if (!currentWO) return;
    try {
        const r = await API(`/elevations/${elevId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ joint_qty: val === '' ? null : Math.max(0, parseInt(val) || 0) }),
        });
        if (!r.ok) { fabToast('Failed to save joint count.', 'error'); return; }
        const wr = await API(`/work-orders/${currentWO.id}`);
        currentWO = await wr.json();
        renderElevations(currentWO.elevations || []);
        renderWOEstimate(currentWO);
        loadWorkOrders();
    } catch (e) { console.error(e); fabToast('Failed to save joint count.', 'error'); }
}

// ============================================================
// Add / Edit Elevation
// ============================================================
async function loadElevTypes() {
    try {
        const r = await API('/elevation-types?with_templates=1');
        const data = await r.json();
        elevTypes = data.elevation_types || [];
        const sel = document.getElementById('elev-type');
        elevTypes.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            sel.appendChild(opt);
        });
    } catch (e) {
        console.error(e);
    }
}

// Elevation types whose "complexity tier" is a fabrication *system* (Curtainwall,
// Storefront, Window Wall). These are matched by name (case-insensitive) so it
// also works in environments where "WW" exists but this one where it doesn't.
const SYSTEM_TYPE_NAMES = ['cw', 'sf', 'ww'];

function elevTypeById(typeId) {
    return elevTypes.find(t => t.id === parseInt(typeId)) || null;
}
function isSystemType(typeId) {
    const t = elevTypeById(typeId);
    return !!t && SYSTEM_TYPE_NAMES.includes(String(t.name).trim().toLowerCase());
}
// "System" for CW/SF/WW, "Complexity Tier" otherwise. Same underlying
// template_set_id either way — this is only a label.
function tierNoun(typeId) {
    return isSystemType(typeId) ? 'System' : 'Complexity Tier';
}
function tierSetsForType(typeId) {
    const t = elevTypeById(typeId);
    return (t?.stage_template_sets || []).slice().sort((a, b) => a.sort_order - b.sort_order);
}
function defaultTierId(typeId) {
    const sets = tierSetsForType(typeId);
    if (!sets.length) return null;
    return (sets.find(s => s.is_default) || sets[0]).id;
}

// Populate the complexity-tier / system picker for the selected elevation type.
// `preselectId` keeps the elevation's current tier when editing.
function refreshElevTierOptions(preselectId) {
    const typeId = parseInt(document.getElementById('elev-type').value) || null;
    const tierSel = document.getElementById('elev-tier');
    const hint = document.getElementById('elev-tier-hint');
    const label = document.getElementById('elev-tier-label');
    const noun = tierNoun(typeId);
    if (label) label.textContent = noun;
    const sets = tierSetsForType(typeId);

    tierSel.innerHTML = '';
    if (!sets.length) {
        tierSel.disabled = true;
        hint.textContent = typeId ? `This type has no ${noun.toLowerCase()} set up yet.` : 'Pick a type first.';
        return;
    }
    tierSel.disabled = false;
    const chosen = sets.some(s => s.id === preselectId)
        ? preselectId
        : defaultTierId(typeId);
    sets.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = `${s.name} (${(s.stage_templates || []).length} steps)${s.is_default ? ' · default' : ''}`;
        if (s.id === chosen) opt.selected = true;
        tierSel.appendChild(opt);
    });
    hint.textContent = `Changing the ${noun.toLowerCase()} on an existing elevation re-syncs its stages.`;
}

function onElevTypeChange() {
    refreshElevTierOptions(null);
}

async function loadFabUsers() {
    try {
        const r = await API('/fab-users');
        const data = await r.json();
        fabUsers = data.users || [];
        const sel = document.getElementById('elev-completed-by');
        fabUsers.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.name;
            sel.appendChild(opt);
        });
    } catch (e) {
        console.error(e);
    }
}

// ============================================================
// Stage cycling
// ============================================================
function findElevationForStage(stageId) {
    for (const elev of (currentWO?.elevations || [])) {
        const idx = (elev.stages || []).findIndex(s => s.id === stageId);
        if (idx >= 0) return { elevation: elev, index: idx };
    }
    return null;
}

async function cycleStage(stageId, currentStatus, event) {
    event.stopPropagation();
    const nextStatus = STAGE_CYCLE[currentStatus] || 'pending';
    await setStageStatus(stageId, nextStatus);
}

// The first earlier blocking stage in the same elevation that isn't done yet,
// or null. Mirrors StageGateService::blockingStageFor on the server.
function stageBlocker(stage, siblings) {
    const done = st => st === 'complete' || st === 'not_required';
    const ph = s => (s.phase ?? s.sort_order);   // steps sharing a phase run concurrently
    return (siblings || []).find(p =>
        p.id !== stage.id && p.blocks_next && ph(p) < ph(stage) && !done(p.status)
    ) || null;
}

async function setStageStatus(stageId, status) {
    try {
        const send = (override) => API(`/work-order-stages/${stageId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(override
                ? { status, override: true, log_message: 'Gate overridden from Work Orders screen' }
                : { status }),
        });

        let r = await send(false);
        if (!r.ok) {
            const err = await r.json().catch(() => ({}));
            if (err.code === 'stage_gated'
                && confirm(`"${err.blocking_stage?.name || 'An earlier stage'}" isn't complete. Override the gate and continue?`)) {
                r = await send(true);
            }
            if (!r.ok) {
                const e2 = await r.json().catch(() => ({}));
                fabToast(e2.message || 'Failed to update stage', 'error');
                return;
            }
        }

        const expanded = getExpandedElevationIds();
        const wo = await (await API(`/work-orders/${currentWO.id}`)).json();
        currentWO = wo;
        renderElevations(wo.elevations || []);
        restoreExpandedElevations(expanded);
        loadWorkOrders();
        checkElevationCompletion(stageId, status);
        maybePromptWoComplete();
    } catch (e) {
        console.error(e);
    }
}

// ── Elevation completion prompts ──────────────────────────────────────────
let _elevPromptId = null;

function findElevForStage(stageId) {
    for (const e of currentWO?.elevations || []) {
        if ((e.stages || []).some(s => s.id === stageId)) return e;
    }
    return null;
}

function checkElevationCompletion(stageId, newStatus) {
    const elev = findElevForStage(stageId);
    if (!elev || !(elev.stages || []).length) return;
    const TERMINAL = ['complete', 'not_required'];
    const allTerminal = elev.stages.every(s => TERMINAL.includes(s.status));

    if (allTerminal && !elev.date_completed) {
        showElevCompletePrompt(elev);
    } else if (elev.date_completed && !TERMINAL.includes(newStatus)) {
        showElevReopenPrompt(elev);
    }
}

// Prompt shown after the last stage of an elevation is cycled complete one at a
// time. No "completed by" here — a fabricator credit is only collected on the
// bulk "complete all stages" flow (openBulkCompletePrompt), and only for
// managers/admins.
function showElevCompletePrompt(elev) {
    _elevPromptId = elev.id;
    document.getElementById('elev-cp-msg').textContent =
        `All stages for "${elev.elevation_tag}" are done — mark elevation as complete?`;
    document.getElementById('elev-complete-prompt').style.display = 'flex';
}

function showElevReopenPrompt(elev) {
    _elevPromptId = elev.id;
    document.getElementById('elev-rp-msg').textContent =
        `Elevation "${elev.elevation_tag}" was marked complete. Reopen it as in-progress?`;
    document.getElementById('elev-reopen-prompt').style.display = 'flex';
}

function dismissElevPrompt(which) {
    document.getElementById(which === 'complete' ? 'elev-complete-prompt' : 'elev-reopen-prompt').style.display = 'none';
    _elevPromptId = null;
}

async function confirmElevComplete() {
    if (!_elevPromptId) return;
    try {
        // Only the completion date — `completed_by_id` is left untouched (the
        // bulk flow is the only place a fabricator credit is captured).
        await API(`/elevations/${_elevPromptId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                date_completed: new Date().toISOString().slice(0, 10),
            }),
        });
        document.getElementById('elev-complete-prompt').style.display = 'none';
        _elevPromptId = null;
        const expanded = getExpandedElevationIds();
        const r = await API(`/work-orders/${currentWO.id}`);
        const wo = await r.json();
        currentWO = wo;
        renderElevations(wo.elevations || []);
        restoreExpandedElevations(expanded);
        loadWorkOrders();
        maybePromptWoComplete();
    } catch (e) { console.error(e); }
}

async function confirmElevReopen() {
    if (!_elevPromptId) return;
    try {
        await API(`/elevations/${_elevPromptId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ date_completed: null, completed_by_id: null }),
        });
        document.getElementById('elev-reopen-prompt').style.display = 'none';
        _elevPromptId = null;
        const expanded = getExpandedElevationIds();
        const r = await API(`/work-orders/${currentWO.id}`);
        const wo = await r.json();
        currentWO = wo;
        renderElevations(wo.elevations || []);
        restoreExpandedElevations(expanded);
        loadWorkOrders();
    } catch (e) { console.error(e); }
}

function stageContextMenu(stageId, currentStatus, event) {
    event.preventDefault();
    event.stopPropagation();
    document.getElementById('stage-ctx-menu')?.remove();
    const menu = document.createElement('div');
    menu.id = 'stage-ctx-menu';
    menu.className = 'dropdown-menu show';
    menu.style.cssText = `position:fixed;z-index:9999;left:${event.clientX}px;top:${event.clientY}px`;
    const isSpecial = currentStatus === 'not_required' || currentStatus === 'on_hold';
    menu.innerHTML = isSpecial
        ? `<button class="dropdown-item" onclick="setStageStatus(${stageId},'pending');this.closest('#stage-ctx-menu').remove()">Reset to Pending</button>`
        : `<button class="dropdown-item text-muted" onclick="setStageStatus(${stageId},'not_required');this.closest('#stage-ctx-menu').remove()">Mark Not Required</button>
           <button class="dropdown-item text-warning" onclick="setStageStatus(${stageId},'on_hold');this.closest('#stage-ctx-menu').remove()">Mark On Hold</button>`;
    document.body.appendChild(menu);
    const close = (e) => { if (!menu.contains(e.target)) { menu.remove(); document.removeEventListener('click', close); } };
    setTimeout(() => document.addEventListener('click', close), 0);
}

function getExpandedElevationIds() {
    return [...document.querySelectorAll('[id^="elev-stages-"]')]
        .filter(row => row.style.display !== 'none')
        .map(row => row.id.replace('elev-stages-', ''));
}

function restoreExpandedElevations(ids) {
    ids.forEach(id => {
        const row = document.getElementById(`elev-stages-${id}`);
        if (!row) return;
        row.style.display = '';
        const elevRow = row.previousElementSibling;
        if (elevRow) {
            const btn = elevRow.querySelector('button[onclick^="toggleStages"]');
            if (btn) btn.querySelector('i').style.transform = 'rotate(90deg)';
        }
    });
}

// ============================================================
// Bulk complete — stage (across a WO) and elevation (all stages)
// ============================================================

// True when the signed-in app user is a manager or admin. Only they may credit
// a specific fabricator with bulk-completed work.
function isManagerOrAbove() {
    return typeof currentUser !== 'undefined' && !!currentUser
        && ['admin', 'manager'].includes(currentUser.role);
}

// Reload the open WO detail, preserving which elevation stage rows are expanded.
async function reloadWODetailKeepExpanded() {
    if (!currentWO) return;
    const expanded = getExpandedElevationIds();
    const wo = await (await API(`/work-orders/${currentWO.id}`)).json();
    currentWO = wo;
    renderElevations(wo.elevations || []);
    restoreExpandedElevations(expanded);
    renderWoStatusBar(wo);
    renderWOEstimate(wo);
    loadWorkOrders();
    maybePromptWoComplete();
}

// Distinct stage names across the current WO's elevations, in stage order.
function distinctStageNames() {
    const seen = new Map();
    (currentWO?.elevations || []).forEach(e => (e.stages || []).forEach(s => {
        const k = s.name.toLowerCase();
        if (!seen.has(k)) seen.set(k, { name: s.name, order: s.sort_order ?? 999 });
    }));
    return [...seen.values()].sort((a, b) => a.order - b.order).map(v => v.name);
}

// Shared prompt for both bulk-complete flows. Resolves to
// { stageName, fabUserId } on confirm, or null on cancel.
let _bcpResolve = null;
function openBulkCompletePrompt({ title, message, allowUser, stageNames }) {
    document.getElementById('bcp-title').textContent = title;
    document.getElementById('bcp-msg').textContent = message;

    const stageWrap = document.getElementById('bcp-stage-wrap');
    const stageSel  = document.getElementById('bcp-stage');
    if (stageNames && stageNames.length) {
        stageWrap.style.display = '';
        stageSel.innerHTML = stageNames.map(n => `<option value="${esc(n)}">${esc(n)}</option>`).join('');
    } else {
        stageWrap.style.display = 'none';
        stageSel.innerHTML = '';
    }

    const userWrap = document.getElementById('bcp-user-wrap');
    const userSel  = document.getElementById('bcp-user');
    if (allowUser) {
        userWrap.style.display = '';
        userSel.innerHTML = '<option value="">— none —</option>' +
            fabUsers.map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('');
    } else {
        userWrap.style.display = 'none';
        userSel.innerHTML = '';
    }

    document.getElementById('bulk-complete-prompt').style.display = 'flex';
    return new Promise(res => { _bcpResolve = res; });
}

function _bcpDone(confirmed) {
    const stageSel = document.getElementById('bcp-stage');
    const userSel  = document.getElementById('bcp-user');
    document.getElementById('bulk-complete-prompt').style.display = 'none';
    const res = _bcpResolve; _bcpResolve = null;
    if (!res) return;
    res(confirmed
        ? { stageName: stageSel.value || null, fabUserId: userSel.value || null }
        : null);
}

// Send a bulk-complete request, retrying once with an override if a stage gate
// blocks it and the user agrees.
async function sendBulkComplete(url, payload, failMsg) {
    const send = (override) => API(url, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(override ? { ...payload, override: true } : payload),
    });
    let r = await send(false);
    if (!r.ok) {
        const err = await r.json().catch(() => ({}));
        if (err.code === 'stage_gated'
            && confirm(`"${err.blocking_stage?.name || 'An earlier stage'}" isn't complete. Override the gate and complete anyway?`)) {
            r = await send(true);
        }
        if (!r.ok) { fabToast(failMsg, 'error'); return null; }
    }
    return r.json().catch(() => ({}));
}

async function openBulkCompleteStage() {
    if (!currentWO) return;
    const names = distinctStageNames();
    if (!names.length) { fabToast('This work order has no stages yet.', 'info'); return; }

    const res = await openBulkCompletePrompt({
        title: 'Bulk Complete Stage',
        message: 'Mark the selected stage complete on every elevation of this work order. Stages on hold or blocked are left untouched.',
        allowUser: isManagerOrAbove(),
        stageNames: names,
    });
    if (!res || !res.stageName) return;

    try {
        const data = await sendBulkComplete(
            `/work-orders/${currentWO.id}/stages/bulk-complete`,
            { stage_name: res.stageName, fab_user_id: res.fabUserId },
            'Failed to complete stages.',
        );
        if (!data) return;
        await reloadWODetailKeepExpanded();
        if (data.updated) {
            const closed = data.elevations_completed || 0;
            const tail = closed ? ` ${closed} elevation${closed !== 1 ? 's' : ''} marked complete.` : '';
            fabToast(`Completed ${data.updated} “${res.stageName}” stage${data.updated !== 1 ? 's' : ''}.${tail}`, 'success');
        } else {
            fabToast('No open stages matched.', 'info');
        }
    } catch (e) { console.error(e); fabToast('Failed to complete stages.', 'error'); }
}

async function openBulkCompleteWO() {
    if (!currentWO) return;
    const open = (currentWO.elevations || [])
        .reduce((n, e) => n + (e.stages || []).filter(s => ['pending', 'in_progress'].includes(s.status)).length, 0);
    if (!open) { fabToast('No open stages on this work order.', 'info'); return; }

    const res = await openBulkCompletePrompt({
        title: 'Bulk Complete Work Order',
        message: `Complete all ${open} open stage${open !== 1 ? 's' : ''} across every elevation of this work order? Stages on hold or blocked are left untouched.`,
        allowUser: isManagerOrAbove(),
    });
    if (!res) return;

    try {
        const data = await sendBulkComplete(
            `/work-orders/${currentWO.id}/stages/bulk-complete`,
            { fab_user_id: res.fabUserId },
            'Failed to complete the work order.',
        );
        if (!data) return;
        await reloadWODetailKeepExpanded();
        const closed = data.elevations_completed || 0;
        const tail = closed ? ` ${closed} elevation${closed !== 1 ? 's' : ''} marked complete.` : '';
        fabToast(`Completed ${data.updated} stage${data.updated !== 1 ? 's' : ''}.${tail}`, 'success');
    } catch (e) { console.error(e); fabToast('Failed to complete the work order.', 'error'); }
}

async function bulkCompleteElevation(elevId) {
    if (!currentWO) return;
    const elev = (currentWO.elevations || []).find(e => e.id === elevId);
    if (!elev) return;
    const open = (elev.stages || []).filter(s => ['pending', 'in_progress'].includes(s.status)).length;
    if (!open) { fabToast('No open stages on this elevation.', 'info'); return; }

    const res = await openBulkCompletePrompt({
        title: 'Complete Elevation',
        message: `Complete all ${open} open stage${open !== 1 ? 's' : ''} on “${elev.elevation_tag}” and close the line?`,
        allowUser: isManagerOrAbove(),
    });
    if (!res) return;

    try {
        const data = await sendBulkComplete(
            `/elevations/${elevId}/complete-all-stages`,
            { fab_user_id: res.fabUserId },
            'Failed to complete the elevation.',
        );
        if (!data) return;
        await reloadWODetailKeepExpanded();
        fabToast('Elevation completed.', 'success');
    } catch (e) { console.error(e); fabToast('Failed to complete the elevation.', 'error'); }
}

function openAddElev() {
    document.getElementById('elev-modal-title').textContent = 'Add Elevation';
    document.getElementById('elev-id').value = '';
    document.getElementById('elev-tag').value = '';
    document.getElementById('elev-type').value = '';
    document.getElementById('elev-qty').value = 1;
    document.getElementById('elev-date-req').value = '';
    document.getElementById('elev-date-done').value = '';
    document.getElementById('elev-completed-by').value = '';
    document.getElementById('elev-scope').checked = true;
    document.getElementById('elev-notes').value = '';
    refreshElevTierOptions(null);
    showModal(document.getElementById('addElevModal'));
}

function openEditElev(elevId) {
    if (!currentWO) return;
    const e = (currentWO.elevations || []).find(x => x.id === elevId);
    if (!e) return;
    document.getElementById('elev-modal-title').textContent = 'Edit Elevation';
    document.getElementById('elev-id').value = e.id;
    document.getElementById('elev-tag').value = e.elevation_tag;
    document.getElementById('elev-type').value = e.elevation_type_id || '';
    document.getElementById('elev-qty').value = e.quantity;
    document.getElementById('elev-date-req').value = e.date_requested || '';
    document.getElementById('elev-date-done').value = e.date_completed || '';
    document.getElementById('elev-completed-by').value = e.completed_by_id || '';
    document.getElementById('elev-scope').checked = (e.scope !== 'kit');
    document.getElementById('elev-notes').value = e.notes || '';
    refreshElevTierOptions(e.template_set_id || null);
    showModal(document.getElementById('addElevModal'));
}

async function saveElev() {
    if (!currentWO) return;
    const elevId = document.getElementById('elev-id').value;
    const isEdit = !!elevId;

    const tierVal = document.getElementById('elev-tier').value;
    const body = {
        elevation_tag: document.getElementById('elev-tag').value,
        elevation_type_id: document.getElementById('elev-type').value || null,
        template_set_id: tierVal ? parseInt(tierVal) : null,
        quantity: parseInt(document.getElementById('elev-qty').value) || 1,
        date_requested: document.getElementById('elev-date-req').value || null,
        date_completed: document.getElementById('elev-date-done').value || null,
        completed_by_id: document.getElementById('elev-completed-by').value || null,
        scope: document.getElementById('elev-scope').checked ? 'assemble' : 'kit',
        notes: document.getElementById('elev-notes').value || null,
    };

    if (!body.elevation_tag) { fabToast('Elevation Tag is required.', 'info'); return; }

    try {
        let resp;
        if (isEdit) {
            resp = await API(`/elevations/${elevId}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
        } else {
            resp = await API(`/work-orders/${currentWO.id}/elevations`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
        }
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok) { fabToast(data.message || 'Failed to save elevation.', 'error'); return; }
        if (data.resync_summary) {
            const s = data.resync_summary;
            fabToast(`Stages re-synced — added ${s.added.length}, kept ${s.carried.length}, retired ${s.retired.length}` +
                (s.kept_with_progress.length ? `, left ${s.kept_with_progress.length} with progress` : ''), 'success');
        }
        hideModal(document.getElementById('addElevModal'));
        // Reload detail
        const r = await API(`/work-orders/${currentWO.id}`);
        const wo = await r.json();
        currentWO = wo;
        renderElevations(wo.elevations || []);
        loadWorkOrders();
    } catch (e) {
        console.error(e);
        fabToast('Failed to save elevation.', 'error');
    }
}

function deleteElev(elevId) {
    showConfirmModal('Delete Elevation', 'Delete this elevation and all its stages?', 'Delete', 'btn-danger', async () => {
        try {
            await API(`/elevations/${elevId}`, { method: 'DELETE' });
            const r = await API(`/work-orders/${currentWO.id}`);
            const wo = await r.json();
            currentWO = wo;
            renderElevations(wo.elevations || []);
            loadWorkOrders();
        } catch (e) {
            console.error(e);
            fabToast('Failed to delete elevation.', 'error');
        }
    });
}

// ============================================================
// Create WO Wizard — Job picker (searchable) + Excel Import
// ============================================================
let _wizardImportedElevations = [];
let _wizardImportMeta = { division: null, jobNumber: null, jobName: null, projectManager: null, superintendent: null };
// job.id:sheetNumber pairs we've already asked "update the job?" about, so the
// prompt fires at most once per (job, sheet number) per wizard run.
let _jobMismatchPrompted = new Set();

// All active jobs, loaded once when the wizard opens. `_wizardJobsReady` lets
// the Excel importer wait for the list before auto-matching.
let wizardJobs = [];
let _wizardJobsReady = null;
let _wizJobActiveIdx = -1;

async function loadWizardJobs() {
    try {
        const r = await API('/business-jobs?per_page=1000&status=active');
        const data = await r.json();
        wizardJobs = data.jobs || [];
    } catch (e) {
        console.error(e);
        wizardJobs = [];
    }
    return wizardJobs;
}

// Uppercase, strip everything that isn't a letter or digit — so "42-50403 R1"
// and "4250403r1" compare equal.
function normJobNum(s) {
    return String(s ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '');
}

// Dice coefficient over character bigrams — cheap fuzzy string match in [0,1].
function jobNameSimilarity(a, b) {
    a = String(a ?? '').toLowerCase().replace(/\s+/g, ' ').trim();
    b = String(b ?? '').toLowerCase().replace(/\s+/g, ' ').trim();
    if (!a || !b) return 0;
    if (a === b) return 1;
    if (a.length < 2 || b.length < 2) return a === b ? 1 : 0;
    const grams = s => {
        const m = new Map();
        for (let i = 0; i < s.length - 1; i++) {
            const g = s.substr(i, 2);
            m.set(g, (m.get(g) || 0) + 1);
        }
        return m;
    };
    const ma = grams(a), mb = grams(b);
    let inter = 0;
    for (const [g, c] of ma) if (mb.has(g)) inter += Math.min(c, mb.get(g));
    return (2 * inter) / ((a.length - 1) + (b.length - 1));
}

// "Regex-style" people match: given a free-text name from a sheet, find the most
// likely active user. Tries a regex (if the string compiles as one) and a
// word-order-independent bigram score; returns { id, label, score } or null.
async function bestPersonMatch(name) {
    const q = String(name ?? '').trim();
    if (q.length < 2) return null;
    const people = await fetchPeople();          // [{ id, label }] — label is "Last, First"
    let re = null;
    try { re = new RegExp(q, 'i'); } catch (_) { re = null; }
    // Normalise "Last, First" and "First Last" to a sorted token string so order
    // doesn't matter.
    const canon = s => String(s || '').toLowerCase().replace(/[,]/g, ' ')
        .replace(/\s+/g, ' ').trim().split(' ').sort().join(' ');
    const cq = canon(q);

    let best = null;
    for (const p of people) {
        const reHit = re && re.test(p.label);
        const score = Math.max(jobNameSimilarity(canon(p.label), cq), reHit ? 0.9 : 0);
        if (!best || score > best.score) best = { id: p.id, label: p.label, score };
    }
    return best && best.score >= 0.5 ? best : null;
}

// Populate a people <select>. Prefer an explicit id; otherwise fuzzy-match the
// free-text name and pre-select the best candidate (keeping the raw name as a
// legacy label when nothing matches well).
async function populatePeopleSelectMatched(select, { id, name } = {}) {
    if (id) { await populatePeopleSelect(select, id, { placeholder: '— Select —' }); return; }
    if (name) {
        const m = await bestPersonMatch(name);
        await populatePeopleSelect(select, m?.id || '', { placeholder: '— Select —', legacyLabel: name });
        return;
    }
    await populatePeopleSelect(select, '', { placeholder: '— Select —' });
}

// Filter `wizardJobs` for a query. Space-separated tokens must each appear
// (substring) in "number name customer"; a query that is a valid regex also
// matches via RegExp. Number/prefix hits rank first.
function filterWizardJobs(query) {
    const q = String(query || '').trim();
    // Drop pure-punctuation tokens (e.g. the "–" in a selected job's label).
    const tokens = q.toLowerCase().split(/\s+/).filter(t => /[a-z0-9]/.test(t));
    let re = null;
    if (q.length >= 2) { try { re = new RegExp(q, 'i'); } catch (_) { re = null; } }
    const nq = normJobNum(q);

    const scored = [];
    for (const j of wizardJobs) {
        const hay = `${j.job_number || ''} ${j.job_name || ''} ${j.customer_name || ''}`.toLowerCase();
        const tokenHit = tokens.length && tokens.every(t => hay.includes(t));
        const reHit = re && re.test(hay);
        if (!q || tokenHit || reHit) {
            const nnum = normJobNum(j.job_number);
            let rank = 3;
            if (nq && nnum === nq) rank = 0;
            else if (nq && (nnum.startsWith(nq) || nq.startsWith(nnum))) rank = 1;
            else if (tokens.length && (j.job_number || '').toLowerCase().startsWith(tokens[0])) rank = 2;
            scored.push({ j, rank });
        }
    }
    scored.sort((a, b) => a.rank - b.rank
        || String(a.j.job_number).localeCompare(String(b.j.job_number), undefined, { numeric: true }));
    return scored.slice(0, 25).map(s => s.j);
}

function onWizardJobSearch(query) {
    const box = document.getElementById('wiz-job-results');
    const jobs = filterWizardJobs(query);
    _wizJobActiveIdx = -1;
    if (!jobs.length) {
        box.innerHTML = `<div class="dropdown-item-text text-muted small">No matching active job${wizardJobs.length ? '' : ' loaded yet'}.</div>`;
        box.classList.add('show');
        return;
    }
    box.innerHTML = jobs.map((j, i) => `
        <button type="button" class="dropdown-item d-flex flex-column align-items-start" data-idx="${i}" data-id="${j.id}"
            onmousedown="event.preventDefault()" onclick="selectWizardJob(${j.id})">
            <span><strong>${esc(j.job_number || '—')}</strong> ${esc(j.job_name || '')}</span>
            ${j.customer_name ? `<span class="text-muted small">${esc(j.customer_name)}</span>` : ''}
        </button>`).join('');
    box.classList.add('show');
}

function hideWizardJobResults() {
    document.getElementById('wiz-job-results')?.classList.remove('show');
}

function onWizardJobKeydown(e) {
    const box = document.getElementById('wiz-job-results');
    const items = [...box.querySelectorAll('.dropdown-item')];
    if (e.key === 'Escape') { hideWizardJobResults(); return; }
    if (!items.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        _wizJobActiveIdx += (e.key === 'ArrowDown' ? 1 : -1);
        if (_wizJobActiveIdx < 0) _wizJobActiveIdx = items.length - 1;
        if (_wizJobActiveIdx >= items.length) _wizJobActiveIdx = 0;
        items.forEach((el, i) => el.classList.toggle('active', i === _wizJobActiveIdx));
        items[_wizJobActiveIdx].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const pick = items[_wizJobActiveIdx] || items[0];
        if (pick) selectWizardJob(parseInt(pick.dataset.id));
    }
}

function selectWizardJob(id) {
    const j = wizardJobs.find(x => String(x.id) === String(id));
    document.getElementById('new-wo-job').value = j ? j.id : '';
    document.getElementById('new-wo-job-search').value = j ? `${j.job_number} – ${j.job_name}` : '';
    document.getElementById('wiz-job-hint').textContent = '';
    hideWizardJobResults();
    refreshWizardDivisionBadge();
    if (j) maybePromptJobMismatch(j);
}

// When an Excel import is in play and the picked job's number doesn't match the
// sheet's, offer to open the Edit Job modal. Fires at most once per (job, sheet
// number); skipped when the user can't edit jobs.
async function maybePromptJobMismatch(job) {
    const sheetNum = _wizardImportMeta.jobNumber;
    if (!job || !sheetNum) return;
    if (normJobNum(job.job_number) === normJobNum(sheetNum)) return;
    const canEdit = (typeof isAdmin === 'function' && isAdmin())
        || (typeof hasPermission === 'function' && hasPermission('jobs.edit-core'));
    if (!canEdit) return;

    const key = `${job.id}:${normJobNum(sheetNum)}`;
    if (_jobMismatchPrompted.has(key)) return;
    _jobMismatchPrompted.add(key);

    const sheetBits = [`job number "${sheetNum}"`];
    if (_wizardImportMeta.jobName) sheetBits.push(`name "${_wizardImportMeta.jobName}"`);
    const ok = await fabConfirm({
        title: "Job info doesn't match the sheet",
        message: `You picked "${job.job_number} - ${job.job_name}", but the uploaded sheet lists `
            + `${sheetBits.join(', ')}. Update this job's information now?`,
        confirmLabel: 'Edit Job',
        confirmClass: 'btn-primary',
    });
    if (ok) openEditJobForWizard(job.id, { number: sheetNum, name: _wizardImportMeta.jobName });
}

function setWizardJobQuery(q) {
    const inp = document.getElementById('new-wo-job-search');
    inp.value = q || '';
    onWizardJobSearch(inp.value);
}

// A job number the sheet matched that isn't active — completed, cancelled or
// on hold. Looked up via the API since wizardJobs only holds active jobs.
async function findInactiveJobByNumber(nNum) {
    if (!nNum) return null;
    try {
        const r = await API(`/business-jobs?search=${encodeURIComponent(nNum)}`);
        const data = await r.json();
        const jobs = data.jobs || [];
        return jobs.find(j => normJobNum(j.job_number) === nNum && j.status !== 'active') || null;
    } catch (e) {
        console.error(e);
        return null;
    }
}

// Offers to reactivate an archived/completed job matched from the sheet.
// Returns true if the job was reactivated (and selected), false otherwise.
async function maybePromptUnarchiveJob(job) {
    const ok = await fabConfirm({
        title: 'Matched job is archived',
        message: `The sheet matches job "${job.job_number} - ${job.job_name}", but it's marked `
            + `${job.status_label || job.status}. Reactivate it so this work order can use it?`,
        confirmLabel: 'Reactivate Job',
        confirmClass: 'btn-primary',
    });
    if (!ok) return false;

    try {
        const r = await API(`/business-jobs/${job.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: 'active' }),
        });
        if (!r.ok) { fabToast('Failed to reactivate job.', 'error'); return false; }
    } catch (e) {
        console.error(e);
        fabToast('Failed to reactivate job.', 'error');
        return false;
    }

    wizardJobs.push({ ...job, status: 'active' });
    return true;
}

// After an Excel parse, try to select the job automatically:
//  1. exact (normalised) job-number match
//  2. one job whose number is a prefix of the sheet's (or vice-versa)
//  3. a clearly-best fuzzy match on job name
// Otherwise pre-fill the search with what we parsed and leave it to the user.
async function autoMatchWizardJob(jobNumber, jobName) {
    const hint = document.getElementById('wiz-job-hint');
    if (document.getElementById('new-wo-job').value) return;   // user already picked

    const nNum = normJobNum(jobNumber);
    if (nNum) {
        const exact = wizardJobs.filter(j => normJobNum(j.job_number) === nNum);
        if (exact.length === 1) {
            selectWizardJob(exact[0].id);
            hint.textContent = `Matched job ${exact[0].job_number} from the sheet.`;
            return;
        }
        const pre = wizardJobs.filter(j => {
            const x = normJobNum(j.job_number);
            return x && (x.startsWith(nNum) || nNum.startsWith(x));
        });
        if (pre.length === 1) {
            selectWizardJob(pre[0].id);
            hint.textContent = `Matched the closest job number (${pre[0].job_number}) — confirm it's right.`;
            return;
        }
        if (pre.length > 1) {
            setWizardJobQuery(jobNumber);
            hint.textContent = `${pre.length} jobs look close to "${jobNumber}" — pick the right one.`;
            return;
        }

        const inactive = await findInactiveJobByNumber(nNum);
        if (inactive) {
            const canReactivate = (typeof isAdmin === 'function' && isAdmin())
                || (typeof hasPermission === 'function' && hasPermission('jobs.edit'));
            const reactivated = canReactivate && await maybePromptUnarchiveJob(inactive);
            if (reactivated) {
                selectWizardJob(inactive.id);
                hint.textContent = `Reactivated job ${inactive.job_number} and matched it from the sheet.`;
            } else {
                setWizardJobQuery(jobNumber);
                hint.textContent = `Job ${inactive.job_number} exists but is ${inactive.status_label || inactive.status} — pick or create a job.`;
            }
            return;
        }
    }

    if (jobName) {
        const scored = wizardJobs
            .map(j => ({ j, s: jobNameSimilarity(j.job_name, jobName) }))
            .sort((a, b) => b.s - a.s);
        const best = scored[0], next = scored[1];
        if (best && best.s >= 0.55 && (!next || best.s - next.s >= 0.12)) {
            selectWizardJob(best.j.id);
            hint.textContent = `Matched by name: "${best.j.job_name}" (${Math.round(best.s * 100)}% similar) — confirm it's right.`;
            return;
        }
        if (best && best.s >= 0.35) {
            setWizardJobQuery(jobName);
            hint.textContent = `No job-number match — showing the closest names. Pick one or create a job.`;
            return;
        }
    }

    const label = jobNumber || jobName;
    if (label) {
        setWizardJobQuery(label);
        hint.textContent = `No active job matched "${label}". Search, or create a new job.`;
    }
}

// First numeric character of a string, or '' when there is none.
function firstDigit(s) {
    const m = String(s ?? '').match(/\d/);
    return m ? m[0] : '';
}

// The job number of the job picked in wizard step 1.
function selectedWizardJobNumber() {
    const id = document.getElementById('new-wo-job')?.value;
    const j = wizardJobs.find(x => String(x.id) === String(id));
    return j ? (j.job_number || '') : '';
}

// Division badge: the value parsed from the sheet, else the first digit of the
// sheet's job-number cell, else the first digit of the job picked in step 1.
function refreshWizardDivisionBadge() {
    const el = document.getElementById('wo-excel-division');
    if (!el) return;
    const div = _wizardImportMeta.division
        || firstDigit(_wizardImportMeta.jobNumber)
        || firstDigit(selectedWizardJobNumber());
    el.textContent = div || '—';
}

async function importWOExcel(input) {
    const file = input.files[0];
    if (!file) return;

    const statusEl  = document.getElementById('wo-excel-status');
    const hintEl    = document.getElementById('wo-excel-hint');
    statusEl.textContent = 'Parsing…';
    hintEl.style.display = 'none';

    const fd = new FormData();
    fd.append('file', file);

    try {
        const r = await API('/work-orders/parse-excel', {
            method: 'POST',
            body: fd,
        });

        let data;
        try { data = await r.json(); } catch (_) { data = {}; }

        if (!r.ok) {
            statusEl.textContent = data.error || `Upload failed (${r.status})`;
            input.value = '';
            return;
        }

        _wizardImportedElevations = data.elevations || [];
        _wizardImportMeta = {
            division: data.division || null,
            jobNumber: data.job_number || null,
            jobName: data.job_name || null,
            projectManager: data.project_manager || null,
            superintendent: data.superintendent || null,
        };
        refreshWizardDivisionBadge();

        // Auto-select the job: exact job-number match, then a close number, then
        // a fuzzy job-name match. Falls back to seeding the search box.
        try { await (_wizardJobsReady || loadWizardJobs()); } catch (_) {}
        autoMatchWizardJob(data.job_number, data.job_name);

        const doorRows  = _wizardImportedElevations.filter(e => (e.type || '').toLowerCase() === 'door');
        const doorCount = doorRows.reduce((sum, e) => sum + Math.max(1, parseInt(e.quantity) || 1), 0);
        const elevCount = _wizardImportedElevations.length - doorRows.length;
        const parts = [];
        if (elevCount > 0) parts.push(`${elevCount} elevation${elevCount !== 1 ? 's' : ''} ready for Step 2`);
        if (doorCount > 0) parts.push(`${doorCount} door${doorCount !== 1 ? 's' : ''} ready for Step 3`);
        document.getElementById('wo-excel-elev-count').textContent = parts.length ? parts.join(' · ') : 'No elevations found';

        statusEl.textContent = '✓ ' + file.name;
        hintEl.style.display = '';
    } catch (e) {
        console.error(e);
        statusEl.textContent = 'Error reading file';
        input.value = '';
    }
}

// ============================================================
// Create WO Wizard
// ============================================================
let wizardStep = 1;
let wizardWoId = null;
let wizardWoLabel = '';
let wizardBulkRowId = 0;
let wizardDoorRowId = 0;

async function openCreateWO() {
    wizardStep = 1;
    wizardWoId = null;
    wizardWoLabel = '';
    wizardBulkRowId = 0;
    wizardDoorRowId = 0;
    _wizardSystemPrompted = new Set();
    _jobMismatchPrompted = new Set();
    _wizardImportedElevations = [];
    _wizardImportMeta = { division: null, jobNumber: null, jobName: null, projectManager: null, superintendent: null };

    // Reset excel import UI
    document.getElementById('wo-excel-upload').value = '';
    document.getElementById('wo-excel-status').textContent = '';
    document.getElementById('wo-excel-hint').style.display = 'none';
    document.getElementById('wo-excel-division').textContent = '—';

    // Reset step 1 — searchable job picker
    document.getElementById('new-wo-job').value = '';
    document.getElementById('new-wo-job-search').value = '';
    document.getElementById('wiz-job-hint').textContent = '';
    hideWizardJobResults();
    _wizardJobsReady = loadWizardJobs().then(() => {
        const s = document.getElementById('new-wo-job-search');
        if (s && document.activeElement === s && !document.getElementById('new-wo-job').value) {
            onWizardJobSearch(s.value);
        }
    });
    document.getElementById('new-wo-release-code').value = '';
    document.getElementById('new-wo-material').value = '';
    document.getElementById('new-wo-notes').value = '';

    // Seed step 2 with one blank row (will be replaced with imports when advancing)
    document.getElementById('wiz-bulk-body').innerHTML = '';
    addWizardBulkRow();

    // Seed step 3 with one blank row
    document.getElementById('wiz-door-body').innerHTML = '';
    addWizardDoorRow();

    showWizardStep(1);
    showModal(document.getElementById('createWoModal'));
}

function showWizardStep(step) {
    wizardStep = step;
    [1, 2, 3].forEach(s => {
        document.getElementById(`wiz-step-${s}`).style.display = s === step ? '' : 'none';
        const ind = document.getElementById(`wiz-ind-${s}`);
        ind.classList.toggle('active', s <= step);
    });
    const bar = document.getElementById('wiz-progress-bar');
    if (bar) bar.style.width = Math.round((step / 3) * 100) + '%';
    const titles    = ['New Work Order', 'Add Elevations', 'Door & Frame Schedule'];
    const subtitles = ['Step 1 of 3 — Work order details', 'Step 2 of 3 — Optional', 'Step 3 of 3 — Optional'];
    document.getElementById('wo-wizard-title').textContent    = titles[step - 1];
    document.getElementById('wo-wizard-subtitle').textContent = subtitles[step - 1];

    const skipBtn = document.getElementById('wo-wizard-skip');
    const nextBtn = document.getElementById('wo-wizard-next');
    skipBtn.style.display = step > 1 ? '' : 'none';
    skipBtn.textContent   = 'Skip this step';
    if (step === 1) { nextBtn.textContent = 'Create Work Order →'; }
    else if (step === 2) { nextBtn.textContent = 'Save Elevations →'; }
    else { nextBtn.textContent = 'Finish'; }

    document.getElementById('wo-wizard-status').textContent = wizardWoLabel
        ? `Work order ${wizardWoLabel} created`
        : '';
}

async function wizardNext() {
    if (wizardStep === 1) await wizardCreateWO();
    else if (wizardStep === 2) await wizardSaveElevations();
    else await wizardFinishDoors();
}

function wizardSkip() {
    if (wizardStep === 2) showWizardStep(3);
    else if (wizardStep === 3) wizardComplete();
}

function closeWoWizard() {
    hideModal(document.getElementById('createWoModal'));
    if (wizardWoId) { loadWorkOrders(); openWODetail(wizardWoId); }
}

async function wizardCreateWO() {
    const jobId = document.getElementById('new-wo-job').value;
    if (!jobId) { fabToast('Please select a job.', 'info'); return; }

    const btn = document.getElementById('wo-wizard-next');
    btn.disabled = true; btn.textContent = 'Creating…';
    try {
        const today = new Date().toISOString().slice(0, 10);
        const r = await API('/work-orders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                business_job_id:   parseInt(jobId),
                date_issued:       today,
                release_code:      document.getElementById('new-wo-release-code').value.trim() || null,
                material_delivery: document.getElementById('new-wo-material').value || null,
                notes:             document.getElementById('new-wo-notes').value || null,
            }),
        });
        if (!r.ok) throw new Error('Failed to create');
        const data = await r.json();
        wizardWoId    = data.id;
        wizardWoLabel = data.release_label || `#${data.id}`;
        loadWorkOrders();

        // Pre-populate step 2 from Excel import (if any).
        // Rows tagged as "Door" belong in the Door & Frame Schedule (step 3), not the
        // general elevations step.
        // A door row's quantity means "N separate doors under this tag" — expand into one
        // editable line per unit (H01 qty 9 → H01-1 … H01-9) rather than one row per tag.
        const importedDoors = [];
        _wizardImportedElevations
            .filter(e => (e.type || '').toLowerCase() === 'door')
            .forEach(e => {
                const qty = Math.max(1, parseInt(e.quantity) || 1);
                for (let i = 1; i <= qty; i++) {
                    importedDoors.push({ tag: qty > 1 ? `${e.tag}-${i}` : e.tag });
                }
            });
        const importedElevations = _wizardImportedElevations.filter(e => (e.type || '').toLowerCase() !== 'door');

        document.getElementById('wiz-bulk-body').innerHTML = '';
        wizardBulkRowId = 0;
        if (importedElevations.length > 0) {
            importedElevations.forEach(e => addWizardBulkRow(e));
        } else {
            addWizardBulkRow();
        }

        document.getElementById('wiz-door-body').innerHTML = '';
        wizardDoorRowId = 0;
        if (importedDoors.length > 0) {
            importedDoors.forEach(e => addWizardDoorRow(e));
        } else {
            addWizardDoorRow();
        }

        showWizardStep(2);
    } catch (e) {
        console.error(e);
        fabToast('Failed to create work order.', 'error');
    } finally {
        btn.disabled = false;
    }
}

// Promise-based date prompt modal
let _wizDatePromptResolve = null;
function wizDatePromptResolve(date) {
    hideModal(document.getElementById('wizDatePromptModal'));
    if (_wizDatePromptResolve) { _wizDatePromptResolve(date); _wizDatePromptResolve = null; }
}
function wizDatePromptSubmit() {
    const val = document.getElementById('wiz-date-prompt-value').value || null;
    if (!val) { fabToast('Please enter a date, or click Skip.', 'info'); return; }
    wizDatePromptResolve(val);
}
function promptForDate() {
    return new Promise(resolve => {
        _wizDatePromptResolve = resolve;
        document.getElementById('wiz-date-prompt-value').value = '';
        showModal(document.getElementById('wizDatePromptModal'));
    });
}

async function wizardSaveElevations() {
    const rows = [...document.querySelectorAll('#wiz-bulk-body tr')];
    const creates = [];
    rows.forEach(row => {
        const tag = row.querySelector('.wiz-bulk-tag')?.value.trim();
        if (!tag) return;
        creates.push({
            elevation_tag:     tag,
            elevation_type_id: row.querySelector('.wiz-bulk-type')?.value || null,
            template_set_id:   row.querySelector('.wiz-bulk-system')?.value || null,
            quantity:          parseInt(row.querySelector('.wiz-bulk-qty')?.value) || 1,
            date_requested:    row.querySelector('.wiz-bulk-date')?.value || null,
            scope:             row.querySelector('.wiz-bulk-scope')?.checked ? 'assemble' : 'kit',
        });
    });

    if (!creates.length) { showWizardStep(3); return; }

    // If every row has no date_requested, prompt once and apply to all
    const allBlank = creates.every(c => !c.date_requested);
    if (allBlank) {
        const sharedDate = await promptForDate();
        if (sharedDate) creates.forEach(c => c.date_requested = sharedDate);
    }

    const btn = document.getElementById('wo-wizard-next');
    btn.disabled = true; btn.textContent = 'Saving…';
    try {
        for (const body of creates) {
            await API(`/work-orders/${wizardWoId}/elevations`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
        }
        showWizardStep(3);
    } catch (e) {
        console.error(e);
        fabToast('Failed to save some elevations.', 'error');
    } finally {
        btn.disabled = false;
    }
}

// Shared helper: expand a door-schedule row into individual elevation records.
// Pairs produce two separate door elevations tagged {tag}-LH and {tag}-RH.
function buildDoorCreates(tag, leaves, frameChk, doorTypeId, frameTypeId, dateRequested) {
    const out = [];
    const frameOnly = leaves === 0;
    const dateField = dateRequested ? { date_requested: dateRequested } : {};
    if (!frameOnly && doorTypeId) {
        if (leaves === 2) {
            out.push({ elevation_tag: `${tag}-LH`, elevation_type_id: doorTypeId, quantity: 1, ...dateField });
            out.push({ elevation_tag: `${tag}-RH`, elevation_type_id: doorTypeId, quantity: 1, ...dateField });
        } else {
            out.push({ elevation_tag: tag, elevation_type_id: doorTypeId, quantity: leaves, ...dateField });
        }
    }
    if ((frameOnly || frameChk) && frameTypeId) {
        out.push({ elevation_tag: tag, elevation_type_id: frameTypeId, quantity: 1, ...dateField });
    }
    return out;
}

async function wizardFinishDoors() {
    const doorTypeId  = elevTypes.find(t => t.name === 'Door')?.id;
    const frameTypeId = elevTypes.find(t => t.name === 'Frame')?.id;

    const creates = [];
    document.querySelectorAll('#wiz-door-body tr').forEach(row => {
        const tag           = row.querySelector('.wiz-door-tag')?.value.trim();
        const leaves        = parseInt(row.querySelector('.wiz-door-leaves')?.value ?? '1');
        const frameChk      = row.querySelector('.wiz-door-frame')?.checked ?? false;
        const dateRequested = row.querySelector('.wiz-door-date')?.value || null;
        if (!tag) return;
        creates.push(...buildDoorCreates(tag, leaves, frameChk, doorTypeId, frameTypeId, dateRequested));
    });

    if (!creates.length) { wizardComplete(); return; }

    const btn = document.getElementById('wo-wizard-next');
    btn.disabled = true; btn.textContent = 'Saving…';
    try {
        for (const body of creates) {
            await API(`/work-orders/${wizardWoId}/elevations`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
        }
    } catch (e) {
        console.error(e);
        fabToast('Failed to save some door/frame elevations.', 'error');
    } finally {
        btn.disabled = false;
        wizardComplete();
    }
}

function wizardComplete() {
    hideModal(document.getElementById('createWoModal'));
    loadWorkOrders();
    if (wizardWoId) openWODetail(wizardWoId);
}

// ── Wizard step 2: bulk elevation row builder ──
// prefill: optional { type, tag, quantity, scope } from Excel import
// Resolve an imported "Type" cell to a configured elevation type id, using each
// type's name plus its admin-defined linked names (aliases). Case- and
// whitespace-insensitive. Tries a whole-cell match first, then falls back to
// "the cell contains this term" — longest matching term wins so "Curtain Wall"
// beats a bare "CW". Returns null when nothing matches.
function resolveElevTypeId(raw) {
    const cell = String(raw ?? '').trim().toLowerCase();
    if (!cell) return null;
    const termsOf = t => [t.name, ...(t.aliases || [])]
        .map(s => String(s).trim().toLowerCase())
        .filter(Boolean);

    for (const t of elevTypes) {
        if (termsOf(t).includes(cell)) return t.id;
    }
    let bestId = null, bestLen = 0;
    for (const t of elevTypes) {
        for (const term of termsOf(t)) {
            if (term.length > bestLen && cell.includes(term)) {
                bestId = t.id;
                bestLen = term.length;
            }
        }
    }
    return bestId;
}

// Types for which a system/tier was already offered "apply to all" — so we
// prompt only on the *first* pick per type. One set per row-builder, reset when
// the modal opens.
let _wizardSystemPrompted = new Set();
let _bulkElevSystemPrompted = new Set();

// (Re)build one elevation row's System dropdown from its type's tiers.
// `tr` is the row; `typeCls`/`sysCls` are the select class names.
function fillRowSystemSelect(tr, typeCls, sysCls, preselectId) {
    const typeSel = tr.querySelector('.' + typeCls);
    const sysSel  = tr.querySelector('.' + sysCls);
    if (!typeSel || !sysSel) return;
    const typeId = parseInt(typeSel.value) || null;
    const sets = tierSetsForType(typeId);
    const noun = tierNoun(typeId);

    if (!typeId) {
        sysSel.innerHTML = '<option value="">— Pick a type —</option>';
        sysSel.disabled = true;
        return;
    }
    if (!sets.length) {
        sysSel.innerHTML = `<option value="">No ${noun.toLowerCase()} set up</option>`;
        sysSel.disabled = true;
        return;
    }
    sysSel.disabled = false;
    const keep = sets.some(s => s.id === preselectId) ? preselectId : '';
    sysSel.innerHTML = `<option value="">— ${noun}: use default —</option>` +
        sets.map(s => `<option value="${s.id}"${s.id === keep ? ' selected' : ''}>${esc(s.name)}${s.is_default ? ' · default' : ''}</option>`).join('');
}

// First time a System is chosen for a type in this modal, offer to apply it to
// every other row of the same type that has no System set yet.
async function offerApplySystemToAll(sysSel, bodySel, typeCls, sysCls, promptedSet) {
    const tr = sysSel.closest('tr');
    const typeId = parseInt(tr.querySelector('.' + typeCls)?.value) || null;
    const setId = sysSel.value;
    if (!typeId || !setId || promptedSet.has(typeId)) return;
    promptedSet.add(typeId);

    const blanks = [...document.querySelectorAll(`${bodySel} tr`)].filter(row => {
        if (row === tr) return false;
        const t = parseInt(row.querySelector('.' + typeCls)?.value) || null;
        const s = row.querySelector('.' + sysCls);
        return t === typeId && s && !s.disabled && !s.value;
    });
    if (!blanks.length) return;

    const typeName = elevTypeById(typeId)?.name || 'this type';
    const sysName = sysSel.options[sysSel.selectedIndex]?.textContent.replace(/ · default$/, '') || 'this system';
    const n = blanks.length;
    const ok = await fabConfirm({
        title: `Apply ${tierNoun(typeId)} to all ${typeName}?`,
        message: `Set "${sysName}" on the ${n} other ${typeName} elevation${n !== 1 ? 's' : ''} that ${n !== 1 ? "don't" : "doesn't"} have one yet?`,
        confirmLabel: 'Apply to all',
        confirmClass: 'btn-primary',
    });
    if (!ok) return;
    blanks.forEach(row => { row.querySelector('.' + sysCls).value = setId; });
}

// Wizard step 2 row hooks
function onWizardRowTypeChange(typeSel) {
    fillRowSystemSelect(typeSel.closest('tr'), 'wiz-bulk-type', 'wiz-bulk-system', null);
}
function onWizardSystemChange(sysSel) {
    offerApplySystemToAll(sysSel, '#wiz-bulk-body', 'wiz-bulk-type', 'wiz-bulk-system', _wizardSystemPrompted);
}
// "Add Elevations" (existing WO) row hooks
function onBulkElevTypeChange(typeSel) {
    fillRowSystemSelect(typeSel.closest('tr'), 'bulk-type', 'bulk-system', null);
}
function onBulkElevSystemChange(sysSel) {
    offerApplySystemToAll(sysSel, '#bulk-elev-body', 'bulk-type', 'bulk-system', _bulkElevSystemPrompted);
}

function addWizardBulkRow(prefill) {
    const id = ++wizardBulkRowId;
    const typeOptions = elevTypes.map(t =>
        `<option value="${t.id}">${esc(t.name)}</option>`
    ).join('');
    const tr = document.createElement('tr');
    tr.id = `wiz-bulk-row-${id}`;
    tr.innerHTML = `
        <td><input type="text" class="form-control form-control-sm wiz-bulk-tag" placeholder="e.g. A1" autocomplete="off"></td>
        <td><select class="form-select form-select-sm wiz-bulk-type" onchange="onWizardRowTypeChange(this)"><option value="">— None —</option>${typeOptions}</select></td>
        <td><select class="form-select form-select-sm wiz-bulk-system" onchange="onWizardSystemChange(this)" disabled><option value="">— Pick a type —</option></select></td>
        <td><input type="number" class="form-control form-control-sm wiz-bulk-qty" value="1" min="1" style="width:70px"></td>
        <td><input type="date" class="form-control form-control-sm wiz-bulk-date"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input wiz-bulk-scope" checked title="Checked = Assemble, Unchecked = Kit"></td>
        <td><button class="btn btn-sm btn-ghost-danger" onclick="document.getElementById('wiz-bulk-row-${id}').remove()"><i class="ti ti-x"></i></button></td>`;
    document.getElementById('wiz-bulk-body').appendChild(tr);

    if (prefill) {
        tr.querySelector('.wiz-bulk-tag').value = prefill.tag || '';
        tr.querySelector('.wiz-bulk-qty').value = prefill.quantity || 1;
        tr.querySelector('.wiz-bulk-scope').checked = (prefill.scope !== 'kit');
        // Match the imported type against each elevation type's name + linked names.
        const matchId = resolveElevTypeId(prefill.type);
        if (matchId) tr.querySelector('.wiz-bulk-type').value = matchId;
    }
    // Fill the system dropdown for the row's (possibly prefilled) type. Leave it
    // unset so the user's first pick drives the "apply to all" prompt.
    fillRowSystemSelect(tr, 'wiz-bulk-type', 'wiz-bulk-system', null);
}

// ── Wizard step 3: door/frame row builder ──
// prefill: optional { tag } from Excel import (rows tagged "Door"; quantity is
// pre-expanded into one row per unit before this is called)
function addWizardDoorRow(prefill) {
    const id = ++wizardDoorRowId;
    const tr = document.createElement('tr');
    tr.id = `wiz-door-row-${id}`;
    tr.innerHTML = `
        <td><input type="text" class="form-control form-control-sm wiz-door-tag" placeholder="e.g. 101A" autocomplete="off"></td>
        <td>
          <select class="form-select form-select-sm wiz-door-leaves" onchange="updateWizardDoorRow(${id})">
            <option value="1">Single (1 leaf)</option>
            <option value="2">Pair (2 leaves)</option>
            <option value="0">Frame Only</option>
          </select>
        </td>
        <td class="text-center wiz-door-frame-cell-${id}">
          <div class="form-check d-inline-flex align-items-center gap-2 mb-0">
            <input class="form-check-input wiz-door-frame mt-0" type="checkbox" checked>
          </div>
        </td>
        <td><input type="date" class="form-control form-control-sm wiz-door-date"></td>
        <td><button class="btn btn-sm btn-ghost-danger" onclick="document.getElementById('wiz-door-row-${id}').remove()"><i class="ti ti-x"></i></button></td>`;
    document.getElementById('wiz-door-body').appendChild(tr);

    if (prefill) {
        tr.querySelector('.wiz-door-tag').value = prefill.tag || '';
        // Imported rows are explicitly "Door" only — leave frame unchecked until the user confirms.
        tr.querySelector('.wiz-door-frame').checked = false;
    }
}

function updateWizardDoorRow(id) {
    const row = document.getElementById(`wiz-door-row-${id}`);
    const leaves = row.querySelector('.wiz-door-leaves').value;
    const cell   = row.querySelector(`.wiz-door-frame-cell-${id}`);
    if (leaves === '0') {
        cell.innerHTML = '<span class="text-muted small">—</span>';
    } else if (!row.querySelector('.wiz-door-frame')) {
        cell.innerHTML = `<div class="form-check d-inline-flex align-items-center gap-2 mb-0">
            <input class="form-check-input wiz-door-frame mt-0" type="checkbox" checked></div>`;
    }
}

// ============================================================
// Helpers
// ============================================================
function esc(str) {
    if (str == null) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

// 'YYYY-MM-DD' -> 'MM/DD/YYYY'
function fmtDate(d) {
    if (!d) return '';
    const [y, m, day] = String(d).slice(0, 10).split('-');
    return (y && m && day) ? `${m}/${day}/${y}` : String(d);
}

// Bold due-date cell; adds an (i) tooltip with the first/final elevation dates
// when a work order's elevations span more than one requested date.
function dueDateHtml(wo, extraClass) {
    const first = wo.due_date_first || wo.due_date;
    const last = wo.due_date_last || first;
    if (!first) return '<span class="text-muted">—</span>';
    const past = new Date(first) < new Date(new Date().toDateString());
    const info = (last && last !== first)
        ? ` <i class="ti ti-info-circle text-muted" style="cursor:help"
             title="First due date: ${fmtDate(first)}&#10;Final due date: ${fmtDate(last)}"></i>`
        : '';
    return `<span class="fw-bold ${past ? 'text-danger' : ''} ${extraClass || ''}">${fmtDate(first)}</span>${info}`;
}

// "Last, First" (people-picker label) or "First Last" -> "FL" (first initial,
// then last), matching how the assigned-worker pills read.
function pmInitials(name) {
    if (!name) return '';
    const trimmed = name.trim();
    if (trimmed.includes(',')) {
        const [last, first] = trimmed.split(',').map(s => s.trim());
        return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase();
    }
    const parts = trimmed.split(/\s+/).filter(Boolean);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function pmPill(name) {
    if (!name) return '<span class="text-muted">—</span>';
    return `<span class="badge bg-blue-lt text-blue" title="${esc(name)}">${esc(pmInitials(name))}</span>`;
}

// Compact date field: a calendar icon + plain-text date, backed by a native
// (visually hidden) date input opened on click. `onChangeJs` runs after the
// visible label updates, with `this` bound to the hidden input, same as a
// normal inline onchange handler. Pass stopPropagation:true inside a
// clickable row so opening the picker doesn't also trigger the row's onclick.
function compactDateHtml(id, value, onChangeJs, opts = {}) {
    const stop = opts.stopPropagation ? 'event.stopPropagation();' : '';
    return `<span class="d-inline-flex align-items-center gap-1">
        <i class="ti ti-calendar text-muted" style="cursor:pointer" onclick="${stop}openDatePicker('${id}')" title="Pick a date"></i>
        <span class="small ${value ? '' : 'text-muted'}" id="${id}-label" style="cursor:pointer" onclick="${stop}openDatePicker('${id}')">${value ? fmtDate(value) : '—'}</span>
        <input type="date" id="${id}" value="${value || ''}" tabindex="-1"
            style="position:absolute;width:0;height:0;padding:0;margin:0;border:0;opacity:0;pointer-events:none"
            onclick="${stop}" onchange="${stop}updateCompactDateLabel('${id}');${onChangeJs || ''}">
    </span>`;
}

function updateCompactDateLabel(id) {
    const input = document.getElementById(id);
    const label = document.getElementById(`${id}-label`);
    if (!input || !label) return;
    label.textContent = input.value ? fmtDate(input.value) : '—';
    label.classList.toggle('text-muted', !input.value);
}

function openDatePicker(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof el.showPicker === 'function') {
        try { el.showPicker(); return; } catch (e) { /* fall through */ }
    }
    el.focus();
}

// Inline click-to-select date cell for the dashboard table — patches the WO
// directly by id (no need for the detail panel to be open).
function plannedDateCell(woId, field, value) {
    return compactDateHtml(`pd-${field}-${woId}`, value, `patchWOField(${woId}, '${field}', this.value || null)`, { stopPropagation: true });
}

// Estimated labour-hours left on open (non-terminal) stages — null/0 both
// read as "—" since a WO with no joint-based estimate and one that's fully
// worked through both compute to nothing left to flag.
function remainingHoursHtml(minutesRemaining) {
    if (!minutesRemaining) return '—';
    return (minutesRemaining / 60).toFixed(1) + ' h';
}

// Combined "remaining / total" labour-hours, e.g. "12.5 / 40.0 h" — lets a
// user gauge how much of a work order's estimated content is left without
// checking the separate Work Content and Est. Remaining columns.
function workCombinedHtml(wo) {
    if (wo.estimated_minutes == null) return '—';
    const total = (wo.estimated_minutes / 60).toFixed(1);
    const remaining = (wo.estimated_minutes_remaining || 0) / 60;
    return `${remaining.toFixed(1)} / ${total} h`;
}

function matBadge(mat) {
    if (!mat) return '<span class="badge bg-secondary-lt text-secondary">Pending</span>';
    if (mat === 'In Shop') return '<span class="badge bg-success-lt text-success">In Shop</span>';
    if (mat === 'SOF') return '<span class="badge bg-warning-lt text-warning">SOF</span>';
    return `<span class="badge bg-info-lt text-info">${esc(mat)}</span>`;
}

function formatBytes(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

// ============================================================
// Bulk Add Elevations
// ============================================================
let bulkElevRowId = 0;

function openBulkElev() {
    document.getElementById('bulk-elev-body').innerHTML = '';
    bulkElevRowId = 0;
    _bulkElevSystemPrompted = new Set();
    addBulkElevRow();
    showModal(document.getElementById('bulkElevModal'));
}

function addBulkElevRow() {
    const id = ++bulkElevRowId;
    const typeOptions = elevTypes.map(t =>
        `<option value="${t.id}">${esc(t.name)}</option>`
    ).join('');

    const tr = document.createElement('tr');
    tr.id = `bulk-elev-row-${id}`;
    tr.innerHTML = `
        <td>
            <input type="text" class="form-control form-control-sm bulk-tag"
                placeholder="e.g. A1" autocomplete="off">
        </td>
        <td>
            <select class="form-select form-select-sm bulk-type" onchange="onBulkElevTypeChange(this)">
                <option value="">— None —</option>
                ${typeOptions}
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm bulk-system" onchange="onBulkElevSystemChange(this)" disabled>
                <option value="">— Pick a type —</option>
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm bulk-qty"
                value="1" min="1" style="width:70px">
        </td>
        <td>
            <input type="date" class="form-control form-control-sm bulk-date">
        </td>
        <td class="text-center">
            <input type="checkbox" class="form-check-input bulk-scope" checked
                title="Checked = Assemble, Unchecked = Kit">
        </td>
        <td>
            <button class="btn btn-sm btn-ghost-danger"
                onclick="document.getElementById('bulk-elev-row-${id}').remove()">
                <i class="ti ti-x"></i>
            </button>
        </td>`;
    document.getElementById('bulk-elev-body').appendChild(tr);
}

async function saveBulkElev() {
    if (!currentWO) return;
    const rows = document.querySelectorAll('#bulk-elev-body tr');
    if (!rows.length) return;

    const btn = document.getElementById('bulk-elev-save');
    btn.disabled = true;
    btn.textContent = 'Creating…';

    const creates = [];
    rows.forEach(row => {
        const tag = row.querySelector('.bulk-tag')?.value.trim();
        if (!tag) return;
        creates.push({
            elevation_tag:     tag,
            elevation_type_id: row.querySelector('.bulk-type')?.value || null,
            template_set_id:   row.querySelector('.bulk-system')?.value || null,
            quantity:          parseInt(row.querySelector('.bulk-qty')?.value) || 1,
            date_requested:    row.querySelector('.bulk-date')?.value || null,
            scope:             row.querySelector('.bulk-scope')?.checked ? 'assemble' : 'kit',
        });
    });

    try {
        for (const body of creates) {
            await API(`/work-orders/${currentWO.id}/elevations`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
        }
        hideModal(document.getElementById('bulkElevModal'));
        const r = await API(`/work-orders/${currentWO.id}`);
        const wo = await r.json();
        currentWO = wo;
        renderElevations(wo.elevations || []);
        loadWorkOrders();
    } catch (e) {
        console.error(e);
        fabToast('Failed to create some elevations.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Create Elevations';
    }
}

// ============================================================
// Door / Frame Schedule
// ============================================================
let doorRowId = 0;

function openDoorSchedule() {
    document.getElementById('door-schedule-body').innerHTML = '';
    doorRowId = 0;
    addDoorRow();   // start with one empty row
    showModal(document.getElementById('doorScheduleModal'));
}

function addDoorRow() {
    const id = ++doorRowId;
    const tr = document.createElement('tr');
    tr.id = `door-row-${id}`;
    tr.innerHTML = `
        <td>
            <input type="text" class="form-control form-control-sm door-tag"
                placeholder="e.g. 101A" autocomplete="off">
        </td>
        <td>
            <select class="form-select form-select-sm door-leaves" onchange="updateDoorRow(${id})">
                <option value="1">Single (1 leaf)</option>
                <option value="2">Pair (2 leaves)</option>
                <option value="0">Frame Only</option>
            </select>
        </td>
        <td class="text-center door-frame-cell-${id}">
            <div class="form-check d-inline-flex align-items-center gap-2 mb-0">
                <input class="form-check-input door-frame mt-0" type="checkbox" checked>
            </div>
        </td>
        <td><input type="date" class="form-control form-control-sm door-date"></td>
        <td>
            <button class="btn btn-sm btn-ghost-danger" onclick="document.getElementById('door-row-${id}').remove()">
                <i class="ti ti-x"></i>
            </button>
        </td>`;
    document.getElementById('door-schedule-body').appendChild(tr);
}

function updateDoorRow(id) {
    const row = document.getElementById(`door-row-${id}`);
    const leaves = row.querySelector('.door-leaves').value;
    const frameCell = row.querySelector(`.door-frame-cell-${id}`);
    if (leaves === '0') {
        // Frame Only — frame is implied, hide checkbox
        frameCell.innerHTML = '<span class="text-muted small">—</span>';
    } else if (!row.querySelector('.door-frame')) {
        // Restore checkbox if switching back from Frame Only
        frameCell.innerHTML = `
            <div class="form-check d-inline-flex align-items-center gap-2 mb-0">
                <input class="form-check-input door-frame mt-0" type="checkbox" checked>
            </div>`;
    }
}

async function saveDoorSchedule() {
    if (!currentWO) return;

    const doorTypeId = elevTypes.find(t => t.name === 'Door')?.id;
    const frameTypeId = elevTypes.find(t => t.name === 'Frame')?.id;

    if (!doorTypeId || !frameTypeId) {
        fabToast('Door or Frame elevation types not found. Check Admin → Elevation Types.', 'error');
        return;
    }

    const rows = document.querySelectorAll('#door-schedule-body tr');
    if (!rows.length) return;

    const btn = document.getElementById('door-schedule-save');
    btn.disabled = true;
    btn.textContent = 'Creating…';

    const creates = [];
    rows.forEach(row => {
        const tag           = row.querySelector('.door-tag')?.value.trim();
        const leaves        = parseInt(row.querySelector('.door-leaves')?.value ?? '1');
        const frameChecked  = row.querySelector('.door-frame')?.checked ?? false;
        const dateRequested = row.querySelector('.door-date')?.value || null;
        if (!tag) return;
        creates.push(...buildDoorCreates(tag, leaves, frameChecked, doorTypeId, frameTypeId, dateRequested));
    });

    try {
        for (const body of creates) {
            await API(`/work-orders/${currentWO.id}/elevations`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
        }
        hideModal(document.getElementById('doorScheduleModal'));
        const r = await API(`/work-orders/${currentWO.id}`);
        const wo = await r.json();
        currentWO = wo;
        renderElevations(wo.elevations || []);
        loadWorkOrders();
    } catch (e) {
        console.error(e);
        fabToast('Failed to create some elevations.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Create Elevations';
    }
}

async function openQuickJobCreate(prefill) {
    // No explicit prefill + an Excel import that matched no job => carry the
    // parsed job number / name / PM / superintendent into the form.
    if (!prefill && _wizardImportMeta.jobNumber && !document.getElementById('new-wo-job').value) {
        prefill = {
            job_number: _wizardImportMeta.jobNumber,
            job_name: _wizardImportMeta.jobName || '',
            project_manager: _wizardImportMeta.projectManager || null,
            superintendent: _wizardImportMeta.superintendent || null,
        };
    }
    prefill = prefill || {};

    document.getElementById('qj-number').value = prefill.job_number || '';
    document.getElementById('qj-name').value = prefill.job_name || '';
    document.getElementById('qj-customer').value = prefill.customer_name || '';
    document.getElementById('qj-start').value = '';
    document.getElementById('qj-target').value = '';
    document.getElementById('qj-status').value = 'active';
    document.getElementById('qj-notes').value = '';

    new bootstrap.Modal(document.getElementById('quickJobModal')).show();

    // PM + superintendent: use an explicit id, else fuzzy-match the sheet's name.
    await populatePeopleSelectMatched(document.getElementById('qj-pm'),
        { id: prefill.project_manager_id, name: prefill.project_manager });
    await populatePeopleSelectMatched(document.getElementById('qj-super'),
        { id: prefill.superintendent_id, name: prefill.superintendent });
}

async function saveQuickJob() {
    const jobNumber = document.getElementById('qj-number').value.trim();
    const jobName = document.getElementById('qj-name').value.trim();
    if (!jobNumber || !jobName) { fabToast('Job Number and Job Name are required.', 'info'); return; }

    try {
        const r = await API('/business-jobs', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                job_number: jobNumber,
                job_name: jobName,
                customer_name: document.getElementById('qj-customer').value || null,
                project_manager_id: document.getElementById('qj-pm').value || null,
                superintendent_id: document.getElementById('qj-super').value || null,
                start_date: document.getElementById('qj-start').value || null,
                target_completion_date: document.getElementById('qj-target').value || null,
                status: document.getElementById('qj-status').value,
                notes: document.getElementById('qj-notes').value || null,
            }),
        });
        if (!r.ok) {
            const err = await r.json();
            const fieldErrors = err.errors ? Object.values(err.errors).flat().join(' ') : '';
            throw new Error(fieldErrors || err.message || 'Failed to create job');
        }
        const data = await r.json();
        bootstrap.Modal.getInstance(document.getElementById('quickJobModal')).hide();

        // Add the new job to the picker's list and select it.
        const job = {
            id: data.job.id,
            job_number: data.job.job_number,
            job_name: data.job.job_name,
            customer_name: data.job.customer_name || document.getElementById('qj-customer').value || null,
            project_manager: data.job.project_manager || null,
            project_manager_id: data.job.project_manager_id || null,
            superintendent: data.job.superintendent || null,
            superintendent_id: data.job.superintendent_id || null,
        };
        if (!wizardJobs.some(j => String(j.id) === String(job.id))) wizardJobs.unshift(job);
        selectWizardJob(job.id);
    } catch (e) {
        fabToast('Error: ' + e.message, 'error');
    }
}
</script>

<!-- Quick Create Job Modal (from Work Orders wizard) -->
<div class="modal fade" id="quickJobModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create New Job</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label required">Job Number</label>
            <input type="text" class="form-control" id="qj-number" required>
          </div>
          <div class="col-md-6">
            <label class="form-label required">Job Name</label>
            <input type="text" class="form-control" id="qj-name" required>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Customer Name</label>
            <input type="text" class="form-control" id="qj-customer">
          </div>
          <div class="col-md-4">
            <label class="form-label">Project Manager</label>
            <select class="form-select" id="qj-pm"></select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Superintendent</label>
            <select class="form-select" id="qj-super"></select>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Start Date</label>
            <input type="date" class="form-control" id="qj-start">
          </div>
          <div class="col-md-4">
            <label class="form-label">Target Completion</label>
            <input type="date" class="form-control" id="qj-target">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" id="qj-status">
              <option value="active">Active</option>
              <option value="on_hold">On Hold</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="qj-notes" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveQuickJob()">Create Job</button>
      </div>
    </div>
  </div>
</div>
<!-- ── Elevation completion prompt ── -->
<div id="elev-complete-prompt" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:3000;align-items:center;justify-content:center;">
  <div class="card shadow-lg" style="width:min(400px,92vw);margin:0">
    <div class="card-header">
      <h5 class="card-title mb-0"><i class="ti ti-circle-check text-success me-2"></i>Elevation Complete?</h5>
    </div>
    <div class="card-body">
      <p class="mb-0" id="elev-cp-msg">All stages are done — mark this elevation as complete?</p>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
      <button class="btn btn-ghost-secondary" onclick="dismissElevPrompt('complete')">Not Yet</button>
      <button class="btn btn-success" onclick="confirmElevComplete()">Mark Complete</button>
    </div>
  </div>
</div>

<!-- ── Elevation reopen prompt ── -->
<div id="elev-reopen-prompt" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:3000;align-items:center;justify-content:center;">
  <div class="card shadow-lg" style="width:min(380px,92vw);margin:0">
    <div class="card-header">
      <h5 class="card-title mb-0"><i class="ti ti-arrow-back-up text-warning me-2"></i>Reopen Elevation?</h5>
    </div>
    <div class="card-body">
      <p class="mb-0" id="elev-rp-msg">This elevation was marked complete. Reopen it as in-progress?</p>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
      <button class="btn btn-ghost-secondary" onclick="dismissElevPrompt('reopen')">Keep Complete</button>
      <button class="btn btn-warning" onclick="confirmElevReopen()">Yes, Reopen</button>
    </div>
  </div>
</div>

<!-- ── Bulk complete prompt (stage across WO / all stages on an elevation) ── -->
<div id="bulk-complete-prompt" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:3000;align-items:center;justify-content:center;">
  <div class="card shadow-lg" style="width:min(420px,92vw);margin:0">
    <div class="card-header">
      <h5 class="card-title mb-0"><i class="ti ti-checks text-success me-2"></i><span id="bcp-title">Bulk Complete</span></h5>
    </div>
    <div class="card-body">
      <p class="mb-3" id="bcp-msg"></p>
      <div class="mb-3" id="bcp-stage-wrap" style="display:none">
        <label class="form-label form-label-sm mb-1">Stage</label>
        <select class="form-select form-select-sm" id="bcp-stage"></select>
      </div>
      <div class="mb-0" id="bcp-user-wrap" style="display:none">
        <label class="form-label form-label-sm mb-1">Completed by</label>
        <select class="form-select form-select-sm" id="bcp-user">
          <option value="">— none —</option>
        </select>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
      <button class="btn btn-ghost-secondary" onclick="_bcpDone(false)">Cancel</button>
      <button class="btn btn-success" onclick="_bcpDone(true)">Complete</button>
    </div>
  </div>
</div>

<!-- ── WO on-hold prompt (note required) ── -->
<div id="wo-hold-prompt" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:3000;align-items:center;justify-content:center;">
  <div class="card shadow-lg" style="width:min(440px,92vw);margin:0">
    <div class="card-header">
      <h5 class="card-title mb-0"><i class="ti ti-player-pause text-orange me-2"></i>Place Work Order On Hold</h5>
    </div>
    <div class="card-body">
      <label class="form-label form-label-sm mb-1">Reason for hold <span class="text-danger">*</span></label>
      <textarea class="form-control form-control-sm" id="wo-hold-note" rows="3"
        placeholder="Why is this work order on hold?"></textarea>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
      <button class="btn btn-ghost-secondary" onclick="dismissWoHoldPrompt()">Cancel</button>
      <button class="btn btn-warning" onclick="confirmWoHold()">Place On Hold</button>
    </div>
  </div>
</div>

<!-- ── WO completion prompt — step 1: mark complete ── -->
<div id="wo-complete-prompt" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:3000;align-items:center;justify-content:center;">
  <div class="card shadow-lg" style="width:min(440px,92vw);margin:0">
    <div class="card-header">
      <h5 class="card-title mb-0"><i class="ti ti-circle-check text-success me-2"></i>Mark Work Order Complete?</h5>
    </div>
    <div class="card-body">
      <p class="mb-3">All elevations and work-order steps are complete. Mark this work order as complete?</p>
      <label class="form-label form-label-sm mb-1">Completion notes <span class="text-muted">(optional)</span></label>
      <textarea class="form-control form-control-sm" id="wo-complete-note" rows="2"
        placeholder="Any notes about this completion…"></textarea>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
      <button class="btn btn-ghost-secondary" onclick="dismissWoCompletePrompt()">Not Yet</button>
      <button class="btn btn-success" onclick="confirmWoComplete()">Mark Complete</button>
    </div>
  </div>
</div>

<!-- ── WO completion prompt — step 2: send email (manager/admin) ── -->
<div id="wo-completion-email-prompt" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:3000;align-items:center;justify-content:center;">
  <div class="card shadow-lg" style="width:min(460px,92vw);margin:0">
    <div class="card-header">
      <h5 class="card-title mb-0"><i class="ti ti-mail text-primary me-2"></i>Send Completion Email?</h5>
    </div>
    <div class="card-body">
      <p class="mb-3">Email the project manager and admins that this work order is complete?</p>
      <label class="form-label form-label-sm mb-1">Message <span class="text-muted">(optional)</span></label>
      <textarea class="form-control form-control-sm" id="wo-email-note" rows="3"
        placeholder="Add a note to include in the email…"></textarea>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
      <button class="btn btn-ghost-secondary" onclick="dismissWoCompletionEmail()">Skip</button>
      <button class="btn btn-primary" onclick="sendWoCompletionEmail()">Send Email</button>
    </div>
  </div>
</div>
@endpush
