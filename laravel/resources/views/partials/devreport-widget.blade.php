<style>
  #devReportFab {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 1050;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--tblr-orange, #f76707);
    color: #fff;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,.25);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform .15s, box-shadow .15s;
  }
  #devReportFab:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(0,0,0,.35);
  }
  #devReportPanel {
    position: fixed;
    bottom: 5rem;
    right: 1.5rem;
    z-index: 1050;
    width: 360px;
    max-width: calc(100vw - 2rem);
    background: var(--tblr-bg-surface, #fff);
    border: 1px solid var(--tblr-border-color);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,.18);
    display: none;
  }
  #devReportPanel.open { display: block; }
  .devreport-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .75rem 1rem;
    border-bottom: 1px solid var(--tblr-border-color);
    font-weight: 600;
    font-size: .9rem;
  }
  .devreport-body { padding: .75rem 1rem; }
  .devreport-body .form-label { font-size: .8rem; margin-bottom: .25rem; }
  .devreport-body .form-control,
  .devreport-body .form-select { font-size: .85rem; }
  .devreport-footer {
    padding: .5rem 1rem .75rem;
    display: flex;
    gap: .5rem;
    justify-content: flex-end;
    border-top: 1px solid var(--tblr-border-color);
  }
</style>

<!-- Floating action button -->
<button id="devReportFab" title="Report a dev issue" onclick="devReportToggle()">
  <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
    <path d="M9 12l2 2l4 -4" />
    <path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3" />
  </svg>
</button>

<!-- Quick report panel -->
<div id="devReportPanel">
  <div class="devreport-header">
    <span>
      <span class="badge bg-orange text-white me-1" style="font-size:.6rem">DEV</span>
      Report Issue
    </span>
    <button type="button" class="btn-close btn-sm" onclick="devReportClose()"></button>
  </div>
  <div class="devreport-body">
    <div class="mb-2">
      <label class="form-label required">Title</label>
      <input type="text" class="form-control form-control-sm" id="drTitle" placeholder="What went wrong?">
    </div>
    <div class="row g-2 mb-2">
      <div class="col">
        <label class="form-label">Priority</label>
        <select class="form-select form-select-sm" id="drPriority">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
      </div>
      <div class="col">
        <label class="form-label">Your Name</label>
        <input type="text" class="form-control form-control-sm" id="drReporter" placeholder="Optional">
      </div>
    </div>
    <div class="mb-2">
      <label class="form-label">Description</label>
      <textarea class="form-control form-control-sm" id="drDescription" rows="2" placeholder="Steps to reproduce, what you expected…"></textarea>
    </div>
    <div class="mb-0">
      <label class="form-label d-flex justify-content-between align-items-center">
        Console Output
        <button type="button" class="btn btn-xs btn-ghost-secondary py-0 px-1" style="font-size:.75rem"
          onclick="devReportPasteConsole()">Paste clipboard</button>
      </label>
      <textarea class="form-control form-control-sm font-monospace" id="drConsole" rows="3"
        placeholder="Paste errors here, or click 'Paste clipboard'…" style="font-size:.75rem"></textarea>
    </div>
    <div id="drError" class="alert alert-danger alert-sm mt-2 py-1 px-2" style="display:none;font-size:.8rem"></div>
    <div id="drSuccess" class="alert alert-success alert-sm mt-2 py-1 px-2" style="display:none;font-size:.8rem"></div>
  </div>
  <div class="devreport-footer">
    <button type="button" class="btn btn-sm btn-secondary" onclick="devReportClose()">Cancel</button>
    <button type="button" class="btn btn-sm btn-orange" id="drSubmitBtn" onclick="devReportSubmit()">Submit &amp; Copy Console</button>
  </div>
</div>

