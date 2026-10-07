{{--
  Signed-in user dropdown (top navbar and sidebar footer). Uses js-* classes so
  both copies are filled/wired by partials/auth-scripts.

  @param string $placement  'top' (default) or 'side'
--}}
@php $placement = $placement ?? 'top'; @endphp
<div class="nav-item dropdown {{ $placement === 'side' ? 'dropup' : '' }}">
  <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Open user menu">
    <span class="avatar avatar-sm js-user-avatar">{{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}</span>
    <div class="{{ $placement === 'side' ? '' : 'd-none d-xl-block' }} ps-2">
      <div class="js-user-name">{{ Auth::user()->name ?? 'Admin' }}</div>
      <div class="mt-1 small text-secondary js-user-email">{{ Auth::user()->email ?? 'admin@forgedesk.local' }}</div>
    </div>
  </a>
  <div class="dropdown-menu {{ $placement === 'side' ? '' : 'dropdown-menu-end dropdown-menu-arrow' }}">
    <a href="/status" class="dropdown-item">Status</a>
    <a href="#" class="dropdown-item">Profile</a>
    <div class="dropdown-divider"></div>
    <a href="#" class="dropdown-item">Settings</a>
    <a href="#" class="dropdown-item js-logout">Logout</a>
  </div>
</div>
