<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Mail\MerchantApplicationMail;
use App\Models\Merchant;
use App\Models\MerchantWithdrawalRequest;
use App\Models\OrderItem;
use App\Services\Admin\AdminAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class MerchantController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'active');

        $merchants = Merchant::query()
            ->with('owner')
            ->withCount('products')
            ->when($tab === 'pending', fn ($q) => $q->where('status', ShopStatus::Pending))
            ->when($tab === 'active', fn ($q) => $q->where('status', ShopStatus::Active))
            ->when($tab === 'suspended', fn ($q) => $q->where('status', ShopStatus::Suspended))
            ->when($tab === 'rejected', fn ($q) => $q->where('status', ShopStatus::Rejected))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $pendingCount = Merchant::query()->where('status', ShopStatus::Pending)->count();

        return view('admin.merchants.index', compact('merchants', 'tab', 'pendingCount'));
    }

    public function show(Merchant $merchant, AdminAnalyticsService $analytics): View
    {
        $merchant->load(['owner', 'wallet']);
        $revenue = (float) OrderItem::query()
            ->where('merchant_id', $merchant->id)
            ->whereHas('order', fn ($q) => $q->whereIn('payment_status', ['completed', 'paid']))
            ->sum('total');

        return view('admin.merchants.show', compact('merchant', 'revenue'));
    }

    public function approve(Merchant $merchant): RedirectResponse
    {
        $merchant->update([
            'status' => ShopStatus::Active->value,
            'is_verified' => true,
            'rejection_reason' => null,
            'rejected_at' => null,
        ]);

        Mail::to($merchant->owner->email)->send(new MerchantApplicationMail($merchant, 'approved'));

        return back()->with('success', 'Merchant approved and notified.');
    }

    public function reject(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $merchant->update([
            'status' => ShopStatus::Rejected->value,
            'is_verified' => false,
            'rejection_reason' => $data['reason'],
            'rejected_at' => now(),
        ]);

        Mail::to($merchant->owner->email)->send(new MerchantApplicationMail($merchant, 'rejected', $data['reason']));

        return back()->with('success', 'Merchant rejected and notified.');
    }

    public function suspend(Merchant $merchant): RedirectResponse
    {
        $merchant->update(['status' => ShopStatus::Suspended->value]);

        return back()->with('success', 'Merchant suspended.');
    }

    public function updateCommission(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate(['commission_rate' => ['required', 'numeric', 'min:0', 'max:100']]);
        $merchant->update(['commission_rate' => $data['commission_rate']]);

        return back()->with('success', 'Commission rate updated.');
    }

    public function payouts(Request $request): View
    {
        $payouts = MerchantWithdrawalRequest::query()
            ->with('merchant.owner')
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.merchants.payouts', compact('payouts'));
    }

    public function approvePayout(MerchantWithdrawalRequest $payout): RedirectResponse
    {
        $payout->update(['status' => 'approved']);
        $payout->merchant->wallet?->decrement('pending_balance', $payout->amount);
        $payout->merchant->wallet?->increment('total_withdrawn', $payout->amount);

        return back()->with('success', 'Payout approved.');
    }

    public function rejectPayout(Request $request, MerchantWithdrawalRequest $payout): RedirectResponse
    {
        $payout->update(['status' => 'rejected', 'note' => $request->input('note')]);
        $wallet = $payout->merchant->wallet;
        if ($wallet) {
            $wallet->increment('balance', $payout->amount);
            $wallet->decrement('pending_balance', $payout->amount);
        }

        return back()->with('success', 'Payout rejected and balance restored.');
    }
}
