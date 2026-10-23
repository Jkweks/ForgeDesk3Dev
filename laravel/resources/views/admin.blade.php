@extends('layouts.app')

@section('title', 'Admin Panel - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">System Administration</div>
              <h1 class="page-title">Admin</h1>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <div class="row g-3">
            <!-- Section navigation -->
            <div class="col-lg-3 col-xl-2">
              <div class="card admin-nav-card">
                <div class="list-group list-group-flush" id="adminNav" role="tablist">
                  <div class="admin-nav-label">People &amp; access</div>
                  <a href="#tab-users" class="list-group-item list-group-item-action active" data-bs-toggle="tab" role="tab" data-title="Users" data-stats="1">
                    <i class="ti ti-users me-2"></i>Users
                  </a>
                  <a href="#tab-permissions" class="list-group-item list-group-item-action" data-bs-toggle="tab" role="tab" data-title="Roles &amp; Permissions" data-stats="1">
                    <i class="ti ti-shield-lock me-2"></i>Roles &amp; Permissions
                  </a>
                  <div class="admin-nav-label">Company</div>
                  <a href="#tab-settings" class="list-group-item list-group-item-action" data-bs-toggle="tab" role="tab" data-title="System Settings">
                    <i class="ti ti-settings me-2"></i>System Settings
                  </a>
                  <div class="admin-nav-label">Inventory</div>
                  <a href="#tab-inventory" class="list-group-item list-group-item-action" data-bs-toggle="tab" role="tab" data-title="Inventory Pricing">
                    <i class="ti ti-package me-2"></i>Pricing Management
                  </a>
                  <div class="admin-nav-label">Fabrication</div>
                  <a href="#tab-elevation-types" class="list-group-item list-group-item-action" data-bs-toggle="tab" role="tab" data-title="Elevation Types &amp; Job Steps"
                     onclick="loadElevationTypes(); loadJobStepTemplates()">
                    <i class="ti ti-ruler-2 me-2"></i>Elevation Types &amp; Steps
                  </a>
                  <a href="#tab-fab-users" class="list-group-item list-group-item-action" data-bs-toggle="tab" role="tab" data-title="Fab Users"
                     onclick="loadFabUsersAdmin()">
                    <i class="ti ti-hard-hat me-2"></i>Fab Users
                  </a>
                </div>
              </div>
            </div>

            <!-- Content -->
            <div class="col-lg-9 col-xl-10">
              <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
                <h2 class="mb-0" id="adminSectionTitle">Users</h2>
                <div class="ms-auto d-flex gap-2" id="adminStatStrip">
                  <span class="badge bg-secondary-lt fs-5 fw-normal"><span id="statTotalUsers">-</span> users</span>
                  <span class="badge bg-green-lt fs-5 fw-normal"><span id="statActiveUsers">-</span> active</span>
                  <span class="badge bg-blue-lt fs-5 fw-normal"><span id="statAdminUsers">-</span> admins</span>
                  <span class="badge bg-purple-lt fs-5 fw-normal"><span id="statTotalRoles">-</span> roles</span>
                </div>
              </div>
              <div class="card">
                <div class="card-body">
                  <div class="tab-content">
                    @include('admin.people')
                    @include('admin.settings')
                    @include('admin.pricing')
                    @include('admin.elevation')
                    @include('admin.fabusers')



                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

@push('scripts')
<script>

      // Bootstrap Modal helpers
      function showModal(element) {
        const modal = new bootstrap.Modal(element);
        modal.show();
      }

      function hideModal(element) {
        const modal = bootstrap.Modal.getInstance(element);
        if (modal) modal.hide();
      }

      // Notification helper
      function showNotification(message, type = 'success') {
        const bgColors = {
          success: 'bg-success',
          danger: 'bg-danger',
          warning: 'bg-warning',
          info: 'bg-info'
        };

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white ${bgColors[type] || 'bg-success'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
          <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
        `;

        let container = document.getElementById('toastContainer');
        if (!container) {
          container = document.createElement('div');
          container.id = 'toastContainer';
          container.className = 'toast-container position-fixed top-0 end-0 p-3';
          container.style.zIndex = '9999';
          document.body.appendChild(container);
        }

        container.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 3000 });
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => {
          toast.remove();
        });
      }


      function adminCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
      }

      function locEscHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
      }

      document.addEventListener('shown.bs.tab', (e) => {
        const link = e.target.closest('#adminNav a');
        if (!link) return;
        document.getElementById('adminSectionTitle').textContent = link.dataset.title || link.textContent.trim();
        document.getElementById('adminStatStrip').style.display = link.dataset.stats ? '' : 'none';
        try { history.replaceState(null, '', link.getAttribute('href')); } catch (err) { /* ignore */ }
      });


      document.addEventListener('DOMContentLoaded', () => {
        // Deep link support, e.g. /admin#tab-settings
        if (location.hash && document.querySelector(`a[href="${location.hash}"]`)) {
          try {
            new bootstrap.Tab(document.querySelector(`a[href="${location.hash}"]`)).show();
          } catch (e) { /* bootstrap not ready yet — tab still reachable by click */ }
        }
      });

</script>
@endpush

    <style>
      .admin-nav-card { position: sticky; top: calc(var(--fd-nav-offset, 0px) + 1rem); overflow: hidden; }
      .admin-nav-label {
        font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 600;
        color: var(--tblr-secondary-color); padding: 12px 16px 4px;
      }
      .admin-nav-label:first-child { padding-top: 10px; }
      #adminNav .list-group-item { border: 0; padding: 8px 16px; margin: 0; white-space: normal; }
      #adminNav .list-group-item.active { background: var(--tblr-primary-lt); color: var(--tblr-primary); font-weight: 500; }
      #adminNav .list-group-item .ti { width: 1.25rem; text-align: center; }
      @media (max-width: 991.98px) {
        .admin-nav-card { position: static; }
        #adminNav { flex-direction: row; flex-wrap: wrap; padding: 6px; }
        .admin-nav-label { display: none; }
        #adminNav .list-group-item { border-radius: var(--tblr-border-radius); }
      }
      .form-check {
        margin-bottom: 0.5rem;
      }

      .loading {
        padding: 2rem;
        text-align: center;
      }
    </style>
@endsection
