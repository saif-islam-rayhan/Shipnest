<?php

namespace App\Services\Admin;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
{
    public function dashboardStats(): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $paidStatuses = ['completed', 'paid'];

        $revenueToday = (float) Order::query()
            ->whereIn('payment_status', $paidStatuses)
            ->where('created_at', '>=', $today)
            ->sum('total');

        $revenueYesterday = (float) Order::query()
            ->whereIn('payment_status', $paidStatuses)
            ->whereBetween('created_at', [$yesterday, $today])
            ->sum('total');

        $ordersToday = Order::query()->where('created_at', '>=', $today)->count();
        $ordersYesterday = Order::query()
            ->whereBetween('created_at', [$yesterday, $today])
            ->count();

        return [
            'total_users' => User::query()->count(),
            'total_merchants' => Merchant::query()->where('status', 'active')->count(),
            'total_orders' => Order::query()->count(),
            'orders_today' => $ordersToday,
            'orders_yesterday' => $ordersYesterday,
            'revenue_today' => $revenueToday,
            'revenue_yesterday' => $revenueYesterday,
            'revenue_trend' => $this->percentChange($revenueYesterday, $revenueToday),
            'orders_trend' => $this->percentChange($ordersYesterday, $ordersToday),
            'total_revenue' => (float) Order::query()->whereIn('payment_status', $paidStatuses)->sum('total'),
            'active_products' => Product::query()->where('status', 'active')->count(),
            'pending_merchants' => Merchant::query()->where('status', 'pending')->count(),
            'pending_products' => Product::query()->where('approval_status', 'pending')->count(),
            'pending_reviews' => ProductReview::query()->pending()->count(),
            'pending_approvals' => Merchant::query()->where('status', 'pending')->count()
                + Product::query()->where('approval_status', 'pending')->count()
                + ProductReview::query()->pending()->count(),
            'pending_orders' => Order::query()->where('status', 'pending')->count(),
        ];
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
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
            ->mapWithKeys(fn ($count, $status) => [ucfirst((string) $status) => $count])
            ->toArray();
    }

    public function ordersLast7Days(): array
    {
        $start = now()->subDays(6)->startOfDay();
        $rows = Order::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('D');
            $data[] = (int) ($rows[$date] ?? 0);
        }

        return compact('labels', 'data');
    }

    public function newUsersLast30Days(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $rows = User::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('M d');
            $data[] = (int) ($rows[$date] ?? 0);
        }

        return compact('labels', 'data');
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
