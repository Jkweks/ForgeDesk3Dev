{{--
  Customize panel (colour mode, accent, navigation, layout, ...). Available on every page.

  Every setting is a `data-bs-<key>` attribute on <html> plus a `tabler-<key>` localStorage
  entry, written only when it differs from the default (same contract as Tabler's
  tabler-theme.js, which applies the stored values on page load). Changes are also saved to
  the user's profile (PUT /user/theme-preferences) so they follow them to other devices.
--}}
@php
  $themeSections = [
    ['key' => 'theme', 'title' => 'Color mode', 'default' => 'auto', 'options' => [
      'auto' => 'Auto', 'light' => 'Light', 'dark' => 'Dark',
    ]],
    ['key' => 'theme-primary', 'title' => 'Accent color', 'default' => 'blue', 'type' => 'color', 'options' => [
      'blue' => 'Blue', 'azure' => 'Azure', 'indigo' => 'Indigo', 'purple' => 'Purple', 'pink' => 'Pink', 'red' => 'Red',
      'orange' => 'Orange', 'yellow' => 'Yellow', 'lime' => 'Lime', 'green' => 'Green', 'teal' => 'Teal', 'cyan' => 'Cyan',
    ]],
    ['key' => 'navbar-position', 'title' => 'Navigation', 'default' => 'horizontal', 'options' => [
      'horizontal' => 'Top bar', 'vertical' => 'Sidebar',
    ]],
    ['key' => 'sidebar', 'title' => 'Sidebar', 'default' => 'default', 'only' => 'vertical', 'options' => [
      'default' => 'Expanded', 'folded' => 'Folded', 'folded-hover' => 'Folded, open on hover',
    ]],
    ['key' => 'navbar', 'title' => 'Top bar behavior', 'default' => 'default', 'only' => 'horizontal', 'options' => [
      'default' => 'Scrolls with page', 'sticky' => 'Sticky',
    ]],
    ['key' => 'layout', 'title' => 'Container width', 'default' => 'default', 'options' => [
      'default' => 'Default', 'fluid' => 'Full width', 'boxed' => 'Boxed',
    ]],
    ['key' => 'theme-base', 'title' => 'Gray scale', 'default' => 'neutral', 'options' => [
      'slate' => 'Slate', 'gray' => 'Gray', 'zinc' => 'Zinc', 'neutral' => 'Neutral', 'stone' => 'Stone',
    ]],
    ['key' => 'theme-font', 'title' => 'Font', 'default' => 'sans-serif', 'type' => 'font', 'options' => [
      'sans-serif' => 'Sans-serif', 'serif' => 'Serif', 'monospace' => 'Mono', 'comic' => 'Comic',
    ]],
    ['key' => 'theme-radius', 'title' => 'Corner radius', 'default' => '1', 'options' => [
      '0' => 'None', '0.5' => 'Small', '1' => 'Default', '1.5' => 'Large', '2' => 'Round',
    ]],
  ];
  $themeDefaults = collect($themeSections)->mapWithKeys(fn ($s) => [$s['key'] => $s['default']])->all();
@endphp
<form class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasTheme" role="dialog" aria-modal="true" aria-labelledby="offcanvasThemeLabel">
  <div class="offcanvas-header">
    <h2 class="offcanvas-title" id="offcanvasThemeLabel">Customize</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
    @foreach ($themeSections as $section)
      <section class="p-4 {{ ! $loop->last ? 'border-bottom' : '' }}" data-theme-section="{{ $section['key'] }}" @if (! empty($section['only'])) data-only="{{ $section['only'] }}" @endif>
        <div class="subheader mb-2">{{ $section['title'] }}</div>
        @if (($section['type'] ?? null) === 'color')
          <div class="row g-2">
            @foreach ($section['options'] as $value => $label)
              <div class="col-auto">
                <label class="form-colorinput">
                  <input type="radio" name="{{ $section['key'] }}" value="{{ $value }}" class="form-colorinput-input" aria-label="{{ $label }}">
                  <span class="form-colorinput-color bg-{{ $value }}"></span>
                </label>
              </div>
            @endforeach
          </div>
        @else
          <div class="form-selectgroup">
            @foreach ($section['options'] as $value => $label)
              <label class="form-selectgroup-item">
                <input type="radio" name="{{ $section['key'] }}" value="{{ $value }}" class="form-selectgroup-input">
                <span class="form-selectgroup-label {{ ($section['type'] ?? null) === 'font' ? 'font-'.$value : '' }}">{{ $label }}</span>
              </label>
            @endforeach
          </div>
        @endif
      </section>
    @endforeach
  </div>
  <div class="offcanvas-footer border-top p-3 d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary" id="resetThemeBtn">Reset</button>
    <button type="button" class="btn btn-primary flex-fill" data-bs-dismiss="offcanvas">Done</button>
  </div>
