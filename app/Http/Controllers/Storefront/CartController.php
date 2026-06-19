<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function index(Request $request): View
    {
        $cart = $this->cartService->getCart($request->user());
        $groupedItems = $this->cartService->getItemsGroupedByShop($cart);

        return view('storefront.cart.index', compact('cart', 'groupedItems'));
    }

    public function store(AddToCartRequest $request): RedirectResponse
    {
        $product = Product::query()->with('shop')->findOrFail($request->validated('product_id'));
        $cart = $this->cartService->getCart($request->user());

        try {
            $this->cartService->addItem($cart, $product, $request->validated('quantity'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
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
}
