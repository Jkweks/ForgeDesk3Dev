@extends('layouts.app')

@section('title', 'Product Library - Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Project Management</div>
          <h1 class="page-title">Product Library</h1>
        </div>
        <div class="col-auto ms-auto btn-list" data-permission="pm.library.manage">
          <button class="btn" onclick="addLookup('manufacturers')">+ Manufacturer</button>
          <button class="btn" onclick="addLookup('categories')">+ Category</button>
          <button class="btn btn-primary" onclick="openProduct()">+ Product</button>
        </div>
      </div>
    </div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div class="card">
        <div class="card-header">
          <div class="row g-2 w-100">
            <div class="col-md-3"><select class="form-select" id="fMan"><option value="">All manufacturers</option></select></div>
            <div class="col-md-3"><select class="form-select" id="fCat"><option value="">All categories</option></select></div>
            <div class="col-md-4"><input type="search" class="form-control" id="fSearch" placeholder="Search name, model, description"></div>
            <div class="col-md-2"><label class="form-check form-switch m-0 pt-2"><input class="form-check-input" type="checkbox" id="fInactive"><span class="form-check-label">Inactive</span></label></div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Product</th><th>Model</th><th>Manufacturer</th><th>Category</th><th class="text-end">Docs</th></tr></thead>
            <tbody id="prodBody"><tr><td colspan="5" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="prodModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="prodTitle">Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <form id="prodForm" onsubmit="saveProduct(event)">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label required">Manufacturer</label><select class="form-select" id="pMan" required></select></div>
          <div class="col-md-6"><label class="form-label">Category</label><select class="form-select" id="pCat"></select></div>
          <div class="col-md-8"><label class="form-label required">Name</label><input class="form-control" id="pName" required></div>
          <div class="col-md-4"><label class="form-label">Model</label><input class="form-control" id="pModel"></div>
          <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" id="pDesc" rows="2"></textarea></div>
          <div class="col-12"><label class="form-check form-switch"><input class="form-check-input" type="checkbox" id="pActive" checked><span class="form-check-label">Active</span></label></div>
        </div>
        <div class="mt-3 d-flex gap-2" data-permission="pm.library.manage"><button class="btn btn-primary">Save product</button><button type="button" class="btn btn-outline-danger ms-auto" id="pDelete" onclick="deleteProduct()">Delete</button></div>
      </form>
      <div id="docsBox" class="mt-4" style="display:none">
        <h4>Documents</h4>
        <div id="docsList" class="mb-2"></div>
        <div class="input-group" data-permission="pm.library.manage">
          <select class="form-select" id="dKind" style="max-width:130px"><option value="spec">Spec</option><option value="color">Color</option><option value="cut">Cut sheet</option><option value="other">Other</option></select>
          <input class="form-control" id="dLabel" placeholder="Label (optional)">
          <input type="file" class="form-control" id="dFile">
          <button class="btn" onclick="addDoc()">Upload</button>
        </div>
      </div>
      <div id="prodNotice" class="mt-3"></div>
    </div>
  </div></div>
</div>

@include('partials.pm-modal-helpers')

<script>
  const esc = (s) => escapeHtml(String(s ?? ''));
  let lookups = { manufacturers: [], categories: [] }, editing = null;
  const post = (url, body, method = 'POST') => authenticatedFetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) });
  const note = (m, t = 'danger') => { document.getElementById('prodNotice').innerHTML = m ? `<div class="alert alert-${t} mb-0">${esc(m)}</div>` : ''; };

  async function loadLookups() {
    lookups = await authenticatedFetch('/pm/library/lookups');
    const opt = (rows, sel) => rows.map(r => `<option value="${r.id}">${esc(r.name)}</option>`).join('');
    fMan.innerHTML = '<option value="">All manufacturers</option>' + opt(lookups.manufacturers);
    fCat.innerHTML = '<option value="">All categories</option>' + opt(lookups.categories);
    pMan.innerHTML = opt(lookups.manufacturers);
    pCat.innerHTML = '<option value="">None</option>' + opt(lookups.categories);
  }

  async function loadProducts() {
    const q = new URLSearchParams();
    if (fMan.value) q.set('manufacturer_id', fMan.value);
    if (fCat.value) q.set('category_id', fCat.value);
    if (fSearch.value) q.set('search', fSearch.value);
    if (fInactive.checked) q.set('include_inactive', '1');
    const rows = await authenticatedFetch('/pm/library/products?' + q);
    prodBody.innerHTML = rows.length ? rows.map(p => `<tr style="cursor:pointer" onclick="openProduct(${p.id})"><td>${esc(p.name)}${p.is_active ? '' : ' <span class="badge bg-secondary-lt">Inactive</span>'}</td><td>${esc(p.model)}</td><td>${esc(p.manufacturer && p.manufacturer.name)}</td><td>${esc(p.category && p.category.name)}</td><td class="text-end">${p.documents_count}</td></tr>`).join('')
      : '<tr><td colspan="5" class="text-muted">No products.</td></tr>';
  }

  async function addLookup(kind) {
    const name = prompt(kind === 'manufacturers' ? 'Manufacturer name:' : 'Category name:');
    if (!name) return;
    try { await post('/pm/library/' + kind, { name }); await loadLookups(); } catch (e) { alert(e.message || 'Could not add'); }
  }

  async function openProduct(id) {
    note('');
    editing = id || null;
    prodForm.reset(); pActive.checked = true;
    docsBox.style.display = 'none'; pDelete.style.display = id ? '' : 'none';
    prodTitle.textContent = id ? 'Edit product' : 'New product';
    if (id) {
      const p = await authenticatedFetch('/pm/library/products/' + id);
      pMan.value = p.pm_manufacturer_id; pCat.value = p.pm_product_category_id || ''; pName.value = p.name; pModel.value = p.model || '';
      pDesc.value = p.description || ''; pActive.checked = p.is_active; renderDocs(p);
    }
    safeShowModal('prodModal');
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  function renderDocs(p) {
    docsBox.style.display = '';
    docsList.innerHTML = (p.documents || []).map(d => `<div class="d-flex align-items-center gap-2 mb-1"><span class="badge bg-blue-lt">${esc(d.kind)}</span>
      <a href="${location.origin}/api/v1/pm/files/${d.pm_file_id}/download" target="_blank">${esc(d.label || d.file.original_name)}</a>
      <button class="btn btn-sm btn-ghost-danger ms-auto" data-permission="pm.library.manage" onclick="removeDoc(${d.id})">&times;</button></div>`).join('') || '<div class="text-muted">No documents yet.</div>';
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  async function saveProduct(e) {
    e.preventDefault();
    const body = { pm_manufacturer_id: +pMan.value, pm_product_category_id: pCat.value ? +pCat.value : null, name: pName.value, model: pModel.value || null, description: pDesc.value || null, is_active: pActive.checked };
    try {
      const p = editing ? await post('/pm/library/products/' + editing, body, 'PUT') : await post('/pm/library/products', body);
      editing = p.id; prodTitle.textContent = 'Edit product'; pDelete.style.display = ''; renderDocs(p); note('Saved.', 'success');
      loadProducts(); loadLookups();
    } catch (err) { note(err.message || 'Could not save'); }
  }

  async function deleteProduct() {
    if (!editing || !confirm('Delete this product from the library?')) return;
    try { await authenticatedFetch('/pm/library/products/' + editing, { method: 'DELETE' }); safeHideModal('prodModal'); loadProducts(); } catch (e) { note(e.message); }
  }

  async function addDoc() {
    if (!dFile.files[0]) { note('Choose a file first.', 'warning'); return; }
    const fd = new FormData(); fd.append('file', dFile.files[0]); fd.append('kind', dKind.value); if (dLabel.value) fd.append('label', dLabel.value);
    const r = await authenticatedUpload(`/pm/library/products/${editing}/documents`, fd);
    const d = await r.json().catch(() => ({}));
    if (!r.ok) { note(d.message || 'Upload failed'); return; }
    dFile.value = ''; dLabel.value = ''; renderDocs(d); loadProducts(); note('');
  }

  async function removeDoc(docId) {
    try { renderDocs(await authenticatedFetch(`/pm/library/products/${editing}/documents/${docId}`, { method: 'DELETE' })); loadProducts(); } catch (e) { note(e.message); }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    try {
      await loadLookups(); await loadProducts();
      [fMan, fCat, fInactive].forEach(el => el.addEventListener('change', loadProducts));
      let t; fSearch.addEventListener('input', () => { clearTimeout(t); t = setTimeout(loadProducts, 250); });
    } catch (e) { console.error(e); }
  });
</script>
@endsection
