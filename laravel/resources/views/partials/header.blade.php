<a href="#content" class="visually-hidden skip-link">Skip to main content</a>
<header class="navbar navbar-expand-md d-print-none">
  <div class="container-xl">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <h1 class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3">
      <a href="/">
        <svg xmlns="http://www.w3.org/2000/svg" width="110" height="32" viewBox="0 0 232 68" class="navbar-brand-image">
          <text x="10" y="50" font-family="Arial, sans-serif" font-size="48" font-weight="bold" fill="var(--tblr-primary, #066fd1)">FD</text>
        </svg>
      </a>
    </h1>
    <div class="navbar-nav flex-row order-md-last">
      <div class="nav-item dropdown d-none d-md-flex me-3">
        <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1" aria-label="Show notifications">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>
          <span class="badge text-bg-red"></span>
        </a>
        <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end dropdown-menu-card">
          <div class="card">
            <div class="card-header d-flex">
              <h3 class="card-title">Notifications</h3>
              <div class="btn-close ms-auto" data-bs-dismiss="dropdown"></div>
            </div>
            <div class="list-group list-group-flush list-group-hoverable">
              <div class="list-group-item">
                <div class="row align-items-center">
                  <div class="col text-truncate">
                    <div class="text-muted">No new notifications</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="nav-item d-none d-md-flex me-3">
        <a href="#" class="nav-link px-0" title="Theme settings" data-bs-toggle="offcanvas" data-bs-target="#offcanvasTheme" aria-controls="offcanvasTheme">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 21a9 9 0 0 1 0 -18c4.97 0 9 3.582 9 8c0 1.06 -.474 2.078 -1.318 2.828c-.844 .75 -1.989 1.172 -3.182 1.172h-2.5a2 2 0 0 0 -1 3.75a1.3 1.3 0 0 1 -1 2.25" /><path d="M8.5 10.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M12.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M16.5 10.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /></svg>
        </a>
      </div>
      <div class="nav-item dropdown">
        <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Open user menu">
          <span class="avatar avatar-sm" id="userAvatar">{{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}</span>
          <div class="d-none d-xl-block ps-2">
            <div id="userName">{{ Auth::user()->name ?? 'Admin' }}</div>
            <div class="mt-1 small text-secondary" id="userEmail">{{ Auth::user()->email ?? 'admin@forgedesk.local' }}</div>
          </div>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
          <a href="/status" class="dropdown-item">Status</a>
          <a href="#" class="dropdown-item">Profile</a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">Settings</a>
          <a href="#" class="dropdown-item" id="logoutBtn">Logout</a>
        </div>
      </div>
    </div>
  </div>
</header>
