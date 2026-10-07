/**
 * Customizable dashboard: renders the signed-in user's widget layout on a
 * GridStack grid and handles edit mode (drag / resize / add / remove / save /
 * reset / admin "save as default").
 *
 * Server side: App\Dashboard\WidgetRegistry (catalog + permissions) and
 * Api\DashboardLayoutController (layout storage). Depends on the globals from
 * partials/auth-scripts: apiCall, hasPermission, window.sessionReady.
 */
(function () {
  'use strict';

  const REFRESH_TICK_MS = 30000;
  const COLUMNS = 12;
  const CELL_HEIGHT = 70;

  const esc = (value) => {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  };

  // ---- Widget renderers, keyed by registry `type` -------------------------
  // Each takes the widget's body element, its catalog entry, and the JSON from
  // its data endpoint.
  const renderers = {
    stat(body, def, data) {
      const raw = data ? data[def.field] : null;
      const value = typeof raw === 'number' ? raw.toLocaleString() : (raw ?? '-');
      body.innerHTML = `
        <div class="h1 mb-1">${esc(value)}</div>
        ${def.link ? `<a href="${esc(def.link)}" class="small text-secondary">View details</a>` : ''}`;
    },
  };

  const state = {
    grid: null,
    catalog: {},          // key -> catalog entry
    items: new Map(),     // widget instance id -> {def, settings, loadedAt, bodyEl}
    savedLayout: null,    // last layout from the server (for Cancel)
    source: 'built-in',
    editing: false,
  };

  const $ = (id) => document.getElementById(id);

  // ---- Data loading -------------------------------------------------------
  const inflight = new Map(); // endpoint -> Promise<json>, shared by widgets in one round

  function fetchEndpoint(endpoint) {
    if (!inflight.has(endpoint)) {
      const p = apiCall(endpoint)
        .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`))))
        .finally(() => setTimeout(() => inflight.delete(endpoint), 0));
      inflight.set(endpoint, p);
    }
    return inflight.get(endpoint);
  }

  async function loadWidget(id) {
    const item = state.items.get(id);
    if (!item) return;
    const { def, bodyEl } = item;
    try {
      const data = await fetchEndpoint(def.endpoint);
      (renderers[def.type] || renderers.stat)(bodyEl, def, data);
      item.loadedAt = Date.now();
    } catch (e) {
      bodyEl.innerHTML = '<div class="text-danger small">Could not load this widget.</div>';
    }
  }

  function refreshDue(force) {
    if (document.hidden && !force) return;
    state.items.forEach((item, id) => {
      const age = (Date.now() - (item.loadedAt || 0)) / 1000;
      if (force || age >= (item.def.refresh_seconds || 120)) loadWidget(id);
    });
  }

  // ---- Grid ---------------------------------------------------------------
  function widgetHtml(def) {
    return `
      <div class="card h-100 dash-widget">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-start mb-2">
            <div class="subheader flex-fill text-truncate"><i class="ti ${esc(def.icon)} me-1"></i>${esc(def.title)}</div>
            <button type="button" class="btn-close dash-remove" aria-label="Remove widget"></button>
          </div>
          <div class="dash-widget-body flex-fill"><div class="text-secondary small">Loading…</div></div>
        </div>
      </div>`;
  }

  function addToGrid(entry) {
    const def = state.catalog[entry.key];
    if (!def) return;
    const el = state.grid.addWidget({
      id: entry.id,
      x: entry.x, y: entry.y, w: entry.w, h: entry.h,
      minW: def.min_size ? def.min_size.w : 1,
      minH: def.min_size ? def.min_size.h : 1,
      content: widgetHtml(def),
    });
    const bodyEl = el.querySelector('.dash-widget-body');
    el.querySelector('.dash-remove').addEventListener('click', () => removeWidget(entry.id));
    state.items.set(entry.id, { def, settings: entry.settings || {}, loadedAt: 0, bodyEl });
    loadWidget(entry.id);
  }

  function removeWidget(id) {
    const node = state.grid.engine.nodes.find((n) => n.id === id);
    if (node) state.grid.removeWidget(node.el);
    state.items.delete(id);
    updateEmptyState();
  }

  function renderLayout(layout) {
    state.grid.removeAll();
    state.items.clear();
    // Batch so GridStack doesn't re-flow on every add.
    state.grid.batchUpdate();
    (layout.widgets || []).forEach(addToGrid);
    state.grid.commit();
    updateEmptyState();
  }

  function updateEmptyState() {
    $('dashboardEmpty').classList.toggle('d-none', state.items.size > 0);
  }

  function currentLayout() {
    const positions = state.grid.save(false); // [{id,x,y,w,h}]
    return {
      widgets: positions
        .filter((p) => state.items.has(p.id))
        .map((p) => ({
          id: p.id,
          key: state.items.get(p.id).def.key,
          x: p.x, y: p.y, w: p.w, h: p.h,
          settings: state.items.get(p.id).settings || {},
        })),
    };
  }

  // ---- Edit mode ----------------------------------------------------------
  function setEditing(on) {
    state.editing = on;
    state.grid.setStatic(!on);
    $('dashboardGrid').classList.toggle('dash-editing', on);
    $('dashViewButtons').classList.toggle('d-none', on);
    $('dashEditButtons').classList.toggle('d-none', !on);
    $('dashSaveDefault').classList.toggle('d-none', !hasPermission('settings.edit'));
  }

  async function sendLayout(url, method, body) {
    const res = await apiCall(url, { method, body: body ? JSON.stringify(body) : undefined });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || `Request failed (${res.status})`);
    }
    return res.json();
  }

  function notify(message, ok = true) {
    if (typeof fabToast === 'function') fabToast(message, ok ? 'success' : 'error');
    else if (!ok) alert(message);
  }

  async function save() {
    try {
      const result = await sendLayout('/dashboard/layout', 'PUT', currentLayout());
      state.savedLayout = result.layout;
      state.source = result.source;
      setEditing(false);
      notify('Dashboard saved');
    } catch (e) { notify(e.message, false); }
  }

  function cancel() {
    renderLayout(state.savedLayout);
    setEditing(false);
  }

  async function reset() {
    if (!confirm('Reset your dashboard to the default layout?')) return;
    try {
      const result = await sendLayout('/dashboard/layout', 'DELETE');
      state.savedLayout = result.layout;
      state.source = result.source;
      renderLayout(result.layout);
      setEditing(false);
      notify('Dashboard reset');
    } catch (e) { notify(e.message, false); }
  }

  async function saveAsDefault() {
    if (!confirm('Make this layout the default for everyone who has not customized their own dashboard?')) return;
    try {
      await sendLayout('/dashboard/default-layout', 'PUT', currentLayout());
      notify('Saved as the default layout');
    } catch (e) { notify(e.message, false); }
  }

  // ---- Add-widget panel ---------------------------------------------------
  function uniqueId(key) {
    let id = key;
    for (let n = 2; state.items.has(id); n++) id = `${key}-${n}`;
    return id;
  }

  function addWidgetFromCatalog(key) {
    const def = state.catalog[key];
    addToGrid({ id: uniqueId(key), key, x: 0, y: 0, w: def.default_size.w, h: def.default_size.h, settings: {} });
    updateEmptyState();
  }

  function renderCatalog() {
    const term = ($('dashCatalogSearch').value || '').toLowerCase();
    const groups = {};
    Object.values(state.catalog)
      .filter((d) => !term || `${d.title} ${d.description} ${d.category}`.toLowerCase().includes(term))
      .forEach((d) => { (groups[d.category] = groups[d.category] || []).push(d); });

    const html = Object.keys(groups).sort().map((cat) => `
      <div class="subheader mt-3 mb-2">${esc(cat)}</div>
      ${groups[cat].map((d) => `
        <div class="d-flex align-items-center border rounded p-2 mb-2">
          <i class="ti ${esc(d.icon)} fs-2 me-3 text-secondary"></i>
          <div class="flex-fill">
            <div class="fw-medium">${esc(d.title)}</div>
            <div class="small text-secondary">${esc(d.description)}</div>
          </div>
          <button type="button" class="btn btn-sm btn-primary ms-2" data-add-widget="${esc(d.key)}">Add</button>
        </div>`).join('')}`).join('');
    $('dashCatalogList').innerHTML = html || '<div class="text-secondary">No matching widgets.</div>';
  }

  // ---- Boot ---------------------------------------------------------------
  async function init() {
    const [widgetsRes, layoutRes] = await Promise.all([apiCall('/dashboard/widgets'), apiCall('/dashboard/layout')]);
    if (!widgetsRes.ok || !layoutRes.ok) {
      $('dashboardEmpty').classList.remove('d-none');
      return;
    }
    (await widgetsRes.json()).widgets.forEach((d) => { state.catalog[d.key] = d; });
    const { layout, source } = await layoutRes.json();
    state.savedLayout = layout;
    state.source = source;

    state.grid = GridStack.init({
      column: COLUMNS,
      cellHeight: CELL_HEIGHT,
      margin: 8,
      float: false,
      staticGrid: true,
      animate: true,
      columnOpts: { breakpoints: [{ w: 768, c: 1 }] },
    }, $('dashboardGrid'));

    renderLayout(layout);

    $('dashCustomize').addEventListener('click', () => setEditing(true));
    $('dashSave').addEventListener('click', save);
    $('dashCancel').addEventListener('click', cancel);
    $('dashReset').addEventListener('click', reset);
    $('dashSaveDefault').addEventListener('click', saveAsDefault);
    $('dashCatalogSearch').addEventListener('input', renderCatalog);
    $('dashCatalogList').addEventListener('click', (e) => {
      const btn = e.target.closest('[data-add-widget]');
      if (btn) addWidgetFromCatalog(btn.dataset.addWidget);
    });
    $('dashAddWidget').addEventListener('click', () => {
      renderCatalog();
      bootstrap.Offcanvas.getOrCreateInstance($('dashCatalogPanel')).show();
    });

    setInterval(() => refreshDue(false), REFRESH_TICK_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshDue(false); });
  }

  window.sessionReady.then(() => {
    if (typeof currentUser === 'undefined' || !currentUser) return;
    init();
  });
})();
