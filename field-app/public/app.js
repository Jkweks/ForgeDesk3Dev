'use strict';
/*
 * ForgeDesk Field: mobile client.
 *
 * Offline model: the asset/checklist snapshot and an outbox of unsent submissions live in
 * IndexedDB. Every submission gets a client-side UUID that is reused on every retry, so the
 * server (and ForgeDesk behind it) can safely drop duplicates.
 */

// ---------- helpers ---------------------------------------------------------------------

const $app = document.getElementById('app');
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function uuidv4() {
  if (crypto.randomUUID) return crypto.randomUUID();
  const b = crypto.getRandomValues(new Uint8Array(16));
  b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
  const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

let toastTimer;
function toast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3200);
}

const STATUS = {
  active: ['Active', 'ok'], in_service_job: ['On a job', 'ok'],
  out_of_service: ['Out of service', 'bad'], in_repair: ['In repair', 'warn'],
};
const statusChip = (s) => { const [t, c] = STATUS[s] || [s, '']; return `<span class="chip ${c}">${esc(t)}</span>`; };
const METER_LABEL = { odometer_miles: 'Odometer (miles)', engine_hours: 'Engine hours' };

// ---------- storage (IndexedDB) ---------------------------------------------------------

const db = (() => {
  let opened;
  const open = () => opened || (opened = new Promise((resolve, reject) => {
    const req = indexedDB.open('forgedesk-field', 1);
    req.onupgradeneeded = () => {
      req.result.createObjectStore('kv');
      req.result.createObjectStore('outbox', { keyPath: 'submission_uuid' });
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  }));
  const run = async (store, mode, fn) => {
    const d = await open();
    return new Promise((resolve, reject) => {
      const tx = d.transaction(store, mode);
      const out = fn(tx.objectStore(store));
      tx.oncomplete = () => resolve(out && 'result' in out ? out.result : undefined);
      tx.onerror = () => reject(tx.error);
    });
  };
  return {
    get: (k) => run('kv', 'readonly', (s) => s.get(k)),
    set: (k, v) => run('kv', 'readwrite', (s) => s.put(v, k)),
    del: (k) => run('kv', 'readwrite', (s) => s.delete(k)),
    outbox: () => run('outbox', 'readonly', (s) => s.getAll()).then((r) => (r || []).sort((a, b) => a.created - b.created)),
    put: (rec) => run('outbox', 'readwrite', (s) => s.put(rec)),
    remove: (id) => run('outbox', 'readwrite', (s) => s.delete(id)),
  };
})();

// ---------- API -------------------------------------------------------------------------

class ApiError extends Error { constructor(status, message) { super(message); this.status = status; } }

async function api(path, { method = 'GET', body } = {}) {
  const res = await fetch(`/api${path}`, {
    method,
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fieldapp' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new ApiError(res.status, data.error || `Error ${res.status}`);
  return data;
}

// ---------- state -----------------------------------------------------------------------

const state = { user: null, assets: [], checklists: [], recent: [], syncedAt: null, online: navigator.onLine, outbox: [], needLogin: false };

async function loadCache() {
  const snap = await db.get('snapshot');
  if (snap) Object.assign(state, { user: snap.user, assets: snap.assets, checklists: snap.checklists, recent: snap.recent || [], syncedAt: snap.syncedAt });
  state.outbox = await db.outbox();
}

/** Pull fresh assets/checklists. Returns false when offline; throws ApiError(401) if signed out. */
async function refresh() {
  try {
    const d = await api('/bootstrap');
    state.user = d.user; state.assets = d.assets; state.checklists = d.checklists; state.recent = d.recent;
    state.syncedAt = d.server_time; state.online = true; state.needLogin = false;
    await db.set('snapshot', { user: d.user, assets: d.assets, checklists: d.checklists, recent: d.recent, syncedAt: d.server_time });
    return true;
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) { state.needLogin = true; throw e; }
    if (e instanceof ApiError) throw e;
    state.online = false;
    return false;
  }
}

// ---------- outbox ----------------------------------------------------------------------

let flushing = false;
let retryTimer;

async function enqueue(kind, assetNumber, payload, photos) {
  const rec = {
    submission_uuid: uuidv4(), kind, asset_number: assetNumber,
    submitted_at: new Date().toISOString(), payload, photos: photos || [],
    status: 'pending', error: null, attempts: 0, created: Date.now(),
  };
  await db.put(rec);
  state.outbox = await db.outbox();
  flush();
  return rec;
}

/** Send queued submissions oldest-first. Stops (and retries later) on network trouble. */
async function flush() {
  if (flushing) return;
  flushing = true;
  try {
    for (const rec of await db.outbox()) {
      if (rec.status === 'failed') continue;
      try {
        await api('/submissions', { method: 'POST', body: {
          submission_uuid: rec.submission_uuid, kind: rec.kind, asset_number: rec.asset_number,
          submitted_at: rec.submitted_at, payload: rec.payload, photos: rec.photos,
        } });
        await db.remove(rec.submission_uuid);
        state.online = true;
      } catch (e) {
        if (e instanceof ApiError && e.status === 401) { state.needLogin = true; break; }
        if (e instanceof ApiError && e.status >= 400 && e.status < 500 && e.status !== 429) {
          // The server understood and refused it (e.g. the asset was retired). Keep it visible.
          await db.put({ ...rec, status: 'failed', error: e.message, attempts: rec.attempts + 1 });
          continue;
        }
        state.online = e instanceof ApiError ? state.online : false;
        await db.put({ ...rec, attempts: rec.attempts + 1 });
        clearTimeout(retryTimer);
        retryTimer = setTimeout(flush, Math.min(60000, 5000 * 2 ** Math.min(rec.attempts, 4)));
        break;
      }
    }
  } finally {
    flushing = false;
    state.outbox = await db.outbox();
    if (location.hash.startsWith('#/queue') || location.hash === '' || location.hash === '#/') render();
  }
}

const pendingCount = () => state.outbox.filter((r) => r.status !== 'failed').length;
const failedCount = () => state.outbox.filter((r) => r.status === 'failed').length;

// ---------- router ----------------------------------------------------------------------

let stopScan = null;

function go(hash) { if (location.hash === hash) render(); else location.hash = hash; }

async function render() {
  if (stopScan) { stopScan(); stopScan = null; }
  if (state.needLogin || !state.user) return viewLogin();
  const [, route, arg] = (location.hash || '#/').split('/').map((p) => decodeURIComponent(p));
  switch (route) {
    case 'asset': return viewAsset(arg);
    case 'inspect': return viewInspect(arg);
    case 'meter': return viewMeter(arg);
    case 'defect': return viewDefect(arg);
    case 'scan': return viewScan();
    case 'queue': return viewQueue();
    default: return viewHome();
  }
}
window.addEventListener('hashchange', render);

const findAsset = (n) => state.assets.find((a) => a.asset_number === n);

function topbar(title, back) {
  return `<div class="topbar">${back ? `<button class="back" data-go="${back}" aria-label="Back">‹ Back</button>` : ''}<h1>${esc(title)}</h1></div>`;
}

function statusBanner() {
  const bits = [];
  if (!state.online) bits.push('<div class="banner warn">You’re offline. You can keep working; everything will send when you’re back in range.</div>');
  const p = pendingCount(), f = failedCount();
  if (p) bits.push(`<div class="banner warn">${p} waiting to send. <button data-go="#/queue">View</button></div>`);
  if (f) bits.push(`<div class="banner bad">${f} couldn’t be sent. <button data-go="#/queue">Review</button></div>`);
  return bits.join('');
}

// Delegated navigation so templates stay free of inline handlers (CSP forbids them).
document.addEventListener('click', (e) => {
  const t = e.target.closest('[data-go]');
  if (t) { e.preventDefault(); go(t.dataset.go === 'back' ? '#/' : t.dataset.go); }
});

// ---------- views -----------------------------------------------------------------------

function viewLogin() {
  $app.innerHTML = `
    <div class="topbar"><h1>ForgeDesk Field</h1></div>
    <form id="login" class="card" autocomplete="on">
      ${state.needLogin && state.user ? '<div class="banner warn">Your session ended. Sign in to continue. Unsent work is safe.</div>' : ''}
      <label for="u">Email</label>
      <input id="u" name="username" type="email" inputmode="email" autocomplete="username" autocapitalize="none" autocorrect="off" required>
      <label for="p">Password</label>
      <input id="p" name="password" type="password" autocomplete="current-password" required>
      <p id="err" class="small" style="color:var(--bad);min-height:1.2em" role="alert"></p>
      <button class="btn primary" type="submit">Sign in</button>
    </form>`;
  document.getElementById('login').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button'); btn.disabled = true;
    try {
      await api('/login', { method: 'POST', body: { username: e.target.elements.username.value.trim(), password: e.target.elements.password.value } });
      state.needLogin = false;
      await refresh();
      flush();
      go('#/');
    } catch (err) {
      document.getElementById('err').textContent = err instanceof ApiError ? err.message : 'Can’t reach the server. Check your connection.';
      btn.disabled = false;
    }
  });
}

