<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Concerns\InteractsWithShop;
use App\Models\MerchantWalletTransaction;
use App\Models\MerchantWithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    use InteractsWithShop;

    public function index(Request $request): View
    {
        $shop = $this->shop($request);
        $wallet = $shop->wallet()->firstOrCreate([
            'balance' => 0,
            'pending_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $transactions = MerchantWalletTransaction::query()
            ->where('merchant_id', $shop->id)
            ->latest()
            ->paginate(20);

        $withdrawals = $shop->withdrawalRequests()->latest()->limit(10)->get();

        return view('merchant.wallet.index', compact('shop', 'wallet', 'transactions', 'withdrawals'));
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $wallet = $shop->wallet()->firstOrCreate(['balance' => 0]);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:100'],
            'method' => ['required', 'in:bkash,bank'],
            'account_number' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['amount'] > $wallet->balance) {
            return back()->with('error', 'Insufficient balance.');
        }

        MerchantWithdrawalRequest::query()->create([
            'merchant_id' => $shop->id,
            'amount' => $data['amount'],
            'method' => $data['method'],
            'account_number' => $data['account_number'],
            'note' => $data['note'] ?? null,
            'status' => 'pending',
        ]);

        $wallet->increment('pending_balance', $data['amount']);
        $wallet->decrement('balance', $data['amount']);

        MerchantWalletTransaction::query()->create([
            'merchant_id' => $shop->id,
            'type' => 'debit',
            'amount' => $data['amount'],
            'description' => 'Withdrawal request ('.$data['method'].')',
        ]);

        return back()->with('success', 'Withdrawal request submitted.');
    }
}
