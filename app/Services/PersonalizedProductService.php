<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PersonalizedProductService
{
    public function __construct(
        private readonly UserInterestService $interest,
        private readonly ProductService $products,
    ) {}

    /**
     * Related products via Agent catalog logic (agentRelatedTrending).
     *
     * @return array<int, Product>
     */
    public function getRelatedProducts(?int $userId, string $sessionId, int $limit = 24): array
    {
        $query = $this->interest->getInterestQuery($userId, $sessionId);

        return $this->products->agentRelatedTrending($query, [], $limit);
    }

    /**
     * @return array<int, int>
     */
    public function getRelatedProductIds(?int $userId, string $sessionId, int $limit = 24): array
    {
        return collect($this->getRelatedProducts($userId, $sessionId, $limit))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Hero carousel: related products that have a discount, same full-banner style.
     */
    public function getHeroDiscountProducts(?int $userId, string $sessionId, int $limit = 5): Collection
    {
        $related = collect($this->getRelatedProducts($userId, $sessionId, 24));

        $discounted = $related
            ->filter(fn (Product $p) => $p->discount_percentage > 0)
            ->take($limit)
            ->values();

        if ($discounted->count() >= $limit) {
            return $discounted;
        }

        $excludeIds = $discounted->pluck('id')->all();
        $fallback = $this->getGlobalDiscounted($limit - $discounted->count(), $excludeIds);

        return $discounted->merge($fallback)->unique('id')->take($limit)->values();
    }

    /**
     * @param  array<int, int>  $excludeIds
     */
    protected function getGlobalDiscounted(int $limit, array $excludeIds = []): Collection
    {
        return Product::query()
            ->with(['images', 'merchant', 'defaultVariant'])
            ->withAvg('reviews', 'rating')
            ->active()
            ->inStock()
            ->when($excludeIds !== [], fn (Builder $q) => $q->whereNotIn('id', $excludeIds))
            ->whereHas('variants', fn (Builder $q) => $q
                ->where('status', 'active')
                ->whereNotNull('compare_price')
                ->whereColumn('compare_price', '>', 'price')
            )
            ->orderByDesc('is_featured')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
