@extends('layouts.app')

@section('title', 'Fabrication Documents')

@section('styles')
<style>
/* ── Filter sidebar nav ───────────────────────────────────────── */
.fab-nav-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--tblr-secondary-color);
  font-weight: 600;
  padding: 10px 8px 3px;
  margin-top: 4px;
}
.fab-nav-label:first-child { padding-top: 0; margin-top: 0; }
.fab-nav-item {
  padding: 6px 10px;
  border-radius: var(--tblr-border-radius);
  cursor: pointer;
  font-size: 0.8125rem;
  color: var(--tblr-secondary-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: background .12s, color .12s;
  user-select: none;
}
.fab-nav-item:hover { background: rgba(var(--tblr-primary-rgb), .06); color: var(--tblr-body-color); }
.fab-nav-item.active { background: var(--tblr-primary-lt); color: var(--tblr-primary); font-weight: 500; }
.fab-nav-count {
  font-size: 0.7rem;
  color: var(--tblr-secondary-color);
  background: var(--tblr-bg-surface-secondary, rgba(0,0,0,.04));
  padding: 1px 7px;
  border-radius: 20px;
}

/* ── Document cards ───────────────────────────────────────────── */
#fab-docgrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
  align-content: start;
}
.fab-doc-card {
  background: var(--tblr-card-bg);
  border: var(--tblr-border-width) solid var(--tblr-border-color);
  border-radius: var(--tblr-border-radius-lg);
  padding: 14px;
  cursor: pointer;
  transition: border-color .15s, box-shadow .15s, transform .15s;
  display: flex;
  flex-direction: column;
  gap: 8px;
  position: relative;
  user-select: none;
}
.fab-doc-card:hover {
  border-color: var(--tblr-primary);
  box-shadow: 0 2px 12px rgba(var(--tblr-primary-rgb), .12);
  transform: translateY(-1px);
}
.fab-doc-card.selected { border-color: var(--tblr-primary); background: var(--tblr-primary-lt); }
.fab-card-check {
  position: absolute;
  top: 10px; right: 10px;
  width: 18px; height: 18px;
  border-radius: 4px;
  border: var(--tblr-border-width) solid var(--tblr-border-color);
  background: var(--tblr-card-bg);
  display: flex; align-items: center; justify-content: center;
  opacity: 0;
  transition: opacity .12s;
  pointer-events: none;
}
.fab-doc-card:hover .fab-card-check { opacity: 1; }
.fab-card-check.checked { background: var(--tblr-primary-lt); border-color: var(--tblr-primary); opacity: 1; }
.fab-doc-title { font-size: 0.875rem; font-weight: 500; line-height: 1.4; }
.fab-doc-meta { font-size: 0.75rem; color: var(--tblr-secondary-color); }
.fab-doc-notes {
  font-size: 0.75rem;
  color: var(--tblr-secondary-color);
  border-top: var(--tblr-border-width) solid var(--tblr-border-color);
  padding-top: 8px;
  font-style: italic;
  line-height: 1.5;
}
.fab-file-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.75rem;
  color: var(--tblr-secondary-color);
}

/* ── Tags ─────────────────────────────────────────────────────── */
.fab-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.fab-tag {
  font-size: 0.7rem;
  padding: 2px 8px;
  border-radius: 20px;
  background: var(--tblr-bg-surface-secondary, rgba(0,0,0,.05));
  color: var(--tblr-secondary-color);
  border: var(--tblr-border-width) solid var(--tblr-border-color);
}

/* ── View toggle ──────────────────────────────────────────────── */
.fab-view-toggle {
  display: flex;
  gap: 2px;
  background: var(--tblr-bg-surface-secondary, rgba(0,0,0,.04));
  border: var(--tblr-border-width) solid var(--tblr-border-color);
  border-radius: var(--tblr-border-radius);
  padding: 2px;
}
.fab-view-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: calc(var(--tblr-border-radius) - 2px);
  color: var(--tblr-secondary-color);
  transition: background .12s, color .12s;
  display: flex; align-items: center;
}
.fab-view-btn:hover { color: var(--tblr-body-color); }
.fab-view-btn.active { background: var(--tblr-card-bg); color: var(--tblr-body-color); box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.fab-view-btn svg { width: 15px; height: 15px; display: block; }

/* ── Selection bar ────────────────────────────────────────────── */
#fab-sel-bar {
  display: none;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: var(--tblr-primary-lt);
  border: var(--tblr-border-width) solid var(--tblr-primary);
  border-radius: var(--tblr-border-radius);
  margin-bottom: 12px;
}
#fab-sel-bar.visible { display: flex; }

