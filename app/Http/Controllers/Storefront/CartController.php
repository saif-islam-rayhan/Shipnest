<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use App\Services\UserInterestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly UserInterestService $userInterestService,
    ) {}

    public function index(Request $request): View
    {
        $cart = $this->cartService->getCart($request->user());
        $groupedItems = $this->cartService->getItemsGroupedByShop($cart);
        $totals = $this->cartService->getTotals($cart);

        return view('storefront.cart.index', compact('cart', 'groupedItems', 'totals'));
    }

    public function store(AddToCartRequest $request): RedirectResponse
    {
        $product = Product::query()->with(['shop', 'variants', 'defaultVariant'])->findOrFail($request->validated('product_id'));
        $cart = $this->cartService->getCart($request->user());

        try {
            $this->cartService->addItem(
                $cart,
                $product,
                $request->validated('quantity'),
                $request->validated('variant_id'),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->userInterestService->trackCart(
            $product,
            $request->user()?->id,
            session()->getId(),
        );

        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout.index')->with('success', 'Product added to cart.');
        }

        return back()->with('success', 'Product added to cart.');
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): RedirectResponse
    {
        $cart = $this->cartService->getCart($request->user());

        if ($cartItem->cart_id !== $cart->id) {
            abort(403);
        }

        try {
            $this->cartService->updateQuantity($cartItem, $request->validated('quantity'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        $cart = $this->cartService->getCart($request->user());

        if ($cartItem->cart_id !== $cart->id) {
            abort(403);
        }

        $this->cartService->removeItem($cartItem);

        return back()->with('success', 'Item removed from cart.');
    }

    public function applyCoupon(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['coupon_code' => ['required', 'string', 'max:50']]);

        try {
            $cart = $this->cartService->applyCoupon($request->input('coupon_code'));
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $totals = $this->cartService->getTotals($cart);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Coupon applied successfully.',
                'totals' => $totals,
            ]);
        }

        return back()->with('success', 'Coupon applied successfully.');
    }

    public function removeCoupon(Request $request): JsonResponse|RedirectResponse
    {
        $cart = $this->cartService->removeCoupon();
        $totals = $this->cartService->getTotals($cart);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Coupon removed.',
                'totals' => $totals,
            ]);
        }

        return back()->with('success', 'Coupon removed.');
    }
}
