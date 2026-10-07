require('dotenv').config();

const crypto = require('crypto');
const express = require('express');
const net = require('net');
const { SerialPort } = require('serialport');
const { ReadlineParser } = require('@serialport/parser-readline');

// Single source of truth is package.json — bump it there on every bridge change
// and raise TigerBridgeClient::MIN_BRIDGE_VERSION in ForgeDesk when Laravel
// starts depending on new behaviour. Reported via GET /status so the app can
// tell when the Windows box is still running an old copy.
const VERSION = require('./package.json').version;

function envNumber(raw) {
  if (raw === undefined || raw === '') return null;
  const n = Number(raw);
  return Number.isFinite(n) ? n : null;
}

const app = express();
app.use(express.json());

const BRIDGE_PORT = process.env.BRIDGE_PORT || 9111;
const SERIAL_PATH = process.env.SERIAL_PORT || 'COM3';
const BAUD_RATE = parseInt(process.env.BAUD_RATE || '57600', 10);
const PRINTER_HOST = process.env.PRINTER_HOST || '192.168.1.50';
const PRINTER_PORT = parseInt(process.env.PRINTER_PORT || '9100', 10);
const BRIDGE_TOKEN = process.env.BRIDGE_TOKEN || '';
const MOCK_SERIAL = process.env.MOCK_SERIAL === 'true';
// Only used with MOCK_SERIAL — a real amp reports its own limits (D10/D11).
const MOCK_LIMIT_MIN = envNumber(process.env.MOCK_LIMIT_MIN);
const MOCK_LIMIT_MAX = envNumber(process.env.MOCK_LIMIT_MAX);
const ALLOWED_CLIENT_IPS = (process.env.ALLOWED_CLIENT_IPS || '')
  .split(',')
  .map((ip) => ip.trim())
  .filter(Boolean);

// --- auth --------------------------------------------------------------
//
// This service drives real hardware (a saw's stop position, a printer) for
// any client that can reach it over the LAN. BRIDGE_TOKEN is a long-lived
// shared secret: only the ForgeDesk instance configured with the matching
// TIGER_BRIDGE_TOKEN is allowed to issue commands. Without a token
// configured, this refuses to start rather than silently running open —
// there is no hardware-safe "no auth" mode.
if (!BRIDGE_TOKEN) {
  console.error(
    '[tiger-bridge] BRIDGE_TOKEN is not set. Refusing to start: without it, ' +
    'anyone on the network could move the saw or trigger the printer. ' +
    'Set BRIDGE_TOKEN in .env (see .env.example) to a long random value ' +
    'and configure the same value as TIGER_BRIDGE_TOKEN in ForgeDesk.'
  );
  process.exit(1);
}

function timingSafeEqual(a, b) {
  const bufA = Buffer.from(a);
  const bufB = Buffer.from(b);
  if (bufA.length !== bufB.length) {
    // still run a compare of matching length so this doesn't short-circuit
    // on length and leak timing info about the token's length.
    crypto.timingSafeEqual(bufA, bufA);
    return false;
  }
  return crypto.timingSafeEqual(bufA, bufB);
}

function requireToken(req, res, next) {
  const header = req.get('authorization') || '';
  const presented = header.startsWith('Bearer ') ? header.slice(7) : '';

  if (!presented || !timingSafeEqual(presented, BRIDGE_TOKEN)) {
    return res.status(401).json({ ok: false, error: 'missing or invalid bridge token' });
  }

  next();
}

// --- IP allowlist --------------------------------------------------------
//
// Second, independent layer on top of the token: even a request carrying a
// valid BRIDGE_TOKEN is rejected unless it comes from a source address in
// ALLOWED_CLIENT_IPS. In this deployment the only thing that ever calls the
// bridge over the network is the ForgeDesk app server (TigerBridgeClient),
// so this should normally be set to that single host's IP (or its /32,
// or a narrow CIDR if it's a small trusted subnet). Left unset, this layer
// is skipped and the token alone gates access — set ALLOWED_CLIENT_IPS as
// soon as the app server's IP is known.
//
// Only exact IPs and simple IPv4 CIDR ranges are supported — no DNS names
// (a spoofed/poisoned lookup would defeat the point of an IP check).

function normalizeIp(ip) {
  return ip && ip.startsWith('::ffff:') ? ip.slice(7) : ip;
}

