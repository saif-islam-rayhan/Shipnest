<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAddress>
 */
class UserAddressFactory extends Factory
{
    protected $model = UserAddress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Office', 'Other']),
            'recipient_name' => fake()->name(),
            'phone' => '01'.fake()->numerify('#########'),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi']),
            'district' => fake()->randomElement(['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi']),
            'thana' => fake()->citySuffix(),
            'postal_code' => fake()->numerify('####'),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
