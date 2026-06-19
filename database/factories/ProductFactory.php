<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'merchant_id' => Merchant::factory(),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('#####'),
            'sku' => 'SN-'.strtoupper(Str::random(8)),
            'description' => fake()->paragraphs(3, true),
            'short_description' => fake()->sentence(),
            'thumbnail' => null,
            'status' => 'active',
            'is_featured' => fake()->boolean(15),
            'warranty' => fake()->optional()->randomElement(['6 months', '1 year', '2 years']),
            'tags' => fake()->words(4),
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }
}
