'use strict';
/*
 * Integration tests against a real Postgres built from db/init (so the grants are exercised
 * too). Skipped unless TEST_ADMIN_PASSWORD is set; the PG* env must point at the field_app role.
 *   docker compose exec -e TEST_ADMIN_PASSWORD=... app node --test test/
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const { Pool } = require('pg');
// A genuine Laravel hash (PHP password_hash, $2y$) of 'correct horse', as ForgeDesk publishes it.
const LARAVEL_HASH = '$2y$12$GO/7rGftpqLjj798M83kpuUPPbz8Atawx2qnfCSbJSo4lwFqjKdCO';

const ADMIN_PW = process.env.TEST_ADMIN_PASSWORD;
const skip = ADMIN_PW ? false : 'TEST_ADMIN_PASSWORD not set';

const JPEG = Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(30)]).toString('base64');
let admin, server, base;

async function call(path, { method = 'GET', body, cookie, headers = {} } = {}) {
  const res = await fetch(base + path, {
    method,
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fieldapp', ...(cookie && { Cookie: cookie }), ...headers },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  return { res, json: await res.json().catch(() => ({})), setCookie: res.headers.get('set-cookie') };
}
const uuid = () => require('crypto').randomUUID();

test.before(async () => {
  if (skip) return;
  admin = new Pool({ host: process.env.PGHOST, user: 'fieldadmin', password: ADMIN_PW, database: process.env.PGDATABASE });
  await admin.query("TRUNCATE fa_sessions, fa_users, fa_submissions, fa_photos, fa_assets, fa_checklists RESTART IDENTITY CASCADE");
  await admin.query("INSERT INTO fa_users (forgedesk_user_id, login, display_name, password_hash, is_active) VALUES (7, 'terry@example.com', 'Terry Tester', $1, true)", [LARAVEL_HASH]);
  await admin.query("INSERT INTO fa_users (forgedesk_user_id, login, display_name, password_hash, is_active) VALUES (8, 'temp@example.com', 'Temp Password', '', false)");
  await admin.query("INSERT INTO fa_assets (asset_number, name, type_name, meter_type, status, current_meter, is_active) VALUES ('FA-1','Truck 1','Truck','odometer_miles','active',1000,true),('FA-9','Old truck','Truck','odometer_miles','active',5,false)");
  await admin.query("INSERT INTO fa_checklists (type_name, template_name, items) VALUES ('Truck','Truck inspection','[{\"label\":\"Brakes\"}]')");
  process.env.COOKIE_SECURE = 'false';
  const { createApp } = require('../server/app');
  server = createApp().listen(0);
  base = `http://127.0.0.1:${server.address().port}`;
});

test.after(async () => { server?.close(); await admin?.end(); await require('../server/db').pool.end(); });

async function login() {
  const r = await call('/api/login', { method: 'POST', body: { username: 'Terry@Example.com', password: 'correct horse' } });
  assert.equal(r.res.status, 200);
  assert.match(r.setCookie, /HttpOnly/);
  assert.match(r.setCookie, /SameSite=Strict/);
  return r.setCookie.split(';')[0];
}

test('API requires sign-in and a CSRF-safe request', { skip }, async () => {
  assert.equal((await call('/api/bootstrap')).res.status, 401);
  const r = await fetch(`${base}/api/login`, { method: 'POST', headers: { 'Content-Type': 'text/plain' }, body: '{}' });
  assert.equal(r.status, 400);
  const r2 = await fetch(`${base}/api/login`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
  assert.equal(r2.status, 400, 'missing X-Requested-With');
});

test('login: wrong password rejected, right one issues a session; bootstrap lists only active assets', { skip }, async () => {
  assert.equal((await call('/api/login', { method: 'POST', body: { username: 'terry@example.com', password: 'nope' } })).res.status, 401);
  const cookie = await login();
  const { json } = await call('/api/bootstrap', { cookie });
  assert.deepEqual(json.assets.map((a) => a.asset_number), ['FA-1']);
  assert.equal(json.checklists[0].items[0].label, 'Brakes');
  assert.equal(json.user.display_name, 'Terry Tester');
});

test('submissions: accepted once, replay is a harmless duplicate, attributed server-side', { skip }, async () => {
  const cookie = await login();
  const id = uuid();
  const body = { submission_uuid: id, kind: 'meter', asset_number: 'FA-1', submitted_at: new Date().toISOString(), submitted_by: 'Mallory', payload: { reading: 1010 } };
  assert.equal((await call('/api/submissions', { method: 'POST', body, cookie })).res.status, 201);
  const again = await call('/api/submissions', { method: 'POST', body, cookie });
  assert.equal(again.json.status, 'duplicate');
  const { rows } = await admin.query('SELECT * FROM fa_submissions WHERE submission_uuid = $1', [id]);
  assert.equal(rows.length, 1);
  assert.equal(rows[0].submitted_by, 'Terry Tester', 'client-supplied identity is ignored');
  assert.deepEqual(JSON.parse(rows[0].payload), { reading: 1010 });
  assert.equal(rows[0].ingested_at, null);
});

test('submissions: photos stored, bad asset / retired asset / spoofed image refused', { skip }, async () => {
  const cookie = await login();
  const id = uuid();
  const ok = await call('/api/submissions', { method: 'POST', cookie, body: {
    submission_uuid: id, kind: 'defect', asset_number: 'FA-1', payload: { title: 'Leak', severity: 'high' },
    photos: [{ filename: 'leak.jpg', data_b64: `data:image/jpeg;base64,${JPEG}` }] } });
  assert.equal(ok.res.status, 201);
  assert.equal((await admin.query('SELECT count(*)::int n FROM fa_photos WHERE submission_uuid = $1', [id])).rows[0].n, 1);

  const bad = (over) => call('/api/submissions', { method: 'POST', cookie, body: { submission_uuid: uuid(), kind: 'meter', asset_number: 'FA-1', payload: { reading: 1 }, ...over } });
  assert.equal((await bad({ asset_number: 'NOPE' })).res.status, 422);
  assert.equal((await bad({ asset_number: 'FA-9' })).res.status, 422, 'retired assets are not accepted');
  assert.equal((await bad({ photos: [{ filename: 'x.jpg', data_b64: Buffer.from('MZ not an image').toString('base64') }] })).res.status, 422);
});

test('mine: shows the user\'s sends and whether the office has them', { skip }, async () => {
  const cookie = await login();
  await admin.query("UPDATE fa_submissions SET ingested_at = now() WHERE id = (SELECT min(id) FROM fa_submissions)");
  const { json } = await call('/api/submissions/mine', { cookie });
  assert.ok(json.items.length >= 2);
  assert.ok(json.items.some((i) => i.processed) && json.items.some((i) => !i.processed));
});

test('logout invalidates the session; deactivated users are locked out immediately', { skip }, async () => {
  const cookie = await login();
  await call('/api/logout', { method: 'POST', cookie, body: {} });
  assert.equal((await call('/api/bootstrap', { cookie })).res.status, 401);

  const c2 = await login();
  await admin.query("UPDATE fa_users SET is_active = false WHERE forgedesk_user_id = 7");
  assert.equal((await call('/api/bootstrap', { cookie: c2 })).res.status, 401);
  assert.equal((await call('/api/login', { method: 'POST', body: { username: 'Terry@Example.com', password: 'correct horse' } })).res.status, 401);
  await admin.query("UPDATE fa_users SET is_active = true WHERE forgedesk_user_id = 7");
});

test('ForgeDesk accounts without a usable credential cannot sign in', { skip }, async () => {
  const signIn = (username, password) => call('/api/login', { method: 'POST', body: { username, password } });
  assert.equal((await signIn('temp@example.com', '')).res.status, 401, 'empty hash (temp password / no access)');
  assert.equal((await signIn('temp@example.com', 'anything')).res.status, 401);
  assert.equal((await signIn('nobody@example.com', 'correct horse')).res.status, 401);

  // Access revoked in ForgeDesk: the next sync blanks the hash and live sessions stop working.
  const cookie = await login();
  assert.equal((await call('/api/bootstrap', { cookie })).res.status, 200);
  await admin.query("UPDATE fa_users SET password_hash = '' WHERE forgedesk_user_id = 7");
  assert.equal((await call('/api/bootstrap', { cookie })).res.status, 401);
  assert.equal((await signIn('terry@example.com', 'correct horse')).res.status, 401);
  await admin.query('UPDATE fa_users SET password_hash = $1 WHERE forgedesk_user_id = 7', [LARAVEL_HASH]);
  assert.equal((await signIn('terry@example.com', 'correct horse')).res.status, 200);
});

test('database grants: the app role cannot rewrite the inbox; the sync role can drain it', { skip }, async () => {
  const app = require('../server/db').pool;
  await assert.rejects(app.query("UPDATE fa_submissions SET kind = 'x'"), /permission denied/);
  await assert.rejects(app.query('DELETE FROM fa_submissions'), /permission denied/);
  await assert.rejects(app.query("INSERT INTO fa_assets (asset_number,name,type_name,status) VALUES ('Z','z','z','active')"), /permission denied/);
  await assert.rejects(app.query('SELECT * FROM fa_photos'), /permission denied/);
  await assert.rejects(app.query("UPDATE fa_users SET password_hash = 'x'"), /permission denied/, 'the app cannot alter logins');
  await assert.rejects(app.query("INSERT INTO fa_users (forgedesk_user_id, login, display_name) VALUES (99,'x@y','x')"), /permission denied/);

  const sync = new Pool({ host: process.env.PGHOST, user: 'forgedesk_sync', password: process.env.TEST_SYNC_PASSWORD, database: process.env.PGDATABASE });
  try {
    await sync.query("INSERT INTO fa_assets (asset_number,name,type_name,status,is_active) VALUES ('FA-2','Van','Van','active',true) ON CONFLICT (asset_number) DO UPDATE SET name = EXCLUDED.name");
    await sync.query('UPDATE fa_submissions SET ingested_at = now() WHERE ingested_at IS NULL');
    await sync.query('DELETE FROM fa_photos');
    await assert.rejects(sync.query("UPDATE fa_submissions SET payload = '{}'"), /permission denied/);
    await sync.query("INSERT INTO fa_users (forgedesk_user_id, login, display_name, password_hash, is_active) VALUES (9,'new@example.com','New Crew','',false) ON CONFLICT (forgedesk_user_id) DO UPDATE SET login = EXCLUDED.login");
    await assert.rejects(sync.query('SELECT * FROM fa_sessions'), /permission denied/, 'ForgeDesk never sees sessions');
  } finally { await sync.end(); }
});
