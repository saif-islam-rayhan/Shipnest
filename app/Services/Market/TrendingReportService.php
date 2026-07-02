<?php

namespace App\Services\Market;

class TrendingReportService
{
    public function __construct(
        private TrendingGoogleService $google,
        private TrendingAnalyzeService $analyze,
        private TrendingOverviewService $overview,
        private AnswerValidatorService $validator,
        private InputParsers $inputParsers,
    ) {}

    public function run(
        string $question,
        GooglePeriod $period,
        int $topN = 5,
        ?string $category = null,
        ?int $budgetMin = null,
        ?int $budgetMax = null,
    ): string {
        return $this->runStructured($question, $period, $topN, $category, $budgetMin, $budgetMax)['markdown'];
    }

    /**
     * @return array{
     *     markdown: string,
     *     summary: string,
     *     products: array<int, array<string, mixed>>,
     *     follow_ups: array<int, string>,
     *     thought_process: array<int, string>,
     *     sources: array<int, array<string, string>>
     * }
     */
    public function runStructured(
        string $question,
        GooglePeriod $period,
        int $topN = 5,
        ?string $category = null,
        ?int $budgetMin = null,
        ?int $budgetMax = null,
    ): array {
        $thoughtProcess = [];
        $catLabel = $category ? config("market.categories.{$category}.label_bn", $category) : 'General';
        $budgetLabel = $this->inputParsers->formatBudgetLabel($budgetMin, $budgetMax);

        $thoughtProcess[] = "Query parsed — Category: {$catLabel}, Period: {$period->labelBn()}, Budget: {$budgetLabel}";
        $header = $this->buildHeader($period, $category, $budgetMin, $budgetMax, $question);
        $maxRetries = config('market.validation_max_retries');

        try {
            $searchBackend = config('market.use_google_ai_mode') && config('market.serpapi_key')
                ? 'Google AI Mode'
                : 'multi-site web search';
            $thoughtProcess[] = "Running {$searchBackend} (Daraz, Shajgoj, Pickaboo...)";
            $searchData = $this->google->runSearch($period, $question, $topN, $category, $budgetMin, $budgetMax);
            $source = $searchData['search_source'] ?? 'web';
            $thoughtProcess[] = 'Search complete ('.$source.') — '.($searchData['result_count'] ?? 0).' results from '
                .implode(', ', $searchData['selected_site_labels'] ?? ['web']);
            if (! empty($searchData['ai_answer'])) {
                $thoughtProcess[] = 'Google AI Mode returned structured answer — LLM will analyze';
            }
        } catch (\Throwable $e) {
            $thoughtProcess[] = 'Search failed: '.$e->getMessage();

            return [
                'markdown' => $header."❌ **Search failed:** `{$e->getMessage()}`\n\n"
                    ."_Tip: Google AI Mode-এর জন্য `.env` এ `SERPAPI_KEY` add করুন। অথবা `TAVILY_API_KEY` দিয়ে web search চালু করুন।_",
                'summary' => 'Search ব্যর্থ হয়েছে। API key বা network check করুন।',
                'products' => [],
                'follow_ups' => AgentResponseBuilder::defaultFollowUps($category),
                'thought_process' => $thoughtProcess,
                'sources' => [],
            ];
        }

        $sitesLine = '';
        if (! empty($searchData['selected_site_labels'])) {
            $sitesLine = '🏪 **Sites:** '.implode(', ', $searchData['selected_site_labels'])."\n\n";
        }

        $aiModeNote = ($searchData['search_source'] ?? '') === 'google_ai_mode'
            ? "🤖 _Google AI Mode search — real-time web data, no hardcoded catalog._\n\n"
            : '';

        $body = '';
        $retryNote = '';
        $uiProducts = [];
        $summary = '';
        $analysisType = 'snippet';

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $thoughtProcess[] = 'Analyzing snippets + LLM extract (attempt '.($attempt + 1).")";
            [, $meta] = $this->analyze->analyze($searchData, $topN);
            $products = $meta['products'] ?? [];
            $analysisType = $meta['analysis'] ?? 'snippet';

            $overview = $this->overview->generateStructured($searchData, $products, $topN);
            $body = $overview['markdown'];
            $uiProducts = $overview['products'];
            $summary = $overview['summary'];

            $thoughtProcess[] = 'Product analysis: '.$analysisType.' — '.count($uiProducts).' products matched';

            $validation = $this->validator->validateTrending($question, $searchData, $products, $body);
            if ($validation['is_satisfactory']) {
                $thoughtProcess[] = 'Validation passed (score: '.($validation['score'] ?? 'n/a').')';
                if ($attempt > 0) {
                    $retryNote = '🔄 **'.($attempt + 1)." বার search + verify** করে উত্তর তৈরি।\n\n";
                }
                break;
            }

            $thoughtProcess[] = 'Validation needs improvement (score: '.($validation['score'] ?? 'n/a').')';

            if ($attempt >= $maxRetries - 1) {
                if (! empty($validation['reason_bn'])) {
                    $retryNote = '⚠️ _'.$validation['reason_bn']." (সর্বোচ্চ {$maxRetries} বার চেষ্টা)_\n\n";
                }
                break;
            }

            if (($validation['needs_clarification'] ?? false) && ! empty($validation['clarifying_question'])) {
                $clarifyQ = $validation['clarifying_question'];
                $thoughtProcess[] = 'Google AI Mode follow-up: '.$clarifyQ;
                if (! empty($validation['clarifying_question_bn'])) {
                    $thoughtProcess[] = 'Clarification: '.$validation['clarifying_question_bn'];
                }
                $searchData = $this->google->runAiModeFollowUp($searchData, $clarifyQ);
                continue;
            }

            $newQueries = $validation['suggested_search_queries'] ?? [];
            if (empty($newQueries)) {
                break;
            }
            $searchData = $this->google->runExtraSearches($searchData, $newQueries);
            $thoughtProcess[] = 'Extra searches: '.implode('; ', array_slice($newQueries, 0, 2));
        }

