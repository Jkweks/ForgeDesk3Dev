'use strict';
const express = require('express');
const { pool, query } = require('./db');
const auth = require('./auth');
const { validateSubmission } = require('./validate');

const router = express.Router();
router.use(auth.csrfGuard);
router.use(auth.loadUser);

const SHIPMENT_PERMS = ['shipments.view', 'shipments.manage', 'shipments.inspect', 'dev.report'];
/** Permissions are a UI hint only; ForgeDesk re-checks them when the submission is ingested. */
const permsOf = (u) => { try { const p = JSON.parse(u.permissions || '[]'); return Array.isArray(p) ? p.filter((x) => SHIPMENT_PERMS.includes(x)) : []; } catch { return []; } };
const publicUser = (u) => ({ id: u.id, username: u.username, display_name: u.display_name, permissions: permsOf(u) });

router.post('/login', async (req, res, next) => {
  try {
    if (auth.loginBlocked(req)) return res.status(429).json({ error: 'Too many attempts. Try again in a few minutes.' });
    const user = await auth.verifyLogin(req.body?.username, req.body?.password);
    if (!user) {
      auth.loginFailed(req);
      return res.status(401).json({ error: 'Wrong email or password. Mobile access is set up by an administrator in ForgeDesk.' });
    }
    auth.loginSucceeded(req);
    const token = await auth.createSession(user.id);
    res.set('Set-Cookie', auth.cookieHeader(token, auth.SESSION_DAYS * 86400));
    res.json({ user: publicUser(user) });
  } catch (e) { next(e); }
});

router.post('/logout', async (req, res, next) => {
  try {
    await auth.destroySession(req.sessionToken);
    res.set('Set-Cookie', auth.cookieHeader('', 0));
    res.json({ ok: true });
  } catch (e) { next(e); }
});

router.get('/me', auth.requireAuth, (req, res) => res.json({ user: publicUser(req.user) }));

/** Everything the phone needs to work offline: assets, checklists, and this user's recent sends. */
router.get('/bootstrap', auth.requireAuth, async (req, res, next) => {
  try {
    const [assets, checklists, shipments, jobs, types, mine] = await Promise.all([
      query(`SELECT asset_number, name, type_name, meter_type, status, current_meter::float8 AS current_meter,
                    current_job_label, synced_at
               FROM fa_assets WHERE is_active ORDER BY asset_number`),
      query('SELECT type_name, template_name, items FROM fa_checklists ORDER BY type_name'),
      query(`SELECT shipment_number, job_label, destination, carrier, pickup_name, pickup_address, pickup_lat, pickup_lng,
                    dropoff_name, dropoff_address, dropoff_lat, dropoff_lng, status, loads, to_char(ship_date, 'YYYY-MM-DD') AS ship_date,
                    checklist, pre_checklist
               FROM fa_shipments WHERE is_active ORDER BY ship_date NULLS FIRST, shipment_number`),
      query('SELECT job_number, job_name FROM fa_jobs WHERE is_active ORDER BY job_number DESC'),
      query('SELECT type_id, name FROM fa_shipment_types WHERE is_active ORDER BY sort_order, name'),
      recent(req.user.display_name),
    ]);
    res.json({
      user: publicUser(req.user),
      server_time: new Date().toISOString(),
      assets: assets.rows,
      checklists: checklists.rows.map((c) => ({ ...c, items: safeJson(c.items, []) })),
      shipments: shipments.rows.map((r) => ({ ...r, checklist: safeJson(r.checklist, []), pre_checklist: safeJson(r.pre_checklist, []) })),
      jobs: jobs.rows,
      shipment_types: types.rows,
      recent: mine,
    });
  } catch (e) { next(e); }
});

router.get('/submissions/mine', auth.requireAuth, async (req, res, next) => {
  try { res.json({ items: await recent(req.user.display_name) }); } catch (e) { next(e); }
});