<script>
(function() {
  function devReportToggle() {
    const panel = document.getElementById('devReportPanel');
    if (panel.classList.contains('open')) {
      devReportClose();
    } else {
      panel.classList.add('open');
      // pre-fill page URL
      document.getElementById('drConsole').value = '';
      document.getElementById('drTitle').value = '';
      document.getElementById('drDescription').value = '';
      document.getElementById('drError').style.display = 'none';
      document.getElementById('drSuccess').style.display = 'none';
      document.getElementById('drTitle').focus();
      // try auto-grab console via intercepted logs (if available)
      if (window.__devReportLogs && window.__devReportLogs.length) {
        document.getElementById('drConsole').value = window.__devReportLogs.join('\n');
      }
    }
  }
  window.devReportToggle = devReportToggle;

  function devReportClose() {
    document.getElementById('devReportPanel').classList.remove('open');
  }
  window.devReportClose = devReportClose;

  async function devReportPasteConsole() {
    try {
      const text = await navigator.clipboard.readText();
      document.getElementById('drConsole').value = text;
    } catch {
      alert('Clipboard access denied. Please paste manually (Ctrl+V / Cmd+V).');
    }
  }
  window.devReportPasteConsole = devReportPasteConsole;

  async function devReportSubmit() {
    const title   = document.getElementById('drTitle').value.trim();
    const errEl   = document.getElementById('drError');
    const succEl  = document.getElementById('drSuccess');
    errEl.style.display = 'none';
    succEl.style.display = 'none';

    if (!title) {
      errEl.textContent = 'Title is required.';
      errEl.style.display = 'block';
      return;
    }

    const consoleText = document.getElementById('drConsole').value.trim();
    const payload = {
      title,
      description:    document.getElementById('drDescription').value.trim(),
      console_output: consoleText,
      page_url:       window.location.pathname,
      user_agent:     navigator.userAgent,
      reported_by:    document.getElementById('drReporter').value.trim(),
      priority:       document.getElementById('drPriority').value,
    };

    const btn = document.getElementById('drSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    try {
      await apiCall('/api/v1/dev-issues', { method: 'POST', body: JSON.stringify(payload) });

      // Auto-copy console output to clipboard after submit
      if (consoleText) {
        try { await navigator.clipboard.writeText(consoleText); } catch {}
      }

      succEl.textContent = consoleText
        ? 'Issue saved! Console output copied to clipboard.'
        : 'Issue saved!';
      succEl.style.display = 'block';

      document.getElementById('drTitle').value = '';
      document.getElementById('drDescription').value = '';
      document.getElementById('drConsole').value = '';

      setTimeout(devReportClose, 2000);
    } catch (e) {
      errEl.textContent = e.message || 'Failed to save issue.';
      errEl.style.display = 'block';
    } finally {
      btn.disabled = false;
      btn.textContent = 'Submit & Copy Console';
    }
  }
  window.devReportSubmit = devReportSubmit;

  // Intercept console.error and console.warn to capture them automatically
  window.__devReportLogs = [];
  const _origError = console.error.bind(console);
  const _origWarn  = console.warn.bind(console);
  console.error = function(...args) {
    window.__devReportLogs.push('[ERROR] ' + args.map(a => typeof a === 'object' ? JSON.stringify(a) : a).join(' '));
    if (window.__devReportLogs.length > 100) window.__devReportLogs.shift();
    _origError(...args);
  };
  console.warn = function(...args) {
    window.__devReportLogs.push('[WARN] ' + args.map(a => typeof a === 'object' ? JSON.stringify(a) : a).join(' '));
    if (window.__devReportLogs.length > 100) window.__devReportLogs.shift();
    _origWarn(...args);
  };
  window.addEventListener('error', function(e) {
    window.__devReportLogs.push(`[UNCAUGHT] ${e.message} at ${e.filename}:${e.lineno}`);
  });
  window.addEventListener('unhandledrejection', function(e) {
    window.__devReportLogs.push(`[UNHANDLED PROMISE] ${e.reason}`);
  });
})();
</script>
