@extends('layouts.app')

@section('title', 'Fabrication Documents')

@section('styles')
<style>
/* ── Filter bar ───────────────────────────────────────────────── */
.fab-seg .btn { font-size: .8125rem; }
.fab-seg .btn .fab-nav-count { margin-left: 6px; }
.fab-nav-count {
  font-size: 0.7rem; color: var(--tblr-secondary-color);
  background: var(--tblr-bg-surface-secondary, rgba(0,0,0,.05));
  padding: 1px 7px; border-radius: var(--tblr-border-radius-pill);
}
.btn.active .fab-nav-count { background: color-mix(in oklab, currentColor 12%, transparent); color: inherit; }

/* ── Layout ───────────────────────────────────────────────────── */
.fab-preview { position: sticky; top: calc(var(--fd-nav-offset, 0px) + 1rem); max-height: calc(100vh - var(--fd-nav-offset, 0px) - 2rem); display: flex; flex-direction: column; }
.fab-preview .card-body { overflow-y: auto; }
.fab-viewer { width: 100%; height: 360px; border: 1px solid var(--tblr-border-color); border-radius: var(--tblr-border-radius); background: var(--tblr-bg-surface-secondary); }
img.fab-viewer { object-fit: contain; height: auto; max-height: 420px; }
.fab-viewer-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 160px; color: var(--tblr-secondary-color); }
.fab-preview.drag-over { outline: 2px dashed var(--tblr-primary); outline-offset: -2px; }

/* ── List ─────────────────────────────────────────────────────── */
.fab-tbl { width: 100%; border-collapse: collapse; font-size: .875rem; }
.fab-tbl thead th {
  position: sticky; top: 0; background: var(--tblr-card-bg); z-index: 1;
  font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 600;
  color: var(--tblr-secondary-color); padding: 8px 10px; border-bottom: 1px solid var(--tblr-border-color);
  white-space: nowrap; cursor: pointer; user-select: none;
}
.fab-tbl thead th.nosort { cursor: default; }
.fab-tbl thead th.sort-active { color: var(--tblr-primary); }
.fab-tbl tbody tr { cursor: pointer; border-bottom: 1px solid var(--tblr-border-color-translucent, var(--tblr-border-color)); }
.fab-tbl tbody tr:hover { background: color-mix(in oklab, var(--tblr-primary) 4%, transparent); }
.fab-tbl tbody tr.current { background: var(--tblr-primary-lt); box-shadow: inset 3px 0 0 var(--tblr-primary); }
.fab-tbl td { padding: 7px 10px; vertical-align: middle; }
.fab-tbl .sub { font-size: .75rem; color: var(--tblr-secondary-color); }
.fab-tbl .chk { width: 34px; }
.fab-scroll { max-height: calc(100vh - 330px); min-height: 300px; overflow-y: auto; }

/* ── Cards ────────────────────────────────────────────────────── */
#fab-docgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; align-content: start; padding: 12px; }
.fab-doc-card {
  background: var(--tblr-card-bg); border: 1px solid var(--tblr-border-color); border-radius: var(--tblr-border-radius-lg);
  padding: 12px; cursor: pointer; display: flex; flex-direction: column; gap: 6px; position: relative;
  transition: border-color .15s, box-shadow .15s;
}
.fab-doc-card:hover { border-color: var(--tblr-primary); }
.fab-doc-card.current { border-color: var(--tblr-primary); background: var(--tblr-primary-lt); }
.fab-doc-card .form-check-input { position: absolute; top: 10px; right: 10px; }
.fab-doc-title { font-size: .875rem; font-weight: 500; line-height: 1.35; padding-right: 22px; }
.fab-doc-meta { font-size: .75rem; color: var(--tblr-secondary-color); }

/* ── Tags ─────────────────────────────────────────────────────── */
.fab-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.fab-tag {
  font-size: 0.7rem;
  padding: 2px 8px;
  border-radius: var(--tblr-border-radius-pill);
  background: var(--tblr-bg-surface-secondary, rgba(0,0,0,.05));
  color: var(--tblr-secondary-color);
  border: var(--tblr-border-width) solid var(--tblr-border-color);
}

/* ── View toggle ──────────────────────────────────────────────── */

.fab-tag.clickable { cursor: pointer; }
.fab-tag.clickable:hover { background: var(--tblr-primary-lt); color: var(--tblr-primary); }

/* ── Selection bar / drop overlay ─────────────────────────────── */
#fab-sel-bar { display: none; align-items: center; gap: 8px; padding: 8px 12px; background: var(--tblr-primary-lt); border-radius: var(--tblr-border-radius); margin-bottom: 12px; }
#fab-sel-bar.visible { display: flex; }
#fab-drop-overlay {
  display: none; position: fixed; inset: 0; z-index: 2000; background: color-mix(in oklab, var(--tblr-primary) 14%, transparent);
  backdrop-filter: blur(2px); align-items: center; justify-content: center; pointer-events: none;
}
#fab-drop-overlay.visible { display: flex; }
#fab-drop-overlay > div { background: var(--tblr-card-bg); border: 2px dashed var(--tblr-primary); border-radius: 16px; padding: 36px 56px; text-align: center; font-size: 1.1rem; }

/* ── Form: upload, tag input, file pill ───────────────────────── */
.fab-upload-zone {
  border: 1px dashed var(--tblr-border-color);
  border-radius: var(--tblr-border-radius);
  padding: 18px;
  text-align: center;
  cursor: pointer;
  transition: border-color .15s, background .15s;
  position: relative;
}
.fab-upload-zone:hover, .fab-upload-zone.drag-over {
  border-color: var(--tblr-primary);
  background: var(--tblr-primary-lt);
}
.fab-upload-zone input[type=file] {
  position: absolute; inset: 0;
  opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.fab-upload-preview {
  display: flex; align-items: center; gap: 10px;
  background: var(--tblr-bg-surface-secondary, rgba(0,0,0,.04));
  border: var(--tblr-border-width) solid var(--tblr-border-color);
  border-radius: var(--tblr-border-radius);
  padding: 10px 12px;
}
.fab-upload-preview-name {
  font-size: 0.875rem; flex: 1;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.fab-upload-preview-size { font-size: 0.75rem; color: var(--tblr-secondary-color); flex-shrink: 0; }
.fab-upload-clear {
  background: none; border: none; cursor: pointer;
  color: var(--tblr-secondary-color); font-size: 18px; line-height: 1;
}
.fab-upload-clear:hover { color: var(--tblr-danger); }

/* ── Tag input ────────────────────────────────────────────────── */
.fab-tag-wrap {
  display: flex; flex-wrap: wrap; gap: 5px;
  border: var(--tblr-border-width) solid var(--tblr-border-color);
  border-radius: var(--tblr-border-radius);
  padding: 6px 8px;
  background: var(--tblr-form-control-bg, var(--tblr-card-bg));
  cursor: text; min-height: 36px; align-items: center;
}
.fab-tag-wrap:focus-within { border-color: var(--tblr-primary); box-shadow: 0 0 0 .2rem color-mix(in oklab, var(--tblr-primary) 10%, transparent); }
.fab-tag-pill {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 0.7rem; padding: 2px 7px; border-radius: var(--tblr-border-radius-pill);
  background: var(--tblr-primary-lt);
  color: var(--tblr-primary);
  border: var(--tblr-border-width) solid color-mix(in oklab, var(--tblr-primary) 30%, transparent);
  white-space: nowrap;
}
.fab-tag-pill-remove {
  background: none; border: none; cursor: pointer;
  color: var(--tblr-primary); font-size: 13px; padding: 0; line-height: 1;
  display: flex; align-items: center; opacity: .7;
}
.fab-tag-pill-remove:hover { opacity: 1; }
.fab-tag-raw-input {
  border: none; outline: none; font-size: 0.875rem;
  background: transparent; color: var(--tblr-body-color);
  font-family: inherit; min-width: 90px; flex: 1; padding: 1px 2px;
}
.fab-tag-raw-input::placeholder { color: var(--tblr-secondary-color); }

/* ── Detail modal ─────────────────────────────────────────────── */
.fab-file-pill {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--tblr-bg-surface-secondary, rgba(0,0,0,.04));
  border: var(--tblr-border-width) solid var(--tblr-border-color);
  border-radius: var(--tblr-border-radius);
  padding: 9px 13px;
  font-size: 0.875rem; color: var(--tblr-body-color);
  text-decoration: none; max-width: 100%; overflow: hidden;
}
.fab-file-pill-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--tblr-primary); }
.fab-file-pill-size { font-size: 0.75rem; color: var(--tblr-secondary-color); flex-shrink: 0; }
</style>
@endsection