</form>

<script>
(function () {
  var DEFAULTS = @json($themeDefaults);
  var html = document.documentElement;
  var form = document.getElementById('offcanvasTheme');
  var prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

  function stored(key) {
    try { return localStorage.getItem('tabler-' + key); } catch (e) { return null; }
  }
  function setStored(key, value) {
    try {
      if (value === null || value === DEFAULTS[key]) localStorage.removeItem('tabler-' + key);
      else localStorage.setItem('tabler-' + key, value);
    } catch (e) { /* private mode — the attribute below still applies for this page */ }
  }
  function current(key) {
    var v = stored(key);
    return v !== null ? v : DEFAULTS[key];
  }

  // Mirror tabler-theme.js: default => attribute absent; "auto" resolves to light/dark.
  function apply(key, value) {
    setStored(key, value);
    if (key === 'theme') {
      html.setAttribute('data-bs-theme', value === 'auto' ? (prefersDark.matches ? 'dark' : 'light') : value);
    } else if (value === DEFAULTS[key]) {
      html.removeAttribute('data-bs-' + key);
    } else {
      html.setAttribute('data-bs-' + key, value);
    }
  }

  prefersDark.addEventListener('change', function () {
    if (current('theme') === 'auto') apply('theme', 'auto');
  });

  function syncForm() {
    Object.keys(DEFAULTS).forEach(function (key) {
      var input = form.querySelector('input[name="' + key + '"][value="' + current(key) + '"]');
      if (input) input.checked = true;
    });
    var position = current('navbar-position');
    form.querySelectorAll('[data-only]').forEach(function (el) {
      el.hidden = el.dataset.only !== position;
    });
  }

  // Non-default values only; the server stores exactly what is sent (full replace).
  function persistToServer() {
    if (typeof apiCall !== 'function' || typeof currentUser === 'undefined' || !currentUser) return;
    var payload = {};
    Object.keys(DEFAULTS).forEach(function (key) {
      var v = stored(key);
      if (v !== null && v !== DEFAULTS[key]) payload[key] = v;
    });
    apiCall('/user/theme-preferences', { method: 'PUT', body: JSON.stringify(payload) })
      .catch(function () { /* best-effort — local state already applied */ });
  }

  // Pull the signed-in user's saved preferences so the look follows them to a new device.
  function syncFromServer() {
    if (typeof window.sessionReady === 'undefined') return;
    window.sessionReady.then(function () {
      var prefs = typeof currentUser !== 'undefined' && currentUser ? currentUser.theme_preferences : null;
      if (!prefs) return;
      Object.keys(DEFAULTS).forEach(function (key) {
        var value = prefs[key];
        if (value && current(key) !== value) apply(key, value);
      });
      syncForm();
    }).catch(function () { /* offline / not logged in — keep local theme */ });
  }

  form.addEventListener('change', function (e) {
    if (e.target.type !== 'radio' || !(e.target.name in DEFAULTS)) return;
    apply(e.target.name, e.target.value);
    syncForm();
    persistToServer();
    window.dispatchEvent(new Event('resize')); // let sticky/scroll layouts re-measure
  });

  document.getElementById('resetThemeBtn').addEventListener('click', function () {
    Object.keys(DEFAULTS).forEach(function (key) { apply(key, DEFAULTS[key]); });
    syncForm();
    persistToServer();
  });

  form.addEventListener('submit', function (e) { e.preventDefault(); });
  syncForm();
  syncFromServer();
})();
</script>
