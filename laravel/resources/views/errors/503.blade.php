<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta http-equiv="refresh" content="30">
  <title>ForgeDesk — Maintenance</title>
  {{-- Self-hosted, not a CDN — must render even if outbound network to third
       parties is unreliable during the outage this page exists for. Rendered
       once when `artisan down` runs (not per-request), so no dynamic app
       state (auth, DB, etc.) is available or needed here. --}}
  <link href="{{ asset('assets/tabler/css/tabler.min.css') }}" rel="stylesheet">
  <style>
    body { background: var(--tblr-bg-surface-secondary, #f4f6fb); }
    .maint-icon { width: 64px; height: 64px; color: var(--tblr-primary, #206bc4); }
  </style>
</head>
<body>
  <div class="page page-center">
    <div class="container container-tight py-4">
      <div class="empty">
        <div class="empty-icon">
          <svg xmlns="http://www.w3.org/2000/svg" class="maint-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <path d="M7 10h3v-3l-3.5 -3.5a6 6 0 0 1 8 8l6 6a2 2 0 0 1 -3 3l-6 -6a6 6 0 0 1 -8 -8l3.5 3.5" />
          </svg>
        </div>
        <p class="empty-title">ForgeDesk is undergoing brief maintenance</p>
        <p class="empty-subtitle text-muted">
          We're applying a scheduled update. This page will refresh automatically —
          no action needed on your end, and nothing you'd already saved is affected.
        </p>
      </div>
    </div>
  </div>
</body>
</html>
