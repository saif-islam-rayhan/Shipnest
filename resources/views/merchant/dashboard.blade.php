@extends('layouts.merchant')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    @foreach([
        ['label' => "Today's Orders", 'value' => $stats['today_orders'], 'color' => 'blue'],
        ['label' => 'Total Revenue', 'value' => config('shipnest.currency_symbol').number_format($stats['total_revenue']), 'color' => 'green'],
        ['label' => 'Pending Orders', 'value' => $stats['pending_orders'], 'color' => 'yellow'],
        ['label' => 'Low Stock', 'value' => $stats['low_stock'], 'color' => 'red'],
        ['label' => 'Total Products', 'value' => $stats['total_products'], 'color' => 'purple'],
    ] as $stat)
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stat['value'] }}</p>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-4">Revenue (Last 30 Days)</h2>
        <canvas id="revenueChart" height="100"
                data-labels='@json($chart['labels'])'
                data-values='@json($chart['data'])'></canvas>
    </div>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-4">Low Stock Alerts</h2>
        @forelse($lowStockProducts as $product)
            <div class="flex items-center gap-3 py-2 border-b last:border-0">
                <div class="w-10 h-10 rounded bg-gray-100 overflow-hidden flex-shrink-0">
                    @if($product->primary_image_url)
                        <img src="{{ $product->primary_image_url }}" alt="" class="w-full h-full object-cover">
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ $product->name }}</p>
                    <p class="text-xs text-red-600">Stock: {{ $product->stock }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">All products well stocked.</p>
        @endforelse
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200">
    <div class="px-5 py-4 border-b flex justify-between items-center">
        <h2 class="font-semibold text-gray-900">Recent Orders</h2>
        <a href="{{ route('merchant.orders.index') }}" class="text-sm text-[#F57C00] hover:underline">View All</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-5 py-3">Order</th>
                    <th class="px-5 py-3">Customer</th>
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Total</th>
                    <th class="px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($recentOrders as $order)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('merchant.orders.show', $order) }}" class="text-[#F57C00] font-medium hover:underline">#{{ $order->order_number }}</a>
                        </td>
                        <td class="px-5 py-3">{{ $order->user->name }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $order->created_at->format('M d, Y') }}</td>
                        <td class="px-5 py-3 font-medium">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</td>
                        <td class="px-5 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800">
                                {{ $order->status->label() }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>window.initRevenueChart && window.initRevenueChart('revenueChart');</script>
@endpush
