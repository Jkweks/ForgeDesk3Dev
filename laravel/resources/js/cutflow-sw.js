// CutFlow service worker. Intentionally minimal — see docs/plans/cut-station-pwa.md.
// The two double-underscore placeholders below are substituted by PwaController::serviceWorker().
//
// Rules that must not be relaxed:
//  - never cache or serve a cut-station HTML page (Livewire snapshot + CSRF token)
//  - never touch non-GET requests or /livewire* (cuts, sensor polls, bridge calls)
const CACHE = 'cutflow-__VERSION__';
const OFFLINE_URL = '/cut-station/offline';
const PRECACHE = [OFFLINE_URL, ...__ICONS__];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(PRECACHE.map((u) => new Request(u, { cache: 'reload' }))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k.startsWith('cutflow-') && k !== CACHE).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((res) => (res.status === 502 || res.status === 503 || res.status === 504)
                    ? offline()
                    : res)
                .catch(offline)
        );
        return;
    }

    // Content-hashed by Vite, so cache-first can never go stale.
    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/cutflow-pwa/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(CACHE).then((c) => c.put(req, copy));
                }
                return res;
            }))
        );
    }
    // Everything else (incl. /livewire*) falls through to the network untouched.
});

function offline() {
    return caches.match(OFFLINE_URL).then((r) => r || new Response('Cannot reach the server.', { status: 503 }));
}
