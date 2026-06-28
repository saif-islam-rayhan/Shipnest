<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Concerns\InteractsWithShop;
use App\Services\Merchant\MerchantAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    use InteractsWithShop;

    public function __construct(
        private readonly MerchantAnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
        $shop = $this->shop($request);
        $period = $request->input('period', 'daily');

        $revenueChart = $this->analytics->revenueLast30Days($shop);
        $topProducts = $this->analytics->topProducts($shop);
        $statusBreakdown = $this->analytics->orderStatusBreakdown($shop);
        $summary = $this->analytics->revenueSummary($shop, $period);

        return view('merchant.analytics.index', compact(
            'shop', 'revenueChart', 'topProducts', 'statusBreakdown', 'summary', 'period'
        ));
    }
}
