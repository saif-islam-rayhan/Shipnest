<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(): View
    {
        $stats = [
            'total_users' => User::query()->count(),
            'total_shops' => Merchant::query()->count(),
            'pending_shops' => Merchant::query()->where('status', ShopStatus::Pending)->count(),
            'total_products' => Product::query()->count(),
            'total_orders' => Order::query()->count(),
            'revenue' => Order::query()->where('payment_status', 'completed')->sum('total'),
        ];

        $recentOrders = Order::query()
            ->with(['user', 'shop', 'items'])
            ->latest()
            ->limit(10)
            ->get();

        $pendingShops = Merchant::query()
            ->with('owner')
            ->withCount('products')
            ->where('status', ShopStatus::Pending)
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'pendingShops'));
    }

    public function shops(Request $request): View
    {
        $shops = Merchant::query()
            ->with('owner')
            ->withCount('products')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return view('admin.shops.index', compact('shops'));
    }

    public function approveShop(Merchant $shop): RedirectResponse
    {
        $shop->update([
            'status' => ShopStatus::Active->value,
            'is_verified' => true,
        ]);

        return back()->with('success', 'Shop approved successfully.');
    }

    public function suspendShop(Merchant $shop): RedirectResponse
    {
        $shop->update(['status' => ShopStatus::Suspended->value]);

        return back()->with('success', 'Shop suspended.');
    }

    public function orders(Request $request): View
    {
        $orders = Order::query()
            ->with(['user', 'shop', 'items'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return view('admin.orders.index', compact('orders'));
    }

    public function updateOrderStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate(['status' => ['required', 'string']]);

        $this->orderService->updateStatus($order, OrderStatus::from($request->input('status')));

        return back()->with('success', 'Order status updated.');
    }

    public function users(Request $request): View
    {
        $users = User::query()
            ->with('merchant')
            ->when($request->input('role'), fn ($q, $role) => $q->where('role', $role))
            ->latest()
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }
}
