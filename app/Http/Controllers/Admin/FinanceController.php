<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantWithdrawalRequest;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Services\Admin\AdminAnalyticsService;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(AdminAnalyticsService $analytics): View
    {
        $commissionReport = $analytics->commissionReport(30);
        $totalCommission = collect($commissionReport)->sum('commission');

        $payouts = MerchantWithdrawalRequest::query()->with('merchant')->latest()->limit(20)->get();
        $transactions = PaymentTransaction::query()->with(['user', 'order'])->latest()->paginate(20);
        $refunds = Refund::query()->with(['order', 'user', 'processor'])->latest()->limit(20)->get();

        return view('admin.finance.index', compact('commissionReport', 'totalCommission', 'payouts', 'transactions', 'refunds'));
    }
}
