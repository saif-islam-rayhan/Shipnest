<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Merchant;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(): View
    {
        $heroBanners = Banner::query()
            ->active()
            ->home()
            ->position('top')
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        $promoBanners = Banner::query()
            ->active()
            ->home()
            ->position('middle')
            ->orderBy('sort_order')
            ->limit(2)
            ->get();

        $flashSale = FlashSale::query()
            ->active()
            ->with([
                'products' => fn ($q) => $q->inStock()->with([
                    'product' => fn ($pq) => $pq->with(['images', 'merchant', 'defaultVariant'])->withAvg('reviews', 'rating'),
                ]),
            ])
            ->latest('ends_at')
            ->first();

        $featuredProducts = $this->productService->getFeatured(8);
        $newArrivals = $this->productService->getNewArrivals(8);

        $categories = Category::query()
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->active()
            ->roots()
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

        $brands = Brand::query()
            ->active()
            ->featured()
            ->orderBy('name')
            ->limit(12)
            ->get();

        $featuredMerchants = Merchant::query()
            ->with('owner')
            ->withCount('products')
            ->active()
            ->featured()
            ->latest()
            ->limit(8)
            ->get();

        return view('frontend.home', compact(
            'heroBanners',
            'promoBanners',
            'flashSale',
            'featuredProducts',
            'newArrivals',
            'categories',
            'brands',
            'featuredMerchants',
        ));
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        return back()->with('success', 'Thank you for subscribing to our newsletter!');
    }
}
