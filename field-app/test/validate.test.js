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

test('shipment_inspection: answers keyed by item id, photo refs bounded, up to 30 photos', () => {
  const ok = validateSubmission(base({ kind: 'shipment_inspection', asset_number: 'SHP-00001', payload: {
    notes: 'dock', items: [{ item_id: 4, value: 'fail', comment: 'cracked', photos: [0], critical: true }, { item_id: 5, value: '4' }] } }), true);
  assert.equal(ok.ok, true);
  assert.deepEqual(ok.value.payload.items[0], { item_id: 4, value: 'fail', comment: 'cracked', photos: [0] });   // client "critical" dropped
  assert.equal(ok.value.payload.notes, 'dock');

  const bad = (items) => validateSubmission(base({ kind: 'shipment_inspection', payload: { items } }), true).ok;
  assert.equal(bad([]), false);
  assert.equal(bad([{ item_id: 'x', value: 'pass' }]), false);
  assert.equal(bad([{ item_id: 1, value: 5 }]), false);
  assert.equal(bad([{ item_id: 1, value: 'fail', photos: [99] }]), false);
  assert.equal(validateSubmission(base({ kind: 'shipment_inspection', payload: { items: [{ item_id: 1, value: 'pass' }] }, photos: Array(30).fill({ filename: 'a.jpg', data_b64: JPEG }) }), true).ok, true);
  assert.equal(validateSubmission(base({ kind: 'shipment_inspection', payload: { items: [{ item_id: 1, value: 'pass' }] }, photos: Array(31).fill({ filename: 'a.jpg', data_b64: JPEG }) }), true).ok, false);
  assert.equal(validateSubmission(base({ kind: 'shipment_inspection', payload: { items: [{ item_id: 1, value: 'pass' }] } }), false).ok, false);
});

test('shipment_create keeps known fields, dedupes jobs and needs at least one load', () => {
  const ok = validateSubmission(base({ kind: 'shipment_create', asset_number: 'NEW-AB12CD', payload: {
    job_numbers: ['A-1', 'A-1', ' A-2 '], ship_date: '2026-12-01', destination: 'Dock', evil: 'x',
    loads: [{ type_id: 2, piece_count: 4 }, { type_id: 3 }],
  } }), true);
  assert.equal(ok.ok, true);
  assert.deepEqual(ok.value.payload, { ship_date: '2026-12-01', destination: 'Dock', job_numbers: ['A-1', 'A-2'], loads: [{ type_id: 2, piece_count: 4 }, { type_id: 3 }] });

  const bad = (payload) => validateSubmission(base({ kind: 'shipment_create', asset_number: 'NEW-1', payload }), true).ok;
  assert.equal(bad({ loads: [] }), false);
  assert.equal(bad({ loads: [{ type_id: 1 }, { type_id: 1 }] }), false);          // duplicate type
  assert.equal(bad({ loads: [{ type_id: 1, piece_count: -3 }] }), false);
  assert.equal(bad({ loads: [{ type_id: 1 }], ship_date: '12/01/2026' }), false);
  assert.equal(bad({ loads: [{ type_id: 1 }], job_numbers: 'A-1' }), false);
});

test('shipment_reschedule takes a date or clears it; precheck reuses the delivery answer rules', () => {
  const r = (payload) => validateSubmission(base({ kind: 'shipment_reschedule', asset_number: 'SHP-1', payload }), true);
  assert.deepEqual(r({ ship_date: '2026-12-24' }).value.payload, { ship_date: '2026-12-24' });
  assert.deepEqual(r({ ship_date: null }).value.payload, { ship_date: null });
  assert.equal(r({ ship_date: 'soon' }).ok, false);

  const pre = validateSubmission(base({ kind: 'shipment_precheck', asset_number: 'SHP-1', payload: { items: [{ item_id: 5, value: 'pass', photos: [0] }] }, photos: [{ filename: 'a.jpg', data_b64: JPEG }] }), true);
  assert.equal(pre.ok, true);
  assert.equal(pre.value.photos.length, 1);
  assert.equal(validateSubmission(base({ kind: 'shipment_precheck', asset_number: 'SHP-1', payload: { items: [] } }), true).ok, false);
});
