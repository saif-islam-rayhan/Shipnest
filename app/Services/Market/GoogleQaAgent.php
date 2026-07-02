<?php

namespace App\Services\Market;

class GoogleQaAgent
{
    public function __construct(
        private CompositeQueryParser $parser,
        private TrendingReportService $trendingReport,
        private GoogleSearchService $search,
        private SearchHelpers $helpers,
        private LlmClient $llm,
        private AnswerValidatorService $validator,
        private TrendingAnalyzeService $analyze,
        private SiteSelectorService $siteSelector,
        private QueryIntentClassifier $intentClassifier,
    ) {}

    public function handle(string $message): string
    {
        return $this->handleStructured($message)['content'];
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    public function handleStructured(string $message): array
    {
        $parsed = $this->parser->parse($message);
        $intent = $this->intentClassifier->classify($message, $parsed);

        if ($parsed->isComplete()) {
            return AgentResponseBuilder::fromTrending($this->trendingReport->runStructured(
                $parsed->question,
                $parsed->period,
                $parsed->topN,
                $parsed->category,
                $parsed->budgetMin,
                $parsed->budgetMax,
            ));
        }

        if ($parsed->isTrending && $parsed->category && $parsed->period) {
            return AgentResponseBuilder::fromTrending($this->trendingReport->runStructured(
                $parsed->question,
                $parsed->period,
                $parsed->topN,
                $parsed->category,
                $parsed->budgetMin,
                $parsed->budgetMax,
            ));
        }

        if ($this->intentClassifier->isGeneralKnowledge($message, $parsed)) {
            return $this->runGeneralQaStructured($message);
        }

        $includeProducts = $this->intentClassifier->shouldShowProductCards($message, $parsed, $intent);

        return $this->runSearchStructured($message, $parsed, $includeProducts);
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function runGeneralQaStructured(string $question): array
    {
        $thoughtProcess = ['Understanding your question: "'.$question.'"'];
        $hits = [];

        try {
            $hits = $this->search->search($question);
            $thoughtProcess[] = 'Web search returned '.count($hits).' results';
        } catch (\Throwable $e) {
            $thoughtProcess[] = 'Web search unavailable — answering from model knowledge';
        }

        $systemPrompt = 'You are a helpful AI assistant for ShipNest. Answer clearly and directly. '
            .'Use Bangla mixed with English when natural. Keep answers concise (2-5 sentences). '
            .'If web search results are provided, prefer those facts. If unsure, say so honestly.';

        if ($hits) {
            $userPrompt = "User question: {$question}\n\nWeb results:\n".$this->helpers->compactResultsJson($hits);
        } else {
            $userPrompt = "User question: {$question}";
        }

        try {
            $body = $this->llm->chat(config('market.model_google_search'), $systemPrompt, $userPrompt, temperature: 0.3);
            $thoughtProcess[] = 'Generated direct answer';
        } catch (\Throwable $e) {
            $thoughtProcess[] = 'LLM failed: '.$e->getMessage();

            return AgentResponseBuilder::make(
                '❌ উত্তর তৈরি করা যায়নি: `'.$e->getMessage().'`',
                [
                    'type' => 'error',
                    'thought_process' => $thoughtProcess,
                ],
            );
        }

        $sources = $hits ? AgentResponseBuilder::extractSources($hits) : [];

        return AgentResponseBuilder::make($body, [
            'type' => 'chat',
            'summary' => null,
            'products' => [],
            'follow_ups' => [],
            'thought_process' => $thoughtProcess,
            'sources' => $sources,
            'query' => $question,
        ]);
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function runSearchStructured(string $question, CompositeQuery $parsed, bool $includeProducts = true): array
    {
        $topN = config('market.default_top_n', 5);
        $thoughtProcess = [
            'Understanding your query: "'.$question.'"',
        ];

        $searchQ = $this->buildSmartQuery($question, $parsed);
        $thoughtProcess[] = 'Built smart search: `'.$searchQ.'`';

        try {
            $aiData = null;
            if (app(GoogleAiModeSearchService::class)->isAvailable()) {
                $aiData = app(GoogleAiModeSearchService::class)->search($searchQ);
                $hits = $aiData['results'];
                $thoughtProcess[] = 'Google AI Mode returned '.count($hits).' results';
            } else {
                $hits = $this->search->search($searchQ);
                $thoughtProcess[] = 'Web search returned '.count($hits).' results';
            }
        } catch (\Throwable $e) {
            $thoughtProcess[] = 'Search failed: '.$e->getMessage();

            return AgentResponseBuilder::make(
                '❌ Search failed: `'.$e->getMessage().'`',
                [
                    'type' => 'error',
                    'summary' => 'Search ব্যর্থ হয়েছে। আবার চেষ্টা করুন বা query পরিবর্তন করুন।',
                    'thought_process' => $thoughtProcess,
                    'follow_ups' => AgentResponseBuilder::followUpsForQuery($question),
                ],
            );
        }

        $searchData = [
            'question' => $question,
            'result_count' => count($hits),
            'results' => $hits,
            'queries' => [$searchQ],
            'category' => $parsed->category,
            'category_label' => $parsed->category
                ? config("market.categories.{$parsed->category}.label_bn", '')
                : '',
            'budget_min' => $parsed->budgetMin,
            'budget_max' => $parsed->budgetMax,
            'search_source' => app(GoogleAiModeSearchService::class)->isAvailable() ? 'google_ai_mode' : 'web',
        ];
        if (isset($aiData)) {
            $searchData['ai_answer'] = $aiData['ai_answer'] ?? '';
            $searchData['ai_mode_token'] = $aiData['subsequent_request_token'] ?? null;
            $searchData['shopping_results'] = $aiData['shopping_results'] ?? [];
        }
        if ($parsed->period) {
            $searchData['period'] = $parsed->period->toArray();
        }

        $products = [];
        if ($includeProducts) {
            $thoughtProcess[] = 'Extracting product names from search snippets via LLM';
            [, $meta] = $this->analyze->analyze($searchData, $topN);
            $products = AgentResponseBuilder::formatProductsForUi($meta['products'] ?? [], $parsed->category);

            if (count($products) < $topN) {
                $thoughtProcess[] = 'Supplementing with listing cards from search results';
                $listings = $this->helpers->extractListingsFromResults(
                    $hits,
                    $parsed->budgetMin,
                    $parsed->budgetMax,
                    $topN,
                );
                $products = $this->helpers->mergeProductLists(
                    $topN,
                    $products,
                    AgentResponseBuilder::formatProductsForUi($listings, $parsed->category),
                );
            }

            if (count($products) < 3) {
                $thoughtProcess[] = 'Building discovery cards from top search hits';
                $discovery = AgentResponseBuilder::discoveryCardsFromHits($hits, $question, $topN);
                $products = $this->helpers->mergeProductLists($topN, $products, $discovery);
            }
        } else {
            $thoughtProcess[] = 'Text-only answer (no product cards for this query type)';
        }

        $thoughtProcess[] = 'Generating AI summary'.($includeProducts ? ' and validating answer' : '');

        $body = '';
        if ($includeProducts) {
            $maxRetries = config('market.validation_max_retries');
            for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
                $body = $this->generateReply($question, $searchData);
                $validation = $this->validator->validateQa($question, $searchData, $body);
                if ($validation['is_satisfactory']) {
                    $thoughtProcess[] = 'Answer validated (attempt '.($attempt + 1).')';
                    break;
                }
                if ($attempt >= $maxRetries - 1) {
                    break;
                }
                if (($validation['needs_clarification'] ?? false) && ! empty($validation['clarifying_question'])) {
                    $searchData = app(TrendingGoogleService::class)->runAiModeFollowUp(
                        $searchData,
                        $validation['clarifying_question'],
                    );
                    $thoughtProcess[] = 'Google AI Mode follow-up: '.$validation['clarifying_question'];
                    continue;
                }
                $newQueries = $validation['suggested_search_queries'] ?? [];
                if (empty($newQueries)) {
                    break;
                }
                $searchData = app(TrendingGoogleService::class)->runExtraSearches($searchData, $newQueries);
                $thoughtProcess[] = 'Retry search: '.implode('; ', array_slice($newQueries, 0, 2));
            }
        } else {
            $body = $this->generateReply($question, $searchData);
        }

        $sources = AgentResponseBuilder::extractSources($searchData['results'] ?? []);
        $summary = $includeProducts
            ? AgentResponseBuilder::buildQaSummary($question, $products, count($hits))
            : null;
        $followUps = $includeProducts
            ? AgentResponseBuilder::followUpsForQuery($question, $parsed->category)
            : [];

        $markdown = $includeProducts
            ? $this->buildCompactMarkdown($question, $summary ?? '', $body)
            : $body;

        if ($includeProducts) {
            $thoughtProcess[] = 'Rendered '.count($products).' product cards + '.count($followUps).' follow-up suggestions';
        }

        return AgentResponseBuilder::make($markdown, [
            'type' => $includeProducts ? 'qa' : 'chat',
            'summary' => $summary,
            'products' => $products,
            'follow_ups' => $followUps,
            'thought_process' => $thoughtProcess,
            'sources' => $sources,
            'query' => $question,
        ]);
    }

    private function buildCompactMarkdown(string $question, string $summary, string $body): string
    {
        return "_Query: {$question}_\n\n{$summary}\n\n".$body;
    }

    private function generateReply(string $question, array $searchData): string
    {
        if (! empty($searchData['category']) && ! empty($searchData['period'])) {
            $overview = app(TrendingOverviewService::class);
            [, $meta] = $this->analyze->analyze($searchData, config('market.default_top_n'));

            return $overview->generate($searchData, $meta['products'] ?? [], config('market.default_top_n'));
        }

        $prompt = 'You are a helpful AI assistant for ShipNest with expertise in Bangladesh e-commerce and general knowledge. '
            .'Answer in Bangla mixed with English using ONLY the provided facts when available. '
            .'Be direct and helpful (2-5 sentences). For market/trending questions, summarize trends clearly without listing random products.';
        $userPrompt = "User question: {$question}\nWeb results:\n".$this->helpers->compactResultsJson($searchData['results'] ?? []);

        try {
            return $this->llm->chat(config('market.model_google_search'), $prompt, $userPrompt, temperature: 0.2);
        } catch (\Throwable $e) {
            return '❌ Reply failed: '.$e->getMessage();
        }
    }

    private function buildSmartQuery(string $question, CompositeQuery $parsed): string
    {
        $parts = [trim($question)];
        if ($parsed->category) {
            $label = config("market.categories.{$parsed->category}.label", '');
            if ($label && ! str_contains(strtolower($question), strtolower($label))) {
                $parts[] = $label;
            }
        }
        if ($parsed->period) {
            $parts[] = $parsed->period->label();
        }
        if ($parsed->budgetMin && $parsed->budgetMax) {
            $parts[] = "{$parsed->budgetMin}-{$parsed->budgetMax} taka";
        }
        $sites = $this->siteSelector->selectForCategory($parsed->category, 3);
        $siteNames = collect($sites)
            ->map(fn ($k) => $this->siteSelector->siteInfo($k)['label'] ?? $k)
            ->implode(' ');
        if ($siteNames) {
            $parts[] = $siteNames;
        }
        $parts[] = 'Bangladesh';

        return implode(' ', array_filter($parts));
    }
}
