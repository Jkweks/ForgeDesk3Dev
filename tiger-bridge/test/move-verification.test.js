// /move: query the stop's position first (P), skip the move when it is already
// there, otherwise MG checked against the amp's MGS echo, then confirm with P.
// A fake port plays the amp: amp(cmd, n) returns the lines it sends back for
// each write (n counts writes of that command kind), so echo corruption, a stop
// that never arrives, an unreadable P etc. can be scripted without hardware.

const { test } = require('node:test');
const assert = require('node:assert/strict');
const { EventEmitter } = require('node:events');

const SERVER_PATH = require.resolve('../server.js');

async function withFakeAmp(amp, fn) {
  process.env.BRIDGE_TOKEN = 'test-token';
  process.env.ALLOWED_CLIENT_IPS = '';
  process.env.NODE_ENV = 'test';
  process.env.POSITION_TIMEOUT_MS = '40';
  delete require.cache[SERVER_PATH];
  const mod = require(SERVER_PATH);

  const parser = new EventEmitter();
  const writes = [];
  const counts = {};
  const port = {
    write(cmd, cb) {
      writes.push(cmd);
      const kind = cmd.startsWith('MG') ? 'MG' : cmd.trim();
      counts[kind] = (counts[kind] ?? 0) + 1;
      const lines = amp(kind, counts[kind], cmd) ?? [];
      cb();
      setImmediate(() => lines.forEach((l) => parser.emit('data', l)));
    },
  };
  mod._setSerial(port, parser);

  const server = await new Promise((resolve) => {
    const s = mod.app.listen(0, () => resolve(s));
  });
  try {
    await fn(`http://127.0.0.1:${server.address().port}`, writes);
  } finally {
    server.close();
  }
}

const headers = { Authorization: 'Bearer test-token', 'Content-Type': 'application/json' };
const move = (baseUrl, inches) =>
  fetch(`${baseUrl}/move`, { method: 'POST', headers, body: JSON.stringify({ inches }) });

// Amp whose stop starts at `start`, honours MG (echoing what it parsed via
// `echo(n, wanted)`), and reports `after(n, wanted)` from P once moved.
function stopAt(start, { echo = (n, w) => w, after = (n, w) => w, mgf = true } = {}) {
  let pos = start;
  return (kind, n, cmd) => {
    if (kind === 'P') return ['p', String(pos)];
    if (kind === 'MG') {
      const wanted = parseFloat(cmd.slice(2));
      const heard = echo(n, wanted);
      pos = after(n, wanted);
      return [`MGS ${Number(heard).toFixed(3)}`, ...(mgf ? ['MGF'] : [])];
    }
    return [];
  };
}

test('moves when the stop is elsewhere, then confirms with P', async () => {
  await withFakeAmp(stopAt(10), async (baseUrl, writes) => {
    const res = await move(baseUrl, 29.5);
    assert.equal(res.status, 200);
    const body = await res.json();
    assert.equal(body.skipped, false);
    assert.equal(body.ampTarget, 29.5);
    assert.equal(body.position, 29.5);
    assert.equal(body.lastPosition, 29.5);
    assert.deepEqual(writes, ['P\r', 'MG29.500\r', 'P\r']);
  });
});

test('already at the target: no MG is sent', async () => {
  await withFakeAmp(stopAt(29.5), async (baseUrl, writes) => {
    const res = await move(baseUrl, 29.5);
    assert.equal(res.status, 200);
    const body = await res.json();
    assert.equal(body.skipped, true);
    assert.equal(body.position, 29.5);
    assert.equal(body.lastPosition, 29.5);
    assert.deepEqual(writes, ['P\r']);
  });
});

test('within tolerance of the target counts as already there', async () => {
  await withFakeAmp(stopAt(29.498), async (baseUrl, writes) => {
    const res = await move(baseUrl, 29.5);
    assert.equal((await res.json()).skipped, true);
    assert.deepEqual(writes, ['P\r']);
  });
});

test('a stop that drifted off the last length is moved again', async () => {
  // Same dimension as the previous cut, but the stop reads 29.0 now.
  await withFakeAmp(stopAt(29.0), async (baseUrl, writes) => {
    const res = await move(baseUrl, 29.5);
    assert.equal(res.status, 200);
    const body = await res.json();
    assert.equal(body.skipped, false);
    assert.equal(body.position, 29.5);
    assert.deepEqual(writes, ['P\r', 'MG29.500\r', 'P\r']);
  });
});

test('a dropped digit is caught by the echo and the retry recovers', async () => {
  await withFakeAmp(stopAt(10, { echo: (n, w) => (n === 1 ? 29.0 : w) }), async (baseUrl, writes) => {
    const res = await move(baseUrl, 29.5);
    assert.equal(res.status, 200);
    assert.equal((await res.json()).ampTarget, 29.5);
    assert.equal(writes.filter((w) => w.startsWith('MG')).length, 2);
  });
});

test('a repeated bad echo fails the move with a do-not-cut error', async () => {
  await withFakeAmp(stopAt(10, { echo: (n) => (n === 1 ? 29.0 : 29.2) }), async (baseUrl) => {
    const res = await move(baseUrl, 29.5);
    assert.equal(res.status, 502);
    assert.match((await res.json()).error, /do not cut/);
  });
});

test('a move with no target echo is not accepted as unverified', async () => {
  const amp = (kind) => (kind === 'P' ? ['p', '10'] : kind === 'MG' ? ['MGF'] : []);
  await withFakeAmp(amp, async (baseUrl) => {
    const res = await move(baseUrl, 29.5);
    assert.equal(res.status, 502);
    assert.match((await res.json()).error, /did not confirm/);
  });
});

test('the echo is right but the stop did not arrive: fail after the move', async () => {
  await withFakeAmp(stopAt(10, { after: () => 28.75 }), async (baseUrl) => {
    const res = await move(baseUrl, 29.5);
    assert.equal(res.status, 502);
    const { error } = await res.json();
    assert.match(error, /reads 28.75/);
    assert.match(error, /do not cut/);
  });
});

test('if P cannot be read, the move falls back to the echo check alone', async () => {
  const amp = (kind, n, cmd) => (kind === 'MG' ? [`MGS ${parseFloat(cmd.slice(2)).toFixed(3)}`, 'MGF'] : []);
  await withFakeAmp(amp, async (baseUrl, writes) => {
    const res = await move(baseUrl, 29.5);
    assert.equal(res.status, 200);
    const body = await res.json();
    assert.equal(body.position, null);
    assert.equal(body.lastPosition, 29.5);
    assert.equal(writes.filter((w) => w.startsWith('MG')).length, 1);
  });
});

test('an amp error is not retried', async () => {
  const amp = (kind) => (kind === 'P' ? ['p', '10'] : kind === 'MG' ? ['X EC=6 ERR_MOVEMAX'] : []);
  await withFakeAmp(amp, async (baseUrl, writes) => {
    const res = await move(baseUrl, 29.5);
    assert.equal(res.status, 502);
    assert.equal(writes.filter((w) => w.startsWith('MG')).length, 1);
  });
});
