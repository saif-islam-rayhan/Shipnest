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
        ?array $priorityProductIds = null,
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
            priorityProductIds: $priorityProductIds,
        );
    }

    public function getFeatured(int $limit = 12): Collection
    {
        return Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->withApprovedReviewStats()
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
            ->withApprovedReviewStats()
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
            ->withApprovedReviewStats()
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
                'questions' => fn ($q) => $q->visible()->with(['user', 'answeredByUser'])->latest(),
            ])
            ->withApprovedReviewStats()
            ->withCount('questions')
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
        ?array $priorityProductIds = null,
    ): LengthAwarePaginator {
        $builder = Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->withApprovedReviewStats()
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

        if ($priorityProductIds !== null && $priorityProductIds !== []) {
            $ids = implode(',', array_map('intval', $priorityProductIds));
            $builder->orderByRaw("FIELD(id, {$ids}) DESC");
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
            ->withApprovedReviewStats()
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
     * Image-aware catalog search — scores products by type/brand relevance instead of loose OR matching.
     *
     * @param  array<string, mixed>  $analysis  Vision LLM output (product_name, brand, search_keywords, product_type, category)
     * @return array{
     *     products: array<int, Product>,
     *     scores: array<int, int>,
     *     match_quality: 'strong'|'weak'|'none',
     *     type_keywords: array<int, string>
     * }
     */
    public function agentImageCatalogSearch(array $analysis, int $limit = 48): array
    {
        $signals = $this->buildImageSearchSignals($analysis);
        $candidates = $this->fetchImageSearchCandidates($signals, min($limit * 3, 120));

        $scored = [];
        foreach ($candidates as $product) {
            $score = $this->scoreProductForImageSearch($product, $signals);
            if ($score >= 2) {
                $scored[] = ['product' => $product, 'score' => $score];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $b['product']->id <=> $a['product']->id);

        $strong = array_values(array_filter(
            $scored,
            fn ($row) => $row['score'] >= 5 && $this->productNameMatchesTypeKeyword($row['product'], $signals),
        ));
        $weak = array_values(array_filter(
            $scored,
            fn ($row) => $row['score'] >= 2 && $row['score'] < 5
                || ($row['score'] >= 5 && ! $this->productNameMatchesTypeKeyword($row['product'], $signals)),
        ));

        if ($strong !== []) {
            $picked = array_slice($strong, 0, $limit);

            return [
                'products' => array_column($picked, 'product'),
                'scores' => collect($picked)->mapWithKeys(fn ($r) => [$r['product']->id => $r['score']])->all(),
                'match_quality' => 'strong',
                'type_keywords' => $signals['type_keywords'],
            ];
        }

        if ($weak !== []) {
            $picked = array_slice($weak, 0, $limit);

            return [
                'products' => array_column($picked, 'product'),
                'scores' => collect($picked)->mapWithKeys(fn ($r) => [$r['product']->id => $r['score']])->all(),
                'match_quality' => 'weak',
                'type_keywords' => $signals['type_keywords'],
            ];
        }

        return [
            'products' => [],
            'scores' => [],
            'match_quality' => 'none',
            'type_keywords' => $signals['type_keywords'],
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array{product_name: string, brand: string, type_keywords: array<int, string>, color_keywords: array<int, string>}
     */
    protected function buildImageSearchSignals(array $analysis): array
    {
        $brand = strtolower(trim((string) ($analysis['brand'] ?? '')));
        $productName = strtolower(trim((string) ($analysis['product_name'] ?? '')));
        $productType = strtolower(trim((string) ($analysis['product_type'] ?? '')));

        $typeKeywords = [];
        $colorKeywords = [];

        if ($productType !== '' && mb_strlen($productType) >= 3) {
            $typeKeywords[] = $productType;
        }

        foreach ((array) ($analysis['search_keywords'] ?? []) as $kw) {
            $kw = strtolower(trim((string) $kw));
            if ($kw === '' || mb_strlen($kw) < 3) {
                continue;
            }
            if ($brand !== '' && $kw === $brand) {
                continue;
            }
            if ($this->isImageSearchColorWord($kw)) {
                $colorKeywords[] = $kw;

                continue;
            }
            if ($this->isImageSearchGenericWord($kw)) {
                continue;
            }
            $typeKeywords[] = $kw;
        }

        if ($productName !== '') {
            foreach (preg_split('/\s+/', $productName) ?: [] as $word) {
                $word = trim($word);
                if ($word === '' || mb_strlen($word) < 3) {
                    continue;
                }
                if ($brand !== '' && $word === $brand) {
                    continue;
                }
                if ($this->isImageSearchColorWord($word) || $this->isImageSearchGenericWord($word)) {
                    continue;
                }
                $typeKeywords[] = $word;
            }
        }

        $typeKeywords = array_values(array_unique(array_filter(
            $typeKeywords,
            fn ($kw) => $brand === '' || $kw !== $brand,
        )));

        $typeKeywords = $this->expandImageSearchTypeKeywords($typeKeywords);

        return [
            'product_name' => $productName,
            'brand' => $brand,
            'type_keywords' => $typeKeywords,
            'color_keywords' => array_values(array_unique($colorKeywords)),
        ];
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<int, string>
     */
    protected function expandImageSearchTypeKeywords(array $keywords): array
    {
        $expanded = $keywords;

        $synonyms = [
            'sneaker' => ['sneakers', 'shoe', 'shoes', 'footwear', 'running shoe', 'running shoes'],
            'sneakers' => ['sneaker', 'shoe', 'shoes', 'footwear'],
            'shoe' => ['shoes', 'sneaker', 'sneakers', 'footwear'],
            'shoes' => ['shoe', 'sneaker', 'sneakers', 'footwear'],
            'footwear' => ['shoe', 'shoes', 'sneaker', 'sneakers'],
            'watch' => ['watches', 'smartwatch', 'smart watch', 'wristwatch'],
            'watches' => ['watch', 'smartwatch', 'smart watch'],
            'smartwatch' => ['smart watch', 'watch', 'watches'],
            'earbud' => ['earbuds', 'earphone', 'earphones', 'headphone', 'headphones'],
            'earbuds' => ['earbud', 'earphone', 'earphones'],
            'kurti' => ['kurta', 'tunic'],
            'kurta' => ['kurti', 'panjabi', 'punjabi'],
            'phone' => ['mobile', 'smartphone', 'cellphone'],
            'mobile' => ['phone', 'smartphone'],
            'lipstick' => ['lip stick', 'lip color'],
            'bag' => ['handbag', 'backpack', 'purse'],
        ];

        foreach ($keywords as $kw) {
            $key = strtolower(trim($kw));
            foreach ($synonyms[$key] ?? [] as $syn) {
                $expanded[] = $syn;
            }
        }

        return array_values(array_unique(array_filter(
            $expanded,
            fn ($kw) => mb_strlen(trim($kw)) >= 3,
        )));
    }

    /**
     * @param  array{product_name: string, brand: string, type_keywords: array<int, string>, color_keywords: array<int, string>}  $signals
     * @return Collection<int, Product>
     */
    protected function fetchImageSearchCandidates(array $signals, int $limit): Collection
    {
        $builder = Product::query()
            ->with(['images', 'merchant', 'category', 'brand', 'defaultVariant'])
            ->withApprovedReviewStats()
            ->active()
            ->inStock();

        $typeKeywords = $signals['type_keywords'];
        $brand = $signals['brand'];

        if ($typeKeywords !== []) {
            $builder->where(function (Builder $q) use ($typeKeywords) {
                foreach ($typeKeywords as $tk) {
                    $q->orWhere('name', 'like', '%'.$tk.'%')
                        ->orWhere('short_description', 'like', '%'.$tk.'%');
                }
            });
        } elseif ($brand !== '') {
            $builder->whereHas('brand', fn (Builder $b) => $b->whereRaw('LOWER(name) = ?', [$brand]));
        } else {
            return collect();
        }

        return $builder
            ->orderByDesc('is_featured')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{product_name: string, brand: string, type_keywords: array<int, string>, color_keywords: array<int, string>}  $signals
     */
    protected function scoreProductForImageSearch(Product $product, array $signals): int
    {
        $score = 0;
        $name = strtolower($product->name);
        $desc = strtolower((string) ($product->short_description ?? ''));
        $brandName = strtolower((string) ($product->brand?->name ?? ''));

        $typeHitsInName = 0;
        foreach ($signals['type_keywords'] as $tk) {
            if (str_contains($name, $tk)) {
                $score += 5;
                $typeHitsInName++;
            } elseif (str_contains($desc, $tk)) {
                $score += 2;
            }
        }

        $productName = $signals['product_name'];
        if ($productName !== '') {
            if ($name === $productName) {
                $score += 10;
            } elseif (str_contains($name, $productName)) {
                $score += 7;
            } else {
                $nameWords = array_filter(preg_split('/\s+/', $productName) ?: [], fn ($w) => mb_strlen($w) >= 3);
                $matchedWords = 0;
                foreach ($nameWords as $word) {
                    if ($this->isImageSearchColorWord($word) || $word === $signals['brand']) {
                        continue;
                    }
                    if (str_contains($name, $word)) {
                        $matchedWords++;
                        $score += 2;
                    }
                }
                if ($matchedWords >= 2) {
                    $score += 3;
                }
            }
        }

        if ($signals['brand'] !== '' && $brandName === $signals['brand']) {
            $score += $typeHitsInName > 0 ? 3 : 1;
        }

        $categoryName = strtolower((string) ($product->category?->name ?? ''));
        if ($categoryName !== '') {
            foreach ($signals['type_keywords'] as $tk) {
                if (str_contains($categoryName, $tk) || str_contains($tk, $categoryName)) {
                    $score += 2;
                    break;
                }
            }
        }

        foreach ($signals['color_keywords'] as $color) {
            if (str_contains($name, $color) || str_contains($desc, $color)) {
                $score += 1;
            }
        }

        if ($product->is_featured) {
            $score += 1;
        }

        return $score;
    }

    protected function isImageSearchColorWord(string $word): bool
    {
        static $colors = [
            'white', 'black', 'red', 'blue', 'green', 'yellow', 'pink', 'grey', 'gray',
            'brown', 'orange', 'purple', 'gold', 'silver', 'navy', 'beige', 'সাদা', 'কালো',
        ];

        return in_array(strtolower($word), $colors, true);
    }

    protected function isImageSearchGenericWord(string $word): bool
    {
        static $generic = [
            'product', 'item', 'new', 'premium', 'best', 'original', 'brand', 'style',
            'men', 'women', 'man', 'woman', 'unisex', 'classic', 'pro', 'plus',
        ];

        return in_array(strtolower($word), $generic, true);
    }

    /**
     * @param  array{product_name: string, brand: string, type_keywords: array<int, string>, color_keywords: array<int, string>}  $signals
     */
    protected function productNameMatchesTypeKeyword(Product $product, array $signals): bool
    {
        $name = strtolower($product->name);
        $desc = strtolower((string) ($product->short_description ?? ''));

        foreach ($signals['type_keywords'] as $tk) {
            if (str_contains($name, $tk) || str_contains($desc, $tk)) {
                return true;
            }
        }

        return false;
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
            ->withApprovedReviewStats()
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