function ipv4ToInt(ip) {
  const parts = ip.split('.').map(Number);
  if (parts.length !== 4 || parts.some((p) => !Number.isInteger(p) || p < 0 || p > 255)) {
    return null;
  }
  return ((parts[0] << 24) | (parts[1] << 16) | (parts[2] << 8) | parts[3]) >>> 0;
}

function ipMatches(clientIp, rule) {
  if (rule === clientIp) return true;

  if (rule.includes('/')) {
    const [rangeIp, prefixStr] = rule.split('/');
    const prefix = Number(prefixStr);
    const rangeInt = ipv4ToInt(rangeIp);
    const clientInt = ipv4ToInt(clientIp);
    if (rangeInt === null || clientInt === null || !Number.isInteger(prefix) || prefix < 0 || prefix > 32) {
      return false;
    }
    const mask = prefix === 0 ? 0 : (0xffffffff << (32 - prefix)) >>> 0;
    return (rangeInt & mask) === (clientInt & mask);
  }

  return false;
}

function requireAllowedIp(req, res, next) {
  if (ALLOWED_CLIENT_IPS.length === 0) {
    return next();
  }

  const clientIp = normalizeIp(req.socket.remoteAddress);

  if (!ALLOWED_CLIENT_IPS.some((rule) => ipMatches(clientIp, rule))) {
    console.warn(`[tiger-bridge] rejected request from disallowed IP ${clientIp}`);
    return res.status(403).json({ ok: false, error: 'source address not authorized' });
  }

  next();
}

// Not behind a reverse proxy — remoteAddress is the real peer, not a
// spoofable X-Forwarded-For header. If this ever moves behind one, trust
// proxy config and this check both need revisiting together.
app.set('trust proxy', false);

app.use(requireAllowedIp);
app.use(requireToken);

let port;
let parser;
let portReady = false;
let reconnectTimer = null;
let shuttingDown = false;

// Last confirmed stop position — set once sendMove() resolves (real or
// mocked), reported back via GET /status. Without this, ForgeDesk's
// wire:poll.10s status refresh has nothing but null to read every 10s,
// which stomps the position it set optimistically right after a move.
let lastPosition = null;
let lastPositionAt = null;

// Travel limits read from the amp's system data on connect (D10 = Lim Max,
// D11 = Lim Min — see docs/tigerstop-sdk/RS-232-TigerStop-Serial.md). null
// until read; while null, range checking is skipped and the amp's own
// ERR_MOVEMAX/ERR_MOVEMIN (EC=6/7) is the only guard.
let limitMin = null;
let limitMax = null;
let readingLimits = false;

// The amp answers one command at a time and replies carry no request id, so
// every serial exchange (moves, D queries) runs through this chain — a limits
// query can never interleave with a move's MGS/MGF acks.
let serialQueue = Promise.resolve();

function enqueueSerial(task) {
  const run = serialQueue.then(task, task);
  serialQueue = run.catch(() => {});
  return run;
}

// --- TigerStop serial connection ------------------------------------------
// Protocol: plain ASCII, \r terminated. "MG<inches>\r" moves the stop.
// The amp replies "MGS ..." (move started) then "MGF" (move finished).
// Requires TigerSET enabled on the amp (firmware v5.60+).

function connectSerial() {
  if (MOCK_SERIAL) {
    // No real TigerStop attached — skip opening a COM port entirely and
    // just pretend one is connected so /move can be exercised standalone.
    portReady = true;
    limitMin = MOCK_LIMIT_MIN;
    limitMax = MOCK_LIMIT_MAX;
    console.log('[tiger-bridge] MOCK_SERIAL enabled — no real COM port opened');
    return;
  }

  port = new SerialPort({ path: SERIAL_PATH, baudRate: BAUD_RATE }, (err) => {
    if (err) {
      console.error('[tiger-bridge] serial open failed:', err.message);
      portReady = false;
      if (!shuttingDown) reconnectTimer = setTimeout(connectSerial, 3000);
      return;
    }
    portReady = true;
    console.log(`[tiger-bridge] connected to TigerStop on ${SERIAL_PATH} @ ${BAUD_RATE}`);
    readLimits();
  });

  parser = port.pipe(new ReadlineParser({ delimiter: '\r' }));

  port.on('close', () => {
    portReady = false;
    if (shuttingDown) return;
    console.warn('[tiger-bridge] serial port closed, retrying in 3s');
    reconnectTimer = setTimeout(connectSerial, 3000);
  });

  port.on('error', (e) => console.error('[tiger-bridge] serial error:', e.message));
}

