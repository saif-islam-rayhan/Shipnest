<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::role('customer')->with('addresses')->get();
        $products = Product::query()->with(['variants', 'merchant'])->where('status', 'active')->get();
        $coupons = Coupon::query()->where('status', 'active')->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            return;
        }

        $statuses = [
            'pending' => 8,
            'confirmed' => 10,
            'processing' => 10,
            'shipped' => 10,
            'delivered' => 10,
            'cancelled' => 2,
        ];

        $statusPool = [];
        foreach ($statuses as $status => $count) {
            $statusPool = array_merge($statusPool, array_fill(0, $count, $status));
        }

        $paymentMethods = ['cod', 'sslcommerz', 'bkash', 'nagad'];

        for ($i = 0; $i < 50; $i++) {
            $customer = $customers->random();
            $address = $customer->addresses->first()
                ?? UserAddress::query()->where('user_id', $customer->id)->first();

            $status = $statusPool[$i] ?? fake()->randomElement(array_keys($statuses));
            $itemCount = fake()->numberBetween(1, 4);
            $selectedProducts = $products->random(min($itemCount, $products->count()));

            $subtotal = 0;
            $orderItemsData = [];

            foreach ($selectedProducts as $product) {
                $variant = $product->variants->where('status', 'active')->first()
                    ?? $product->variants->first();
                if (! $variant) {
                    continue;
                }

                $quantity = fake()->numberBetween(1, 3);
                $unitPrice = (float) $variant->price;
                $discount = fake()->boolean(20) ? round($unitPrice * 0.05, 2) : 0;
                $lineTotal = ($unitPrice - $discount) * $quantity;
                $subtotal += $lineTotal;

                $orderItemsData[] = [
                    'merchant_id' => $product->merchant_id,
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'total' => $lineTotal,
                    'status' => $status === 'cancelled' ? 'cancelled' : $status,
                ];
            }

            if (empty($orderItemsData)) {
                continue;
            }

            $orderDiscount = 0;
            $coupon = fake()->boolean(25) && $coupons->isNotEmpty() ? $coupons->random() : null;
            if ($coupon) {
                $orderDiscount = $coupon->calculateDiscount($subtotal);
            }

            $shippingCharge = $subtotal >= config('shipnest.free_shipping_threshold', 500) ? 0 : 120;
            $tax = 0;
            $total = $subtotal - $orderDiscount + $shippingCharge + $tax;

            $paymentStatus = match ($status) {
                'delivered', 'shipped', 'processing', 'confirmed' => 'paid',
                'cancelled' => fake()->randomElement(['pending', 'refunded']),
                default => 'pending',
            };

            $order = Order::query()->create([
                'user_id' => $customer->id,
                'order_number' => 'ORD-'.strtoupper(Str::random(10)),
                'status' => $status,
                'subtotal' => $subtotal,
                'discount' => $orderDiscount,
                'shipping_charge' => $shippingCharge,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => fake()->randomElement($paymentMethods),
                'payment_status' => $paymentStatus,
                'shipping_address_id' => $address?->id,
                'coupon_id' => $coupon?->id,
                'note' => fake()->optional(0.3)->sentence(),
            ]);

            foreach ($orderItemsData as $itemData) {
                OrderItem::query()->create(array_merge($itemData, ['order_id' => $order->id]));
            }

            $statusFlow = match ($status) {
                'pending' => ['pending'],
                'confirmed' => ['pending', 'confirmed'],
                'processing' => ['pending', 'confirmed', 'processing'],
                'shipped' => ['pending', 'confirmed', 'processing', 'shipped'],
                'delivered' => ['pending', 'confirmed', 'processing', 'shipped', 'delivered'],
                'cancelled' => ['pending', 'cancelled'],
                default => [$status],
            };

            foreach ($statusFlow as $flowStatus) {
                OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'status' => $flowStatus,
                    'comment' => "Order {$flowStatus}.",
                    'created_by' => null,
                ]);
            }

            if ($paymentStatus === 'paid') {
                $txn = PaymentTransaction::query()->create([
                    'user_id' => $customer->id,
                    'order_id' => $order->id,
                    'method' => $order->payment_method,
                    'transaction_id' => 'TXN-'.strtoupper(Str::random(12)),
                    'amount' => $total,
                    'status' => 'completed',
                    'gateway_response' => ['status' => 'success'],
                ]);

                $order->update(['payment_transaction_id' => $txn->transaction_id]);
            }
        }
    }
}
