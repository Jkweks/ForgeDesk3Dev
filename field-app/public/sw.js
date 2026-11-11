'use strict';
// App-shell cache only. Data (assets, checklists, the outbox) lives in IndexedDB, managed by app.js.
const CACHE = 'field-shell-v13';
const SHELL = ['/', '/style.css', '/app.js', '/vendor/jsqr.js', '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/apple-touch-icon.png'];

const withTimeout = (p, ms) => new Promise((resolve, reject) => {
  const t = setTimeout(() => reject(new Error('timeout')), ms);
  p.then((v) => { clearTimeout(t); resolve(v); }, (err) => { clearTimeout(t); reject(err); });
});

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith('/api/') || url.pathname === '/healthz') return;
  // Network first so a deploy is picked up when the phone is online, but never make a weak signal wait:
  // after 3 s fall back to the cached copy (the network request still finishes and refreshes the cache).
  e.respondWith((async () => {
    const cache = await caches.open(CACHE);
    const net = fetch(e.request).then((res) => { if (res.ok) cache.put(e.request, res.clone()); return res; });
    const cached = () => cache.match(e.request).then((hit) => hit || (e.request.mode === 'navigate' ? cache.match('/') : undefined));
    try {
      return await Promise.race([net, new Promise((_, rej) => setTimeout(rej, 3000))]);
    } catch {
      return (await cached()) || net.catch(() => Response.error());
    }
  })());
});
