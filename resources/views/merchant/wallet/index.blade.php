@extends('layouts.merchant')

@section('title', 'Wallet')
@section('page-title', 'Wallet')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-gradient-to-br from-[#1A237E] to-[#283593] rounded-xl p-6 text-white">
        <p class="text-white/70 text-sm">Available Balance</p>
        <p class="text-3xl font-bold mt-1">{{ config('shipnest.currency_symbol') }}{{ number_format($wallet->balance) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <p class="text-gray-500 text-sm">Pending Balance</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ config('shipnest.currency_symbol') }}{{ number_format($wallet->pending_balance) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <p class="text-gray-500 text-sm">Total Earned</p>
        <p class="text-2xl font-bold text-[#F57C00] mt-1">{{ config('shipnest.currency_symbol') }}{{ number_format($wallet->total_earned) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Transaction History</h2>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2">Description</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($transactions as $tx)
                    <tr>
                        <td class="px-4 py-2 text-gray-500">{{ $tx->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-2 capitalize">{{ $tx->type }}</td>
                        <td class="px-4 py-2">{{ $tx->description }}</td>
                        <td class="px-4 py-2 text-right font-medium {{ $tx->type === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $tx->type === 'credit' ? '+' : '-' }}{{ config('shipnest.currency_symbol') }}{{ number_format($tx->amount) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Request Withdrawal</h2>
        <form action="{{ route('merchant.wallet.withdraw') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Amount</label>
                <input type="number" name="amount" min="100" step="1" class="input-field" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Method</label>
                <select name="method" class="input-field" required>
                    <option value="bkash">bKash</option>
                    <option value="bank">Bank Account</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Account Number</label>
                <input type="text" name="account_number" class="input-field" required placeholder="bKash number or bank account">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Note</label>
                <textarea name="note" rows="2" class="input-field"></textarea>
            </div>
            <button type="submit" class="btn-primary w-full">Submit Request</button>
        </form>

        @if($withdrawals->isNotEmpty())
            <h3 class="font-medium mt-6 mb-2 text-sm">Recent Requests</h3>
            @foreach($withdrawals as $w)
                <div class="text-xs py-2 border-b">
                    {{ config('shipnest.currency_symbol') }}{{ number_format($w->amount) }} via {{ $w->method }}
                    <span class="float-right capitalize">{{ $w->status }}</span>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
