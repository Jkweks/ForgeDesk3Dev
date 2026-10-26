#!/usr/bin/env node
'use strict';
/**
 * Crew logins are ForgeDesk accounts (Users -> "Allow mobile app"), published here by ForgeDesk's
 * sync. They are not created or changed on this server. Read-only helpers:
 *   docker compose exec app node scripts/user.js list              who can sign in, last sync time
 *   docker compose exec app node scripts/user.js signout <email>   end that person's sessions now
 */
const { pool, query } = require('../server/db');

const [cmd, email] = process.argv.slice(2);

(async () => {
  if (cmd === 'list') {
    const { rows } = await query(`SELECT login, display_name, is_active, (password_hash <> '') AS has_credential, synced_at
                                    FROM fa_users ORDER BY lower(login)`);
    rows.forEach((u) => console.log(`${u.is_active && u.has_credential ? 'can sign in ' : 'blocked     '} ${u.login.padEnd(36)} ${u.display_name}  (synced ${u.synced_at?.toISOString?.() ?? 'never'})`));
    if (!rows.length) console.log('No users yet. In ForgeDesk, tick "Allow mobile app" on a user and wait for the next sync (2 min).');
  } else if (cmd === 'signout' && email) {
    const r = await query('DELETE FROM fa_sessions WHERE user_id IN (SELECT forgedesk_user_id FROM fa_users WHERE lower(login) = lower($1))', [email]);
    console.log(`Ended ${r.rowCount} session(s).`);
  } else {
    console.error('Usage: user.js list | signout <email>\nAdd, remove or disable people in ForgeDesk (Users).');
    process.exitCode = 1;
  }
})().catch((e) => { console.error(e.message); process.exitCode = 1; }).finally(() => pool.end());
