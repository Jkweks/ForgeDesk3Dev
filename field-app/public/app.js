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
const APP_VERSION = '2.0';

// Inline icons (stroke, currentColor) so nothing depends on emoji fonts or external assets.
const ICON_PATHS = {
  truck: '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
  clipboard: '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9zM9 13l2 2 4-4"/>',
  box: '<path d="M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10"/>',
  calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
  scan: '<path d="M4 8V5a1 1 0 011-1h3M16 4h3a1 1 0 011 1v3M20 16v3a1 1 0 01-1 1h-3M8 20H5a1 1 0 01-1-1v-3M4 12h16"/>',
  refresh: '<path d="M20 11a8 8 0 10-2.3 5.7M20 4v7h-7"/>',
  back: '<path d="M15 5l-7 7 7 7"/>',
  check: '<path d="M5 12l5 5 9-10"/>',
  x: '<path d="M6 6l12 12M18 6L6 18"/>',
  minus: '<path d="M6 12h12"/>',
  camera: '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
  image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.7"/><path d="M21 16l-5-5-8 8"/>',
  bolt: '<path d="M13 3L5 14h6l-1 7 8-11h-6z"/>',
  eye: '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  alert: '<path d="M12 3l10 18H2zM12 10v5M12 18v.5"/>',
  gauge: '<path d="M4 17a8 8 0 1116 0M12 17l4-5"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  wrench: '<path d="M14 6a4 4 0 005 5l-9 9a2 2 0 01-3-3l9-9a4 4 0 01-2-2z"/>',
};
const icon = (name) => `<svg class="i" viewBox="0 0 24 24" aria-hidden="true">${ICON_PATHS[name] || ''}</svg>`;

/** "just now", "5 min ago", "3 h ago", or a date. */
function ago(iso) {
  if (!iso) return 'never';
  const s = (Date.now() - new Date(iso).getTime()) / 1000;
  if (s < 45) return 'just now';
  if (s < 3600) return `${Math.round(s / 60)} min ago`;
  if (s < 86400) return `${Math.round(s / 3600)} h ago`;
  return new Date(iso).toLocaleDateString();
}

/** In-app replacement for confirm(): a bottom sheet. Resolves true when the user confirms. */
function confirmSheet(title, message, okLabel = 'Confirm', danger = false) {
  return new Promise((resolve) => {
    const el = document.createElement('div');
    el.className = 'overlay';
    el.setAttribute('role', 'dialog'); el.setAttribute('aria-modal', 'true');
    el.innerHTML = `<div class="sheet"><h3>${esc(title)}</h3>${message ? `<p>${esc(message)}</p>` : ''}
      <div class="btn-pair"><button class="btn" type="button" data-r="0">Cancel</button>
      <button class="btn ${danger ? 'danger solid' : 'primary'}" type="button" data-r="1">${esc(okLabel)}</button></div></div>`;
    const done = (v) => { el.remove(); resolve(v); };
    el.addEventListener('click', (e) => { if (e.target === el) done(false); else if (e.target.dataset.r != null) done(e.target.dataset.r === '1'); });
    document.body.appendChild(el);
    el.querySelector('[data-r="1"]').focus();
  });
}

// ---------- drafts ----------------------------------------------------------------------
// Forms are autosaved (text, choices; not photos) so an OS kill, a phone call or a dropped signal
// never costs the crew a half-finished inspection. Cleared on submit; ignored after 24 hours.
const DRAFT_MS = 24 * 3600 * 1000;
let draftTimer;
const draftKey = () => `draft:${location.hash}`;

function bindDraft(form) {
  const key = draftKey();
  const fields = () => [...form.elements].filter((el) => (el.name || el.id) && !['file', 'password', 'submit', 'button'].includes(el.type));
  const snap = () => {
    const o = {};
    for (const el of fields()) {
      const k = el.name || el.id;
      if (el.type === 'radio') { if (el.checked) o[k] = el.value; }
      else if (el.type === 'checkbox') { if (el.checked) o[k] = true; }
      else if (el.value !== '') o[k] = el.value;
    }
    return o;
  };
  const save = () => {
    clearTimeout(draftTimer);
    draftTimer = setTimeout(() => { const v = snap(); (Object.keys(v).length ? db.set(key, { v, t: Date.now() }) : db.del(key)).catch(() => {}); }, 400);
  };
  form.addEventListener('input', save);
  form.addEventListener('change', save);
  db.get(key).then((d) => {
    if (!d || !form.isConnected || Date.now() - d.t > DRAFT_MS) return;
    let any = false;
    for (const el of fields()) {
      const k = el.name || el.id;
      if (!(k in d.v)) continue;
      any = true;
      if (el.type === 'radio') { if (el.value === d.v[k]) { el.checked = true; el.dispatchEvent(new Event('change', { bubbles: true })); } }
      else if (el.type === 'checkbox') el.checked = !!d.v[k];
      else { el.value = d.v[k]; el.dispatchEvent(new Event('input', { bubbles: true })); }
    }
    if (any) toast('Restored your unsaved draft.');
  }).catch(() => {});
}

async function clearDraft() { clearTimeout(draftTimer); await db.del(draftKey()).catch(() => {}); }

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
const canReportDev = () => can('dev.report');
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
    updateBanner();
    softRender();
  }
}

/** Pull fresh data and repaint list screens. Never touches a form the user may be filling in. */
async function refreshNow(manual = false) {
  try {
    const ok = await refresh();
    if (ok) { flush(); softRender(); if (manual) toast('Up to date.'); }
    else if (manual) toast('No connection. Showing saved data.');
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) render();
    else if (manual) toast(e.message);
  }
  updateBanner();
}

/** Re-render only screens that have no inputs (lists, details), and only when nothing is focused. */
const SOFT_ROUTES = new Set(['', 'pre', 'post', 'shipping', 'me', 'queue', 'asset']);
function softRender() {
  if (state.needLogin || !state.user) return;
  const route = decodeURIComponent((location.hash || '#/').split('/')[1] ?? '');
  if (!SOFT_ROUTES.has(route)) return;
  if (document.getElementById('devSheet') || document.querySelector('.overlay')) return;
  if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || '')) return;
  render();
}

