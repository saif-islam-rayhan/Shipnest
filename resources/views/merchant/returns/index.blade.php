@extends('layouts.merchant')

@section('title', 'Returns')
@section('page-title', 'Return Requests')

@section('content')
<div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-5 py-3">Order</th>
                <th class="px-5 py-3">Item</th>
                <th class="px-5 py-3">Customer</th>
                <th class="px-5 py-3">Reason</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($returns as $return)
                <tr>
                    <td class="px-5 py-3">#{{ $return->order->order_number }}</td>
                    <td class="px-5 py-3">{{ $return->orderItem->product_name ?? '—' }}</td>
                    <td class="px-5 py-3">{{ $return->user->name }}</td>
                    <td class="px-5 py-3">{{ ucfirst(str_replace('_', ' ', $return->reason)) }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ match($return->status) {
                            'approved' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800',
                            default => 'bg-yellow-100 text-yellow-800',
                        } }}">{{ ucfirst($return->status) }}</span>
                    </td>
                    <td class="px-5 py-3">
                        @if($return->status === 'pending')
                            <div class="flex gap-2 flex-wrap">
                                <form action="{{ route('merchant.returns.approve', $return) }}" method="POST" class="inline">
                                    @csrf
                                    <input type="text" name="merchant_note" placeholder="Note (optional)" class="input-field text-xs w-32 mb-1">
                                    <button class="text-xs text-green-600 hover:underline block">Approve</button>
                                </form>
                                <form action="{{ route('merchant.returns.reject', $return) }}" method="POST" class="inline">
                                    @csrf
                                    <input type="text" name="merchant_note" placeholder="Rejection reason *" class="input-field text-xs w-32 mb-1" required>
                                    <button class="text-xs text-red-600 hover:underline block">Reject</button>
                                </form>
                            </div>
                        @elseif($return->merchant_note)
                            <p class="text-xs text-gray-500">{{ $return->merchant_note }}</p>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">No return requests.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $returns->links() }}</div>
@endsection
