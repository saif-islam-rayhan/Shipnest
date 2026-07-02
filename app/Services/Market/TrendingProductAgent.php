<?php

namespace App\Services\Market;

/**
 * Trending product agent — search first (DuckDuckGo / SearXNG), then GitHub Model analysis.
 * Never answers from the LLM's internal knowledge alone.
 */
class TrendingProductAgent
{
    private const ANALYZE_PROMPT = <<<'PROMPT'
You are a Bangladesh e-commerce trend analyst. Analyze ONLY the WEB SEARCH DATA provided below.

CRITICAL RULES:
1. Analyze DARAZ/e-commerce data AND Google AI Mode data separately, then merge.
2. Use ONLY specific product names that literally appear in the search data (listing titles, shopping results).
3. Do NOT return generic categories like "Best Sellers", "Online Shopping", "most selling products".
4. Do NOT invent, guess, or add products from your own training knowledge.
5. Count how often each product appears across different sources.
6. Rank products by popularity (mention frequency) and confidence (how clearly they are described as trending).
7. trend_score must be 0.0–1.0 based on frequency and confidence.
8. estimated_price only if a price appears in the search data for that product; otherwise null.
9. source_urls must only include URLs from the search data that mention that product.
10. If the data is too sparse to identify real trending products, set insufficient_data: true.
11. Return exactly the requested number of products when enough specific names exist in the data.

Return ONLY valid JSON:
{
  "insufficient_data": false,
  "products": [
    {
      "product_name": "exact name from search data",
      "category": "fashion|electronics|beauty|home|kids|food|other",
      "estimated_price": "৳X,XXX or price range or null",
      "trend_score": 0.85,
      "reason": "why trending based on search snippets only",
      "source_urls": ["url1", "url2"]
    }
  ]
}
PROMPT;

    public function __construct(
        private TrendingGoogleService $google,
        private GoogleSearchService $searchService,
        private AnswerValidatorService $validator,
        private InputParsers $inputParsers,
        private BangladeshSeasonService $season,
        private LlmClient $llm,
        private SearchHelpers $helpers,
    ) {}

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    public function handle(string $message, CompositeQuery $parsed): array
    {
        return AgentResponseBuilder::fromTrending($this->runStructured($message, $parsed));
    }

