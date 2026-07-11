<?php

namespace App\Services\Market;

class QueryIntent
{
    public const PLATFORM_SEARCH = 'platform_search';

    public const MARKET_ANALYSIS = 'market_analysis';

    public const GENERAL_QA = 'general_qa';

    public const PRODUCT_CREATE = 'product_create';
}

class QueryIntentClassifier
{
    private const ANALYSIS_KW = [
        'trending', 'tranding', 'demand', 'market', 'analysis', 'analyze', 'analyse',
        'compare', 'bikri', 'popular', 'research', 'forecast', 'চাহিদা', 'বাজার',
        'ট্রেন্ডিং', 'kon product', 'ki product', 'best selling', 'beshi bikri',
        'কোন প্রোডাক্ট', 'কি প্রোডাক্ট',
    ];

    private const QUESTION_STARTERS = [
        'what is', 'what are', 'who is', 'who are', 'when was', 'when did', 'when is',
        'where is', 'where are', 'why is', 'why do', 'why does', 'how many', 'how much',
        'how old', 'how does', 'how do', 'define', 'explain', 'tell me about',
        'capital of', 'meaning of', 'কি', 'কে', 'কখন', 'কোথায়', 'কেন', 'কেমন',
    ];

    private const CREATE_KW = [
        'create product', 'add product', 'new product', 'product create', 'product add',
        'প্রোডাক্ট তৈরি', 'পণ্য তৈরি', 'নতুন প্রোডাক্ট', 'product add koro', 'product create koro',
    ];

