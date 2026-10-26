'use strict';
const { Pool } = require('pg');

// Connection settings come from the standard PG* environment variables (see docker-compose.yml).
const pool = new Pool({ max: 10 });
pool.on('error', (e) => console.error('[db] idle client error:', e.message));

/** Block startup until Postgres answers, so `docker compose up` ordering hiccups don't crash-loop. */
async function waitForDb(tries = 30) {
  for (let i = 1; ; i++) {
    try {
      await pool.query('SELECT 1');
      return;
    } catch (e) {
      if (i >= tries) throw e;
      await new Promise((r) => setTimeout(r, 1000));
    }
  }
}

module.exports = { pool, query: (...a) => pool.query(...a), waitForDb };
