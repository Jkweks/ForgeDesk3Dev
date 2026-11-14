'use strict';
/**
 * F: share watcher. Watches the configured folders (read-only: it never writes to the share) and
 * posts one event per settled file to ForgeDesk's POST /api/v1/ingest/fdrive. ForgeDesk decides what
 * the file is and whether to import it; this service only reports. See docs/plans/pm-suite-integration.md 4a.
 *
 * Config (environment):
 *   WATCH_ROOTS    folders to watch, separated by | (e.g. F:\Engineering\Jobs In PROGRESS|F:\Engineering\Estimates)
 *   INGEST_URL     e.g. https://forgedesk.example.com/api/v1/ingest/fdrive
 *   INGEST_TOKEN   the FDRIVE_INGEST_TOKEN configured in ForgeDesk
 *   SETTLE_MS      wait until a file's size has been stable this long before reporting (default 4000)
 *   EXTENSIONS     optional allow-list, comma separated (default: xls,xlsx,xlsm,pdf)
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

function sha256File(file) {
  return new Promise((resolve, reject) => {
    const h = crypto.createHash('sha256');
    fs.createReadStream(file).on('data', d => h.update(d)).on('error', reject).on('end', () => resolve(h.digest('hex')));
  });
}

/** Resolves once the file's size has not changed for `settleMs` (big PDFs copy slowly). Resolves null if it vanished. */
async function waitUntilSettled(file, settleMs, { stat = fs.promises.stat, sleep = ms => new Promise(r => setTimeout(r, ms)), maxWaitMs = 10 * 60 * 1000 } = {}) {
  let last = null;
  const started = Date.now();
  for (;;) {
    let st;
    try { st = await stat(file); } catch (e) { return null; }
    if (last && st.size === last.size && st.mtimeMs === last.mtimeMs) return st;
    last = st;
    if (Date.now() - started > maxWaitMs) return st;
    await sleep(settleMs);
  }
}

/** POST with a few retries (network blips, ForgeDesk restarting). Never throws; returns {ok, status}. */
async function report(event, { url, token, fetchImpl = fetch, retries = 4, sleep = ms => new Promise(r => setTimeout(r, ms)) }) {
  let status = 0;
  for (let attempt = 0; attempt <= retries; attempt++) {
    try {
      const res = await fetchImpl(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Ingest-Token': token }, body: JSON.stringify(event) });
      status = res.status;
      if (res.ok) return { ok: true, status, body: await res.json().catch(() => ({})) };
      if (status >= 400 && status < 500 && status !== 429) return { ok: false, status }; // our mistake (bad token / payload): retrying won't help
    } catch (e) { status = 0; }
    await sleep(Math.min(30000, 1000 * 2 ** attempt));
  }
  return { ok: false, status };
}

function createWatcher(cfg, deps = {}) {
  const exts = new Set((cfg.extensions || ['xls', 'xlsx', 'xlsm', 'pdf']).map(e => e.toLowerCase()));
  const pending = new Map(); // path -> timer (debounce bursts of fs events for one file)
  const log = deps.log || console.log;
  const handlers = [];

  async function process(file, event) {
    const st = await waitUntilSettled(file, cfg.settleMs, deps);
    if (!st || !st.isFile()) return;
    const sha256 = await sha256File(file);
    const r = await report({ path: file, size: st.size, mtime: new Date(st.mtimeMs).toISOString(), sha256, event }, { url: cfg.url, token: cfg.token, ...deps });
    log(`${r.ok ? 'sent' : 'FAILED'} ${file} -> ${r.ok ? (r.body && r.body.action) : r.status}`);
  }

  function onChange(root, event, name) {
    if (!name) return;
    const file = path.join(root, name);
    if (!exts.has(path.extname(file).slice(1).toLowerCase())) return;
    clearTimeout(pending.get(file));
    pending.set(file, setTimeout(() => { pending.delete(file); process(file, event === 'rename' ? 'created' : 'changed').catch(e => log('error ' + file + ': ' + e.message)); }, 500));
  }

  return {
    process,
    onChange,
    start() {
      for (const root of cfg.roots) {
        const w = fs.watch(root, { recursive: true, persistent: true }, (event, name) => onChange(root, event, name));
        w.on('error', e => log(`watch error on ${root}: ${e.message}`));
        handlers.push(w);
        log('watching ' + root);
      }
    },
    stop() { handlers.forEach(w => w.close()); pending.forEach(t => clearTimeout(t)); },
  };
}

function configFromEnv(env = process.env) {
  const roots = (env.WATCH_ROOTS || '').split('|').map(s => s.trim()).filter(Boolean);
  if (!roots.length || !env.INGEST_URL || !env.INGEST_TOKEN) throw new Error('WATCH_ROOTS, INGEST_URL and INGEST_TOKEN are required');
  return { roots, url: env.INGEST_URL, token: env.INGEST_TOKEN, settleMs: parseInt(env.SETTLE_MS || '4000', 10), extensions: env.EXTENSIONS ? env.EXTENSIONS.split(',').map(s => s.trim()) : undefined };
}

if (require.main === module) {
  try { createWatcher(configFromEnv()).start(); } catch (e) { console.error(e.message); process.exit(1); }
}

module.exports = { sha256File, waitUntilSettled, report, createWatcher, configFromEnv };
