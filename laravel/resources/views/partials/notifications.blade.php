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
      return 'bg-info';
    }

    const EMPTY = '<div class="list-group-item"><div class="row align-items-center"><div class="col text-truncate"><div class="text-secondary">No new notifications</div></div></div></div>';

    function renderNotifications(notifications) {
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
          + '<div class="col text-truncate">'
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
      loadNotifications();
      setInterval(loadNotifications, 60000);
    });
  })();
</script>
@endonce
@endif
