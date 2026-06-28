@extends('layouts.admin')
@section('title','Payments') @section('page-title','Payment Verification')
@section('content')
<div class="flex flex-wrap gap-2 mb-4">
    <a href="?status=pending" class="px-3 py-1 rounded-full text-sm {{ request('status','pending')==='pending' ? 'bg-[#F57C00] text-white' : 'bg-white ring-1 ring-gray-200' }}">Pending</a>
    <a href="?status=completed" class="px-3 py-1 rounded-full text-sm {{ request('status')==='completed' ? 'bg-[#F57C00] text-white' : 'bg-white ring-1 ring-gray-200' }}">Completed</a>
    <a href="?status=failed" class="px-3 py-1 rounded-full text-sm {{ request('status')==='failed' ? 'bg-[#F57C00] text-white' : 'bg-white ring-1 ring-gray-200' }}">Failed</a>
</div>
<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
<table class="admin-datatable w-full text-sm"><thead class="bg-gray-50"><tr>
    <th class="px-4 py-3 text-left">Transaction</th><th class="px-4 py-3">Order</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Method</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th>
</tr></thead><tbody>@forelse($payments as $p)<tr>
    <td class="px-4 py-3 font-mono text-xs">{{ $p->transaction_id }}</td>
    <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $p->order) }}" class="text-[#F57C00]">#{{ $p->order->order_number }}</a></td>
    <td class="px-4 py-3">{{ $p->user->name }}</td>
    <td class="px-4 py-3 uppercase">{{ $p->method }}</td>
    <td class="px-4 py-3">{{ config('shipnest.currency_symbol') }}{{ number_format($p->amount) }}</td>
    <td class="px-4 py-3 text-xs">{{ $p->gateway_response['reference'] ?? $p->order->payment_reference ?? '—' }}</td>
    <td class="px-4 py-3">{{ ucfirst($p->status) }}</td>
    <td class="px-4 py-3 flex gap-2 text-xs">
        @if($p->status === 'pending')
            <form action="{{ route('admin.payments.approve', $p) }}" method="POST">@csrf @method('PATCH')<button class="text-green-600">Approve</button></form>
            <form action="{{ route('admin.payments.reject', $p) }}" method="POST" class="flex gap-1">@csrf<input name="note" placeholder="Reason" class="input-field text-xs w-24"><button class="text-red-600">Reject</button></form>
        @else
            <span class="text-gray-400">—</span>
        @endif
    </td>
</tr>@empty<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No payments found.</td></tr>@endforelse</tbody></table></div>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection
