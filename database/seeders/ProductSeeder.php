<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $merchants = Merchant::query()->where('is_verified', true)->get();
        $leafCategories = Category::query()->whereNotNull('parent_id')->where('status', 'active')->get();
        $brands = Brand::query()->where('status', 'active')->get();

        if ($merchants->isEmpty() || $leafCategories->isEmpty()) {
            return;
        }

        $productTemplates = [
            'Samsung Galaxy A54 5G', 'Apple AirPods Pro', 'Xiaomi Redmi Note 13', 'Sony WH-1000XM5',
            'Nike Air Max 90', 'Adidas Ultraboost 22', 'Puma Running Shoes', 'H&M Cotton T-Shirt',
            'Walton Refrigerator 350L', 'Vision Smart LED TV 43"', 'LG Washing Machine', 'Philips Air Fryer',
            'Unilever Dove Body Wash', 'L\'Oreal Shampoo', 'Maybelline Lipstick', 'Nivea Moisturizer',
            'Yoga Mat Premium', 'Dumbbell Set 20kg', 'Football Size 5', 'Cricket Bat English Willow',
            'The Alchemist Book', 'Atomic Habits', 'Harry Potter Box Set', 'Programming with PHP',
            'Lay\'s Potato Chips', 'Coca-Cola 2L', 'Basmati Rice 5kg', 'Organic Honey 500g',
            'LEGO City Set', 'Remote Control Car', 'Barbie Doll', 'Board Game Monopoly',
            'Bluetooth Speaker', 'USB-C Cable', 'Wireless Mouse', 'Mechanical Keyboard',
            'Denim Jeans Slim Fit', 'Leather Wallet', 'Sunglasses UV400', 'Smart Watch Series 8',
            'Coffee Maker', 'Non-Stick Pan Set', 'Bed Sheet King Size', 'Table Lamp LED',
            'Protein Powder 1kg', 'Resistance Bands Set', 'Tennis Racket', 'Swimming Goggles',
            'Kindle E-Reader', 'Notebook Pack of 5', 'Art Supplies Set', 'Puzzle 1000 Pieces',
            'Instant Noodles Pack', 'Green Tea 100 Bags', 'Olive Oil 1L', 'Chocolate Gift Box',
        ];

        $attributeSets = [
            ['Color', 'Black'], ['Color', 'White'], ['Size', 'M'], ['Size', 'L'],
            ['Material', 'Cotton'], ['Material', 'Plastic'], ['Weight', '500g'], ['Warranty', '1 Year'],
        ];

        $created = 0;
        $target = 100;

        while ($created < $target) {
            $name = $productTemplates[$created % count($productTemplates)];
            if ($created >= count($productTemplates)) {
                $name .= ' '.($created + 1);
            }

            $merchant = $merchants->random();
            $category = $leafCategories->random();
            $brand = $brands->random();
            $sku = 'SN-'.strtoupper(Str::random(8));
            $slug = Str::slug($name).'-'.$merchant->id.'-'.($created + 1);

            $price = fake()->randomFloat(2, 200, 80000);
            $comparePrice = fake()->boolean(50) ? round($price * fake()->randomFloat(2, 1.05, 1.25), 2) : null;

            $product = Product::query()->create([
                'merchant_id' => $merchant->id,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => $name,
                'slug' => $slug,
                'sku' => $sku,
                'short_description' => "Premium quality {$name} from {$merchant->shop_name}.",
                'description' => "Buy {$name} online at ShipNest. Best price in Bangladesh with fast delivery. Sold by {$merchant->shop_name}.",
                'status' => 'active',
                'is_featured' => $created < 20,
                'warranty' => fake()->optional()->randomElement(['6 months', '1 year', '2 years']),
                'tags' => fake()->words(3),
            ]);

            ProductVariant::query()->create([
                'product_id' => $product->id,
                'name' => 'Default',
                'sku' => $sku.'-V1',
                'price' => $price,
                'compare_price' => $comparePrice,
                'cost_price' => round($price * 0.65, 2),
                'stock' => fake()->numberBetween(5, 150),
                'weight' => fake()->randomFloat(2, 0.1, 5),
                'status' => 'active',
            ]);

            if (fake()->boolean(40)) {
                $colors = ['Red', 'Blue', 'Black', 'White'];
                foreach (array_slice($colors, 0, fake()->numberBetween(1, 3)) as $color) {
                    $variantPrice = round($price * fake()->randomFloat(2, 0.95, 1.1), 2);
                    ProductVariant::query()->create([
                        'product_id' => $product->id,
                        'name' => $color,
                        'sku' => $sku.'-'.strtoupper(substr($color, 0, 3)),
                        'price' => $variantPrice,
                        'compare_price' => $comparePrice ? round($variantPrice * 1.1, 2) : null,
                        'stock' => fake()->numberBetween(0, 50),
                        'status' => 'active',
                    ]);
                }
            }

            $imageCount = fake()->numberBetween(1, 4);
            for ($img = 0; $img < $imageCount; $img++) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'image_path' => 'products/placeholder-'.fake()->numerify('####').'.jpg',
                    'sort_order' => $img,
                ]);
            }

            $attrs = fake()->randomElements($attributeSets, fake()->numberBetween(2, 4));
            foreach ($attrs as [$attrName, $attrValue]) {
                ProductAttribute::query()->create([
                    'product_id' => $product->id,
                    'attribute_name' => $attrName,
                    'attribute_value' => $attrValue,
                ]);
            }

            $created++;
        }
    }
}
