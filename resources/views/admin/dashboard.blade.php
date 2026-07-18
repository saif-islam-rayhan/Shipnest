@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@php
    $currency = config('shipnest.currency_symbol');
    $trendBadge = function (?float $trend) {
        if ($trend === null) {
            return '<span class="dash-trend-neutral">—</span>';
        }
        $up = $trend >= 0;
        $class = $up ? 'dash-trend-up' : 'dash-trend-down';
        $arrow = $up ? '↑' : '↓';

        return '<span class="dash-badge '.$class.'">'.$arrow.' '.abs($trend).'%</span>';
    };
    $statCards = [
        [
            'label' => 'Total revenue',
            'value' => $currency.number_format($stats['total_revenue']),
            'sub' => $currency.number_format($stats['revenue_today']).' today',
            'trend' => $stats['revenue_trend'],
            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'bg' => 'dash-stat-revenue',
        ],
        [
            'label' => "Today's orders",
            'value' => number_format($stats['orders_today']),
            'sub' => number_format($stats['total_orders']).' total orders',
            'trend' => $stats['orders_trend'],
            'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
            'bg' => 'dash-stat-orders',
        ],
        [
            'label' => 'Active products',
            'value' => number_format($stats['active_products']),
            'sub' => $stats['pending_products'].' pending review',
            'trend' => null,
            'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
            'bg' => 'dash-stat-products',
        ],
        [
            'label' => 'Merchants',
            'value' => number_format($stats['total_merchants']),
            'sub' => $stats['pending_merchants'].' applications waiting',
            'trend' => null,
            'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
            'bg' => 'dash-stat-merchants',
        ],
        [
            'label' => 'Total users',
            'value' => number_format($stats['total_users']),
            'sub' => 'Registered customers',
            'trend' => null,
            'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
            'bg' => 'dash-stat-users',
        ],
        [
            'label' => 'Pending actions',
            'value' => number_format($stats['pending_approvals']),
            'sub' => $stats['pending_orders'].' orders pending',
            'trend' => null,
            'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            'bg' => 'dash-stat-pending',
        ],
    ];
@endphp

<div class="dash-welcome mb-6">
    <div class="relative z-10 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-medium text-white/70">{{ now()->format('l, F j, Y') }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight lg:text-3xl">
                Welcome back, {{ auth()->user()->name }}
            </h2>
            <p class="mt-2 max-w-xl text-sm text-white/80">
                Here's what's happening across ShipNest today — sales, orders, and items that need your attention.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.orders.index') }}" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/20 backdrop-blur hover:bg-white/20">View orders</a>
            <a href="{{ route('admin.products.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-600">+ Add product</a>
        </div>
    </div>
    <div class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/10"></div>
    <div class="pointer-events-none absolute -bottom-10 right-24 h-28 w-28 rounded-full bg-primary/20"></div>
</div>

<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
    @foreach($statCards as $card)
        <div class="dash-stat-card {{ $card['bg'] }}">
            <div class="dash-stat-glow"></div>
            <div class="relative flex items-start justify-between gap-3">
                <div class="dash-stat-icon">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $card['icon'] }}"/>
                    </svg>
                </div>
                @if($card['trend'] !== null)
                    {!! $trendBadge($card['trend']) !!}
                @endif
            </div>
            <p class="dash-stat-label">{{ $card['label'] }}</p>
            <p class="dash-stat-value">{{ $card['value'] }}</p>
            <p class="dash-stat-sub">{{ $card['sub'] }}</p>
        </div>
    @endforeach
</div>

