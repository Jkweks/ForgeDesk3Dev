# Plan: Cut Station as an installable PWA (and only the cut station)

## Goal

Make `/cut-station` installable on the shop tablet as a full-screen, app-like
kiosk (home-screen icon, no browser chrome, branded splash, graceful
"can't reach server" screen). The rest of ForgeDesk is **not** a PWA and must
not be affected.

## What a PWA can and cannot do here (set expectations)

The cut station is a Livewire component: every keypad press, stick build, cut
confirmation and sensor poll is a server round trip, and the server in turn
calls tiger-bridge (TigerStop + Zebra printer). There is nothing meaningful to
do offline — a cut can't be recorded, planned or labelled without the server.

So the PWA value is **installability + kiosk feel + clean failure**, not
offline operation. Explicitly out of scope: offline cut queueing, caching job
or part data, background sync. (If offline cutting is ever wanted, that is a
separate, much larger redesign away from Livewire.)

## Current state (verified in code)

- Cut station = `App\Livewire\CutFlow\Dashboard` at `/cut-station`, layout
  `resources/views/components/cutflow/layout.blade.php`, CSS
  `resources/css/cutflow.css` (separate Vite entry from the main app).
- Already tablet-minded: viewport has `user-scalable=no`, on-screen keypad,
  PIN sign-in, tablet IP allowlist for hardware actions.
- No manifest, service worker, or icons exist anywhere in the repo.
- Public, unauthenticated route group (no ForgeDesk login) — same as `/shop`.
- Other routes under the same prefix: `/cut-station/import`,
  `/cut-station/settings`, `/cut-station/cuts/{uuid}` (the QR-scan cut record).
- `APP_URL=https://dev.kweks.co` behind a reverse proxy → HTTPS (a PWA
  requirement) is already satisfied *if the tablet uses the https hostname*,
  not `http://<lan-ip>:8040`.

## Design

### 1. Scope isolation
Everything PWA-related is scoped to `/cut-station/`:
- Manifest `scope` and `start_url` = `/cut-station/`.
- The manifest `<link>`, theme-color meta, apple-touch-icon and service-worker
  registration go **only** in the cutflow layout — never in `layouts/app`.
- Service worker registered with scope `/cut-station/`, so it never sees
  ForgeDesk's other pages or `/api` calls.

### 2. Web app manifest
Served by a Laravel route `GET /cut-station/manifest.webmanifest`
(`application/manifest+json`):
- `name: "CutFlow"`, `short_name: "CutFlow"`, `id` and `start_url`/`scope`
  `/cut-station/`
- `display: "standalone"` (consider `"fullscreen"` once the on-screen UI is
  confirmed not to need the status bar); `orientation` per the tablet's mount
  (landscape likely — confirm)