if (require.main === module) {
  connectSerial();
}

// "D<index>\r" reads one system data item. The amp echoes the command ("d10")
// on its own line, then sends the value ("78.000"); a bad index comes back as
// "X EC=3 BAD INDEX".
function queryData(index) {
  return enqueueSerial(() => new Promise((resolve, reject) => {
    if (!portReady) {
      return reject(new Error('serial port not connected'));
    }

    const timeout = setTimeout(() => {
      cleanup();
      reject(new Error(`timed out waiting for TigerStop reply to D${index}`));
    }, 3000);

    function onLine(line) {
      const trimmed = line.trim();

      if (trimmed.startsWith('X ')) {
        cleanup();
        reject(new Error(`TigerStop reported an error: ${trimmed}`));
      } else if (/^-?\d+(\.\d+)?$/.test(trimmed)) {
        cleanup();
        resolve(parseFloat(trimmed));
      }
      // anything else (the "d10" echo) is ignored
    }

    function cleanup() {
      clearTimeout(timeout);
      parser.off('data', onLine);
    }

    parser.on('data', onLine);

    port.write(`D${index}\r`, (err) => {
      if (err) {
        cleanup();
        reject(err);
      }
    });
  }));
}

async function readLimits() {
  if (readingLimits || MOCK_SERIAL) return;
  readingLimits = true;

  try {
    const max = await queryData(10);
    const min = await queryData(11);
    limitMax = max;
    limitMin = min;
    console.log(`[tiger-bridge] TigerStop travel limits: ${min}" to ${max}"`);
  } catch (e) {
    console.warn(`[tiger-bridge] could not read travel limits: ${e.message}`);
  } finally {
    readingLimits = false;
  }
}

// null when the move is fine (or limits aren't known yet), else a message.
function rangeError(inches) {
  if (limitMin !== null && inches < limitMin - 0.0005) {
    return `${inches}" is below the TigerStop's minimum position (${limitMin}")`;
  }
  if (limitMax !== null && inches > limitMax + 0.0005) {
    return `${inches}" is beyond the TigerStop's maximum position (${limitMax}")`;
  }
  return null;
}

function sendMove(inches) {
  return enqueueSerial(() => sendMoveNow(inches));
}

function sendMoveNow(inches) {
  return new Promise((resolve, reject) => {
    if (!portReady) {
      return reject(new Error('serial port not connected'));
    }

    const cmd = `MG${Number(inches).toFixed(3)}\r`;

    if (MOCK_SERIAL) {
      console.log(`[tiger-bridge] MOCK_SERIAL: would send ${JSON.stringify(cmd)}`);
      // simulate the amp actually taking a second to move instead of an
      // instant resolve, so callers (CutFlow's UI, position display) see
      // roughly realistic timing rather than an immediate ack.
      setTimeout(() => {
        console.log(`[tiger-bridge] MOCK_SERIAL: move complete, stop at ${Number(inches).toFixed(3)}"`);
        resolve({ started: true, finished: true, raw: 'MGF (mocked)' });
      }, 1000);
      return;
    }

    const timeout = setTimeout(() => {
      cleanup();
      reject(new Error('timed out waiting for TigerStop ack (MGF)'));
    }, 15000);

    function onLine(line) {
      const trimmed = line.trim();

      if (trimmed.startsWith('MGF')) {
        cleanup();
        resolve({ started: true, finished: true, raw: trimmed });
      } else if (trimmed.startsWith('X ')) {
        cleanup();
        reject(new Error(`TigerStop reported an error: ${trimmed}`));
      }
      // MGS ... (move started ack) is ignored — we resolve on MGF.
    }

    function cleanup() {
      clearTimeout(timeout);
      parser.off('data', onLine);
    }

    parser.on('data', onLine);

    port.write(cmd, (err) => {
      if (err) {
        cleanup();
        reject(err);
      }
    });
  });
}

app.post('/move', async (req, res) => {
  const inches = Number(req.body.inches);

  if (!Number.isFinite(inches)) {
    return res.status(400).json({ ok: false, error: 'inches must be a number' });
  }

  const outOfRange = rangeError(inches);
  if (outOfRange) {
    return res.status(400).json({ ok: false, error: outOfRange, outOfRange: true, limitMin, limitMax });
  }

  try {
    const result = await sendMove(inches);
    // a fresh move means whatever the sensor reported for the previous cut
    // no longer applies — back to idle until this new cut completes.
    sensorState = 'idle';
    lastPosition = inches;
    lastPositionAt = new Date().toISOString();
    res.json({ ok: true, lastPosition, lastPositionAt, ...result });
  } catch (e) {
    res.status(502).json({ ok: false, error: e.message });
  }
});

