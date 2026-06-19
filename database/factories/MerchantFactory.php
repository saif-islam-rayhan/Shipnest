<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    public function definition(): array
    {
        $shopName = fake()->company().' Store';

        return [
            'user_id' => User::factory(),
            'shop_name' => $shopName,
            'shop_slug' => Str::slug($shopName).'-'.fake()->unique()->numerify('###'),
            'logo' => null,
            'banner' => null,
            'description' => fake()->paragraph(),
            'phone' => '01'.fake()->numerify('#########'),
            'address' => fake()->streetAddress(),
            'district' => fake()->randomElement(['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi']),
            'commission_rate' => fake()->randomFloat(2, 5, 15),
            'balance' => 0,
            'total_sales' => fake()->randomFloat(2, 0, 500000),
            'rating' => fake()->randomFloat(2, 3, 5),
            'is_verified' => fake()->boolean(60),
            'status' => 'active',
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['is_verified' => true, 'status' => 'active']);
    }
}
