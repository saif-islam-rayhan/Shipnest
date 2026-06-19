<?php

namespace Database\Factories;

use App\Models\OtpVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OtpVerification>
 */
class OtpVerificationFactory extends Factory
{
    protected $model = OtpVerification::class;

    public function definition(): array
    {
        return [
            'phone' => '01'.fake()->numerify('#########'),
            'email' => null,
            'otp' => fake()->numerify('######'),
            'type' => fake()->randomElement(['registration', 'login', 'password_reset']),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['verified_at' => now()]);
    }

    public function forEmail(): static
    {
        return $this->state(fn () => [
            'phone' => null,
            'email' => fake()->safeEmail(),
        ]);
    }
}
