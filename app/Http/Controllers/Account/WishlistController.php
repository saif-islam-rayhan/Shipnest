<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\CartService;
use App\Services\UserInterestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly UserInterestService $userInterestService,
    ) {}

    public function index(Request $request): View
    {
        $wishlists = $request->user()
            ->wishlists()
            ->with(['product.images', 'product.merchant', 'product.defaultVariant', 'variant'])
            ->latest()
            ->paginate(12);

        return view('account.wishlist.index', compact('wishlists'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ]);

        Wishlist::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
            'variant_id' => $request->input('variant_id'),
        ]);

        $this->userInterestService->trackWishlist($product, $request->user()->id);

        return back()->with('success', 'Added to wishlist.');
    }

    public function destroy(Request $request, Wishlist $wishlist): RedirectResponse
    {
        if ($wishlist->user_id !== $request->user()->id) {
            abort(403);
        }

        $wishlist->delete();

        return back()->with('success', 'Removed from wishlist.');
    }

    public function moveToCart(Request $request, Wishlist $wishlist): RedirectResponse
    {
        if ($wishlist->user_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $this->cartService->add(
                $wishlist->product_id,
                $wishlist->variant_id,
                1,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $wishlist->delete();

        return redirect()
            ->route('cart.index')
            ->with('success', 'Item moved to cart.');
    }
}
