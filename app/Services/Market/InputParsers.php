<?php

namespace App\Services\Market;

class InputParsers
{
    public function parseCategory(string $text): ?string
    {
        $lower = strtolower(trim($text));
        $cats = array_keys(config('market.categories'));

        if (preg_match('/^(\d+)$/', $lower, $m)) {
            $idx = (int) $m[1] - 1;

            return $cats[$idx] ?? null;
        }

        foreach (config('market.categories') as $key => $info) {
            if (str_contains($lower, $key)
                || str_contains($lower, strtolower($info['label']))
                || str_contains($text, $info['label_bn'])) {
                return $key;
            }
        }

        return null;
    }

    public function parseBudget(string $text): array
    {
        $lower = strtolower($text);
        if (preg_match('/\b(\d[\dk,]*)\s*(?:-|–|to|থেকে|theke)\s*(\d[\dk,]*)\s*(?:tk|taka|bdt)?/i', $lower, $m)) {
            return [$this->parseNum($m[1]), $this->parseNum($m[2])];
        }
        if (preg_match('/\b(\d[\dk,]+)\s*(?:tk|taka|bdt)\b/i', $lower, $m)) {
            $n = $this->parseNum($m[1]);

            return [null, $n];
        }
        if (preg_match('/\bunder\s+(\d[\dk,]+)/i', $lower, $m)) {
            return [null, $this->parseNum($m[1])];
        }

        return [null, null];
    }

    private function parseNum(string $raw): int
    {
        return (int) str_replace([',', 'k'], ['', '000'], strtolower($raw));
    }

    public function formatBudgetLabel(?int $min, ?int $max): string
    {
        if ($min && $max) {
            return '৳'.number_format($min).' – ৳'.number_format($max);
        }
        if ($max) {
            return 'সর্বোচ্চ ৳'.number_format($max);
        }
        if ($min) {
            return '৳'.number_format($min).'+';
        }

        return 'Unlimited';
    }

    public function categoryPrompt(): string
    {
        $lines = ["📂 **Category** বেছে নিন:\n"];
        $i = 1;
        foreach (config('market.categories') as $key => $info) {
            $lines[] = "{$i}. **{$info['label_bn']}** (`{$key}`)";
            $i++;
        }

        return implode("\n", $lines);
    }

    public function budgetPrompt(): string
    {
        return "💰 **Budget** লিখুন (BDT):\n• `500-600 tk`\n• `under 1000`\n• `unlimited`";
    }
}
