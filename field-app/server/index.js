'use strict';
const { waitForDb } = require('./db');
const { createApp } = require('./app');

(async () => {
  await waitForDb();
  const port = parseInt(process.env.PORT || '3000', 10);
  createApp().listen(port, () => console.log(`[field-app] listening on :${port}`));
})().catch((e) => { console.error('[field-app] failed to start:', e.message); process.exit(1); });
