@extends('layouts.admin')
@section('title',$merchant->shop_name) @section('page-title','Merchant Details')
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
        <h2 class="text-xl font-bold">{{ $merchant->shop_name }}</h2>
        <p class="text-gray-500">{{ $merchant->owner->name }} · {{ $merchant->owner->email }}</p>
        <p class="mt-2 text-sm">Status: {{ $merchant->status->label() }}</p>
        <p class="text-sm">Commission: {{ $merchant->commission_rate }}%</p>
        <p class="text-sm">Revenue: {{ config('shipnest.currency_symbol') }}{{ number_format($revenue) }}</p>
        <form action="{{ route('admin.merchants.commission', $merchant) }}" method="POST" class="mt-4 flex gap-2">@csrf @method('PATCH')
            <input type="number" name="commission_rate" value="{{ $merchant->commission_rate }}" step="0.01" class="input-field w-24">
            <button class="btn-primary text-sm">Update</button>
        </form>
    </div>
    <div class="lg:col-span-2 bg-white rounded-xl ring-1 ring-gray-200 p-6">
        <h3 class="font-semibold mb-2">Wallet</h3>
        <p>Balance: {{ config('shipnest.currency_symbol') }}{{ number_format($merchant->wallet?->balance ?? 0) }}</p>
        <p>Pending: {{ config('shipnest.currency_symbol') }}{{ number_format($merchant->wallet?->pending_balance ?? 0) }}</p>
    </div>
</div>
@endsection
