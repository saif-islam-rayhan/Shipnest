<?php

namespace App\Services\Market;

class TrendingAnalyzeService
{
    private const EXTRACT_PROMPT = <<<'PROMPT'
You are BD Market Product Analyst for Bangladesh e-commerce.
Extract SPECIFIC product TYPE names from search snippets — e.g. kurti, tops, t-shirt, panjabi, kurta.
Rules:
- Use short product category names only (1-3 words).
- NEVER include prices in the product name (no "2000 taka dress").
- NEVER copy SEO listing titles like "Buy X Online" or "ladies dress 500 taka".
- price_bdt goes in the price_bdt field, not in product name.
Return valid JSON only:
{"summary":"","products":[{"product":"kurti","price_bdt":850,"label":"HOT","reason":"~৳800–1,200"}]}
PROMPT;

    public function __construct(
        private LlmClient $llm,
        private SearchHelpers $helpers,
    ) {}

    public function analyze(array $searchData, int $topN): array
    {
        $results = $searchData['results'] ?? [];
        $meta = ['google_count' => count($results), 'analysis' => 'snippet-only', 'source' => 'google'];

        if (empty($results)) {
            return [
                $this->helpers->formatProductList([], $topN, $searchData, 'Google search থেকে result পাওয়া যায়নি।'),
                $meta,
            ];
        }

        $products = [];
        try {
            $products = $this->llmExtract($searchData, $topN);
            $meta['analysis'] = 'llm';
        } catch (\Throwable) {
            $products = [];
            $meta['analysis'] = 'snippet-only';
        }

        $bmin = $searchData['budget_min'] ?? null;
        $bmax = $searchData['budget_max'] ?? null;

        $llmProducts = $this->helpers->filterSpecificProducts($products);

        $listings = $this->helpers->extractListingsFromResults(
            $results,
            $bmin,
            $bmax,
            $topN + 2,
        );

        $aiShopping = $this->helpers->extractProductsFromAiShopping($searchData['shopping_results'] ?? []);

        // Priority: LLM extract → AI Mode shopping → search listings (no hardcoded catalog)
        $products = $this->helpers->mergeProductLists($topN, $llmProducts, $aiShopping, $listings);

        if (($searchData['search_source'] ?? '') === 'google_ai_mode') {
            $meta['analysis'] = ($meta['analysis'] ?? 'llm').'+ai_mode';
        }

        $products = $this->helpers->filterByBudget(
            $this->helpers->filterSpecificProducts($products),
            $bmin,
            $bmax,
            $topN,
        );

        $note = count($products) < $topN
            ? 'Search result-এ সরাসরি product name কম — আরও specific query দিয়ে refine করতে পারেন।'
            : '';

        $meta['product_count'] = count($products);
        $meta['products'] = $products;

        return [
            $this->helpers->formatProductList($products, $topN, $searchData, $note),
            $meta,
        ];
    }

    private function llmExtract(array $searchData, int $topN): array
    {
        $bmin = $searchData['budget_min'] ?? null;
        $bmax = $searchData['budget_max'] ?? null;
        $budgetNote = $bmax
            ? 'Budget: ৳'.number_format($bmin ?? 0).' – ৳'.number_format($bmax).' BDT.'
            : '';

        $periodNote = '';
        if (! empty($searchData['period']['label'])) {
            $periodNote = 'Period: '.($searchData['period']['label_bn'] ?? $searchData['period']['label'])."\n";
        }

        $aiAnswer = trim($searchData['ai_answer'] ?? '');
        $aiSection = $aiAnswer
            ? "\n\nGoogle AI Mode answer:\n".mb_substr($aiAnswer, 0, 3000)
            : '';

        $userPrompt = "Category: ".($searchData['category_label'] ?? '')."\n{$periodNote}{$budgetNote}\n"
            ."Extract up to {$topN} products:\n"
            .$this->helpers->compactResultsJson($searchData['results'] ?? [])
            .$aiSection;

        $raw = $this->llm->chat(
            config('market.model_google_search'),
            self::EXTRACT_PROMPT,
            $userPrompt,
            jsonMode: true,
            temperature: 0.2,
        );

        $parsed = json_decode($raw, true);
        $items = $parsed['products'] ?? [];

        return collect($items)->map(fn ($item) => [
            'product' => $item['product'] ?? '',
            'price_bdt' => $item['price_bdt'] ?? null,
            'label' => $item['label'] ?? 'MODERATE',
            'reason' => $item['reason'] ?? '',
        ])->filter(fn ($p) => ! empty($p['product']))->values()->all();
    }
}