function viewHome() {
  const q = (sessionStorage.getItem('q') || '').toLowerCase();
  const list = state.assets.filter((a) => !q || [a.asset_number, a.name, a.type_name, a.current_job_label].some((v) => String(v || '').toLowerCase().includes(q)));
  $app.innerHTML = `
    <div class="topbar"><h1>Assets</h1>
      <button class="btn small" data-go="#/queue">Sent${pendingCount() + failedCount() ? ` (${pendingCount() + failedCount()})` : ''}</button>
      <button class="btn small" id="logout">Sign out</button></div>
    ${statusBanner()}
    <button class="btn primary" data-go="#/scan">Scan asset QR code</button>
    <label for="q" class="small muted">Or search by number, name or job</label>
    <input id="q" type="search" placeholder="e.g. FA-0012 or Ford F-250" value="${esc(sessionStorage.getItem('q') || '')}" autocomplete="off">
    <div class="card" style="margin-top:12px"><ul class="list">${list.map((a) => `
      <li><a href="#/asset/${encodeURIComponent(a.asset_number)}">
        <div class="grow"><div><span class="num">${esc(a.asset_number)}</span> ${esc(a.name)}</div>
        <div class="small muted">${esc(a.type_name)}${a.current_job_label ? ` · ${esc(a.current_job_label)}` : ''}</div></div>
        ${statusChip(a.status)}</a></li>`).join('') || `<li class="item muted">${state.assets.length ? 'No matches.' : 'No assets yet. Connect once to download them.'}</li>`}
    </ul></div>
    <p class="small muted" style="text-align:center">${state.syncedAt ? `Asset list updated ${new Date(state.syncedAt).toLocaleString()}` : ''} · ${esc(state.user.display_name)}</p>`;
  document.getElementById('q').addEventListener('input', (e) => { sessionStorage.setItem('q', e.target.value); const pos = e.target.selectionStart; viewHome(); const n = document.getElementById('q'); n.focus(); n.setSelectionRange(pos, pos); });
  document.getElementById('logout').addEventListener('click', logout);
}

