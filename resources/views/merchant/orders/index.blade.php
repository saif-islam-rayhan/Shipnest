@extends('layouts.merchant')

@section('title', 'Orders')
@section('page-title', 'Orders')

@section('content')
<form method="GET" class="flex flex-wrap gap-2 mb-6">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Order number..." class="input-field w-44">
    <select name="status" class="input-field w-36">
        <option value="">All Status</option>
        @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" class="input-field w-36">
    <input type="date" name="to" value="{{ request('to') }}" class="input-field w-36">
    <button type="submit" class="btn-primary">Filter</button>
</form>

<div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-5 py-3">Order</th>
                <th class="px-5 py-3">Customer</th>
                <th class="px-5 py-3">Date</th>
                <th class="px-5 py-3">Items</th>
                <th class="px-5 py-3">Total</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($orders as $order)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium">#{{ $order->order_number }}</td>
                    <td class="px-5 py-3">{{ $order->user->name }}</td>
                    <td class="px-5 py-3 text-gray-500">{{ $order->created_at->format('M d, Y') }}</td>
                    <td class="px-5 py-3">{{ $order->items_count }}</td>
                    <td class="px-5 py-3 font-medium">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800">{{ $order->status->label() }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <a href="{{ route('merchant.orders.show', $order) }}" class="text-[#F57C00] hover:underline text-sm">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-gray-500">No orders found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
