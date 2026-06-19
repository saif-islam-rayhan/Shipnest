<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $price = fake()->randomFloat(2, 100, 50000);
        $comparePrice = fake()->boolean(40) ? $price * fake()->randomFloat(2, 1.05, 1.3) : null;

        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Default', 'Standard', 'Large', 'Small', 'Red', 'Blue', 'Black']),
            'sku' => 'VAR-'.strtoupper(Str::random(10)),
            'price' => $price,
            'compare_price' => $comparePrice ? round($comparePrice, 2) : null,
            'cost_price' => round($price * 0.7, 2),
            'stock' => fake()->numberBetween(0, 200),
            'weight' => fake()->randomFloat(2, 0.1, 10),
            'status' => 'active',
        ];
    }

    public function inStock(): static
    {
        return $this->state(fn () => ['stock' => fake()->numberBetween(10, 200)]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }
}