- `background_color` / `theme_color` from the cutflow.css palette
  (light/dark: the app already has a dark toggle — pick one for the manifest,
  can't vary per theme)
- Icons: 192, 512 PNG + a maskable 512; apple-touch-icon 180. New artwork
  under `public/cutflow-pwa/` (needs a logo — see open questions).

### 3. Service worker (deliberately minimal)
Served by a Laravel route `GET /cut-station/sw.js` (not a static file in
`public/`: a path under `public/cut-station/` would collide with the Laravel
route, and a root-level file would need `Service-Worker-Allowed`). Response
headers: `Content-Type: text/javascript`, `Cache-Control: no-cache`.

Behavior:
- **Precache** only: `/cut-station/offline` (static fallback page), icons,
  manifest.
- **Navigations:** network-only; on network failure return the offline page.
  **Never serve a cached cut-station HTML page** — it carries a Livewire
  snapshot and CSRF token and would produce 419s / stale state.
- **Livewire/XHR/POST and `/livewire*` requests:** pass straight through, never
  cached. (Cached tiger-bridge state or cut confirmations would be dangerous.)
- **Vite hashed assets (`/build/assets/*`):** cache-first is safe (content-hashed);
  optional, purely a load-speed win.
- Versioned cache name; `skipWaiting` + `clients.claim` plus delete old caches
  on activate so updates don't strand the kiosk on an old shell.

### 4. Offline / error page
A small static Blade page (`cutflow/offline.blade.php`) in the cutflow
styling: "Can't reach the server — check the network", with a Retry button that
reloads, and an auto-retry every few seconds. Distinct from Livewire's own
error handling.

### 5. Layout changes (`components/cutflow/layout.blade.php`)
- `<link rel="manifest">`, `theme-color`, `apple-mobile-web-app-capable`,
  `apple-mobile-web-app-title`, `apple-touch-icon`.
- Service-worker registration snippet (feature-detected, try/catch).
- `viewport-fit=cover` and safe-area padding where the tablet has insets.

### 6. Standalone-mode fixes in `cutflow.css`
- `.layout { height: calc(100vh - 60px) }` → use `100dvh` (with `100vh`
  fallback) — `100vh` is wrong under dynamic browser UI.
- `overscroll-behavior: none` and `touch-action: manipulation` on the shell to
  stop pull-to-refresh/double-tap-zoom accidentally reloading mid-cut.
- Disable text selection/long-press callout on buttons/keypad.
- Standalone has no address bar/back button: audit that every in-app screen
  (import, settings, cut record) has an on-screen way back to the dashboard.

### 7. Kiosk behavior worth adding while here
- **Screen Wake Lock** (`navigator.wakeLock`) while the dashboard is open so
  the tablet doesn't sleep mid-job (re-acquire on `visibilitychange`).
- **Session expiry:** `SESSION_LIFETIME=120`. A tablet left on the dashboard
  overnight will 419 on the next Livewire action. Handle with a friendly
  "session expired — tap to reload" (Livewire `request` hook / `Livewire.hook`
  for 419) rather than the default modal. This is a real kiosk bug independent
  of PWA, but PWA installs make it more visible.
- `wire:poll` pauses in background tabs; irrelevant when installed full-screen,
  but verify the sensor/bridge polling resumes after the tablet wakes.

### 8. Infra
- Nginx (`nginx/default.conf`) needs no change if SW/manifest go through
  Laravel. If we instead used static files: add `Cache-Control: no-cache` for
  the SW and `Service-Worker-Allowed`.
- Reverse proxy must serve the tablet over HTTPS with a valid cert. Check the
  tablet uses `https://dev.kweks.co/cut-station` (or the prod host), not the
  raw `:8040` URL — otherwise the SW won't register and Chrome won't offer
  install.
- IP allowlist uses `$request->ip()`: unchanged by the PWA, but confirm the
  proxy's forwarded-for handling still yields the tablet's real IP once it's
  launched from the installed app (it should — same network path).

## Files

New:
- `laravel/app/Http/Controllers/CutFlow/PwaController.php` — `manifest()`,
  `serviceWorker()`, `offline()`
- `laravel/resources/views/cutflow/offline.blade.php`
- `laravel/resources/js/cutflow-sw.js` (source, or inline string in controller
  /Blade view so no Vite build step is needed for the SW)
- `laravel/public/cutflow-pwa/icon-192.png`, `icon-512.png`,
  `icon-maskable-512.png`, `apple-touch-icon.png`

Changed:
- `laravel/routes/web.php` — three routes under `/cut-station/` (declare before
  `cuts/{cutLogEntry:uuid}`; no conflict but keep the file's ordering habit)
- `laravel/resources/views/components/cutflow/layout.blade.php`
- `laravel/resources/css/cutflow.css`
- `laravel/resources/views/cutflow/livewire/dashboard.blade.php` — wake lock +
  419 handling snippets (or in the layout)

Untouched: `layouts/app.blade.php`, every non-CutFlow page, nginx, docker.

## Verification

1. Feature tests: manifest route returns valid JSON with correct scope/type;
   SW route returns JS with `no-cache`; offline route 200; the **main app
   layout does not contain** a manifest link or SW registration (guards the
   "only the cut station" requirement). Run via the project's documented
   container test path, not against the live DB.
2. Lighthouse "Installable" audit on `/cut-station` over HTTPS.
3. On the real tablet: install, launch from the icon (standalone, no browser
   bar), run a full flow — PIN sign-in → build stick → move → cut → label
   print — and confirm identical behavior to the browser tab.
4. Kill the network mid-session: confirm offline page appears, and on
   reconnect the app recovers without a stale-CSRF 419.
5. Deploy a new SW version (bump cache name); confirm the tablet picks it up
   without manual cache clearing.
6. Leave it idle > `SESSION_LIFETIME`, then tap: friendly reload, no dead UI.
7. Confirm scanning a printed label's QR (`/cut-station/cuts/{uuid}`) on a
   phone still works as a plain web page (see risk below).

## Risks / things to decide

- **QR cut-record pages share the scope.** On Android, an installed PWA can
  capture links in its scope, so a phone that installed the app could open QR
  scans inside it. Mitigation: only install on the shop tablet; or move the
  record page outside the scope (`/cut-record/{uuid}`) — a small route change
  that also keeps the manifest scope clean. Recommended.
- **Stale shell risk** is the main hazard of any SW on a Livewire app; the
  network-only-navigation rule above is what prevents it. Do not add
  runtime HTML caching later.
- **iOS** (if the tablet is an iPad): Add-to-Home-Screen works but SW support
  and wake-lock are more limited; no install prompt (manual Share → Add).
- **Manifest-only fallback:** if the SW causes any trouble, the manifest +
  meta tags alone still give home-screen/standalone launch on most tablets.
  The SW adds only the offline page and asset caching, so it is the part to
  drop if in doubt.

## Open questions

1. What is the shop tablet (Android/Chrome, iPad, Windows touchscreen) and
   which URL does it use today?
2. Landscape-locked, or should orientation be free?
3. Is there a logo/icon for CutFlow, or should I generate a simple one?
4. OK to move the QR record page out of `/cut-station/` (recommended above)?
   Printed labels already in the field point at the old URL, so the old path
   would need to keep working (redirect) either way.
5. Want the wake-lock and session-expiry handling included in this change, or
   split off as a separate fix?

## Rough effort

Small: ~1 day including icons and on-device testing. The code is a controller,
a ~40-line service worker, an offline page, layout tags and CSS tweaks; most
of the time is verifying on the real tablet.
