@component('mail::message')
# Purchase Order Awaiting Your Approval

**{{ $poNumber }}** has been submitted and is waiting on your approval in ForgeDesk.

@if($supplierName)
- **Supplier:** {{ $supplierName }}
@endif
@if($orderDate)
- **Order date:** {{ $orderDate }}
@endif
- **Total:** ${{ number_format((float) $totalAmount, 2) }}
@if($submittedBy)
- **Submitted by:** {{ $submittedBy }}
@endif

@component('mail::button', ['url' => $url])
Review Purchase Order
@endcomponent

Regards,
The ForgeDesk Team
@endcomponent
