<?php

namespace App\Services\Market;

use App\Models\Merchant;
use App\Models\OrderItem;
use App\Services\Merchant\MerchantAnalyticsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiDesignStudioService
{
    public const MODE_DESIGN = 'design';

    public const MODE_SEARCH = 'search';

    public const MODE_BESTSELLERS = 'bestsellers';

    public const MODE_MARKET = 'market';

    public const MODE_TRENDS = 'trends';

    public const MODES = [
        self::MODE_DESIGN,
        self::MODE_SEARCH,
        self::MODE_BESTSELLERS,
        self::MODE_MARKET,
        self::MODE_TRENDS,
    ];

    public function __construct(
        private readonly ProductDesignImageGenerator $designGenerator,
        private readonly PlatformProductAgent $platformAgent,
        private readonly MerchantAnalyticsService $analytics,
        private readonly TrendingProductAgent $trendingAgent,
        private readonly CompositeQueryParser $queryParser,
        private readonly LlmClient $llm,
    ) {}

    /**
     * @return array{
     *     mode: string,
     *     description: string,
     *     image_url: ?string,
     *     image_path: ?string,
     *     products: array<int, array<string, mixed>>
     * }
     */
    public function handle(string $mode, string $prompt, ?Merchant $shop = null): array
    {
        $mode = strtolower(trim($mode));
        $prompt = trim($prompt);

        if (! in_array($mode, self::MODES, true)) {
            throw new RuntimeException('Unknown AI mode.');
        }

        return match ($mode) {
            self::MODE_DESIGN => $this->design($prompt),
            self::MODE_SEARCH => $this->search($prompt),
            self::MODE_BESTSELLERS => $this->bestsellers($shop, $prompt),
            self::MODE_MARKET => $this->marketPotential($prompt, $shop),
            self::MODE_TRENDS => $this->trends($prompt),
        };
    }

    /**
     * @return array{mode: string, description: string, image_url: ?string, image_path: ?string, products: array}
     */
    private function design(string $prompt): array
    {
        if (mb_strlen($prompt) < 3) {
            throw new RuntimeException('Describe what you want to design (at least a few words).');
        }

        $result = $this->designGenerator->generate($prompt);

        return [
            'mode' => self::MODE_DESIGN,
            'description' => $result['description'],
            'image_url' => $result['image_url'],
            'image_path' => $result['image_path'],
            'products' => [],
        ];
    }

    /**
     * @return array{mode: string, description: string, image_url: null, image_path: null, products: array}
     */
    private function search(string $prompt): array
    {
        if (mb_strlen($prompt) < 2) {
            throw new RuntimeException('Type a product name to search the ShipNest catalog.');
        }

        $reply = $this->platformAgent->search($prompt);
        $products = $this->normalizeProducts($reply['meta']['products'] ?? $reply['meta']['products_all'] ?? []);

        return [
            'mode' => self::MODE_SEARCH,
            'description' => $this->plainText($reply['content'] ?? 'Search results'),
            'image_url' => null,
            'image_path' => null,
            'products' => array_slice($products, 0, 8),
        ];
    }

    /**
     * @return array{mode: string, description: string, image_url: null, image_path: null, products: array}
     */
    private function bestsellers(?Merchant $shop, string $prompt): array
    {
        $rows = $this->topSellerRows($shop, 10);
        $symbol = config('shipnest.currency_symbol', '৳');
        $scope = $shop ? 'Your store' : 'Platform-wide';

        if ($rows->isEmpty()) {
            return [
                'mode' => self::MODE_BESTSELLERS,
                'description' => ($shop
                    ? 'Your store has no sales data yet. Once orders come in, bestsellers will show here.'
                    : 'No platform sales data yet. Once orders come in, bestsellers will show here.')
                    .($prompt !== '' ? "\n\nYou asked about: {$prompt}" : ''),
                'image_url' => null,
                'image_path' => null,
                'products' => [],
            ];
        }

        $lines = $rows->values()->map(function ($row, int $i) use ($symbol) {
            $name = $row->product_name ?: 'Product #'.$row->product_id;
            $sold = (int) $row->sold;
            $revenue = number_format((float) $row->revenue);

            return ($i + 1).". {$name} — {$sold} sold, {$symbol}{$revenue} revenue";
        })->implode("\n");

        $intro = "{$scope} bestsellers (by revenue):";
        if ($prompt !== '') {
            $intro = "{$scope} bestsellers (filter hint: {$prompt}):";
        }

        $products = $rows->map(fn ($row) => [
            'name' => $row->product_name ?: 'Product #'.$row->product_id,
            'price_label' => $symbol.number_format((float) $row->revenue).' rev',
            'image' => null,
            'url' => null,
            'meta' => ((int) $row->sold).' sold',
        ])->values()->all();

        return [
            'mode' => self::MODE_BESTSELLERS,
            'description' => $intro."\n\n".$lines,
            'image_url' => null,
            'image_path' => null,
            'products' => $products,
        ];
    }

    /**
     * @return array{mode: string, description: string, image_url: null, image_path: null, products: array}
     */
    private function marketPotential(string $prompt, ?Merchant $shop): array
    {
        if (mb_strlen($prompt) < 3) {
            throw new RuntimeException('Describe a product or niche to evaluate market potential.');
        }

        $top = $this->topSellerRows($shop, 5);
        $contextLines = $top->map(fn ($row) => '- '.($row->product_name ?: 'Product').' (sold '.(int) $row->sold.')')->implode("\n");
        if ($contextLines === '') {
            $contextLines = $shop
                ? '- No sales history yet for this store.'
                : '- No platform sales history yet.';
        }

        $audience = $shop ? 'ShipNest seller' : 'ShipNest admin';
        $fallback = "Market potential for “{$prompt}” (Bangladesh ecommerce):\n\n"
            ."• Demand: Check Daraz/Facebook groups for similar listings and review volume.\n"
            ."• Differentiation: Unique design, bundle, or faster delivery can win.\n"
            ."• Pricing: Start mid-range, then A/B test.\n"
            ."• Risk: Crowded niches need stronger branding or niche targeting.\n\n"
            ."Sales context:\n{$contextLines}";

        try {
            $text = trim($this->llm->chat(
                '',
                "You are a Bangladesh ecommerce market analyst helping a {$audience}. "
                .'Reply in 4-6 short bullet-style sentences (plain text, no markdown headings). '
                .'Cover demand, competition, pricing tip, and risk. Mix Bangla + English if natural.',
                "Evaluate market potential for: {$prompt}\n\nTop products context:\n{$contextLines}",
                false,
                0.4,
            ));

            if ($text === '') {
                $text = $fallback;
            }
        } catch (Throwable) {
            $text = $fallback;
        }

        return [
            'mode' => self::MODE_MARKET,
            'description' => $text,
            'image_url' => null,
            'image_path' => null,
            'products' => [],
        ];
    }

    /**
     * @return array{mode: string, description: string, image_url: null, image_path: null, products: array}
     */
    private function trends(string $prompt): array
    {
        $query = $prompt !== '' ? $prompt : 'trending products Bangladesh';

        try {
            $parsed = $this->queryParser->parse($query);
            if (! $parsed->isTrending) {
                $parsed = new CompositeQuery(
                    raw: $query,
                    question: $query,
                    category: $parsed->category,
                    budgetMin: $parsed->budgetMin,
                    budgetMax: $parsed->budgetMax,
                    period: $parsed->period,
                    topN: max(5, $parsed->topN),
                    isTrending: true,
                    audience: $parsed->audience,
                );
            }

            $reply = $this->trendingAgent->handle($query, $parsed);
            $products = $this->normalizeProducts(
                $reply['meta']['products'] ?? $reply['meta']['trending_products'] ?? []
            );

            if (! empty($products) || trim((string) ($reply['content'] ?? '')) !== '') {
                return [
                    'mode' => self::MODE_TRENDS,
                    'description' => $this->plainText($reply['content'] ?? 'Trending insights'),
                    'image_url' => null,
                    'image_path' => null,
                    'products' => array_slice($products, 0, 8),
                ];
            }
        } catch (Throwable) {
            // Fall through to ShipNest catalog trending
        }

        $reply = $this->platformAgent->trending();
        $products = $this->normalizeProducts(
            $reply['meta']['products'] ?? $reply['meta']['products_all'] ?? []
        );

        return [
            'mode' => self::MODE_TRENDS,
            'description' => $this->plainText($reply['content'] ?? 'ShipNest trending products'),
            'image_url' => null,
            'image_path' => null,
            'products' => array_slice($products, 0, 8),
        ];
    }

    private function topSellerRows(?Merchant $shop, int $limit): Collection
    {
        if ($shop) {
            return $this->analytics->topProducts($shop, $limit);
        }

        return OrderItem::query()
            ->selectRaw('product_id, product_name, SUM(total) as revenue, SUM(quantity) as sold')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<int, mixed>  $products
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProducts(array $products): array
    {
        $out = [];

        foreach ($products as $p) {
            if (! is_array($p)) {
                continue;
            }

            $name = (string) ($p['name'] ?? $p['product_name'] ?? '');
            if ($name === '') {
                continue;
            }

            $out[] = [
                'name' => $name,
                'price_label' => $p['price_label'] ?? $p['estimated_price'] ?? null,
                'image' => $p['image'] ?? null,
                'url' => $p['url'] ?? ($p['source_urls'][0] ?? null),
                'meta' => $p['reason'] ?? $p['merchant'] ?? $p['label'] ?? null,
            ];
        }

        return $out;
    }

    private function plainText(string $markdown): string
    {
        $text = preg_replace('/\*\*(.*?)\*\*/u', '$1', $markdown) ?? $markdown;
        $text = preg_replace('/^#+\s*/mu', '', $text) ?? $text;

        return trim(Str::limit($text, 4000, '…'));
    }
}
