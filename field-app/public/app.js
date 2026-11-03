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

const state = { user: null, assets: [], checklists: [], shipments: [], jobs: [], types: [], recent: [], syncedAt: null, online: navigator.onLine, outbox: [], needLogin: false };

async function loadCache() {
  const snap = await db.get('snapshot');
  if (snap) Object.assign(state, { user: snap.user, assets: snap.assets, checklists: snap.checklists, shipments: snap.shipments || [], jobs: snap.jobs || [], types: snap.shipment_types || [], recent: snap.recent || [], syncedAt: snap.syncedAt });
  state.outbox = await db.outbox();
}

const saveSnapshot = () => db.set('snapshot', {
  user: state.user, assets: state.assets, checklists: state.checklists, shipments: state.shipments, jobs: state.jobs, shipment_types: state.types, recent: state.recent, syncedAt: state.syncedAt,
});

/** UI hint only: ForgeDesk re-checks permissions when the submission arrives. */
const can = (perm) => (state.user?.permissions || []).includes(perm);
const canInspect = () => can('shipments.inspect');
const canManage = () => can('shipments.manage');
const canSeeQueue = () => can('shipments.view') || canManage() || canInspect();

/** Pull fresh assets/checklists. Returns false when offline; throws ApiError(401) if signed out. */
async function refresh() {
  try {
    const d = await api('/bootstrap');
    state.user = d.user; state.assets = d.assets; state.checklists = d.checklists; state.shipments = d.shipments || []; state.jobs = d.jobs || []; state.types = d.shipment_types || []; state.recent = d.recent;
    state.syncedAt = d.server_time; state.online = true; state.needLogin = false;
    await saveSnapshot();
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
    if (['#/queue', '#/pre', '#/post', '#/shipping'].some((h) => location.hash.startsWith(h)) || location.hash === '' || location.hash === '#/') render();
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
    case 'delivery': return viewShipmentCheck(arg, 'post');
    case 'precheck': return viewShipmentCheck(arg, 'pre');
    case 'pre': return viewPre();
    case 'post': return viewPost();
    case 'shipping': return viewShipping();
    case 'shipment': return viewShipment(arg);
    case 'newshipment': return viewNewShipment();
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

/** Bottom navigation on the top-level screens. Sections the signed-in user can't use are left out. */
function tabs(active) {
  const items = [['fleet', '#/', 'Fleet', '🚚', 0]];
  if (canInspect()) items.push(['pre', '#/pre', 'Pre-check', '📋', openPreChecks().length]);
  items.push(['post', '#/post', 'Deliveries', '📦', openDeliveries().length]);
  if (canSeeQueue()) items.push(['shipping', '#/shipping', 'Queue', '🗓️', 0]);
  return `<nav class="tabs" aria-label="Sections">${items.map(([k, h, label, icon, n]) => `
    <a href="${h}" data-go="${h}" class="${k === active ? 'on' : ''}" ${k === active ? 'aria-current="page"' : ''}>
      <span class="ico" aria-hidden="true">${icon}</span><span>${label}</span>${n ? `<b class="dot">${n}</b>` : ''}</a>`).join('')}</nav>`;
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
    <div class="topbar"><h1>Fleet</h1>
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
    <p class="small muted" style="text-align:center">${state.syncedAt ? `Asset list updated ${new Date(state.syncedAt).toLocaleString()}` : ''} · ${esc(state.user.display_name)}</p>${tabs('fleet')}`;
  document.getElementById('q').addEventListener('input', (e) => { sessionStorage.setItem('q', e.target.value); const pos = e.target.selectionStart; viewHome(); const n = document.getElementById('q'); n.focus(); n.setSelectionRange(pos, pos); });
  document.getElementById('logout').addEventListener('click', logout);
}

// ---------- shipping lists (queue, pre-checks, deliveries) --------------------------------

const SHIP_STATUS = { draft: ['Draft', ''], pre_check_done: ['Ready', 'ok'], shipped: ['In transit', 'warn'] };
const shipChip = (s) => { const [t, c] = SHIP_STATUS[s] || [s, '']; return `<span class="chip ${c}">${esc(t)}</span>`; };
const pad2 = (n) => String(n).padStart(2, '0');
const todayStr = () => { const d = new Date(); return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`; };
const prettyDate = (iso) => new Date(`${iso}T12:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
const findShipment = (n) => state.shipments.find((x) => x.shipment_number === n);

/** What this phone has already sent for a kind (still queued, or recently accepted), by shipment number. */
const SENT_GRACE_MS = 2 * 3600 * 1000;
const sentFor = (kind) => {
  const now = Date.now();
  const stale = (r) => {
    // Accepted by the field server but the shipment is still in the same state on ForgeDesk after the
    // grace window (sync lag): ForgeDesk never applied it, so offer it again instead of hiding it.
    if (!r.processed || !r.submitted_at) return false;
    return now - new Date(r.submitted_at).getTime() > SENT_GRACE_MS && kind.startsWith('shipment_');
  };
  return new Set([...state.outbox, ...state.recent].filter((r) => r.kind === kind && !stale(r)).map((r) => r.asset_number));
};

/** Shipments on the road that this phone hasn't inspected yet. */
function openDeliveries() {
  const done = sentFor('shipment_inspection');
  return state.shipments.filter((sh) => sh.status === 'shipped' && !done.has(sh.shipment_number));
}

/** Drafts waiting for their pre-shipment check. */
function openPreChecks() {
  const done = sentFor('shipment_precheck');
  return state.shipments.filter((sh) => sh.status === 'draft' && !done.has(sh.shipment_number));
}

function shipRow(sh, href, chipHtml) {
  return `<li><a href="${href}" data-go="${href}"><div class="grow">
    <div><span class="num">${esc(sh.shipment_number)}</span> ${esc(sh.job_label || 'No job')}</div>
    <div class="small muted">${esc([sh.ship_date ? prettyDate(sh.ship_date) : 'No date', sh.destination, sh.loads].filter(Boolean).join(' · '))}</div></div>
    ${chipHtml ?? shipChip(sh.status)}</a></li>`;
}

const newShipmentButton = () => (canManage() ? '<button class="btn small primary" data-go="#/newshipment">+ New</button>' : '');

function viewPre() {
  if (!canInspect()) return go('#/');
  const list = openPreChecks();
  $app.innerHTML = `
    <div class="topbar"><h1>Pre-shipment checks</h1>${newShipmentButton()}</div>${statusBanner()}
    <div class="card"><ul class="list">${list.map((sh) => shipRow(sh, `#/precheck/${encodeURIComponent(sh.shipment_number)}`, '<span class="chip warn">Check</span>')).join('')
      || '<li class="item muted">Nothing waiting for a pre-shipment check.</li>'}</ul></div>${tabs('pre')}`;
}

function viewPost() {
  const list = openDeliveries();
  $app.innerHTML = `
    <div class="topbar"><h1>Deliveries to inspect</h1></div>${statusBanner()}
    <div class="card"><ul class="list">${list.map((sh) => shipRow(sh, `#/delivery/${encodeURIComponent(sh.shipment_number)}`, '<span class="chip warn">Inspect</span>')).join('')
      || '<li class="item muted">No deliveries waiting for inspection.</li>'}</ul></div>${tabs('post')}`;
}

function viewShipping() {
  if (!canSeeQueue()) return go('#/');
  const today = todayStr();
  const late = [], unscheduled = [], byDate = new Map();
  for (const sh of state.shipments) {
    if (!sh.ship_date) unscheduled.push(sh);
    else if (sh.ship_date < today && sh.status !== 'shipped') late.push(sh);
    else { if (!byDate.has(sh.ship_date)) byDate.set(sh.ship_date, []); byDate.get(sh.ship_date).push(sh); }
  }
  const section = (title, list, cls = '') => (list.length ? `<div class="card"><h2 class="${cls}">${esc(title)}</h2><ul class="list">${list.map((sh) => shipRow(sh, `#/shipment/${encodeURIComponent(sh.shipment_number)}`)).join('')}</ul></div>` : '');
  const unsent = state.outbox.filter((r) => r.kind === 'shipment_create');
  $app.innerHTML = `
    <div class="topbar"><h1>Shipping queue</h1>${newShipmentButton()}</div>${statusBanner()}
    ${unsent.length ? `<div class="card"><h2>Waiting to send</h2><ul class="list">${unsent.map((r) => `<li><div class="item"><div class="grow"><strong>New shipment</strong>
      <div class="small muted">${esc([r.payload.destination, r.payload.job_numbers?.join(', ')].filter(Boolean).join(' · ') || 'No details')}</div></div><span class="chip warn">Waiting</span></div></li>`).join('')}</ul></div>` : ''}
    ${section('Late', late, 'late')}
    ${[...byDate.keys()].sort().map((d) => section(d === today ? `Today · ${prettyDate(d)}` : prettyDate(d), byDate.get(d))).join('')}
    ${section('Not scheduled', unscheduled)}
    ${state.shipments.length ? '' : '<div class="card"><p class="muted" style="margin:0">No open shipments.</p></div>'}
    ${tabs('shipping')}`;
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

function photoPicker(root, max = 10) {
  const photos = [];
  root.innerHTML = `
    <label class="btn file-btn"><input type="file" accept="image/*" multiple>Add photos</label>
    <div class="thumbs"></div>`;
  const input = root.querySelector('input'), thumbs = root.querySelector('.thumbs');
  const paint = () => {
    thumbs.innerHTML = photos.map((p, i) => `<div class="thumb"><img src="${p.data_b64}" alt="Photo ${i + 1}"><button type="button" data-rm="${i}" aria-label="Remove photo ${i + 1}">×</button></div>`).join('');
  };
  input.addEventListener('change', async () => {
    for (const f of input.files) {
      if (photos.length >= max) { toast(`Up to ${max} photos`); break; }
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

// ----- shipment checks (pre-shipment and post-delivery share one form)

function deliveryItemHtml(i) {
  const id = i.id;
  let input;
  if (i.input_type === 'pass_fail') {
    input = `<div class="seg" role="radiogroup" aria-label="${esc(i.label)}">
      <label><input type="radio" name="v${id}" value="pass"><span class="pass">Pass</span></label>
      <label><input type="radio" name="v${id}" value="fail"><span class="fail">Fail</span></label>
      <label><input type="radio" name="v${id}" value="na"><span class="na">N/A</span></label></div>`;
  } else if (i.input_type === 'number') {
    input = `<input type="number" inputmode="decimal" step="any" name="v${id}" aria-label="${esc(i.label)}">${i.unit ? `<div class="small muted">${esc(i.unit)}</div>` : ''}`;
  } else if (i.input_type === 'choice') {
    input = `<select name="v${id}" aria-label="${esc(i.label)}"><option value="">Select…</option>${(i.options || []).map((o) => `<option>${esc(o)}</option>`).join('')}</select>`;
  } else if (i.input_type === 'photo') {
    input = '';
  } else {
    input = `<input type="text" name="v${id}" maxlength="255" aria-label="${esc(i.label)}">`;
  }
  return `<div class="check-item" data-item="${id}">
    <div class="lbl">${esc(i.label)}${i.required ? '' : ' <span class="muted">(optional)</span>'}</div>
    ${input}
    <textarea name="c${id}" placeholder="Comment${i.input_type === 'pass_fail' ? ' — what’s wrong?' : ' (optional)'}" ${i.input_type === 'pass_fail' ? 'hidden' : ''} maxlength="1000"></textarea>
    <div class="ph" data-ph="${id}"></div></div>`;
}

/** phase 'post' = delivery inspection (shipped shipments); 'pre' = pre-shipment check (drafts). */
function viewShipmentCheck(number, phase) {
  const pre = phase === 'pre';
  const sh = findShipment(number);
  const back = pre ? '#/pre' : '#/post';
  if (!sh || (pre ? !['draft', 'pre_check_done'].includes(sh.status) : sh.status !== 'shipped')) { toast(pre ? 'That shipment is no longer open for a pre-check.' : 'That delivery is no longer open.'); return go(back); }
  if (pre && !canInspect()) { toast('You don’t have access to pre-shipment checks.'); return go('#/'); }
  const groups = (pre ? sh.pre_checklist : sh.checklist) || [];
  const items = groups.flatMap((g) => g.items);
  if (!items.length) { toast('This shipment has no checklist for that step.'); return go(back); }
  $app.innerHTML = `
    ${topbar(pre ? 'Pre-shipment check' : 'Delivery inspection', back)}${statusBanner()}
    <p class="muted" style="margin-top:0"><strong>${esc(sh.shipment_number)}</strong> · ${esc(sh.job_label || '')}${sh.destination ? `<br>${esc(sh.destination)}` : ''}</p>
    <form id="f" novalidate>
      ${groups.map((g) => `<div class="card"><div class="row"><h2 class="grow" style="margin:0">${esc(g.type_name)}</h2></div>
        ${g.items.map(deliveryItemHtml).join('')}</div>`).join('')}
      <div class="card"><label for="n" style="margin-top:0">Notes</label><textarea id="n" maxlength="5000"></textarea>
        <label>${pre ? 'Photos of the load' : 'Photos of the delivery'}</label><div id="ph"></div></div>
      <p id="err" class="small" style="color:var(--bad)" role="alert"></p>
      <div class="sticky-submit"><button class="btn primary" type="submit">${pre ? 'Submit pre-check' : 'Submit inspection'}</button></div>
    </form>`;
  const form = document.getElementById('f');
  const pickers = {};
  items.forEach((i) => { pickers[i.id] = photoPicker(form.querySelector(`[data-ph="${i.id}"]`), i.input_type === 'photo' ? 10 : 5); });
  const general = photoPicker(document.getElementById('ph'), 10);

  form.addEventListener('change', (e) => {
    const m = /^v(\d+)$/.exec(e.target.name || '');
    const item = m && items.find((i) => i.id === +m[1]);
    if (item && item.input_type === 'pass_fail') form.elements[`c${item.id}`].hidden = e.target.value !== 'fail';
  });
  const answer = (i) => i.input_type === 'photo' ? (pickers[i.id].get().length ? String(pickers[i.id].get().length) : '')
    : (i.input_type === 'pass_fail'
    ? form.querySelector(`input[name=v${i.id}]:checked`)?.value
    : (form.elements[`v${i.id}`].value || '').trim()) || '';

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const err = document.getElementById('err');
    for (const i of items) {
      const v = answer(i), box = form.querySelector(`[data-item="${i.id}"]`);
      const needsPhoto = i.photo_required && (i.input_type !== 'pass_fail' || v === 'fail');
      const problem = (i.required && !v) ? (i.input_type === 'photo' ? `Add a photo for “${i.label}”.` : `Answer “${i.label}”.`)
        : (needsPhoto && v && !pickers[i.id].get().length) ? `Add a photo for “${i.label}”.`
        : (i.input_type === 'pass_fail' && v === 'fail' && !(form.elements[`c${i.id}`].value || '').trim()) ? `Say what’s wrong with “${i.label}”.` : '';
      if (problem) { err.textContent = problem; box.scrollIntoView({ block: 'center' }); return; }
    }
    const photos = [];
    const answers = [];
    for (const i of items) {
      const v = answer(i);
      if (!v) continue;
      const mine = pickers[i.id].get();
      const idx = mine.map((p) => photos.push(p) - 1);
      const comment = (form.elements[`c${i.id}`].value || '').trim();
      answers.push({ item_id: i.id, value: v, ...(comment && { comment }), ...(idx.length && { photos: idx }) });
    }
    general.get().forEach((p) => photos.push(p));
    if (photos.length > 30) { err.textContent = 'Too many photos (30 max). Remove a few.'; return; }
    const notes = document.getElementById('n').value.trim();
    const failed = answers.some((a) => a.value === 'fail');
    document.querySelectorAll('button[type=submit]').forEach((b) => { b.disabled = true; });
    await enqueue(pre ? 'shipment_precheck' : 'shipment_inspection', sh.shipment_number, { items: answers, ...(notes && { notes }) }, photos);
    await sleep(150);
    toast(`${failed ? `${pre ? 'Pre-check' : 'Inspection'} saved with problems. The office will be notified.` : `${pre ? 'Pre-check' : 'Inspection'} saved.`}${state.online ? '' : ' It will send when you’re online.'}`);
    go(back);
  });
}

// ----- one shipment: details, start its check, reschedule

function viewShipment(number) {
  const sh = findShipment(number);
  if (!sh) { toast('That shipment is no longer open.'); return go('#/shipping'); }
  const enc = encodeURIComponent(sh.shipment_number);
  const preDone = sentFor('shipment_precheck').has(sh.shipment_number), postDone = sentFor('shipment_inspection').has(sh.shipment_number);
  const movable = ['draft', 'pre_check_done'].includes(sh.status);
  const shipSent = sentFor('shipment_ship').has(sh.shipment_number);
  $app.innerHTML = `
    ${topbar(sh.shipment_number, '#/shipping')}${statusBanner()}
    <div class="card">
      <div class="row"><div class="grow"><strong>${esc(sh.job_label || 'No job')}</strong><div class="muted">${esc(sh.loads || '')}</div></div>${shipChip(sh.status)}</div>
      <p class="small" style="margin:10px 0 0">Ship date: <strong>${sh.ship_date ? esc(prettyDate(sh.ship_date)) : 'not scheduled'}</strong></p>
      ${sh.destination ? `<p class="small" style="margin:6px 0 0">Destination: ${esc(sh.destination)}</p>` : ''}
      ${sh.carrier ? `<p class="small" style="margin:6px 0 0">Carrier / driver: ${esc(sh.carrier)}</p>` : ''}
    </div>
    <div class="actions">
      ${movable && canInspect() && !preDone && sh.pre_checklist?.length ? `<button class="btn primary" data-go="#/precheck/${enc}">Start pre-shipment check</button>` : ''}
      ${movable && preDone ? '<div class="banner ok">Pre-shipment check sent.</div>' : ''}
      ${sh.status === 'pre_check_done' && !shipSent ? `<div class="banner ok">Pre-check passed. Ready to go.</div>${canManage() ? '<button class="btn primary" id="ship">Mark shipped</button>' : ''}` : ''}
      ${sh.status === 'pre_check_done' && shipSent ? '<div class="banner ok">Marked shipped. Waiting for the office system to confirm.</div>' : ''}
      ${sh.status === 'shipped' && !postDone ? `<button class="btn primary" data-go="#/delivery/${enc}">Start delivery inspection</button>` : ''}
      ${sh.status === 'shipped' && postDone ? '<div class="banner ok">Delivery inspection sent.</div>' : ''}
    </div>
    ${movable && canManage() ? `<form id="resched" class="card" style="margin-top:12px"><h2>Reschedule</h2>
      <label for="d" style="margin-top:0">Ship date</label><input id="d" type="date" value="${esc(sh.ship_date || '')}">
      <button class="btn" style="margin-top:10px" type="submit">Save date</button></form>` : ''}`;
  document.getElementById('ship')?.addEventListener('click', async (e) => {
    if (!confirm(`Mark ${sh.shipment_number} as shipped? It will then show up under Deliveries.`)) return;
    e.target.disabled = true;
    await enqueue('shipment_ship', sh.shipment_number, {}, []);
    sh.status = 'shipped';               // show it right away; the next sync confirms it
    sh.ship_date = sh.ship_date || todayStr();
    await saveSnapshot();
    toast(`Marked shipped.${state.online ? '' : ' It will send when you’re online.'}`);
    go('#/shipping');
  });
  document.getElementById('resched')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const date = document.getElementById('d').value || null;
    e.target.querySelector('button').disabled = true;
    await enqueue('shipment_reschedule', sh.shipment_number, { ship_date: date }, []);
    sh.ship_date = date;                 // show it right away; the next sync confirms it
    await saveSnapshot();
    toast(`Date saved.${state.online ? '' : ' It will send when you’re online.'}`);
    go('#/shipping');
  });
}

// ----- new shipment

function viewNewShipment() {
  if (!canManage()) { toast('You don’t have access to create shipments.'); return go('#/'); }
  const picked = new Map();
  const types = state.types;
  $app.innerHTML = `
    ${topbar('New shipment', '#/shipping')}${statusBanner()}
    <form id="f" novalidate>
      <div class="card"><h2>Jobs</h2>
        <div id="chips" class="chips"></div>
        <input id="js" type="search" placeholder="Search jobs by number or name" autocomplete="off">
        <ul class="list pick" id="jl"></ul></div>
      <div class="card"><h2>Details</h2>
        <label for="sd" style="margin-top:0">Ship date</label><input id="sd" type="date">
        <label for="ca">Carrier / driver</label><input id="ca" maxlength="255" autocomplete="off">
        <label for="de">Destination</label><input id="de" maxlength="255" autocomplete="off">
        <label for="no">Notes</label><textarea id="no" maxlength="5000"></textarea></div>
      <div class="card"><h2>What is on this shipment?</h2>
        ${types.map((t) => `<div class="loadrow"><label class="chk"><input type="checkbox" data-type="${t.type_id}"> ${esc(t.name)}</label>
          <input type="number" inputmode="numeric" min="0" placeholder="Pieces" data-pieces="${t.type_id}" aria-label="Pieces of ${esc(t.name)}"></div>`).join('')
          || '<p class="muted small" style="margin:0">No load types yet. Connect once to download them.</p>'}</div>
      <p id="err" class="small" style="color:var(--bad)" role="alert"></p>
      <div class="sticky-submit"><button class="btn primary" type="submit">Create shipment</button></div>
    </form>`;
  const form = document.getElementById('f');
  const drawChips = () => {
    document.getElementById('chips').innerHTML = picked.size
      ? [...picked.values()].map((j) => `<span class="chip">${esc(j.job_number)}<button type="button" data-rm="${esc(j.job_number)}" aria-label="Remove ${esc(j.job_number)}">×</button></span>`).join('')
      : '<span class="small muted">No jobs selected (optional).</span>';
  };
  const drawJobs = () => {
    const q = document.getElementById('js').value.trim().toLowerCase();
    const hits = state.jobs.filter((j) => !q || `${j.job_number} ${j.job_name}`.toLowerCase().includes(q)).slice(0, 25);
    document.getElementById('jl').innerHTML = hits.map((j) => `<li><label class="chk"><input type="checkbox" data-job="${esc(j.job_number)}" ${picked.has(j.job_number) ? 'checked' : ''}>
      <span><strong>${esc(j.job_number)}</strong> ${esc(j.job_name)}</span></label></li>`).join('') || `<li class="item muted">${state.jobs.length ? 'No matches.' : 'No jobs downloaded yet.'}</li>`;
  };
  drawChips(); drawJobs();
  document.getElementById('js').addEventListener('input', drawJobs);
  form.addEventListener('change', (e) => {
    const n = e.target.dataset?.job;
    if (n == null) return;
    if (e.target.checked) picked.set(n, state.jobs.find((j) => j.job_number === n)); else picked.delete(n);
    drawChips();
  });
  form.addEventListener('click', (e) => {
    const n = e.target.dataset?.rm;
    if (n == null) return;
    picked.delete(n); drawChips(); drawJobs();
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const err = document.getElementById('err');
    const loads = [...form.querySelectorAll('[data-type]:checked')].map((c) => {
      const raw = form.querySelector(`[data-pieces="${c.dataset.type}"]`).value;
      return { type_id: +c.dataset.type, ...(raw !== '' && { piece_count: Math.max(0, parseInt(raw, 10) || 0) }) };
    });
    if (!loads.length) { err.textContent = 'Pick at least one thing that is on the shipment.'; return; }
    const val = (id) => document.getElementById(id).value.trim();
    const payload = {
      job_numbers: [...picked.keys()], loads,
      ...(val('sd') && { ship_date: val('sd') }), ...(val('ca') && { carrier: val('ca') }),
      ...(val('de') && { destination: val('de') }), ...(val('no') && { notes: val('no') }),
    };
    form.querySelector('[type=submit]').disabled = true;
    await enqueue('shipment_create', `NEW-${uuidv4().slice(0, 6).toUpperCase()}`, payload, []);
    await sleep(150);
    toast(`Shipment saved.${state.online ? ' The office will see it shortly.' : ' It will send when you’re online.'}`);
    go('#/shipping');
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

const KIND = { inspection: 'Inspection', meter: 'Meter reading', defect: 'Problem report', shipment_inspection: 'Delivery inspection', shipment_precheck: 'Pre-shipment check', shipment_create: 'New shipment', shipment_reschedule: 'Reschedule', shipment_ship: 'Marked shipped' };

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
