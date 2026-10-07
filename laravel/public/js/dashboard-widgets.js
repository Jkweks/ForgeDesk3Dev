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
  // Each takes the widget's body element, its catalog entry, the JSON from its
  // data endpoint, and its runtime item (for per-widget state such as a chart).
  const renderers = {
    stat(body, def, data) {
      const raw = data ? data[def.field] : null;
      const value = typeof raw === 'number' ? raw.toLocaleString() : (raw ?? '-');
      body.innerHTML = `
        <div class="h1 mb-1">${esc(value)}</div>
        ${def.link ? `<a href="${esc(def.link)}" class="small text-secondary">View details</a>` : ''}`;
    },

    list(body, def, data) {
      const items = (data && data.items) || [];
      if (!items.length) {
        body.innerHTML = '<div class="text-secondary small">Nothing to show.</div>';
        return;
      }
      body.innerHTML = `<div class="list-group list-group-flush overflow-auto h-100">${items.map((i) => `
        <a href="${esc(i.link || '#')}" class="list-group-item list-group-item-action d-flex align-items-center px-0">
          <div class="flex-fill text-truncate">
            <div class="fw-medium text-truncate">${esc(i.label)}</div>
            ${i.sub ? `<div class="small text-secondary text-truncate">${esc(i.sub)}</div>` : ''}
          </div>
          ${i.meta ? `<span class="badge ${esc(i.meta_class || 'bg-secondary-lt')} ms-2">${esc(i.meta)}</span>` : ''}
        </a>`).join('')}</div>`;
    },

    table(body, def, data) {
      const cols = (data && data.columns) || [];
      const rows = (data && data.rows) || [];
      if (!rows.length) {
        body.innerHTML = '<div class="text-secondary small">Nothing to show.</div>';
        return;
      }
      const cell = (c) => (c && typeof c === 'object'
        ? `<span class="badge ${esc(c.class || 'bg-secondary-lt')}">${esc(c.text)}</span>`
        : esc(c));
      const align = (c) => (c.align === 'end' ? ' class="text-end"' : '');
      const more = data.total > rows.length
        ? `<div class="small text-secondary pt-2">Showing ${rows.length} of ${data.total}</div>` : '';
      body.innerHTML = `
        <div class="table-responsive h-100">
          <table class="table table-sm table-vcenter table-hover mb-0 dash-table">
            <thead><tr>${cols.map((c) => `<th${align(c)}>${esc(c.label)}</th>`).join('')}</tr></thead>
            <tbody>${rows.map((r) => `
              <tr data-href="${esc(r.link || '')}">${cols.map((c) => `<td${align(c)}>${cell(r.cells[c.key])}</td>`).join('')}</tr>`).join('')}
            </tbody>
          </table>${more}
        </div>`;
      body.querySelectorAll('tr[data-href]').forEach((tr) => {
        if (tr.dataset.href) tr.addEventListener('click', () => { if (!state.editing) window.location.href = tr.dataset.href; });
      });
    },

    chart(body, def, data, item) {
      const build = chartBuilders[def.chart];
      if (!build || typeof Chart === 'undefined') {
        body.innerHTML = '<div class="text-danger small">Chart unavailable.</div>';
        return;
      }
      const config = build(data);
      if (!config) {
        if (item.chart) { item.chart.destroy(); item.chart = null; }
        body.innerHTML = '<div class="text-secondary small">No data yet.</div>';
        return;
      }
      if (item.chart) item.chart.destroy();
      body.innerHTML = '<div class="dash-chart"><canvas></canvas></div>';
      Chart.defaults.color = getComputedStyle(document.body).color;
      Chart.defaults.borderColor = 'rgba(128,128,128,0.2)';
      item.chart = new Chart(body.querySelector('canvas'), config);
    },
  };

  // Tabler palette, matching fabrication/quality.blade.php.
  const BLUE = 'rgba(32,107,196,0.5)';
  const RED = '#d63939';
  const GREEN = '#2fb344';
  const baseOptions = { responsive: true, maintainAspectRatio: false };

  const chartBuilders = {
    incident_rate(data) {
      const rows = (data && data.data) || [];
      if (!rows.length) return null;
      const labels = rows.map((r) => r.month_label);
      return {
        type: 'bar',
        data: {
          labels,
          datasets: [
            { type: 'bar', label: 'Joints completed', data: rows.map((r) => r.joint_count), backgroundColor: BLUE, yAxisID: 'y', order: 2 },
            { type: 'line', label: 'Incident rate (%)', data: rows.map((r) => r.incident_rate), borderColor: RED, backgroundColor: RED, tension: 0.3, yAxisID: 'y1', order: 1 },
            { type: 'line', label: 'Goal (1.5%)', data: rows.map(() => 1.5), borderColor: GREEN, borderDash: [6, 4], pointRadius: 0, yAxisID: 'y1', order: 0 },
          ],
        },
        options: {
          ...baseOptions,
          scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } },
          },
        },
      };
    },

    problem_types(data) {
      const rows = (data && data.data) || [];
      if (!rows.length) return null;
      return {
        type: 'bar',
        data: { labels: rows.map((r) => r.problem_type), datasets: [{ label: 'Cases', data: rows.map((r) => r.count), backgroundColor: BLUE }] },
        options: { ...baseOptions, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
      };
    },

    weekly_trend(data) {
      const rows = (data && data.data) || [];
      if (!rows.length) return null;
      return {
        type: 'bar',
        data: {
          labels: rows.map((r) => r.week),
          datasets: [
            { type: 'bar', label: 'Cases', data: rows.map((r) => r.case_count), backgroundColor: BLUE },
            { type: 'line', label: 'Trend', data: rows.map((r) => r.trend_value), borderColor: RED, tension: 0, pointRadius: 0 },
          ],
        },
        options: { ...baseOptions, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
      };
    },

    stage_wip(data) {
      const rows = (data && data.data) || [];
      if (!rows.length) return null;
      return {
        type: 'bar',
        data: { labels: rows.map((r) => r.name), datasets: [{ label: 'Open stages', data: rows.map((r) => r.count), backgroundColor: BLUE }] },
        options: { ...baseOptions, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
      };
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
      (renderers[def.type] || renderers.stat)(bodyEl, def, data, item);
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

  function destroyItem(id) {
    const item = state.items.get(id);
    if (item && item.chart) item.chart.destroy();
    state.items.delete(id);
  }

  function removeWidget(id) {
    const node = state.grid.engine.nodes.find((n) => n.id === id);
    if (node) state.grid.removeWidget(node.el);
    destroyItem(id);
    updateEmptyState();
  }

  function renderLayout(layout) {
    state.grid.removeAll();
    [...state.items.keys()].forEach(destroyItem);
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
    // Read nodes directly: grid.save() omits values equal to GridStack's
    // defaults (x:0, y:0, ...), but the server requires all four.
    const nodes = state.grid.engine.nodes;
    return {
      widgets: nodes
        .filter((p) => state.items.has(p.id))
        .sort((a, b) => (a.y - b.y) || (a.x - b.x))
        .map((p) => ({
          id: p.id,
          key: state.items.get(p.id).def.key,
          x: p.x ?? 0, y: p.y ?? 0, w: p.w ?? 1, h: p.h ?? 1,
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

    // GridStack 12 renders `content` as plain text by default. Ours is markup
    // built by widgetHtml(), which escapes every interpolated registry value.
    GridStack.renderCB = (el, w) => { if (w && w.content) el.innerHTML = w.content; };

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
