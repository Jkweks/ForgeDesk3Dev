@component('mail::message')
# Special Orders Received

All special-order items for **{{ trim(($jobNumber ?? '').' R'.($release ?? '').' '.($jobName ?? '')) }}** are now in stock.

@if($neededBy)
- **Needed by:** {{ $neededBy }}
@endif

@foreach($items as $item)
- {{ $item['sku'] }} — {{ $item['description'] }} (qty {{ rtrim(rtrim(number_format($item['qty'], 1), '0'), '.') }})
@endforeach

@component('mail::button', ['url' => $url])
Open Reservations
@endcomponent

Regards,
The ForgeDesk Team
@endcomponent