@section('content')
<div class="page-wrapper">
<div class="container-xl">

  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title"><i class="ti ti-drill me-2"></i>Fabrication Documents</h2>
        <div class="text-muted mt-1">Hardware templates, installation instructions and maintenance guides
          <span class="d-none d-md-inline">· drop files anywhere to add them</span></div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <button class="btn btn-primary" id="fab-add-btn" data-permission="fabrication.create">
          <i class="ti ti-plus me-1"></i>Add Document
        </button>
      </div>
    </div>
  </div>

  <div class="page-body">
    <!-- Filter bar -->
    <div class="card mb-3">
      <div class="card-body p-2">
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <div class="input-icon flex-fill" style="min-width:200px">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" id="fab-search" placeholder="Search title, hardware, manufacturer, tags, notes…" autocomplete="off">
          </div>
          <div class="btn-group fab-seg" id="fab-type-seg">
            <button type="button" class="btn active" data-filter-type="all">All <span class="fab-nav-count" id="fab-cnt-all">0</span></button>
            <button type="button" class="btn" data-filter-type="fabrication">Fabrication <span class="fab-nav-count" id="fab-cnt-fab">0</span></button>
            <button type="button" class="btn" data-filter-type="installation">Installation <span class="fab-nav-count" id="fab-cnt-install">0</span></button>
            <button type="button" class="btn" data-filter-type="maintenance">Maintenance <span class="fab-nav-count" id="fab-cnt-maint">0</span></button>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
          <select class="form-select form-select-sm" id="fab-hw-filter" style="width:auto"></select>
          <select class="form-select form-select-sm" id="fab-mfr-filter" style="width:auto"></select>
          <select class="form-select form-select-sm" id="fab-tag-filter" style="width:auto"></select>
          <button class="btn btn-sm btn-link" id="fab-clear-filters" style="display:none"><i class="ti ti-x me-1"></i>Clear filters</button>
          <span class="text-muted small ms-auto" id="fab-result-count"></span>
          <div class="btn-group">
            <button class="btn btn-sm btn-icon active" id="fab-view-table-btn" title="List"><i class="ti ti-list"></i></button>
            <button class="btn btn-sm btn-icon" id="fab-view-grid-btn" title="Cards"><i class="ti ti-layout-grid"></i></button>
          </div>
        </div>
      </div>
    </div>

    <div id="fab-sel-bar">
      <span id="fab-sel-label" class="flex-fill text-primary fw-medium" style="font-size:.875rem;"></span>
      <button class="btn btn-sm btn-ghost-secondary" id="fab-sel-clear">Clear</button>
      <button class="btn btn-sm btn-primary" id="fab-export-btn"><i class="ti ti-file-export me-1"></i>Merge selected to PDF</button>
    </div>

    <div class="row g-3">
      <!-- List -->
      <div class="col-12 col-xl-7">
        <div class="card">
          <div id="fab-loading" class="text-center py-5 text-muted">
            <div class="spinner-border spinner-border-sm me-2"></div>Loading documents…
          </div>
          <div id="fab-doctable" class="fab-scroll" style="display:none"></div>
          <div id="fab-docgrid" class="fab-scroll" style="display:none"></div>
        </div>
      </div>

      <!-- Preview -->
      <div class="col-12 col-xl-5" id="fab-preview-col">
        <div class="card fab-preview" id="fab-preview">
          <div class="card-body d-flex flex-column align-items-center justify-content-center text-muted" id="fab-pv-empty" style="min-height:300px">
            <i class="ti ti-file-search" style="font-size:3rem"></i>
            <p class="mt-2 mb-0">Select a document to preview it</p>
            <p class="small mb-0">Drop a file here to add a new revision to the selected document</p>
          </div>
          <div id="fab-pv" style="display:none" class="d-flex flex-column overflow-hidden">
            <div class="card-header flex-column align-items-stretch gap-2">
              <div class="d-flex align-items-start gap-2">
                <div class="flex-fill min-w-0">
                  <div id="fab-pv-badges" class="mb-1 d-flex flex-wrap gap-1"></div>
                  <h3 class="card-title mb-0" id="fab-pv-title"></h3>
                  <div class="text-muted small" id="fab-pv-sub"></div>
                </div>
                <button class="btn-close" id="fab-pv-close" aria-label="Close preview"></button>
              </div>
              <div class="btn-list">
                <a class="btn btn-sm btn-primary" id="fab-pv-open" target="_blank" rel="noopener"><i class="ti ti-external-link me-1"></i>Open</a>
                <a class="btn btn-sm" id="fab-pv-download" download><i class="ti ti-download me-1"></i>Download</a>
                <button class="btn btn-sm" id="fab-pv-edit" data-permission="fabrication.edit"><i class="ti ti-edit me-1"></i>Edit</button>
                <button class="btn btn-sm" id="fab-pv-newrev" data-permission="fabrication.edit"><i class="ti ti-upload me-1"></i>New revision</button>
                <button class="btn btn-sm btn-outline-danger ms-auto" id="fab-pv-delete" data-permission="fabrication.delete"><i class="ti ti-trash"></i></button>
              </div>
            </div>
            <div class="card-body">
              <div id="fab-pv-viewer" class="mb-3"></div>
              <div id="fab-pv-hw" class="mb-3"></div>
              <div id="fab-pv-tags-wrap" class="mb-3" style="display:none"><div class="subheader mb-1">Tags</div><div class="fab-tags" id="fab-pv-tags"></div></div>
              <div id="fab-pv-notes-wrap" class="mb-3" style="display:none"><div class="subheader mb-1">Notes</div>
                <div class="text-secondary" style="white-space:pre-wrap;font-size:.875rem;line-height:1.6" id="fab-pv-notes"></div></div>
              <div id="fab-pv-revs-wrap" style="display:none">
                <div class="subheader mb-1">Revisions</div>
                <div class="list-group list-group-flush" id="fab-pv-revs"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="fab-drop-overlay"><div><i class="ti ti-cloud-upload d-block mb-2" style="font-size:2.5rem"></i>Drop files to add them as documents</div></div>

