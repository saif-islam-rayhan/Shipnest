<?php

namespace App\Services\Market;

use App\Models\ProductReview;
use App\Services\Market\Llm\LlmProviderManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReviewSentimentAnalyzer
{
    private const POSITIVE_KW = [
        'good', 'great', 'love', 'excellent', 'amazing', 'awesome', 'perfect',
        'best', 'nice', 'happy', 'satisfied', 'recommend', 'worth', 'quality',
        'ভালো', 'দারুণ', 'সুন্দর', 'চমৎকার', 'অসাধারণ', 'খুশি', 'রেকমেন্ড',
    ];

    private const NEGATIVE_KW = [
        'bad', 'very bad', 'poor', 'hate', 'worst', 'terrible', 'awful', 'fake', 'scam',
        'broken', 'waste', 'disappointed', 'refund', 'useless', 'not good', 'not worth',
        'খারাপ', 'বাজে', 'নষ্ট', 'ভুয়া', 'প্রতারণা', 'রিফান্ড', 'নামঞ্জুর',
        'kharap', 'baje', 'vlo na', 'bhalo na', 'valoi na',
    ];

    public function __construct(
        private readonly LlmClient $llm,
        private readonly LlmProviderManager $providers,
    ) {}

    /**
     * Text-only fallback (rating + keywords).
     */
    public function detect(ProductReview $review): string
    {
        return $this->detectTextSentiment($review);
    }

    public function detectTextSentiment(ProductReview $review): string
    {
        $text = mb_strtolower(trim($review->title.' '.$review->body));
        [$pos, $neg] = $this->keywordScores($text);

        // Explicit complaint/praise in text wins over star rating
        // (e.g. 5★ + "very bad" → negative).
        if ($neg > $pos) {
            return 'negative';
        }

        if ($pos > $neg) {
            return 'positive';
        }

        if ($review->rating >= 4) {
            return 'positive';
        }

        if ($review->rating <= 2) {
            return 'negative';
        }

        return $review->rating >= 3 ? 'positive' : 'negative';
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function keywordScores(string $text): array
    {
        if ($text === '') {
            return [0, 0];
        }

        $pos = 0;
        $neg = 0;

        foreach (self::POSITIVE_KW as $kw) {
            if ($this->containsKeyword($text, $kw)) {
                $pos++;
            }
        }

        foreach (self::NEGATIVE_KW as $kw) {
            if ($this->containsKeyword($text, $kw)) {
                $neg++;
            }
        }

        return [$pos, $neg];
    }

    private function containsKeyword(string $text, string $keyword): bool
    {
        $kw = mb_strtolower($keyword);

        // Multi-word / Bangla phrases: plain contains is fine.
        if (str_contains($kw, ' ') || preg_match('/\p{Bengali}/u', $kw)) {
            return str_contains($text, $kw);
        }

        return (bool) preg_match('/\b'.preg_quote($kw, '/').'\b/ui', $text);
    }

    /**
     * @return array{
     *     sentiment: string,
     *     text_sentiment: string,
     *     image_sentiment: ?string,
     *     text_reason: string,
     *     image_reason: ?string,
     *     used_vision: bool,
     *     vision_error: ?string
     * }
     */
    public function analyze(ProductReview $review): array
    {
        $textSentiment = $this->detectTextSentiment($review);
        $textReason = $this->textReason($review, $textSentiment);

        $imageSentiment = null;
        $imageReason = null;
        $usedVision = false;
        $visionError = null;

        $hasImages = $this->reviewHasImages($review);

        // Local symbol hints (thumbs-up / red-X). Vision overrides when available.
        $local = $hasImages ? $this->detectSymbolicImageHint($review) : null;
        if ($local !== null) {
            $imageSentiment = $local['sentiment'];
            $imageReason = $local['reason'];
        }

        $dataUris = $this->encodeReviewImages($review);
        if ($dataUris !== [] && $this->providers->isReady()) {
            try {
                $vision = $this->analyzeImagesWithVision($review, $dataUris);
                $visionSentiment = in_array($vision['sentiment'] ?? '', ['positive', 'negative'], true)
                    ? $vision['sentiment']
                    : null;
                $visionReason = trim((string) ($vision['reason'] ?? '')) ?: null;
                $usedVision = $visionSentiment !== null;

                if ($visionSentiment !== null) {
                    // Vision is primary. Only keep local when it is a STRONG red-X
                    // and vision somehow disagrees — still prefer vision for thumbs-up cases.
                    if ($visionSentiment === 'positive') {
                        $imageSentiment = 'positive';
                        $imageReason = $visionReason ?: 'Positive review photo';
                    } else {
                        $imageSentiment = 'negative';
                        $imageReason = $visionReason ?: $imageReason;
                    }
                }
            } catch (\Throwable $e) {
                $visionError = $e->getMessage();
                Log::warning('ReviewSentimentAnalyzer: vision failed', [
                    'review_id' => $review->id,
                    'error' => $visionError,
                ]);
            }
        } elseif ($dataUris !== [] && ! $this->providers->isReady()) {
            $visionError = 'Vision LLM not ready';
        }

        if ($hasImages && $imageSentiment === null && $visionError) {
            $imageReason = 'Image present but not analyzed: '.$visionError;
        }

        $sentiment = $this->mergeSentiment($textSentiment, $imageSentiment);

        return [
            'sentiment' => $sentiment,
            'text_sentiment' => $textSentiment,
            'image_sentiment' => $imageSentiment,
            'text_reason' => $textReason,
            'image_reason' => $imageReason,
            'used_vision' => $usedVision,
            'vision_error' => $visionError,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $analysis
     */
    public function buildSummary(ProductReview $review, string $sentiment, ?array $analysis = null): string
    {
        $review->loadMissing(['product', 'user']);

        $label = $sentiment === 'positive' ? 'Positive' : 'Negative';
        $productName = $review->product->name ?? 'Product';
        $customer = $review->user->name ?? 'Customer';
        $title = trim((string) $review->title);
        $body = trim((string) $review->body);
        $snippet = $title !== '' ? $title : $body;
        if (mb_strlen($snippet) > 120) {
            $snippet = mb_substr($snippet, 0, 120).'…';
        }

        $imageCount = count(array_filter($review->images ?? []));

        $line = "New review #{$review->id} — **{$label}**. "
            ."Product: {$productName}. "
            ."Customer: {$customer}. "
            ."Rating: {$review->rating}/5.";

        if ($imageCount > 0) {
            $line .= " Images: {$imageCount}.";
        }

        if ($snippet !== '') {
            $line .= " \"{$snippet}\"";
        }

        if (is_array($analysis)) {
            $textSent = $analysis['text_sentiment'] ?? null;
            $imageSent = $analysis['image_sentiment'] ?? null;

            if ($imageSent && $textSent && $textSent !== $imageSent) {
                $line .= ' [Text: '.ucfirst((string) $textSent).', Image: '.ucfirst((string) $imageSent).' → final '.$label.']';
            } elseif (! empty($analysis['image_reason'])) {
                $reason = (string) $analysis['image_reason'];
                if (mb_strlen($reason) > 120) {
                    $reason = mb_substr($reason, 0, 120).'…';
                }
                $line .= ' Image note: '.$reason;
            }
        }

        return $line;
    }

    public function analyzeAndPersist(ProductReview $review): ProductReview
    {
        $analysis = $this->analyze($review);
        $sentiment = $analysis['sentiment'];
        $summary = $this->buildSummary($review, $sentiment, $analysis);

        $review->update([
            'sentiment' => $sentiment,
            'agent_summary' => $summary,
            'agent_analyzed_at' => now(),
        ]);

        return $review->fresh(['product', 'user']);
    }

    private function mergeSentiment(string $textSentiment, ?string $imageSentiment): string
    {
        if ($imageSentiment === null) {
            return $textSentiment;
        }

        // Bad / reject / damaged photo overrides praise text or high star rating.
        if ($imageSentiment === 'negative') {
            return 'negative';
        }

        if ($textSentiment === 'negative') {
            return 'negative';
        }

        return 'positive';
    }

    private function textReason(ProductReview $review, string $sentiment): string
    {
        $text = mb_strtolower(trim($review->title.' '.$review->body));
        [$pos, $neg] = $this->keywordScores($text);

        if ($neg > $pos) {
            return 'Negative words in review text override rating';
        }

        if ($pos > $neg) {
            return 'Positive words in review text';
        }

        if ($review->rating >= 4) {
            return "Rating {$review->rating}/5 suggests positive";
        }

        if ($review->rating <= 2) {
            return "Rating {$review->rating}/5 suggests negative";
        }

        return 'Text/keywords lean '.$sentiment;
    }

    /**
     * @param  array<int, string>  $dataUris
     * @return array{sentiment?: string, reason?: string}
     */
    private function analyzeImagesWithVision(ProductReview $review, array $dataUris): array
    {
        $review->loadMissing('product');
        $productName = $review->product->name ?? 'Unknown product';
        $title = trim((string) $review->title);
        $body = trim((string) $review->body);

        $systemPrompt = <<<'PROMPT'
You moderate ecommerce product review photos for ShipNest (Bangladesh).
Look ONLY at the attached image(s). Classify the PHOTO symbol/content.

Return JSON only with keys:
- sentiment: "positive" or "negative"
- reason: short English reason (max 25 words)

Mark POSITIVE if you see:
- thumbs up, like hand, green check / OK / success mark
- happy face, clap, heart, star sticker
- intact product, unboxing, normal product photo without defects

Mark NEGATIVE if you see:
- red X / cross / cancel / rejection stamp / "bad sign" graphic
- thumbs down, sad face, warning triangle, stop sign
- text overlays like BAD, FAKE, REJECT, SCAM, FAIL
- damaged/broken/cracked product, stain, wrong item, empty box

Critical rules:
- Thumbs UP = positive. Thumbs DOWN = negative.
- Red X / rejection graphic = negative.
- Do NOT mark thumbs-up as negative just because skin/orange tones appear.
PROMPT;

        $textPrompt = "Product: {$productName}\n"
            ."Customer rating: {$review->rating}/5\n"
            ."Review title: {$title}\n"
            ."Review body: {$body}\n"
            .'Does the PHOTO look positive or negative for moderation? Return JSON.';

        $raw = $this->llm->chatWithImages(
            config('market.model_vision', config('market.model_google_search', 'gpt-4o-mini')),
            $systemPrompt,
            $textPrompt,
            $dataUris,
            jsonMode: true,
            temperature: 0.0,
        );

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            // Some providers wrap JSON in markdown fences.
            if (is_string($raw) && preg_match('/\{.*\}/s', $raw, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid review vision JSON response');
        }

        $sentiment = strtolower(trim((string) ($decoded['sentiment'] ?? '')));
        if (! in_array($sentiment, ['positive', 'negative'], true)) {
            throw new \RuntimeException('Vision sentiment missing');
        }

        return [
            'sentiment' => $sentiment,
            'reason' => trim((string) ($decoded['reason'] ?? '')),
        ];
    }

    /**
     * Local symbol hints without LLM.
     *
     * @return array{sentiment: string, reason: string, confidence: string}|null
     */
    private function detectSymbolicImageHint(ProductReview $review): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        foreach (array_slice($review->images ?? [], 0, 3) as $path) {
            if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            try {
                $binary = Storage::disk('public')->get($path);
                if ($binary === null || $binary === '') {
                    continue;
                }

                $img = @imagecreatefromstring($binary);
                if ($img === false) {
                    continue;
                }

                $w = imagesx($img);
                $h = imagesy($img);
                if ($w < 8 || $h < 8) {
                    imagedestroy($img);

                    continue;
                }

                $stats = $this->sampleImageColorStats($img, $w, $h);
                imagedestroy($img);

                $isCompact = ($w * $h) <= 400_000;

                // Strong red-X only: both diagonals heavily red (not "any red pixels").
                if (
                    $isCompact
                    && $stats['diagonal_red_score'] >= 0.45
                    && $stats['saturated_red_ratio'] >= 0.05
                    && $stats['green_ratio'] < 0.12
                ) {
                    return [
                        'sentiment' => 'negative',
                        'reason' => 'Detected red X / cross mark in review photo',
                        'confidence' => 'strong',
                    ];
                }

                // Green-dominant compact icon → thumbs-up / OK style.
                if (
                    $isCompact
                    && $stats['green_ratio'] >= 0.12
                    && $stats['green_ratio'] > ($stats['saturated_red_ratio'] + 0.04)
                    && $stats['diagonal_red_score'] < 0.30
                ) {
                    return [
                        'sentiment' => 'positive',
                        'reason' => 'Detected green thumbs-up / OK style mark in review photo',
                        'confidence' => 'strong',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('ReviewSentimentAnalyzer: local image scan failed', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @return array{
     *     red_ratio: float,
     *     saturated_red_ratio: float,
     *     green_ratio: float,
     *     diagonal_red_score: float
     * }
     */
    private function sampleImageColorStats(\GdImage $img, int $w, int $h): array
    {
        $stepX = max(1, (int) floor($w / 64));
        $stepY = max(1, (int) floor($h / 64));
        $total = 0;
        $redish = 0;
        $saturatedRed = 0;
        $greenish = 0;
        $diagHits = 0;
        $diagChecked = 0;

        for ($y = 0; $y < $h; $y += $stepY) {
            for ($x = 0; $x < $w; $x += $stepX) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $total++;

                $isRed = $r >= 150 && $r > ($g + 40) && $r > ($b + 40);
                if ($isRed) {
                    $redish++;
                }
                if ($r >= 180 && $g <= 120 && $b <= 120 && ($r - max($g, $b)) >= 50) {
                    $saturatedRed++;
                }
                if ($g >= 120 && $g > ($r + 25) && $g > ($b + 15)) {
                    $greenish++;
                }

                // Main diagonal + anti-diagonal band (±3% of size).
                $tol = max(2, (int) round(min($w, $h) * 0.03));
                $onMain = abs(($x * ($h - 1)) - ($y * ($w - 1))) <= ($tol * max($w, $h));
                $onAnti = abs(($x * ($h - 1)) + ($y * ($w - 1)) - (($w - 1) * ($h - 1))) <= ($tol * max($w, $h));
                if ($onMain || $onAnti) {
                    $diagChecked++;
                    if ($isRed) {
                        $diagHits++;
                    }
                }
            }
        }

        return [
            'red_ratio' => $total > 0 ? $redish / $total : 0.0,
            'saturated_red_ratio' => $total > 0 ? $saturatedRed / $total : 0.0,
            'green_ratio' => $total > 0 ? $greenish / $total : 0.0,
            'diagonal_red_score' => $diagChecked > 0 ? $diagHits / $diagChecked : 0.0,
        ];
    }

    private function reviewHasImages(ProductReview $review): bool
    {
        foreach ($review->images ?? [] as $path) {
            if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function encodeReviewImages(ProductReview $review): array
    {
        $uris = [];

        foreach (array_slice($review->images ?? [], 0, 3) as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            try {
                if (! Storage::disk('public')->exists($path)) {
                    continue;
                }

                $binary = Storage::disk('public')->get($path);
                if ($binary === null || $binary === '') {
                    continue;
                }

                $mime = $this->mimeFromPath($path);
                $uris[] = 'data:'.$mime.';base64,'.base64_encode($binary);
            } catch (\Throwable $e) {
                Log::warning('ReviewSentimentAnalyzer: could not read image', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $uris;
    }

    private function mimeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/jpeg',
        };
    }
}
