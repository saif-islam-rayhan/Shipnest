<?php

namespace App\Services\Admin;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
{
    public function dashboardStats(): array
    {
        $today = now()->startOfDay();

        return [
            'total_users' => User::query()->count(),
            'total_merchants' => Merchant::query()->count(),
            'orders_today' => Order::query()->where('created_at', '>=', $today)->count(),
            'total_revenue' => (float) Order::query()->whereIn('payment_status', ['completed', 'paid'])->sum('total'),
            'active_products' => Product::query()->where('status', 'active')->count(),
            'pending_approvals' => Merchant::query()->where('status', 'pending')->count()
                + Product::query()->where('approval_status', 'pending')->count(),
        ];
    }

    public function revenueLast30Days(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $rows = Order::query()
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue')
            ->whereIn('payment_status', ['completed', 'paid'])
            ->where('created_at', '>=', $start)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('revenue', 'date');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('M d');
            $data[] = (float) ($rows[$date] ?? 0);
        }

        return compact('labels', 'data');
    }

    public function orderStatusBreakdown(): array
    {
        return Order::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function commissionReport(int $days = 30): array
    {
        $rate = (float) config('shipnest.commission_rate', 10);

        return Order::query()
            ->selectRaw('DATE(created_at) as period, SUM(total) as revenue, COUNT(*) as orders')
            ->whereIn('payment_status', ['completed', 'paid'])
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'revenue' => (float) $row->revenue,
                'commission' => round((float) $row->revenue * $rate / 100, 2),
                'orders' => (int) $row->orders,
            ])
            ->toArray();
    }
}
