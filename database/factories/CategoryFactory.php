<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->sentence(),
            'image' => null,
            'icon' => fake()->randomElement(['📱', '👗', '🏠', '💄', '⚽', '📚', '🍔', '🧸']),
            'is_featured' => fake()->boolean(20),
            'sort_order' => fake()->numberBetween(0, 100),
            'status' => 'active',
        ];
    }

    public function child(int $parentId): static
    {
        return $this->state(fn () => ['parent_id' => $parentId]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
