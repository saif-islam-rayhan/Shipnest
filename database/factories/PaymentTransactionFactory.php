<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'method' => fake()->randomElement(['sslcommerz', 'bkash', 'nagad', 'cod']),
            'transaction_id' => 'TXN-'.strtoupper(Str::random(12)),
            'amount' => fake()->randomFloat(2, 500, 50000),
            'status' => fake()->randomElement(['pending', 'completed', 'failed', 'refunded']),
            'gateway_response' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
