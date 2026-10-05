// Stub coverage for tiger-bridge's *network* connection surface only:
// bearer-token auth, the IP allowlist, and the boot-time refusal to start
// without a token. The serial (COM port) side — connectSerial/sendMove — is
// intentionally out of scope here; it's local hardware I/O, not network.
//
// server.js guards `connectSerial()` and `app.listen()` behind
// `require.main === module`, so requiring it below never opens a real COM
// port or binds the production port — each test binds its own ephemeral
// port via `app.listen(0)`.

const { test } = require('node:test');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');

const SERVER_PATH = require.resolve('../server.js');

function loadApp({ token = 'test-token', allowedIps = '' } = {}) {
  process.env.BRIDGE_TOKEN = token;
  process.env.ALLOWED_CLIENT_IPS = allowedIps;
  delete require.cache[SERVER_PATH];
  return require(SERVER_PATH).app;
}

function listen(app) {
  return new Promise((resolve) => {
    const server = app.listen(0, () => resolve(server));
  });
}

async function withServer(opts, fn) {
  const app = loadApp(opts);
  const server = await listen(app);
  const { port } = server.address();
  try {
    await fn(`http://127.0.0.1:${port}`);
  } finally {
    server.close();
  }
}

test('rejects a request with no Authorization header', async () => {
  await withServer({}, async (baseUrl) => {
    const res = await fetch(`${baseUrl}/status`);
    assert.equal(res.status, 401);
  });
});

test('rejects a request with the wrong bearer token', async () => {
  await withServer({ token: 'correct-token' }, async (baseUrl) => {
    const res = await fetch(`${baseUrl}/status`, {
      headers: { Authorization: 'Bearer wrong-token' },
    });
    assert.equal(res.status, 401);
  });
});

test('accepts a request with the correct bearer token', async () => {
  await withServer({ token: 'correct-token' }, async (baseUrl) => {
    const res = await fetch(`${baseUrl}/status`, {
      headers: { Authorization: 'Bearer correct-token' },
    });
    assert.equal(res.status, 200);
  });
});

test('IP allowlist rejects a valid token from a disallowed address', async () => {
  // 127.0.0.1 (the test client) is deliberately excluded.
  await withServer({ token: 'correct-token', allowedIps: '10.0.0.1' }, async (baseUrl) => {
    const res = await fetch(`${baseUrl}/status`, {
      headers: { Authorization: 'Bearer correct-token' },
    });
    assert.equal(res.status, 403);
  });
});

test('IP allowlist admits a valid token from an allowed CIDR', async () => {
  await withServer({ token: 'correct-token', allowedIps: '127.0.0.0/8' }, async (baseUrl) => {
    const res = await fetch(`${baseUrl}/status`, {
      headers: { Authorization: 'Bearer correct-token' },
    });
    assert.equal(res.status, 200);
  });
});

test('IP allowlist is checked before the token, so a disallowed IP gets 403 even with no token', async () => {
  await withServer({ token: 'correct-token', allowedIps: '10.0.0.1' }, async (baseUrl) => {
    const res = await fetch(`${baseUrl}/status`);
    assert.equal(res.status, 403);
  });
});

test('empty ALLOWED_CLIENT_IPS skips the IP layer and falls through to the token check', async () => {
  await withServer({ token: 'correct-token', allowedIps: '' }, async (baseUrl) => {
    const res = await fetch(`${baseUrl}/status`);
    assert.equal(res.status, 401); // token layer, not the IP layer, rejects it
  });
});

test('refuses to start at all when BRIDGE_TOKEN is unset', () => {
  const result = spawnSync(process.execPath, [SERVER_PATH], {
    env: { ...process.env, BRIDGE_TOKEN: '', ALLOWED_CLIENT_IPS: '' },
    encoding: 'utf8',
  });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /BRIDGE_TOKEN is not set/);
});
