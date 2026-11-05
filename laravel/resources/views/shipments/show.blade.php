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
          <div id="verifyBanner"></div>
          <div data-section="details"></div>
          <div data-section="pre" class="d-none"><div id="preCard"></div><div id="preResults"></div></div>
          <div data-section="pre-photos" class="d-none"></div>
          <div data-section="post" class="d-none"><div id="postCard"></div><div id="postResults"></div></div>
          <div data-section="post-photos" class="d-none"></div>
          <div data-section="tickets" class="d-none"></div>
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
    document.getElementById('hdrJob').textContent = S.jobs.map(j => `${j.job_name} (${j.job_number})`).concat(S.custom_jobs || []).join(', ');
    renderDetails();
    renderVerifyBanner();
    renderTickets();

    const acts = [];
    if (S.status === 'awaiting_verification') acts.push(`<button class="btn btn-success" data-permission="shipments.manage" onclick="verifyDelivery()"><i class="ti ti-circle-check me-1"></i>Verify delivery</button>`);
    if (S.status === 'pre_check_done') acts.push(`<button class="btn btn-success" data-permission="shipments.manage" onclick="markShipped()"><i class="ti ti-truck me-1"></i>Mark shipped</button>`);
    if (!['awaiting_verification', 'delivered', 'delivered_issues', 'cancelled'].includes(S.status)) acts.push(`<button class="btn btn-outline-danger" data-permission="shipments.manage" onclick="cancelShipment()">Cancel</button>`);
    if (!['awaiting_verification', 'delivered', 'delivered_issues', 'cancelled'].includes(S.status)) acts.unshift(`<button class="btn" data-permission="shipments.manage" onclick="shipmentForm(S)"><i class="ti ti-edit me-1"></i>Edit</button>`);
    acts.unshift(`<button class="btn" onclick="openReport(this)"><i class="ti ti-file-type-pdf me-1"></i>Report PDF</button>`);
    document.getElementById('hdrActions').innerHTML = acts.join('');

    renderInspectForm('pre');
    renderInspectForm('post');
    renderInspections();
    renderNav();
    showSection(section);
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
  }

  // ---- pre-shipment checklist form (phone friendly, with camera capture) ----
  function renderInspectForm(phase) {
    const el = document.getElementById(phase + 'Card');
    const open = phase === 'pre' ? ['draft', 'pre_check_done'].includes(S.status) : S.status === 'shipped';
    if (!open) { el.innerHTML = ''; return; }
    const checklist = S[phase + '_checklist'];
    const label = phase === 'pre' ? 'Pre-shipment' : 'Delivery';
    if (!checklist.length) { el.innerHTML = `<div class="alert alert-warning">None of this shipment\'s types has a ${phase === 'pre' ? 'pre-shipment' : 'post-delivery'} checklist yet.</div>`; return; }
    preFiles = [];
    el.innerHTML = `<form class="card mb-3" id="${phase}Form" data-permission="shipments.inspect" novalidate>
      <div class="card-header"><h3 class="card-title">${phase === 'pre' ? 'Pre-shipment inspection' : 'Record delivery — condition on arrival'}</h3></div>
      <div class="card-body">${checklist.map(g => `<h4 class="mt-2">${esc(g.type_name)}</h4>` + g.items.map(itemHtml).join('')).join('')}
        <div class="mt-3"><label class="form-label">${phase === 'pre' ? 'Other photos (whole load, strapping, labels…)' : 'Delivery photos (load as delivered, any damage, signed paperwork…)'}</label>${photoBtn(null)}<div class="d-flex flex-wrap gap-2 mt-2" id="thumbs-general"></div></div>
        <div class="mt-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
      </div>
      <div class="card-footer text-end"><button type="submit" class="btn btn-primary">${phase === 'pre' ? 'Save inspection' : 'Save delivery inspection'}</button></div></form>`;
    el.querySelectorAll('.photo-input').forEach(inp => inp.addEventListener('change', onPhotos));
    el.querySelector('form').addEventListener('submit', ev => submitInspection(ev, phase));
    el.querySelectorAll('[data-item-id] input[type=radio]').forEach(r => r.addEventListener('change', () => { r.closest('[data-item-id]').classList.remove('border-danger'); }));
  }

  function photoBtn(itemId) {
    return `<label class="btn btn-sm"><i class="ti ti-camera me-1"></i>Add photos<input type="file" class="d-none photo-input" accept="image/*" multiple data-item="${itemId ?? ''}"></label>`;
  }

  function itemHtml(i) {
    let input;
    if (i.input_type === 'pass_fail') {
      input = `<div class="btn-group w-100">${['pass', 'fail', 'na'].map(v => `<input type="radio" class="btn-check" name="v${i.id}" id="v${i.id}-${v}" value="${v}" autocomplete="off">
        <label for="v${i.id}-${v}" class="btn btn-outline-${v === 'fail' ? 'danger' : v === 'pass' ? 'success' : 'secondary'}">${v === 'na' ? 'N/A' : v[0].toUpperCase() + v.slice(1)}</label>`).join('')}</div>`;
    } else if (i.input_type === 'number') {
      input = `<div class="input-group"><input type="number" step="any" inputmode="decimal" class="form-control" name="v${i.id}">${i.unit ? `<span class="input-group-text">${esc(i.unit)}</span>` : ''}</div>`;
    } else if (i.input_type === 'choice') {
      input = `<select class="form-select" name="v${i.id}"><option value="">Select…</option>${(i.options || []).map(o => `<option>${esc(o)}</option>`).join('')}</select>`;
    } else if (i.input_type === 'photo') {
      input = '';
    } else input = `<input type="text" class="form-control" name="v${i.id}" maxlength="255">`;
    return `<div class="border rounded p-2 mb-2" data-item-id="${i.id}" data-type="${i.input_type}">
      <div class="fw-bold mb-1">${esc(i.label)}${i.required ? '' : ' <span class="text-muted fw-normal small">(optional)</span>'}${i.photo_required || i.input_type === 'photo' ? ' <i class="ti ti-camera text-muted" title="Photo needed"></i>' : ''}</div>
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

  async function submitInspection(e, phase) {
    e.preventDefault();
    const form = e.target;
    const answers = {};
    form.querySelectorAll('[data-item-id]').forEach(box => {
      const id = box.dataset.itemId;
      const ctl = box.querySelector(`[name="v${id}"]:checked`) || box.querySelector(`select[name="v${id}"], input[name="v${id}"]:not([type=radio])`);
      const photos = preFiles.map((p, idx) => p.itemId === +id ? idx : -1).filter(x => x >= 0);
      const isPhoto = box.dataset.type === 'photo';
      answers[id] = { value: isPhoto ? String(photos.length) : (ctl ? ctl.value : ''), comment: form.elements['c' + id].value, photos };
    });
    // Unanswered optional items are simply omitted; the server enforces required ones and photos.
    const fd = new FormData();
    fd.append('answers', JSON.stringify(answers));
    fd.append('notes', form.elements.notes.value);
    preFiles.forEach(p => fd.append('photos[]', p.file, 'photo.jpg'));
    const btn = form.querySelector('[type=submit]'); btn.disabled = true;
    try {
      await authenticatedUpload(`/shipments/${SHIPMENT_ID}/${phase}-inspection`, fd);
      showNotification(phase === 'pre' ? 'Pre-shipment inspection saved.' : 'Delivery recorded.', 'success');
      await load();
    } catch (err) {
      const msgs = err.data && err.data.errors ? Object.values(err.data.errors).flat() : [err.message || 'Could not save'];
      showNotification(msgs.slice(0, 3).join(' '), 'danger');
      btn.disabled = false;
    }
  }

  // Session-cookie auth: fetch the PDF with credentials, then open the blob.
  async function openReport(btn) {
    btn.disabled = true;
    try {
      const res = await fetch(`/api/v1/shipments/${SHIPMENT_ID}/report`, { credentials: 'include', headers: { Accept: 'application/pdf' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const url = URL.createObjectURL(await res.blob());
      window.open(url, '_blank');
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (e) { showNotification('Could not build the report.', 'danger'); }
    btn.disabled = false;
  }

  async function markShipped() { await flApi(`/shipments/${SHIPMENT_ID}/ship`, 'POST'); showNotification('Marked shipped. The crew can now inspect it on delivery in the field app.', 'success'); load(); }
  function cancelShipment() {
    flModal('Cancel ' + S.shipment_number,
      `<div class="col-12"><div class="alert alert-warning mb-0">This takes the shipment off the queue and hides it from the field app.</div></div>` +
      flText('reason', 'Reason for cancelling (required)', '', 3),
      async f => {
        const reason = (f.querySelector('[name=reason]').value || '').trim();
        if (reason.length < 3) { showNotification('Enter a reason for cancelling.', 'warning'); throw new Error('no reason'); }
        await flApi(`/shipments/${SHIPMENT_ID}/cancel`, 'POST', { reason });
        await load();
      }, 'Cancel shipment');
  }

  // ---- delivery verification & signed tickets ----
  const docKb = b => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
  const docUrl = d => `/api/v1/shipments/${S.id}/documents/${d.id}`;

  function renderVerifyBanner() {
    const el = document.getElementById('verifyBanner');
    if (S.status === 'awaiting_verification') {
      const n = byPhase('post')[0];
      const bad = n && n.result !== 'pass';
      el.innerHTML = `<div class="alert alert-${bad ? 'warning' : 'info'} d-flex align-items-center gap-3">
        <i class="ti ti-clipboard-check fs-2"></i>
        <div class="flex-grow-1"><b>Delivery received &mdash; waiting for office verification.</b><br>
          <span class="small">${bad ? 'The crew reported problems on delivery. Review the inspection, ' : 'Review the delivery inspection, '}upload the signed shipping ticket, then verify.
          ${(S.documents || []).length ? '' : '<b>No signed ticket has been uploaded yet.</b>'}</span></div>
        <button class="btn btn-success" data-permission="shipments.manage" onclick="verifyDelivery()">Verify delivery</button></div>`;
    } else if (S.verified_at) {
      el.innerHTML = `<div class="alert alert-success mb-3"><i class="ti ti-circle-check me-1"></i><b>Verified</b> by ${esc(S.verifier?.name || 'the office')} on ${flDate(S.verified_at)}${S.verification_notes ? ' &mdash; ' + esc(S.verification_notes) : ''}</div>`;
    } else el.innerHTML = '';
  }

  function verifyDelivery() {
    const none = !(S.documents || []).length;
    flModal('Verify ' + S.shipment_number,
      `<div class="col-12"><p class="mb-0">Confirm the delivery has been reviewed. The shipment will be closed out as ${byPhase('post')[0] && byPhase('post')[0].result !== 'pass' ? '<b>delivered with issues</b>' : '<b>delivered</b>'}.</p></div>` +
      (none ? `<div class="col-12"><div class="alert alert-warning mb-0">No signed shipping ticket has been uploaded for this shipment.</div></div>` : '') +
      flText('notes', 'Verification notes (optional)', '', 3),
      async f => {
        await flApi(`/shipments/${SHIPMENT_ID}/verify`, 'POST', { notes: (f.querySelector('[name=notes]').value || '').trim() || null });
        showNotification('Delivery verified.', 'success');
        await load();
      }, 'Verify delivery');
  }

  function renderTickets() {
    const docs = S.documents || [];
    const canUpload = !['draft', 'pre_check_done', 'cancelled'].includes(S.status);
    const locked = ['delivered', 'delivered_issues'].includes(S.status);
    document.querySelector('[data-section="tickets"]').innerHTML = `<div class="card"><div class="card-header"><h3 class="card-title">Signed shipping tickets</h3></div>
      <div class="card-body">${canUpload ? `<div data-permission="shipments.manage" class="mb-3"><label class="btn btn-primary"><i class="ti ti-upload me-1"></i>Upload PDF or photo
          <input type="file" class="d-none" id="ticketInput" accept="application/pdf,image/*" multiple></label>
          <span class="text-muted small ms-2">PDF, JPG, PNG or WebP, up to 15 MB each.</span></div>` : '<div class="text-muted mb-3">Tickets can be attached once the shipment has shipped.</div>'}
        ${docs.length ? `<div class="list-group list-group-flush">${docs.map(d => `<div class="list-group-item d-flex align-items-center gap-2 px-0">
          <i class="ti ${d.file_mime === 'application/pdf' ? 'ti-file-type-pdf text-danger' : 'ti-photo text-azure'} fs-2"></i>
          <div class="flex-grow-1"><a href="${docUrl(d)}" target="_blank" rel="noopener">${esc(d.original_name)}</a>
            <div class="small text-muted">${docKb(d.file_size)} &middot; ${esc(d.uploader?.name || '')} &middot; ${flDate(d.created_at)}</div></div>
          ${locked ? '' : `<button class="btn btn-sm btn-outline-danger" data-permission="shipments.manage" onclick="deleteTicket(${d.id})"><i class="ti ti-trash"></i></button>`}</div>`).join('')}</div>`
          : '<div class="text-muted">No tickets uploaded yet.</div>'}</div></div>`;
    document.getElementById('ticketInput')?.addEventListener('change', uploadTickets);
  }

  async function uploadTickets(e) {
    const files = [...e.target.files]; e.target.value = '';
    if (!files.length) return;
    const fd = new FormData();
    files.forEach(f => fd.append('files[]', f));
    try {
      await authenticatedUpload(`/shipments/${SHIPMENT_ID}/documents`, fd);
      showNotification(files.length > 1 ? 'Tickets uploaded.' : 'Ticket uploaded.', 'success');
      await load();
    } catch (err) {
      const msgs = err.data && err.data.errors ? Object.values(err.data.errors).flat() : [err.message || 'Upload failed'];
      showNotification(msgs.slice(0, 3).join(' '), 'danger');
    }
  }

  async function deleteTicket(id) {
    if (!confirm('Remove this document?')) return;
    await flApi(`/shipments/${SHIPMENT_ID}/documents/${id}`, 'DELETE');
    await load();
  }

  // ---- results ----
  function valueCell(i) {
    if (i.input_type === 'pass_fail') {
      const m = { pass: ['Pass', 'success'], fail: ['Fail', 'danger'], na: ['N/A', 'secondary'] }[i.value] || [i.value, 'secondary'];
      return flBadge(m[0], m[1]);
    }
    if (i.input_type === 'photo') return `<i class="ti ti-camera"></i> ${esc(i.value)} photo${i.value === '1' ? '' : 's'}`;
    return esc(i.value) + (i.unit ? ' ' + esc(i.unit) : '');
  }

  const SECTIONS = [['details', 'Details', 'ti-info-circle'], ['pre', 'Pre-shipment inspection', 'ti-clipboard-check'],
    ['pre-photos', 'Pre-shipment photos', 'ti-camera'], ['post', 'Post-delivery inspection', 'ti-truck-delivery'],
    ['post-photos', 'Post-delivery photos', 'ti-photo'], ['tickets', 'Signed tickets', 'ti-file-certificate']];

  const byPhase = ph => S.inspections.filter(n => n.phase === ph);
  const photoCount = ph => byPhase(ph).reduce((t, n) => t + n.photos.length, 0);

  function sectionBadge(key) {
    if (key === 'pre' || key === 'post') {
      const n = byPhase(key)[0];                          // newest first
      return n ? shResult(n.result) : flBadge(key === 'post' && S.status === 'shipped' ? 'Waiting' : 'None', 'secondary');
    }
    if (key.endsWith('-photos')) return flBadge(photoCount(key.replace('-photos', '')), 'secondary');
    if (key === 'tickets') return flBadge((S.documents || []).length, (S.documents || []).length ? 'success' : 'secondary');
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

  const placeLine = (loc, addr) => !loc ? '—' : esc(loc.name) + (addr ? ` <span class="text-muted">· ${esc(addr)}</span>` : '');

  function renderDetails() {
    document.querySelector('[data-section="details"]').innerHTML = `<div class="card"><div class="card-header"><h3 class="card-title">Details</h3></div><div class="card-body"><dl class="row mb-0">
      <dt class="col-sm-3">${S.jobs.length + (S.custom_jobs || []).length > 1 ? 'Jobs' : 'Job'}</dt><dd class="col-sm-9">${(S.jobs.length || (S.custom_jobs || []).length) ? S.jobs.map(j => esc(j.job_name) + ' <span class="text-muted">' + esc(j.job_number) + '</span>').concat((S.custom_jobs || []).map(n => esc(n) + ' <span class="text-muted">(custom)</span>')).join('<br>') : '—'}</dd>
      ${S.status === 'cancelled' ? `<dt class="col-sm-3 text-danger">Cancelled</dt><dd class="col-sm-9">${esc(S.cancel_reason || '—')}<div class="text-muted small">${S.canceller ? esc(S.canceller.name) + ' · ' : ''}${flDate(S.cancelled_at)}</div></dd>` : ''}
      <dt class="col-sm-3">Pickup</dt><dd class="col-sm-9">${placeLine(S.pickup_location, S.pickup_address)}</dd>
      <dt class="col-sm-3">Drop off</dt><dd class="col-sm-9">${placeLine(S.dropoff_location, S.dropoff_address)}</dd>
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
      post = S.status === 'shipped' ? '<div class="alert alert-info">Waiting for the post-delivery inspection — record it below, or the crew can submit it from the field app.</div>'
        : `<div class="card"><div class="card-body text-muted">${['draft', 'pre_check_done'].includes(S.status) ? 'This shipment has not shipped yet.' : 'No post-delivery inspection recorded.'}</div></div>`;
    }
    document.getElementById('postResults').innerHTML = post;
    renderPhotos('pre');
    renderPhotos('post');
  }

  document.addEventListener('DOMContentLoaded', load);
</script>
@endpush
