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
.wo-offcanvas    { width: 700px !important; }
.elev-row td     { vertical-align: middle; }
.wo-detail-header { background: var(--tblr-bg-surface-secondary, var(--tblr-light)); }
.card.bg-light, table.bg-light { background: var(--tblr-bg-surface-secondary) !important; }
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
            <div class="col-auto ms-auto">
              <span class="text-muted small" id="wo-count-label"></span>
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
              <thead>
                <tr>
                  <th style="width:3rem">#</th>
                  <th>Release</th>
                  <th>Job Name</th>
                  <th>PM</th>
                  <th>Assigned</th>
                  <th>Material</th>
                  <th>Elevations</th>
                  <th class="w-1"></th>
                </tr>
              </thead>
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
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title" id="wo-detail-title">Work Order</h5>
    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-sm btn-ghost-warning" onclick="archiveCurrentWO()" id="btn-archive-wo" title="Archive">
        <i class="ti ti-archive"></i>
      </button>
      <button class="btn btn-sm btn-ghost-secondary" onclick="closeOffcanvas('wo-detail')" title="Close">
        <i class="ti ti-x"></i>
      </button>
    </div>
  </div>
  <div class="offcanvas-body p-0">

    <!-- WO Header -->
    <div class="p-3 border-bottom wo-detail-header">
      <div class="row g-3">
        <div class="col-6 col-md-3">
          <div class="subheader">Job</div>
          <div class="fw-bold" id="d-job-number">—</div>
        </div>
        <div class="col-6 col-md-5">
          <div class="subheader">Job Name</div>
          <div id="d-job-name">—</div>
        </div>
        <div class="col-6 col-md-2">
          <div class="subheader">PM</div>
          <div id="d-pm">—</div>
        </div>
        <div class="col-6 col-md-2">
          <div class="subheader">Division</div>
          <div id="d-division">—</div>
        </div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-auto">
          <div class="subheader">Date Issued</div>
          <input type="date" class="form-control form-control-sm" id="d-date-issued" style="width:150px"
            onchange="patchWO('date_issued', this.value)">
        </div>
        <div class="col-auto">
          <div class="subheader">Priority</div>
          <input type="number" class="form-control form-control-sm" id="d-priority" style="width:80px"
            min="1" placeholder="—" onchange="patchWO('priority', this.value ? parseInt(this.value) : null)">
        </div>
        <div class="col-auto">
          <div class="subheader">Material Delivery</div>
          <div class="input-group input-group-sm" style="width:260px">
            <input type="text" class="form-control" id="d-material" placeholder="Date, In Shop, SOF"
              onchange="patchWO('material_delivery', this.value || null)">
            <button class="btn btn-ghost-success" type="button"
              onclick="setMaterial('In Shop')">In Shop</button>
            <button class="btn btn-ghost-warning" type="button"
              onclick="setMaterial('SOF')">SOF</button>
          </div>
        </div>
        <div class="col">
          <div class="subheader">Notes</div>
          <input type="text" class="form-control form-control-sm" id="d-notes" placeholder="Notes"
            onchange="patchWO('notes', this.value || null)">
        </div>
      </div>
      <!-- Assigned Workers -->
      <div class="mt-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <div class="subheader">Assigned Workers</div>
          <button class="btn btn-xs btn-ghost-primary" onclick="toggleAssignPanel()">
            <i class="ti ti-pencil" style="font-size:.8rem"></i>
          </button>
        </div>
        <div id="d-assigned-display" class="d-flex flex-wrap gap-1 min-height-1"></div>
        <div id="d-assign-panel" style="display:none;" class="mt-2 p-2 border rounded">
          <div id="d-assign-checkboxes" class="d-flex flex-wrap gap-2 mb-2"></div>
          <button class="btn btn-sm btn-primary" onclick="saveWOAssignments()">Save</button>
          <button class="btn btn-sm btn-ghost-secondary ms-1" onclick="toggleAssignPanel()">Cancel</button>
        </div>
      </div>
    </div>

    <!-- WO Steps -->
    <div class="px-3 py-2 border-bottom">
      <div class="d-flex align-items-center gap-1 flex-wrap" id="wo-steps-container">
        <span class="text-muted small">Loading steps…</span>
      </div>
    </div>

    <!-- Shop Drawings -->
    <div class="p-3 border-bottom">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Shop Drawings</h5>
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

    <!-- Elevations -->
    <div class="p-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Elevations</h5>
        <div class="btn-group btn-group-sm">
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
</div>
<div id="wo-backdrop" onclick="closeOffcanvas('wo-detail')"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:1040;"></div>

