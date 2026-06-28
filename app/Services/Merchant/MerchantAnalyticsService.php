<?php

namespace App\Services\Merchant;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MerchantAnalyticsService
{
    public function revenueLast30Days(Merchant $shop): array
    {
        $start = now()->subDays(29)->startOfDay();
        $rows = OrderItem::query()
            ->selectRaw('DATE(orders.created_at) as date, SUM(order_items.total) as revenue')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.merchant_id', $shop->id)
            ->whereIn('orders.payment_status', ['completed', 'paid'])
            ->where('orders.created_at', '>=', $start)
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

    public function topProducts(Merchant $shop, int $limit = 10): Collection
    {
        return OrderItem::query()
            ->selectRaw('product_id, product_name, SUM(total) as revenue, SUM(quantity) as sold')
            ->where('merchant_id', $shop->id)
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    public function orderStatusBreakdown(Merchant $shop): array
    {
        return Order::query()
            ->select('orders.status', DB::raw('COUNT(DISTINCT orders.id) as count'))
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.merchant_id', $shop->id)
            ->groupBy('orders.status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function revenueSummary(Merchant $shop, string $period = 'daily'): array
    {
        $format = match ($period) {
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $labelFormat = match ($period) {
            'weekly' => 'W',
            'monthly' => 'M Y',
            default => 'M d',
        };

        $rows = OrderItem::query()
            ->selectRaw("DATE_FORMAT(orders.created_at, '{$format}') as period, SUM(order_items.total) as revenue, COUNT(DISTINCT orders.id) as orders")
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.merchant_id', $shop->id)
            ->whereIn('orders.payment_status', ['completed', 'paid'])
            ->where('orders.created_at', '>=', now()->subDays($period === 'monthly' ? 365 : ($period === 'weekly' ? 90 : 30)))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $rows->map(fn ($row) => [
            'period' => $row->period,
            'revenue' => (float) $row->revenue,
            'orders' => (int) $row->orders,
        ])->toArray();
    }

    public function lowStockProducts(Merchant $shop, int $threshold = 5, int $limit = 10): Collection
    {
        return $shop->products()
            ->with(['variants', 'images'])
            ->whereHas('variants', fn ($q) => $q->where('stock', '<=', $threshold)->where('status', 'active'))
            ->limit($limit)
            ->get();
    }
}
