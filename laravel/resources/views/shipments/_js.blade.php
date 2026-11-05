<script>
  // Shared helpers for the Shipments pages (builds on the fleet helpers: flModal, flApi, flField, ...).
  const SH = {
    STATUS: {
      draft: ['Draft', 'secondary'], pre_check_done: ['Pre-check done', 'blue'], shipped: ['Shipped', 'cyan'], awaiting_verification: ['Awaiting verification', 'orange'],
      delivered: ['Delivered', 'success'], delivered_issues: ['Delivered – issues', 'danger'], cancelled: ['Cancelled', 'secondary'],
    },
    RESULT: { pass: ['Pass', 'success'], pass_with_issues: ['Pass with issues', 'warning'], fail: ['Fail', 'danger'] },
    INPUTS: { pass_fail: 'Pass / Fail / N/A', number: 'Number', text: 'Text', choice: 'Choice list', photo: 'Photos' },
  };
  const shStatus = s => { const m = SH.STATUS[s] || [s, 'secondary']; return flBadge(m[0], m[1]); };
  const shResult = s => { const m = SH.RESULT[s] || [s, 'secondary']; return flBadge(m[0], m[1]); };
  const shPhotoUrl = (shipmentId, photoId) => `/api/v1/shipments/${shipmentId}/photos/${photoId}`;

  /** New-shipment / edit-shipment modal. `S` = existing shipment detail (edit) or null (create); `preJob` pre-selects a job on create. */
  async function shipmentForm(S = null, preJob = null) {
    const onLoads = new Map((S ? S.loads : []).map(l => [l.shipment_type_id, l]));
    const shTypes = (await flApi('/shipment-types')).filter(t => t.is_active || onLoads.has(t.id));
    const locs = await flApi('/shipments/locations');
    const place = {
      pickup: { id: S?.pickup_location_id || '', address: S?.pickup_address || '', lat: S?.pickup_lat ?? null, lng: S?.pickup_lng ?? null, src: S?.pickup_address ? 'existing' : '' },
      dropoff: { id: S?.dropoff_location_id || '', address: S?.dropoff_address || '', lat: S?.dropoff_lat ?? null, lng: S?.dropoff_lng ?? null, src: S?.dropoff_address ? 'existing' : '' },
    };
    const locOf = id => locs.find(l => String(l.id) === String(id));
    const placeHtml = (which, title) => {
      const opts = locs.filter(l => l.kind === 'both' || l.kind === which);
      return `<div class="col-md-6"><label class="form-label">${title}</label>
        <select class="form-select" id="loc_${which}" onchange="placeChange('${which}', this.value)">
          <option value="">Choose…</option>${opts.map(l => `<option value="${l.id}" ${String(l.id) === String(place[which].id) ? 'selected' : ''}>${esc(l.name)}</option>`).join('')}</select>
        <div id="locBody_${which}" class="mt-1"></div></div>`;
    };
    const isJobsite = l => l && l.requires_address && !l.saves_visits;
    const drawPlace = which => {
      const el = document.getElementById('locBody_' + which); if (!el) return;
      const p = place[which], l = locOf(p.id);
      if (!l) { el.innerHTML = ''; return; }
      if (!l.requires_address) {
        el.innerHTML = l.address ? `<div class="small text-muted">${esc(l.address)}</div>`
          : `<div class="small text-warning">No address saved for ${esc(l.name)} yet, so drivers won&rsquo;t get a map link.</div>`;
        return;
      }
      let top = '', bottom = '';
      if (isJobsite(l)) {
        const withAddr = [...picked.values()].filter(j => j.site_address);
        if (p.src && p.src.startsWith('job:') && !withAddr.some(j => 'job:' + j.id === p.src)) { p.address = ''; p.lat = p.lng = null; p.src = ''; }
        if (!p.address && withAddr.length) { p.address = withAddr[0].site_address; p.lat = p.lng = null; p.src = 'job:' + withAddr[0].id; }
        top = withAddr.map(j => `<label class="form-check small mb-0"><input type="radio" class="form-check-input" name="site_${which}" ${p.src === 'job:' + j.id ? 'checked' : ''}
          onchange="placeFromJob('${which}', ${j.id})"><span class="form-check-label">${esc(j.job_name)} &middot; ${esc(j.site_address)}</span></label>`).join('')
          || (picked.size ? '<div class="small text-muted">The selected job has no site address on file. Search for it below.</div>' : '<div class="small text-muted">Select a job above to use its site address, or search for one below.</div>');
        const noAddr = [...picked.values()].filter(j => !j.site_address);
        if (p.src === 'search' && noAddr.length) {
          bottom = `<div class="small mt-1">Save this address to job:
            <select class="form-select form-select-sm d-inline-block w-auto" onchange="place.${which}.saveJob = this.value">
              <option value="">Don&rsquo;t save</option>${noAddr.map(j => `<option value="${j.id}" ${String(p.saveJob) === String(j.id) ? 'selected' : ''}>${esc(j.job_name)}</option>`).join('')}</select></div>`;
        }
      } else if (p.src === 'search') {
        bottom = `<label class="form-label small mb-0 mt-1">Save as (business name)</label>
          <input type="text" class="form-control form-control-sm" maxlength="120" value="${esc(p.newName || '')}" oninput="place.${which}.newName = this.value">`;
      }
      el.innerHTML = top + `<input type="text" class="form-control form-control-sm mt-1" placeholder="${isJobsite(l) ? 'Or search for a different address…' : 'Search for a business or address…'}" autocomplete="off"
          oninput="placeSearch('${which}', this.value)"><div id="locHits_${which}" class="list-group mt-1" style="max-height:9rem;overflow:auto"></div>
        <div class="small mt-1 ${p.address ? '' : 'text-muted'}">${p.address ? '<i class="ti ti-map-pin text-success"></i> ' + esc(p.address) : 'No address picked yet.'}</div>` + bottom;
    };
    window.place = place;
    window.placeFromJob = (which, jobId) => {
      const j = picked.get(jobId); if (!j) return;
      place[which] = { ...place[which], address: j.site_address, lat: null, lng: null, src: 'job:' + jobId, saveJob: '' };
      drawPlace(which);
    };
    window.placeChange = (which, id) => {
      if (String(place[which].id) !== String(id)) place[which] = { id, address: '', lat: null, lng: null, src: '' };
      drawPlace(which);
    };
    const pt = {};
    window.placeSearch = (which, v) => {
      clearTimeout(pt[which]);
      const el = document.getElementById('locHits_' + which);
      if (v.trim().length < 3) { if (el) el.innerHTML = ''; return; }
      pt[which] = setTimeout(async () => {
        let hits = [];
        try { hits = await authenticatedFetch('/shipments/address-search?q=' + encodeURIComponent(v.trim())); } catch (e) { showNotification(e.message || 'Address search failed', 'warning'); return; }
        window._placeHits = window._placeHits || {}; window._placeHits[which] = hits;
        const box = document.getElementById('locHits_' + which); if (!box) return;
        box.innerHTML = hits.map((h, i) => `<a href="#" class="list-group-item list-group-item-action py-1 small" onclick="event.preventDefault(); placePick('${which}', ${i})">${esc(h.label)}</a>`).join('')
          || '<div class="list-group-item small text-muted">No matches. Try adding the city or state.</div>';
      }, 700);
    };
    window.placePick = (which, i) => {
      const h = window._placeHits[which][i];
      place[which] = { ...place[which], address: h.label, lat: h.lat, lng: h.lng, src: 'search', newName: (h.name || h.label.split(',')[0] || '').trim() };
      drawPlace(which);
    };
    setTimeout(() => { drawPlace('pickup'); drawPlace('dropoff'); }, 0);
    const picked = new Map((S ? S.jobs : []).map(j => [j.id, j]));
    const customs = [...(S ? S.custom_jobs || [] : [])];
    let lastSearch = '';
    let jobs = await flApi('/shipments/job-options' + (preJob ? '?id=' + encodeURIComponent(preJob) : ''));
    if (preJob && !S) { const j = jobs.find(x => String(x.id) === preJob); if (j) picked.set(j.id, j); jobs = await flApi('/shipments/job-options'); }
    const drawPicked = () => {
      const el = document.getElementById('jobPicked'); if (!el) return;
      el.innerHTML = (picked.size || customs.length)
        ? [...picked.values()].map(j => `<span class="badge bg-blue-lt me-1 mb-1">${esc(j.job_number)} · ${esc(j.job_name)}
          <a href="#" class="ms-1 text-reset" onclick="event.preventDefault(); jobToggle(${j.id}, false)">&times;</a></span>`).join('')
          + customs.map((n, i) => `<span class="badge bg-orange-lt me-1 mb-1">${esc(n)} (custom)
          <a href="#" class="ms-1 text-reset" onclick="event.preventDefault(); customRemove(${i})">&times;</a></span>`).join('')
        : '<span class="text-muted small">No jobs selected.</span>';
    };
    const drawList = list => {
      jobs = list; const el = document.getElementById('jobList'); if (!el) return;
      el.innerHTML = list.map(j => `<label class="list-group-item py-1"><input type="checkbox" class="form-check-input me-2" ${picked.has(j.id) ? 'checked' : ''}
        onchange="jobToggle(${j.id}, this.checked)">${esc(j.job_number)} · ${esc(j.job_name)}</label>`).join('') || '<div class="list-group-item text-muted">No matching jobs.</div>';
      const t = lastSearch.trim();
      if (t && !customs.some(c => c.toLowerCase() === t.toLowerCase())) {
        el.innerHTML += `<a href="#" class="list-group-item list-group-item-action py-1 text-orange" onclick="event.preventDefault(); customAdd()">
          <i class="ti ti-plus"></i> Use &ldquo;${esc(t)}&rdquo; as a custom job name</a>`;
      }
    };
    window.customAdd = () => {
      const t = lastSearch.trim(); if (!t) return;
      customs.push(t.slice(0, 120));
      const inp = document.getElementById('jobSearchInput'); if (inp) inp.value = '';
      lastSearch = ''; drawPicked(); jobSearch('');
    };
    window.customRemove = i => { customs.splice(i, 1); drawPicked(); drawList(jobs); };
    window.jobToggle = (id, on) => {
      const j = jobs.find(x => x.id === id) || picked.get(id);
      if (on && j) picked.set(id, j); else picked.delete(id);
      drawPicked(); drawList(jobs); drawPlace('dropoff');
    };
    let jt; window.jobSearch = v => { lastSearch = v; clearTimeout(jt); drawList(jobs); jt = setTimeout(async () => { const r = await flApi('/shipments/job-options?search=' + encodeURIComponent(v)); if (v === lastSearch) drawList(r); }, 250); };
    setTimeout(() => { drawPicked(); drawList(jobs); }, 0);
    const MAIN = ['metal', 'glass', 'doors'];
    const main = shTypes.filter(t => MAIN.includes(t.name.trim().toLowerCase()));
    const rest = shTypes.filter(t => !main.includes(t));
    const restOn = rest.some(t => onLoads.has(t.id));
    const loadRow = list => list.map(t => `<div class="row g-2 align-items-center mb-1">
        <div class="col"><label class="form-check mb-0"><input type="checkbox" class="form-check-input load-cb" value="${t.id}" ${onLoads.has(t.id) ? 'checked' : ''}><span class="form-check-label">${esc(t.name)}</span></label></div>
        <div class="col-4"><input type="number" min="0" class="form-control form-control-sm" placeholder="Pieces" data-pieces="${t.id}" value="${onLoads.get(t.id)?.piece_count ?? ''}"></div></div>`).join('');
    const form = flModal(S ? 'Edit ' + S.shipment_number : 'New Shipment',
      `<div class="col-12"><label class="form-label">Jobs <span class="text-muted small">(select one or more)</span></label>
        <div id="jobPicked" class="mb-1"></div>
        <input type="text" id="jobSearchInput" class="form-control form-control-sm mb-1" placeholder="Search jobs, or type a name to add a custom one…" oninput="jobSearch(this.value)" autocomplete="off">
        <div id="jobList" class="list-group" style="max-height:11rem;overflow:auto"></div></div>` +
      placeHtml('pickup', 'Pickup from') + placeHtml('dropoff', 'Drop off at') +
      flField('ship_date', 'Ship date', S ? String(S.ship_date || '').substring(0, 10) : '', 'date') + flField('carrier', 'Carrier / driver', S?.carrier || '') +
      flField('destination', 'Destination', S?.destination || '', 'text', '', 'col-12') +
      `<div class="col-12"><label class="form-label">What is on this shipment?</label>` + loadRow(main) +
      (rest.length ? `<div class="mb-1"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" id="otherCb" ${restOn ? 'checked' : ''}
          onchange="document.getElementById('otherTypes').classList.toggle('d-none', !this.checked); if (!this.checked) document.querySelectorAll('#otherTypes .load-cb').forEach(c => c.checked = false)">
          <span class="form-check-label">Other</span></label></div>
        <div id="otherTypes" class="${restOn ? '' : 'd-none'} ms-4 ps-2 border-start">${loadRow(rest)}</div>` : '') + `</div>` +
      flText('notes', 'Notes', S?.notes || '', 2),
      async f => {
        const d = flFormData(f);
        d.loads = [...f.querySelectorAll('.load-cb:checked')].map(c => ({ shipment_type_id: +c.value, piece_count: f.querySelector(`[data-pieces="${c.value}"]`).value || null }));
        if (!d.loads.length) { showNotification('Pick at least one load type.', 'warning'); throw new Error('no loads'); }
        delete d.undefined;
        d.job_ids = [...picked.keys()];
        d.custom_jobs = customs;
        d.save_site_to_job_id = place.dropoff.src === 'search' && place.dropoff.saveJob ? +place.dropoff.saveJob : null;
        for (const w of ['pickup', 'dropoff']) {
          const p = place[w], l = locOf(p.id);
          if (!l) { showNotification('Choose a ' + (w === 'pickup' ? 'pickup' : 'drop-off') + ' location.', 'warning'); throw new Error('no ' + w); }
          if (l.requires_address && !p.address) { showNotification('Search for and pick the ' + l.name + ' address.', 'warning'); throw new Error('no address'); }
          if (l.saves_visits && !(p.newName || '').trim()) { showNotification('Enter the business name to save this location under.', 'warning'); throw new Error('no name'); }
          d[w + '_new_name'] = l.saves_visits ? p.newName.trim() : null;
          d[w + '_location_id'] = +p.id; d[w + '_address'] = p.address || null; d[w + '_lat'] = p.lat; d[w + '_lng'] = p.lng;
        }
        if (S) { await flApi('/shipments/' + S.id, 'PUT', d); await load(); return; }
        const s = await flApi('/shipments', 'POST', d);
        location.href = '/shipments/' + s.id;
      }, S ? 'Save' : 'Create', 'modal-xl');
  }
</script>
