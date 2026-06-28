<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\FlashSaleProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'banners');

        $banners = Banner::query()->orderBy('sort_order')->get();
        $flashSales = FlashSale::query()->withCount('products')->latest()->get();
        $coupons = Coupon::query()->latest()->paginate(20);

        return view('admin.promotions.index', compact('tab', 'banners', 'flashSales', 'coupons'));
    }

    public function storeBanner(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'link' => ['nullable', 'url'],
            'position' => ['required', 'in:homepage_hero,category,sidebar'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $data['image'] = $request->file('image')->store('banners', 'public');
        $data['type'] = 'promo';
        $data['status'] = 'active';
        $data['sort_order'] = Banner::query()->max('sort_order') + 1;

        Banner::query()->create($data);

        return back()->with('success', 'Banner created.');
    }

    public function destroyBanner(Banner $banner): RedirectResponse
    {
        Storage::disk('public')->delete($banner->image);
        $banner->delete();

        return back()->with('success', 'Banner deleted.');
    }

    public function storeFlashSale(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        FlashSale::query()->create([...$data, 'status' => 'scheduled']);

        return back()->with('success', 'Flash sale created.');
    }

    public function addFlashSaleProduct(Request $request, FlashSale $flashSale): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:1'],
        ]);

        FlashSaleProduct::query()->create([...$data, 'flash_sale_id' => $flashSale->id]);

        return back()->with('success', 'Product added to flash sale.');
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
        ]);

        Coupon::query()->create([
            ...$data,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        return back()->with('success', 'Coupon created.');
    }

    public function destroyCoupon(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }
}
