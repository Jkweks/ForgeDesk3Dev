'use strict';
/**
 * Validation for crew submissions. ForgeDesk re-validates everything it ingests, so this is the
 * first line of defence and the source of friendly errors for the phone, not the last word.
 * Output is rebuilt from known fields only: unknown keys (and any client-sent "critical" flag)
 * never reach the database.
 */

const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const KINDS = ['inspection', 'meter', 'defect'];
const RESULTS = ['pass', 'fail', 'na'];
const SEVERITIES = ['low', 'medium', 'high', 'critical'];
const MAX_PHOTOS = 10;
const MAX_PHOTO_BYTES = 10 * 1024 * 1024; // decoded; matches ForgeDesk's limit

const fail = (error) => ({ ok: false, error });
const str = (v, max) => (typeof v === 'string' ? v.trim().slice(0, max) : '');
const isNum = (v, max = 99999999) => typeof v === 'number' && Number.isFinite(v) && v >= 0 && v <= max;

/** Identify an image by its first bytes; the claimed filename/mime is never trusted. */
function sniffImage(buf) {
  if (buf.length > 3 && buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return 'image/jpeg';
  if (buf.length > 8 && buf.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return 'image/png';
  if (buf.length > 12 && buf.subarray(0, 4).toString('latin1') === 'RIFF' && buf.subarray(8, 12).toString('latin1') === 'WEBP') return 'image/webp';
  return null;
}

const EXT = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };

function validatePhotos(photos) {
  if (photos == null) return { ok: true, value: [] };
  if (!Array.isArray(photos) || photos.length > MAX_PHOTOS) return fail(`At most ${MAX_PHOTOS} photos per submission.`);
  const out = [];
  for (const p of photos) {
    const b64 = typeof p?.data_b64 === 'string' ? p.data_b64.replace(/^data:[^,]*,/, '') : '';
    if (!b64 || !/^[A-Za-z0-9+/]+={0,2}$/.test(b64)) return fail('A photo is not valid base64 image data.');
    const buf = Buffer.from(b64, 'base64');
    if (buf.length > MAX_PHOTO_BYTES) return fail('A photo is larger than 10 MB.');
    const mime = sniffImage(buf);
    if (!mime) return fail('Photos must be JPEG, PNG or WebP images.');
    const base = str(p.filename, 100).replace(/[^\w.\- ]+/g, '_').replace(/\.[^.]*$/, '') || 'photo';
    out.push({ filename: `${base}.${EXT[mime]}`, mime, data_b64: b64 });
  }
  return { ok: true, value: out };
}

function validatePayload(kind, p) {
  if (!p || typeof p !== 'object') return fail('Payload is missing.');

  if (kind === 'meter') {
    if (!isNum(p.reading)) return fail('Enter a valid meter reading.');
    return { ok: true, value: { reading: p.reading } };
  }

  if (kind === 'defect') {
    const title = str(p.title, 255);
    if (!title) return fail('A defect needs a title.');
    const severity = SEVERITIES.includes(p.severity) ? p.severity : 'medium';
    const description = str(p.description, 5000);
    return { ok: true, value: { title, severity, ...(description && { description }) } };
  }

  // inspection
  if (!Array.isArray(p.items) || p.items.length < 1 || p.items.length > 100) return fail('An inspection needs 1–100 checklist items.');
  const items = [];
  for (const i of p.items) {
    const label = str(i?.label, 255);
    if (!label || !RESULTS.includes(i?.result)) return fail('Every checklist item needs a label and a result.');
    const comment = str(i.comment, 1000);
    items.push({ label, result: i.result, ...(comment && { comment }) });
  }
  const value = { items };
  const template = str(p.template_name, 255);
  const notes = str(p.notes, 5000);
  if (template) value.template_name = template;
  if (notes) value.notes = notes;
  if (p.meter != null) {
    if (!isNum(p.meter)) return fail('Meter must be a positive number.');
    value.meter = p.meter;
  }
  return { ok: true, value };
}

/**
 * @param body  parsed JSON request
 * @param knownAsset  true if asset_number is a published, active asset
 */
function validateSubmission(body, knownAsset) {
  if (!body || typeof body !== 'object') return fail('Bad request.');
  if (!UUID_V4.test(String(body.submission_uuid || ''))) return fail('submission_uuid must be a UUID v4.');
  if (!KINDS.includes(body.kind)) return fail('Unknown submission kind.');
  if (!knownAsset) return fail('That asset is not available. Refresh your asset list.');

  const payload = validatePayload(body.kind, body.payload);
  if (!payload.ok) return payload;
  const photos = validatePhotos(body.photos);
  if (!photos.ok) return photos;

  // Device clocks are unreliable; fall back to server time if unparseable. ForgeDesk also clamps.
  const t = new Date(body.submitted_at);
  const submittedAt = Number.isNaN(t.getTime()) ? new Date() : t;

  return {
    ok: true,
    value: {
      submission_uuid: body.submission_uuid.toLowerCase(),
      kind: body.kind,
      asset_number: String(body.asset_number),
      submitted_at: submittedAt.toISOString(),
      payload: payload.value,
      photos: photos.value,
    },
  };
}

module.exports = { validateSubmission, sniffImage, UUID_V4 };
