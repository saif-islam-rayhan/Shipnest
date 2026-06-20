<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request): View
    {
        return $this->renderListing($request);
    }

    public function category(Request $request, string $slug): View
    {
        $category = Category::query()->active()->where('slug', $slug)->firstOrFail();

        return $this->renderListing($request, category: $category);
    }

    public function brand(Request $request, string $slug): View
    {
        $brand = Brand::query()->active()->where('slug', $slug)->firstOrFail();

        return $this->renderListing($request, brand: $brand);
    }

    public function search(Request $request): View
    {
        return $this->renderListing($request, isSearch: true);
    }

    public function show(string $slug): View
    {
        $product = $this->productService->findBySlug($slug);

        $relatedProducts = $this->productService
            ->getByCategory($product->category_id, 8)
            ->getCollection()
            ->where('id', '!=', $product->id)
            ->take(6);

        $merchantProducts = $this->productService->getByMerchant(
            $product->merchant_id,
            6,
            $product->id,
        );

        $reviewDistribution = $this->productService->getReviewDistribution($product);

        $canReview = auth()->check() && OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', auth()->id()))
            ->whereDoesntHave('review')
            ->exists();

        $variantsJson = $product->variants->where('status', 'active')->values()->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'price' => (float) $v->price,
            'compare_price' => $v->compare_price ? (float) $v->compare_price : null,
            'stock' => (int) $v->stock,
            'sku' => $v->sku,
        ])->values();

        return view('storefront.products.show', compact(
            'product',
            'relatedProducts',
            'merchantProducts',
            'reviewDistribution',
            'canReview',
            'variantsJson',
        ));
    }

    protected function renderListing(
        Request $request,
        ?Category $category = null,
        ?Brand $brand = null,
        bool $isSearch = false,
    ): View {
        $brandIds = array_filter(array_map('intval', (array) $request->input('brands', [])));
        $categoryIds = $category
            ? $this->productService->resolveCategoryIds($category)
            : null;

        $products = $this->productService->search(
            query: $request->input('q'),
            categoryIds: $categoryIds,
            brandId: $brand && empty($brandIds) ? $brand->id : null,
            brandIds: ! empty($brandIds) ? $brandIds : null,
            shopId: $request->integer('shop') ?: null,
            minPrice: $request->filled('min_price') ? (float) $request->input('min_price') : null,
            maxPrice: $request->filled('max_price') ? (float) $request->input('max_price') : null,
            minRating: $request->integer('rating') ?: null,
            minDiscount: $request->integer('discount') ?: null,
            sort: $request->input('sort', $request->filled('q') ? 'relevance' : 'newest'),
        );

        $categories = Category::query()->active()->roots()->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])->orderBy('sort_order')->get();
        $brands = $this->productService->getBrandsWithCounts();

        $priceMax = (float) ProductVariant::query()->where('status', 'active')->max('price') ?: 50000;

        $pageTitle = match (true) {
            $isSearch && $request->filled('q') => "Results for \"{$request->input('q')}\"",
            $category !== null => $category->name,
            $brand !== null => $brand->name,
            default => 'All Products',
        };

        $listingRoute = match (true) {
            $category !== null => route('category.show', $category->slug),
            $brand !== null => route('brand.show', $brand->slug),
            $isSearch => route('search'),
            default => route('products.index'),
        };

        return view('storefront.products.index', compact(
            'products',
            'categories',
            'brands',
            'category',
            'brand',
            'pageTitle',
            'listingRoute',
            'priceMax',
            'isSearch',
        ));
    }
}
