require('dotenv').config();

const express = require('express');
const net = require('net');
const { SerialPort } = require('serialport');
const { ReadlineParser } = require('@serialport/parser-readline');

const app = express();
app.use(express.json());

const BRIDGE_PORT = process.env.BRIDGE_PORT || 9111;
const SERIAL_PATH = process.env.SERIAL_PORT || 'COM3';
const BAUD_RATE = parseInt(process.env.BAUD_RATE || '57600', 10);
const PRINTER_HOST = process.env.PRINTER_HOST || '192.168.1.50';
const PRINTER_PORT = parseInt(process.env.PRINTER_PORT || '9100', 10);

let port;
let parser;
let portReady = false;

// --- TigerStop serial connection ------------------------------------------
// Protocol: plain ASCII, \r terminated. "MG<inches>\r" moves the stop.
// The amp replies "MGS ..." (move started) then "MGF" (move finished).
// Requires TigerSET enabled on the amp (firmware v5.60+).

function connectSerial() {
  port = new SerialPort({ path: SERIAL_PATH, baudRate: BAUD_RATE }, (err) => {
    if (err) {
      console.error('[tiger-bridge] serial open failed:', err.message);
      portReady = false;
      setTimeout(connectSerial, 3000);
      return;
    }
    portReady = true;
    console.log(`[tiger-bridge] connected to TigerStop on ${SERIAL_PATH} @ ${BAUD_RATE}`);
  });

  parser = port.pipe(new ReadlineParser({ delimiter: '\r' }));

  port.on('close', () => {
    portReady = false;
    console.warn('[tiger-bridge] serial port closed, retrying in 3s');
    setTimeout(connectSerial, 3000);
  });

  port.on('error', (e) => console.error('[tiger-bridge] serial error:', e.message));
}

connectSerial();

function sendMove(inches) {
  return new Promise((resolve, reject) => {
    if (!portReady) {
      return reject(new Error('serial port not connected'));
    }

    const cmd = `MG${Number(inches).toFixed(3)}\r`;

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

  try {
    const result = await sendMove(inches);
    // a fresh move means whatever the sensor reported for the previous cut
    // no longer applies — back to idle until this new cut completes.
    sensorState = 'idle';
    res.json({ ok: true, ...result });
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

function escapeZpl(value) {
  return String(value ?? '').replace(/[\^~]/g, '');
}

function buildZpl({ job, part, partUse, elevation, size, operator, timestamp, uuid, qrUrl }) {
  const lines = ['^XA'];

  if (part) {
    // top-left: job name / part number - finish / part use
    lines.push('^CF0,26');
    lines.push(`^FO20,15^FD${escapeZpl(job)}^FS`);
    lines.push(`^FO20,45^FD${escapeZpl(part)}^FS`);
    lines.push(`^FO20,75^FD${escapeZpl(partUse)}^FS`);

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
    lines.push('^FO600,15');
    lines.push('^BQN,2,5');
    lines.push(`^FDQA,${escapeZpl(qrPayload)}^FS`);
  }

  lines.push('^XZ');

  return lines.join('\n');
}

app.post('/print', (req, res) => {
  const { job, part, partUse, elevation, size, operator, timestamp, uuid, qrUrl } = req.body;
  const zpl = buildZpl({ job, part, partUse, elevation, size, operator, timestamp, uuid, qrUrl });

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
  res.json({
    ok: true,
    serialConnected: portReady,
    serialPath: SERIAL_PATH,
    baudRate: BAUD_RATE,
    printerHost: PRINTER_HOST,
    printerPort: PRINTER_PORT,
  });
});

app.listen(BRIDGE_PORT, () => {
  console.log(`[tiger-bridge] listening on http://localhost:${BRIDGE_PORT}`);
});