<!-- ── Add / Edit modal ───────────────────────────────────────────── -->
<div class="modal modal-blur fade" id="fab-form-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="fab-modal-title">Add document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <label class="form-label required">Document title</label>
            <input type="text" class="form-control" id="fab-f-title" placeholder="e.g. Hager 1251 Prep Template">
          </div>
          <div class="col-md-4">
            <label class="form-label required">Document type</label>
            <select class="form-select" id="fab-f-type">
              <option value="fabrication">Fabrication template</option>
              <option value="installation">Installation instruction</option>
              <option value="maintenance">Maintenance guide</option>
            </select>
          </div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label">Hardware type</label>
            <input type="text" class="form-control" id="fab-f-hwtype" placeholder="e.g. Closer, Hinge" list="fab-hw-dl">
            <datalist id="fab-hw-dl"></datalist>
          </div>
          <div class="col-6">
            <label class="form-label">Manufacturer</label>
            <input type="text" class="form-control" id="fab-f-mfr" placeholder="e.g. LCN, Hager" list="fab-mfr-dl">
            <datalist id="fab-mfr-dl"></datalist>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" id="fab-f-file-label">File</label>
          <div class="fab-upload-zone" id="fab-upload-zone">
            <input type="file" id="fab-f-file" accept=".pdf,.png,.jpg,.jpeg,.dwg,.dxf,.doc,.docx">
            <div class="text-muted" style="pointer-events:none;font-size:.875rem">
              Drop a file here or <span class="text-primary">browse</span><br>
              <span style="font-size:.75rem">PDF, image, DWG, DXF, DOC — max 50 MB</span>
            </div>
          </div>
          <div id="fab-upload-preview" class="fab-upload-preview mt-2" style="display:none"></div>
          <div id="fab-keep-file-note" class="form-text" style="display:none"></div>
        </div>
        <div class="mb-3" id="fab-rev-row">
          <label class="form-label">Revision label</label>
          <input type="text" class="form-control" id="fab-f-revision" maxlength="50" placeholder="e.g. Rev C — leave blank for the next v-number">
          <div class="form-text" id="fab-rev-help"></div>
        </div>
        <div class="mb-3">
          <label class="form-label">Tags <span class="text-muted">(Enter or comma to add)</span></label>
          <div class="fab-tag-wrap" id="fab-tag-wrap" onclick="document.getElementById('fab-tag-raw-input').focus()">
            <input type="text" class="fab-tag-raw-input" id="fab-tag-raw-input" placeholder="Add a tag…">
          </div>
        </div>
        <div class="mb-0">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="fab-f-notes" rows="3" placeholder="Installation notes, special instructions…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger me-auto" id="fab-form-delete-btn" style="display:none"><i class="ti ti-trash me-1"></i>Delete</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="fab-modal-save"><i class="ti ti-device-floppy me-1"></i>Save document</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Batch upload modal (dropped files) ─────────────────────────── -->
<div class="modal modal-blur fade" id="fab-batch-modal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add <span id="fab-batch-count"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-md-4"><label class="form-label">Hardware type (all)</label>
            <input type="text" class="form-control form-control-sm" id="fab-b-hwtype" list="fab-hw-dl"></div>
          <div class="col-md-4"><label class="form-label">Manufacturer (all)</label>
            <input type="text" class="form-control form-control-sm" id="fab-b-mfr" list="fab-mfr-dl"></div>
          <div class="col-md-4"><label class="form-label">Tags (all, comma separated)</label>
            <input type="text" class="form-control form-control-sm" id="fab-b-tags"></div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter mb-0">
            <thead><tr><th>File</th><th>Title</th><th style="width:170px">Type</th><th style="width:90px"></th></tr></thead>
            <tbody id="fab-batch-rows"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="fab-batch-cancel">Cancel</button>
        <button class="btn btn-primary" id="fab-batch-save"><i class="ti ti-upload me-1"></i>Upload all</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Export modal ───────────────────────────────────────────────── -->
<div class="modal modal-blur fade" id="fab-export-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">Export merged PDF</h5>
          <div class="text-muted small">Drag to reorder before exporting</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="fab-export-list" style="min-height:80px;max-height:50vh;overflow-y:auto;"></div>
      <div class="modal-footer">
        <span class="me-auto text-muted small" id="fab-export-footer-note"></span>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="fab-export-confirm-btn"><i class="ti ti-file-zip me-1"></i>Merge &amp; download</button>
      </div>
    </div>
  </div>
</div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
<script>
// ── State ────────────────────────────────────────────────────────────────────
let fabDocs = [];
let fabEditingId  = null;
let fabActiveTags = [];
let fabPendingFile = null;
let fabFilterType = 'all';
let fabFilterHw   = '';
let fabFilterMfr  = '';
let fabFilterTag  = '';
let fabCurrentId  = null;       // document shown in the preview pane
let fabCurrent    = null;       // full record (with revisions) for the preview pane
let fabSelectedIds = new Set(); // numeric ids ticked for merged export
let fabExportOrder = [];
let fabDragSrcIdx  = null;
let fabViewMode    = 'table';   // 'table' | 'grid'
let fabSortCol     = 'created_at';
let fabSortDir     = 'desc';
let fabBatchFiles  = [];
let fabRevisionTarget = false;  // form opened to add a revision

try { if (localStorage.getItem('fab_view') === 'grid') fabViewMode = 'grid'; } catch (e) {}

const FAB_TYPE_LABELS = { fabrication: 'Fabrication template', installation: 'Installation instruction', maintenance: 'Maintenance guide' };
const FAB_TYPE_SHORT  = { fabrication: 'Fabrication', installation: 'Installation', maintenance: 'Maintenance' };
const FAB_TYPE_BADGE  = {
  fabrication:  'badge bg-blue-lt text-blue',
  installation: 'badge bg-green-lt text-green',
  maintenance:  'badge bg-yellow-lt text-yellow-fg',
};