const pendingCount = () => state.outbox.filter((r) => r.status !== 'failed').length;
const failedCount = () => state.outbox.filter((r) => r.status === 'failed').length;

// ---------- router ----------------------------------------------------------------------

let stopScan = null;

function go(hash) { if (location.hash === hash) render(); else location.hash = hash; }

async function render() {
  syncDevFab();
  if (stopScan) { stopScan(); stopScan = null; }
  await routeView();
  // Screens without the bottom tab bar (forms, details) don't need room reserved for it.
  $app.classList.toggle('notabs', !$app.querySelector('.tabs'));
}

function routeView() {
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
    case 'trip': return viewTrip(arg);
    case 'newshipment': return viewNewShipment();
    case 'scan': return viewScan();
    case 'me':
    case 'queue': return viewMe();
    default: return viewHome();
  }
}
window.addEventListener('hashchange', () => { window.scrollTo(0, 0); render(); });

const findAsset = (n) => state.assets.find((a) => a.asset_number === n);

function topbar(title, back, actions = '', sub = '') {
  return `<div class="topbar">${back ? `<button class="back" data-go="${back}" aria-label="Back">${icon('back')}Back</button>` : ''}<h1>${esc(title)}${sub ? `<span class="sub">${esc(sub)}</span>` : ''}</h1>${actions}</div>`;
}

/** Bottom navigation on the top-level screens. Sections the signed-in user can't use are left out. */
function tabs(active) {
  const items = [['fleet', '#/', 'Fleet', 'truck', 0]];
  if (canInspect()) items.push(['pre', '#/pre', 'Pre-check', 'clipboard', openPreChecks().length]);
  items.push(['post', '#/post', 'Deliveries', 'box', openDeliveries().length]);
  if (canSeeQueue()) items.push(['shipping', '#/shipping', 'Shipping', 'calendar', 0]);
  items.push(['me', '#/me', 'Me', 'user', pendingCount() + failedCount()]);
  return `<nav class="tabs" aria-label="Sections">${items.map(([k, h, label, ico, n]) => `
    <a href="${h}" data-go="${h}" class="${k === active ? 'on' : ''}" ${k === active ? 'aria-current="page"' : ''}>
      ${icon(ico)}<span>${label}</span>${n ? `<b class="dot">${n}</b>` : ''}</a>`).join('')}</nav>`;
}

function statusBannerInner() {
  const bits = [];
  if (!state.online) bits.push(`<div class="banner warn">${icon('alert')}<div class="grow">You’re offline. Keep working; everything sends when you’re back in range.</div></div>`);
  const p = pendingCount(), f = failedCount();
  if (p) bits.push(`<div class="banner warn"><div class="grow">${p} waiting to send.</div><button data-go="#/me">View</button></div>`);
  if (f) bits.push(`<div class="banner bad"><div class="grow">${f} couldn’t be sent.</div><button data-go="#/me">Review</button></div>`);
  return bits.join('');
}
/** Wrapped so connection changes can update it in place without re-rendering (and wiping) the screen. */
const statusBanner = () => `<div id="status">${statusBannerInner()}</div>`;
function updateBanner() { const el = document.getElementById('status'); if (el) el.innerHTML = statusBannerInner(); }

// Delegated navigation so templates stay free of inline handlers (CSP forbids them).
document.addEventListener('click', (e) => {
  const t = e.target.closest('[data-go]');
  if (t) { e.preventDefault(); go(t.dataset.go === 'back' ? '#/' : t.dataset.go); }
});

// ---------- views -----------------------------------------------------------------------

function viewLogin() {
  $app.innerHTML = `
    <div class="login-brand"><img src="/icons/icon-192.png" alt=""><h1>ForgeDesk Field</h1><p>Inspections, shipments and fleet</p></div>
    <form id="login" class="card" autocomplete="on">
      ${state.needLogin && state.user ? '<div class="banner warn">Your session ended. Sign in to continue. Unsent work is safe.</div>' : ''}
      <label for="u" style="margin-top:0">Email</label>
      <input id="u" name="username" type="email" inputmode="email" autocomplete="username" autocapitalize="none" autocorrect="off" required>
      <label for="p">Password</label>
      <div class="pw"><input id="p" name="password" type="password" autocomplete="current-password" required>
        <button class="icon-btn" type="button" id="pwt" aria-label="Show password">${icon('eye')}</button></div>
      <p id="err" class="err" role="alert"></p>
      <button class="btn primary" type="submit" style="margin-top:12px">Sign in</button>
    </form>
    <p class="small muted" style="text-align:center">Use your ForgeDesk email and password.<br>Not working? Ask an administrator to enable mobile access.</p>`;
  document.getElementById('pwt').addEventListener('click', () => {
    const p = document.getElementById('p'); p.type = p.type === 'password' ? 'text' : 'password';
  });
  document.getElementById('login').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type=submit]'); btn.disabled = true; btn.textContent = 'Signing in…';
    try {
      await api('/login', { method: 'POST', body: { username: e.target.elements.username.value.trim(), password: e.target.elements.password.value } });
      state.needLogin = false;
      await refresh();
      flush();
      go('#/');
    } catch (err) {
      document.getElementById('err').textContent = err instanceof ApiError ? err.message : 'Can’t reach the server. Check your connection.';
      btn.disabled = false; btn.textContent = 'Sign in';
    }
  });
}

const isAttn = (a) => a.status === 'out_of_service' || a.status === 'in_repair';
const onJob = (a) => a.status === 'in_service_job' || !!a.current_job_label;

function assetRow(a) {
  const tone = a.status === 'out_of_service' ? 'bad' : a.status === 'in_repair' ? 'warn' : '';
  return `<li><a href="#/asset/${encodeURIComponent(a.asset_number)}" data-go="#/asset/${encodeURIComponent(a.asset_number)}">
    <span class="avatar ${tone}">${icon(tone === 'bad' ? 'alert' : tone === 'warn' ? 'wrench' : 'truck')}</span>
    <div class="grow"><div><span class="num">${esc(a.asset_number)}</span> ${esc(a.name)}</div>
    <div class="small muted">${esc(a.type_name)}${a.current_job_label ? ` · ${esc(a.current_job_label)}` : ''}</div></div>
    ${statusChip(a.status)}</a></li>`;
}

