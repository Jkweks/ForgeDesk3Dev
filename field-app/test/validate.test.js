'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { validateSubmission, sniffImage } = require('../server/validate');

const UUID = '3f2b6c1e-8a4d-4e1b-9c3a-5d7e8f901234';
const JPEG = Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(20)]).toString('base64');
const base = (over = {}) => ({
  submission_uuid: UUID, kind: 'meter', asset_number: 'FA-1', submitted_at: '2026-10-25T12:00:00Z', payload: { reading: 100.5 }, ...over,
});

test('accepts a valid meter reading and rebuilds the payload from known fields only', () => {
  const r = validateSubmission(base({ payload: { reading: 5, evil: '<script>' } }), true);
  assert.equal(r.ok, true);
  assert.deepEqual(r.value.payload, { reading: 5 });
});

test('rejects bad uuid, unknown kind, unknown asset', () => {
  assert.equal(validateSubmission(base({ submission_uuid: 'nope' }), true).ok, false);
  assert.equal(validateSubmission(base({ kind: 'hack' }), true).ok, false);
  assert.equal(validateSubmission(base(), false).ok, false);
});

test('meter must be a finite non-negative number, not a string', () => {
  for (const reading of [-1, '12', NaN, Infinity, null, 1e12]) {
    assert.equal(validateSubmission(base({ payload: { reading } }), true).ok, false, String(reading));
  }
});

test('inspection: requires items, validates results, strips client critical flag, trims long text', () => {
  assert.equal(validateSubmission(base({ kind: 'inspection', payload: { items: [] } }), true).ok, false);
  assert.equal(validateSubmission(base({ kind: 'inspection', payload: { items: [{ label: 'Brakes', result: 'maybe' }] } }), true).ok, false);
  const r = validateSubmission(base({
    kind: 'inspection',
    payload: { template_name: 'Truck inspection', meter: 10, notes: 'x'.repeat(9000),
      items: [{ label: 'Brakes', result: 'fail', comment: 'grinding', critical: true }, { label: 'Lights', result: 'pass' }] },
  }), true);
  assert.equal(r.ok, true);
  assert.equal(r.value.payload.items[0].critical, undefined);
  assert.equal(r.value.payload.notes.length, 5000);
  assert.equal(r.value.payload.meter, 10);
});

test('defect: needs a title, defaults severity', () => {
  assert.equal(validateSubmission(base({ kind: 'defect', payload: { title: '  ' } }), true).ok, false);
  const r = validateSubmission(base({ kind: 'defect', payload: { title: 'Leak', severity: 'bogus' } }), true);
  assert.equal(r.value.payload.severity, 'medium');
});

test('photos are identified by content, not by claimed name or type', () => {
  const ok = validateSubmission(base({ photos: [{ filename: '../../x.exe', data_b64: `data:image/jpeg;base64,${JPEG}` }] }), true);
  assert.equal(ok.ok, true);
  assert.equal(ok.value.photos[0].mime, 'image/jpeg');
  assert.match(ok.value.photos[0].filename, /^[\w.\- ]+\.jpg$/);
  const spoof = Buffer.from('<?php echo 1;').toString('base64');
  assert.equal(validateSubmission(base({ photos: [{ filename: 'a.jpg', data_b64: spoof }] }), true).ok, false);
  assert.equal(validateSubmission(base({ photos: Array(11).fill({ filename: 'a.jpg', data_b64: JPEG }) }), true).ok, false);
  assert.equal(validateSubmission(base({ photos: [{ filename: 'a.jpg', data_b64: '***' }] }), true).ok, false);
});

test('sniffImage recognises jpeg/png/webp only', () => {
  assert.equal(sniffImage(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0])), 'image/png');
  assert.equal(sniffImage(Buffer.concat([Buffer.from('RIFF'), Buffer.alloc(4), Buffer.from('WEBPVP8 ')])), 'image/webp');
  assert.equal(sniffImage(Buffer.from('GIF89a......')), null);
});

test('unparseable device time falls back to now', () => {
  const r = validateSubmission(base({ submitted_at: 'garbage' }), true);
  assert.equal(r.ok, true);
  assert.ok(Math.abs(Date.now() - new Date(r.value.submitted_at)) < 5000);
});
