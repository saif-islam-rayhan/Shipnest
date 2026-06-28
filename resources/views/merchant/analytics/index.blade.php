@extends('layouts.merchant')

@section('title', 'Analytics')
@section('page-title', 'Analytics')

@section('content')
<div class="flex gap-2 mb-6">
    @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label)
        <a href="{{ route('merchant.analytics.index', ['period' => $key]) }}"
           class="px-4 py-1.5 rounded-full text-sm font-medium {{ $period === $key ? 'bg-[#F57C00] text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Sales by Date</h2>
        <canvas id="salesChart" height="120"
                data-labels='@json($revenueChart['labels'])'
                data-values='@json($revenueChart['data'])'></canvas>
    </div>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Order Status Breakdown</h2>
        <canvas id="statusChart" height="120"
                data-labels='@json(array_keys($statusBreakdown))'
                data-values='@json(array_values($statusBreakdown))'></canvas>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Top 10 Products by Revenue</h2>
        <canvas id="topProductsChart" height="200"
                data-labels='@json($topProducts->pluck('product_name'))'
                data-values='@json($topProducts->pluck('revenue'))'></canvas>
    </div>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Revenue Summary ({{ ucfirst($period) }})</h2>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Period</th>
                    <th class="px-4 py-2 text-right">Orders</th>
                    <th class="px-4 py-2 text-right">Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($summary as $row)
                    <tr>
                        <td class="px-4 py-2">{{ $row['period'] }}</td>
                        <td class="px-4 py-2 text-right">{{ $row['orders'] }}</td>
                        <td class="px-4 py-2 text-right font-medium">{{ config('shipnest.currency_symbol') }}{{ number_format($row['revenue']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.initRevenueChart && window.initRevenueChart('salesChart');
window.initBarChart && window.initBarChart('topProductsChart');
window.initDonutChart && window.initDonutChart('statusChart');
</script>
@endpush
