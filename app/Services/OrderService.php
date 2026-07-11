<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly UserInterestService $userInterestService,
    ) {}

    public function placeOrder(User $user, array $data): Collection
    {
        $address = $this->resolveAddress($user, $data);
        $cart = $this->cartService->getCart($user);
        $cart->load(['items.product.shop', 'items.product.images', 'items.variant']);

        if ($cart->items->isEmpty()) {
            throw new \InvalidArgumentException('Your cart is empty.');
        }

        $couponCode = $cart->coupon_code;
        $shippingMethod = $data['shipping_method'] ?? 'standard';
        $paymentMethod = PaymentMethod::from($data['payment_method']);
        $paymentReference = $data['payment_reference'] ?? null;
        $notes = $data['notes'] ?? null;

        $totals = $this->calculateTotal($cart, $shippingMethod, $couponCode);
        $grouped = $cart->items->groupBy(fn (CartItem $item) => $item->product->shop_id);
        $totalShipping = $totals['shipping'];

        $orders = DB::transaction(function () use (
            $user, $address, $paymentMethod, $paymentReference, $notes,
            $cart, $grouped, $totals, $shippingMethod, $totalShipping
        ) {
            $orders = collect();
            $coupon = $totals['coupon'];
            $firstShop = true;

            foreach ($grouped as $items) {
                $shopSubtotal = $items->sum(fn (CartItem $item) => $item->total_price);
                $shopDiscount = $coupon && $totals['subtotal'] > 0
                    ? round($totals['discount'] * ($shopSubtotal / $totals['subtotal']), 2)
                    : 0;
                $shopShipping = $firstShop ? $totalShipping : 0;
                $firstShop = false;
                $shopTotal = $shopSubtotal - $shopDiscount + $shopShipping;

                $order = Order::query()->create([
                    'order_number' => $this->generateOrderNumber(),
                    'user_id' => $user->id,
                    'status' => OrderStatus::Pending->value,
                    'subtotal' => $shopSubtotal,
                    'discount' => $shopDiscount,
                    'shipping_charge' => $shopShipping,
                    'shipping_method' => $shippingMethod,
                    'tax' => 0,
                    'total' => $shopTotal,
                    'payment_method' => $paymentMethod->value,
                    'payment_status' => $paymentMethod === PaymentMethod::Cod
                        ? PaymentStatus::Pending->value
                        : PaymentStatus::Processing->value,
                    'payment_reference' => $paymentReference,
                    'shipping_address_id' => $address->id,
                    'coupon_id' => $coupon?->id,
                    'note' => $notes,
                ]);

                foreach ($items as $cartItem) {
                    $product = $cartItem->product;
                    $variant = $cartItem->variant ?? $product->defaultVariant;

                    if ($product->status !== ProductStatus::Active || ! $variant || $variant->stock < $cartItem->quantity) {
                        throw new \InvalidArgumentException("Product {$product->name} is no longer available.");
                    }

                    $order->items()->create([
                        'merchant_id' => $product->merchant_id,
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'product_name' => $product->name,
                        'variant_name' => $variant->name,
                        'sku' => $variant->sku,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => $cartItem->unit_price,
                        'discount' => 0,
                        'total' => $cartItem->total_price,
                        'status' => OrderStatus::Pending->value,
                    ]);

                    $variant->decrement('stock', $cartItem->quantity);
                }

                $orders->push($order->load(['items', 'shop', 'user', 'shippingAddress']));
            }

            if ($coupon && $totals['discount'] > 0) {
                $coupon->increment('used_count');
            }

            $this->cartService->clear($cart);

            return $orders;
        });

        foreach ($orders as $order) {
            $order->load(['items.product']);
            foreach ($order->items as $item) {
                if ($item->product) {
                    $this->userInterestService->trackPurchase($item->product, $user->id);
                }
            }
        }

        return $orders;
    }

    /** @deprecated Use placeOrder() */
    public function createFromCart(
        User $user,
        UserAddress $address,
        PaymentMethod $paymentMethod,
        ?string $couponCode = null,
        ?string $notes = null,
    ): Collection {
        return $this->placeOrder($user, [
            'address_id' => $address->id,
            'shipping_method' => 'standard',
            'payment_method' => $paymentMethod->value,
            'notes' => $notes,
        ]);
    }

    public function calculateTotal(Cart $cart, string $shippingMethod = 'standard', ?string $couponCode = null): array
    {
        $cart->loadMissing('items');
        $subtotal = $cart->subtotal;
        $discount = 0;
        $coupon = null;

        if ($couponCode) {
            $coupon = Coupon::query()->where('code', $couponCode)->valid()->first();
            if ($coupon && $coupon->isValid()) {
                $discount = $coupon->calculateDiscount($subtotal);
            }
        }

        $shipping = $this->applyShipping($shippingMethod, $subtotal);
        $total = max(0, $subtotal - $discount + $shipping);

        return compact('subtotal', 'discount', 'shipping', 'total', 'coupon');
    }

    public function applyShipping(string $method, float $subtotal): float
    {
        if (config('shipping.free_shipping_enabled', false)
            && $subtotal >= config('shipnest.free_shipping_threshold', 500)) {
            return 0;
        }

        $methods = config('shipping.methods', []);

        return (float) ($methods[$method]['rate'] ?? 60);
    }

    public function confirmOrder(Order $order): Order
    {
        if ($order->status !== OrderStatus::Confirmed) {
            $order->update(['status' => OrderStatus::Confirmed->value]);
            $this->sendConfirmationEmail($order->fresh(['user', 'items', 'shippingAddress']));
        }

        return $order->fresh();
    }

    public function sendConfirmationEmail(Order $order): void
    {
        SendOrderConfirmationEmail::dispatch($order);
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        $updates = ['status' => $status->value];

        if ($status === OrderStatus::Delivered && ! $order->delivered_at) {
            $updates['delivered_at'] = now();
        }

        $order->update($updates);

        $order->statusHistories()->create([
            'status' => $status->value,
            'comment' => null,
            'created_by' => auth()->id(),
        ]);

        return $order->fresh(['items', 'shop', 'user', 'payment']);
    }

    public function cancelOrder(Order $order): Order
    {
        if (! $order->canBeCancelled()) {
            throw new \InvalidArgumentException('This order cannot be cancelled.');
        }

        return DB::transaction(function () use ($order) {
            $order->load('items.variant');

            foreach ($order->items as $item) {
                if ($item->variant) {
                    $item->variant->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => OrderStatus::Cancelled->value]);

            $order->statusHistories()->create([
                'status' => OrderStatus::Cancelled->value,
                'comment' => 'Cancelled by customer',
                'created_by' => auth()->id(),
            ]);

            return $order->fresh(['items', 'shop', 'user', 'payment', 'shippingAddress']);
        });
    }

    public function getEstimatedDelivery(Order $order): string
    {
        $method = $order->shipping_method ?? 'standard';
        $methods = config('shipping.methods', []);

        return $methods[$method]['days'] ?? '3-5 business days';
    }

    protected function resolveAddress(User $user, array $data): UserAddress
    {
        if (! empty($data['address_id'])) {
            return UserAddress::query()
                ->where('user_id', $user->id)
                ->findOrFail($data['address_id']);
        }

        $new = $data['new_address'] ?? $data;
        $isDefault = (bool) ($new['is_default'] ?? false);

        if ($isDefault) {
            UserAddress::query()->where('user_id', $user->id)->update(['is_default' => false]);
        }

        return UserAddress::query()->create([
            'user_id' => $user->id,
            'label' => $new['label'] ?? 'Home',
            'recipient_name' => $new['recipient_name'],
            'phone' => $new['phone'],
            'address_line1' => $new['address_line1'],
            'city' => $new['city'],
            'district' => $new['district'],
            'thana' => $new['thana'] ?? null,
            'postal_code' => $new['postal_code'] ?? null,
            'latitude' => $new['latitude'] ?? null,
            'longitude' => $new['longitude'] ?? null,
            'is_default' => $isDefault,
        ]);
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'SN-'.strtoupper(Str::random(8));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