function viewAsset(number) {
  const a = findAsset(number);
  if (!a) { toast('Asset not found'); return go('#/'); }
  const enc = encodeURIComponent(a.asset_number);
  $app.innerHTML = `
    ${topbar(a.asset_number, '#/')}${statusBanner()}
    <div class="card">
      <div class="row"><div class="grow"><strong>${esc(a.name)}</strong><div class="muted">${esc(a.type_name)}</div></div>${statusChip(a.status)}</div>
      ${a.current_job_label ? `<p class="small" style="margin:10px 0 0">At: ${esc(a.current_job_label)}</p>` : ''}
      ${a.meter_type && a.meter_type !== 'none' ? `<p class="small" style="margin:6px 0 0">${esc(METER_LABEL[a.meter_type] || 'Meter')}: <strong>${a.current_meter ?? '–'}</strong></p>` : ''}
    </div>
    ${a.status === 'out_of_service' ? '<div class="banner bad">This asset is out of service. Do not use it until the office clears it.</div>' : ''}
    <div class="actions">
      <button class="btn primary" data-go="#/inspect/${enc}">Start inspection</button>
      ${a.meter_type && a.meter_type !== 'none' ? `<button class="btn" data-go="#/meter/${enc}">Update ${a.meter_type === 'engine_hours' ? 'hours' : 'odometer'}</button>` : ''}
      <button class="btn" data-go="#/defect/${enc}">Report a problem</button>
    </div>`;
}

