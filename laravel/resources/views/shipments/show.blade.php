@extends('layouts.app')

@section('title', 'Shipment - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle"><a href="/shipments">Shipments</a></div>
          <h1 class="page-title"><span id="hdrNumber">Loading…</span> <span id="hdrStatus" class="ms-2"></span></h1>
          <div class="text-muted" id="hdrJob"></div>
        </div>
        <div class="col-auto ms-auto d-print-none btn-list" id="hdrActions"></div>
      </div>
    </div>
  </div>

  <main class="page-body">
    <div class="container-xl">
      <div class="row row-cards">
        <div class="col-lg-3">
          <div class="card"><div class="list-group list-group-flush" id="reportNav"></div></div>
        </div>
        <div class="col-lg-9">
          <div data-section="details"></div>
          <div data-section="pre" class="d-none"><div id="preCard"></div><div id="preResults"></div></div>
          <div data-section="pre-photos" class="d-none"></div>
          <div data-section="post" class="d-none"></div>
          <div data-section="post-photos" class="d-none"></div>
        </div>
      </div>
    </div>
  </main>
</div>
@endsection

@push('scripts')
@include('fleet._js')
@include('shipments._js')
<script>
  const SHIPMENT_ID = {{ (int) $shipmentId }};
  let S = null;
  let section = (location.hash || '#details').slice(1);
  let preFiles = [];            // {file, itemId|null}

  async function load() {
    S = await flApi('/shipments/' + SHIPMENT_ID);
    render();
  }

  function render() {
    document.getElementById('hdrNumber').textContent = S.shipment_number;
    document.getElementById('hdrStatus').innerHTML = shStatus(S.status);
    document.getElementById('hdrJob').textContent = S.job ? `${S.job.job_name} (${S.job.job_number})` : '';
    renderDetails();

    const acts = [];
    if (S.status === 'pre_check_done') acts.push(`<button class="btn btn-success" data-permission="shipments.manage" onclick="markShipped()"><i class="ti ti-truck me-1"></i>Mark shipped</button>`);
    if (!['delivered', 'delivered_issues', 'cancelled'].includes(S.status)) acts.push(`<button class="btn btn-outline-danger" data-permission="shipments.manage" onclick="cancelShipment()">Cancel</button>`);
    document.getElementById('hdrActions').innerHTML = acts.join('');

    renderPre();
    renderInspections();
    renderNav();
    showSection(section);
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  // ---- pre-shipment checklist form (phone friendly, with camera capture) ----
  function renderPre() {
    const el = document.getElementById('preCard');
    if (!['draft', 'pre_check_done'].includes(S.status)) { el.innerHTML = ''; return; }
    if (!S.pre_checklist.length) { el.innerHTML = '<div class="alert alert-warning">None of this shipment\'s types has a pre-shipment checklist yet.</div>'; return; }
    preFiles = [];
    el.innerHTML = `<form class="card mb-3" id="preForm" data-permission="shipments.inspect" novalidate>
      <div class="card-header"><h3 class="card-title">Pre-shipment inspection</h3></div>
      <div class="card-body">${S.pre_checklist.map(g => `<h4 class="mt-2">${esc(g.type_name)}</h4>` + g.items.map(itemHtml).join('')).join('')}
        <div class="mt-3"><label class="form-label">Other photos (whole load, strapping, labels…)</label>${photoBtn(null)}<div class="d-flex flex-wrap gap-2 mt-2" id="thumbs-general"></div></div>
        <div class="mt-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
      </div>
      <div class="card-footer text-end"><button type="submit" class="btn btn-primary">Save inspection</button></div></form>`;
    el.querySelectorAll('.photo-input').forEach(inp => inp.addEventListener('change', onPhotos));
    el.querySelector('form').addEventListener('submit', submitPre);
    el.querySelectorAll('[data-item-id] input[type=radio]').forEach(r => r.addEventListener('change', () => { r.closest('[data-item-id]').classList.remove('border-danger'); }));
  }

  function photoBtn(itemId) {
    return `<label class="btn btn-sm"><i class="ti ti-camera me-1"></i>Take / add photo<input type="file" class="d-none photo-input" accept="image/*" capture="environment" multiple data-item="${itemId ?? ''}"></label>`;
  }

  function itemHtml(i) {
    let input;
    if (i.input_type === 'pass_fail') {
      input = `<div class="btn-group w-100">${['pass', 'fail', 'na'].map(v => `<label class="btn btn-outline-${v === 'fail' ? 'danger' : v === 'pass' ? 'success' : 'secondary'}">
        <input type="radio" class="btn-check" name="v${i.id}" value="${v}">${v === 'na' ? 'N/A' : v[0].toUpperCase() + v.slice(1)}</label>`).join('')}</div>`;
    } else if (i.input_type === 'number') {
      input = `<div class="input-group"><input type="number" step="any" inputmode="decimal" class="form-control" name="v${i.id}">${i.unit ? `<span class="input-group-text">${esc(i.unit)}</span>` : ''}</div>`;
    } else if (i.input_type === 'choice') {
      input = `<select class="form-select" name="v${i.id}"><option value="">Select…</option>${(i.options || []).map(o => `<option>${esc(o)}</option>`).join('')}</select>`;
    } else input = `<input type="text" class="form-control" name="v${i.id}" maxlength="255">`;
    return `<div class="border rounded p-2 mb-2" data-item-id="${i.id}">
      <div class="fw-bold mb-1">${esc(i.label)}${i.required ? '' : ' <span class="text-muted fw-normal small">(optional)</span>'}${i.photo_required ? ' <i class="ti ti-camera text-muted" title="Photo needed"></i>' : ''}</div>
      ${input}
      <div class="d-flex flex-wrap gap-2 align-items-center mt-2">${photoBtn(i.id)}<div class="d-flex flex-wrap gap-2" id="thumbs-${i.id}"></div></div>
      <input type="text" class="form-control form-control-sm mt-2" name="c${i.id}" placeholder="Comment (optional)" maxlength="1000"></div>`;
  }

  // Phone photos are 3-8 MB; downscale before upload.
  function shrink(file, maxSide = 1600, quality = 0.8) {
    return new Promise(resolve => {
      const img = new Image(), url = URL.createObjectURL(file);
      img.onload = () => {
        const k = Math.min(1, maxSide / Math.max(img.width, img.height));
        const c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(b => resolve(b ? new File([b], 'photo.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', quality);
      };
      img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  async function onPhotos(e) {
    const itemId = e.target.dataset.item ? +e.target.dataset.item : null;
    for (const f of e.target.files) {
      const small = await shrink(f);
      preFiles.push({ file: small, itemId, url: URL.createObjectURL(small) });
    }
    e.target.value = '';
    drawThumbs();
  }

  function drawThumbs() {
    document.querySelectorAll('[id^=thumbs-]').forEach(t => { t.innerHTML = ''; });
    preFiles.forEach((p, idx) => {
      const t = document.getElementById('thumbs-' + (p.itemId ?? 'general'));
      if (t) t.insertAdjacentHTML('beforeend', `<div class="position-relative"><img src="${p.url}" style="height:56px;border-radius:4px">
        <button type="button" class="btn-close position-absolute top-0 end-0 bg-white" style="transform:scale(.7)" onclick="removePhoto(${idx})"></button></div>`);
    });
  }
  function removePhoto(idx) { preFiles.splice(idx, 1); drawThumbs(); }

  async function submitPre(e) {
    e.preventDefault();
    const form = e.target;
    const answers = {};
    form.querySelectorAll('[data-item-id]').forEach(box => {
      const id = box.dataset.itemId;
      const ctl = box.querySelector(`[name="v${id}"]:checked`) || box.querySelector(`select[name="v${id}"], input[name="v${id}"]:not([type=radio])`);
      const photos = preFiles.map((p, idx) => p.itemId === +id ? idx : -1).filter(x => x >= 0);
      answers[id] = { value: ctl ? ctl.value : '', comment: form.elements['c' + id].value, photos };
    });
    // Unanswered optional items are simply omitted; the server enforces required ones and photos.
    const fd = new FormData();
    fd.append('answers', JSON.stringify(answers));
    fd.append('notes', form.elements.notes.value);
    preFiles.forEach(p => fd.append('photos[]', p.file, 'photo.jpg'));
    const btn = form.querySelector('[type=submit]'); btn.disabled = true;
    try {
      await authenticatedUpload(`/shipments/${SHIPMENT_ID}/pre-inspection`, fd);
      showNotification('Pre-shipment inspection saved.', 'success');
      await load();
    } catch (err) {
      const msgs = err.data && err.data.errors ? Object.values(err.data.errors).flat() : [err.message || 'Could not save'];
      showNotification(msgs.slice(0, 3).join(' '), 'danger');
      btn.disabled = false;
    }
  }

  async function markShipped() { await flApi(`/shipments/${SHIPMENT_ID}/ship`, 'POST'); showNotification('Marked shipped. The crew can now inspect it on delivery in the field app.', 'success'); load(); }
  function cancelShipment() { flConfirm('Cancel this shipment?', async () => { await flApi(`/shipments/${SHIPMENT_ID}/cancel`, 'POST'); load(); }); }

  // ---- results ----
  function valueCell(i) {
    if (i.input_type === 'pass_fail') {
      const m = { pass: ['Pass', 'success'], fail: ['Fail', 'danger'], na: ['N/A', 'secondary'] }[i.value] || [i.value, 'secondary'];
      return flBadge(m[0], m[1]);
    }
    return esc(i.value) + (i.unit ? ' ' + esc(i.unit) : '');
  }

  const SECTIONS = [['details', 'Details', 'ti-info-circle'], ['pre', 'Pre-shipment inspection', 'ti-clipboard-check'],
    ['pre-photos', 'Pre-shipment photos', 'ti-camera'], ['post', 'Post-delivery inspection', 'ti-truck-delivery'],
    ['post-photos', 'Post-delivery photos', 'ti-photo']];

  const byPhase = ph => S.inspections.filter(n => n.phase === ph);
  const photoCount = ph => byPhase(ph).reduce((t, n) => t + n.photos.length, 0);

  function sectionBadge(key) {
    if (key === 'pre' || key === 'post') {
      const n = byPhase(key)[0];                          // newest first
      return n ? shResult(n.result) : flBadge(key === 'post' && S.status === 'shipped' ? 'Waiting' : 'None', 'secondary');
    }
    if (key.endsWith('-photos')) return flBadge(photoCount(key.replace('-photos', '')), 'secondary');
    return '';
  }

  function renderNav() {
    document.getElementById('reportNav').innerHTML = SECTIONS.map(([key, label, icon]) => `<a href="#${key}" data-nav="${key}"
      class="list-group-item list-group-item-action d-flex align-items-center gap-2"><i class="ti ${icon}"></i><span class="flex-grow-1">${label}</span>${sectionBadge(key)}</a>`).join('');
    document.querySelectorAll('[data-nav]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); history.replaceState(null, '', '#' + a.dataset.nav); showSection(a.dataset.nav); }));
  }

  function showSection(key) {
    if (!SECTIONS.some(x => x[0] === key)) key = 'details';
    section = key;
    document.querySelectorAll('[data-section]').forEach(p => p.classList.toggle('d-none', p.dataset.section !== key));
    document.querySelectorAll('[data-nav]').forEach(a => a.classList.toggle('active', a.dataset.nav === key));
  }

  function renderDetails() {
    document.querySelector('[data-section="details"]').innerHTML = `<div class="card"><div class="card-header"><h3 class="card-title">Details</h3></div><div class="card-body"><dl class="row mb-0">
      <dt class="col-sm-3">Job</dt><dd class="col-sm-9">${S.job ? esc(S.job.job_name) + ' <span class="text-muted">' + esc(S.job.job_number) + '</span>' : '—'}</dd>
      <dt class="col-sm-3">Ship date</dt><dd class="col-sm-9">${flDate(S.ship_date)}</dd>
      <dt class="col-sm-3">Carrier</dt><dd class="col-sm-9">${esc(S.carrier || '—')}</dd>
      <dt class="col-sm-3">Destination</dt><dd class="col-sm-9">${esc(S.destination || '—')}</dd>
      <dt class="col-sm-3">Loads</dt><dd class="col-sm-9">${S.loads.map(l => `${flBadge(l.type.name, 'azure')}${l.piece_count ? ' ×' + l.piece_count : ''}`).join(' &nbsp; ')}</dd>
      <dt class="col-sm-3">Notes</dt><dd class="col-sm-9">${esc(S.notes || '—')}</dd></dl></div></div>`;
  }

  function renderPhotos(phase) {
    const el = document.querySelector(`[data-section="${phase}-photos"]`);
    const list = byPhase(phase).filter(n => n.photos.length);
    el.innerHTML = list.map(n => {
      const label = id => (n.items.find(i => i.id === id) || {}).label;
      return `<div class="card mb-3"><div class="card-header"><h3 class="card-title">${phase === 'pre' ? 'Pre-shipment' : 'Post-delivery'} photos ${shResult(n.result)}</h3>
        <div class="card-actions text-muted small">${esc(String(n.performed_at).replace('T', ' ').substring(0, 16))}</div></div>
        <div class="card-body"><div class="row g-3">${n.photos.map(p => `<div class="col-6 col-md-4"><a href="${shPhotoUrl(S.id, p.id)}" target="_blank">
          <img src="${shPhotoUrl(S.id, p.id)}" class="img-fluid rounded border" style="width:100%;height:160px;object-fit:cover" loading="lazy"></a>
          <div class="small text-muted mt-1">${esc(label(p.item_id) || 'General')}</div></div>`).join('')}</div></div></div>`;
    }).join('') || `<div class="card"><div class="card-body text-muted">No ${phase === 'pre' ? 'pre-shipment' : 'post-delivery'} photos yet.</div></div>`;
  }

  function inspectionCard(n) {
    const byItem = {};
    n.items.forEach(i => { (byItem[i.type_name || 'Other'] ||= []).push(i); });
    return `<div class="card mb-3"><div class="card-header"><h3 class="card-title">${n.phase === 'pre' ? 'Pre-shipment' : 'Post-delivery'} inspection ${shResult(n.result)}</h3>
      <div class="card-actions text-muted small">${esc(n.performer?.name || n.submitted_by_external || '')} · ${esc(String(n.performed_at).replace('T', ' ').substring(0, 16))}${n.source === 'field_app' ? ' · ' + flBadge('Field app', 'purple') : ''}</div></div>
      <div class="card-body">${Object.entries(byItem).map(([tn, items]) => `<h4>${esc(tn)}</h4><div class="table-responsive"><table class="table table-sm table-vcenter"><tbody>${items.map(i => `<tr>
        <td style="width:40%">${esc(i.label)}${i.critical ? ' <span class="badge bg-red-lt">critical</span>' : ''}</td>
        <td>${valueCell(i)}</td><td class="text-muted">${esc(i.comment || '')}</td>
        <td class="text-end text-muted small text-nowrap">${i.photos.length ? `<i class="ti ti-camera"></i> ${i.photos.length}` : ''}</td></tr>`).join('')}</tbody></table></div>`).join('')}
        ${n.notes ? `<p class="mt-2 mb-0"><b>Notes:</b> ${esc(n.notes)}</p>` : ''}</div></div>`;
  }

  function renderInspections() {
    document.getElementById('preResults').innerHTML = byPhase('pre').map(inspectionCard).join('');
    let post = byPhase('post').map(inspectionCard).join('');
    if (!post) {
      post = S.status === 'shipped' ? '<div class="alert alert-info">Waiting for the post-delivery inspection from the field app.</div>'
        : `<div class="card"><div class="card-body text-muted">${['draft', 'pre_check_done'].includes(S.status) ? 'This shipment has not shipped yet.' : 'No post-delivery inspection recorded.'}</div></div>`;
    }
    document.querySelector('[data-section="post"]').innerHTML = post;
    renderPhotos('pre');
    renderPhotos('post');
  }

  document.addEventListener('DOMContentLoaded', load);
</script>
@endpush
