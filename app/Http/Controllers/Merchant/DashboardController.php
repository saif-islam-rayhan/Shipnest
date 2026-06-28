<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Concerns\InteractsWithShop;
use App\Http\Requests\Merchant\StoreShopRequest;
use App\Models\Order;
use App\Services\Merchant\MerchantAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithShop;

    public function __construct(
        private readonly MerchantAnalyticsService $analytics,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $shop = $this->shop($request);
        $shop->wallet()->firstOrCreate(['balance' => 0]);

        $today = now()->startOfDay();
        $stats = [
            'today_orders' => Order::query()->forShop($shop->id)->where('created_at', '>=', $today)->count(),
            'total_revenue' => (float) $shop->orderItems()
                ->whereHas('order', fn ($q) => $q->whereIn('payment_status', ['completed', 'paid']))
                ->sum('total'),
            'pending_orders' => Order::query()->forShop($shop->id)->where('status', 'pending')->count(),
            'low_stock' => $shop->products()->whereHas('variants', fn ($q) => $q->where('stock', '<=', 5)->where('status', 'active'))->count(),
            'total_products' => $shop->products()->count(),
        ];

        $chart = $this->analytics->revenueLast30Days($shop);
        $recentOrders = Order::query()->with(['user'])->forShop($shop->id)->latest()->limit(8)->get();
        $lowStockProducts = $this->analytics->lowStockProducts($shop);

        return view('merchant.dashboard', compact('shop', 'stats', 'chart', 'recentOrders', 'lowStockProducts'));
    }

    public function createShop(): View
    {
        return view('merchant.shop.create');
    }

    public function storeShop(StoreShopRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('shops/logos', 'public');
        }

        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('shops/banners', 'public');
        }

        $request->user()->merchant()->create([
            'shop_name' => $data['name'],
            'shop_slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'district' => $data['district'] ?? $data['city'] ?? null,
            'logo' => $data['logo'] ?? null,
            'banner' => $data['banner'] ?? null,
            'status' => ShopStatus::Pending,
        ]);

        return redirect()->route('merchant.pending')->with('success', 'Shop submitted for approval.');
    }
}
