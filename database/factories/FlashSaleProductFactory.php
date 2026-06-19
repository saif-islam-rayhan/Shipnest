<?php

namespace Database\Factories;

use App\Models\FlashSale;
use App\Models\FlashSaleProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlashSaleProduct>
 */
class FlashSaleProductFactory extends Factory
{
    protected $model = FlashSaleProduct::class;

    public function definition(): array
    {
        return [
            'flash_sale_id' => FlashSale::factory(),
            'product_id' => Product::factory(),
            'variant_id' => ProductVariant::factory(),
            'discount_type' => fake()->randomElement(['percentage', 'fixed']),
            'discount_value' => fake()->randomElement([10, 15, 20, 25, 500, 1000]),
            'stock' => fake()->numberBetween(5, 100),
        ];
    }
}