// ----- photos

/** Downscale on the phone: full-size camera shots are 4–8 MB each, which is hopeless on a weak signal. */
function shrinkImage(file, maxSide = 1600, quality = 0.8) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => {
      const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
      const c = document.createElement('canvas');
      c.width = Math.round(img.width * scale); c.height = Math.round(img.height * scale);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(url);
      resolve(c.toDataURL('image/jpeg', quality));
    };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Could not read that photo.')); };
    img.src = url;
  });
}

function photoPicker(root) {
  const photos = [];
  root.innerHTML = `
    <label class="btn file-btn"><input type="file" accept="image/*" capture="environment" multiple>Add photos</label>
    <div class="thumbs"></div>`;
  const input = root.querySelector('input'), thumbs = root.querySelector('.thumbs');
  const paint = () => {
    thumbs.innerHTML = photos.map((p, i) => `<div class="thumb"><img src="${p.data_b64}" alt="Photo ${i + 1}"><button type="button" data-rm="${i}" aria-label="Remove photo ${i + 1}">×</button></div>`).join('');
  };
  input.addEventListener('change', async () => {
    for (const f of input.files) {
      if (photos.length >= 10) { toast('Up to 10 photos'); break; }
      try { photos.push({ filename: `photo-${photos.length + 1}.jpg`, data_b64: await shrinkImage(f) }); } catch (e) { toast(e.message); }
    }
    input.value = '';
    paint();
  });
  thumbs.addEventListener('click', (e) => { const i = e.target.dataset.rm; if (i != null) { photos.splice(+i, 1); paint(); } });
  return { get: () => photos.slice() };
}

// ----- forms

function checklistFor(asset) {
  const c = state.checklists.find((x) => x.type_name === asset.type_name);
  const items = (c?.items || []).map((i) => i.label).filter(Boolean);
  return { template: c?.template_name || `${asset.type_name} inspection`, items: items.length ? items : ['General condition'] };
}

