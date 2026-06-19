<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CartService
{
    public function getCart(?User $user = null): Cart
    {
        if ($user) {
            return Cart::query()
                ->with(['items.product.images', 'items.product.shop'])
                ->firstOrCreate(['user_id' => $user->id]);
        }

        $sessionId = Session::getId();

        return Cart::query()
            ->with(['items.product.images', 'items.product.shop'])
            ->firstOrCreate(['session_id' => $sessionId], ['user_id' => null]);
    }

    public function addItem(Cart $cart, Product $product, int $quantity = 1): CartItem
    {
        if ($product->stock < $quantity) {
            throw new \InvalidArgumentException('Insufficient stock for this product.');
        }

        $item = $cart->items()->where('product_id', $product->id)->first();

        if ($item) {
            $newQuantity = $item->quantity + $quantity;

            if ($product->stock < $newQuantity) {
                throw new \InvalidArgumentException('Insufficient stock for this product.');
            }

            $item->update([
                'quantity' => $newQuantity,
                'unit_price' => $product->price,
            ]);

            return $item->fresh(['product.images', 'product.shop']);
        }

        return $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
        ])->load(['product.images', 'product.shop']);
    }

    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return $item;
        }

        $product = $item->product;

        if ($product->stock < $quantity) {
            throw new \InvalidArgumentException('Insufficient stock for this product.');
        }

        $item->update(['quantity' => $quantity]);

        return $item->fresh(['product.images', 'product.shop']);
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    public function getItemCount(?User $user = null): int
    {
        $cart = $this->getCart($user);

        return (int) $cart->items()->sum('quantity');
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
                $existing = $userCart->items()->where('product_id', $guestItem->product_id)->first();

                if ($existing) {
                    $existing->update([
                        'quantity' => $existing->quantity + $guestItem->quantity,
                    ]);
                } else {
                    $userCart->items()->create([
                        'product_id' => $guestItem->product_id,
                        'quantity' => $guestItem->quantity,
                        'unit_price' => $guestItem->unit_price,
                    ]);
                }
            }

            $guestCart->items()->delete();
            $guestCart->delete();
        });
    }

    public function getItemsGroupedByShop(Cart $cart): array
    {
        $cart->load(['items.product.images', 'items.product.shop']);

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
}
