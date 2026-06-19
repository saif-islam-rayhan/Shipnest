<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StoreProductRequest;
use App\Http\Requests\Merchant\StoreShopRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('merchant.shop.create');
        }

        $stats = [
            'total_products' => $shop->products()->count(),
            'total_orders' => $shop->orders()->count(),
            'pending_orders' => $shop->orders()->where('status', 'pending')->count(),
            'revenue' => $shop->orders()->where('payment_status', 'completed')->sum('total'),
        ];

        $recentOrders = Order::query()
            ->with(['user', 'items'])
            ->forShop($shop->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('merchant.dashboard', compact('shop', 'stats', 'recentOrders'));
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

        $request->user()->shop()->create([
            ...$data,
            'slug' => Str::slug($data['name']),
            'status' => ShopStatus::Pending,
        ]);

        return redirect()->route('merchant.dashboard')->with('success', 'Shop created successfully. Awaiting approval.');
    }

    public function products(Request $request): View
    {
        $shop = $request->user()->shop;
        $products = $shop->products()
            ->with(['category', 'brand', 'images'])
            ->latest()
            ->paginate(20);

        return view('merchant.products.index', compact('shop', 'products'));
    }

    public function createProduct(Request $request): View
    {
        $categories = Category::query()->active()->orderBy('name')->get();
        $brands = Brand::query()->active()->orderBy('name')->get();

        return view('merchant.products.create', compact('categories', 'brands'));
    }

    public function storeProduct(StoreProductRequest $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $data = $request->validated();
        unset($data['images']);

        $product = $shop->products()->create([
            ...$data,
            'slug' => Str::slug($data['name']),
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'path' => $path,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        $shop->increment('total_products');

        return redirect()->route('merchant.products.index')->with('success', 'Product created successfully.');
    }

    public function orders(Request $request): View
    {
        $shop = $request->user()->shop;
        $orders = Order::query()
            ->with(['user', 'items'])
            ->forShop($shop->id)
            ->latest()
            ->paginate(20);

        return view('merchant.orders.index', compact('shop', 'orders'));
    }
}