function viewHome() {
  const counts = { all: state.assets.length, attn: state.assets.filter(isAttn).length, job: state.assets.filter(onJob).length };
  const FILTERS = [['all', 'All'], ['attn', 'Needs attention'], ['job', 'On a job']];
  $app.innerHTML = `
    ${topbar('Fleet', '', `<button class="icon-btn" id="refresh" aria-label="Refresh">${icon('refresh')}</button>`, state.syncedAt ? `Updated ${ago(state.syncedAt)}` : 'Not downloaded yet')}
    ${statusBanner()}
    <button class="btn primary lg" data-go="#/scan" style="margin-bottom:12px">${icon('scan')} Scan asset QR code</button>
    <input id="q" type="search" placeholder="Search number, name, type or job" value="${esc(sessionStorage.getItem('q') || '')}" autocomplete="off" aria-label="Search assets">
    <div class="filters" id="filters" style="margin-top:12px">${FILTERS.map(([k, l]) => `<button class="fchip" type="button" data-f="${k}">${l}<b>${counts[k]}</b></button>`).join('')}</div>
    <div class="card flush"><ul class="list" id="list"></ul></div>${tabs('fleet')}`;

  const paint = () => {
    const q = (sessionStorage.getItem('q') || '').trim().toLowerCase();
    const f = sessionStorage.getItem('f') || 'all';
    document.querySelectorAll('#filters .fchip').forEach((b) => b.classList.toggle('on', b.dataset.f === f));
    const list = state.assets.filter((a) => (f === 'all' || (f === 'attn' ? isAttn(a) : onJob(a)))
      && (!q || [a.asset_number, a.name, a.type_name, a.current_job_label].some((v) => String(v || '').toLowerCase().includes(q))));
    document.getElementById('list').innerHTML = list.map(assetRow).join('')
      || `<li class="empty">${icon('truck')}${state.assets.length ? 'No matching assets.' : 'No assets yet. Connect once to download them.'}</li>`;
  };
  paint();
  document.getElementById('q').addEventListener('input', (e) => { sessionStorage.setItem('q', e.target.value); paint(); });
  document.getElementById('filters').addEventListener('click', (e) => { const f = e.target.closest('[data-f]')?.dataset.f; if (f) { sessionStorage.setItem('f', f); paint(); } });
  document.getElementById('refresh').addEventListener('click', async (e) => {
    const b = e.currentTarget; b.classList.add('spin');
    await refreshNow(true);
    b.classList.remove('spin');
  });
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

// ---- map links ----------------------------------------------------------------------------
// One link per stop, shaped for the phone it's opened on: iPhones open Apple Maps, Android's geo: link brings up the
// phone's own "open with" chooser (Google Maps, Waze, ...), and anything else gets Google Maps on the web.
function mapLink(name, address, lat, lng) {
  const hasPos = lat != null && lng != null;
  const q = encodeURIComponent(address || name || '');
  const ua = navigator.userAgent;
  if (/iPhone|iPad|iPod/.test(ua)) return `https://maps.apple.com/?daddr=${hasPos ? `${lat},${lng}` : q}&q=${q}&dirflg=d`;
  if (/Android/.test(ua)) return `geo:${hasPos ? `${lat},${lng}` : '0,0'}?q=${q}`;
  return `https://www.google.com/maps/dir/?api=1&destination=${hasPos ? `${lat},${lng}` : q}`;
}

/** One line on the shipment page; the full address and directions live on the trip page. */
function stopHtml(label, name, address) {
  if (!name && !address) return '';
  return `<div class="small" style="margin-top:6px"><strong>${label}:</strong> ${esc(name || '')}${address ? `<div class="muted">${esc(address)}</div>` : ''}</div>`;
}

function viewTrip(number) {
  const sh = findShipment(number);
  if (!sh) { toast('That shipment is no longer open.'); return go('#/shipping'); }
  const stops = [
    { key: 'pickup', label: 'Pickup', name: sh.pickup_name, address: sh.pickup_address, lat: sh.pickup_lat, lng: sh.pickup_lng },
    { key: 'dropoff', label: 'Drop off', name: sh.dropoff_name, address: sh.dropoff_address, lat: sh.dropoff_lat, lng: sh.dropoff_lng },
  ].filter((s) => s.name || s.address);
  $app.innerHTML = `
    ${topbar('Trip details', `#/shipment/${encodeURIComponent(sh.shipment_number)}`)}${statusBanner()}
    <div class="card">
      <div class="row"><div class="grow"><strong>${esc(sh.shipment_number)}</strong> ${esc(sh.job_label || 'No job')}
        <div class="muted small">${esc([sh.ship_date ? prettyDate(sh.ship_date) : 'No date', sh.loads].filter(Boolean).join(' · '))}</div></div>${shipChip(sh.status)}</div>
      ${sh.carrier ? `<p class="small" style="margin:8px 0 0">Carrier / driver: ${esc(sh.carrier)}</p>` : ''}
    </div>
    ${stops.length ? stops.map((s) => {
      const target = s.address || (s.lat != null && s.lng != null);
      return `<div class="card"><h2>${s.label}</h2>
        <div><strong>${esc(s.name || '')}</strong></div>
        ${s.address ? `<div class="addr" style="margin-top:4px">${esc(s.address)}</div>` : '<div class="muted small">No address on file.</div>'}
        ${target ? `<div class="row" style="margin-top:10px;gap:8px">
          <a class="btn primary small grow" href="${esc(mapLink(s.name, s.address, s.lat, s.lng))}" target="_blank" rel="noopener">Open in maps</a>
          ${s.address ? `<button class="btn small" type="button" data-copy="${s.key}">Copy</button>` : ''}</div>` : ''}</div>`;
    }).join('') : '<div class="card muted">No pickup or drop-off locations on this shipment.</div>'}`;

  const byKey = Object.fromEntries(stops.map((s) => [s.key, s]));
  document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
    const text = byKey[b.dataset.copy].address;
    try { await navigator.clipboard.writeText(text); toast('Address copied.'); } catch { toast('Press and hold the address to copy it.'); }
  }));
}

function shipRow(sh, href, chipHtml) {
  const late = sh.ship_date && sh.ship_date < todayStr() && sh.status !== 'shipped';
  const tone = late ? 'bad' : sh.status === 'shipped' ? 'warn' : sh.status === 'pre_check_done' ? 'ok' : '';
  return `<li><a href="${href}" data-go="${href}">
    <span class="avatar ${tone}">${icon(sh.status === 'shipped' ? 'truck' : sh.status === 'pre_check_done' ? 'check' : 'box')}</span>
    <div class="grow">
    <div><span class="num">${esc(sh.shipment_number)}</span> ${esc(sh.job_label || 'No job')}</div>
    <div class="small muted">${esc([sh.ship_date ? prettyDate(sh.ship_date) : 'No date', sh.destination, sh.loads].filter(Boolean).join(' · '))}</div></div>
    ${chipHtml ?? shipChip(sh.status)}</a></li>`;
}

const newShipmentButton = () => (canManage() ? `<button class="btn small primary" data-go="#/newshipment">${icon('plus')} New</button>` : '');
const emptyState = (ico, text) => `<li class="empty">${icon(ico)}${esc(text)}</li>`;

function viewPre() {
  if (!canInspect()) return go('#/');
  const list = openPreChecks();
  $app.innerHTML = `
    ${topbar('Pre-shipment checks', '', newShipmentButton(), list.length ? `${list.length} waiting` : '')}${statusBanner()}
    <div class="card flush"><ul class="list">${list.map((sh) => shipRow(sh, `#/precheck/${encodeURIComponent(sh.shipment_number)}`, '<span class="chip warn">Check</span>')).join('')
      || emptyState('clipboard', 'Nothing is waiting for a pre-shipment check.')}</ul></div>${tabs('pre')}`;
}

function viewPost() {
  const list = openDeliveries();
  $app.innerHTML = `
    ${topbar('Deliveries', '', '', list.length ? `${list.length} to inspect` : '')}${statusBanner()}
    <div class="card flush"><ul class="list">${list.map((sh) => shipRow(sh, `#/delivery/${encodeURIComponent(sh.shipment_number)}`, '<span class="chip warn">Inspect</span>')).join('')
      || emptyState('box', 'No deliveries are waiting for inspection.')}</ul></div>${tabs('post')}`;
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
  const section = (title, list, cls = '') => (list.length ? `<div class="card flush"><h2 class="${cls}" style="padding-top:12px;margin:0">${esc(title)}</h2><ul class="list">${list.map((sh) => shipRow(sh, `#/shipment/${encodeURIComponent(sh.shipment_number)}`)).join('')}</ul></div>` : '');
  const unsent = state.outbox.filter((r) => r.kind === 'shipment_create');
  const todayN = (byDate.get(today) || []).length;
  const ready = state.shipments.filter((s) => s.status === 'pre_check_done').length;
  $app.innerHTML = `
    ${topbar('Shipping', '', newShipmentButton(), `${state.shipments.length} open`)}${statusBanner()}
    <div class="stats">
      <div class="stat ${late.length ? 'bad' : ''}"><b>${late.length}</b><span>Late</span></div>
      <div class="stat ${todayN ? 'warn' : ''}"><b>${todayN}</b><span>Today</span></div>
      <div class="stat ${ready ? 'ok' : ''}"><b>${ready}</b><span>Ready to ship</span></div></div>
    ${unsent.length ? `<div class="card"><h2>Waiting to send</h2><ul class="list">${unsent.map((r) => `<li><div class="item"><div class="grow"><strong>New shipment</strong>
      <div class="small muted">${esc([r.payload.destination, r.payload.job_numbers?.join(', ')].filter(Boolean).join(' · ') || 'No details')}</div></div><span class="chip warn">Waiting</span></div></li>`).join('')}</ul></div>` : ''}
    ${section('Late', late, 'late')}
    ${[...byDate.keys()].sort().map((d) => section(d === today ? `Today · ${prettyDate(d)}` : prettyDate(d), byDate.get(d))).join('')}
    ${section('Not scheduled', unscheduled)}
    ${state.shipments.length ? '' : `<div class="card flush"><ul class="list">${emptyState('calendar', 'No open shipments.')}</ul></div>`}
    ${tabs('shipping')}`;
}

function viewAsset(number) {
  const a = findAsset(number);
  if (!a) { toast('Asset not found'); return go('#/'); }
  const enc = encodeURIComponent(a.asset_number);
  const hasMeter = a.meter_type && a.meter_type !== 'none';
  const tone = a.status === 'out_of_service' ? 'bad' : a.status === 'in_repair' ? 'warn' : '';
  // What this phone has sent for this asset lately (still queued first).
  const activity = [...state.outbox.map((r) => ({ ...r, queued: true })), ...state.recent.map((r) => ({ ...r, queued: false }))]
    .filter((r) => r.asset_number === a.asset_number && KIND[r.kind]).slice(0, 5);
  $app.innerHTML = `
    ${topbar(a.asset_number, '#/')}${statusBanner()}
    <div class="card">
      <div class="asset-head"><span class="avatar ${tone}">${icon(tone === 'bad' ? 'alert' : 'truck')}</span>
        <div class="grow"><strong style="font-size:1.1rem">${esc(a.name)}</strong><div class="muted">${esc(a.type_name)}</div>
        ${a.current_job_label ? `<div class="small muted">At ${esc(a.current_job_label)}</div>` : ''}</div>${statusChip(a.status)}</div>
      ${hasMeter ? `<div class="meter-box"><span class="muted small">${esc(METER_LABEL[a.meter_type] || 'Meter')}</span><b>${a.current_meter != null ? Number(a.current_meter).toLocaleString() : '–'}</b></div>` : ''}
    </div>
    ${a.status === 'out_of_service' ? `<div class="banner bad">${icon('alert')}<div class="grow">Out of service. Do not use it until the office clears it.</div></div>` : ''}
    <div class="actions">
      <button class="btn primary lg" data-go="#/inspect/${enc}">${icon('clipboard')} Start inspection</button>
      <div class="btn-pair">
        ${hasMeter ? `<button class="btn" data-go="#/meter/${enc}">${icon('gauge')} ${a.meter_type === 'engine_hours' ? 'Hours' : 'Odometer'}</button>` : ''}
        <button class="btn" data-go="#/defect/${enc}" ${hasMeter ? '' : 'style="grid-column:1/-1"'}>${icon('alert')} Report problem</button>
      </div>
    </div>
    ${activity.length ? `<div class="card"><h2>Your recent activity</h2><ul class="list">${activity.map((r) => `
      <li><div class="item" style="min-height:48px"><div class="grow"><strong>${esc(KIND[r.kind])}</strong>
        <div class="small muted">${new Date(r.submitted_at).toLocaleString()}</div></div>
        <span class="chip ${r.queued ? 'warn' : r.processed ? 'ok' : ''}">${r.queued ? (r.status === 'failed' ? 'Failed' : 'Waiting') : r.processed ? 'Office has it' : 'Delivered'}</span></div></li>`).join('')}</ul></div>` : ''}`;
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
  let busy = 0;
  root.innerHTML = `
    <div class="photo-btns">
      <label class="btn file-btn">${icon('camera')} Take photo<input type="file" accept="image/*" capture="environment" data-src="cam"></label>
      <label class="btn file-btn">${icon('image')} Choose<input type="file" accept="image/*" multiple data-src="lib"></label>
    </div>
    <div class="thumbs"></div>`;
  const thumbs = root.querySelector('.thumbs');
  const paint = () => {
    thumbs.innerHTML = photos.map((p, i) => `<div class="thumb"><img src="${p.data_b64}" alt="Photo ${i + 1}"><button type="button" data-rm="${i}" aria-label="Remove photo ${i + 1}">×</button></div>`).join('')
      + '<div class="thumb busy">Adding…</div>'.repeat(busy);
  };
  root.querySelectorAll('input[type=file]').forEach((input) => input.addEventListener('change', async () => {
    const files = [...input.files];
    input.value = '';
    busy += files.length; paint();
    for (const f of files) {
      busy -= 1;
      if (photos.length >= max) { toast(`Up to ${max} photos`); continue; }
      try { photos.push({ filename: `photo-${photos.length + 1}.jpg`, data_b64: await shrinkImage(f) }); } catch (e) { toast(e.message); }
      paint();
    }
    paint();
  }));
  thumbs.addEventListener('click', (e) => { const i = e.target.dataset.rm; if (i != null) { photos.splice(+i, 1); paint(); } });
  return { get: () => photos.slice() };
}

// ----- forms

function checklistFor(asset) {
  const c = state.checklists.find((x) => x.type_name === asset.type_name);
  const items = (c?.items || []).map((i) => i.label).filter(Boolean);
  return { template: c?.template_name || `${asset.type_name} inspection`, items: items.length ? items : ['General condition'] };
}

/** Pass / Fail / N/A buttons shared by every checklist. */
const segHtml = (name, label) => `<div class="seg" role="radiogroup" aria-label="${esc(label)}">
  <label><input type="radio" name="${name}" value="pass"><span class="pass">${icon('check')} Pass</span></label>
  <label><input type="radio" name="${name}" value="fail"><span class="fail">${icon('x')} Fail</span></label>
  <label><input type="radio" name="${name}" value="na"><span class="na">N/A</span></label></div>`;

function viewInspect(number) {
  const a = findAsset(number);
  if (!a) return go('#/');
  const { template, items } = checklistFor(a);
  const hasMeter = a.meter_type && a.meter_type !== 'none';
  $app.innerHTML = `
    ${topbar('Inspection', `#/asset/${encodeURIComponent(number)}`, '', `${a.asset_number} · ${a.name}`)}
    <form id="f" novalidate>
      <div class="progress" id="prog"><div class="meta"><span id="pc"></span><span id="pf"></span></div><div class="track"><div class="bar" id="pb"></div></div></div>
      ${hasMeter ? `<div class="card"><label for="m" style="margin-top:0">${esc(METER_LABEL[a.meter_type])} <span class="muted">(last: ${a.current_meter ?? '–'})</span></label>
        <input id="m" type="number" inputmode="decimal" step="any" min="0"></div>` : ''}
      <div class="card"><div class="row"><h2 class="grow" style="margin:0">${esc(template)}</h2><button type="button" class="btn small" id="allpass">Mark rest Pass</button></div>
        ${items.map((label, i) => `
          <div class="check-item" data-i="${i}">
            <div class="lbl">${esc(label)}</div>
            ${segHtml(`r${i}`, label)}
            <textarea name="c${i}" placeholder="What’s wrong?" hidden maxlength="1000"></textarea>
          </div>`).join('')}
      </div>
      <div class="card"><label for="n" style="margin-top:0">Notes</label><textarea id="n" maxlength="5000"></textarea>
        <label>Photos</label><div id="ph"></div></div>
      <p id="err" class="err" role="alert"></p>
      <div class="sticky-submit"><button class="btn primary" type="submit">Submit inspection</button></div>
    </form>`;
  const form = document.getElementById('f');
  const pics = photoPicker(document.getElementById('ph'));
  const answered = (i) => !!form.querySelector(`input[name=r${i}]:checked`);

  const progress = () => {
    const done = items.filter((_, i) => answered(i)).length;
    const failed = items.filter((_, i) => form.querySelector(`input[name=r${i}]:checked`)?.value === 'fail').length;
    document.getElementById('pc').textContent = `${done} of ${items.length} answered`;
    document.getElementById('pf').textContent = failed ? `${failed} failed` : '';
    document.getElementById('pf').style.color = 'var(--bad)';
    document.getElementById('pb').style.width = `${items.length ? (done / items.length) * 100 : 100}%`;
    document.getElementById('prog').classList.toggle('done', done === items.length);
  };
  progress();

  form.addEventListener('change', (e) => {
    const m = /^r(\d+)$/.exec(e.target.name || '');
    if (!m) return;
    const i = +m[1], box = form.querySelector(`[data-i="${i}"]`);
    const wasDone = box.classList.contains('done');
    box.classList.add('done');
    const ta = form.elements[`c${i}`];
    ta.hidden = e.target.value !== 'fail';
    progress();
    if (!e.isTrusted) return;                 // a restored draft: don't move the screen
    if (e.target.value === 'fail') { ta.focus(); return; }
    if (!wasDone) {                            // first answer on this line: bring up the next open one
      const next = items.findIndex((_, j) => j > i && !answered(j));
      if (next >= 0) form.querySelector(`[data-i="${next}"]`).scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
  });
  document.getElementById('allpass').addEventListener('click', () => {
    items.forEach((_, i) => {
      if (answered(i)) return;
      form.querySelector(`input[name=r${i}][value=pass]`).checked = true;
      form.querySelector(`[data-i="${i}"]`).classList.add('done');
    });
    progress();
    form.dispatchEvent(new Event('change'));
  });
  bindDraft(form);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const err = document.getElementById('err');
    const results = items.map((label, i) => {
      const v = form.querySelector(`input[name=r${i}]:checked`)?.value;
      return { label, result: v, comment: (form.elements[`c${i}`].value || '').trim() };
    });
    const flag = (i, msg) => { err.textContent = msg; const box = form.querySelector(`[data-i="${i}"]`); box.scrollIntoView({ block: 'center' }); box.classList.remove('flag'); void box.offsetWidth; box.classList.add('flag'); navigator.vibrate?.(40); };
    const missing = results.findIndex((r) => !r.result);
    if (missing >= 0) return flag(missing, `Answer “${results[missing].label}”.`);
    const noComment = results.findIndex((r) => r.result === 'fail' && !r.comment);
    if (noComment >= 0) { flag(noComment, `Say what’s wrong with “${results[noComment].label}”.`); form.elements[`c${noComment}`].focus(); return; }
    const payload = { template_name: template, items: results.map((r) => ({ label: r.label, result: r.result, ...(r.comment && { comment: r.comment }) })) };
    const notes = document.getElementById('n').value.trim();
    if (notes) payload.notes = notes;
    const mEl = document.getElementById('m');
    if (mEl && mEl.value !== '') {
      const meter = parseFloat(mEl.value);
      if (a.current_meter != null && meter < a.current_meter && !(await confirmSheet('Lower than last reading', `That’s lower than the last reading (${a.current_meter}). The office will be asked to review it.`, 'Send anyway'))) return;
      payload.meter = meter;
    }
    await finish('inspection', a, payload, pics.get(), results.some((r) => r.result === 'fail') ? 'Inspection saved with failed items. The office will be notified.' : 'Inspection saved.');
  });
}

function viewMeter(number) {
  const a = findAsset(number);
  if (!a) return go('#/');
  $app.innerHTML = `
    ${topbar('Update meter', `#/asset/${encodeURIComponent(number)}`, '', `${a.asset_number} · ${a.name}`)}
    <form id="f" class="card">
      <div class="meter-box" style="margin-top:0"><span class="muted small">Last reading</span><b>${a.current_meter != null ? Number(a.current_meter).toLocaleString() : '–'}</b></div>
      <label for="m">${esc(METER_LABEL[a.meter_type] || 'Reading')}</label>
      <input id="m" type="number" inputmode="decimal" step="any" min="0" required autofocus>
      <p id="err" class="err" role="alert"></p>
      <button class="btn primary" type="submit" style="margin-top:12px">Save reading</button>
    </form>`;
  bindDraft(document.getElementById('f'));
  document.getElementById('f').addEventListener('submit', async (e) => {
    e.preventDefault();
    const reading = parseFloat(document.getElementById('m').value);
    if (!Number.isFinite(reading) || reading < 0) { document.getElementById('err').textContent = 'Enter a valid number.'; return; }
    if (a.current_meter != null && reading < a.current_meter && !(await confirmSheet('Lower than last reading', `That’s lower than the last reading (${a.current_meter}). The office will be asked to review it.`, 'Send anyway'))) return;
    await finish('meter', a, { reading }, [], 'Reading saved.');
  });
}

function viewDefect(number) {
  const a = findAsset(number);
  if (!a) return go('#/');
  $app.innerHTML = `
    ${topbar('Report a problem', `#/asset/${encodeURIComponent(number)}`, '', `${a.asset_number} · ${a.name}`)}
    <form id="f" class="card">
      <label for="t" style="margin-top:0">What’s wrong?</label><input id="t" maxlength="255" required placeholder="e.g. Hydraulic leak at left cylinder">
      <label for="s">How bad is it?</label>
      <select id="s"><option value="low">Low: can wait</option><option value="medium" selected>Medium: fix soon</option><option value="high">High: needs attention</option><option value="critical">Critical: unsafe to use</option></select>
      <label for="d">Details</label><textarea id="d" maxlength="5000"></textarea>
      <label>Photos</label><div id="ph"></div>
      <div class="sticky-submit"><button class="btn primary" type="submit">Send report</button></div>
    </form>`;
  const pics = photoPicker(document.getElementById('ph'));
  bindDraft(document.getElementById('f'));
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
    input = segHtml(`v${id}`, i.label);
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
    ${topbar(pre ? 'Pre-shipment check' : 'Delivery inspection', back, '', `${sh.shipment_number} · ${sh.job_label || ''}`)}${statusBanner()}
    ${sh.destination ? `<p class="muted small" style="margin-top:0">${esc(sh.destination)}</p>` : ''}
    <form id="f" novalidate>
      ${groups.map((g) => `<div class="card"><div class="row"><h2 class="grow" style="margin:0">${esc(g.type_name)}</h2></div>
        ${g.items.map(deliveryItemHtml).join('')}</div>`).join('')}
      <div class="card"><label for="n" style="margin-top:0">Notes</label><textarea id="n" maxlength="5000"></textarea>
        <label>${pre ? 'Photos of the load' : 'Photos of the delivery'}</label><div id="ph"></div></div>
      <p id="err" class="err" role="alert"></p>
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
  bindDraft(form);
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
      if (problem) { err.textContent = problem; box.scrollIntoView({ block: 'center' }); navigator.vibrate?.(40); return; }
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
    await clearDraft();
    await sleep(150);
    toast(`${failed ? `${pre ? 'Pre-check' : 'Inspection'} saved with problems. The office will be notified.` : (pre ? 'Pre-check saved. The load will be marked as shipped.' : 'Delivery saved. The office will verify it.')}${state.online ? '' : ' It will send when you’re online.'}`);
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
    ${topbar(sh.shipment_number, '#/shipping', '', sh.job_label || '')}${statusBanner()}
    <div class="card">
      <div class="row"><div class="grow"><strong>${esc(sh.job_label || 'No job')}</strong><div class="muted">${esc(sh.loads || '')}</div></div>${shipChip(sh.status)}</div>
      <p class="small" style="margin:10px 0 0">Ship date: <strong>${sh.ship_date ? esc(prettyDate(sh.ship_date)) : 'not scheduled'}</strong></p>
      ${sh.destination ? `<p class="small" style="margin:6px 0 0">Destination: ${esc(sh.destination)}</p>` : ''}
      ${stopHtml('Pickup', sh.pickup_name, sh.pickup_address)}
      ${stopHtml('Drop off', sh.dropoff_name, sh.dropoff_address)}
      ${sh.pickup_name || sh.dropoff_name ? `<button class="btn small" style="margin-top:12px" data-go="#/trip/${enc}">${icon('truck')} Addresses &amp; directions</button>` : ''}
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
    if (!(await confirmSheet(`Mark ${sh.shipment_number} as shipped?`, 'It will then show up under Deliveries.', 'Mark shipped'))) return;
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
      <p id="err" class="err" role="alert"></p>
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
  await clearDraft();
  await sleep(150);
  toast(state.online ? message : `${message} It will send when you’re online.`);
  go(`#/asset/${encodeURIComponent(asset.asset_number)}`);
}

// ----- scanning

function viewScan() {
  $app.innerHTML = `
    ${topbar('Scan QR code', '#/')}
    <div class="scan-wrap"><video id="v" playsinline muted></video><div class="frame"><i></i><i></i><i></i><i></i></div>
      <button class="icon-btn torch" id="torch" type="button" aria-label="Toggle flashlight" hidden>${icon('bolt')}</button></div>
    <p id="msg" class="muted small" style="text-align:center">Point the camera at the asset’s QR label.</p>
    <form id="manual" class="card"><label for="num" style="margin-top:0">Or type the asset number</label>
      <div class="row"><input id="num" autocapitalize="characters" autocomplete="off" placeholder="FA-0012"><button class="btn small primary" type="submit">Open</button></div></form>`;

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
    const track = stream.getVideoTracks()[0];
    if (track?.getCapabilities?.().torch) {
      const tb = document.getElementById('torch'); let on = false; tb.hidden = false;
      tb.addEventListener('click', async () => {
        on = !on;
        try { await track.applyConstraints({ advanced: [{ torch: on }] }); tb.classList.toggle('on', on); } catch { on = !on; }
      });
    }

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
      if (value !== lastMiss) { lastMiss = value; navigator.vibrate?.([30, 40, 30]); msg.textContent = `“${value.slice(0, 40)}” isn’t an active asset here.`; }
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

const KIND = { inspection: 'Inspection', meter: 'Meter reading', defect: 'Problem report', shipment_inspection: 'Delivery inspection', shipment_precheck: 'Pre-shipment check', shipment_create: 'New shipment', shipment_reschedule: 'Reschedule', shipment_ship: 'Marked shipped', dev_report: 'Dev report' };

function viewMe() {
  const q = state.outbox;
  const pending = pendingCount();
  const roles = [canInspect() && 'Inspector', canManage() && 'Shipping', canReportDev() && 'Admin'].filter(Boolean).join(' · ');
  $app.innerHTML = `
    ${topbar('Me')}${statusBanner()}
    <div class="card">
      <div class="asset-head"><span class="avatar">${icon('user')}</span>
        <div class="grow"><strong>${esc(state.user.display_name)}</strong><div class="small muted">${esc(state.user.username || '')}${roles ? ` · ${esc(roles)}` : ''}</div></div></div>
      <dl class="kv">
        <dt>Connection</dt><dd>${state.online ? 'Online' : 'Offline'}</dd>
        <dt>Data updated</dt><dd>${state.syncedAt ? esc(ago(state.syncedAt)) : 'never'}</dd>
        <dt>Version</dt><dd>${APP_VERSION}</dd></dl>
      <button class="btn" id="refresh" style="margin-top:14px">${icon('refresh')} Refresh now</button>
    </div>
    ${q.length ? `<div class="card"><h2>On this phone</h2><ul class="list">${q.map((r) => `
      <li><div class="item"><div class="grow"><strong>${esc(KIND[r.kind] || r.kind)}</strong> · ${esc(r.asset_number)}
        <div class="small muted">${new Date(r.submitted_at).toLocaleString()}${r.photos.length ? ` · ${r.photos.length} photo${r.photos.length > 1 ? 's' : ''}` : ''}</div>
        ${r.status === 'failed' ? `<div class="small" style="color:var(--bad)">${esc(r.error)}</div>` : ''}</div>
        ${r.status === 'failed' ? `<button class="btn small danger" data-discard="${esc(r.submission_uuid)}">Discard</button>` : '<span class="chip warn">Waiting</span>'}</div></li>`).join('')}</ul>
      ${pending ? '<button class="btn primary" id="retry" style="margin-top:8px">Send now</button>' : ''}</div>` : ''}
    <div class="card"><h2>Recently sent</h2><ul class="list">${state.recent.map((r) => `
      <li><div class="item" style="min-height:52px"><div class="grow"><strong>${esc(KIND[r.kind] || r.kind)}</strong> · ${esc(r.asset_number)}
        <div class="small muted">${new Date(r.submitted_at).toLocaleString()}</div></div>
        <span class="chip ${r.processed ? 'ok' : ''}">${r.processed ? 'Office has it' : 'Delivered'}</span></div></li>`).join('') || emptyState('check', 'Nothing sent yet.')}</ul></div>
    <button class="btn danger" id="logout">Sign out</button>${tabs('me')}`;
  document.getElementById('refresh').addEventListener('click', async (e) => {
    const b = e.currentTarget; b.disabled = true;
    await refreshNow(true);
    if (document.getElementById('refresh')) document.getElementById('refresh').disabled = false;
  });
  document.getElementById('logout').addEventListener('click', logout);
  document.getElementById('retry')?.addEventListener('click', async () => { await flush(); toast(pendingCount() ? 'Still waiting for a connection.' : 'Everything sent.'); });
  $app.querySelectorAll('[data-discard]').forEach((b) => b.addEventListener('click', async () => {
    if (!(await confirmSheet('Discard this submission?', 'It won’t be sent.', 'Discard', true))) return;
    await db.remove(b.dataset.discard); state.outbox = await db.outbox(); viewMe();
  }));
}

async function logout() {
  const n = pendingCount();
  if (!(await confirmSheet('Sign out?', n ? `${n} item(s) haven’t been sent yet. They stay on this phone and send after you sign in again.` : '', 'Sign out', true))) return;
  try { await api('/logout', { method: 'POST', body: {} }); } catch { /* offline: cookie expires on its own */ }
  state.user = null; state.needLogin = true;
  await db.del('snapshot');
  state.assets = []; state.checklists = []; state.shipments = []; state.jobs = []; state.types = []; state.recent = [];
  render();
}

// ---------- boot ------------------------------------------------------------------------

window.addEventListener('online', () => { state.online = true; updateBanner(); refreshNow(); });
window.addEventListener('offline', () => { state.online = false; updateBanner(); });
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && state.user && !state.needLogin) refreshNow(); });
// Keep lists fresh while the app sits open (shipments change in the office all day).
setInterval(() => { if (document.visibilityState === 'visible' && state.user && !state.needLogin && navigator.onLine) refreshNow(); }, 120000);

