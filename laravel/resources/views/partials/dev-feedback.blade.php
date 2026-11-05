{{--
  Admin-only development tracker popup: floating button + modal on every page.
  Entries land in /development (dev_items). Server enforces admin via the `admin` middleware.
  The console buffer is installed on every page (admin only) so "Attach recent console output"
  can include errors that happened before the modal was opened.
--}}
@if(Auth::user() && Auth::user()->isAdmin())
<style>
  #devFeedbackFab { position: fixed; right: 1rem; bottom: 1rem; z-index: 1030; box-shadow: var(--tblr-box-shadow); }
</style>
<button type="button" id="devFeedbackFab" class="btn btn-primary btn-icon rounded-circle d-print-none" title="Report a bug / request a feature / add a note" aria-label="Open development tracker form">
  <i class="ti ti-bug icon"></i>
</button>

<div class="modal modal-blur fade" id="devFeedbackModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="devFeedbackForm">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-bug me-2"></i>Development Tracker</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Type</label>
              <div class="form-selectgroup w-100">
                <label class="form-selectgroup-item flex-fill"><input type="radio" name="devType" value="bug" class="form-selectgroup-input" checked><span class="form-selectgroup-label"><i class="ti ti-bug me-1"></i>Bug</span></label>
                <label class="form-selectgroup-item flex-fill"><input type="radio" name="devType" value="feature" class="form-selectgroup-input"><span class="form-selectgroup-label"><i class="ti ti-bulb me-1"></i>Feature</span></label>
                <label class="form-selectgroup-item flex-fill"><input type="radio" name="devType" value="note" class="form-selectgroup-input"><span class="form-selectgroup-label"><i class="ti ti-note me-1"></i>Note</span></label>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Priority</label>
              <select class="form-select" id="devPriority">
                <option value="low">Low</option>
                <option value="normal" selected>Normal</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label required">Title</label>
              <input type="text" class="form-control" id="devTitle" maxlength="255" required placeholder="Short summary">
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="devDescription" rows="5" placeholder="What happened / what you expected / what you want. Steps to reproduce for bugs."></textarea>
            </div>
            <div class="col-12">
              <div class="d-flex align-items-center mb-1">
                <label class="form-label mb-0">Console output <span class="text-muted">(optional)</span></label>
                <button type="button" class="btn btn-sm btn-ghost-secondary ms-auto" id="devAttachConsole"><i class="ti ti-terminal-2 me-1"></i>Attach recent console output</button>
              </div>
              <textarea class="form-control font-monospace small" id="devConsole" rows="5" placeholder="Paste errors or logs here"></textarea>
            </div>
            <div class="col-12 text-muted small">Page: <span id="devPageUrl"></span></div>
          </div>
          <div id="devFeedbackError" class="alert alert-danger mt-3 mb-0" style="display:none;"></div>
        </div>
        <div class="modal-footer">
          <a href="/development" class="btn btn-link me-auto">Open tracker</a>
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="devSubmitBtn">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  // Rolling buffer of console errors/warnings and uncaught errors for this page view.
  const buf = [];
  const MAX = 40;
  const fmt = (a) => { try { return typeof a === 'string' ? a : (a instanceof Error ? (a.stack || a.message) : JSON.stringify(a)); } catch (e) { return String(a); } };
  const push = (lvl, text) => { buf.push('[' + new Date().toLocaleTimeString() + '] ' + lvl + ': ' + text); if (buf.length > MAX) buf.shift(); };
  ['error', 'warn'].forEach((lvl) => {
    const orig = console[lvl].bind(console);
    console[lvl] = (...args) => { push(lvl, args.map(fmt).join(' ')); orig(...args); };
  });
  window.addEventListener('error', (e) => push('uncaught', e.message + ' (' + (e.filename || '') + ':' + (e.lineno || '') + ')'));
  window.addEventListener('unhandledrejection', (e) => push('unhandledrejection', fmt(e.reason)));

  const $ = (id) => document.getElementById(id);
  const modalEl = $('devFeedbackModal');
  let modal = null;

  function open() {
    $('devFeedbackForm').reset();
    $('devFeedbackError').style.display = 'none';
    $('devPageUrl').textContent = location.pathname + location.search;
    // Pre-fill with recent errors when there are any; admin can clear/edit it.
    $('devConsole').value = buf.length ? buf.join('\n') : '';
    modal = modal || new bootstrap.Modal(modalEl);
    modal.show();
    setTimeout(() => $('devTitle').focus(), 300);
  }
  window.openDevFeedback = open;
  $('devFeedbackFab').addEventListener('click', open);

  $('devAttachConsole').addEventListener('click', () => {
    $('devConsole').value = buf.length ? buf.join('\n') : '(no console errors or warnings captured on this page)';
  });

  $('devFeedbackForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $('devSubmitBtn');
    const err = $('devFeedbackError');
    err.style.display = 'none';
    btn.disabled = true;
    try {
      const res = await apiCall('/dev-items', {
        method: 'POST',
        body: JSON.stringify({
          type: document.querySelector('input[name="devType"]:checked').value,
          priority: $('devPriority').value,
          title: $('devTitle').value.trim(),
          description: $('devDescription').value.trim() || null,
          console_output: $('devConsole').value.trim() || null,
          page_url: location.pathname + location.search,
        }),
      });
      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.message || ('HTTP ' + res.status));
      }
      modal.hide();
      showNotification('Saved to development tracker', 'success');
      if (typeof window.onDevItemSaved === 'function') window.onDevItemSaved();
    } catch (ex) {
      err.textContent = ex.message;
      err.style.display = 'block';
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>
@endif
