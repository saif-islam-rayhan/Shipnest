<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Concerns\InteractsWithShop;
use App\Http\Requests\Merchant\StoreProductRequest;
use App\Http\Requests\Merchant\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Merchant\MerchantProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    use InteractsWithShop;

    public function __construct(
        private readonly MerchantProductService $productService,
    ) {}

    public function index(Request $request): View
    {
        $shop = $this->shop($request);

        $products = $shop->products()
            ->with(['category', 'brand', 'images', 'variants'])
            ->when($request->input('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%"))
            ->when($request->input('category'), fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $categories = Category::query()->active()->orderBy('name')->get();

        return view('merchant.products.index', compact('shop', 'products', 'categories'));
    }

    public function create(): View
    {
        return view('merchant.products.form', [
            'product' => new Product,
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'brands' => Brand::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $data = $request->validated();
        $data['status'] = $request->input('action') === 'publish' ? ProductStatus::Active->value : ProductStatus::Draft->value;

        $this->productService->create(
            $shop,
            $data,
            $request->input('variants', []),
            $request->input('attributes', []),
            $request->file('images', []),
            $request->input('image_order'),
        );

        return redirect()->route('merchant.products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeProduct($this->shop($request), $product);
        $product->load(['variants', 'images', 'attributes']);

        return view('merchant.products.form', [
            'product' => $product,
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'brands' => Brand::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($this->shop($request), $product);
        $data = $request->validated();
        $data['status'] = $request->input('action') === 'publish' ? ProductStatus::Active->value : ($data['status'] ?? ProductStatus::Draft->value);

        $this->productService->update(
            $product,
            $data,
            $request->input('variants', []),
            $request->input('attributes', []),
            $request->file('images', []),
            $request->input('image_order'),
            $request->input('remove_images', []),
        );

        return redirect()->route('merchant.products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($this->shop($request), $product);
        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    public function toggleStatus(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($this->shop($request), $product);
        $product->update([
            'status' => $product->status === ProductStatus::Active ? ProductStatus::Inactive->value : ProductStatus::Active->value,
        ]);

        return back()->with('success', 'Product status updated.');
    }

    public function duplicate(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($this->shop($request), $product);
        $copy = $this->productService->duplicate($product);

        return redirect()->route('merchant.products.edit', $copy)->with('success', 'Product duplicated. Review and publish.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $ids = $request->input('ids', []);
        $action = $request->input('bulk_action');

        $products = $shop->products()->whereIn('id', $ids)->get();

        foreach ($products as $product) {
            match ($action) {
                'enable' => $product->update(['status' => ProductStatus::Active->value]),
                'disable' => $product->update(['status' => ProductStatus::Inactive->value]),
                'delete' => $product->delete(),
                default => null,
            };
        }

        return back()->with('success', 'Bulk action completed.');
    }
}