// ── Utilities ─────────────────────────────────────────────────────────────────
function fabEsc(s){ return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fabFmtDate(iso){ return iso ? new Date(iso).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—'; }
function fabFmtSize(b){ if(!b)return''; if(b<1024)return b+'B'; if(b<1048576)return(b/1024).toFixed(1)+'KB'; return(b/1048576).toFixed(1)+'MB'; }
function fabExt(name){ return (name||'').split('.').pop().toLowerCase(); }
function fabFileIcon(name){
  if(!name)return'📄';
  const ext=fabExt(name);
  if(ext==='pdf')return'📕';
  if(['png','jpg','jpeg','gif','webp'].includes(ext))return'🖼';
  if(['dwg','dxf'].includes(ext))return'📐';
  if(['doc','docx'].includes(ext))return'📝';
  return'📄';
}
function fabIsPdf(name){ return fabExt(name)==='pdf'; }
function fabIsImage(name){ return ['png','jpg','jpeg','gif','webp'].includes(fabExt(name)); }
function fabGetUnique(field){ return [...new Set(fabDocs.map(d=>d[field]).filter(Boolean))].sort((a,b)=>a.localeCompare(b)); }
function fabTitleFromFile(name){ return name.replace(/\.[^.]+$/,'').replace(/[_]+/g,' ').replace(/\s+/g,' ').trim(); }
function fabShowModal(id){ showModal(document.getElementById(id)); }
function fabHideModal(id){ hideModal(document.getElementById(id)); }
function fabPerms(){ if (typeof applyActionPermissions === 'function') applyActionPermissions(); }

// ── API helpers ────────────────────────────────────────────────────────────────
async function fabApiJson(endpoint, options={}){
  const response = await apiCall(endpoint, options);
  if(!response.ok){
    if(response.status===401){ return; }
    const err = await response.json().catch(()=>({message:'Request failed'}));
    throw new Error(err.message||`HTTP ${response.status}`);
  }
  return response.json();
}

async function fabApiMultipart(endpoint, formData, method='POST'){
  const headers = {
    'Accept': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
  };
  const response = await fetch(`${API_BASE}${endpoint}`, { method, headers, body: formData });
  if(!response.ok){
    if(response.status===401){ showLogin(); return null; }
    const err = await response.json().catch(()=>({message:'Request failed'}));
    throw new Error(err.message || Object.values(err.errors||{}).flat().join(' ') || `HTTP ${response.status}`);
  }
  return response.json();
}

// ── Load ──────────────────────────────────────────────────────────────────────
async function fabLoadAll(){
  try {
    const data = await fabApiJson('/fabrication-documents?per_page=all');
    fabDocs = data?.data ?? [];
    fabRenderAll();
    // Deep link from dashboard widgets: ?doc=<id>
    const deepDoc = parseInt(new URLSearchParams(location.search).get('doc'), 10);
    if (deepDoc && fabDocs.some(d => d.id === deepDoc)) fabOpenPreview(deepDoc);
  } catch(e){
    console.error('Failed to load documents:', e);
    showNotification('Failed to load documents', 'danger');
  } finally {
    document.getElementById('fab-loading').style.display = 'none';
    fabRenderAll();
  }
}

// ── Filters ───────────────────────────────────────────────────────────────────
function fabTagCounts(){
  const m = new Map();
  fabDocs.forEach(d => (d.tags||[]).forEach(t => m.set(t, (m.get(t)||0)+1)));
  return [...m.entries()].sort((a,b)=> b[1]-a[1] || a[0].localeCompare(b[0]));
}

function fabFillSelect(id, label, values, current, fmt){
  const el = document.getElementById(id);
  const opts = [`<option value="">${label}</option>`].concat(values.map(v => {
    const [val, text] = Array.isArray(v) ? v : [v, v];
    return `<option value="${fabEsc(val)}"${val===current?' selected':''}>${fabEsc(fmt ? fmt(v) : text)}</option>`;
  }));
  el.innerHTML = opts.join('');
}

function fabUpdateFilters(){
  const c = {all:fabDocs.length, fabrication:0, installation:0, maintenance:0};
  fabDocs.forEach(d=>{ if(c[d.type]!==undefined) c[d.type]++; });
  document.getElementById('fab-cnt-all').textContent     = c.all;
  document.getElementById('fab-cnt-fab').textContent     = c.fabrication;
  document.getElementById('fab-cnt-install').textContent = c.installation;
  document.getElementById('fab-cnt-maint').textContent   = c.maintenance;
  document.querySelectorAll('[data-filter-type]').forEach(n=>n.classList.toggle('active', n.dataset.filterType===fabFilterType));

  fabFillSelect('fab-hw-filter',  'All hardware',      fabGetUnique('hwtype'),       fabFilterHw);
  fabFillSelect('fab-mfr-filter', 'All manufacturers', fabGetUnique('manufacturer'), fabFilterMfr);
  fabFillSelect('fab-tag-filter', 'All tags', fabTagCounts().map(([t,n])=>[t,`${t} (${n})`]), fabFilterTag);

  document.getElementById('fab-hw-dl').innerHTML  = fabGetUnique('hwtype').map(v=>`<option value="${fabEsc(v)}">`).join('');
  document.getElementById('fab-mfr-dl').innerHTML = fabGetUnique('manufacturer').map(v=>`<option value="${fabEsc(v)}">`).join('');

  const any = fabFilterType!=='all' || fabFilterHw || fabFilterMfr || fabFilterTag || document.getElementById('fab-search').value.trim();
  document.getElementById('fab-clear-filters').style.display = any ? '' : 'none';
}

function fabClearFilters(){
  fabFilterType='all'; fabFilterHw=''; fabFilterMfr=''; fabFilterTag='';
  document.getElementById('fab-search').value='';
  fabRenderAll();
}

function fabFiltered(){
  const q = document.getElementById('fab-search').value.trim().toLowerCase();
  const terms = q.split(/\s+/).filter(Boolean);
  const out = fabDocs.filter(d=>{
    if(fabFilterType!=='all' && d.type!==fabFilterType) return false;
    if(fabFilterHw  && d.hwtype!==fabFilterHw)          return false;
    if(fabFilterMfr && d.manufacturer!==fabFilterMfr)   return false;
    if(fabFilterTag && !(d.tags||[]).includes(fabFilterTag)) return false;
    if(terms.length){
      const hay = `${d.title} ${d.hwtype||''} ${d.manufacturer||''} ${d.revision||''} ${(d.tags||[]).join(' ')} ${d.notes||''} ${d.file?.name||''}`.toLowerCase();
      if(!terms.every(t=>hay.includes(t))) return false;
    }
    return true;
  });
  const dir = fabSortDir==='asc' ? 1 : -1;
  out.sort((a,b)=>{
    if(fabSortCol==='created_at'||fabSortCol==='updated_at') return dir*(new Date(a[fabSortCol]||0)-new Date(b[fabSortCol]||0));
    if(fabSortCol==='file') return dir*((a.file?.name||'').localeCompare(b.file?.name||''));
    return dir*String(a[fabSortCol]||'').localeCompare(String(b[fabSortCol]||''), undefined, {numeric:true});
  });
  return out;
}

// ── Render list ───────────────────────────────────────────────────────────────
const FAB_EMPTY = `<div class="text-center text-muted py-5">
  <i class="ti ti-file-off mb-2" style="font-size:2rem;display:block"></i>
  No documents match. Drop a file here or use “Add Document”.
</div>`;

function fabRenderAll(){
  fabUpdateFilters();
  const list = fabFiltered();
  document.getElementById('fab-result-count').textContent =
    list.length === fabDocs.length ? `${fabDocs.length} documents` : `${list.length} of ${fabDocs.length} documents`;
  document.getElementById('fab-view-table-btn').classList.toggle('active', fabViewMode==='table');
  document.getElementById('fab-view-grid-btn').classList.toggle('active', fabViewMode==='grid');
  const tbl = document.getElementById('fab-doctable'), grid = document.getElementById('fab-docgrid');
  if(document.getElementById('fab-loading').style.display !== 'none') return;
  tbl.style.display  = fabViewMode==='table' ? '' : 'none';
  grid.style.display = fabViewMode==='grid'  ? '' : 'none';
  fabViewMode==='table' ? fabRenderTable(list) : fabRenderGrid(list);
  fabUpdateSelBar();
}

function fabTagsHtml(tags, max=3){
  if(!tags||!tags.length) return '';
  const shown = tags.slice(0,max).map(t=>`<span class="fab-tag">${fabEsc(t)}</span>`).join('');
  return shown + (tags.length>max ? `<span class="fab-tag">+${tags.length-max}</span>` : '');
}

function fabRenderTable(list){
  const wrap = document.getElementById('fab-doctable');
  if(!list.length){ wrap.innerHTML = FAB_EMPTY; return; }
  const cols = [
    {key:'title', label:'Document'}, {key:'type', label:'Type'}, {key:'file', label:'File'},
    {key:'tags', label:'Tags', nosort:true}, {key:'updated_at', label:'Updated'},
  ];
  const allSel = list.length && list.every(d=>fabSelectedIds.has(d.id));
  const arrow = fabSortDir==='asc'?'↑':'↓';
  const thead = `<thead><tr><th class="chk nosort"><input type="checkbox" class="form-check-input" id="fab-sel-all" ${allSel?'checked':''} title="Select all shown"></th>${
    cols.map(c=>`<th data-col="${c.key}" class="${c.nosort?'nosort':''}${fabSortCol===c.key?' sort-active':''}">${c.label}${fabSortCol===c.key?` ${arrow}`:''}</th>`).join('')}</tr></thead>`;
  const rows = list.map(d=>`
    <tr data-id="${d.id}" class="${d.id===fabCurrentId?'current':''}">
      <td class="chk"><input type="checkbox" class="form-check-input fab-row-chk" ${fabSelectedIds.has(d.id)?'checked':''}></td>
      <td style="max-width:280px">
        <div class="fw-medium text-truncate">${fabEsc(d.title)}${d.revision?` <span class="badge bg-secondary-lt ms-1">${fabEsc(d.revision)}</span>`:''}</div>
        <div class="sub text-truncate">${[d.hwtype,d.manufacturer].filter(Boolean).map(fabEsc).join(' · ') || '&nbsp;'}</div>
      </td>
      <td><span class="${FAB_TYPE_BADGE[d.type]}">${FAB_TYPE_SHORT[d.type]}</span></td>
      <td class="text-nowrap">${d.file?`${fabFileIcon(d.file.name)} <span class="sub">${fabFmtSize(d.file.size)}</span>`:'<span class="text-muted">—</span>'}</td>
      <td><div class="fab-tags">${fabTagsHtml(d.tags)||'<span class="text-muted">—</span>'}</div></td>
      <td class="sub text-nowrap">${fabFmtDate(d.updated_at||d.created_at)}</td>
    </tr>`).join('');
  wrap.innerHTML = `<table class="fab-tbl">${thead}<tbody>${rows}</tbody></table>`;
}

function fabRenderGrid(list){
  const grid = document.getElementById('fab-docgrid');
  if(!list.length){ grid.innerHTML = `<div style="grid-column:1/-1">${FAB_EMPTY}</div>`; return; }
  grid.innerHTML = list.map(d=>`
    <div class="fab-doc-card${d.id===fabCurrentId?' current':''}" data-id="${d.id}">
      <input type="checkbox" class="form-check-input fab-row-chk" ${fabSelectedIds.has(d.id)?'checked':''}>
      <div><span class="${FAB_TYPE_BADGE[d.type]}">${FAB_TYPE_SHORT[d.type]}</span>${d.revision?` <span class="badge bg-secondary-lt">${fabEsc(d.revision)}</span>`:''}</div>
      <div class="fab-doc-title">${fabEsc(d.title)}</div>
      <div class="fab-doc-meta">${[d.hwtype,d.manufacturer].filter(Boolean).map(fabEsc).join(' · ') || '&nbsp;'}</div>
      ${d.file?`<div class="fab-doc-meta">${fabFileIcon(d.file.name)} ${fabEsc(d.file.name)}</div>`:''}
      <div class="fab-tags">${fabTagsHtml(d.tags,2)}</div>
    </div>`).join('');
}

// ── Selection (for merged export) ─────────────────────────────────────────────
function fabToggleSelect(id, on){
  if(on===undefined) on = !fabSelectedIds.has(id);
  on ? fabSelectedIds.add(id) : fabSelectedIds.delete(id);
  fabUpdateSelBar();
}
function fabClearSelection(){ fabSelectedIds.clear(); fabRenderAll(); }
function fabUpdateSelBar(){
  const bar = document.getElementById('fab-sel-bar'), n = fabSelectedIds.size;
  bar.classList.toggle('visible', n>0);
  if(n) document.getElementById('fab-sel-label').textContent = `${n} document${n!==1?'s':''} selected`;
  const all = document.getElementById('fab-sel-all');
  if(all){ const l = fabFiltered(); all.checked = l.length>0 && l.every(d=>fabSelectedIds.has(d.id)); }
}

// ── Preview pane ──────────────────────────────────────────────────────────────
function fabClosePreview(){
  fabCurrentId = null; fabCurrent = null;
  document.getElementById('fab-pv').style.display = 'none';
  document.getElementById('fab-pv-empty').style.display = '';
  fabRenderAll();
}

async function fabOpenPreview(id){
  const d = fabDocs.find(x=>x.id===id); if(!d) return;
  fabCurrentId = id;
  fabCurrent = d;
  fabRenderPreview();
  fabRenderAll();
  if (window.matchMedia('(max-width: 1199px)').matches) document.getElementById('fab-preview-col').scrollIntoView({behavior:'smooth', block:'start'});
  // revisions come with the single-document endpoint
  if ((d.revisions_count||0) > 0) {
    try {
      const full = await fabApiJson(`/fabrication-documents/${id}`);
      if (full && fabCurrentId === id) { fabCurrent = full; fabRenderPreview(); }
    } catch (e) { console.error(e); }
  }
}

function fabRenderPreview(){
  const d = fabCurrent; if(!d) return;
  document.getElementById('fab-pv-empty').style.display = 'none';
  document.getElementById('fab-pv').style.display = '';
  document.getElementById('fab-pv-title').textContent = d.title;
  document.getElementById('fab-pv-badges').innerHTML =
    `<span class="${FAB_TYPE_BADGE[d.type]}">${FAB_TYPE_LABELS[d.type]}</span>` +
    (d.revision ? `<span class="badge bg-secondary-lt">${fabEsc(d.revision)}</span>` : '');
  const updated = d.updated_at && d.updated_at!==d.created_at ? ` · updated ${fabFmtDate(d.updated_at)}` : '';
  document.getElementById('fab-pv-sub').textContent = `Added ${fabFmtDate(d.created_at)}${updated}`;

  const open = document.getElementById('fab-pv-open'), dl = document.getElementById('fab-pv-download');
  [open, dl].forEach(a => { a.style.display = d.file?.url ? '' : 'none'; });
  if (d.file?.url) { open.href = d.file.url; dl.href = d.file.url; dl.setAttribute('download', d.file.name); }

  const v = document.getElementById('fab-pv-viewer');
  if (d.file?.url && fabIsPdf(d.file.name)) {
    v.innerHTML = `<iframe class="fab-viewer" src="${fabEsc(d.file.url)}#toolbar=0&navpanes=0" title="${fabEsc(d.file.name)}"></iframe>`;
  } else if (d.file?.url && fabIsImage(d.file.name)) {
    v.innerHTML = `<img class="fab-viewer" src="${fabEsc(d.file.url)}" alt="${fabEsc(d.title)}">`;
  } else if (d.file) {
    v.innerHTML = `<div class="fab-viewer-empty"><span style="font-size:2.5rem">${fabFileIcon(d.file.name)}</span><div class="mt-1">${fabEsc(d.file.name)}</div><div class="small">No inline preview for this file type — use Download</div></div>`;
  } else {
    v.innerHTML = `<div class="fab-viewer-empty"><i class="ti ti-file-off" style="font-size:2rem"></i><div class="mt-1">No file attached</div></div>`;
  }
  const fileLine = d.file ? `<div class="small text-muted mt-1">${fabEsc(d.file.name)} · ${fabFmtSize(d.file.size)}</div>` : '';
  document.getElementById('fab-pv-hw').innerHTML = fileLine +
    ((d.hwtype||d.manufacturer) ? `<div class="mt-2">${[
      d.hwtype && `<span class="me-3"><strong>Hardware:</strong> ${fabEsc(d.hwtype)}</span>`,
      d.manufacturer && `<span><strong>Manufacturer:</strong> ${fabEsc(d.manufacturer)}</span>`].filter(Boolean).join('')}</div>` : '');

  const tw = document.getElementById('fab-pv-tags-wrap');
  tw.style.display = d.tags?.length ? '' : 'none';
  document.getElementById('fab-pv-tags').innerHTML = (d.tags||[]).map(t=>`<span class="fab-tag clickable" data-tag="${fabEsc(t)}" title="Filter by this tag">${fabEsc(t)}</span>`).join('');
  const nw = document.getElementById('fab-pv-notes-wrap');
  nw.style.display = d.notes ? '' : 'none';
  document.getElementById('fab-pv-notes').textContent = d.notes || '';

  const revs = d.revisions || [];
  document.getElementById('fab-pv-revs-wrap').style.display = d.file || revs.length ? '' : 'none';
  document.getElementById('fab-pv-revs').innerHTML =
    (d.file ? `<div class="list-group-item px-0 d-flex align-items-center gap-2">
        <span class="badge bg-green-lt">Current</span><strong>${fabEsc(d.revision||'—')}</strong>
        <span class="text-muted small text-truncate flex-fill">${fabEsc(d.file.name)}</span></div>` : '') +
    revs.map(r=>`<div class="list-group-item px-0 d-flex align-items-center gap-2" data-rev="${r.id}">
        <strong>${fabEsc(r.revision||'—')}</strong>
        <span class="text-muted small text-truncate flex-fill" title="${fabEsc(r.file.name)}">${fabEsc(r.file.name)} · ${fabFmtSize(r.file.size)} · ${fabFmtDate(r.archived_at)}</span>
        <a class="btn btn-sm btn-icon btn-ghost-secondary" href="${fabEsc(r.file.url)}" target="_blank" rel="noopener" title="Open"><i class="ti ti-external-link"></i></a>
        <button class="btn btn-sm btn-ghost-primary" data-act="restore" data-permission="fabrication.edit" title="Make this the current file">Restore</button>
        <button class="btn btn-sm btn-icon btn-ghost-danger" data-act="del-rev" data-permission="fabrication.delete" title="Delete permanently"><i class="ti ti-trash"></i></button>
      </div>`).join('');
  fabPerms();
}

async function fabRevisionAction(act, revId){
  const d = fabCurrent; if(!d) return;
  const rev = (d.revisions||[]).find(r=>r.id===revId); if(!rev) return;
  try {
    let result;
    if (act==='restore') {
      if(!confirm(`Make ${rev.revision||'this revision'} the current file? The current file ${d.revision?`(${d.revision}) `:''}will be kept as a revision.`)) return;
      result = await fabApiJson(`/fabrication-documents/${d.id}/revisions/${revId}/restore`, {method:'POST'});
    } else {
      if(!confirm(`Permanently delete ${rev.revision||'this revision'} (${rev.file.name})?`)) return;
      result = await fabApiJson(`/fabrication-documents/${d.id}/revisions/${revId}`, {method:'DELETE'});
    }
    if(!result) return;
    fabDocs = fabDocs.map(x=>x.id===result.id ? result : x);
    fabCurrent = result;
    fabRenderPreview(); fabRenderAll();
    showNotification(act==='restore' ? 'Revision restored' : 'Revision deleted', 'success');
  } catch(e){ showNotification(e.message||'Action failed','danger'); }
}

// ── Form modal ────────────────────────────────────────────────────────────────
function fabOpenAdd(prefill={}){
  fabEditingId=null; fabActiveTags=[]; fabPendingFile=null; fabRevisionTarget=false;
  document.getElementById('fab-modal-title').textContent = 'Add document';
  ['fab-f-title','fab-f-hwtype','fab-f-mfr','fab-f-notes','fab-f-revision'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('fab-f-type').value = fabFilterType!=='all' ? fabFilterType : 'fabrication';
  document.getElementById('fab-f-file-label').textContent = 'File';
  document.getElementById('fab-rev-help').textContent = 'Optional. Defaults to v1.';
  document.getElementById('fab-form-delete-btn').style.display = 'none';
  document.getElementById('fab-keep-file-note').style.display  = 'none';
  fabRenderTags(); fabInitTagInput(); fabClearFilePreview();
  fabShowModal('fab-form-modal');
}

function fabOpenEdit(id, newRevision=false){
  const d = fabDocs.find(x=>x.id===id); if(!d) return;
  fabEditingId = id; fabActiveTags=[...(d.tags||[])]; fabPendingFile=null; fabRevisionTarget=newRevision;
  document.getElementById('fab-modal-title').textContent = newRevision ? `New revision — ${d.title}` : 'Edit document';
  document.getElementById('fab-f-title').value   = d.title;
  document.getElementById('fab-f-type').value    = d.type;
  document.getElementById('fab-f-hwtype').value  = d.hwtype||'';
  document.getElementById('fab-f-mfr').value     = d.manufacturer||'';
  document.getElementById('fab-f-notes').value   = d.notes||'';
  document.getElementById('fab-f-revision').value = newRevision ? '' : (d.revision||'');
  document.getElementById('fab-f-file-label').textContent = newRevision ? 'New file' : 'Replace file';
  document.getElementById('fab-rev-help').textContent = newRevision
    ? 'Label for the new file. Leave blank for the next v-number; the current file is kept as a revision.'
    : 'Renames the current revision. Uploading a new file keeps the old one as a revision.';
  document.getElementById('fab-form-delete-btn').style.display = '';
  const keepNote = document.getElementById('fab-keep-file-note');
  if(d.file){ keepNote.style.display=''; keepNote.textContent=`Current file: ${d.file.name}${d.revision?` (${d.revision})`:''}`; }
  else keepNote.style.display='none';
  fabRenderTags(); fabInitTagInput(); fabClearFilePreview();
  fabShowModal('fab-form-modal');
}

async function fabSaveDoc(){
  const title = document.getElementById('fab-f-title').value.trim();
  if(!title){ showNotification('Please enter a title', 'warning'); return; }
  if(fabRevisionTarget && !fabPendingFile){ showNotification('Choose the file for the new revision', 'warning'); return; }
  fabCommitTagInput();

  const btn = document.getElementById('fab-modal-save');
  btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
  try {
    const fd = new FormData();
    fd.append('title',        title);
    fd.append('type',         document.getElementById('fab-f-type').value);
    fd.append('revision',     document.getElementById('fab-f-revision').value.trim());
    fd.append('hwtype',       document.getElementById('fab-f-hwtype').value.trim());
    fd.append('manufacturer', document.getElementById('fab-f-mfr').value.trim());
    fd.append('notes',        document.getElementById('fab-f-notes').value.trim());
    fd.append('tags',         JSON.stringify(fabActiveTags));
    if(fabPendingFile) fd.append('file', fabPendingFile);

    let result;
    if(fabEditingId){
      fd.append('_method','PUT');
      result = await fabApiMultipart(`/fabrication-documents/${fabEditingId}`, fd);
    } else {
      result = await fabApiMultipart('/fabrication-documents', fd);
    }
    if(!result) return;

    const wasEditing = fabEditingId;
    fabDocs = wasEditing ? fabDocs.map(d=>d.id===wasEditing ? result : d) : [result, ...fabDocs];
    fabHideModal('fab-form-modal');
    fabCurrentId = result.id; fabCurrent = result;
    fabRenderPreview(); fabRenderAll();
    showNotification(wasEditing ? (fabRevisionTarget ? 'New revision saved' : 'Document updated') : 'Document added', 'success');
  } catch(e){
    showNotification(e.message||'Save failed','danger');
  } finally {
    btn.disabled=false; btn.innerHTML='<i class="ti ti-device-floppy me-1"></i>Save document';
  }
}

async function fabDeleteDoc(id){
  const d = fabDocs.find(x=>x.id===id);
  if(!confirm(`Delete “${d?.title||'this document'}”${d?.revisions_count?` and its ${d.revisions_count} older revision(s)`:''}? This cannot be undone.`)) return;
  try {
    await fabApiJson(`/fabrication-documents/${id}`, {method:'DELETE'});
    fabDocs = fabDocs.filter(x=>x.id!==id);
    fabSelectedIds.delete(id);
    fabHideModal('fab-form-modal');
    if (fabCurrentId===id) { fabClosePreview(); } else { fabRenderAll(); }
    showNotification('Document deleted','success');
  } catch(e){
    showNotification(e.message||'Delete failed','danger');
  }
}

// ── Drag & drop: new documents anywhere, new revision onto the preview pane ──
const FAB_BLOCKED_EXT = ['php','phtml','phar','html','htm','xhtml','svg','xml','js','mjs','sh','exe','bat'];

function fabOpenBatch(files){
  const ok = files.filter(f=>!FAB_BLOCKED_EXT.includes(fabExt(f.name)));
  if (ok.length < files.length) showNotification(`${files.length-ok.length} file(s) skipped (type not allowed)`, 'warning');
  if (!ok.length) return;
  if (ok.length === 1) {                       // single file: use the full form
    fabOpenAdd();
    fabHandleFileSelect(ok[0]);
    document.getElementById('fab-f-title').value = fabTitleFromFile(ok[0].name);
    return;
  }
  fabBatchFiles = ok.map(f=>({file:f, title:fabTitleFromFile(f.name), type: fabFilterType!=='all' ? fabFilterType : 'fabrication', status:''}));
  document.getElementById('fab-batch-count').textContent = `${ok.length} documents`;
  ['fab-b-hwtype','fab-b-mfr','fab-b-tags'].forEach(id=>document.getElementById(id).value='');
  fabRenderBatch();
  fabShowModal('fab-batch-modal');
}

function fabRenderBatch(){
  document.getElementById('fab-batch-rows').innerHTML = fabBatchFiles.map((b,i)=>`
    <tr data-i="${i}">
      <td class="text-nowrap small">${fabFileIcon(b.file.name)} ${fabEsc(b.file.name)}<div class="text-muted">${fabFmtSize(b.file.size)}</div></td>
      <td><input type="text" class="form-control form-control-sm" data-f="title" value="${fabEsc(b.title)}"></td>
      <td><select class="form-select form-select-sm" data-f="type">${Object.entries(FAB_TYPE_LABELS).map(([k,l])=>`<option value="${k}"${b.type===k?' selected':''}>${l}</option>`).join('')}</select></td>
      <td class="small" data-status>${b.status}</td>
    </tr>`).join('');
}

async function fabRunBatch(){
  const btn = document.getElementById('fab-batch-save');
  document.querySelectorAll('#fab-batch-rows tr').forEach(tr=>{
    const b = fabBatchFiles[+tr.dataset.i];
    b.title = tr.querySelector('[data-f=title]').value.trim() || fabTitleFromFile(b.file.name);
    b.type  = tr.querySelector('[data-f=type]').value;
  });
  const hw = document.getElementById('fab-b-hwtype').value.trim(), mfr = document.getElementById('fab-b-mfr').value.trim();
  const tags = JSON.stringify(document.getElementById('fab-b-tags').value.split(',').map(t=>t.trim()).filter(Boolean));
  btn.disabled = true;
  let failed = 0;
  for (const [i,b] of fabBatchFiles.entries()) {
    const cell = document.querySelector(`#fab-batch-rows tr[data-i="${i}"] [data-status]`);
    if (b.status === 'done') continue;
    cell.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
      const fd = new FormData();
      fd.append('title', b.title); fd.append('type', b.type);
      fd.append('hwtype', hw); fd.append('manufacturer', mfr); fd.append('tags', tags);
      fd.append('file', b.file);
      const res = await fabApiMultipart('/fabrication-documents', fd);
      if (res) { fabDocs.unshift(res); b.status = 'done'; cell.innerHTML = '<span class="text-success">✓ Added</span>'; }
    } catch (e) { failed++; b.status = 'error'; cell.innerHTML = `<span class="text-danger" title="${fabEsc(e.message)}">Failed</span>`; }
  }
  btn.disabled = false;
  fabRenderAll();
  if (!failed) { fabHideModal('fab-batch-modal'); showNotification(`Added ${fabBatchFiles.length} documents`, 'success'); }
  else showNotification(`${failed} file(s) failed — fix and press Upload again to retry`, 'danger');
}

function fabSetupGlobalDrop(){
  const overlay = document.getElementById('fab-drop-overlay');
  const pane = document.getElementById('fab-preview');
  let depth = 0;
  const hasFiles = e => e.dataTransfer && [...(e.dataTransfer.types||[])].includes('Files');
  const modalOpen = () => !!document.querySelector('.modal.show');
  const inPane = e => !!e.target.closest('#fab-preview');

  window.addEventListener('dragenter', e=>{ if(!hasFiles(e)||modalOpen()) return; depth++; if(!(inPane(e)&&fabCurrent)) overlay.classList.add('visible'); });
  window.addEventListener('dragleave', e=>{ if(!hasFiles(e)) return; depth=Math.max(0,depth-1); if(!depth) overlay.classList.remove('visible'); });
  window.addEventListener('dragover',  e=>{
    if(!hasFiles(e)||modalOpen()) return;
    e.preventDefault();
    const revDrop = inPane(e) && fabCurrent && hasPermission('fabrication.edit');
    pane.classList.toggle('drag-over', revDrop);
    overlay.classList.toggle('visible', !revDrop && hasPermission('fabrication.create'));
  });
  window.addEventListener('drop', e=>{
    if(!hasFiles(e)||modalOpen()) return;
    e.preventDefault(); depth=0; overlay.classList.remove('visible'); pane.classList.remove('drag-over');
    const files = [...e.dataTransfer.files];
    if(!files.length) return;
    if (inPane(e) && fabCurrent) {
      if(!hasPermission('fabrication.edit')) return;
      fabOpenEdit(fabCurrentId, true);
      fabHandleFileSelect(files[0]);
    } else if (hasPermission('fabrication.create')) {
      fabOpenBatch(files);
    }
  });
}

// ── Tag input ─────────────────────────────────────────────────────────────────
function fabRenderTags(){
  const wrap = document.getElementById('fab-tag-wrap');
  const inp  = document.getElementById('fab-tag-raw-input');
  wrap.querySelectorAll('.fab-tag-pill').forEach(p=>p.remove());
  fabActiveTags.forEach(t=>{
    const pill = document.createElement('span');
    pill.className='fab-tag-pill';
    pill.innerHTML=`${fabEsc(t)}<button class="fab-tag-pill-remove" tabindex="-1">×</button>`;
    pill.querySelector('.fab-tag-pill-remove').addEventListener('click',e=>{
      e.stopPropagation();
      fabActiveTags=fabActiveTags.filter(x=>x!==t);
      fabRenderTags();
    });
    wrap.insertBefore(pill,inp);
  });
}
function fabInitTagInput(){
  const inp=document.getElementById('fab-tag-raw-input');
  inp.value='';
  inp.onkeydown=fabHandleTagKey;
  inp.onblur=fabCommitTagInput;
}
function fabHandleTagKey(e){
  const inp=e.target;
  if(e.key==='Enter'||e.key===','){e.preventDefault();fabAddTagFromInput(inp);}
  else if(e.key==='Backspace'&&inp.value===''&&fabActiveTags.length){fabActiveTags.pop();fabRenderTags();}
}
function fabCommitTagInput(){
  const inp=document.getElementById('fab-tag-raw-input');
  if(inp&&inp.value.trim()) fabAddTagFromInput(inp);
}
function fabAddTagFromInput(inp){
  const v=inp.value.replace(/,+$/,'').trim();
  if(v&&!fabActiveTags.includes(v)){fabActiveTags.push(v);fabRenderTags();}
  inp.value='';
}

// ── File upload ───────────────────────────────────────────────────────────────
function fabSetupUploadZone(){
  const zone=document.getElementById('fab-upload-zone');
  const fi=document.getElementById('fab-f-file');
  zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('drag-over');});
  zone.addEventListener('dragleave',()=>zone.classList.remove('drag-over'));
  zone.addEventListener('drop',e=>{
    e.preventDefault(); zone.classList.remove('drag-over');
    if(e.dataTransfer.files[0]) fabHandleFileSelect(e.dataTransfer.files[0]);
  });
  fi.addEventListener('change',()=>{ if(fi.files[0]) fabHandleFileSelect(fi.files[0]); });
}
function fabHandleFileSelect(file){
  fabPendingFile=file;
  fabShowFilePreview(file.name, file.size);
}
function fabShowFilePreview(name, size){
  const zone=document.getElementById('fab-upload-zone');
  const prev=document.getElementById('fab-upload-preview');
  zone.style.display='none'; prev.style.display='flex';
  prev.innerHTML=`
    <span style="font-size:22px">${fabFileIcon(name)}</span>
    <span class="fab-upload-preview-name">${fabEsc(name)}</span>
    <span class="fab-upload-preview-size">${fabFmtSize(size)}</span>
    <button class="fab-upload-clear" id="fab-upload-clear">×</button>`;
  document.getElementById('fab-upload-clear').addEventListener('click',fabClearFilePreview);
}
function fabClearFilePreview(){
  fabPendingFile=null;
  document.getElementById('fab-f-file').value='';
  document.getElementById('fab-upload-zone').style.display='';
  document.getElementById('fab-upload-preview').style.display='none';
}

