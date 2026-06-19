<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 500, 50000);
        $discount = fake()->randomFloat(2, 0, $subtotal * 0.2);
        $shipping = fake()->randomFloat(2, 60, 200);
        $tax = 0;
        $total = $subtotal - $discount + $shipping + $tax;

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'status' => fake()->randomElement(['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled']),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_charge' => $shipping,
            'tax' => $tax,
            'total' => $total,
            'payment_method' => fake()->randomElement(['cod', 'sslcommerz', 'bkash', 'nagad']),
            'payment_status' => fake()->randomElement(['pending', 'paid', 'failed', 'refunded']),
            'payment_transaction_id' => null,
            'shipping_address_id' => UserAddress::factory(),
            'coupon_id' => null,
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'payment_status' => 'pending']);
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['status' => 'delivered', 'payment_status' => 'paid']);
    }

    public function withCoupon(): static
    {
        return $this->state(fn () => ['coupon_id' => Coupon::factory()]);
    }
}
