<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['percentage', 'fixed']);

        return [
            'code' => strtoupper(Str::random(8)),
            'type' => $type,
            'value' => $type === 'percentage' ? fake()->numberBetween(5, 30) : fake()->randomFloat(2, 50, 500),
            'min_order' => fake()->optional()->randomFloat(2, 500, 2000),
            'max_discount' => $type === 'percentage' ? fake()->optional()->randomFloat(2, 100, 1000) : null,
            'usage_limit' => fake()->optional()->numberBetween(10, 500),
            'used_count' => 0,
            'starts_at' => now()->subDays(7),
            'expires_at' => now()->addMonths(3),
            'status' => 'active',
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subMonth(),
        ]);
    }
}
