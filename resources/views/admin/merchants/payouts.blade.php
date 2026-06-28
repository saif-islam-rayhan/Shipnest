@extends('layouts.admin')
@section('title','Payouts') @section('page-title','Payout Requests')
@section('content')
<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
<table class="w-full text-sm"><thead class="bg-gray-50"><tr>
    <th class="px-4 py-3 text-left">Merchant</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Method</th><th class="px-4 py-3">Account</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th>
</tr></thead><tbody>@foreach($payouts as $p)<tr>
    <td class="px-4 py-3">{{ $p->merchant->shop_name }}</td>
    <td class="px-4 py-3">{{ config('shipnest.currency_symbol') }}{{ number_format($p->amount) }}</td>
    <td class="px-4 py-3">{{ $p->method }}</td>
    <td class="px-4 py-3">{{ $p->account_number }}</td>
    <td class="px-4 py-3">{{ $p->status }}</td>
    <td class="px-4 py-3">@if($p->status==='pending')
        <form action="{{ route('admin.payouts.approve', $p) }}" method="POST" class="inline">@csrf @method('PATCH')<button class="text-green-600 text-xs">Approve</button></form>
        <form action="{{ route('admin.payouts.reject', $p) }}" method="POST" class="inline">@csrf<input name="note" placeholder="Reason" class="input-field text-xs w-24 inline"><button class="text-red-600 text-xs">Reject</button></form>
    @endif</td>
</tr>@endforeach</tbody></table></div>
<div class="mt-4">{{ $payouts->links() }}</div>
@endsection
