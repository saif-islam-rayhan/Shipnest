<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAttribute>
 */
class ProductAttributeFactory extends Factory
{
    protected $model = ProductAttribute::class;

    public function definition(): array
    {
        $attributes = [
            'Color' => fake()->safeColorName(),
            'Size' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'Material' => fake()->randomElement(['Cotton', 'Polyester', 'Leather', 'Plastic']),
            'Weight' => fake()->numberBetween(100, 5000).'g',
        ];

        $name = fake()->randomElement(array_keys($attributes));

        return [
            'product_id' => Product::factory(),
            'attribute_name' => $name,
            'attribute_value' => $attributes[$name],
        ];
    }
}
