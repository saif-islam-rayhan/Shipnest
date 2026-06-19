<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 100, 10000);
        $quantity = fake()->numberBetween(1, 5);
        $discount = fake()->randomFloat(2, 0, $unitPrice * 0.1);
        $total = ($unitPrice - $discount) * $quantity;
        $productName = fake()->words(3, true);

        return [
            'order_id' => Order::factory(),
            'merchant_id' => Merchant::factory(),
            'product_id' => Product::factory(),
            'variant_id' => ProductVariant::factory(),
            'product_name' => ucfirst($productName),
            'variant_name' => fake()->randomElement(['Default', 'Standard', 'Large']),
            'sku' => 'SKU-'.strtoupper(Str::random(8)),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'total' => $total,
            'status' => fake()->randomElement(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled']),
        ];
    }
}