function viewInspect(number) {
  const a = findAsset(number);
  if (!a) return go('#/');
  const { template, items } = checklistFor(a);
  const hasMeter = a.meter_type && a.meter_type !== 'none';
  $app.innerHTML = `
    ${topbar('Inspection', `#/asset/${encodeURIComponent(number)}`)}
    <p class="muted" style="margin-top:0"><strong>${esc(a.asset_number)}</strong> · ${esc(a.name)}</p>
    <form id="f">
      ${hasMeter ? `<div class="card"><label for="m" style="margin-top:0">${esc(METER_LABEL[a.meter_type])} <span class="muted">(last: ${a.current_meter ?? '–'})</span></label>
        <input id="m" type="number" inputmode="decimal" step="any" min="0"></div>` : ''}
      <div class="card"><div class="row"><h2 class="grow" style="margin:0">${esc(template)}</h2><button type="button" class="btn small" id="allpass">All pass</button></div>
        ${items.map((label, i) => `
          <div class="check-item" data-i="${i}">
            <div class="lbl">${esc(label)}</div>
            <div class="seg" role="radiogroup" aria-label="${esc(label)}">
              <label><input type="radio" name="r${i}" value="pass"><span class="pass">Pass</span></label>
              <label><input type="radio" name="r${i}" value="fail"><span class="fail">Fail</span></label>
              <label><input type="radio" name="r${i}" value="na"><span class="na">N/A</span></label>
            </div>
            <textarea name="c${i}" placeholder="What’s wrong?" hidden maxlength="1000"></textarea>
          </div>`).join('')}
      </div>
      <div class="card"><label for="n" style="margin-top:0">Notes</label><textarea id="n" maxlength="5000"></textarea>
        <label>Photos</label><div id="ph"></div></div>
      <p id="err" class="small" style="color:var(--bad)" role="alert"></p>
      <div class="sticky-submit"><button class="btn primary" type="submit">Submit inspection</button></div>
    </form>`;
  const form = document.getElementById('f');
  const pics = photoPicker(document.getElementById('ph'));

  form.addEventListener('change', (e) => {
    const m = /^r(\d+)$/.exec(e.target.name || '');
    if (m) form.elements[`c${m[1]}`].hidden = e.target.value !== 'fail';
  });
  document.getElementById('allpass').addEventListener('click', () => {
    items.forEach((_, i) => { const r = form.querySelector(`input[name=r${i}][value=pass]`); r.checked = true; form.elements[`c${i}`].hidden = true; });
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const err = document.getElementById('err');
    const results = items.map((label, i) => {
      const v = form.querySelector(`input[name=r${i}]:checked`)?.value;
      return { label, result: v, comment: (form.elements[`c${i}`].value || '').trim() };
    });
    const missing = results.findIndex((r) => !r.result);
    if (missing >= 0) { err.textContent = `Answer “${results[missing].label}”.`; form.querySelector(`[data-i="${missing}"]`).scrollIntoView({ block: 'center' }); return; }
    const noComment = results.findIndex((r) => r.result === 'fail' && !r.comment);
    if (noComment >= 0) { err.textContent = `Say what’s wrong with “${results[noComment].label}”.`; return; }
    const payload = { template_name: template, items: results.map((r) => ({ label: r.label, result: r.result, ...(r.comment && { comment: r.comment }) })) };
    const notes = document.getElementById('n').value.trim();
    if (notes) payload.notes = notes;
    const mEl = document.getElementById('m');
    if (mEl && mEl.value !== '') {
      const meter = parseFloat(mEl.value);
      if (a.current_meter != null && meter < a.current_meter && !confirm(`That’s lower than the last reading (${a.current_meter}). The office will be asked to review it. Send anyway?`)) return;
      payload.meter = meter;
    }
    await finish('inspection', a, payload, pics.get(), results.some((r) => r.result === 'fail') ? 'Inspection saved with failed items. The office will be notified.' : 'Inspection saved.');
  });
}

function viewMeter(number) {
  const a = findAsset(number);
  if (!a) return go('#/');
  $app.innerHTML = `
    ${topbar('Update meter', `#/asset/${encodeURIComponent(number)}`)}
    <form id="f" class="card">
      <p style="margin-top:0"><strong>${esc(a.asset_number)}</strong> · ${esc(a.name)}</p>
      <label for="m">${esc(METER_LABEL[a.meter_type] || 'Reading')} <span class="muted">(last: ${a.current_meter ?? '–'})</span></label>
      <input id="m" type="number" inputmode="decimal" step="any" min="0" required autofocus>
      <p id="err" class="small" style="color:var(--bad)" role="alert"></p>
      <button class="btn primary" type="submit">Save reading</button>
    </form>`;
  document.getElementById('f').addEventListener('submit', async (e) => {
    e.preventDefault();
    const reading = parseFloat(document.getElementById('m').value);
    if (!Number.isFinite(reading) || reading < 0) { document.getElementById('err').textContent = 'Enter a valid number.'; return; }
    if (a.current_meter != null && reading < a.current_meter && !confirm(`That’s lower than the last reading (${a.current_meter}). The office will be asked to review it. Send anyway?`)) return;
    await finish('meter', a, { reading }, [], 'Reading saved.');
  });
}

function viewDefect(number) {
  const a = findAsset(number);
  if (!a) return go('#/');
  $app.innerHTML = `
    ${topbar('Report a problem', `#/asset/${encodeURIComponent(number)}`)}
    <form id="f" class="card">
      <p style="margin-top:0"><strong>${esc(a.asset_number)}</strong> · ${esc(a.name)}</p>
      <label for="t">What’s wrong?</label><input id="t" maxlength="255" required placeholder="e.g. Hydraulic leak at left cylinder">
      <label for="s">How bad is it?</label>
      <select id="s"><option value="low">Low: can wait</option><option value="medium" selected>Medium: fix soon</option><option value="high">High: needs attention</option><option value="critical">Critical: unsafe to use</option></select>
      <label for="d">Details</label><textarea id="d" maxlength="5000"></textarea>
      <label>Photos</label><div id="ph"></div>
      <div class="sticky-submit"><button class="btn primary" type="submit">Send report</button></div>
    </form>`;
  const pics = photoPicker(document.getElementById('ph'));
  document.getElementById('f').addEventListener('submit', async (e) => {
    e.preventDefault();
    const description = document.getElementById('d').value.trim();
    await finish('defect', a, { title: document.getElementById('t').value.trim(), severity: document.getElementById('s').value, ...(description && { description }) }, pics.get(), 'Report saved.');
  });
}

async function finish(kind, asset, payload, photos, message) {
  document.querySelectorAll('button[type=submit]').forEach((b) => { b.disabled = true; });
  await enqueue(kind, asset.asset_number, payload, photos);
  await sleep(150);
  toast(state.online ? message : `${message} It will send when you’re online.`);
  go(`#/asset/${encodeURIComponent(asset.asset_number)}`);
}

