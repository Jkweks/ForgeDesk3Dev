{{--
  Customize panel (colour mode, accent, navigation, layout, ...). Available on every page.

  Every setting is a `data-bs-<key>` attribute on <html> plus a `tabler-<key>` localStorage
  entry, written only when it differs from the default (same contract as Tabler's
  tabler-theme.js, which applies the stored values on page load). Changes are also saved to
  the user's profile (PUT /user/theme-preferences) so they follow them to other devices.
--}}
@php
  use App\Support\ThemeTileSvg;

  // control: tile (SVG preview), color (accent swatches), font ("Aa" sample), pill (text only)
  $themeGroups = [
    ['title' => 'Appearance', 'settings' => [
      ['key' => 'theme', 'legend' => 'Color mode', 'hint' => 'Auto follows your device setting.', 'default' => 'auto', 'control' => 'tile',
        'options' => ['auto' => 'Auto', 'light' => 'Light', 'dark' => 'Dark']],
      ['key' => 'theme-primary', 'legend' => 'Accent color', 'hint' => 'Buttons, links and highlights.', 'default' => 'blue', 'control' => 'color',
        'options' => ['blue' => 'Blue', 'azure' => 'Azure', 'indigo' => 'Indigo', 'purple' => 'Purple', 'pink' => 'Pink', 'red' => 'Red',
          'orange' => 'Orange', 'yellow' => 'Yellow', 'lime' => 'Lime', 'green' => 'Green', 'teal' => 'Teal', 'cyan' => 'Cyan']],
      ['key' => 'theme-base', 'legend' => 'Gray scale', 'hint' => 'The neutral tone behind backgrounds, borders and text.', 'default' => 'neutral', 'control' => 'tile',
        'options' => ['slate' => 'Slate', 'gray' => 'Gray', 'zinc' => 'Zinc', 'neutral' => 'Neutral', 'stone' => 'Stone']],
      ['key' => 'theme-font', 'legend' => 'Font', 'hint' => 'Typeface for the whole app.', 'default' => 'sans-serif', 'control' => 'font',
        'options' => ['sans-serif' => 'Sans-serif', 'serif' => 'Serif', 'monospace' => 'Mono', 'comic' => 'Comic']],
      ['key' => 'theme-radius', 'legend' => 'Corner radius', 'hint' => 'How rounded cards, buttons and inputs are.', 'default' => '1', 'control' => 'tile',
        'options' => ['0' => 'None', '0.5' => 'Small', '1' => 'Default', '1.5' => 'Large', '2' => 'Round']],
    ]],
    ['title' => 'Layout', 'settings' => [
      ['key' => 'navbar-position', 'legend' => 'Navigation', 'hint' => 'Menu across the top or down the side.', 'default' => 'horizontal', 'control' => 'tile',
        'options' => ['horizontal' => 'Top bar', 'vertical' => 'Sidebar']],
      ['key' => 'sidebar', 'legend' => 'Sidebar', 'hint' => 'Fold it to icons to make room.', 'default' => 'default', 'control' => 'tile', 'only' => 'vertical',
        'options' => ['default' => 'Expanded', 'folded' => 'Folded', 'folded-hover' => 'Folded, opens on hover']],
      ['key' => 'navbar', 'legend' => 'Top bar behavior', 'hint' => 'Keep the top bar in view while scrolling.', 'default' => 'default', 'control' => 'tile', 'only' => 'horizontal',
        'options' => ['default' => 'Scrolls with page', 'sticky' => 'Sticky']],
      ['key' => 'navbar-theme', 'legend' => 'Navigation color', 'hint' => 'Light, dark or accent-colored navigation.', 'default' => 'default', 'control' => 'tile',
        'options' => ['default' => 'Default', 'dark' => 'Dark', 'primary' => 'Accent']],
      ['key' => 'nav-menu', 'legend' => 'Menu organization', 'hint' => 'New regroups the menu (Inventory now includes purchasing). Original keeps the earlier layout.', 'default' => 'default', 'control' => 'pill',
        'options' => ['default' => 'New', 'classic' => 'Original']],
      ['key' => 'layout', 'legend' => 'Container width', 'hint' => 'How wide page content may grow.', 'default' => 'default', 'control' => 'tile',
        'options' => ['default' => 'Default', 'fluid' => 'Full width', 'boxed' => 'Boxed']],
    ]],
  ];
  // TABLER_DEFAULTS = value when the data-bs-* attribute is absent (Tabler's CSS default).
  // APP_DEFAULTS = what a user who never customized sees (see $themeAppDefaults in the layout).
  $themeDefaults = collect($themeGroups)->flatMap(fn ($g) => $g['settings'])->mapWithKeys(fn ($s) => [$s['key'] => $s['default']])->all();
  $themeAppDefaults = array_merge($themeDefaults, $themeAppDefaults ?? []);
