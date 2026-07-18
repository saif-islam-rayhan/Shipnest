<?php

namespace App\Services\Merchant;

use App\Enums\ProductStatus;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MerchantProductService
{
    public function create(Merchant $shop, array $data, array $variants, array $attributes, array $images = [], ?array $imageOrder = null, array $imageUrls = []): Product
    {
        return DB::transaction(function () use ($shop, $data, $variants, $attributes, $images, $imageOrder, $imageUrls) {
            $product = $shop->products()->create([
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($shop->id, $data['name']),
                'sku' => $data['sku'],
                'description' => $data['description'] ?? null,
                'short_description' => $data['short_description'] ?? null,
                'status' => $data['status'] ?? ProductStatus::Draft->value,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'tags' => $this->parseTags($data['tags'] ?? null),
            ]);

            $this->syncVariants($product, $variants);
            $this->syncAttributes($product, $attributes);
            $this->storeImages($product, $images, $imageOrder);
            $this->storeImageUrls($product, $imageUrls);
            $this->updateThumbnail($product, $imageOrder);

            return $product->fresh(['variants', 'images', 'attributes', 'category', 'brand']);
        });
    }

    public function update(Product $product, array $data, array $variants, array $attributes, array $images = [], ?array $imageOrder = null, array $removeImageIds = [], array $imageUrls = []): Product
    {
        return DB::transaction(function () use ($product, $data, $variants, $attributes, $images, $imageOrder, $removeImageIds, $imageUrls) {
            $product->update([
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->uniqueSlug($product->merchant_id, $data['name'], $product->id),
                'sku' => $data['sku'],
                'description' => $data['description'] ?? null,
                'short_description' => $data['short_description'] ?? null,
                'status' => $data['status'] ?? $product->status,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'tags' => $this->parseTags($data['tags'] ?? null),
            ]);

            $this->syncVariants($product, $variants);
            $this->syncAttributes($product, $attributes);
            $this->removeImages($product, $removeImageIds);
            $this->storeImages($product, $images, $imageOrder);
            $this->storeImageUrls($product, $imageUrls);
            $this->updateThumbnail($product, $imageOrder);

            return $product->fresh(['variants', 'images', 'attributes', 'category', 'brand']);
        });
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @param  array<int, string>  $imageUrls
     */
    public function addImages(Product $product, array $images = [], array $imageUrls = []): Product
    {
        $this->storeImages($product, $images, null);
        $this->storeImageUrls($product, $imageUrls);
        $this->updateThumbnail($product, null);

        return $product->fresh(['images']);
    }

    /**
     * @param  array<int, string>  $paths
     */
    public function attachStoredPaths(Product $product, array $paths): Product
    {
        foreach ($paths as $path) {
            $path = trim((string) $path);
            if ($path === '' || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $product->images()->create([
                'image_path' => $path,
                'sort_order' => $product->images()->count(),
            ]);
        }

        $this->updateThumbnail($product, null);

        return $product->fresh(['images']);
    }

    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $copy = $product->replicate(['slug', 'sku']);
            $copy->name = $product->name.' (Copy)';
            $copy->slug = $this->uniqueSlug($product->merchant_id, $copy->name);
            $copy->sku = $product->sku.'-'.strtoupper(Str::random(4));
            $copy->status = ProductStatus::Draft->value;
            $copy->save();

            foreach ($product->variants as $variant) {
                $copySku = $variant->sku.'-'.strtoupper(Str::random(3));
                $copy->variants()->create([
                    'name' => $variant->name,
                    'sku' => $copySku,
                    'barcode' => $copySku,
                    'price' => $variant->price,
                    'compare_price' => $variant->compare_price,
                    'cost_price' => $variant->cost_price,
                    'stock' => $variant->stock,
                    'weight' => $variant->weight,
                    'status' => $variant->status,
                ]);
            }

            foreach ($product->attributes as $attr) {
                $copy->attributes()->create([
                    'attribute_name' => $attr->attribute_name,
                    'attribute_value' => $attr->attribute_value,
                ]);
            }

            return $copy->load(['variants', 'attributes']);
        });
    }

    protected function syncVariants(Product $product, array $variants): void
    {
        $keepIds = [];
        $index = 0;

        foreach ($variants as $variant) {
            if (empty($variant['name']) && empty($variant['price'])) {
                continue;
            }

            $index++;
            $variantSku = trim((string) ($variant['sku'] ?? ''));
            if ($variantSku === '') {
                $variantSku = count($variants) === 1
                    ? $product->sku
                    : $product->sku.'-V'.$index;
            }

            $payload = [
                'name' => $variant['name'] ?? 'Default',
                'sku' => $variantSku,
                'barcode' => $this->resolveBarcode($variant, $variantSku, $product),
                'price' => $variant['price'] ?? 0,
                'compare_price' => $variant['compare_price'] ?? null,
                'cost_price' => $variant['cost_price'] ?? null,
                'stock' => $variant['stock'] ?? 0,
                'weight' => $variant['weight'] ?? null,
                'status' => 'active',
            ];

            if (! empty($variant['id'])) {
                $existing = $product->variants()->find($variant['id']);
                if ($existing) {
                    $existing->update($payload);
                    $keepIds[] = $existing->id;

                    continue;
                }
            }

            $created = $product->variants()->create($payload);
            $keepIds[] = $created->id;
        }

        if ($keepIds) {
            $product->variants()->whereNotIn('id', $keepIds)->delete();
        } elseif ($product->variants()->count() === 0) {
            $product->variants()->create([
                'name' => 'Default',
                'sku' => $product->sku,
                'barcode' => $product->sku,
                'price' => 0,
                'stock' => 0,
                'status' => 'active',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    protected function resolveBarcode(array $variant, string $variantSku, Product $product): string
    {
        $barcode = trim((string) ($variant['barcode'] ?? ''));
        if ($barcode === '') {
            $barcode = $variantSku !== '' ? $variantSku : $product->sku;
        }

        return $barcode;
    }

    protected function syncAttributes(Product $product, array $attributes): void
    {
        $product->attributes()->delete();

        foreach ($attributes as $attr) {
            if (empty($attr['name']) || empty($attr['value'])) {
                continue;
            }

            $product->attributes()->create([
                'attribute_name' => $attr['name'],
                'attribute_value' => $attr['value'],
            ]);
        }
    }

    protected function storeImages(Product $product, array $images, ?array $imageOrder): void
    {
        foreach ($images as $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            $path = $image->store('products', 'public');
            $product->images()->create([
                'image_path' => $path,
                'sort_order' => $product->images()->count(),
            ]);
        }

        if ($imageOrder) {
            foreach ($imageOrder as $index => $imageId) {
                if (is_numeric($imageId)) {
                    ProductImage::query()
                        ->where('product_id', $product->id)
                        ->where('id', $imageId)
                        ->update(['sort_order' => $index]);
                }
            }
        }

        $this->updateThumbnail($product, $imageOrder);
    }

    /**
     * @param  array<int, string>  $urls
     */
    protected function storeImageUrls(Product $product, array $urls): void
    {
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $product->images()->create([
                'image_path' => $url,
                'sort_order' => $product->images()->count(),
            ]);
        }
    }

    protected function removeImages(Product $product, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $product->images()->whereIn('id', $ids)->each(function (ProductImage $image) {
            if (! ProductImage::isExternalUrl($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
            $image->delete();
        });
    }

    protected function updateThumbnail(Product $product, ?array $imageOrder): void
    {
        $firstId = $imageOrder[0] ?? null;
        $image = $firstId
            ? $product->images()->find($firstId)
            : $product->images()->orderBy('sort_order')->first();

        if ($image) {
            $product->update(['thumbnail' => $image->image_path]);
        }
    }

    protected function uniqueSlug(int $merchantId, string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Product::query()
            ->where('merchant_id', $merchantId)
            ->where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    protected function parseTags(?string $tags): ?array
    {
        if (! $tags) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $tags))));
    }
}
