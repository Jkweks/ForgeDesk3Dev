const test = require('node:test');
const assert = require('node:assert');

// server.js refuses to load without a token (see network-security.test.js).
process.env.BRIDGE_TOKEN = process.env.BRIDGE_TOKEN || 'test-token';
const { buildZpl, qrLayout } = require('../server');

const LABEL_HEIGHT_DOTS = 203;

test('QR for a real cut-record URL fits inside the 1" label height', () => {
  const url = 'https://dev.kweks.co/cut-station/cuts/0b9f2a3e-6c1d-4f7a-9d52-3e8b1c4a7f10';
  const qr = qrLayout(url);

  assert.ok(qr.y >= 0);
  assert.ok(qr.y + qr.size <= LABEL_HEIGHT_DOTS, `QR spans ${qr.y}..${qr.y + qr.size}`);
  assert.ok(qr.magnification >= 4);
});

test('QR fits for payloads from a bare uuid up to a long URL', () => {
  for (const len of [10, 36, 60, 74, 100, 150]) {
    const qr = qrLayout('x'.repeat(len));
    assert.ok(qr.y + qr.size <= LABEL_HEIGHT_DOTS, `len ${len}: ${qr.y + qr.size} dots`);
  }
});

test('buildZpl emits the computed QR placement', () => {
  const zpl = buildZpl({ size: '45.125', uuid: 'abc', qrUrl: 'https://dev.kweks.co/cut-station/cuts/0b9f2a3e-6c1d-4f7a-9d52-3e8b1c4a7f10' });
  const qr = qrLayout('https://dev.kweks.co/cut-station/cuts/0b9f2a3e-6c1d-4f7a-9d52-3e8b1c4a7f10');

  assert.match(zpl, new RegExp(`\\^FO600,${qr.y}\\n\\^BQN,2,${qr.magnification}\\n\\^FD${qr.ecLevel}A,`));
});

test('drop tag prints image, SKU and length, and no QR', () => {
  const zpl = buildZpl({ kind: 'drop', sku: 'E14025-BL', size: '85', image: { bytesPerRow: 2, total: 4, hex: 'FF00FF00' } });

  assert.match(zpl, /\^GFA,4,4,2,FF00FF00/);
  assert.match(zpl, /E14025-BL/);
  assert.match(zpl, /\^FD85"\^FS/);
  assert.doesNotMatch(zpl, /\^BQN/);
});

test('scrap tag says SCRAP with the real length as detail', () => {
  const zpl = buildZpl({ kind: 'scrap', sku: 'E14025-BL', size: 'SCRAP', detail: 'too short - 71"' });

  assert.match(zpl, /\^FDSCRAP\^FS/);
  assert.match(zpl, /too short - 71"/);
});
