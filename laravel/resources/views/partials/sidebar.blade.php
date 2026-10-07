{{-- Vertical navigation. Rendered alongside the top navbar; Tabler shows it when
     html[data-bs-navbar-position=vertical]. Fold/pin toggle uses data-bs-toggle="sidebar-folded". --}}
<aside class="navbar navbar-vertical navbar-expand-lg d-print-none">
  <div class="container-fluid">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="navbar-brand navbar-brand-autodark">
      <a href="/" aria-label="ForgeDesk home">@include('partials.brand')</a>
      <button type="button" class="btn btn-action btn-sm d-none d-lg-inline-flex ms-auto" data-bs-toggle="sidebar-folded" aria-label="Fold sidebar" title="Fold sidebar">
        <i class="ti ti-layout-sidebar-left-collapse icon"></i>
      </button>
    </div>
    <div class="navbar-footer">
      <ul class="navbar-nav">
        @include('partials.notifications', ['placement' => 'side'])
        @include('partials.user-menu', ['placement' => 'side'])
      </ul>
    </div>
    <div class="collapse navbar-collapse" id="sidebar-menu">
      @include('partials.nav-menu', ['mode' => 'side'])
      <div class="navbar-side">
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
</aside>
