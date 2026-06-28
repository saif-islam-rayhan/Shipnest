@extends('layouts.admin')
@section('title','Finance') @section('page-title','Finance')
@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-gradient-to-br from-[#1A237E] to-[#283593] rounded-xl p-6 text-white"><p class="text-white/70 text-sm">Commission (30d)</p><p class="text-3xl font-bold">{{ config('shipnest.currency_symbol') }}{{ number_format($totalCommission) }}</p></div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-3">Commission Report</h2>
        <table class="w-full text-sm"><thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-right">Revenue</th><th class="px-3 py-2 text-right">Commission</th></tr></thead>
        <tbody>@foreach($commissionReport as $row)<tr><td class="px-3 py-2">{{ $row['period'] }}</td><td class="px-3 py-2 text-right">{{ number_format($row['revenue']) }}</td><td class="px-3 py-2 text-right font-medium">{{ number_format($row['commission']) }}</td></tr>@endforeach</tbody></table>
    </div>
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-3">Payout History</h2>
        @foreach($payouts as $p)<div class="py-2 border-b text-sm flex justify-between"><span>{{ $p->merchant->shop_name }}</span><span>{{ config('shipnest.currency_symbol') }}{{ number_format($p->amount) }} · {{ $p->status }}</span></div>@endforeach
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-3">Payment Transactions</h2>
        @foreach($transactions as $tx)<div class="py-2 border-b text-sm flex justify-between"><span>#{{ $tx->order?->order_number ?? '—' }} · {{ $tx->method }}</span><span>{{ config('shipnest.currency_symbol') }}{{ number_format($tx->amount) }} · {{ $tx->status }}</span></div>@endforeach
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-3">Refund Log</h2>
        @forelse($refunds as $r)<div class="py-2 border-b text-sm flex justify-between"><span>#{{ $r->order->order_number }}</span><span class="text-red-600">-{{ config('shipnest.currency_symbol') }}{{ number_format($r->amount) }}</span></div>@empty<p class="text-gray-500 text-sm">No refunds.</p>@endforelse
    </div>
</div>
@endsection