// ----- scanning

function viewScan() {
  $app.innerHTML = `
    ${topbar('Scan QR code', '#/')}
    <div class="scan-wrap"><video id="v" playsinline muted></video></div>
    <p id="msg" class="muted small" style="text-align:center">Point the camera at the asset’s QR label.</p>
    <form id="manual" class="card"><label for="num" style="margin-top:0">Or type the asset number</label>
      <input id="num" autocapitalize="characters" autocomplete="off" placeholder="FA-0012"><button class="btn" style="margin-top:10px" type="submit">Open</button></form>`;

  const open = (raw) => {
    const code = String(raw).trim().split(/[/?#]/).filter(Boolean).pop() || '';
    const hit = state.assets.find((a) => a.asset_number.toLowerCase() === code.toLowerCase());
    if (hit) { go(`#/asset/${encodeURIComponent(hit.asset_number)}`); return true; }
    return false;
  };
  document.getElementById('manual').addEventListener('submit', (e) => {
    e.preventDefault();
    if (!open(document.getElementById('num').value)) toast('No active asset with that number');
  });

  let stream, raf, stopped = false;
  stopScan = () => { stopped = true; cancelAnimationFrame(raf); stream?.getTracks().forEach((t) => t.stop()); };

  (async () => {
    const msg = document.getElementById('msg');
    if (!navigator.mediaDevices?.getUserMedia) { msg.textContent = 'Camera isn’t available here. Type the number below.'; return; }
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    } catch { msg.textContent = 'Camera blocked. Allow camera access, or type the number below.'; return; }
    if (stopped) { stream.getTracks().forEach((t) => t.stop()); return; }
    const video = document.getElementById('v');
    video.srcObject = stream;
    await video.play().catch(() => {});

    const native = 'BarcodeDetector' in window ? new BarcodeDetector({ formats: ['qr_code'] }) : null;
    if (!native) await loadScript('/vendor/jsqr.js').catch(() => {});
    const canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
    let last = 0, lastMiss = '';

    const tick = async (t) => {
      if (stopped) return;
      raf = requestAnimationFrame(tick);
      if (t - last < 200 || video.readyState < 2) return;   // ~5 scans/second is plenty
      last = t;
      let value = null;
      try {
        if (native) {
          value = (await native.detect(video))[0]?.rawValue;
        } else if (window.jsQR) {
          const w = 480, h = Math.round(w * video.videoHeight / video.videoWidth) || w;
          canvas.width = w; canvas.height = h;
          ctx.drawImage(video, 0, 0, w, h);
          value = window.jsQR(ctx.getImageData(0, 0, w, h).data, w, h)?.data;
        }
      } catch { /* keep scanning */ }
      if (!value) return;
      if (open(value)) { navigator.vibrate?.(60); return; }
      if (value !== lastMiss) { lastMiss = value; msg.textContent = `“${value.slice(0, 40)}” isn’t an active asset here.`; }
    };
    raf = requestAnimationFrame(tick);
  })();
}

function loadScript(src) {
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = src; s.onload = resolve; s.onerror = reject;
    document.head.appendChild(s);
  });
}

