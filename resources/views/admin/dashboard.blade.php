@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('content')
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    @foreach([
        ['Total Users', $stats['total_users']],
        ['Merchants', $stats['total_merchants']],
        ['Orders Today', $stats['orders_today']],
        ['Revenue', config('shipnest.currency_symbol').number_format($stats['total_revenue'])],
        ['Active Products', $stats['active_products']],
        ['Pending Approvals', $stats['pending_approvals']],
    ] as [$label, $value])
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4"><p class="text-xs text-gray-500 uppercase">{{ $label }}</p><p class="text-2xl font-bold mt-1">{{ $value }}</p></div>
    @endforeach
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Revenue (30 Days)</h2>
        <canvas id="revenueChart" data-labels='@json($chart['labels'])' data-values='@json($chart['data'])'></canvas>
    </div>
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Order Status</h2>
        <canvas id="statusChart" data-labels='@json(array_keys($statusBreakdown))' data-values='@json(array_values($statusBreakdown))'></canvas>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl ring-1 ring-gray-200">
        <div class="px-5 py-4 border-b flex justify-between"><h2 class="font-semibold">Recent Orders</h2><a href="{{ route('admin.orders.index') }}" class="text-sm text-[#F57C00]">View All</a></div>
        <div class="divide-y">@foreach($recentOrders as $order)
            <div class="p-4 flex justify-between text-sm"><div><a href="{{ route('admin.orders.show', $order) }}" class="text-[#F57C00] font-medium">#{{ $order->order_number }}</a><p class="text-gray-500">{{ $order->user->name }}</p></div><span>{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</span></div>
        @endforeach</div>
    </div>
    <div class="bg-white rounded-xl ring-1 ring-gray-200">
        <div class="px-5 py-4 border-b flex justify-between"><h2 class="font-semibold">Merchant Applications</h2><a href="{{ route('admin.merchants.index', ['tab'=>'pending']) }}" class="text-sm text-[#F57C00]">View All</a></div>
        <div class="divide-y">@forelse($pendingMerchants as $m)
            <div class="p-4 flex justify-between items-center text-sm"><div><p class="font-medium">{{ $m->shop_name }}</p><p class="text-gray-500">{{ $m->owner->name }}</p></div>
                <form action="{{ route('admin.merchants.approve', $m) }}" method="POST">@csrf @method('PATCH')<button class="btn-primary text-xs py-1 px-2">Approve</button></form></div>
        @empty<p class="p-4 text-gray-500 text-sm">No pending applications.</p>@endforelse</div>
    </div>
</div>
@endsection
@push('scripts')<script>initAdminLineChart('revenueChart'); initAdminDonutChart('statusChart');</script>@endpush