@endphp
<form class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasTheme" role="dialog" aria-modal="true" aria-labelledby="offcanvasThemeLabel">
  <div class="offcanvas-header">
    <h2 class="offcanvas-title" id="offcanvasThemeLabel"><i class="ti ti-palette me-2"></i>Customize</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
    @foreach ($themeGroups as $group)
      <section class="p-4 {{ ! $loop->last ? 'border-bottom' : '' }}">
        <h3 class="subheader mb-3">{{ $group['title'] }}</h3>
        <div class="space-y-4">
          @foreach ($group['settings'] as $setting)
            <div role="group" aria-labelledby="theme-setting-{{ $setting['key'] }}" @if (! empty($setting['only'])) data-only="{{ $setting['only'] }}" @endif>
              <div class="form-label mb-1" id="theme-setting-{{ $setting['key'] }}">{{ $setting['legend'] }}</div>
              <div class="form-text mb-3 mt-0">{{ $setting['hint'] }}</div>

              @if ($setting['control'] === 'color')
                <div class="row g-2">
                  @foreach ($setting['options'] as $value => $label)
                    <div class="col-auto">
                      <label class="form-colorinput">
                        <input type="radio" name="{{ $setting['key'] }}" value="{{ $value }}" class="form-colorinput-input" aria-label="{{ $label }}">
                        <span class="form-colorinput-color bg-{{ $value }}"></span>
                      </label>
                    </div>
                  @endforeach
                </div>
              @elseif ($setting['control'] === 'font')
                <div class="row g-3">
                  @foreach ($setting['options'] as $value => $label)
                    <div class="col-4">
                      <label class="form-imagecheck d-block">
                        <input type="radio" name="{{ $setting['key'] }}" value="{{ $value }}" class="form-imagecheck-input">
                        <span class="form-imagecheck-figure p-1">
                          <span class="form-imagecheck-image text-center py-2 display-5 fw-medium lh-1 font-{{ $value }}" aria-hidden="true">Aa</span>
                          <span class="form-imagecheck-caption">{{ $label }}</span>
                        </span>
                      </label>
                    </div>
                  @endforeach
                </div>
              @elseif ($setting['control'] === 'pill')
                <div class="form-selectgroup">
                  @foreach ($setting['options'] as $value => $label)
                    <label class="form-selectgroup-item">
                      <input type="radio" name="{{ $setting['key'] }}" value="{{ $value }}" class="form-selectgroup-input">
                      <span class="form-selectgroup-label">{{ $label }}</span>
                    </label>
                  @endforeach
                </div>
              @else
                <div class="row g-3">
                  @foreach ($setting['options'] as $value => $label)
                    <div class="col-4">
                      <label class="form-imagecheck">
                        <input type="radio" name="{{ $setting['key'] }}" value="{{ $value }}" class="form-imagecheck-input">
                        <span class="form-imagecheck-figure p-1">
                          {!! ThemeTileSvg::for($setting['key'], (string) $value) !!}
                          <span class="form-imagecheck-caption">{{ $label }}</span>
                        </span>
                      </label>
                    </div>
                  @endforeach
                </div>
              @endif
            </div>
          @endforeach
        </div>
      </section>
    @endforeach
  </div>
  <div class="offcanvas-footer border-top p-3 space-y-2">
    <button type="button" class="btn w-100" id="resetThemeBtn"><i class="ti ti-rotate me-1"></i>Reset changes</button>
    <button type="button" class="btn btn-primary w-100" data-bs-dismiss="offcanvas">Done</button>
  </div>
