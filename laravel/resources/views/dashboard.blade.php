@extends('layouts.app')

@section('title', 'Dashboard - ForgeDesk')

@section('content')
    {{-- layouts.app's `styles` section lives inside a <style> block, so the vendored CSS is linked here. --}}
    <link rel="stylesheet" href="/assets/gridstack/gridstack.min.css">
    <style>
      /* Widgets fill their grid cell; the remove (x) only shows while editing. */
      #dashboardGrid .grid-stack-item-content { overflow: hidden; inset: 4px; }
      #dashboardGrid .dash-widget-body { position: relative; min-height: 0; }
      #dashboardGrid .dash-chart { position: absolute; inset: 0; }
      #dashboardGrid .dash-table thead th { position: sticky; top: 0; background: var(--tblr-bg-surface, #fff); z-index: 1; }
      #dashboardGrid .dash-table tbody tr[data-href] { cursor: pointer; }
      #dashboardGrid .dash-remove, #dashboardGrid .dash-settings { display: none; }
      #dashboardGrid.dash-editing .dash-remove { display: inline-block; }
      #dashboardGrid.dash-editing .dash-settings { display: inline-flex; }
      #dashboardGrid.dash-editing .grid-stack-item-content { outline: 2px dashed var(--tblr-border-color, #ccc); outline-offset: -2px; cursor: move; }
    </style>
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Overview</div>
              <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="col-auto ms-auto d-print-none">
              {{-- Editing is desktop-only: on phones the grid collapses to one column. --}}
              <div id="dashViewButtons" class="btn-list d-none d-md-flex">
                <button type="button" class="btn" id="dashCustomize">
                  <i class="ti ti-layout-dashboard me-1"></i>Customize
                </button>
              </div>
              <div id="dashEditButtons" class="btn-list d-none">
                <button type="button" class="btn" id="dashAddWidget"><i class="ti ti-plus me-1"></i>Add widget</button>
                <button type="button" class="btn btn-outline-secondary" id="dashReset">Reset</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="dashSaveDefault">Save as default</button>
                <button type="button" class="btn" id="dashCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="dashSave">Save</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="page-body">
        <div class="container-xl">
          <div id="dashboardGrid" class="grid-stack"></div>
          <div id="dashboardEmpty" class="empty d-none">
            <p class="empty-title">No widgets yet</p>
            <p class="empty-subtitle text-secondary">Click Customize, then Add widget to build your dashboard.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="modal modal-blur fade" id="dashSettingsModal" tabindex="-1" aria-labelledby="dashSettingsTitle" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="dashSettingsTitle">Widget settings</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="dashSettingsBody"></div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="dashSettingsApply">Apply</button>
          </div>
        </div>
      </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="dashCatalogPanel" aria-labelledby="dashCatalogTitle">
      <div class="offcanvas-header">
        <h2 class="offcanvas-title" id="dashCatalogTitle">Add widget</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <input type="search" class="form-control" id="dashCatalogSearch" placeholder="Search widgets…" autocomplete="off">
        <div id="dashCatalogList"></div>
      </div>
    </div>
@endsection

@push('scripts')
<script src="/assets/gridstack/gridstack-all.js"></script>
<script src="/assets/chartjs/chart.umd.min.js"></script>
<script src="/js/dashboard-widgets.js"></script>
@endpush
