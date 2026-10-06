@props(['pwa' => true])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    @if ($pwa)
    <link rel="manifest" href="{{ route('cutflow.pwa.manifest', [], false) }}">
    <meta name="theme-color" content="#22262B">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="CutFlow">
    <link rel="apple-touch-icon" href="/cutflow-pwa/apple-touch-icon.png">
    @endif
    <title>CutFlow</title>
    <script>
        // Set before first paint so there's no light-mode flash on load —
        // must run before the stylesheet's data-theme selectors are matched.
        (function () {
            try {
                if (localStorage.getItem('cutflow_theme') === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/cutflow.css'])
    @livewireStyles
</head>
<body>
    {{ $slot }}

    <div class="theme-toggle" id="theme-toggle" title="Toggle dark mode" role="button" aria-label="Toggle dark mode"></div>
    <script>
        (function () {
            var root = document.documentElement;
            var btn = document.getElementById('theme-toggle');

            function isDark() {
                return root.getAttribute('data-theme') === 'dark';
            }

            function render() {
                btn.textContent = isDark() ? '☀' : '☽';
            }

            btn.addEventListener('click', function () {
                var next = isDark() ? 'light' : 'dark';

                if (next === 'dark') {
                    root.setAttribute('data-theme', 'dark');
                } else {
                    root.removeAttribute('data-theme');
                }

                try { localStorage.setItem('cutflow_theme', next); } catch (e) {}

                render();
            });

            render();
        })();
    </script>

    @livewireScripts

    @if ($pwa)
    <script>
        // Installable kiosk behaviour — see docs/plans/cut-station-pwa.md.
        (function () {
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    try {
                        navigator.serviceWorker.register('{{ route('cutflow.pwa.sw', [], false) }}', { scope: '/cut-station' })
                            .catch(function () {});
                    } catch (e) {}
                });
            }

            // Keep the tablet awake while a cut screen is open; the lock is
            // released by the browser whenever the page is hidden, so re-take it.
            var wakeLock = null;
            function takeWakeLock() {
                try {
                    if (!('wakeLock' in navigator) || document.visibilityState !== 'visible') return;
                    navigator.wakeLock.request('screen').then(function (l) { wakeLock = l; }).catch(function () {});
                } catch (e) {}
            }
            document.addEventListener('visibilitychange', takeWakeLock);
            takeWakeLock();

            // Idle past SESSION_LIFETIME => next Livewire request 419s. Replace
            // Livewire's default modal with a plain reload prompt.
            document.addEventListener('livewire:init', function () {
                Livewire.hook('request', function (ctx) {
                    ctx.fail(function (f) {
                        if (f.status === 419) {
                            f.preventDefault();
                            if (window.confirm('This screen timed out. Tap OK to reload.')) location.reload();
                        }
                    });
                });
            });
        })();
    </script>
    @endif
</body>
</html>