</form>

<script>
(function () {
  var TABLER_DEFAULTS = @json($themeDefaults);
  var APP_DEFAULTS = @json($themeAppDefaults);
  var KEYS = Object.keys(TABLER_DEFAULTS);
  var html = document.documentElement;
  var form = document.getElementById('offcanvasTheme');
  var prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

  // localStorage holds ONLY explicit user choices (even when equal to a default), so "never
  // customized" (null) stays distinguishable and falls back to APP_DEFAULTS.
  function stored(key) {
    try { return localStorage.getItem('tabler-' + key); } catch (e) { return null; }
  }
  function setStored(key, value) {
    try {
      if (value === null) localStorage.removeItem('tabler-' + key);
      else localStorage.setItem('tabler-' + key, value);
    } catch (e) { /* private mode — the attribute below still applies for this page */ }
  }
  function current(key) {
    var v = stored(key);
    return v !== null ? v : APP_DEFAULTS[key];
  }

  // Attribute is absent when the value equals Tabler's CSS default; "auto" resolves to light/dark.
  function paint(key, value) {
    if (key === 'theme') {
      html.setAttribute('data-bs-theme', value === 'auto' ? (prefersDark.matches ? 'dark' : 'light') : value);
    } else if (value === TABLER_DEFAULTS[key]) {
      html.removeAttribute('data-bs-' + key);
    } else {
      html.setAttribute('data-bs-' + key, value);
    }
  }
  // value === null clears the explicit choice and reverts to the app default.
  function apply(key, value) {
    setStored(key, value);
    paint(key, value === null ? APP_DEFAULTS[key] : value);
  }

  prefersDark.addEventListener('change', function () {
    if (current('theme') === 'auto') paint('theme', 'auto');
  });

  function syncForm() {
    KEYS.forEach(function (key) {
      var input = form.querySelector('input[name="' + key + '"][value="' + current(key) + '"]');
      if (input) input.checked = true;
    });
    var position = current('navbar-position');
    form.querySelectorAll('[data-only]').forEach(function (el) {
      el.hidden = el.dataset.only !== position;
    });
  }

  function explicitChoices() {
    var payload = {};
    KEYS.forEach(function (key) {
      var v = stored(key);
      if (v !== null) payload[key] = v;
    });
    return payload;
  }

  // Sends every explicit choice; the server stores exactly what is sent (full replace, empty = none).
  function persistToServer() {
    if (typeof apiCall !== 'function' || typeof currentUser === 'undefined' || !currentUser) return;
    apiCall('/user/theme-preferences', { method: 'PUT', body: JSON.stringify(explicitChoices()) })
      .catch(function () { /* best-effort — local state already applied */ });
  }

  // On login/page load the account is the source of truth, so the look follows the user across
  // devices. If the account has nothing saved yet but this browser has explicit choices, upload them.
  function syncFromServer() {
    if (typeof window.sessionReady === 'undefined') return;
    window.sessionReady.then(function () {
      if (typeof currentUser === 'undefined' || !currentUser) return;
      var prefs = currentUser.theme_preferences;
      if (!prefs || !Object.keys(prefs).length) {
        if (Object.keys(explicitChoices()).length) persistToServer();
        return;
      }
      KEYS.forEach(function (key) {
        var value = prefs[key] || null;
        if (stored(key) !== value) apply(key, value);
      });
      syncForm();
    }).catch(function () { /* offline / not logged in — keep local theme */ });
  }

  form.addEventListener('change', function (e) {
    if (e.target.type !== 'radio' || !(e.target.name in TABLER_DEFAULTS)) return;
    apply(e.target.name, e.target.value);
    syncForm();
    persistToServer();
    window.dispatchEvent(new Event('resize')); // let sticky/scroll layouts re-measure
  });

  document.getElementById('resetThemeBtn').addEventListener('click', function () {
    KEYS.forEach(function (key) { apply(key, null); });
    syncForm();
    persistToServer();
  });

  form.addEventListener('submit', function (e) { e.preventDefault(); });
  syncForm();
  syncFromServer();
})();
</script>
