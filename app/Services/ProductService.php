<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductService
{
    public function search(
        ?string $query = null,
        ?int $categoryId = null,
        ?array $categoryIds = null,
        ?int $brandId = null,
        ?array $brandIds = null,
        ?int $shopId = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        ?int $minRating = null,
        ?int $minDiscount = null,
        string $sort = 'newest',
        int $perPage = 24,
    ): LengthAwarePaginator {
        return $this->databaseSearch(
            query: $query,
            categoryId: $categoryId,
            categoryIds: $categoryIds,
            brandId: $brandId,
            brandIds: $brandIds,
            shopId: $shopId,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            minRating: $minRating,
            minDiscount: $minDiscount,
            sort: $sort,
            perPage: $perPage,
        );
    }

    public function getFeatured(int $limit = 12): Collection
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

    public function getNewArrivals(int $limit = 8): Collection
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
        $categoryIds = $this->resolveCategoryIds(Category::query()->find($categoryId));

        return $this->search(categoryIds: $categoryIds, perPage: $perPage);
    }

    public function getByMerchant(int $merchantId, int $limit = 8, ?int $excludeProductId = null): Collection
    {
        return Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->withAvg('reviews', 'rating')
            ->active()
            ->inStock()
            ->where('merchant_id', $merchantId)
            ->when($excludeProductId, fn ($q) => $q->where('id', '!=', $excludeProductId))
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function findBySlug(string $slug): Product
    {
        return Product::query()
            ->with([
                'images',
                'merchant',
                'category',
                'brand',
                'defaultVariant',
                'variants',
                'attributes',
                'reviews' => fn ($q) => $q->approved()->with('user')->latest(),
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->withSum('orderItems as order_items_sum_quantity', 'quantity')
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();
    }

    public function getBrandsWithCounts(): Collection
    {
        return Brand::query()
            ->active()
            ->withCount(['products' => fn ($q) => $q->active()->inStock()])
            ->orderBy('name')
            ->get();
    }

    public function getReviewDistribution(Product $product): array
    {
        $counts = $product->reviews()
            ->approved()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $distribution[$i] = (int) ($counts[$i] ?? 0);
        }

        return $distribution;
    }

    public function resolveCategoryIds(?Category $category): ?array
    {
        if (! $category) {
            return null;
        }

        $ids = collect([$category->id]);
        $category->loadMissing('children');

        foreach ($category->children as $child) {
            $ids = $ids->merge($this->resolveCategoryIds($child));
        }

        return $ids->unique()->values()->all();
    }

    protected function databaseSearch(
        ?string $query = null,
        ?int $categoryId = null,
        ?array $categoryIds = null,
        ?int $brandId = null,
        ?array $brandIds = null,
        ?int $shopId = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        ?int $minRating = null,
        ?int $minDiscount = null,
        string $sort = 'newest',
        int $perPage = 24,
    ): LengthAwarePaginator {
        $builder = Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->withAvg('reviews', 'rating')
            ->active()
            ->inStock();

        if ($query) {
            $builder->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('short_description', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
            });
        }

        if ($categoryIds) {
            $builder->whereIn('category_id', $categoryIds);
        } elseif ($categoryId) {
            $builder->where('category_id', $categoryId);
        }

        if ($brandIds) {
            $builder->whereIn('brand_id', $brandIds);
        } elseif ($brandId) {
            $builder->where('brand_id', $brandId);
        }

        if ($shopId) {
            $builder->where('merchant_id', $shopId);
        }

        $this->applyVariantPriceFilter($builder, $minPrice, $maxPrice);
        $this->applyDiscountFilter($builder, $minDiscount);

        if ($minRating) {
            $builder->having('reviews_avg_rating', '>=', $minRating);
        }

        $this->applyDatabaseSort($builder, $sort);

        return $builder->paginate($perPage)->withQueryString();
    }

    /**
     * Agent catalog search — multi-keyword OR match with relevance ranking.
     * "watch" matches "Smart Watch Series 8", "smart watch" matches full phrase.
     */
    public function agentCatalogSearch(
        string $query,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        int $perPage = 8,
    ): LengthAwarePaginator {
        $query = trim($query);
        $lower = strtolower($query);
        $words = array_values(array_filter(
            preg_split('/\s+/', $lower) ?: [],
            fn ($w) => mb_strlen($w) >= 2,
        ));

        $builder = Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->active()
            ->inStock();

        if ($query !== '') {
            $builder->where(function (Builder $q) use ($query, $words) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('short_description', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', "%{$query}%"));

                foreach ($words as $word) {
                    $q->orWhere('name', 'like', "%{$word}%")
                        ->orWhere('short_description', 'like', "%{$word}%")
                        ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', "%{$word}%"));
                }
            });

            $likeStart = $lower.'%';
            $likeContains = '%'.$lower.'%';
            $builder->orderByRaw(
                'CASE
                    WHEN LOWER(name) = ? THEN 0
                    WHEN LOWER(name) LIKE ? THEN 1
                    WHEN LOWER(name) LIKE ? THEN 2
                    ELSE 3
                END',
                [$lower, $likeStart, $likeContains],
            );
        }

        $this->applyVariantPriceFilter($builder, $minPrice, $maxPrice);

        return $builder->orderByDesc('is_featured')->latest()->paginate($perPage);
    }

    /**
     * Related trending products from ShipNest catalog for agent UI.
     *
     * @param  array<int, int>  $excludeProductIds
     * @return array<int, Product>
     */
    public function agentRelatedTrending(string $query, array $excludeProductIds = [], int $limit = 24): array
    {
        $query = trim($query);
        $lower = strtolower($query);

        $categoryIds = Product::query()
            ->active()
            ->inStock()
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('short_description', 'like', "%{$query}%");
            })
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->filter()
            ->values()
            ->all();

        if ($categoryIds === []) {
            $categoryIds = $this->guessCategoryIdsForAgentTerm($lower);
        } else {
            $categoryIds = $this->expandCategoryIds($categoryIds);
        }

        $builder = Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->active()
            ->inStock();

        if ($excludeProductIds !== []) {
            $builder->whereNotIn('id', $excludeProductIds);
        }

        if ($categoryIds !== []) {
            $builder->whereIn('category_id', $categoryIds);
        } elseif (mb_strlen($lower) >= 2) {
            $builder->where(function (Builder $q) use ($lower) {
                $q->where('is_featured', true)
                    ->orWhere('name', 'like', '%'.$lower.'%');
            });
        } else {
            $builder->where('is_featured', true);
        }

        return $builder
            ->orderByDesc('is_featured')
            ->orderByDesc('reviews_count')
            ->latest()
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     */
    protected function expandCategoryIds(array $categoryIds): array
    {
        return Category::query()
            ->whereIn('id', $categoryIds)
            ->orWhereIn('parent_id', $categoryIds)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    protected function guessCategoryIdsForAgentTerm(string $lower): array
    {
        $matchedKeys = [];

        foreach (config('market.categories', []) as $key => $cat) {
            foreach ($cat['trending_keywords'] ?? [] as $kw) {
                $kwLower = strtolower($kw);
                if (str_contains($lower, $kwLower) || str_contains($kwLower, $lower)) {
                    $matchedKeys[] = $key;
                    break;
                }
            }

            if (in_array($key, $matchedKeys, true)) {
                continue;
            }

            foreach ($cat['budget_products'] ?? [] as $budgetProduct) {
                $prodLower = strtolower((string) ($budgetProduct['product'] ?? ''));
                if ($prodLower !== '' && (str_contains($lower, $prodLower) || str_contains($prodLower, $lower))) {
                    $matchedKeys[] = $key;
                    break;
                }
            }
        }

        if ($matchedKeys === []) {
            return [];
        }

        $labels = collect($matchedKeys)
            ->map(fn (string $key) => config("market.categories.{$key}.label"))
            ->filter()
            ->values()
            ->all();

        $ids = Category::query()
            ->where(function (Builder $q) use ($matchedKeys, $labels) {
                $q->whereIn('slug', $matchedKeys);
                if ($labels !== []) {
                    $q->orWhereIn('name', $labels);
                }
            })
            ->pluck('id')
            ->all();

        return $this->expandCategoryIds($ids);
    }

    protected function applyVariantPriceFilter(Builder $query, ?float $minPrice, ?float $maxPrice): void
    {
        if ($minPrice === null && $maxPrice === null) {
            return;
        }

        $query->whereHas('variants', function (Builder $variantQuery) use ($minPrice, $maxPrice) {
            $variantQuery->where('status', 'active');

            if ($minPrice !== null) {
                $variantQuery->where('price', '>=', $minPrice);
            }

            if ($maxPrice !== null) {
                $variantQuery->where('price', '<=', $maxPrice);
            }
        });
    }

    protected function applyDiscountFilter(Builder $query, ?int $minDiscount): void
    {
        if (! $minDiscount) {
            return;
        }

        $query->whereHas('variants', function (Builder $variantQuery) use ($minDiscount) {
            $variantQuery->where('status', 'active')
                ->whereNotNull('compare_price')
                ->whereColumn('compare_price', '>', 'price')
                ->whereRaw('((compare_price - price) / compare_price * 100) >= ?', [$minDiscount]);
        });
    }

    protected function applyDatabaseSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'relevance' => $query->latest(),
            'price_asc' => $query->addSelect([
                'variant_price' => ProductVariant::query()
                    ->select('price')
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->limit(1),
            ])->orderBy('variant_price'),
            'price_desc' => $query->addSelect([
                'variant_price' => ProductVariant::query()
                    ->select('price')
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->limit(1),
            ])->orderByDesc('variant_price'),
            'popular' => $query->withSum('orderItems as total_sold_sum', 'quantity')->orderByDesc('total_sold_sum'),
            'rating' => $query->orderByDesc('reviews_avg_rating'),
            default => $query->latest(),
        };
    }
}
