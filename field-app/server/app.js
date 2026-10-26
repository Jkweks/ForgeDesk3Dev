'use strict';
const path = require('path');
const express = require('express');
const { query } = require('./db');
const routes = require('./routes');

function createApp() {
  const app = express();
  app.disable('x-powered-by');
  app.set('trust proxy', 1); // Caddy sits in front; needed for the real client IP and Secure cookies

  app.use((_req, res, next) => {
    res.set({
      'Content-Security-Policy': "default-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; style-src 'self'; style-src-attr 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'",
      'X-Content-Type-Options': 'nosniff',
      'Referrer-Policy': 'no-referrer',
      'Permissions-Policy': 'camera=(self), geolocation=(), microphone=()',
      'Strict-Transport-Security': 'max-age=31536000',
    });
    next();
  });

  app.get('/healthz', async (_req, res) => {
    try { await query('SELECT 1'); res.json({ ok: true }); } catch { res.status(503).json({ ok: false }); }
  });

  // Photos arrive base64-encoded inside the JSON body (10 x ~1 MB after the phone resizes them).
  app.use('/api', express.json({ limit: '40mb' }), routes);
  app.use('/api', (_req, res) => res.status(404).json({ error: 'Not found.' }));

  const pub = path.join(__dirname, '..', 'public');
  app.get('/vendor/jsqr.js', (_req, res) => res.sendFile(require.resolve('jsqr/dist/jsQR.js')));
  app.use(express.static(pub, {
    setHeaders: (res, file) => {
      // The service worker and shell must revalidate so a deploy reaches phones promptly.
      if (/(sw\.js|index\.html|manifest\.webmanifest)$/.test(file)) res.set('Cache-Control', 'no-cache');
    },
  }));

  // eslint-disable-next-line no-unused-vars
  app.use((err, _req, res, _next) => {
    if (err.type === 'entity.too.large') return res.status(413).json({ error: 'Submission too large.' });
    if (err.type === 'entity.parse.failed') return res.status(400).json({ error: 'Bad request.' });
    console.error('[error]', err);
    res.status(500).json({ error: 'Something went wrong. Try again.' });
  });

  return app;
}

module.exports = { createApp };
