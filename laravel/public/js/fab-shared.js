// Shared helpers for the Fabrication module (work orders, jobs WO tab, shop floor, admin).
// Plain global script (no bundler) — matches how every other page in this app works.
(function (global) {
  // ── Toasts ──────────────────────────────────────────────────────────────
  function ensureToastContainer() {
    let el = document.getElementById('fab-toast-container');
    if (!el) {
      el = document.createElement('div');
      el.id = 'fab-toast-container';
      el.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:10000;display:flex;flex-direction:column;gap:.5rem;max-width:340px;';
      document.body.appendChild(el);
    }
    return el;
  }

  const TOAST_STYLES = {
    success: { bg: '#d1e7dd', color: '#0a3622', icon: '✓' },
    error:   { bg: '#f8d7da', color: '#58151c', icon: '⚠' },
    info:    { bg: '#dce7f9', color: '#2c5fc3', icon: 'ℹ' },
  };

  function fabToast(message, type) {
    type = type && TOAST_STYLES[type] ? type : 'info';
    const style = TOAST_STYLES[type];
    const container = ensureToastContainer();
    const toast = document.createElement('div');
    toast.style.cssText = `background:${style.bg};color:${style.color};padding:.65rem .9rem;border-radius:8px;` +
      'box-shadow:0 4px 16px rgba(0,0,0,.15);font-size:.85rem;font-weight:500;display:flex;align-items:center;gap:.5rem;' +
      'opacity:0;transform:translateY(8px);transition:opacity .15s,transform .15s;';
    toast.innerHTML = `<span>${style.icon}</span><span>${String(message)}</span>`;
    container.appendChild(toast);
    requestAnimationFrame(() => {
      toast.style.opacity = '1';
      toast.style.transform = 'translateY(0)';
    });
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(8px)';
      setTimeout(() => toast.remove(), 200);
    }, type === 'error' ? 5000 : 3000);
  }

  // ── Confirm dialog (replaces native confirm() / alert()) ──────────────────
  function ensureConfirmOverlay() {
    let el = document.getElementById('fab-confirm-overlay');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'fab-confirm-overlay';
    el.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10001;align-items:center;justify-content:center;';
    el.innerHTML = `
      <div style="background:var(--tblr-bg-surface,#fff);border-radius:12px;padding:1.75rem;width:min(380px,90vw);box-shadow:0 8px 32px rgba(0,0,0,.3);text-align:center">
        <h4 id="fab-confirm-title" style="margin-bottom:.5rem">Confirm</h4>
        <p id="fab-confirm-message" style="color:var(--tblr-secondary,#6c7a91);margin-bottom:1.5rem"></p>
        <div class="d-flex gap-2 justify-content-center">
          <button type="button" id="fab-confirm-cancel" class="btn btn-ghost-secondary">Cancel</button>
          <button type="button" id="fab-confirm-ok" class="btn btn-danger">Confirm</button>
        </div>
      </div>`;
    document.body.appendChild(el);
    return el;
  }

  function fabConfirm(opts) {
    opts = opts || {};
    const el = ensureConfirmOverlay();
    el.querySelector('#fab-confirm-title').textContent = opts.title || 'Confirm';
    el.querySelector('#fab-confirm-message').textContent = opts.message || 'Are you sure?';
    const okBtn = el.querySelector('#fab-confirm-ok');
    okBtn.textContent = opts.confirmLabel || 'Confirm';
    okBtn.className = 'btn ' + (opts.confirmClass || 'btn-danger');
    el.style.display = 'flex';

    return new Promise(resolve => {
      const cancelBtn = el.querySelector('#fab-confirm-cancel');
      const cleanup = (result) => {
        el.style.display = 'none';
        okBtn.removeEventListener('click', onOk);
        cancelBtn.removeEventListener('click', onCancel);
        resolve(result);
      };
      const onOk = () => cleanup(true);
      const onCancel = () => cleanup(false);
      okBtn.addEventListener('click', onOk);
      cancelBtn.addEventListener('click', onCancel);
    });
  }

  // ── WO Step status (shared between work-orders.blade.php and jobs.blade.php) ──
  const STEP_STATUS_META = {
    pending:      { color: 'secondary', icon: '' },
    complete:     { color: 'success',   icon: '✓' },
    not_required: { color: 'info',      icon: '—' },
    on_hold:      { color: 'orange',    icon: '⏸' },
  };

  function stepBadgeHtml(step, opts) {
    opts = opts || {};
    const esc = opts.esc || (s => s);
    const meta = STEP_STATUS_META[step.status] || STEP_STATUS_META.pending;
    const title = step.status === 'complete' && step.completed_by_name
      ? 'By: ' + step.completed_by_name
      : step.status;
    const attrs = [`onclick="${opts.onClick}"`];
    if (opts.onContextMenu) attrs.push(`oncontextmenu="${opts.onContextMenu}"`);
    return `<span class="badge bg-${meta.color}-lt text-${meta.color}" style="cursor:pointer;font-size:${opts.fontSize || '.78rem'};padding:${opts.padding || '.28rem .6rem'}"
        title="${esc(title)}" ${attrs.join(' ')}>
        ${meta.icon ? meta.icon + ' ' : ''}${esc(step.name)}
    </span>`;
  }

  // Determines the next status for a plain click-to-cycle interaction.
  // Clearing an on_hold step always requires confirmation first — a step
  // put on hold by the office shouldn't be silently cleared by one click.
  async function cycleStepStatus(currentStatus) {
    const next = currentStatus === 'pending' ? 'complete' : 'pending';
    if (currentStatus === 'on_hold') {
      const ok = await fabConfirm({
        title: 'Step is on hold',
        message: 'This step was put on hold by the office. Take it off hold?',
        confirmLabel: 'Yes, Take Off Hold',
        confirmClass: 'btn-warning',
      });
      if (!ok) return null;
    }
    return next;
  }

  global.fabToast = fabToast;
  global.fabConfirm = fabConfirm;
  global.FabStep = { STEP_STATUS_META, stepBadgeHtml, cycleStepStatus };
})(window);
