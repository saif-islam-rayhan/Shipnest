<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\UserInterestEvent;
use App\Models\UserInterestScore;
use Illuminate\Support\Facades\DB;

class UserInterestService
{
    public const WEIGHT_SEARCH = 2;

    public const WEIGHT_VIEW = 3;

    public const WEIGHT_WISHLIST = 5;

    public const WEIGHT_CART = 7;

    public const WEIGHT_PURCHASE = 10;

    public function subjectKey(?int $userId, string $sessionId): string
    {
        return $userId ? 'user:'.$userId : 'session:'.$sessionId;
    }

    public function trackSearch(string $query, ?int $userId, string $sessionId): void
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return;
        }

        $subjectKey = $this->subjectKey($userId, $sessionId);

        UserInterestEvent::query()->create([
            'subject_key' => $subjectKey,
            'user_id' => $userId,
            'event_type' => 'search',
            'search_query' => $query,
            'weight' => self::WEIGHT_SEARCH,
        ]);

        $categoryIds = Category::query()
            ->active()
            ->where('name', 'like', '%'.$query.'%')
            ->pluck('id');

        $productCategoryIds = Product::query()
            ->active()
            ->where('name', 'like', '%'.$query.'%')
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id');

        foreach ($categoryIds->merge($productCategoryIds)->unique() as $categoryId) {
            $this->bumpScore($subjectKey, $userId, (int) $categoryId, 'category', self::WEIGHT_SEARCH);
        }
    }

    public function trackView(Product $product, ?int $userId, string $sessionId): void
    {
        $subjectKey = $this->subjectKey($userId, $sessionId);

        UserInterestEvent::query()->create([
            'subject_key' => $subjectKey,
            'user_id' => $userId,
            'event_type' => 'view',
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'weight' => self::WEIGHT_VIEW,
        ]);

        $this->applyProductInterest($subjectKey, $userId, $product, self::WEIGHT_VIEW);
    }

    public function trackWishlist(Product $product, int $userId): void
    {
        $subjectKey = $this->subjectKey($userId, session()->getId());

        UserInterestEvent::query()->create([
            'subject_key' => $subjectKey,
            'user_id' => $userId,
            'event_type' => 'wishlist',
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'weight' => self::WEIGHT_WISHLIST,
        ]);

        $this->applyProductInterest($subjectKey, $userId, $product, self::WEIGHT_WISHLIST);
    }

    public function trackCart(Product $product, ?int $userId, string $sessionId): void
    {
        $subjectKey = $this->subjectKey($userId, $sessionId);

        UserInterestEvent::query()->create([
            'subject_key' => $subjectKey,
            'user_id' => $userId,
            'event_type' => 'cart',
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'weight' => self::WEIGHT_CART,
        ]);

        $this->applyProductInterest($subjectKey, $userId, $product, self::WEIGHT_CART);
    }

    public function trackPurchase(Product $product, int $userId): void
    {
        $subjectKey = $this->subjectKey($userId, session()->getId());

        UserInterestEvent::query()->create([
            'subject_key' => $subjectKey,
            'user_id' => $userId,
            'event_type' => 'purchase',
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'weight' => self::WEIGHT_PURCHASE,
        ]);

        $this->applyProductInterest($subjectKey, $userId, $product, self::WEIGHT_PURCHASE);
    }

    /**
     * Best query term for agentRelatedTrending based on user behaviour.
     */
    public function getInterestQuery(?int $userId, string $sessionId): string
    {
        $subjectKey = $this->subjectKey($userId, $sessionId);

        $recentSearch = UserInterestEvent::query()
            ->where('subject_key', $subjectKey)
            ->where('event_type', 'search')
            ->whereNotNull('search_query')
            ->latest()
            ->value('search_query');

        if (is_string($recentSearch) && trim($recentSearch) !== '') {
            return trim($recentSearch);
        }

        $topCategoryId = UserInterestScore::query()
            ->where('subject_key', $subjectKey)
            ->where('interest_type', 'category')
            ->orderByDesc('score')
            ->value('interest_id');

        if ($topCategoryId) {
            $name = Category::query()->whereKey($topCategoryId)->value('name');

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return '';
    }

    /**
     * @return array<int, int>
     */
    public function getTopCategoryIds(?int $userId, string $sessionId, int $limit = 3): array
    {
        $subjectKey = $this->subjectKey($userId, $sessionId);

        return UserInterestScore::query()
            ->where('subject_key', $subjectKey)
            ->where('interest_type', 'category')
            ->orderByDesc('score')
            ->limit($limit)
            ->pluck('interest_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function mergeGuestSession(User $user, string $sessionId): void
    {
        $guestKey = $this->subjectKey(null, $sessionId);
        $userKey = $this->subjectKey($user->id, $sessionId);

        if ($guestKey === $userKey) {
            $this->bootstrapUserHistory($user);

            return;
        }

        DB::transaction(function () use ($guestKey, $userKey, $user) {
            $guestScores = UserInterestScore::query()
                ->where('subject_key', $guestKey)
                ->get();

            foreach ($guestScores as $guestScore) {
                $existing = UserInterestScore::query()
                    ->where('subject_key', $userKey)
                    ->where('interest_type', $guestScore->interest_type)
                    ->where('interest_id', $guestScore->interest_id)
                    ->first();

                if ($existing) {
                    $existing->increment('score', $guestScore->score);
                    $guestScore->delete();
                } else {
                    $guestScore->update([
                        'subject_key' => $userKey,
                        'user_id' => $user->id,
                    ]);
                }
            }

            UserInterestEvent::query()
                ->where('subject_key', $guestKey)
                ->update([
                    'subject_key' => $userKey,
                    'user_id' => $user->id,
                ]);
        });

        $this->bootstrapUserHistory($user);
    }

    public function bootstrapUserHistory(User $user): void
    {
        $subjectKey = $this->subjectKey($user->id, session()->getId());

        if (UserInterestScore::query()->where('subject_key', $subjectKey)->exists()) {
            return;
        }

        $purchasedProducts = Product::query()
            ->whereHas('orderItems.order', fn ($q) => $q->where('user_id', $user->id))
            ->with(['category', 'brand'])
            ->limit(50)
            ->get();

        foreach ($purchasedProducts as $product) {
            $this->applyProductInterest($subjectKey, $user->id, $product, self::WEIGHT_PURCHASE);
        }
    }

    protected function applyProductInterest(string $subjectKey, ?int $userId, Product $product, int $weight): void
    {
        if ($product->category_id) {
            $this->bumpScore($subjectKey, $userId, $product->category_id, 'category', $weight);
        }

        if ($product->brand_id) {
            $this->bumpScore($subjectKey, $userId, $product->brand_id, 'brand', $weight);
        }
    }

    protected function bumpScore(string $subjectKey, ?int $userId, int $interestId, string $interestType, int $weight): void
    {
        $score = UserInterestScore::query()->firstOrCreate(
            [
                'subject_key' => $subjectKey,
                'interest_type' => $interestType,
                'interest_id' => $interestId,
            ],
            [
                'user_id' => $userId,
                'score' => 0,
            ],
        );

        $score->increment('score', $weight);

        if ($userId && ! $score->user_id) {
            $score->update(['user_id' => $userId]);
        }
    }
}
