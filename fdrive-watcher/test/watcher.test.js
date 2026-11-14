'use strict';
const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { sha256File, waitUntilSettled, report, createWatcher, configFromEnv } = require('../watcher');

const noSleep = () => Promise.resolve();

test('sha256File hashes the file contents', async () => {
  const f = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'fw-')), 'a.pdf');
  fs.writeFileSync(f, 'hello');
  assert.strictEqual(await sha256File(f), crypto.createHash('sha256').update('hello').digest('hex'));
});

test('waitUntilSettled waits for a growing file and returns null for a vanished one', async () => {
  const sizes = [10, 20, 30, 30, 30];
  let i = 0;
  const stat = async () => ({ size: sizes[Math.min(i++, sizes.length - 1)], mtimeMs: 1 });
  const st = await waitUntilSettled('x', 1, { stat, sleep: noSleep });
  assert.strictEqual(st.size, 30);
  assert.ok(i >= 4, 'kept polling while the size changed');

  assert.strictEqual(await waitUntilSettled('x', 1, { stat: async () => { throw new Error('ENOENT'); }, sleep: noSleep }), null);
});

test('report retries server errors, gives up on client errors, and sends the token', async () => {
  const calls = [];
  const flaky = async (url, opts) => { calls.push(opts); return calls.length < 3 ? { ok: false, status: 503 } : { ok: true, status: 200, json: async () => ({ action: 'imported' }) }; };
  const r = await report({ path: 'a' }, { url: 'u', token: 't0k', fetchImpl: flaky, sleep: noSleep });
  assert.deepStrictEqual([r.ok, r.body.action, calls.length], [true, 'imported', 3]);
  assert.strictEqual(calls[0].headers['X-Ingest-Token'], 't0k');

  let n = 0;
  const unauthorized = async () => { n++; return { ok: false, status: 401 }; };
  const bad = await report({}, { url: 'u', token: 'x', fetchImpl: unauthorized, sleep: noSleep });
  assert.deepStrictEqual([bad.ok, bad.status, n], [false, 401, 1]);

  const down = await report({}, { url: 'u', token: 'x', fetchImpl: async () => { throw new Error('offline'); }, sleep: noSleep, retries: 2 });
  assert.deepStrictEqual([down.ok, down.status], [false, 0]);
});

test('process reports a settled file with its hash, size and mtime', async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'fw-'));
  const f = path.join(dir, 'BidEstimate_X.xlsx');
  fs.writeFileSync(f, 'workbook');
  const sent = [];
  const w = createWatcher({ roots: [dir], url: 'u', token: 't', settleMs: 1 }, {
    sleep: noSleep, log: () => {},
    fetchImpl: async (url, opts) => { sent.push(JSON.parse(opts.body)); return { ok: true, status: 200, json: async () => ({ action: 'imported' }) }; },
  });
  await w.process(f, 'created');
  assert.strictEqual(sent.length, 1);
  assert.strictEqual(sent[0].path, f);
  assert.strictEqual(sent[0].size, 8);
  assert.strictEqual(sent[0].sha256, crypto.createHash('sha256').update('workbook').digest('hex'));
  assert.strictEqual(sent[0].event, 'created');
  assert.ok(!Number.isNaN(Date.parse(sent[0].mtime)));
});

test('configFromEnv requires the three settings and splits roots', () => {
  assert.throws(() => configFromEnv({}), /required/);
  const c = configFromEnv({ WATCH_ROOTS: 'F:\\A|F:\\B', INGEST_URL: 'https://x/api/v1/ingest/fdrive', INGEST_TOKEN: 't', SETTLE_MS: '100' });
  assert.deepStrictEqual([c.roots, c.settleMs], [['F:\\A', 'F:\\B'], 100]);
});