(async function boot() {
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
  await loadCache();
  if (state.user) {
    // Show the saved data straight away (works with no signal); update in the background.
    render();
    flush();
    refreshNow();
    return;
  }
  try { await refresh(); } catch (e) { if (!(e instanceof ApiError)) console.error(e); }
  render();
  if (state.user && !state.needLogin) flush();
})();


// ---------- development tracker (admins only) ---------------------------------------------
// A floating button on every screen opens a form that queues a `dev_report` like any other
// submission (works offline). ForgeDesk re-checks that the sender is an administrator.

const devLog = [];
(function captureConsole() {
  const fmt = (a) => { try { return typeof a === 'string' ? a : (a instanceof Error ? (a.stack || a.message) : JSON.stringify(a)); } catch { return String(a); } };
  const push = (lvl, text) => { devLog.push(`[${new Date().toLocaleTimeString()}] ${lvl}: ${text}`); if (devLog.length > 40) devLog.shift(); };
  ['error', 'warn'].forEach((lvl) => { const orig = console[lvl].bind(console); console[lvl] = (...a) => { push(lvl, a.map(fmt).join(' ')); orig(...a); }; });
  window.addEventListener('error', (e) => push('uncaught', `${e.message} (${e.filename || ''}:${e.lineno || ''})`));
  window.addEventListener('unhandledrejection', (e) => push('unhandledrejection', fmt(e.reason)));
})();

