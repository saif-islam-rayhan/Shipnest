<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderReturn>
 */
class OrderReturnFactory extends Factory
{
    protected $model = OrderReturn::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_item_id' => OrderItem::factory(),
            'user_id' => User::factory(),
            'reason' => fake()->randomElement(['defective', 'wrong_item', 'not_as_described', 'changed_mind']),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected', 'refunded']),
            'refund_amount' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}