<!-- ============================================================
     Create WO Wizard Modal (3-step)
     ============================================================ -->
<div class="modal modal-blur fade" id="createWoModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header border-bottom-0 pb-0">
        <div>
          <h5 class="modal-title mb-0" id="wo-wizard-title">New Work Order</h5>
          <div class="text-muted small" id="wo-wizard-subtitle">Step 1 of 3 — Work order details</div>
        </div>
        <button type="button" class="btn-close" onclick="closeWoWizard()"></button>
      </div>

      <!-- Step indicators -->
      <div class="px-3 pt-2">
        <ul class="steps steps-green">
          <li class="step-item active" id="wiz-ind-1">Work Order</li>
          <li class="step-item" id="wiz-ind-2">Elevations</li>
          <li class="step-item" id="wiz-ind-3">Doors &amp; Frames</li>
        </ul>
      </div>

      <div class="modal-body">

        <!-- ── Step 1: WO Details ── -->
        <div id="wiz-step-1">
          <!-- Excel import -->
          <div class="mb-3 p-3 border rounded bg-light">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="ti ti-file-spreadsheet text-success"></i>
              <span class="fw-medium">Import from Excel</span>
              <span class="text-muted small">Optional — upload a WO sheet to autofill elevations</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <label class="btn btn-sm btn-outline-success mb-0" for="wo-excel-upload">
                <i class="ti ti-upload me-1"></i>Upload Excel
                <input type="file" id="wo-excel-upload" class="d-none" accept=".xlsx,.xls"
                  onchange="importWOExcel(this)">
              </label>
              <span id="wo-excel-status" class="text-muted small"></span>
            </div>
            <div id="wo-excel-hint" style="display:none" class="mt-2 small">
              <span class="badge bg-blue-lt text-blue me-1">Division: <span id="wo-excel-division">—</span></span>
              <span id="wo-excel-elev-count" class="text-muted"></span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label required">Job</label>
            <select class="form-select" id="new-wo-job" required>
              <option value="">— Select a job —</option>
            </select>
            <div class="mt-1">
              <button type="button" class="btn btn-sm btn-ghost-primary" onclick="openQuickJobCreate()">
                <i class="ti ti-plus me-1"></i>Create New Job
              </button>
            </div>
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
                  <th style="width:22%">Tag</th>
                  <th style="width:22%">Type</th>
                  <th style="width:10%">Qty</th>
                  <th style="width:20%">Date Requested</th>
                  <th style="width:14%" title="Checked = Assemble, Unchecked = Kit">Assemble</th>
                  <th style="width:12%"></th>
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
          <div class="col-md-6">
            <label class="form-label">Type</label>
            <select class="form-select" id="elev-type">
              <option value="">— None —</option>
            </select>
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
  <div class="modal-dialog modal-lg modal-dialog-centered">
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
                <th style="width:22%">Tag</th>
                <th style="width:22%">Type</th>
                <th style="width:10%">Qty</th>
                <th style="width:20%">Date Requested</th>
                <th style="width:14%" title="Checked = Assemble, Unchecked = Kit">Assemble</th>
                <th style="width:12%"></th>
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
        <p class="text-muted small mb-3">Drag to set the order the shop floor should work these. Unprioritized work orders aren't shown here.</p>
        <ul class="list-group" id="reorder-queue-list" style="max-height:60vh;overflow-y:auto;"></ul>
      </div>
      <div class="modal-footer">
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
    await Promise.all([loadWorkOrders(), loadElevTypes(), loadFabUsers()]);

    // Open WO from query param (e.g. linked from Jobs page)
    const params = new URLSearchParams(location.search);
    if (params.has('wo')) openWODetail(parseInt(params.get('wo')));
});

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

