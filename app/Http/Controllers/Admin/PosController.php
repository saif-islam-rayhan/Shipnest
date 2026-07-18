<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        private readonly PosService $posService,
    ) {}

    public function index(): View
    {
        $categories = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $cashier = auth()->user();
        $currencySymbol = config('shipnest.currency_symbol', '৳');
        $taxRate = (float) config('shipnest.pos.tax_rate', 0);
        $branchName = config('shipnest.pos.branch_name', 'Main Outlet');
        $counter = config('shipnest.pos.counter', '01');
        $platformName = config('shipnest.name', 'ShipNest');

        return view('admin.pos.index', compact(
            'categories',
            'cashier',
            'currencySymbol',
            'taxRate',
            'branchName',
            'counter',
            'platformName',
        ));
    }

    public function products(Request $request): JsonResponse
    {
        $perPage = min(48, max(12, (int) $request->input('per_page', 24)));
        $search = trim((string) $request->input('search', ''));
        $categoryId = $request->input('category_id');
        $popular = $request->boolean('popular');

        $query = Product::query()
            ->with([
                'defaultVariant',
                'images' => fn ($q) => $q->orderBy('sort_order')->limit(1),
                'category:id,name',
                'variants' => fn ($q) => $q->where('status', 'active')->orderBy('id'),
            ])
            ->where('status', ProductStatus::Active->value)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                    ->orWhere('approval_status', 'approved');
            })
            ->whereHas('variants', fn ($q) => $q->where('status', 'active')->where('stock', '>', 0));

        if ($categoryId === 'popular' || $popular) {
            $query->where('is_featured', true);
        } elseif ($categoryId) {
            $query->where('category_id', (int) $categoryId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->latest('id')->paginate($perPage);

        $products = $paginator->getCollection()->map(fn (Product $product) => $this->serializeProduct($product));

        return response()->json([
            'data' => $products,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $code = trim((string) $request->input('code', ''));

        if ($code === '') {
            return response()->json(['message' => 'Barcode is required.'], 422);
        }

        $variant = ProductVariant::query()
            ->with(['product.images' => fn ($q) => $q->orderBy('sort_order')->limit(1)])
            ->where('status', 'active')
            ->where(function ($q) use ($code) {
                $q->where('barcode', $code)
                    ->orWhere('sku', $code);
            })
            ->first();

        if (! $variant || ! $variant->product) {
            return response()->json(['message' => 'Product not found for barcode: '.$code], 404);
        }

        $product = $variant->product;

        if ($product->status !== ProductStatus::Active
            || ($product->approval_status !== null && $product->approval_status !== 'approved')) {
            return response()->json(['message' => 'Product is not available for sale.'], 422);
        }

        if ($variant->stock < 1) {
            return response()->json(['message' => 'Out of stock: '.$product->name], 422);
        }

        return response()->json([
            'product' => $this->serializeProduct($product->load([
                'variants' => fn ($q) => $q->where('status', 'active')->orderBy('id'),
                'defaultVariant',
                'category:id,name',
            ])),
            'variant' => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode ?: $variant->sku,
                'price' => (float) $variant->price,
                'stock' => (int) $variant->stock,
            ],
        ]);
    }

    protected function serializeProduct(Product $product): array
    {
        $variants = $product->variants->map(fn (ProductVariant $v) => [
            'id' => $v->id,
            'name' => $v->name,
            'sku' => $v->sku,
            'barcode' => $v->barcode ?: $v->sku,
            'price' => (float) $v->price,
            'stock' => (int) $v->stock,
        ])->values();

        $default = $product->defaultVariant ?? $product->variants->first();
        $stock = (int) $product->variants->sum('stock');
        $initial = strtoupper(Str::substr($product->name, 0, 1));

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $default?->sku ?? $product->getAttributes()['sku'] ?? null,
            'barcode' => $default?->barcode ?: ($default?->sku ?? $product->getAttributes()['sku'] ?? null),
            'price' => (float) ($default?->price ?? 0),
            'stock' => $stock,
            'image' => $product->primary_image_url,
            'initial' => $initial,
            'category' => $product->category?->name,
            'variant_id' => $default?->id,
            'variants' => $variants,
            'has_multiple_variants' => $variants->count() > 1,
        ];
    }

    public function customers(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $customers = User::query()
            ->role('customer')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
            ]);

        return response()->json(['data' => $customers]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'invoice_discount' => ['nullable', 'numeric', 'min:0'],
            'invoice_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', 'in:cash,cod,bkash,nagad'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $orders = $this->posService->checkout(
                cashier: $request->user(),
                items: $data['items'],
                customerId: $data['customer_id'] ?? null,
                invoiceDiscount: (float) ($data['invoice_discount'] ?? 0),
                invoiceDiscountPercent: (float) ($data['invoice_discount_percent'] ?? 0),
                notes: $data['notes'] ?? null,
                paymentMethod: $data['payment_method'] ?? 'cash',
                amountPaid: isset($data['amount_paid']) ? (float) $data['amount_paid'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale completed successfully.',
            'orders' => $orders->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total' => (float) $order->total,
                'url' => route('admin.orders.show', $order),
            ]),
        ]);
    }

    public function held(Request $request): JsonResponse
    {
        return response()->json([
            'data' => array_values($request->session()->get('pos.held', [])),
        ]);
    }

    public function hold(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cart' => ['required', 'array', 'min:1'],
            'customer' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'invoice_discount' => ['nullable', 'numeric', 'min:0'],
            'invoice_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $held = $request->session()->get('pos.held', []);
        $id = (string) Str::uuid();

        $held[$id] = [
            'id' => $id,
            'label' => $data['label'] ?? ('Hold #'.(count($held) + 1)),
            'cart' => $data['cart'],
            'customer' => $data['customer'] ?? null,
            'notes' => $data['notes'] ?? null,
            'invoice_discount' => (float) ($data['invoice_discount'] ?? 0),
            'invoice_discount_percent' => (float) ($data['invoice_discount_percent'] ?? 0),
            'held_at' => now()->toIso8601String(),
            'item_count' => collect($data['cart'])->sum('quantity'),
        ];

        $request->session()->put('pos.held', $held);

        return response()->json([
            'message' => 'Sale held.',
            'held_count' => count($held),
            'id' => $id,
        ]);
    }

    public function deleteHeld(Request $request, string $id): JsonResponse
    {
        $held = $request->session()->get('pos.held', []);
        unset($held[$id]);
        $request->session()->put('pos.held', $held);

        return response()->json([
            'message' => 'Held sale removed.',
            'held_count' => count($held),
        ]);
    }
}