// ── Export modal ──────────────────────────────────────────────────────────────
function fabOpenExportModal(){
  fabExportOrder=[...fabDocs.filter(d=>fabSelectedIds.has(d.id))];
  fabRenderExportList();
  const noPdf=fabExportOrder.filter(d=>!d.file||!fabIsPdf(d.file.name));
  document.getElementById('fab-export-footer-note').textContent=
    noPdf.length ? `${noPdf.length} doc${noPdf.length!==1?'s':''} without a PDF will be skipped` : '';
  fabShowModal('fab-export-modal');
}
function fabRenderExportList(){
  const list=document.getElementById('fab-export-list');
  list.innerHTML=fabExportOrder.map((d,i)=>{
    const hasPdf=d.file&&fabIsPdf(d.file.name);
    return`<div class="d-flex align-items-center gap-2 p-2 border rounded mb-2 bg-body" data-idx="${i}" draggable="true" style="cursor:grab">
      <span class="text-muted" style="font-size:18px;cursor:grab">⠿</span>
      <span style="font-size:16px">${d.file?fabFileIcon(d.file.name):'📄'}</span>
      <span class="flex-fill text-truncate" style="font-size:.875rem">${fabEsc(d.title)}</span>
      <span class="${FAB_TYPE_BADGE[d.type]} badge" style="font-size:.7rem">${FAB_TYPE_LABELS[d.type]}</span>
      ${!hasPdf?`<span class="badge bg-warning-lt text-warning" style="font-size:.7rem">no PDF</span>`:''}
    </div>`;
  }).join('');
  list.querySelectorAll('[data-idx]').forEach(item=>{
    item.addEventListener('dragstart',e=>{fabDragSrcIdx=+item.dataset.idx;item.style.opacity='.4';e.dataTransfer.effectAllowed='move';});
    item.addEventListener('dragend',()=>{item.style.opacity='';list.querySelectorAll('[data-idx]').forEach(i=>i.style.borderColor='');});
    item.addEventListener('dragover',e=>{e.preventDefault();item.style.borderColor='var(--tblr-primary)';});
    item.addEventListener('dragleave',()=>item.style.borderColor='');
    item.addEventListener('drop',e=>{
      e.preventDefault(); item.style.borderColor='';
      const ti=+item.dataset.idx;
      if(fabDragSrcIdx===null||fabDragSrcIdx===ti)return;
      const moved=fabExportOrder.splice(fabDragSrcIdx,1)[0];
      fabExportOrder.splice(ti,0,moved);
      fabDragSrcIdx=null;
      fabRenderExportList();
    });
  });
}
async function fabRunExport(){
  const toMerge=fabExportOrder.filter(d=>d.file&&fabIsPdf(d.file.name)&&d.file.url);
  if(!toMerge.length){ showNotification('No PDF files to merge. Attach PDFs to selected documents first.','warning'); return; }
  fabHideModal('fab-export-modal');
  fabClearSelection();
  const btn=document.getElementById('fab-export-confirm-btn');
  btn.disabled=true;
  showNotification(`Merging ${toMerge.length} PDF${toMerge.length!==1?'s':''}…`,'info');
  try {
    const {PDFDocument}=PDFLib;
    const merged=await PDFDocument.create();
    for(const d of toMerge){
      try{
        const resp=await fetch(d.file.url);
        const buf=await resp.arrayBuffer();
        const src=await PDFDocument.load(new Uint8Array(buf),{ignoreEncryption:true});
        const pages=await merged.copyPages(src,src.getPageIndices());
        pages.forEach(p=>merged.addPage(p));
      } catch(err){ console.warn('Skipping',d.title,err); }
    }
    const bytes=await merged.save();
    const blob=new Blob([bytes],{type:'application/pdf'});
    const url=URL.createObjectURL(blob);
    const a=document.createElement('a');
    a.href=url; a.download='fabrication-docs-merged.pdf';
    document.body.appendChild(a); a.click();
    document.body.removeChild(a);
    setTimeout(()=>URL.revokeObjectURL(url),10000);
    showNotification(`Export complete — ${merged.getPageCount()} page${merged.getPageCount()!==1?'s':''} merged`,'success');
  } catch(err){
    showNotification(err.message||'Export failed','danger');
    console.error(err);
  } finally { btn.disabled=false; }
}

