<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
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
        $products = $this->productService->search(
            query: $request->input('q'),
            categoryId: $request->integer('category'),
            brandId: $request->integer('brand'),
            shopId: $request->integer('shop'),
            minPrice: $request->input('min_price'),
            maxPrice: $request->input('max_price'),
            sort: $request->input('sort', 'newest'),
        );

        $categories = Category::query()->active()->roots()->with('children')->orderBy('sort_order')->get();
        $brands = Brand::query()->active()->orderBy('name')->get();

        return view('storefront.products.index', compact('products', 'categories', 'brands'));
    }

    public function show(string $slug): View
    {
        $product = $this->productService->findBySlug($slug);
        $product->increment('views');

        $relatedProducts = $this->productService->getByCategory($product->category_id, 8);

        return view('storefront.products.show', compact('product', 'relatedProducts'));
    }
}
