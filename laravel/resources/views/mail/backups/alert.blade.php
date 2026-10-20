@component('mail::message')
# {{ $title }}

{{ $detail }}

@if(!empty($components))
**Latest run**

@foreach($components as $name => $c)
- {{ $name }}: {{ ($c['success'] ?? false) ? 'OK' : 'FAILED' }}@if(!empty($c['message'])) — {{ $c['message'] }}@endif

@endforeach
@endif

@component('mail::button', ['url' => $url])
Open Status Page
@endcomponent

Regards,
The ForgeDesk Team
@endcomponent
