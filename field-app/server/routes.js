'use strict';
const express = require('express');
const { pool, query } = require('./db');
const auth = require('./auth');
const { validateSubmission } = require('./validate');

const router = express.Router();
router.use(auth.csrfGuard);
router.use(auth.loadUser);

const publicUser = (u) => ({ id: u.id, username: u.username, display_name: u.display_name });

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
    const [assets, checklists, mine] = await Promise.all([
      query(`SELECT asset_number, name, type_name, meter_type, status, current_meter::float8 AS current_meter,
                    current_job_label, synced_at
               FROM fa_assets WHERE is_active ORDER BY asset_number`),
      query('SELECT type_name, template_name, items FROM fa_checklists ORDER BY type_name'),
      recent(req.user.display_name),
    ]);
    res.json({
      user: publicUser(req.user),
      server_time: new Date().toISOString(),
      assets: assets.rows,
      checklists: checklists.rows.map((c) => ({ ...c, items: safeJson(c.items, []) })),
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
    const asset = await client.query('SELECT 1 FROM fa_assets WHERE asset_number = $1 AND is_active', [String(req.body?.asset_number || '')]);
    const v = validateSubmission(req.body, asset.rowCount === 1);
    if (!v.ok) return res.status(422).json({ error: v.error });
    const s = v.value;

    await client.query('BEGIN');
    const ins = await client.query(
      `INSERT INTO fa_submissions (submission_uuid, kind, asset_number, submitted_by, submitted_at, payload)
       VALUES ($1, $2, $3, $4, $5, $6) ON CONFLICT (submission_uuid) DO NOTHING RETURNING id`,
      [s.submission_uuid, s.kind, s.asset_number, req.user.display_name, s.submitted_at, JSON.stringify(s.payload)],
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
