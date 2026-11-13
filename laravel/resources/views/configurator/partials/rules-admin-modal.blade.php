{{-- Configurator Admin > Rules: rule editor modal + the tab's script. Uses the page's globals
     (esc, authenticatedFetch, showModal/hideModal, hwCategories, hwItems, hwVariables, hwFunctions, hwBackers). --}}
<div class="modal modal-blur fade" id="cr-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <form id="cr-form">
        <div class="modal-header"><h5 class="modal-title">Rule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="cr-id">
          <div class="row">
            <div class="col-md-7 mb-3"><label class="form-label">Name</label><input type="text" class="form-control" id="cr-name" required maxlength="255" placeholder="e.g. HES 9400 strike backer"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Order</label><input type="number" class="form-control" id="cr-sort" min="0"></div>
            <div class="col-md-3 mb-3 d-flex align-items-end"><label class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="cr-enabled" checked><span class="form-check-label">Enabled</span></label></div>
          </div>
          <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" id="cr-notes" rows="2" maxlength="2000" placeholder="Why this rule exists"></textarea></div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
              <span class="fw-bold">When</span>
              <select class="form-select form-select-sm w-auto" id="cr-logic">
                <option value="all">all of these are true</option>
                <option value="any">any of these is true</option>
              </select>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="crAddCondition()"><i class="ti ti-plus"></i> Condition</button>
          </div>
          <div id="cr-conditions"></div>
          <div class="text-muted small mb-3" id="cr-conditions-empty">No conditions &mdash; the rule applies to every opening.</div>

          <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
            <span class="fw-bold">Then</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="crAddAction()"><i class="ti ti-plus"></i> Action</button>
          </div>
          <div id="cr-actions"></div>

          <details class="mt-3">
            <summary class="text-muted" style="cursor:pointer">What can I type in a formula?</summary>
            <div class="small mt-2">
              <p class="mb-2">Numbers, <code>+ - * /</code>, parentheses, and comparisons (<code>&gt; &lt; &gt;= &lt;= == !=</code>). A fraction is a division: <code>1/2</code>. Functions: <code>min(a, b)</code>, <code>max(a, b)</code>, <code>abs(x)</code>, <code>round(x, 1/16)</code>, <code>if(condition, then, else)</code>.</p>
              <div class="row">
                <div class="col-md-6">
                  <div class="fw-bold mb-1">Opening</div>
                  <div id="cr-ref-builtins"></div>
                  <div class="fw-bold mb-1 mt-2">Parts</div>
                  <div><code>P2451.length</code> or <code>length('P2451')</code> &mdash; a backer's length (Hardware Library &rarr; Backers)</div>
                  <div><code>section('Lock Jamb Stop')</code> &mdash; a frame part's section height</div>
                  <div class="fw-bold mb-1 mt-2">Inside a part action</div>
                  <div><code>ORIGINAL</code> &mdash; the matched part's length; <code>ORIGINAL_QTY</code> &mdash; its quantity</div>
                  <div><code>PIECE1</code>, <code>PIECE2</code>&hellip; &mdash; earlier pieces of a split; <code>REMAINDER</code> &mdash; what is left of the original</div>
                </div>
                <div class="col-md-6">
                  <div class="fw-bold mb-1">Hardware prep values <span class="text-muted fw-normal">(the value on this opening)</span></div>
                  <div id="cr-ref-variables" style="max-height:220px;overflow:auto"></div>
                </div>
              </div>
              <p class="text-muted mt-2 mb-0">If a value a formula needs is missing on an opening (say, no panic is linked so there is no panic centerline), that action is skipped with a warning rather than cutting from a guess.</p>
            </div>
          </details>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save rule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<datalist id="cr-dl-pns"></datalist>
<datalist id="cr-dl-roles"></datalist>

