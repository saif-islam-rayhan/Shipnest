<?php

namespace App\Services\Market;

class TrendingGoogleService
{
    public function __construct(
        private GoogleSearchService $search,
        private GoogleAiModeSearchService $aiMode,
        private SearchHelpers $helpers,
        private SiteSelectorService $siteSelector,
        private BangladeshSeasonService $season,
    ) {}

    public function buildNaturalLanguageQuery(
        GooglePeriod $period,
        int $topN,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
        ?string $question = null,
    ): string {
        if ($this->isNaturalTrendingQuestion($question)) {
            return $this->enhanceUserQuestionForAiMode(
                $question,
                $period,
                $topN,
                $category,
                $budgetMin,
                $budgetMax,
            );
        }

        if ($question && trim($question) !== '') {
            return $this->enhanceUserQuestionForAiMode(
                $question,
                $period,
                $topN,
                $category,
                $budgetMin,
                $budgetMax,
            );
        }

        $catLabel = $category ? config("market.categories.{$category}.label", $category) : 'products';
        $periodLabel = $period->label();
        $budget = $this->budgetClause($budgetMin, $budgetMax);

        $sites = $this->siteSelector->selectForCategory($category, 3);
        $siteNames = collect($sites)
            ->map(fn ($k) => $this->siteSelector->siteInfo($k)['label'] ?? $k)
            ->implode(', ');

        $parts = [
            "What are the top {$topN} trending {$catLabel} products in Bangladesh",
            "for {$periodLabel}",
        ];

        if ($budget) {
            $parts[] = "in the {$budget} taka price range";
        }

        if ($siteNames) {
            $parts[] = "on {$siteNames}";
        }

        $parts[] = 'e-commerce sites?';
        $parts[] = 'List specific product types with estimated BDT prices.';

        if ($question && trim($question) !== '') {
            $parts[] = "Context: {$question}";
        }

        return implode(' ', array_filter($parts));
    }

    public function buildQueries(
        GooglePeriod $period,
        int $topN,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
        ?string $question = null,
        ?array $selectedSites = null,
    ): array {
        $nlQuery = $this->buildNaturalLanguageQuery($period, $topN, $category, $budgetMin, $budgetMax, $question);

        if ($this->aiMode->isAvailable()) {
            return [$nlQuery];
        }

        $queries = $this->season->buildMonthQueries($period, $topN, $category, $budgetMin, $budgetMax);
        $queries[] = $nlQuery;

        $label = $period->label();
        $cat = $category ? config("market.categories.{$category}.label", $category) : '';
        $budget = $this->budgetClause($budgetMin, $budgetMax);
        $sites = $selectedSites ?? $this->siteSelector->selectForCategory(
            $category,
            config('market.site_selector_count', 5),
        );

        foreach ($sites as $siteKey) {
            $info = $this->siteSelector->siteInfo($siteKey);
            if (! $info) {
                continue;
            }
            $siteLabel = $info['label'];
            $domain = $info['domain'];
            $queries[] = "site:{$domain} {$cat} {$budget} taka trending Bangladesh {$label}";
            $queries[] = "{$siteLabel} {$cat} best selling {$budget} tk Bangladesh {$label}";
        }

        if ($cat) {
            $siteNames = collect($sites)
                ->map(fn ($k) => $this->siteSelector->siteInfo($k)['label'] ?? $k)
                ->implode(' ');
            $queries[] = "top {$topN} {$cat} products {$siteNames} Bangladesh {$budget} {$label}";
        }

        if ($question && $cat) {
            $queries[] = trim("{$cat} trending products Bangladesh {$budget} {$label}");
        }

        if (empty($queries) || ! $cat) {
            $season = $this->season->forPeriod($period);
            $queries[] = "top {$topN} best selling products Bangladesh {$label} {$season['label_en']} daraz";
            $queries[] = "trending products Bangladesh {$label} {$season['label_en']} ecommerce";
        }

        return array_values(array_unique(array_filter($queries)));
    }

