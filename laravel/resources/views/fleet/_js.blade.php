<script>
  // Shared helpers for the Field & Fleet pages.
  const FL = {
    STATUS: {
      active: ['Active', 'success'], in_service_job: ['On job', 'blue'], out_of_service: ['Out of service', 'danger'],
      in_repair: ['In repair', 'warning'], sold: ['Sold', 'secondary'], retired: ['Retired', 'secondary'],
    },
    WO_STATUS: {
      open: ['Open', 'blue'], scheduled: ['Scheduled', 'cyan'], in_progress: ['In progress', 'warning'],
      waiting_parts: ['Waiting parts', 'orange'], completed: ['Completed', 'success'], cancelled: ['Cancelled', 'secondary'],
    },
    PRIORITY: { low: 'secondary', medium: 'blue', high: 'orange', critical: 'danger' },
    DUE: { overdue: ['Overdue', 'danger'], due_soon: ['Due soon', 'warning'], ok: ['OK', 'success'], expired: ['Expired', 'danger'], expiring: ['Expiring', 'warning'] },
    KINDS: ['registration', 'insurance', 'dot_inspection', 'state_inspection', 'crane_cert', 'load_test', 'swing_stage_cert', 'calibration', 'warranty', 'other'],
    DOC_TYPES: ['title', 'registration', 'insurance', 'manual', 'warranty', 'invoice', 'receipt', 'inspection_report', 'certification', 'photo', 'other'],
    canCosts: () => typeof hasPermission === 'function' && hasPermission('fleet.costs.view'),
  };

  const esc = v => escapeHtml(v == null ? '' : String(v));
  const flLabel = s => esc(String(s || '').replace(/_/g, ' ').replace(/^./, c => c.toUpperCase()));
  const flBadge = (text, color) => `<span class="badge bg-${color}-lt">${esc(text)}</span>`;
  const flStatus = s => { const m = FL.STATUS[s] || [s, 'secondary']; return flBadge(m[0], m[1]); };
  const flWoStatus = s => { const m = FL.WO_STATUS[s] || [s, 'secondary']; return flBadge(m[0], m[1]); };
  const flDue = s => { const m = FL.DUE[s] || [s, 'secondary']; return flBadge(m[0], m[1]); };
  const flDate = d => d ? String(d).substring(0, 10) : '—';
  const flMoney = n => (n == null || n === '') ? '—' : '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const flNum = n => (n == null || n === '') ? '—' : Number(n).toLocaleString(undefined, { maximumFractionDigits: 1 });
  const flOpts = (list, sel, blank) => (blank ? `<option value="">${esc(blank)}</option>` : '') +
    list.map(o => { const v = typeof o === 'object' ? o.id : o; const t = typeof o === 'object' ? o.name : String(o).replace(/_/g, ' '); return `<option value="${esc(v)}" ${String(v) === String(sel ?? '') ? 'selected' : ''}>${esc(t)}</option>`; }).join('');
  const flField = (name, label, value = '', type = 'text', extra = '', col = 'col-md-6') =>
    `<div class="${col}"><label class="form-label">${esc(label)}</label><input type="${type}" class="form-control" name="${name}" value="${esc(value)}" ${extra}></div>`;
  const flSelect = (name, label, optionsHtml, col = 'col-md-6') =>
    `<div class="${col}"><label class="form-label">${esc(label)}</label><select class="form-select" name="${name}">${optionsHtml}</select></div>`;
  const flText = (name, label, value = '', rows = 3, col = 'col-12') =>
    `<div class="${col}"><label class="form-label">${esc(label)}</label><textarea class="form-control" name="${name}" rows="${rows}">${esc(value)}</textarea></div>`;

  // Read a form into a plain object; blank strings become null, numbers stay strings (the API casts).
  function flFormData(form) {
    const out = {};
    new FormData(form).forEach((v, k) => { out[k] = v === '' ? null : v; });
    form.querySelectorAll('input[type=checkbox]').forEach(c => { out[c.name] = c.checked; });
    return out;
  }

  async function flApi(endpoint, method = 'GET', body = null) {
    try {
      return await authenticatedFetch(endpoint, body == null ? { method } : { method, body: JSON.stringify(body) });
    } catch (e) {
      const first = e.data && e.data.errors ? Object.values(e.data.errors).flat()[0] : null;
      showNotification(first || e.message || 'Request failed', 'danger');
      throw e;
    }
  }

  let flModalEl = null, flModalInst = null;
  // Generic form modal. onSubmit(formEl) returns a promise; the modal closes when it resolves.
  function flModal(title, bodyHtml, onSubmit, submitLabel = 'Save', size = '') {
    if (!flModalEl) {
      flModalEl = document.createElement('div');
      flModalEl.className = 'modal modal-blur fade';
      flModalEl.tabIndex = -1;
      document.body.appendChild(flModalEl);
    }
    flModalEl.innerHTML = `<div class="modal-dialog modal-dialog-centered ${size}"><form class="modal-content" novalidate>
      <div class="modal-header"><h5 class="modal-title">${esc(title)}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><div class="row g-3">${bodyHtml}</div></div>
      <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
      ${onSubmit ? `<button type="submit" class="btn btn-primary">${esc(submitLabel)}</button>` : ''}</div></form></div>`;
    flModalInst = bootstrap.Modal.getOrCreateInstance(flModalEl);
    const form = flModalEl.querySelector('form');
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const btn = form.querySelector('[type=submit]');
      btn.disabled = true;
      try { await onSubmit(form); flModalInst.hide(); } catch (err) { /* flApi already showed the error */ } finally { btn.disabled = false; }
    });
    flModalInst.show();
    if (typeof applyActionPermissions === 'function') applyActionPermissions();
    return form;
  }

  function flConfirm(message, onYes) {
    flModal('Please confirm', `<div class="col-12">${esc(message)}</div>`, async () => { await onYes(); }, 'Confirm');
  }

  const flDocUrl = (assetId, docId) => `/api/v1/field-assets/${assetId}/documents/${docId}/download`;
</script>