// --- Zebra printer: raw ZPL over TCP port 9100 -----------------------------
//
// 1" x 4" label at 203dpi (~203 x 812 dots). Layout per docs/cutlist.txt:
//   top-left, stacked: job name / part number - finish / part use
//   bottom-left: elevation - length
//   right side: QR code linking to that cut's page in CutFlow (qrUrl) —
//   scanning it opens /cuts/{uuid}, which shows this same record.
//
// Manual-entry stickers (no part/job) omit the top block and print only the
// size + operator/timestamp + QR, per the same spec ("Manual size entry
// print sticker with only size and cut record data").

// The label has no ^CI, so the printer reads ^FD text as single-byte CP850 —
// but Node sends UTF-8, so any non-ASCII character comes out as 2+ garbage
// glyphs (e.g. "·" U+00B7 = C2 B7 prints as "┬À"). Everything is reduced to
// printable ASCII here instead: common punctuation is mapped to a plain
// equivalent, accents are stripped, and anything else is dropped.
const ZPL_ASCII_MAP = {
  '·': '-', '•': '-', '‐': '-', '‑': '-', '‒': '-', '–': '-', '—': '-', '−': '-',
  '‘': "'", '’': "'", '“': '"', '”': '"', '″': '"', '′': "'",
  '×': 'x', '\u00a0': ' ', '…': '...',
};

function escapeZpl(value) {
  return String(value ?? '')
    .replace(/[^\x00-\x7f]/g, (c) => ZPL_ASCII_MAP[c] ?? c)
    .normalize('NFD')
    .replace(/[^\x20-\x7e]/g, '')
    .replace(/[\^~]/g, '');
}

// The label is only 1" tall (203 dots), so the QR code has to be sized to
// its payload: a fixed magnification overflows once the payload (the cut-
// record URL, ~75 bytes) pushes the symbol past version 5. Byte-mode
// capacities per version (1-10) at each error-correction level; a QR symbol
// is (17 + 4*version) modules per side, and ZPL prints each module as
// `magnification` dots. ZPL adds no quiet zone, so the margin is just the
// bare label stock around it.
const LABEL_HEIGHT_DOTS = 203;
const QR_MAX_DOTS = 183; // leaves ~10 dots top and bottom
const QR_BYTE_CAPACITY = {
  Q: [11, 20, 32, 46, 60, 74, 86, 108, 130, 151],
  M: [14, 26, 42, 62, 84, 106, 122, 152, 180, 213],
  L: [17, 32, 53, 78, 106, 134, 154, 192, 230, 271],
};

// Picks the sturdiest error-correction level that still prints at a module
// size of at least 5 dots (reliable for phone cameras on thermal prints),
// else the largest module size achievable at level L. Returns the y offset
// that vertically centres the symbol on the label.
function qrLayout(payload) {
  const bytes = Buffer.byteLength(payload, 'utf8');
  let best = null;

  for (const ecLevel of ['Q', 'M', 'L']) {
    const version = QR_BYTE_CAPACITY[ecLevel].findIndex((cap) => cap >= bytes) + 1;
    if (version === 0) continue;

    const modules = 17 + 4 * version;
    const magnification = Math.min(10, Math.floor(QR_MAX_DOTS / modules));
    if (magnification < 1) continue;

    best = { ecLevel, magnification, size: modules * magnification };
    if (magnification >= 5) break;
  }

  if (!best) {
    // Payload too large for any symbol we can fit; fall back to the old
    // fixed size rather than refusing to print the label.
    return { ecLevel: 'L', magnification: 2, y: 15, size: 0 };
  }

  return { ...best, y: Math.max(0, Math.floor((LABEL_HEIGHT_DOTS - best.size) / 2)) };
}

