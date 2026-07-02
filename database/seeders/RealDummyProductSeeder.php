<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealDummyProductSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = array_merge(
            require __DIR__.'/data/real_dummy_products.php',
            require __DIR__.'/data/common_products_50.php',
        );

        $merchants = Merchant::query()->where('status', 'active')->get()->keyBy('shop_name');
        $brands = Brand::query()->where('status', 'active')->get()->keyBy('name');

        if ($merchants->isEmpty()) {
            $this->command?->warn('No active merchants found. Run MerchantSeeder first.');

            return;
        }

        foreach ($catalog as $index => $item) {
            $merchant = $merchants->get($item['merchant']);
            if (! $merchant) {
                continue;
            }

            $category = $this->findCategory($item['parent_category'], $item['category']);
            if (! $category) {
                $this->command?->warn("Category not found: {$item['parent_category']} / {$item['category']}");

                continue;
            }

            $brand = isset($item['brand']) ? $brands->get($item['brand']) : null;
            $slug = Str::slug($item['name']).'-'.$merchant->id;
            $sku = 'DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

            $product = Product::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'merchant_id' => $merchant->id,
                    'category_id' => $category->id,
                    'brand_id' => $brand?->id,
                    'name' => $item['name'],
                    'sku' => $sku,
                    'short_description' => $item['short_description'],
                    'description' => "Order {$item['name']} online at ShipNest. ".$item['short_description'],
                    'status' => 'active',
                    'approval_status' => 'approved',
                    'is_featured' => $item['featured'] ?? false,
                ],
            );

            ProductVariant::query()->updateOrCreate(
                ['product_id' => $product->id, 'sku' => $sku.'-DEFAULT'],
                [
                    'name' => 'Default',
                    'price' => $item['price'],
                    'compare_price' => $item['compare_price'] ?? null,
                    'cost_price' => round($item['price'] * 0.68, 2),
                    'stock' => $item['stock'],
                    'weight' => 0.5,
                    'status' => 'active',
                ],
            );

            $imageUrl = $item['image'];

            $product->images()->delete();
            ProductImage::query()->create([
                'product_id' => $product->id,
                'image_path' => $imageUrl,
                'sort_order' => 0,
            ]);

            $product->update(['thumbnail' => $imageUrl]);
        }

        $this->command?->info('Inserted '.count($catalog).' demo products.');
    }

    private function findCategory(string $parentName, string $childName): ?Category
    {
        return Category::query()
            ->where('name', $childName)
            ->whereHas('parent', fn ($q) => $q->where('name', $parentName))
            ->first();
    }
}
