<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
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
</body>
</html>
