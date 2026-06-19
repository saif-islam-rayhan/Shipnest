<?php

namespace Database\Factories;

use App\Models\FlashSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlashSale>
 */
class FlashSaleFactory extends Factory
{
    protected $model = FlashSale::class;

    public function definition(): array
    {
        $startsAt = now()->subHours(2);
        $endsAt = now()->addDays(2);

        return [
            'title' => fake()->words(3, true).' Flash Sale',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'active',
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(5),
            'status' => 'scheduled',
        ]);
    }
}
