{{--
  Signed-in user dropdown. Rendered in the top navbar (placement 'top') and in the sidebar
  footer (placement 'side'); both copies are filled/wired by partials/auth-scripts via
  js-* classes. The sidebar copy follows Tabler's footer structure so its text (the
  .nav-link-title) collapses to just the avatar when the sidebar is folded.

  @param string $placement  'top' (default) or 'side'
--}}
@php $placement = $placement ?? 'top'; @endphp
@if ($placement === 'side')
<li class="nav-item dropup">
  <a href="#" class="nav-link" data-bs-toggle="dropdown" aria-label="Open user menu">
    <span class="avatar avatar-sm js-user-avatar">{{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}</span>
    <div class="nav-link-title lh-1">
      <div class="js-user-name">{{ Auth::user()->name ?? 'Admin' }}</div>
      <div class="mt-1 small text-secondary js-user-email">{{ Auth::user()->email ?? 'admin@forgedesk.local' }}</div>
    </div>
  </a>
  <div class="dropdown-menu">
    <a href="/status" class="dropdown-item">Status</a>
    <a href="#" class="dropdown-item">Profile</a>
    <div class="dropdown-divider"></div>
    <a href="#" class="dropdown-item">Settings</a>
    <a href="#" class="dropdown-item js-logout">Logout</a>
  </div>
</li>
@else
<div class="nav-item dropdown">
  <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Open user menu">
    <span class="avatar avatar-sm js-user-avatar">{{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}</span>
    <div class="d-none d-xl-block ps-2">
      <div class="js-user-name">{{ Auth::user()->name ?? 'Admin' }}</div>
      <div class="mt-1 small text-secondary js-user-email">{{ Auth::user()->email ?? 'admin@forgedesk.local' }}</div>
    </div>
  </a>
  <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
    <a href="/status" class="dropdown-item">Status</a>
    <a href="#" class="dropdown-item">Profile</a>
    <div class="dropdown-divider"></div>
    <a href="#" class="dropdown-item">Settings</a>
    <a href="#" class="dropdown-item js-logout">Logout</a>
  </div>
</div>
@endif