function renderWOList(wos) {
    document.getElementById('wo-loading').style.display = 'none';
    document.getElementById('wo-count-label').textContent = `${wos.length} work order${wos.length !== 1 ? 's' : ''}`;

    if (wos.length === 0) {
        document.getElementById('wo-empty').style.display = 'block';
        return;
    }

    document.getElementById('wo-table-wrap').style.display = 'block';
    const tbody = document.getElementById('wo-tbody');
    tbody.innerHTML = wos.map(wo => {
        const assignedPills = (wo.assigned_users || []).map(u =>
            `<span class="badge bg-blue-lt text-blue" title="${esc(u.name)}">${esc(u.initials || u.name.slice(0,2))}</span>`
        ).join(' ') || '<span class="text-muted">—</span>';
        const priorityCell = wo.priority != null
            ? `<span class="badge bg-secondary-lt text-secondary">${wo.priority}</span>`
            : '<span class="text-muted">—</span>';
        return `<tr style="cursor:pointer" onclick="openWODetail(${wo.id})">
            <td>${priorityCell}</td>
            <td><strong>${esc(wo.release_label)}</strong></td>
            <td>${esc(wo.job?.job_name || '—')}</td>
            <td class="text-muted">${esc(wo.job?.project_manager || '—')}</td>
            <td>${assignedPills}</td>
            <td>${matBadge(wo.material_delivery)}</td>
            <td>
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
        await Promise.all(reorderQueueWOs.map((wo, idx) =>
            API(`/work-orders/${wo.id}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ priority: idx + 1 }),
            })
        ));
        hideModal(document.getElementById('reorderQueueModal'));
        await loadWorkOrders();
    } catch (e) {
        console.error(e);
        fabToast('Failed to save queue order. Please try again.', 'error');
    } finally {
        btn.disabled = false;
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

function populateDetail(wo) {
    const job = wo.job || {};
    document.getElementById('wo-detail-title').textContent = wo.release_label || `WO #${wo.id}`;
    document.getElementById('d-job-number').textContent = job.job_number || '—';
    document.getElementById('d-job-name').textContent = job.job_name || '—';
    document.getElementById('d-pm').textContent = job.project_manager || '—';
    document.getElementById('d-division').textContent = job.division || '—';
    document.getElementById('d-date-issued').value = wo.date_issued || '';
    document.getElementById('d-priority').value = wo.priority != null ? wo.priority : '';
    document.getElementById('d-material').value = wo.material_delivery || '';
    document.getElementById('d-notes').value = wo.notes || '';

    renderAssignedUsers(wo.assigned_users || []);
    renderWoSteps(wo.id, wo.steps || []);
    renderDrawings(wo.drawings || []);
    renderElevations(wo.elevations || []);
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
    } catch (e) {
        console.error(e);
    }
}