    public function isProductCreateIntent(string $message): bool
    {
        $lower = strtolower(trim($message));

        foreach (self::CREATE_KW as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return (bool) preg_match('/\b(create|add|তৈরি)\b.*\b(product|প্রোডাক্ট|পণ্য)\b/ui', $message);
    }

    public function classify(string $message, CompositeQuery $parsed): string
    {
        if ($this->isGeneralKnowledge($message, $parsed)) {
            return QueryIntent::GENERAL_QA;
        }

        if ($parsed->isTrending) {
            return QueryIntent::MARKET_ANALYSIS;
        }

        if ($parsed->category && ($parsed->period || $parsed->budgetMin !== null || $parsed->budgetMax !== null)) {
            return QueryIntent::MARKET_ANALYSIS;
        }

        $lower = strtolower(trim($message));

        if ($this->isProductCreateIntent($message)) {
            return QueryIntent::PRODUCT_CREATE;
        }

        if ($this->isConversationalPhrase($message)) {
            return QueryIntent::GENERAL_QA;
        }

        foreach (self::ANALYSIS_KW as $kw) {
            if (str_contains($lower, $kw)) {
                return QueryIntent::MARKET_ANALYSIS;
            }
        }

        if (preg_match('/\b(top\s*\d+|under\s+\d+|category\s+|june|january|february|march|april|may|july|august|september|october|november|december)\b/i', $message)) {
            return QueryIntent::MARKET_ANALYSIS;
        }

        $term = $this->extractProductTerm($message);
        if ($term !== '' && mb_strlen($term) >= 2 && mb_strlen($term) <= 80 && ! $this->looksLikeQuestion($term)) {
            return QueryIntent::PLATFORM_SEARCH;
        }

        return QueryIntent::GENERAL_QA;
    }

    public function isGeneralKnowledge(string $message, CompositeQuery $parsed): bool
    {
        if ($parsed->category || $parsed->period || $parsed->budgetMin !== null || $parsed->budgetMax !== null) {
            return false;
        }

        $lower = strtolower(trim($message));

        if ($this->looksLikeMarketQuery($lower)) {
            return false;
        }

        if (preg_match('/\?$/u', trim($message))) {
            return true;
        }

        foreach (self::QUESTION_STARTERS as $starter) {
            if (str_starts_with($lower, $starter.' ') || $lower === $starter) {
                return true;
            }
        }

        if (preg_match('/\b(what is|who is|when was|where is|why is|how many|how much|capital of)\b/i', $message)) {
            return true;
        }

        return false;
    }

    public function shouldShowProductCards(string $message, CompositeQuery $parsed, string $intent): bool
    {
        if ($intent === QueryIntent::PLATFORM_SEARCH) {
            return true;
        }

        if ($this->isGeneralKnowledge($message, $parsed)) {
            return false;
        }

        if ($intent === QueryIntent::MARKET_ANALYSIS) {
            return $parsed->isComplete();
        }

        $lower = strtolower(trim($message));

        return (bool) preg_match(
            '/\b(buy|price|under\s+\d+|cheapest|best\s+\w+\s+for|show me|find|search|daraz|pickaboo)\b/i',
            $lower,
        );
    }

    private function looksLikeQuestion(string $text): bool
    {
        $lower = strtolower(trim($text));

        if (str_ends_with($lower, '?')) {
            return true;
        }

        foreach (self::QUESTION_STARTERS as $starter) {
            if (str_starts_with($lower, $starter.' ') || $lower === $starter) {
                return true;
            }
        }

        return (bool) preg_match('/\b(what|who|when|where|why|how)\s+(is|are|was|were|do|does|did|many|much)\b/i', $lower);
    }

    public function isConversationalPhrase(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = rtrim($normalized, '!?.।');
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        if ($normalized === '' || mb_strlen($normalized) > 50) {
            return false;
        }

        if (preg_match('/\b(help|cancel|cart|order|checkout|trending|create\s+product|view\s+cart)\b/ui', $normalized)) {
            return false;
        }

        if (preg_match('/^(hi+|hy+|hello+|hey+|halo+|helo+|hola+|salam+|salaam+)$/ui', $normalized)) {
            return true;
        }

        if (preg_match('/\bkemon\s+(a[csz]o|acho|achen|achis|achhen|aso|achho|achis)\b/ui', $normalized)) {
            return true;
        }

        if (preg_match('/\b(ki\s+obostha|ki\s+obosta|ki\s+khobor|ki\s+khabar|ki\s+korcho|ki\s+koro|apni\s+kemon|tumi\s+kemon|kemon\s+acho|kemon\s+achen)\b/ui', $normalized)) {
            return true;
        }

        if (preg_match('/(হ্যালো|হাই|নমস্কার|আসসালাম|ধন্যবাদ|কেমন\s+আছ)/u', $message)) {
            return true;
        }

        if (preg_match('/\b(good\s+(morning|evening|afternoon|night)|thank\s*you|thanks|assalamu?\s+alaikum)\b/ui', $normalized)) {
            return true;
        }

        if (mb_strlen($normalized) <= 20 && preg_match('/^(accha|acha|ok|okay|bhalo|valo|nice|great)$/ui', $normalized)) {
            return true;
        }

        return false;
    }

    private function looksLikeMarketQuery(string $lower): bool
    {
        foreach (self::ANALYSIS_KW as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return (bool) preg_match('/\b(under\s+\d+|tk\b|taka|bdt|daraz|pickaboo|shajgoj|price|buy|bikri|selling)\b/i', $lower);
    }

    public function extractProductTerm(string $message): string
    {
        $text = trim($message);
        $text = preg_replace('/^(show|filter|find|search|get|list|dekhao|dekhaw|khujun|khuje\s*dao)\s+/iu', '', $text) ?? $text;
        $text = preg_replace('/\s+on\s+(daraz|shipnest|pickaboo|shajgoj).*$/i', '', $text) ?? $text;
        $text = preg_replace('/\s+in\s+bangladesh.*$/i', '', $text) ?? $text;
        $text = preg_replace('/\s+with\s+.+$/i', '', $text) ?? $text;
        $text = preg_replace('/\s+trending.*$/i', '', $text) ?? $text;
        $text = preg_replace('/\s+under\s+[\d,]+\s*(tk|taka|bdt)?.*$/i', '', $text) ?? $text;
        $text = preg_replace('/\s*(ache|ase|আছে|কি|ki|\?)+$/iu', '', $text) ?? $text;

        return trim($text, " \t\n\r\0\x0B?।");
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    public function parsePriceFilter(string $message): array
    {
        $lower = strtolower($message);
        if (preg_match('/\bunder\s+([\d,]+)\s*(tk|taka|bdt)?/i', $lower, $m)) {
            return [null, (float) str_replace(',', '', $m[1])];
        }
        if (preg_match('/\b(\d[\d,]*)\s*(?:-|–|to)\s*(\d[\d,]*)\s*(tk|taka|bdt)?/i', $lower, $m)) {
            return [
                (float) str_replace(',', '', $m[1]),
                (float) str_replace(',', '', $m[2]),
            ];
        }

        return [null, null];
    }

    public function intentLabel(string $intent): string
    {
        return match ($intent) {
            QueryIntent::PLATFORM_SEARCH => 'Product search (ShipNest catalog)',
            QueryIntent::MARKET_ANALYSIS => 'Market analysis / trending research',
            QueryIntent::PRODUCT_CREATE => 'Admin product creation',
            QueryIntent::GENERAL_QA => 'General Q&A',
            default => 'General Q&A',
        };
    }
}