    /**
     * @return array{
     *     markdown: string,
     *     summary: string,
     *     products: array<int, array<string, mixed>>,
     *     trending_products: array<int, array<string, mixed>>,
     *     follow_ups: array<int, string>,
     *     thought_process: array<int, string>,
     *     sources: array<int, array<string, string>>,
     *     query: string
     * }
     */
    public function runStructured(string $message, CompositeQuery $parsed): array
    {
        $backend = config('market.search_backend', 'duckduckgo');
        $thoughtProcess = ["Query type: trending product → {$backend} search first, then GitHub Model analysis"];
        $topN = max(5, $parsed->topN);
        $period = $parsed->period ?? $this->defaultPeriod();
        $category = $parsed->category;
        $budgetMin = $parsed->budgetMin;
        $budgetMax = $parsed->budgetMax;
        $seasonInfo = $this->season->forPeriod($period);
        $audience = $parsed->audience;
        $minResults = config('market.trending_search_min_results', 5);
        $targetResults = config('market.trending_search_target_results', 15);

        if (! $parsed->period) {
            $thoughtProcess[] = 'No month — defaulting to '.$period->labelBn();
        }

        $catLabel = $category ? config("market.categories.{$category}.label_bn", $category) : 'সব ক্যাটাগরি';
        $audienceLabel = $audience === 'women' ? 'মেয়েদের' : ($audience === 'men' ? 'ছেলেদের' : '');
        $thoughtProcess[] = "Period: {$period->labelBn()}, Season: {$seasonInfo['label_bn']}, Category: {$catLabel}"
            .($audienceLabel ? ", Audience: {$audienceLabel}" : '');

        $header = $this->buildHeader($period, $category, $budgetMin, $budgetMax, $message, $backend);
        $maxRetries = config('market.validation_max_retries');

        try {
            $thoughtProcess[] = ucfirst($backend).': '.$period->label().' '.$seasonInfo['label_en'].' season';
            $searchData = $this->google->runSearch(
                $period, $message, $topN, $category, $budgetMin, $budgetMax, useAiMode: true,
            );
            $thoughtProcess[] = 'Got '.($searchData['result_count'] ?? 0).' search hits'
                .' (Daraz: '.($searchData['daraz_result_count'] ?? 0)
                .', Google AI: '.($searchData['google_ai_result_count'] ?? 0).')';
            $thoughtProcess[] = 'Search source: '.($searchData['search_source'] ?? 'web');
            $searchData = $this->ensureMarketAnalysisSearch(
                $searchData, $period, $topN, $category, $audience, $message, $thoughtProcess,
            );
            $searchData = $this->ensureProductPageResults(
                $searchData, $period, $category, $audience, $thoughtProcess,
            );
            $searchData['audience'] = $audience;
            $searchData['season_keywords'] = $this->season->seasonSearchKeywords($category, $audience, $seasonInfo);
            $searchData = $this->fetchMoreIfNeeded($searchData, $period, $topN, $category, $budgetMin, $budgetMax, $thoughtProcess);
        } catch (\Throwable $e) {
            $thoughtProcess[] = 'Search failed: '.$e->getMessage();

            return $this->errorResponse($header, $e->getMessage(), $category, $thoughtProcess, $message);
        }

        $dedupedResults = $this->helpers->deduplicateSearchResults(
            $searchData['results'] ?? [],
            $targetResults,
        );
        $searchData['results'] = $dedupedResults;
        $searchData['result_count'] = count($dedupedResults);
        $thoughtProcess[] = 'Deduplicated to '.$searchData['result_count'].' unique search results';

        if ($searchData['result_count'] < $minResults) {
            $thoughtProcess[] = "Insufficient search results ({$searchData['result_count']} < {$minResults})";

            return $this->insufficientDataResponse(
                $header, $searchData, $category, $thoughtProcess, $message,
                'সাম্প্রতিক তথ্য যথেষ্ট নেই — web search থেকে পর্যাপ্ত ফলাফল পাওয়া যায়নি।',
            );
        }

        $corpus = $this->helpers->buildSearchCorpus($dedupedResults, $targetResults);
        $thoughtProcess[] = 'Built search corpus: '.$corpus['result_count'].' results, '.count($corpus['mentions']).' product mentions';

        $ecommerceCandidates = $this->helpers->extractEcommerceProductTitles(
            $dedupedResults,
            $budgetMin,
            $budgetMax,
            $topN + 4,
        );
        if ($audience) {
            $ecommerceCandidates = array_values(array_filter(
                $ecommerceCandidates,
                fn ($p) => $this->helpers->matchesAudience((string) ($p['product'] ?? ''), $audience),
            ));
        }
        if (! empty($ecommerceCandidates)) {
            $thoughtProcess[] = 'Daraz/e-commerce titles extracted: '.count($ecommerceCandidates).' specific product(s)';
        }

        $body = '';
        $retryNote = '';
        $trendingProducts = [];
        $summary = '';

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $thoughtProcess[] = 'GitHub Model analysis (attempt '.($attempt + 1).')';

            $analysis = $this->analyzeWithLlm($corpus, $searchData, $topN, $seasonInfo, $category);

            if ($analysis['insufficient_data'] ?? false) {
                $thoughtProcess[] = 'LLM flagged insufficient_data — trying corpus + Daraz title fallback';
                $analysis['products'] = $this->fallbackFromCorpus(
                    $corpus,
                    $dedupedResults,
                    $topN,
                    $category,
                    $ecommerceCandidates,
                );
                $analysis['insufficient_data'] = empty($analysis['products']);
            }

            if ($analysis['insufficient_data'] ?? false) {
                $thoughtProcess[] = 'Not enough signal in search results after fallback';

                return $this->insufficientDataResponse(
                    $header, $searchData, $category, $thoughtProcess, $message,
                    'সাম্প্রতিক তথ্য যথেষ্ট নেই — search result থেকে নির্ভরযোগ্য trending product চিহ্নিত করা যায়নি।',
                );
            }

            $trendingProducts = $this->validateAnalyzedProducts(
                $analysis['products'] ?? [],
                $corpus,
                $dedupedResults,
            );
            $trendingProducts = $this->supplementFromEcommerce(
                $trendingProducts,
                $ecommerceCandidates,
                $topN,
                $audience,
            );
            $trendingProducts = $this->helpers->filterByAudience(
                $this->mapTrendingProducts($trendingProducts),
                $audience,
            );
            $trendingProducts = $this->filterTrendingByBudget($trendingProducts, $budgetMin, $budgetMax, $topN);
            $trendingProducts = array_slice($trendingProducts, 0, $topN);

            $body = $this->formatProductListMarkdown($trendingProducts, $period, $topN, $seasonInfo);
            $summary = $this->buildSummary($period, $category, count($trendingProducts), $seasonInfo);
            $thoughtProcess[] = count($trendingProducts).' trending products ranked by GitHub Model (target: '.$topN.')';

            if (count($trendingProducts) >= $topN || (count($trendingProducts) >= min(3, $topN) && $attempt >= 1)) {
                $thoughtProcess[] = 'Analysis complete — products ranked by popularity and confidence';
                if ($attempt > 0) {
                    $retryNote = '🔄 **'.($attempt + 1)." বার search + analysis করে verify।_\n\n";
                }
                break;
            }

            if ($attempt >= $maxRetries - 1) {
                if (count($trendingProducts) === 0) {
                    return $this->insufficientDataResponse(
                        $header, $searchData, $category, $thoughtProcess, $message,
                        'সাম্প্রতিক তথ্য যথেষ্ট নেই — search result থেকে product extract করা যায়নি।',
                    );
                }
                break;
            }

            $newQueries = $this->season->buildMonthQueries($period, $topN, $category, $budgetMin, $budgetMax);
            $searchData = $this->google->runExtraSearches($searchData, array_slice($newQueries, 0, 2));
            $dedupedResults = $this->helpers->deduplicateSearchResults(
                $searchData['results'] ?? [],
                $targetResults,
            );
            $searchData['results'] = $dedupedResults;
            $corpus = $this->helpers->buildSearchCorpus($dedupedResults, $targetResults);
            $thoughtProcess[] = 'Retry search: '.implode('; ', array_slice($newQueries, 0, 2));
        }

