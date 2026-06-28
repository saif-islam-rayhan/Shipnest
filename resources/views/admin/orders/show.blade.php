@extends('layouts.admin')
@section('title','Order #'.$order->order_number) @section('page-title','Order Details')
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
            <h2 class="font-semibold mb-3">Items</h2>
            @foreach($order->items as $item)<div class="py-2 border-b text-sm flex justify-between"><span>{{ $item->product_name }} × {{ $item->quantity }}</span><span>{{ config('shipnest.currency_symbol') }}{{ number_format($item->total) }}</span></div>@endforeach
            <p class="font-bold mt-3 text-right">Total: {{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</p>
        </div>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
            <h2 class="font-semibold mb-3">Disputes</h2>
            @forelse($disputes as $d)
                <div class="py-2 border-b text-sm"><strong>{{ $d->reason }}</strong> — {{ $d->status }}<p class="text-gray-500">{{ $d->description }}</p>
                    @if($d->status==='open')<form action="{{ route('admin.disputes.resolve', $d) }}" method="POST" class="mt-2 flex gap-2">@csrf @method('PATCH')<select name="status" class="input-field text-xs w-32"><option value="resolved">Resolved</option><option value="closed">Closed</option></select><input name="admin_note" placeholder="Note" class="input-field text-xs flex-1"><button class="btn-primary text-xs">Update</button></form>@endif
                </div>
            @empty<p class="text-gray-500 text-sm">No disputes.</p>@endforelse
            <form action="{{ route('admin.orders.disputes.store', $order) }}" method="POST" class="mt-4 space-y-2">@csrf
                <input name="reason" placeholder="Dispute reason" class="input-field text-sm" required>
                <textarea name="description" placeholder="Description" class="input-field text-sm" rows="2"></textarea>
                <button class="btn-outline text-sm">Log Dispute</button>
            </form>
        </div>
    </div>
    <div class="space-y-6">
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 text-sm space-y-2">
            <p><strong>Customer:</strong> {{ $order->user->name }}</p>
            <p><strong>Payment:</strong> {{ $order->payment_method->label() }} ({{ $order->payment_status->label() }})</p>
            <form action="{{ route('admin.orders.status', $order) }}" method="POST" class="pt-2">@csrf @method('PATCH')
                <select name="status" class="input-field text-sm mb-2">@foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)<option value="{{ $s }}" @selected($order->status->value===$s)>{{ ucfirst($s) }}</option>@endforeach</select>
                <button class="btn-primary w-full text-sm">Update Status</button>
            </form>
        </div>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
            <h3 class="font-semibold mb-2">Manual Refund</h3>
            <form action="{{ route('admin.orders.refund', $order) }}" method="POST" class="space-y-2">@csrf
                <input type="number" name="amount" max="{{ $order->total }}" step="0.01" placeholder="Amount" class="input-field text-sm" required>
                <input name="note" placeholder="Note" class="input-field text-sm">
                <button class="btn-primary w-full text-sm">Process Refund</button>
            </form>
        </div>
    </div>
</div>
@endsection