// Drop-rack tag: profile image left, SKU + length to its right. A scrap tag
// swaps the length for "SCRAP" with the real length as a small detail line.
// `image` is a pre-rendered 1-bit bitmap ({ bytesPerRow, total, hex }) so the
// bridge needs no image library.
function buildDropZpl({ kind, sku, size, detail, image }) {
  const lines = ['^XA'];
  let textX = 20;

  if (image && image.hex) {
    lines.push(`^FO10,10^GFA,${image.total},${image.total},${image.bytesPerRow},${image.hex}^FS`);
    textX = 10 + image.bytesPerRow * 8 + 20;
  }

  lines.push('^CF0,28');
  lines.push(`^FO${textX},12^FD${escapeZpl(kind === 'scrap' ? 'SCRAP' : 'DROP')}^FS`);
  lines.push('^CF0,56');
  lines.push(`^FO${textX},44^FD${escapeZpl(sku)}^FS`);
  lines.push('^CF0,80');
  lines.push(`^FO${textX},108^FD${escapeZpl(size)}${kind === 'scrap' ? '' : '"'}^FS`);

  if (detail) {
    lines.push('^CF0,28');
    lines.push(`^FO${textX + 340},150^FD${escapeZpl(detail)}^FS`);
  }

  lines.push('^XZ');

  return lines.join('\n');
}

function buildZpl({ job, workOrder, part, partUse, elevation, size, operator, timestamp, uuid, qrUrl, kind, sku, detail, image }) {
  if (kind === 'drop' || kind === 'scrap') {
    return buildDropZpl({ kind, sku, size, detail, image });
  }

  const lines = ['^XA'];

  if (part) {
    // top-left: job name / WO# / part number - finish / part use
    // (older ForgeDesk builds send no workOrder — then the WO line is skipped
    // and the rest keeps its original spacing)
    lines.push('^CF0,26');
    let y = 15;
    lines.push(`^FO20,${y}^FD${escapeZpl(job)}^FS`);
    y += 30;
    if (workOrder) {
      lines.push(`^FO20,${y}^FDWO# ${escapeZpl(workOrder)}^FS`);
      y += 30;
    }
    lines.push(`^FO20,${y}^FD${escapeZpl(part)}^FS`);
    y += 30;
    lines.push(`^FO20,${y}^FD${escapeZpl(partUse)}^FS`);

    // bottom-left: elevation - length
    lines.push('^CF0,34');
    lines.push(`^FO20,160^FD${escapeZpl(elevation)} - ${escapeZpl(size)}"^FS`);
  } else {
    // manual entry: size only, no job/part fields
    lines.push('^CF0,50');
    lines.push(`^FO20,20^FD${escapeZpl(size)}"^FS`);
    lines.push('^CF0,20');
    lines.push(`^FO20,80^FD${escapeZpl(operator)}^FS`);
    lines.push(`^FO20,105^FD${escapeZpl(timestamp)}^FS`);
  }

  // right side: QR code — points at the cut's page when a URL was given
  // (current behavior), falls back to the bare uuid for callers that
  // haven't been updated to send qrUrl yet.
  const qrPayload = qrUrl || uuid;
  if (qrPayload) {
    const qr = qrLayout(escapeZpl(qrPayload));
    lines.push(`^FO600,${qr.y}`);
    lines.push(`^BQN,2,${qr.magnification}`);
    lines.push(`^FD${qr.ecLevel}A,${escapeZpl(qrPayload)}^FS`);
  }

  lines.push('^XZ');

  return lines.join('\n');
}

app.post('/print', (req, res) => {
  const { job, workOrder, part, partUse, elevation, size, operator, timestamp, uuid, qrUrl, kind, sku, detail, image } = req.body;
  const zpl = buildZpl({ job, workOrder, part, partUse, elevation, size, operator, timestamp, uuid, qrUrl, kind, sku, detail, image });

  if (MOCK_SERIAL) {
    // No real Zebra printer either in this mode — same spirit as the
    // mocked /move: skip the real socket, log what would have been sent,
    // and report success so the dashboard's move/print/confirm flow can be
    // exercised end-to-end without any hardware attached.
    console.log(`[tiger-bridge] MOCK_SERIAL: would print label uuid=${uuid}`);
    return res.json({ ok: true, mocked: true });
  }

  const socket = new net.Socket();
  socket.setTimeout(5000);

  // A failed connection fires BOTH 'error' and 'close' on the same socket —
  // without this guard both handlers would call res.*() and crash the whole
  // process with ERR_HTTP_HEADERS_SENT the moment the printer is
  // unreachable, taking TigerStop control down with it since it's the same
  // process. Only the first event to land gets to respond.
  let responded = false;

  function respondOnce(fn) {
    if (responded) return;
    responded = true;
    fn();
  }

  socket.connect(PRINTER_PORT, PRINTER_HOST, () => {
    socket.write(zpl, () => socket.end());
  });

  socket.on('close', () => respondOnce(() => res.json({ ok: true })));
  socket.on('timeout', () => {
    socket.destroy();
    respondOnce(() => res.status(504).json({ ok: false, error: 'printer connection timed out' }));
  });
  socket.on('error', (e) => respondOnce(() => res.status(502).json({ ok: false, error: e.message })));
});

