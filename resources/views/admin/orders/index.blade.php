@extends('layouts.admin')
@section('title', 'Orders')
@section('page-title', 'Order Management')

@section('content')
@include('layouts.partials.admin-page-header', [
    'subtitle' => 'Track and manage customer orders across all merchants.',
])

<form method="GET" class="admin-filter-bar mb-5">
    <input name="search" value="{{ request('search') }}" placeholder="Order #" class="input-field w-36">
    <select name="status" class="input-field w-32">
        <option value="">Status</option>
        @foreach(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <select name="merchant_id" class="input-field w-40">
        <option value="">All Merchants</option>
        @foreach($merchants as $m)
            <option value="{{ $m->id }}" @selected(request('merchant_id') == $m->id)>{{ $m->shop_name }}</option>
        @endforeach
    </select>
    <select name="payment_method" class="input-field w-32">
        <option value="">Payment</option>
        @foreach(['cod', 'bkash', 'nagad', 'sslcommerz', 'stripe'] as $m)
            <option value="{{ $m }}" @selected(request('payment_method') === $m)>{{ strtoupper($m) }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" class="input-field w-36">
    <input type="date" name="to" value="{{ request('to') }}" class="input-field w-36">
    <button type="submit" class="btn-primary">Filter</button>
    @if(request()->hasAny(['search', 'status', 'merchant_id', 'payment_method', 'from', 'to']))
        <a href="{{ route('admin.orders.index') }}" class="btn-outline">Clear</a>
    @endif
</form>

<div class="admin-card">
    <div class="overflow-x-auto">
        <table class="admin-datatable admin-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Merchant</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>
                            <a href="{{ route('admin.orders.show', $order) }}" class="admin-link font-semibold">#{{ $order->order_number }}</a>
                            <p class="text-xs text-gray-400">{{ $order->created_at->format('M d, Y') }}</p>
                        </td>
                        <td class="text-gray-700">{{ $order->user->name ?? '—' }}</td>
                        <td class="text-gray-600">{{ $order->shop?->shop_name ?? '—' }}</td>
                        <td class="font-semibold text-gray-900">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</td>
                        <td>
                            <span class="admin-badge bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800">
                                {{ $order->status->label() }}
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('admin.orders.show', $order) }}" class="admin-link">View details</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="admin-empty">No orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($orders->hasPages())
    <div class="mt-4">{{ $orders->links() }}</div>
@endif
@endsection
