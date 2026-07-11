<?php

namespace App\Services\Market;

use App\Services\Market\Llm\LlmProviderManager;
use App\Services\ProductService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ImageProductSearchAgent
{
    private const PREVIEW_COUNT = 4;

    private const MAX_SEARCH = 48;

    private const MAX_TRENDING = 24;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly ProductService $productService,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $uploadedImages
     * @return array{content: string, meta: array<string, mixed>}
     */
    public function search(array $uploadedImages, string $userMessage = ''): array
    {
        $thoughtProcess = ['Query intent: image-based product search'];

        if (! app(LlmProviderManager::class)->isReady()) {
            return AgentResponseBuilder::make(
                "❌ Image দিয়ে product খুঁজতে **Text AI provider** configure করুন।\n\n"
                .'Admin → Settings → Agent / AI থেকে যেকোনো provider-এ API key দিন এবং Enable করুন।\n\n'
                .'অথবা product-এর নাম লিখে search করুন (যেমন `watch`, `kurti`)।',
                [
                    'type' => 'error',
                    'follow_ups' => ['watch', 'earbuds', 'trending product ki?'],
                    'thought_process' => $thoughtProcess,
                ],
            );
        }

        $dataUris = $this->encodeImages($uploadedImages);
        if ($dataUris === []) {
            return AgentResponseBuilder::make(
                '❌ Image পড়া যায়নি। JPEG, PNG, JPG বা WebP (max 5MB) আবার upload করুন।',
                ['type' => 'error', 'follow_ups' => ['watch', 'earbuds']],
            );
        }

        try {
            $analysis = $this->analyzeImages($dataUris, $userMessage);
        } catch (\Throwable $e) {
            Log::warning('ImageProductSearchAgent: vision analysis failed', ['error' => $e->getMessage()]);

            return AgentResponseBuilder::make(
                '❌ Image analyze করা যায়নি। আবার চেষ্টা করুন অথবা product-এর নাম লিখে search করুন।',
                [
                    'type' => 'error',
                    'follow_ups' => ['watch', 'earbuds', 'kurti'],
                    'thought_process' => array_merge($thoughtProcess, ['Vision analysis failed: '.$e->getMessage()]),
                ],
            );
        }

        $detectedName = trim((string) ($analysis['product_name'] ?? ''));
        $description = trim((string) ($analysis['description'] ?? ''));
        $productType = trim((string) ($analysis['product_type'] ?? ''));

        $thoughtProcess[] = 'Vision: '.($description !== '' ? $description : 'product identified');
        if ($detectedName !== '') {
            $thoughtProcess[] = 'Detected: '.$detectedName;
        }
        if ($productType !== '') {
            $thoughtProcess[] = 'Product type: '.$productType;
        }

        $searchResult = $this->productService->agentImageCatalogSearch($analysis, self::MAX_SEARCH);
        $matchQuality = $searchResult['match_quality'];
        $typeKeywords = $searchResult['type_keywords'];
        $scores = $searchResult['scores'];

        $thoughtProcess[] = 'Type keywords: '.implode(', ', $typeKeywords ?: ['none']);
        $thoughtProcess[] = 'Match quality: '.$matchQuality;

        $relatedTerm = $typeKeywords[0] ?? $productType ?: $detectedName;
        $queryLabel = $detectedName !== '' ? $detectedName : ($productType !== '' ? $productType : 'image search');

        if ($matchQuality === 'none') {
            return $this->buildNoMatchResponse(
                $analysis,
                $detectedName,
                $description,
                $relatedTerm,
                $queryLabel,
                $thoughtProcess,
            );
        }

        $allProducts = $this->formatScoredProducts($searchResult['products'], $scores, $matchQuality);
        $total = count($allProducts);

        if ($matchQuality === 'weak') {
            $thoughtProcess[] = "Weak matches only ({$total}) — exact product not in catalog";

            return $this->buildWeakMatchResponse(
                $analysis,
                $detectedName,
                $description,
                $allProducts,
                $relatedTerm,
                $queryLabel,
                $thoughtProcess,
            );
        }

        $thoughtProcess[] = "Strong matches: {$total}";
        $excludeIds = collect($allProducts)->pluck('id')->filter()->values()->all();
        $relatedRaw = $this->productService->agentRelatedTrending($relatedTerm, $excludeIds, self::MAX_TRENDING);
        $relatedAll = AgentResponseBuilder::formatPlatformTrendingProducts($relatedRaw);
        $thoughtProcess[] = 'Related: '.count($relatedAll).' product(s)';

        $content = $this->buildAnalysisHeader($detectedName, $description);
        $content .= "✅ ShipNest catalog-এ **{$total}টি** মিলে যাওয়া product পাওয়া গেছে।\n\n"
            .'নিচে দেখুন — **Add to cart** করতে পারবেন।';

        $typeLabel = $productType !== '' ? $productType : ($typeKeywords[0] ?? $queryLabel);
        $summary = "Image থেকে **{$typeLabel}** শনাক্ত — catalog-এ **{$total}টি** relevant match।";

        return AgentResponseBuilder::make($content, [
            'type' => 'platform',
            'intent' => QueryIntent::PLATFORM_SEARCH,
            'catalog_mode' => 'image_search',
            'image_analysis' => $analysis,
            'summary' => $summary,
            'products' => array_slice($allProducts, 0, self::PREVIEW_COUNT),
            'products_all' => $allProducts,
            'products_preview_count' => self::PREVIEW_COUNT,
            'total_count' => $total,
            'trending_products' => array_slice($relatedAll, 0, self::PREVIEW_COUNT),
            'trending_products_all' => $relatedAll,
            'trending_total_count' => count($relatedAll),
            'trending_preview_count' => self::PREVIEW_COUNT,
            'follow_ups' => $this->followUpsFromAnalysis($analysis),
            'thought_process' => $thoughtProcess,
            'query' => $queryLabel,
            'show_content' => true,
        ]);
    }

    /**
     * @param  array<int, \App\Models\Product>  $products
     * @param  array<int, int>  $scores
     * @return array<int, array<string, mixed>>
     */
    private function formatScoredProducts(array $products, array $scores, string $matchQuality): array
    {
        $formatted = AgentResponseBuilder::formatPlatformProducts($products);

        foreach ($formatted as &$product) {
            $id = $product['id'] ?? null;
            $score = $id ? ($scores[$id] ?? 0) : 0;

            $product['match_score'] = $score;
            $product['match_label'] = match (true) {
                $score >= 10 => 'Exact match',
                $score >= 7 => 'Strong match',
                $score >= 5 => 'Good match',
                $matchQuality === 'weak' => 'Similar product',
                default => 'Possible match',
            };
        }
        unset($product);

        return $formatted;
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<int, string>  $thoughtProcess
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function buildNoMatchResponse(
        array $analysis,
        string $detectedName,
        string $description,
        string $relatedTerm,
        string $queryLabel,
        array $thoughtProcess,
    ): array {
        $relatedRaw = $this->productService->agentRelatedTrending($relatedTerm, [], self::MAX_TRENDING);
        $relatedAll = AgentResponseBuilder::formatPlatformTrendingProducts($relatedRaw);
        $thoughtProcess[] = 'No match — showing '.count($relatedAll).' related product(s)';

        $content = $this->buildAnalysisHeader($detectedName, $description);
        $content .= "⚠️ ShipNest catalog-এ **একদম একই product** পাওয়া যায়নি।\n\n";
        $content .= $relatedAll !== []
            ? 'নিচে **সম্পর্কিত product** দেখুন — হয়তো আপনার পছন্দের মতো কিছু পাবেন।'
            : 'সম্পর্কিত product-ও পাওয়া যায়নি। অন্য keyword দিয়ে search করুন।';

        return AgentResponseBuilder::make($content, [
            'type' => 'platform',
            'intent' => QueryIntent::PLATFORM_SEARCH,
            'catalog_mode' => 'image_search',
            'image_analysis' => $analysis,
            'summary' => $detectedName !== ''
                ? "«{$detectedName}» catalog-এ নেই — related products দেখানো হচ্ছে।"
                : 'Image থেকে product match পাওয়া যায়নি — related products দেখানো হচ্ছে।',
            'products' => [],
            'products_all' => [],
            'trending_products' => array_slice($relatedAll, 0, self::PREVIEW_COUNT),
            'trending_products_all' => $relatedAll,
            'trending_total_count' => count($relatedAll),
            'trending_preview_count' => self::PREVIEW_COUNT,
            'follow_ups' => $this->followUpsFromAnalysis($analysis),
            'thought_process' => $thoughtProcess,
            'query' => $queryLabel,
            'show_content' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<int, array<string, mixed>>  $similarProducts
     * @param  array<int, string>  $thoughtProcess
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function buildWeakMatchResponse(
        array $analysis,
        string $detectedName,
        string $description,
        array $similarProducts,
        string $relatedTerm,
        string $queryLabel,
        array $thoughtProcess,
    ): array {
        $excludeIds = collect($similarProducts)->pluck('id')->filter()->values()->all();
        $relatedRaw = $this->productService->agentRelatedTrending($relatedTerm, $excludeIds, self::MAX_TRENDING);
        $relatedAll = AgentResponseBuilder::formatPlatformTrendingProducts($relatedRaw);

        $content = $this->buildAnalysisHeader($detectedName, $description);
        $content .= "⚠️ **একদম একই product** catalog-এ নেই — শুধু brand বা আংশিক match পাওয়া গেছে।\n\n";
        $content .= 'নিচে **সম্পর্কিত / similar product** দেখুন।';

        return AgentResponseBuilder::make($content, [
            'type' => 'platform',
            'intent' => QueryIntent::PLATFORM_SEARCH,
            'catalog_mode' => 'image_search',
            'image_analysis' => $analysis,
            'summary' => $detectedName !== ''
                ? "«{$detectedName}» exact match নেই — similar products দেখানো হচ্ছে।"
                : 'Exact match নেই — similar products দেখানো হচ্ছে।',
            'products' => [],
            'products_all' => [],
            'trending_products' => array_slice($similarProducts, 0, self::PREVIEW_COUNT),
            'trending_products_all' => array_merge($similarProducts, $relatedAll),
            'trending_total_count' => count($similarProducts) + count($relatedAll),
            'trending_preview_count' => self::PREVIEW_COUNT,
            'follow_ups' => $this->followUpsFromAnalysis($analysis),
            'thought_process' => $thoughtProcess,
            'query' => $queryLabel,
            'show_content' => true,
        ]);
    }

    private function buildAnalysisHeader(string $detectedName, string $description): string
    {
        $content = "📷 **Image analyze করা হয়েছে**\n\n";
        if ($detectedName !== '') {
            $content .= "🔍 দেখা যাচ্ছে: **{$detectedName}**";
            if ($description !== '') {
                $content .= " — {$description}";
            }
            $content .= "\n\n";
        }

        return $content;
    }

    /**
     * @param  array<int, string>  $dataUris
     * @return array<string, mixed>
     */
    private function analyzeImages(array $dataUris, string $userMessage): array
    {
        $systemPrompt = <<<'PROMPT'
You analyze product images for an ecommerce catalog search in Bangladesh.
Return JSON only with keys:
- product_name (string, best guess of full product name)
- product_type (string, the TYPE of product only — e.g. sneakers, smart watch, kurti, lipstick — NOT the brand)
- category (string, e.g. electronics, fashion, beauty, sports)
- brand (string or null)
- search_keywords (array of 2-5 catalog search terms — prioritize product TYPE words like "sneakers", "running shoes", NOT brand alone)
- description (string, one short sentence in Bangla describing the product)

Important: product_type must describe WHAT the item is (footwear, watch, bag), not just brand or color.
PROMPT;

        $textPrompt = 'Identify this product for catalog search. Focus on product type (e.g. sneakers, shoes, watch).';
        if (trim($userMessage) !== '') {
            $textPrompt .= ' User also asked: '.trim($userMessage);
        }

        $raw = $this->llm->chatWithImages(
            config('market.model_vision', config('market.model_google_search', 'gpt-4o-mini')),
            $systemPrompt,
            $textPrompt,
            $dataUris,
            jsonMode: true,
            temperature: 0.2,
        );

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid vision JSON response');
        }

        return $decoded;
    }

    /**
     * @param  array<int, UploadedFile>  $uploadedImages
     * @return array<int, string>
     */
    private function encodeImages(array $uploadedImages): array
    {
        $uris = [];

        foreach (array_slice($uploadedImages, 0, 3) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $mime = $file->getMimeType() ?: 'image/jpeg';
            $data = base64_encode((string) file_get_contents($file->getRealPath()));
            $uris[] = "data:{$mime};base64,{$data}";
        }

        return $uris;
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<int, string>
     */
    private function followUpsFromAnalysis(array $analysis): array
    {
        $type = trim((string) ($analysis['product_type'] ?? ''));
        $keywords = (array) ($analysis['search_keywords'] ?? []);
        $first = $type !== '' ? $type : trim((string) ($keywords[0] ?? $analysis['product_name'] ?? 'watch'));

        return array_slice(array_values(array_unique(array_filter([
            $first,
            $first.' under 2000 tk',
            'trending product ki?',
        ]))), 0, 3);
    }
}
