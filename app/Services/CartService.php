<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CartService
{
    public function getCart(?User $user = null): Cart
    {
        if ($user) {
            return Cart::query()
                ->with(['items.product.images', 'items.product.shop', 'items.variant'])
                ->firstOrCreate(['user_id' => $user->id]);
        }

        $sessionId = Session::getId();

        return Cart::query()
            ->with(['items.product.images', 'items.product.shop', 'items.variant'])
            ->firstOrCreate(['session_id' => $sessionId], ['user_id' => null]);
    }

    public function add(int $productId, ?int $variantId = null, int $quantity = 1): CartItem
    {
        $product = Product::query()->with('variants')->findOrFail($productId);
        $cart = $this->getCart(Auth::user());

        return $this->addItem($cart, $product, $quantity, $variantId);
    }

    public function update(int $cartItemId, int $quantity): CartItem
    {
        $item = CartItem::query()->findOrFail($cartItemId);
        $cart = $this->getCart(Auth::user());

        if ($item->cart_id !== $cart->id) {
            throw new \InvalidArgumentException('Cart item not found.');
        }

        return $this->updateQuantity($item, $quantity);
    }

    public function remove(int $cartItemId): void
    {
        $item = CartItem::query()->findOrFail($cartItemId);
        $cart = $this->getCart(Auth::user());

        if ($item->cart_id !== $cart->id) {
            throw new \InvalidArgumentException('Cart item not found.');
        }

        $this->removeItem($item);
    }

    public function applyCoupon(string $code): Cart
    {
        $cart = $this->getCart(Auth::user());
        $coupon = Coupon::query()->where('code', $code)->valid()->first();

        if (! $coupon || ! $coupon->isValid()) {
            throw new \InvalidArgumentException('Invalid or expired coupon code.');
        }

        if ($coupon->min_order && $cart->subtotal < (float) $coupon->min_order) {
            throw new \InvalidArgumentException('Order total does not meet the minimum for this coupon.');
        }

        $cart->update(['coupon_code' => $code]);

        return $cart->fresh(['items.product.images', 'items.variant']);
    }

    public function removeCoupon(): Cart
    {
        $cart = $this->getCart(Auth::user());
        $cart->update(['coupon_code' => null]);

        return $cart->fresh(['items.product.images', 'items.variant']);
    }

    public function merge(User $user): void
    {
        $this->mergeGuestCart($user);
    }

    public function addItem(Cart $cart, Product $product, int $quantity = 1, ?int $variantId = null): CartItem
    {
        $variant = $this->resolveVariant($product, $variantId);

        if ($variant->stock < $quantity) {
            throw new \InvalidArgumentException('Insufficient stock for this product.');
        }

        $item = $cart->items()
            ->where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->first();

        if ($item) {
            $newQuantity = $item->quantity + $quantity;

            if ($variant->stock < $newQuantity) {
                throw new \InvalidArgumentException('Insufficient stock for this product.');
            }

            $item->update([
                'quantity' => $newQuantity,
                'price' => $variant->price,
            ]);

            return $item->fresh(['product.images', 'product.shop', 'variant']);
        }

        return $cart->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => $quantity,
            'price' => $variant->price,
        ])->load(['product.images', 'product.shop', 'variant']);
    }

    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return $item;
        }

        $variant = $item->variant ?? $this->resolveVariant($item->product);

        if ($variant->stock < $quantity) {
            throw new \InvalidArgumentException('Insufficient stock for this product.');
        }

        $item->update(['quantity' => $quantity]);

        return $item->fresh(['product.images', 'product.shop', 'variant']);
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update(['coupon_code' => null]);
    }

    public function getItemCount(?User $user = null): int
    {
        $cart = $this->getCart($user);

        return (int) $cart->items()->sum('quantity');
    }

    public function getTotals(Cart $cart): array
    {
        $cart->loadMissing('items');
        $subtotal = $cart->subtotal;
        $discount = 0;

        if ($cart->coupon_code) {
            $coupon = Coupon::query()->where('code', $cart->coupon_code)->valid()->first();
            if ($coupon && $coupon->isValid()) {
                $discount = $coupon->calculateDiscount($subtotal);
            }
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => max(0, $subtotal - $discount),
            'item_count' => $cart->item_count,
            'coupon_code' => $cart->coupon_code,
        ];
    }

    public function mergeGuestCart(User $user): void
    {
        $sessionId = Session::getId();
        $guestCart = Cart::query()
            ->with('items')
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->first();

        if (! $guestCart || $guestCart->items->isEmpty()) {
            return;
        }

        $userCart = $this->getCart($user);

        DB::transaction(function () use ($guestCart, $userCart) {
            foreach ($guestCart->items as $guestItem) {
                $existing = $userCart->items()
                    ->where('product_id', $guestItem->product_id)
                    ->where('variant_id', $guestItem->variant_id)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'quantity' => $existing->quantity + $guestItem->quantity,
                    ]);
                } else {
                    $userCart->items()->create([
                        'product_id' => $guestItem->product_id,
                        'variant_id' => $guestItem->variant_id,
                        'quantity' => $guestItem->quantity,
                        'price' => $guestItem->price,
                    ]);
                }
            }

            if ($guestCart->coupon_code && ! $userCart->coupon_code) {
                $userCart->update(['coupon_code' => $guestCart->coupon_code]);
            }

            $guestCart->items()->delete();
            $guestCart->delete();
        });
    }

    public function getItemsGroupedByShop(Cart $cart): array
    {
        $cart->load(['items.product.images', 'items.product.shop', 'items.variant']);

        return $cart->items
            ->groupBy(fn (CartItem $item) => $item->product->shop_id)
            ->map(function ($items, $shopId) {
                $shop = $items->first()->product->shop;

                return [
                    'shop' => $shop,
                    'items' => $items,
                    'subtotal' => $items->sum(fn (CartItem $item) => $item->total_price),
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveVariant(Product $product, ?int $variantId = null): ProductVariant
    {
        $product->loadMissing('variants');

        $variant = $variantId
            ? $product->variants->firstWhere('id', $variantId)
            : $product->variants->where('status', 'active')->first();

        if (! $variant) {
            throw new \InvalidArgumentException('This product is not available.');
        }

        return $variant;
    }
}
