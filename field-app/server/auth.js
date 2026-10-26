'use strict';
const crypto = require('crypto');
const bcrypt = require('bcryptjs');
const { query } = require('./db');

const COOKIE = 'fa_session';
const SESSION_DAYS = Math.max(1, parseInt(process.env.SESSION_DAYS || '30', 10));
const SECURE = (process.env.COOKIE_SECURE ?? 'true') !== 'false';
// Compared against when the username doesn't exist, so response time doesn't reveal valid usernames.
const DUMMY_HASH = bcrypt.hashSync('not-a-real-password', 10);

const sha256 = (s) => crypto.createHash('sha256').update(s).digest('hex');

function parseCookies(header = '') {
  const out = {};
  for (const part of header.split(';')) {
    const i = part.indexOf('=');
    if (i > 0) out[part.slice(0, i).trim()] = decodeURIComponent(part.slice(i + 1).trim());
  }
  return out;
}

function cookieHeader(value, maxAgeSeconds) {
  return [
    `${COOKIE}=${encodeURIComponent(value)}`,
    'Path=/', 'HttpOnly', 'SameSite=Strict', `Max-Age=${maxAgeSeconds}`,
    ...(SECURE ? ['Secure'] : []),
  ].join('; ');
}

async function createSession(userId) {
  const token = crypto.randomBytes(32).toString('base64url');
  await query(
    `INSERT INTO fa_sessions (token_hash, user_id, expires_at)
     VALUES ($1, $2, (now() at time zone 'utc') + make_interval(days => $3))`,
    [sha256(token), userId, SESSION_DAYS],
  );
  return token;
}

async function verifyLogin(username, password) {
  // Logins are the ForgeDesk accounts published to fa_users. An empty hash means the account can't
  // sign in right now (inactive, disabled, access removed, or still on a temporary password).
  const { rows } = await query(
    `SELECT forgedesk_user_id AS id, login AS username, display_name, password_hash
       FROM fa_users WHERE lower(login) = lower($1) AND is_active AND password_hash <> ''`,
    [String(username || '').trim().slice(0, 200)],
  );
  const user = rows[0];
  // Laravel writes $2y$ hashes; bcryptjs understands them. Compare against a dummy when there is
  // no such user so response time doesn't reveal which emails exist.
  const ok = await bcrypt.compare(String(password || '').slice(0, 200), user ? user.password_hash : DUMMY_HASH);
  return ok && user ? user : null;
}

/** Attach req.user from the session cookie (sliding expiry). */
async function loadUser(req, _res, next) {
  try {
    const token = parseCookies(req.headers.cookie)[COOKIE];
    if (token) {
      const { rows } = await query(
        `SELECT u.forgedesk_user_id AS id, u.login AS username, u.display_name, s.expires_at
           FROM fa_sessions s JOIN fa_users u ON u.forgedesk_user_id = s.user_id
          WHERE s.token_hash = $1 AND s.expires_at > (now() at time zone 'utc') AND u.is_active AND u.password_hash <> ''`,
        [sha256(token)],
      );
      if (rows[0]) {
        req.user = rows[0];
        req.sessionToken = token;
        // Keep active phones signed in: push expiry out once less than half remains.
        if (new Date(rows[0].expires_at).getTime() - Date.now() < SESSION_DAYS * 43200000) {
          query(`UPDATE fa_sessions SET expires_at = (now() at time zone 'utc') + make_interval(days => $2) WHERE token_hash = $1`,
            [sha256(token), SESSION_DAYS]).catch(() => {});
        }
      }
    }
    next();
  } catch (e) { next(e); }
}

function requireAuth(req, res, next) {
  if (!req.user) return res.status(401).json({ error: 'Sign in required.' });
  next();
}

async function destroySession(token) {
  if (token) await query('DELETE FROM fa_sessions WHERE token_hash = $1', [sha256(token)]);
}

/**
 * Cross-site request forgery guard. SameSite=Strict already blocks cookies on cross-site
 * requests; additionally every state-changing call must be JSON and carry our header, which a
 * foreign page cannot send without a CORS preflight (which we never allow).
 */
function csrfGuard(req, res, next) {
  if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) return next();
  if (req.get('x-requested-with') !== 'fieldapp' || !req.is('application/json')) {
    return res.status(400).json({ error: 'Bad request.' });
  }
  next();
}

/** Tiny in-memory limiter for the login endpoint: 10 failures / 15 min per IP+username. */
const attempts = new Map();
const WINDOW = 15 * 60 * 1000;
const MAX_FAILS = 10;
function loginKey(req) { return `${req.ip}|${String(req.body?.username || '').toLowerCase().slice(0, 100)}`; }
function loginBlocked(req) {
  const rec = attempts.get(loginKey(req));
  return !!rec && rec.until > Date.now() && rec.fails >= MAX_FAILS;
}
function loginFailed(req) {
  const k = loginKey(req);
  const rec = attempts.get(k);
  attempts.set(k, !rec || rec.until < Date.now() ? { fails: 1, until: Date.now() + WINDOW } : { fails: rec.fails + 1, until: rec.until });
}
function loginSucceeded(req) { attempts.delete(loginKey(req)); }
setInterval(() => { const n = Date.now(); for (const [k, v] of attempts) if (v.until < n) attempts.delete(k); }, 60000).unref();

module.exports = {
  COOKIE, SESSION_DAYS, cookieHeader, createSession, verifyLogin, loadUser, requireAuth,
  destroySession, csrfGuard, loginBlocked, loginFailed, loginSucceeded, parseCookies,
};
