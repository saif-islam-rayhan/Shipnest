<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Concerns\GeneratesProductDescriptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Services\Merchant\MerchantProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    use GeneratesProductDescriptions;

    public function __construct(
        private readonly MerchantProductService $productService,
    ) {}

    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['merchant', 'category', 'images', 'variants'])
            ->when($request->input('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->input('merchant_id'), fn ($q, $id) => $q->where('merchant_id', $id))
            ->when($request->input('approval_status'), fn ($q, $s) => $q->where('approval_status', $s))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $merchants = Merchant::query()->orderBy('shop_name')->get(['id', 'shop_name']);

        return view('admin.products.index', compact('products', 'merchants'));
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product,
            'merchants' => $this->merchantOptions(),
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'brands' => Brand::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $merchant = Merchant::query()->findOrFail($request->validated('merchant_id'));
        $data = $request->validated();
        $data['status'] = $request->input('action') === 'publish' ? ProductStatus::Active->value : ProductStatus::Draft->value;

        $product = $this->productService->create(
            $merchant,
            $data,
            $request->input('variants', []),
            $request->input('attributes', []),
            $request->file('images', []),
            $request->input('image_order'),
            $this->parseImageUrls($request->input('image_urls')),
        );

        $this->syncAdminFields($product, $request);

        return redirect()->route('admin.products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): View
    {
        $product->load(['variants', 'images', 'attributes', 'merchant']);

        return view('admin.products.form', [
            'product' => $product,
            'merchants' => $this->merchantOptions(),
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'brands' => Brand::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        if ($request->filled('merchant_id') && (int) $request->input('merchant_id') !== (int) $product->merchant_id) {
            $product->update(['merchant_id' => $request->input('merchant_id')]);
        }

        $data = $request->validated();
        $data['status'] = $request->input('action') === 'publish'
            ? ProductStatus::Active->value
            : ($data['status'] ?? $product->status->value);

        $this->productService->update(
            $product,
            $data,
            $request->input('variants', []),
            $request->input('attributes', []),
            $request->file('images', []),
            $request->input('image_order'),
            $request->input('remove_images', []),
            $this->parseImageUrls($request->input('image_urls')),
        );

        $this->syncAdminFields($product->fresh(), $request);

        return redirect()->route('admin.products.index')->with('success', 'Product updated successfully.');
    }

    public function approve(Product $product): RedirectResponse
    {
        $product->update(['approval_status' => 'approved', 'status' => 'active']);

        return back()->with('success', 'Product approved.');
    }

    public function reject(Request $request, Product $product): RedirectResponse
    {
        $product->update(['approval_status' => 'rejected', 'status' => 'inactive']);

        return back()->with('success', 'Product rejected.');
    }

    public function feature(Product $product): RedirectResponse
    {
        $product->update(['is_featured' => ! $product->is_featured]);

        return back()->with('success', 'Featured status updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    protected function merchantOptions()
    {
        return Merchant::query()
            ->where('status', 'active')
            ->orderBy('shop_name')
            ->get(['id', 'shop_name']);
    }

    protected function syncAdminFields(Product $product, Request $request): void
    {
        $product->update([
            'approval_status' => $request->input('approval_status', 'approved'),
            'is_featured' => $request->boolean('is_featured'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function parseImageUrls(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
    }
}