function setMaterial(val) {
    document.getElementById('d-material').value = val;
    patchWO('material_delivery', val);
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
    container.innerHTML = `<span class="text-muted small me-2" style="white-space:nowrap">WO Steps:</span>${html}
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
        await API(`/work-orders/${woId}/steps/complete-all`, { method: 'PATCH' });
        await reloadWoSteps();
        fabToast('Steps marked complete.', 'success');
    } catch (e) {
        console.error(e);
        fabToast('Failed to complete steps.', 'error');
    }
}

async function reloadWoSteps() {
    if (!currentWO) return;
    try {
        const r = await API(`/work-orders/${currentWO.id}/steps`);
        const data = await r.json();
        renderWoSteps(currentWO.id, data.steps || []);
    } catch (e) { console.error(e); }
}

async function cycleWoStep(stepId, currentStatus) {
    const nextStatus = await FabStep.cycleStepStatus(currentStatus);
    if (!nextStatus) return;
    try {
        await API(`/job-steps/${stepId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: nextStatus }),
        });
        reloadWoSteps();
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
    for (const file of files) {
        const fd = new FormData();
        fd.append('file', file);
        try {
            await fetch(`/api/v1/work-orders/${currentWO.id}/drawings`, {
                method: 'POST',
                body: fd,
            });
        } catch (e) {
            console.error('Upload failed:', e);
        }
    }
    document.getElementById('drawings-loading').style.display = 'none';
    document.getElementById('drawing-upload').value = '';
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
    const typeBadge = e.elevation_type
        ? `<span class="badge" style="background:${esc(e.elevation_type.color || '#666')}">${esc(e.elevation_type.name)}</span>`
        : '<span class="text-muted">—</span>';

    const nextLabels = { pending: 'Start', in_progress: 'Complete', complete: 'Reset', blocked: 'Reset', not_required: 'Reset', on_hold: 'Reset' };
    const stages = e.stages || [];
    const pips = stages.map(s =>
        `<span class="pip pip-${s.status}" style="cursor:pointer"
            title="${s.name}: ${s.status} → left-click to ${nextLabels[s.status] || 'advance'}, right-click for options"
            onclick="cycleStage(${s.id},'${s.status}',event)"
            oncontextmenu="stageContextMenu(${s.id},'${s.status}',event)"></span>`
    ).join('');

    const completedInfo = e.date_completed
        ? `<span class="badge bg-success">${e.date_completed}</span>${e.completed_by_name ? `<br><small class="text-muted">${esc(e.completed_by_name)}</small>` : ''}`
        : `<span class="text-muted small">—</span>`;

    const hasStages = stages.length > 0;
    const expandBtn = hasStages
        ? `<button class="btn btn-ghost-secondary btn-sm px-1" onclick="toggleStages('elev-stages-${e.id}', this)" title="Expand stages">
               <i class="ti ti-chevron-right" style="transition:transform .15s"></i>
           </button>`
        : '';

    const stageColors = { pending: 'secondary', in_progress: 'warning', complete: 'success', blocked: 'danger', not_required: 'blue-lt', on_hold: 'orange' };
    const stageLabels = { pending: 'Pending', in_progress: 'In Progress', complete: 'Done', blocked: 'Blocked', not_required: 'N/R', on_hold: 'On Hold' };
    const stageDetailRows = stages.map(s => `
        <tr>
            <td style="padding-left:2rem" class="text-muted small">${esc(s.name)}</td>
            <td>
                <span class="badge bg-${stageColors[s.status] || 'secondary'}" style="cursor:pointer"
                    onclick="cycleStage(${s.id},'${s.status}',event)"
                    oncontextmenu="stageContextMenu(${s.id},'${s.status}',event)">
                    ${stageLabels[s.status] || s.status}
                </span>
            </td>
            <td class="text-muted small">${s.assigned_name ? esc(s.assigned_name) : '—'}</td>
            <td class="text-muted small">${s.started_at ? new Date(s.started_at).toLocaleDateString() : '—'}</td>
            <td class="text-muted small">${s.completed_at ? new Date(s.completed_at).toLocaleDateString() : '—'}${s.completed_by_name ? `<br><span class="text-muted" style="font-size:.7rem">${esc(s.completed_by_name)}</span>` : ''}</td>
        </tr>`).join('');

    const stageDetailBlock = hasStages ? `
        <tr id="elev-stages-${e.id}" style="display:none">
            <td colspan="7" class="p-0">
                <table class="table table-sm mb-0 bg-light">
                    <thead>
                        <tr class="text-muted" style="font-size:.7rem;text-transform:uppercase">
                            <th style="padding-left:2rem">Stage</th>
                            <th>Status</th>
                            <th>Assigned</th>
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

    return `<tr class="elev-row">
        <td><strong>${esc(e.elevation_tag)}</strong>${scopeBadge}</td>
        <td>${typeBadge}</td>
        <td>${e.quantity}</td>
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