/* ── Table ────────────────────────────────────────────────────── */
#fab-doctable { display: none; }
#fab-doctable.visible { display: block; }
#fab-docgrid.hidden { display: none; }
.fab-tbl { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.fab-tbl thead th {
  position: sticky; top: 0;
  background: var(--tblr-card-bg);
  z-index: 1;
  text-align: left;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--tblr-secondary-color);
  padding: 10px 12px;
  border-bottom: var(--tblr-border-width) solid var(--tblr-border-color);
  white-space: nowrap;
  cursor: pointer;
  user-select: none;
}
.fab-tbl thead th:hover { color: var(--tblr-body-color); }
.fab-tbl thead th.sort-active { color: var(--tblr-primary); }
.fab-tbl thead th .sort-arrow { margin-left: 4px; opacity: 0.5; font-size: 0.625rem; }
.fab-tbl thead th.sort-active .sort-arrow { opacity: 1; }
.fab-tbl tbody tr {
  border-bottom: var(--tblr-border-width) solid var(--tblr-border-color);
  cursor: pointer;
  transition: background .1s;
}
.fab-tbl tbody tr:hover { background: rgba(var(--tblr-primary-rgb), .04); }
.fab-tbl tbody tr.selected { background: var(--tblr-primary-lt); }
.fab-tbl tbody tr:last-child { border-bottom: none; }
.fab-tbl td { padding: 9px 12px; vertical-align: middle; }
.fab-tbl td.muted { color: var(--tblr-secondary-color); }
.fab-tbl-tags { display: flex; flex-wrap: wrap; gap: 3px; }
.fab-tbl-check { width: 36px; padding-left: 12px; }
.fab-tbl-cb {
  width: 16px; height: 16px;
  border-radius: 3px;
  border: var(--tblr-border-width) solid var(--tblr-border-color);
  background: var(--tblr-card-bg);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.fab-tbl-cb.checked { background: var(--tblr-primary-lt); border-color: var(--tblr-primary); }

/* ── Upload zone ──────────────────────────────────────────────── */
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
.fab-tag-wrap:focus-within { border-color: var(--tblr-primary); box-shadow: 0 0 0 .2rem rgba(var(--tblr-primary-rgb), .1); }
.fab-tag-pill {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 0.7rem; padding: 2px 7px; border-radius: 20px;
  background: var(--tblr-primary-lt);
  color: var(--tblr-primary);
  border: var(--tblr-border-width) solid rgba(var(--tblr-primary-rgb), .3);
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
<div class="container-xl">

  <!-- Page header -->
  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">
          <i class="ti ti-drill me-2"></i>Fabrication Documents
        </h2>
        <div class="text-muted mt-1">Hardware templates, installation instructions and maintenance guides</div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <button class="btn btn-primary" id="fab-add-btn" data-permission="fabrication.create">
          <i class="ti ti-plus me-1"></i>Add Document
        </button>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="row g-3">

      <!-- ── Left: filter sidebar ──────────────────────────────── -->
      <div class="col-12 col-md-3">
        <div class="card" style="position: sticky; top: 72px;">
          <div class="card-body p-3">

            <div class="fab-nav-label">Document type</div>
            <div class="fab-nav-item active" data-filter-type="all">
              All documents <span class="fab-nav-count" id="fab-cnt-all">0</span>
            </div>
            <div class="fab-nav-item" data-filter-type="fabrication">
              Fabrication templates <span class="fab-nav-count" id="fab-cnt-fab">0</span>
            </div>
            <div class="fab-nav-item" data-filter-type="installation">
              Installation instructions <span class="fab-nav-count" id="fab-cnt-install">0</span>
            </div>
            <div class="fab-nav-item" data-filter-type="maintenance">
              Maintenance guides <span class="fab-nav-count" id="fab-cnt-maint">0</span>
            </div>

            <div class="fab-nav-label">Hardware type</div>
            <div id="fab-hw-nav"></div>

            <div class="fab-nav-label">Manufacturer</div>
            <div id="fab-mfr-nav"></div>

          </div>
        </div>
      </div>

      <!-- ── Right: main content ───────────────────────────────── -->
      <div class="col-12 col-md-9">

        <!-- Toolbar -->
        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
          <div class="flex-fill">
            <input type="text" class="form-control" id="fab-search" placeholder="Search documents…">
          </div>
          <select class="form-select" id="fab-sort" style="width: auto;">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="alpha">A to Z</option>
          </select>
          <div class="fab-view-toggle">
            <button class="fab-view-btn active" id="fab-view-grid-btn" title="Card view">
              <svg viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="1" width="5.5" height="5.5" rx="1" fill="currentColor"/><rect x="8.5" y="1" width="5.5" height="5.5" rx="1" fill="currentColor"/><rect x="1" y="8.5" width="5.5" height="5.5" rx="1" fill="currentColor"/><rect x="8.5" y="8.5" width="5.5" height="5.5" rx="1" fill="currentColor"/></svg>
            </button>
            <button class="fab-view-btn" id="fab-view-table-btn" title="Table view">
              <svg viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="2" width="13" height="1.5" rx="0.75" fill="currentColor"/><rect x="1" y="6" width="13" height="1.5" rx="0.75" fill="currentColor"/><rect x="1" y="10" width="13" height="1.5" rx="0.75" fill="currentColor"/></svg>
            </button>
          </div>
        </div>

        <!-- Selection bar -->
        <div id="fab-sel-bar">
          <span id="fab-sel-label" class="flex-fill text-primary fw-medium" style="font-size:.875rem;"></span>
          <button class="btn btn-sm btn-ghost-secondary" id="fab-sel-clear">Clear</button>
          <button class="btn btn-sm btn-primary" id="fab-export-btn">
            <i class="ti ti-file-export me-1"></i>Export PDF
          </button>
        </div>

        <!-- Loading indicator -->
        <div id="fab-loading" class="text-center py-5 text-muted">
          <div class="spinner-border spinner-border-sm me-2"></div>Loading documents…
        </div>

        <!-- Card grid -->
        <div id="fab-docgrid" class="hidden"></div>

        <!-- Table view -->
        <div id="fab-doctable" class="card"></div>

      </div>
    </div>
  </div>
</div>

<!-- ── Detail modal ───────────────────────────────────────────────── -->
<div class="modal modal-blur fade" id="fab-detail-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div id="fab-d-badge" class="mb-1"></div>
          <h5 class="modal-title" id="fab-d-title"></h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-3" id="fab-d-meta"></div>
        <div id="fab-d-hw-section" class="mb-3" style="display:none">
          <div class="subheader mb-1">Hardware details</div>
          <div id="fab-d-hw"></div>
        </div>
        <div id="fab-d-file-section" class="mb-3" style="display:none">
          <div class="subheader mb-2">Attached file</div>
          <div id="fab-d-file"></div>
        </div>
        <div id="fab-d-tags-section" class="mb-3" style="display:none">
          <div class="subheader mb-2">Tags</div>
          <div class="fab-tags" id="fab-d-tags"></div>
        </div>
        <div id="fab-d-notes-section" style="display:none">
          <div class="subheader mb-2">Notes</div>
          <div class="card card-sm bg-muted-lt">
            <div class="card-body text-secondary" style="line-height:1.65;white-space:pre-wrap;font-size:.875rem" id="fab-d-notes"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger me-auto" id="fab-d-delete-btn" data-permission="fabrication.delete">
          <i class="ti ti-trash me-1"></i>Delete
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="fab-d-edit-btn" data-permission="fabrication.edit">
          <i class="ti ti-edit me-1"></i>Edit
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Add / Edit modal ───────────────────────────────────────────── -->
<div class="modal modal-blur fade" id="fab-form-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="fab-modal-title">Add document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label required">Document title</label>
          <input type="text" class="form-control" id="fab-f-title" placeholder="e.g. Hager 1251 Prep Template">
        </div>
        <div class="mb-3">
          <label class="form-label required">Document type</label>
          <select class="form-select" id="fab-f-type">
            <option value="fabrication">Fabrication template</option>
            <option value="installation">Installation instruction</option>
            <option value="maintenance">Maintenance guide</option>
          </select>
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
          <label class="form-label">File</label>
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
        <div class="mb-3">
          <label class="form-label">Tags <span class="text-muted">(Enter or comma to add)</span></label>
          <div class="fab-tag-wrap" id="fab-tag-wrap" onclick="document.getElementById('fab-tag-raw-input').focus()">
            <input type="text" class="fab-tag-raw-input" id="fab-tag-raw-input" placeholder="Add a tag…">
          </div>
        </div>
        <div class="mb-0">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="fab-f-notes" rows="3" placeholder="Installation notes, revision info, special instructions…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger me-auto" id="fab-form-delete-btn" style="display:none">
          <i class="ti ti-trash me-1"></i>Delete
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="fab-modal-save">
          <i class="ti ti-device-floppy me-1"></i>Save document
        </button>
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
        <button class="btn btn-primary" id="fab-export-confirm-btn">
          <i class="ti ti-file-zip me-1"></i>Merge &amp; download
        </button>
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
let fabPendingFile = null;      // { file: File } or null
let fabKeepFile   = true;       // for edit: whether existing file is retained
let fabFilterType = 'all';
let fabFilterHw   = '';
let fabFilterMfr  = '';
let fabDetailId   = null;
let fabSelectedIds = new Set();
let fabExportOrder = [];
let fabDragSrcIdx  = null;
let fabViewMode    = 'grid';    // 'grid' | 'table'
let fabTblSortCol  = 'created_at';
let fabTblSortDir  = 'desc';

const FAB_TYPE_LABELS = {
  fabrication:  'Fabrication template',
  installation: 'Installation instruction',
  maintenance:  'Maintenance guide',
};
const FAB_TYPE_BADGE = {
  fabrication:  'badge bg-blue-lt text-blue',
  installation: 'badge bg-green-lt text-green',
  maintenance:  'badge bg-yellow-lt text-yellow-fg',
};

// ── Utilities ─────────────────────────────────────────────────────────────────
function fabEsc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fabFmtDate(iso){ return iso ? new Date(iso).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—'; }
function fabFmtSize(b){ if(!b)return''; if(b<1024)return b+'B'; if(b<1048576)return(b/1024).toFixed(1)+'KB'; return(b/1048576).toFixed(1)+'MB'; }
function fabFileIcon(name){
  if(!name)return'📄';
  const ext=(name.split('.').pop()||'').toLowerCase();
  if(ext==='pdf')return'📕';
  if(['png','jpg','jpeg','gif','webp'].includes(ext))return'🖼';
  if(['dwg','dxf'].includes(ext))return'📐';
  if(['doc','docx'].includes(ext))return'📝';
  return'📄';
}
function fabIsPdf(name){ return (name||'').split('.').pop().toLowerCase()==='pdf'; }
function fabGetUnique(field){ return [...new Set(fabDocs.map(d=>d[field]).filter(Boolean))].sort(); }

// Bootstrap modal helpers (use window.bootstrap to match Tabler's bundle exposure)
function fabShowModal(id){ const el=document.getElementById(id); showModal(el); }
function fabHideModal(id){ const el=document.getElementById(id); hideModal(el); }

// ── API helpers ────────────────────────────────────────────────────────────────
async function fabApiJson(endpoint, options={}){
  const response = await apiCall(endpoint, options);
  if(!response.ok){
    if(response.status===401){ return; } // handled globally
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
  if(authToken) headers['Authorization'] = `Bearer ${authToken}`;
  const response = await fetch(`${API_BASE}${endpoint}`, { method, headers, body: formData });
  if(!response.ok){
    if(response.status===401){ showLogin(); return null; }
    const err = await response.json().catch(()=>({message:'Request failed'}));
    throw new Error(err.message||`HTTP ${response.status}`);
  }
  return response.json();
}

// ── Load all docs ─────────────────────────────────────────────────────────────
async function fabLoadAll(){
  try {
    const data = await fabApiJson('/fabrication-documents?per_page=all');
    fabDocs = data?.data ?? [];
    fabRenderAll();
  } catch(e){
    console.error('Failed to load documents:', e);
    showNotification('Failed to load documents', 'danger');
  } finally {
    document.getElementById('fab-loading').style.display = 'none';
  }
}

// ── Sidebar ───────────────────────────────────────────────────────────────────
function fabUpdateSidebar(){
  const c = {all:fabDocs.length, fabrication:0, installation:0, maintenance:0};
  fabDocs.forEach(d=>{ if(c[d.type]!==undefined) c[d.type]++; });
  document.getElementById('fab-cnt-all').textContent      = c.all;
  document.getElementById('fab-cnt-fab').textContent      = c.fabrication;
  document.getElementById('fab-cnt-install').textContent  = c.installation;
  document.getElementById('fab-cnt-maint').textContent    = c.maintenance;
  fabRenderNavGroup('fab-hw-nav',  fabGetUnique('hwtype'),       'hw');
  fabRenderNavGroup('fab-mfr-nav', fabGetUnique('manufacturer'), 'mfr');
  // Datalists for form inputs
  document.getElementById('fab-hw-dl').innerHTML  = fabGetUnique('hwtype').map(v=>`<option value="${fabEsc(v)}">`).join('');
  document.getElementById('fab-mfr-dl').innerHTML = fabGetUnique('manufacturer').map(v=>`<option value="${fabEsc(v)}">`).join('');
}

function fabRenderNavGroup(cid, vals, gk){
  const el = document.getElementById(cid);
  const active = gk==='hw' ? fabFilterHw : fabFilterMfr;
  el.innerHTML = vals.map(v=>`
    <div class="fab-nav-item${active===v?' active':''}" data-val="${fabEsc(v)}">${fabEsc(v)}</div>
  `).join('');
  el.querySelectorAll('.fab-nav-item').forEach(n=>n.addEventListener('click',()=>{
    if(gk==='hw') fabFilterHw  = fabFilterHw===n.dataset.val  ? '' : n.dataset.val;
    else           fabFilterMfr = fabFilterMfr===n.dataset.val ? '' : n.dataset.val;
    fabRenderAll();
  }));
}

// ── Filtering & sorting ───────────────────────────────────────────────────────
function fabFiltered(){
  const q = document.getElementById('fab-search').value.toLowerCase();
  const s = document.getElementById('fab-sort').value;
  let out = fabDocs.filter(d=>{
    if(fabFilterType!=='all' && d.type!==fabFilterType) return false;
    if(fabFilterHw  && d.hwtype!==fabFilterHw)          return false;
    if(fabFilterMfr && d.manufacturer!==fabFilterMfr)   return false;
    if(q && !`${d.title} ${d.hwtype} ${d.manufacturer} ${(d.tags||[]).join(' ')} ${d.notes||''}`.toLowerCase().includes(q)) return false;
    return true;
  });
  if(s==='oldest') out.sort((a,b)=> new Date(a.created_at)-new Date(b.created_at));
  else if(s==='alpha') out.sort((a,b)=>a.title.localeCompare(b.title));
  else out.sort((a,b)=> new Date(b.created_at)-new Date(a.created_at));
  return out;
}

function fabTableSorted(){
  const list = [...fabFiltered()];
  const dir  = fabTblSortDir==='asc' ? 1 : -1;
  list.sort((a,b)=>{
    if(fabTblSortCol==='created_at'||fabTblSortCol==='updated_at'){
      return dir*(new Date(a[fabTblSortCol]||0)-new Date(b[fabTblSortCol]||0));
    }
    if(fabTblSortCol==='file'){
      return dir*((a.file?.name||'').localeCompare(b.file?.name||''));
    }
    if(fabTblSortCol==='tags'){
      return dir*((a.tags||[]).join(',').localeCompare((b.tags||[]).join(',')));
    }
    return dir*String(a[fabTblSortCol]||'').localeCompare(String(b[fabTblSortCol]||''));
  });
  return list;
}

// ── Render all ────────────────────────────────────────────────────────────────
function fabRenderAll(){
  fabUpdateSidebar();
  document.querySelectorAll('[data-filter-type]').forEach(n=>
    n.classList.toggle('active', n.dataset.filterType===fabFilterType)
  );
  const grid = document.getElementById('fab-docgrid');
  const tbl  = document.getElementById('fab-doctable');
  if(fabViewMode==='table'){
    grid.classList.add('hidden');
    tbl.classList.add('visible');
    fabRenderTable();
  } else {
    grid.classList.remove('hidden');
    tbl.classList.remove('visible');
    fabRenderGrid();
  }
}

// ── Grid view ─────────────────────────────────────────────────────────────────
function fabRenderGrid(){
  const grid = document.getElementById('fab-docgrid');
  const list = fabFiltered();
  if(!list.length){
    grid.innerHTML = `<div class="col-12 text-center text-muted py-5">
      <i class="ti ti-file-off mb-2" style="font-size:2rem;display:block"></i>
      No documents found. Add your first document to get started.
    </div>`;
    return;
  }
  grid.innerHTML = list.map(d=>{
    const sel = fabSelectedIds.has(d.id);
    return `<div class="fab-doc-card${sel?' selected':''}" data-id="${d.id}">
      <div class="fab-card-check${sel?' checked':''}">
        ${sel?`<svg viewBox="0 0 10 8" fill="none" width="10" height="10"><path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`:''}
      </div>
      <div><span class="${FAB_TYPE_BADGE[d.type]} ${FAB_TYPE_BADGE[d.type]?'':'badge text-bg-secondary'}">${FAB_TYPE_LABELS[d.type]}</span></div>
      <div class="fab-doc-title">${fabEsc(d.title)}</div>
      <div class="fab-doc-meta">${[d.hwtype,d.manufacturer].filter(Boolean).map(fabEsc).join(' · ') || '&nbsp;'}</div>
      ${d.file?`<div class="fab-file-chip"><span style="font-size:15px">${fabFileIcon(d.file.name)}</span><span>${fabEsc(d.file.name)}</span></div>`:''}
      ${d.tags&&d.tags.length?`<div class="fab-tags">${d.tags.map(t=>`<span class="fab-tag">${fabEsc(t)}</span>`).join('')}</div>`:''}
      ${d.notes?`<div class="fab-doc-notes">${fabEsc(d.notes.slice(0,90))}${d.notes.length>90?'…':''}</div>`:''}
    </div>`;
  }).join('');
  grid.querySelectorAll('.fab-doc-card').forEach(c=>{
    c.addEventListener('click', e=>{
      if(e.shiftKey||e.ctrlKey||e.metaKey||fabSelectedIds.size>0) fabToggleSelect(c.dataset.id);
      else fabOpenDetail(c.dataset.id);
    });
    c.addEventListener('contextmenu', e=>{ e.preventDefault(); fabToggleSelect(c.dataset.id); });
  });
}

// ── Table view ────────────────────────────────────────────────────────────────
function fabRenderTable(){
  const wrap = document.getElementById('fab-doctable');
  const list = fabTableSorted();
  const cols = [
    {key:'sel',      label:'',             sortable:false},
    {key:'title',    label:'Title',         sortable:true},
    {key:'type',     label:'Type',          sortable:true},
    {key:'hwtype',   label:'Hardware',      sortable:true},
    {key:'manufacturer',label:'Manufacturer',sortable:true},
    {key:'file',     label:'File',          sortable:true},
    {key:'tags',     label:'Tags',          sortable:false},
    {key:'created_at',label:'Date added',  sortable:true},
  ];
  if(!list.length){
    wrap.innerHTML = `<div class="card-body text-center text-muted py-5">
      <i class="ti ti-file-off mb-2" style="font-size:2rem;display:block"></i>
      No documents found. Add your first document to get started.
    </div>`;
    return;
  }
  const arrow = dir=> dir==='asc'?'↑':'↓';
  const thead = `<thead><tr>${cols.map(c=>{
    if(c.key==='sel') return `<th class="fab-tbl-check"></th>`;
    const active = fabTblSortCol===c.key;
    return `<th data-col="${c.key}" class="${active?'sort-active':''}">${fabEsc(c.label)}${c.sortable?`<span class="sort-arrow">${active?arrow(fabTblSortDir):'↕'}</span>`:''}</th>`;
  }).join('')}</tr></thead>`;
  const tbody = `<tbody>${list.map(d=>{
    const sel = fabSelectedIds.has(d.id);
    const cb  = `<div class="fab-tbl-cb${sel?' checked':''}">
      ${sel?`<svg viewBox="0 0 10 8" fill="none" width="8" height="8"><path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`:''}
    </div>`;
    const tags = (d.tags||[]).length
      ? `<div class="fab-tbl-tags">${d.tags.map(t=>`<span class="fab-tag">${fabEsc(t)}</span>`).join('')}</div>`
      : `<span class="text-muted">—</span>`;
    const fileCell = d.file
      ? `<span style="font-size:14px">${fabFileIcon(d.file.name)}</span> <span style="font-size:.8125rem">${fabEsc(d.file.name)}</span>`
      : `<span class="text-muted">—</span>`;
    return `<tr data-id="${d.id}" class="${sel?'selected':''}">
      <td class="fab-tbl-check">${cb}</td>
      <td style="font-weight:500;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${fabEsc(d.title)}</td>
      <td><span class="${FAB_TYPE_BADGE[d.type]}">${FAB_TYPE_LABELS[d.type]}</span></td>
      <td class="${d.hwtype?'':'muted'}">${d.hwtype?fabEsc(d.hwtype):'—'}</td>
      <td class="${d.manufacturer?'':'muted'}">${d.manufacturer?fabEsc(d.manufacturer):'—'}</td>
      <td>${fileCell}</td>
      <td>${tags}</td>
      <td class="muted" style="white-space:nowrap">${fabFmtDate(d.created_at)}</td>
    </tr>`;
  }).join('')}</tbody>`;
  wrap.innerHTML = `<div class="table-responsive"><table class="fab-tbl">${thead}${tbody}</table></div>`;
  wrap.querySelectorAll('th[data-col]').forEach(th=>{
    th.addEventListener('click',()=>{
      const col = th.dataset.col;
      if(fabTblSortCol===col) fabTblSortDir = fabTblSortDir==='asc'?'desc':'asc';
      else { fabTblSortCol=col; fabTblSortDir='asc'; }
      fabRenderTable();
    });
  });
  wrap.querySelectorAll('tbody tr').forEach(row=>{
    row.addEventListener('click',e=>{
      const clickedCb = e.target.closest('.fab-tbl-cb,.fab-tbl-check');
      if(clickedCb||e.shiftKey||e.ctrlKey||e.metaKey||fabSelectedIds.size>0) fabToggleSelect(row.dataset.id);
      else fabOpenDetail(row.dataset.id);
    });
    row.addEventListener('contextmenu',e=>{ e.preventDefault(); fabToggleSelect(row.dataset.id); });
  });
}

// ── Selection ─────────────────────────────────────────────────────────────────
function fabToggleSelect(id){
  if(fabSelectedIds.has(id)) fabSelectedIds.delete(id);
  else fabSelectedIds.add(id);
  fabUpdateSelBar();
  fabViewMode==='table' ? fabRenderTable() : fabRenderGrid();
}
function fabClearSelection(){ fabSelectedIds.clear(); fabUpdateSelBar(); fabViewMode==='table'?fabRenderTable():fabRenderGrid(); }
function fabUpdateSelBar(){
  const bar   = document.getElementById('fab-sel-bar');
  const count = fabSelectedIds.size;
  if(count>0){
    bar.classList.add('visible');
    document.getElementById('fab-sel-label').textContent = `${count} document${count!==1?'s':''} selected`;
  } else {
    bar.classList.remove('visible');
  }
}

// ── Detail modal ──────────────────────────────────────────────────────────────
function fabOpenDetail(id){
  const d = fabDocs.find(x=>x.id==id); if(!d) return;
  fabDetailId = id;
  document.getElementById('fab-d-title').textContent = d.title;
  document.getElementById('fab-d-badge').innerHTML   = `<span class="${FAB_TYPE_BADGE[d.type]}">${FAB_TYPE_LABELS[d.type]}</span>`;
  const updated = d.updated_at&&d.updated_at!==d.created_at ? ` · Updated ${fabFmtDate(d.updated_at)}` : '';
  document.getElementById('fab-d-meta').textContent  = `Added ${fabFmtDate(d.created_at)}${updated}`;

  const hwSec = document.getElementById('fab-d-hw-section');
  if(d.hwtype||d.manufacturer){
    hwSec.style.display='';
    document.getElementById('fab-d-hw').innerHTML = [
      d.hwtype       && `<span class="me-3"><strong>Hardware:</strong> ${fabEsc(d.hwtype)}</span>`,
      d.manufacturer && `<span><strong>Manufacturer:</strong> ${fabEsc(d.manufacturer)}</span>`,
    ].filter(Boolean).join('');
  } else hwSec.style.display='none';

  const fileSec = document.getElementById('fab-d-file-section');
  if(d.file){
    fileSec.style.display='';
    document.getElementById('fab-d-file').innerHTML = d.file.url
      ? `<a class="fab-file-pill" href="${d.file.url}" target="_blank" rel="noopener">
           <span style="font-size:20px">${fabFileIcon(d.file.name)}</span>
           <span class="fab-file-pill-name">${fabEsc(d.file.name)}</span>
           <span class="fab-file-pill-size">${fabFmtSize(d.file.size)}</span>
           <span class="text-primary ms-1" style="font-size:.75rem;flex-shrink:0">Open ↗</span>
         </a>`
      : `<div class="fab-file-pill"><span style="font-size:20px">${fabFileIcon(d.file.name)}</span><span>${fabEsc(d.file.name)}</span></div>`;
  } else fileSec.style.display='none';

  const tagSec = document.getElementById('fab-d-tags-section');
  if(d.tags&&d.tags.length){
    tagSec.style.display='';
    document.getElementById('fab-d-tags').innerHTML = d.tags.map(t=>`<span class="fab-tag">${fabEsc(t)}</span>`).join('');
  } else tagSec.style.display='none';

  const noteSec = document.getElementById('fab-d-notes-section');
  if(d.notes){ noteSec.style.display=''; document.getElementById('fab-d-notes').textContent=d.notes; }
  else noteSec.style.display='none';

  fabShowModal('fab-detail-modal');
}

// ── Form modal ────────────────────────────────────────────────────────────────
function fabOpenAdd(){
  fabEditingId=null; fabActiveTags=[]; fabPendingFile=null; fabKeepFile=false;
  document.getElementById('fab-modal-title').textContent = 'Add document';
  ['fab-f-title','fab-f-hwtype','fab-f-mfr','fab-f-notes'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('fab-f-type').value = 'fabrication';
  document.getElementById('fab-form-delete-btn').style.display = 'none';
  document.getElementById('fab-keep-file-note').style.display  = 'none';
  fabRenderTags(); fabInitTagInput(); fabClearFilePreview();
  fabShowModal('fab-form-modal');
}

function fabOpenEdit(id){
  const d = fabDocs.find(x=>x.id==id); if(!d) return;
  fabEditingId = id; fabActiveTags=[...(d.tags||[])]; fabPendingFile=null; fabKeepFile=true;
  document.getElementById('fab-modal-title').textContent = 'Edit document';
  document.getElementById('fab-f-title').value   = d.title;
  document.getElementById('fab-f-type').value    = d.type;
  document.getElementById('fab-f-hwtype').value  = d.hwtype||'';
  document.getElementById('fab-f-mfr').value     = d.manufacturer||'';
  document.getElementById('fab-f-notes').value   = d.notes||'';
  document.getElementById('fab-form-delete-btn').style.display = '';
  const keepNote = document.getElementById('fab-keep-file-note');
  if(d.file){ keepNote.style.display=''; keepNote.textContent=`Current file: ${d.file.name} — upload a new file to replace it.`; }
  else keepNote.style.display='none';
  fabRenderTags(); fabInitTagInput(); fabClearFilePreview();
  fabHideModal('fab-detail-modal');
  fabShowModal('fab-form-modal');
}

async function fabSaveDoc(){
  const title = document.getElementById('fab-f-title').value.trim();
  if(!title){ showNotification('Please enter a title', 'warning'); return; }
  fabCommitTagInput();

  const btn = document.getElementById('fab-modal-save');
  btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

  try {
    const fd = new FormData();
    fd.append('title',        title);
    fd.append('type',         document.getElementById('fab-f-type').value);
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

    if(fabEditingId){
      fabDocs = fabDocs.map(d=>d.id==fabEditingId ? result : d);
    } else {
      fabDocs.unshift(result);
    }
    fabHideModal('fab-form-modal');
    fabRenderAll();
    showNotification(fabEditingId?'Document updated':'Document added', 'success');
  } catch(e){
    showNotification(e.message||'Save failed','danger');
  } finally {
    btn.disabled=false; btn.innerHTML='<i class="ti ti-device-floppy me-1"></i>Save document';
  }
}

async function fabDeleteDoc(id){
  if(!confirm('Delete this document? This cannot be undone.')) return;
  try {
    await fabApiJson(`/fabrication-documents/${id}`, {method:'DELETE'});
    fabDocs = fabDocs.filter(d=>d.id!=id);
    fabSelectedIds.delete(id);
    fabHideModal('fab-detail-modal');
    fabHideModal('fab-form-modal');
    fabUpdateSelBar();
    fabRenderAll();
    showNotification('Document deleted','success');
  } catch(e){
    showNotification(e.message||'Delete failed','danger');
  }
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
document.getElementById('fab-add-btn').addEventListener('click', fabOpenAdd);
document.getElementById('fab-modal-save').addEventListener('click', fabSaveDoc);
document.getElementById('fab-form-delete-btn').addEventListener('click', ()=>fabDeleteDoc(fabEditingId));
document.getElementById('fab-d-edit-btn').addEventListener('click', ()=>fabOpenEdit(fabDetailId));
document.getElementById('fab-d-delete-btn').addEventListener('click', ()=>fabDeleteDoc(fabDetailId));
document.getElementById('fab-search').addEventListener('input', fabRenderAll);
document.getElementById('fab-sort').addEventListener('change', fabRenderAll);
document.getElementById('fab-sel-clear').addEventListener('click', fabClearSelection);
document.getElementById('fab-export-btn').addEventListener('click', fabOpenExportModal);
document.getElementById('fab-export-confirm-btn').addEventListener('click', fabRunExport);

document.querySelectorAll('[data-filter-type]').forEach(n=>n.addEventListener('click',()=>{
  fabFilterType=n.dataset.filterType; fabRenderAll();
}));

document.getElementById('fab-view-grid-btn').addEventListener('click',()=>{
  fabViewMode='grid';
  document.getElementById('fab-view-grid-btn').classList.add('active');
  document.getElementById('fab-view-table-btn').classList.remove('active');
  fabRenderAll();
});
document.getElementById('fab-view-table-btn').addEventListener('click',()=>{
  fabViewMode='table';
  document.getElementById('fab-view-table-btn').classList.add('active');
  document.getElementById('fab-view-grid-btn').classList.remove('active');
  fabRenderAll();
});

fabSetupUploadZone();
fabLoadAll();
</script>
@endpush