        $footer = $this->buildFooter($searchData);
        $sources = $this->extractSources($searchData);
        $followUps = $this->buildFollowUps($category, $budgetMin, $budgetMax, $period, $uiProducts);

        $thoughtProcess[] = 'Generated '.count($uiProducts).' product cards + '.count($followUps).' follow-up suggestions';

        return [
            'markdown' => $header.$sitesLine.$aiModeNote.$retryNote.$body.$footer,
            'summary' => $summary,
            'products' => $uiProducts,
            'follow_ups' => $followUps,
            'thought_process' => $thoughtProcess,
            'sources' => $sources,
            'query' => $question,
        ];
    }

    private function buildHeader(GooglePeriod $period, ?string $category, ?int $bmin, ?int $bmax, string $question): string
    {
        $catLabel = $category ? config("market.categories.{$category}.label_bn", '') : '';
        $budgetLabel = $this->inputParsers->formatBudgetLabel($bmin, $bmax);
        $sourceLabel = config('market.use_google_ai_mode') && config('market.serpapi_key')
            ? 'Google AI Mode'
            : 'Google Search';
        $header = "🔍 **{$sourceLabel}** | {$period->labelBn()} ({$period->label()})\n";
        if ($catLabel) {
            $header .= "📂 **Category:** {$catLabel}\n";
        }
        if ($bmax || $bmin) {
            $header .= "💰 **Budget:** {$budgetLabel}\n";
        }
        $header .= "_প্রশ্ন: `{$question}`_\n\n";

        return $header;
    }

    private function buildFooter(array $searchData): string
    {
        $results = collect($searchData['results'] ?? [])->filter(fn ($r) => ! empty($r['url']));
        if ($results->isEmpty()) {
            return '';
        }

        $grouped = $results->groupBy(fn ($r) => $r['site'] ?? app(SiteSelectorService::class)->detectSiteFromUrl($r['url'] ?? ''));
        $lines = [];
        foreach ($grouped as $site => $hits) {
            foreach ($hits->take(2) as $r) {
                $title = mb_substr($r['title'] ?? 'link', 0, 55);
                $lines[] = "- **[{$site}]** [{$title}]({$r['url']})";
            }
        }
        $sources = implode("\n", array_slice($lines, 0, 8));

        return "\n\n---\n📎 **Sources ({$searchData['result_count']}) — multi-site:**\n{$sources}";
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
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, string>
     */
    private function buildFollowUps(
        ?string $category,
        ?int $bmin,
        ?int $bmax,
        GooglePeriod $period,
        array $products,
    ): array {
        $periodLabel = $period->label();
        $suggestions = [];

        if ($category === 'fashion') {
            $suggestions[] = "Show women's kurti under 1000 tk trending {$periodLabel}";
            $suggestions[] = 'Filter panjabi 800-1500 tk trending';
            $suggestions[] = 'Compare Daraz vs Shajgoj fashion trending';
        } elseif ($category === 'electronics') {
            $suggestions[] = 'Show wireless earbuds under 1500 tk trending';
            $suggestions[] = 'Filter power bank 600-2000 tk trending';
            $suggestions[] = 'Compare Pickaboo vs Daraz electronics';
        } else {
            $suggestions = AgentResponseBuilder::defaultFollowUps($category);
        }

        if ($bmin && $bmax) {
            $mid = (int) (($bmin + $bmax) / 2);
            $suggestions[] = "top 5 {$category} products under {$mid} tk trending";
        }

        foreach ($products as $p) {
            $name = $p['name'] ?? $p['product'] ?? '';
            if ($name) {
                $suggestions[] = "Show more {$name} options on Daraz";
            }
        }

        return array_values(array_unique(array_slice($suggestions, 0, 3)));
    }
}
