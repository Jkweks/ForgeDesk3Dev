{{-- Top (horizontal) navigation: brand + utilities bar, then the menu row. Both are
     direct children of .page, which Tabler hides when the user picks the sidebar. --}}
<a href="#content" class="visually-hidden-focusable skip-link">Skip to main content</a>
<header class="navbar navbar-expand-md d-print-none">
  <div class="container-xl">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3">
      <a href="/" aria-label="ForgeDesk home">@include('partials.brand')</a>
    </div>
    <div class="navbar-nav flex-row order-md-last">
      @include('partials.notifications', ['placement' => 'top'])
      <div class="nav-item d-none d-md-flex me-3">
        <a href="#" class="nav-link px-0" title="Customize" aria-label="Customize" data-bs-toggle="offcanvas" data-bs-target="#offcanvasTheme" aria-controls="offcanvasTheme">
          <i class="ti ti-palette icon"></i>
        </a>
      </div>
      @include('partials.user-menu', ['placement' => 'top'])
    </div>
  </div>
</header>
<div class="navbar-expand-md">
  <div class="collapse navbar-collapse" id="navbar-menu">
    <div class="navbar">
      <div class="container-xl">
        <div class="row flex-column flex-md-row flex-fill align-items-center">
          <div class="col">
            <nav aria-label="Primary">
              @include('partials.nav-menu', ['mode' => 'top', 'set' => 'new'])
              @include('partials.nav-menu', ['mode' => 'top', 'set' => 'classic'])
            </nav>
          </div>
          <div class="col col-md-auto d-md-none">
            <ul class="navbar-nav">
              <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasTheme" aria-controls="offcanvasTheme">
                  <span class="nav-link-icon"><i class="ti ti-palette icon"></i></span>
                  <span class="nav-link-title">Customize</span>
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
