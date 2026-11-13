{{-- Development > Cut-list rules: integrity rules for uploaded cut lists + sill quarter-point settings. Admin only (API enforces). --}}
<div id="crPane" style="display:none;">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Integrity rules</h3>
          <div class="card-actions btn-list">
            <label class="btn btn-sm mb-0" title="Run the enabled rules and the sill report on a CSV without importing it"><i class="ti ti-flask me-1"></i>Test a CSV<input type="file" id="crTestFile" accept=".csv,.txt" hidden></label>
            <button class="btn btn-sm btn-primary" id="crNew"><i class="ti ti-plus me-1"></i>New rule</button>
          </div>
        </div>
        <div class="card-body text-muted small border-bottom">
          Run on every cut-list CSV uploaded to CutFlow. <b>Error</b> rules stop the import; <b>warning</b> rules import it and list the problem. Rows are grouped into elevations (job / work order / door tag).
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table table-hover">
            <thead><tr><th style="width:60px">On</th><th>Rule</th><th>Kind</th><th>Severity</th><th></th></tr></thead>
            <tbody id="crRows"><tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="card" id="crTestCard" style="display:none;">
        <div class="card-header"><h3 class="card-title">Test result</h3></div>
        <div class="card-body" id="crTestOut"></div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Sill quarter-point settings</h3>
          <div class="card-actions"><button class="btn btn-sm btn-outline-secondary" id="crReset" title="Discard every override and go back to the config defaults">Reset</button></div>
        </div>
        <div class="card-body">
          <div id="crSettings"></div>
          <div id="crSetErr" class="alert alert-danger mt-2 mb-0" style="display:none;"></div>
          <button class="btn btn-primary mt-3 w-100" id="crSaveSettings">Save settings</button>
          <div class="text-muted small mt-2">Inches. The report is rebuilt on each download, so changes apply to existing uploads too. Product face widths (Inventory &gt; Configurator tab) still win over the fallback widths here.</div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h3 class="card-title">Built-in checks <span class="badge bg-secondary-lt ms-1">fixed</span></h3></div>
        <div class="card-body small">
          <div class="text-muted mb-2">The sill layout maths can't run without these, so they can't be switched off. An elevation that fails one is listed as "needs review".</div>
          <ul class="ps-3 mb-0" id="crBuiltIn"></ul>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal modal-blur fade" id="crModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="crModalTitle">Rule</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" id="rName" maxlength="255"></div>
          <div class="col-6 col-md-3"><label class="form-label">Kind</label>
            <select class="form-select" id="rKind">
              <option value="row">Row check</option><option value="companion">Companion row</option><option value="count">Count per elevation</option>
            </select></div>
          <div class="col-6 col-md-3"><label class="form-label">Severity</label>
            <select class="form-select" id="rSeverity"><option value="warning">Warning</option><option value="error">Error (blocks import)</option></select></div>
          <div class="col-12"><label class="form-label">Description</label><input class="form-control" id="rDesc" maxlength="2000" placeholder="Why this rule exists"></div>
          <div class="col-12"><div class="alert alert-info mb-0 py-2 small" id="rKindHelp"></div></div>
          <div class="col-12" id="rBody"></div>
          <div class="col-12"><label class="form-label">Custom message <span class="text-muted">(optional)</span></label>
            <input class="form-control" id="rMessage" maxlength="500" placeholder="e.g. Sill {part_number} in bay {bay} must be cut under 144&quot;">
            <div class="form-hint">Placeholders: {part_number} {use} {length} {qty} {bay} {column} {finish} {elevation} {work_order} {project} {tags}; count rules also {actual} {value}.</div></div>
          <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" id="rEnabled" checked><span class="form-check-label">Enabled</span></label></div>
        </div>
        <div id="rError" class="alert alert-danger mt-3 mb-0" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger me-auto" id="rDelete" style="display:none"><i class="ti ti-trash me-1"></i>Delete</button>
        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="rSave">Save rule</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const $ = (id) => document.getElementById(id);
  const esc = (s) => escapeHtml(String(s ?? ''));
  let meta = null, rules = [], settings = null, editing = null, def = {}, modal = null, loaded = false;

  const KIND_HELP = {
    row: 'Every row that matches <b>Applies to</b> must also satisfy <b>Must be true</b>. Example: applies to Use = SILL, must be true: Length &le; 144.',
    companion: 'For every row matching <b>Applies to</b>, a <b>partner</b> row must exist (or must not exist) in the same elevation. Tick "same bay" to look only in the row\'s own bay. Example: each SILL needs a FEMALE VERTICAL in the same bay.',
    count: 'In each elevation, measure the rows matching <b>Count these rows</b> and compare it to a number. Optionally limit to elevations that contain a row matching <b>Only elevations that contain</b>. Example: elevations containing a SILL must have exactly 1 LEFT JAMB row.'
  };
  const emptyGroup = () => ({ logic: 'all', items: [] });
  const newCond = () => ({ field: 'use', op: 'eq', value: '' });
  const NOVALUE = ['empty', 'not_empty'];

  // ------------------------------------------------------------ rule list
  async function load() {
    const res = await apiCall('/cutlist-rules');
    if (!res.ok) { $('crRows').innerHTML = '<tr><td colspan="5" class="text-danger text-center py-4">Failed to load (HTTP ' + res.status + ')</td></tr>'; return; }
    const b = await res.json();
    meta = b.meta; rules = b.rules; settings = b.settings;
    $('crBuiltIn').innerHTML = b.built_in.map((x) => '<li class="mb-1">' + esc(x.text) + '</li>').join('');
    renderRules(); renderSettings();
  }

  function renderRules() {
    if (!rules.length) { $('crRows').innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No custom rules yet. Click "New rule".</td></tr>'; return; }
    const K = { row: 'Row check', companion: 'Companion row', count: 'Count per elevation' };
    $('crRows').innerHTML = rules.map((r) => '<tr data-id="' + r.id + '" role="button">'
      + '<td><span class="badge ' + (r.enabled ? 'bg-green-lt' : 'bg-secondary-lt') + '">' + (r.enabled ? 'On' : 'Off') + '</span></td>'
      + '<td><div class="fw-medium">' + esc(r.name) + '</div><div class="text-muted small">' + esc(r.description || '') + '</div></td>'
      + '<td class="text-muted">' + K[r.kind] + '</td>'
      + '<td><span class="badge ' + (r.severity === 'error' ? 'bg-red-lt' : 'bg-yellow-lt') + '">' + r.severity + '</span></td>'
      + '<td class="text-end"><i class="ti ti-pencil text-muted"></i></td></tr>').join('');
  }

  $('crRows').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-id]'); if (!tr) return;
    openEditor(rules.find((r) => r.id == tr.dataset.id));
  });
  $('crNew').addEventListener('click', () => openEditor(null));

  // ------------------------------------------------------------ editor
  const defaultsFor = (kind) => ({
    row: { filter: emptyGroup(), require: emptyGroup() },
    companion: { filter: emptyGroup(), partner: emptyGroup(), mode: 'require', same_bay: false },
    count: { when: emptyGroup(), filter: emptyGroup(), metric: 'count', field: 'length', op: 'eq', value: 1 },
  }[kind]);

  function openEditor(rule) {
    editing = rule;
    $('crModalTitle').textContent = rule ? 'Edit rule' : 'New rule';
    $('rName').value = rule?.name || ''; $('rDesc').value = rule?.description || '';
    $('rKind').value = rule?.kind || 'row'; $('rKind').disabled = !!rule;
    $('rSeverity').value = rule?.severity || 'warning'; $('rMessage').value = rule?.message || '';
    $('rEnabled').checked = rule ? rule.enabled : true;
    def = rule ? JSON.parse(JSON.stringify(rule.definition)) : defaultsFor($('rKind').value);
    $('rDelete').style.display = rule ? '' : 'none'; $('rError').style.display = 'none';
    renderBody();
    modal = modal || new bootstrap.Modal($('crModal')); modal.show();
  }
  $('rKind').addEventListener('change', () => { def = defaultsFor($('rKind').value); renderBody(); });

  const opts = (map, sel) => Object.entries(map).map(([k, v]) => '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + esc(typeof v === 'object' ? v.label : v) + '</option>').join('');

  function groupHtml(path, title, group) {
    const g = group || emptyGroup();
    return '<div class="card card-sm mb-3"><div class="card-body">'
      + '<div class="d-flex align-items-center mb-2"><div class="fw-medium me-3">' + title + '</div>'
      + '<select class="form-select form-select-sm w-auto" data-logic="' + path + '"><option value="all"' + (g.logic === 'all' ? ' selected' : '') + '>all of these</option><option value="any"' + (g.logic === 'any' ? ' selected' : '') + '>any of these</option></select></div>'
      + (g.items.length ? '' : '<div class="text-muted small mb-2">No conditions — matches every row.</div>')
      + g.items.map((c, i) => '<div class="row g-2 mb-2 align-items-center" data-row="' + path + ':' + i + '">'
        + '<div class="col-4"><select class="form-select form-select-sm" data-f="field">' + opts(meta.fields, c.field) + '</select></div>'
        + '<div class="col-3"><select class="form-select form-select-sm" data-f="op">' + opts(meta.ops, c.op) + '</select></div>'
        + '<div class="col"><input class="form-control form-control-sm" data-f="value" value="' + esc(c.value) + '"' + (NOVALUE.includes(c.op) ? ' disabled' : '') + '></div>'
        + '<div class="col-auto"><button class="btn btn-sm btn-icon btn-ghost-danger" data-del="' + path + ':' + i + '" title="Remove"><i class="ti ti-x"></i></button></div></div>').join('')
      + '<button class="btn btn-sm btn-outline-primary" data-add="' + path + '"><i class="ti ti-plus me-1"></i>Add condition</button>'
      + '</div></div>';
  }

  function renderBody() {
    const kind = $('rKind').value;
    $('rKindHelp').innerHTML = KIND_HELP[kind];
    let h = '';
    if (kind === 'row') {
      h += groupHtml('filter', 'Applies to rows where', def.filter) + groupHtml('require', 'Must be true', def.require);
    } else if (kind === 'companion') {
      h += groupHtml('filter', 'Applies to rows where', def.filter)
        + '<div class="row g-2 mb-3 align-items-center"><div class="col-auto"><select class="form-select form-select-sm" data-d="mode"><option value="require"' + (def.mode === 'require' ? ' selected' : '') + '>there must be a partner row</option><option value="forbid"' + (def.mode === 'forbid' ? ' selected' : '') + '>there must NOT be a partner row</option></select></div>'
        + '<div class="col-auto"><label class="form-check mb-0"><input class="form-check-input" type="checkbox" data-d="same_bay"' + (def.same_bay ? ' checked' : '') + '><span class="form-check-label">in the same bay</span></label></div></div>'
        + groupHtml('partner', 'Partner row is one where', def.partner);
    } else {
      const needsField = ['sum', 'min', 'max', 'distinct'].includes(def.metric);
      h += groupHtml('when', 'Only elevations that contain a row where', def.when) + groupHtml('filter', 'Count these rows', def.filter)
        + '<div class="row g-2 mb-1 align-items-center"><div class="col-auto fw-medium">Expect</div>'
        + '<div class="col-auto"><select class="form-select form-select-sm" data-d="metric">' + opts(meta.metrics, def.metric) + '</select></div>'
        + (needsField ? '<div class="col-auto"><select class="form-select form-select-sm" data-d="field">' + opts(meta.fields, def.field) + '</select></div>' : '')
        + '<div class="col-auto"><select class="form-select form-select-sm" data-d="op">' + opts({ eq: '=', neq: '≠', gt: '>', gte: '≥', lt: '<', lte: '≤' }, def.op) + '</select></div>'
        + '<div class="col-2"><input type="number" step="any" class="form-control form-control-sm" data-d="value" value="' + esc(def.value) + '"></div></div>';
    }
    $('rBody').innerHTML = h;
  }

  $('rBody').addEventListener('input', sync);
  $('rBody').addEventListener('change', (e) => { sync(e); if (e.target.dataset.f === 'op' || ['metric'].includes(e.target.dataset.d) || e.target.dataset.logic) renderBody(); });
  $('rBody').addEventListener('click', (e) => {
    const add = e.target.closest('[data-add]'), del = e.target.closest('[data-del]');
    if (add) { (def[add.dataset.add] ||= emptyGroup()).items.push(newCond()); renderBody(); }
    if (del) { const [p, i] = del.dataset.del.split(':'); def[p].items.splice(+i, 1); renderBody(); }
  });
  function sync(e) {
    const t = e.target, row = t.closest('[data-row]');
    if (row && t.dataset.f) { const [p, i] = row.dataset.row.split(':'); def[p].items[+i][t.dataset.f] = t.value; }
    else if (t.dataset.logic) def[t.dataset.logic].logic = t.value;
    else if (t.dataset.d) def[t.dataset.d] = t.type === 'checkbox' ? t.checked : t.value;
  }

  $('rSave').addEventListener('click', async () => {
    const body = { name: $('rName').value.trim(), description: $('rDesc').value || null, kind: $('rKind').value, severity: $('rSeverity').value,
      enabled: $('rEnabled').checked, message: $('rMessage').value || null, definition: def };
    const res = await apiCall(editing ? '/cutlist-rules/' + editing.id : '/cutlist-rules', { method: editing ? 'PUT' : 'POST', body: JSON.stringify(body) });
    if (!res.ok) { $('rError').innerHTML = await errText(res); $('rError').style.display = 'block'; return; }
    modal.hide(); load();
  });
  $('rDelete').addEventListener('click', async () => {
    if (!confirm('Delete rule "' + editing.name + '"?')) return;
    const res = await apiCall('/cutlist-rules/' + editing.id, { method: 'DELETE' });
    if (res.ok) { modal.hide(); load(); } else { $('rError').textContent = 'Delete failed (HTTP ' + res.status + ')'; $('rError').style.display = 'block'; }
  });

  async function errText(res) {
    const j = await res.json().catch(() => ({}));
    const lines = j.errors ? Object.values(j.errors).flat() : [j.message || 'HTTP ' + res.status];
    return lines.map(esc).join('<br>');
  }

  // ------------------------------------------------------------ sill settings
  const SCALAR_LABELS = {
    left_gap: 'Left gap (before left jamb)', right_gap: 'Right gap (beyond right jamb)', vertical_width: 'Female vertical width (fallback)',
    default_jamb_width: 'Jamb width (fallback)', comp_jamb_width: 'Comp jamb width', receptor_gap: 'Receptor piece gap',
    flag_tolerance: 'Receptor flag tolerance', knee_wall_tolerance: 'Knee-wall height tolerance', fraction_denominator: 'Fraction rounding (1/N)'
  };
  const ROLE_LABELS = { sill: 'Sill', female_vertical: 'Female vertical', left_jamb: 'Left jamb', right_jamb: 'Right jamb', threshold: 'Door threshold', receptor: 'Sill receptor channel' };

  function renderSettings() {
    const e = settings.effective, over = settings.overridden;
    const mark = (k) => over.includes(k) ? ' <span class="badge bg-blue-lt">changed</span>' : '';
    let h = Object.entries(SCALAR_LABELS).map(([k, l]) => '<div class="mb-2"><label class="form-label mb-1">' + l + mark(k) + '</label><input type="number" step="any" class="form-control form-control-sm" data-s="' + k + '" value="' + esc(e[k]) + '"></div>').join('');
    h += '<hr><div class="fw-medium mb-2">Row names (the cut list "use")' + mark('roles') + '</div>'
      + Object.entries(ROLE_LABELS).map(([k, l]) => '<div class="mb-2"><label class="form-label mb-1">' + l + '</label><input class="form-control form-control-sm" data-role="' + k + '" value="' + esc(e.roles[k]) + '"></div>').join('');
    h += '<div class="mb-2"><label class="form-label mb-1">Door jamb tags' + mark('door_tags') + '</label><input class="form-control form-control-sm" id="sDoorTags" value="' + esc((e.door_tags || []).join(', ')) + '"><div class="form-hint">Comma list, braces included, e.g. {SIDELITE JAMB}</div></div>';
    h += '<hr><div class="fw-medium mb-2">Fallback part widths' + mark('part_widths') + '</div><div id="sWidths"></div><button class="btn btn-sm btn-outline-primary" id="sAddWidth"><i class="ti ti-plus me-1"></i>Add part</button>';
    $('crSettings').innerHTML = h;
    Object.entries(e.part_widths || {}).forEach(([pn, w]) => addWidthRow(pn, w));
    $('sAddWidth').addEventListener('click', () => addWidthRow('', ''));
  }
  function addWidthRow(pn, w) {
    const d = document.createElement('div'); d.className = 'row g-2 mb-1 sw-row';
    d.innerHTML = '<div class="col-6"><input class="form-control form-control-sm" data-pn placeholder="Part number" value="' + esc(pn) + '"></div><div class="col-4"><input type="number" step="any" class="form-control form-control-sm" data-w placeholder="Width" value="' + esc(w) + '"></div><div class="col-2"><button class="btn btn-sm btn-icon btn-ghost-danger" type="button"><i class="ti ti-x"></i></button></div>';
    d.querySelector('button').addEventListener('click', () => d.remove());
    $('sWidths').appendChild(d);
  }

  $('crSaveSettings').addEventListener('click', async () => {
    const body = { roles: {}, part_widths: {} };
    document.querySelectorAll('[data-s]').forEach((i) => { body[i.dataset.s] = i.value === '' ? null : i.value; });
    document.querySelectorAll('[data-role]').forEach((i) => { if (i.value.trim()) body.roles[i.dataset.role] = i.value.trim(); });
    body.door_tags = $('sDoorTags').value.split(',').map((s) => s.trim()).filter(Boolean);
    document.querySelectorAll('.sw-row').forEach((r) => { const pn = r.querySelector('[data-pn]').value.trim(), w = r.querySelector('[data-w]').value; if (pn && w !== '') body.part_widths[pn] = w; });
    const res = await apiCall('/cutlist-rules/settings', { method: 'PUT', body: JSON.stringify(body) });
    if (!res.ok) { $('crSetErr').innerHTML = await errText(res); $('crSetErr').style.display = 'block'; return; }
    $('crSetErr').style.display = 'none';
    const b = await res.json(); settings = b.settings; renderSettings(); showNotification('Settings saved', 'success');
  });
  $('crReset').addEventListener('click', async () => {
    if (!confirm('Discard all sill quarter-point overrides and go back to the config defaults?')) return;
    const res = await apiCall('/cutlist-rules/settings', { method: 'DELETE' });
    if (res.ok) { settings = (await res.json()).settings; renderSettings(); showNotification('Reset to defaults', 'success'); }
  });

  // ------------------------------------------------------------ dry run
  $('crTestFile').addEventListener('change', async (e) => {
    const f = e.target.files[0]; if (!f) return;
    const fd = new FormData(); fd.append('csv', f);
    const res = await fetch(API_BASE + '/cutlist-rules/test', { method: 'POST', body: fd, credentials: 'include', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getXsrfToken() } });
    e.target.value = '';
    $('crTestCard').style.display = 'block';
    if (!res.ok) { $('crTestOut').innerHTML = '<div class="text-danger">' + await errText(res) + '</div>'; return; }
    const b = await res.json();
    const list = (items, cls) => items.length ? '<ul class="mb-3">' + items.map((x) => '<li class="' + cls + '">' + x + '</li>').join('') + '</ul>' : '<div class="text-muted mb-3">None.</div>';
    $('crTestOut').innerHTML = '<div class="text-muted mb-2">' + esc(f.name) + ' — ' + b.rows + ' rows (nothing was imported)</div>'
      + '<div class="fw-medium">Rule findings</div>' + list(b.findings.map((x) => '<b>' + esc(x.severity.toUpperCase()) + '</b> ' + esc(x.scope ? x.scope + ': ' : '') + esc(x.message)), '')
      + '<div class="fw-medium">Sill report (' + b.sill.elevations + ' elevations)</div>'
      + list(b.sill.needs_review.map((x) => esc(x.label) + ': ' + esc(x.reason)), 'text-danger')
      + list(b.sill.receptor_flags.map((x) => esc(x.elevation) + ' ' + esc(x.run) + ': receptor ' + esc(x.receptor) + '" vs layout ' + esc(x.layout) + '" (' + esc(x.diff) + ')'), 'text-warning')
      + (b.sill.assumptions.length ? '<div class="fw-medium">Assumptions</div>' + list(b.sill.assumptions.map(esc), 'text-muted') : '');
  });

  window.cutlistRulesShow = () => { if (!loaded) { loaded = true; load(); } };
})();
</script>
