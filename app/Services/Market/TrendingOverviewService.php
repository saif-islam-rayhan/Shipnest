<?php

namespace App\Services\Market;

class TrendingOverviewService
{
    private const OVERVIEW_PROMPT = <<<'PROMPT'
You are a Bangladesh e-commerce market analyst.
Given product candidates and search context, pick exactly N trending product TYPE names.
Rules:
- Output clean names only: kurti, tops, t-shirt, panjabi, kurta (NOT "2000 taka dress").
- Each line: product name with price range in BDT.
- Answer in Bangla mixed with English. Use markdown numbered list.
PROMPT;

    public function __construct(
        private SearchHelpers $helpers,
        private LlmClient $llm,
    ) {}

    public function generate(array $searchData, array $products, int $topN): string
    {
        $structured = $this->generateStructured($searchData, $products, $topN);

        return $structured['markdown'];
    }

    /**
     * @return array{markdown: string, summary: string, products: array<int, array<string, mixed>>}
     */
    public function generateStructured(array $searchData, array $products, int $topN): array
    {
        $bmin = $searchData['budget_min'] ?? null;
        $bmax = $searchData['budget_max'] ?? null;
        $category = $searchData['category'] ?? null;
        $results = $searchData['results'] ?? [];

        $merged = $this->resolveMergedProducts($searchData, $products, $topN);
        $uiProducts = AgentResponseBuilder::formatProductsForUi($merged, $category);

        $cat = $searchData['category_label'] ?? 'সাধারণ';
        $periodBn = $searchData['period']['label_bn'] ?? $searchData['period']['label'] ?? '';
        $budgetStr = ($bmin && $bmax) ? '৳'.number_format($bmin).'–'.number_format($bmax) : '';

        $summary = $this->buildSummary($cat, $periodBn, $budgetStr, count($uiProducts));

        $lines = ["### 🔎 AI Overview", ''];
        if ($budgetStr && $periodBn) {
            $lines[] = "**{$periodBn}**-এ **{$cat}** ক্যাটাগরিতে **{$budgetStr}** বাজেটে trending প্রোডাক্ট:";
            $lines[] = '';
        }

        $body = $this->tryLlmOverview($searchData, $merged, $topN);
        if ($body) {
            $lines[] = $body;
            $lines[] = '';
        } else {
            $lines = array_merge($lines, $this->formatBySection($merged));
        }

        $siteHits = collect($results)
            ->filter(fn ($r) => ! empty($r['url']))
            ->groupBy(fn ($r) => $r['site'] ?? app(SiteSelectorService::class)->detectSiteFromUrl($r['url'] ?? ''))
            ->take(5);

        if ($siteHits->isNotEmpty()) {
            $lines[] = '### 🛒 E-commerce Sites';
            $lines[] = '';
            foreach ($siteHits as $site => $hits) {
                foreach ($hits->take(2) as $r) {
                    $title = mb_substr($r['title'] ?? $site.' listing', 0, 70);
                    $lines[] = "- **[{$site}]** [{$title}]({$r['url']})";
                }
            }
            $lines[] = '';
        }

        return [
            'markdown' => implode("\n", $lines),
            'summary' => $summary,
            'products' => $uiProducts,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveMergedProducts(array $searchData, array $products, int $topN): array
    {
        $bmin = $searchData['budget_min'] ?? null;
        $bmax = $searchData['budget_max'] ?? null;
        $category = $searchData['category'] ?? null;
        $results = $searchData['results'] ?? [];

        $cleanProducts = array_map(fn ($p) => array_merge($p, [
            'price_label' => $p['price_label'] ?? $p['reason'] ?? (
                isset($p['price_bdt']) ? '৳'.number_format($p['price_bdt']) : ''
            ),
            'section' => $p['section'] ?? $this->helpers->productSection($p['product'] ?? ''),
        ]), $this->helpers->filterSpecificProducts($products));

        $listings = array_map(fn ($p) => array_merge($p, [
            'section' => $this->helpers->productSection($p['product'] ?? ''),
        ]), $this->helpers->extractListingsFromResults($results, $bmin, $bmax, $topN + 3));

        $aiShopping = array_map(fn ($p) => array_merge($p, [
            'section' => $this->helpers->productSection($p['product'] ?? ''),
            'price_label' => $p['reason'] ?? '',
        ]), $this->helpers->extractProductsFromAiShopping($searchData['shopping_results'] ?? []));

        $merged = $this->helpers->mergeProductLists($topN, $cleanProducts, $aiShopping, $listings);

        foreach ($merged as &$item) {
            $item['section'] = $item['section'] ?? $this->helpers->productSection($item['product']);
            $item['price_label'] = $item['price_label'] ?? $item['reason'] ?? '';
        }
        unset($item);

        return $merged;
    }

    private function buildSummary(string $cat, string $periodBn, string $budgetStr, int $count): string
    {
        if ($count === 0) {
            return 'আপনার অনুসন্ধানের জন্য সরাসরি product match পাওয়া যায়নি। নিচে refine করার suggestion দেখুন।';
        }

        $parts = ["আমি {$count}টি trending product খুঁজে পেয়েছি"];
        if ($cat && $cat !== 'সাধারণ') {
            $parts[] = "**{$cat}** ক্যাটাগরিতে";
        }
        if ($periodBn) {
            $parts[] = "**{$periodBn}** সময়ের জন্য";
        }
        if ($budgetStr) {
            $parts[] = "**{$budgetStr}** বাজেটে";
        }

        return implode(' ', $parts).'. নিচে product list এবং refine option দেখুন।';
    }

    /**
     * @param  array<int, array<string, mixed>>  $merged
     */
    private function tryLlmOverview(array $searchData, array $merged, int $topN): ?string
    {
        if (! config('market.use_live_llm') || count($merged) < 2) {
            return null;
        }

        $candidates = collect($merged)->take($topN)->map(fn ($p) => [
            'product' => $p['product'],
            'price' => $p['price_label'] ?? $p['reason'] ?? '',
        ])->values()->all();

        $bmin = $searchData['budget_min'] ?? null;
        $bmax = $searchData['budget_max'] ?? null;
        $budgetNote = ($bmin && $bmax)
            ? 'Budget: ৳'.number_format($bmin).' – ৳'.number_format($bmax)
            : '';

        $userPrompt = "Category: ".($searchData['category_label'] ?? '')."\n"
            ."Period: ".($searchData['period']['label_bn'] ?? '')."\n"
            ."{$budgetNote}\n"
            ."Pick exactly {$topN} products from candidates (you may reword but keep clean type names):\n"
            .json_encode($candidates, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Search snippets:\n".$this->helpers->compactResultsJson($searchData['results'] ?? [], 4);

        $aiAnswer = trim($searchData['ai_answer'] ?? '');
        if ($aiAnswer) {
            $userPrompt .= "\n\nGoogle AI Mode answer:\n".mb_substr($aiAnswer, 0, 2500);
        }

        try {
            return trim($this->llm->chat(
                config('market.model_google_search'),
                self::OVERVIEW_PROMPT,
                $userPrompt,
                temperature: 0.2,
            ));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $merged
     * @return array<int, string>
     */
    private function formatBySection(array $merged): array
    {
        $bySection = ['women' => [], 'men' => [], 'general' => []];
        foreach ($merged as $item) {
            $sec = $item['section'] ?? $this->helpers->productSection($item['product']);
            $bySection[$sec][] = $item;
        }

        $titles = [
            'women' => '### 👗 মেয়েদের ফ্যাশন',
            'men' => '### 👔 পুরুষদের ফ্যাশন',
            'general' => '### 🛍️ ট্রেন্ডিং প্রোডাক্ট',
        ];

        $lines = [];
        $idx = 0;
        foreach (['women', 'men', 'general'] as $key) {
            if (empty($bySection[$key])) {
                continue;
            }
            $lines[] = $titles[$key];
            $lines[] = '';
            foreach ($bySection[$key] as $item) {
                $idx++;
                $lines[] = $this->formatLine($idx, $item);
            }
            $lines[] = '';
        }

        return $lines;
    }

    private function formatLine(int $i, array $item): string
    {
        $line = "{$i}. **{$item['product']}**";
        $price = $item['price_label'] ?? $item['reason'] ?? '';
        if ($price) {
            $line .= " — {$price}";
        }
        if (! empty($item['url'])) {
            $site = $item['site'] ?? 'দেখুন';
            $line .= " — [{$site}]({$item['url']})";
        }

        return $line;
    }
}
