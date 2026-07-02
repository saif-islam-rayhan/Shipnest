<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Order;
use App\Services\Admin\AdminAnalyticsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminAnalyticsService $analytics,
    ) {}


    public function index(): View
    {
        $stats = $this->analytics->dashboardStats();
        $chart = $this->analytics->revenueLast30Days();
        $statusBreakdown = $this->analytics->orderStatusBreakdown();

        $recentOrders = Order::query()->with(['user', 'shop'])->latest()->limit(8)->get();
        $pendingMerchants = Merchant::query()->with('owner')->where('status', 'pending')->latest()->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'chart', 'statusBreakdown', 'recentOrders', 'pendingMerchants'));
    }
}
