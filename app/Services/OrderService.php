<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function createFromCart(
        User $user,
        Address $address,
        PaymentMethod $paymentMethod,
        ?string $couponCode = null,
        ?string $notes = null,
    ): Collection {
        $cart = $this->cartService->getCart($user);
        $cart->load(['items.product.shop', 'items.product.images']);

        if ($cart->items->isEmpty()) {
            throw new \InvalidArgumentException('Your cart is empty.');
        }

        $grouped = $cart->items->groupBy(fn (CartItem $item) => $item->product->shop_id);

        return DB::transaction(function () use ($user, $address, $paymentMethod, $couponCode, $notes, $cart, $grouped) {
            $orders = collect();

            foreach ($grouped as $shopId => $items) {
                $shop = $items->first()->product->shop;
                $subtotal = $items->sum(fn (CartItem $item) => $item->total_price);
                $shippingFee = $this->calculateShippingFee($items);
                $discount = 0;

                if ($couponCode) {
                    $coupon = Coupon::query()
                        ->where('code', $couponCode)
                        ->where(function ($query) use ($shopId) {
                            $query->whereNull('shop_id')->orWhere('shop_id', $shopId);
                        })
                        ->first();

                    if ($coupon && $coupon->isValid()) {
                        $discount = $coupon->calculateDiscount($subtotal);
                        $coupon->increment('used_count');
                    }
                }

                $total = $subtotal + $shippingFee - $discount;
                $commissionAmount = round($total * ($shop->commission_rate / 100), 2);

                $order = Order::query()->create([
                    'order_number' => $this->generateOrderNumber(),
                    'user_id' => $user->id,
                    'shop_id' => $shopId,
                    'address_id' => $address->id,
                    'status' => OrderStatus::Pending,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentMethod === PaymentMethod::Cod
                        ? PaymentStatus::Pending
                        : PaymentStatus::Processing,
                    'subtotal' => $subtotal,
                    'shipping_fee' => $shippingFee,
                    'discount' => $discount,
                    'total' => $total,
                    'commission_amount' => $commissionAmount,
                    'coupon_code' => $couponCode,
                    'notes' => $notes,
                    'shipping_address' => $address->toShippingArray(),
                ]);

                foreach ($items as $cartItem) {
                    $product = $cartItem->product;

                    if ($product->status !== ProductStatus::Active || $product->stock < $cartItem->quantity) {
                        throw new \InvalidArgumentException("Product {$product->name} is no longer available.");
                    }

                    $primaryImage = $product->images->firstWhere('is_primary', true)
                        ?? $product->images->first();

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'product_image' => $primaryImage?->path,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => $cartItem->unit_price,
                        'total_price' => $cartItem->total_price,
                    ]);

                    $product->decrement('stock', $cartItem->quantity);
                    $product->increment('total_sold', $cartItem->quantity);

                    if ($product->stock <= 0) {
                        $product->update(['status' => ProductStatus::OutOfStock]);
                    }
                }

                $shop->increment('total_orders');
                $orders->push($order->load(['items', 'shop', 'user']));
            }

            $this->cartService->clear($cart);

            return $orders;
        });
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        $updates = ['status' => $status];

        match ($status) {
            OrderStatus::Shipped => $updates['shipped_at'] = now(),
            OrderStatus::Delivered => $updates['delivered_at'] = now(),
            OrderStatus::Cancelled => $updates['cancelled_at'] = now(),
            default => null,
        };

        $order->update($updates);

        return $order->fresh(['items', 'shop', 'user', 'payment']);
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'SN-'.strtoupper(Str::random(8));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    protected function calculateShippingFee(Collection $items): float
    {
        $hasFreeShipping = $items->every(fn (CartItem $item) => $item->product->is_free_shipping);

        if ($hasFreeShipping) {
            return 0;
        }

        $subtotal = $items->sum(fn (CartItem $item) => $item->total_price);

        return $subtotal >= config('shipnest.free_shipping_threshold', 500) ? 0 : 60;
    }
}