function syncDevFab() {
  let fab = document.getElementById('devFab');
  if (!canReportDev()) { fab?.remove(); return; }
  if (fab) return;
  fab = document.createElement('button');
  fab.id = 'devFab'; fab.type = 'button'; fab.textContent = '🐞'; fab.setAttribute('aria-label', 'Report a bug, request a feature or add a note');
  fab.addEventListener('click', openDevReport);
  document.body.appendChild(fab);
}

function openDevReport() {
  if (document.getElementById('devSheet')) return;
  const el = document.createElement('div');
  el.id = 'devSheet';
  el.innerHTML = `<form class="card">
    <h2 style="margin-top:0">Development tracker</h2>
    <label for="dvType">Type</label>
    <select id="dvType"><option value="bug">Bug</option><option value="feature">Feature request</option><option value="note">Note</option></select>
    <label for="dvPri">Priority</label>
    <select id="dvPri"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="critical">Critical</option></select>
    <label for="dvTitle">Title</label><input id="dvTitle" maxlength="255" required placeholder="Short summary">
    <label for="dvDesc">Description</label><textarea id="dvDesc" maxlength="5000" placeholder="What happened / what you want"></textarea>
    <label for="dvCons">Console output (optional)</label><textarea id="dvCons" maxlength="20000" style="font-family:monospace;font-size:12px">${esc(devLog.join('\n'))}</textarea>
    <p class="small muted">Page: ${esc(location.hash || '#/')}</p>
    <div style="display:flex;gap:8px"><button class="btn" type="button" id="dvCancel">Cancel</button><button class="btn primary" type="submit">Send</button></div>
  </form>`;
  document.body.appendChild(el);
  const close = () => el.remove();
  document.getElementById('dvCancel').addEventListener('click', close);
  el.addEventListener('click', (e) => { if (e.target === el) close(); });
  el.querySelector('form').addEventListener('submit', async (e) => {
    e.preventDefault();
    e.submitter && (e.submitter.disabled = true);
    const v = (id) => document.getElementById(id).value.trim();
    await enqueue('dev_report', 'DEV', {
      type: v('dvType'), priority: v('dvPri'), title: v('dvTitle'),
      ...(v('dvDesc') && { description: v('dvDesc') }), ...(v('dvCons') && { console_output: v('dvCons') }),
      page: `field-app ${location.hash || '#/'}`,
    }, []);
    close();
    toast(state.online ? 'Sent to the development tracker.' : 'Saved. It will send when you’re online.');
  });
}
