<?php

namespace App\Services\Market;

class CompositeQuery
{
    public function __construct(
        public string $raw,
        public string $question,
        public ?string $category,
        public ?int $budgetMin,
        public ?int $budgetMax,
        public ?GooglePeriod $period,
        public int $topN,
        public bool $isTrending,
        public ?string $audience = null,
    ) {}

    public function isComplete(): bool
    {
        return $this->isTrending && $this->category && $this->period;
    }
}

class CompositeQueryParser
{
    private const TRENDING_KW = [
        'kon product', 'ki product', 'which product', 'trending', 'tranding',
        'ki beshi', 'beshi bikri', 'best selling', 'demand', 'popular',
        'কোন প্রোডাক্ট', 'কি প্রোডাক্ট', 'ট্রেন্ডিং',
    ];

    public function __construct(
        private InputParsers $inputParsers,
        private GooglePeriodParser $periodParser,
    ) {}

    public function parse(string $message): CompositeQuery
    {
        $text = trim($message);
        [$isTrending, $topN] = $this->detectTrending($text);
        $category = $this->parseCategoryFromMessage($text);
        [$budgetMin, $budgetMax] = $this->inputParsers->parseBudget($text);
        $period = $this->periodParser->parse($text);
        $audience = $this->parseAudience($text);

        return new CompositeQuery(
            raw: $text,
            question: $text,
            category: $category,
            budgetMin: $budgetMin,
            budgetMax: $budgetMax,
            period: $period,
            topN: $topN,
            isTrending: $isTrending,
            audience: $audience,
        );
    }

    private function detectTrending(string $text): array
    {
        $lower = strtolower($text);
        if (preg_match('/\btop\s*(\d{1,2})\b/i', $text, $m)) {
            return [true, max(3, min(10, (int) $m[1]))];
        }
        foreach (self::TRENDING_KW as $kw) {
            if (str_contains($lower, $kw)) {
                return [true, config('market.default_top_n')];
            }
        }
        $cat = $this->parseCategoryFromMessage($text);
        $period = $this->periodParser->parse($text);
        [$bmin, $bmax] = $this->inputParsers->parseBudget($text);
        if ($cat && ($period || $bmin || $bmax)) {
            return [true, config('market.default_top_n')];
        }

        return [false, config('market.default_top_n')];
    }

    private function parseCategoryFromMessage(string $text): ?string
    {
        $stripped = preg_replace('/\b(catagory|category|ক্যাটাগরি|cat)\s*[:\-]?\s*/iu', '', $text);

        return $this->inputParsers->parseCategory($stripped ?? $text)
            ?? $this->inputParsers->parseCategory($text);
    }

    private function parseAudience(string $text): ?string
    {
        $lower = strtolower($text);

        if (preg_match('/\b(female|women\'?s?|ladies|girls?|woman|মেয়ে|মহিলা|নারী)\b/ui', $lower)) {
            return 'women';
        }
        if (preg_match('/\b(male|men\'?s?|boys?|man|পুরুষ|ছেলে)\b/ui', $lower)) {
            return 'men';
        }

        return null;
    }
}
