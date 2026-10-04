// /move range checking and the /status version/limits fields. No serial port
// is involved: _setLimits() stands in for the amp's D10/D11 replies, and an
// out-of-range request is rejected before anything would be sent to the amp.

const { test } = require('node:test');
const assert = require('node:assert/strict');

const SERVER_PATH = require.resolve('../server.js');

async function withServer(limits, fn) {
  process.env.BRIDGE_TOKEN = 'test-token';
  process.env.ALLOWED_CLIENT_IPS = '';
  delete require.cache[SERVER_PATH];
  const mod = require(SERVER_PATH);
  if (limits) mod._setLimits(...limits);
  const server = await new Promise((resolve) => {
    const s = mod.app.listen(0, () => resolve(s));
  });
  try {
    await fn(`http://127.0.0.1:${server.address().port}`, mod);
  } finally {
    server.close();
  }
}

const headers = { Authorization: 'Bearer test-token', 'Content-Type': 'application/json' };

function move(baseUrl, inches) {
  return fetch(`${baseUrl}/move`, { method: 'POST', headers, body: JSON.stringify({ inches }) });
}

test('rejects a move below the minimum limit', async () => {
  await withServer([6, 78], async (baseUrl) => {
    const res = await move(baseUrl, 5.5);
    assert.equal(res.status, 400);
    const body = await res.json();
    assert.equal(body.outOfRange, true);
    assert.equal(body.limitMin, 6);
    assert.match(body.error, /below/);
  });
});

test('rejects a move beyond the maximum limit', async () => {
  await withServer([6, 78], async (baseUrl) => {
    const res = await move(baseUrl, 78.5);
    assert.equal(res.status, 400);
    assert.match((await res.json()).error, /beyond/);
  });
});

test('in-range moves get past the range check', async () => {
  // no serial port in tests, so a valid move fails later with 502 — what
  // matters is that it is not the 400 range rejection.
  await withServer([6, 78], async (baseUrl) => {
    const res = await move(baseUrl, 48);
    assert.equal(res.status, 502);
  });
});

test('with limits unknown the range check is skipped', async () => {
  await withServer(null, async (baseUrl) => {
    const res = await move(baseUrl, 500);
    assert.equal(res.status, 502);
  });
});

test('/status reports version and limits', async () => {
  await withServer([6, 78], async (baseUrl, mod) => {
    const body = await (await fetch(`${baseUrl}/status`, { headers })).json();
    assert.equal(body.version, mod.VERSION);
    assert.equal(body.limitMin, 6);
    assert.equal(body.limitMax, 78);
  });
});
