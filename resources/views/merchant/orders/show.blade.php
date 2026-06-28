@extends('layouts.merchant')

@section('title', 'Order #'.$order->order_number)
@section('page-title', 'Order #'.$order->order_number)

@section('content')
<div class="mb-4">
    <a href="{{ route('merchant.orders.index') }}" class="text-sm text-[#F57C00] hover:underline">&larr; Back to Orders</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
            <h2 class="font-semibold mb-4">Order Items</h2>
            @foreach($order->items as $item)
                <div class="flex gap-4 py-3 border-b last:border-0">
                    <div class="w-14 h-14 rounded bg-gray-100 overflow-hidden">
                        @if($item->product_image)
                            <img src="{{ asset('storage/'.$item->product_image) }}" class="w-full h-full object-cover">
                        @endif
                    </div>
                    <div class="flex-1">
                        <p class="font-medium">{{ $item->product_name }}</p>
                        <p class="text-sm text-gray-500">Qty: {{ $item->quantity }} × {{ config('shipnest.currency_symbol') }}{{ number_format($item->unit_price) }}</p>
                    </div>
                    <p class="font-medium">{{ config('shipnest.currency_symbol') }}{{ number_format($item->total_price) }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
            <h2 class="font-semibold mb-3">Shipping Address</h2>
            @if($order->shippingAddress)
                <p class="text-sm text-gray-600">
                    {{ $order->shippingAddress->recipient_name }}<br>
                    {{ $order->shippingAddress->full_address }}<br>
                    {{ $order->shippingAddress->phone }}
                </p>
            @else
                <p class="text-sm text-gray-500">No address.</p>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
            <h2 class="font-semibold mb-3">Customer</h2>
            <p class="font-medium">{{ $order->user->name }}</p>
            <p class="text-sm text-gray-500">{{ $order->user->email }}</p>
            @if($order->user->phone)<p class="text-sm text-gray-500">{{ $order->user->phone }}</p>@endif
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
            <h2 class="font-semibold mb-3">Status</h2>
            <p class="mb-3">
                <span class="inline-flex px-2 py-1 rounded-full text-sm bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800">{{ $order->status->label() }}</span>
            </p>
            <form action="{{ route('merchant.orders.status', $order) }}" method="POST" class="space-y-2">
                @csrf @method('PATCH')
                <select name="status" class="input-field text-sm">
                    @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
                        <option value="{{ $s }}" @selected($order->status->value === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-primary w-full text-sm">Update Status</button>
            </form>
            @if($order->status->value === 'pending')
                <form action="{{ route('merchant.orders.confirm', $order) }}" method="POST" class="mt-2">@csrf
                    <button class="btn-primary w-full text-sm">Confirm Order</button>
                </form>
            @endif
            @if($order->status->value === 'confirmed')
                <form action="{{ route('merchant.orders.ready', $order) }}" method="POST" class="mt-2">@csrf
                    <button class="btn-outline w-full text-sm">Mark Ready for Pickup</button>
                </form>
            @endif
            <a href="{{ route('merchant.orders.invoice', $order) }}" class="btn-outline w-full text-sm mt-2 inline-block text-center">Print Invoice</a>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5 text-sm space-y-2">
            <div class="flex justify-between"><span class="text-gray-500">Payment</span><span>{{ $order->payment_method->label() }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Payment Status</span><span>{{ $order->payment_status->label() }}</span></div>
            <div class="flex justify-between font-bold pt-2 border-t"><span>Total</span><span class="text-[#F57C00]">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</span></div>
        </div>
    </div>
</div>
@endsection
