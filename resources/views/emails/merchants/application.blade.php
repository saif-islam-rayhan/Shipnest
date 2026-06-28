<x-mail::message>
@if($action === 'approved')
# Shop Approved!

Hi {{ $merchant->owner->name ?? 'Merchant' }},

Great news! Your shop **{{ $merchant->shop_name }}** has been approved on {{ config('shipnest.name') }}.

You can now access your seller panel and start listing products.

<x-mail::button :url="route('merchant.dashboard')">
Go to Seller Center
</x-mail::button>
@else
# Application Not Approved

Hi {{ $merchant->owner->name ?? 'Merchant' }},

Unfortunately, your shop application for **{{ $merchant->shop_name }}** was not approved at this time.

@if($reason)
**Reason:** {{ $reason }}
@endif

If you have questions, please contact {{ config('shipnest.support_email') }}.
@endif

Thanks,<br>
{{ config('shipnest.name') }} Team
</x-mail::message>