// ── Wiring ────────────────────────────────────────────────────────────────────
const fabEl = id => document.getElementById(id);
fabEl('fab-add-btn').addEventListener('click', () => fabOpenAdd());
fabEl('fab-modal-save').addEventListener('click', fabSaveDoc);
fabEl('fab-form-delete-btn').addEventListener('click', () => fabDeleteDoc(fabEditingId));
fabEl('fab-pv-edit').addEventListener('click', () => fabOpenEdit(fabCurrentId));
fabEl('fab-pv-newrev').addEventListener('click', () => fabOpenEdit(fabCurrentId, true));
fabEl('fab-pv-delete').addEventListener('click', () => fabDeleteDoc(fabCurrentId));
fabEl('fab-pv-close').addEventListener('click', fabClosePreview);
fabEl('fab-batch-save').addEventListener('click', fabRunBatch);
fabEl('fab-sel-clear').addEventListener('click', fabClearSelection);
fabEl('fab-export-btn').addEventListener('click', fabOpenExportModal);
fabEl('fab-export-confirm-btn').addEventListener('click', fabRunExport);
fabEl('fab-clear-filters').addEventListener('click', fabClearFilters);

let fabSearchTimer;
fabEl('fab-search').addEventListener('input', () => { clearTimeout(fabSearchTimer); fabSearchTimer = setTimeout(fabRenderAll, 150); });
fabEl('fab-hw-filter').addEventListener('change',  e => { fabFilterHw  = e.target.value; fabRenderAll(); });
fabEl('fab-mfr-filter').addEventListener('change', e => { fabFilterMfr = e.target.value; fabRenderAll(); });
fabEl('fab-tag-filter').addEventListener('change', e => { fabFilterTag = e.target.value; fabRenderAll(); });
document.querySelectorAll('[data-filter-type]').forEach(n => n.addEventListener('click', () => { fabFilterType = n.dataset.filterType; fabRenderAll(); }));