async function recent(displayName) {
  const { rows } = await query(
    `SELECT submission_uuid, kind, asset_number, submitted_at, (ingested_at IS NOT NULL) AS processed
       FROM fa_submissions WHERE submitted_by = $1 ORDER BY id DESC LIMIT 30`,
    [displayName],
  );
  return rows;
}

/**
 * Idempotent: the phone replays a queued submission with the same submission_uuid until it hears
 * back, so a repeat is acknowledged as "duplicate" and never inserted twice.
 */
router.post('/submissions', auth.requireAuth, async (req, res, next) => {
  const client = await pool.connect().catch(next);
  if (!client) return;
  try {
    // For shipment kinds asset_number carries the shipment number (contract v3/v4); a new shipment has none yet.
    const kind = req.body?.kind;
    const num = String(req.body?.asset_number || '');
    let known;
    if (kind === 'shipment_create' || kind === 'dev_report') known = { rowCount: 1 };
    else if (kind === 'shipment_inspection') known = await client.query(
      // Also accepted while a "mark shipped" from this app is still waiting for ForgeDesk to publish the new status.
      `SELECT 1 FROM fa_shipments sh WHERE sh.shipment_number = $1 AND sh.is_active AND (sh.status = 'shipped' OR
         (sh.status = 'pre_check_done' AND EXISTS (SELECT 1 FROM fa_submissions x WHERE x.kind = 'shipment_ship' AND x.asset_number = sh.shipment_number)))`, [num]);
    else if (kind === 'shipment_ship') known = await client.query("SELECT 1 FROM fa_shipments WHERE shipment_number = $1 AND is_active AND status = 'pre_check_done'", [num]);
    else if (kind === 'shipment_precheck' || kind === 'shipment_reschedule') known = await client.query("SELECT 1 FROM fa_shipments WHERE shipment_number = $1 AND is_active AND status IN ('draft','pre_check_done')", [num]);
    else known = await client.query('SELECT 1 FROM fa_assets WHERE asset_number = $1 AND is_active', [num]);
    if (kind === 'dev_report' && !permsOf(req.user).includes('dev.report')) return res.status(403).json({ error: 'Only administrators can send development reports.' });
    if (kind === 'shipment_create' && !permsOf(req.user).includes('shipments.manage')) return res.status(403).json({ error: 'You do not have permission to create shipments.' });
    if ((kind === 'shipment_reschedule' || kind === 'shipment_ship') && !permsOf(req.user).includes('shipments.manage')) return res.status(403).json({ error: 'You do not have permission to reschedule shipments.' });
    if (kind === 'shipment_precheck' && !permsOf(req.user).includes('shipments.inspect')) return res.status(403).json({ error: 'You do not have permission to do pre-shipment checks.' });
    const v = validateSubmission(req.body, known.rowCount === 1);
    if (!v.ok) return res.status(422).json({ error: v.error });
    const s = v.value;

    await client.query('BEGIN');
    const ins = await client.query(
      `INSERT INTO fa_submissions (submission_uuid, kind, asset_number, submitted_by, submitted_by_user_id, submitted_at, payload)
       VALUES ($1, $2, $3, $4, $5, $6, $7) ON CONFLICT (submission_uuid) DO NOTHING RETURNING id`,
      [s.submission_uuid, s.kind, s.asset_number, req.user.display_name, req.user.id, s.submitted_at, JSON.stringify(s.payload)],
    );
    if (ins.rowCount === 0) {
      await client.query('ROLLBACK');
      return res.json({ status: 'duplicate' });
    }
    for (const p of s.photos) {
      await client.query('INSERT INTO fa_photos (submission_uuid, filename, mime, data_b64) VALUES ($1, $2, $3, $4)',
        [s.submission_uuid, p.filename, p.mime, p.data_b64]);
    }
    await client.query('COMMIT');
    res.status(201).json({ status: 'accepted' });
  } catch (e) {
    await client.query('ROLLBACK').catch(() => {});
    next(e);
  } finally {
    client.release();
  }
});

function safeJson(text, fallback) {
  try { return JSON.parse(text); } catch { return fallback; }
}

module.exports = router;