    public function runSearch(
        GooglePeriod $period,
        string $question,
        int $topN,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
        bool $useAiMode = true,
    ): array {
        if ($useAiMode) {
            return $this->runHybridSearch(
                $period,
                $question,
                $topN,
                $category,
                $budgetMin,
                $budgetMax,
            );
        }

        $selectedSites = $this->siteSelector->selectForCategory(
            $category,
            config('market.site_selector_count', 5),
        );
        $siteLabels = collect($selectedSites)
            ->map(fn ($k) => $this->siteSelector->siteInfo($k)['label'] ?? $k)
            ->values()
            ->all();
        $queries = $this->buildQueries($period, $topN, $category, $budgetMin, $budgetMax, $question, $selectedSites);

        return $this->runWebSearch(
            $queries,
            $period,
            $question,
            $category,
            $budgetMin,
            $budgetMax,
            $selectedSites,
            $siteLabels,
        );
    }

    /**
     * Daraz/e-commerce web search + Google AI Mode, merged for LLM analysis.
     */
    public function runHybridSearch(
        GooglePeriod $period,
        string $question,
        int $topN,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
    ): array {
        $selectedSites = $this->siteSelector->selectForCategory(
            $category,
            config('market.site_selector_count', 5),
        );
        $siteLabels = collect($selectedSites)
            ->map(fn ($k) => $this->siteSelector->siteInfo($k)['label'] ?? $k)
            ->values()
            ->all();

        $primaryQuery = $this->buildNaturalLanguageQuery(
            $period, $topN, $category, $budgetMin, $budgetMax, $question,
        );
        $darazQueries = $this->buildDarazQueries(
            $period, $question, $topN, $category, $budgetMin, $budgetMax,
            $this->resolveAudienceFromQuestion($question),
        );
        $perQuery = config('market.trending_search_target_results', 15);
        $maxResults = $perQuery + 10;

        $allResults = [];
        $seenUrls = [];
        $queriesRun = [];
        $errors = [];
        $aiAnswer = '';
        $shoppingResults = [];
        $aiModeToken = null;

        foreach ($darazQueries as $q) {
            $queriesRun[] = $q;
            try {
                $hits = $this->search->search($q, $perQuery);
                $allResults = $this->mergeSearchHits($allResults, $seenUrls, $hits, $q, 'daraz_web');
            } catch (\Throwable $e) {
                $errors[] = 'Daraz: '.$e->getMessage();
            }
            if (count($allResults) >= $maxResults) {
                break;
            }
        }

        if ($this->aiMode->isAvailable()) {
            $queriesRun[] = '[Google AI Mode] '.$primaryQuery;
            try {
                $aiData = $this->aiMode->search($primaryQuery);
                $aiAnswer = trim($aiData['ai_answer'] ?? '');
                $shoppingResults = $aiData['shopping_results'] ?? [];
                $aiModeToken = $aiData['subsequent_request_token'] ?? null;
                $allResults = $this->mergeSearchHits(
                    $allResults,
                    $seenUrls,
                    $aiData['results'] ?? [],
                    $primaryQuery,
                    'google_ai_mode',
                );
            } catch (\Throwable $e) {
                $errors[] = 'Google AI Mode: '.$e->getMessage();
            }
        }

        $targetMin = config('market.trending_search_min_results', 5);
        if (count($allResults) < $targetMin) {
            $fallbackQueries = $this->buildQueries(
                $period, $topN, $category, $budgetMin, $budgetMax, $question, $selectedSites,
            );
            foreach (array_slice($fallbackQueries, 0, 4) as $q) {
                if (in_array($q, $queriesRun, true)) {
                    continue;
                }
                $queriesRun[] = $q;
                try {
                    $hits = $this->search->search($q, $perQuery);
                    $allResults = $this->mergeSearchHits($allResults, $seenUrls, $hits, $q, 'web');
                } catch (\Throwable $e) {
                    $errors[] = 'Web: '.$e->getMessage();
                    continue;
                }
                if (count($allResults) >= $maxResults) {
                    break;
                }
            }
        }

        if (empty($allResults)) {
            $detail = $errors ? implode('; ', array_slice($errors, -3)) : 'no results';
            throw new \RuntimeException("Hybrid search failed for {$period->label()}. {$detail}");
        }

        $catInfo = $category ? config("market.categories.{$category}", []) : [];
        $searchSource = $this->aiMode->isAvailable() && $aiAnswer !== ''
            ? 'hybrid_daraz_google_ai'
            : 'hybrid_daraz_web';

        return [
            'question' => "{$question} | ".($catInfo['label_bn'] ?? 'সব').' | '.$period->labelBn().' ('.$period->label().')',
            'period' => ['label' => $period->label(), 'label_bn' => $period->labelBn()],
            'category' => $category,
            'category_label' => $catInfo['label_bn'] ?? '',
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'selected_sites' => $selectedSites,
            'selected_site_labels' => $siteLabels,
            'queries' => $queriesRun,
            'result_count' => count($allResults),
            'results' => array_slice($allResults, 0, $maxResults),
            'search_source' => $searchSource,
            'ai_answer' => $aiAnswer,
            'ai_mode_token' => $aiModeToken,
            'shopping_results' => $shoppingResults,
            'daraz_result_count' => collect($allResults)->filter(
                fn ($r) => ($r['search_channel'] ?? '') === 'daraz_web'
                    || str_contains(strtolower($r['url'] ?? ''), 'daraz.com'),
            )->count(),
            'google_ai_result_count' => collect($allResults)->filter(
                fn ($r) => ($r['search_channel'] ?? '') === 'google_ai_mode'
                    || str_contains($r['source'] ?? '', 'google_ai'),
            )->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildDarazQueries(
        GooglePeriod $period,
        string $question,
        int $topN,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
        ?string $audience = null,
    ): array {
        $classifier = app(QueryIntentClassifier::class);
        $term = $this->extractFocusTerm($question, $classifier);
        if ($term !== '' && preg_match('/\b(top|trending|bangladesh|product|products|july|june|january|february|march|april|may|august|september|october|november|december)\b/i', $term)) {
            $term = preg_replace('/\b(top\s*\d+|trending|bangladesh|product|products|\d{4}|july|june|january|february|march|april|may|august|september|october|november|december)\b/i', '', $term) ?? $term;
            $term = trim($term);
        }

        $label = $period->label();
        $catLabel = $category ? config("market.categories.{$category}.label", $category) : '';
        $budget = $this->budgetClause($budgetMin, $budgetMax);
        $audienceLabel = match ($audience) {
            'women' => 'women ladies female',
            'men' => 'men male',
            default => '',
        };
        $focus = trim(implode(' ', array_filter([$audienceLabel, $term, $catLabel])));
        if ($focus === '') {
            $focus = 'products';
        }

        $queries = [
            "site:daraz.com.bd/products/ {$focus} Bangladesh {$label}",
            "site:daraz.com.bd {$focus} best selling trending Bangladesh {$budget}",
            "site:daraz.com.bd {$focus} {$label} price Bangladesh",
        ];

        if ($audience === 'women' && $category === 'fashion') {
            $queries[] = "site:daraz.com.bd/products/ women kurti trending Bangladesh {$label}";
            $queries[] = "site:daraz.com.bd ladies tops saree trending Bangladesh {$label}";
        }

        if ($term !== '') {
            $queries[] = "site:pickaboo.com {$focus} Bangladesh {$label}";
            $queries[] = "top {$topN} {$focus} trending Bangladesh daraz {$label}";
        }

        return array_values(array_unique(array_filter($queries)));
    }

    private function extractFocusTerm(string $question, QueryIntentClassifier $classifier): string
    {
        $text = strtolower($question);
        if (preg_match('/\b(female|women\'?s?|ladies)\s+fashion\b/i', $text)) {
            return 'women fashion';
        }
        if (preg_match('/\b(male|men\'?s?)\s+fashion\b/i', $text)) {
            return 'men fashion';
        }

        $term = $classifier->extractProductTerm($question);
        if ($term !== '' && ! preg_match('/^\s*top\s*\d*\s*$/i', $term)) {
            return $term;
        }

        $parser = app(CompositeQueryParser::class);
        $parsed = $parser->parse($question);
        if ($parsed->category) {
            return config("market.categories.{$parsed->category}.label", $parsed->category);
        }

        return '';
    }

    private function resolveAudienceFromQuestion(string $question): ?string
    {
        return app(CompositeQueryParser::class)->parse($question)->audience;
    }

    /**
     * @param  array<int, array<string, mixed>>  $allResults
     * @param  array<string, true>  $seenUrls
     * @param  array<int, array<string, mixed>>  $hits
     * @return array<int, array<string, mixed>>
     */
    private function mergeSearchHits(
        array $allResults,
        array &$seenUrls,
        array $hits,
        string $query,
        string $channel,
    ): array {
        foreach ($hits as $hit) {
            $url = strtolower(trim($hit['url'] ?? ''));
            $titleKey = strtolower(trim(preg_replace('/\s+/', ' ', $hit['title'] ?? '') ?? ''));

            if ($url !== '' && isset($seenUrls[$url])) {
                continue;
            }
            if ($url === '' && $titleKey !== '' && isset($seenUrls['title:'.$titleKey])) {
                continue;
            }

            if ($url !== '') {
                $seenUrls[$url] = true;
            } elseif ($titleKey !== '') {
                $seenUrls['title:'.$titleKey] = true;
            }

            $hit['query'] = $query;
            $hit['search_channel'] = $channel;
            $hit['site'] = $hit['site'] ?? $this->siteSelector->detectSiteFromUrl($hit['url'] ?? '');

            $allResults[] = $hit;
        }

        return $allResults;
    }

    /**
     * @param  array<int, string>  $selectedSites
     * @param  array<int, string>  $siteLabels
     */
    private function runAiModeSearch(
        string $query,
        GooglePeriod $period,
        string $question,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
        array $selectedSites,
        array $siteLabels,
    ): array {
        $aiData = $this->aiMode->search($query);
        $catInfo = $category ? config("market.categories.{$category}", []) : [];

        return [
            'question' => "{$question} | ".($catInfo['label_bn'] ?? 'সব').' | '.$period->labelBn().' ('.$period->label().')',
            'period' => ['label' => $period->label(), 'label_bn' => $period->labelBn()],
            'category' => $category,
            'category_label' => $catInfo['label_bn'] ?? '',
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'selected_sites' => $selectedSites,
            'selected_site_labels' => $siteLabels,
            'queries' => [$query],
            'result_count' => count($aiData['results']),
            'results' => $aiData['results'],
            'search_source' => 'google_ai_mode',
            'ai_answer' => $aiData['ai_answer'],
            'ai_mode_token' => $aiData['subsequent_request_token'],
            'shopping_results' => $aiData['shopping_results'],
        ];
    }

    /**
     * @param  array<int, string>  $queries
     * @param  array<int, string>  $selectedSites
     * @param  array<int, string>  $siteLabels
     */
    private function runWebSearch(
        array $queries,
        GooglePeriod $period,
        string $question,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
        array $selectedSites,
        array $siteLabels,
    ): array {
        $allResults = [];
        $seenUrls = [];
        $queriesRun = [];
        $errors = [];

        foreach ($queries as $q) {
            $queriesRun[] = $q;
            try {
                $hits = $this->search->search($q, config('market.trending_search_target_results', 15));
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                continue;
            }
            foreach ($hits as $hit) {
                $url = $hit['url'] ?? '';
                if ($url && isset($seenUrls[$url])) {
                    continue;
                }
                if ($url) {
                    $seenUrls[$url] = true;
                }
                $hit['query'] = $q;
                $hit['site'] = $this->siteSelector->detectSiteFromUrl($url);
                $allResults[] = $hit;
            }
            if (count($allResults) >= config('market.trending_search_target_results', 15)) {
                break;
            }
        }

        if (empty($allResults)) {
            $detail = $errors ? implode('; ', array_slice($errors, -2)) : 'no network results';
            throw new \RuntimeException("Google search failed for {$period->label()}. {$detail}");
        }

        $catInfo = $category ? config("market.categories.{$category}", []) : [];

        return [
            'question' => "{$question} | ".($catInfo['label_bn'] ?? 'সব').' | '.$period->labelBn().' ('.$period->label().')',
            'period' => ['label' => $period->label(), 'label_bn' => $period->labelBn()],
            'category' => $category,
            'category_label' => $catInfo['label_bn'] ?? '',
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'selected_sites' => $selectedSites,
            'selected_site_labels' => $siteLabels,
            'queries' => $queriesRun,
            'result_count' => count($allResults),
            'results' => array_slice($allResults, 0, config('market.trending_search_target_results', 15) + 5),
            'search_source' => 'web',
        ];
    }

    public function runAiModeFollowUp(array $searchData, string $clarifyingQuestion): array
    {
        $token = $searchData['ai_mode_token'] ?? null;
        if (! $token || ! $this->aiMode->isAvailable()) {
            return $searchData;
        }

        $aiData = $this->aiMode->search($clarifyingQuestion, $token);

        $existingResults = $searchData['results'] ?? [];
        $seen = collect($existingResults)->pluck('title')->filter()->flip()->all();
        $merged = $existingResults;

        foreach ($aiData['results'] as $hit) {
            $key = $hit['title'] ?? '';
            if ($key && isset($seen[$key])) {
                continue;
            }
            if ($key) {
                $seen[$key] = true;
            }
            $merged[] = $hit;
        }

        $prevAnswer = trim($searchData['ai_answer'] ?? '');
        $newAnswer = trim($aiData['ai_answer'] ?? '');
        $combinedAnswer = $prevAnswer && $newAnswer
            ? $prevAnswer."\n\n---\n\n".$newAnswer
            : ($newAnswer ?: $prevAnswer);

        $queriesRun = $searchData['queries'] ?? [];
        $queriesRun[] = '[AI follow-up] '.$clarifyingQuestion;

        $searchData['queries'] = $queriesRun;
        $searchData['results'] = array_slice($merged, 0, 15);
        $searchData['result_count'] = count($merged);
        $searchData['ai_answer'] = $combinedAnswer;
        $searchData['ai_mode_token'] = $aiData['subsequent_request_token'] ?? $token;
        $searchData['search_source'] = 'google_ai_mode';

        return $searchData;
    }

    public function runExtraSearches(array $searchData, array $extraQueries): array
    {
        if (empty($extraQueries)) {
            return $searchData;
        }

        $useWebOnly = $this->usesFreeSearchBackend();

        if (! $useWebOnly && $this->aiMode->isAvailable() && ! empty($searchData['ai_mode_token'])) {
            $followUp = $extraQueries[0];

            return $this->runAiModeFollowUp($searchData, $followUp);
        }

        $allResults = $searchData['results'] ?? [];
        $seenUrls = collect($allResults)->pluck('url')->filter()->flip()->all();
        $queriesRun = $searchData['queries'] ?? [];
        $perQuery = config('market.trending_search_target_results', 15);
        $maxResults = $perQuery + 5;

        foreach ($extraQueries as $q) {
            if (in_array($q, $queriesRun, true)) {
                continue;
            }
            $queriesRun[] = $q;

            if (! $useWebOnly && $this->aiMode->isAvailable()) {
                try {
                    $aiData = $this->aiMode->search($q);
                    foreach ($aiData['results'] as $hit) {
                        $title = $hit['title'] ?? '';
                        if ($title && isset($seenUrls[$title])) {
                            continue;
                        }
                        if ($title) {
                            $seenUrls[$title] = true;
                        }
                        $allResults[] = $hit;
                    }
                    if ($aiData['ai_answer']) {
                        $searchData['ai_answer'] = trim(($searchData['ai_answer'] ?? '')."\n\n".$aiData['ai_answer']);
                    }
                    $searchData['ai_mode_token'] = $aiData['subsequent_request_token'] ?? $searchData['ai_mode_token'] ?? null;
                } catch (\Throwable) {
                    continue;
                }
            } else {
                try {
                    $hits = $this->search->search($q, $perQuery);
                } catch (\Throwable) {
                    continue;
                }
                foreach ($hits as $hit) {
                    $url = $hit['url'] ?? '';
                    if ($url && isset($seenUrls[$url])) {
                        continue;
                    }
                    if ($url) {
                        $seenUrls[$url] = true;
                    }
                    $hit['query'] = $q;
                    $hit['site'] = $this->siteSelector->detectSiteFromUrl($url);
                    $allResults[] = $hit;
                }
            }
        }

        $searchData['queries'] = $queriesRun;
        $searchData['results'] = array_slice($allResults, 0, $maxResults);
        $searchData['result_count'] = count($allResults);

        return $searchData;
    }

    private function usesFreeSearchBackend(): bool
    {
        return in_array(config('market.search_backend', 'duckduckgo'), ['duckduckgo', 'searxng', 'free'], true);
    }

    private function budgetClause(?int $min, ?int $max): string
    {
        if ($min && $max) {
            return "{$min} to {$max}";
        }
        if ($max) {
            return "under {$max}";
        }

        return '';
    }

    private function isNaturalTrendingQuestion(string $question): bool
    {
        $lower = strtolower(trim($question));

        return (bool) preg_match(
            '/\b(trending|tranding|ki product|kon product|beshi bikri|best selling|ট্রেন্ডিং|প্রোডাক্ট)\b/ui',
            $lower,
        );
    }

    private function enhanceUserQuestionForAiMode(
        string $question,
        GooglePeriod $period,
        int $topN,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
    ): string {
        $catLabel = $category ? config("market.categories.{$category}.label", $category) : 'all categories';
        $periodLabel = $period->label();
        $budget = $this->budgetClause($budgetMin, $budgetMax);

        $sites = $this->siteSelector->selectForCategory($category, 4);
        $siteNames = collect($sites)
            ->map(fn ($k) => $this->siteSelector->siteInfo($k)['label'] ?? $k)
            ->implode(', ');

        $season = app(BangladeshSeasonService::class)->forPeriod($period);

        $parts = [
            "What are the exactly {$topN} top trending consumer products in Bangladesh",
            "specifically for {$periodLabel} ({$season['label_en']} season)?",
        ];

        if ($category) {
            $catLabel = config("market.categories.{$category}.label", $category);
            $parts[0] = "What are the exactly {$topN} top trending {$catLabel} products in Bangladesh for {$periodLabel}?";
        }

        if ($budget) {
            $parts[] = "Budget: {$budget} BDT.";
        }

        if ($siteNames) {
            $parts[] = "Sold on {$siteNames}.";
        }

        $parts[] = "Season: {$season['label_en']} in Bangladesh.";
        $parts[] = 'List only products found in real search data for this month — do not guess product names.';
        $parts[] = 'Each product: type name + BDT price range. Exclude currency and deals.';
        $parts[] = "User question: \"{$question}\"";

        return implode(' ', array_filter($parts));
    }
}
