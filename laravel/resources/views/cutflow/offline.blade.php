<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>CutFlow — offline</title>
    {{-- Styles are inline on purpose: this page is precached by the service worker,
         and the Vite CSS filename changes on every build. --}}
    <style>
        :root { --bg:#EDEEF0; --ink:#1C1F23; --muted:#55585D; --accent:#B23A3A; }
        @media (prefers-color-scheme: dark) { :root { --bg:#14171A; --ink:#E7E9EC; --muted:#9AA0A6; } }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { background: var(--bg); color: var(--ink); font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
               display: flex; align-items: center; justify-content: center; text-align: center; padding: 24px; }
        h1 { font-size: 28px; margin: 0 0 8px; }
        p { font-size: 16px; color: var(--muted); margin: 0 0 24px; }
        button { font: inherit; font-weight: 600; font-size: 18px; padding: 16px 40px; border: 0; border-radius: 12px;
                 background: var(--accent); color: #fff; touch-action: manipulation; }
        small { display: block; margin-top: 16px; color: var(--muted); }
    </style>
</head>
<body>
    <main>
        <h1>Can't reach the server</h1>
        <p>Check the network connection. CutFlow will reconnect automatically.</p>
        <button type="button" onclick="location.reload()">Retry now</button>
        <small id="status">Retrying every few seconds…</small>
    </main>
    <script>
        // Served by the service worker in place of whatever page failed, so
        // location.href is the page to return to.
        (function () {
            function check() {
                fetch(location.href, { cache: 'no-store', headers: { 'X-CutFlow-Probe': '1' } })
                    .then(function (res) { if (res.ok) location.reload(); })
                    .catch(function () {});
            }
            setInterval(check, 4000);
        })();
    </script>
</body>
</html>
