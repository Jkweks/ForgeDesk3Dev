<div class="navbar-expand-md">
  <div class="collapse navbar-collapse" id="navbar-menu">
    <div class="navbar">
      <div class="container-xl">
        <div class="row flex-column flex-md-row flex-fill align-items-center">
          <div class="col">
            <nav aria-label="Primary">
              <ul class="navbar-nav">
                <li class="nav-item {{ Request::is('/') ? 'active' : '' }}" data-nav-permission="nav.dashboard">
                  <a class="nav-link" href="/" {{ Request::is('/') ? 'aria-current=page' : '' }}>
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l-2 0l9 -9l9 9l-2 0" /><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" /><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" /></svg>
                    </span>
                    <span class="nav-link-title">Dashboard</span>
                  </a>
                </li>
                <li class="nav-item dropdown" data-nav-permission="nav.inventory">
                  <a class="nav-link dropdown-toggle" href="#navbar-inventory" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /></svg>
                    </span>
                    <span class="nav-link-title">Inventory</span>
                  </a>
                  <div class="dropdown-menu">
                    <a class="dropdown-item" href="/">All Products</a>
                    <a class="dropdown-item" href="/low-stock">Low Stock</a>
                    <a class="dropdown-item" href="/critical-stock">Critical Stock</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/transactions">Transaction History</a>
                    <a class="dropdown-item" href="/categories">Categories</a>
                    <a class="dropdown-item" href="/suppliers">Suppliers</a>
                  </div>
                </li>
                <li class="nav-item dropdown {{ Request::is('purchase-orders') || Request::is('cycle-counting') ? 'active' : '' }}" data-nav-permission="nav.operations">
                  <a class="nav-link dropdown-toggle" href="#navbar-operations" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 9l5 5v7h-5v-4m0 4h-5v-7l5 -5m1 1v-6a1 1 0 0 1 1 -1h10a1 1 0 0 1 1 1v17h-8" /><path d="M13 7l0 .01" /><path d="M17 7l0 .01" /><path d="M17 11l0 .01" /><path d="M17 15l0 .01" /></svg>
                    </span>
                    <span class="nav-link-title">Operations</span>
                  </a>
                  <div class="dropdown-menu">
                    <a class="dropdown-item" href="/purchase-orders">
                      <i class="ti ti-shopping-cart me-2"></i>Purchase Orders
                    </a>
                    <a class="dropdown-item" href="/cycle-counting">
                      <i class="ti ti-clipboard-check me-2"></i>Cycle Counting
                    </a>
                    <a class="dropdown-item" href="/storage-locations">
                      <i class="ti ti-map-pin me-2"></i>Storage Locations
                    </a>
                  </div>
                </li>
                <li class="nav-item dropdown {{ Request::is('fulfillment*') ? 'active' : '' }}" data-nav-permission="nav.fulfillment">
                  <a class="nav-link dropdown-toggle" href="#navbar-fulfillment" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 10l5 -6l5 6" /><path d="M21 10l-2 8a2 2.5 0 0 1 -2 2h-10a2 2.5 0 0 1 -2 -2l-2 -8z" /><path d="M12 15m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /></svg>
                    </span>
                    <span class="nav-link-title">Fulfillment</span>
                  </a>
                  <div class="dropdown-menu">
                    <a class="dropdown-item" href="/jobs">
                      <i class="ti ti-briefcase me-2"></i>Jobs Dashboard
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/fulfillment/material-check">
                      <i class="ti ti-checklist me-2"></i>Material Check
                    </a>
                    <a class="dropdown-item" href="/fulfillment/job-reservations">
                      <i class="ti ti-clipboard-list me-2"></i>Job Reservations
                    </a>
                  </div>
                </li>
                <li class="nav-item {{ Request::is('reports') ? 'active' : '' }}" data-nav-permission="nav.reports">
                  <a class="nav-link" href="/reports" {{ Request::is('reports') ? 'aria-current=page' : '' }}>
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M9 8m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M15 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M4 20l14 0" /></svg>
                    </span>
                    <span class="nav-link-title">Reports</span>
                  </a>
                </li>
                <li class="nav-item dropdown {{ Request::is('maintenance*') ? 'active' : '' }}" data-nav-permission="nav.maintenance">
                  <a class="nav-link dropdown-toggle" href="#navbar-maintenance" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 10h3v-3l-3.5 -3.5a6 6 0 0 1 8 8l6 6a2 2 0 0 1 -3 3l-6 -6a6 6 0 0 1 -8 -8l3.5 3.5" /></svg>
                    </span>
                    <span class="nav-link-title">Maintenance</span>
                  </a>
                  <div class="dropdown-menu">
                    <a class="dropdown-item" href="/maintenance">Maintenance Hub</a>
                    <a class="dropdown-item" href="/maintenance#tab-machines">Machines</a>
                    <a class="dropdown-item" href="/maintenance#tab-tasks">Tasks</a>
                    <a class="dropdown-item" href="/maintenance#tab-records">Service Log</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/maintenance#tab-assets">Assets</a>
                  </div>
                </li>
                <li class="nav-item {{ Request::is('admin*') ? 'active' : '' }}" data-nav-permission="nav.admin">
                  <a class="nav-link" href="/admin" {{ Request::is('admin') ? 'aria-current=page' : '' }}>
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M12 7a5 5 0 1 1 -4.995 5.217l-.005 -.217l.005 -.217a5 5 0 0 1 4.995 -4.783z" /><path d="M12 19l-1 2" /><path d="M16 22l-4 -2" /><path d="M12 13l-2 1" /><path d="M12 5v-2" /><path d="M16 2l-4 2" /><path d="M12 11l2 -1" /></svg>
                    </span>
                    <span class="nav-link-title">Admin</span>
                  </a>
                </li>
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
