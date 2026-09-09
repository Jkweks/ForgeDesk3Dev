@component('mail::message')
# Work Order Completed

**{{ $label }}** has been marked complete in ForgeDesk.

@if($jobNumber || $jobName)
- **Job:** {{ trim(($jobNumber ? $jobNumber.' — ' : '').($jobName ?? '')) }}
@endif
@if($completedAt)
- **Completed:** {{ $completedAt }}
@endif
@if($completedBy)
- **Confirmed by:** {{ $completedBy }}
@endif

@if($note)
**Notes**

> {{ $note }}
@endif

@component('mail::button', ['url' => $url])
Open Work Orders
@endcomponent

Regards,
The ForgeDesk Team
@endcomponent
