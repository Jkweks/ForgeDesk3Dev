@extends('layouts.app')

@section('title', 'Change History - Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="page-pretitle">Project Management</div>
      <h1 class="page-title">Change History</h1>
    </div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div class="card">
        <div class="card-header">
          <div class="row g-2 w-100">
            <div class="col-md-3"><select class="form-select" id="fType"><option value="">All records</option>
              <option value="job">Jobs</option><option value="po">Job POs</option><option value="submittal">Submittals</option>
              <option value="vendor">Vendors</option><option value="product">Library products</option><option value="manufacturer">Manufacturers</option></select></div>
            <div class="col-md-2"><select class="form-select" id="fAction"><option value="">Any change</option><option value="created">Created</option><option value="updated">Updated</option><option value="deleted">Deleted</option></select></div>
            <div class="col-md-3"><input type="search" class="form-control" id="fSearch" placeholder="Search record (PO number, job...)"></div>
            <div class="col-md-2"><input type="date" class="form-control" id="fFrom"></div>
            <div class="col-md-2"><input type="date" class="form-control" id="fTo"></div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>When</th><th>Who</th><th>Record</th><th>Change</th></tr></thead>
            <tbody id="hBody"><tr><td colspan="4" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
        <div class="card-footer d-flex"><button class="btn" id="hMore" style="display:none" onclick="loadMore()">Load more</button></div>
      </div>
    </div>
  </main>
</div>
<script>
  const esc = (s) => escapeHtml(String(s ?? ''));
  const TYPES = { job: 'Job', po: 'Job PO', submittal: 'Submittal', vendor: 'Vendor', product: 'Product', manufacturer: 'Manufacturer' };
  const BADGE = { created: 'bg-green-lt', updated: 'bg-blue-lt', deleted: 'bg-red-lt' };
  let page = 1, hasMore = false;

  function row(l) {
    const fields = l.fields.map(f => `<div class="small"><span class="text-muted">${esc(f.field)}:</span> ${l.action === 'updated' ? `${f.old_value === null ? '<em>empty</em>' : esc(f.old_value)} &rarr; ` : ''}${f.new_value === null ? '<em>empty</em>' : esc(f.new_value)}</div>`).join('');
    return `<tr><td class="text-nowrap">${esc((l.created_at || '').replace('T', ' ').slice(0, 16))}</td>
      <td>${esc(l.user ? (l.user.name || l.user.email) : 'System')}</td>
      <td>${esc(TYPES[l.entity_type] || l.entity_type)}<div class="text-muted small">${esc(l.entity_label)}</div></td>
      <td><span class="badge ${BADGE[l.action]}">${esc(l.action)}</span>${fields}</td></tr>`;
  }

  async function load(reset) {
    if (reset) page = 1;
    const q = new URLSearchParams({ page });
    [['entity_type', fType], ['action', fAction], ['search', fSearch], ['from', fFrom], ['to', fTo]].forEach(([k, el]) => el.value && q.set(k, el.value));
    const res = await authenticatedFetch('/pm/history?' + q);
    const html = res.data.map(row).join('');
    if (reset) hBody.innerHTML = html || '<tr><td colspan="4" class="text-muted">No changes recorded.</td></tr>'; else hBody.insertAdjacentHTML('beforeend', html);
    hasMore = res.current_page < res.last_page; hMore.style.display = hasMore ? '' : 'none';
  }
  function loadMore() { page++; load(false); }

  document.addEventListener('DOMContentLoaded', () => {
    [fType, fAction, fFrom, fTo].forEach(el => el.addEventListener('change', () => load(true)));
    let t; fSearch.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => load(true), 250); });
    load(true).catch(console.error);
  });
</script>
@endsection