        $uiProducts = AgentResponseBuilder::formatTrendingNamesForUi($trendingProducts);

        $sitesLine = ! empty($searchData['selected_site_labels'])
            ? '🏪 **Sites:** '.implode(', ', $searchData['selected_site_labels'])."\n\n"
            : '';

        return [
            'markdown' => $header.$sitesLine.$retryNote.$body.$this->buildFooter($searchData),
            'summary' => $summary,
            'products' => $uiProducts,
            'trending_products' => $trendingProducts,
            'follow_ups' => $this->buildFollowUps($period, $uiProducts),
            'thought_process' => $thoughtProcess,
            'sources' => $this->extractSources($searchData),
            'query' => $message,
        ];
    }

    /**
     * @return array{insufficient_data: bool, products: array<int, array<string, mixed>>}
     */
    private function analyzeWithLlm(
        array $corpus,
        array $searchData,
        int $topN,
        array $seasonInfo,
        ?string $category,
    ): array {
        if (! config('market.use_live_llm')) {
            return ['insufficient_data' => true, 'products' => []];
        }

        $periodBn = $searchData['period']['label_bn'] ?? '';
        $catLabel = $searchData['category_label'] ?? ($category ? config("market.categories.{$category}.label_bn", '') : '');
        $budgetNote = '';
        if ($searchData['budget_max'] ?? null) {
            $bmin = $searchData['budget_min'] ?? 0;
            $bmax = $searchData['budget_max'];
            $budgetNote = "Budget: ৳".number_format($bmin).' – ৳'.number_format($bmax)." BDT.\n";
        }

        $darazResults = collect($corpus['results'] ?? [])
            ->filter(fn ($r) => ($r['search_channel'] ?? '') === 'daraz_web'
                || str_contains(strtolower($r['url'] ?? ''), 'daraz.com'))
            ->take(15)
            ->values()
            ->all();
        $googleResults = collect($corpus['results'] ?? [])
            ->filter(fn ($r) => ($r['search_channel'] ?? '') === 'google_ai_mode'
                || str_contains($r['source'] ?? '', 'google_ai'))
            ->take(15)
            ->values()
            ->all();

        $corpusJson = json_encode([
            'user_question' => $searchData['question'] ?? '',
            'daraz_and_ecommerce_data' => $darazResults,
            'google_ai_mode_data' => $googleResults,
            'google_ai_summary' => $searchData['ai_answer'] ?? '',
            'all_search_results' => array_slice($corpus['results'] ?? [], 0, 20),
            'extracted_mentions' => array_slice($corpus['mentions'] ?? [], 0, 30),
        ], JSON_UNESCAPED_UNICODE);

        $userPrompt = "USER QUESTION: ".($searchData['question'] ?? '')."\n"
            ."Month: {$periodBn} ({$seasonInfo['label_bn']} season)\n"
            ."Category: {$catLabel}\n"
            .($searchData['audience'] ?? null ? 'Audience: '.($searchData['audience'] === 'women' ? 'women/female/ladies' : 'men/male')."\n" : '')
            ."{$budgetNote}"
            ."Rank exactly {$topN} specific trending products (real product names only).\n\n"
            ."MERGED SEARCH DATA (Daraz + Google AI Mode):\n{$corpusJson}";

        try {
            $raw = $this->llm->chat(
                config('market.model_google_search'),
                self::ANALYZE_PROMPT,
                $userPrompt,
                jsonMode: true,
                temperature: 0.0,
            );
            $parsed = json_decode($raw, true) ?? [];

            return [
                'insufficient_data' => (bool) ($parsed['insufficient_data'] ?? false),
                'products' => $parsed['products'] ?? [],
            ];
        } catch (\Throwable) {
            return ['insufficient_data' => true, 'products' => []];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private function validateAnalyzedProducts(array $products, array $corpus, array $results): array
    {
        $corpusText = strtolower(collect($corpus['results'] ?? [])
            ->map(fn ($r) => ($r['title'] ?? '').' '.($r['snippet'] ?? ''))
            ->implode(' '));
        $mentionNames = collect($corpus['mentions'] ?? [])->pluck('product_name')->map('strtolower')->all();
        $valid = [];

        foreach ($products as $item) {
            $name = trim($item['product_name'] ?? $item['product'] ?? '');
            if ($name === ''
                || $this->helpers->isGenericProductName($name)
                || ! $this->helpers->isSpecificProductName($name)) {
                continue;
            }

            $needle = strtolower($name);
            $inCorpus = str_contains($corpusText, $needle);
            if (! $inCorpus) {
                $words = array_filter(
                    preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/i', ' ', $needle)) ?: [],
                    fn ($w) => strlen($w) >= 3,
                );
                $inCorpus = count($words) > 0 && collect($words)->every(fn ($w) => str_contains($corpusText, $w));
            }

            $inMentions = collect($mentionNames)->contains(fn ($m) => str_contains($m, $needle) || str_contains($needle, $m));

            if (! $inCorpus && ! $inMentions) {
                continue;
            }

            $sourceUrls = array_values(array_filter($item['source_urls'] ?? [], fn ($u) => is_string($u) && $u !== ''));
            if (empty($sourceUrls)) {
                $sourceUrls = $this->findSourceUrlsForProduct($name, $results);
            }

            $valid[] = [
                'product_name' => $name,
                'category' => $item['category'] ?? 'other',
                'estimated_price' => $item['estimated_price'] ?? null,
                'trend_score' => min(1.0, max(0.0, (float) ($item['trend_score'] ?? 0.5))),
                'reason' => $item['reason'] ?? '',
                'source_urls' => $sourceUrls,
            ];
        }

        usort($valid, fn ($a, $b) => ($b['trend_score'] ?? 0) <=> ($a['trend_score'] ?? 0));

        return $valid;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private function mapTrendingProducts(array $products): array
    {
        return collect($products)->map(fn ($p) => [
            'product_name' => $p['product_name'] ?? '',
            'product' => $p['product_name'] ?? '',
            'category' => $p['category'] ?? 'other',
            'estimated_price' => $p['estimated_price'] ?? null,
            'trend_score' => $p['trend_score'] ?? null,
            'reason' => $p['reason'] ?? '',
            'source_urls' => $p['source_urls'] ?? [],
            'price_label' => is_string($p['estimated_price'] ?? null) ? $p['estimated_price'] : null,
            'url' => ($p['source_urls'] ?? [])[0] ?? null,
        ])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private function filterTrendingByBudget(array $products, ?int $bmin, ?int $bmax, int $topN): array
    {
        if (! $bmax && ! $bmin) {
            return $products;
        }

        $bmin = $bmin ?? 0;
        $bmax = $bmax ?? 999999;
        $inBudget = [];
        $unknown = [];

        foreach ($products as $p) {
            $priceText = (string) ($p['estimated_price'] ?? '');
            $prices = $this->helpers->extractPrices($priceText);
            $price = $prices[0] ?? null;

            if ($price !== null) {
                if ($price >= $bmin && $price <= $bmax) {
                    $inBudget[] = $p;
                }
            } else {
                $unknown[] = $p;
            }
        }

        $merged = $inBudget;
        foreach ($unknown as $p) {
            if (count($merged) >= $topN) {
                break;
            }
            $merged[] = $p;
        }

        return $merged;
    }

    /**
     * @return array<int, string>
     */
    private function findSourceUrlsForProduct(string $name, array $results): array
    {
        $needle = strtolower($name);
        $urls = [];

        foreach ($results as $r) {
            $text = strtolower(($r['title'] ?? '').' '.($r['snippet'] ?? ''));
            $url = $r['url'] ?? '';
            if ($url && (str_contains($text, $needle) || collect(explode(' ', $needle))
                ->filter(fn ($w) => strlen($w) >= 3)
                ->every(fn ($w) => str_contains($text, $w)))) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Heuristic fallback — rank corpus mentions by frequency (no LLM knowledge).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fallbackFromCorpus(
        array $corpus,
        array $results,
        int $topN,
        ?string $category,
        array $ecommerceCandidates = [],
    ): array {
        if (! empty($ecommerceCandidates)) {
            return collect($ecommerceCandidates)->take($topN)->map(function ($p) use ($category) {
                $price = $p['price_bdt'] ?? $p['price'] ?? null;

                return [
                    'product_name' => $p['product'] ?? $p['name'] ?? '',
                    'category' => $category ?? 'other',
                    'estimated_price' => $price ? '৳'.number_format((int) $price) : ($p['price_label'] ?? null),
                    'trend_score' => 0.85,
                    'reason' => 'Found on '.($p['site'] ?? 'Daraz').' listing',
                    'source_urls' => ! empty($p['url']) ? [$p['url']] : [],
                ];
            })->values()->all();
        }

        $mentions = $corpus['mentions'] ?? [];
        if (empty($mentions)) {
            $listings = $this->helpers->extractListingsFromResults($results, null, null, $topN);
            $mentions = collect($listings)->map(fn ($p) => [
                'product_name' => $p['product'] ?? '',
                'prices' => isset($p['price_bdt']) ? [(int) $p['price_bdt']] : [],
                'source_urls' => ! empty($p['url']) ? [$p['url']] : [],
                'mention_count' => 1,
            ])->all();
        }

        $counts = array_column($mentions, 'mention_count');
        $maxMentions = $counts !== [] ? max(1, ...$counts) : 1;
        $catDefault = $category ?? 'other';

        return collect($mentions)
            ->filter(fn ($m) => ! empty($m['product_name']) && ! $this->helpers->isGenericProductName($m['product_name']))
            ->take($topN)
            ->map(function ($m) use ($maxMentions, $catDefault) {
                $price = ($m['prices'] ?? [])[0] ?? null;

                return [
                    'product_name' => $m['product_name'],
                    'category' => $catDefault,
                    'estimated_price' => $price ? '৳'.number_format($price) : null,
                    'trend_score' => round(min(1.0, ($m['mention_count'] ?? 1) / $maxMentions), 2),
                    'reason' => 'Mentioned '.$m['mention_count'].' time(s) across search results',
                    'source_urls' => array_values(array_filter($m['source_urls'] ?? [])),
                ];
            })->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, array<string, mixed>>  $ecommerceCandidates
     * @return array<int, array<string, mixed>>
     */
    private function supplementFromEcommerce(array $products, array $ecommerceCandidates, int $topN, ?string $audience = null): array
    {
        if (count($products) >= $topN || empty($ecommerceCandidates)) {
            return $products;
        }

        $seen = collect($products)->pluck('product_name')->map('strtolower')->flip()->all();

        foreach ($ecommerceCandidates as $candidate) {
            $name = trim($candidate['product'] ?? $candidate['name'] ?? '');
            if ($name === '' || isset($seen[strtolower($name)])) {
                continue;
            }
            if ($this->helpers->isGenericProductName($name) || ! $this->helpers->isSpecificProductName($name)) {
                continue;
            }
            if ($audience && ! $this->helpers->matchesAudience($name, $audience)) {
                continue;
            }

            $price = $candidate['price_bdt'] ?? $candidate['price'] ?? null;
            $products[] = [
                'product_name' => $name,
                'category' => 'other',
                'estimated_price' => $price ? '৳'.number_format((int) $price) : ($candidate['price_label'] ?? null),
                'trend_score' => 0.75,
                'reason' => 'Daraz/e-commerce listing match',
                'source_urls' => ! empty($candidate['url']) ? [$candidate['url']] : [],
            ];
            $seen[strtolower($name)] = true;

            if (count($products) >= $topN) {
                break;
            }
        }

        usort($products, fn ($a, $b) => ($b['trend_score'] ?? 0) <=> ($a['trend_score'] ?? 0));

        return $products;
    }

    /**
     * @param  array<int, string>  $thoughtProcess
     */
    private function ensureMarketAnalysisSearch(
        array $searchData,
        GooglePeriod $period,
        int $topN,
        ?string $category,
        ?string $audience,
        string $message,
        array &$thoughtProcess,
    ): array {
        $queries = $this->season->buildMarketAnalysisQueries($period, $topN, $category, $audience, $message);
        $thoughtProcess[] = 'Market analysis queries for '.$period->labelBn();

        return $this->google->runExtraSearches($searchData, array_slice($queries, 0, 4));
    }

    /**
     * @param  array<int, string>  $thoughtProcess
     */
    private function ensureProductPageResults(
        array $searchData,
        GooglePeriod $period,
        ?string $category,
        ?string $audience,
        array &$thoughtProcess,
    ): array {
        $queries = $this->season->buildMultiSiteProductQueries($period, $category, $audience);
        $thoughtProcess[] = 'Season product search: '.implode(', ', array_slice($this->season->seasonSearchKeywords($category, $audience, $this->season->forPeriod($period)), 0, 4));

        return $this->google->runExtraSearches($searchData, array_slice($queries, 0, 6));
    }

    /**
     * @param  array<int, string>  $thoughtProcess
     */
    private function fetchMoreIfNeeded(
        array $searchData,
        GooglePeriod $period,
        int $topN,
        ?string $category,
        ?int $bmin,
        ?int $bmax,
        array &$thoughtProcess,
    ): array {
        $minResults = config('market.trending_search_min_results', 5);
        if (($searchData['result_count'] ?? 0) >= $minResults) {
            return $searchData;
        }

        $extra = $this->season->buildMultiSiteProductQueries($period, $category, $searchData['audience'] ?? null);
        $thoughtProcess[] = "Only {$searchData['result_count']}/{$minResults} results — fetching more";

        return $this->google->runExtraSearches($searchData, array_slice($extra, 0, 4));
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function formatProductListMarkdown(array $products, GooglePeriod $period, int $topN, ?array $seasonInfo = null): string
    {
        if (empty($products)) {
            return '_Web search-এ স্পষ্ট product name পাওয়া যায়নি।_';
        }

        $seasonBn = $seasonInfo['label_bn'] ?? '';
        $lines = ["### 🏆 {$period->labelBn()} trending — {$seasonBn} season\n"];
        if ($seasonBn) {
            $lines[] = "_Search → analyze workflow: products ranked by **trend_score** from real web data._\n";
        }
        foreach (array_slice($products, 0, $topN) as $i => $p) {
            $score = isset($p['trend_score']) ? ' ('.round((float) $p['trend_score'] * 100).'%)' : '';
            $line = ($i + 1).'. **'.($p['product_name'] ?? $p['product'] ?? '').'**'.$score;
            $price = $p['estimated_price'] ?? $p['price_label'] ?? null;
            if ($price) {
                $line .= " — {$price}";
            }
            $lines[] = $line;
            if (! empty($p['reason'])) {
                $lines[] = '   _'.$p['reason'].'_';
            }
        }

        return implode("\n", $lines);
    }

    private function buildSummary(GooglePeriod $period, ?string $category, int $found, ?array $seasonInfo = null): string
    {
        if ($found === 0) {
            return "**{$period->labelBn()}** — search থেকে product extract হয়নি।";
        }

        $catBn = $category ? config("market.categories.{$category}.label_bn", '').' — ' : '';
        $seasonBn = $seasonInfo['label_bn'] ?? '';

        return "Web search + GitHub Model analysis থেকে **{$found}টি** trending product ({$catBn}**{$period->labelBn()}**, {$seasonBn} season)।";
    }

    private function defaultPeriod(): GooglePeriod
    {
        $now = now();

        return new GooglePeriod($now->month, $now->month, $now->year, $now->year);
    }

    private function buildHeader(
        GooglePeriod $period,
        ?string $category,
        ?int $bmin,
        ?int $bmax,
        string $question,
        string $backend,
    ): string {
        $header = '🔍 **'.ucfirst($backend).'** | '.$period->labelBn().' ('.$period->label().")\n";
        if ($category) {
            $header .= '📂 **Category:** '.config("market.categories.{$category}.label_bn", '')."\n";
        }
        if ($bmax || $bmin) {
            $header .= '💰 **Budget:** '.$this->inputParsers->formatBudgetLabel($bmin, $bmax)."\n";
        }

        return $header."_প্রশ্ন: `{$question}`_\n\n";
    }

    private function buildFooter(array $searchData): string
    {
        $results = collect($searchData['results'] ?? [])->filter(fn ($r) => ! empty($r['url']));
        if ($results->isEmpty()) {
            return '';
        }

        $lines = $results->take(8)->map(function ($r) {
            $site = $r['site'] ?? app(SiteSelectorService::class)->detectSiteFromUrl($r['url'] ?? '');
            $title = mb_substr($r['title'] ?? 'link', 0, 55);

            return "- **[{$site}]** [{$title}]({$r['url']})";
        })->all();

        $backend = config('market.search_backend', 'duckduckgo');

        return "\n\n---\n📎 **Sources ({$searchData['result_count']}) — ".ucfirst($backend).":**\n".implode("\n", $lines);
    }

    /**
     * @return array<int, array{site: string, title: string, url: string}>
     */
    private function extractSources(array $searchData): array
    {
        return collect($searchData['results'] ?? [])
            ->filter(fn ($r) => ! empty($r['url']))
            ->take(8)
            ->map(fn ($r) => [
                'site' => $r['site'] ?? app(SiteSelectorService::class)->detectSiteFromUrl($r['url'] ?? ''),
                'title' => mb_substr($r['title'] ?? 'Listing', 0, 70),
                'url' => $r['url'],
            ])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, string>
     */
    private function buildFollowUps(GooglePeriod $period, array $products): array
    {
        $label = $period->label();
        $suggestions = [
            "top 5 trending products Bangladesh {$label}",
            "best selling products daraz {$label}",
        ];

        foreach ($products as $p) {
            $name = $p['name'] ?? $p['product_name'] ?? '';
            if ($name) {
                $suggestions[] = "more {$name} options Bangladesh {$label}";
            }
        }

        return array_values(array_unique(array_slice($suggestions, 0, 3)));
    }

    /**
     * @param  array<int, string>  $thoughtProcess
     * @return array<string, mixed>
     */
    private function insufficientDataResponse(
        string $header,
        array $searchData,
        ?string $category,
        array $thoughtProcess,
        string $message,
        string $reasonBn,
    ): array {
        return [
            'markdown' => $header."⚠️ **{$reasonBn}**\n\n"
                ."_উত্তর model knowledge থেকে দেওয়া হয়নি — শুধুমাত্র web search result বিশ্লেষণ করা হয়েছে।_"
                .$this->buildFooter($searchData),
            'summary' => $reasonBn,
            'products' => [],
            'trending_products' => [],
            'follow_ups' => AgentResponseBuilder::defaultFollowUps($category),
            'thought_process' => $thoughtProcess,
            'sources' => $this->extractSources($searchData),
            'query' => $message,
        ];
    }

    /**
     * @param  array<int, string>  $thoughtProcess
     * @return array<string, mixed>
     */
    private function errorResponse(string $header, string $error, ?string $category, array $thoughtProcess, string $message): array
    {
        return [
            'markdown' => $header.'❌ **Web search failed:** `'.$error.'`',
            'summary' => 'Search ব্যর্থ।',
            'products' => [],
            'trending_products' => [],
            'follow_ups' => AgentResponseBuilder::defaultFollowUps($category),
            'thought_process' => $thoughtProcess,
            'sources' => [],
            'query' => $message,
        ];
    }
}
