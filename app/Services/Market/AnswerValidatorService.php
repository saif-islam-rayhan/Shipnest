<?php

namespace App\Services\Market;

class AnswerValidatorService
{
    private const PROMPT = <<<'PROMPT'
You are a QA validator for BD Market Analyzer (Bangladesh e-commerce).
Compare the USER QUESTION with the Google AI Mode answer, search results, and generated answer.

Return ONLY valid JSON:
{
  "is_satisfactory": true,
  "score": 0.85,
  "issues": [],
  "suggested_search_queries": [],
  "needs_clarification": false,
  "clarifying_question": "",
  "clarifying_question_bn": "",
  "reason_bn": ""
}

Rules:
- The Google AI Mode answer is the PRIMARY source of truth — verify the generated answer reflects it accurately.
- Set is_satisfactory=true only when score >= 0.75 AND the answer directly addresses the user's question (month, category, budget).
- If the AI Mode answer lists different products than the generated answer, set is_satisfactory=false.
- If search results mention a DIFFERENT month/season than requested, set needs_clarification=true.
- If results include irrelevant categories or budget mismatches, set needs_clarification=true with a specific clarifying_question for Google AI Mode follow-up.
- clarifying_question must be a direct English question to refine the search (e.g. "What are the top 5 trending fashion products in Bangladesh for June 2026 under 1000 BDT on Daraz?").
- clarifying_question_bn is the same question in Bangla mixed with English for the user.
- suggested_search_queries: alternative English search queries for Google AI Mode (max 2).
PROMPT;

    public function __construct(
        private LlmClient $llm,
        private SearchHelpers $helpers,
    ) {}

    public function validateTrending(string $question, array $searchData, array $products, string $answerBody): array
    {
        $period = $searchData['period'] ?? [];
        $bmin = $searchData['budget_min'] ?? null;
        $bmax = $searchData['budget_max'] ?? null;
        $budgetStr = ($bmin && $bmax) ? '৳'.number_format($bmin).' – ৳'.number_format($bmax) : '';

        $productLines = collect($products)->take(8)->map(fn ($p) => '- '.($p['product'] ?? '?'))->implode("\n");

        $aiAnswer = trim($searchData['ai_answer'] ?? '');
        $aiSection = $aiAnswer ? "\n\nGOOGLE AI MODE ANSWER:\n".mb_substr($aiAnswer, 0, 2500) : '';

        $userPrompt = "USER QUESTION: {$question}\n"
            .'Category: '.($searchData['category_label'] ?? '')."\n"
            .'Period: '.($period['label_bn'] ?? $period['label'] ?? '')."\n"
            ."Budget: {$budgetStr}\n"
            .'Search source: '.($searchData['search_source'] ?? 'web')."\n"
            ."Products:\n{$productLines}\n\nANSWER:\n".mb_substr($answerBody, 0, 2000)
            ."\n\nSNIPPETS:\n".$this->helpers->compactResultsJson($searchData['results'] ?? [])
            .$aiSection;

        return $this->run($userPrompt);
    }

    public function validateQa(string $question, array $searchData, string $answerBody): array
    {
        $aiAnswer = trim($searchData['ai_answer'] ?? '');
        $aiSection = $aiAnswer ? "\n\nGOOGLE AI MODE ANSWER:\n".mb_substr($aiAnswer, 0, 2000) : '';

        $userPrompt = "USER QUESTION: {$question}\n\nANSWER:\n".mb_substr($answerBody, 0, 2000)
            ."\n\nSNIPPETS:\n".$this->helpers->compactResultsJson($searchData['results'] ?? [])
            .$aiSection;

        return $this->run($userPrompt);
    }

    private function run(string $userPrompt): array
    {
        try {
            $raw = $this->llm->chat(
                config('market.model_google_search'),
                self::PROMPT,
                $userPrompt,
                jsonMode: true,
                temperature: 0.1,
            );
            $parsed = json_decode($raw, true);
            if (! is_array($parsed)) {
                return $this->defaultPass();
            }

            return [
                'is_satisfactory' => (bool) ($parsed['is_satisfactory'] ?? false),
                'score' => (float) ($parsed['score'] ?? 0),
                'issues' => $parsed['issues'] ?? [],
                'suggested_search_queries' => array_slice($parsed['suggested_search_queries'] ?? [], 0, 3),
                'needs_clarification' => (bool) ($parsed['needs_clarification'] ?? false),
                'clarifying_question' => trim($parsed['clarifying_question'] ?? ''),
                'clarifying_question_bn' => trim($parsed['clarifying_question_bn'] ?? ''),
                'reason_bn' => $parsed['reason_bn'] ?? '',
            ];
        } catch (\Throwable) {
            return $this->defaultPass();
        }
    }

    private function defaultPass(): array
    {
        return [
            'is_satisfactory' => true,
            'score' => 1.0,
            'issues' => [],
            'suggested_search_queries' => [],
            'needs_clarification' => false,
            'clarifying_question' => '',
            'clarifying_question_bn' => '',
            'reason_bn' => '',
        ];
    }
}
