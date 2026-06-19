<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'image' => 'banners/banner-'.fake()->numerify('####').'.jpg',
            'link' => fake()->optional()->url(),
            'type' => 'home',
            'position' => fake()->randomElement(['top', 'middle', 'bottom']),
            'sort_order' => fake()->numberBetween(0, 10),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
