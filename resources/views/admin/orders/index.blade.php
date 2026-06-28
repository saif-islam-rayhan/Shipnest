@extends('layouts.admin')
@section('title','Orders') @section('page-title','Order Management')
@section('content')
<form method="GET" class="flex flex-wrap gap-2 mb-4">
    <input name="search" value="{{ request('search') }}" placeholder="Order #" class="input-field w-36">
    <select name="status" class="input-field w-32"><option value="">Status</option>@foreach(['pending','confirmed','shipped','delivered','cancelled'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select>
    <select name="merchant_id" class="input-field w-40"><option value="">All Merchants</option>@foreach($merchants as $m)<option value="{{ $m->id }}" @selected(request('merchant_id')==$m->id)>{{ $m->shop_name }}</option>@endforeach</select>
    <select name="payment_method" class="input-field w-32"><option value="">Payment</option>@foreach(['cod','bkash','nagad','sslcommerz','stripe'] as $m)<option value="{{ $m }}" @selected(request('payment_method')===$m)>{{ strtoupper($m) }}</option>@endforeach</select>
    <input type="date" name="from" value="{{ request('from') }}" class="input-field w-36"><input type="date" name="to" value="{{ request('to') }}" class="input-field w-36">
    <button class="btn-primary">Filter</button>
</form>
<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
<table class="admin-datatable w-full text-sm"><thead class="bg-gray-50"><tr>
    <th class="px-4 py-3 text-left">Order</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Merchant</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
</tr></thead><tbody>@foreach($orders as $order)<tr>
    <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="text-[#F57C00] font-medium">#{{ $order->order_number }}</a></td>
    <td class="px-4 py-3">{{ $order->user->name }}</td>
    <td class="px-4 py-3">{{ $order->shop?->shop_name ?? '—' }}</td>
    <td class="px-4 py-3">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</td>
    <td class="px-4 py-3">{{ $order->status->label() }}</td>
    <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="text-xs">View</a></td>
</tr>@endforeach</tbody></table></div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