// --- cut-complete sensor -----------------------------------------------------
//
// Hardware TBD (see docs/plans/multi-job-cutflow-plan.md, Phase 4) — likely
// another serial line off the TigerStop or a GPIO/relay board once it's
// picked. Until then this is an in-memory stub: idle by default, settable
// via POST /sensor/trigger so the Laravel side (nextCut() / checkSensor())
// can be built and tested against it now. Swap sensorState's writes for
// real hardware events later; GET /sensor/status doesn't need to change.

let sensorState = 'idle'; // idle | cutting | complete

app.get('/sensor/status', (req, res) => {
  res.json({ ok: true, status: sensorState });
});

// Dev/test hook until real hardware fires this. Not for shop-floor use.
app.post('/sensor/trigger', (req, res) => {
  const { status } = req.body;

  if (!['idle', 'cutting', 'complete'].includes(status)) {
    return res.status(400).json({ ok: false, error: 'status must be idle, cutting, or complete' });
  }

  sensorState = status;
  res.json({ ok: true, status: sensorState });
});

// --- status -----------------------------------------------------------------

app.get('/status', (req, res) => {
  // limits unread (amp was off when we connected, or the first query failed)
  // — retry in the background; the next poll picks them up.
  if (portReady && limitMin === null && limitMax === null) {
    readLimits();
  }

  res.json({
    ok: true,
    version: VERSION,
    serialConnected: portReady,
    serialPath: SERIAL_PATH,
    baudRate: BAUD_RATE,
    mockSerial: MOCK_SERIAL,
    printerHost: PRINTER_HOST,
    printerPort: PRINTER_PORT,
    lastPosition,
    lastPositionAt,
    limitMin,
    limitMax,
  });
});

// --- shutdown -----------------------------------------------------------
//
// A plain `kill`/window-close on the old code left the OS to reclaim the
// COM handle whenever it got around to it — usually fine, but on some
// USB-serial adapters the port stays "busy" for a few seconds after an
// ungraceful exit, so a quick stop/start could fail to reopen it. This
// closes the serial port explicitly before exiting so the handle is
// released immediately, and stops the reconnect loop from re-opening it
// again the instant we've asked it to close.

let httpServer;

function shutdown(signal) {
  if (shuttingDown) return;
  shuttingDown = true;
  console.log(`[tiger-bridge] ${signal} received, shutting down...`);

  if (reconnectTimer) clearTimeout(reconnectTimer);

  // don't hang forever if the serial close (or an in-flight request)
  // never calls back — e.g. a wedged USB-serial adapter.
  const forceExit = setTimeout(() => {
    console.error('[tiger-bridge] shutdown timed out, forcing exit');
    process.exit(1);
  }, 5000);

  const finish = () => {
    clearTimeout(forceExit);
    process.exit(0);
  };

  httpServer.close(() => {
    if (port && port.isOpen) {
      port.close((err) => {
        if (err) console.error('[tiger-bridge] error closing serial port:', err.message);
        else console.log(`[tiger-bridge] serial port ${SERIAL_PATH} released`);
        finish();
      });
    } else {
      finish();
    }
  });
}

if (require.main === module) {
  httpServer = app.listen(BRIDGE_PORT, () => {
    console.log(`[tiger-bridge] v${VERSION} listening on http://localhost:${BRIDGE_PORT}`);
  });

  // SIGINT: Ctrl+C in the console this was started from, or `pm2 stop`.
  // SIGTERM: sent by most service managers/`taskkill` (without /F) on stop.
  process.on('SIGINT', () => shutdown('SIGINT'));
  process.on('SIGTERM', () => shutdown('SIGTERM'));
}

module.exports = {
  app,
  buildZpl,
  qrLayout,
  VERSION,
  // test hook: pretend the amp reported these limits
  _setLimits: (min, max) => { limitMin = min; limitMax = max; },
};
