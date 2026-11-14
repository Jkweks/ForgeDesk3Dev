{{-- Modal helpers for the Project Management pages (the older pages each carry their own copy).
     Uses Bootstrap's modal when it is loaded and falls back to a plain show/hide. --}}
<script>
  function safeShowModal(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return null;
    if (window.bootstrap && window.bootstrap.Modal) {
      const modal = window.bootstrap.Modal.getOrCreateInstance(el);
      modal.show();
      return modal;
    }
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    backdrop.id = 'backdrop-' + modalId;
    document.body.appendChild(backdrop);
    el.style.display = 'block';
    el.classList.add('show');
    el.setAttribute('aria-modal', 'true');
    el.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');
    backdrop.addEventListener('click', () => safeHideModal(modalId));
    el.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => { btn.onclick = () => safeHideModal(modalId); });
    return null;
  }

  function safeHideModal(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;
    if (window.bootstrap && window.bootstrap.Modal) {
      const modal = window.bootstrap.Modal.getInstance(el);
      if (modal) { modal.hide(); return; }
    }
    el.style.display = 'none';
    el.classList.remove('show');
    el.setAttribute('aria-hidden', 'true');
    el.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    const backdrop = document.getElementById('backdrop-' + modalId);
    if (backdrop) backdrop.remove();
  }
</script>