// ----- queue / history

const KIND = { inspection: 'Inspection', meter: 'Meter reading', defect: 'Problem report' };

async function viewQueue() {
  state.outbox = await db.outbox();
  if (state.online) { try { state.recent = (await api('/submissions/mine')).items; } catch { /* keep cached */ } }
  const q = state.outbox;
  $app.innerHTML = `
    ${topbar('Sent', '#/')}${!state.online ? '<div class="banner warn">Offline. Showing what’s on this phone.</div>' : ''}
    ${q.length ? `<div class="card"><h2>On this phone</h2><ul class="list">${q.map((r) => `
      <li><div class="item"><div class="grow"><strong>${esc(KIND[r.kind])}</strong> · ${esc(r.asset_number)}
        <div class="small muted">${new Date(r.submitted_at).toLocaleString()}${r.photos.length ? ` · ${r.photos.length} photo${r.photos.length > 1 ? 's' : ''}` : ''}</div>
        ${r.status === 'failed' ? `<div class="small" style="color:var(--bad)">${esc(r.error)}</div>` : ''}</div>
        ${r.status === 'failed' ? `<button class="btn small danger" data-discard="${esc(r.submission_uuid)}">Discard</button>` : '<span class="chip warn">Waiting</span>'}</div></li>`).join('')}</ul>
      ${pendingCount() ? '<button class="btn" id="retry" style="margin-top:8px">Send now</button>' : ''}</div>` : ''}
    <div class="card"><h2>Recently sent</h2><ul class="list">${state.recent.map((r) => `
      <li><div class="item"><div class="grow"><strong>${esc(KIND[r.kind] || r.kind)}</strong> · ${esc(r.asset_number)}
        <div class="small muted">${new Date(r.submitted_at).toLocaleString()}</div></div>
        <span class="chip ${r.processed ? 'ok' : ''}">${r.processed ? 'Office has it' : 'Delivered'}</span></div></li>`).join('') || '<li class="item muted">Nothing sent yet.</li>'}</ul></div>`;
  document.getElementById('retry')?.addEventListener('click', async () => { await flush(); viewQueue(); });
  $app.querySelectorAll('[data-discard]').forEach((b) => b.addEventListener('click', async () => {
    if (!confirm('Discard this submission? It won’t be sent.')) return;
    await db.remove(b.dataset.discard); viewQueue();
  }));
}

async function logout() {
  if (pendingCount() && !confirm(`${pendingCount()} item(s) haven’t been sent yet. They stay on this phone and send after you sign in again. Sign out?`)) return;
  try { await api('/logout', { method: 'POST', body: {} }); } catch { /* offline: cookie expires on its own */ }
  state.user = null; state.needLogin = true;
  await db.del('snapshot');
  state.assets = []; state.checklists = []; state.recent = [];
  render();
}

// ---------- boot ------------------------------------------------------------------------

window.addEventListener('online', () => { state.online = true; refresh().then(() => { flush(); render(); }).catch(render); });
window.addEventListener('offline', () => { state.online = false; render(); });
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible' && state.user) refresh().then(() => { flush(); }).catch(() => {});
});

(async function boot() {
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
  await loadCache();
  try {
    await refresh();
  } catch (e) {
    if (!(e instanceof ApiError)) console.error(e);
  }
  // Signed out: drop the cached user but keep the outbox so queued work survives re-login.
  if (state.needLogin) state.user = state.user || null;
  render();
  if (state.user && !state.needLogin) flush();
})();
