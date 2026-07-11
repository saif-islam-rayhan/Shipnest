<?php

namespace App\Services\Market;

use App\Services\ProductService;

class PlatformProductAgent
{
    private const TYPO_FIXES = [
        'wathc' => 'watch',
        'wacth' => 'watch',
        'wath' => 'watch',
        'smrat' => 'smart',
        'smat' => 'smart',
        'earbud' => 'earbuds',
    ];

    private const PREVIEW_COUNT = 4;

    private const MAX_SEARCH = 48;

    private const MAX_TRENDING = 24;

    public function __construct(
        private ProductService $productService,
        private QueryIntentClassifier $intentClassifier,
    ) {}

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    /**
     * ShipNest catalog trending — no web search, works without login or LLM.
     *
     * @return array{content: string, meta: array<string, mixed>}
     */
    public function trending(): array
    {
        $raw = $this->productService->agentRelatedTrending('', [], self::MAX_TRENDING);
        $allProducts = AgentResponseBuilder::formatPlatformTrendingProducts($raw);
        $total = count($allProducts);
        $thoughtProcess = [
            'Query intent: ShipNest catalog trending (featured + popular products)',
            'Found '.$total.' trending product(s) in ShipNest catalog',
        ];

        if ($total === 0) {
            return AgentResponseBuilder::make(
                '⚠️ ShipNest-এ এখনো কোনো trending product নেই। Admin panel থেকে product add করুন এবং **featured** mark করুন।',
                [
                    'type' => 'platform',
                    'intent' => QueryIntent::PLATFORM_SEARCH,
                    'catalog_mode' => 'trending',
                    'summary' => 'No trending products on ShipNest yet.',
                    'products' => [],
                    'products_all' => [],
                    'cart_url' => route('cart.index'),
                    'follow_ups' => ['watch', 'earbuds', 'kurti'],
                    'thought_process' => $thoughtProcess,
                    'query' => 'trending product',
                ],
            );
        }

        $nameLines = collect($allProducts)
            ->take(10)
            ->map(fn (array $p, int $i) => ($i + 1).'. **'.($p['name'] ?? '').'** — '.($p['price_label'] ?? ''))
            ->implode("\n");

        $content = "🔥 **ShipNest Trending Products** ({$total})\n\n{$nameLines}\n\n"
            .'নিচে product card থেকে **Add to cart** করুন, অথবা `view cart` লিখুন।';

        return AgentResponseBuilder::make($content, [
            'type' => 'platform',
            'intent' => QueryIntent::PLATFORM_SEARCH,
            'catalog_mode' => 'trending',
            'summary' => "ShipNest-এ **{$total}টি** trending product — select করে cart-এ add করুন (login লাগবে না)।",
            'products' => array_slice($allProducts, 0, self::PREVIEW_COUNT),
            'products_all' => $allProducts,
            'products_preview_count' => self::PREVIEW_COUNT,
            'total_count' => $total,
            'cart_url' => route('cart.index'),
            'follow_ups' => ['view cart', 'watch', 'earbuds'],
            'thought_process' => $thoughtProcess,
            'query' => 'trending product',
        ]);
    }

    public function search(string $message, ?CompositeQuery $parsed = null): array
    {
        $term = $this->normalizeTerm($this->intentClassifier->extractProductTerm($message));
        if ($term === '') {
            $term = $this->normalizeTerm(trim($message));
        }

        [$minPrice, $maxPrice] = $this->intentClassifier->parsePriceFilter($message);
        if ($parsed && $parsed->budgetMax !== null) {
            $minPrice = $parsed->budgetMin !== null ? (float) $parsed->budgetMin : $minPrice;
            $maxPrice = (float) $parsed->budgetMax;
        }

        $thoughtProcess = [
            'Step 1: Query analyzed → **product search** on ShipNest catalog',
            'Step 2: Searching for "'.$term.'" in your marketplace',
        ];

        if ($minPrice !== null || $maxPrice !== null) {
            $thoughtProcess[] = 'Price filter: '
                .($minPrice ? '৳'.number_format($minPrice) : 'any')
                .' – '
                .($maxPrice ? '৳'.number_format($maxPrice) : 'any');
        }

        $paginator = $this->productService->agentCatalogSearch(
            query: $term,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            perPage: self::MAX_SEARCH,
        );

        // Fallback: try strongest keyword if full phrase empty (e.g. "smart watch" → "watch")
        if ($paginator->total() === 0 && str_contains($term, ' ')) {
            $keywords = array_filter(explode(' ', $term), fn ($w) => mb_strlen($w) >= 3);
            usort($keywords, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            foreach ($keywords as $kw) {
                $paginator = $this->productService->agentCatalogSearch($kw, $minPrice, $maxPrice, self::MAX_SEARCH);
                if ($paginator->total() > 0) {
                    $thoughtProcess[] = 'Expanded search using keyword: "'.$kw.'"';
                    $term = $kw;
                    break;
                }
            }
        }

        $allProducts = AgentResponseBuilder::formatPlatformProducts(
            collect($paginator->items())->all(),
        );

        $total = $paginator->total();
        $thoughtProcess[] = 'Step 3: Found '.$total.' product(s) in ShipNest catalog';

        $summary = AgentResponseBuilder::buildPlatformSummary($term, min(count($allProducts), self::PREVIEW_COUNT), $total);
        $followUps = AgentResponseBuilder::platformFollowUps($term);

        if (empty($allProducts)) {
            $thoughtProcess[] = 'No matches — ensure products exist in Admin → Products and are active + in stock';

            return AgentResponseBuilder::make($summary, [
                'type' => 'platform',
                'intent' => QueryIntent::PLATFORM_SEARCH,
                'summary' => "I couldn't find **{$term}** on ShipNest. "
                    .'Add products in Admin panel or try: `smart watch`, `earbuds`, `kurti`.',
                'products' => [],
                'products_all' => [],
                'follow_ups' => [
                    'smart watch',
                    'earbuds',
                    "top 5 {$term} trending Bangladesh",
                ],
                'thought_process' => $thoughtProcess,
                'query' => $term,
            ]);
        }

        $excludeIds = collect($allProducts)->pluck('id')->filter()->values()->all();
        $relatedRaw = $this->productService->agentRelatedTrending($term, $excludeIds, self::MAX_TRENDING);
        $trendingAll = AgentResponseBuilder::formatPlatformTrendingProducts($relatedRaw);

        $thoughtProcess[] = 'Step 4: Showing '.min(count($allProducts), self::PREVIEW_COUNT).' search result(s) with See more';
        $thoughtProcess[] = 'Step 5: Found '.count($trendingAll).' related trending product(s) on ShipNest';

        return AgentResponseBuilder::make($summary, [
            'type' => 'platform',
            'intent' => QueryIntent::PLATFORM_SEARCH,
            'summary' => $summary,
            'products' => array_slice($allProducts, 0, self::PREVIEW_COUNT),
            'products_all' => $allProducts,
            'products_preview_count' => self::PREVIEW_COUNT,
            'total_count' => $total,
            'trending_products' => array_slice($trendingAll, 0, self::PREVIEW_COUNT),
            'trending_products_all' => $trendingAll,
            'trending_total_count' => count($trendingAll),
            'trending_preview_count' => self::PREVIEW_COUNT,
            'follow_ups' => $followUps,
            'thought_process' => $thoughtProcess,
            'query' => $term,
        ]);
    }

    private function normalizeTerm(string $term): string
    {
        $lower = strtolower(trim($term));
        foreach (self::TYPO_FIXES as $typo => $fix) {
            $lower = str_replace($typo, $fix, $lower);
        }

        return trim($lower);
    }
}
