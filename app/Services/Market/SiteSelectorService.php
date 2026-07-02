<?php

namespace App\Services\Market;

class SiteSelectorService
{
    private const SELECT_PROMPT = <<<'PROMPT'
You are a Bangladesh e-commerce site selector.
Given a product category, pick the best e-commerce/marketplace sites for trending product research in Bangladesh.
Return ONLY valid JSON:
{"sites": ["daraz", "pickaboo", "shajgoj", "othoba", "bikroy"]}
Use site keys from the allowed list only. Return exactly up to 5 sites, most relevant first.
PROMPT;

    public function __construct(private LlmClient $llm) {}

    /**
     * @return array<int, string> site keys e.g. ['daraz','shajgoj',...]
     */
    public function selectForCategory(?string $category, int $count = 5): array
    {
        $count = max(1, min(5, $count));
        $allowed = $this->allowedSitesForCategory($category);

        if (config('market.use_live_llm') && $category) {
            $llmSites = $this->selectViaLlm($category, $allowed, $count);
            if (count($llmSites) >= min(3, $count)) {
                return array_slice($llmSites, 0, $count);
            }
        }

        return array_slice($allowed, 0, $count);
    }

    public function siteInfo(string $siteKey): ?array
    {
        $info = config("market.site_registry.{$siteKey}");
        if (! $info) {
            return null;
        }

        return array_merge(['key' => $siteKey], $info);
    }

    public function searchUrl(string $siteKey, string $query): string
    {
        $info = $this->siteInfo($siteKey);
        $template = $info['search_url'] ?? 'https://www.google.com/search?q={query}';

        return str_replace('{query}', urlencode($query), $template);
    }

    public function detectSiteFromUrl(string $url): string
    {
        $lower = strtolower($url);
        foreach (config('market.site_registry', []) as $key => $info) {
            if (str_contains($lower, $info['domain'] ?? '')) {
                return $info['label'] ?? ucfirst($key);
            }
        }

        return 'Web';
    }

    /**
     * @return array<int, string>
     */
    private function allowedSitesForCategory(?string $category): array
    {
        $categorySites = $category
            ? (config("market.categories.{$category}.sites") ?? [])
            : [];

        $registry = array_keys(config('market.site_registry', []));
        $merged = array_values(array_unique(array_merge($categorySites, $registry)));

        return array_values(array_filter($merged, fn ($k) => isset(config('market.site_registry')[$k])));
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array<int, string>
     */
    private function selectViaLlm(string $category, array $allowed, int $count): array
    {
        try {
            $catLabel = config("market.categories.{$category}.label_bn")
                ?? config("market.categories.{$category}.label", $category);
            $allowedList = implode(', ', $allowed);

            $userPrompt = "Category: {$catLabel} ({$category})\n"
                ."Allowed site keys: {$allowedList}\n"
                ."Pick exactly {$count} best sites for trending {$category} products in Bangladesh.";

            $raw = $this->llm->chat(
                config('market.model_google_search'),
                self::SELECT_PROMPT,
                $userPrompt,
                jsonMode: true,
                temperature: 0.1,
            );

            $parsed = json_decode($raw, true);
            $sites = $parsed['sites'] ?? [];
            if (! is_array($sites)) {
                return [];
            }

            $valid = [];
            foreach ($sites as $site) {
                $key = strtolower(trim((string) $site));
                if (in_array($key, $allowed, true) && ! in_array($key, $valid, true)) {
                    $valid[] = $key;
                }
            }

            return $valid;
        } catch (\Throwable) {
            return [];
        }
    }
}
