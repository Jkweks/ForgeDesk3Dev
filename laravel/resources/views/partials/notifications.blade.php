{{--
  Admin-only system alerts (e.g. backup health) — see NotificationController.
  Included in both the top navbar and the sidebar footer, so it uses classes
  (not ids) and the script is emitted once and updates every instance.

  @param string $placement  'top' (default) or 'side'
--}}
@php $placement = $placement ?? 'top'; @endphp
@if(Auth::user() && Auth::user()->isAdmin())
@if ($placement === 'side')
<li class="nav-item dropup">
  <a href="#" class="nav-link" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="Show notifications">
    <span class="nav-link-icon position-relative">
      <i class="ti ti-bell icon"></i>
      <span class="badge text-bg-red badge-notification badge-blink d-none js-notification-badge"></span>
    </span>
    <span class="nav-link-title">Notifications</span>
  </a>
  {{-- Left-aligned: the sidebar sits on the left edge, so an end-aligned menu runs off-screen when folded --}}
  <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-card" style="max-width:calc(100vw - 1rem);">
    <div class="card">
      <div class="card-header d-flex">
        <h3 class="card-title">Notifications</h3>
        <div class="btn-close ms-auto" data-bs-dismiss="dropdown"></div>
      </div>
      <div class="list-group list-group-flush list-group-hoverable js-notification-list">
        <div class="list-group-item">
          <div class="row align-items-center">
            <div class="col text-truncate">
              <div class="text-secondary">No new notifications</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</li>
@else
<div class="nav-item dropdown d-none d-md-flex me-3">
  <a href="#" class="nav-link px-0 position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" tabindex="-1" aria-label="Show notifications">
    <i class="ti ti-bell icon"></i>
    <span class="badge text-bg-red badge-notification badge-blink d-none js-notification-badge"></span>
  </a>
  <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end dropdown-menu-card">
    <div class="card">
      <div class="card-header d-flex">
        <h3 class="card-title">Notifications</h3>
        <div class="btn-close ms-auto" data-bs-dismiss="dropdown"></div>
      </div>
      <div class="list-group list-group-flush list-group-hoverable js-notification-list">
        <div class="list-group-item">
          <div class="row align-items-center">
            <div class="col text-truncate">
              <div class="text-secondary">No new notifications</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endif
@once
<div class="modal modal-blur fade" id="notification-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="notification-modal-title"></h5>
          <div class="text-secondary small" id="notification-modal-meta"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="notification-modal-body" style="white-space:pre-wrap; overflow-wrap:anywhere;"></div>
      <div class="modal-footer">
        <a href="#" class="btn btn-link me-auto d-none" id="notification-modal-link">Open</a>
        <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="notification-modal-dismiss">Dismiss notification</button>
      </div>
    </div>
  </div>
</div>
<script>
  (function () {
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text ?? '';
      return div.innerHTML;
    }

    function levelBadgeClass(level) {
      if (level === 'danger') return 'bg-danger';
      if (level === 'warning') return 'bg-warning';
      if (level === 'success') return 'bg-success';
      return 'bg-info';
    }

    const EMPTY = '<div class="list-group-item"><div class="row align-items-center"><div class="col text-truncate"><div class="text-secondary">No new notifications</div></div></div></div>';

    let current = [];

    // Where a notification's context lives, when it carries enough data to say
    function notificationLink(n) {
      const d = n.data || {};
      if (d.job_reservation_id) return { href: '/fulfillment/job-reservations', label: 'Open job reservations' };
      return null;
    }

    window.openNotification = function (id) {
      const n = current.find(function (x) { return x.id === id; });
      if (!n || !window.bootstrap) return;
      document.getElementById('notification-modal-title').textContent = n.title || '';
      const when = n.created_at ? new Date(n.created_at).toLocaleString() : '';
      document.getElementById('notification-modal-meta').textContent = [n.level, when].filter(Boolean).join(' · ');
      document.getElementById('notification-modal-body').textContent = n.message || '';
      const link = notificationLink(n);
      const a = document.getElementById('notification-modal-link');
      a.classList.toggle('d-none', !link);
      if (link) { a.href = link.href; a.textContent = link.label; }
      document.getElementById('notification-modal-dismiss').onclick = function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('notification-modal')).hide();
        window.dismissNotification(n.id);
      };
      // Close any open bell dropdown so it doesn't sit behind/over the modal
      document.querySelectorAll('.dropdown-menu.show').forEach(function (m) {
        const t = m.previousElementSibling;
        if (t && bootstrap.Dropdown.getInstance(t)) bootstrap.Dropdown.getInstance(t).hide();
      });
      bootstrap.Modal.getOrCreateInstance(document.getElementById('notification-modal')).show();
    };

    function renderNotifications(notifications) {
      current = notifications || [];
      const badges = document.querySelectorAll('.js-notification-badge');
      const lists = document.querySelectorAll('.js-notification-list');
      const has = notifications && notifications.length > 0;

      badges.forEach(function (badge) {
        badge.textContent = has ? notifications.length : '';
        badge.classList.toggle('d-none', !has);
      });

      const html = has ? notifications.map(function (n) {
        return '<div class="list-group-item">'
          + '<div class="row align-items-center">'
          + '<div class="col-auto"><span class="badge ' + levelBadgeClass(n.level) + '">&nbsp;</span></div>'
          + '<div class="col text-truncate" role="button" title="View full notification" onclick="window.openNotification(' + n.id + ')">'
          + '<div class="fw-bold">' + escapeHtml(n.title) + '</div>'
          + '<div class="text-secondary small">' + escapeHtml(n.message) + '</div>'
          + '</div>'
          + '<div class="col-auto">'
          + '<button type="button" class="btn btn-icon btn-sm" aria-label="Dismiss" onclick="window.dismissNotification(' + n.id + ')">'
          + '<i class="ti ti-x"></i>'
          + '</button>'
          + '</div>'
          + '</div>'
          + '</div>';
      }).join('') : EMPTY;

      lists.forEach(function (list) { list.innerHTML = html; });
    }

    async function loadNotifications() {
      try {
        const notifications = await authenticatedFetch('/notifications');
        renderNotifications(notifications);
      } catch (e) {
        console.error('Failed to load notifications', e);
      }
    }

    window.dismissNotification = async function (id) {
      try {
        await authenticatedFetch('/notifications/' + id + '/dismiss', { method: 'POST' });
        loadNotifications();
      } catch (e) {
        console.error('Failed to dismiss notification', e);
      }
    };

    document.addEventListener('DOMContentLoaded', function () {
      // Rendered inside the nav list; modals must live on <body> to overlay the whole page
      document.body.appendChild(document.getElementById('notification-modal'));
      loadNotifications();
      setInterval(loadNotifications, 60000);
    });
  })();
</script>
@endonce
@endif