function fabSetView(mode){
  fabViewMode = mode;
  try { localStorage.setItem('fab_view', mode); } catch (e) {}
  fabRenderAll();
}
fabEl('fab-view-table-btn').addEventListener('click', () => fabSetView('table'));
fabEl('fab-view-grid-btn').addEventListener('click',  () => fabSetView('grid'));

// One delegated handler per container instead of per-row listeners
function fabListClick(e){
  if (e.target.id === 'fab-sel-all') {
    const on = e.target.checked;
    fabFiltered().forEach(d => on ? fabSelectedIds.add(d.id) : fabSelectedIds.delete(d.id));
    fabRenderAll(); return;
  }
  const th = e.target.closest('th[data-col]');
  if (th && !th.classList.contains('nosort')) {
    const col = th.dataset.col;
    if (fabSortCol === col) fabSortDir = fabSortDir==='asc' ? 'desc' : 'asc';
    else { fabSortCol = col; fabSortDir = col==='updated_at' ? 'desc' : 'asc'; }
    fabRenderAll(); return;
  }
  const row = e.target.closest('[data-id]');
  if (!row) return;
  const id = Number(row.dataset.id);
  if (e.target.classList.contains('fab-row-chk')) { fabToggleSelect(id, e.target.checked); return; }
  fabOpenPreview(id);
}
fabEl('fab-doctable').addEventListener('click', fabListClick);
fabEl('fab-docgrid').addEventListener('click', fabListClick);

fabEl('fab-pv-tags').addEventListener('click', e => {
  const t = e.target.closest('[data-tag]');
  if (t) { fabFilterTag = t.dataset.tag; fabRenderAll(); }
});
fabEl('fab-pv-revs').addEventListener('click', e => {
  const btn = e.target.closest('[data-act]'); if (!btn) return;
  fabRevisionAction(btn.dataset.act, Number(btn.closest('[data-rev]').dataset.rev));
});

// Arrow keys step through the list while a document is open
document.addEventListener('keydown', e => {
  if (!fabCurrentId || e.target.closest('input, textarea, select') || document.querySelector('.modal.show')) return;
  if (e.key === 'Escape') { fabClosePreview(); return; }
  if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
  const list = fabFiltered(), i = list.findIndex(d => d.id === fabCurrentId);
  const next = list[i + (e.key === 'ArrowDown' ? 1 : -1)];
  if (next) { e.preventDefault(); fabOpenPreview(next.id); document.querySelector(`[data-id="${next.id}"]`)?.scrollIntoView({block:'nearest'}); }
});

fabSetupUploadZone();
fabSetupGlobalDrop();
fabLoadAll();
</script>
@endpush
