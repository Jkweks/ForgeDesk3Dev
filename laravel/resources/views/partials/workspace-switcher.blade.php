{{-- Workspace switcher: Project Management (front of house, /pm/*) vs Fabrication (back of house).
     Shown only to users who can see both. See docs/plans/pm-suite-integration.md section 4d. --}}
@php
  $wsUser = Auth::user();
  $wsInPm = Request::is('pm', 'pm/*');
  $wsHasPm = $wsUser && $wsUser->hasPermission('nav.pm');
  $wsHasFab = $wsUser && ($wsUser->isAdmin() || $wsUser->hasAnyPermission(['nav.dashboard', 'nav.inventory', 'nav.fulfillment', 'nav.fabrication']));
@endphp
@if ($wsHasPm && $wsHasFab)
  <div class="btn-group btn-group-sm workspace-switcher {{ ! empty($vertical) ? 'w-100 my-2 px-2' : 'mx-md-2' }}" role="group" aria-label="Workspace">
    <a href="/pm" class="btn {{ $wsInPm ? 'btn-primary' : 'btn-outline-secondary' }}" @if ($wsInPm) aria-current="page" @endif>
      <i class="ti ti-briefcase me-1"></i>Project Management
    </a>
    <a href="/" class="btn {{ $wsInPm ? 'btn-outline-secondary' : 'btn-primary' }}" @unless ($wsInPm) aria-current="page" @endunless>
      <i class="ti ti-hammer me-1"></i>Fabrication
    </a>
  </div>
@endif
