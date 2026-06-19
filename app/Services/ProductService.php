<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Builder as ScoutBuilder;

class ProductService
{
    public function search(
        ?string $query = null,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $shopId = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        string $sort = 'newest',
        int $perPage = 24,
    ): LengthAwarePaginator {
        if ($query) {
            return $this->scoutSearch($query, $categoryId, $brandId, $shopId, $minPrice, $maxPrice, $sort, $perPage);
        }

        return $this->databaseSearch($categoryId, $brandId, $shopId, $minPrice, $maxPrice, $sort, $perPage);
    }

    public function getFeatured(int $limit = 12)
    {
        return Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->withAvg('reviews', 'rating')
            ->active()
            ->inStock()
            ->featured()
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getNewArrivals(int $limit = 8)
    {
        return Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->withAvg('reviews', 'rating')
            ->active()
            ->inStock()
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getByCategory(int $categoryId, int $perPage = 24): LengthAwarePaginator
    {
        return Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->active()
            ->inStock()
            ->where('category_id', $categoryId)
            ->latest()
            ->paginate($perPage);
    }

    public function findBySlug(string $slug): Product
    {
        return Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant', 'reviews.user'])
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();
    }

    protected function scoutSearch(
        string $query,
        ?int $categoryId,
        ?int $brandId,
        ?int $shopId,
        ?float $minPrice,
        ?float $maxPrice,
        string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $builder = Product::search($query)->where('status', 'active');

        if ($categoryId) {
            $builder->where('category_id', $categoryId);
        }

        if ($brandId) {
            $builder->where('brand_id', $brandId);
        }

        if ($shopId) {
            $builder->where('shop_id', $shopId);
        }

        if ($minPrice !== null) {
            $builder->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $builder->where('price', '<=', $maxPrice);
        }

        $this->applyScoutSort($builder, $sort);

        return $builder->paginate($perPage)->through(function (Product $product) {
            return $product->load(['images', 'shop', 'category', 'brand']);
        });
    }

    protected function databaseSearch(
        ?int $categoryId,
        ?int $brandId,
        ?int $shopId,
        ?float $minPrice,
        ?float $maxPrice,
        string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->active()
            ->inStock();

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        if ($shopId) {
            $query->where('merchant_id', $shopId);
        }

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        $this->applyDatabaseSort($query, $sort);

        return $query->paginate($perPage);
    }

    protected function applyDatabaseSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => $query->orderByDesc('total_sold'),
            'rating' => $query->orderByDesc('rating'),
            default => $query->latest(),
        };
    }

    protected function applyScoutSort(ScoutBuilder $builder, string $sort): void
    {
        match ($sort) {
            'price_asc' => $builder->orderBy('price'),
            'price_desc' => $builder->orderBy('price', 'desc'),
            'popular' => $builder->orderBy('total_sold', 'desc'),
            'rating' => $builder->orderBy('rating', 'desc'),
            default => $builder->orderBy('created_at', 'desc'),
        };
    }
}