// ============================================================
// Add / Edit Elevation
// ============================================================
async function loadElevTypes() {
    try {
        const r = await API('/elevation-types');
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

async function setStageStatus(stageId, status) {
    try {
        await API(`/work-order-stages/${stageId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status }),
        });
        const expanded = getExpandedElevationIds();
        const r = await API(`/work-orders/${currentWO.id}`);
        const wo = await r.json();
        currentWO = wo;
        renderElevations(wo.elevations || []);
        restoreExpandedElevations(expanded);
        loadWorkOrders();
        checkElevationCompletion(stageId, status);
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

function showElevCompletePrompt(elev) {
    _elevPromptId = elev.id;
    document.getElementById('elev-cp-msg').textContent =
        `All stages for "${elev.elevation_tag}" are done — mark elevation as complete?`;
    // Populate completed-by with fab users
    const sel = document.getElementById('elev-cp-user');
    sel.innerHTML = '<option value="">— select —</option>';
    fabUsers.forEach(u => {
        const o = document.createElement('option');
        o.value = u.id; o.textContent = u.name;
        sel.appendChild(o);
    });
    const prompt = document.getElementById('elev-complete-prompt');
    prompt.style.display = 'flex';
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
    const completedById = document.getElementById('elev-cp-user').value || null;
    try {
        await API(`/elevations/${_elevPromptId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                date_completed:  new Date().toISOString().slice(0, 10),
                completed_by_id: completedById,
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
    showModal(document.getElementById('addElevModal'));
}

async function saveElev() {
    if (!currentWO) return;
    const elevId = document.getElementById('elev-id').value;
    const isEdit = !!elevId;

    const body = {
        elevation_tag: document.getElementById('elev-tag').value,
        elevation_type_id: document.getElementById('elev-type').value || null,
        quantity: parseInt(document.getElementById('elev-qty').value) || 1,
        date_requested: document.getElementById('elev-date-req').value || null,
        date_completed: document.getElementById('elev-date-done').value || null,
        completed_by_id: document.getElementById('elev-completed-by').value || null,
        scope: document.getElementById('elev-scope').checked ? 'assemble' : 'kit',
        notes: document.getElementById('elev-notes').value || null,
    };

    if (!body.elevation_tag) { fabToast('Elevation Tag is required.', 'info'); return; }

    try {
        if (isEdit) {
            await API(`/elevations/${elevId}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
        } else {
            await API(`/work-orders/${currentWO.id}/elevations`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
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
// Create WO Wizard — Excel Import
// ============================================================
let _wizardImportedElevations = [];

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

        document.getElementById('wo-excel-division').textContent = data.division || '—';
        const n = _wizardImportedElevations.length;
        document.getElementById('wo-excel-elev-count').textContent =
            n > 0 ? `${n} elevation${n !== 1 ? 's' : ''} ready for Step 2` : 'No elevations found';

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
    _wizardImportedElevations = [];

    // Reset excel import UI
    document.getElementById('wo-excel-upload').value = '';
    document.getElementById('wo-excel-status').textContent = '';
    document.getElementById('wo-excel-hint').style.display = 'none';

    // Reset step 1
    const sel = document.getElementById('new-wo-job');
    sel.innerHTML = '<option value="">— Select a job —</option>';
    try {
        const r = await API('/business-jobs?per_page=500&status=active');
        const data = await r.json();
        (data.jobs || []).forEach(j => {
            const opt = document.createElement('option');
            opt.value = j.id;
            opt.textContent = `${j.job_number} – ${j.job_name}`;
            sel.appendChild(opt);
        });
    } catch (e) { console.error(e); }
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
                material_delivery: document.getElementById('new-wo-material').value || null,
                notes:             document.getElementById('new-wo-notes').value || null,
            }),
        });
        if (!r.ok) throw new Error('Failed to create');
        const data = await r.json();
        wizardWoId    = data.id;
        wizardWoLabel = data.release_label || `#${data.id}`;
        loadWorkOrders();

        // Pre-populate step 2 from Excel import (if any)
        document.getElementById('wiz-bulk-body').innerHTML = '';
        wizardBulkRowId = 0;
        if (_wizardImportedElevations.length > 0) {
            _wizardImportedElevations.forEach(e => addWizardBulkRow(e));
        } else {
            addWizardBulkRow();
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
function addWizardBulkRow(prefill) {
    const id = ++wizardBulkRowId;
    const typeOptions = elevTypes.map(t =>
        `<option value="${t.id}">${esc(t.name)}</option>`
    ).join('');
    const tr = document.createElement('tr');
    tr.id = `wiz-bulk-row-${id}`;
    tr.innerHTML = `
        <td><input type="text" class="form-control form-control-sm wiz-bulk-tag" placeholder="e.g. A1" autocomplete="off"></td>
        <td><select class="form-select form-select-sm wiz-bulk-type"><option value="">— None —</option>${typeOptions}</select></td>
        <td><input type="number" class="form-control form-control-sm wiz-bulk-qty" value="1" min="1" style="width:70px"></td>
        <td><input type="date" class="form-control form-control-sm wiz-bulk-date"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input wiz-bulk-scope" checked title="Checked = Assemble, Unchecked = Kit"></td>
        <td><button class="btn btn-sm btn-ghost-danger" onclick="document.getElementById('wiz-bulk-row-${id}').remove()"><i class="ti ti-x"></i></button></td>`;
    document.getElementById('wiz-bulk-body').appendChild(tr);

    if (prefill) {
        tr.querySelector('.wiz-bulk-tag').value = prefill.tag || '';
        tr.querySelector('.wiz-bulk-qty').value = prefill.quantity || 1;
        tr.querySelector('.wiz-bulk-scope').checked = (prefill.scope !== 'kit');
        // Match type name to elevTypes list (case-insensitive)
        if (prefill.type) {
            const match = elevTypes.find(t => t.name.toLowerCase() === prefill.type.toLowerCase());
            if (match) tr.querySelector('.wiz-bulk-type').value = match.id;
        }
    }
}

// ── Wizard step 3: door/frame row builder ──
function addWizardDoorRow() {
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
            <select class="form-select form-select-sm bulk-type">
                <option value="">— None —</option>
                ${typeOptions}
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

function openQuickJobCreate() {
    document.getElementById('qj-number').value = '';
    document.getElementById('qj-name').value = '';
    document.getElementById('qj-customer').value = '';
    document.getElementById('qj-pm').value = '';
    document.getElementById('qj-start').value = '';
    document.getElementById('qj-target').value = '';
    document.getElementById('qj-status').value = 'active';
    document.getElementById('qj-notes').value = '';
    const m = new bootstrap.Modal(document.getElementById('quickJobModal'));
    m.show();
}

async function saveQuickJob() {
    const jobNumber = document.getElementById('qj-number').value.trim();
    const jobName = document.getElementById('qj-name').value.trim();
    if (!jobNumber || !jobName) { fabToast('Job Number and Job Name are required.', 'info'); return; }

    try {
        const r = await fetch('/api/v1/business-jobs', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                job_number: jobNumber,
                job_name: jobName,
                customer_name: document.getElementById('qj-customer').value || null,
                project_manager: document.getElementById('qj-pm').value || null,
                start_date: document.getElementById('qj-start').value || null,
                target_completion_date: document.getElementById('qj-target').value || null,
                status: document.getElementById('qj-status').value,
                notes: document.getElementById('qj-notes').value || null,
            }),
        });
        if (!r.ok) {
            const err = await r.json();
            throw new Error(err.message || 'Failed to create job');
        }
        const data = await r.json();
        bootstrap.Modal.getInstance(document.getElementById('quickJobModal')).hide();

        // Reload job list and select the new job
        const sel = document.getElementById('new-wo-job');
        if (sel) {
            const opt = document.createElement('option');
            opt.value = data.job.id;
            opt.textContent = `${data.job.job_number} — ${data.job.job_name}`;
            sel.appendChild(opt);
            sel.value = data.job.id;
        }
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
          <div class="col-md-6">
            <label class="form-label">Customer Name</label>
            <input type="text" class="form-control" id="qj-customer">
          </div>
          <div class="col-md-6">
            <label class="form-label">Project Manager</label>
            <input type="text" class="form-control" id="qj-pm">
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
      <p class="mb-3" id="elev-cp-msg">All stages are done — mark this elevation as complete?</p>
      <div class="mb-0">
        <label class="form-label form-label-sm mb-1">Completed by</label>
        <select class="form-select form-select-sm" id="elev-cp-user">
          <option value="">— select —</option>
        </select>
      </div>
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
@endpush