<script>
  let crRules = [];
  let crMeta = { scopes: {}, actions: {}, fields: {}, built_ins: {}, field_options: {}, frame_roles: [] };
  let crConfigs = [];

  const crSel = (options, selected, cls = '') => `<select class="form-select form-select-sm ${cls}">${
    options.map(([v, l]) => `<option value="${esc(v)}" ${String(v) === String(selected ?? '') ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`;
  const crInput = (cls, value, placeholder = '', extra = '') =>
    `<input type="text" class="form-control form-control-sm ${cls}" value="${esc(value ?? '')}" placeholder="${esc(placeholder)}" ${extra}>`;
  const crVal = (row, cls) => row.querySelector('.' + cls)?.value ?? '';

  async function crLoad() {
    try {
      const data = await authenticatedFetch('/config/rules');
      crRules = data.rules || [];
      crMeta = data.meta || crMeta;
      crRender();
    } catch (err) {
      document.getElementById('cr-tbody').innerHTML = `<tr><td colspan="6" class="text-danger">${esc(err.message)}</td></tr>`;
    }
  }

  // ---- plain-language summaries for the list -------------------------------------------------
  function crDescribeCondition(c) {
    if (c.items) return '(' + c.items.map(crDescribeCondition).join(c.logic === 'any' ? ' or ' : ' and ') + ')';
    const not = c.present === false ? 'no ' : '';
    if (c.type === 'hardware') {
      const what = c.by === 'item' ? (hwItems.find(i => i.id == c.value)?.name || `item #${c.value}`) : c.by === 'category' ? `any ${c.value}` : c.value;
      return `${not}${what} on the opening${c.leaf ? ` (${c.leaf} leaf)` : ''}`;
    }
    if (c.type === 'function') return `function ${c.value} ${c.present === false ? 'not ' : ''}selected`;
    if (c.type === 'field') return `${crMeta.fields[c.field] || c.field} ${{ eq: 'is', neq: 'is not', in: 'is one of' }[c.op || 'eq']} ${c.value}`;
    if (c.type === 'compare') return `${c.left} ${{ eq: '=', neq: '≠', gt: '>', gte: '≥', lt: '<', lte: '≤' }[c.op] || c.op} ${c.right}`;
    return '?';
  }
  function crDescribeMatch(m) { return `${m?.by === 'pn' ? 'PN ' : ''}"${m?.value || ''}"${m?.op === 'contains' ? ' (contains)' : ''}`; }
  function crDescribeAction(a) {
    const scope = a.scope ? ` <span class="text-muted">(${esc(a.scope)})</span>` : '';
    switch (a.type) {
      case 'add_part': return `Add ${esc(a.qty || '1')} × ${esc(a.pn)}${a.length ? ` @ ${esc(a.length)}` : ''}${scope}`;
      case 'remove_part': return `Remove ${esc(crDescribeMatch(a.match))}${scope}`;
      case 'swap_part': return `Swap ${esc(crDescribeMatch(a.match))} for ${esc(a.pn)}${scope}`;
      case 'split_part': return `Split ${esc(crDescribeMatch(a.match))} into ${(a.pieces || []).map(p => `${esc(p.label || 'piece')} = <code>${esc(p.length)}</code>`).join(', ')}${scope}`;
      case 'adjust_part': return `Set ${esc(crDescribeMatch(a.match))} ${[a.length ? `length = <code>${esc(a.length)}</code>` : '', a.qty ? `qty = <code>${esc(a.qty)}</code>` : ''].filter(Boolean).join(', ')}${scope}`;
      case 'set_variable': return `Set ${esc(a.code)} = <code>${esc(a.value)}</code>`;
      case 'message': return `${a.severity === 'error' ? '<span class="badge bg-red-lt">blocks release</span>' : '<span class="badge bg-yellow-lt">warning</span>'} ${esc(a.text)}`;
    }
    return esc(a.type);
  }

  function crRender() {
    const tbody = document.getElementById('cr-tbody');
    tbody.innerHTML = crRules.map(r => {
      const items = r.conditions?.items || [];
      const when = items.length ? items.map(c => esc(crDescribeCondition(c))).join(r.conditions.logic === 'any' ? ' <span class="text-muted">or</span> ' : ' <span class="text-muted">and</span> ') : '<span class="text-muted">always</span>';
      return `<tr>
        <td class="text-muted">${r.sort_order}</td>
        <td><div class="fw-bold">${esc(r.name)}</div>${r.notes ? `<div class="text-muted small">${esc(r.notes)}</div>` : ''}</td>
        <td class="small">${when}</td>
        <td class="small">${(r.actions || []).map(a => `<div>${crDescribeAction(a)}</div>`).join('')}</td>
        <td>${r.enabled ? '<span class="badge bg-green-lt">enabled</span>' : '<span class="badge bg-secondary-lt">off</span>'}</td>
        <td class="text-end text-nowrap">
          <button type="button" class="btn btn-sm btn-icon" onclick="crOpenModal(${r.id})" data-permission="configurator.catalog.manage" title="Edit"><i class="ti ti-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-icon" onclick="crOpenModal(${r.id}, true)" data-permission="configurator.catalog.manage" title="Duplicate"><i class="ti ti-copy"></i></button>
          <button type="button" class="btn btn-sm btn-icon text-danger" onclick="crDelete(${r.id})" data-permission="configurator.catalog.manage" title="Delete"><i class="ti ti-trash"></i></button>
        </td>
      </tr>`;
    }).join('') || '<tr><td colspan="6" class="text-muted">No rules yet. A rule describes an exception to the standard BOM &mdash; for example a strike that needs a backer and a split door stop.</td></tr>';
    applyActionPermissions();
  }

  // ---- condition rows ------------------------------------------------------------------------
  function crConditionFields(c) {
    const yesNo = [['1', 'is'], ['0', 'is not']];
    switch (c.type) {
      case 'hardware': {
        const by = c.by || 'pn';
        const value = by === 'category'
          ? crSel(hwCategories.map(x => [x.name, x.name]), c.value, 'cr-c-value')
          : by === 'item'
            ? crSel(hwItems.slice().sort((a, b) => a.name.localeCompare(b.name)).map(x => [x.id, `${x.name}${x.pn ? ' (' + x.pn + ')' : ''}`]), c.value, 'cr-c-value')
            : crInput('cr-c-value', c.value, 'e.g. P2454', 'list="cr-dl-pns"');
        return `<div class="col-md-3">${crSel([['pn', 'Hardware with part number'], ['category', 'Any hardware in category'], ['item', 'A specific library item']], by, 'cr-c-by')}</div>
          <div class="col-md-3">${value}</div>
          <div class="col-md-2">${crSel([['1', 'is on the opening'], ['0', 'is NOT on the opening']], c.present === false ? '0' : '1', 'cr-c-present')}</div>
          <div class="col-md-2">${crSel([['', 'either leaf'], ['active', 'active leaf'], ['inactive', 'inactive leaf']], c.leaf || '', 'cr-c-leaf')}</div>`;
      }
      case 'function':
        return `<div class="col-md-4">${crSel(hwFunctions.map(f => [f.code, `${f.code} — ${f.label}`]), c.value, 'cr-c-value')}</div>
          <div class="col-md-3">${crSel([['1', 'is selected'], ['0', 'is NOT selected']], c.present === false ? '0' : '1', 'cr-c-present')}</div>`;
      case 'field': {
        const field = c.field || Object.keys(crMeta.fields)[0];
        const options = crMeta.field_options[field];
        const op = c.op || 'eq';
        const value = options && op !== 'in'
          ? crSel(options.map(o => [o, o]), c.value, 'cr-c-value')
          : crInput('cr-c-value', c.value, op === 'in' ? 'comma-separated list' : 'value');
        return `<div class="col-md-3">${crSel(Object.entries(crMeta.fields), field, 'cr-c-field')}</div>
          <div class="col-md-2">${crSel([['eq', 'is'], ['neq', 'is not'], ['in', 'is one of']], op, 'cr-c-op')}</div>
          <div class="col-md-4">${value}</div>`;
      }
      case 'compare':
        return `<div class="col-md-4">${crInput('cr-c-left font-monospace', c.left, 'e.g. PANIC_CL_AFF')}</div>
          <div class="col-md-2">${crSel([['gt', '>'], ['gte', '≥'], ['lt', '<'], ['lte', '≤'], ['eq', '='], ['neq', '≠']], c.op || 'gt', 'cr-c-op')}</div>
          <div class="col-md-3">${crInput('cr-c-right font-monospace', c.right, 'e.g. 40')}</div>`;
    }
    return '';
  }

  function crConditionRow(c) {
    const types = [['hardware', 'Hardware'], ['function', 'Hardware function'], ['field', 'Opening / frame / door'], ['compare', 'Compare values']];
    return `<div class="row g-2 align-items-center mb-2 cr-condition">
      <div class="col-md-2">${crSel(types, c.type, 'cr-c-type')}</div>
      ${crConditionFields(c)}
      <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-icon text-danger" onclick="this.closest('.cr-condition').remove(); crConditionsChanged()"><i class="ti ti-x"></i></button></div>
    </div>`;
  }

  function crReadCondition(row) {
    const type = crVal(row, 'cr-c-type');
    if (type === 'hardware') return { type, by: crVal(row, 'cr-c-by'), value: crVal(row, 'cr-c-value').trim(), present: crVal(row, 'cr-c-present') === '1', leaf: crVal(row, 'cr-c-leaf') };
    if (type === 'function') return { type, value: crVal(row, 'cr-c-value'), present: crVal(row, 'cr-c-present') === '1' };
    if (type === 'field') return { type, field: crVal(row, 'cr-c-field'), op: crVal(row, 'cr-c-op'), value: crVal(row, 'cr-c-value').trim() };
    return { type, left: crVal(row, 'cr-c-left').trim(), op: crVal(row, 'cr-c-op'), right: crVal(row, 'cr-c-right').trim() };
  }

  function crAddCondition(c) {
    document.getElementById('cr-conditions').insertAdjacentHTML('beforeend', crConditionRow(c || { type: 'hardware', by: 'pn', present: true }));
    crConditionsChanged();
  }
  function crConditionsChanged() {
    document.getElementById('cr-conditions-empty').style.display = document.querySelector('#cr-conditions .cr-condition') ? 'none' : '';
  }

  // Changing a row's type (or a choice that changes its inputs) redraws that row, keeping what still applies.
  document.getElementById('cr-conditions').addEventListener('change', (e) => {
    const row = e.target.closest('.cr-condition');
    if (!row || !['cr-c-type', 'cr-c-by', 'cr-c-field', 'cr-c-op'].some(c => e.target.classList.contains(c))) return;
    const current = crReadCondition(row);
    if (e.target.classList.contains('cr-c-type')) Object.keys(current).forEach(k => { if (k !== 'type') delete current[k]; });
    if (e.target.classList.contains('cr-c-by') || e.target.classList.contains('cr-c-field')) current.value = '';
    row.outerHTML = crConditionRow(current);
  });

  // ---- action cards --------------------------------------------------------------------------
  function crMatchFields(m, scope) {
    const list = scope === 'frame' && (m?.by || 'label') === 'label' ? 'list="cr-dl-roles"' : '';
    return `<div class="col-md-2"><label class="form-label small mb-0">Part matched by</label>${crSel([['label', 'Label'], ['pn', 'Part number']], m?.by || 'label', 'cr-a-mby')}</div>
      <div class="col-md-2"><label class="form-label small mb-0">&nbsp;</label>${crSel([['eq', 'is exactly'], ['contains', 'contains']], m?.op || 'eq', 'cr-a-mop')}</div>
      <div class="col-md-3"><label class="form-label small mb-0">&nbsp;</label>${crInput('cr-a-mvalue', m?.value, 'e.g. Lock Jamb Stop', list)}</div>`;
  }
  function crPieceRow(p) {
    return `<div class="row g-2 align-items-center mb-1 cr-piece">
      <div class="col-md-3">${crInput('cr-p-label', p?.label, 'Piece name, e.g. Lower')}</div>
      <div class="col-md-8">${crInput('cr-p-length font-monospace', p?.length, 'Length formula, e.g. PANIC_CL_AFF - THRESHOLD - P2451.length / 2')}</div>
      <div class="col-auto"><button type="button" class="btn btn-sm btn-icon text-danger" onclick="this.closest('.cr-piece').remove()"><i class="ti ti-x"></i></button></div>
    </div>`;
  }

  function crActionFields(a) {
    const scopes = Object.entries(crMeta.scopes);
    const scope = a.scope || 'frame';
    const scopeSel = (opts = scopes) => `<div class="col-md-2"><label class="form-label small mb-0">In the</label>${crSel(opts, scope, 'cr-a-scope')}</div>`;
    const formula = (cls, label, value, ph) => `<div class="col-md-4"><label class="form-label small mb-0">${label}</label>${crInput(cls + ' font-monospace', value, ph)}</div>`;
    switch (a.type) {
      case 'add_part':
        return `${scopeSel()}
          <div class="col-md-2"><label class="form-label small mb-0">Part number</label>${crInput('cr-a-pn', a.pn, 'e.g. P2451', 'list="cr-dl-pns"')}</div>
          <div class="col-md-3"><label class="form-label small mb-0">Label <span class="text-muted">(optional)</span></label>${crInput('cr-a-label', a.label, 'defaults to the description')}</div>
          <div class="col-md-2"><label class="form-label small mb-0">Qty per opening</label>${crInput('cr-a-qty font-monospace', a.qty, '1')}</div>
          ${scope === 'hardware'
            ? `<div class="col-md-2"><label class="form-label small mb-0">Listed as</label>${crSel([['backer', 'Backer'], ['item', 'Hardware item'], ['fastener', 'Fastener']], a.source_type || 'backer', 'cr-a-source')}</div>`
            : `<div class="col-md-3"><label class="form-label small mb-0">Cut length <span class="text-muted">(blank = not cut)</span></label>${crInput('cr-a-length font-monospace', a.length, 'formula')}</div>`}`;
      case 'remove_part':
        return `${scopeSel()}${crMatchFields(a.match, scope)}`;
      case 'swap_part':
        return `${scopeSel()}${crMatchFields(a.match, scope)}
          <div class="col-md-2"><label class="form-label small mb-0">Use part number</label>${crInput('cr-a-pn', a.pn, 'new PN', 'list="cr-dl-pns"')}</div>`;
      case 'adjust_part':
        return `${scopeSel()}${crMatchFields(a.match, scope)}
          <div class="w-100"></div>
          ${scope === 'hardware' ? '' : formula('cr-a-length', 'New length <span class="text-muted">(blank = keep)</span>', a.length, 'e.g. ORIGINAL - 1/4')}
          ${formula('cr-a-qty', 'New quantity <span class="text-muted">(blank = keep)</span>', a.qty, 'e.g. ORIGINAL_QTY * 2')}`;
      case 'split_part':
        return `${scopeSel(scopes.filter(([k]) => k !== 'hardware'))}${crMatchFields(a.match, scope)}
          <div class="col-12">
            <label class="form-label small mb-1">Pieces <span class="text-muted">(each becomes its own cut; later pieces can use PIECE1, PIECE2&hellip; and REMAINDER)</span></label>
            <div class="cr-pieces">${(a.pieces?.length ? a.pieces : [{}, {}]).map(crPieceRow).join('')}</div>
            <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="this.previousElementSibling.insertAdjacentHTML('beforeend', crPieceRow())"><i class="ti ti-plus"></i> Piece</button>
          </div>`;
      case 'set_variable':
        return `<div class="col-md-4"><label class="form-label small mb-0">Prep value</label>${crSel(hwVariables.filter(v => v.var_type === 'number').map(v => [v.code, `${v.code} — ${v.label}`]), a.code, 'cr-a-code')}</div>
          ${formula('cr-a-value', 'Becomes', a.value, 'e.g. PANIC_CL_AFF')}
          <div class="col-12 text-muted small">Applies to every hardware item on the opening that uses this value, unless the value was typed in for that opening.</div>`;
      case 'message':
        return `<div class="col-md-3"><label class="form-label small mb-0">Kind</label>${crSel([['warning', 'Warning'], ['error', 'Error — blocks release']], a.severity || 'warning', 'cr-a-severity')}</div>
          <div class="col-md-8"><label class="form-label small mb-0">Message</label>${crInput('cr-a-text', a.text, 'e.g. P1420 needs push and pull hardware selected')}</div>`;
    }
    return '';
  }

  function crActionCard(a) {
    return `<div class="card card-sm mb-2 cr-action"><div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-0">Action</label>${crSel(Object.entries(crMeta.actions), a.type, 'cr-a-type')}</div>
        <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-icon text-danger" onclick="this.closest('.cr-action').remove()" title="Remove action"><i class="ti ti-x"></i></button></div>
        <div class="w-100"></div>
        ${crActionFields(a)}
      </div>
    </div></div>`;
  }

  function crReadAction(card) {
    const type = crVal(card, 'cr-a-type');
    const a = { type };
    const has = cls => card.querySelector('.' + cls) !== null;
    if (has('cr-a-scope')) a.scope = crVal(card, 'cr-a-scope');
    if (has('cr-a-mvalue')) a.match = { by: crVal(card, 'cr-a-mby'), op: crVal(card, 'cr-a-mop'), value: crVal(card, 'cr-a-mvalue').trim() };
    [['pn', 'cr-a-pn'], ['label', 'cr-a-label'], ['qty', 'cr-a-qty'], ['length', 'cr-a-length'], ['source_type', 'cr-a-source'],
      ['code', 'cr-a-code'], ['value', 'cr-a-value'], ['severity', 'cr-a-severity'], ['text', 'cr-a-text']]
      .forEach(([key, cls]) => { if (has(cls)) a[key] = crVal(card, cls).trim(); });
    if (type === 'split_part') {
      a.pieces = Array.from(card.querySelectorAll('.cr-piece'))
        .map(p => ({ label: crVal(p, 'cr-p-label').trim(), length: crVal(p, 'cr-p-length').trim() }))
        .filter(p => p.label || p.length);
    }
    return a;
  }

  function crAddAction(a) {
    document.getElementById('cr-actions').insertAdjacentHTML('beforeend', crActionCard(a || { type: 'add_part', scope: 'hardware' }));
  }

  document.getElementById('cr-actions').addEventListener('change', (e) => {
    const card = e.target.closest('.cr-action');
    if (!card || !['cr-a-type', 'cr-a-scope', 'cr-a-mby'].some(c => e.target.classList.contains(c))) return;
    const current = crReadAction(card);
    if (e.target.classList.contains('cr-a-type')) {
      // Keep what carries over between part actions (which BOM, which part); drop the rest.
      Object.keys(current).forEach(k => { if (!['type', 'scope', 'match', 'pn'].includes(k)) delete current[k]; });
      if (current.type === 'split_part' && current.scope === 'hardware') current.scope = 'frame';
    }
    card.outerHTML = crActionCard(current);
  });

  // ---- modal ---------------------------------------------------------------------------------
  function crOpenModal(id, duplicate = false) {
    const r = id ? crRules.find(x => x.id == id) : null;
    document.getElementById('cr-id').value = r && !duplicate ? r.id : '';
    document.getElementById('cr-name').value = r ? (duplicate ? `${r.name} (copy)` : r.name) : '';
    document.getElementById('cr-sort').value = r && !duplicate ? r.sort_order : '';
    document.getElementById('cr-enabled').checked = r ? !!r.enabled : true;
    document.getElementById('cr-notes').value = r?.notes || '';
    document.getElementById('cr-logic').value = r?.conditions?.logic || 'all';

    document.getElementById('cr-dl-pns').innerHTML = [...new Set([...hwItems.map(i => i.pn), ...hwBackers.map(b => b.pn)].filter(Boolean))].sort()
      .map(pn => `<option value="${esc(pn)}">`).join('');
    document.getElementById('cr-dl-roles').innerHTML = (crMeta.frame_roles || []).map(x => `<option value="${esc(x)}">`).join('');
    document.getElementById('cr-ref-builtins').innerHTML = Object.entries(crMeta.built_ins).map(([k, v]) => `<div><code>${esc(k)}</code> &mdash; ${esc(v)}</div>`).join('');
    document.getElementById('cr-ref-variables').innerHTML = hwVariables.filter(v => v.var_type === 'number')
      .sort((a, b) => a.code.localeCompare(b.code)).map(v => `<div><code>${esc(v.code)}</code> &mdash; ${esc(v.label)}</div>`).join('');

    document.getElementById('cr-conditions').innerHTML = '';
    // The editor works on a flat list; a nested group (only possible through the API) is flattened into it.
    const flatten = items => (items || []).flatMap(c => c.items ? flatten(c.items) : [c]);
    flatten(r?.conditions?.items).forEach(c => crAddCondition(c));
    crConditionsChanged();
    document.getElementById('cr-actions').innerHTML = '';
    (r?.actions?.length ? r.actions : [{ type: 'add_part', scope: 'hardware' }]).forEach(a => crAddAction(a));

    showModal(document.getElementById('cr-modal'));
  }

  document.getElementById('cr-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('cr-id').value;
    const sort = document.getElementById('cr-sort').value;
    const payload = {
      name: document.getElementById('cr-name').value.trim(),
      notes: document.getElementById('cr-notes').value.trim() || null,
      enabled: document.getElementById('cr-enabled').checked,
      sort_order: sort === '' ? null : parseInt(sort, 10),
      conditions: {
        logic: document.getElementById('cr-logic').value,
        items: Array.from(document.querySelectorAll('#cr-conditions .cr-condition')).map(crReadCondition),
      },
      actions: Array.from(document.querySelectorAll('#cr-actions .cr-action')).map(crReadAction),
    };
    if (id && payload.sort_order === null) delete payload.sort_order;
    try {
      await authenticatedFetch(id ? `/config/rules/${id}` : '/config/rules', { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
      hideModal(document.getElementById('cr-modal'));
      showNotification('Rule saved. Openings it affects will ask to be recalculated.', 'success');
      await crLoad();
    } catch (err) { showNotification(err.message, 'danger'); }
  });

  async function crDelete(id) {
    const r = crRules.find(x => x.id == id);
    if (!confirm(`Delete the rule "${r?.name}"? Openings already generated keep their parts until they are recalculated.`)) return;
    try { await authenticatedFetch(`/config/rules/${id}`, { method: 'DELETE' }); await crLoad(); }
    catch (err) { showNotification(err.message, 'danger'); }
  }

  // ---- try on an opening ---------------------------------------------------------------------
  async function crLoadConfigs() {
    try {
      const data = await authenticatedFetch('/door-frame-configurations?archived=0');
      crConfigs = data.configurations || [];
      const jobs = new Map(crConfigs.map(c => [c.business_job_id, `${c.job_number} — ${c.job_name}`]));
      document.getElementById('cr-test-job').innerHTML = [...jobs.entries()].sort((a, b) => a[1].localeCompare(b[1]))
        .map(([jobId, label]) => `<option value="${jobId}">${esc(label)}</option>`).join('') || '<option value="">No openings yet</option>';
      crTestFillOpenings();
    } catch (err) { /* the test panel is optional */ }
  }
  function crTestFillOpenings() {
    const jobId = document.getElementById('cr-test-job').value;
    document.getElementById('cr-test-config').innerHTML = crConfigs.filter(c => c.business_job_id == jobId)
      .sort((a, b) => (a.door_tags || '').localeCompare(b.door_tags || '', undefined, { numeric: true }))
      .map(c => `<option value="${c.id}">${esc(c.door_tags || `#${c.id}`)} — ${esc(c.scope_label)} (${esc(c.status_label)})</option>`).join('');
  }
  async function crRunTest() {
    const id = document.getElementById('cr-test-config').value;
    const out = document.getElementById('cr-test-result');
    if (!id) { out.innerHTML = '<div class="text-muted">Choose an opening first.</div>'; return; }
    out.innerHTML = '<div class="text-muted">Running&hellip;</div>';
    try {
      const res = await authenticatedFetch('/config/rules/test', { method: 'POST', body: JSON.stringify({ configuration_id: parseInt(id, 10) }) });
      const fired = res.fired.length
        ? res.fired.map(f => `<span class="badge bg-blue-lt me-1">${esc(f.name)}</span>`).join('')
        : '<span class="text-muted">No rule applies to this opening.</span>';
      const messages = res.messages.map(m => `<div class="alert ${m.severity === 'error' ? 'alert-danger' : 'alert-warning'} py-2 mb-2"><strong>${esc(m.rule)}:</strong> ${esc(m.text)}</div>`).join('');
      const scopes = Object.entries(res.scopes).map(([scope, s]) => {
        const title = `<div class="fw-bold mt-3 mb-1">${esc(crMeta.scopes[scope] || scope)}</div>`;
        if (s.skipped) return `${title}<div class="text-muted small">Not generated: ${esc(s.skipped)}</div>`;
        const warnings = s.warnings.map(w => `<div class="text-warning small"><i class="ti ti-alert-triangle me-1"></i>${esc(w)}</div>`).join('');
        if (!s.rows.length) return `${title}${warnings}<div class="text-muted small">No rule-made rows.</div>`;
        return `${title}${warnings}<div class="table-responsive"><table class="table table-sm table-vcenter mb-0"><thead><tr><th>Part</th><th>PN</th><th>Length</th><th>Qty</th><th>Rule</th></tr></thead><tbody>${
          s.rows.map(r => `<tr><td>${esc(r.part_label)}</td><td>${esc(r.part_number || '')}</td><td>${r.length != null ? esc(String(r.length)) + '"' : '—'}</td><td>${esc(String(r.quantity ?? ''))}</td><td class="text-muted small">${esc(r.rule || '')}</td></tr>`).join('')
        }</tbody></table></div>`;
      }).join('');
      out.innerHTML = `<div class="mb-2"><span class="text-muted me-2">Rules that apply:</span>${fired}</div>${messages}${scopes}`;
    } catch (err) { out.innerHTML = `<div class="text-danger">${esc(err.message)}</div>`; }
  }

  document.addEventListener('DOMContentLoaded', () => {
    window.sessionReady.then(() => { crLoad(); crLoadConfigs(); });
  });
</script>
