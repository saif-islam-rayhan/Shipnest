<x-mail::message>
# Order Confirmed

Hi {{ $order->user->name }},

Thank you for your order! We've received **{{ $order->order_number }}** and will process it shortly.

**Estimated delivery:** {{ $estimatedDelivery }}

<x-mail::table>
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | {{ config('shipnest.currency_symbol') }}{{ number_format($item->total) }} |
@endforeach
</x-mail::table>

**Order total:** {{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}

<x-mail::button :url="route('account.orders.show', $order->order_number)">
View Order
</x-mail::button>

Thanks,<br>
{{ config('shipnest.name') }}
</x-mail::message>