<div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-12">
    <div class="dash-panel xl:col-span-8">
        <div class="dash-panel-head">
            <div>
                <h2 class="dash-panel-title">Revenue overview</h2>
                <p class="mt-0.5 text-xs text-gray-500">Last 30 days performance</p>
            </div>
            <span class="dash-badge bg-primary-50 text-primary">{{ $currency }}{{ number_format(array_sum($chart['data'])) }} total</span>
        </div>
        <div class="p-5 pt-2">
            <div class="h-[280px]">
                <canvas id="revenueChart"
                    data-labels='@json($chart['labels'])'
                    data-values='@json($chart['data'])'></canvas>
            </div>
        </div>
    </div>

    <div class="dash-panel xl:col-span-4">
        <div class="dash-panel-head">
            <div>
                <h2 class="dash-panel-title">Order status</h2>
                <p class="mt-0.5 text-xs text-gray-500">Distribution by status</p>
            </div>
        </div>
        <div class="p-5">
            <div class="mx-auto h-[220px] max-w-[220px]">
                <canvas id="statusChart"
                    data-labels='@json(array_keys($statusBreakdown))'
                    data-values='@json(array_values($statusBreakdown))'></canvas>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($statusBreakdown as $label => $count)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">{{ $label }}</span>
                        <span class="font-semibold text-gray-900">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2 class="dash-panel-title">Orders this week</h2>
                <p class="mt-0.5 text-xs text-gray-500">Daily order count — last 7 days</p>
            </div>
            <span class="dash-badge bg-blue-50 text-blue-600">{{ array_sum($ordersChart['data']) }} orders</span>
        </div>
        <div class="p-5 pt-2">
            <div class="h-[220px]">
                <canvas id="ordersChart"
                    data-labels='@json($ordersChart['labels'])'
                    data-values='@json($ordersChart['data'])'></canvas>
            </div>
        </div>
    </div>

    <div class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2 class="dash-panel-title">New customers</h2>
                <p class="mt-0.5 text-xs text-gray-500">User registrations — last 30 days</p>
            </div>
            <span class="dash-badge bg-emerald-50 text-emerald-600">{{ array_sum($usersChart['data']) }} new</span>
        </div>
        <div class="p-5 pt-2">
            <div class="h-[220px]">
                <canvas id="usersChart"
                    data-labels='@json($usersChart['labels'])'
                    data-values='@json($usersChart['data'])'></canvas>
            </div>
        </div>
    </div>
</div>

<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <a href="{{ route('admin.merchants.index', ['tab' => 'pending']) }}" class="dash-quick-action">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        Review merchants
    </a>
    <a href="{{ route('admin.products.index', ['approval_status' => 'pending']) }}" class="dash-quick-action">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </span>
        Approve products
    </a>
    <a href="{{ route('admin.reviews.index', ['status' => 'pending']) }}" class="dash-quick-action">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
        </span>
        Approve reviews
    </a>
    <a href="{{ route('admin.agent.index') }}" class="dash-quick-action">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
        </span>
        AI Mode
    </a>
    <a href="{{ route('admin.settings.edit') }}" class="dash-quick-action">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </span>
        Platform settings
    </a>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="dash-panel">
        <div class="dash-panel-head">
            <h2 class="dash-panel-title">Recent orders</h2>
            <a href="{{ route('admin.orders.index') }}" class="dash-link">View all →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Order</th>
                        <th class="px-5 py-3 font-semibold">Customer</th>
                        <th class="px-5 py-3 font-semibold">Date</th>
                        <th class="px-5 py-3 font-semibold">Total</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentOrders as $order)
                        <tr class="transition hover:bg-gray-50/80">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-primary hover:underline">
                                    #{{ $order->order_number }}
                                </a>
                            </td>
                            <td class="px-5 py-3.5 text-gray-700">{{ $order->user->name }}</td>
                            <td class="px-5 py-3.5 text-gray-500">{{ $order->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $currency }}{{ number_format($order->total) }}</td>
                            <td class="px-5 py-3.5">
                                <span class="dash-badge bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800">
                                    {{ $order->status->label() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-gray-500">No orders yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="dash-panel">
        <div class="dash-panel-head">
            <h2 class="dash-panel-title">Merchant applications</h2>
            <a href="{{ route('admin.merchants.index', ['tab' => 'pending']) }}" class="dash-link">View all →</a>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($pendingMerchants as $merchant)
                <div class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-gray-50/80">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-gray-900">{{ $merchant->shop_name }}</p>
                        <p class="text-xs text-gray-500">{{ $merchant->owner->name }} · {{ $merchant->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a href="{{ route('admin.merchants.show', $merchant) }}" class="rounded-lg px-3 py-1.5 text-xs font-medium text-gray-600 ring-1 ring-gray-200 hover:bg-white">Review</a>
                        <form action="{{ route('admin.merchants.approve', $merchant) }}" method="POST">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Approve</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-900">All caught up</p>
                    <p class="mt-1 text-xs text-gray-500">No pending merchant applications right now.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
