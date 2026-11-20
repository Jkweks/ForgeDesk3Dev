@extends('layouts.app')

@section('title', 'Job - Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle"><a href="/pm/jobs">Jobs</a></div>
          <h1 class="page-title" id="jobTitle">Job</h1>
        </div>
        <div class="col-auto ms-auto"><span class="badge" id="jobStage"></span></div>
      </div>
    </div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">

      <div id="jobNotice"></div>
      <div class="row row-cards mb-3" id="jobStats">
        <div class="col-6 col-lg-3"><a class="card card-sm card-link" href="#drawings" onclick="showJobTab('drawings'); setDrawTab('all'); return false"><div class="card-body">
          <div class="subheader">Elevations</div><div class="h1 mb-0" id="statElevs">-</div></div></a></div>
        <div class="col-6 col-lg-3"><a class="card card-sm card-link" href="#drawings" onclick="showJobTab('drawings'); setDrawTab('attention'); return false"><div class="card-body">
          <div class="subheader">Need attention</div><div class="h1 mb-0 text-danger" id="statAttention">-</div></div></a></div>
        <div class="col-6 col-lg-3"><a class="card card-sm card-link" href="#drawings" onclick="showJobTab('drawings'); setDrawTab('approved'); return false"><div class="card-body">
          <div class="subheader">Approved</div><div class="h1 mb-0 text-success" id="statApproved">-</div>
          <div class="progress progress-sm mt-2"><div class="progress-bar bg-success" id="statApprovedBar" style="width:0%"></div></div></div></a></div>
        <div class="col-6 col-lg-3"><a class="card card-sm card-link" href="#workorders" onclick="showJobTab('workorders'); return false"><div class="card-body">
          <div class="subheader">Work order requests pending</div><div class="h1 mb-0" id="statWo">-</div></div></a></div>
      </div>
      <ul class="nav nav-tabs mb-3" id="jobTabs">
        <li class="nav-item"><a class="nav-link" href="#overview" data-tab="overview" onclick="showJobTab('overview'); return false">Overview</a></li>
        <li class="nav-item"><a class="nav-link" href="#drawings" data-tab="drawings" onclick="showJobTab('drawings'); return false">Drawings <span class="badge bg-red-lt ms-1" id="tabAttention" style="display:none"></span></a></li>
        <li class="nav-item"><a class="nav-link" href="#estimate" data-tab="estimate" onclick="showJobTab('estimate'); return false">Estimate</a></li>
        <li class="nav-item"><a class="nav-link" href="#submittals" data-tab="submittals" onclick="showJobTab('submittals'); return false">Submittals</a></li>
        <li class="nav-item"><a class="nav-link" href="#workorders" data-tab="workorders" onclick="showJobTab('workorders'); return false">Work orders <span class="badge bg-orange-lt ms-1" id="tabWo" style="display:none"></span></a></li>
        <li class="nav-item"><a class="nav-link" href="#forecast" data-tab="forecast" onclick="showJobTab('forecast'); return false">Forecast</a></li>
      </ul>
      <style>
        .dw-pane { min-width: 0; }
        /* a long list of values scrolls beside the sheet; the sheet stays in view */
        @media (min-width: 1200px) { .dw-sticky { position: sticky; top: 0; align-self: flex-start; z-index: 2; } }
        .dw-left, .dw-split > .col-lg-4 { height: calc(100vh - 330px); min-height: 520px; }
        .dw-right { overflow-y: auto; height: calc(100vh - 330px); min-height: 520px; }
        .dw-scroll { overflow-y: auto; flex: 1 1 auto; min-height: 0; }
        .dw-row { display: flex; align-items: center; gap: 8px; padding: 7px 8px; border-radius: var(--tblr-border-radius); cursor: pointer; }
        .dw-row:hover { background: var(--tblr-bg-surface-secondary); }
        .dw-row.active { background: var(--tblr-primary-lt); }
        .dw-dot { width: 9px; height: 9px; border-radius: 50%; flex: none; background: var(--tblr-border-color); }
        .dw-dot.attention { background: var(--tblr-danger); } .dw-dot.pending { background: var(--tblr-yellow); }
        .dw-dot.approved { background: var(--tblr-success); } .dw-dot.excluded { background: var(--tblr-secondary); }
        .dw-label { flex: 1; min-width: 0; }
        .dw-label .name { display: block; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dw-label .sub { display: block; font-size: .75rem; color: var(--tblr-secondary-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dw-set-head { display: flex; align-items: center; gap: 8px; padding: 7px 8px; border-radius: var(--tblr-border-radius); cursor: pointer; }
        .dw-set-head:hover { background: var(--tblr-bg-surface-secondary); }
        .dw-chev { width: 16px; text-align: center; color: var(--tblr-secondary-color); transition: transform .15s; flex: none; }
        .dw-set.collapsed > .dw-set-head .dw-chev { transform: rotate(-90deg); }
        .dw-set.collapsed > .dw-set-body { display: none; }
        .dw-set-body { margin-left: 15px; padding-left: 4px; border-left: 1px solid var(--tblr-border-color); }
        .dw-set-info { font-size: .75rem; color: var(--tblr-secondary-color); padding: 2px 8px 6px; }
        .dw-group { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: var(--tblr-secondary-color); padding: 8px 8px 2px; }
        .min-w-0 { min-width: 0; }
        @media (max-width: 991.98px) { .dw-split > .col-lg-4, .dw-right { height: auto; min-height: 0; } .dw-scroll { max-height: 320px; } }
      </style>
      <div class="job-pane" data-pane="overview" style="display:none">
      <div class="card mb-3" id="detailsCard">
        <div class="card-header"><h3 class="card-title">Job details</h3><div class="card-actions"><button class="btn btn-sm" data-permission="pm.jobs.manage" onclick="toggleDetailsEdit()">Edit</button></div></div>
        <div class="card-body" id="detailsView"></div>
        <form class="card-body border-top" id="detailsForm" style="display:none" onsubmit="saveDetails(event)">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label required">Job name</label><input class="form-control" id="dName" required></div>
            <div class="col-md-6"><label class="form-label">Customer / GC</label><input class="form-control" id="dCustomer"></div>
            <div class="col-md-6"><label class="form-label">Site address</label><input class="form-control" id="dAddress"></div>
            <div class="col-md-6"><label class="form-label">Target completion</label><input type="date" class="form-control" id="dTarget"></div>
            <div class="col-md-4"><label class="form-label">Contact</label><input class="form-control" id="dContact"></div>
            <div class="col-md-4"><label class="form-label">Contact phone</label><input class="form-control" id="dPhone"></div>
            <div class="col-md-4"><label class="form-label">Contact email</label><input type="email" class="form-control" id="dEmail"></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" id="dNotes" rows="2"></textarea></div>
          </div>
          <div class="mt-3"><button class="btn btn-primary">Save</button> <button type="button" class="btn" onclick="toggleDetailsEdit()">Cancel</button></div>
        </form>
      </div>

      </div>

      <div class="job-pane" data-pane="drawings" style="display:none">
      <div class="card mb-3" id="drawCard">
        <div class="card-header">
          <h3 class="card-title">Shop drawings</h3>
          <div class="card-actions btn-list" data-permission="pm.jobs.manage">
            <button class="btn btn-sm" onclick="document.getElementById('drawFiles').click()"><i class="ti ti-upload me-1"></i>Upload drawings</button>
            <button class="btn btn-sm" id="drawApproveBtn" onclick="approveHigh()">Approve all high confidence</button>
          </div>
        </div>
        <div class="card-body border-bottom py-2" data-permission="pm.jobs.manage">
          <div id="drawDrop" class="rounded px-3 py-2 text-center text-muted small" style="border:1px dashed var(--tblr-border-color)"
               ondragover="event.preventDefault(); this.classList.add('bg-azure-lt')" ondragleave="this.classList.remove('bg-azure-lt')" ondrop="dropDrawings(event)">
            Drop the whole drawing package here (several PDFs at once), or <a href="#" onclick="event.preventDefault(); drawFiles.click()">choose files</a>
            <input type="file" accept=".pdf" multiple hidden id="drawFiles" onchange="uploadDrawings(this.files)">
          </div>
        </div>
        <div class="card-body border-bottom py-2" id="drawSummary"></div>
        <div class="card-body p-0">
          <div class="row g-0 dw-split">
            <div class="col-lg-4 border-end d-flex flex-column dw-pane">
              <div class="p-2 border-bottom">
                <div class="d-flex gap-2 mb-2">
                  <div class="input-icon flex-fill"><span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="search" class="form-control" id="drawSearch" placeholder="Search elevation, system, arch ref…" autocomplete="off" oninput="renderDrawReview()"></div>
                  <div class="btn-group">
                    <button class="btn btn-icon" title="Expand all files" onclick="setAllSets(true)"><i class="ti ti-arrows-diagonal"></i></button>
                    <button class="btn btn-icon" title="Collapse all files" onclick="setAllSets(false)"><i class="ti ti-arrows-diagonal-minimize-2"></i></button>
                  </div>
                </div>
                <div class="btn-group btn-group-sm w-100" id="drawTabs">
                  <button class="btn" data-tab="attention" onclick="setDrawTab('attention')">Attention <span class="badge bg-red-lt ms-1" id="drawCntAttention">0</span></button>
                  <button class="btn" data-tab="pending" onclick="setDrawTab('pending')">To review <span class="badge bg-yellow-lt ms-1" id="drawCntPending">0</span></button>
                  <button class="btn" data-tab="approved" onclick="setDrawTab('approved')">Approved <span class="badge bg-green-lt ms-1" id="drawCntApproved">0</span></button>
                  <button class="btn" data-tab="all" onclick="setDrawTab('all')">All</button>
                </div>
              </div>
              <div class="dw-scroll p-2" id="drawList"><div class="text-muted small p-2">Loading...</div></div>
              <div class="p-2 border-top text-muted small" id="drawCount"></div>
            </div>
            <div class="col-lg-8 dw-pane dw-right">
              <div id="drawEmpty" class="d-flex flex-column align-items-center justify-content-center text-muted h-100 py-6">
                <i class="ti ti-ruler-measure" style="font-size:3rem"></i>
                <p class="mt-2 mb-0">Select an elevation to check its sizes</p>
              </div>
              <div id="drawDetail" class="d-none">
                <div class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-start">
                  <div class="min-w-0">
                    <div class="text-muted small" id="szCrumb"></div>
                    <h3 class="mb-0" id="szTitle">Check sizes</h3>
                    <div class="text-muted small" id="szMeta"></div>
                  </div>
                  <div class="ms-auto d-flex gap-2 align-items-center flex-wrap" id="szActions"></div>
                </div>
                <div class="p-3">
                  <div class="row g-3">
                    <div class="col-xl-8 dw-sticky">
                      <div class="d-flex align-items-center gap-2 mb-2 flex-wrap"><select class="form-select form-select-sm w-auto" id="szPage" onchange="szLoadPage()"></select>
                        <span class="text-muted small" id="szCap"></span>
                        <span class="ms-auto d-flex gap-1">
                          <button type="button" class="btn btn-sm" title="Previous value (←)" onclick="szStep(-1)">◀</button>
                          <button type="button" class="btn btn-sm" title="Next value (→)" onclick="szStep(1)">▶</button>
                          <button type="button" class="btn btn-sm btn-primary" title="Jump to the next value not yet checked" onclick="szStep(1, true)">Next unchecked</button>
                        </span></div>
                      <div id="szStage" style="position:relative;height:calc(100vh - 520px);min-height:360px;background:#39434f;border-radius:4px;overflow:hidden;touch-action:none;cursor:grab">
                        <canvas id="szCanvas" style="position:absolute;left:0;top:0;width:100%;height:100%;display:block"></canvas>
                        <div class="position-absolute d-flex gap-1" style="right:8px;top:8px" onpointerdown="event.stopPropagation()">
                          <button type="button" class="btn btn-sm" title="Zoom out (−)" onclick="szZoomBy(1/1.4)">−</button>
                          <button type="button" class="btn btn-sm" title="Zoom in (+)" onclick="szZoomBy(1.4)">+</button>
                          <button type="button" class="btn btn-sm" onclick="szFrameElevation(true)">This elevation</button>
                          <button type="button" class="btn btn-sm" onclick="szFrameSheet(true)">Whole sheet</button>
                        </div>
                      </div>
                      <div class="d-flex gap-3 small text-muted mt-2 flex-wrap">
                        <span><span class="badge" style="background:rgba(40,170,70,.5);border:1px solid #1e8c37">&nbsp;</span> confirmed</span>
                        <span><span class="badge" style="background:rgba(255,215,0,.55);border:1px solid #d4a00a">&nbsp;</span> read by the parser, not yet checked</span>
                        <span><span class="badge" style="background:rgba(225,45,35,.45);border:1px solid #cd281e">&nbsp;</span> corrected (differs from the reading)</span>
                        <span class="ms-auto">scroll to zoom · drag to pan · click a value to jump to it · ← → step, Enter confirms</span>
                      </div>
                    </div>
                    <div class="col-xl-4">
                      <div id="szElevInfo" class="mb-3"></div>
                      <div class="d-flex align-items-center mb-2"><h4 class="mb-0">Values on this sheet</h4>
                        <button class="btn btn-sm btn-primary ms-auto" data-permission="pm.jobs.manage" onclick="szConfirmAll()">Confirm all</button></div>
                      <div id="szList"></div>
                      <div id="szNote" class="mt-2 small"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="card-footer text-muted small">Each set is read in the background (a large one can take a few minutes). The importer checks that every frame closes (frame size = openings + members) and rates how far to trust each reading. Nothing is applied to a work order until someone approves it.</div>
      </div>

      </div>

      <div class="job-pane" data-pane="estimate" style="display:none">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Estimate</h3></div>
        <div class="card-body">
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Imported sheets</label>
              <select class="form-select" id="sheetPick"><option value="">None imported yet</option></select>
            </div>
            <div class="col-md-5" data-permission="pm.jobs.manage">
              <label class="form-label">Upload BidEstimate workbook (.xls / .xlsx)</label>
              <div class="input-group">
                <input type="file" class="form-control" id="estFile" accept=".xls,.xlsx,.xlsm">
                <button class="btn" id="estUploadBtn" onclick="uploadEstimate()">Upload</button>
              </div>
            </div>
            <div class="col-md-3" id="importBox" style="display:none" data-permission="pm.jobs.manage">
              <label class="form-label">Tab to import</label>
              <div class="input-group">
                <select class="form-select" id="importSheet"></select>
                <button class="btn btn-primary" onclick="importSheet()">Import</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div id="estimateBody" style="display:none">
        <div class="row row-cards mb-3" id="estSummary"></div>
        <div id="estStale" class="alert alert-warning" style="display:none">This sheet was imported with an older parser. Re-import it to pick up parser fixes.</div>

        <div class="card mb-3" id="reviewCard" style="display:none">
          <div class="card-header"><h3 class="card-title">Needs your decision <span class="badge bg-orange-lt ms-2" id="reviewCount"></span></h3></div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>Row</th><th>Description</th><th class="text-end">Total</th><th>Why</th><th>Cost code</th><th class="w-1"></th></tr></thead>
              <tbody id="reviewBody"></tbody>
            </table>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">By cost code</h3></div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>Code</th><th>Description</th><th class="text-end">Lines</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr></thead>
              <tbody id="codeBody"></tbody>
            </table>
          </div>
        </div>

        <div class="card mb-3" id="decisionsCard" style="display:none">
          <div class="card-header"><h3 class="card-title">Your decisions</h3></div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table"><tbody id="decisionsBody"></tbody></table>
          </div>
        </div>

        <div class="card mb-3" id="warningsCard" style="display:none">
          <div class="card-header"><h3 class="card-title">Parser warnings</h3></div>
          <div class="card-body" id="warningsBody"></div>
        </div>
      </div>
      </div>

      <div class="job-pane" data-pane="submittals" style="display:none">
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Submittals</h3>
          <div class="card-actions btn-list">
            <label class="form-check form-switch m-0 me-2"><input class="form-check-input" type="checkbox" id="smNeeds" onchange="loadSubmittals()"><span class="form-check-label">Needs response</span></label>
            <button class="btn btn-sm btn-primary" data-permission="pm.jobs.manage" onclick="showSubmittalForm()">+ Submittal</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>#</th><th>Name / section</th><th>Kind</th><th>POs</th><th>Sent</th><th style="width:190px">Status</th><th class="w-1"></th></tr></thead>
            <tbody id="smBody"><tr><td colspan="7" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>

      </div>

      <div class="job-pane" data-pane="workorders" style="display:none">
      <div class="card mb-3" id="woCard">
        <div class="card-header">
          <h3 class="card-title">Work order requests</h3>
          <div class="card-actions"><button class="btn btn-sm btn-primary" data-permission="pm.jobs.manage" onclick="showWoForm()">Request work order</button></div>
        </div>
        <div class="list-group list-group-flush" id="woBody"><div class="list-group-item text-muted">Loading...</div></div>
        <div class="card-footer text-muted small">Fabrication validates each request and turns it into a work order. The job must be active.</div>
      </div>

      </div>

      <div class="job-pane" data-pane="forecast" style="display:none">
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Material forecast</h3>
          <div class="card-actions"><label class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="fcAll" onchange="loadForecast()"><span class="form-check-label">All cost codes</span></label></div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Code</th><th>Description</th><th class="text-end">Estimated</th><th class="text-end">Ordered</th><th class="text-end">Ordered %</th><th class="text-end">Planned (draft)</th><th class="text-end">Received</th><th>Status</th></tr></thead>
            <tbody id="forecastBody"><tr><td colspan="8" class="text-muted">Loading...</td></tr></tbody>
            <tfoot id="forecastFoot"></tfoot>
          </table>
        </div>
        <div class="card-footer text-muted small">Ordered and received count issued job purchase orders only. Draft POs are shown as planned. Stock and internal POs are not job material.</div>
      </div>

      </div>

    </div>
  </main>
</div>

<div class="modal fade" id="woModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><form class="modal-content" onsubmit="saveWo(event)">
    <div class="modal-header"><h5 class="modal-title">Request a work order</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2 mb-3"><div class="col-6"><label class="form-label">Release code</label><input class="form-control" id="woCode" placeholder="e.g. A"></div>
        <div class="col-6"><label class="form-label">Needed by</label><input type="date" class="form-control" id="woDue"></div></div>
      <div class="mb-3"><label class="form-label">Drawing</label>
        <select class="form-select" id="woSet" onchange="renderWoElevs()"><option value="">Not tied to a drawing</option></select>
        <div class="form-hint" id="woSetHint"></div></div>
      <div class="mb-3" id="woElevBox" style="display:none">
        <label class="form-label">Elevations and quantity to release from this drawing</label>
        <div class="table-responsive border rounded"><table class="table table-sm table-vcenter mb-0">
          <thead><tr><th class="w-1"></th><th>Elevation</th><th>System / finish</th><th>R.O. W × H</th><th>Quantity</th></tr></thead>
          <tbody id="woElevList"></tbody></table></div>
        <div class="small text-muted mt-1">Only approved elevations can be released. The quantity cannot be more than the drawing calls for, less what other requests already cover.</div>
      </div>
      <div class="mb-3"><label class="form-label">Scope</label><textarea class="form-control" id="woScope" rows="3" placeholder="Elevations, openings, what is being released"></textarea></div>
      <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" id="woNotes" rows="2"></textarea></div>
      <div class="mb-1"><label class="form-label">Other drawings or files</label><input type="file" class="form-control" id="woFiles" multiple></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Send request</button></div>
  </form></div>
</div>

<div class="modal fade" id="smModal" tabindex="-1">
  <div class="modal-dialog"><form class="modal-content" onsubmit="saveSubmittal(event)">
    <div class="modal-header"><h5 class="modal-title">New submittal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label required">Name</label><input class="form-control" id="smName" required></div>
      <div class="row g-2"><div class="col-6"><label class="form-label">Spec section</label><input class="form-control" id="smSpec" placeholder="08 41 13"></div>
      <div class="col-6"><label class="form-label">Kind</label><select class="form-select" id="smKind"><option value="docs">Docs</option><option value="sample">Sample</option><option value="docs_samples">Docs + samples</option></select></div></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
  </form></div>
</div>

<div class="modal fade" id="smRespModal" tabindex="-1">
  <div class="modal-dialog"><form class="modal-content" onsubmit="saveResponse(event)">
    <div class="modal-header"><h5 class="modal-title">Record GC/AE response</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label required">Response</label><select class="form-select" id="rStatus" required></select></div>
      <div class="row g-2 mb-3"><div class="col-6"><label class="form-label required">Returned</label><input type="date" class="form-control" id="rReturned" required></div>
      <div class="col-6"><label class="form-label">Responded by</label><input class="form-control" id="rBy"></div></div>
      <div class="mb-3"><label class="form-label">Marked-up document</label><input type="file" class="form-control" id="rFile"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save response</button></div>
  </form></div>
</div>

@include('partials.pm-modal-helpers')

<script>
  const JOB_ID = {{ (int) $jobId }};
  const money = (n) => n == null ? '—' : '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const REASONS = { no_cost_code_found: 'No cost code found', code_not_on_master_list: 'Code not on master list', recovered_from_malformed_code_prefix: 'Code recovered from a typo' };
  let costCodes = [], uploadedFile = null, currentSheet = null;

  const esc = (s) => escapeHtml(String(s ?? ''));
  const post = (url, body) => authenticatedFetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  const base = () => `/pm/jobs/${JOB_ID}/estimates`;

  function notice(msg, type = 'info') { document.getElementById('jobNotice').innerHTML = msg ? `<div class="alert alert-${type} alert-dismissible mb-3" role="alert">${esc(msg)}<a class="btn-close" onclick="notice('')"></a></div>` : ''; }

  // ---- tabs: the page was one long scroll; each area now has its own tab, remembered in the address (#drawings) ----
  const JOB_TABS = ['overview', 'drawings', 'estimate', 'submittals', 'workorders', 'forecast'];
  function showJobTab(name, push = true) {
    if (!JOB_TABS.includes(name)) name = 'overview';
    document.querySelectorAll('.job-pane').forEach(p => { p.style.display = p.dataset.pane === name ? '' : 'none'; });
    document.querySelectorAll('#jobTabs [data-tab]').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    if (push) try { history.replaceState(null, '', '#' + name); } catch (_) {}
    if (name === 'drawings' && typeof sz !== 'undefined' && sz) szDraw();
  }

  let jobData = null;
  function renderDetails(job) {
    jobData = job;
    const row = (l, v) => v ? `<div class="col-md-4 mb-2"><div class="text-muted small">${l}</div><div>${esc(v)}</div></div>` : '';
    detailsView.innerHTML = `<div class="row">${row('Customer / GC', job.customer_name)}${row('Site', job.site_address)}${row('Target completion', (job.target_completion_date || '').slice(0, 10))}
      ${row('Contact', [job.contact_name, job.contact_phone, job.contact_email].filter(Boolean).join(' · '))}${row('Source', job.origin === 'pm' ? 'PM bid' : 'Created in Fabrication')}${row('Notes', job.notes)}</div>`
      || '<div class="text-muted">No details yet.</div>';
  }
  function toggleDetailsEdit() {
    const f = detailsForm, show = f.style.display === 'none';
    f.style.display = show ? '' : 'none';
    if (show && jobData) { dName.value = jobData.job_name || ''; dCustomer.value = jobData.customer_name || ''; dAddress.value = jobData.site_address || ''; dTarget.value = (jobData.target_completion_date || '').slice(0, 10);
      dContact.value = jobData.contact_name || ''; dPhone.value = jobData.contact_phone || ''; dEmail.value = jobData.contact_email || ''; dNotes.value = jobData.notes || ''; }
  }
  async function saveDetails(e) {
    e.preventDefault();
    try {
      const job = await post(`/pm/jobs/${JOB_ID}`, { job_name: dName.value, customer_name: dCustomer.value || null, site_address: dAddress.value || null, target_completion_date: dTarget.value || null,
        contact_name: dContact.value || null, contact_phone: dPhone.value || null, contact_email: dEmail.value || null, notes: dNotes.value || null }, 'PUT');
      renderDetails(job); document.getElementById('jobTitle').textContent = `${job.job_number} — ${job.job_name}`; detailsForm.style.display = 'none'; notice('Saved.', 'success');
    } catch (err) { notice(err.message || 'Could not save', 'danger'); }
  }

  async function loadJob() {
    const job = await authenticatedFetch(`/pm/jobs/${JOB_ID}`);
    renderDetails(job);
    document.getElementById('jobTitle').textContent = `${job.job_number} — ${job.job_name}`;
    const b = document.getElementById('jobStage');
    b.textContent = job.status_label || job.status; b.className = 'badge bg-blue-lt';
  }

  async function loadSheets(select) {
    const sheets = await authenticatedFetch(base());
    const pick = document.getElementById('sheetPick');
    pick.innerHTML = sheets.length ? sheets.map(s => `<option value="${esc(s.sheet)}">${esc(s.sheet)} — ${money(s.contract_amount)}${s.review_count ? ` (${s.review_count} to review)` : ''}</option>`).join('') : '<option value="">None imported yet</option>';
    const target = select || currentSheet || (sheets[0] && sheets[0].sheet);
    if (target) { pick.value = target; await showSheet(target); }
  }

  async function showSheet(sheet) {
    if (!sheet) { document.getElementById('estimateBody').style.display = 'none'; return; }
    currentSheet = sheet;
    render(await authenticatedFetch(`${base()}/${encodeURIComponent(sheet)}`));
  }

  function render(e) {
    document.getElementById('estimateBody').style.display = '';
    document.getElementById('estStale').style.display = e.parserStale ? '' : 'none';
    const s = e.summary || {};
    const tiles = [['Contract amount', s.contractAmount], ['$ / sf glass', s.dollarsPerSquareFootGlass], ['Material', s.material], ['Large sub', s.large_sub], ['Other', s.other], ['Labor', s.labor]];
    document.getElementById('estSummary').innerHTML = tiles.filter(t => t[1] != null).map(([l, v]) =>
      `<div class="col-6 col-md-2"><div class="card"><div class="card-body"><div class="subheader">${l}</div><div class="h3 mb-0">${money(v)}</div></div></div></div>`).join('');

    document.getElementById('codeBody').innerHTML = Object.entries(e.byCostCode).map(([code, a]) =>
      `<tr><td>${esc(code)}</td><td>${esc(a.description)}</td><td class="text-end">${a.lineCount}</td><td class="text-end">${a.qty}</td><td class="text-end">${money(a.total)}</td></tr>`).join('')
      || '<tr><td colspan="5" class="text-muted">No coded lines.</td></tr>';

    const opts = costCodes.map(c => `<option value="${c.code}">${c.code} — ${esc(c.description)}</option>`).join('');
    document.getElementById('reviewCard').style.display = e.reviewNeeded.length ? '' : 'none';
    document.getElementById('reviewCount').textContent = e.reviewNeeded.length;
    document.getElementById('reviewBody').innerHTML = e.reviewNeeded.map((r, i) => `<tr>
      <td>${esc(r.section)} ${r.row}</td><td>${esc(r.description)}</td><td class="text-end">${money(r.total)}</td>
      <td>${esc(REASONS[r.reviewReason] || r.reviewReason)}${r.costCode ? ` (${esc(r.costCode)})` : ''}</td>
      <td><select class="form-select form-select-sm" id="fix-${i}"><option value="">Choose code…</option>${opts}</select></td>
      <td class="text-nowrap" data-permission="pm.jobs.manage">
        <button class="btn btn-sm btn-primary me-1" onclick="decide('${r.section}', ${r.row}, 'fix', ${i})">Fix</button>
        <button class="btn btn-sm" onclick="decide('${r.section}', ${r.row}, 'ignore')">Ignore</button></td></tr>`).join('');

    document.getElementById('decisionsCard').style.display = e.decisions.length ? '' : 'none';
    document.getElementById('decisionsBody').innerHTML = e.decisions.map(d => `<tr>
      <td>${esc(d.section)} ${d.row}</td><td>${esc(d.description)}</td><td class="text-end">${money(d.total)}</td>
      <td>${d.action === 'fix' ? 'Coded ' + esc(d.costCode) : 'Ignored'}</td>
      <td class="w-1" data-permission="pm.jobs.manage"><button class="btn btn-sm" onclick="restore('${d.section}', ${d.row})">Undo</button></td></tr>`).join('');

    document.getElementById('warningsCard').style.display = e.warnings.length ? '' : 'none';
    document.getElementById('warningsBody').innerHTML = e.warnings.map(w => `<div class="mb-1">${esc(w.type)}${w.section ? ' · ' + esc(w.section) : ''}
      ${w.computedFromLines != null ? `— lines add to ${money(w.computedFromLines)}, sheet says ${money(w.statedInSheet)}` : ''} ${w.note ? esc(w.note) : ''}</div>`).join('');
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  const FC_STATUS = {
    none: ['—', 'bg-secondary-lt'], not_ordered: ['Not ordered', 'bg-orange-lt'], draft_only: ['Draft only', 'bg-yellow-lt'],
    ordered: ['Ordered', 'bg-blue-lt'], partially_received: ['Partly received', 'bg-cyan-lt'], received: ['Received', 'bg-green-lt'],
    over_budget: ['Over estimate', 'bg-red-lt'],
  };

  async function loadForecast() {
    const all = document.getElementById('fcAll').checked;
    const f = await authenticatedFetch(`/pm/jobs/${JOB_ID}/forecast${all ? '?category=all' : ''}`);
    const body = document.getElementById('forecastBody');
    body.innerHTML = f.rows.length ? f.rows.map(r => {
      const [label, cls] = FC_STATUS[r.status] || [r.status, 'bg-secondary-lt'];
      return `<tr><td>${r.costCode}</td><td>${esc(r.description)}</td>
        <td class="text-end">${money(r.estimatedTotal)}<div class="text-muted small">${r.estimatedQty} qty</div></td>
        <td class="text-end">${money(r.orderedTotal)}<div class="text-muted small">${r.orderedQty} qty</div></td>
        <td class="text-end">${r.orderedPct == null ? '—' : r.orderedPct + '%'}</td>
        <td class="text-end">${r.draftTotal ? money(r.draftTotal) : '—'}</td>
        <td class="text-end">${money(r.receivedTotal)}<div class="text-muted small">${r.receivedPct == null ? '—' : r.receivedPct + '% of ordered'}</div></td>
        <td><span class="badge ${cls}">${label}</span></td></tr>`;
    }).join('') : '<tr><td colspan="8" class="text-muted">Import an estimate or add job purchase orders to see the forecast.</td></tr>';
    const t = f.totals;
    document.getElementById('forecastFoot').innerHTML = f.rows.length ? `<tr class="fw-bold"><td colspan="2">Total</td><td class="text-end">${money(t.estimatedTotal)}</td><td class="text-end">${money(t.orderedTotal)}</td><td></td><td class="text-end">${money(t.draftTotal)}</td><td class="text-end">${money(t.receivedTotal)}</td><td></td></tr>` : '';
  }

  // ---- shop drawings: upload a whole package, review elevations with automatic checks and confidence ----
  let drawTimer = null, drawTab = 'attention', drawData = { summary: null, elevations: [] }, drawSetsList = [];
  const DR_STATUS = { queued: ['Queued', 'bg-secondary-lt'], running: ['Reading…', 'bg-blue-lt'], done: ['Ready', 'bg-green-lt'], failed: ['Failed', 'bg-red-lt'] };
  const AX_STATE = { closes: ['closes', 'bg-green-lt'], corrected: ['corrected · closes', 'bg-green-lt'], flagged: ['doesn’t close', 'bg-red-lt'], unverified: ['unverified', 'bg-yellow-lt'],
    accepted: ['accepted', 'bg-yellow-lt'], unverifiable: ['can’t check', 'bg-secondary-lt'] };
  const CONF_CLASS = { high: 'bg-green-lt', medium: 'bg-yellow-lt', low: 'bg-red-lt' };
  const dim = (v) => v == null ? '—' : fmtIn(v);

  const axisChip = (e, axis) => { const c = (e.axis_checks || []).find(x => x.axis === axis); const [l, cls] = AX_STATE[c ? c.state : 'unverifiable'];
    return `<span class="badge ${cls}"${c && c.member ? ` title="uniform member ${fmtIn(c.member)}"` : ''}>${l}</span>`; };
  const confChip = (cf) => `<span class="badge ${CONF_CLASS[cf.level]}" title="${esc(cf.reasons.join(' • '))}">${cf.score}% ${cf.level}</span>`;

  async function loadDrawings() {
    const [sets, rev] = await Promise.all([authenticatedFetch(`/pm/jobs/${JOB_ID}/drawing-sets`), authenticatedFetch(`/pm/jobs/${JOB_ID}/drawing-elevations`)]);
    drawSetsList = sets; drawData = rev;
    renderDrawSets(); renderDrawReview();
    clearTimeout(drawTimer);
    if (sets.some(s => s.status === 'queued' || s.status === 'running')) drawTimer = setTimeout(loadDrawings, 3000);
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  function renderDrawSets() { renderDrawReview(); }   // the files are the headers of the list now

  // ---- the list: each drawing file is a header that opens to its elevations ----
  const drawCollapsedKey = () => `pm.job.${JOB_ID}.drawCollapsed`;
  let drawCollapsed = new Set();
  try { drawCollapsed = new Set(JSON.parse(localStorage.getItem(drawCollapsedKey()) || '[]')); } catch (_) {}
  const saveCollapsed = () => { try { localStorage.setItem(drawCollapsedKey(), JSON.stringify([...drawCollapsed])); } catch (_) {} };
  function toggleSet(id) { drawCollapsed.has(id) ? drawCollapsed.delete(id) : drawCollapsed.add(id); saveCollapsed(); renderDrawReview(); }
  function setAllSets(open) { drawCollapsed = open ? new Set() : new Set(drawSetsList.map(s => s.id)); saveCollapsed(); renderDrawReview(); }

  // which tab (needs attention / to review / approved / all) an elevation falls under
  const needsAttention = (e) => e.review_status === 'pending' && (e.unresolved_axes.length || e.confidence.level === 'low');
  const inTab = (e, t) => t === 'all' || (t === 'approved' && e.review_status === 'approved') || (t === 'pending' && e.review_status === 'pending') || (t === 'attention' && needsAttention(e));
  let drawOrder = [];   // the elevations shown in the list, in order (to step to the next one after an approval)

  function renderDrawReview() {
    const sm = drawData.summary, els = drawData.elevations;
    drawSummary.innerHTML = sm && sm.total ? `<span class="me-3"><strong>${sm.total}</strong> elevations</span>
      <span class="me-3 ${sm.needs_attention ? 'text-danger' : ''}"><strong>${sm.needs_attention}</strong> need attention</span>
      <span class="me-3"><strong>${sm.pending}</strong> to review</span><span class="me-3"><strong>${sm.approved}</strong> approved</span>
      ${sm.excluded ? `<span class="me-3">${sm.excluded} excluded</span>` : ''}
      <span class="text-muted">average confidence ${sm.average_confidence}%</span>` : '<span class="text-muted">No drawing sets read yet.</span>';
    drawApproveBtn.disabled = !(sm && sm.high);
    drawApproveBtn.textContent = sm && sm.high ? `Approve ${sm.high} high-confidence` : 'Approve high confidence';
    // page header cards and tab badges
    statElevs.textContent = sm ? sm.total : 0; statAttention.textContent = sm ? sm.needs_attention : 0; statApproved.textContent = sm ? sm.approved : 0;
    statApprovedBar.style.width = sm && sm.total ? Math.round(100 * sm.approved / sm.total) + '%' : '0%';
    tabAttention.textContent = sm ? sm.needs_attention : 0; tabAttention.style.display = sm && sm.needs_attention ? '' : 'none';
    drawCntAttention.textContent = els.filter(needsAttention).length; drawCntPending.textContent = els.filter(e => e.review_status === 'pending').length;
    drawCntApproved.textContent = els.filter(e => e.review_status === 'approved').length;
    document.querySelectorAll('#drawTabs button').forEach(b => b.classList.toggle('active', b.dataset.tab === drawTab));

    const q = (drawSearch.value || '').trim().toLowerCase();
    const show = els.filter(e => inTab(e, drawTab) && (!q || [e.label, e.system, e.finish, e.arch_ref].some(v => String(v || '').toLowerCase().includes(q))));
    drawOrder = show.map(e => e.id);
    const state = (e) => e.review_status === 'approved' ? 'approved' : e.review_status === 'excluded' ? 'excluded' : needsAttention(e) ? 'attention' : 'pending';
    const elevRow = (e) => `<div class="dw-row${sz && sz.elevId === e.id ? ' active' : ''}" onclick="openSheetViewer(${e.id})" title="${esc(e.confidence.reasons.join(' • '))}">
        <span class="dw-dot ${state(e)}"></span>
        <div class="dw-label"><span class="name">${esc(e.label)}${e.multi_sheet ? ' <span class="badge bg-azure-lt">multi-sheet</span>' : ''}</span>
          <span class="sub">${esc([e.system, e.finish].filter(Boolean).join(' · ') || '—')} · ${e.total_reqd ?? '?'} lites · R.O. ${dim(e.ro_w)} × ${dim(e.ro_h)}</span></div>
        <div class="text-end text-nowrap">${confChip(e.confidence)}<div class="mt-1">${axisChip(e, 'w')} ${axisChip(e, 'h')}</div></div></div>`;
    const setHead = (st, shown, all) => {
      const reading = st.status === 'queued' || st.status === 'running' || st.status === 'failed';
      const [stLabel, stCls] = DR_STATUS[st.status] || [st.status, 'bg-secondary-lt'];
      const attn = all.filter(needsAttention).length, pct = st.progress_count ? Math.round(100 * st.progress_done / st.progress_count) : 0;
      const sub = reading ? (st.status === 'running' ? `page ${st.progress_done} of ${st.progress_count || '?'}${st.progress_note ? ' · found ' + esc(st.progress_note) : ''}` : st.error ? esc(st.error) : '')
        : `${all.length} elevation${all.length === 1 ? '' : 's'}${st.page_count ? ' · ' + st.page_count + ' pages' : ''}${shown.length !== all.length ? ' · ' + shown.length + ' shown' : ''}`;
      return `<div class="dw-set-head" onclick="toggleSet(${st.id})" title="${esc(st.file ? st.file.original_name : '')}">
          <i class="ti ti-chevron-down dw-chev"></i><i class="ti ti-file-type-pdf text-red"></i>
          <div class="dw-label"><span class="name">${esc(st.file ? st.file.original_name : 'Drawing set')}</span><span class="sub${st.status === 'failed' ? ' text-danger' : ''}">${sub}</span></div>
          ${reading ? `<span class="badge ${stCls}">${stLabel}</span>` : attn ? `<span class="badge bg-red-lt" title="need attention">${attn}</span>` : ''}
          <span class="text-nowrap" data-permission="pm.jobs.manage" onclick="event.stopPropagation()">
            ${st.status === 'done' || st.status === 'failed' ? `<button class="btn btn-sm btn-icon btn-ghost-secondary" title="Read again" onclick="reimportDrawing(${st.id})"><i class="ti ti-refresh"></i></button>` : ''}
            <button class="btn btn-sm btn-icon btn-ghost-danger" title="Delete this set and its results" onclick="deleteDrawing(${st.id})"><i class="ti ti-x"></i></button></span></div>
        ${st.status === 'running' ? `<div class="progress progress-sm mx-2 mb-1"><div class="progress-bar" style="width:${pct}%"></div></div>` : ''}`;
    };
    const setInfo = (st) => (st.skip_summary || (st.glazing_tags || []).length)
      ? `<div class="dw-set-info">${st.skip_summary ? esc(st.skip_summary) : ''}${(st.glazing_tags || []).length ? `<div class="mt-1">Glazing schedule: ${st.glazing_tags.map(t => `<span class="badge bg-azure-lt" title="${esc(t.makeup || '')}">${esc(t.tag)}</span>`).join(' ')}</div>` : ''}</div>` : '';
    // a file stays listed while it is being read or has failed; a finished one only when something in it matches the view
    const groups = drawSetsList.map(st => ({ st, all: els.filter(e => e.set.id === st.id), shown: show.filter(e => e.set.id === st.id) }))
      .filter(g => g.st.status !== 'done' || g.shown.length);
    drawList.innerHTML = groups.length ? groups.map(g => {
      const open = !drawCollapsed.has(g.st.id) || (sz && g.shown.some(e => e.id === sz.elevId));
      return `<div class="dw-set${open ? '' : ' collapsed'}">${setHead(g.st, g.shown, g.all)}
        <div class="dw-set-body">${setInfo(g.st)}${g.shown.map(elevRow).join('')}</div></div>`;
    }).join('') : `<div class="text-muted small p-2">${els.length || drawSetsList.length ? 'Nothing in this view.' : 'Upload a drawing package to begin.'}</div>`;
    drawCount.textContent = els.length ? `${show.length} of ${els.length} elevations` : '';
    if (sz && !elevById(sz.elevId)) { sz = null; drawDetail.classList.add('d-none'); drawEmpty.classList.remove('d-none'); }   // a re-read replaced it
    if (sz) renderDrawHead();
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  // the selected elevation's heading: where it comes from, what it is, and the review actions
  function renderDrawHead() {
    const e = elevById(sz.elevId); if (!e) return;
    szTitle.innerHTML = `${esc(e.label)} ${confChip(e.confidence)}`;
    szCrumb.textContent = `${e.set.file || 'Drawing set'}${e.sheets.length ? ' · sheet' + (e.sheets.length > 1 ? 's ' : ' ') + e.sheets.map(s => s.page).join(', ') : ''}`;
    szMeta.textContent = [e.arch_ref, e.system, e.finish, `${e.total_reqd ?? '?'} lites`, `R.O. ${dim(e.ro_w)} × ${dim(e.ro_h)}`].filter(Boolean).join(' · ');
    szActions.innerHTML = `<span class="d-flex gap-2 align-items-center" data-permission="pm.jobs.manage">${e.review_status === 'pending'
      ? `<button class="btn btn-primary" onclick="reviewElev(${e.id}, 'approve')">Approve</button><button class="btn btn-ghost-secondary" onclick="reviewElev(${e.id}, 'exclude')">Exclude</button>`
      : `<span class="badge bg-${e.review_status === 'approved' ? 'green' : 'secondary'}-lt">${e.review_status}</span><button class="btn btn-ghost-secondary" onclick="reviewElev(${e.id}, 'reopen')">Reopen</button>`}</span>`;
  }

  function setDrawTab(t) { drawTab = t; renderDrawReview(); }
  const elevById = (id) => drawData.elevations.find(e => e.id === id);

  async function reviewElev(id, action) {
    const e = elevById(id), at = drawOrder.indexOf(id);
    try {
      await post(`/pm/jobs/${JOB_ID}/drawing-sets/${e.set.id}/elevations/${id}/review`, { action }); await loadDrawings();
      if (action === 'approve' || action === 'exclude') {   // carry on down the list
        const next = drawOrder.find(x => x !== id && drawOrder.indexOf(x) >= Math.max(at, 0)) ?? drawOrder.filter(x => x !== id).pop();
        if (next != null && elevById(next)) openSheetViewer(next);
      }
    } catch (err) { notice(err.message || 'Could not update', 'danger'); if (action === 'approve') openSheetViewer(id); }
  }
  async function approveHigh() {
    try {
      const r = await post(`/pm/jobs/${JOB_ID}/drawing-elevations/bulk-review`, { min_level: 'high' });
      notice(`Approved ${r.approved} elevation${r.approved === 1 ? '' : 's'}.${r.skipped.length ? ' Left for you: ' + r.skipped.map(s => s.label + ' (' + s.why + ')').join(', ') + '.' : ''}`, r.approved ? 'success' : 'info');
      loadDrawings();
    } catch (err) { notice(err.message || 'Could not approve', 'danger'); }
  }

  function dropDrawings(ev) { ev.preventDefault(); drawDrop.classList.remove('bg-azure-lt'); uploadDrawings(ev.dataTransfer.files); }
  async function uploadDrawings(fileList) {
    const files = [...fileList].filter(f => /\.pdf$/i.test(f.name));
    if (!files.length) { notice('Choose PDF drawing files.', 'warning'); return; }
    const fd = new FormData(); files.forEach(f => fd.append('files[]', f));
    notice(`Uploading ${files.length} file${files.length === 1 ? '' : 's'}…`, 'info');
    const r = await authenticatedUpload(`/pm/jobs/${JOB_ID}/drawing-sets/bulk`, fd);
    const d = await r.json().catch(() => ({}));
    if (!r.ok) { notice(d.message || (d.errors ? Object.values(d.errors).flat().join(' ') : 'Upload failed'), 'danger'); return; }
    const dup = d.filter(x => x.already_uploaded).length;
    notice(`${d.length - dup} set${d.length - dup === 1 ? '' : 's'} queued${dup ? `, ${dup} already uploaded` : ''}. They are read one after another in the background.`, 'info');
    drawFiles.value = ''; loadDrawings();
  }
  async function reimportDrawing(id) { try { await post(`/pm/jobs/${JOB_ID}/drawing-sets/${id}/reimport`, {}); loadDrawings(); } catch (e) { notice(e.message, 'danger'); } }
  async function deleteDrawing(id) { if (confirm('Delete this drawing set and its results?')) { try { await authenticatedFetch(`/pm/jobs/${JOB_ID}/drawing-sets/${id}`, { method: 'DELETE' }); loadDrawings(); } catch (e) { notice(e.message, 'danger'); } } }

  // ---- visual size check (the sheet with each read dimension drawn over it) ----
  const SZ_KIND = { ro_w: 'R.O. width', fs_w: 'F.S. width', ro_h: 'R.O. height', fs_h: 'F.S. height', do_w: 'Door opening width', do_h: 'Door opening height', dlo_w: 'D.L.O. width', dlo_h: 'D.L.O. height' };
  const SZ_COLORS = { confirmed: ['rgba(40,170,70,0.28)', '#1e8c37'], suggested: ['rgba(255,215,0,0.34)', '#d4a00a'], mismatch: ['rgba(225,45,35,0.28)', '#cd281e'] };
  let sz = null;   // { elevId, page, img, imgs, hover, selected, view: {s, tx, ty}, raf }

  // inches -> 38 1/4" (nearest 1/16)
  function fmtIn(v) { v = Number(v); const w = Math.floor(v), s = Math.round((v - w) * 16); const W = s === 16 ? w + 1 : w, S = s === 16 ? 0 : s;
    if (!S) return W + '"'; let n = S, d = 16; while (n % 2 === 0) { n /= 2; d /= 2; } return (W ? W + ' ' : '') + n + '/' + d + '"'; }
  // "38 1/4", "38-1/4", "38.25" -> 38.25
  function parseIn(t) { t = String(t).replace(/["”]/g, '').trim(); if (!t) return NaN; const m = t.match(/^(\d+(?:\.\d+)?)?[\s-]*(?:(\d+)\/(\d+))?$/); if (!m || (!m[1] && !m[2])) return NaN;
    return (m[1] ? parseFloat(m[1]) : 0) + (m[2] ? parseInt(m[2]) / parseInt(m[3]) : 0); }

  const szElev = () => elevById(sz.elevId);
  // On a run drawn across several sheets each sheet owns the values between ITS break lines; it also draws a little of
  // its neighbours for context, and those are counted on the sheet that draws them whole (counted === false).
  const szMarksOnPage = () => szElev().marks.filter(m => m.page === sz.page && m.counted !== false);
  const szContextMarks = () => szElev().marks.filter(m => m.page === sz.page && m.counted === false);
  // [lo, hi] in page points of the part this sheet owns; null = open on that side. One line: it bounds whichever end it is at.
  function szOwnedRange(sheet, brk) {
    if (!brk.length) return [null, null];
    if (brk.length === 1) return brk[0].x < Number(sheet.width) / 2 ? [brk[0].x, null] : [null, brk[0].x];
    return [brk[0].x, brk[brk.length - 1].x];
  }
  function szBreaks(sheet) { return ((sheet && sheet.breaks) || []).map(b => ({ num: b.num, x: Number(b.x) })).sort((a, b) => a.x - b.x); }

  async function openSheetViewer(elevId) {
    const e = elevById(elevId); if (!e) return;
    showJobTab('drawings', false);
    // open on the first sheet that still has something to check
    const first = (e.sheets.find(sh => e.marks.some(m => m.page === sh.page && m.counted !== false && m.status === 'suggested')) || e.sheets[0] || {}).page || null;
    if (sz) cancelAnimationFrame(sz.raf);
    sz = { elevId, page: first, img: null, imgs: {}, hover: null, selected: null, view: null };
    if (drawCollapsed.delete(e.set.id)) saveCollapsed();   // its file opens so the selection is visible in the list
    drawEmpty.classList.add('d-none'); drawDetail.classList.remove('d-none');
    szPage.innerHTML = e.sheets.map(s => `<option value="${s.page}">Sheet ${s.page}</option>`).join('');
    szPage.value = first;
    szNote.innerHTML = '';
    szBindStage();
    renderDrawHead(); renderDrawReview(); szRenderInfo();
    szCap.textContent = e.marks.length ? '' : 'No dimension positions were stored for this elevation.';
    setTimeout(() => { if (sz && sz.elevId === elevId) szLoadPage(); }, 0);   // let the pane lay out so the stage has a size
  }
  const selectElevation = openSheetViewer;

  // elevation-level panel: confidence and why, and each frame's check with what to do about it
  function szRenderInfo() {
    const e = szElev(), cf = e.confidence;
    const axisBlock = (axis, label) => {
      const c = (e.axis_checks || []).find(x => x.axis === axis); if (!c) return '';
      const [l, cls] = AX_STATE[c.state];
      const detail = c.state === 'closes' || c.state === 'corrected' ? `uniform member ${c.member ? fmtIn(c.member) : '—'}${c.printed ? ' (members as printed)' : ''}${c.dropped ? `, ${c.dropped} reading${c.dropped === 1 ? '' : 's'} left out` : ''}`
        : c.state === 'unverifiable' ? (e.multi_sheet ? 'This elevation runs across several sheets; each frame closes per sheet, so it is not judged.' : 'No frame size, or nothing in the frame, to check against.')
        : c.state === 'accepted' ? `Accepted: ${esc(c.resolution_reason || '')}` : c.state === 'unverified' ? 'Kept as read, marked unverified.' : 'Frame size ≠ openings + a uniform 1" to 4 1/2" member. A reading is probably wrong.';
      const act = (c.state === 'flagged') ? `<div class="mt-1" data-permission="pm.jobs.manage"><button class="btn btn-sm" onclick="szResolve('${axis}', 'unverified')">Keep, mark unverified</button> <button class="btn btn-sm" onclick="szResolve('${axis}', 'accepted')">Accept with a reason…</button></div>`
        : (c.state === 'accepted' || c.state === 'unverified') ? `<div class="mt-1" data-permission="pm.jobs.manage"><button class="btn btn-sm btn-ghost-secondary" onclick="szUnresolve('${axis}')">Undo</button></div>` : '';
      return `<div class="border rounded p-2 mb-2"><div class="d-flex align-items-center"><strong>${label}</strong><span class="badge ${cls} ms-2">${l}</span></div><div class="small text-muted">${detail}</div>${act}</div>`;
    };
    // a long run is drawn across sheets; each owns the lites between its own break lines (the rest is context)
    const runBlock = !e.multi_sheet ? '' : (() => {
      const rc = e.run_checks, per = rc ? rc.sheets : e.sheets.map(sh => ({ page: sh.page, lites: e.marks.filter(m => m.page === sh.page && m.kind === 'dlo_w' && m.counted !== false).length }));
      const total = rc ? rc.total_lites : per.reduce((a, x) => a + x.lites, 0);
      const mark = (ok) => ok === true ? '<span class="text-success">✓</span>' : ok === false ? '<span class="text-danger">✗</span>' : '<span class="text-muted">–</span>';
      const lines = !rc ? '' : rc.breaks.map(b => `<div>${mark(b.ok)} Break line ${b.num}: sheets ${b.sheets[0]} and ${b.sheets[1]} ` + (b.ok === true ? `agree on the ${b.compared} overlapping lite${b.compared === 1 ? '' : 's'}`
          : b.ok === false ? 'disagree: ' + b.mismatches.map(m => `sheet ${m.drawn_on} draws ${fmtIn(m.drawn)} where sheet ${m.owned_on} has ${fmtIn(m.owned)}`).join('; ') : 'draw none of each other, nothing to compare') + '</div>').join('')
        + rc.heights.map(h => `<div>${mark(h.ok)} ${h.label}: ${h.values.map(v => `p.${v.page} ${fmtIn(v.value)}`).join(', ')}${h.ok ? ' — the same on every sheet' : ' — the sheets differ'}</div>`).join('');
      return `<div class="border rounded p-2 mb-2 small"><div class="d-flex align-items-center"><strong>Run across ${e.sheets.length} sheets</strong>
        ${rc ? `<span class="badge ${rc.ok ? 'bg-green-lt' : 'bg-red-lt'} ms-auto">${rc.ok ? 'sheets agree' : 'sheets disagree'}</span>` : ''}</div>
        <div class="text-muted">Each sheet owns the lites between its break lines: ${per.map(x => `p.${x.page}: ${x.lites}`).join(' + ')} = ${total} lites${rc ? ` · ${fmtIn(rc.total_width)} of lites in all` : ''}.</div>${lines}</div>`;
    })();
    szElevInfo.innerHTML = `<div class="d-flex align-items-center mb-2"><h4 class="mb-0">Confidence</h4><span class="ms-auto">${confChip(cf)}</span></div>
      <ul class="small text-muted ps-3 mb-2">${cf.reasons.map(r => `<li>${esc(r)}</li>`).join('')}</ul>
      ${e.manual_takeoff ? `<div class="alert alert-warning py-1 small">Manual takeoff: ${esc(e.manual_takeoff)}. The numbers are what the sheet printed; nothing was sized.</div>` : ''}
      ${runBlock}${axisBlock('w', 'Width frame')}${axisBlock('h', 'Height frame')}`;
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  // ---- the sheet viewer: framed on the elevation, wheel zoom, drag pan, jump to a value ----
  // The view is { s, tx, ty }: s CSS pixels per page point, (tx, ty) where the page's top-left sits in the stage.
  const SZ_MAX_SCALE = 6;
  const szSheet = () => szElev().sheets.find(s => s.page === sz.page);
  const szBox = m => [Number(m.x0), Number(m.top), Number(m.x1), Number(m.bottom)];
  const szStageSize = () => [szStage.clientWidth || 800, szStage.clientHeight || 500];

  // The region of the sheet that holds THIS elevation (ported from the PM Suite's sheet crop). The printed dimensions
  // sit outside the frame: width chains above or below it, height chains beside it, so their union misses the far side.
  // The overall R.O. (else F.S.) text is centred on the frame, and the frame starts just past the innermost chain on
  // the other axis, so mirroring that edge about the centre gives the far edge. The title block is included.
  // [left, right] of the frame in page points, from the width values; null when it cannot be worked out.
  // Each D.L.O./door text is centred over its opening, so the centres are spaced by the openings and the members between.
  function szFrameExtentX(marks) {
    const fs = marks.find(m => m.kind === 'fs_w'); if (!fs) return null;      // F.S. = openings + members, so it fixes the scale (R.O. adds joints)
    const fsV = Number(fs.value);
    const terms = marks.filter(m => m.kind === 'dlo_w' || m.kind === 'do_w');
    if (terms.length < 2) return null;
    // one chain: the row of openings printed at the same height (several stacked chains would overlap in x)
    const top = Number(terms[0].top), row = terms.filter(m => Math.abs(Number(m.top) - top) <= 20)
      .map(m => ({ c: (Number(m.x0) + Number(m.x1)) / 2, v: Number(m.value) })).sort((a, b) => a.c - b.c);
    if (row.length < 2) return null;
    const member = (fsV - row.reduce((a, r) => a + r.v, 0)) / (row.length + 1);   // the uniform member between openings
    if (!(member >= 0.5 && member <= 6)) return null;
    let inches = 0; for (let i = 0; i < row.length - 1; i++) inches += (row[i].v + row[i + 1].v) / 2 + member;
    const scale = (row[row.length - 1].c - row[0].c) / inches;                 // page points per inch
    if (!(scale > 0.05 && scale < 20)) return null;
    const cx = (Number(fs.x0) + Number(fs.x1)) / 2, half = fsV * scale / 2;
    return [cx - half, cx + half];
  }
  function szCropBox(sheet, marks) {
    const full = [0, 0, Number(sheet.width), Number(sheet.height)];
    if (!marks.length) return full;
    let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    marks.forEach(m => { const b = szBox(m); x0 = Math.min(x0, b[0]); y0 = Math.min(y0, b[1]); x1 = Math.max(x1, b[2]); y1 = Math.max(y1, b[3]); });
    const ctr = kinds => { for (const k of kinds) { const m = marks.find(q => q.kind === k); if (m) { const b = szBox(m); return [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2]; } } return null; };
    const W = marks.filter(m => /_w$/.test(m.kind)), H = marks.filter(m => /_h$/.test(m.kind));
    const far = (c, chains, lo, hi) => {
      const mid = m => (szBox(m)[lo] + szBox(m)[hi]) / 2;
      const before = chains.filter(m => mid(m) < c), after = chains.filter(m => mid(m) >= c);
      if (before.length && before.length >= after.length) { const near = Math.max(...before.map(m => szBox(m)[hi])); return [near, 2 * c - near]; }
      if (after.length) { const near = Math.min(...after.map(m => szBox(m)[lo])); return [2 * c - near, near]; }
      return null;
    };
    const cw = ctr(['ro_w', 'fs_w']), ch = ctr(['ro_h', 'fs_h']);
    if (cw) { const f = far(cw[0], H, 0, 2); if (f) { x0 = Math.min(x0, f[0], f[1]); x1 = Math.max(x1, f[0], f[1]); } }
    if (ch) { const f = far(ch[1], W, 1, 3); if (f) { y0 = Math.min(y0, f[0], f[1]); y1 = Math.max(y1, f[0], f[1]); } }
    // The marks only say where READ values sit, and a chain the parser missed (46/E22: no heights read) has none. So also
    // measure the frame itself from the width values: points per inch from the spacing of the lites' centres, then the
    // frame is the F.S. wide about the F.S. text's centre. The height chains and the R.O./B.O. notes sit just outside it,
    // mostly on the left in this drafting style, so leave room on both sides (more on the left).
    const fr = szFrameExtentX(marks);
    if (fr) {
      const fw = fr[1] - fr[0];
      x0 = Math.min(x0, fr[0] - Math.max(130, 0.30 * fw)); x1 = Math.max(x1, fr[1] + Math.max(60, 0.12 * fw));
    }
    const t = [sheet.title_x0, sheet.title_top, sheet.title_x1, sheet.title_bottom].map(v => v == null ? NaN : Number(v));
    if (t.every(isFinite)) { x0 = Math.min(x0, t[0]); y0 = Math.min(y0, t[1]); x1 = Math.max(x1, t[2]); y1 = Math.max(y1, t[3]); }
    szBreaks(sheet).forEach(b => { x0 = Math.min(x0, b.x); x1 = Math.max(x1, b.x); });      // a run's break lines frame this sheet's own part
    const pad = 0.10 * Math.max(x1 - x0, y1 - y0, 24);
    return [Math.max(0, x0 - pad), Math.max(0, y0 - pad), Math.min(full[2], x1 + pad), Math.min(full[3], y1 + pad)];
  }

  function szViewFor(box, maxS) {
    const [W, H] = szStageSize(), bw = Math.max(1, box[2] - box[0]), bh = Math.max(1, box[3] - box[1]);
    const s = Math.min(maxS || SZ_MAX_SCALE, W / bw, H / bh);
    return { s, tx: -box[0] * s + (W - bw * s) / 2, ty: -box[1] * s + (H - bh * s) / 2 };
  }
  // move the camera: instant, or a short ease (centre moves straight, scale changes geometrically)
  function szGo(target, animate) {
    cancelAnimationFrame(sz.raf);
    if (!animate || !sz.view) { sz.view = target; szDraw(); return; }
    const [W, H] = szStageSize(), a = sz.view, t0 = performance.now(), D = 280;
    const ca = { x: (W / 2 - a.tx) / a.s, y: (H / 2 - a.ty) / a.s }, cb = { x: (W / 2 - target.tx) / target.s, y: (H / 2 - target.ty) / target.s };
    const step = now => {
      if (!sz) return;
      const k = Math.min(1, (now - t0) / D), e = 1 - Math.pow(1 - k, 3), s = a.s * Math.pow(target.s / a.s, e);
      sz.view = { s, tx: W / 2 - (ca.x + (cb.x - ca.x) * e) * s, ty: H / 2 - (ca.y + (cb.y - ca.y) * e) * s };
      szDraw(); if (k < 1) sz.raf = requestAnimationFrame(step);
    };
    sz.raf = requestAnimationFrame(step);
  }
  function szFrameSheet(animate) { const sh = szSheet(); if (sh) { sz.selected = null; szGo(szViewFor([0, 0, Number(sh.width), Number(sh.height)]), animate); szRenderList(); } }
  function szFrameElevation(animate) { const sh = szSheet(); if (sh) { sz.selected = null; szGo(szViewFor(szCropBox(sh, szMarksOnPage())), animate); szRenderList(); } }
  // frame one value with enough around it to read it: about 4 px per point, never a tiny sliver
  function szFocus(id, animate) {
    const m = szMarksOnPage().find(x => x.id === id), sh = szSheet(); if (!m || !sh) return;
    sz.selected = id;
    const b = szBox(m), pad = Math.max(60, 1.5 * Math.max(b[2] - b[0], b[3] - b[1]));
    szGo(szViewFor([b[0] - pad, b[1] - pad, b[2] + pad, b[3] + pad], 4), animate !== false);
    szRenderList();
    const row = document.getElementById('szMark' + id); if (row && row.scrollIntoView) row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
  function szZoomBy(f, px, py) {
    const sh = szSheet(); if (!sh || !sz.view) return;
    const [W, H] = szStageSize(), v = sz.view, fit = Math.min(W / sh.width, H / sh.height);
    if (px == null) { px = W / 2; py = H / 2; }
    const ns = Math.min(SZ_MAX_SCALE, Math.max(fit * 0.6, v.s * f));
    cancelAnimationFrame(sz.raf);
    sz.view = { s: ns, tx: px - (px - v.tx) * ns / v.s, ty: py - (py - v.ty) * ns / v.s }; szDraw();
  }

  // walk the elevation's values in order (across sheets); onlyUnchecked skips what is already confirmed or corrected
  function szStep(dir, onlyUnchecked, fromId) {
    const e = szElev(), seq = e.marks.filter(m => m.counted !== false).sort((a, b) => a.page - b.page || a.id - b.id);
    if (!seq.length) return;
    let i = seq.findIndex(m => m.id === (fromId ?? sz.selected));
    if (i < 0) i = dir > 0 ? -1 : 0;
    for (let n = 0; n < seq.length; n++) {
      i = (i + dir + seq.length) % seq.length;
      if (onlyUnchecked && seq[i].status !== 'suggested') continue;
      const m = seq[i];
      if (m.page !== sz.page) { szPage.value = m.page; szLoadPage(m.id); } else szFocus(m.id);
      return;
    }
    if (onlyUnchecked) { szNote.innerHTML = '<div class="text-success">Every value on this elevation has been checked.</div>'; szFrameElevation(true); }
  }

  // "Sheet 2 — values between break lines 1 and 2": which part of the run this sheet owns
  function szCapText() {
    const sh = szSheet(), b = szBreaks(sh), n = x => x.num != null ? x.num : '?';
    if (!b.length) return `Sheet ${sz.page}`;
    const r = szOwnedRange(sh, b);
    return `Sheet ${sz.page} — values ` + (b.length === 1 ? `${r[0] != null ? 'right' : 'left'} of break line ${n(b[0])}` : `between break lines ${n(b[0])} and ${n(b[b.length - 1])}`);
  }

  function szLoadPage(focusId) {
    sz.page = parseInt(szPage.value || sz.page); sz.selected = focusId || null; sz.error = null;
    const sheet = szSheet();
    szCap.textContent = sheet ? 'Loading the sheet…' : '';
    // frame first (the sheet's size and the printed positions are known before the picture arrives)
    if (sheet) szGo(focusId ? (() => { const m = szMarksOnPage().find(x => x.id === focusId), b = szBox(m), pad = Math.max(60, 1.5 * Math.max(b[2] - b[0], b[3] - b[1])); return szViewFor([b[0] - pad, b[1] - pad, b[2] + pad, b[3] + pad], 4); })()
      : szViewFor(szCropBox(sheet, szMarksOnPage())), false);
    szRenderList();
    const page = sz.page;
    if (sz.imgs[page]) { sz.img = sz.imgs[page]; szCap.textContent = szCapText(); szDraw(); return; }
    sz.img = null;
    const img = new Image();
    img.onload = () => { if (!sz) return; sz.imgs[page] = img; if (sz.page === page) { sz.img = img; szCap.textContent = szCapText(); szDraw(); } };
    img.onerror = () => { if (!sz || sz.page !== page) return; sz.error = true; szCap.textContent = 'The sheet image could not be loaded (is the drawing worker running?). The values are still listed.'; szDraw(); };
    img.src = `${location.origin}/api/v1/pm/jobs/${JOB_ID}/drawing-sets/${szElev().set.id}/pages/${page}/image?dpi=160`;   // sharp enough to read printed dimensions when zoomed in
  }

  function szDraw() {
    if (!sz || !sz.view) return;
    const sheet = szSheet(), cv = szCanvas; if (!sheet) return;
    const [W, H] = szStageSize(), dpr = window.devicePixelRatio || 1;
    if (cv.width !== Math.round(W * dpr) || cv.height !== Math.round(H * dpr)) { cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr); }
    const ctx = cv.getContext('2d'), { s, tx, ty } = sz.view, u = 1 / s;   // u: one screen pixel in page points
    ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.fillStyle = '#39434f'; ctx.fillRect(0, 0, cv.width, cv.height);
    ctx.setTransform(dpr * s, 0, 0, dpr * s, dpr * tx, dpr * ty);
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, sheet.width, sheet.height);
    ctx.imageSmoothingQuality = 'high';
    if (sz.img) ctx.drawImage(sz.img, 0, 0, Number(sheet.width), Number(sheet.height));
    // a run drawn across sheets: shade what lies beyond this sheet's break lines and draw the lines, numbered
    const brk = szBreaks(sheet), range = szOwnedRange(sheet, brk);
    if (brk.length) {
      ctx.fillStyle = 'rgba(40,50,70,0.22)';
      if (range[0] != null) ctx.fillRect(0, 0, range[0], Number(sheet.height));
      if (range[1] != null) ctx.fillRect(range[1], 0, Number(sheet.width) - range[1], Number(sheet.height));
      brk.forEach(b => {
        ctx.save(); ctx.setLineDash([10 * u, 6 * u]); ctx.lineWidth = 2 * u; ctx.strokeStyle = '#206bc4';
        ctx.beginPath(); ctx.moveTo(b.x, 0); ctx.lineTo(b.x, Number(sheet.height)); ctx.stroke(); ctx.restore();
        const fs = Math.max(12 * u, 9), txt = 'Break line' + (b.num != null ? ' ' + b.num : '');
        ctx.font = `bold ${fs}px sans-serif`; ctx.textBaseline = 'top'; const tw = ctx.measureText(txt).width;
        ctx.fillStyle = 'rgba(255,255,255,0.92)'; ctx.fillRect(b.x + 4 * u, 6 * u, tw + 8 * u, fs + 6 * u);
        ctx.fillStyle = '#206bc4'; ctx.fillText(txt, b.x + 8 * u, 9 * u);
      });
    }
    szContextMarks().forEach(m => {      // drawn beyond the lines for context: outlined only, counted on the neighbouring sheet
      const b = szBox(m), pad = 3 * u;
      ctx.save(); ctx.setLineDash([5 * u, 4 * u]); ctx.lineWidth = 1.5 * u; ctx.strokeStyle = '#6e7781';
      ctx.strokeRect(b[0] - pad, b[1] - pad, b[2] - b[0] + pad * 2, b[3] - b[1] + pad * 2); ctx.restore();
    });
    szMarksOnPage().forEach(m => {
      const [fill, line] = SZ_COLORS[m.status] || SZ_COLORS.suggested, pad = 3 * u, b = szBox(m);
      const x = b[0] - pad, y = b[1] - pad, w = b[2] - b[0] + pad * 2, h = b[3] - b[1] + pad * 2;
      const hot = sz.hover === m.id, sel = sz.selected === m.id;
      ctx.fillStyle = fill; ctx.fillRect(x, y, w, h);
      ctx.lineWidth = (hot || sel ? 3 : 1.5) * u; ctx.strokeStyle = line; ctx.strokeRect(x, y, w, h);
      if (sel) { const o = 5 * u; ctx.lineWidth = 2 * u; ctx.strokeStyle = '#206bc4'; ctx.strokeRect(x - o, y - o, w + o * 2, h + o * 2); }
      else if (hot) { ctx.save(); ctx.shadowColor = line; ctx.shadowBlur = 12 * dpr; ctx.strokeRect(x, y, w, h); ctx.restore(); }
      if (m.status === 'mismatch') {   // the PM Suite's correction stamp: the printed value struck in red, the corrected one written beside it
        ctx.strokeStyle = '#cd281e'; ctx.lineWidth = 2.5 * u; ctx.beginPath(); ctx.moveTo(x, y + h / 2); ctx.lineTo(x + w, y + h / 2); ctx.stroke();
        const fs = Math.max(11 * u, h * 0.95), txt = fmtIn(m.confirmed_value), vertical = /_h$/.test(m.kind);
        ctx.font = `bold ${fs}px sans-serif`; ctx.textBaseline = 'alphabetic';
        const tX = vertical ? x + w + 4 * u : x, tY = vertical ? y + h / 2 + fs / 3 : y - 5 * u, tw = ctx.measureText(txt).width;
        ctx.fillStyle = 'rgba(255,255,255,0.92)'; ctx.fillRect(tX - 2 * u, tY - fs, tw + 4 * u, fs + 4 * u);
        ctx.fillStyle = '#111'; ctx.fillText(txt, tX, tY);
      }
    });
  }

  function szRenderList() {
    const marks = szMarksOnPage();
    szList.innerHTML = marks.length ? marks.map(m => {
      const [, line] = SZ_COLORS[m.status] || SZ_COLORS.suggested;
      return `<div id="szMark${m.id}" class="border rounded p-2 mb-2${sz.selected === m.id ? ' bg-azure-lt' : ''}" style="border-left:4px solid ${line} !important;cursor:pointer" onmouseenter="szSetHover(${m.id})" onmouseleave="szSetHover(null)" onclick="szFocus(${m.id})">
        <div class="d-flex align-items-center"><div><div class="small text-muted">${esc(SZ_KIND[m.kind] || m.kind)}${/^dlo/.test(m.kind) || (m.kind === 'do_w' && m.idx > 0) ? ' #' + (m.idx + 1) : ''}
            <span class="badge ${CONF_CLASS[m.confidence.level]} ms-1" title="${esc(m.confidence.reasons.join(' • '))}">${m.confidence.score}%</span></div>
          <strong>${fmtIn(m.value)}</strong>${m.status === 'mismatch' ? ` <span class="text-danger">→ ${fmtIn(m.confirmed_value)}</span>` : ''}</div>
          <span class="ms-auto text-nowrap" data-permission="pm.jobs.manage">
            <button class="btn btn-sm ${m.status === 'confirmed' ? 'btn-success' : ''}" onclick="event.stopPropagation(); szConfirmNext(${m.id})">✓</button>
            <button class="btn btn-sm" onclick="event.stopPropagation(); szCorrect(${m.id})">Correct</button>
            ${m.status !== 'suggested' ? `<button class="btn btn-sm btn-ghost-secondary" onclick="event.stopPropagation(); szMark([${m.id}], 'clear')">Clear</button>` : ''}</span></div></div>`;
    }).join('') : '<div class="text-muted small">No dimensions were located on this sheet.</div>';
    const ctx = szContextMarks().length;
    if (ctx) szList.innerHTML += `<div class="small text-muted border rounded p-2">${ctx} more reading${ctx === 1 ? '' : 's'} on this sheet lie beyond its break lines and are drawn dashed for context. They are counted on the neighbouring sheet.</div>`;
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  function szSetHover(id) { if (sz.hover === id) return; sz.hover = id; szDraw(); }
  const szHit = (cx, cy) => {   // stage pixel -> the value printed there, if any
    const px = (cx - sz.view.tx) / sz.view.s, py = (cy - sz.view.ty) / sz.view.s, pad = Math.max(3, 4 / sz.view.s);
    return szMarksOnPage().find(m => { const b = szBox(m); return px >= b[0] - pad && px <= b[2] + pad && py >= b[1] - pad && py <= b[3] + pad; });
  };
  let szBound = false;
  function szBindStage() {
    if (szBound) return; szBound = true;
    let drag = null;
    const pos = ev => { const r = szStage.getBoundingClientRect(); return [ev.clientX - r.left, ev.clientY - r.top]; };
    szStage.addEventListener('wheel', ev => { if (!sz || !sz.view) return; ev.preventDefault(); const [x, y] = pos(ev); szZoomBy(Math.exp(-ev.deltaY * 0.0015), x, y); }, { passive: false });
    szStage.addEventListener('pointerdown', ev => { if (!sz || !sz.view) return; cancelAnimationFrame(sz.raf); drag = { x: ev.clientX, y: ev.clientY, tx: sz.view.tx, ty: sz.view.ty, moved: false };
      try { szStage.setPointerCapture(ev.pointerId); } catch (_) {} });
    szStage.addEventListener('pointermove', ev => {
      if (!sz || !sz.view) return;
      if (drag) {
        if (!drag.moved && Math.hypot(ev.clientX - drag.x, ev.clientY - drag.y) > 4) { drag.moved = true; szStage.style.cursor = 'grabbing'; }
        if (drag.moved) { sz.view = { s: sz.view.s, tx: drag.tx + ev.clientX - drag.x, ty: drag.ty + ev.clientY - drag.y }; szDraw(); }
        return;
      }
      const [x, y] = pos(ev), hit = szHit(x, y); szStage.style.cursor = hit ? 'pointer' : 'grab'; szSetHover(hit ? hit.id : null);
    });
    const up = ev => { if (!drag) return; const wasClick = !drag.moved; drag = null; szStage.style.cursor = 'grab';
      if (wasClick && sz && sz.view) { const [x, y] = pos(ev), hit = szHit(x, y); if (hit) szFocus(hit.id); } };
    szStage.addEventListener('pointerup', up);
    szStage.addEventListener('pointercancel', () => { drag = null; szStage.style.cursor = 'grab'; });
    szStage.addEventListener('pointerleave', () => { if (!drag && sz) szSetHover(null); });
    if (window.ResizeObserver) new ResizeObserver(() => { if (sz) szDraw(); }).observe(szStage);
    document.addEventListener('keydown', ev => {
      if (!sz || drawDetail.classList.contains('d-none') || drawCard.closest('.job-pane').style.display === 'none' || /INPUT|TEXTAREA|SELECT/.test(ev.target.tagName)) return;
      if (ev.key === 'ArrowRight') { ev.preventDefault(); szStep(1); }
      else if (ev.key === 'ArrowLeft') { ev.preventDefault(); szStep(-1); }
      else if (ev.key === '+' || ev.key === '=') szZoomBy(1.4);
      else if (ev.key === '-') szZoomBy(1 / 1.4);
      else if (ev.key === 'Enter' && sz.selected && hasPermission('pm.jobs.manage')) { ev.preventDefault(); szConfirmNext(sz.selected); }
    });
  }
  // confirm a value and go straight to the next one still unchecked, which is the flow when working down a sheet
  async function szConfirmNext(id) {
    const ok = await szMark([id], 'confirm');
    if (ok && sz) szStep(1, true, id);
  }

  // after any change the server re-runs the frame check and recomputes confidence: reload and repaint
  async function szRefresh() {
    await loadDrawings();
    if (sz && elevById(sz.elevId)) { szRenderInfo(); szRenderList(); szDraw(); }
  }
  async function szMark(ids, action, value) {
    try { await post(`/pm/jobs/${JOB_ID}/drawing-sets/${szElev().set.id}/marks`, { ids, action, value }); szNote.innerHTML = ''; await szRefresh(); return true; }
    catch (err) { szNote.innerHTML = `<div class="text-danger">${esc(err.message || 'Could not save')}</div>`; return false; }
  }
  function szCorrect(id) {
    const m = szMarksOnPage().find(x => x.id === id), t = prompt(`What is the real ${SZ_KIND[m.kind]}? (the drawing read ${fmtIn(m.value)})`, fmtIn(m.confirmed_value ?? m.value));
    if (t === null) return;
    const v = parseIn(t); if (isNaN(v)) { szNote.innerHTML = '<div class="text-danger">Enter inches like 38 1/4 or 38.25.</div>'; return; }
    szMark([id], 'correct', v);
  }
  function szConfirmAll() { const ids = szMarksOnPage().filter(m => m.status === 'suggested').map(m => m.id); if (ids.length) szMark(ids, 'confirm'); }
  async function szResolve(axis, mode) {
    let reason = null;
    if (mode === 'accepted') { reason = prompt('Accept this frame as it is? Say why in one line. It stays on the record.'); if (!reason || !reason.trim()) return; }
    try { await post(`/pm/jobs/${JOB_ID}/drawing-sets/${szElev().set.id}/elevations/${sz.elevId}/axes/${axis}/resolve`, { mode, reason }); await szRefresh(); }
    catch (err) { szNote.innerHTML = `<div class="text-danger">${esc(err.message || 'Could not save')}</div>`; }
  }
  async function szUnresolve(axis) {
    try { await authenticatedFetch(`/pm/jobs/${JOB_ID}/drawing-sets/${szElev().set.id}/elevations/${sz.elevId}/axes/${axis}/resolve`, { method: 'DELETE' }); await szRefresh(); }
    catch (err) { szNote.innerHTML = `<div class="text-danger">${esc(err.message || 'Could not save')}</div>`; }
  }

  // ---- work order requests ----
  const WO_STATUS = { pending: ['Pending', 'bg-orange-lt'], approved: ['Approved', 'bg-green-lt'], rejected: ['Rejected', 'bg-red-lt'], withdrawn: ['Withdrawn', 'bg-secondary-lt'] };
  async function loadWo() {
    const rows = await authenticatedFetch(`/pm/jobs/${JOB_ID}/work-order-requests`);
    const pendingWo = rows.filter(r => r.status === 'pending').length;
    statWo.textContent = pendingWo; tabWo.textContent = pendingWo; tabWo.style.display = pendingWo ? '' : 'none';
    woBody.innerHTML = rows.length ? rows.map(r => { const [l, c] = WO_STATUS[r.status] || [r.status, 'bg-secondary-lt'];
      return `<div class="list-group-item"><div class="d-flex align-items-center gap-2"><span class="badge ${c}">${l}</span>
        <strong>${esc(r.release_code || 'Release')}</strong><span class="text-muted">${r.requested_due_date ? 'needed ' + esc(r.requested_due_date) : ''}</span>
        <span class="ms-auto">${r.status === 'pending' ? `<button class="btn btn-sm" data-permission="pm.jobs.manage" onclick="withdrawWo(${r.id})">Withdraw</button>` : ''}</span></div>
        ${r.drawing_set ? `<div class="small mt-1"><i class="ti ti-file-type-pdf text-red"></i> ${esc(r.drawing_set.file ? r.drawing_set.file.original_name : 'Drawing set')}${(r.elevations || []).length ? ': ' + r.elevations.map(l => `<span class="badge bg-azure-lt">${esc(l.label)} × ${l.quantity}${l.total_reqd ? ' of ' + l.total_reqd : ''}</span>`).join(' ') : ''}</div>` : ''}
        ${r.scope ? `<div class="small mt-1">${esc(r.scope)}</div>` : ''}
        ${r.files.length ? `<div class="small text-muted mt-1">${r.files.length} drawing file(s) attached</div>` : ''}
        ${(r.changes || []).length ? `<div class="small mt-1 border-start border-warning ps-2"><strong>Changed by Fabrication</strong>${r.changes.map(c => `<div>${esc(c.user ? c.user.name : 'Fabrication')}, ${esc((c.created_at || '').slice(0, 10))}: ${esc(c.summary.split('\n').join('; '))}${c.note ? ` <em>— ${esc(c.note)}</em>` : ''}</div>`).join('')}</div>` : ''}
        ${r.decision_note ? `<div class="small mt-1"><em>Fabrication:</em> ${esc(r.decision_note)}</div>` : ''}
        ${r.work_order ? `<div class="small mt-1">Work order created (release ${r.work_order.release_number}).</div>` : ''}</div>`; }).join('') : '<div class="list-group-item text-muted">No requests yet.</div>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }
  // elevations still to release: what the drawing calls for, less what pending / approved requests already cover
  const woRemaining = (e) => e.total_reqd == null ? null : Math.max(0, e.total_reqd - (e.requested_qty || 0));
  function showWoForm() {
    document.querySelector('#woModal form').reset();
    // a drawing can be tied once it is finished and has at least one approved elevation
    const usable = drawSetsList.filter(st => st.status === 'done' && (drawData.elevations || []).some(e => e.set.id === st.id && e.review_status === 'approved'));
    woSet.innerHTML = '<option value="">Not tied to a drawing</option>' + usable.map(st => `<option value="${st.id}">${esc(st.file ? st.file.original_name : 'Drawing set ' + st.id)}</option>`).join('');
    woSetHint.textContent = usable.length ? 'Tie the request to a shop drawing to choose which elevations, and how many of each, it releases.'
      : 'Approve elevations on the Drawings tab to tie a drawing to a request.';
    if (usable.length === 1) woSet.value = usable[0].id;
    renderWoElevs();
    safeShowModal('woModal');
  }
  function renderWoElevs() {
    const sid = +woSet.value;
    woElevBox.style.display = sid ? '' : 'none';
    if (!sid) { woElevList.innerHTML = ''; return; }
    const els = (drawData.elevations || []).filter(e => e.set.id === sid);
    woElevList.innerHTML = els.map(e => {
      const rem = woRemaining(e), ok = e.review_status === 'approved' && rem !== 0;
      const why = e.review_status !== 'approved' ? 'not approved yet' : rem === 0 ? `all ${e.total_reqd} already requested` : '';
      return `<tr class="${ok ? '' : 'text-muted'}"><td><input type="checkbox" class="form-check-input" id="woE${e.id}" value="${e.id}" ${ok ? '' : 'disabled'} onchange="woToggle(${e.id})"></td>
        <td><label for="woE${e.id}" class="mb-0"><strong>${esc(e.label)}</strong><div class="small text-muted">${esc(e.arch_ref || '')}</div></label></td>
        <td>${esc([e.system, e.finish].filter(Boolean).join(' · ') || '—')}</td><td>${dim(e.ro_w)} × ${dim(e.ro_h)}</td>
        <td style="width:170px">${ok ? `<div class="input-group input-group-sm"><input type="number" class="form-control" id="woQ${e.id}" min="1" ${rem != null ? `max="${rem}"` : ''} value="${rem ?? 1}" disabled>${rem != null ? `<span class="input-group-text">of ${rem}</span>` : ''}</div>
          ${e.requested_qty ? `<div class="small text-muted">${e.requested_qty} of ${e.total_reqd} already requested</div>` : ''}` : `<span class="small">${why}</span>`}</td></tr>`;
    }).join('') || '<tr><td colspan="5" class="text-muted">No elevations were read from this drawing.</td></tr>';
  }
  function woToggle(id) { const q = document.getElementById('woQ' + id); q.disabled = !document.getElementById('woE' + id).checked; if (!q.disabled) q.focus(); }
  // [{ drawing_elevation_id, quantity }] for the elevations ticked
  function woSelection() {
    return [...woElevList.querySelectorAll('input[type=checkbox]:checked')].map(cb => ({ drawing_elevation_id: +cb.value, quantity: +document.getElementById('woQ' + cb.value).value }));
  }
  async function saveWo(e) {
    e.preventDefault();
    const sel = woSelection(), sid = +woSet.value || null;
    if (sid && !sel.length) { notice('Tick the elevations this request releases, or choose "Not tied to a drawing".', 'warning'); return; }
    try {
      const r = await post(`/pm/jobs/${JOB_ID}/work-order-requests`, { release_code: woCode.value || null, requested_due_date: woDue.value || null, scope: woScope.value || null, notes: woNotes.value || null,
        drawing_set_id: sid, elevations: sel });
      for (const f of woFiles.files) { const fd = new FormData(); fd.append('file', f); await authenticatedUpload(`/pm/work-order-requests/${r.id}/files`, fd); }
      safeHideModal('woModal'); notice('Request sent to Fabrication.', 'success'); loadWo(); loadDrawings();   // the drawing's remaining quantities change
    } catch (err) { notice(err.message || 'Could not send the request', 'danger'); }
  }
  async function withdrawWo(id) { if (confirm('Withdraw this request?')) { try { await post(`/pm/work-order-requests/${id}/withdraw`, {}); loadWo(); } catch (e) { notice(e.message, 'danger'); } } }

  // ---- submittals ----
  const SM_STATUS = { pending: 'Pending', approved: 'Approved', approved_as_noted: 'Approved as noted', revise_resubmit: 'Revise & resubmit', rejected: 'Rejected', for_record_only: 'For record only' };
  const SM_KIND = { docs: 'Docs', sample: 'Sample', docs_samples: 'Docs + samples' };
  const smBase = () => `/pm/jobs/${JOB_ID}/submittals`;
  let smOpen = null, smRespFor = null, jobPos = [];

  async function loadSubmittals() {
    const needs = document.getElementById('smNeeds').checked;
    const rows = await authenticatedFetch(smBase() + (needs ? '?needs_response=1' : ''));
    const body = document.getElementById('smBody');
    body.innerHTML = rows.length ? rows.map(s => `<tr style="cursor:pointer" onclick="toggleSubmittal(${s.id})">
      <td>${esc(s.number)}${s.revision_number ? ` <span class="badge bg-azure-lt">Rev ${s.revision_number}</span>` : ''}</td>
      <td>${esc(s.name)}<div class="text-muted small">${esc(s.spec_section || '')}</div></td><td>${SM_KIND[s.kind] || s.kind}</td>
      <td>${(s.purchase_orders || []).map(p => `<a href="/pm/purchase-orders/${p.id}" onclick="event.stopPropagation()">${esc(p.po_number)}</a>`).join(', ')}</td>
      <td>${esc((s.sent_at || '').slice(0, 10))}</td>
      <td onclick="event.stopPropagation()"><select class="form-select form-select-sm" data-permission="pm.jobs.manage" onchange="setSmStatus(${s.id}, this.value)">${Object.entries(SM_STATUS).map(([k, v]) => `<option value="${k}" ${k === s.status ? 'selected' : ''}>${v}</option>`).join('')}</select></td>
      <td class="text-nowrap" onclick="event.stopPropagation()" data-permission="pm.jobs.manage"><button class="btn btn-sm" onclick="reviseSm(${s.id})">Revise</button></td></tr>
      <tr id="smd-${s.id}" style="display:none"><td colspan="7" class="bg-body-tertiary" id="smdc-${s.id}"></td></tr>`).join('') : '<tr><td colspan="7" class="text-muted">No submittals yet.</td></tr>';
    if (smOpen) toggleSubmittal(smOpen, true);
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  async function toggleSubmittal(id, keep) {
    const row = document.getElementById('smd-' + id); if (!row) return;
    if (!keep && row.style.display !== 'none') { row.style.display = 'none'; smOpen = null; return; }
    document.querySelectorAll('[id^="smd-"]').forEach(r => r.style.display = 'none');
    const s = await authenticatedFetch(`${smBase()}/${id}`);
    smOpen = id; row.style.display = '';
    if (!jobPos.length) { try { jobPos = (await authenticatedFetch(`/job-purchase-orders?business_job_id=${JOB_ID}`)).data || []; } catch (e) {} }
    const linked = new Set((s.purchase_orders || []).map(p => p.id));
    document.getElementById('smdc-' + id).innerHTML = `<div class="row g-3">
      <div class="col-md-4"><h4>GC/AE responses</h4>${(s.responses || []).map(r => `<div class="mb-2">${SM_STATUS[r.status] || r.status} · ${esc((r.returned_at || '').slice(0, 10))}${r.responded_by ? ' · ' + esc(r.responded_by) : ''}${r.file ? `<div class="text-muted small">${esc(r.file.original_name)} (Rev ${r.revision_number})</div>` : ''}</div>`).join('') || '<div class="text-muted">None yet.</div>'}
        <button class="btn btn-sm mt-1" data-permission="pm.jobs.manage" onclick="showResponse(${id})">+ Record response</button></div>
      <div class="col-md-4"><h4>Samples</h4>${(s.samples || []).map(x => `<div class="mb-2 d-flex gap-2 align-items-center"><span class="flex-fill">${esc(x.description)}${x.manufacturer ? ' · ' + esc(x.manufacturer) : ''}</span>
        <select class="form-select form-select-sm w-auto" data-permission="pm.jobs.manage" onchange="setSampleStatus(${id}, ${x.id}, this.value)">${Object.entries(SM_STATUS).map(([k, v]) => `<option value="${k}" ${k === x.status ? 'selected' : ''}>${v}</option>`).join('')}</select>
        <button class="btn btn-sm btn-ghost-danger" data-permission="pm.jobs.manage" onclick="delSample(${id}, ${x.id})">&times;</button></div>`).join('') || '<div class="text-muted">None.</div>'}
        <button class="btn btn-sm mt-1" data-permission="pm.jobs.manage" onclick="addSampleRow(${id})">+ Sample</button></div>
      <div class="col-md-4"><h4>Purchase orders</h4>${(s.purchase_orders || []).map(p => `<div class="mb-1"><a href="/pm/purchase-orders/${p.id}">${esc(p.po_number)}</a> <button class="btn btn-sm btn-ghost-danger" data-permission="pm.jobs.manage" onclick="unlinkPo(${id}, ${p.id})">&times;</button></div>`).join('') || '<div class="text-muted">None linked.</div>'}
        <div class="input-group input-group-sm mt-1" data-permission="pm.jobs.manage"><select class="form-select" id="linkPo-${id}"><option value="">Link a PO…</option>${jobPos.filter(p => !linked.has(p.id)).map(p => `<option value="${p.id}">${esc(p.po_number)}</option>`).join('')}</select><button class="btn" onclick="linkPo(${id})">Link</button></div></div></div>
      <h4 class="mt-3">Package</h4>
      <div class="row g-3"><div class="col-md-7">
        ${(s.items || []).map((it, k, arr) => `<div class="d-flex align-items-center gap-1 mb-1"><span class="text-muted me-1">${k + 1}.</span><span class="flex-fill">${esc(it.label)}</span>
          <button class="btn btn-sm btn-ghost-secondary" data-permission="pm.jobs.manage" ${k === 0 ? 'disabled' : ''} onclick="moveItem(${id}, ${k}, -1)">↑</button>
          <button class="btn btn-sm btn-ghost-secondary" data-permission="pm.jobs.manage" ${k === arr.length - 1 ? 'disabled' : ''} onclick="moveItem(${id}, ${k}, 1)">↓</button>
          <button class="btn btn-sm btn-ghost-danger" data-permission="pm.jobs.manage" onclick="removeItem(${id}, ${it.id})">&times;</button></div>`).join('') || '<div class="text-muted">No documents in this package yet.</div>'}
        <div data-permission="pm.jobs.manage" class="mt-2">
          <div class="input-group input-group-sm mb-1"><select class="form-select" id="libProd-${id}" onchange="loadLibDocs(${id})"><option value="">Library product…</option></select>
            <select class="form-select" id="libDoc-${id}"><option value="">Document…</option></select><button class="btn" onclick="addLibItem(${id})">Add</button></div>
          <div class="input-group input-group-sm"><input type="file" accept=".pdf" class="form-control" id="itemFile-${id}"><input class="form-control" id="itemLabel-${id}" placeholder="Label (optional)"><button class="btn" onclick="addFileItem(${id})">Upload PDF</button></div>
        </div></div>
        <div class="col-md-5"><button class="btn btn-primary btn-sm mb-2" data-permission="pm.jobs.manage" onclick="buildPackage(${id})">Build package PDF</button>
          ${(s.packages || []).map(pk => `<div><a href="${location.origin}/api/v1/pm/files/${pk.pm_file_id}/download" target="_blank">${esc(pk.file.original_name)}</a> <span class="text-muted small">${pk.item_count} docs · ${esc((pk.created_at || '').slice(0, 10))}</span></div>`).join('') || '<div class="text-muted">Not built yet.</div>'}</div></div>
      <div class="mt-3 text-end"><button class="btn btn-sm btn-ghost-danger" data-permission="pm.jobs.manage" onclick="deleteSm(${id})">Delete submittal</button></div>`;
    fillLibProducts(id);
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  const smCall = async (path, body, method = 'POST') => { try { await post(smBase() + path, body, method); } catch (e) { notice(e.message || 'Could not save', 'danger'); return false; } return true; };
  let libProducts = null;
  async function fillLibProducts(id) {
    if (!libProducts) { try { libProducts = await authenticatedFetch('/pm/library/products'); } catch (e) { libProducts = []; } }
    const sel = document.getElementById('libProd-' + id);
    if (sel) sel.innerHTML = '<option value="">Library product…</option>' + libProducts.filter(p => p.documents_count > 0).map(p => `<option value="${p.id}">${esc(p.manufacturer ? p.manufacturer.name + ' — ' : '')}${esc(p.name)}</option>`).join('');
  }
  async function loadLibDocs(id) {
    const pid = document.getElementById('libProd-' + id).value, sel = document.getElementById('libDoc-' + id);
    if (!pid) { sel.innerHTML = '<option value="">Document…</option>'; return; }
    const p = await authenticatedFetch('/pm/library/products/' + pid);
    sel.innerHTML = p.documents.map(d => `<option value="${d.id}">${esc(d.kind)} — ${esc(d.label || d.file.original_name)}</option>`).join('');
  }
  async function addLibItem(id) { const v = document.getElementById('libDoc-' + id).value; if (v && await smCall(`/${id}/items`, { product_document_id: +v })) toggleSubmittal(id, true); }
  async function addFileItem(id) {
    const f = document.getElementById('itemFile-' + id).files[0]; if (!f) { notice('Choose a PDF first.', 'warning'); return; }
    const fd = new FormData(); fd.append('file', f); const l = document.getElementById('itemLabel-' + id).value; if (l) fd.append('label', l);
    const r = await authenticatedUpload(`${smBase()}/${id}/items`, fd);
    if (!r.ok) { const d = await r.json().catch(() => ({})); notice(d.message || 'Could not add the PDF', 'danger'); return; }
    toggleSubmittal(id, true);
  }
  async function removeItem(id, itemId) { try { await authenticatedFetch(`${smBase()}/${id}/items/${itemId}`, { method: 'DELETE' }); toggleSubmittal(id, true); } catch (e) { notice(e.message, 'danger'); } }
  async function moveItem(id, idx, dir) {
    const s = await authenticatedFetch(`${smBase()}/${id}`), ids = s.items.map(i => i.id), j = idx + dir;
    [ids[idx], ids[j]] = [ids[j], ids[idx]];
    if (await smCall(`/${id}/items/reorder`, { order: ids })) toggleSubmittal(id, true);
  }
  async function buildPackage(id) {
    notice('Building the package…', 'info');
    if (await smCall(`/${id}/package`)) { notice('Package built.', 'success'); toggleSubmittal(id, true); }
  }
  async function setSmStatus(id, status) { if (await smCall(`/${id}`, { status }, 'PUT')) loadSubmittals(); }
  async function reviseSm(id) { if (confirm('Record this round and start the next revision?') && await smCall(`/${id}/revise`)) loadSubmittals(); }
  async function deleteSm(id) { if (confirm('Delete this submittal and its history?')) { try { await authenticatedFetch(`${smBase()}/${id}`, { method: 'DELETE' }); smOpen = null; loadSubmittals(); } catch (e) { notice(e.message, 'danger'); } } }
  async function setSampleStatus(id, sid, status) { if (await smCall(`/${id}/samples/${sid}`, { status }, 'PUT')) loadSubmittals(); }
  async function delSample(id, sid) { try { await authenticatedFetch(`${smBase()}/${id}/samples/${sid}`, { method: 'DELETE' }); loadSubmittals(); } catch (e) { notice(e.message, 'danger'); } }
  async function addSampleRow(id) { const d = prompt('Sample description:'); if (d && await smCall(`/${id}/samples`, { description: d })) loadSubmittals(); }
  async function linkPo(id) { const v = document.getElementById('linkPo-' + id).value; if (v && await smCall(`/${id}/purchase-orders`, { purchase_order_id: +v })) loadSubmittals(); }
  async function unlinkPo(id, poId) { try { await authenticatedFetch(`${smBase()}/${id}/purchase-orders/${poId}`, { method: 'DELETE' }); loadSubmittals(); } catch (e) { notice(e.message, 'danger'); } }

  function showSubmittalForm() { document.querySelector('#smModal form').reset(); safeShowModal('smModal'); }
  async function saveSubmittal(e) {
    e.preventDefault();
    if (await smCall('', { name: smName.value, spec_section: smSpec.value || null, kind: smKind.value })) { safeHideModal('smModal'); loadSubmittals(); }
  }
  function showResponse(id) {
    smRespFor = id; document.querySelector('#smRespModal form').reset();
    rStatus.innerHTML = Object.entries(SM_STATUS).filter(([k]) => k !== 'pending').map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
    rReturned.value = new Date().toISOString().slice(0, 10); safeShowModal('smRespModal');
  }
  async function saveResponse(e) {
    e.preventDefault();
    const fd = new FormData(); fd.append('status', rStatus.value); fd.append('returned_at', rReturned.value);
    if (rBy.value) fd.append('responded_by', rBy.value); if (rFile.files[0]) fd.append('file', rFile.files[0]);
    const r = await authenticatedUpload(`${smBase()}/${smRespFor}/responses`, fd);
    if (!r.ok) { const d = await r.json().catch(() => ({})); notice(d.message || 'Could not save the response', 'danger'); return; }
    safeHideModal('smRespModal'); loadSubmittals();
  }

  async function decide(section, row, action, i) {
    const body = { section, row, action };
    if (action === 'fix') {
      body.cost_code = document.getElementById('fix-' + i).value;
      if (!body.cost_code) { notice('Choose a cost code first.', 'warning'); return; }
    }
    try { render(await post(`${base()}/${encodeURIComponent(currentSheet)}/override`, body)); notice(''); }
    catch (e) { notice(e.message || 'Could not save that decision', 'danger'); }
  }

  async function restore(section, row) {
    try { render(await authenticatedFetch(`${base()}/${encodeURIComponent(currentSheet)}/override/${section}/${row}`, { method: 'DELETE' })); }
    catch (e) { notice(e.message || 'Could not undo', 'danger'); }
  }

  async function uploadEstimate() {
    const f = document.getElementById('estFile').files[0];
    if (!f) { notice('Choose a workbook first.', 'warning'); return; }
    const fd = new FormData(); fd.append('file', f);
    const btn = document.getElementById('estUploadBtn'); btn.disabled = true; btn.textContent = 'Uploading…';
    try {
      const r = await authenticatedUpload(`${base()}/upload`, fd);
      const d = await r.json().catch(() => ({}));
      if (!r.ok) { notice(d.message || (d.errors ? Object.values(d.errors).flat().join(' ') : 'Upload failed'), 'danger'); return; }
      uploadedFile = d.file;
      document.getElementById('importSheet').innerHTML = d.sheets.map(s => `<option>${esc(s)}</option>`).join('');
      document.getElementById('importBox').style.display = '';
      notice(d.already_uploaded ? 'This exact file was uploaded before. Choose a tab to import.' : 'Uploaded. Choose a tab to import.', 'info');
    } catch (e) { notice(e.message || 'Upload failed', 'danger'); }
    finally { btn.disabled = false; btn.textContent = 'Upload'; }
  }

  async function importSheet() {
    const sheet = document.getElementById('importSheet').value;
    try {
      const d = await post(`${base()}/import`, { pm_file_id: uploadedFile.id, sheet });
      const msg = { imported: 'Imported.', reimported: 'Re-imported with the new file.', unchanged: 'Nothing changed since the last import, so nothing was re-read.' }[d.outcome];
      await Promise.all([loadSheets(sheet), loadForecast()]); notice(msg, 'success');
    } catch (e) { notice(e.message || 'Import failed', 'danger'); }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    showJobTab((location.hash || '').slice(1) || 'overview', false);
    document.getElementById('sheetPick').addEventListener('change', (ev) => showSheet(ev.target.value));
    try {
      [costCodes] = await Promise.all([authenticatedFetch('/pm/cost-codes'), loadJob()]);
      await Promise.all([loadSheets(), loadForecast(), loadSubmittals(), loadWo(), loadDrawings()]);
    } catch (e) { console.error(e); notice(e.message || 'Could not load this job', 'danger'); }
  });
</script>
@endsection
