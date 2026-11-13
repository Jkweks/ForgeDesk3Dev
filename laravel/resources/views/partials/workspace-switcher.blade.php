{{-- Workspace switch: a binary slide. Off = Fabrication (back of house), on = Project Management
     (front of house, /pm/*). Shown only to users who can see both. Sits on the same line as the
     Customize button (sidebar footer; top navbar utility bar). docs/plans/pm-suite-integration.md 4d. --}}
@php
  $wsUser = Auth::user();
  $wsInPm = Request::is('pm', 'pm/*');
  $wsHasPm = $wsUser && $wsUser->hasPermission('nav.pm');
  $wsHasFab = $wsUser && ($wsUser->isAdmin() || $wsUser->hasAnyPermission(['nav.dashboard', 'nav.inventory', 'nav.fulfillment', 'nav.fabrication']));
@endphp
@if ($wsHasPm && $wsHasFab)
  <style>.navbar-vertical .workspace-switcher .ws-label{display:none}.navbar-vertical.navbar-folded .workspace-switcher{flex-direction:column}</style>
  <div class="workspace-switcher d-flex align-items-center gap-2" role="group" aria-label="Workspace">
    <span class="small {{ $wsInPm ? 'text-muted' : 'fw-bold' }}" title="Fabrication"><i class="ti ti-hammer"></i><span class="ws-label ms-1">Fab</span></span>
    <label class="form-check form-switch m-0 p-0 d-flex align-items-center" title="Switch workspace: Fabrication / Project Management">
      <input class="form-check-input m-0" type="checkbox" role="switch" style="margin-left:0"
             aria-label="Project Management workspace" @checked($wsInPm)
             onchange="window.location.href = this.checked ? '/pm' : '/'">
    </label>
    <span class="small {{ $wsInPm ? 'fw-bold' : 'text-muted' }}" title="Project Management"><i class="ti ti-briefcase"></i><span class="ws-label ms-1">PM</span></span>
  </div>
@endif
